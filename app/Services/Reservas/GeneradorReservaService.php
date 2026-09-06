<?php

namespace App\Services\Reservas;

use App\Models\User;
use App\Services\AuditoriaService;
use App\Services\CotizacionService;
use App\Support\Licencia;
use Illuminate\Support\Facades\DB;

/**
 * Generador de reservas (v1): alta manual de un file con sus servicios desde
 * /app, réplica del núcleo de `reserva.php::reservar()` + `reserva_model::set_reserva()`
 * + `servicio_model::set_servicio()` del CI, sin el carrito ni los tarifadores.
 *
 * Mejoras de integridad respecto del legacy (ver docs/GENERADOR_RESERVAS.md):
 *  1. Código de file atómico: lock nombrado + MAX+1 dentro de la transacción
 *     (el CI hacía `codigo = (SELECT MAX+1)` sin lock y generaba duplicados).
 *  2. Una sola transacción: reserva, servicios, nómina, historial y auditoría
 *     se escriben todos o ninguno (el CI dejaba files vacíos si fallaba un servicio).
 *  3. Totales calculados en el servidor a partir de los servicios (el CI
 *     confiaba en `total_apagar` y compañía que venían del POST).
 *  4. Validaciones de dominio: cliente habilitado y del área, proveedor y tipo
 *     de producto activos, moneda existente, fechas coherentes y no anteriores a
 *     la fecha mínima, pax > 0, importes no negativos, nómina ≤ pax.
 *  5. Control de límite de crédito antes de crear (bloquea salvo `forzar_credito`,
 *     que queda auditado).
 *  6. Cotizaciones siempre persistidas en el servicio (cotcosto/cotventa nunca 0).
 */
class GeneradorReservaService
{
    public const PASAJEROS_TIPOS = ['ADT', 'CHD', 'INF', 'JUN'];

    public function __construct(
        private CotizacionService $cotizaciones,
        private CreditoClienteService $credito,
        private AuditoriaService $auditoria,
    ) {}

    /** Fecha mínima de inicio: hoy para internos; +4 días hábiles (sin feriados) para externos, como el CI. */
    public function fechaMinima(User $usuario): string
    {
        if ($this->esInterno($usuario)) {
            return now()->toDateString();
        }
        $feriados = DB::table('feriado')->where('feriado_fecha', '>=', now()->toDateString())->orderBy('feriado_fecha')->limit(5)->pluck('feriado_fecha')
            ->map(fn ($f) => substr((string) $f, 0, 10))->all();
        $fecha = now();
        $habiles = 0;
        while ($habiles < 4) {
            $fecha = $fecha->addWeekday();
            if (! in_array($fecha->toDateString(), $feriados, true)) {
                $habiles++;
            }
        }

        return $fecha->toDateString();
    }

    /** Clientes habilitados para reservar en el área (mismas reglas que reserva.php::nueva()). */
    public function clientes(int $idsistema): array
    {
        $q = DB::table('cliente')->whereIn('cliente.habilita', ['Y', '1'])->orderByRaw('TRIM(cliente.cliente_nombre)');
        if (Licencia::flag('tipolicencia') === 'minorista' || $idsistema === 5) {
            // todos
        } elseif ($idsistema === 3) {
            $q->where('clienteminorista', 1);
        } elseif ($idsistema === 4) {
            $q->whereIn('consolidador', ['Y', '1']);
        } else {
            $q->join('rel_clientesistema as rcs', 'rcs.fk_cliente_id', '=', 'cliente.cliente_id')->where('rcs.fk_sistema_id', $idsistema)->distinct();
        }

        return $q->get(['cliente.cliente_id', 'cliente.cliente_nombre', 'cliente.limite_credito', 'cliente.credito_habilitado', 'cliente.fk_moneda_id', 'cliente.fk_usuario_vendedor'])
            ->map(fn ($c) => [
                'value' => (int) $c->cliente_id,
                'label' => $c->cliente_nombre.((float) $c->limite_credito != 0 ? ' ('.number_format((float) $c->limite_credito, 0, ',', '.').')' : ''),
                'limite' => (float) $c->limite_credito,
                'credito_habilitado' => (int) $c->credito_habilitado,
                'vendedor' => (int) $c->fk_usuario_vendedor,
            ])->all();
    }

