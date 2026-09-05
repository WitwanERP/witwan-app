<?php

namespace App\Services\Vigencias;

use App\Exceptions\Productos\VigenciaException;
use App\Services\Pricing\ScopeResolver;
use App\Services\Productos\HabitacionService;
use App\Services\Productos\ProductoService;
use App\Support\Productos\DiasSemana;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Vigencias (períodos tarifarios) y sus tarifas.
 *
 * Reemplaza a `Vigencia::save()` (vigencia.php:159-517). Corrige, por diseño:
 *   B1 celda vaciada → se borra sólo esa celda (TarifaUpsert por clave única);
 *   B2 el id nuevo sale siempre del insert, nunca del POST;
 *   B3 UPDATE sobre la fila existente en vez de REPLACE (no se pierden clase,
 *      infoadicional, promocional, cotizacionespecial, fk_tarifario_id...);
 *   B4 el bloque de tramos corre una vez;
 *   B5 `weekdays` y `rel_vigenciadia` se escriben desde el mismo array.
 * Todo en una transacción.
 */
class VigenciaService
{
    /** Columnas de `vigencia` que escribe el formulario (las demás no se tocan en edición). */
    private const COLUMNAS = [
        'fk_regimen_id', 'vigencia_promocional', 'vigencia_prioridad', 'acumulable', 'promo_noches', 'promo_pornoches',
        'vencimiento_reserva', 'vencimiento_checkin', 'vigencia_ini', 'vigencia_fin', 'vigencia_ventaini', 'vigencia_ventafin',
        'vencimiento_promocion', 'residente', 'cargamanual', 'vigencia_descripcion', 'nota_promocion', 'web', 'comentarios',
        'noches_minimas', 'modo_nochesminimas', 'noches', 'dias',
    ];

    private const FECHAS = ['vigencia_ini', 'vigencia_fin', 'vigencia_ventaini', 'vigencia_ventafin', 'vencimiento_promocion'];

    public function __construct(
        private ProductoService $productos,
        private HabitacionService $habitaciones,
        private BasesResolver $bases,
        private GrillaTarifas $grilla,
        private VigenciaReglas $reglas,
        private TarifaUpsert $tarifas,
        private ScopeResolver $scope,
    ) {}

    // ------------------------------------------------------------------
    // Consulta
    // ------------------------------------------------------------------

    /** Vigencias del producto con cantidad de tarifas, para la solapa "Vigencias" del form. */
    public function listar(int $productoId): array
    {
        return DB::table('vigencia')
            ->leftJoin('tarifa', 'tarifa.fk_vigencia_id', '=', 'vigencia.vigencia_id')
            ->where('vigencia.fk_producto_id', $productoId)
            ->groupBy('vigencia.vigencia_id')
            ->orderByDesc('vigencia.vigencia_ini')
            ->orderByDesc('vigencia.vigencia_id')
            ->select([
                'vigencia.vigencia_id', 'vigencia.vigencia_descripcion', 'vigencia.vigencia_ini', 'vigencia.vigencia_fin',
                'vigencia.vigencia_ventaini', 'vigencia.vigencia_ventafin', 'vigencia.vigencia_prioridad', 'vigencia.residente',
                'vigencia.cargamanual', 'vigencia.noches_minimas', DiasSemana::columnaSelect('weekdays_bits'),
                DB::raw('COUNT(tarifa.tarifa_id) AS tarifas'),
                DB::raw('MIN(tarifa.moneda_costo) AS moneda_costo'),
            ])
            ->get()
            ->map(function ($v) {
                $fila = (array) $v;
                $fila['dias_semana'] = DiasSemana::desdeBits($v->weekdays_bits);
                unset($fila['weekdays_bits']);

                return $fila;
            })
            ->all();
    }

