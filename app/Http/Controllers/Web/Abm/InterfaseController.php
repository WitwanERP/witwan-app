<?php

namespace App\Http\Controllers\Web\Abm;

use App\Services\CatalogosService;
use App\Support\Licencia;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;

/**
 * Configuración > Interfases XML (CI: configuracion/interfases). Como en el
 * legacy, el listado oculta las inactivas salvo para mundotour_sdg.
 */
class InterfaseController extends AbmController
{
    protected string $tabla = 'interfases';

    protected string $pk = 'interfases_id';

    protected string $ruta = 'config/interfases';

    protected string $titulo = 'Interfases XML';

    protected string $singular = 'Interfase';

    protected array $columnasListado = [
        ['campo' => 'interfases_id', 'label' => 'ID'],
        ['campo' => 'interfases_nombre', 'label' => 'Nombre'],
        ['campo' => 'fk_proveedor_id', 'label' => 'Proveedor', 'opciones' => 'proveedores'],
        ['campo' => 'interfases_mup', 'label' => 'Markup'],
        ['campo' => 'interfases_release', 'label' => 'Free release'],
        ['campo' => 'interfases_penalidad', 'label' => 'Penalidad extra'],
        ['campo' => 'interfases_activo', 'label' => 'Activo', 'tipo' => 'bool'],
    ];

    protected array $filtrosLike = ['interfases_nombre'];

    protected array $filtrosSelect = [
        ['campo' => 'fk_proveedor_id', 'label' => 'Proveedor', 'opciones' => 'proveedores'],
    ];

    protected string $sortDefault = 'interfases_nombre';

    public function __construct(private CatalogosService $catalogos) {}

    protected function filtrarListado(Builder $query, Request $request): void
    {
        if (! Licencia::es('mundotour_sdg')) {
            $query->where('interfases_activo', '<>', 0);
        }
    }

    protected function campos(): array
    {
        return [
            ['campo' => 'interfases_nombre', 'label' => 'Nombre', 'tipo' => 'text', 'required' => true, 'max' => 150],
            ['campo' => 'fk_proveedor_id', 'label' => 'Proveedor', 'tipo' => 'select', 'opciones' => 'proveedores'],
            ['campo' => 'interfases_mup', 'label' => 'Markup', 'tipo' => 'decimal'],
            ['campo' => 'interfases_release', 'label' => 'Cobertura de free release', 'tipo' => 'number'],
            ['campo' => 'interfases_penalidad', 'label' => 'Monto extra penalidad', 'tipo' => 'number'],
            ['campo' => 'interfases_activo', 'label' => 'Activo', 'tipo' => 'checkbox', 'default' => 1],
            ['campo' => 'interfases_receptivo', 'label' => 'Usar en área verde (receptivo)', 'tipo' => 'checkbox'],
            ['campo' => 'interfases_mayorista', 'label' => 'Usar en área rosa (mayorista)', 'tipo' => 'checkbox', 'default' => 1],
        ];
    }

    protected function opciones(): array
    {
        return ['proveedores' => $this->catalogos->proveedores()];
    }
}
