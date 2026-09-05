<?php

namespace App\Services\Productos;

use App\Exceptions\Productos\ProductoException;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

/**
 * Alta, edición, clonado, baja y consulta de productos de cualquier tipo.
 *
 * Reemplaza a los 18 controladores `productos/*.php` del CI (uno por tipo,
 * copias literales) y a su `save()` (hotel.php:129-306), que concatenaba el
 * SQL con addslashes, borraba y reinsertaba las relaciones sin transacción y
 * usaba REPLACE sobre un EAV sin clave única.
 *
 * Diferencias con el legacy, todas a propósito:
 *  - una sola transacción por guardado;
 *  - extras y relaciones se escriben por diferencia (ProductoExtraService);
 *  - `clonar()` copia de verdad (el `copy()` del CI sólo precargaba el form);
 *  - la baja es lógica (`producto.eliminar = 1`, que el tarifario del CI ya
 *    filtra: tarifario.php:692-720) en vez del DELETE en cascada incompleto.
 */
class ProductoService
{
    public const FILTROS = ['producto_id', 'producto_nombre', 'fk_proveedor_id', 'fk_ciudad_id', 'habilitar', 'aparece_tarifario'];

    public function __construct(
        private ProductoFormulario $formulario,
        private ProductoReglas $reglas,
        private ProductoExtraService $extras,
        private HabitacionService $habitaciones,
    ) {}

    // ------------------------------------------------------------------
    // Consulta
    // ------------------------------------------------------------------

    /**
     * Listado fiel al `_query` de hotel.php:15 (proveedor y ciudad por LEFT JOIN,
     * agrupado por producto) con los filtros de `_fields`.
     */
    public function listar(int $sistemaId, string $tipo, array $filtros, ?int $proveedorUsuario = null): LengthAwarePaginator
    {
        $q = DB::table('producto')
            ->leftJoin('proveedor', 'proveedor.proveedor_id', '=', 'producto.fk_proveedor_id')
            ->leftJoin('rel_productociudad', 'rel_productociudad.fk_producto_id', '=', 'producto.producto_id')
            ->leftJoin('ciudad', 'ciudad.ciudad_id', '=', 'rel_productociudad.fk_ciudad_id')
            ->where('producto.fk_sistema_id', $sistemaId)
            ->where('producto.fk_tipoproducto_id', $tipo)
            ->where('producto.eliminar', 0)
            ->select([
                'producto.producto_id', 'producto.producto_nombre', 'producto.producto_codigo', 'producto.habilitar',
                'producto.aparece_tarifario', 'producto.fk_proveedor_id', 'proveedor.proveedor_nombre',
                'rel_productociudad.fk_ciudad_id', 'ciudad.ciudad_nombre',
            ])
            ->groupBy('producto.producto_id')
            ->orderByRaw('TRIM(producto.producto_nombre) ASC');

        // hotel.php:20: un usuario de proveedor (ACP) sólo ve sus productos.
        if ($proveedorUsuario !== null && $proveedorUsuario > 0) {
            $q->where('producto.fk_proveedor_id', $proveedorUsuario);
        }

        if (($id = trim((string) ($filtros['producto_id'] ?? ''))) !== '' && ctype_digit($id)) {
            $q->where('producto.producto_id', (int) $id);
        }
        if (($nombre = trim((string) ($filtros['producto_nombre'] ?? ''))) !== '') {
            $q->where('producto.producto_nombre', 'LIKE', "%{$nombre}%");
        }
        if ((int) ($filtros['fk_proveedor_id'] ?? 0) > 0) {
            $q->where('producto.fk_proveedor_id', (int) $filtros['fk_proveedor_id']);
        }
        if ((int) ($filtros['fk_ciudad_id'] ?? 0) > 0) {
            $q->where('rel_productociudad.fk_ciudad_id', (int) $filtros['fk_ciudad_id']);
        }
        foreach (['habilitar', 'aparece_tarifario'] as $flag) {
            if (($filtros[$flag] ?? '') !== '' && $filtros[$flag] !== null) {
                $q->where("producto.{$flag}", (int) $filtros[$flag]);
            }
        }

        return $q->paginate((int) config('productos.per_page', 50))->withQueryString();
    }

