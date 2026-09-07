<?php

namespace App\Services\Reservas\Busqueda;

use Illuminate\Support\Facades\DB;

/**
 * Productos candidatos a cotizar en una búsqueda: port del SELECT de
 * reserva.php::resultado() (3891-3910) con sus filtros.
 *
 *   producto ⋈ rel_productociudad ⋈ vigencia ⟕ productogrupo
 *   WHERE habilitar=1 AND eliminar=0 AND (grupo no eliminado)
 *     AND tipo AND sistema (minorista 3 vende los de mayorista 2)
 *     AND aparece_tarifario=1 (sólo usuarios externos)
 *     AND ciudad / origen / destino / nombre LIKE / producto puntual
 *     AND alguna vigencia cubra la fecha de inicio (y la de fin en alojamiento)
 *
 * El tarifado fino (día por día, cupos, menores) lo hace después el Tarifador
 * por producto; acá sólo se acota la lista para no cotizar el catálogo entero.
 */
class CandidatosQuery
{
    /**
     * @param  array  $filtros  ['ciudades'=>list<int>, 'origen'=>int, 'destino'=>int, 'nombre'=>string, 'producto_ids'=>list<int>, 'stars'=>list<int>,
     *                          'from'=>Y-m-d, 'to'=>Y-m-d|null, 'solo_circuito'=>bool, 'limite'=>int]
     * @return array{productos: list<object>, truncado: bool}
     */
    public function buscar(string $tipo, array $filtros, array $ctx): array
    {
        $limite = (int) ($filtros['limite'] ?? config('reservas_busqueda.max_candidatos', 60));
        $q = DB::table('producto')
            ->leftJoin('productogrupo as pg', 'pg.productogrupo_id', '=', 'producto.fk_productogrupo_id')
            ->where('producto.habilitar', 1)->where('producto.eliminar', 0)
            ->where(fn ($w) => $w->whereNull('pg.productogrupo_id')->orWhere('pg.eliminar', 0))
            ->where('producto.fk_tipoproducto_id', $tipo)
            ->where('producto.fk_sistema_id', (int) $ctx['sistema_productos'])
            ->select('producto.producto_id', 'producto.producto_nombre', 'producto.fk_tipoproducto_id', 'producto.fk_proveedor_id', 'producto.fk_prestador_id',
                'producto.origen', 'producto.destino', 'producto.disponibilidad', 'producto.modotarifa', 'producto.politica_cancelacion')
            ->orderBy('producto.producto_nombre')
            ->limit($limite + 1);

        if (empty($ctx['interno'])) {
            $q->where('producto.aparece_tarifario', 1);
        }

        $ids = array_values(array_filter(array_map('intval', (array) ($filtros['producto_ids'] ?? []))));
        if ($ids !== []) {
            $q->whereIn('producto.producto_id', $ids);
        }
        $ciudades = array_values(array_filter(array_map('intval', (array) ($filtros['ciudades'] ?? []))));
        if ($ids === [] && $ciudades !== []) {
            $q->whereExists(fn ($s) => $s->selectRaw('1')->from('rel_productociudad as rpc')->whereColumn('rpc.fk_producto_id', 'producto.producto_id')->whereIn('rpc.fk_ciudad_id', $ciudades));
        }
        if (! empty($filtros['origen'])) {
            $q->where('producto.origen', (int) $filtros['origen']);
        }
        if (! empty($filtros['destino'])) {
            $q->where('producto.destino', (int) $filtros['destino']);
        }
        $nombre = trim((string) ($filtros['nombre'] ?? ''));
        if ($nombre !== '') {
            $q->where('producto.producto_nombre', 'LIKE', '%'.str_replace(['%', '_'], ['\%', '\_'], $nombre).'%');
        }
        if (! empty($filtros['solo_circuito'])) {
            // PAQ: sólo circuitos (reserva.php:3892-3894).
            $q->whereExists(fn ($s) => $s->selectRaw('1')->from('producto_extra as pe')->whereColumn('pe.fk_producto_id', 'producto.producto_id')->where('pe.extra_nombre', 'circuito')->where('pe.extra_valor', '1'));
        }
        $stars = array_values(array_filter(array_map('intval', (array) ($filtros['stars'] ?? []))));
        if ($stars !== []) {
            $q->whereExists(fn ($s) => $s->selectRaw('1')->from('producto_extra as pe')->join('hotelcategoria as hc', DB::raw('CAST(hc.hotelcategoria_id AS CHAR)'), '=', 'pe.extra_valor')
                ->whereColumn('pe.fk_producto_id', 'producto.producto_id')->where('pe.extra_nombre', 'fk_hotelcategoria_id')->whereIn(DB::raw('FLOOR(hc.hotelcategoria_stars)'), $stars));
        }
        // Alguna vigencia tiene que cubrir el inicio (y el fin, en alojamiento) — vigencia_ini <= from AND vigencia_fin >= from (reserva.php:3266-3282).
        $from = (string) ($filtros['from'] ?? '');
        $to = (string) ($filtros['to'] ?? '');
        if ($from !== '') {
            $q->whereExists(function ($s) use ($from, $to) {
                $s->selectRaw('1')->from('vigencia as v')->whereColumn('v.fk_producto_id', 'producto.producto_id')->where('v.vigencia_ini', '<=', $from);
                $s->where('v.vigencia_fin', '>=', $from);
                if ($to !== '' && $to > $from) {
                    // La estadía puede cruzar vigencias: alcanza con que alguna toque el rango.
                    $s->orWhere(fn ($w) => $w->whereColumn('v.fk_producto_id', 'producto.producto_id')->where('v.vigencia_ini', '<=', $to)->where('v.vigencia_fin', '>=', $from));
                }
            });
        }

        $filas = $q->get()->all();
        $truncado = count($filas) > $limite;
        if ($truncado) {
            array_pop($filas);
        }

        return ['productos' => $this->enriquecer($filas), 'truncado' => $truncado];
    }

