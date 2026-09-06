<?php

namespace App\Http\Controllers\Web\Reportes;

use App\Services\CatalogosService;
use Illuminate\Support\Facades\DB;

/**
 * Administración > Reportes > Pendientes de factura (CI: dashboard/afacturarmt,
 * que sólo bajaba un CSV): servicios no cancelados de files no cancelados que
 * no tienen ninguna factura relacionada. En pantalla se corta en $limite; el
 * CSV sale completo. Los filtros son un agregado (el legacy no tenía).
 */
class PendientesFacturaController extends ReporteController
{
    protected string $titulo = 'Pendientes de factura';

    protected string $ruta = 'admin/reportes/pendientes-factura';

    protected bool $requiereFiltros = false;

    protected int $limite = 500;

    public function __construct(protected CatalogosService $catalogos) {}

    protected function filtros(): array
    {
        return [
            ['campo' => 'fk_cliente_id', 'label' => 'Cliente', 'tipo' => 'select', 'opciones' => $this->catalogos->clientes()],
            ['campo' => 'fecha_alta', 'label' => 'Alta del file', 'tipo' => 'rango'],
            ['campo' => 'codigo', 'label' => 'File (código)', 'tipo' => 'text'],
        ];
    }

    protected function columnas(): array
    {
        return [
            ['campo' => 'file', 'label' => 'File', 'link' => 'file_link'],
            ['campo' => 'tipo', 'label' => 'Tipo servicio'],
            ['campo' => 'status', 'label' => 'Status'],
            ['campo' => 'servicio_id', 'label' => 'ID servicio'],
            ['campo' => 'servicio', 'label' => 'Servicio'],
            ['campo' => 'vendedor', 'label' => 'Vendedor'],
            ['campo' => 'creado_por', 'label' => 'Creado por'],
            ['campo' => 'moneda', 'label' => 'Moneda'],
            ['campo' => 'total', 'label' => 'Total', 'tipo' => 'num', 'total' => true],
            ['campo' => 'cliente', 'label' => 'Cliente'],
            ['campo' => 'fecha_alta', 'label' => 'Alta file'],
            ['campo' => 'negocio', 'label' => 'Negocio'],
            ['campo' => 'proveedor', 'label' => 'Proveedor'],
            ['campo' => 'proveedor_rut', 'label' => 'RUT proveedor'],
        ];
    }

    protected function consultar(array $f): array
    {
        $q = DB::table('servicio as s')
            ->join('reserva as r', 'r.reserva_id', '=', 's.fk_reserva_id')
            ->join('cliente as c', 'c.cliente_id', '=', 'r.fk_cliente_id')
            ->join('usuario as u', 'u.usuario_id', '=', 'r.fk_usuario_id')
            ->leftJoin('usuario as v', 'v.usuario_id', '=', 'r.agente')
            ->leftJoin('proveedor as p', 'p.proveedor_id', '=', 's.fk_proveedor_id')
            ->leftJoin('negocio as n', 'n.negocio_id', '=', 'r.fk_negocio_id')
            ->where('r.fk_filestatus_id', '<>', 'CA')->where('s.status', '<>', 'CA')->where('r.titular_nombre', '<>', 'FXC MIGRADO')
            ->whereNotIn('s.servicio_id', fn ($sub) => $sub->selectRaw('DISTINCT fk_servicio_id')->from('rel_serviciofactura'))
            ->select('r.reserva_id', 'r.tipocodigo', 'r.codigo', 's.fk_tipoproducto_id', 's.status', 's.servicio_id', 's.servicio_nombre', 's.fk_moneda_id', 's.total', 'r.fecha_alta',
                'c.cliente_nombre', 'n.negocio_nombre', 'p.proveedor_nombre', 'p.cuit',
                DB::raw("CONCAT(COALESCE(v.usuario_nombre, ''), ' ', COALESCE(v.usuario_apellido, '')) AS vendedor"),
                DB::raw("CONCAT(u.usuario_nombre, ' ', u.usuario_apellido) AS creadopor"))
            ->orderByDesc('r.reserva_id')->orderBy('s.servicio_id');

        if ($f['fk_cliente_id'] !== '') {
            $q->where('r.fk_cliente_id', (int) $f['fk_cliente_id']);
        }
        if ($f['codigo'] !== '') {
            $q->where('r.codigo', $f['codigo']);
        }
        $this->rango($q, 'r.fecha_alta', $f, 'fecha_alta', true);
        $this->limitar($q);

        return $q->get()->map(fn ($x) => [
            'file' => "{$x->tipocodigo}-{$x->codigo}",
            'file_link' => "/app/reservas/{$x->reserva_id}",
            'tipo' => $x->fk_tipoproducto_id,
            'status' => $x->status,
            'servicio_id' => (int) $x->servicio_id,
            'servicio' => $x->servicio_nombre,
            'vendedor' => trim((string) $x->vendedor),
            'creado_por' => trim((string) $x->creadopor),
            'moneda' => $x->fk_moneda_id,
            'total' => (float) $x->total,
            'cliente' => $x->cliente_nombre,
            'fecha_alta' => $this->dmy((string) $x->fecha_alta),
            'negocio' => (string) $x->negocio_nombre,
            'proveedor' => (string) $x->proveedor_nombre,
            'proveedor_rut' => (string) $x->cuit,
        ])->all();
    }
}
