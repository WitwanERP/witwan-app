<?php

namespace App\Http\Controllers\Web\Operaciones;

use App\Http\Controllers\Web\Reportes\ReporteController;
use Illuminate\Support\Facades\DB;

/**
 * Operaciones > Cerrar grupo (CI: operaciones/cierregrupo/cerrar): servicios
 * de un negocio (grupo) con venta y costo convertidos a la moneda local, para
 * el cierre. Réplica del SQL del legacy, que convierte a CLP con las
 * cotizaciones guardadas en el servicio (cotventa / cotcosto).
 */
class CierreGrupoController extends ReporteController
{
    protected string $titulo = 'Cerrar grupo';

    protected string $ruta = 'operaciones/cierre-grupo';

    protected string $area = 'Operaciones';

    protected string $grupo = '';

    protected ?string $agruparPor = null;

    protected function filtros(): array
    {
        $negocios = DB::table('negocio as n')
            ->join('reserva as r', 'r.fk_negocio_id', '=', 'n.negocio_id')
            ->groupBy('n.negocio_id', 'n.negocio_nombre')
            ->orderBy('n.negocio_nombre')
            ->get(['n.negocio_id', 'n.negocio_nombre'])
            ->map(fn ($n) => ['value' => (int) $n->negocio_id, 'label' => $n->negocio_nombre])
            ->all();

        return [
            ['campo' => 'negocio', 'label' => 'Negocio / grupo', 'tipo' => 'select', 'opciones' => $negocios],
        ];
    }

    protected function columnas(): array
    {
        return [
            ['campo' => 'file', 'label' => 'N° file'],
            ['campo' => 'cliente', 'label' => 'Cliente'],
            ['campo' => 'producto', 'label' => 'Producto'],
            ['campo' => 'pax', 'label' => 'Pax', 'tipo' => 'num'],
            ['campo' => 'tipo', 'label' => 'Tipo servicio'],
            ['campo' => 'proveedor', 'label' => 'Proveedor'],
            ['campo' => 'status', 'label' => 'Status'],
            ['campo' => 'moneda', 'label' => 'Moneda'],
            ['campo' => 'tc', 'label' => 'TC', 'tipo' => 'num'],
            ['campo' => 'venta_neta', 'label' => 'Vta neta', 'tipo' => 'num', 'total' => true],
            ['campo' => 'venta_iva', 'label' => 'Vta IVA', 'tipo' => 'num', 'total' => true],
            ['campo' => 'venta_total', 'label' => 'Vta total', 'tipo' => 'num', 'total' => true],
            ['campo' => 'costo_neto', 'label' => 'Cos. neto', 'tipo' => 'num', 'total' => true],
            ['campo' => 'costo_iva', 'label' => 'Cos. IVA', 'tipo' => 'num', 'total' => true],
            ['campo' => 'costo_total', 'label' => 'Cos. total', 'tipo' => 'num', 'total' => true],
            ['campo' => 'comision', 'label' => 'Comisión', 'tipo' => 'num', 'total' => true],
        ];
    }

    protected function consultar(array $f): array
    {
        if ($f['negocio'] === '') {
            return [];
        }

        $filas = DB::table('servicio as s')
            ->join('reserva as r', 'r.reserva_id', '=', 's.fk_reserva_id')
            ->join('cliente as c', 'c.cliente_id', '=', 'r.fk_cliente_id')
            ->join('submodulo as sm', 'sm.submodulo_id', '=', 's.fk_tipoproducto_id')
            ->leftJoin('proveedor as p', 'p.proveedor_id', '=', 's.fk_proveedor_id')
            ->leftJoin('pnraereo as pnr', 'pnr.fk_ocupacion_id', '=', 's.servicio_id')
            ->leftJoin('aerolinea as a', 'a.aerolinea_id', '=', 'pnr.fk_aerolinea_id')
            ->where('r.fk_negocio_id', (int) $f['negocio'])
            ->where('s.status', '<>', 'CA')
            ->orderBy('s.vigencia_ini')->orderByDesc('r.codigo')
            ->get([
                'r.reserva_id', 'c.cliente_nombre', 'r.tipocodigo', 'r.codigo', 's.servicio_id', 's.servicio_nombre', 's.fk_tipoproducto_id', 's.status',
                's.cotventa', 's.cotcosto', 's.moneda_costo', 's.fk_moneda_id', 's.costo', 's.iva_costo', 's.total', 's.iva',
                DB::raw('(s.adultos + s.menores) AS totalpax'),
                DB::raw('IF(ISNULL(a.aerolinea_nombre), p.proveedor_nombre, a.aerolinea_nombre) AS proveedor'),
                'sm.tipoproducto_tipo',
            ]);

        $comisiones = DB::table('servicio_extra')
            ->whereIn('fk_servicio_id', $filas->pluck('servicio_id'))
            ->where('extra_nombre', 'evt_comisioncosto')
            ->orderBy('regdate')
            ->get()
            ->keyBy('fk_servicio_id');

        return $filas->map(function ($x) use ($comisiones) {
            $tcCosto = $x->moneda_costo === 'CLP' ? 1 : (float) $x->cotcosto;
            $tcVenta = $x->fk_moneda_id === 'CLP' ? 1 : (float) $x->cotventa;
            $ventaTotal = (float) $x->total * $tcVenta;
            $ventaIva = (float) $x->iva * $tcVenta;
            $costoNeto = (float) $x->costo * $tcCosto;
            $costoIva = (float) $x->iva_costo * $tcCosto;

            return [
                'file' => "{$x->tipocodigo}-{$x->codigo}",
                'cliente' => $x->cliente_nombre,
                'producto' => $x->servicio_nombre,
                'pax' => (int) $x->totalpax,
                'tipo' => $x->fk_tipoproducto_id,
                'proveedor' => $x->proveedor,
                'status' => $x->status,
                'moneda' => $x->fk_moneda_id,
                'tc' => $tcVenta,
                'venta_neta' => round($ventaTotal - $ventaIva, 2),
                'venta_iva' => round($ventaIva, 2),
                'venta_total' => round($ventaTotal, 2),
                'costo_neto' => round($costoNeto, 2),
                'costo_iva' => round($costoIva, 2),
                'costo_total' => round($costoNeto + $costoIva, 2),
                'comision' => (float) ($comisiones[$x->servicio_id]->extra_valor ?? 0),
            ];
        })->all();
    }
}
