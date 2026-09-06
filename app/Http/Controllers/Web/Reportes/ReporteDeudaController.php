<?php

namespace App\Http\Controllers\Web\Reportes;

use App\Services\CatalogosService;
use App\Services\Reservas\ReservaCobradoService;
use Illuminate\Support\Facades\DB;

/**
 * Administración > Reportes > Reporte de deuda (CI: administracion/reportedeuda).
 * El saldo se calcula con el cobrado real (ReservaCobradoService, réplica de
 * reserva_model::cobrado()), no con reserva.cobrado.
 */
class ReporteDeudaController extends ReporteController
{
    protected string $titulo = 'Reporte de deuda';

    protected string $ruta = 'admin/reportes/deuda';

    public function __construct(private CatalogosService $catalogos, private ReservaCobradoService $cobrados) {}

    protected function filtros(): array
    {
        return [
            ['campo' => 'codigo', 'label' => 'Código', 'tipo' => 'text'],
            ['campo' => 'fk_cliente_id', 'label' => 'Cliente', 'tipo' => 'select', 'opciones' => $this->catalogos->clientes()],
            ['campo' => 'agente', 'label' => 'Vendedor', 'tipo' => 'select', 'opciones' => $this->catalogos->usuariosInternos()],
            ['campo' => 'titular', 'label' => 'Titular', 'tipo' => 'text'],
            ['campo' => 'fecha_alta', 'label' => 'Fecha de alta', 'tipo' => 'rango'],
            ['campo' => 'fecha_vencimiento', 'label' => 'Fecha de vencimiento', 'tipo' => 'rango'],
            ['campo' => 'inicio', 'label' => 'Fecha de check-in', 'tipo' => 'rango'],
            ['campo' => 'saldo_0', 'label' => 'Mostrar saldos en 0', 'tipo' => 'bool', 'default' => '0'],
        ];
    }

    protected function columnas(): array
    {
        return [
            ['campo' => 'cliente', 'label' => 'Cliente'],
            ['campo' => 'n_file', 'label' => 'File'],
            ['campo' => 'itr', 'label' => 'ITR'],
            ['campo' => 'usuario', 'label' => 'Vendedor'],
            ['campo' => 'titular', 'label' => 'Titular'],
            ['campo' => 'servicios', 'label' => 'Servicios', 'tipo' => 'pre'],
            ['campo' => 'fecha_alta', 'label' => 'Fecha alta'],
            ['campo' => 'fecha_vencimiento', 'label' => 'Vencimiento para cobro'],
            ['campo' => 'inicio', 'label' => 'Check-in'],
            ['campo' => 'moneda', 'label' => 'Moneda'],
            ['campo' => 'total', 'label' => 'Total file', 'tipo' => 'num', 'total' => true],
            ['campo' => 'saldo', 'label' => 'Saldo pendiente', 'tipo' => 'num', 'total' => true],
        ];
    }

    protected function consultar(array $f): array
    {
        $q = DB::table('reserva as r')
            ->join('cliente as c', 'c.cliente_id', '=', 'r.fk_cliente_id')
            ->leftJoin('usuario as u', 'u.usuario_id', '=', 'r.agente')
            ->whereNotIn('r.fk_filestatus_id', ['CA', 'AG'])
            ->select([
                'r.reserva_id', 'r.tipocodigo', 'r.codigo', 'r.fecha_alta', 'r.titular_apellido', 'r.titular_nombre', 'r.fecha_vencimiento',
                'c.cliente_nombre', 'r.inicio', 'r.fk_moneda_id', 'r.total', 'r.codigo_externo',
                DB::raw("CONCAT(u.usuario_apellido, ', ', u.usuario_nombre) AS usuario_fullname"),
                DB::raw("(SELECT GROUP_CONCAT(sv.servicio_nombre SEPARATOR '\n') FROM servicio sv WHERE sv.fk_reserva_id = r.reserva_id AND sv.status <> 'CA') AS servicio_nombres"),
            ]);

        if ($f['codigo'] !== '') {
            $q->where('r.codigo', $f['codigo']);
        }
        if ($f['fk_cliente_id'] !== '') {
            $q->where('c.cliente_id', (int) $f['fk_cliente_id']);
        }
        if ($f['agente'] !== '') {
            $q->where('r.agente', (int) $f['agente']);
        }
        if ($f['titular'] !== '') {
            $q->where(fn ($w) => $w->where('r.titular_apellido', 'LIKE', "%{$f['titular']}%")->orWhere('r.titular_nombre', 'LIKE', "%{$f['titular']}%"));
        }
        $this->rango($q, 'r.fecha_alta', $f, 'fecha_alta');
        $this->rango($q, 'r.fecha_vencimiento', $f, 'fecha_vencimiento');
        $this->rango($q, 'r.inicio', $f, 'inicio');
        if ($f['saldo_0'] === '0') {
            $q->whereRaw('(r.total - r.cobrado) <> 0');
        }

        $filas = [];
        foreach ($q->orderBy('r.fk_moneda_id')->orderBy('r.codigo')->orderByDesc('r.fecha_alta')->get() as $x) {
            $cobrado = $this->cobrados->total((int) $x->reserva_id, $x->fk_moneda_id);
            $saldo = round((float) $x->total - $cobrado, 2);
            if ($f['saldo_0'] === '0' && $saldo == 0) {
                continue;
            }
            $filas[] = [
                'cliente' => $x->cliente_nombre,
                'n_file' => "{$x->tipocodigo}-{$x->codigo}",
                'itr' => $x->codigo_externo,
                'usuario' => trim((string) $x->usuario_fullname, ' ,'),
                'titular' => trim("{$x->titular_apellido} {$x->titular_nombre}"),
                'servicios' => $x->servicio_nombres,
                'fecha_alta' => $this->dmy($x->fecha_alta),
                'fecha_vencimiento' => $this->dmy($x->fecha_vencimiento),
                'inicio' => $this->dmy($x->inicio),
                'moneda' => $x->fk_moneda_id,
                'total' => (float) $x->total,
                'saldo' => $saldo,
            ];
        }

        return $filas;
    }
}
