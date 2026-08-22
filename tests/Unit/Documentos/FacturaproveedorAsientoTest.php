<?php

namespace Tests\Unit\Documentos;

use App\Exceptions\Documentos\FacturaproveedorException;
use App\Services\Documentos\FacturaproveedorAsientoService;
use App\Services\Documentos\FacturaproveedorCalculo;
use App\Support\Contable\CuentasContables;
use Tests\TestCase;

/**
 * Asiento contable de una factura de tercero.
 *
 * Se prueba `planDeMovimientos()`, que arma las líneas sin tocar la base: eso
 * permite verificar el balance y la selección de cuentas sin levantar las veinte
 * tablas legacy que necesitaría un test de integración.
 */
class FacturaproveedorAsientoTest extends TestCase
{
    /** Mapa de cuentas de una licencia bien configurada. */
    private const CUENTAS = [
        'fc3exento' => 101,
        'fc3gral' => 102,
        'fc3especial' => 103,
        'fc3nocomputable' => 104,
        'fc3monto27' => 105,
        'fc3monto25' => 106,
        'ctaivatur' => 110,
        'fc3retiva' => 111,
        'fc3retiibb' => 112,
        'fc3retganancias' => 113,
        'fc3perciibb' => 114,
        'fc3perciva' => 115,
        'fc3perganancias' => 116,
        'fc3otros' => 117,
        'fc3ivatotal' => 120,
        'fc3ivatotal_i' => 121,
        'fc3ivaespecial' => 122,
        'fc3ivaespecial_i' => 123,
        'fc3iva27' => 124,
        'fc3iva25' => 125,
        'cuentaproveedor' => 200,
        'cuentaproveedorusd' => 201,
        'cuentaproveedorvarios' => 202,
        'provisionBSP' => 203,
    ];

    protected function setUp(): void
    {
        parent::setUp();

        app()->instance('tenant', (object) ['base' => 'licencia_test', 'pais' => 'AR', 'licencia' => 1]);
    }

    public function test_el_asiento_balancea_en_el_caso_habitual(): void
    {
        $lineas = $this->plan([
            'general' => 1000,
            'exento' => 200,
            'retencioniva' => 50,
        ]);

        $this->assertSame(0.0, $this->servicio()->descuadre($lineas));
    }

    public function test_el_asiento_balancea_con_todas_las_alicuotas_menos_la_de_2_5(): void
    {
        $lineas = $this->plan([
            'general' => 1000,
            'especial' => 500,
            'monto27' => 300,
            'nocomputable' => 100,
            'exento' => 50,
            'ivatur' => 30,
            'percepcioniibb' => 20,
            'otrosimpuestos' => 10,
        ]);

        $this->assertSame(0.0, $this->servicio()->descuadre($lineas));
    }

    /**
     * FIDELIDAD DOCUMENTADA, no un descuido.
     *
     * El legacy suma el IVA del 2,5% al crédito del proveedor (vía `soloiva`,
     * factura3ero.php:869) pero nunca genera el débito correspondiente
     * (:1281-1339), así que el asiento queda descuadrado exactamente en ese
     * importe. Corregirlo mueve saldos históricos, y el CI —que sigue vivo—
     * seguiría generando asientos con el bug. Por eso está detrás de un flag.
     *
     * Si alguien "arregla" esto sin coordinarlo, este test se pone en rojo y
     * obliga a tomar la decisión conscientemente.
     */
    public function test_el_iva_del_2_5_por_ciento_descuadra_el_asiento_como_en_el_legacy(): void
    {
        config(['facturaproveedor.iva_monto25_al_debe' => false]);

        $lineas = $this->plan(['general' => 1000, 'monto25' => 400]);

        // 400 * 0.025 = 10, que entra al crédito pero no al débito.
        $this->assertSame(-10.0, $this->servicio()->descuadre($lineas));
    }

    public function test_con_el_flag_activado_el_iva_del_2_5_por_ciento_cuadra(): void
    {
        config(['facturaproveedor.iva_monto25_al_debe' => true]);

        $lineas = $this->plan(['general' => 1000, 'monto25' => 400]);

        $this->assertSame(0.0, $this->servicio()->descuadre($lineas));
        $this->assertContains(125, array_column($lineas, 'cuenta'));
    }

    public function test_los_gastos_usan_la_cuenta_de_iva_alternativa(): void
    {
        $normal = $this->plan(['general' => 1000], ['tipomovimiento' => 'Servicio']);
        $gasto = $this->plan(['general' => 1000], ['tipomovimiento' => 'Gasto']);

        $this->assertSame(120, $this->cuentaDe($normal, 'iva_general'));
        $this->assertSame(121, $this->cuentaDe($gasto, 'iva_general'));
    }

