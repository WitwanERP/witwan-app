<?php

namespace App\Http\Controllers\Web\Abm;

use Illuminate\Support\Facades\DB;

class NegocioController extends AbmController
{
    protected string $tabla = 'negocio';
    protected string $pk = 'negocio_id';
    protected string $ruta = 'config/negocios';
    protected string $titulo = 'Negocios';
    protected string $singular = 'Negocio';
    protected array $columnasListado = [
        ['campo' => 'negocio_id', 'label' => 'ID'],
        ['campo' => 'negocio_nombre', 'label' => 'Nombre'],
        ['campo' => 'negocio_vencimiento', 'label' => 'Vencimiento'],
    ];
    protected array $filtrosLike = ['negocio_nombre'];
    protected string $sortDefault = 'negocio_nombre';

    protected function campos(): array
    {
        return [
            ['campo' => 'negocio_nombre', 'label' => 'Nombre', 'tipo' => 'text', 'required' => true, 'max' => 150],
            ['campo' => 'negocio_vencimiento', 'label' => 'Vencimiento', 'tipo' => 'date', 'regla' => 'nullable|date'],
            ['campo' => 'fk_ciudad_id', 'label' => 'Ciudad', 'tipo' => 'select', 'opciones' => 'ciudades'],
            ['campo' => 'negocio_descripcion', 'label' => 'Descripción', 'tipo' => 'textarea'],
        ];
    }

    protected function opciones(): array
    {
        return [
            'ciudades' => DB::table('ciudad')->where('fk_ciudad_id', 0)
                ->orderBy('ciudad_nombre')
                ->limit(2000)
                ->get(['ciudad_id', 'ciudad_nombre'])
                ->map(fn ($c) => ['value' => $c->ciudad_id, 'label' => $c->ciudad_nombre])
                ->all(),
        ];
    }
}