    /**
     * Todo lo que necesita el formulario: producto, cabecera, alojamientos,
     * grilla, tarifarios (con divisor de markup resuelto) y catálogos.
     */
    public function paraFormulario(int $productoId, ?int $vigenciaId = null): ?array
    {
        $producto = $this->productos->cargar($productoId);
        if ($producto === null) {
            return null;
        }

        $cabecera = $vigenciaId !== null ? $this->cabecera($vigenciaId) : $this->cabeceraVacia($productoId);
        if ($cabecera === null || (int) $cabecera['fk_producto_id'] !== $productoId) {
            return null;
        }

        $tarifas = $vigenciaId !== null
            ? DB::table('tarifa')->where('fk_vigencia_id', $vigenciaId)->get()->map(fn ($t) => (array) $t)->all()
            : [];

        // vigenciabyid(): moneda/impuestos/redondeo se leen de las filas de costo.
        foreach ($tarifas as $t) {
            if ((int) $t['fk_tarifario_id'] === 0) {
                $cabecera['moneda_costo'] = $cabecera['moneda_costo'] ?: (string) $t['moneda_costo'];
                $cabecera['redondeo'] = (string) $t['redondear'];
                if (BasesResolver::esNumerica((string) $t['fk_base_id'])) {
                    $cabecera['impuestos'] = (float) $t['impuestos'];
                } else {
                    $cabecera['impuestos_menor'] = (float) $t['impuestos'];
                }
                if ((int) $t['fk_tarifacategoria_id'] !== 0) {
                    $cabecera['fk_tarifacategoria_id'] = (int) $t['fk_tarifacategoria_id'];
                }
            }
        }

        $tarifarios = $this->tarifarios($producto);
        $habitaciones = array_map(fn ($h) => [
            'fk_tarifacategoria_id' => (int) $h['fk_tarifacategoria_id'],
            'nombre' => $h['nombre'],
            'max_child' => (int) $h['max_child'],
        ], $this->habitaciones->listar($productoId, true));

        return [
            'producto' => $this->resumenProducto($producto),
            'vigencia' => $cabecera,
            'alojamientos' => $vigenciaId !== null ? $this->alojamientos($vigenciaId) : [],
            'grilla' => $this->grilla->armar($producto, $habitaciones, $tarifarios, $tarifas),
            'otras' => array_values(array_filter($this->listar($productoId), fn ($v) => (int) $v['vigencia_id'] !== (int) $vigenciaId)),
            'catalogos' => [
                'regimenes' => DB::table('regimen')->orderBy('regimen_nombre')->get(['regimen_id', 'regimen_nombre'])->map(fn ($r) => ['value' => (int) $r->regimen_id, 'label' => $r->regimen_nombre])->all(),
                'tarifacategorias' => DB::table('tarifacategoria')->where('fk_submodulo_id', $producto['fk_tipoproducto_id'])->orderBy('tarifacategoria_nombre')->get(['tarifacategoria_id', 'tarifacategoria_nombre'])->map(fn ($r) => ['value' => (int) $r->tarifacategoria_id, 'label' => $r->tarifacategoria_nombre])->all(),
                'redondeo' => config('productos.redondeo'),
                'residente' => config('productos.residente'),
                'modo_nochesminimas' => config('productos.modo_nochesminimas'),
                'dias' => config('productos.dias'),
            ],
        ];
    }

    public function cabecera(int $vigenciaId): ?array
    {
        $v = DB::table('vigencia')->where('vigencia_id', $vigenciaId)->select('vigencia.*', DiasSemana::columnaSelect('weekdays_bits'))->first();
        if ($v === null) {
            return null;
        }

        $fila = (array) $v;
        $fila['dias_semana'] = $this->diasDesdeBase($vigenciaId, $v->weekdays_bits);
        unset($fila['weekdays_bits'], $fila['weekdays']);
        foreach (self::FECHAS as $f) {
            $fila[$f] = $this->fechaSalida($fila[$f] ?? null);
        }
        $fila += ['moneda_costo' => '', 'impuestos' => 0, 'impuestos_menor' => 0, 'redondeo' => ' ', 'fk_tarifacategoria_id' => 0];

        return $fila;
    }

