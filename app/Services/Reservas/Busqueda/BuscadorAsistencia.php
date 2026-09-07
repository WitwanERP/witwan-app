<?php

namespace App\Services\Reservas\Busqueda;

use App\Services\Pricing\ScopeResolver;
use App\Services\Productos\ProductoService;
use App\Support\Licencia;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Asistencia al viajero (ASV): port de la rama propia de tarifar()
 * (tarifa_model.php:1333-1510), que no usa categorías ni tramos de pax:
 *
 *  - `tarifa.max_pax` es la CANTIDAD DE DÍAS de cobertura y `fk_tipopax_id`
 *    es '<70' o '>70' (menores o mayores de 70 años);
 *  - días = (hasta − desde) + 1; si hay tarifa exacta para esos días se usa;
 *    si la cobertura máxima cargada es menor se repite (y el resto con la de 1
 *    día); si es mayor se toma la menor cobertura y se multiplica por día;
 *  - costo = (costo + IVA costo) × pax < 70 + idem × pax > 70; venta = costo ÷ markup,
 *    redondeada; sin IVA de venta, impuestos ni comisión; disponibilidad CI;
 *  - se buscan TODAS las ciudades con productos ASV del sistema (reserva.php:3374).
 */
class BuscadorAsistencia implements BuscadorTipo
{
    public function __construct(
        private CandidatosQuery $candidatos,
        private ProductoService $productos,
        private ScopeResolver $scope,
        private NormalizadorFila $normalizador,
    ) {}

    public function reglas(string $tipo): array
    {
        return ['ad' => 'required|integer|min:1', 'to' => 'required|date_format:Y-m-d'];
    }

    public function buscar(string $tipo, array $p, array $ctx, Presupuesto $presupuesto): array
    {
        $c = $this->candidatos->buscar($tipo, [
            'nombre' => $p['nombre'] ?? '',
            'producto_ids' => $p['producto_ids'] ?? array_filter([(int) ($p['producto_id'] ?? 0)]),
            'from' => $p['from'],
        ], $ctx);

        $ad = max(1, (int) ($p['ad'] ?? 1));
        $mayores = min($ad, max(0, (int) ($p['mayores70'] ?? 0)));
        $menores70 = $ad - $mayores;
        $dias = (int) Carbon::createFromFormat('Y-m-d', $p['from'])->diffInDays(Carbon::createFromFormat('Y-m-d', $p['to'])) + 1;

        $resultados = [];
        $truncado = $c['truncado'];
        foreach ($c['productos'] as $cand) {
            if ($presupuesto->agotado()) {
                $truncado = true;
                break;
            }
            $producto = $this->productos->cargar((int) $cand->producto_id);
            if ($producto === null) {
                continue;
            }
            $cot = $this->cotizar($producto, $p['from'], $p['to'], $dias, $menores70, $mayores, $ctx);
            if (empty($cot['ok'])) {
                continue;
            }
            $resultados[] = $this->normalizador->fila($producto, $tipo, [['ad' => $ad, 'mn' => [], 'cot' => $cot]], [
                'estrellas' => null, 'proveedor_nombre' => $cand->proveedor_nombre, 'ciudad' => $cand->ciudad, 'pickup' => false,
                'disponibilidad_producto' => 'CI', 'vigencia_fin' => $p['to'],
            ]);
        }

        return ['resultados' => $resultados, 'truncado' => $truncado, 'candidatos' => count($c['productos'])];
    }

