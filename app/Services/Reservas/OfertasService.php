<?php

namespace App\Services\Reservas;

use App\Services\CotizacionService;
use App\Services\Reservas\Busqueda\Presupuesto;
use App\Support\Licencia;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * "Valores superadores" para quien atiende a un cliente: sobre el producto que
 * está mirando, qué pasaría moviendo las fechas unos días, qué promociones hay
 * vigentes en ese destino y qué pagó ese cliente por lo mismo (o parecido) en
 * el último año. Las alternativas más baratas del mismo tipo y categoría las
 * resuelve el front sobre la grilla ya cargada.
 */
class OfertasService
{
    public function __construct(private BusquedaProductosService $busqueda, private CotizacionService $cotizaciones) {}

    /**
     * Recotiza el producto elegido corriendo la estadía ±N días (misma duración,
     * misma categoría/régimen por habitación cuando existe). Salta fechas
     * anteriores a la mínima del usuario.
     *
     * @param  array  $p  parámetros de la búsqueda original + producto_id + elegidas[{categoria,regimen}]
     * @return list<array{delta:int, from:string, to:?string, total:float, moneda:string, disponibilidad:string, diferencia:float}>
     */
    public function fechasCercanas(string $tipo, array $p, array $ctx, float $totalElegido = 0.0): array
    {
        $buscador = $this->busqueda->buscador($tipo);
        if ($buscador === null) {
            return [];
        }
        $dias = (int) config('reservas_busqueda.ofertas.dias', 3);
        $from = Carbon::createFromFormat('Y-m-d', (string) $p['from']);
        $to = ! empty($p['to']) ? Carbon::createFromFormat('Y-m-d', (string) $p['to']) : null;
        $elegidas = (array) ($p['elegidas'] ?? []);
        $presupuesto = new Presupuesto((int) config('reservas_busqueda.presupuesto_ms', 8000));
        $out = [];

        for ($d = -$dias; $d <= $dias; $d++) {
            if ($d === 0 || $presupuesto->agotado()) {
                continue;
            }
            $nf = $from->copy()->addDays($d);
            if ($nf->format('Y-m-d') < (string) $ctx['fecha_minima']) {
                continue;
            }
            $q = $p;
            $q['from'] = $nf->format('Y-m-d');
            $q['to'] = $to ? $to->copy()->addDays($d)->format('Y-m-d') : null;
            $q['producto_ids'] = [(int) $p['producto_id']];
            unset($q['ciudad'], $q['nombre'], $q['stars'], $q['elegidas'], $q['total_elegido']);
            $r = $buscador->buscar($tipo, $q, $ctx, $presupuesto);
            $fila = $r['resultados'][0] ?? null;
            if ($fila === null) {
                $out[] = ['delta' => $d, 'from' => $q['from'], 'to' => $q['to'], 'total' => null, 'moneda' => '', 'disponibilidad' => '', 'diferencia' => null];

                continue;
            }
            $total = 0.0;
            $peor = 'CI';
            foreach ($fila['habitaciones'] as $i => $h) {
                $el = $elegidas[$i] ?? null;
                $op = null;
                if ($el) {
                    foreach ($h['opciones'] as $o) {
                        if ((int) $o['categoria'] === (int) ($el['categoria'] ?? -1) && (int) $o['regimen'] === (int) ($el['regimen'] ?? -1)) {
                            $op = $o;
                            break;
                        }
                    }
                }
                $op ??= $h['opciones'][0] ?? null;
                if ($op === null) {
                    continue;
                }
                $total += (float) $op['total'];
                $peor = $this->peor($peor, (string) $op['disponibilidad']);
            }
            $out[] = ['delta' => $d, 'from' => $q['from'], 'to' => $q['to'], 'total' => round($total, 2), 'moneda' => (string) $fila['moneda'], 'disponibilidad' => $peor, 'diferencia' => $totalElegido > 0 ? round($total - $totalElegido, 2) : null];
        }

        return $out;
    }

