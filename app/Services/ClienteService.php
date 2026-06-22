<?php

namespace App\Services;

use App\Models\Cliente;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * Lógica de negocio de Clientes compartida por la API (JSON) y el frontend
 * Inertia. Acá vive el "core" de listado/búsqueda para no duplicarlo entre
 * controladores.
 */
class ClienteService
{
    /** Columnas habilitadas para ordenar (whitelist anti inyección). */
    private const ORDENABLES = [
        'cliente_nombre',
        'cliente_razonsocial',
        'cuit',
        'cliente_email',
        'cliente_id',
    ];

    /**
     * Listado paginado de clientes visibles para el usuario actual,
     * con búsqueda, filtro por estado y orden.
     *
     * @param  array{search?:string,estado?:string,sort?:string,dir?:string}  $filtros
     */
    public function listar(array $filtros = [], int $perPage = 20): LengthAwarePaginator
    {
        $query = Cliente::visiblesAlUsuario();

        $search = trim((string) ($filtros['search'] ?? ''));
        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('cliente_nombre', 'LIKE', "%{$search}%")
                    ->orWhere('cliente_razonsocial', 'LIKE', "%{$search}%")
                    ->orWhere('cuit', 'LIKE', "%{$search}%");
            });
        }

        // Estado: 'S' habilitado, 'N' deshabilitado (columna habilita).
        $estado = $filtros['estado'] ?? '';
        if (in_array($estado, ['S', 'N'], true)) {
            $query->where('habilita', $estado);
        }

        $sort = in_array($filtros['sort'] ?? '', self::ORDENABLES, true)
            ? $filtros['sort']
            : 'cliente_nombre';
        $dir = strtolower((string) ($filtros['dir'] ?? 'asc')) === 'desc' ? 'desc' : 'asc';

        $query->orderBy($sort, $dir);

        return $query->paginate($perPage)->withQueryString();
    }
}
