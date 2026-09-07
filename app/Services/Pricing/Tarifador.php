<?php

namespace App\Services\Pricing;

use App\Services\CotizacionService;
use App\Services\Productos\ProductoService;
use App\Support\Licencia;
use App\Support\Productos\DiasSemana;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Cotización de un producto para una estadía y una composición de pax.
 *
 * Port PARCIAL de Tarifa_model::tarifar() (tarifa_model.php:1101-3340), con
 * la misma selección de vigencias y tarifas por noche, la misma derivación de
 * bases de menores, cupos/soldout y la fórmula de venta de la cabecera del
 * archivo:
 *
 *   costo (+ iva costo, − promo) × cotización → costo en moneda de venta
 *   ÷ divisor_markup (redondeo)               → venta (carga manual: el valor cargado)
 *   venta × %comisión                          → comisión
 *   IVA venta sobre la renta (modo 2/3) o sobre la venta → iva
 *   + percepciones extra1/extra2 del sistema + impuestos → total; total − comisión = a pagar
 *
 * Lo que NO está (a propósito, ver doc §4.8.4): las ramas por licencia
 * (expert, tower, grh, greatchile, hb, med, mundotour_sdg), PKD/CAE/CTK,
 * vigenciaalojamiento como "categoría" de paquetes, gasto de proveedor (B9,
 * siempre 0 en el CI) y el texto HTML de precio por pax. Un query por rango
 * en vez de ~10 por habitación y por día.
 *
 * Diferencia documentada con el CI: cuando dos vigencias de igual prioridad
 * cubren el mismo día, el CI se queda con la ÚLTIMA fila del ORDER BY
 * (prioridad DESC, costo ASC), o sea la de costo más alto. Acá se replica.
 *
 * Estado: validado con tests propios (TarifadorTest), todavía NO contra
 * fixtures generados desde el CI para los mismos inputs.
 */
class Tarifador
{
    public function __construct(
        private ProductoService $productos,
        private ScopeResolver $scope,
        private MarkupCalculadora $markup,
        private CotizacionService $cotizaciones,
    ) {}