    // ------------------------------------------------------------------
    // Escritura
    // ------------------------------------------------------------------

    /**
     * Guarda cabecera, días, alojamientos y tarifas. Devuelve id, avisos y
     * estadísticas del upsert.
     *
     * @throws VigenciaException con errores por campo
     */
    public function guardar(int $productoId, array $datos, ?int $vigenciaId = null): array
    {
        $producto = $this->productos->cargar($productoId);
        if ($producto === null) {
            throw new VigenciaException("El producto {$productoId} no existe.");
        }
        if ($vigenciaId !== null) {
            $actual = DB::table('vigencia')->where('vigencia_id', $vigenciaId)->first();
            if ($actual === null || (int) $actual->fk_producto_id !== $productoId) {
                throw new VigenciaException("La vigencia {$vigenciaId} no pertenece al producto {$productoId}.");
            }
        }

        $datos = $this->normalizar($datos, $vigenciaId);
        $tarifarios = $this->tarifarios($producto);
        [$filas, $celdas, $erroresGrilla] = $this->filasDeTarifa($producto, $datos, $tarifarios);

        $validacion = $this->reglas->validar($datos, $celdas, $this->listar($productoId));
        $errores = $validacion['errores'] + $erroresGrilla;
        if ($errores !== []) {
            throw VigenciaException::porCampos($errores);
        }

        $stats = DB::transaction(function () use ($datos, $vigenciaId, $productoId, $filas, &$id) {
            $fila = $this->filaVigencia($datos);
            $fila['weekdays'] = DiasSemana::valorParaGuardar($datos['dias_semana']);

            if ($vigenciaId === null) {
                $fila['fk_producto_id'] = $productoId;
                $fila += $this->defaultsInsert();
                $id = (int) DB::table('vigencia')->insertGetId($fila);
            } else {
                DB::table('vigencia')->where('vigencia_id', $vigenciaId)->update($fila);
                $id = $vigenciaId;
            }

            DB::table('rel_vigenciadia')->where('fk_vigencia_id', $id)->delete();
            foreach ($datos['dias_semana'] as $d) {
                DB::table('rel_vigenciadia')->insert(['fk_vigencia_id' => $id, 'fk_dia_id' => $d]);
            }

            if (array_key_exists('alojamientos', $datos)) {
                $this->sincronizarAlojamientos($id, $productoId, (array) $datos['alojamientos']);
            }

            return $this->tarifas->sincronizar($id, $filas);
        });

        return ['id' => $id, 'avisos' => $validacion['avisos'], 'tarifas' => $stats];
    }

    /**
     * Clona una vigencia con días, alojamientos y tarifas.
     *
     * @param  array{producto_id?:int, desplazar_dias?:int, desplazar_meses?:int, ajustar_pct?:float, descripcion?:string}  $opciones
     */
    public function clonar(int $vigenciaId, array $opciones = []): int
    {
        $origen = DB::table('vigencia')->where('vigencia_id', $vigenciaId)->first();
        if ($origen === null) {
            throw new VigenciaException("La vigencia {$vigenciaId} no existe.");
        }

        return DB::transaction(function () use ($origen, $vigenciaId, $opciones) {
            $fila = (array) $origen;
            unset($fila['vigencia_id']);
            $fila['fk_producto_id'] = (int) ($opciones['producto_id'] ?? $fila['fk_producto_id']);
            if (! empty($opciones['descripcion'])) {
                $fila['vigencia_descripcion'] = (string) $opciones['descripcion'];
            }

            $dias = (int) ($opciones['desplazar_dias'] ?? 0);
            $meses = (int) ($opciones['desplazar_meses'] ?? 0);
            if ($dias !== 0 || $meses !== 0) {
                foreach (self::FECHAS as $f) {
                    $fila[$f] = $this->desplazar($fila[$f] ?? null, $dias, $meses);
                }
            }

            // El bit(7) viaja como llegó de la base; en MySQL hay que reexpresarlo.
            $bits = $this->diasDesdeBase($vigenciaId, $fila['weekdays'] ?? null);
            $fila['weekdays'] = DiasSemana::valorParaGuardar($bits);

            $nuevo = (int) DB::table('vigencia')->insertGetId($fila);

            foreach ($bits as $d) {
                DB::table('rel_vigenciadia')->insert(['fk_vigencia_id' => $nuevo, 'fk_dia_id' => $d]);
            }
            foreach (DB::table('vigenciaalojamiento')->where('fk_vigencia_id', $vigenciaId)->get() as $va) {
                $va = (array) $va;
                unset($va['vigenciaalojamiento_id']);
                $va['fk_vigencia_id'] = $nuevo;
                DB::table('vigenciaalojamiento')->insert($va);
            }

            $this->tarifas->copiar($vigenciaId, $nuevo, (float) ($opciones['ajustar_pct'] ?? 0));

            return $nuevo;
        });
    }

