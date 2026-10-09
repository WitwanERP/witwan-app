<?php

namespace Tests\Unit\Reservas;

use App\Services\Reservas\MapaContable\EconomiaFile;
use PHPUnit\Framework\TestCase;

/**
 * Descomposición de la renta de un file en precio y cambio. Cada escenario está
 * hecho a mano y se verifica además contra lo efectivamente facturado/pagado:
 * la renta real tiene que cerrar exacto con los comprobantes.
 */
class EconomiaFileTest extends TestCase
{
    private function servicio(array $over = []): array
    {
        return array_merge([
            'id' => 1, 'cancelado' => false,
            'total' => 1100.0, 'iva' => 100.0, 'costo_operado' => 800.0,
            'cv' => 1000.0, 'cc' => 1000.0,
            'renta_congelada' => null, 'tasa_factura' => null,
            'fc3' => [], 'pagos' => [],
        ], $over);
    }

    public function test_escenario_completo_en_dolares_cierra_contra_los_comprobantes(): void
    {
        // Venta USD 1.100 (neto 1.000) presupuestada a 1.000, facturada a 1.050 y cobrada a 1.100.
        // Costo USD 800 presupuestado a 1.000, facturado por el proveedor a 1.020 y pagado a 1.080.
        $s = $this->servicio([
            'tasa_factura' => 1050.0,
            'renta_congelada' => 190.0,
            'fc3' => [['cantidad' => 800.0, 'tasa' => 1020.0, 'base' => 816000.0]],
            'pagos' => [['cantidad' => 800.0, 'tasa' => 1080.0, 'base' => 864000.0]],
        ]);
        $ventas = [['signo' => 1, 'neto_doc' => 1100.0, 'tasa' => 1050.0, 'share' => 1.0]];
        $cobranza = ['moneda' => 'USD', 'facturado' => [['cantidad' => 1100.0, 'tasa' => 1050.0]], 'cobrado' => [['cantidad' => 1100.0, 'tasa' => 1100.0]]];

        $r = (new EconomiaFile)->calcular([$s], $ventas, $cobranza, 1210000.0);
        $t = $r['totales'];

        $this->assertSame(200000.0, $t['renta_presupuestada']);            // 1.000·1.000 − 800·1.000
        $this->assertSame(0.0, $t['dif_precio_venta']);
        $this->assertSame(0.0, $t['dif_precio_costo']);
        $this->assertSame(50000.0, $t['dc_servicio_factura']);             // 1.000·(1.050 − 1.000)
        $this->assertSame(55000.0, $t['dc_factura_cobranza']);             // 1.100·(1.100 − 1.050)
        $this->assertSame(-16000.0, $t['dc_servicio_fc3']);                // 800·(1.000 − 1.020)
        $this->assertSame(-48000.0, $t['dc_fc3_pago']);                    // 800·(1.020 − 1.080)
        $this->assertSame(0.0, $t['dc_servicio_pago']);
        $this->assertSame(41000.0, $t['resultado_cambio']);
        $this->assertSame(241000.0, $t['renta_real']);
        $this->assertSame(190000.0, $t['renta_congelada']);                // 190 USD a cv

        // Cierre: venta neta a la tasa de la factura + DV2 − costo al TC del pago.
        $this->assertSame(1000 * 1050 + 55000 - 800 * 1080, (int) $t['renta_real']);
        $this->assertSame(864000.0, $r['servicios'][1]['costo_real']);
        $this->assertSame(0.0, $r['servicios'][1]['cantidades']['saldo_proveedor']);
        $this->assertSame(1210000.0 - 864000.0, $t['caja']);
    }

    public function test_sin_factura_de_proveedor_lo_impago_sigue_a_la_cotizacion_del_servicio(): void
    {
        // Costo USD 1.000 a 1.000; se pagaron USD 600 a 1.100 y no hay factura de proveedor.
        $s = $this->servicio(['total' => 1500.0, 'iva' => 0.0, 'costo_operado' => 1000.0,
            'pagos' => [['cantidad' => 600.0, 'tasa' => 1100.0, 'base' => 660000.0]]]);

        $r = (new EconomiaFile)->calcular([$s], [], ['moneda' => null], 0.0);
        $e = $r['servicios'][1];

        $this->assertSame(0.0, $e['dif_precio_costo']);                    // lo no pagado no es precio
        $this->assertSame(-60000.0, $e['dc_servicio_pago']);               // 600·(1.000 − 1.100)
        $this->assertSame(600 * 1100 + 400 * 1000.0, $e['costo_real']);
        $this->assertSame(400.0, $e['cantidades']['saldo_proveedor']);
        $this->assertSame(500000.0 - 60000.0, $r['totales']['renta_real']);
    }