    /**
     * @param  array{producto_id:int, fecha_ini:string, fecha_fin?:string, adultos?:int, menores?:list<int>, residente?:string, tarifario_id?:int, cliente_id?:int, categoria_id?:int, vigencia_id?:int, hoy?:string}  $q
     */
    public function cotizar(array $q): array
    {
        $productoId = (int) $q['producto_id'];
        // El generador cotiza el mismo producto para varias habitaciones: acepta el producto ya cargado.
        $producto = isset($q['producto']) && (int) ($q['producto']['producto_id'] ?? 0) === $productoId ? $q['producto'] : $this->productos->cargar($productoId);
        if ($producto === null) {
            return ['ok' => false, 'motivos' => ['El producto no existe.'], 'resultado' => []];
        }

        $tipo = (string) $producto['fk_tipoproducto_id'];
        $cfg = (array) config("productos.tipos.{$tipo}", []);
        $esAlojamiento = (bool) ($cfg['alojamiento'] ?? false);
        $esHotel = in_array($tipo, ['HOT', 'AEL', 'MSC'], true);
        $sistema = (int) $producto['fk_sistema_id'];
        $modotarifa = (string) ($producto['modotarifa'] ?: 'P');
        $hoy = (string) ($q['hoy'] ?? now()->format('Y-m-d'));

        $ad = max(0, (int) ($q['adultos'] ?? 1));
        $menores = array_values(array_map('intval', (array) ($q['menores'] ?? [])));
        sort($menores);
        $mn = count($menores);
        $residente = (string) ($q['residente'] ?? '') === 'R' ? 'R' : 'N';

        // Fechas: sin "hasta" (o igual al "desde") es una noche; PAQ/TRL/TRE siempre una (tarifar() :1122, :1216).
        $ini = Carbon::createFromFormat('Y-m-d', substr((string) $q['fecha_ini'], 0, 10))->startOfDay();
        $fin = ! empty($q['fecha_fin']) ? Carbon::createFromFormat('Y-m-d', substr((string) $q['fecha_fin'], 0, 10))->startOfDay() : $ini->copy();
        if ($fin->lte($ini) || in_array($tipo, ['PAQ', 'TRL', 'TRE'], true)) {
            $fin = $ini->copy()->addDay();
        }
        $noches = [];
        for ($d = $ini->copy(); $d->lt($fin); $d->addDay()) {
            $noches[] = $d->format('Y-m-d');
        }
        $nNoches = count($noches);

        // Tarifario, markup y comisión.
        $tarifarioId = (int) ($q['tarifario_id'] ?? 0);
        if ($tarifarioId === 0 && ! empty($q['cliente_id'])) {
            $tarifarioId = (int) (DB::table('rel_clientesistema')->where('fk_cliente_id', (int) $q['cliente_id'])->where('fk_sistema_id', $sistema)->value('fk_tarifario_id') ?? 0);
        }
        $tarifario = $tarifarioId ? DB::table('tarifario')->where('tarifario_id', $tarifarioId)->first() : null;
        $com = $tarifarioId ? $this->scope->comision($tarifarioId, $productoId, $tipo, (int) $producto['fk_ciudad_id'], (int) $producto['pais']) : null;
        $mup = (float) ($com['divisor_markup'] ?? 0);
        $comisionPct = (float) ($com['porcentaje_comision'] ?? 0);
        if ($mup == 0 && ! Licencia::es('witwan_tower', 'witwan_tower_dev')) {
            $mup = 1.0;
        }

        // IVA (cascada única) y regla AR/HOT/no residente.
        $iva = $this->scope->iva($productoId, $sistema, $tipo, (int) $producto['fk_ciudad_id'], (int) $producto['pais']) ?? ['iva_costo' => 0, 'iva_valor' => 0, 'fk_modoivaventa_id' => 0];
        // tarifar() :2716: la regla AR/HOT mira el `residente` de la VIGENCIA ('O' = no residentes), no del titular.
        $ivaCostoBase = (float) $iva['iva_costo'];
        $ivaVentaPct = (float) $iva['iva_valor'];
        $modoIvaVenta = (int) $iva['fk_modoivaventa_id'];

        // Percepciones del sistema (extra1/extra2), sólo mayorista/minorista o residente en receptivo (:3095).
        $sis = DB::table('sistema')->where('sistema_id', $sistema)->first(['extra1', 'extra2']);
        $aplicaExtras = in_array($sistema, [2, 3], true) || ($residente === 'R' && $sistema === 1);
        $extra1 = $aplicaExtras ? (float) ($sis->extra1 ?? 0) / 100 : 0.0;
        $extra2 = $aplicaExtras ? (float) ($sis->extra2 ?? 0) / 100 : 0.0;

        // Filas candidatas de tarifa × vigencia para todo el rango (una sola query).
        // Tramos: tipos de pax de la tarifa a considerar (ADU por defecto; ASV usa '<70' / '>70').
        $tiposPax = array_values(array_unique(array_merge(array_map('strval', (array) ($q['tipopax'] ?? ['ADU'])), [''])));
        $filas = $this->filas($productoId, $noches, $hoy, $ad, $residente, $tarifarioId, $esAlojamiento, $esHotel, (int) ($q['categoria_id'] ?? 0), (int) ($q['vigencia_id'] ?? 0), $nNoches, $tiposPax);

        $habitaciones = $esHotel ? $this->habitacionesValidas($productoId, $ad, $mn) : null;
        $edades = $producto['edades'];

        // diapordia[categoria][regimen][noche] = ['costo','impuestos','vigencia','venta'?,'menores'=>[...],'promo'=>[...]]
        $diapordia = [];
        $ventaManual = [];
        $tiposTarifa = [];
        $motivos = [];

        foreach ($noches as $dt) {
            $diaSemana = (int) Carbon::createFromFormat('Y-m-d', $dt)->format('N');
            foreach ($filas as $f) {
                if ($dt < $f->vigencia_ini || $dt > $f->vigencia_fin) {
                    continue;
                }
                if ($esHotel && substr($f->weekdays_bits, $diaSemana - 1, 1) !== '1' && $f->weekdays_bits !== '0000000') {
                    continue;
                }
                $cat = (int) $f->fk_tarifacategoria_id;
                if ($habitaciones !== null && ! isset($habitaciones[$cat])) {
                    $motivos["hab{$cat}"] = "La categoría {$cat} no admite {$ad} adulto(s) y {$mn} menor(es) o está deshabilitada.";

                    continue;
                }
                $reg = (int) $f->fk_regimen_id;
                $tiposTarifa[$cat] = $f->min_pax != 0 && $f->max_pax != 0 ? "De {$f->min_pax} a {$f->max_pax} pasajeros" : null;

                if ((int) $f->fk_tarifario_id !== 0) {
                    // Venta manual del tarifario: sólo si la vigencia es de carga manual.
                    if ((int) $f->cargamanual === 1) {
                        $ventaManual[$cat][$reg][$dt] = ['venta' => (float) $f->costo, 'menores' => $this->menores($f, $menores, $edades, $esAlojamiento, $tarifarioId)];
                    }

                    continue;
                }

                $costoMenores = $this->menores($f, $menores, $edades, $esAlojamiento, 0);
                if ($costoMenores === null) {
                    $motivos["mn{$cat}"] = 'No hay tarifa de menor para la edad indicada en la categoría '.$cat.'.';
                    unset($diapordia[$cat][$reg][$dt]);

                    continue;
                }

                // Gana la mayor prioridad; a igual prioridad, la última fila del ORDER BY (costo ASC), como el CI (:2617-2640).
                if (isset($diapordia[$cat][$reg][$dt]) && $diapordia[$cat][$reg][$dt]['prioridad'] > (int) $f->vigencia_prioridad) {
                    continue;
                }
                $diapordia[$cat][$reg][$dt] = [
                    'costo' => (float) $f->costo,
                    'impuestos' => (float) $f->impuestos,
                    'vigencia' => (int) $f->vigencia_id,
                    'prioridad' => (int) $f->vigencia_prioridad,
                    'cargamanual' => (int) $f->cargamanual,
                    'residente' => (string) $f->residente,
                    'moneda_costo' => (string) $f->moneda_costo,
                    'redondear' => (string) $f->redondear,
                    'menores' => $costoMenores,
                    'promo' => (int) $f->promo_noches > 0 && (int) $f->promo_pornoches > 0 ? [(int) $f->promo_noches, (int) $f->promo_pornoches] : null,
                    'nota_promocion' => (string) $f->nota_promocion,
                    'vencimiento_checkin' => (int) $f->vencimiento_checkin,
                    'vencimiento_reserva' => (int) $f->vencimiento_reserva,
                ];
            }
        }

        $cupos = $esAlojamiento || $esHotel ? $this->cupos($productoId, array_keys($diapordia), $noches, $ad, (int) ($q['cliente_id'] ?? 0), $hoy) : [];
        $disponibilidadCI = (string) ($producto['disponibilidad'] ?? '') === 'CI';

        $resultado = [];
        $mejor = null;
        $monedaCosto = 'USD';

        foreach ($diapordia as $cat => $porRegimen) {
            foreach ($porRegimen as $reg => $dias) {
                if (count($dias) < $nNoches) {
                    $motivos["falta{$cat}_{$reg}"] = "La categoría {$cat} no tiene tarifa para todas las noches.";

                    continue;
                }
                ksort($dias);
                $primera = reset($dias);
                $monedaCosto = $primera['moneda_costo'] ?: 'USD';
                $redondeo = $primera['redondear'];

                $total = 0.0;
                $totalMenor = 0.0;
                $impuestos = 0.0;
                $descuento = 0.0;
                $promoAplicada = null;
                foreach ($dias as $d) {
                    $total += $d['costo'];
                    $totalMenor += array_sum($d['menores']);
                    $impuestos = $d['impuestos'];
                    if ($d['promo'] !== null && $promoAplicada === null && $nNoches >= $d['promo'][0]) {
                        // Promo "N x M": se paga M de cada N noches; el CI descuenta una sola vez (:2680).
                        $descuento = $d['costo'] * ($modotarifa === 'P' ? $ad : 1) * ($d['promo'][0] - $d['promo'][1]);
                        $promoAplicada = "{$d['promo'][0]} X {$d['promo'][1]}";
                    }
                }

                [$final, $finalAdt] = match ($modotarifa) {
                    'P' => [$total * $ad + $totalMenor, $total * $ad],
                    default => [$total, $total],
                };
                $impuestos = $modotarifa === 'P' ? round($impuestos) * $ad : $impuestos;

                $ivaCostoPct = $this->markup->ivaCostoAplicable($ivaCostoBase, $tipo, Licencia::pais(), $primera['residente'], $sistema);
                $ivaCosto = round($final * $ivaCostoPct / 100, $monedaCosto === 'CLP' ? 0 : 2);
                $costo = $final + $ivaCosto - $descuento;

                // Cotización costo → venta (tarifar() :2735-2750).
                $monedaVenta = (string) ($tarifario->fk_moneda_id ?? $monedaCosto) ?: $monedaCosto;
                $cotCosto = $this->cotizaciones->alCosto($monedaCosto, $ini->format('Y-m-d'));
                $cotVenta = ($monedaVenta === 'USD' && (float) ($tarifario->cotizacion ?? 0) != 0) ? (float) $tarifario->cotizacion : $this->cotizaciones->alCosto($monedaVenta, $ini->format('Y-m-d'));
                $factor = $this->markup->factorCotizacion($monedaCosto, $cotCosto, $monedaVenta, $cotVenta);
                $costoEnMoneda = $costo * $factor;
                $impuestoEnMoneda = $impuestos * $factor;

                // Venta: carga manual completa para el tarifario, o markup.
                $manual = $ventaManual[$cat][$reg] ?? [];
                $esManual = $tarifarioId !== 0 && (int) $primera['cargamanual'] === 1 && count($manual) >= $nNoches;
                if ($esManual) {
                    $vAd = 0.0;
                    $vMn = 0.0;
                    foreach ($manual as $m) {
                        $vAd += $m['venta'];
                        $vMn += array_sum($m['menores'] ?? []);
                    }
                    $venta = $modotarifa === 'P' ? round($vAd) * $ad + round($vMn) : round($vAd + $vMn);
                    $ventaAdt = $modotarifa === 'P' ? round($vAd) * $ad : $venta;
                } else {
                    $venta = $mup == 0 ? 0.0 : round($costoEnMoneda / $mup);
                    $ventaAdt = $mup == 0 ? 0.0 : round(($finalAdt + round($finalAdt * $ivaCostoPct / 100, 2)) * $factor / $mup);
                    if ($modotarifa === 'P' && $ad > 0) {
                        while (fmod($venta, $ad) != 0) {
                            $venta++;
                        }
                    }
                }

                $comision = round($venta * $comisionPct / 100, 2);
                $renta = $venta - $costoEnMoneda - $comision;
                if (in_array($modoIvaVenta, [2, 3], true) && $ivaVentaPct != 0) {
                    $ivaVenta = round($renta * $ivaVentaPct / 100, 2);
                } elseif ($ivaCostoPct == 0) {
                    $ivaVenta = round($venta * $ivaVentaPct / 100, 2);
                } else {
                    $ivaVenta = 0.0;
                }
                if ($modoIvaVenta === 1) {
                    $ivaVenta = 0.0;
                }

                $totalSinCom = $venta + $ivaVenta + round($impuestoEnMoneda) - $comision;
                $percepcion1 = round($totalSinCom * $extra1, 2);
                $percepcion2 = round($totalSinCom * $extra2, 2);

                $totalFinal = round($venta + round($impuestoEnMoneda) + $ivaVenta + $percepcion1 + $percepcion2, 2);

                $cupoInfo = $cupos[$cat] ?? ['cupo' => 0, 'soldout' => false];
                $soldout = (bool) $cupoInfo['soldout'];
                $cupo = $soldout ? 0 : (int) $cupoInfo['cupo'];
                if ($disponibilidadCI) {
                    $cupo = 1;
                }

                $primeraVig = $primera['vigencia'];
                $vencePago = null;
                if ($primera['vencimiento_checkin'] > 0) {
                    $vencePago = $ini->copy()->subDays($primera['vencimiento_checkin'])->format('Y-m-d');
                } elseif ($primera['vencimiento_reserva'] > 0) {
                    $vencePago = Carbon::createFromFormat('Y-m-d', $hoy)->addDays($primera['vencimiento_reserva'])->format('Y-m-d');
                }

                $item = [
                    'categoria' => $cat,
                    'regimen' => $reg,
                    'nombre' => $tiposTarifa[$cat] ?? ($habitaciones[$cat]['nombre'] ?? (string) $cat),
                    'noches' => $nNoches,
                    'vigencia' => $primeraVig,
                    'cargamanual' => $esManual ? 1 : 0,
                    'moneda_costo' => $monedaCosto,
                    'moneda' => $monedaVenta,
                    'cotizacion' => $factor,
                    'costoadt' => round($total, 2),
                    'costomen' => round($totalMenor, 2),
                    'costosiniva' => round($final, 2),
                    'ivacosto' => $ivaCosto,
                    'descuento' => round($descuento, 2),
                    'textodescuento' => $promoAplicada ?? '',
                    'costo' => round($costo, 2),
                    'costoenmoneda' => round($costoEnMoneda, 2),
                    'mup' => $mup,
                    'venta' => $venta,
                    'ventaadt' => $ventaAdt,
                    'promedio' => $nNoches > 0 ? round($venta / $nNoches / ($modotarifa === 'P' && $ad > 0 ? $ad : 1)) : 0,
                    'pcomision' => $comisionPct,
                    'comision' => $comision,
                    'iva' => $ivaVenta,
                    'impuestos' => round($impuestoEnMoneda),
                    'percepcion1' => $percepcion1,
                    'percepcion2' => $percepcion2,
                    'total' => $totalFinal,
                    'totalapagar' => round($totalFinal - $comision, 2),
                    'cupo' => $cupo,
                    'soldout' => $soldout ? 1 : 0,
                    'vencepago' => $vencePago,
                    'nota_promocion' => $primera['nota_promocion'],
                    'dias' => array_values(array_map(fn ($fecha, $d) => ['fecha' => $fecha, 'costo' => $d['costo'], 'menores' => $d['menores'], 'vigencia' => $d['vigencia']], array_keys($dias), $dias)),
                ];
                $resultado[$cat][$reg] = $item;

                if ($totalFinal > 0 && ($mejor === null || $totalFinal < $mejor['total'])) {
                    $mejor = $item;
                }
            }
        }

        return [
            'ok' => $mejor !== null,
            'params' => ['producto_id' => $productoId, 'fecha_ini' => $ini->format('Y-m-d'), 'fecha_fin' => $fin->format('Y-m-d'), 'noches' => $nNoches, 'adultos' => $ad, 'menores' => $menores, 'residente' => $residente, 'tarifario_id' => $tarifarioId, 'sistema' => $sistema],
            'producto' => ['producto_id' => $productoId, 'nombre' => $producto['producto_nombre'], 'tipo' => $tipo, 'modotarifa' => $modotarifa, 'disponibilidad' => $producto['disponibilidad'] ?? ''],
            'pricing' => ['mup' => $mup, 'comision' => $comisionPct, 'iva_costo' => $ivaCostoBase, 'iva_venta' => $ivaVentaPct, 'modoivaventa' => $modoIvaVenta, 'extra1' => $extra1, 'extra2' => $extra2],
            'resultado' => $resultado,
            'mejor' => $mejor,
            'motivos' => array_values($motivos),
        ];
    }

