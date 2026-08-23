<?php

namespace Tests\Unit\Contable;

use App\Exceptions\Contable\AsientoException;
use App\Services\Contable\PlanAsiento;
use App\Support\Contable\TipoAsiento;
use Tests\TestCase;

/**
 * Armado de las filas de `movimiento`, sin base.
 *
 * Es la parte del port que más fácil se rompe: las tres variantes escriben en
 * columnas distintas y con convenciones que no se deducen del nombre (el debe va
 * a `cuenta_credito`). Todo lo que se verifica acá está contrastado contra
 * asientocontable.php:150-225, asientocta.php:160-235 y fondos.php:218-256.
 */
class PlanAsientoTest extends TestCase
{
    private function plan(): PlanAsiento
    {
        return new PlanAsiento;
    }

    private function cabecera(array $extra = []): array
    {
        return array_merge([
            'fecha' => '2026-08-20',
            'fk_moneda_id' => 'ARS',
            'cotizacion' => 1,
            'fk_usuario_id' => 7,
        ], $extra);
    }

    private function linea(array $extra = []): array
    {
        return array_merge(['cuenta' => 0, 'descripcion' => '', 'debe' => '', 'haber' => ''], $extra);
    }

    // ------------------------------------------------------------------
    // Debe / haber
    // ------------------------------------------------------------------

    /**
     * La convención invertida del CI: el DEBE se graba en `cuenta_credito` y el
     * HABER en `cuenta_debito`. Los reportes (Balance.php:21-24, libros.php) leen
     * esas columnas como "cuál de los dos casilleros está lleno", así que darlo
     * vuelta rompería el mayor y el balance.
     */
    public function test_el_debe_va_a_cuenta_credito_y_el_haber_a_cuenta_debito(): void
    {
        $plan = $this->plan()->construir(TipoAsiento::desde('contable'), $this->cabecera([
            'lineas' => [
                $this->linea(['cuenta' => 10, 'debe' => 100]),
                $this->linea(['cuenta' => 20, 'haber' => 100]),
            ],
        ]));

        [$debe, $haber] = $plan['asientos'][0]['movimientos'];

        $this->assertSame(10, $debe['cuenta_credito']);
        $this->assertSame(0, $debe['cuenta_debito']);
        $this->assertSame('D', $debe['deha']);

        $this->assertSame(0, $haber['cuenta_credito']);
        $this->assertSame(20, $haber['cuenta_debito']);
        $this->assertSame('H', $haber['deha']);

        // `fk_plancuenta_id` lleva la cuenta en los dos casos.
        $this->assertSame(10, $debe['fk_plancuenta_id']);
        $this->assertSame(20, $haber['fk_plancuenta_id']);
    }

    /**
     * Única diferencia de fondo entre asiento contable y asiento en cuenta
     * corriente: el `movimiento.tipo` de las líneas del debe. El contable deja
     * el valor inicial 'I' (asientocontable.php:157) y el de cuenta corriente lo
     * pisa con 'D' (asientocta.php:186). El haber es 'E' en los dos.
     */
    public function test_el_tipo_del_debe_cambia_entre_contable_y_cuenta_corriente(): void
    {
        $lineas = [
            $this->linea(['cuenta' => 10, 'debe' => 50]),
            $this->linea(['cuenta' => 20, 'haber' => 50]),
        ];

        $contable = $this->plan()->construir(TipoAsiento::desde('contable'), $this->cabecera(['lineas' => $lineas]));
        $ctacte = $this->plan()->construir(TipoAsiento::desde('cuenta-corriente'), $this->cabecera(['lineas' => $lineas]));

        $this->assertSame('I', $contable['asientos'][0]['movimientos'][0]['tipo']);
        $this->assertSame('D', $ctacte['asientos'][0]['movimientos'][0]['tipo']);

        $this->assertSame('E', $contable['asientos'][0]['movimientos'][1]['tipo']);
        $this->assertSame('E', $ctacte['asientos'][0]['movimientos'][1]['tipo']);
    }