    /**
     * Producto completo para el formulario / vigencias: fila + extras mapeados
     * al ítem (como `byid()`), bases, ciudades, facilidades, galería y
     * habitaciones.
     */
    public function cargar(int $id): ?array
    {
        $fila = DB::table('producto')->where('producto_id', $id)->first();
        if ($fila === null) {
            return null;
        }

        $producto = (array) $fila;
        $extras = $this->extras->leer($id);
        $producto['extras'] = $extras;
        // byid() mapea cada extra al ítem (producto_model.php:766).
        foreach ($extras as $k => $v) {
            if (! array_key_exists($k, $producto)) {
                $producto[$k] = $v;
            }
        }

        $producto['edades'] = [
            'edad_infoa' => (int) ($extras['edad_infoa'] ?? 0),
            'edad_menor1' => (int) ($extras['edad_menor1'] ?? 0),
            'edad_menor2' => (int) ($extras['edad_menor2'] ?? 0),
            'edad_junior' => (int) ($extras['edad_junior'] ?? 0),
            'edad_senior' => (int) ($extras['edad_senior'] ?? 0),
        ];

        $producto['bases'] = $this->bases($id, $extras);

        $producto['ciudades'] = DB::table('rel_productociudad')
            ->join('ciudad', 'ciudad.ciudad_id', '=', 'rel_productociudad.fk_ciudad_id')
            ->where('rel_productociudad.fk_producto_id', $id)
            ->orderBy('ciudad.ciudad_nombre')
            ->get(['ciudad.ciudad_id', 'ciudad.ciudad_nombre', 'ciudad.fk_pais_id', 'rel_productociudad.tipo'])
            ->map(fn ($c) => ['ciudad_id' => (int) $c->ciudad_id, 'nombre' => (string) $c->ciudad_nombre, 'pais_id' => (int) $c->fk_pais_id, 'tipo' => (string) $c->tipo])
            ->all();

        // byid(): la primera ciudad es "la" ciudad del producto y define el país.
        $primera = $producto['ciudades'][0] ?? null;
        $producto['fk_ciudad_id'] = $primera['ciudad_id'] ?? (int) $producto['destino'];
        $producto['pais'] = $primera['pais_id'] ?? 0;
        if ($producto['pais'] === 0 && (int) $producto['destino'] > 0) {
            $producto['pais'] = (int) (DB::table('ciudad')->where('ciudad_id', (int) $producto['destino'])->value('fk_pais_id') ?? 0);
        }
        $producto['destinos'] = array_map(fn ($c) => ['ciudad_id' => $c['ciudad_id'], 'tipo' => $c['tipo']], $producto['ciudades']);

        $producto['facilidades'] = DB::table('rel_productoalojamientofacilidad')->where('fk_producto_id', $id)->pluck('fk_alojamientofacilidad_id')->map(fn ($f) => (int) $f)->all();
        $producto['galeria'] = DB::table('productogaleria')->where('fk_producto_id', $id)->orderBy('orden')->get()->map(fn ($g) => (array) $g)->all();
        $producto['habitaciones'] = $this->habitaciones->listar($id);

        return $producto;
    }

    /**
     * Bases numéricas del producto: rel_productobase, o el extra `bases`
     * (producto_model.php:923-939). Sin ninguna, lista vacía: BasesResolver
     * aplica el default.
     *
     * @return list<string>
     */
    public function bases(int $id, ?array $extras = null): array
    {
        $bases = DB::table('rel_productobase')->where('fk_producto_id', $id)->orderBy('fk_base_id')->pluck('fk_base_id')->map(fn ($b) => (string) $b)->all();

        if ($bases === []) {
            $extras ??= $this->extras->leer($id);
            $bases = array_values(array_filter(array_map('trim', explode(',', (string) ($extras['bases'] ?? ''))), fn ($b) => $b !== ''));
            sort($bases, SORT_NUMERIC);
        }

        return $bases;
    }