    /**
     * Vigencias promocionales (promocional, nota o N×M noches) del tipo en el
     * destino para las fechas, más los destacados del home de reserva.
     *
     * @return list<array{producto_id:int, nombre:string, nota:string, promo:string, vigencia_ini:string, vigencia_fin:string, vencimiento:?string, origen:string}>
     */
    public function promociones(string $tipo, int $ciudadId, string $from, ?string $to, array $ctx): array
    {
        $to = $to ?: $from;
        $clave = 'generador.promos.'.md5(json_encode([Licencia::base(), $tipo, $ciudadId, $from, $to, $ctx['sistema_productos'], $ctx['interno']]));

        return Cache::store(config('reservas_busqueda.cache_store'))->remember($clave, (int) config('reservas_busqueda.ofertas.cache_ttl', 600), function () use ($tipo, $ciudadId, $from, $to, $ctx) {
            $base = fn () => DB::table('producto as p')->where('p.fk_tipoproducto_id', $tipo)->where('p.habilitar', 1)->where('p.eliminar', 0)->where('p.fk_sistema_id', (int) $ctx['sistema_productos'])
                ->when(empty($ctx['interno']), fn ($q) => $q->where('p.aparece_tarifario', 1))
                ->when($ciudadId > 0, fn ($q) => $q->whereExists(fn ($s) => $s->selectRaw('1')->from('rel_productociudad as rpc')->whereColumn('rpc.fk_producto_id', 'p.producto_id')->where('rpc.fk_ciudad_id', $ciudadId)));

            $vigencias = $base()->join('vigencia as v', 'v.fk_producto_id', '=', 'p.producto_id')
                ->where(fn ($w) => $w->where('v.promocional', 1)->orWhere('v.nota_promocion', '<>', '')->orWhere('v.promo_noches', '>', 0))
                ->where('v.vigencia_fin', '>=', $from)->where('v.vigencia_ini', '<=', $to)
                ->orderBy('p.producto_nombre')->limit(30)
                ->get(['p.producto_id', 'p.producto_nombre', 'v.vigencia_id', 'v.nota_promocion', 'v.promo_noches', 'v.promo_pornoches', 'v.vigencia_ini', 'v.vigencia_fin', 'v.vencimiento_promocion'])
                ->map(fn ($v) => [
                    'producto_id' => (int) $v->producto_id, 'nombre' => (string) $v->producto_nombre, 'nota' => trim((string) $v->nota_promocion),
                    'promo' => (int) $v->promo_noches > 0 && (int) $v->promo_pornoches > 0 ? "{$v->promo_noches} X {$v->promo_pornoches}" : '',
                    'vigencia_ini' => substr((string) $v->vigencia_ini, 0, 10), 'vigencia_fin' => substr((string) $v->vigencia_fin, 0, 10),
                    'vencimiento' => in_array((string) $v->vencimiento_promocion, ['', '0000-00-00'], true) ? null : substr((string) $v->vencimiento_promocion, 0, 10),
                    'origen' => 'vigencia',
                ])->all();

            $destacados = [];
            if (DB::getSchemaBuilder()->hasTable('destacado')) {
                $destacados = $base()->join('destacado as d', 'd.fk_producto_id', '=', 'p.producto_id')->orderBy('d.destacado_id')->limit(10)
                    ->get(['p.producto_id', 'p.producto_nombre', 'd.destacado_nombre'])
                    ->map(fn ($d) => ['producto_id' => (int) $d->producto_id, 'nombre' => (string) $d->producto_nombre, 'nota' => trim((string) $d->destacado_nombre), 'promo' => '', 'vigencia_ini' => '', 'vigencia_fin' => '', 'vencimiento' => null, 'origen' => 'destacado'])->all();
            }

            return array_values(array_merge($vigencias, $destacados));
        });
    }

