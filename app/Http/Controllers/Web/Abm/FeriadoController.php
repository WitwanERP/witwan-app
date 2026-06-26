<?php

namespace App\Http\Controllers\Web\Abm;

class FeriadoController extends AbmController
{
    protected string $tabla = 'feriado';
    protected string $pk = 'feriado_id';
    protected string $ruta = 'config/feriados';
    protected string $titulo = 'Feriados';
    protected string $singular = 'Feriado';
    protected array $columnasListado = [
        ['campo' => 'feriado_id', 'label' => 'ID'],
        ['campo' => 'feriado_fecha', 'label' => 'Fecha'],
    ];
    protected array $filtrosLike = ['feriado_fecha'];
    protected string $sortDefault = 'feriado_fecha';

    protected function campos(): array
    {
        return [
            ['campo' => 'feriado_fecha', 'label' => 'Fecha', 'tipo' => 'date', 'required' => true, 'regla' => 'required|date'],
        ];
    }
}
