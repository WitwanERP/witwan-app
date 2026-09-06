<?php

namespace App\Http\Controllers\Web\Abm;

/**
 * Administración > Monedas > Monedas (CI: administracion/moneda). La PK es el
 * código de 3 letras que escribe el usuario (la tabla legacy ni siquiera tiene
 * PRIMARY KEY), por eso no es numérica y sólo se define en el alta.
 */
class MonedaController extends AbmController
{
    protected string $tabla = 'moneda';

    protected string $pk = 'moneda_id';

    protected bool $pkNumerica = false;

    protected string $ruta = 'admin/monedas';

    protected string $titulo = 'Monedas';

    protected string $singular = 'Moneda';

    protected string $area = 'Administración';

    protected array $columnasListado = [
        ['campo' => 'moneda_id', 'label' => 'ID'],
        ['campo' => 'moneda_nombre', 'label' => 'Nombre'],
        ['campo' => 'iso_code', 'label' => 'ISO'],
        ['campo' => 'descripcion', 'label' => 'Descripción'],
        ['campo' => 'orden', 'label' => 'Orden'],
        ['campo' => 'moneda_basica', 'label' => 'Básica', 'tipo' => 'bool'],
    ];

    protected array $filtrosLike = ['moneda_nombre', 'iso_code'];

    protected string $sortDefault = 'moneda_id';

    protected function campos(): array
    {
        return [
            ['campo' => 'moneda_id', 'label' => 'ID (3 caracteres)', 'tipo' => 'text', 'required' => true, 'soloAlta' => true, 'regla' => 'required|string|size:3|alpha_num|unique:moneda,moneda_id', 'ayuda' => 'Debe ser 3 caracteres. No se puede cambiar después del alta.'],
            ['campo' => 'moneda_nombre', 'label' => 'Nombre', 'tipo' => 'text', 'required' => true, 'max' => 100],
            ['campo' => 'iso_code', 'label' => 'ISO', 'tipo' => 'text', 'max' => 3],
            ['campo' => 'descripcion', 'label' => 'Descripción', 'tipo' => 'textarea', 'max' => 100],
            ['campo' => 'orden', 'label' => 'Orden', 'tipo' => 'number'],
        ];
    }

    protected function antesDeGuardar(array $data, int|string|null $id): array
    {
        if (isset($data['moneda_id'])) {
            $data['moneda_id'] = strtoupper($data['moneda_id']);
        }

        return $data;
    }
}