    /** El asiento contable no imputa cliente/proveedor/file: sólo cuenta corriente. */
    public function test_solo_cuenta_corriente_imputa_cliente_proveedor_y_file(): void
    {
        $lineas = [
            $this->linea(['cuenta' => 10, 'debe' => 50, 'cliente' => 5, 'proveedor' => 6, 'file' => 7]),
            $this->linea(['cuenta' => 20, 'haber' => 50]),
        ];

        $contable = $this->plan()->construir(TipoAsiento::desde('contable'), $this->cabecera(['lineas' => $lineas]));
        $ctacte = $this->plan()->construir(TipoAsiento::desde('cuenta-corriente'), $this->cabecera(['lineas' => $lineas]));

        $this->assertSame(0, $contable['asientos'][0]['movimientos'][0]['fk_cliente_id']);
        $this->assertSame(0, $contable['asientos'][0]['movimientos'][0]['fk_file_id']);

        $this->assertSame(5, $ctacte['asientos'][0]['movimientos'][0]['fk_cliente_id']);
        $this->assertSame(6, $ctacte['asientos'][0]['movimientos'][0]['fk_proveedor_id']);
        $this->assertSame(7, $ctacte['asientos'][0]['movimientos'][0]['fk_file_id']);
    }

    /**
     * asientocontable.php:216-218: sin el tilde "tocar arqueo", el movimiento
     * nace con statusmovimiento='AR' (fuera del arqueo).
     */
    public function test_sin_tocar_arqueo_el_movimiento_nace_fuera_del_arqueo(): void
    {
        $lineas = [
            $this->linea(['cuenta' => 10, 'debe' => 50]),
            $this->linea(['cuenta' => 20, 'haber' => 50]),
        ];

        $con = $this->plan()->construir(TipoAsiento::desde('contable'), $this->cabecera(['lineas' => $lineas, 'arqueo' => true]));
        $sin = $this->plan()->construir(TipoAsiento::desde('contable'), $this->cabecera(['lineas' => $lineas, 'arqueo' => false]));

        $this->assertArrayNotHasKey('statusmovimiento', $con['asientos'][0]['movimientos'][0]);
        $this->assertSame('AR', $sin['asientos'][0]['movimientos'][0]['statusmovimiento']);
    }

    /** `ordenadmin.monto` es el total del debe (asientocontable.php:142). */
    public function test_el_monto_de_la_cabecera_es_el_total_del_debe(): void
    {
        $plan = $this->plan()->construir(TipoAsiento::desde('contable'), $this->cabecera([
            'lineas' => [
                $this->linea(['cuenta' => 10, 'debe' => 30]),
                $this->linea(['cuenta' => 11, 'debe' => 70]),
                $this->linea(['cuenta' => 20, 'haber' => 100]),
            ],
        ]));

        $this->assertSame(100.0, $plan['monto']);
        $this->assertSame(100.0, $plan['debe']);
        $this->assertSame(100.0, $plan['haber']);
        $this->assertCount(3, $plan['asientos'][0]['movimientos']);
    }

    /** Las filas en blanco de la grilla no generan movimiento. */
    public function test_las_lineas_vacias_se_descartan(): void
    {
        $plan = $this->plan()->construir(TipoAsiento::desde('contable'), $this->cabecera([
            'lineas' => [
                $this->linea(['cuenta' => 10, 'debe' => 10]),
                $this->linea(),
                $this->linea(['cuenta' => 20, 'haber' => 10]),
                $this->linea(),
            ],
        ]));

        $this->assertCount(2, $plan['asientos'][0]['movimientos']);
    }

    // ------------------------------------------------------------------
    // Validaciones que el legacy no tenía en el servidor
    // ------------------------------------------------------------------

    public function test_un_asiento_descuadrado_no_se_arma(): void
    {
        $this->expectException(AsientoException::class);
        $this->expectExceptionMessage('no balancea');

        $this->plan()->construir(TipoAsiento::desde('contable'), $this->cabecera([
            'lineas' => [
                $this->linea(['cuenta' => 10, 'debe' => 100]),
                $this->linea(['cuenta' => 20, 'haber' => 90]),
            ],
        ]));
    }

    public function test_una_linea_con_cuenta_pero_sin_importe_es_un_error(): void
    {
        $this->expectException(AsientoException::class);
        $this->expectExceptionMessage('Línea 1');

        $this->plan()->construir(TipoAsiento::desde('contable'), $this->cabecera([
            'lineas' => [$this->linea(['cuenta' => 10, 'descripcion' => 'algo'])],
        ]));
    }