    /**
     * Cotización con la forma de Tarifador::cotizar() (ok, params, resultado, mejor, motivos)
     * para que NormalizadorFila y el carrito la traten igual que al resto.
     */
    public function cotizar(array $producto, string $from, string $to, int $dias, int $menores70, int $mayores70, array $ctx): array
    {
        $productoId = (int) $producto['producto_id'];
        $filas = DB::table('tarifa')->join('vigencia', 'vigencia.vigencia_id', '=', 'tarifa.fk_vigencia_id')
            ->where('vigencia.fk_producto_id', $productoId)->where('tarifa.fk_tarifario_id', 0)
            ->where(fn ($w) => $w->where('vigencia.vigencia_ini', '<=', $from)->orWhere('vigencia.vigencia_ini', '0000-00-00'))
            ->where(fn ($w) => $w->where('vigencia.vigencia_fin', '>=', $from)->orWhere('vigencia.vigencia_fin', '0000-00-00'))
            ->whereIn('tarifa.fk_tipopax_id', ['<70', '>70'])
            ->orderBy('vigencia.vigencia_id')->orderBy('tarifa.tarifa_id')
            ->get(['tarifa.max_pax', 'tarifa.fk_tipopax_id', 'tarifa.costo', 'tarifa.ivacosto', 'tarifa.moneda_costo', 'vigencia.vigencia_id', 'vigencia.nota_promocion', 'vigencia.vencimiento_reserva']);
        if ($filas->isEmpty()) {
            return ['ok' => false, 'motivos' => ['Sin tarifa de asistencia vigente para la fecha de inicio.'], 'resultado' => [], 'mejor' => null, 'params' => ['fecha_ini' => $from, 'fecha_fin' => $to]];
        }

        // tarifas[días][tipo] = última fila (como el foreach del CI, que pisa).
        $tarifas = [];
        $vigenciaId = 0;
        $nota = '';
        $vencimientoReserva = 0;
        foreach ($filas as $f) {
            $tarifas[(int) $f->max_pax][(string) $f->fk_tipopax_id] = ['costo' => (float) $f->costo, 'iva' => (float) $f->ivacosto, 'moneda' => (string) $f->moneda_costo];
            $vigenciaId = (int) $f->vigencia_id;
            $nota = (string) $f->nota_promocion;
            $vencimientoReserva = (int) $f->vencimiento_reserva;
        }
        ksort($tarifas);
        $ok = $this->porDias($tarifas, $dias);
        if ($ok === []) {
            return ['ok' => false, 'motivos' => ['Sin tarifa de asistencia para esa cantidad de días.'], 'resultado' => [], 'mejor' => null, 'params' => ['fecha_ini' => $from, 'fecha_fin' => $to]];
        }

        $sistema = (int) $producto['fk_sistema_id'];
        $tarifarioId = (int) ($ctx['tarifario_id'] ?? 0);
        $com = $tarifarioId ? $this->scope->comision($tarifarioId, $productoId, 'ASV', (int) $producto['fk_ciudad_id'], (int) $producto['pais']) : null;
        $mup = (float) ($com['divisor_markup'] ?? 0);
        if ($mup == 0 && ! Licencia::es('witwan_tower', 'witwan_tower_dev')) {
            $mup = 1.0;
        }

        $menor = $ok['<70'] ?? ['costo' => 0.0, 'iva' => 0.0, 'moneda' => ''];
        $mayor = $ok['>70'] ?? $menor;
        $moneda = $menor['moneda'] ?: $mayor['moneda'] ?: 'USD';
        $costoSinIva = $menor['costo'] * $menores70 + $mayor['costo'] * $mayores70;
        $ivaCosto = $menor['iva'] * $menores70 + $mayor['iva'] * $mayores70;
        $costo = $costoSinIva + $ivaCosto;
        $venta = Licencia::es('witwan_morisan', 'witwan_med') ? round($costo / ($mup ?: 1), 2) : round($costo / ($mup ?: 1));
        if ($venta <= 0) {
            return ['ok' => false, 'motivos' => ['La tarifa de asistencia da cero para esa composición.'], 'resultado' => [], 'mejor' => null, 'params' => ['fecha_ini' => $from, 'fecha_fin' => $to]];
        }

        $item = [
            'categoria' => 0, 'regimen' => 0, 'nombre' => 'Asistencia al viajero'.($mayores70 > 0 ? " ({$menores70} <70, {$mayores70} >70)" : ''), 'noches' => $dias, 'vigencia' => $vigenciaId, 'cargamanual' => 0,
            'moneda_costo' => $moneda, 'moneda' => $moneda, 'cotizacion' => 1.0,
            'costoadt' => round($costoSinIva, 2), 'costomen' => 0.0, 'costosiniva' => round($costoSinIva, 2), 'ivacosto' => round($ivaCosto, 2), 'descuento' => 0.0, 'textodescuento' => '',
            'costo' => round($costo, 2), 'costoenmoneda' => round($costo, 2), 'mup' => $mup,
            'venta' => $venta, 'ventaadt' => $venta, 'promedio' => $dias > 0 ? round($venta / $dias) : $venta,
            'pcomision' => 0.0, 'comision' => 0.0, 'iva' => 0.0, 'impuestos' => 0.0, 'percepcion1' => 0.0, 'percepcion2' => 0.0,
            'total' => $venta, 'totalapagar' => $venta, 'cupo' => 1, 'soldout' => 0,
            'vencepago' => $vencimientoReserva > 0 ? Carbon::createFromFormat('Y-m-d', (string) $ctx['hoy'])->addDays($vencimientoReserva)->format('Y-m-d') : null,
            'nota_promocion' => $nota, 'dias' => [],
        ];

        return [
            'ok' => true,
            'params' => ['producto_id' => $productoId, 'fecha_ini' => $from, 'fecha_fin' => $to, 'noches' => $dias, 'adultos' => $menores70 + $mayores70, 'menores' => [], 'residente' => $ctx['residente'] ?? 'N', 'tarifario_id' => $tarifarioId, 'sistema' => $sistema],
            'producto' => ['producto_id' => $productoId, 'nombre' => $producto['producto_nombre'], 'tipo' => 'ASV', 'modotarifa' => 'P', 'disponibilidad' => 'CI'],
            'pricing' => ['mup' => $mup, 'comision' => 0, 'iva_costo' => 0, 'iva_venta' => 0, 'modoivaventa' => 0, 'extra1' => 0, 'extra2' => 0],
            'resultado' => [0 => [0 => $item]],
            'mejor' => $item,
            'motivos' => [],
        ];
    }

