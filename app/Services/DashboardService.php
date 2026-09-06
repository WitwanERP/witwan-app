<?php

namespace App\Services;

use App\Helpers\PermisoHelper;
use App\Models\User;
use App\Services\Reservas\ReservaListadoService;
use Illuminate\Support\Facades\DB;

/**
 * Datos del inicio (CI: dashboard.php index/reservas/cobranzas/notificaciones/
 * ureservas/iniciooperacion/ucotizaciones + views/dashboards.php).
 *
 * Los widgets se habilitan por permiso sobre las secciones hijas de brain
 * "Inicio ..." (207-214 y 238), con las mismas claves que el legacy. Los
 * widgets de pagos, cobros y "a facturar" están muertos en el CI (die() al
 * entrar) y no se portan.
 */
class DashboardService
{
    /** seccion_id de brain => clave del permiso / widget (dashboard.php:27). */
    public const MODULOS = [
        214 => 'inicio_cobranza',
        213 => 'inicio_cobros',
        212 => 'inicio_afacturar',
        211 => 'inicio_pagos',
        210 => 'inicio_ucotizaciones',
        207 => 'inicio_reservas',
        208 => 'inicio_vencimientos',
        209 => 'inicio_ureservas',
        238 => 'inicio_operacion',
    ];

    public const PERIODOS = ['semana' => '2 WEEK', 'mes' => '1 MONTH', 'seis' => '6 MONTH', 'anio' => '1 YEAR'];

    public function __construct(private CotizacionService $cotizaciones, private ReservaListadoService $reservas) {}

    /** @return list<string> claves de widget habilitadas para el usuario. */
    public function modulos(User $usuario): array
    {
        $out = [];
        foreach (self::MODULOS as $seccion => $clave) {
            if (PermisoHelper::tienePermiso($seccion, $clave)) {
                $out[] = $clave;
            }
        }

        return $out;
    }

    /**
     * Reservas CL/CO dadas de alta en el período, agrupadas por día o mes, con
     * costo/renta/impuestos convertidos a USD-equivalente (cotización del día
     * de cada moneda contra la de USD, como el legacy).
     */
    public function reservasPorPeriodo(?int $sistema, string $periodo): array
    {
        [$desde, $formato] = $this->periodo($periodo);
        $q = DB::table('reserva')
            ->where('reserva_id', '<>', 1)
            ->where('fecha_alta', '>=', $desde)
            ->whereIn('fk_filestatus_id', ['CL', 'CO'])
            ->orderBy('fecha_alta');
        $this->porSistema($q, $sistema);

        $cotUsd = $this->cotizacionReferencia();
        $series = [];
        $tot = ['reservas' => 0, 'costo' => 0.0, 'renta' => 0.0, 'impuestos' => 0.0];
        foreach ($q->get(['fecha_alta', 'costo', 'renta', 'impuestos', 'fk_moneda_id']) as $r) {
            $clave = date($formato, strtotime((string) $r->fecha_alta));
            $rel = $this->relacion((string) $r->fk_moneda_id, $cotUsd);
            $series[$clave] ??= ['clave' => $clave, 'total' => 0, 'costo' => 0.0, 'renta' => 0.0, 'impuestos' => 0.0];
            $series[$clave]['total']++;
            $series[$clave]['costo'] += (float) $r->costo * $rel;
            $series[$clave]['renta'] += (float) $r->renta * $rel;
            $series[$clave]['impuestos'] += (float) $r->impuestos * $rel;
            $tot['reservas']++;
            $tot['costo'] += (float) $r->costo * $rel;
            $tot['renta'] += (float) $r->renta * $rel;
            $tot['impuestos'] += (float) $r->impuestos * $rel;
        }

        return [
            'series' => array_values(array_map(fn ($s) => ['clave' => $s['clave'], 'total' => $s['total'], 'costo' => round($s['costo']), 'renta' => round($s['renta']), 'impuestos' => round($s['impuestos'])], $series)),
            'totales' => ['reservas' => $tot['reservas'], 'costo' => round($tot['costo']), 'renta' => round($tot['renta']), 'impuestos' => round($tot['impuestos'])],
        ];
    }

