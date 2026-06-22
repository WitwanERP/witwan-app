<?php

namespace App\Services;

use App\Models\Cliente;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * Lógica de negocio de Clientes compartida por la API (JSON) y el frontend
 * Inertia. Acá vive el "core" de listado/búsqueda para no duplicarlo entre
 * controladores.
 *
 * El listado es FIEL al de CodeIgniter (configuracion/ruc): mismas columnas
 * y mismos filtros (ver el array $_fields de Ruc, sub-array 'display').
 */
class ClienteService
{
    /** Columnas que se muestran en el listado (= 'display' ⊇ 'list' en CI). */
    private const COLUMNAS_LISTADO = [
        'cliente_id',
        'cliente_nombre',
        'cliente_razonsocial',
        'limite_credito',
        'cuit',
    ];

    /** Columnas habilitadas para ordenar (whitelist anti inyección). */
    private const ORDENABLES = self::COLUMNAS_LISTADO;

    /** Filtros de texto → LIKE %valor%. */
    private const FILTROS_LIKE = [
        'cliente_nombre',
        'cliente_razonsocial',
        'cuit',
        'cliente_ciudad',
    ];

    /** Filtros de select (FK / código) → igualdad exacta. */
    private const FILTROS_EXACTOS = [
        'fk_pais_id',
        'fk_usuario_vendedor',
        'fk_cadenacliente_id',
        'fk_moneda_id',
    ];

    /**
     * Listado paginado de clientes visibles para el usuario actual.
     *
     * @param  array<string,mixed>  $filtros  cliente_id, cliente_nombre, cliente_razonsocial,
     *                                        cuit, cliente_ciudad, fk_pais_id, fk_usuario_vendedor, fk_cadenacliente_id,
     *                                        fk_moneda_id, clienteminorista, sort, dir
     */
    public function listar(array $filtros = [], int $perPage = 80): LengthAwarePaginator
    {
        $query = Cliente::visiblesAlUsuario()->select(self::COLUMNAS_LISTADO);

        // Texto: LIKE %valor%
        foreach (self::FILTROS_LIKE as $campo) {
            $valor = trim((string) ($filtros[$campo] ?? ''));
            if ($valor !== '') {
                $query->where($campo, 'LIKE', "%{$valor}%");
            }
        }

        // ID: match exacto si es numérico
        $id = trim((string) ($filtros['cliente_id'] ?? ''));
        if ($id !== '' && ctype_digit($id)) {
            $query->where('cliente_id', (int) $id);
        }

        // Selects: igualdad exacta (solo si hay valor)
        foreach (self::FILTROS_EXACTOS as $campo) {
            $valor = (string) ($filtros[$campo] ?? '');
            if ($valor !== '') {
                $query->where($campo, $valor);
            }
        }

        // Boolean cliente minorista (0/1)
        $minorista = (string) ($filtros['clienteminorista'] ?? '');
        if ($minorista === '0' || $minorista === '1') {
            $query->where('clienteminorista', (int) $minorista);
        }

        // Orden (whitelist; default cliente_nombre ASC, como CI)
        $sort = in_array($filtros['sort'] ?? '', self::ORDENABLES, true)
            ? $filtros['sort']
            : 'cliente_nombre';
        $dir = strtolower((string) ($filtros['dir'] ?? 'asc')) === 'desc' ? 'desc' : 'asc';
        $query->orderBy($sort, $dir);

        return $query->paginate($perPage)->withQueryString();
    }
}
