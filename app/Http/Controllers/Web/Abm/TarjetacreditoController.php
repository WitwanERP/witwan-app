<?php

namespace App\Http\Controllers\Web\Abm;

/** Configuración > Varios > Tarjetas de crédito (CI: configuracion/tarjetacredito). */
class TarjetacreditoController extends AbmController
{
    protected string $tabla = 'tarjetacredito';

    protected string $pk = 'tarjetacredito_id';

    protected string $ruta = 'config/tarjetas-credito';

    protected string $titulo = 'Tarjetas de crédito';

    protected string $singular = 'Tarjeta de crédito';

    protected array $columnasListado = [
        ['campo' => 'tarjetacredito_id', 'label' => 'ID'],
        ['campo' => 'tarjetacredito_nombre', 'label' => 'Nombre'],
    ];

    protected array $filtrosLike = ['tarjetacredito_nombre'];

    protected string $sortDefault = 'tarjetacredito_nombre';

    protected function campos(): array
    {
        return [
            ['campo' => 'tarjetacredito_nombre', 'label' => 'Nombre', 'tipo' => 'text', 'required' => true, 'max' => 150],
        ];
    }
}
