<?php

namespace App\Http\Controllers\Web\Abm;

class TipoclavefiscalController extends AbmController
{
    protected string $tabla = 'tipoclavefiscal';
    protected string $pk = 'tipoclavefiscal_id';
    protected string $ruta = 'config/tipos-clave-fiscal';
    protected string $titulo = 'Tipos de clave fiscal';
    protected string $singular = 'Tipo de clave fiscal';
    protected array $columnasListado = [
        ['campo' => 'tipoclavefiscal_id', 'label' => 'ID'],
        ['campo' => 'tipoclavefiscal_nombre', 'label' => 'Nombre'],
    ];
    protected array $filtrosLike = ['tipoclavefiscal_nombre'];
    protected string $sortDefault = 'tipoclavefiscal_nombre';

    protected function campos(): array
    {
        return [
            ['campo' => 'tipoclavefiscal_nombre', 'label' => 'Nombre', 'tipo' => 'text', 'required' => true, 'max' => 150],
        ];
    }
}
