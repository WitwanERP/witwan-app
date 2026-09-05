<?php

namespace App\Services\Pricing;

/**
 * Costo → venta. Clase pura.
 *
 * Reemplaza al botón "calcular venta" de assets/admin/js/vigencia.js:37-91, que
 * duplicaba (mal: sin comisión, sin redondeo configurable) la fórmula de
 * Tarifa_model::tarifar(). El orden de las operaciones es el del tarifador
 * (tarifa_model.php:1505-1514 y cabecera del archivo):
 *
 *   costo
 *   + IVA de costo            (iva_costo %, salvo HOT en AR para no residentes: vigencia.js:63)
 *   × cotización              (moneda de costo → moneda del tarifario)
 *   ÷ divisor_markup          (si es 0 no se aplica)
 *   → redondeo                (tarifa.redondear: ' ' 2 decimales, 'R' entero, 'C' entero hacia arriba)
 *   = venta
 *   venta × %comisión         = comisión
 *
 * Cuando la vigencia es de carga manual el valor cargado es la venta y no
 * pasa por acá (tarifa_model.php:2951-3010).
 */
final class MarkupCalculadora
{
    /**
     * @param  float  $costo  en moneda de costo
     * @param  float  $ivaCosto  porcentaje (21 = 21 %)
     * @param  float  $cotizacion  factor moneda costo → moneda de venta (1 si es la misma)
     * @param  float  $divisorMarkup  tarifariocomision.divisor_markup (0 = sin markup)
     * @param  string  $redondeo  tarifa.redondear
     * @param  float  $comision  porcentaje de comisión del tarifario (informativo)
     * @return array{costo:float, costo_con_iva:float, costo_en_moneda:float, venta:float, comision:float, neto:float}
     */
    public function calcular(float $costo, float $ivaCosto = 0, float $cotizacion = 1, float $divisorMarkup = 0, string $redondeo = ' ', float $comision = 0): array
    {
        $conIva = $costo + $costo * $ivaCosto / 100;
        $enMoneda = $conIva * ($cotizacion != 0 ? $cotizacion : 1);
        $venta = $divisorMarkup != 0 ? $enMoneda / $divisorMarkup : $enMoneda;
        $venta = $this->redondear($venta, $redondeo);

        $vComision = round($venta * $comision / 100, 2);

        return [
            'costo' => round($costo, 2),
            'costo_con_iva' => round($conIva, 2),
            'costo_en_moneda' => round($enMoneda, 2),
            'venta' => $venta,
            'comision' => $vComision,
            'neto' => round($venta - $vComision, 2),
        ];
    }

    /**
     * Regla del CI para el IVA de costo: en Argentina, hotelería para NO
     * residentes no lleva IVA de costo (vigencia.js:63 y tarifa_model.php:2716).
     * El legacy lo hace en dos lugares con condiciones distintas (el JS anula
     * para todo lo que no sea 'R', el tarifador sólo para 'O' en receptivo); se
     * toma la del tarifador porque es la que fija el precio final.
     */
    public function ivaCostoAplicable(float $ivaCosto, string $tipoProducto, string $paisLicencia, string $residente, int $sistemaId): float
    {
        if ($paisLicencia === 'AR' && $tipoProducto === 'HOT' && $residente === 'O' && $sistemaId === 1) {
            return 0.0;
        }

        return $ivaCosto;
    }

    /**
     * Factor de conversión entre la moneda de costo y la del tarifario, con las
     * cotizaciones de ambas contra la básica: (cotización costo / cotización venta).
     * Igual moneda → 1 (vigencia.js:50-53 y tarifa_model.php:2746-2750).
     */
    public function factorCotizacion(string $monedaCosto, float $cotizacionCosto, string $monedaVenta, float $cotizacionVenta): float
    {
        if ($monedaVenta === '' || $monedaVenta === $monedaCosto) {
            return 1.0;
        }
        if ($cotizacionVenta == 0) {
            return 0.0;
        }

        return $cotizacionCosto / $cotizacionVenta;
    }

    public function redondear(float $valor, string $modo): float
    {
        return match (trim($modo)) {
            'R' => (float) round($valor),
            'C' => (float) ceil($valor - 1e-9),
            default => round($valor, 2),
        };
    }
}
