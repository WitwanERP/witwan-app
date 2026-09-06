<?php

namespace App\Http\Controllers\Web\Reportes;

use Illuminate\Support\Facades\DB;

/**
 * Administración > Reportes > Pagos (CI: reportes/pagos): servicios CON orden
 * de pago; compara lo pagado (órdenes, convertido a moneda básica) contra lo
 * facturado por el proveedor.
 */
class PagosProveedoresController extends ProvisionDeudaController
{
    protected string $titulo = 'Pagos a proveedores';

    protected string $ruta = 'admin/reportes/pagos';

    protected function filtros(): array
    {
        return [
            ['campo' => 'fk_proveedor_id', 'label' => 'Proveedor', 'tipo' => 'select', 'opciones' => $this->catalogos->proveedores()],
            ['campo' => 'vigencia_ini', 'label' => 'Fecha in del servicio', 'tipo' => 'rango'],
        ];
    }

    protected function columnas(): array
    {
        $basica = $this->cotizaciones->monedaBasica();

        return [
            ['campo' => 'codigo', 'label' => 'File'],
            ['campo' => 'proveedor_nombre', 'label' => 'Proveedor'],
            ['campo' => 'moneda', 'label' => 'Moneda'],
            ['campo' => 'factura', 'label' => 'Factura'],
            ['campo' => 'costo', 'label' => 'Monto (costo + IVA)', 'tipo' => 'num'],
            ['campo' => 'ars', 'label' => "Monto {$basica}", 'tipo' => 'num', 'total' => true],
            ['campo' => 'pagado', 'label' => "Pagado {$basica}", 'tipo' => 'num', 'total' => true],
            ['campo' => 'arsfc', 'label' => "Facturado {$basica}", 'tipo' => 'num', 'total' => true],
            ['campo' => 'provision', 'label' => 'Pagado - facturado', 'tipo' => 'num', 'total' => true],
        ];
    }

    protected function consultar(array $f): array
    {
        $conOrden = DB::table('rel_ordenadminocupacion as roa')
            ->join('ordenadmin as oa', 'oa.ordenadmin_id', '=', 'roa.fk_ordenadmin_id')
            ->where('oa.tipo', 'P')
            ->select('roa.fk_ocupacion_id')->distinct();

        $q = DB::table('servicio as s')
            ->join('reserva as r', 'r.reserva_id', '=', 's.fk_reserva_id')
            ->join('proveedor as p', 'p.proveedor_id', '=', 's.fk_proveedor_id')
            ->whereNotIn('s.status', ['CA', 'RQ'])
            ->whereIn('s.servicio_id', $conOrden)
            ->select('s.servicio_id', 's.fk_reserva_id', 's.costo', 's.iva_costo', 's.moneda_costo', 'r.tipocodigo', 'r.codigo', 'p.proveedor_nombre',
                DB::raw("IF(s.moneda_costo = '', s.fk_moneda_id, s.moneda_costo) AS mcosto"));
        if ($f['fk_proveedor_id'] !== '') {
            $q->where('s.fk_proveedor_id', (int) $f['fk_proveedor_id']);
        }
        $this->rango($q, 's.vigencia_ini', $f, 'vigencia_ini');

        $servicios = $q->orderBy('r.codigo')->orderBy('s.servicio_id')->get();
        $ids = $servicios->pluck('servicio_id')->all();
        $facturas = $this->facturasPorServicio($ids, ['vigencia_ini' => '', 'vigencia_ini_to' => '']);
        $pagos = $this->pagadoPorServicio($ids);
        $basica = $this->cotizaciones->monedaBasica();
        $ctz = [];

        return $servicios->map(function ($x) use ($facturas, $pagos, &$ctz, $basica) {
            $mon = (string) ($x->mcosto ?: $basica);
            $ctz[$mon] ??= $this->cotizaciones->aLaVenta($mon);
            $costo = (float) $x->costo + (float) $x->iva_costo;
            $fc = $facturas[$x->servicio_id] ?? ['nros' => [], 'arsfc' => 0.0];
            $pagado = $pagos[$x->servicio_id] ?? 0.0;

            return [
                'codigo' => "{$x->tipocodigo}-{$x->codigo}",
                'proveedor_nombre' => $x->proveedor_nombre,
                'moneda' => $x->moneda_costo,
                'factura' => end($fc['nros']) ?: '',
                'costo' => round($costo, 2),
                'pagado' => round($pagado, 2),
                'ars' => round($costo * $ctz[$mon], 2),
                'arsfc' => $fc['arsfc'],
                'provision' => round($pagado - $fc['arsfc'], 2),
            ];
        })->all();
    }

    /**
     * Pagado por servicio: órdenes de pago imputadas, convertidas con la
     * cotización de la orden (o la de su orden padre); la moneda básica va 1:1.
     *
     * @return array<int,float>
     */
    private function pagadoPorServicio(array $servicioIds): array
    {
        if ($servicioIds === []) {
            return [];
        }
        $basica = $this->cotizaciones->monedaBasica();
        $filas = DB::table('rel_ordenadminocupacion as roa')
            ->join('ordenadmin as oa', 'oa.ordenadmin_id', '=', 'roa.fk_ordenadmin_id')
            ->join('ordenadmin as o2', 'oa.ordenadmin_id', '=', 'o2.fk_ordenadmin_id')
            ->where('oa.tipo', 'P')
            ->whereIn('roa.fk_ocupacion_id', $servicioIds)
            ->get(['roa.fk_ocupacion_id', 'roa.monto', 'roa.fk_moneda_id', 'oa.cotizacion', 'o2.cotizacion as cot2']);

        $out = [];
        foreach ($filas as $r) {
            $cot = (float) $r->cotizacion != 0 ? (float) $r->cotizacion : (float) $r->cot2;
            if ($r->fk_moneda_id === $basica) {
                $cot = 1;
            }
            $out[$r->fk_ocupacion_id] = ($out[$r->fk_ocupacion_id] ?? 0.0) + round((float) $r->monto * $cot, 2);
        }

        return $out;
    }
}