    public function eliminar(int $vigenciaId): void
    {
        DB::transaction(function () use ($vigenciaId) {
            $this->tarifas->borrarTodas($vigenciaId);
            DB::table('rel_vigenciadia')->where('fk_vigencia_id', $vigenciaId)->delete();
            DB::table('vigenciaalojamiento')->where('fk_vigencia_id', $vigenciaId)->delete();
            DB::table('vigencia')->where('vigencia_id', $vigenciaId)->delete();
        });
    }

    // ------------------------------------------------------------------
    // Tarifarios y filas de tarifa
    // ------------------------------------------------------------------

    /**
     * Tarifarios del sistema del producto (vigencia.php:44) con el divisor de
     * markup que aplica al producto (ScopeResolver, cascada única).
     */
    public function tarifarios(array $producto): array
    {
        $lista = DB::table('tarifario')
            ->where('fk_sistema_id', (int) $producto['fk_sistema_id'])
            ->where('interno', 0)
            ->orderBy('tarifario_nombre')
            ->get()
            ->map(fn ($t) => (array) $t)
            ->all();

        foreach ($lista as &$t) {
            $c = $this->scope->comision((int) $t['tarifario_id'], (int) $producto['producto_id'], (string) $producto['fk_tipoproducto_id'], (int) ($producto['fk_ciudad_id'] ?? 0), (int) ($producto['pais'] ?? 0));
            $t['divisor_markup'] = $c['divisor_markup'] ?? 0.0;
            $t['porcentaje_comision'] = $c['porcentaje_comision'] ?? 0.0;
        }

        return $lista;
    }

