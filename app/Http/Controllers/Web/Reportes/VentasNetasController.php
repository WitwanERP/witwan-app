<?php

namespace App\Http\Controllers\Web\Reportes;

use App\Services\CatalogosService;
use Illuminate\Support\Facades\DB;

/** Administración > Reportes > Ventas netas (CI: administracion/ventasnetas). */
class VentasNetasController extends ReporteController
{
    protected string $titulo = 'Ventas netas';

    protected string $ruta = 'admin/reportes/ventas-netas';

    public function __construct(private CatalogosService $catalogos) {}

    protected function filtros(): array
    {
        return [
            ['campo' => 'codigo', 'label' => 'Código', 'tipo' => 'text'],
            ['campo' => 'fk_cliente_id', 'label' => 'Cliente', 'tipo' => 'select', 'opciones' => $this->catalogos->clientes()],
            ['campo' => 'fk_vendedor_id', 'label' => 'Vendedor', 'tipo' => 'select', 'opciones' => $this->catalogos->usuariosInternos()],
            ['campo' => 'fecha_alta', 'label' => 'Fecha de alta', 'tipo' => 'rango'],
            ['campo' => 'fecha_vencimiento', 'label' => 'Fecha de vencimiento', 'tipo' => 'rango'],
        ];
    }

    protected function columnas(): array
    {
        return [
            ['campo' => 'n_file', 'label' => 'N° file'],
            ['campo' => 'cliente', 'label' => 'Cliente'],
            ['campo' => 'usuario', 'label' => 'Vendedor'],
            ['campo' => 'fecha_alta', 'label' => 'Fecha alta'],
            ['campo' => 'servicios', 'label' => 'Servicio'],
            ['campo' => 'pais', 'label' => 'País'],
            ['campo' => 'ciudad', 'label' => 'Ciudad'],
            ['campo' => 'proveedor', 'label' => 'Proveedor'],
            ['campo' => 'promotor', 'label' => 'Promotor'],
            ['campo' => 'moneda_costo', 'label' => 'Mon. costo'],
            ['campo' => 'costo_neto', 'label' => 'Costo neto', 'tipo' => 'num', 'total' => true],
            ['campo' => 'comision', 'label' => 'Comisión', 'tipo' => 'num', 'total' => true],
            ['campo' => 'moneda', 'label' => 'Mon. venta'],
            ['campo' => 'venta_neta', 'label' => 'Venta neta', 'tipo' => 'num', 'total' => true],
            ['campo' => 'rentabilidad_neta', 'label' => 'Rentab. neta', 'tipo' => 'num', 'total' => true],
            ['campo' => 'porcentaje_rentabilidad_neta', 'label' => '% rent. neta', 'tipo' => 'num'],
            ['campo' => 'venta_total', 'label' => 'Venta total', 'tipo' => 'num', 'total' => true],
            ['campo' => 'rentabilidad_total', 'label' => 'Rentab. total', 'tipo' => 'num', 'total' => true],
            ['campo' => 'porcentaje_rentabilidad_total', 'label' => '% rent. total', 'tipo' => 'num'],
        ];
    }

    protected function consultar(array $f): array
    {
        $q = DB::table('servicio as s')
            ->join('reserva as r', 'r.reserva_id', '=', 's.fk_reserva_id')
            ->join('cliente as c', 'c.cliente_id', '=', 'r.fk_cliente_id')
            ->leftJoin('usuario as agente', 'agente.usuario_id', '=', 'r.agente')
            ->leftJoin('usuario as promotor', 'promotor.usuario_id', '=', 'r.promotor')
            ->leftJoin('proveedor as p', 'p.proveedor_id', '=', 's.fk_proveedor_id')
            ->leftJoin('ciudad as ci', 'ci.ciudad_id', '=', 's.fk_ciudad_id')
            ->leftJoin('pais as pa', 'pa.pais_id', '=', 'ci.fk_pais_id')
            ->where('s.status', '<>', 'CA')
            ->select([
                's.servicio_id', 's.servicio_nombre', 's.fk_moneda_id', 's.renta', 's.total', 's.iva', 's.comision', 's.costo', 's.moneda_costo',
                'r.tipocodigo', 'r.codigo', 'r.fecha_alta', 'c.cliente_nombre', 'p.proveedor_nombre', 'ci.ciudad_nombre', 'pa.pais_nombre',
                DB::raw("CONCAT(agente.usuario_apellido, ' ', agente.usuario_nombre) AS agente"),
                DB::raw("CONCAT(promotor.usuario_apellido, ' ', promotor.usuario_nombre) AS promotor"),
            ]);

        if ($f['codigo'] !== '') {
            $q->where('r.codigo', $f['codigo']);
        }
        if ($f['fk_cliente_id'] !== '') {
            $q->where('c.cliente_id', (int) $f['fk_cliente_id']);
        }
        if ($f['fk_vendedor_id'] !== '') {
            $q->where('r.agente', (int) $f['fk_vendedor_id']);
        }
        $this->rango($q, 'r.fecha_alta', $f, 'fecha_alta');
        $this->rango($q, 'r.fecha_vencimiento', $f, 'fecha_vencimiento');

        return $q->orderBy('s.fk_moneda_id')->orderBy('r.codigo')->orderByDesc('r.fecha_alta')->get()->map(function ($x) {
            $total = (float) $x->total;
            $iva = (float) $x->iva;
            $renta = (float) $x->renta;

            return [
                'n_file' => "{$x->tipocodigo}-{$x->codigo}",
                'cliente' => $x->cliente_nombre,
                'usuario' => trim((string) $x->agente),
                'fecha_alta' => $this->dmy($x->fecha_alta),
                'servicios' => $x->servicio_nombre,
                'pais' => $x->pais_nombre,
                'ciudad' => $x->ciudad_nombre,
                'proveedor' => $x->proveedor_nombre,
                'promotor' => trim((string) $x->promotor),
                'moneda_costo' => $x->moneda_costo,
                'costo_neto' => (float) $x->costo,
                'comision' => (float) $x->comision,
                'moneda' => $x->fk_moneda_id,
                'venta_neta' => round($total - $iva, 2),
                'rentabilidad_neta' => $renta,
                'porcentaje_rentabilidad_neta' => $total != 0 ? round($renta / $total * 100, 2) : 0,
                'venta_total' => $total,
                'rentabilidad_total' => round($renta + $iva, 2),
                'porcentaje_rentabilidad_total' => ($total + $iva) != 0 ? round(($renta + $iva) / ($total + $iva) * 100, 2) : 0,
            ];
        })->all();
    }
}
