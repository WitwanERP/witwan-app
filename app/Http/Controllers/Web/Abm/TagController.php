<?php

namespace App\Http\Controllers\Web\Abm;

/** Configuración > Varios > Categorías (tags) de clientes / pasajeros (CI: configuracion/tag). */
class TagController extends AbmController
{
    protected string $tabla = 'tag';

    protected string $pk = 'tag_id';

    protected string $ruta = 'config/tags';

    protected string $titulo = 'Categorías de clientes / pasajeros';

    protected string $singular = 'Categoría';

    protected array $columnasListado = [
        ['campo' => 'tag_id', 'label' => 'ID'],
        ['campo' => 'tag_nombre', 'label' => 'Nombre'],
        ['campo' => 'tag_ruc', 'label' => 'Cliente', 'tipo' => 'bool'],
        ['campo' => 'tag_rup', 'label' => 'Pasajero', 'tipo' => 'bool'],
    ];

    protected array $filtrosLike = ['tag_nombre'];

    protected array $filtrosSelect = [
        ['campo' => 'tag_ruc', 'label' => 'Cliente', 'opciones' => 'siNo'],
        ['campo' => 'tag_rup', 'label' => 'Pasajero', 'opciones' => 'siNo'],
    ];

    protected string $sortDefault = 'tag_nombre';

    protected function campos(): array
    {
        return [
            ['campo' => 'tag_nombre', 'label' => 'Nombre', 'tipo' => 'text', 'required' => true, 'max' => 150],
            ['campo' => 'tag_ruc', 'label' => 'Aplica a clientes', 'tipo' => 'checkbox'],
            ['campo' => 'tag_rup', 'label' => 'Aplica a pasajeros', 'tipo' => 'checkbox'],
        ];
    }

    protected function opciones(): array
    {
        return ['siNo' => [['value' => 1, 'label' => 'Sí'], ['value' => 0, 'label' => 'No']]];
    }
}