    // ------------------------------------------------------------------

    /**
     * Tarifa × vigencia candidatas para el rango. Réplica del SELECT de
     * tarifar() :2189-2226 con sus filtros (:2166-2180 y :2140-2160).
     */
    private function filas(int $productoId, array $noches, string $hoy, int $ad, string $residente, int $tarifarioId, bool $esAlojamiento, bool $esHotel, int $categoriaId, int $vigenciaId, int $nNoches, array $tiposPax = ['ADU', '']): array
    {
        $q = DB::table('tarifa')
            ->join('vigencia', 'vigencia.vigencia_id', '=', 'tarifa.fk_vigencia_id')
            ->where('vigencia.fk_producto_id', $productoId)
            ->where('vigencia.vigencia_ini', '<=', end($noches))
            ->where('vigencia.vigencia_fin', '>=', $noches[0])
            ->whereIn('tarifa.fk_tarifario_id', array_unique([0, $tarifarioId]))
            ->select('tarifa.*', 'vigencia.vigencia_id', 'vigencia.fk_regimen_id', 'vigencia.vigencia_prioridad', 'vigencia.vigencia_ini', 'vigencia.vigencia_fin',
                'vigencia.cargamanual', 'vigencia.residente', 'vigencia.promo_noches', 'vigencia.promo_pornoches', 'vigencia.nota_promocion', 'vigencia.vencimiento_checkin', 'vigencia.vencimiento_reserva',
                DiasSemana::columnaSelect('weekdays_bits'))
            ->orderByDesc('vigencia.vigencia_prioridad')
            ->orderBy('tarifa.costo')
            ->orderBy('tarifa.tarifa_id');

        if ($esAlojamiento) {
            $q->where('tarifa.fk_base_id', (string) $ad);
            if ($categoriaId !== 0 && $esHotel) {
                $q->where('tarifa.fk_tarifacategoria_id', $categoriaId);
            }
        } else {
            $q->where('tarifa.min_pax', '<=', $ad)->where('tarifa.max_pax', '>=', $ad)->whereIn('tarifa.fk_tipopax_id', $tiposPax);
        }

        // Residente: 'R' ve R y todos; el resto ve O, N y todos (:2172-2178).
        $q->whereIn('vigencia.residente', $residente === 'R' ? ['R', ''] : ['O', 'N', '']);

        if ($vigenciaId !== 0) {
            $q->where('vigencia.vigencia_id', $vigenciaId);
        } else {
            $q->where(fn ($w) => $w->where('vigencia.vigencia_ventaini', '<=', $hoy)->orWhere('vigencia.vigencia_ventaini', '0000-00-00'))
                ->where(fn ($w) => $w->where('vigencia.vigencia_ventafin', '>=', $hoy)->orWhere('vigencia.vigencia_ventafin', '0000-00-00'))
                ->where(fn ($w) => $w->where('vigencia.noches_minimas', '<=', $nNoches)->orWhere('vigencia.noches_minimas', 0));
        }

        return $q->get()->all();
    }