    /** Proveedor, estrellas y ciudad principal, en lote. */
    private function enriquecer(array $productos): array
    {
        if ($productos === []) {
            return [];
        }
        $ids = array_map(fn ($p) => (int) $p->producto_id, $productos);
        $proveedores = DB::table('proveedor')->whereIn('proveedor_id', array_unique(array_filter(array_map(fn ($p) => (int) $p->fk_proveedor_id, $productos))))->pluck('proveedor_nombre', 'proveedor_id');
        $estrellas = DB::table('producto_extra as pe')->join('hotelcategoria as hc', DB::raw('CAST(hc.hotelcategoria_id AS CHAR)'), '=', 'pe.extra_valor')
            ->whereIn('pe.fk_producto_id', $ids)->where('pe.extra_nombre', 'fk_hotelcategoria_id')->pluck('hc.hotelcategoria_stars', 'pe.fk_producto_id');
        $ciudadIds = array_unique(array_filter(array_map(fn ($p) => (int) $p->destino ?: (int) $p->origen, $productos)));
        $primeraCiudad = DB::table('rel_productociudad as rpc')->join('ciudad as c', 'c.ciudad_id', '=', 'rpc.fk_ciudad_id')->whereIn('rpc.fk_producto_id', $ids)->orderBy('c.ciudad_nombre')->get(['rpc.fk_producto_id', 'c.ciudad_id', 'c.ciudad_nombre']);
        $porProducto = [];
        foreach ($primeraCiudad as $c) {
            $porProducto[(int) $c->fk_producto_id] ??= ['id' => (int) $c->ciudad_id, 'nombre' => (string) $c->ciudad_nombre];
        }
        $nombresCiudad = $ciudadIds === [] ? collect() : DB::table('ciudad')->whereIn('ciudad_id', $ciudadIds)->pluck('ciudad_nombre', 'ciudad_id');

        foreach ($productos as $p) {
            $p->proveedor_nombre = (string) ($proveedores[(int) $p->fk_proveedor_id] ?? '');
            $p->estrellas = isset($estrellas[(int) $p->producto_id]) ? (float) $estrellas[(int) $p->producto_id] : null;
            $destino = (int) $p->destino ?: (int) $p->origen;
            $p->ciudad = $destino && isset($nombresCiudad[$destino]) ? ['id' => $destino, 'nombre' => (string) $nombresCiudad[$destino]] : ($porProducto[(int) $p->producto_id] ?? ['id' => $destino, 'nombre' => '']);
        }

        return $productos;
    }
}
