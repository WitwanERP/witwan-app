<?php

namespace App\Services\Reservas;

use App\Services\Reservas\Busqueda\CandidatosQuery;
use App\Services\Reservas\Busqueda\Presupuesto;
use App\Support\Licencia;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * "Completar el viaje": al agregar un servicio al carrito se ofrecen productos
 * complementarios (traslados, excursiones, asistencia, guías…) del destino,
 * cotizados para las mismas fechas y pax, ordenados por lo que históricamente
 * se vendió junto con ese producto o en esa ciudad (self-join de `servicio`
 * por reserva). No hay tabla de relaciones curadas: el historial es la señal.
 */
class CrossSellingService
{
    public function __construct(private BusquedaProductosService $busqueda, private CandidatosQuery $candidatos) {}

    /**
     * Cuántas reservas (últimos N meses) que tienen este producto o esta ciudad
     * incluyen cada producto de los tipos complementarios.
     *
     * @return array<string, array<int,int>> tipo => [producto_id => reservas]
     */
    public function coocurrencia(int $productoId, int $ciudadId, array $tipos, int $sistemaProductos): array
    {
        if ($tipos === [] || ($productoId <= 0 && $ciudadId <= 0)) {
            return [];
        }
        $clave = 'generador.cross.co.'.md5(json_encode([Licencia::base(), $productoId, $ciudadId, $tipos, $sistemaProductos]));

        return Cache::store(config('reservas_busqueda.cache_store'))->remember($clave, (int) config('reservas_busqueda.cross_selling.cache_ttl', 21600), function () use ($productoId, $ciudadId, $tipos, $sistemaProductos) {
            $desde = now()->subMonths((int) config('reservas_busqueda.cross_selling.ventana_meses', 24))->format('Y-m-d 00:00:00');
            $filas = DB::table('servicio as s1')
                ->join('servicio as s2', fn ($j) => $j->on('s2.fk_reserva_id', '=', 's1.fk_reserva_id')->whereColumn('s2.servicio_id', '<>', 's1.servicio_id'))
                ->join('producto as p', 'p.producto_id', '=', 's2.fk_producto_id')
                ->where(fn ($w) => $w->when($productoId > 0, fn ($q) => $q->orWhere('s1.fk_producto_id', $productoId))->when($ciudadId > 0, fn ($q) => $q->orWhere('s1.fk_ciudad_id', $ciudadId)))
                ->whereIn('s2.fk_tipoproducto_id', $tipos)->where('s2.fk_producto_id', '>', 0)->where('s2.status', '<>', 'CA')
                ->where('s1.regdate', '>=', $desde)
                ->where('p.habilitar', 1)->where('p.eliminar', 0)->where('p.fk_sistema_id', $sistemaProductos)
                ->groupBy('s2.fk_tipoproducto_id', 's2.fk_producto_id')
                ->orderByDesc('n')->limit(80)
                ->get(['s2.fk_tipoproducto_id', 's2.fk_producto_id', DB::raw('COUNT(DISTINCT s2.fk_reserva_id) as n')]);
            $out = [];
            foreach ($filas as $f) {
                $out[(string) $f->fk_tipoproducto_id][(int) $f->fk_producto_id] = (int) $f->n;
            }

            return $out;
        });
    }

