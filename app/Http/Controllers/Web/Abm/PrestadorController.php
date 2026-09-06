<?php

namespace App\Http\Controllers\Web\Abm;

/**
 * Configuración > Proveedores > Prestadores (CI: configuracion/Prestador).
 * Misma tabla que proveedores filtrada por prestador=1 y con un form reducido;
 * el alta fija prestador=1. La réplica a bases hijas no tiene condición de
 * licencia en el legacy.
 */
class PrestadorController extends ProveedorController
{
    protected string $ruta = 'config/prestadores';

    protected string $titulo = 'Prestadores';

    protected string $singular = 'Prestador';

    protected ?int $soloPrestador = 1;

    protected array $columnasListado = [
        ['campo' => 'proveedor_id', 'label' => 'ID'],
        ['campo' => 'proveedor_nombre', 'label' => 'Nombre'],
        ['campo' => 'pais_nombre', 'label' => 'País'],
        ['campo' => 'ciudad_nombre', 'label' => 'Ciudad'],
        ['campo' => 'habilita', 'label' => 'Habilitado', 'tipo' => 'bool'],
    ];

    protected array $filtrosLike = ['proveedor_nombre'];

    protected array $filtrosSelect = [
        ['campo' => 'fk_pais_id', 'label' => 'País', 'opciones' => 'paises'],
        ['campo' => 'fk_ciudad_id', 'label' => 'Ciudad', 'opciones' => 'ciudades'],
    ];

    protected function campos(): array
    {
        return [
            ['campo' => 'proveedor_nombre', 'label' => 'Nombre', 'tipo' => 'text', 'required' => true, 'max' => 150],
            ['campo' => 'proveedor_direccion', 'label' => 'Dirección', 'tipo' => 'text', 'required' => true, 'max' => 255],
            ['campo' => 'proveedor_telefono', 'label' => 'Teléfono', 'tipo' => 'text', 'max' => 50],
            ['campo' => 'proveedor_telefonoemergencia', 'label' => 'Teléfono de emergencia', 'tipo' => 'text', 'max' => 100],
            ['campo' => 'proveedor_codigopostal', 'label' => 'Código postal', 'tipo' => 'text', 'max' => 15],
            ['campo' => 'proveedor_email', 'label' => 'Email', 'tipo' => 'text', 'max' => 100, 'regla' => 'nullable|email|max:100'],
            ['campo' => 'proveedor_emailreservas', 'label' => 'Email reservas', 'tipo' => 'text', 'max' => 100, 'regla' => 'nullable|email|max:100'],
            ['campo' => 'fk_pais_id', 'label' => 'País', 'tipo' => 'select', 'opciones' => 'paises'],
            ['campo' => 'fk_ciudad_id', 'label' => 'Ciudad', 'tipo' => 'select', 'opciones' => 'ciudades', 'dependeDe' => 'fk_pais_id'],
            ['campo' => 'fk_cadenahotelera_id', 'label' => 'Cadena hotelera', 'tipo' => 'select', 'opciones' => 'cadenas'],
            ['campo' => 'habilita', 'label' => 'Habilitar prestador', 'tipo' => 'radio', 'opciones' => 'siNoLetra', 'default' => 'Y'],
        ];
    }

    protected function antesDeGuardar(array $data, int|string|null $id): array
    {
        $data = parent::antesDeGuardar($data, $id);
        $data['prestador'] = 1;

        return $data;
    }

    protected function replica(): bool
    {
        return true;
    }
}
