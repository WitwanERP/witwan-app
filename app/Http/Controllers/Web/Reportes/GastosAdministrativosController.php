<?php

namespace App\Http\Controllers\Web\Reportes;

use Illuminate\Support\Facades\DB;

/**
 * Administración > Reportes > Gastos administrativos (CI: reportes/r14).
 * Files CO/CL con servicios del submódulo GAS; el valor es extra2 si está
 * cargado, si no el total del servicio. El filtro "fecha alta" del legacy se
 * aplica sobre la fecha in del servicio, y se mantiene así.
 */
class GastosAdministrativosController extends ReporteController
{
    protected string $titulo = 'Gastos administrativos';

    protected string $ruta = 'admin/reportes/gastos-administrativos';

    protected function filtros(): array
    {
        return [['campo' => 'fecha_alta', 'label' => 'Fecha in del servicio', 'tipo' => 'rango']];
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
            ->leftJoin('factura as fc', 'fc.fk_file_id', '=', 'r.reserva_id')
            ->where('r.fecha_alta', '>=', '2014-09-01')
            ->where('s.info', 'LIKE', '%<submodulo>GAS</submodulo>%')
            ->whereIn('r.fk_filestatus_id', ['CO', 'CL'])
            ->where('s.status', '<>', 'CA')
            ->groupBy('r.reserva_id')
            ->orderBy('r.fecha_alta')
            ->select('r.reserva_id', 'r.tipocodigo', 'r.codigo', 'r.titular_apellido', 'r.titular_nombre',
                DB::raw('MAX(s.fk_moneda_id) AS fk_moneda_id'), DB::raw('MAX(s.total) AS total'), DB::raw('MAX(s.extra2) AS extra2'), DB::raw('MAX(fc.factura_nro) AS factura_nro'));
        $this->rango($q, 's.vigencia_ini', $f, 'fecha_alta');

        return $q->get()->map(fn ($x) => [
            'file' => "{$x->tipocodigo}-{$x->codigo}",
            'titular' => trim("{$x->titular_apellido}, {$x->titular_nombre}", ', '),
            'factura_nro' => $x->factura_nro,
            'moneda' => $x->fk_moneda_id,
            'valor' => (float) ($x->extra2 != 0 ? $x->extra2 : $x->total),
        ])->all();
    }
}
