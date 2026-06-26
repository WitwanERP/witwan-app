<?php

namespace App\Http\Controllers\Web\Abm;

class ProyectoController extends AbmController
{
    protected string $tabla = 'proyecto';
    protected string $pk = 'proyecto_id';
    protected string $ruta = 'config/proyectos';
    protected string $titulo = 'Proyectos';
    protected string $singular = 'Proyecto';
    protected array $columnasListado = [
        ['campo' => 'proyecto_id', 'label' => 'ID'],
        ['campo' => 'proyecto_nombre', 'label' => 'Nombre'],
    ];
    protected array $filtrosLike = ['proyecto_nombre'];
    protected string $sortDefault = 'proyecto_nombre';

    protected function campos(): array
    {
        return [
            ['campo' => 'proyecto_nombre', 'label' => 'Nombre', 'tipo' => 'text', 'required' => true, 'max' => 150],
        ];
    }
}
