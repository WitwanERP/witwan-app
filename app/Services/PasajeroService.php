<?php

namespace App\Services;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

/**
 * Lógica de negocio de Pasajeros (réplica de configuracion/rup de CI) compartida
 * por el frontend Inertia. Incluye la función clave de CI: crear un cliente a
 * partir de un pasajero (tilde "es_cliente"), con control de duplicados para no
 * crear dos veces el mismo cliente.
 */
class PasajeroService
{
    /** CUIT/documentos "dummy" que CI no considera para detectar duplicados. */
    private const FISCALES_DUMMY = ['555555555', '55555555', '5555555555'];

    public function __construct(private ClienteService $clientes) {}

    /** Listado paginado de pasajeros (columnas y filtros fieles a CI/rup). */
    public function listar(array $filtros = [], int $perPage = 80): LengthAwarePaginator
    {
        $query = DB::table('pasajero')->select([
            'pasajero_id', 'pasajero_apellido', 'pasajero_nombre', 'pasajero_email', 'fk_cliente_id',
        ]);

        foreach (['pasajero_apellido', 'pasajero_nombre', 'pasajero_email'] as $campo) {
            $valor = trim((string) ($filtros[$campo] ?? ''));
            if ($valor !== '') {
                $query->where($campo, 'LIKE', "%{$valor}%");
            }
        }

        $id = trim((string) ($filtros['pasajero_id'] ?? ''));
        if ($id !== '' && ctype_digit($id)) {
            $query->where('pasajero_id', (int) $id);
        }

        $sort = in_array($filtros['sort'] ?? '', ['pasajero_id', 'pasajero_apellido', 'pasajero_nombre', 'pasajero_email'], true)
            ? $filtros['sort']
            : 'pasajero_apellido';
        $dir = strtolower((string) ($filtros['dir'] ?? 'asc')) === 'desc' ? 'desc' : 'asc';
        $query->orderBy($sort, $dir);

        return $query->paginate($perPage)->withQueryString();
    }

    /**
     * Carga un pasajero para editar (fila de `pasajero`). Devuelve null si no existe.
     *
     * @return array<string,mixed>|null
     */
    public function paraEditar(int $id): ?array
    {
        $pasajero = DB::table('pasajero')->where('pasajero_id', $id)->first();

        return $pasajero === null ? null : (array) $pasajero;
    }

    /**
     * Alta de pasajero. Si viene `es_cliente`, crea (o vincula) un cliente a
     * partir de los datos del pasajero y lo deja enlazado en fk_cliente_id.
     *
     * @param  array<string,mixed>  $data  datos ya validados
     * @return int pasajero_id creado
     */
    public function crear(array $data, int $usuarioId, int $licenciaId): int
    {
        return DB::transaction(function () use ($data, $usuarioId, $licenciaId) {
            $esCliente = ! empty($data['es_cliente']);
            unset($data['es_cliente']);

            if ($esCliente) {
                $data['fk_cliente_id'] = $this->crearOVincularCliente($data, $usuarioId, $licenciaId);
                $data['mostrar_ficha'] = 1;
            }

            return (int) DB::table('pasajero')->insertGetId($this->fila($data), 'pasajero_id');
        });
    }

    /**
     * Edición de pasajero. También permite convertirlo en cliente si se tilda
     * `es_cliente` y aún no estaba vinculado.
     *
     * @param  array<string,mixed>  $data  datos ya validados
     */
    public function actualizar(int $id, array $data, int $usuarioId, int $licenciaId): void
    {
        DB::transaction(function () use ($id, $data, $usuarioId, $licenciaId) {
            $esCliente = ! empty($data['es_cliente']);
            unset($data['es_cliente']);

            $yaVinculado = (int) DB::table('pasajero')->where('pasajero_id', $id)->value('fk_cliente_id');

            if ($esCliente && $yaVinculado <= 0) {
                $data['fk_cliente_id'] = $this->crearOVincularCliente($data, $usuarioId, $licenciaId);
                $data['mostrar_ficha'] = 1;
            }

            $fila = $this->filaUpdate($data);
            if (! empty($fila)) {
                DB::table('pasajero')->where('pasajero_id', $id)->update($fila);
            }
        });
    }

