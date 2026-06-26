<?php

namespace App\Http\Controllers\Web\Abm;

class RegionController extends AbmController
{
    protected string $tabla = 'region';
    protected string $pk = 'region_id';
    protected string $ruta = 'geo/regiones';
    protected string $titulo = 'Regiones';
    protected string $singular = 'Región';
    protected array $columnasListado = [
        ['campo' => 'region_id', 'label' => 'ID'],
        ['campo' => 'region_nombre', 'label' => 'Nombre'],
    ];
    protected array $filtrosLike = ['region_nombre'];
    protected string $sortDefault = 'region_nombre';

    protected function campos(): array
    {
        return [
            ['campo' => 'region_nombre', 'label' => 'Nombre', 'tipo' => 'text', 'required' => true, 'max' => 100],
        ];
    }
}