    /**
     * Valida cabecera y servicios. Devuelve ['errores' => [campo => msg], 'advertencias' => [msg]].
     *
     * @param  array<string,mixed>  $cab
     * @param  list<array<string,mixed>>  $servicios
     */
    public function validar(array $cab, array $servicios, User $usuario): array
    {
        $errores = [];
        $avisos = [];
        $idsistema = (int) ($cab['fk_sistema_id'] ?? 0);

        $cliente = DB::table('cliente')->where('cliente_id', (int) ($cab['fk_cliente_id'] ?? 0))->first();
        if ($cliente === null) {
            $errores['fk_cliente_id'] = 'Elegí un cliente.';
        } elseif (! in_array((string) $cliente->habilita, ['Y', '1'], true)) {
            $errores['fk_cliente_id'] = 'El cliente está deshabilitado.';
        } elseif (! collect($this->clientes($idsistema))->contains('value', (int) $cliente->cliente_id)) {
            $errores['fk_cliente_id'] = 'El cliente no está habilitado para reservar en esta área.';
        }
        if (trim((string) ($cab['titular_nombre'] ?? '')) === '' || trim((string) ($cab['titular_apellido'] ?? '')) === '') {
            $errores['titular'] = 'Nombre y apellido del titular son obligatorios.';
        }
        if (! DB::table('moneda')->where('moneda_id', (string) ($cab['fk_moneda_id'] ?? ''))->exists()) {
            $errores['fk_moneda_id'] = 'Moneda del file inexistente.';
        }
        if (! DB::table('sistema')->where('sistema_id', $idsistema)->exists()) {
            $errores['fk_sistema_id'] = 'Área inexistente.';
        }
        $agente = (int) ($cab['agente'] ?? 0);
        if ($agente > 0 && DB::table('usuario')->where('usuario_id', $agente)->doesntExist()) {
            $errores['agente'] = 'El vendedor/escritorio no existe.';
        }

        if ($servicios === []) {
            $errores['servicios'] = 'La reserva necesita al menos un servicio.';
        }
        $minima = $this->fechaMinima($usuario);
        $tipos = DB::table('submodulo')->where('tipoproducto_activo', 1)->pluck('tipoproducto_id')->map(fn ($t) => (string) $t)->all();
        $monedas = DB::table('moneda')->pluck('moneda_id')->map(fn ($m) => (string) $m)->all();
        foreach ($servicios as $i => $s) {
            $n = $i + 1;
            $k = "servicios.{$i}";
            if (! in_array((string) ($s['fk_tipoproducto_id'] ?? ''), $tipos, true)) {
                $errores["{$k}.fk_tipoproducto_id"] = "Servicio {$n}: tipo de producto inexistente o inactivo.";
            }
            if (trim((string) ($s['servicio_nombre'] ?? '')) === '') {
                $errores["{$k}.servicio_nombre"] = "Servicio {$n}: el nombre es obligatorio.";
            }
            $prov = (int) ($s['fk_proveedor_id'] ?? 0);
            if ($prov > 0) {
                $p = DB::table('proveedor')->where('proveedor_id', $prov)->first(['habilita', 'eliminar']);
                if ($p === null || (string) $p->eliminar === 'Y' || ! in_array((string) $p->habilita, ['Y', '1'], true)) {
                    $errores["{$k}.fk_proveedor_id"] = "Servicio {$n}: proveedor inexistente o deshabilitado.";
                }
            }
            $ini = (string) ($s['vigencia_ini'] ?? '');
            $fin = (string) ($s['vigencia_fin'] ?? '') ?: $ini;
            if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $ini)) {
                $errores["{$k}.vigencia_ini"] = "Servicio {$n}: fecha de inicio inválida.";
            } else {
                if ($ini < $minima) {
                    $errores["{$k}.vigencia_ini"] = "Servicio {$n}: el inicio no puede ser anterior al ".date('d/m/Y', strtotime($minima)).'.';
                }
                if ($fin < $ini) {
                    $errores["{$k}.vigencia_fin"] = "Servicio {$n}: el fin es anterior al inicio.";
                }
            }
            $pax = (int) ($s['adultos'] ?? 0) + (int) ($s['menores'] ?? 0) + (int) ($s['infante'] ?? 0) + (int) ($s['juniors'] ?? 0);
            if ($pax <= 0) {
                $errores["{$k}.adultos"] = "Servicio {$n}: tiene que haber al menos un pasajero.";
            }
            foreach (['total', 'costo', 'iva', 'iva_costo', 'impuestos'] as $campo) {
                if (! is_numeric($s[$campo] ?? 0) || (float) ($s[$campo] ?? 0) < 0) {
                    $errores["{$k}.{$campo}"] = "Servicio {$n}: {$campo} debe ser un número no negativo.";
                }
            }
            foreach (['fk_moneda_id', 'moneda_costo'] as $campo) {
                if (! in_array((string) ($s[$campo] ?? ''), $monedas, true)) {
                    $errores["{$k}.{$campo}"] = "Servicio {$n}: moneda inexistente.";
                }
            }
            if (! in_array((string) ($s['status'] ?? 'CO'), ['CO', 'RQ'], true)) {
                $errores["{$k}.status"] = "Servicio {$n}: status inválido (CO o RQ).";
            }
            $nomina = array_values(array_filter($s['pasajeros'] ?? [], fn ($p) => trim((string) ($p['apellido'] ?? '').($p['nombre'] ?? '')) !== ''));
            if (count($nomina) > $pax) {
                $avisos[] = "Servicio {$n}: la nómina tiene más pasajeros (".count($nomina).") que los declarados ({$pax}).";
            }
            if ((float) ($s['total'] ?? 0) == 0.0) {
                $avisos[] = "Servicio {$n}: total en cero.";
            }
        }

        // Límite de crédito.
        if ($cliente !== null && ! isset($errores['fk_cliente_id']) && $errores === []) {
            $limite = (float) $cliente->limite_credito;
            if ($limite > 0 && (int) $cliente->credito_habilitado === 1) {
                $utilizado = $this->credito->utilizado((int) $cliente->cliente_id);
                $nuevo = $this->totalEnMonedaBasica($servicios);
                if ($utilizado + $nuevo > $limite) {
                    $msg = sprintf('El cliente supera su límite de crédito (%s utilizado + %s de esta reserva > %s).', number_format($utilizado, 2, ',', '.'), number_format($nuevo, 2, ',', '.'), number_format($limite, 2, ',', '.'));
                    if (! empty($cab['forzar_credito']) && $this->puedeForzarCredito($usuario)) {
                        $avisos[] = $msg.' Se creó igual por pedido del usuario.';
                    } else {
                        $errores['credito'] = $msg;
                    }
                }
            }
        }

        return ['errores' => $errores, 'advertencias' => $avisos];
    }

    /**
     * Crea la reserva con sus servicios. Lanza \DomainException con los errores si no valida.
     *
     * @return array{reserva_id:int,codigo:int,tipocodigo:string,advertencias:list<string>}
     */
    public function crear(array $cab, array $servicios, User $usuario): array
    {
        ['errores' => $errores, 'advertencias' => $avisos] = $this->validar($cab, $servicios, $usuario);
        if ($errores !== []) {
            throw new ReservaInvalidaException($errores);
        }

        $idsistema = (int) $cab['fk_sistema_id'];
        $cliente = DB::table('cliente')->where('cliente_id', (int) $cab['fk_cliente_id'])->first();
        $sistema = DB::table('sistema')->where('sistema_id', $idsistema)->first();
        $hoy = now()->toDateString();
        $monedaFile = (string) $cab['fk_moneda_id'];
        $basica = $this->cotizaciones->monedaBasica();
        $statusFile = Licencia::pais() === 'CL' ? 'RQ' : 'CO';
        $agente = (int) ($cab['agente'] ?? 0);
        if ($agente === 0) {
            $agente = $this->esInterno($usuario) ? (int) $usuario->usuario_id : ((int) $cliente->fk_usuario_vendedor ?: (int) $usuario->usuario_id);
        }

        return DB::transaction(function () use ($cab, $servicios, $usuario, $cliente, $sistema, $hoy, $monedaFile, $basica, $statusFile, $agente, $idsistema, $avisos) {
            $lock = 'reserva_codigo_'.Licencia::base();
            $ok = DB::selectOne('SELECT GET_LOCK(?, 10) AS ok', [$lock]);
            if ((int) ($ok->ok ?? 0) !== 1) {
                throw new \RuntimeException('No se pudo obtener el lock para numerar la reserva.');
            }
            try {
                $codigo = (int) DB::table('reserva')->where('escotizacion', 0)->max(DB::raw('CAST(codigo AS UNSIGNED)')) + 1;
                $tipocodigo = (string) ($sistema->sistema_codigo ?: 'MA');

                $cotFile = $monedaFile === $basica ? 1.0 : ($this->cotizaciones->aLaVenta($monedaFile, $hoy) ?: 1.0);
                $totales = ['total' => 0.0, 'costo' => 0.0, 'iva' => 0.0, 'ivacosto' => 0.0, 'impuestos' => 0.0, 'renta' => 0.0];
                $filasServicio = [];
                foreach ($servicios as $s) {
                    $fila = $this->filaServicio($s, $hoy, $basica);
                    $filasServicio[] = $fila;
                    // Conversión a la moneda del file sólo cuando la moneda difiere: venta con cotventa,
                    // costo con cotcosto, ambos sobre la cotización del file. La renta viene en básica.
                    $fv = $fila['fk_moneda_id'] === $monedaFile ? 1.0 : $fila['cotventa'] / $cotFile;
                    $fc = $fila['moneda_costo'] === $monedaFile ? 1.0 : $fila['cotcosto'] / $cotFile;
                    $totales['total'] += $fila['total'] * $fv;
                    $totales['iva'] += $fila['iva'] * $fv;
                    $totales['costo'] += $fila['costo'] * $fc;
                    $totales['ivacosto'] += $fila['iva_costo'] * $fc;
                    $totales['impuestos'] += $fila['impuestos'] * $fc;
                    $totales['renta'] += $fila['renta'] / $cotFile;
                }

                $reserva = [
                    'fk_sistema_id' => $idsistema, 'fk_sistemaaplicacion_id' => $idsistema, 'tipocodigo' => $tipocodigo, 'codigo' => $codigo,
                    'fk_cliente_id' => (int) $cliente->cliente_id, 'facturar_a' => (int) $cliente->cliente_id, 'fk_usuario_id' => (int) $usuario->usuario_id, 'agente' => $agente,
                    'promotor' => (int) ($cliente->fk_usuario_promotor1 ?? 0), 'fk_filestatus_id' => $statusFile,
                    'titular_nombre' => mb_strtoupper(trim((string) $cab['titular_nombre'])), 'titular_apellido' => mb_strtoupper(trim((string) $cab['titular_apellido'])),
                    'titular_email' => trim((string) ($cab['titular_email'] ?? '')), 'titular_celular' => trim((string) ($cab['titular_celular'] ?? '')),
                    'observaciones' => (string) ($cab['observaciones'] ?? ''), 'fk_moneda_id' => $monedaFile, 'moneda_factura' => (string) (Licencia::sysconfig('monedafacturadefault') ?: $basica),
                    'fecha_alta' => $hoy, 'fecha_vencimiento' => (string) ($cab['fecha_vencimiento'] ?? '') ?: $hoy, 'inicio' => min(array_column($filasServicio, 'vigencia_ini')),
                    'total' => round($totales['total'], 2), 'totalservicios' => round($totales['total'], 2), 'iva' => round($totales['iva'], 2), 'costo' => round($totales['costo'], 2),
                    'ivacosto' => round($totales['ivacosto'], 2), 'impuestos' => round($totales['impuestos'], 2), 'renta' => round($totales['renta'], 2),
                    'escotizacion' => 0, 'regdate' => now(),
                ];
                $reservaId = (int) DB::table('reserva')->insertGetId($reserva, 'reserva_id');

                // Doble control: el código tiene que haber quedado único.
                if (DB::table('reserva')->where('codigo', $codigo)->where('escotizacion', 0)->count() > 1) {
                    throw new \RuntimeException("El código {$codigo} quedó duplicado; se deshizo la reserva.");
                }

                foreach ($filasServicio as $i => $fila) {
                    $pasajeros = $fila['_pasajeros'];
                    unset($fila['_pasajeros']);
                    $servicioId = (int) DB::table('servicio')->insertGetId($fila + ['fk_reserva_id' => $reservaId, 'regdate' => now()], 'servicio_id');
                    foreach ($pasajeros as $p) {
                        DB::table('servicio_nomina')->insert([
                            'fk_servicio_id' => $servicioId, 'nombre' => mb_strtoupper(trim((string) ($p['nombre'] ?? ''))), 'apellido' => mb_strtoupper(trim((string) ($p['apellido'] ?? ''))),
                            'documento' => (string) ($p['documento'] ?? ''), 'nacionalidad' => (string) ($p['nacionalidad'] ?? ''), 'telefono' => (string) ($p['telefono'] ?? ''),
                            'edad' => (string) ($p['edad'] ?? ''), 'nacimiento' => (string) ($p['nacimiento'] ?? ''), 'tipopax' => in_array($p['tipopax'] ?? '', self::PASAJEROS_TIPOS, true) ? $p['tipopax'] : 'ADT',
                        ]);
                    }
                }

                DB::table('historialfile')->insert([
                    'historial_date' => now(), 'historial_campo' => 'alta', 'historial_valor' => '', 'historial_actual' => "Reserva {$tipocodigo}-{$codigo} creada desde /app con ".count($filasServicio).' servicio(s)',
                    'fk_reserva_id' => $reservaId, 'fk_servicio_id' => 0, 'fk_usuario_id' => (int) $usuario->usuario_id, 'historial_ip' => (string) request()->ip(),
                ]);
                $this->auditoria->registrar('reserva', $reservaId, 'ALTA_GENERADOR', [], ['reserva' => $reserva, 'servicios' => count($filasServicio), 'advertencias' => $avisos, 'forzar_credito' => ! empty($cab['forzar_credito'])]);

                return ['reserva_id' => $reservaId, 'codigo' => $codigo, 'tipocodigo' => $tipocodigo, 'advertencias' => $avisos];
            } finally {
                DB::selectOne('SELECT RELEASE_LOCK(?) AS ok', [$lock]);
            }
        });
    }

    /** Fila de `servicio` con cotizaciones y renta calculadas (como set_servicio + el cálculo de renta de facturados). */
    private function filaServicio(array $s, string $hoy, string $basica): array
    {
        $monedaVenta = (string) $s['fk_moneda_id'];
        $monedaCosto = (string) ($s['moneda_costo'] ?: $monedaVenta);
        $cotventa = $monedaVenta === $basica ? 1.0 : ($this->cotizaciones->aLaVenta($monedaVenta, $hoy) ?: 1.0);
        $cotcosto = $monedaCosto === $basica ? 1.0 : ($this->cotizaciones->alCosto($monedaCosto, $hoy) ?: 1.0);
        $total = round((float) ($s['total'] ?? 0), 2);
        $costo = round((float) ($s['costo'] ?? 0), 2);
        $iva = round((float) ($s['iva'] ?? 0), 2);
        $ivaCosto = round((float) ($s['iva_costo'] ?? 0), 2);
        $impuestos = round((float) ($s['impuestos'] ?? 0), 2);
        $tipo = (string) $s['fk_tipoproducto_id'];
        $renta = $total * $cotventa - $iva * $cotventa - ($costo + $ivaCosto) * $cotcosto - ($tipo !== 'AER' ? $impuestos * $cotcosto : 0);
        $ini = (string) $s['vigencia_ini'];
        $fin = (string) ($s['vigencia_fin'] ?? '') ?: $ini;

        return [
            'servicio_nombre' => trim((string) $s['servicio_nombre']), 'fk_tipoproducto_id' => $tipo, 'fk_producto_id' => (int) ($s['fk_producto_id'] ?? 0),
            'fk_proveedor_id' => (int) ($s['fk_proveedor_id'] ?? 0), 'fk_prestador_id' => (int) ($s['fk_prestador_id'] ?? 0), 'fk_ciudad_id' => (int) ($s['fk_ciudad_id'] ?? 0),
            'fk_tarifacategoria_id' => (int) ($s['fk_tarifacategoria_id'] ?? 0), 'fk_regimen_id' => (int) ($s['fk_regimen_id'] ?? 0),
            'vigencia_ini' => $ini, 'vigencia_fin' => $fin, 'adultos' => (int) ($s['adultos'] ?? 0), 'menores' => (int) ($s['menores'] ?? 0), 'infante' => (int) ($s['infante'] ?? 0), 'juniors' => (int) ($s['juniors'] ?? 0),
            'status' => (string) ($s['status'] ?? 'CO'), 'fk_moneda_id' => $monedaVenta, 'moneda_costo' => $monedaCosto, 'total' => $total, 'totalservicio' => $total, 'costo' => $costo,
            'iva' => $iva, 'iva_costo' => $ivaCosto, 'impuestos' => $impuestos, 'cotventa' => $cotventa, 'cotcosto' => $cotcosto, 'renta' => round($renta, 4),
            'nro_confirmacion' => (string) ($s['nro_confirmacion'] ?? ''), 'comentarios' => (string) ($s['comentarios'] ?? ''), 'vencimiento_proveedor' => (string) ($s['vencimiento_proveedor'] ?? '') ?: null,
            'origen' => (int) ($s['fk_producto_id'] ?? 0) > 0 ? 'TAR' : 'APP',
            '_pasajeros' => array_values(array_filter($s['pasajeros'] ?? [], fn ($p) => trim((string) ($p['apellido'] ?? '').($p['nombre'] ?? '')) !== '')),
        ];
    }

    private function totalEnMonedaBasica(array $servicios): float
    {
        $basica = $this->cotizaciones->monedaBasica();
        $total = 0.0;
        foreach ($servicios as $s) {
            $m = (string) ($s['fk_moneda_id'] ?? $basica);
            $total += (float) ($s['total'] ?? 0) * ($m === $basica ? 1.0 : ($this->cotizaciones->aLaVenta($m) ?: 1.0));
        }

        return round($total, 2);
    }

    private function esInterno(User $usuario): bool
    {
        return in_array((string) ($usuario->usuario_interno ?? 'N'), ['Y', '1'], true) || (string) $usuario->fk_tipousuario_id === 'POW';
    }

    private function puedeForzarCredito(User $usuario): bool
    {
        return (string) $usuario->fk_tipousuario_id === 'POW' || \App\Helpers\PermisoHelper::tienePermiso(255, 'cambiar_limite_credito');
    }
}