    /**
     * Convierte la grilla del payload en filas de `tarifa`. Devuelve también las
     * celdas planas (para VigenciaReglas) y errores de celdas fuera de la grilla.
     *
     * @return array{0: list<array>, 1: list<array>, 2: array<string,string>}
     */
    private function filasDeTarifa(array $producto, array $datos, array $tarifarios): array
    {
        $tipo = (string) $producto['fk_tipoproducto_id'];
        $cfg = (array) config("productos.tipos.{$tipo}", []);
        $manual = (int) ($datos['cargamanual'] ?? 0) === 1;
        $monedaTarifario = array_column($tarifarios, 'fk_moneda_id', 'tarifario_id');
        $comunes = ['moneda_costo' => $datos['moneda_costo'], 'redondear' => $datos['redondeo']];

        $filas = [];
        $celdas = [];
        $errores = [];

        if ($cfg['alojamiento'] ?? false) {
            $habitaciones = $this->habitaciones->listar((int) $producto['producto_id'], true);
            $basesPorCategoria = [];
            foreach ($habitaciones as $h) {
                $basesPorCategoria[(int) $h['fk_tarifacategoria_id']] = $this->bases->resolver($producto['bases'], $producto['edades'], (int) $h['max_child'], $tipo);
            }
            if ($basesPorCategoria === []) {
                $basesPorCategoria[0] = $this->bases->resolver($producto['bases'], $producto['edades'], 0, $tipo);
            }

            foreach ((array) ($datos['tarifas'] ?? []) as $i => $celda) {
                $categoria = (int) ($celda['categoria'] ?? 0);
                $base = (string) ($celda['base'] ?? '');
                $celdas[$i] = ['costo' => $celda['costo'] ?? null, 'venta' => (array) ($celda['venta'] ?? [])];

                if (! isset($basesPorCategoria[$categoria]) || ! in_array($base, $basesPorCategoria[$categoria], true)) {
                    $errores["tarifas.{$i}"] = "La celda categoría {$categoria} / base {$base} no pertenece a la grilla del producto.";

                    continue;
                }

                if ($this->tieneValor($celda['costo'] ?? null)) {
                    $filas[] = $comunes + [
                        'fk_tarifario_id' => 0,
                        'fk_tarifacategoria_id' => $categoria,
                        'fk_base_id' => $base,
                        'costo' => (float) $celda['costo'],
                        'impuestos' => BasesResolver::esNumerica($base) ? (float) $datos['impuestos'] : (float) $datos['impuestos_menor'],
                    ];
                }
                if ($manual) {
                    foreach ((array) ($celda['venta'] ?? []) as $tid => $valor) {
                        if ($this->tieneValor($valor) && isset($monedaTarifario[(int) $tid])) {
                            $filas[] = [
                                'fk_tarifario_id' => (int) $tid,
                                'fk_tarifacategoria_id' => $categoria,
                                'fk_base_id' => $base,
                                'costo' => (float) $valor,
                                'moneda_costo' => (string) $monedaTarifario[(int) $tid],
                                'redondear' => $datos['redondeo'],
                            ];
                        }
                    }
                }
            }

            return [$filas, $celdas, $errores];
        }

        $tiposPax = (array) ($cfg['tipos_pax'] ?? ['ADU', 'CHD', 'INF']);
        $categoria = (int) ($datos['fk_tarifacategoria_id'] ?? 0);

        foreach ((array) ($datos['tramos'] ?? []) as $i => $tramo) {
            $min = (int) ($tramo['min'] ?? 0);
            $max = (int) ($tramo['max'] ?? 0);
            if ($min < 0 || $max < $min) {
                $errores["tramos.{$i}"] = 'El tramo debe tener mínimo ≤ máximo de pax.';

                continue;
            }
            foreach ($tiposPax as $pax) {
                $costo = $tramo['costos'][$pax] ?? null;
                $venta = (array) ($tramo['venta'] ?? []);
                $ventaPax = [];
                foreach ($venta as $tid => $porPax) {
                    $ventaPax[$tid] = $porPax[$pax] ?? null;
                }
                $celdas["{$i}.{$pax}"] = ['costo' => $costo, 'venta' => $ventaPax];

                if ($this->tieneValor($costo)) {
                    $filas[] = $comunes + [
                        'fk_tarifario_id' => 0,
                        'fk_tarifacategoria_id' => $categoria,
                        'min_pax' => $min,
                        'max_pax' => $max,
                        'fk_tipopax_id' => $pax,
                        'costo' => (float) $costo,
                        'impuestos' => (float) $datos['impuestos'],
                    ];
                }
                if ($manual) {
                    foreach ($ventaPax as $tid => $valor) {
                        if ($this->tieneValor($valor) && isset($monedaTarifario[(int) $tid])) {
                            $filas[] = [
                                'fk_tarifario_id' => (int) $tid,
                                'fk_tarifacategoria_id' => $categoria,
                                'min_pax' => $min,
                                'max_pax' => $max,
                                'fk_tipopax_id' => $pax,
                                'costo' => (float) $valor,
                                'moneda_costo' => (string) $monedaTarifario[(int) $tid],
                                'redondear' => $datos['redondeo'],
                            ];
                        }
                    }
                }
            }
        }

        return [$filas, array_values($celdas), $errores];
    }

