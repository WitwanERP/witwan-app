<?php

namespace App\Http\Controllers\Web\Reportes;

use App\Services\CotizacionService;
use Illuminate\Support\Facades\DB;

/**
 * Administración > Contabilidad > Reporte diferencia de cambio (CI:
 * reportes/reportedifcambio): órdenes de pago del período con su cotización
 * (si la OP está en moneda básica con cotización 1, toma la de costo del USD
 * a la fecha) y la factura de venta asociada al servicio.
 */
class DiferenciaCambioController extends ReporteController
{
    protected string $titulo = 'Reporte diferencia de cambio';

    protected string $ruta = 'admin/reportes/diferencia-cambio';

    protected string $grupo = 'Contabilidad';

    protected ?string $agruparPor = null;

    public function __construct(private CotizacionService $cotizaciones) {}

    protected function filtros(): array
    {
        return [['campo' => 'fecha', 'label' => 'Fecha de la orden de pago', 'tipo' => 'rango']];
    }

    protected function columnas(): array
    {
        return [
            ['campo' => 'codigo', 'label' => 'N° de servicio'],
            ['campo' => 'nropago', 'label' => 'N° de pago'],
            ['campo' => 'cliente', 'label' => 'Cliente'],
            ['campo' => 'proveedor', 'label' => 'Proveedor'],
            ['campo' => 'factura_cliente', 'label' => 'Cliente de la factura'],
            ['campo' => 'tipo', 'label' => 'Tipo de producto'],
            ['campo' => 'fecha_orden', 'label' => 'Fecha orden'],
            ['campo' => 'fecha_factura', 'label' => 'Fecha factura'],
            ['campo' => 'cotizacion', 'label' => 'Tipo de cambio', 'tipo' => 'num'],
            ['campo' => 'moneda', 'label' => 'Moneda'],
            ['campo' => 'costo', 'label' => 'Costo', 'tipo' => 'num', 'total' => true],
            ['campo' => 'iva', 'label' => 'IVA', 'tipo' => 'num', 'total' => true],
        ];
    }

    protected function consultar(array $f): array
    {
        // El legacy sólo ejecuta la consulta cuando viene la fecha "hasta".
        if ($f['fecha_to'] === '') {
            return [];
        }
        $basica = $this->cotizaciones->monedaBasica();
        $q = DB::table('ordenadmin as oa')
            ->join('rel_ordenadminocupacion as ro', 'ro.fk_ordenadmin_id', '=', 'oa.ordenadmin_id')
            ->join('servicio as s', 's.servicio_id', '=', 'ro.fk_ocupacion_id')
            ->leftJoin('reserva as r', 'r.reserva_id', '=', 's.fk_reserva_id')
            ->leftJoin('rel_serviciofactura as rsf', 'rsf.fk_servicio_id', '=', 's.servicio_id')
            ->leftJoin('factura as fc', 'fc.factura_id', '=', 'rsf.fk_factura_id')
            ->join('proveedor as p', 'p.proveedor_id', '=', 's.fk_proveedor_id')
            ->join('cliente as c', 'c.cliente_id', '=', 'r.fk_cliente_id')
            ->leftJoin('cliente as fcc', 'fcc.cliente_id', '=', 'fc.fk_cliente_id')
            ->where('oa.tipo', 'P')->where('oa.status', '<>', 'AN')
            ->select('r.tipocodigo', 'r.codigo', 'c.cliente_nombre', 's.costo', 's.iva_costo', 's.fk_tipoproducto_id', 'p.proveedor_nombre', 'oa.nropago', 'oa.fecha as fecha_ordenadmin', 'oa.cotizacion', 'oa.fk_moneda_id', 'ro.monto', 'fcc.cliente_nombre as factura_cliente', 'fc.factura_fecha');
        $this->rango($q, 'oa.fecha', $f, 'fecha', true);

        return $q->get()->map(function ($o) use ($basica) {
            $cot = (float) $o->cotizacion;
            if ($o->fk_moneda_id === $basica && $cot == 1) {
                $cot = $this->cotizaciones->alCosto('USD', (string) $o->fecha_ordenadmin);
            }

            return [
                'codigo' => "{$o->tipocodigo}-{$o->codigo}",
                'nropago' => $o->nropago,
                'cliente' => $o->cliente_nombre,
                'proveedor' => $o->proveedor_nombre,
                'factura_cliente' => $o->factura_cliente,
                'tipo' => $o->fk_tipoproducto_id,
                'fecha_orden' => $this->dmy((string) $o->fecha_ordenadmin),
                'fecha_factura' => $this->dmy((string) $o->factura_fecha),
                'cotizacion' => $cot,
                'moneda' => $o->fk_moneda_id,
                'costo' => (float) $o->costo,
                'iva' => (float) $o->iva_costo,
            ];
        })->all();
    }
}
