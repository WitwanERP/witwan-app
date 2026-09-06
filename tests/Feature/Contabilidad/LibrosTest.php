<?php

namespace Tests\Feature\Contabilidad;

use App\Helpers\SysconfigHelper;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\CreaEsquemaConfiguracion;
use Tests\TestCase;

/** Libro diario y libro mayor (port de administracion/libros). */
class LibrosTest extends TestCase
{
    use CreaEsquemaConfiguracion;

    protected function setUp(): void
    {
        parent::setUp();
        $this->crearEsquemaConfiguracion();
        $this->autenticarPow();
        foreach (['cuentarecibos', 'cuentarecibosusd', 'anticiporecibos', 'anticiporecibosusd', 'auxrecibos'] as $k) {
            SysconfigHelper::olvidar($k);
        }

        DB::table('moneda')->insert([['moneda_id' => 'ARS', 'moneda_nombre' => 'Peso', 'moneda_basica' => 'Y'], ['moneda_id' => 'USD', 'moneda_nombre' => 'Dólar', 'moneda_basica' => 'N']]);
        DB::table('cotizacion')->insert(['cotizacion_moneda' => 'USD', 'cotizacion_fecha' => '2020-01-01', 'cotizacion_relacion' => 1000, 'cotizacion_costo' => 990]);
        DB::table('plancuenta')->insert([
            ['plancuenta_id' => 10, 'plancuenta_nombre' => 'Caja', 'plancuenta_codigo' => '1.1.01'],
            ['plancuenta_id' => 20, 'plancuenta_nombre' => 'Deudores', 'plancuenta_codigo' => '1.1.02'],
            ['plancuenta_id' => 54, 'plancuenta_nombre' => 'Recibos', 'plancuenta_codigo' => '1.1.54'],
        ]);
        DB::table('reserva')->insert(['reserva_id' => 21, 'tipocodigo' => 'RE', 'codigo' => '100', 'fk_cliente_id' => 3, 'fk_usuario_id' => 7, 'agente' => 7, 'fk_sistema_id' => 1]);
        DB::table('recibo')->insert(['recibo_id' => 70, 'recibo_tipo' => 'RC', 'recibo_nro' => 'R-70', 'fecha' => '2026-08-10', 'fk_cliente_id' => 3, 'fk_usuario_id' => 7, 'statusrecibo' => 'EM', 'monto' => 300, 'fk_moneda_id' => 'ARS', 'observaciones' => 'Seña']);
        DB::table('factura')->insert(['factura_id' => 40, 'factura_nro' => '0001-40', 'statusfactura' => 'EM', 'factura_fecha' => '2026-08-11 10:00:00', 'factura_tipo' => 'A', 'fk_cliente_id' => 3, 'fk_file_id' => 21, 'fk_moneda_id' => 'USD']);
        DB::table('ordenadmin')->insert([
            ['ordenadmin_id' => 400, 'fk_ordenadmin_id' => 0, 'fk_proveedor_id' => 5, 'fk_usuario_id' => 7, 'fecha' => '2026-08-12', 'nroservicio' => '', 'nropago' => 'OP-1', 'tipo' => 'P', 'fk_moneda_id' => 'ARS', 'cotizacion' => 1, 'monto' => 50, 'status' => 'OK'],
            ['ordenadmin_id' => 401, 'fk_ordenadmin_id' => 0, 'fk_proveedor_id' => 5, 'fk_usuario_id' => 7, 'fecha' => '2026-08-12', 'nroservicio' => '', 'nropago' => 'OP-2', 'tipo' => 'P', 'fk_moneda_id' => 'ARS', 'cotizacion' => 1, 'monto' => 999, 'status' => 'AN'],
        ]);
        // 1: recibo (cuenta de recibos 54 en débito, imputa a Caja) -> DEBE 300 Caja.
        // 2: factura en USD sobre Deudores -> DEBE 100 USD = 100000 ARS, file RE-100.
        // 3: orden de pago OK sobre Caja -> en el diario: cuenta_debito=0 => DEBE; en el mayor: egreso (ingreso invertido por orden).
        // 4: orden anulada -> excluida. 5: movimiento viejo (2020) sobre Caja -> saldo anterior del mayor.
        DB::table('movimiento')->insert([
            ['movimiento_id' => 1, 'fk_recibo_id' => 70, 'fk_ordenadmin_id' => 0, 'fk_factura_id' => 0, 'fk_facturaproveedor_id' => 0, 'fk_file_id' => 0, 'fk_asientocontable_id' => 0, 'cuenta_debito' => 54, 'cuenta_credito' => 0, 'fk_plancuenta_id' => 10, 'fk_moneda_id' => 'ARS', 'cotizacion_moneda' => 0, 'monto' => 300, 'fecha' => '2026-08-10', 'descripcion' => 'Cobro', 'operacion' => ''],
            ['movimiento_id' => 2, 'fk_recibo_id' => 0, 'fk_ordenadmin_id' => 0, 'fk_factura_id' => 40, 'fk_facturaproveedor_id' => 0, 'fk_file_id' => 21, 'fk_asientocontable_id' => 0, 'cuenta_debito' => 20, 'cuenta_credito' => 0, 'fk_plancuenta_id' => 0, 'fk_moneda_id' => 'USD', 'cotizacion_moneda' => 0, 'monto' => 100, 'fecha' => '2026-08-11', 'descripcion' => 'Venta', 'operacion' => ''],
            ['movimiento_id' => 3, 'fk_recibo_id' => 0, 'fk_ordenadmin_id' => 400, 'fk_factura_id' => 0, 'fk_facturaproveedor_id' => 0, 'fk_file_id' => 0, 'fk_asientocontable_id' => 9, 'cuenta_debito' => 0, 'cuenta_credito' => 0, 'fk_plancuenta_id' => 10, 'fk_moneda_id' => 'ARS', 'cotizacion_moneda' => 0, 'monto' => 50, 'fecha' => '2026-08-12', 'descripcion' => 'Pago prov', 'operacion' => 'TRANSF'],
            ['movimiento_id' => 4, 'fk_recibo_id' => 0, 'fk_ordenadmin_id' => 401, 'fk_factura_id' => 0, 'fk_facturaproveedor_id' => 0, 'fk_file_id' => 0, 'fk_asientocontable_id' => 0, 'cuenta_debito' => 0, 'cuenta_credito' => 0, 'fk_plancuenta_id' => 10, 'fk_moneda_id' => 'ARS', 'cotizacion_moneda' => 0, 'monto' => 999, 'fecha' => '2026-08-12', 'descripcion' => 'Anulada', 'operacion' => ''],
            ['movimiento_id' => 5, 'fk_recibo_id' => 0, 'fk_ordenadmin_id' => 0, 'fk_factura_id' => 0, 'fk_facturaproveedor_id' => 0, 'fk_file_id' => 0, 'fk_asientocontable_id' => 1, 'cuenta_debito' => 10, 'cuenta_credito' => 0, 'fk_plancuenta_id' => 10, 'fk_moneda_id' => 'ARS', 'cotizacion_moneda' => 0, 'monto' => 1000, 'fecha' => '2020-05-05', 'descripcion' => 'Apertura', 'operacion' => ''],
        ]);
    }