    // ------------------------------------------------------------------
    // Internos
    // ------------------------------------------------------------------

    private function normalizar(array $datos, ?int $vigenciaId): array
    {
        $datos['vigencia_id'] = $vigenciaId ?? 0;
        $datos['dias_semana'] = DiasSemana::normalizar((array) ($datos['dias_semana'] ?? []));
        $datos['residente'] = (string) ($datos['residente'] ?? '');
        $datos['moneda_costo'] = trim((string) ($datos['moneda_costo'] ?? ''));
        $datos['redondeo'] = (string) ($datos['redondeo'] ?? ' ');
        if (! array_key_exists($datos['redondeo'], (array) config('productos.redondeo'))) {
            $datos['redondeo'] = ' ';
        }
        $datos['impuestos'] = (float) ($datos['impuestos'] ?? 0);
        $datos['impuestos_menor'] = (float) ($datos['impuestos_menor'] ?? 0);
        foreach (['cargamanual', 'web', 'vigencia_promocional', 'acumulable'] as $flag) {
            $datos[$flag] = (int) (bool) ($datos[$flag] ?? 0);
        }
        foreach (self::FECHAS as $f) {
            $datos[$f] = $this->fechaEntrada($datos[$f] ?? null);
        }

        return $datos;
    }

    private function filaVigencia(array $datos): array
    {
        $fila = [];
        foreach (self::COLUMNAS as $col) {
            if (! array_key_exists($col, $datos)) {
                continue;
            }
            $v = $datos[$col];
            $fila[$col] = match (true) {
                in_array($col, self::FECHAS, true) => $v === '' || $v === null ? '0000-00-00' : $v,
                in_array($col, ['residente', 'vigencia_descripcion', 'nota_promocion', 'comentarios', 'modo_nochesminimas'], true) => (string) ($v ?? ''),
                default => (int) $v,
            };
        }

        return $fila;
    }

    /** NOT NULL sin default de `vigencia` que el form no manda (sólo en el INSERT). */
    private function defaultsInsert(): array
    {
        return [
            'residente' => '', 'vigencia_ventaini' => '0000-00-00', 'vigencia_ventafin' => '0000-00-00', 'vigencia_descripcion' => '',
            'fk_regimen_id' => 0, 'fk_tarifario_id' => 0, 'vencimiento_checkin' => 0, 'vencimiento_reserva' => 0,
            'vencimiento_promocion' => '0000-00-00', 'nota_promocion' => '', 'clase' => '', 'weekdate' => '', 'noches_minimas' => 0,
            'modo_nochesminimas' => '', 'cargamanual' => 0, 'infoadicional' => '', 'promocional' => 0, 'promo_noches' => 0,
            'promo_pornoches' => 0, 'acumulable' => 0, 'comentarios' => '', 'comentarios_en' => '', 'comentarios_pt' => '',
            'web' => 0, 'dias' => 0, 'noches' => 0, 'cotizacionespecial' => 0, 'vigencia_prioridad' => 0, 'vigencia_promocional' => 0,
        ];
    }

    /** @param list<array> $alojamientos */
    private function sincronizarAlojamientos(int $vigenciaId, int $productoId, array $alojamientos): void
    {
        $existentes = DB::table('vigenciaalojamiento')->where('fk_vigencia_id', $vigenciaId)->pluck('vigenciaalojamiento_id')->map(fn ($i) => (int) $i)->all();
        $vistos = [];

        foreach ($alojamientos as $va) {
            $id = (int) ($va['vigenciaalojamiento_id'] ?? 0);
            $fila = [
                'fk_vigencia_id' => $vigenciaId,
                'fk_producto_id' => (int) ($va['fk_producto_id'] ?? 0),
                'fk_tarifacategoria_id' => (int) ($va['fk_tarifacategoria_id'] ?? 0),
                'fk_regimen_id' => (int) ($va['fk_regimen_id'] ?? 0),
                'noches' => (int) ($va['noches'] ?? 0),
                'ncategoria' => (string) ($va['ncategoria'] ?? ''),
            ];
            if ($id !== 0 && in_array($id, $existentes, true)) {
                DB::table('vigenciaalojamiento')->where('vigenciaalojamiento_id', $id)->update($fila);
                $vistos[] = $id;
            } else {
                $vistos[] = (int) DB::table('vigenciaalojamiento')->insertGetId($fila);
            }
        }

        foreach (array_diff($existentes, $vistos) as $id) {
            DB::table('vigenciaalojamiento')->where('vigenciaalojamiento_id', $id)->delete();
        }
    }

