<?php

namespace App\Http\Controllers\Web\Proveedores;

use App\Http\Controllers\Web\Reportes\ReporteController;
use App\Services\CatalogosService;
use App\Services\Proveedores\SaldosProveedorService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Administración > Proveedores > Crédito de proveedores (CI:
 * administracion/creditoproveedor; `precompra.precompra_tipo` = 'C').
 *
 * A diferencia de canjes y pre-compras, el legacy NO recalcula lo utilizado
 * acá: muestra `precompra_utilizado` tal como está. Sin ningún parámetro
 * oculta los créditos con saldo cero; no filtra vencidos por defecto (el
 * "Ver vencidos" del legacy era un campo ignorado por el motor).
 */
class CreditoProveedorListadoController extends ReporteController
{
    protected string $titulo = 'Crédito de proveedores';

    protected string $ruta = 'proveedores/creditos';

    protected string $grupo = 'Proveedores';

    protected bool $requiereFiltros = false;

    protected bool $sinParametros = true;

    public function __construct(protected CatalogosService $catalogos) {}

    protected function preparar(Request $request): void
    {
        $this->sinParametros = $request->query() === [];
    }

    protected function filtros(): array
    {
        return [
            ['campo' => 'fk_proveedor_id', 'label' => 'Proveedor', 'tipo' => 'select', 'opciones' => $this->catalogos->proveedores()],
            ['campo' => 'precompra_inicio', 'label' => 'Inicio', 'tipo' => 'rango'],
            ['campo' => 'fk_moneda_id', 'label' => 'Moneda', 'tipo' => 'select', 'opciones' => $this->catalogos->monedas()],
            ['campo' => 'disponibles', 'label' => 'Disponibles', 'tipo' => 'select', 'opciones' => [['value' => '1', 'label' => 'Con saldo'], ['value' => '0', 'label' => 'Sin saldo']]],
            ['campo' => 'excedidos', 'label' => 'Excedidos', 'tipo' => 'select', 'opciones' => [['value' => '1', 'label' => 'Sólo excedidos'], ['value' => '0', 'label' => 'Sin excedidos']]],
        ];
    }

    protected function columnas(): array
    {
        return [
            ['campo' => 'proveedor', 'label' => 'Proveedor'],
            ['campo' => 'file', 'label' => 'File', 'link' => 'file_link'],
            ['campo' => 'producto', 'label' => 'Producto'],
            ['campo' => 'inicio', 'label' => 'Inicio'],
            ['campo' => 'fin', 'label' => 'Fin'],
            ['campo' => 'moneda', 'label' => 'Moneda'],
            ['campo' => 'dinero', 'label' => 'Crédito', 'tipo' => 'num', 'total' => true],
            ['campo' => 'utilizado', 'label' => 'Utilizado', 'tipo' => 'num', 'total' => true],
            ['campo' => 'saldo', 'label' => 'Por utilizar', 'tipo' => 'num', 'total' => true],
            ['campo' => 'confirmacion', 'label' => 'Confirmación servicio'],
            ['campo' => 'ordenes', 'label' => 'Órdenes'],
            ['campo' => 'observaciones', 'label' => 'Observaciones'],
            ['campo' => 'acciones', 'label' => 'Acciones', 'tipo' => 'acciones'],
        ];
    }

    protected function consultar(array $f): array
    {
        $q = DB::table('precompra')
            ->join('proveedor', 'proveedor.proveedor_id', '=', 'precompra.fk_proveedor_id')
            ->join('moneda', 'moneda.moneda_id', '=', 'precompra.fk_moneda_id')
            ->leftJoin('reserva', 'reserva.reserva_id', '=', 'precompra.fk_file_id')
            ->leftJoin('producto', 'producto.producto_id', '=', 'precompra.fk_producto_id')
            ->where('precompra.precompra_tipo', 'C')
            ->select('precompra.*', 'proveedor.proveedor_nombre', 'reserva.codigo', 'reserva.tipocodigo', 'producto.producto_nombre')
            ->addSelect([
                DB::raw('precompra_dinero - precompra_utilizado AS precompra_saldo'),
                DB::raw(SaldosProveedorService::subOrdenes('precompra', 'precompra_id', ['PRE', 'C', 'CRE'], true)),
                DB::raw('(SELECT MAX(s2.nro_confirmacion) FROM servicio s2 WHERE s2.id_devolucion = precompra.precompra_id) AS nro_confirmacion'),
            ])
            ->orderByDesc('precompra.precompra_inicio')->orderByDesc('precompra.precompra_id');

        if ($f['disponibles'] === '1' || ($f['disponibles'] === '' && $this->sinParametros)) {
            $q->whereRaw('(precompra_dinero - precompra_utilizado) <> 0');
        } elseif ($f['disponibles'] === '0') {
            $q->whereRaw('(precompra_dinero - precompra_utilizado) = 0');
        }
        if ($f['excedidos'] === '1') {
            $q->whereRaw('(precompra_dinero - precompra_utilizado) < 0');
        } elseif ($f['excedidos'] === '0') {
            $q->whereRaw('(precompra_dinero - precompra_utilizado) >= 0');
        }
        foreach (['fk_proveedor_id', 'fk_moneda_id'] as $campo) {
            if ($f[$campo] !== '') {
                $q->where("precompra.{$campo}", $f[$campo]);
            }
        }
        $this->rango($q, 'precompra.precompra_inicio', $f, 'precompra_inicio');

        return $q->get()->map(fn ($x) => [
            'proveedor' => $x->proveedor_nombre,
            'file' => $x->codigo !== null ? trim("{$x->tipocodigo}-{$x->codigo}", '-') : '',
            'file_link' => $x->fk_file_id ? "/app/reservas/{$x->fk_file_id}" : null,
            'producto' => $x->producto_nombre,
            'inicio' => $this->dmy((string) $x->precompra_inicio),
            'fin' => $this->dmy((string) $x->precompra_fin),
            'moneda' => $x->fk_moneda_id,
            'dinero' => (float) $x->precompra_dinero,
            'utilizado' => (float) $x->precompra_utilizado,
            'saldo' => (float) $x->precompra_saldo,
            'confirmacion' => (string) $x->nro_confirmacion,
            'ordenes' => (string) $x->oservicios,
            'observaciones' => (string) $x->observaciones,
            'acciones' => [
                ['label' => 'Editar', 'href' => "/administracion/creditoproveedor/edit/{$x->precompra_id}"],
            ],
        ])->all();
    }
}