    /** Cobranzas (imputaciones de recibos a files) por período, en USD-equivalente. */
    public function cobranzasPorPeriodo(?int $sistema, string $periodo): array
    {
        [$desde, $formato] = $this->periodo($periodo);
        $q = DB::table('reserva as r')
            ->join('rel_filerecibo as rf', 'rf.fk_file_id', '=', 'r.reserva_id')
            ->join('recibo as rc', 'rc.recibo_id', '=', 'rf.fk_recibo_id')
            ->where('r.reserva_id', '<>', 1)
            ->where('rf.fecha', '>=', $desde)
            ->where('rc.statusrecibo', '<>', 'AN')
            ->orderBy('rf.fecha');
        $this->porSistema($q, $sistema, 'r');

        $cotUsd = $this->cotizacionReferencia();
        $series = [];
        $total = 0.0;
        $cant = 0;
        foreach ($q->get(['rf.fecha', 'rf.monto', 'rf.fk_moneda_id']) as $r) {
            $clave = date($formato, strtotime((string) $r->fecha));
            $monto = (float) $r->monto * $this->relacion((string) $r->fk_moneda_id, $cotUsd);
            $series[$clave] ??= ['clave' => $clave, 'total' => 0.0];
            $series[$clave]['total'] += $monto;
            $total += $monto;
            $cant++;
        }
        $usd = $this->cotizaciones->aLaVenta('USD');

        return [
            'series' => array_values(array_map(fn ($s) => ['clave' => $s['clave'], 'total' => round($s['total'])], $series)),
            'totales' => ['cobranzas' => $cant, 'total' => round($total), 'total_usd' => $usd > 0 ? round($total / $usd) : 0],
        ];
    }

    /** Notificaciones del usuario agrupadas por nombre (notification_model::counts()). */
    public function notificaciones(int $usuarioId): array
    {
        return DB::table('sysnotification')
            ->where('fk_usuario_id', $usuarioId)
            ->groupBy('sysnotification_nombre')
            ->orderByDesc(DB::raw('MAX(sysnotification_date)'))
            ->get([
                'sysnotification_nombre', DB::raw('COUNT(sysnotification_id) AS cantidad'), DB::raw('MAX(sysnotification_url) AS url'),
                DB::raw('MAX(sysnotification_type) AS tipo'), DB::raw('MAX(sysnotification_icon) AS icono'),
            ])
            ->map(fn ($n) => [
                'nombre' => $n->sysnotification_nombre,
                'cantidad' => (int) $n->cantidad,
                'url' => $n->url,
                'prioridad' => match ($n->tipo) {
                    'reservasv' => ['label' => 'URGENTE', 'clase' => 'danger'],
                    'reservas24' => ['label' => '24 horas', 'clase' => 'warning'],
                    'reservas48' => ['label' => '48 horas', 'clase' => 'success'],
                    default => ['label' => (string) $n->tipo, 'clase' => 'gray'],
                },
                'tipo' => str_contains((string) $n->tipo, 'reservas') ? 'Reservas' : '',
                'area' => match ((int) $n->icono) {
                    2 => 'Mayorista', 3 => 'Minorista', 4 => 'Consolidador', default => 'Receptivo',
                },
            ])
            ->all();
    }

    /** Mis reservas (vendedor = usuario) — el legacy no filtra por vendedor en mundotour_sdg. */
    public function misReservas(User $usuario, int $limite = 15): array
    {
        $filtros = ['rsv' => 10, 'status' => ''];
        if (! \App\Support\Licencia::es('mundotour_sdg')) {
            $filtros['vendedor'] = (int) $usuario->usuario_id;
        }

        return $this->filas($this->reservas->listar($filtros, $usuario, $limite));
    }

    /** Files donde el usuario es responsable (promotor). */
    public function operaciones(User $usuario, int $limite = 10): array
    {
        return $this->filas($this->reservas->listar(['rsv' => 10, 'status' => '', 'responsable' => (int) $usuario->usuario_id], $usuario, $limite));
    }