    public function test_pago_de_mas_sin_factura_es_mayor_costo(): void
    {
        $s = $this->servicio(['total' => 121.0, 'iva' => 21.0, 'costo_operado' => 50.0, 'cv' => 1.0, 'cc' => 1.0,
            'pagos' => [['cantidad' => 60.0, 'tasa' => 1.0, 'base' => 60.0]]]);

        $e = (new EconomiaFile)->calcular([$s], [], ['moneda' => null], 0.0)['servicios'][1];

        $this->assertSame(-10.0, $e['dif_precio_costo']);
        $this->assertSame(40.0, $e['renta_real']);
        $this->assertSame(-10.0, $e['cantidades']['saldo_proveedor']);
    }

    public function test_proveedor_factura_distinto_del_costo(): void
    {
        // Costo USD 800 a 1.000; el proveedor factura USD 750 a 1.000 y no se pagó.
        $s = $this->servicio(['fc3' => [['cantidad' => 750.0, 'tasa' => 1000.0, 'base' => 750000.0]]]);

        $e = (new EconomiaFile)->calcular([$s], [], ['moneda' => null], 0.0)['servicios'][1];

        $this->assertSame(50000.0, $e['dif_precio_costo']);
        $this->assertSame(0.0, $e['dc_servicio_fc3']);
        $this->assertSame(750000.0, $e['costo_real']);
        $this->assertSame(750.0, $e['cantidades']['saldo_proveedor']);
    }

    public function test_nc_parcial_es_diferencia_de_precio_de_venta_neta_de_iva(): void
    {
        $s = $this->servicio(['tasa_factura' => 1050.0]);
        $ventas = [
            ['signo' => 1, 'neto_doc' => 1100.0, 'tasa' => 1050.0, 'share' => 1.0],
            ['signo' => -1, 'neto_doc' => 110.0, 'tasa' => 1060.0, 'share' => 1.0],
        ];

        $r = (new EconomiaFile)->calcular([$s], $ventas, ['moneda' => null], 0.0);

        $this->assertSame(-116600.0, $r['venta']['diferencia_bruta']);     // −110·1.060
        $this->assertSame(-106000.0, $r['totales']['dif_precio_venta']);   // × 1.000/1.100
    }

    public function test_factura_compartida_toma_solo_la_parte_del_file(): void
    {
        $s = $this->servicio(['tasa_factura' => 1050.0]);
        $ventas = [['signo' => 1, 'neto_doc' => 2200.0, 'tasa' => 1050.0, 'share' => 0.5]];

        $r = (new EconomiaFile)->calcular([$s], $ventas, ['moneda' => null], 0.0);

        $this->assertSame(0.0, $r['venta']['diferencia_bruta']);
    }

    public function test_servicio_cancelado_con_costo_resta_entero(): void
    {
        $s = $this->servicio(['cancelado' => true, 'cv' => 1.0, 'cc' => 1.0,
            'fc3' => [['cantidad' => 30.0, 'tasa' => 1.0, 'base' => 30.0]]]);

        $r = (new EconomiaFile)->calcular([$s], [], ['moneda' => null], 0.0);

        $this->assertSame(0.0, $r['totales']['renta_presupuestada']);
        $this->assertSame(-30.0, $r['totales']['dif_precio_costo']);
        $this->assertSame(-30.0, $r['totales']['renta_real']);
    }

    public function test_cobranza_parcial_solo_sobre_lo_cobrado(): void
    {
        $cobranza = ['moneda' => 'USD',
            'facturado' => [['cantidad' => 1000.0, 'tasa' => 1000.0]],
            'cobrado' => [['cantidad' => 300.0, 'tasa' => 1100.0], ['cantidad' => 200.0, 'tasa' => 1200.0]]];

        $c = (new EconomiaFile)->calcular([], [], $cobranza, 0.0)['cobranza'];

        $this->assertSame(500.0, $c['aplicado']);
        $this->assertSame(1140.0, $c['tasa_cobro']);                       // promedio ponderado
        $this->assertSame(70000.0, $c['diferencia']);                      // 500·(1.140 − 1.000)
    }
}