    // ------------------------------------------------------------------
    // Escritura
    // ------------------------------------------------------------------

    /**
     * Guarda el producto completo. Devuelve el id.
     *
     * @throws ProductoException con los errores por campo
     */
    public function guardar(string $tipo, int $sistemaId, array $datos, int $usuarioId, ?int $id = null): int
    {
        $cfg = $this->formulario->tipo($tipo);

        $errores = $this->reglas->validar($datos, $cfg);
        if ($errores !== []) {
            throw ProductoException::porCampos($errores);
        }

        $porTabla = $this->formulario->porTabla($tipo, $sistemaId);

        return DB::transaction(function () use ($cfg, $tipo, $sistemaId, $datos, $usuarioId, $id, $porTabla) {
            $fila = [];
            foreach ($porTabla['producto'] as $campo) {
                if (array_key_exists($campo['campo'], $datos)) {
                    $fila[$campo['campo']] = $this->valorColumna($campo, $datos[$campo['campo']]);
                }
            }

            if ($id === null) {
                $fila += [
                    'fk_tipoproducto_id' => $tipo,
                    'fk_sistema_id' => $sistemaId,
                    'fk_usuario_id' => $usuarioId,
                    'eliminar' => 0,
                ];
                $fila = $this->conDefaults($fila);
                $id = (int) DB::table('producto')->insertGetId($fila);
            } else {
                if (DB::table('producto')->where('producto_id', $id)->doesntExist()) {
                    throw new ProductoException("El producto {$id} no existe.");
                }
                if ($fila !== []) {
                    DB::table('producto')->where('producto_id', $id)->update($fila);
                }
            }

            // Extras: sólo las claves del form del tipo (las demás no se tocan).
            $extras = [];
            foreach ($porTabla['producto_extra'] as $campo) {
                if (array_key_exists($campo['campo'], $datos)) {
                    $extras[$campo['campo']] = $datos[$campo['campo']];
                }
            }
            $this->extras->sincronizar($id, $extras);

            foreach ($porTabla['relaciones'] as $campo) {
                $this->guardarRelacion($id, $cfg, $campo, $datos);
            }

            if (array_key_exists('galeria', $datos)) {
                $this->sincronizarGaleria($id, (array) $datos['galeria']);
            }

            if (($cfg['habitaciones'] ?? false) && array_key_exists('habitaciones', $datos)) {
                $this->habitaciones->sincronizar($id, (array) $datos['habitaciones']);
            }

            return $id;
        });
    }

    /**
     * Clona producto + extras + relaciones + habitaciones + galería y,
     * opcionalmente, las vigencias con fin >= hoy con sus tarifas.
     *
     * @param  array{con_vigencias?:bool, nombre?:string}  $opciones
     */
    public function clonar(int $id, int $usuarioId, array $opciones = [], ?\App\Services\Vigencias\VigenciaService $vigencias = null): int
    {
        $origen = DB::table('producto')->where('producto_id', $id)->first();
        if ($origen === null) {
            throw new ProductoException("El producto {$id} no existe.");
        }

        return DB::transaction(function () use ($origen, $id, $usuarioId, $opciones, $vigencias) {
            $fila = (array) $origen;
            unset($fila['producto_id']);
            $fila['fk_usuario_id'] = $usuarioId;
            $fila['producto_nombre'] = trim((string) ($opciones['nombre'] ?? '')) ?: $fila['producto_nombre'].' (copia)';
            $fila['eliminar'] = 0;
            $nuevo = (int) DB::table('producto')->insertGetId($fila);

            $this->extras->copiar($id, $nuevo);
            $this->habitaciones->copiar($id, $nuevo);

            foreach (['rel_productobase', 'rel_productociudad', 'rel_productoalojamientofacilidad', 'productogaleria'] as $tabla) {
                foreach (DB::table($tabla)->where('fk_producto_id', $id)->get() as $r) {
                    $r = (array) $r;
                    unset($r['productogaleria_id']);
                    $r['fk_producto_id'] = $nuevo;
                    DB::table($tabla)->insert($r);
                }
            }

            if (! empty($opciones['con_vigencias']) && $vigencias !== null) {
                $hoy = now()->format('Y-m-d');
                $ids = DB::table('vigencia')->where('fk_producto_id', $id)->where('vigencia_fin', '>=', $hoy)->pluck('vigencia_id');
                foreach ($ids as $vid) {
                    $vigencias->clonar((int) $vid, ['producto_id' => $nuevo]);
                }
            }

            return $nuevo;
        });
    }

