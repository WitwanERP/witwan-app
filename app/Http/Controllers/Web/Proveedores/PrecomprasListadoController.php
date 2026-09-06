<?php

namespace App\Http\Controllers\Web\Proveedores;

use App\Http\Controllers\Web\Reportes\ReporteController;
use App\Services\CatalogosService;
use App\Services\Proveedores\SaldosProveedorService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Administración > Proveedores > Pre-compras (CI: administracion/precompra;
 * `precompra.precompra_tipo` = 'P'). Recalcula `precompra_utilizado` desde los
 * servicios PRC confirmados antes de listar, como el legacy.
 */
class PrecomprasListadoController extends ReporteController
{
    protected string $titulo = 'Pre-compras';

    protected string $ruta = 'proveedores/precompras';

    protected string $grupo = 'Proveedores';

    protected bool $requiereFiltros = false;

    public function __construct(protected CatalogosService $catalogos, protected SaldosProveedorService $saldos) {}

    protected function preparar(Request $request): void
    {
        $this->saldos->recalcularPrecompras();
    }

    protected function filtros(): array
    {
        return [
            ['campo' => 'fk_proveedor_id', 'label' => 'Proveedor', 'tipo' => 'select', 'opciones' => $this->catalogos->proveedores()],
            ['campo' => 'fk_producto_id', 'label' => 'Producto', 'tipo' => 'select', 'opciones' => $this->catalogos->productos()],
            ['campo' => 'precompra_inicio', 'label' => 'Inicio', 'tipo' => 'rango'],
            ['campo' => 'precompra_fin', 'label' => 'Fin', 'tipo' => 'rango'],
            ['campo' => 'fk_moneda_id', 'label' => 'Moneda', 'tipo' => 'select', 'opciones' => $this->catalogos->monedas()],
        ];
    }

    protected function columnas(): array
    {
        return [
            ['campo' => 'id', 'label' => 'ID'],
            ['campo' => 'proveedor', 'label' => 'Proveedor'],
            ['campo' => 'producto', 'label' => 'Producto'],
            ['campo' => 'inicio', 'label' => 'Inicio'],
            ['campo' => 'fin', 'label' => 'Fin'],
            ['campo' => 'moneda', 'label' => 'Moneda'],
            ['campo' => 'anticipo', 'label' => 'Anticipo', 'tipo' => 'num', 'total' => true],
            ['campo' => 'aplicado', 'label' => 'Aplicado', 'tipo' => 'num', 'total' => true],
            ['campo' => 'saldo', 'label' => 'Saldo', 'tipo' => 'num', 'total' => true],
            ['campo' => 'observaciones', 'label' => 'Observaciones'],
            ['campo' => 'acciones', 'label' => 'Acciones', 'tipo' => 'acciones'],
        ];
    }

    protected function accionesGlobales(): array
    {
        return [['label' => 'Nueva pre-compra', 'href' => '/administracion/precompra/add']];
    }

    protected function consultar(array $f): array
    {
        $q = DB::table('precompra as pc')
            ->leftJoin('proveedor as p', 'p.proveedor_id', '=', 'pc.fk_proveedor_id')
            ->leftJoin('producto as pr', 'pr.producto_id', '=', 'pc.fk_producto_id')
            ->where('pc.precompra_tipo', 'P')
            ->select('pc.*', 'p.proveedor_nombre', 'pr.producto_nombre')
            ->orderByDesc('pc.precompra_inicio')->orderByDesc('pc.precompra_id');

        foreach (['fk_proveedor_id', 'fk_producto_id', 'fk_moneda_id'] as $campo) {
            if ($f[$campo] !== '') {
                $q->where("pc.{$campo}", $f[$campo]);
            }
        }
        $this->rango($q, 'pc.precompra_inicio', $f, 'precompra_inicio');
        $this->rango($q, 'pc.precompra_fin', $f, 'precompra_fin');

        return $q->get()->map(fn ($x) => [
            'id' => (int) $x->precompra_id,
            'proveedor' => $x->proveedor_nombre,
            'producto' => $x->producto_nombre,
            'inicio' => $this->dmy((string) $x->precompra_inicio),
            'fin' => $this->dmy((string) $x->precompra_fin),
            'moneda' => $x->fk_moneda_id,
            'anticipo' => (float) $x->precompra_dinero,
            'aplicado' => (float) $x->precompra_utilizado,
            'saldo' => round((float) $x->precompra_dinero - (float) $x->precompra_utilizado, 2),
            'observaciones' => (string) $x->observaciones,
            'acciones' => [
                ['label' => 'Editar', 'href' => "/administracion/precompra/edit/{$x->precompra_id}"],
                ['label' => 'Eliminar', 'href' => "/administracion/precompra/delete/{$x->precompra_id}", 'peligro' => true],
            ],
        ])->all();
    }
}