    /**
     * Costo (o venta manual) de cada menor según su edad, con la misma
     * derivación de bases que tarifar() :2503-2534. Null si algún menor no
     * tiene tarifa (el CI descarta la categoría para ese día).
     *
     * @return list<float>|null
     */
    private function menores(object $fila, array $edadesMenores, array $edades, bool $esAlojamiento, int $tarifarioId): ?array
    {
        if ($edadesMenores === []) {
            return [];
        }

        if (! $esAlojamiento) {
            // Tramos: fila CHD del mismo tramo, o el costo de adulto (:2545-2560).
            $chd = DB::table('tarifa')->where('fk_vigencia_id', $fila->vigencia_id)->where('fk_tarifario_id', $tarifarioId)
                ->where('min_pax', $fila->min_pax)->where('max_pax', $fila->max_pax)->where('fk_tipopax_id', 'CHD')->value('costo');
            $unitario = $chd !== null ? (float) $chd : (float) $fila->costo;

            return array_fill(0, count($edadesMenores), $unitario);
        }

        $cortes = [(int) ($edades['edad_infoa'] ?? 0), (int) ($edades['edad_menor1'] ?? 0), (int) ($edades['edad_menor2'] ?? 0), (int) ($edades['edad_junior'] ?? 0)];
        $contador = [];
        $out = [];
        foreach ($edadesMenores as $edad) {
            $base = match (true) {
                $edad >= 0 && $edad <= $cortes[0] => 'INF',
                $edad > $cortes[0] && $edad <= $cortes[1] => 'MN',
                $edad > $cortes[1] && $edad <= $cortes[2] => 'M2',
                $edad > $cortes[2] && $edad <= $cortes[3] => 'JNR',
                default => 'XXX',
            };
            if ($base === 'XXX') {
                return null;
            }
            $contador[$base] = ($contador[$base] ?? 0) + 1;
            $baseId = (str_starts_with($base, 'M') && $contador[$base] > 1) ? $base.$contador[$base] : $base;

            $costo = DB::table('tarifa')->where('fk_vigencia_id', $fila->vigencia_id)->where('fk_tarifacategoria_id', $fila->fk_tarifacategoria_id)
                ->where('fk_tarifario_id', $tarifarioId)->where('fk_base_id', $baseId)->value('costo');
            if ($costo === null) {
                return null;
            }
            $out[] = (float) $costo;
        }

        return $out;
    }