    /** Baja lógica. El CI filtra `eliminar = 0` al tarifar y listar. */
    public function eliminar(int $id): void
    {
        $n = DB::table('producto')->where('producto_id', $id)->update(['eliminar' => 1, 'habilitar' => 0]);
        if ($n === 0) {
            throw new ProductoException("El producto {$id} no existe.");
        }
    }

    public function restaurar(int $id): void
    {
        DB::table('producto')->where('producto_id', $id)->update(['eliminar' => 0]);
    }

    // ------------------------------------------------------------------
    // Internos
    // ------------------------------------------------------------------

    private function guardarRelacion(int $id, array $cfg, array $campo, array $datos): void
    {
        switch ($campo['tipo']) {
            case 'bases':
                if (! array_key_exists('bases', $datos)) {
                    return;
                }
                $this->sincronizarSimple('rel_productobase', 'fk_base_id', $id, array_map('intval', (array) $datos['bases']));
                // El CI también lee el extra `bases` (producto_model.php:930): se mantiene sincronizado.
                $this->extras->sincronizar($id, ['bases' => implode(',', array_map('intval', (array) $datos['bases']))]);
                break;

            case 'facilidades':
                if (array_key_exists('facilidades', $datos)) {
                    $this->sincronizarSimple('rel_productoalojamientofacilidad', 'fk_alojamientofacilidad_id', $id, array_map('intval', (array) $datos['facilidades']));
                }
                break;

            case 'ciudad':
                // hotel.php:280: ciudad única = producto.destino + UNA fila en rel_productociudad.
                if (array_key_exists('destino', $datos)) {
                    $ciudad = (int) $datos['destino'];
                    DB::table('producto')->where('producto_id', $id)->update(['destino' => $ciudad]);
                    $this->sincronizarCiudades($id, $ciudad > 0 ? [['ciudad_id' => $ciudad, 'tipo' => 'D']] : []);
                }
                break;

            case 'destinos':
                if (array_key_exists('destinos', $datos)) {
                    $lista = [];
                    foreach ((array) $datos['destinos'] as $d) {
                        $lista[] = is_array($d)
                            ? ['ciudad_id' => (int) ($d['ciudad_id'] ?? 0), 'tipo' => (string) ($d['tipo'] ?? 'D')]
                            : ['ciudad_id' => (int) $d, 'tipo' => 'D'];
                    }
                    $this->sincronizarCiudades($id, $lista);
                }
                break;
        }
    }

    private function sincronizarSimple(string $tabla, string $columna, int $id, array $valores): void
    {
        $valores = array_values(array_unique(array_filter($valores, fn ($v) => $v > 0)));
        $actuales = DB::table($tabla)->where('fk_producto_id', $id)->pluck($columna)->map(fn ($v) => (int) $v)->all();

        foreach (array_diff($actuales, $valores) as $v) {
            DB::table($tabla)->where('fk_producto_id', $id)->where($columna, $v)->delete();
        }
        foreach (array_diff($valores, $actuales) as $v) {
            DB::table($tabla)->insert(['fk_producto_id' => $id, $columna => $v]);
        }
    }

