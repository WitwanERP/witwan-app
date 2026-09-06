<?php

namespace App\Http\Controllers\Web\Reportes;

use App\Services\CatalogosService;
use Illuminate\Support\Facades\DB;

/**
 * Administración > Reportes > Gastos por área (CI: reportes/gastos, vista
 * centrocosto): facturas de proveedor con su distribución por sistema según
 * el JSON `imputacion` ({sistema_id: porcentaje}). El filtro por área del
 * legacy no filtraba nada (y duplicaba filas); acá deja sólo las facturas
 * imputadas a esa área.
 */
class GastosPorAreaController extends ReporteController
{
    protected string $titulo = 'Gastos por área';

    protected string $ruta = 'admin/reportes/gastos-area';

    public function __construct(private CatalogosService $catalogos) {}

    protected function filtros(): array
    {
        $tipos = DB::table('facturaproveedor')->where('tipomovimiento', '<>', '')->distinct()->orderBy('tipomovimiento')->pluck('tipomovimiento')
            ->map(fn ($t) => ['value' => $t, 'label' => $t])->values()->all();

        return [
            ['campo' => 'fk_proveedor_id', 'label' => 'Proveedor', 'tipo' => 'select', 'opciones' => $this->catalogos->proveedores()],
            ['campo' => 'fechamovimiento', 'label' => 'Fecha', 'tipo' => 'rango'],
            ['campo' => 'fk_sistema_id', 'label' => 'Área', 'tipo' => 'select', 'opciones' => $this->catalogos->sistemas()],
            ['campo' => 'tipogasto', 'label' => 'Tipo de gasto', 'tipo' => 'select', 'opciones' => $tipos],
        ];
    }

    protected function columnas(): array
    {
        return [
            ['campo' => 'proveedor', 'label' => 'Proveedor'],
            ['campo' => 'factura', 'label' => 'Factura'],
            ['campo' => 'fecha', 'label' => 'Fecha'],
            ['campo' => 'moneda', 'label' => 'Moneda'],
            ['campo' => 'monto', 'label' => 'Monto', 'tipo' => 'num', 'total' => true],
            ['campo' => 'distribucion', 'label' => 'Distribución', 'tipo' => 'pre'],
        ];
    }

    protected function consultar(array $f): array
    {
        $q = DB::table('facturaproveedor as fp')
            ->join('proveedor as p', 'p.proveedor_id', '=', 'fp.fk_proveedor_id')
            ->where('fp.facturaproveedor_id', '<>', 0)
            ->orderBy('fp.fecha')
            ->select('fp.facturaproveedor_id', 'fp.facturaproveedor_nro', 'fp.fecha', 'fp.fk_moneda_id', 'fp.montototal', 'fp.imputacion', 'p.proveedor_nombre');

        if ($f['fk_proveedor_id'] !== '') {
            $q->where('fp.fk_proveedor_id', (int) $f['fk_proveedor_id']);
        }
        if ($f['tipogasto'] !== '') {
            $q->where('fp.tipomovimiento', $f['tipogasto']);
        }
        $this->rango($q, 'fp.fecha', $f, 'fechamovimiento');

        $sistemas = collect($this->catalogos->sistemas())->pluck('label', 'value')->all();
        $area = $f['fk_sistema_id'] !== '' ? (int) $f['fk_sistema_id'] : null;

        $filas = [];
        foreach ($q->get() as $x) {
            $division = json_decode((string) $x->imputacion, true) ?: [];
            if ($area !== null && (float) ($division[$area] ?? 0) == 0) {
                continue;
            }
            $partes = [];
            foreach ($division as $sid => $pct) {
                $partes[] = ($sistemas[(int) $sid] ?? "Sistema {$sid}").': '.number_format(round((float) $x->montototal * (float) $pct / 100, 2), 2, ',', '.');
            }
            $filas[] = [
                'proveedor' => $x->proveedor_nombre,
                'factura' => $x->facturaproveedor_nro,
                'fecha' => $this->dmy($x->fecha),
                'moneda' => $x->fk_moneda_id,
                'monto' => (float) $x->montototal,
                'distribucion' => implode("\n", $partes),
            ];
        }

        return $filas;
    }
}
