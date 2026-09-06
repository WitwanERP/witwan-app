<?php

namespace App\Http\Controllers\Web\Abm;

use App\Services\CatalogosService;

/** Configuración > Varios > Formas de pago (CI: configuracion/formasdepago, tabla formapago). */
class FormapagoController extends AbmController
{
    protected string $tabla = 'formapago';

    protected string $pk = 'formapago_id';

    protected string $ruta = 'config/formas-pago';

    protected string $titulo = 'Formas de pago';

    protected string $singular = 'Forma de pago';

    protected array $columnasListado = [
        ['campo' => 'formapago_id', 'label' => 'ID'],
        ['campo' => 'formapago_nombre', 'label' => 'Nombre'],
        ['campo' => 'fk_plancuenta_id', 'label' => 'Plan de cuentas', 'opciones' => 'planCuentas'],
    ];

    protected array $filtrosLike = ['formapago_nombre'];

    protected array $filtrosSelect = [
        ['campo' => 'fk_plancuenta_id', 'label' => 'Plan de cuentas', 'opciones' => 'planCuentas'],
    ];

    protected string $sortDefault = 'formapago_id';

    public function __construct(private CatalogosService $catalogos) {}

    protected function campos(): array
    {
        return [
            ['campo' => 'formapago_nombre', 'label' => 'Nombre', 'tipo' => 'text', 'required' => true, 'max' => 100],
            ['campo' => 'fk_plancuenta_id', 'label' => 'Plan de cuentas', 'tipo' => 'select', 'opciones' => 'planCuentas'],
        ];
    }

    protected function opciones(): array
    {
        return ['planCuentas' => $this->catalogos->planCuentas()];
    }
}
