<?php

namespace App\Http\Controllers\Web\Reservas;

use App\Http\Controllers\Web\Operaciones\OperacionesController;
use App\Http\Controllers\Web\Reportes\ReporteController;
use App\Services\CatalogosService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Reservas > Cotizaciones por área (CI: reserva/cotizaciones/{area}). Las
 * cotizaciones viven en tablas espejo (`ctz` / `servicioctz`) y el legacy las
 * lista con el mismo reserva_model en modo `_cts`. Acá es un listado de
 * lectura con links al legacy para editar / pasar a reserva.
 */
class CotizacionesListadoController extends ReporteController
{
    protected string $titulo = 'Cotizaciones';

    protected string $ruta = 'cotizaciones/receptivo';

    protected string $area = 'Reservas';

    protected string $grupo = '';

    protected ?string $agruparPor = null;

    protected bool $requiereFiltros = false;

    protected int $limite = 500;

    private string $areaSlug = 'receptivo';

    public function __construct(private CatalogosService $catalogos) {}

    protected function preparar(Request $request): void
    {
        $this->areaSlug = (string) $request->route('area');
        abort_unless(isset(OperacionesController::AREAS[$this->areaSlug]) || $this->areaSlug === 'all', 404);
        $this->ruta = "cotizaciones/{$this->areaSlug}";
        $this->titulo = 'Cotizaciones'.($this->areaSlug !== 'all' ? ' ('.ucfirst($this->areaSlug).')' : '');
    }

    protected function filtros(): array
    {
        return [
            ['campo' => 'codigo', 'label' => 'Código', 'tipo' => 'text'],
            ['campo' => 'titular', 'label' => 'Titular', 'tipo' => 'text'],
            ['campo' => 'cliente', 'label' => 'Cliente', 'tipo' => 'select', 'opciones' => $this->catalogos->clientes()],
            ['campo' => 'vendedor', 'label' => 'Vendedor', 'tipo' => 'select', 'opciones' => $this->catalogos->usuariosInternos()],
            ['campo' => 'fecha_alta', 'label' => 'Fecha de alta (sin fecha: últimos 6 meses)', 'tipo' => 'rango'],
            ['campo' => 'checkin', 'label' => 'Check-in (primer servicio)', 'tipo' => 'rango'],
        ];
    }

    protected function columnas(): array
    {
        return [
            ['campo' => 'codigo', 'label' => 'Código'],
            ['campo' => 'fecha_alta', 'label' => 'Fecha alta'],
            ['campo' => 'titular', 'label' => 'Titular'],
            ['campo' => 'cliente', 'label' => 'Cliente'],
            ['campo' => 'vendedor', 'label' => 'Vendedor'],
            ['campo' => 'checkin', 'label' => 'Check-in'],
            ['campo' => 'pax', 'label' => 'Pax', 'tipo' => 'num'],
            ['campo' => 'servicios', 'label' => 'Servicios', 'tipo' => 'pre'],
            ['campo' => 'moneda', 'label' => 'Moneda'],
            ['campo' => 'total', 'label' => 'Total', 'tipo' => 'num', 'total' => true],
            ['campo' => 'acciones', 'label' => 'Acciones', 'tipo' => 'acciones'],
        ];
    }

    protected function accionesGlobales(): array
    {
        $area = $this->areaSlug === 'all' ? 'receptivo' : $this->areaSlug;

        return [['label' => 'Nueva cotización (sistema anterior)', 'href' => "/reserva/nueva/{$area}", 'target' => '_blank']];
    }

    protected function consultar(array $f): array
    {
        if ($f['fecha_alta'] === '' && $f['fecha_alta_to'] === '' && $f['codigo'] === '' && $f['titular'] === '' && $f['cliente'] === '' && $f['vendedor'] === '' && $f['checkin'] === '' && $f['checkin_to'] === '') {
            $f['fecha_alta'] = now()->subMonths(6)->toDateString();
        }

        $q = DB::table('ctz as c')
            ->leftJoin('cliente as cl', 'cl.cliente_id', '=', 'c.fk_cliente_id')
            ->leftJoin('usuario as u', 'u.usuario_id', '=', 'c.fk_usuario_id')
            ->leftJoin('servicioctz as s', 's.fk_reserva_id', '=', 'c.ctz_id')
            ->where('c.fk_agrupado_id', 0)
            ->groupBy('c.ctz_id')
            ->orderByDesc('c.ctz_id')
            ->select('c.ctz_id', 'c.tipocodigo', 'c.codigo', 'c.fecha_alta', 'c.titular_nombre', 'c.titular_apellido', 'c.fk_moneda_id', 'c.total', 'cl.cliente_nombre',
                DB::raw("CONCAT(u.usuario_nombre, ' ', u.usuario_apellido) AS vendedor"),
                DB::raw("MIN(NULLIF(s.vigencia_ini, '0000-00-00')) AS checkin"),
                DB::raw('MAX(s.adultos + s.menores) AS pax'),
                DB::raw("GROUP_CONCAT(DISTINCT s.servicio_nombre SEPARATOR '\n') AS servicios"));

        if ($this->areaSlug !== 'all') {
            $sistema = OperacionesController::AREAS[$this->areaSlug];
            $q->where(fn ($w) => $w->where(fn ($x) => $x->where('c.fk_sistema_id', $sistema)->where('c.fk_sistemaaplicacion_id', 0))->orWhere('c.fk_sistemaaplicacion_id', $sistema));
        }
        if ($f['codigo'] !== '') {
            $q->where('c.codigo', $f['codigo']);
        }
        if ($f['titular'] !== '') {
            $q->where(fn ($w) => $w->where('c.titular_apellido', 'LIKE', "%{$f['titular']}%")->orWhere('c.titular_nombre', 'LIKE', "%{$f['titular']}%"));
        }
        if ($f['cliente'] !== '') {
            $q->where('c.fk_cliente_id', (int) $f['cliente']);
        }
        if ($f['vendedor'] !== '') {
            $q->where('c.fk_usuario_id', (int) $f['vendedor']);
        }
        $this->rango($q, 'c.fecha_alta', $f, 'fecha_alta');
        if ($f['checkin'] !== '' || $f['checkin_to'] !== '') {
            $q->havingRaw('checkin IS NOT NULL');
            if ($f['checkin'] !== '') {
                $q->havingRaw('checkin >= ?', [$f['checkin']]);
            }
            if ($f['checkin_to'] !== '') {
                $q->havingRaw('checkin <= ?', [$f['checkin_to']]);
            }
        }
        $this->limitar($q);

        return $q->get()->map(fn ($x) => [
            'codigo' => "{$x->tipocodigo}-{$x->codigo}",
            'fecha_alta' => $this->dmy($x->fecha_alta),
            'titular' => trim("{$x->titular_apellido}, {$x->titular_nombre}", ', '),
            'cliente' => $x->cliente_nombre,
            'vendedor' => trim((string) $x->vendedor),
            'checkin' => $this->dmy($x->checkin),
            'pax' => (int) $x->pax,
            'servicios' => (string) $x->servicios,
            'moneda' => $x->fk_moneda_id,
            'total' => (float) $x->total,
            'acciones' => [
                ['label' => 'Editar', 'href' => "/reserva/editarctz/{$x->ctz_id}"],
                ['label' => 'Pasar a reserva', 'href' => "/reserva/ctzareserva/{$x->ctz_id}"],
            ],
        ])->all();
    }
}
