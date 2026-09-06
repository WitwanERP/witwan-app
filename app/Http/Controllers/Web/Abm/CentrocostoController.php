<?php

namespace App\Http\Controllers\Web\Abm;

/** Configuración > Varios > Centros de costos (CI: configuracion/Centrocosto). */
class CentrocostoController extends AbmController
{
    protected string $tabla = 'centrocosto';

    protected string $pk = 'centrocosto_id';

    protected string $ruta = 'config/centros-costo';

    protected string $titulo = 'Centros de costos';

    protected string $singular = 'Centro de costos';

    protected array $columnasListado = [
        ['campo' => 'centrocosto_id', 'label' => 'ID'],
        ['campo' => 'centrocosto_nombre', 'label' => 'Nombre'],
        ['campo' => 'centrocosto_codigo', 'label' => 'Código'],
        ['campo' => 'centrocosto_activo', 'label' => 'Activo', 'tipo' => 'bool'],
    ];

    protected array $filtrosLike = ['centrocosto_nombre', 'centrocosto_codigo'];

    protected string $sortDefault = 'centrocosto_nombre';

    protected function campos(): array
    {
        return [
            ['campo' => 'centrocosto_nombre', 'label' => 'Nombre', 'tipo' => 'text', 'required' => true, 'max' => 150],
            ['campo' => 'centrocosto_codigo', 'label' => 'Código', 'tipo' => 'text', 'max' => 10],
            ['campo' => 'centrocosto_activo', 'label' => 'Activo', 'tipo' => 'checkbox', 'default' => 1],
        ];
    }
}
