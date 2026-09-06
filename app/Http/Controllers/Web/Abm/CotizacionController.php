<?php

namespace App\Http\Controllers\Web\Abm;

use App\Services\CatalogosService;

/**
 * Administración > Monedas > Tipo de cambio > "Ver todas las cotizaciones"
 * (CI: administracion/cambio/lista). Histórico de la tabla `cotizacion`; como
 * en el legacy sólo se puede ver y cargar (sin editar ni borrar).
 */
class CotizacionController extends AbmController
{
    protected string $tabla = 'cotizacion';

    protected string $pk = 'cotizacion_id';

    protected string $ruta = 'admin/cotizaciones';

    protected string $titulo = 'Cotizaciones (histórico)';

    protected string $singular = 'Cotización';

    protected string $area = 'Administración';

    protected array $acciones = ['crear'];

    protected int $porPagina = 30;

    protected array $columnasListado = [
        ['campo' => 'cotizacion_fecha', 'label' => 'Fecha'],
        ['campo' => 'cotizacion_moneda', 'label' => 'Moneda', 'opciones' => 'monedas'],
        ['campo' => 'cotizacion_relacion', 'label' => 'Cambio (venta)'],
        ['campo' => 'cotizacion_costo', 'label' => 'Cambio (costo)'],
    ];

    protected array $filtrosSelect = [
        ['campo' => 'cotizacion_moneda', 'label' => 'Moneda', 'opciones' => 'monedas'],
    ];

    protected string $sortDefault = 'cotizacion_fecha';

    protected string $dirDefault = 'desc';

    public function __construct(private CatalogosService $catalogos) {}

    protected function campos(): array
    {
        return [
            ['campo' => 'cotizacion_fecha', 'label' => 'Fecha', 'tipo' => 'date', 'required' => true, 'default' => now()->toDateString()],
            ['campo' => 'cotizacion_moneda', 'label' => 'Moneda', 'tipo' => 'select', 'required' => true, 'opciones' => 'monedas'],
            ['campo' => 'cotizacion_relacion', 'label' => 'Cambio (venta)', 'tipo' => 'decimal', 'required' => true],
            ['campo' => 'cotizacion_costo', 'label' => 'Cambio (costo)', 'tipo' => 'decimal', 'required' => true],
        ];
    }

    protected function opciones(): array
    {
        return ['monedas' => $this->catalogos->monedas(true)];
    }
}