    /**
     * Tarifa por tipo de pax para `dias` de cobertura (tarifa_model.php:1417-1478).
     *
     * @param  array<int,array<string,array{costo:float,iva:float,moneda:string}>>  $tarifas  ordenadas por días
     * @return array<string,array{costo:float,iva:float,moneda:string}>
     */
    public function porDias(array $tarifas, int $dias): array
    {
        if ($tarifas === [] || $dias <= 0) {
            return [];
        }
        if (isset($tarifas[$dias])) {
            return $tarifas[$dias];
        }
        $coberturas = array_keys($tarifas);
        $maximo = max($coberturas);
        $out = [];
        $sumar = function (array $base, int $veces) use (&$out) {
            foreach ($base as $tipo => $t) {
                $out[$tipo] ??= ['costo' => 0.0, 'iva' => 0.0, 'moneda' => $t['moneda']];
                $out[$tipo]['costo'] += $t['costo'] * $veces;
                $out[$tipo]['iva'] += $t['iva'] * $veces;
            }
        };
        if ($maximo < $dias) {
            // Se repite la cobertura máxima y el resto de días con la tarifa de 1 día (si existe).
            $veces = intdiv($dias, $maximo);
            $sumar($tarifas[$maximo], $veces);
            $restantes = $dias - $maximo * $veces;
            if ($restantes > 0 && isset($tarifas[1])) {
                $sumar($tarifas[1], $restantes);
            }

            return $out;
        }
        // Días menores que la mínima cobertura cargada: la menor cobertura, por día (así lo hace el CI).
        $menor = null;
        foreach ($coberturas as $k) {
            if ($k <= $dias) {
                $menor = $k;
                break;
            }
        }
        if ($menor === null) {
            $menor = min($coberturas);
        }
        $sumar($tarifas[$menor], $dias);

        return $out;
    }
}
