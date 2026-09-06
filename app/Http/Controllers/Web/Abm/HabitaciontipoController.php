<?php

namespace App\Http\Controllers\Web\Abm;

use App\Services\CatalogosService;

/**
 * Configuración > Productos > Tipos de habitaciones / tarifas (CI:
 * configuracion/habitaciontipo, tabla tarifacategoria). Es el catálogo global
 * de categorías que después se instancian por producto en alojamientohabitacion.
 */
class HabitaciontipoController extends AbmController
{
    protected string $tabla = 'tarifacategoria';

    protected string $pk = 'tarifacategoria_id';

    protected string $ruta = 'config/tipos-habitacion';

    protected string $titulo = 'Tipos de habitaciones / tarifas';

    protected string $singular = 'Tipo de habitación';

    protected array $columnasListado = [
        ['campo' => 'tarifacategoria_id', 'label' => 'ID'],
        ['campo' => 'tarifacategoria_nombre', 'label' => 'Nombre'],
        ['campo' => 'fk_submodulo_id', 'label' => 'Tipo de producto', 'opciones' => 'submodulos'],
    ];

    protected array $filtrosLike = ['tarifacategoria_nombre'];

    protected array $filtrosSelect = [
        ['campo' => 'fk_submodulo_id', 'label' => 'Tipo de producto', 'opciones' => 'submodulos'],
    ];

    protected string $sortDefault = 'tarifacategoria_nombre';

    public function __construct(private CatalogosService $catalogos) {}

    protected function campos(): array
    {
        return [
            ['campo' => 'tarifacategoria_nombre', 'label' => 'Nombre', 'tipo' => 'text', 'required' => true, 'max' => 100],
            ['campo' => 'tarifacategoria_nombre_en', 'label' => 'Nombre inglés', 'tipo' => 'text', 'max' => 100],
            ['campo' => 'tarifacategoria_nombre_pg', 'label' => 'Nombre portugués', 'tipo' => 'text', 'max' => 100],
            ['campo' => 'fk_submodulo_id', 'label' => 'Tipo de producto', 'tipo' => 'select', 'required' => true, 'opciones' => 'submodulos'],
        ];
    }

    protected function opciones(): array
    {
        return ['submodulos' => $this->catalogos->submodulos()];
    }
}
