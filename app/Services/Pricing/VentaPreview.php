<?php

namespace App\Services\Pricing;

use App\Services\CotizacionService;
use App\Support\Licencia;

/**
 * Preview de venta por tarifario para la grilla de la vigencia. Reemplaza al
 * botón "calcular venta" de vigencia.js: mismo MarkupCalculadora que va a usar
 * el tarifador, con IVA de costo y divisor resueltos por ScopeResolver y las
 * cotizaciones de CotizacionService.
 *
 * Cotización del tarifario (tarifa_model.php:2735): si el tarifario tiene
 * `cotizacion` propia y su moneda es USD, manda esa; si no, la del día.
 */
class VentaPreview
{
    public function __construct(
        private MarkupCalculadora $calculadora,
        private ScopeResolver $scope,
        private CotizacionService $cotizaciones,
    ) {}

    /**
     * @param  array  $producto  producto_id, fk_tipoproducto_id, fk_sistema_id, fk_ciudad_id, pais
     * @param  list<array>  $tarifarios  tarifario_id, fk_moneda_id, cotizacion, divisor_markup, porcentaje_comision
     * @param  list<array{clave:string, costo:float}>  $costos
     * @return array{iva_costo:float, cotizacion_costo:float, tarifarios:array<int,array>, celdas:array<string,array<int,array>>}
     */
    public function calcular(array $producto, string $monedaCosto, string $residente, string $redondeo, array $tarifarios, array $costos): array
    {
        $iva = $this->scope->iva((int) $producto['producto_id'], (int) $producto['fk_sistema_id'], (string) $producto['fk_tipoproducto_id'], (int) ($producto['fk_ciudad_id'] ?? 0), (int) ($producto['pais'] ?? 0));
        $ivaCosto = $this->calculadora->ivaCostoAplicable((float) ($iva['iva_costo'] ?? 0), (string) $producto['fk_tipoproducto_id'], Licencia::pais(), $residente, (int) $producto['fk_sistema_id']);
        $cotCosto = $this->cotizaciones->aLaVenta($monedaCosto);

        $infoTarifarios = [];
        foreach ($tarifarios as $t) {
            $tid = (int) $t['tarifario_id'];
            $moneda = (string) ($t['fk_moneda_id'] ?? '');
            $cotVenta = ($moneda === 'USD' && (float) ($t['cotizacion'] ?? 0) != 0)
                ? (float) $t['cotizacion']
                : $this->cotizaciones->aLaVenta($moneda);
            $infoTarifarios[$tid] = [
                'moneda' => $moneda,
                'cotizacion' => $cotVenta,
                'factor' => $this->calculadora->factorCotizacion($monedaCosto, $cotCosto, $moneda, $cotVenta),
                'divisor_markup' => (float) ($t['divisor_markup'] ?? 0),
                'porcentaje_comision' => (float) ($t['porcentaje_comision'] ?? 0),
            ];
        }

        $celdas = [];
        foreach ($costos as $c) {
            foreach ($infoTarifarios as $tid => $t) {
                $celdas[(string) $c['clave']][$tid] = $this->calculadora->calcular(
                    (float) $c['costo'], $ivaCosto, $t['factor'], $t['divisor_markup'], $redondeo, $t['porcentaje_comision'],
                );
            }
        }

        return ['iva_costo' => $ivaCosto, 'cotizacion_costo' => $cotCosto, 'tarifarios' => $infoTarifarios, 'celdas' => $celdas];
    }
}
