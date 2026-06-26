<?php

namespace App\Http\Controllers\Web\Abm;

class ProgramaFidelidadController extends AbmController
{
    protected string $tabla = 'programafidelidad';
    protected string $pk = 'programafidelidad_id';
    protected string $ruta = 'config/programas-fidelidad';
    protected string $titulo = 'Programas de fidelidad';
    protected string $singular = 'Programa de fidelidad';
    protected array $columnasListado = [
        ['campo' => 'programafidelidad_id', 'label' => 'ID'],
        ['campo' => 'programafidelidad_nombre', 'label' => 'Nombre'],
        ['campo' => 'programafidelidad_tipo', 'label' => 'Rubro'],
    ];
    protected array $filtrosLike = ['programafidelidad_nombre'];
    protected string $sortDefault = 'programafidelidad_nombre';

    protected function campos(): array
    {
        return [
            ['campo' => 'programafidelidad_nombre', 'label' => 'Nombre', 'tipo' => 'text', 'required' => true, 'max' => 150],
            ['campo' => 'programafidelidad_tipo', 'label' => 'Rubro', 'tipo' => 'text', 'max' => 150],
        ];
    }
}
