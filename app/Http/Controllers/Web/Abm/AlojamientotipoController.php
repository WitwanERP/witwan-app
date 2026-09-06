<?php

namespace App\Http\Controllers\Web\Abm;

/** Configuración > Productos > Tipos de alojamiento (CI: configuracion/alojamientotipo). */
class AlojamientotipoController extends AbmController
{
    protected string $tabla = 'alojamientotipo';

    protected string $pk = 'alojamientotipo_id';

    protected string $ruta = 'config/tipos-alojamiento';

    protected string $titulo = 'Tipos de alojamiento';

    protected string $singular = 'Tipo de alojamiento';

    protected array $columnasListado = [
        ['campo' => 'alojamientotipo_id', 'label' => 'ID'],
        ['campo' => 'alojamientotipo_nombre', 'label' => 'Nombre'],
    ];

    protected array $filtrosLike = ['alojamientotipo_nombre'];

    protected string $sortDefault = 'alojamientotipo_nombre';

    protected function campos(): array
    {
        return [
            ['campo' => 'alojamientotipo_nombre', 'label' => 'Nombre', 'tipo' => 'text', 'required' => true, 'max' => 64],
        ];
    }
}
