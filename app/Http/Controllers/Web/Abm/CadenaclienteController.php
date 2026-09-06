<?php

namespace App\Http\Controllers\Web\Abm;

/** Configuración > Clientes > Cadenas de clientes/agencias (CI: configuracion/cadenacliente). */
class CadenaclienteController extends AbmController
{
    protected string $tabla = 'cadenacliente';

    protected string $pk = 'cadenacliente_id';

    protected string $ruta = 'config/cadenas-cliente';

    protected string $titulo = 'Cadenas de agencias';

    protected string $singular = 'Cadena de agencias';

    protected array $columnasListado = [
        ['campo' => 'cadenacliente_id', 'label' => 'ID'],
        ['campo' => 'cadenacliente_nombre', 'label' => 'Nombre'],
    ];

    protected array $filtrosLike = ['cadenacliente_nombre'];

    protected string $sortDefault = 'cadenacliente_nombre';

    protected function campos(): array
    {
        return [
            ['campo' => 'cadenacliente_nombre', 'label' => 'Nombre', 'tipo' => 'text', 'required' => true, 'max' => 150],
        ];
    }
}
