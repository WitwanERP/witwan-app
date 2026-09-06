<?php

namespace App\Http\Controllers\Web\Reportes;

use App\Services\CatalogosService;
use App\Services\CotizacionService;
use Illuminate\Support\Facades\DB;

/**
 * Administración > Reportes > Provisión de deuda (CI: reportes/provisiondeuda).
 * Servicios vigentes sin orden de pago: costo total convertido a la moneda
 * básica con la cotización de venta del día (el `_ctz` del Admin_Controller),
 * menos lo ya facturado por el proveedor => provisión.
 */
class ProvisionDeudaController extends ReporteController
{
    protected string $titulo = 'Provisión de deuda';

    protected string $ruta = 'admin/reportes/provision-deuda';

    protected ?string $agruparPor = null;

    public function __construct(protected CatalogosService $catalogos, protected CotizacionService $cotizaciones) {}

    protected function filtros(): array
    {
        return [
            ['campo' => 'fk_proveedor_id', 'label' => 'Proveedor', 'tipo' => 'select', 'opciones' => $this->catalogos->proveedores()],
            ['campo' => 'vigencia_ini', 'label' => 'Fecha contable de la factura', 'tipo' => 'rango'],
        ];
    }

    protected function columnas(): array
    {
        $basica = $this->cotizaciones->monedaBasica();

        return [
            ['campo' => 'codigo', 'label' => 'File'],
            ['campo' => 'proveedor_nombre', 'label' => 'Proveedor'],
            ['campo' => 'moneda', 'label' => 'Moneda'],
            ['campo' => 'facturas', 'label' => 'Factura(s)'],
            ['campo' => 'costo', 'label' => 'Monto (costo + IVA)', 'tipo' => 'num'],
            ['campo' => 'ars', 'label' => "Monto {$basica}", 'tipo' => 'num', 'total' => true],
            ['campo' => 'arsfc', 'label' => "Facturado {$basica}", 'tipo' => 'num', 'total' => true],
            ['campo' => 'provision', 'label' => 'Provisión', 'tipo' => 'num', 'total' => true],
        ];
    }

    protected function consultar(array $f): array
    {
        $sinOrden = DB::table('rel_ordenadminocupacion as roa')
            ->join('ordenadmin as oa', 'oa.ordenadmin_id', '=', 'roa.fk_ordenadmin_id')
            ->where('oa.tipo', 'P')
            ->select('roa.fk_ocupacion_id')->distinct();

        $q = DB::table('servicio as s')
            ->join('reserva as r', 'r.reserva_id', '=', 's.fk_reserva_id')
            ->join('proveedor as p', 'p.proveedor_id', '=', 's.fk_proveedor_id')
            ->whereNotIn('s.status', ['CA', 'RQ'])
            ->where('s.fk_reserva_id', '<>', 1)
            ->whereNotIn('s.servicio_id', $sinOrden)
            ->where('s.vigencia_ini', '>=', '2015-01-01')
            ->select('s.servicio_id', 's.fk_reserva_id', 's.costo', 's.iva_costo', 's.moneda_costo', 'r.tipocodigo', 'r.codigo', 'p.proveedor_nombre',
                DB::raw("IF(s.moneda_costo = '', s.fk_moneda_id, s.moneda_costo) AS mcosto"));
        if ($f['fk_proveedor_id'] !== '') {
            $q->where('s.fk_proveedor_id', (int) $f['fk_proveedor_id']);
        }

        $servicios = $q->orderBy('r.codigo')->orderBy('s.servicio_id')->get();
        $facturas = $this->facturasPorServicio($servicios->pluck('servicio_id')->all(), $f);
        $ctz = [];

        return $servicios->map(function ($x) use ($facturas, &$ctz) {
            $mon = (string) ($x->mcosto ?: 'ARS');
            $ctz[$mon] ??= $this->cotizaciones->aLaVenta($mon);
            $costo = (float) $x->costo + (float) $x->iva_costo;
            $ars = round($costo * $ctz[$mon], 2);
            $fc = $facturas[$x->servicio_id] ?? ['nros' => [], 'arsfc' => 0.0];

            return [
                'codigo' => "{$x->tipocodigo}-{$x->codigo}",
                'fk_reserva_id' => (int) $x->fk_reserva_id,
                'proveedor_nombre' => $x->proveedor_nombre,
                'moneda' => $x->moneda_costo,
                'costo' => round($costo, 2),
                'ars' => $ars,
                'arsfc' => $fc['arsfc'],
                'facturas' => implode(', ', $fc['nros']),
                'provision' => round($ars - $fc['arsfc'], 2),
            ];
        })->all();
    }

    /**
     * Facturas de proveedor imputadas a cada servicio (filtradas por fecha
     * contable). Como el legacy, el monto facturado que queda es el de la
     * ÚLTIMA factura, no la suma.
     *
     * @return array<int,array{nros:list<string>,arsfc:float}>
     */
    protected function facturasPorServicio(array $servicioIds, array $f): array
    {
        if ($servicioIds === []) {
            return [];
        }
        $q = DB::table('rel_facturaproveedorocupacion as rfo')
            ->join('facturaproveedor as fp', 'fp.facturaproveedor_id', '=', 'rfo.fk_facturaproveedor_id')
            ->whereIn('rfo.fk_ocupacion_id', $servicioIds)
            ->orderBy('fp.facturaproveedor_id')
            ->select('rfo.fk_ocupacion_id', 'rfo.monto', 'fp.cotizacion', 'fp.facturaproveedor_nro', 'fp.facturaproveedor_id');
        $this->rango($q, 'fp.fechacontable', $f, 'vigencia_ini');

        $out = [];
        $vistas = [];
        foreach ($q->get() as $r) {
            $clave = "{$r->fk_ocupacion_id}-{$r->facturaproveedor_id}";
            if (isset($vistas[$clave])) {
                continue;
            }
            $vistas[$clave] = true;
            $out[$r->fk_ocupacion_id] ??= ['nros' => [], 'arsfc' => 0.0];
            $out[$r->fk_ocupacion_id]['nros'][] = (string) $r->facturaproveedor_nro;
            $out[$r->fk_ocupacion_id]['arsfc'] = round((float) $r->monto * (float) $r->cotizacion, 2);
        }

        return $out;
    }
}