    public function test_una_linea_con_importe_en_las_dos_columnas_es_un_error(): void
    {
        $this->expectException(AsientoException::class);
        $this->expectExceptionMessage('debe y en el haber');

        $this->plan()->construir(TipoAsiento::desde('contable'), $this->cabecera([
            'lineas' => [$this->linea(['cuenta' => 10, 'debe' => 5, 'haber' => 5])],
        ]));
    }

    public function test_un_asiento_sin_lineas_es_un_error(): void
    {
        $this->expectException(AsientoException::class);

        $this->plan()->construir(TipoAsiento::desde('contable'), $this->cabecera([
            'lineas' => [$this->linea(), $this->linea()],
        ]));
    }

    // ------------------------------------------------------------------
    // Fondos
    // ------------------------------------------------------------------

    /**
     * Cada línea de fondos genera SU PROPIO asiento con dos movimientos
     * (fondos.php:218-256), a diferencia del debe/haber, que arma uno solo.
     */
    public function test_cada_operacion_de_fondos_genera_un_asiento_de_dos_movimientos(): void
    {
        $plan = $this->plan()->construir(TipoAsiento::desde('fondos'), $this->cabecera([
            'descripcion' => 'TRANSFERENCIA',
            'lineas' => [
                ['monto' => 100, 'ingreso' => 10, 'egreso' => 20],
                ['monto' => 50, 'ingreso' => 11, 'egreso' => 21],
            ],
        ]));

        $this->assertCount(2, $plan['asientos']);
        $this->assertCount(2, $plan['asientos'][0]['movimientos']);
        $this->assertSame(150.0, $plan['monto']);
    }

    /**
     * El ingreso acredita (deha='H') y el egreso debita (deha='D'), al revés que
     * la grilla de debe/haber. Es una incoherencia del propio CI, no del port:
     * `cuenta_credito` recibe tanto el "debe" del asiento contable como el
     * "ingreso" de fondos.
     */
    public function test_el_ingreso_acredita_y_el_egreso_debita(): void
    {
        $plan = $this->plan()->construir(TipoAsiento::desde('fondos'), $this->cabecera([
            'lineas' => [['monto' => 100, 'ingreso' => 10, 'egreso' => 20]],
        ]));

        [$ingreso, $egreso] = $plan['asientos'][0]['movimientos'];

        $this->assertSame('H', $ingreso['deha']);
        $this->assertSame('I', $ingreso['tipo']);
        $this->assertSame(10, $ingreso['cuenta_credito']);
        $this->assertSame(10, $ingreso['fk_plancuenta_id']);

        $this->assertSame('D', $egreso['deha']);
        $this->assertSame('E', $egreso['tipo']);
        $this->assertSame(20, $egreso['cuenta_debito']);

        // Rareza del legacy que se replica: el egreso NO setea fk_plancuenta_id.
        // Las consultas del detalle joinean con `fk_plancuenta_id OR
        // cuenta_debito` justamente por esto (orden_model.php:105-106).
        $this->assertSame(0, $egreso['fk_plancuenta_id']);
    }

    /** fondos.php:227: el proveedor "interno" con el que el CI marca lo propio. */
    public function test_los_movimientos_de_fondos_van_contra_el_proveedor_uno(): void
    {
        $plan = $this->plan()->construir(TipoAsiento::desde('fondos'), $this->cabecera([
            'lineas' => [['monto' => 100, 'ingreso' => 10, 'egreso' => 20]],
        ]));

        foreach ($plan['asientos'][0]['movimientos'] as $mov) {
            $this->assertSame(1, $mov['fk_proveedor_id']);
        }
    }

    /** El legacy grababa un asiento que debita y acredita la misma cuenta: nada, pero ensucia el mayor. */
    public function test_ingreso_y_egreso_no_pueden_ser_la_misma_cuenta(): void
    {
        $this->expectException(AsientoException::class);
        $this->expectExceptionMessage('no pueden ser la misma');

        $this->plan()->construir(TipoAsiento::desde('fondos'), $this->cabecera([
            'lineas' => [['monto' => 100, 'ingreso' => 10, 'egreso' => 10]],
        ]));
    }

    public function test_una_operacion_de_fondos_sin_las_dos_cuentas_es_un_error(): void
    {
        $this->expectException(AsientoException::class);
        $this->expectExceptionMessage('ingreso y la de egreso');

        $this->plan()->construir(TipoAsiento::desde('fondos'), $this->cabecera([
            'lineas' => [['monto' => 100, 'ingreso' => 10, 'egreso' => 0]],
        ]));
    }
}
