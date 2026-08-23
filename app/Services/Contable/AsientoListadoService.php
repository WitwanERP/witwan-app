<?php

namespace App\Services\Contable;

use App\Support\Contable\TipoAsiento;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Listado de asientos de administración (`ordenadmin` filtrada por `tipo`).
 *
 * Réplica del listado que arma el Admin_Controller genérico para
 * asientocontable.php, asientocta.php y fondos.php: los tres declaran la misma
 * tabla, el mismo orden (`ordenadmin.fecha DESC`) y el mismo juego de columnas,
 * y sólo cambian el `where` por tipo.
 *
 * Diferencias deliberadas con el legacy:
 *
 *  1. **Se pagina de verdad.** El genérico trae todo (`_limit` gigante) y
 *     recorta en PHP.
 *  2. **Se agregan filtros por usuario, moneda y estado.** En el CI esas tres
 *     columnas se muestran pero no se pueden filtrar (`display` sin 'filter'),
 *     así que para encontrar el asiento anulado de tal usuario había que
 *     barrer el listado a ojo.
 *  3. **Hay totales por moneda al pie.** El legacy no totaliza nada en estos
 *     listados, y como conviven ARS y USD en la misma grilla, sumar la columna
 *     a mano da cualquier cosa.
 *
 * Lo que NO cambia: sin ningún filtro no se consulta (los tres controllers
 * setean `_filterbefore = true`), porque `ordenadmin` acumula años de asientos.
 */
class AsientoListadoService
{
    /** Filtros aceptados desde la query string. */
    public const FILTROS = [
        'numero',
        'fecha_desde',
        'fecha_hasta',
        'usuario',
        'moneda',
        'status',
        'proyecto',
        'observaciones',
    ];

    /** ¿Hay algún filtro aplicado? Sin filtros el listado no se consulta. */
    public function hayFiltros(array $filtros): bool
    {
        foreach (self::FILTROS as $f) {
            if (isset($filtros[$f]) && $filtros[$f] !== '' && $filtros[$f] !== null) {
                return true;
            }
        }

        return false;
    }

    /** Página de resultados, mapeada al DTO que consume el front. */
    public function listar(TipoAsiento $tipo, array $filtros, ?int $perPage = null): LengthAwarePaginator
    {
        return $this->query($tipo, $filtros)
            ->paginate($perPage ?? (int) config('asientos.per_page', 50))
            ->withQueryString()
            ->through(fn ($fila) => $this->fila($fila));
    }

    /** Todo el conjunto filtrado, sin paginar (export). */
    public function todos(TipoAsiento $tipo, array $filtros): Collection
    {
        return $this->query($tipo, $filtros)->get()->map(fn ($fila) => $this->fila($fila));
    }

    /**
     * Totales por moneda, más el total de asientos.
     *
     * No se suma entre monedas a propósito: `ordenadmin.monto` está expresado en
     * la moneda de cada asiento y no hay una cotización única con la que
     * unificarlos (la del asiento es la del día en que se cargó).
     */
    public function totales(TipoAsiento $tipo, array $filtros): array
    {
        $porMoneda = DB::query()
            ->fromSub($this->baseQuery($tipo, $filtros)->select([
                'ordenadmin.fk_moneda_id',
                'ordenadmin.monto',
                'ordenadmin.status',
            ]), 'o')
            ->groupBy('o.fk_moneda_id')
            ->orderBy('o.fk_moneda_id')
            ->selectRaw(
                'o.fk_moneda_id AS moneda, COUNT(*) AS cantidad, '.
                'ROUND(SUM(o.monto), 2) AS monto, '.
                // Los anulados siguen figurando en el listado (el legacy los
                // muestra tachados), así que se totalizan aparte para que el
                // pie no mienta.
                "ROUND(SUM(IF(o.status = 'AN', 0, o.monto)), 2) AS monto_vigente"
            )
            ->get();

        return [
            'porMoneda' => $porMoneda->map(fn ($f) => [
                'moneda' => (string) $f->moneda,
                'cantidad' => (int) $f->cantidad,
                'monto' => (float) $f->monto,
                'montoVigente' => (float) $f->monto_vigente,
            ])->all(),
            'cantidad' => (int) $porMoneda->sum('cantidad'),
        ];
    }

    /** Query completa, con proyección y orden. Pública porque la comparten listado y export. */
    public function query(TipoAsiento $tipo, array $filtros): Builder
    {
        return $this->baseQuery($tipo, $filtros)
            ->select([
                'ordenadmin.ordenadmin_id',
                'ordenadmin.nropago',
                'ordenadmin.fecha',
                'ordenadmin.fk_moneda_id',
                'ordenadmin.monto',
                'ordenadmin.cotizacion',
                'ordenadmin.status',
                'ordenadmin.observaciones',
                'ordenadmin.fk_proyecto_id',
                'proyecto.proyecto_nombre',
                DB::raw("TRIM(CONCAT(COALESCE(usuario.usuario_nombre,''),' ',COALESCE(usuario.usuario_apellido,''))) AS usuario_nombre"),
                // Subconsulta y no join: `movimiento` es 1:N y un join
                // multiplicaría la fila del asiento por cada movimiento.
                DB::raw('(SELECT COUNT(*) FROM movimiento m WHERE m.fk_ordenadmin_id = ordenadmin.ordenadmin_id) AS movimientos'),
            ])
            // El orden del legacy es sólo por fecha; se agrega el id como
            // desempate porque sin él la paginación real puede repetir o saltear
            // filas entre páginas (con `_limit` gigante eso no se notaba).
            ->orderByDesc('ordenadmin.fecha')
            ->orderByDesc('ordenadmin.ordenadmin_id');
    }