    public function test_libro_diario_clasifica_por_documento_convierte_moneda_y_excluye_anulados(): void
    {
        $this->get('/app/contabilidad/libro-diario')->assertOk()->assertInertia(fn (Assert $p) => $p->component('Reportes/Listado')->has('grupos', 0));

        $this->get('/app/contabilidad/libro-diario?fecha=2026-08-01&fecha_to=2026-08-31')->assertOk()->assertInertia(fn (Assert $p) => $p
            ->has('grupos', 3)
            ->where('grupos.0.filas.0.comprobante', 'RECIBO #R-70')
            ->where('grupos.0.filas.0.cuenta', 'Caja')
            ->where('grupos.0.filas.0.debe', 300)
            ->where('grupos.0.filas.0.haber', 0)
            ->where('grupos.1.filas.0.comprobante', 'FACTURA #0001-40')
            ->where('grupos.1.filas.0.cuenta', 'Deudores')
            ->where('grupos.1.filas.0.file', 'RE-100')
            ->where('grupos.1.filas.0.debe', 100000)
            ->where('grupos.2.filas.0.asiento', '9')
            ->where('grupos.2.filas.0.comprobante', 'ORDEN# OP-1')
            ->where('grupos.2.filas.0.debe', 50)
            ->where('grupos.2.totales.debe', 50)
        );
        // En USD: la factura queda en 100 y el recibo en 0.30.
        $this->get('/app/contabilidad/libro-diario?fecha=2026-08-01&fecha_to=2026-08-31&moneda=USD')->assertOk()->assertInertia(fn (Assert $p) => $p
            ->where('grupos.1.filas.0.debe', 100)->where('grupos.0.filas.0.debe', 0.3));
        // Por número de asiento no hace falta rango.
        $this->get('/app/contabilidad/libro-diario?nroasiento=1')->assertOk()->assertInertia(fn (Assert $p) => $p
            ->has('grupos', 1)->where('grupos.0.filas.0.comprobante', 'ASIENTO')->where('grupos.0.filas.0.debe', 1000));
        $csv = $this->get('/app/contabilidad/libro-diario/export?fecha=2026-08-01&fecha_to=2026-08-31')->assertOk()->streamedContent();
        $this->assertStringContainsString('RECIBO #R-70', $csv);
        $this->assertStringNotContainsString('Anulada', $csv);
    }

