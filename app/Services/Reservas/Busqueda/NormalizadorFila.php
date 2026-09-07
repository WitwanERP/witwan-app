<?php

namespace App\Services\Reservas\Busqueda;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Convierte "producto + cotizaciones del Tarifador por habitación" en la fila
 * única que consumen la grilla de resultados y el carrito:
 *
 *  { producto_id, nombre, tipo, proveedor, ciudad, estrellas, disponibilidad,
 *    moneda, mejor_total, promo, politica_cancelacion, pickup, vigencia_ini/fin,
 *    noches, habitaciones: [{ indice, ad, edades, pax, fk_base_id, mejor, opciones[] }] }
 *
 * Los tipos sin habitaciones (excursiones, traslados, asistencia) usan una sola
 * pseudo-habitación con toda la composición, así el carrito arma siempre "una
 * línea de servicio por habitación".
 */
class NormalizadorFila
{
    /**
     * @param  array  $producto  ProductoService::cargar() (necesita edades, fk_proveedor_id, destino…)
     * @param  list<array{ad:int, mn:list<int>, cot:array}>  $habitaciones  composición + resultado de Tarifador::cotizar()
     * @param  array  $extra  ['vigencia_fin'=>Y-m-d, 'estrellas'=>float|null, 'proveedor_nombre'=>string, 'ciudad'=>['id','nombre'], 'pickup'=>bool, 'disponibilidad_producto'=>string]
     */
    public function fila(array $producto, string $tipo, array $habitaciones, array $extra = []): array
    {
        $regimenes = $this->regimenes();
        $edades = (array) ($producto['edades'] ?? []);
        $habs = [];
        $mejorTotal = 0.0;
        $mejorVenta = 0.0;
        $moneda = '';
        $peor = 'CI';
        $promo = null;
        $noches = 0;
        $vigIni = null;
        $vigFin = null;
        $motivos = [];

        foreach ($habitaciones as $i => $h) {
            $cot = $h['cot'];
            $opciones = [];
            foreach ((array) ($cot['resultado'] ?? []) as $porRegimen) {
                foreach ($porRegimen as $item) {
                    $item['regimen_nombre'] = (string) ($regimenes[(int) $item['regimen']] ?? '');
                    $item['disponibilidad'] = $this->disponibilidad($item, (string) ($extra['disponibilidad_producto'] ?? ($producto['disponibilidad'] ?? '')));
                    $opciones[] = $item;
                }
            }
            usort($opciones, fn ($a, $b) => $a['total'] <=> $b['total']);
            $mejor = $cot['mejor'] ?? null;
            if ($mejor !== null) {
                $mejorTotal += (float) $mejor['total'];
                $mejorVenta += (float) $mejor['venta'];
                $moneda = $moneda ?: (string) $mejor['moneda'];
                $noches = max($noches, (int) $mejor['noches']);
                $d = $this->disponibilidad($mejor, (string) ($extra['disponibilidad_producto'] ?? ($producto['disponibilidad'] ?? '')));
                $peor = $this->peor($peor, $d);
                if ($promo === null && (($mejor['textodescuento'] ?? '') !== '' || ($mejor['nota_promocion'] ?? '') !== '')) {
                    $promo = ['texto' => (string) ($mejor['textodescuento'] ?? ''), 'nota' => (string) ($mejor['nota_promocion'] ?? '')];
                }
            }
            $vigIni ??= $cot['params']['fecha_ini'] ?? null;
            $vigFin ??= $cot['params']['fecha_fin'] ?? null;
            foreach ((array) ($cot['motivos'] ?? []) as $m) {
                $motivos[] = $m;
            }

            $habs[] = [
                'indice' => $i,
                'ad' => (int) $h['ad'],
                'edades' => array_values(array_map('intval', (array) $h['mn'])),
                'pax' => $this->pax((int) $h['ad'], (array) $h['mn'], $edades),
                'fk_base_id' => (string) (int) $h['ad'],
                'mejor' => $mejor ? ['categoria' => (int) $mejor['categoria'], 'regimen' => (int) $mejor['regimen']] : null,
                'opciones' => $opciones,
            ];
        }

        return [
            'producto_id' => (int) $producto['producto_id'],
            'nombre' => (string) $producto['producto_nombre'],
            'tipo' => $tipo,
            'modotarifa' => (string) ($producto['modotarifa'] ?? 'P'),
            'proveedor' => ['id' => (int) ($producto['fk_proveedor_id'] ?? 0), 'nombre' => (string) ($extra['proveedor_nombre'] ?? '')],
            'prestador_id' => (int) ($producto['fk_prestador_id'] ?? 0),
            'ciudad' => $extra['ciudad'] ?? ['id' => (int) ($producto['fk_ciudad_id'] ?? 0), 'nombre' => (string) ($producto['ciudades'][0]['nombre'] ?? '')],
            'estrellas' => $extra['estrellas'] ?? null,
            'disponibilidad' => $peor,
            'moneda' => $moneda,
            'mejor_total' => round($mejorTotal, 2),
            'mejor_venta' => round($mejorVenta, 2),
            'promo' => $promo,
            'politica_cancelacion' => trim((string) ($producto['politica_cancelacion'] ?? '')),
            'pickup' => (bool) ($extra['pickup'] ?? false),
            'vigencia_ini' => $vigIni,
            'vigencia_fin' => $extra['vigencia_fin'] ?? $vigFin,
            'noches' => $noches,
            'habitaciones' => $habs,
            'motivos' => array_values(array_unique($motivos)),
        ];
    }

    /** SO si está sold out, CI con cupo (o disponibilidad inmediata del producto), RQ en el resto (reserva.php:3946-3960). */
    public function disponibilidad(array $item, string $disponibilidadProducto): string
    {
        if ((int) ($item['soldout'] ?? 0) === 1) {
            return 'SO';
        }
        if ((int) ($item['cupo'] ?? 0) > 0 || $disponibilidadProducto === 'CI') {
            return 'CI';
        }

        return 'RQ';
    }

    private function peor(string $a, string $b): string
    {
        $orden = ['CI' => 0, 'RQ' => 1, 'SO' => 2];

        return ($orden[$b] ?? 1) > ($orden[$a] ?? 1) ? $b : $a;
    }

    /**
     * Composición de la fila de servicio a partir de las edades y los cortes del
     * producto (mismos cortes que Tarifador::menores()): infante hasta edad_infoa,
     * junior entre edad_menor2 y edad_junior, menor en el medio.
     *
     * @return array{adultos:int, menores:int, infante:int, juniors:int}
     */
    public function pax(int $ad, array $edadesMenores, array $cortes): array
    {
        $infoa = (int) ($cortes['edad_infoa'] ?? 0);
        $menor2 = (int) ($cortes['edad_menor2'] ?? 0) ?: (int) ($cortes['edad_menor1'] ?? 0);
        $junior = (int) ($cortes['edad_junior'] ?? 0);
        $out = ['adultos' => $ad, 'menores' => 0, 'infante' => 0, 'juniors' => 0];
        foreach ($edadesMenores as $e) {
            $e = (int) $e;
            if ($infoa > 0 && $e <= $infoa) {
                $out['infante']++;
            } elseif ($junior > 0 && $menor2 > 0 && $e > $menor2 && $e <= $junior) {
                $out['juniors']++;
            } else {
                $out['menores']++;
            }
        }

        return $out;
    }

    /** @return array<int,string> */
    private function regimenes(): array
    {
        return Cache::store('array')->rememberForever('generador.regimenes', fn () => DB::table('regimen')->pluck('regimen_nombre', 'regimen_id')->map(fn ($n) => (string) $n)->all());
    }
}
