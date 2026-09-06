<?php

namespace App\Http\Controllers\Web\Abm;

use App\Services\CatalogosService;

/** Administración > Fee y Comisión > Modelo de comisiones (CI: administracion/Modelocomision). */
class ModelocomisionController extends AbmController
{
    protected string $tabla = 'modelocomision';

    protected string $pk = 'modelocomision_id';

    protected string $ruta = 'admin/modelos-comision';

    protected string $titulo = 'Modelos de comisión';

    protected string $singular = 'Modelo de comisión';

    protected string $area = 'Administración';

    protected array $columnasListado = [
        ['campo' => 'modelocomision_id', 'label' => 'ID'],
        ['campo' => 'modelocomision_nombre', 'label' => 'Nombre'],
        ['campo' => 'modelocomision_esquema', 'label' => 'Esquema', 'opciones' => 'esquemas'],
        ['campo' => 'modelocomision_tipo', 'label' => 'Tipo', 'opciones' => 'tipos'],
    ];

    protected array $filtrosLike = ['modelocomision_nombre'];

    protected string $sortDefault = 'modelocomision_nombre';

    public function __construct(private CatalogosService $catalogos) {}

    protected function campos(): array
    {
        $campos = [
            ['campo' => 'modelocomision_nombre', 'label' => 'Nombre', 'tipo' => 'text', 'required' => true, 'max' => 150],
            ['campo' => 'fk_moneda_id', 'label' => 'Moneda', 'tipo' => 'select', 'opciones' => 'monedas'],
            ['campo' => 'fk_usuario_id', 'label' => 'Vendedor / Promotor', 'tipo' => 'select', 'opciones' => 'usuarios'],
            ['campo' => 'fk_cliente_id', 'label' => 'Cliente', 'tipo' => 'select', 'opciones' => 'clientes'],
            ['campo' => 'vigencia_in', 'label' => 'Files abiertos desde', 'tipo' => 'date'],
            ['campo' => 'vigencia_out', 'label' => 'Files abiertos hasta', 'tipo' => 'date'],
            ['campo' => 'modelocomision_esquema', 'label' => 'Esquema', 'tipo' => 'radio', 'opciones' => 'esquemas'],
            ['campo' => 'modelocomision_tipo', 'label' => 'Tipo de modelo', 'tipo' => 'radio', 'opciones' => 'tipos'],
            ['campo' => 'modelocomision_asignacion', 'label' => 'Tipo de asignación', 'tipo' => 'radio', 'opciones' => 'asignaciones', 'ancho' => 'completo'],
            ['campo' => 'modelocomision_basecomision', 'label' => 'Base comisión', 'tipo' => 'radio', 'opciones' => 'basesComision', 'ancho' => 'completo'],
            ['campo' => 'modelocomision_basecalculo', 'label' => 'Base cálculo', 'tipo' => 'radio', 'opciones' => 'basesCalculo', 'ancho' => 'completo'],
            ['campo' => 'fk_submodulo_id', 'label' => 'Tipo de producto', 'tipo' => 'select', 'opciones' => 'submodulos'],
            ['campo' => 'modelocomision_prefijo', 'label' => 'Prefijo reserva', 'tipo' => 'radio', 'opciones' => 'prefijos'],
        ];

        for ($a = 1; $a <= 5; $a++) {
            $campos[] = ['campo' => "in{$a}", 'label' => "Rango inicio {$a}", 'tipo' => 'number'];
            $campos[] = ['campo' => "out{$a}", 'label' => "Rango fin {$a}", 'tipo' => 'number'];
            $campos[] = ['campo' => "porcentaje{$a}", 'label' => "Porcentaje {$a}", 'tipo' => 'decimal'];
        }

        $campos[] = ['campo' => 'meta_anual', 'label' => 'Meta anual', 'tipo' => 'number'];

        return $campos;
    }

    protected function opciones(): array
    {
        return [
            'monedas' => $this->catalogos->monedas(),
            'usuarios' => $this->catalogos->usuariosInternos(),
            'clientes' => $this->catalogos->clientes(),
            'submodulos' => $this->catalogos->submodulos(),
            'prefijos' => $this->catalogos->prefijosReserva(),
            'esquemas' => [['value' => 'ACU', 'label' => 'Acumulativo'], ['value' => 'PRO', 'label' => 'Progresivo']],
            'tipos' => [['value' => 'XFI', 'label' => 'X File'], ['value' => 'XSE', 'label' => 'X Servicio']],
            'asignaciones' => [['value' => 'VEN', 'label' => 'Vendedor del file'], ['value' => 'CLI', 'label' => 'Vendedor asociado al cliente'], ['value' => 'PRO', 'label' => 'Promotor']],
            'basesComision' => [['value' => 'COB', 'label' => 'Files cobrados entre fechas'], ['value' => 'FAC', 'label' => 'Files facturados y cobrados entre fechas'], ['value' => 'FIN', 'label' => 'Files con fecha IN entre fechas']],
            'basesCalculo' => [['value' => 'PVI', 'label' => 'Precio de venta (IVA incluido)'], ['value' => 'PVC', 'label' => 'Precio de venta (s/IVA)'], ['value' => 'RBR', 'label' => 'Renta bruta'], ['value' => 'RNE', 'label' => 'Renta neta']],
        ];
    }
}