    /** Mis cotizaciones (tablas ctz / servicioctz), las últimas del usuario. */
    public function misCotizaciones(User $usuario, int $limite = 15): array
    {
        return DB::table('ctz as c')
            ->leftJoin('cliente as cl', 'cl.cliente_id', '=', 'c.fk_cliente_id')
            ->leftJoin('sistema as si', 'si.sistema_id', '=', 'c.fk_sistema_id')
            ->where('c.fk_usuario_id', (int) $usuario->usuario_id)
            ->where('c.fk_agrupado_id', 0)
            ->orderByDesc('c.ctz_id')
            ->limit($limite)
            ->get(['c.ctz_id', 'c.tipocodigo', 'c.codigo', 'c.fk_filestatus_id', 'c.fecha_alta', 'c.fecha_vencimiento', 'c.titular_nombre', 'c.titular_apellido', 'c.fk_moneda_id', 'c.total', 'cl.cliente_nombre', 'si.sistema_nombre'])
            ->map(fn ($c) => [
                'id' => (int) $c->ctz_id,
                'area' => $c->sistema_nombre,
                'codigo' => "{$c->tipocodigo}-{$c->codigo}",
                'status' => $c->fk_filestatus_id,
                'cliente' => $c->cliente_nombre,
                'titular' => trim("{$c->titular_apellido}, {$c->titular_nombre}", ', '),
                'fecha' => $this->dmy($c->fecha_alta),
                'vencimiento' => $this->dmy($c->fecha_vencimiento),
                'moneda' => $c->fk_moneda_id,
                'total' => (float) $c->total,
                'href' => "/reserva/editarctz/{$c->ctz_id}",
            ])
            ->all();
    }

    /** Filas de ReservaListadoService reducidas a lo que muestra tablareservas.php. */
    private function filas(array $resultado): array
    {
        $registros = $resultado['registros'];
        $items = is_array($registros) ? $registros : $registros->items();

        return array_map(fn ($r) => [
            'id' => (int) ($r['id'] ?? 0),
            'area' => $r['icono'] ?? '',
            'codigo' => $r['ncodigo'] ?? ($r['codigo'] ?? ''),
            'status' => $r['status'] ?? '',
            'cliente' => $r['cliente_nombre'] ?? '',
            'usuario' => $r['usuario'] ?? '',
            'titular' => $r['titular'] ?? '',
            'fecha' => $r['fecha_alta'] ?? '',
            'vencimiento' => $r['fecha_vencimiento'] ?? '',
            'moneda' => $r['moneda'] ?? '',
            'total' => (float) ($r['total'] ?? 0),
            'href' => '/reserva/editar/'.(int) ($r['id'] ?? 0),
        ], is_array($items) ? array_values($items) : []);
    }

    private function periodo(string $periodo): array
    {
        $intervalo = self::PERIODOS[$periodo] ?? self::PERIODOS['anio'];
        $desde = match ($periodo) {
            'semana' => now()->subWeeks(2),
            'mes' => now()->subMonth(),
            'seis' => now()->subMonths(6),
            default => now()->subYear(),
        };
        $formato = match ($periodo) {
            'semana' => 'd/m/Y',
            'mes' => 'd/m',
            default => 'M-Y',
        };

        return [$desde->toDateString(), $formato];
    }

    private function porSistema($q, ?int $sistema, string $alias = ''): void
    {
        if ($sistema === null || $sistema === 0) {
            return;
        }
        $p = $alias !== '' ? "{$alias}." : '';
        $q->where(fn ($w) => $w->where(fn ($x) => $x->where("{$p}fk_sistema_id", $sistema)->where("{$p}fk_sistemaaplicacion_id", 0))->orWhere("{$p}fk_sistemaaplicacion_id", $sistema));
    }

    /** Cotización de referencia: USD, si no REC, si no 1 (dashboard.php:104-114). */
    private function cotizacionReferencia(): float
    {
        foreach (['USD', 'REC'] as $m) {
            $c = $this->cotizaciones->aLaVenta($m);
            if ($c > 0) {
                return $c;
            }
        }

        return 1.0;
    }

    private function relacion(string $moneda, float $cotUsd): float
    {
        $cotiza = $moneda !== '' ? $this->cotizaciones->aLaVenta($moneda) : 0;
        if ($cotiza == 0) {
            $cotiza = 1;
        }

        return $cotUsd > 0 ? $cotiza / $cotUsd : 1;
    }

    private function dmy(?string $fecha): string
    {
        if (! $fecha || str_starts_with($fecha, '0000')) {
            return '';
        }

        return substr($fecha, 8, 2).'/'.substr($fecha, 5, 2).'/'.substr($fecha, 0, 4);
    }
}