    /**
     * Sugerencias para un servicio recién agregado.
     *
     * @param  array  $s  ['tipo','producto_id','ciudad_id','from','to','habitaciones'=>[{ad,mn}], 'ad','mn']
     * @return array{grupos: list<array{tipo:string,nombre:string,items:list<array>}>}
     */
    public function sugerir(array $s, array $ctx): array
    {
        $tipoBase = (string) $s['tipo'];
        $complementarios = array_values(array_filter((array) config("reservas_busqueda.cross_selling.complementarios.{$tipoBase}", []), fn ($t) => $this->busqueda->buscador($t) !== null));
        $ciudadId = (int) ($s['ciudad_id'] ?? 0);
        if ($complementarios === [] || $ciudadId <= 0) {
            return ['grupos' => []];
        }
        $clave = 'generador.cross.'.md5(json_encode([Licencia::base(), $s, $ctx['sistema_productos'], $ctx['tarifario_id'], $ctx['residente'], $ctx['interno'], $ctx['hoy']]));

        return Cache::store(config('reservas_busqueda.cache_store'))->remember($clave, 300, function () use ($s, $ctx, $tipoBase, $complementarios, $ciudadId) {
            $max = (int) config('reservas_busqueda.cross_selling.max_por_tipo', 4);
            $co = $this->coocurrencia((int) ($s['producto_id'] ?? 0), $ciudadId, $complementarios, (int) $ctx['sistema_productos']);
            $nombres = DB::table('submodulo')->whereIn('tipoproducto_id', $complementarios)->pluck('tipoproducto_nombre', 'tipoproducto_id');
            $presupuesto = new Presupuesto((int) config('reservas_busqueda.presupuesto_ms', 8000));

            // Composición total del servicio agregado.
            $habitaciones = (array) ($s['habitaciones'] ?? []);
            $ad = 0;
            $mn = [];
            foreach ($habitaciones as $h) {
                $ad += (int) ($h['ad'] ?? 0);
                foreach ((array) ($h['mn'] ?? []) as $e) {
                    $mn[] = (int) $e;
                }
            }
            if ($ad === 0) {
                $ad = (int) ($s['ad'] ?? 2);
                $mn = array_map('intval', (array) ($s['mn'] ?? []));
            }
            $from = (string) $s['from'];
            $to = ! empty($s['to']) && $s['to'] > $from ? (string) $s['to'] : null;

            $grupos = [];
            foreach ($complementarios as $tipo) {
                if ($tipo === $tipoBase && (int) ($s['producto_id'] ?? 0) > 0 && ! in_array($tipo, ['EXC'], true)) {
                    continue;
                }
                if ($presupuesto->agotado()) {
                    break;
                }
                $porCo = $co[$tipo] ?? [];
                arsort($porCo);
                $base = (int) ($s['producto_id'] ?? 0);
                $ids = array_values(array_filter(array_slice(array_keys($porCo), 0, $max * 2), fn ($id) => $id !== $base));
                if (count($ids) < $max) {
                    // Sin historial suficiente: se completa con productos del destino.
                    $c = $this->candidatos->buscar($tipo, ['ciudades' => [$ciudadId], 'from' => $from, 'limite' => $max * 3], $ctx);
                    foreach ($c['productos'] as $p) {
                        if ((int) $p->producto_id !== $base && ! in_array((int) $p->producto_id, $ids, true)) {
                            $ids[] = (int) $p->producto_id;
                        }
                    }
                }
                if ($ids === []) {
                    continue;
                }
                $buscador = $this->busqueda->buscador($tipo);
                $r = $buscador->buscar($tipo, [
                    'from' => $from, 'to' => $tipo === 'ASV' ? ($to ?: $from) : $to, 'producto_ids' => array_slice($ids, 0, $max * 2),
                    'ad' => $ad, 'mn' => $mn, 'mayores70' => 0, 'habitaciones' => [['ad' => max(1, $ad), 'mn' => $mn]],
                ], $ctx, $presupuesto);
                $items = $r['resultados'];
                foreach ($items as &$it) {
                    $it['coocurrencia'] = (int) ($porCo[$it['producto_id']] ?? 0);
                }
                unset($it);
                usort($items, fn ($a, $b) => [-$a['coocurrencia'], $a['disponibilidad'] === 'SO' ? 1 : 0, (float) $a['mejor_total']] <=> [-$b['coocurrencia'], $b['disponibilidad'] === 'SO' ? 1 : 0, (float) $b['mejor_total']]);
                $items = array_slice($items, 0, $max);
                if ($items === []) {
                    continue;
                }
                $grupos[] = ['tipo' => $tipo, 'nombre' => (string) ($nombres[$tipo] ?? $tipo), 'items' => $items];
            }

            return ['grupos' => $grupos];
        });
    }
}
