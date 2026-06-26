<?php

namespace App\Http\Controllers\Web\Abm;

use Illuminate\Support\Facades\DB;

class PaisController extends AbmController
{
    protected string $tabla = 'pais';
    protected string $pk = 'pais_id';
    protected string $ruta = 'geo/paises';
    protected string $titulo = 'Países';
    protected string $singular = 'País';
    protected array $columnasListado = [
        ['campo' => 'pais_id', 'label' => 'ID'],
        ['campo' => 'pais_nombre', 'label' => 'Nombre'],
        ['campo' => 'pais_codigo', 'label' => 'Código'],
        ['campo' => 'pais_cuit', 'label' => 'CUIT país'],
    ];
    protected array $filtrosLike = ['pais_nombre', 'pais_codigo'];
    protected string $sortDefault = 'pais_nombre';

    protected function campos(): array
    {
        return [
            ['campo' => 'pais_nombre', 'label' => 'Nombre', 'tipo' => 'text', 'required' => true, 'max' => 100],
            ['campo' => 'pais_codigo', 'label' => 'Código', 'tipo' => 'text', 'max' => 100],
            ['campo' => 'pais_cuit', 'label' => 'CUIT país', 'tipo' => 'text', 'max' => 13],
            ['campo' => 'fk_region_id', 'label' => 'Región', 'tipo' => 'select', 'opciones' => 'regiones'],
            ['campo' => 'validado', 'label' => 'Validado', 'tipo' => 'checkbox'],
        ];
    }

    protected function opciones(): array
    {
        return [
            'regiones' => DB::table('region')->orderBy('region_nombre')
                ->get(['region_id', 'region_nombre'])
                ->map(fn ($r) => ['value' => $r->region_id, 'label' => $r->region_nombre])
                ->all(),
        ];
    }
}
