<?php

namespace App\Http\Controllers\Web\Reportes;

use Illuminate\Support\Facades\DB;

/**
 * Administración > Reportes > Gastos bancarios (CI: reportes/r12). Files CL con
 * servicios del submódulo GFI (o con extra1 cargado); suma extra1 si está, si
 * no el total.
 */
class GastosBancariosController extends ReporteController
{
    protected string $titulo = 'Gastos bancarios';

    protected string $ruta = 'admin/reportes/gastos-bancarios';

    protected function filtros(): array
    {
        return [
            ['campo' => 'fecha_alta', 'label' => 'Fecha de alta', 'tipo' => 'rango'],
            ['campo' => 'fecha_inicio', 'label' => 'Fecha de inicio', 'tipo' => 'rango'],
        ];
    }

    protected function columnas(): array
    {
        return [
            ['campo' => 'file', 'label' => 'File'],
            ['campo' => 'titular', 'label' => 'Titular'],
            ['campo' => 'factura_nro', 'label' => 'N° factura'],
            ['campo' => 'moneda', 'label' => 'Moneda'],
            ['campo' => 'valor', 'label' => 'Valor', 'tipo' => 'num', 'total' => true],
        ];
    }

    protected function consultar(array $f): array
    {
        $q = DB::table('reserva as r')
            ->join('servicio as s', 's.fk_reserva_id', '=', 'r.reserva_id')
            ->join('reservain as ri', 'ri.fk_reserva_id', '=', 'r.reserva_id')
            ->leftJoin('rel_serviciofactura as rsf', 'rsf.fk_servicio_id', '=', 's.servicio_id')
            ->leftJoin('factura as fc', 'fc.factura_id', '=', 'rsf.fk_factura_id')
            ->where('r.fecha_alta', '>=', '2014-09-01')
            ->where(fn ($w) => $w->where('s.info', 'LIKE', '%<submodulo>GFI</submodulo>%')->orWhere('s.extra1', '<>', 0))
            ->where('r.fk_filestatus_id', 'CL')
            ->where('s.status', '<>', 'CA')
            ->groupBy('r.reserva_id')
            ->orderBy('r.fecha_alta')
            ->select('r.reserva_id', 'r.tipocodigo', 'r.codigo', 'r.titular_apellido', 'r.titular_nombre',
                DB::raw('MAX(s.fk_moneda_id) AS fk_moneda_id'), DB::raw('SUM(IF(s.extra1 <> 0, s.extra1, s.total)) AS totalf'), DB::raw('MAX(fc.factura_nro) AS factura_nro'));
        $this->rango($q, 'r.fecha_alta', $f, 'fecha_alta');
        $this->rango($q, 'ri.inicio', $f, 'fecha_inicio');

        return $q->get()->map(fn ($x) => [
            'file' => "{$x->tipocodigo}-{$x->codigo}",
            'titular' => trim("{$x->titular_apellido}, {$x->titular_nombre}", ', '),
            'factura_nro' => $x->factura_nro,
            'moneda' => $x->fk_moneda_id,
            'valor' => (float) $x->totalf,
        ])->all();
    }
}
