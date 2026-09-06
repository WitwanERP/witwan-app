<?php

namespace App\Http\Controllers\Web\Proveedores;

use App\Http\Controllers\Web\Reportes\ReporteController;
use App\Services\CatalogosService;
use App\Services\Proveedores\SaldosProveedorService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Administración > Proveedores > Canjes (CI: administracion/canje, listado).
 *
 * Como el legacy, antes de listar recalcula `canje_utilizado` desde los
 * servicios CAN confirmados. Sin ningún parámetro muestra los vigentes en USD;
 * con parámetros, oculta los vencidos salvo que se pida verlos.
 */
class CanjesListadoController extends ReporteController
{
    protected string $titulo = 'Canjes';

    protected string $ruta = 'proveedores/canjes';

    protected string $grupo = 'Proveedores';

    protected bool $requiereFiltros = false;

    protected bool $sinParametros = true;

    public function __construct(protected CatalogosService $catalogos, protected SaldosProveedorService $saldos) {}

    protected function preparar(Request $request): void
    {
        $this->sinParametros = $request->query() === [];
        $this->saldos->recalcularCanjes();
    }

    protected function filtros(): array
    {
        return [
            ['campo' => 'canje_contrato', 'label' => 'Contrato', 'tipo' => 'text'],
            ['campo' => 'canje_inicio', 'label' => 'Inicio', 'tipo' => 'rango'],
            ['campo' => 'vervencido', 'label' => 'Ver vencidos', 'tipo' => 'bool'],
            ['campo' => 'disponibles', 'label' => 'Disponibles', 'tipo' => 'select', 'opciones' => [['value' => '1', 'label' => 'Con saldo'], ['value' => '0', 'label' => 'Sin saldo']]],
            ['campo' => 'fk_proveedor_id', 'label' => 'Proveedor', 'tipo' => 'select', 'opciones' => $this->catalogos->proveedores()],
            ['campo' => 'fk_producto_id', 'label' => 'Producto', 'tipo' => 'select', 'opciones' => $this->catalogos->productos()],
            ['campo' => 'fk_ciudad_id', 'label' => 'Ciudad / Destino', 'tipo' => 'select', 'opciones' => $this->catalogos->ciudades()],
            ['campo' => 'fk_moneda_id', 'label' => 'Moneda', 'tipo' => 'select', 'opciones' => $this->catalogos->monedas()],
        ];
    }

    protected function columnas(): array
    {
        return [
            ['campo' => 'contrato', 'label' => 'Contrato'],
            ['campo' => 'inicio', 'label' => 'Inicio'],
            ['campo' => 'fin', 'label' => 'Fin'],
            ['campo' => 'proveedor', 'label' => 'Proveedor'],
            ['campo' => 'noches', 'label' => 'Noches en canje', 'tipo' => 'num', 'total' => true],
            ['campo' => 'moneda', 'label' => 'Moneda'],
            ['campo' => 'dinero', 'label' => 'Dinero en canje', 'tipo' => 'num', 'total' => true],
            ['campo' => 'utilizado', 'label' => 'Dinero utilizado', 'tipo' => 'num', 'total' => true],
            ['campo' => 'ordenes', 'label' => 'Órdenes'],
            ['campo' => 'saldo', 'label' => 'Dinero por utilizar', 'tipo' => 'num', 'total' => true],
            ['campo' => 'observaciones', 'label' => 'Observaciones'],
            ['campo' => 'acciones', 'label' => 'Acciones', 'tipo' => 'acciones'],
        ];
    }

    protected function accionesGlobales(): array
    {
        return [['label' => 'Nuevo canje', 'href' => '/administracion/canje/add']];
    }

    protected function consultar(array $f): array
    {
        $q = DB::table('canje')
            ->join('moneda', 'moneda.moneda_id', '=', 'canje.fk_moneda_id')
            ->leftJoin('proveedor', 'proveedor.proveedor_id', '=', 'canje.fk_proveedor_id')
            ->select('canje.*', 'proveedor.proveedor_nombre')
            ->addSelect([DB::raw('canje_dinero - canje_utilizado AS canje_saldo'), DB::raw(SaldosProveedorService::subOrdenes('canje', 'canje_id', ['CAN'], false))])
            ->orderByDesc('canje.canje_inicio')->orderByDesc('canje.canje_id');

        if ($this->sinParametros) {
            $q->where('canje.canje_fin', '>=', now()->toDateString())->where('canje.fk_moneda_id', 'USD');
        } elseif ($f['vervencido'] !== '1') {
            $q->where('canje.canje_fin', '>=', now()->toDateString());
        }
        if ($f['disponibles'] === '1') {
            $q->whereRaw('(canje_dinero - canje_utilizado) > 0');
        } elseif ($f['disponibles'] === '0') {
            $q->whereRaw('(canje_dinero - canje_utilizado) = 0');
        }
        if ($f['canje_contrato'] !== '') {
            $q->where('canje.canje_contrato', 'LIKE', "%{$f['canje_contrato']}%");
        }
        foreach (['fk_proveedor_id', 'fk_producto_id', 'fk_ciudad_id', 'fk_moneda_id'] as $campo) {
            if ($f[$campo] !== '') {
                $q->where("canje.{$campo}", $f[$campo]);
            }
        }
        $this->rango($q, 'canje.canje_inicio', $f, 'canje_inicio');

        return $q->get()->map(fn ($x) => [
            'contrato' => $x->canje_contrato,
            'inicio' => $this->dmy((string) $x->canje_inicio),
            'fin' => $this->dmy((string) $x->canje_fin),
            'proveedor' => $x->proveedor_nombre,
            'noches' => (float) $x->canje_noches,
            'moneda' => $x->fk_moneda_id,
            'dinero' => (float) $x->canje_dinero,
            'utilizado' => (float) $x->canje_utilizado,
            'ordenes' => (string) $x->oservicios,
            'saldo' => (float) $x->canje_saldo,
            'observaciones' => (string) $x->observaciones,
            'acciones' => [
                ['label' => 'Editar', 'href' => "/administracion/canje/edit/{$x->canje_id}"],
                ['label' => 'Eliminar', 'href' => "/administracion/canje/delete/{$x->canje_id}", 'peligro' => true],
            ],
        ])->all();
    }
}