    /**
     * Completa los rangos de fecha que llegan a medias.
     *
     * Mismo criterio que el listado de facturas de tercero: el front deja los
     * dos extremos a la vista, pero a esta query también se llega por URL (un
     * link guardado, el export). Un "desde" suelto se cierra en hoy, como hace
     * el legacy; un "hasta" suelto queda como cota superior abierta en lugar de
     * descartar el filtro entero y devolver todo, que es el resultado engañoso
     * que se quiere evitar.
     */
    private function normalizarRangos(array $filtros): array
    {
        if (! blank($filtros['fecha_desde'] ?? null) && blank($filtros['fecha_hasta'] ?? null)) {
            $filtros['fecha_hasta'] = Carbon::today()->format('Y-m-d');
        }

        return $filtros;
    }

    /**
     * Joins y WHERE, sin proyección: lo comparten listado, totales y export.
     *
     * Sin GROUP BY: los dos joins son 1:1, así que el `_group` del genérico no
     * agrupa nada. (Es lo contrario del listado de facturas de tercero, donde el
     * join a servicios sí multiplica filas y el GROUP BY es obligatorio.)
     */
    private function baseQuery(TipoAsiento $tipo, array $filtros): Builder
    {
        $filtros = $this->normalizarRangos($filtros);

        $q = DB::table('ordenadmin')
            ->leftJoin('usuario', 'usuario.usuario_id', '=', 'ordenadmin.fk_usuario_id')
            ->leftJoin('proyecto', 'proyecto.proyecto_id', '=', 'ordenadmin.fk_proyecto_id')
            ->where('ordenadmin.tipo', $tipo->codigo());

        $v = fn (string $k) => isset($filtros[$k]) && $filtros[$k] !== '' && $filtros[$k] !== null ? $filtros[$k] : null;

        // El filtro genérico del Admin_Controller sobre campos de texto es LIKE
        // (Form.php:312-327), no igualdad.
        if ($n = $v('numero')) {
            $q->where('ordenadmin.nropago', 'like', '%'.$n.'%');
        }
        if ($o = $v('observaciones')) {
            $q->where('ordenadmin.observaciones', 'like', '%'.$o.'%');
        }
        if ($u = $v('usuario')) {
            $q->where('ordenadmin.fk_usuario_id', (int) $u);
        }
        if ($m = $v('moneda')) {
            $q->where('ordenadmin.fk_moneda_id', $m);
        }
        if ($s = $v('status')) {
            $q->where('ordenadmin.status', $s);
        }
        if ($p = $v('proyecto')) {
            $q->where('ordenadmin.fk_proyecto_id', (int) $p);
        }
        if ($d = $v('fecha_desde')) {
            $q->whereDate('ordenadmin.fecha', '>=', self::fecha($d));
        }
        if ($h = $v('fecha_hasta')) {
            $q->whereDate('ordenadmin.fecha', '<=', self::fecha($h));
        }

        return $q;
    }

    /** DTO de una fila del listado. */
    private function fila(object $f): array
    {
        return [
            'id' => (int) $f->ordenadmin_id,
            'numero' => (string) $f->nropago,
            'fecha' => self::soloFecha($f->fecha),
            'moneda' => (string) $f->fk_moneda_id,
            'monto' => (float) $f->monto,
            'cotizacion' => (float) $f->cotizacion,
            'status' => (string) $f->status,
            'estado' => (string) (config('asientos.estados')[$f->status] ?? $f->status),
            'anulado' => $f->status === 'AN',
            'observaciones' => (string) $f->observaciones,
            'usuario' => (string) $f->usuario_nombre,
            'proyecto' => $f->proyecto_nombre !== null ? trim((string) $f->proyecto_nombre) : null,
            'movimientos' => (int) $f->movimientos,
        ];
    }

    /** Acepta ISO y dd/mm/YYYY, como el resto del módulo. */
    public static function fecha(string $valor): string
    {
        if (preg_match('#^(\d{2})[/-](\d{2})[/-](\d{4})$#', $valor, $m)) {
            return "{$m[3]}-{$m[2]}-{$m[1]}";
        }

        return substr($valor, 0, 10);
    }

    private static function soloFecha(mixed $valor): ?string
    {
        $texto = (string) $valor;

        return $texto === '' || str_starts_with($texto, '0000') ? null : substr($texto, 0, 10);
    }
}
