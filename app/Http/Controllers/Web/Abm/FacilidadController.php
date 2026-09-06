<?php

namespace App\Http\Controllers\Web\Abm;

use App\Services\CatalogosService;

/** Configuración > Productos > Facilidades (CI: configuracion/facilidad, tabla alojamientofacilidad). */
class FacilidadController extends AbmController
{
    protected string $tabla = 'alojamientofacilidad';

    protected string $pk = 'alojamientofacilidad_id';

    protected string $ruta = 'config/facilidades';

    protected string $titulo = 'Facilidades';

    protected string $singular = 'Facilidad';

    protected array $columnasListado = [
        ['campo' => 'alojamientofacilidad_id', 'label' => 'ID'],
        ['campo' => 'fk_submodulo_id', 'label' => 'Tipo de producto', 'opciones' => 'submodulos'],
        ['campo' => 'alojamientofacilidad_nombre', 'label' => 'Nombre'],
    ];

    protected array $filtrosLike = ['alojamientofacilidad_nombre'];

    protected array $filtrosSelect = [
        ['campo' => 'fk_submodulo_id', 'label' => 'Tipo de producto', 'opciones' => 'submodulos'],
    ];

    protected string $sortDefault = 'alojamientofacilidad_nombre';

    public function __construct(private CatalogosService $catalogos) {}

    protected function campos(): array
    {
        return [
            ['campo' => 'fk_submodulo_id', 'label' => 'Tipo de producto', 'tipo' => 'select', 'required' => true, 'opciones' => 'submodulos', 'default' => 'HOT'],
            ['campo' => 'alojamientofacilidad_nombre', 'label' => 'Nombre', 'tipo' => 'text', 'required' => true, 'max' => 100],
            ['campo' => 'alojamientofacilidad_nombre_en', 'label' => 'Nombre inglés', 'tipo' => 'text', 'max' => 100],
            ['campo' => 'alojamientofacilidad_nombre_pg', 'label' => 'Nombre portugués', 'tipo' => 'text', 'max' => 100],
        ];
    }

    protected function opciones(): array
    {
        return ['submodulos' => $this->catalogos->submodulos()];
    }
}