    /** Habitaciones que admiten la composición (tarifar() :2261-2275). Indexadas por categoría. */
    private function habitacionesValidas(int $productoId, int $ad, int $mn): array
    {
        $q = DB::table('alojamientohabitacion as h')
            ->leftJoin('tarifacategoria as tc', 'tc.tarifacategoria_id', '=', 'h.fk_tarifacategoria_id')
            ->where('h.fk_producto_id', $productoId)->where('h.habilitar', 1)
            ->where('h.max_adultos', '>=', $ad)
            ->where('h.capacidad', '>=', $ad + $mn);
        if ($mn > 0) {
            $q->where('h.max_child', '>=', $mn)->where('h.max_adultos_child', '>=', $ad)->where('h.min_adultos_child', '<=', $ad);
        }

        $out = [];
        foreach ($q->get(['h.*', 'tc.tarifacategoria_nombre']) as $h) {
            $out[(int) $h->fk_tarifacategoria_id] = [
                'nombre' => trim((string) $h->alojamientohabitacion_nombre) !== '' && $h->alojamientohabitacion_nombre !== '0' ? (string) $h->alojamientohabitacion_nombre : (string) ($h->tarifacategoria_nombre ?? ''),
                'capacidad' => (int) $h->capacidad,
            ];
        }

        return $out;
    }