    public function test_libro_mayor_saldo_anterior_debe_haber_y_saldo_del_periodo(): void
    {
        $this->get('/app/contabilidad/libro-mayor?cuenta[]=10&fecha=2026-08-01&fecha_to=2026-08-31')->assertOk()->assertInertia(fn (Assert $p) => $p
            ->has('grupos', 1)
            ->where('grupos.0.clave', 'Caja')
            ->has('grupos.0.filas', 3)
            ->where('grupos.0.filas.0.comprobante', 'SALDO ANTERIOR')
            ->where('grupos.0.filas.0.saldo', 1000)
            ->where('grupos.0.filas.1.comprobante', 'RECIBO #R-70')
            ->where('grupos.0.filas.1.debe', 300)
            ->where('grupos.0.filas.1.saldo', 300)
            ->where('grupos.0.filas.1.moneda', 'ARS')
            ->where('grupos.0.filas.2.comprobante', 'ORDEN# OP-1')
            ->where('grupos.0.filas.2.haber', 50)
            ->where('grupos.0.filas.2.saldo', 250)
            ->where('grupos.0.filas.2.comprobante_link', '/administracion/ordenpago/imprimir/400')
            ->where('grupos.0.totales.debe', 300)
            ->where('grupos.0.totales.haber', 50)
        );
        // Acumular desde 2026 deja el saldo anterior en 0.
        $this->get('/app/contabilidad/libro-mayor?cuenta[]=10&fecha=2026-08-01&fecha_to=2026-08-31&acumulado=2026-01-01')->assertOk()->assertInertia(fn (Assert $p) => $p
            ->where('grupos.0.filas.0.saldo', 0));
        // Dos cuentas: dos grupos; Deudores en USD.
        $this->get('/app/contabilidad/libro-mayor?cuenta[]=10&cuenta[]=20&fecha=2026-08-01&fecha_to=2026-08-31&moneda=USD')->assertOk()->assertInertia(fn (Assert $p) => $p
            ->has('grupos', 2)
            ->where('grupos.1.clave', 'Deudores')
            ->where('grupos.1.filas.1.debe', 100)
            ->where('grupos.1.filas.1.file', 'RE-100'));
    }
}