    /**
     * Crea un cliente a partir de los datos del pasajero, o devuelve el id del
     * cliente existente si ya hay uno con el mismo nombre o documento fiscal
     * (control de duplicados: NO crea dos veces el mismo cliente).
     *
     * @param  array<string,mixed>  $data
     * @return int cliente_id (existente o recién creado)
     */
    public function crearOVincularCliente(array $data, int $usuarioId, int $licenciaId): int
    {
        $nombre = strtoupper(trim(($data['pasajero_apellido'] ?? '').' '.($data['pasajero_nombre'] ?? '')));
        $fiscal = str_replace(['-', '.', ' '], '', (string) ($data['nro_clavefiscal'] ?? ''));
        $fiscalValido = $fiscal !== '' && ! in_array($fiscal, self::FISCALES_DUMMY, true);

        $existente = DB::table('cliente')
            ->where(function ($q) use ($nombre, $fiscal, $fiscalValido) {
                $q->whereRaw('UPPER(cliente_nombre) = ?', [$nombre]);
                if ($fiscalValido) {
                    $q->orWhereRaw(ClienteService::SQL_CUIT_NORMALIZADO.' = ?', [$fiscal]);
                }
            })
            ->value('cliente_id');

        if ($existente) {
            return (int) $existente; // ya existe: lo reutilizamos, no duplicamos
        }

        $clienteData = [
            'cliente_nombre' => $nombre,
            'cliente_razonsocial' => $nombre,
            'cliente_pasajerodirecto' => 1,
            'clienteminorista' => 1,
            'habilita' => $data['habilita'] ?? 'Y',
            'fk_usuario_vendedor' => $data['fk_usuario_vendedor'] ?? 0,
            'cliente_direccionfiscal' => $data['pasajero_direccionfiscal'] ?? '',
            'cliente_codigopostal' => $data['pasajero_codigopostal'] ?? '',
            'fk_pais_id' => $data['fk_pais_id'] ?? 0,
            'cliente_ciudad' => $data['pasajero_ciudad'] ?? '',
            'fk_tipoclavefiscal_id' => $data['fk_tipoclavefiscal_id'] ?? 0,
            'nro_clavefiscal' => $data['nro_clavefiscal'] ?? '',
            'cuit' => $data['nro_clavefiscal'] ?? '',
            'fk_condicioniva_id' => $data['fk_condicioniva_id'] ?? 0,
            'fk_tarifario1_id' => $data['fk_tarifario1_id'] ?? 0,
            'fk_tarifario2_id' => $data['fk_tarifario2_id'] ?? 0,
            'fk_moneda_id' => $data['fk_moneda_id'] ?? '',
            'gastos_iva' => $data['gastos_iva'] ?? 0,
            'gastos_porcentaje_1' => $data['gastos_porcentaje_1'] ?? 0,
            'gastos_fijo_1' => $data['gastos_fijo_1'] ?? 0,
        ];

        return $this->clientes->crear($clienteData, $usuarioId, $licenciaId);
    }

    /**
     * Fila para INSERT: columnas reales de `pasajero` presentes en $data, y las
     * NOT NULL sin default que falten completadas por tipo (tabla legacy sin
     * defaults), igual criterio que ClienteService.
     *
     * @param  array<string,mixed>  $data
     * @return array<string,mixed>
     */
    private function fila(array $data): array
    {
        $row = [];

        foreach (DB::select('DESCRIBE `pasajero`') as $col) {
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

        return $row;
    }

    /**
     * Fila para UPDATE: solo columnas reales presentes en $data (sin defaults).
     *
     * @param  array<string,mixed>  $data
     * @return array<string,mixed>
     */
    private function filaUpdate(array $data): array
    {
        $columnas = collect(DB::select('DESCRIBE `pasajero`'))
            ->reject(fn ($col) => str_contains((string) $col->Extra, 'auto_increment'))
            ->pluck('Field')
            ->all();

        return array_intersect_key($data, array_flip($columnas));
    }

    private function defaultPorTipo(string $tipo): string|int|float
    {
        $t = strtolower($tipo);

        return match (true) {
            str_contains($t, 'int') => 0,
            str_contains($t, 'decimal'), str_contains($t, 'float'), str_contains($t, 'double') => 0,
            str_contains($t, 'date') => now()->toDateString(),
            default => '',
        };
    }
}
