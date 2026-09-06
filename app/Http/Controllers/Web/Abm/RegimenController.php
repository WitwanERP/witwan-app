<?php

namespace App\Http\Controllers\Web\Abm;

/** Configuración > Productos > Regímenes de comida (CI: configuracion/regimen). */
class RegimenController extends AbmController
{
    protected string $tabla = 'regimen';

    protected string $pk = 'regimen_id';

    protected string $ruta = 'config/regimenes';

    protected string $titulo = 'Regímenes de comida';

    protected string $singular = 'Régimen';

    protected array $columnasListado = [
        ['campo' => 'regimen_id', 'label' => 'ID'],
        ['campo' => 'regimen_nombre', 'label' => 'Nombre'],
        ['campo' => 'regimen_nombre_en', 'label' => 'Inglés'],
        ['campo' => 'regimen_nombre_pg', 'label' => 'Portugués'],
    ];

    protected array $filtrosLike = ['regimen_nombre'];

    protected string $sortDefault = 'regimen_nombre';

    protected function campos(): array
    {
        return [
            ['campo' => 'regimen_nombre', 'label' => 'Nombre', 'tipo' => 'text', 'required' => true, 'max' => 100],
            ['campo' => 'regimen_nombre_en', 'label' => 'Nombre inglés', 'tipo' => 'text', 'max' => 100],
            ['campo' => 'regimen_nombre_pg', 'label' => 'Nombre portugués', 'tipo' => 'text', 'max' => 100],
        ];
    }
}