    /**
     * Cupo mínimo del rango por categoría y flag de soldout (tarifar() :2350-2380 y :2766).
     * Cantidad 0 = freesale (+10); cada servicio RQ/CO/CL resta 1; release en días.
     *
     * @return array<int,array{cupo:int, soldout:bool}>
     */
    private function cupos(int $productoId, array $categorias, array $noches, int $ad, int $clienteId, string $hoy): array
    {
        $out = [];
        if ($categorias === []) {
            return $out;
        }
        $ini = $noches[0];
        $fin = end($noches);

        $cupos = DB::table('cupo')->where('fk_producto_id', $productoId)
            ->where('vigencia_ini', '<=', $fin)->where('vigencia_fin', '>=', $ini)
            ->whereIn('fk_cliente_id', [0, $clienteId])->get();
        $servicios = DB::table('servicio')->join('reserva', 'reserva.reserva_id', '=', 'servicio.fk_reserva_id')
            ->where('servicio.fk_producto_id', $productoId)->whereIn('servicio.fk_tarifacategoria_id', $categorias)
            ->where('servicio.vigencia_ini', '<=', $fin)->where('servicio.vigencia_fin', '>=', $ini)
            ->whereIn('servicio.status', ['RQ', 'CO', 'CL'])->get(['servicio.fk_tarifacategoria_id', 'servicio.vigencia_ini', 'servicio.vigencia_fin']);
        $soldouts = DB::table('soldout')->where('fk_producto_id', $productoId)->whereIn('fk_tarifacategoria_id', $categorias)
            ->where('vigencia_ini', '<=', $fin)->where('vigencia_fin', '>=', $ini)->get(['fk_tarifacategoria_id', 'vigencia_ini', 'vigencia_fin']);

        $hoyTs = Carbon::createFromFormat('Y-m-d', $hoy);
        foreach ($categorias as $cat) {
            $minimo = null;
            foreach ($noches as $dt) {
                $cupo = 0;
                foreach ($cupos as $c) {
                    if (! in_array((int) $c->fk_tarifacategoria_id, [0, $cat], true) || $dt < $c->vigencia_ini || $dt > $c->vigencia_fin) {
                        continue;
                    }
                    if (! in_array((string) $ad, array_map('trim', explode(',', (string) $c->bases)), true)) {
                        continue;
                    }
                    $release = (int) $c->release;
                    if ($release > 0 && $hoyTs->diffInDays(Carbon::createFromFormat('Y-m-d', $dt), false) <= $release) {
                        continue;
                    }
                    $cupo += (int) $c->cantidad === 0 ? 10 : (int) $c->cantidad;
                }
                foreach ($servicios as $s) {
                    if ((int) $s->fk_tarifacategoria_id === $cat && $dt >= substr($s->vigencia_ini, 0, 10) && $dt <= substr($s->vigencia_fin, 0, 10)) {
                        $cupo--;
                    }
                }
                $minimo = $minimo === null ? $cupo : min($minimo, $cupo);
            }
            $soldout = $soldouts->contains(fn ($s) => (int) $s->fk_tarifacategoria_id === $cat && collect($noches)->contains(fn ($dt) => $dt >= substr($s->vigencia_ini, 0, 10) && $dt <= substr($s->vigencia_fin, 0, 10)));

            // Sin cupo cargado el CI deja 0 ("a requerir", salvo disponibilidad CI); con cupo, el mínimo del rango.
            $out[$cat] = ['cupo' => max(0, (int) $minimo), 'soldout' => $soldout];
        }

        return $out;
    }
}
