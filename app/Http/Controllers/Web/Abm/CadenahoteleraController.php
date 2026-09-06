<?php

namespace App\Http\Controllers\Web\Abm;

/** Configuración > Productos > Cadenas hoteleras (CI: configuracion/cadenahotelera). */
class CadenahoteleraController extends AbmController
{
    protected string $tabla = 'cadenahotelera';

    protected string $pk = 'cadenahotelera_id';

    protected string $ruta = 'config/cadenas-hoteleras';

    protected string $titulo = 'Cadenas hoteleras';

    protected string $singular = 'Cadena hotelera';

    protected array $columnasListado = [
        ['campo' => 'cadenahotelera_id', 'label' => 'ID'],
        ['campo' => 'cadenahotelera_nombre', 'label' => 'Nombre'],
    ];

    protected array $filtrosLike = ['cadenahotelera_nombre'];

    protected string $sortDefault = 'cadenahotelera_nombre';

    protected function campos(): array
    {
        return [
            ['campo' => 'cadenahotelera_nombre', 'label' => 'Nombre', 'tipo' => 'text', 'required' => true, 'max' => 150],
        ];
    }
}
