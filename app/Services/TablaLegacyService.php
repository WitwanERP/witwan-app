<?php

namespace App\Services;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

/**
 * Helpers genéricos para ABMs sobre las tablas legacy de CI, que tienen casi
 * todas las columnas NOT NULL sin default y, en algunos casos, una PK que NO es
 * auto_increment (hay que asignar el id a mano con MAX+1).
 *
 * Centraliza el patrón que ClienteService/PasajeroService aplican a mano para
 * que los ABMs de configuración (geo, negocios, banco, etc.) no lo repitan.
 */
class TablaLegacyService
{
    /**
     * Listado paginado genérico: filtros de texto (LIKE %valor%) y match exacto
     * por id, con orden por whitelist.
     *
     * @param  list<string>  $columnas      columnas a seleccionar
     * @param  list<string>  $filtrosLike   columnas que filtran por LIKE
     * @param  array<string,mixed>  $filtros valores de filtro (incluye sort/dir)
     */
    public function listar(string $tabla, array $columnas, array $filtrosLike, array $filtros, string $pk, string $sortDefault, int $perPage = 50, array $filtrosExactos = []): LengthAwarePaginator
    {
        $query = DB::table($tabla)->select($columnas);

        foreach ($filtrosLike as $campo) {
            $valor = trim((string) ($filtros[$campo] ?? ''));
            if ($valor !== '') {
                $query->where($campo, 'LIKE', "%{$valor}%");
            }
        }

        // Filtros de igualdad exacta (selects/FK).
        foreach ($filtrosExactos as $campo) {
            $valor = (string) ($filtros[$campo] ?? '');
            if ($valor !== '') {
                $query->where($campo, $valor);
            }
        }

        $id = trim((string) ($filtros[$pk] ?? ''));
        if ($id !== '' && ctype_digit($id)) {
            $query->where($pk, (int) $id);
        }

        $sort = in_array($filtros['sort'] ?? '', $columnas, true) ? $filtros['sort'] : $sortDefault;
        $dir = strtolower((string) ($filtros['dir'] ?? 'asc')) === 'desc' ? 'desc' : 'asc';
        $query->orderBy($sort, $dir);

        return $query->paginate($perPage)->withQueryString();
    }

    /**
     * Inserta una fila completando NOT NULL faltantes con defaults por tipo y,
     * si la PK no es auto_increment, asignando id = MAX(pk)+1. Devuelve el id.
     *
     * @param  array<string,mixed>  $data
     */
    public function insertar(string $tabla, array $data): int
    {
        $pk = null;
        $auto = false;
        $row = [];

        foreach ($this->columnas($tabla) as $col) {
            if ($col->Key === 'PRI') {
                $pk = $col->Field;
                $auto = str_contains((string) $col->Extra, 'auto_increment');
            }

            if (str_contains((string) $col->Extra, 'auto_increment')) {
                continue;
            }

            $valor = $data[$col->Field] ?? null;

            if (array_key_exists($col->Field, $data) && $valor !== null) {
                $row[$col->Field] = $valor;
            } elseif ($col->Null === 'NO' && $col->Default === null) {
                $row[$col->Field] = $this->defaultPorTipo((string) $col->Type);
            }
        }

        // PK no auto_increment: la calculamos a mano (como hace CI con MAX+1).
        if ($pk !== null && ! $auto && empty($row[$pk])) {
            $row[$pk] = ((int) DB::table($tabla)->max($pk)) + 1;
            DB::table($tabla)->insert($row);

            return (int) $row[$pk];
        }

        return (int) DB::table($tabla)->insertGetId($row, $pk);
    }

    /**
     * Actualiza por PK con solo las columnas reales presentes en $data (sin
     * pisar el resto con defaults).
     *
     * @param  array<string,mixed>  $data
     */
    public function actualizar(string $tabla, string $pk, int $id, array $data): void
    {
        $columnas = collect($this->columnas($tabla))->pluck('Field')->all();
        $fila = array_intersect_key($data, array_flip($columnas));
        unset($fila[$pk]);

        if (! empty($fila)) {
            DB::table($tabla)->where($pk, $id)->update($fila);
        }
    }

    /** @return array<string,mixed>|null */
    public function paraEditar(string $tabla, string $pk, int $id): ?array
    {
        $row = DB::table($tabla)->where($pk, $id)->first();

        return $row === null ? null : (array) $row;
    }

    public function eliminar(string $tabla, string $pk, int $id): void
    {
        DB::table($tabla)->where($pk, $id)->delete();
    }

    /** @return array<int,object> */
    private function columnas(string $tabla): array
    {
        return DB::select('DESCRIBE `'.str_replace('`', '', $tabla).'`');
    }

    private function defaultPorTipo(string $tipo): string|int|float
    {
        $t = strtolower($tipo);

        return match (true) {
            str_contains($t, 'int') => 0,
            str_contains($t, 'decimal'), str_contains($t, 'float'), str_contains($t, 'double') => 0,
            str_contains($t, 'datetime'), str_contains($t, 'timestamp') => now()->toDateTimeString(),
            str_contains($t, 'date') => now()->toDateString(),
            default => '',
        };
    }
}