    private function alojamientos(int $vigenciaId): array
    {
        return DB::table('vigenciaalojamiento as va')
            ->leftJoin('producto as p', 'p.producto_id', '=', 'va.fk_producto_id')
            ->where('va.fk_vigencia_id', $vigenciaId)
            ->orderBy('va.vigenciaalojamiento_id')
            ->get(['va.*', 'p.producto_nombre'])
            ->map(fn ($r) => (array) $r)
            ->all();
    }

    /**
     * Días de la vigencia. Prioridad como el form del CI (producto_model.php:648):
     * si hay filas en rel_vigenciadia mandan; si no, los bits.
     */
    private function diasDesdeBase(int $vigenciaId, int|string|null $bits): array
    {
        $rel = DB::table('rel_vigenciadia')->where('fk_vigencia_id', $vigenciaId)->pluck('fk_dia_id')->map(fn ($d) => (int) $d)->all();

        return $rel !== [] ? DiasSemana::normalizar($rel) : DiasSemana::desdeBits($bits);
    }

    private function cabeceraVacia(int $productoId): array
    {
        return $this->defaultsInsert() + [
            'vigencia_id' => 0, 'fk_producto_id' => $productoId, 'vigencia_ini' => '', 'vigencia_fin' => '',
            'dias_semana' => DiasSemana::TODOS, 'moneda_costo' => '', 'impuestos' => 0, 'impuestos_menor' => 0,
            'redondeo' => ' ', 'fk_tarifacategoria_id' => 0,
        ] + array_map(fn () => '', array_flip(self::FECHAS));
    }

    private function resumenProducto(array $p): array
    {
        return [
            'producto_id' => (int) $p['producto_id'],
            'producto_nombre' => (string) $p['producto_nombre'],
            'fk_tipoproducto_id' => (string) $p['fk_tipoproducto_id'],
            'fk_sistema_id' => (int) $p['fk_sistema_id'],
            'modotarifa' => (string) $p['modotarifa'],
            'bases' => $p['bases'],
            'edades' => $p['edades'],
            'fk_ciudad_id' => (int) $p['fk_ciudad_id'],
            'pais' => (int) $p['pais'],
            'ciudades' => $p['ciudades'],
        ];
    }

    private function tieneValor(mixed $v): bool
    {
        return $v !== null && $v !== '' && is_numeric($v);
    }

    private function fechaEntrada(mixed $v): string
    {
        $v = trim((string) $v);
        if ($v === '' || $v === '0000-00-00') {
            return '';
        }
        if (preg_match('#^(\d{2})/(\d{2})/(\d{4})$#', $v, $m)) {
            return "{$m[3]}-{$m[2]}-{$m[1]}";
        }

        return substr($v, 0, 10);
    }

    private function fechaSalida(mixed $v): string
    {
        $v = substr(trim((string) $v), 0, 10);

        return $v === '0000-00-00' ? '' : $v;
    }

    private function desplazar(mixed $fecha, int $dias, int $meses): string
    {
        $f = $this->fechaSalida($fecha);
        if ($f === '') {
            return '0000-00-00';
        }

        return Carbon::createFromFormat('Y-m-d', $f)->addMonths($meses)->addDays($dias)->format('Y-m-d');
    }
}