    /**
     * Qué pagó el cliente por este producto y por este tipo en esta ciudad en los
     * últimos 12 meses, para comparar con lo que está viendo.
     */
    public function historial(int $clienteId, int $productoId, string $tipo, int $ciudadId, float $totalElegido = 0.0, string $monedaElegida = ''): array
    {
        if ($clienteId <= 0) {
            return ['mismo_producto' => [], 'mismo_tipo_ciudad' => null];
        }
        $desde = now()->subMonths((int) config('reservas_busqueda.ofertas.historial_meses', 12))->toDateString();
        $base = fn () => DB::table('servicio as s')->join('reserva as r', 'r.reserva_id', '=', 's.fk_reserva_id')
            ->where('r.fk_cliente_id', $clienteId)->where('r.escotizacion', 0)->where('r.fecha_alta', '>=', $desde)->where('s.status', '<>', 'CA')->where('s.total', '>', 0);

        $mismo = $productoId > 0 ? $base()->where('s.fk_producto_id', $productoId)->orderByDesc('r.fecha_alta')->limit(5)
            ->get(['r.tipocodigo', 'r.codigo', 'r.fecha_alta', 's.vigencia_ini', 's.vigencia_fin', 's.adultos', 's.menores', 's.total', 's.fk_moneda_id'])
            ->map(fn ($s) => $this->filaHistorial($s))->all() : [];

        $agregado = null;
        if ($ciudadId > 0) {
            $filas = $base()->where('s.fk_tipoproducto_id', $tipo)->where('s.fk_ciudad_id', $ciudadId)->orderByDesc('r.fecha_alta')->limit(200)
                ->get(['r.tipocodigo', 'r.codigo', 'r.fecha_alta', 's.vigencia_ini', 's.vigencia_fin', 's.adultos', 's.menores', 's.total', 's.fk_moneda_id']);
            if ($filas->isNotEmpty()) {
                $basica = $this->cotizaciones->monedaBasica();
                $moneda = $monedaElegida ?: (string) $filas->first()->fk_moneda_id ?: $basica;
                $totales = [];
                $porPaxNoche = [];
                foreach ($filas as $s) {
                    $f = $this->filaHistorial($s);
                    $conv = $this->convertir((float) $s->total, (string) $s->fk_moneda_id, $moneda, $basica);
                    $totales[] = $conv;
                    if ($f['pax_noche'] > 0) {
                        $porPaxNoche[] = $conv / $f['pax_noche'];
                    }
                }
                $agregado = [
                    'n' => count($totales), 'moneda' => $moneda,
                    'promedio' => round(array_sum($totales) / count($totales), 2), 'min' => round(min($totales), 2), 'max' => round(max($totales), 2),
                    'promedio_pax_noche' => $porPaxNoche === [] ? null : round(array_sum($porPaxNoche) / count($porPaxNoche), 2),
                    'diferencia' => $totalElegido > 0 ? round($totalElegido - array_sum($totales) / count($totales), 2) : null,
                ];
            }
        }

        return ['mismo_producto' => $mismo, 'mismo_tipo_ciudad' => $agregado];
    }

    private function filaHistorial(object $s): array
    {
        $ini = substr((string) $s->vigencia_ini, 0, 10);
        $fin = substr((string) $s->vigencia_fin, 0, 10);
        $noches = 0;
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $ini) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $fin) && $fin > $ini) {
            $noches = (int) Carbon::createFromFormat('Y-m-d', $ini)->diffInDays(Carbon::createFromFormat('Y-m-d', $fin));
        }
        $pax = (int) $s->adultos + (int) $s->menores;

        return [
            'codigo' => "{$s->tipocodigo}-{$s->codigo}", 'fecha_alta' => substr((string) $s->fecha_alta, 0, 10), 'vigencia_ini' => $ini, 'vigencia_fin' => $fin,
            'noches' => $noches, 'pax' => $pax, 'total' => (float) $s->total, 'moneda' => (string) $s->fk_moneda_id,
            'pax_noche' => $pax * max(1, $noches),
            'por_pax_noche' => $pax > 0 ? round((float) $s->total / ($pax * max(1, $noches)), 2) : null,
        ];
    }

    private function convertir(float $monto, string $de, string $a, string $basica): float
    {
        if ($de === $a || $de === '' || $a === '') {
            return $monto;
        }
        $cd = $de === $basica ? 1.0 : ($this->cotizaciones->aLaVenta($de) ?: 1.0);
        $ca = $a === $basica ? 1.0 : ($this->cotizaciones->aLaVenta($a) ?: 1.0);

        return $monto * $cd / $ca;
    }

    private function peor(string $a, string $b): string
    {
        $orden = ['CI' => 0, 'RQ' => 1, 'SO' => 2];

        return ($orden[$b] ?? 1) > ($orden[$a] ?? 1) ? $b : $a;
    }
}
