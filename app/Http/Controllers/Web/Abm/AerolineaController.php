<?php

namespace App\Http\Controllers\Web\Abm;

use Illuminate\Support\Facades\DB;

class AerolineaController extends AbmController
{
    protected string $tabla = 'aerolinea';
    protected string $pk = 'aerolinea_id';
    protected string $ruta = 'config/aerolineas';
    protected string $titulo = 'Aerolíneas';
    protected string $singular = 'Aerolínea';
    protected array $columnasListado = [
        ['campo' => 'aerolinea_id', 'label' => 'ID'],
        ['campo' => 'aerolinea_nombre', 'label' => 'Nombre'],
        ['campo' => 'aerolinea_codigo', 'label' => 'Código IATA'],
        ['campo' => 'aerolinea_bspcode', 'label' => 'Código BSP'],
    ];
    protected array $filtrosLike = ['aerolinea_nombre', 'aerolinea_codigo'];
    protected string $sortDefault = 'aerolinea_nombre';

    protected function campos(): array
    {
        return [
            ['campo' => 'aerolinea_nombre', 'label' => 'Nombre', 'tipo' => 'text', 'required' => true, 'max' => 150],
            ['campo' => 'aerolinea_codigo', 'label' => 'Código IATA', 'tipo' => 'text', 'max' => 4],
            ['campo' => 'aerolinea_bspcode', 'label' => 'Código BSP', 'tipo' => 'text', 'max' => 3],
            ['campo' => 'comisionbsp', 'label' => 'Comisión BSP (%)', 'tipo' => 'text', 'regla' => 'nullable|numeric'],
            ['campo' => 'fk_cliente_id', 'label' => 'Cliente relacionado', 'tipo' => 'select', 'opciones' => 'clientes'],
            ['campo' => 'fk_proveedor_id', 'label' => 'Proveedor relacionado', 'tipo' => 'select', 'opciones' => 'proveedores'],
        ];
    }

    protected function opciones(): array
    {
        return [
            'clientes' => DB::table('cliente')->orderBy('cliente_nombre')
                ->limit(2000)
                ->get(['cliente_id', 'cliente_nombre'])
                ->map(fn ($c) => ['value' => $c->cliente_id, 'label' => $c->cliente_nombre])
                ->all(),
            'proveedores' => DB::table('proveedor')->orderBy('proveedor_nombre')
                ->limit(2000)
                ->get(['proveedor_id', 'proveedor_nombre'])
                ->map(fn ($p) => ['value' => $p->proveedor_id, 'label' => $p->proveedor_nombre])
                ->all(),
        ];
    }
}
