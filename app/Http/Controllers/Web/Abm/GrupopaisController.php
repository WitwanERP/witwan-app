<?php

namespace App\Http\Controllers\Web\Abm;

/** Configuración > Geo > Grupo de países (CI: configuracion/grupopais). */
class GrupopaisController extends AbmController
{
    protected string $tabla = 'grupopais';

    protected string $pk = 'grupopais_id';

    protected string $ruta = 'config/grupos-pais';

    protected string $titulo = 'Grupos de países';

    protected string $singular = 'Grupo de países';

    protected array $columnasListado = [
        ['campo' => 'grupopais_id', 'label' => 'ID'],
        ['campo' => 'grupopais_nombre', 'label' => 'Nombre'],
    ];

    protected array $filtrosLike = ['grupopais_nombre'];

    protected string $sortDefault = 'grupopais_nombre';

    protected function campos(): array
    {
        return [
            ['campo' => 'grupopais_nombre', 'label' => 'Nombre', 'tipo' => 'text', 'required' => true, 'max' => 255],
        ];
    }
}
