<?php

namespace App\Services;

use App\Models\Cliente;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

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

    /**
     * Alta de cliente (réplica de configuracion/ruc/save de CI).
     *
     * Inserta en `cliente` y guarda contactos/tarjetas en `cliente_extra`. La
     * tabla legacy tiene casi todas las columnas NOT NULL sin default, así que
     * completamos por introspección (DESCRIBE) las que el form no manda, con un
     * valor por tipo. Todo en una transacción.
     *
     * @param  array<string,mixed>  $data  datos ya validados (ClienteRequest)
     * @return int cliente_id creado
     */
    public function crear(array $data, int $usuarioId, int $licenciaId): int
    {
        return DB::transaction(function () use ($data, $usuarioId, $licenciaId) {
            $contactos = $data['contactos'] ?? null;
            $tarjetas = $data['tarjetas'] ?? null;
            unset($data['contactos'], $data['tarjetas']);

            // Campos que pone el servidor, no el form.
            $data['fk_usuario_id'] = $usuarioId;
            $data['licencia_id'] = $licenciaId;
            $data['fechacarga'] = now();
            $data['um'] = now();

            $clienteId = (int) DB::table('cliente')->insertGetId($this->filaCliente($data), 'cliente_id');

            $this->guardarExtra($clienteId, 'contactos', $contactos);
            $this->guardarExtra($clienteId, 'tarjetas', $tarjetas);

            return $clienteId;
        });
    }

    /**
     * Construye la fila a insertar: toma de $data solo las columnas reales de
     * `cliente` y completa las NOT NULL sin default que falten con un default
     * por tipo (la tabla legacy no tiene defaults).
     *
     * @param  array<string,mixed>  $data
     * @return array<string,mixed>
     */
    private function filaCliente(array $data): array
    {
        $row = [];

        foreach (DB::select('DESCRIBE `cliente`') as $col) {
            if (str_contains((string) $col->Extra, 'auto_increment')) {
                continue;
            }

            $valor = $data[$col->Field] ?? null;

            if (array_key_exists($col->Field, $data) && $valor !== null) {
                $row[$col->Field] = $valor;
            } elseif ($col->Null === 'NO' && $col->Default === null) {
                // Falta, o vino null (ConvertEmptyStringsToNull convierte los '' del form),
                // y la columna es NOT NULL sin default: completamos con un default por tipo.
                $row[$col->Field] = $this->defaultPorTipo((string) $col->Type);
            }
            // nullable o con default: se omite y la resuelve la BD.
        }

        return $row;
    }

    private function defaultPorTipo(string $tipo): string|int
    {
        $t = strtolower($tipo);

        return match (true) {
            str_contains($t, 'int') => 0,
            str_contains($t, 'decimal'), str_contains($t, 'float'), str_contains($t, 'double') => 0,
            str_contains($t, 'timestamp'), str_contains($t, 'datetime') => now()->toDateTimeString(),
            str_contains($t, 'date') => now()->toDateString(),
            default => '', // varchar / char / text
        };
    }

    /** Guarda un set repetible (contactos/tarjetas) como fila JSON en cliente_extra. */
    private function guardarExtra(int $clienteId, string $nombre, ?array $valores): void
    {
        if (empty($valores)) {
            return;
        }

        DB::table('cliente_extra')->insert([
            'fk_cliente_id' => $clienteId,
            'extra_nombre' => $nombre,
            'extra_valor' => json_encode(array_values($valores)),
        ]);
    }
}