    /** @param list<array{ciudad_id:int,tipo:string}> $ciudades */
    private function sincronizarCiudades(int $id, array $ciudades): void
    {
        $deseadas = [];
        foreach ($ciudades as $c) {
            if ($c['ciudad_id'] > 0) {
                $deseadas[$c['ciudad_id']] = in_array($c['tipo'], ['D', 'O'], true) ? $c['tipo'] : 'D';
            }
        }

        $actuales = DB::table('rel_productociudad')->where('fk_producto_id', $id)->pluck('tipo', 'fk_ciudad_id')->all();

        foreach ($actuales as $ciudad => $tipo) {
            if (! isset($deseadas[$ciudad])) {
                DB::table('rel_productociudad')->where('fk_producto_id', $id)->where('fk_ciudad_id', $ciudad)->delete();
            } elseif ($deseadas[$ciudad] !== $tipo) {
                DB::table('rel_productociudad')->where('fk_producto_id', $id)->where('fk_ciudad_id', $ciudad)->update(['tipo' => $deseadas[$ciudad]]);
            }
        }
        foreach ($deseadas as $ciudad => $tipo) {
            if (! isset($actuales[$ciudad])) {
                DB::table('rel_productociudad')->insert(['fk_producto_id' => $id, 'fk_ciudad_id' => $ciudad, 'tipo' => $tipo]);
            }
        }
    }

    /** @param list<array{productogaleria_archivo:string, orden?:int}|string> $galeria */
    private function sincronizarGaleria(int $id, array $galeria): void
    {
        $deseadas = [];
        foreach ($galeria as $i => $g) {
            $archivo = is_array($g) ? (string) ($g['productogaleria_archivo'] ?? '') : (string) $g;
            if ($archivo !== '') {
                $deseadas[$archivo] = is_array($g) && isset($g['orden']) ? (int) $g['orden'] : $i;
            }
        }

        $actuales = DB::table('productogaleria')->where('fk_producto_id', $id)->pluck('orden', 'productogaleria_archivo')->all();

        foreach ($actuales as $archivo => $orden) {
            if (! isset($deseadas[$archivo])) {
                DB::table('productogaleria')->where('fk_producto_id', $id)->where('productogaleria_archivo', $archivo)->delete();
            } elseif ((int) $orden !== $deseadas[$archivo]) {
                DB::table('productogaleria')->where('fk_producto_id', $id)->where('productogaleria_archivo', $archivo)->update(['orden' => $deseadas[$archivo]]);
            }
        }
        foreach ($deseadas as $archivo => $orden) {
            if (! isset($actuales[$archivo])) {
                DB::table('productogaleria')->insert(['fk_producto_id' => $id, 'productogaleria_archivo' => $archivo, 'orden' => $orden]);
            }
        }
    }

    private function valorColumna(array $campo, mixed $valor): mixed
    {
        return match ($campo['tipo']) {
            'boolean' => (int) (bool) $valor,
            'number', 'select' => is_numeric($valor) ? $valor + 0 : (int) $valor,
            default => $valor === null ? '' : (string) $valor,
        };
    }

    /** Columnas NOT NULL sin default de `producto` que el form puede no mandar. */
    private function conDefaults(array $fila): array
    {
        return $fila + [
            'fk_proveedor_id' => 0, 'fk_prestador_id' => 0, 'fk_submodulo_id' => 0, 'fk_productogrupo_id' => 0,
            'origen' => 0, 'destino' => 0, 'producto_nombre_en' => '', 'producto_nombre_pt' => '',
            'producto_descripcion' => '', 'producto_descripcion_en' => '', 'producto_descripcion_pt' => '',
            'habilitar' => 1, 'modotarifa' => '', 'gmaps' => '', 'aparece_tarifario' => 1, 'disponibilidad' => '',
            'producto_codigo' => '', 'destacar' => 0, 'politica_cancelacion' => '', 'destinos' => '',
        ];
    }
}
