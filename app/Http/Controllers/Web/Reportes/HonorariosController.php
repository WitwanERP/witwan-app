<?php

namespace App\Http\Controllers\Web\Reportes;

use Illuminate\Support\Facades\DB;

/** Administración > Reportes > Libro de honorarios (CI: reportes/honorarios): facturas de proveedor con retención IIBB/honorarios. */
class HonorariosController extends ReporteController
{
    protected string $titulo = 'Libro de honorarios';

    protected string $ruta = 'admin/reportes/honorarios';

    protected ?string $agruparPor = 'moneda';

    protected function filtros(): array
    {
        return [['campo' => 'fecha', 'label' => 'Fecha del movimiento', 'tipo' => 'rango']];
    }

    protected function columnas(): array
    {
        return [
            ['campo' => 'fecha', 'label' => 'Fecha'],
            ['campo' => 'td', 'label' => 'TD'],
            ['campo' => 'nro', 'label' => 'N°'],
            ['campo' => 'correl', 'label' => 'Correl.'],
            ['campo' => 'proveedor', 'label' => 'Proveedor'],
            ['campo' => 'cuit', 'label' => 'RUT / CUIT'],
            ['campo' => 'exento', 'label' => 'Exento', 'tipo' => 'num', 'total' => true],
            ['campo' => 'neto', 'label' => 'Neto', 'tipo' => 'num', 'total' => true],
            ['campo' => 'retencion', 'label' => 'Retención', 'tipo' => 'num', 'total' => true],
            ['campo' => 'total', 'label' => 'Total', 'tipo' => 'num', 'total' => true],
        ];
    }

    protected function consultar(array $f): array
    {
        $q = DB::table('facturaproveedor as fp')
            ->join('movimiento as m', 'm.fk_facturaproveedor_id', '=', 'fp.facturaproveedor_id')
            ->join('proveedor as p', 'p.proveedor_id', '=', 'fp.fk_proveedor_id')
            ->where('fp.retencioniibb', '<>', 0)
            ->groupBy('fp.facturaproveedor_id')
            ->orderBy('fp.fecha')
            ->select('fp.facturaproveedor_id', 'fp.facturaproveedor_nro', 'fp.facturaproveedor_tipodocumento', 'fp.fecha', 'fp.fk_moneda_id', 'fp.montoexento', 'fp.montogeneral', 'fp.retencioniibb', 'fp.montototal', 'p.razonsocial', 'p.cuit');
        $this->rango($q, 'm.fecha', $f, 'fecha');

        return $q->get()->map(fn ($x) => [
            'fecha' => $this->dmy($x->fecha),
            'td' => $x->facturaproveedor_tipodocumento,
            'nro' => $x->facturaproveedor_nro,
            'correl' => (int) $x->facturaproveedor_id,
            'proveedor' => $x->razonsocial,
            'cuit' => $x->cuit,
            'moneda' => $x->fk_moneda_id,
            'exento' => (float) $x->montoexento,
            'neto' => (float) $x->montogeneral,
            'retencion' => (float) $x->retencioniibb,
            'total' => (float) $x->montototal,
        ])->all();
    }
}
