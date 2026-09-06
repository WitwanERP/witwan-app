<?php

namespace App\Http\Controllers\Web\Reportes;

use App\Services\CatalogosService;
use App\Services\CotizacionService;
use Illuminate\Support\Facades\DB;

/**
 * Administración > Reportes > OP nacionales (CI: reportes/opnacionales):
 * órdenes de proveedores del país 1 con el monto en USD (cotización de venta
 * a la fecha de la factura) y en ARS. Réplica del SQL del legacy, incluido el
 * JOIN a facturaproveedor por proveedor (toma una factura cualquiera).
 */
class OpNacionalesController extends ReporteController
{
    protected string $titulo = 'OP nacionales';

    protected string $ruta = 'admin/reportes/op-nacionales';

    protected ?string $agruparPor = null;

    public function __construct(private CatalogosService $catalogos, private CotizacionService $cotizaciones) {}

    protected function filtros(): array
    {
        return [
            ['campo' => 'nropago', 'label' => 'N° OP', 'tipo' => 'text'],
            ['campo' => 'nroservicio', 'label' => 'N° OS', 'tipo' => 'text'],
            ['campo' => 'fecha', 'label' => 'Fecha de orden de pago', 'tipo' => 'rango'],
            ['campo' => 'fk_proveedor_id', 'label' => 'Proveedor', 'tipo' => 'select', 'opciones' => $this->catalogos->proveedores()],
        ];
    }

    protected function columnas(): array
    {
        return [
            ['campo' => 'op', 'label' => 'OS - OP'],
            ['campo' => 'fecha', 'label' => 'Fecha de pago'],
            ['campo' => 'codigo', 'label' => 'Reserva'],
            ['campo' => 'proveedor', 'label' => 'Proveedor'],
            ['campo' => 'confirmacion', 'label' => 'N° confirmación'],
            ['campo' => 'inicio', 'label' => 'Fecha in'],
            ['campo' => 'usd', 'label' => 'USD', 'tipo' => 'num', 'total' => true],
            ['campo' => 'cotizacion', 'label' => 'Tasa de cambio', 'tipo' => 'num'],
            ['campo' => 'ars', 'label' => 'Total ARS', 'tipo' => 'num', 'total' => true],
            ['campo' => 'factura', 'label' => 'N° factura'],
            ['campo' => 'total_factura', 'label' => 'Total factura', 'tipo' => 'num'],
            ['campo' => 'fecha_factura', 'label' => 'Fecha factura'],
            ['campo' => 'fecha_carga', 'label' => 'Fecha carga'],
        ];
    }

    protected function consultar(array $f): array
    {
        $q = DB::table('ordenadmin as oa')
            ->leftJoin('rel_ordenadminocupacion as ro', 'ro.fk_ordenadmin_id', '=', 'oa.ordenadmin_id')
            ->leftJoin('servicio as s', 's.servicio_id', '=', 'ro.fk_ocupacion_id')
            ->leftJoin('reserva as r', 'r.reserva_id', '=', 's.fk_reserva_id')
            ->leftJoin('reservain as ri', 'ri.fk_reserva_id', '=', 'r.reserva_id')
            ->leftJoin('proveedor as p', 'p.proveedor_id', '=', 'oa.fk_proveedor_id')
            ->leftJoin('facturaproveedor as fp', 'fp.fk_proveedor_id', '=', 'p.proveedor_id')
            ->where('p.fk_pais_id', 1)
            ->groupBy('oa.ordenadmin_id')
            ->orderByDesc(DB::raw('MAX(ri.inicio)'))
            ->select('oa.ordenadmin_id', 'oa.nroservicio', 'oa.nropago', 'oa.fecha', 'oa.fk_moneda_id', 'oa.monto', 'oa.cotizacion', 'p.proveedor_nombre',
                DB::raw("MAX(CONCAT(r.tipocodigo, '-', r.codigo)) AS codigo"), DB::raw('MAX(s.nro_confirmacion) AS nro_confirmacion'), DB::raw('MAX(ri.inicio) AS inicio'),
                DB::raw('MAX(fp.facturaproveedor_nro) AS facturaproveedor_nro'), DB::raw('MAX(fp.montototal) AS montototal'), DB::raw('MAX(fp.fecha) AS factura_fecha'), DB::raw('MAX(fp.fechacarga) AS fechacarga'));
        $this->rango($q, 'oa.fecha', $f, 'fecha');
        if ($f['fk_proveedor_id'] !== '') {
            // Fiel al legacy: compara con <= (no con igual).
            $q->where('oa.fk_proveedor_id', '<=', (int) $f['fk_proveedor_id']);
        }
        if ($f['nropago'] !== '' && (int) $f['nropago'] !== 0) {
            $q->where('oa.nropago', (int) $f['nropago']);
        }
        if ($f['nroservicio'] !== '' && (int) $f['nroservicio'] !== 0) {
            $q->where('oa.nroservicio', (int) $f['nroservicio']);
        }

        return $q->get()->map(function ($r) {
            $monto = (float) $r->monto;
            if ($r->fk_moneda_id === 'USD') {
                $usd = $monto;
            } else {
                $cot = $this->cotizaciones->aLaVenta('USD', $r->factura_fecha ? (string) $r->factura_fecha : null);
                $usd = $cot > 0 ? $monto / $cot : 0;
            }
            $ars = $r->fk_moneda_id === 'ARS' ? $monto : $monto * (float) $r->cotizacion;

            return [
                'op' => "{$r->nroservicio} - {$r->nropago}",
                'fecha' => $this->dmy((string) $r->fecha),
                'codigo' => $r->codigo,
                'proveedor' => $r->proveedor_nombre,
                'confirmacion' => $r->nro_confirmacion,
                'inicio' => $this->dmy((string) $r->inicio),
                'usd' => round($usd, 2),
                'cotizacion' => (float) $r->cotizacion,
                'ars' => round($ars, 2),
                'factura' => $r->facturaproveedor_nro,
                'total_factura' => (float) $r->montototal,
                'fecha_factura' => $this->dmy((string) $r->factura_fecha),
                'fecha_carga' => $this->dmy(substr((string) $r->fechacarga, 0, 10)),
            ];
        })->all();
    }
}