    public function test_la_cuenta_de_credito_depende_del_tipo_de_movimiento_y_la_moneda(): void
    {
        $casos = [
            ['tipomovimiento' => 'Servicio', 'fk_moneda_id' => 'ARS', 'esperada' => 200],
            ['tipomovimiento' => 'Servicio', 'fk_moneda_id' => 'USD', 'esperada' => 201],
            ['tipomovimiento' => 'Gasto', 'fk_moneda_id' => 'ARS', 'esperada' => 202],
            ['tipomovimiento' => 'Boleta', 'fk_moneda_id' => 'ARS', 'esperada' => 202],
            ['tipomovimiento' => 'BSP', 'fk_moneda_id' => 'ARS', 'esperada' => 203],
            // El tipo pisa a la moneda: un gasto en USD va igual a varios.
            ['tipomovimiento' => 'Gasto', 'fk_moneda_id' => 'USD', 'esperada' => 202],
        ];

        foreach ($casos as $caso) {
            $lineas = $this->plan(['general' => 1000], $caso);

            $this->assertSame(
                $caso['esperada'],
                $this->cuentaDe($lineas, 'total'),
                "tipo={$caso['tipomovimiento']} moneda={$caso['fk_moneda_id']}"
            );
        }
    }

    /**
     * El proveedor BSP de la licencia gana sobre el tipo de movimiento, y se
     * evalúa último: es el orden del legacy (factura3ero.php:1353-1366).
     */
    public function test_el_proveedor_bsp_de_la_licencia_pisa_al_tipo_de_movimiento(): void
    {
        config(['facturaproveedor.proveedor_bsp.por_licencia' => ['licencia_test' => 999]]);

        $lineas = $this->plan(['general' => 1000], ['tipomovimiento' => 'Gasto', 'fk_proveedor_id' => 999]);

        $this->assertSame(203, $this->cuentaDe($lineas, 'total'));
    }

    public function test_el_iva_turismo_se_debita_en_negativo(): void
    {
        $lineas = $this->plan(['general' => 1000, 'ivatur' => 40]);

        $ivatur = collect($lineas)->firstWhere('concepto', 'ivatur');

        $this->assertNotNull($ivatur);
        $this->assertSame(-40.0, $ivatur['monto']);
    }

    public function test_la_cuenta_del_formulario_pisa_al_mapa_de_sysconfig(): void
    {
        $lineas = $this->plan(['general' => 1000, 'exento' => 100], ['fk_plancuenta_id' => 777]);

        $this->assertSame(777, $this->cuentaDe($lineas, 'general'));
        $this->assertSame(777, $this->cuentaDe($lineas, 'exento'));
    }

    /**
     * El legacy resolvía una cuenta sin configurar como 0 y grababa igual el
     * movimiento, dejando el asiento apuntando a una cuenta inexistente.
     */
    public function test_una_cuenta_sin_configurar_falla_en_vez_de_grabar_contra_la_cuenta_cero(): void
    {
        $this->expectException(FacturaproveedorException::class);
        $this->expectExceptionMessage('fc3ivatotal');

        $cuentas = self::CUENTAS;
        unset($cuentas['fc3ivatotal']);

        $this->plan(['general' => 1000], [], $cuentas);
    }

    public function test_la_proporcion_prorratea_todas_las_lineas(): void
    {
        $completo = $this->plan(['general' => 1000], [], self::CUENTAS, 1.0);
        $mitad = $this->plan(['general' => 1000], [], self::CUENTAS, 0.5);

        $this->assertSame(
            round(array_sum(array_column($completo, 'monto')) / 2, 2),
            round(array_sum(array_column($mitad, 'monto')), 2)
        );
    }

    // ------------------------------------------------------------------

    private function plan(array $montos, array $extra = [], ?array $cuentas = null, float $prc = 1.0): array
    {
        $datos = array_merge([
            'tipomovimiento' => 'Servicio',
            'fk_moneda_id' => 'ARS',
            'fk_proveedor_id' => 1,
            'fk_plancuenta_id' => 0,
            'fecha' => '2026-01-15',
            'fechacontable' => '2026-01-15',
        ], $extra);

        $calculo = new FacturaproveedorCalculo(0.21, 'AR', (array) config('facturaproveedor.alicuotas_fijas'));

        return $this->servicio($cuentas)->planDeMovimientos($datos, $calculo->calcular($montos), $calculo, $prc);
    }

    private function servicio(?array $cuentas = null): FacturaproveedorAsientoService
    {
        return new FacturaproveedorAsientoService(new CuentasContables($cuentas ?? self::CUENTAS));
    }

    private function cuentaDe(array $lineas, string $concepto): ?int
    {
        foreach ($lineas as $l) {
            if ($l['concepto'] === $concepto) {
                return $l['cuenta'];
            }
        }

        return null;
    }
}
