<?php

namespace App\Http\Controllers\Web\Abm;

use App\Services\CatalogosService;

/**
 * Administración > Fee y Comisión > Modelo de fees (CI: administracion/Modelofee).
 * La cascada región destino → país → ciudad usa selects dependientes (el
 * legacy tenía la de país→ciudad rota: consultaba `proveedor`).
 */
class ModelofeeController extends AbmController
{
    protected string $tabla = 'modelofee';

    protected string $pk = 'modelofee_id';

    protected string $ruta = 'admin/modelos-fee';

    protected string $titulo = 'Modelos de fee';

    protected string $singular = 'Modelo de fee';

    protected string $area = 'Administración';

    protected array $columnasListado = [
        ['campo' => 'modelofee_id', 'label' => 'ID'],
        ['campo' => 'modelofee_nombre', 'label' => 'Nombre'],
        ['campo' => 'fk_cliente_id', 'label' => 'Cliente', 'opciones' => 'clientes'],
        ['campo' => 'modelofee_tipo', 'label' => 'Aplicación', 'opciones' => 'tiposAplicacion'],
        ['campo' => 'tipocodigo', 'label' => 'Tipo de file'],
        ['campo' => 'fk_region_id', 'label' => 'Región destino', 'opciones' => 'regiones'],
        ['campo' => 'fk_submodulo_id', 'label' => 'Tipo de producto', 'opciones' => 'submodulos'],
        ['campo' => 'fk_moneda_id', 'label' => 'Moneda'],
        ['campo' => 'modelofee_minimo', 'label' => 'Mínimo'],
        ['campo' => 'modelofee_maximo', 'label' => 'Máximo'],
        ['campo' => 'modelofee_normal', 'label' => 'Valor offline'],
        ['campo' => 'modelofee_normal_r', 'label' => 'Reemisión'],
    ];

    protected array $filtrosLike = ['modelofee_nombre'];

    protected array $filtrosSelect = [
        ['campo' => 'fk_cliente_id', 'label' => 'Cliente', 'opciones' => 'clientes'],
        ['campo' => 'fk_region_id', 'label' => 'Región destino', 'opciones' => 'regiones'],
        ['campo' => 'fk_submodulo_id', 'label' => 'Tipo de producto', 'opciones' => 'submodulos'],
    ];

    protected string $sortDefault = 'modelofee_nombre';

    public function __construct(private CatalogosService $catalogos) {}

    protected function campos(): array
    {
        return [
            ['campo' => 'modelofee_nombre', 'label' => 'Nombre', 'tipo' => 'text', 'required' => true, 'max' => 150],
            ['campo' => 'fk_cliente_id', 'label' => 'Cliente asociado', 'tipo' => 'select', 'opciones' => 'clientes'],
            ['campo' => 'modelofee_tipo', 'label' => 'Tipo de aplicación', 'tipo' => 'radio', 'opciones' => 'tiposAplicacion', 'default' => 'P'],
            ['campo' => 'tipocodigo', 'label' => 'Tipo de file', 'tipo' => 'radio', 'opciones' => 'tiposCodigo'],
            ['campo' => 'region_origen', 'label' => 'Región origen', 'tipo' => 'select', 'opciones' => 'regiones'],
            ['campo' => 'fk_region_id', 'label' => 'Región destino', 'tipo' => 'select', 'opciones' => 'regiones'],
            ['campo' => 'fk_pais_id', 'label' => 'País', 'tipo' => 'select', 'opciones' => 'paises'],
            ['campo' => 'fk_ciudad_id', 'label' => 'Ciudad', 'tipo' => 'select', 'opciones' => 'ciudades', 'dependeDe' => 'fk_pais_id'],
            ['campo' => 'modelofee_minimo', 'label' => 'Rango mínimo', 'tipo' => 'decimal'],
            ['campo' => 'modelofee_maximo', 'label' => 'Rango máximo', 'tipo' => 'decimal'],
            ['campo' => 'fk_submodulo_id', 'label' => 'Tipo de producto', 'tipo' => 'select', 'opciones' => 'submodulos', 'default' => 'AER'],
            ['campo' => 'fk_moneda_id', 'label' => 'Moneda', 'tipo' => 'select', 'opciones' => 'monedas'],
            ['campo' => 'modelofee_normal', 'label' => 'Valor offline', 'tipo' => 'decimal'],
            ['campo' => 'modelofee_offline', 'label' => 'Valor online', 'tipo' => 'decimal'],
            ['campo' => 'modelofee_emergencia', 'label' => 'Valor emergencia', 'tipo' => 'decimal'],
            ['campo' => 'modelofee_normal_r', 'label' => 'Reemisiones: valor', 'tipo' => 'decimal'],
        ];
    }

    protected function opciones(): array
    {
        return [
            'clientes' => $this->catalogos->clientes(),
            'regiones' => $this->catalogos->regiones(),
            'paises' => $this->catalogos->paises(),
            'ciudades' => $this->catalogos->ciudades(),
            'submodulos' => $this->catalogos->submodulos(),
            'monedas' => $this->catalogos->monedas(),
            'tiposCodigo' => $this->catalogos->tiposCodigo(),
            'tiposAplicacion' => [['value' => 'P', 'label' => 'Porcentaje'], ['value' => 'F', 'label' => 'Valor fijo']],
        ];
    }
}
