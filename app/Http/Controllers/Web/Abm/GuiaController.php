<?php

namespace App\Http\Controllers\Web\Abm;

use App\Services\CatalogosService;

/** Configuración > Productos > Guías (CI: configuracion/guia). */
class GuiaController extends AbmController
{
    protected string $tabla = 'guia';

    protected string $pk = 'guia_id';

    protected string $ruta = 'config/guias';

    protected string $titulo = 'Guías';

    protected string $singular = 'Guía';

    protected array $columnasListado = [
        ['campo' => 'guia_id', 'label' => 'ID'],
        ['campo' => 'guia_apellido', 'label' => 'Apellido'],
        ['campo' => 'guia_nombre', 'label' => 'Nombre'],
        ['campo' => 'fk_ciudad_id', 'label' => 'Ciudad', 'opciones' => 'ciudades'],
    ];

    protected array $filtrosLike = ['guia_nombre', 'guia_apellido'];

    protected array $filtrosSelect = [
        ['campo' => 'fk_ciudad_id', 'label' => 'Ciudad', 'opciones' => 'ciudades'],
    ];

    protected string $sortDefault = 'guia_apellido';

    public function __construct(private CatalogosService $catalogos) {}

    protected function campos(): array
    {
        return [
            ['campo' => 'guia_nombre', 'label' => 'Nombre', 'tipo' => 'text', 'required' => true, 'max' => 150],
            ['campo' => 'guia_apellido', 'label' => 'Apellido', 'tipo' => 'text', 'required' => true, 'max' => 150],
            ['campo' => 'fk_ciudad_id', 'label' => 'Ciudad', 'tipo' => 'select', 'opciones' => 'ciudades'],
        ];
    }

    protected function opciones(): array
    {
        return ['ciudades' => $this->catalogos->ciudades()];
    }
}
