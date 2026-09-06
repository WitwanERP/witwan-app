<?php

namespace App\Http\Controllers\Web\Abm;

use App\Services\CatalogosService;

/**
 * Administración > Contabilidad > Tabla de IVA (CI: administracion/tablaiva).
 * Alícuotas por cascada tipo de producto / país / ciudad / producto; la ciudad
 * depende del país como el `options.update` del legacy.
 */
class TablaIvaController extends AbmController
{
    protected string $tabla = 'iva';

    protected string $pk = 'iva_id';

    protected string $ruta = 'admin/tabla-iva';

    protected string $titulo = 'Tabla de alícuotas de IVA';

    protected string $singular = 'Alícuota';

    protected string $area = 'Administración';

    protected int $porPagina = 200;

    protected array $columnasListado = [
        ['campo' => 'iva_id', 'label' => 'ID'],
        ['campo' => 'fk_submodulo_id', 'label' => 'Tipo de producto', 'opciones' => 'submodulos'],
        ['campo' => 'fk_pais_id', 'label' => 'País', 'opciones' => 'paises'],
        ['campo' => 'fk_ciudad_id', 'label' => 'Ciudad', 'opciones' => 'ciudades'],
        ['campo' => 'fk_producto_id', 'label' => 'Producto', 'opciones' => 'productos'],
        ['campo' => 'fk_modoivaventa_id', 'label' => 'Tipo', 'opciones' => 'modos'],
        ['campo' => 'iva_costo', 'label' => '% IVA costo'],
        ['campo' => 'iva_valor', 'label' => '% IVA venta'],
    ];

    protected array $filtrosSelect = [
        ['campo' => 'fk_submodulo_id', 'label' => 'Tipo de producto', 'opciones' => 'submodulos'],
        ['campo' => 'fk_pais_id', 'label' => 'País', 'opciones' => 'paises'],
        ['campo' => 'fk_modoivaventa_id', 'label' => 'Tipo', 'opciones' => 'modos'],
    ];

    protected string $sortDefault = 'fk_submodulo_id';

    public function __construct(private CatalogosService $catalogos) {}

    protected function campos(): array
    {
        return [
            ['campo' => 'fk_submodulo_id', 'label' => 'Tipo de producto', 'tipo' => 'select', 'opciones' => 'submodulos', 'vacio' => '0'],
            ['campo' => 'fk_pais_id', 'label' => 'País', 'tipo' => 'select', 'opciones' => 'paises'],
            ['campo' => 'fk_ciudad_id', 'label' => 'Ciudad', 'tipo' => 'select', 'opciones' => 'ciudades', 'dependeDe' => 'fk_pais_id'],
            ['campo' => 'fk_producto_id', 'label' => 'Producto', 'tipo' => 'select', 'opciones' => 'productos'],
            ['campo' => 'fk_modoivaventa_id', 'label' => 'Tipo', 'tipo' => 'select', 'opciones' => 'modos'],
            ['campo' => 'iva_costo', 'label' => '% IVA costo', 'tipo' => 'decimal', 'required' => true],
            ['campo' => 'iva_valor', 'label' => '% IVA venta', 'tipo' => 'decimal', 'required' => true],
        ];
    }

    protected function opciones(): array
    {
        return [
            'submodulos' => $this->catalogos->submodulos(),
            'paises' => $this->catalogos->paises(),
            'ciudades' => $this->catalogos->ciudades(),
            'productos' => $this->catalogos->productos(),
            'modos' => $this->catalogos->modosIvaVenta(),
        ];
    }
}
