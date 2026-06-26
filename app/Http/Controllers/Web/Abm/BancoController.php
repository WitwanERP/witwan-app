<?php

namespace App\Http\Controllers\Web\Abm;

class BancoController extends AbmController
{
    protected string $tabla = 'crmbanco';
    protected string $pk = 'crmbanco_id';
    protected string $ruta = 'config/bancos';
    protected string $titulo = 'Bancos';
    protected string $singular = 'Banco';
    protected array $columnasListado = [
        ['campo' => 'crmbanco_id', 'label' => 'ID'],
        ['campo' => 'crmbanco_nombre', 'label' => 'Nombre'],
    ];
    protected array $filtrosLike = ['crmbanco_nombre'];
    protected string $sortDefault = 'crmbanco_nombre';

    protected function campos(): array
    {
        return [
            ['campo' => 'crmbanco_nombre', 'label' => 'Nombre', 'tipo' => 'text', 'required' => true, 'max' => 100],
        ];
    }
}
