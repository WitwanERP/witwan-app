<?php

namespace Tests\Feature\Caja;

use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\CreaEsquemaConfiguracion;
use Tests\TestCase;

/** Cartera, arqueo de caja y solicitudes. */
class CajaTest extends TestCase
{
    use CreaEsquemaConfiguracion;

    protected function setUp(): void
    {
        parent::setUp();
        $this->crearEsquemaConfiguracion();
        $this->autenticarPow();
        DB::table('moneda')->insert([['moneda_id' => 'ARS', 'moneda_nombre' => 'Peso', 'moneda_basica' => 'Y'], ['moneda_id' => 'USD', 'moneda_nombre' => 'Dólar', 'moneda_basica' => 'N']]);
        DB::table('plancuenta')->insert([
            ['plancuenta_id' => 213101, 'plancuenta_nombre' => 'Valores a depositar', 'plancuenta_codigo' => '2.1', 'cartera' => 1, 'arqueo' => 0, 'fk_moneda_id' => ''],
            ['plancuenta_id' => 10, 'plancuenta_nombre' => 'Caja', 'plancuenta_codigo' => '1.1', 'cartera' => 0, 'arqueo' => 1, 'fk_moneda_id' => 'ARS'],
        ]);
        DB::table('recibo')->insert([
            ['recibo_id' => 70, 'recibo_tipo' => 'RC', 'recibo_nro' => 'R-70', 'fecha' => '2026-08-10', 'fk_cliente_id' => 3, 'fk_usuario_id' => 7, 'statusrecibo' => 'EM', 'monto' => 300, 'fk_moneda_id' => 'ARS'],
            ['recibo_id' => 71, 'recibo_tipo' => 'RC', 'recibo_nro' => 'R-71', 'fecha' => '2026-08-10', 'fk_cliente_id' => 3, 'fk_usuario_id' => 7, 'statusrecibo' => 'AN', 'monto' => 1, 'fk_moneda_id' => 'ARS'],
        ]);
        DB::table('ordenadmin')->insert(['ordenadmin_id' => 400, 'fk_ordenadmin_id' => 0, 'fk_proveedor_id' => 5, 'fk_usuario_id' => 7, 'fecha' => '2026-08-12', 'nroservicio' => '', 'nropago' => 'MF-1', 'tipo' => 'M', 'fk_moneda_id' => 'ARS', 'cotizacion' => 1, 'monto' => 50, 'status' => 'OK']);
        DB::table('movimiento')->insert([
            // Cartera: cheque del recibo 70 (débito) en BANCO NACION; cheque de recibo anulado (excluido); movimiento ya utilizado (excluido); orden MF (haber, banco GALICIA).
            ['movimiento_id' => 1, 'fk_recibo_id' => 70, 'fk_ordenadmin_id' => 0, 'fk_plancuenta_id' => 213101, 'cuenta_debito' => 213101, 'cuenta_credito' => 0, 'fk_moneda_id' => 'ARS', 'cotizacion_moneda' => 1, 'monto' => 300, 'fecha' => '2026-08-10', 'fecha_acreditacion' => '2026-09-10', 'banco' => 'Banco Nacion', 'descripcion' => '12345', 'operacion' => 'CHEQUE', 'utilizado' => 0, 'statusmovimiento' => 'OK'],
            ['movimiento_id' => 2, 'fk_recibo_id' => 71, 'fk_ordenadmin_id' => 0, 'fk_plancuenta_id' => 213101, 'cuenta_debito' => 213101, 'cuenta_credito' => 0, 'fk_moneda_id' => 'ARS', 'cotizacion_moneda' => 1, 'monto' => 1, 'fecha' => '2026-08-10', 'fecha_acreditacion' => '2026-09-10', 'banco' => 'Banco Nacion', 'descripcion' => '999', 'operacion' => 'CHEQUE', 'utilizado' => 0, 'statusmovimiento' => 'OK'],
            ['movimiento_id' => 3, 'fk_recibo_id' => 0, 'fk_ordenadmin_id' => 0, 'fk_plancuenta_id' => 213101, 'cuenta_debito' => 213101, 'cuenta_credito' => 0, 'fk_moneda_id' => 'ARS', 'cotizacion_moneda' => 1, 'monto' => 5, 'fecha' => '2026-08-10', 'fecha_acreditacion' => '2026-09-10', 'banco' => 'Galicia', 'descripcion' => '1', 'operacion' => '', 'utilizado' => 1, 'statusmovimiento' => 'OK'],
            ['movimiento_id' => 4, 'fk_recibo_id' => 0, 'fk_ordenadmin_id' => 400, 'fk_plancuenta_id' => 213101, 'cuenta_debito' => 0, 'cuenta_credito' => 0, 'fk_moneda_id' => 'ARS', 'cotizacion_moneda' => 1, 'monto' => 50, 'fecha' => '2026-08-12', 'fecha_acreditacion' => '2026-09-12', 'banco' => 'Galicia', 'descripcion' => '777', 'operacion' => 'CHEQUE', 'utilizado' => 0, 'statusmovimiento' => 'OK'],
            // Arqueo Caja: 1000 ayer (inicial), 200 hoy ingreso (recibo), 50 hoy egreso (orden).
            ['movimiento_id' => 5, 'fk_recibo_id' => 0, 'fk_ordenadmin_id' => 0, 'fk_plancuenta_id' => 10, 'cuenta_debito' => 10, 'cuenta_credito' => 0, 'fk_moneda_id' => 'ARS', 'cotizacion_moneda' => 1, 'monto' => 1000, 'fecha' => now()->subDay()->toDateString(), 'fecha_acreditacion' => null, 'banco' => '', 'descripcion' => 'Apertura', 'operacion' => '', 'utilizado' => 0, 'statusmovimiento' => 'OK'],
            ['movimiento_id' => 6, 'fk_recibo_id' => 70, 'fk_ordenadmin_id' => 0, 'fk_plancuenta_id' => 10, 'cuenta_debito' => 54, 'cuenta_credito' => 0, 'fk_moneda_id' => 'ARS', 'cotizacion_moneda' => 1, 'monto' => 200, 'fecha' => now()->toDateString(), 'fecha_acreditacion' => null, 'banco' => '', 'descripcion' => 'Cobro', 'operacion' => '', 'utilizado' => 0, 'statusmovimiento' => 'OK'],
            ['movimiento_id' => 7, 'fk_recibo_id' => 0, 'fk_ordenadmin_id' => 400, 'fk_plancuenta_id' => 10, 'cuenta_debito' => 0, 'cuenta_credito' => 0, 'fk_moneda_id' => 'ARS', 'cotizacion_moneda' => 1, 'monto' => 50, 'fecha' => now()->toDateString(), 'fecha_acreditacion' => null, 'banco' => '', 'descripcion' => 'Pago', 'operacion' => '', 'utilizado' => 0, 'statusmovimiento' => 'OK'],
            ['movimiento_id' => 8, 'fk_recibo_id' => 0, 'fk_ordenadmin_id' => 0, 'fk_plancuenta_id' => 10, 'cuenta_debito' => 10, 'cuenta_credito' => 0, 'fk_moneda_id' => 'ARS', 'cotizacion_moneda' => 1, 'monto' => 9999, 'fecha' => now()->toDateString(), 'fecha_acreditacion' => null, 'banco' => '', 'descripcion' => 'Pendiente', 'operacion' => '', 'utilizado' => 0, 'statusmovimiento' => 'PE'],
        ]);
        DB::table('solicitud')->insert([
            ['solicitud_id' => 1, 'solicitud_nombre' => 'Juan', 'solicitud_apellido' => 'Gómez', 'solicitud_email' => 'j@x.com', 'solicitud_empresa' => 'Viajes JG', 'solicitud_status' => 'PENDIENTE', 'solicitud_fecha' => '2026-08-01 10:00:00'],
            ['solicitud_id' => 2, 'solicitud_nombre' => 'Ana', 'solicitud_apellido' => 'Ruiz', 'solicitud_email' => 'a@x.com', 'solicitud_empresa' => 'AR Tours', 'solicitud_status' => 'APROBADA', 'solicitud_fecha' => '2026-07-01 10:00:00'],
        ]);
    }

    public function test_cartera_agrupa_por_banco_excluye_anulados_y_utilizados_y_permite_quitar(): void
    {
        $this->get('/app/caja/cartera')->assertOk()->assertInertia(fn (Assert $p) => $p
            ->component('Reportes/Listado')
            ->has('grupos', 2)
            ->where('grupos.0.clave', 'BANCO NACION')
            ->has('grupos.0.filas', 1)
            ->where('grupos.0.filas.0.doc', 'RC R-70')
            ->where('grupos.0.filas.0.monto', 300)
            ->where('grupos.1.clave', 'GALICIA')
            ->where('grupos.1.filas.0.doc', 'MF MF-1')
            ->where('grupos.1.filas.0.monto', 50)
            ->where('grupos.1.filas.0.acciones.0.method', 'post')
        );
        $this->get('/app/caja/cartera?tipos=213101&banco=GALICIA&nrocheque=777')->assertOk()->assertInertia(fn (Assert $p) => $p->has('grupos', 1)->has('grupos.0.filas', 1));
        $this->get('/app/caja/cartera?fecha_to=2026-09-11')->assertOk()->assertInertia(fn (Assert $p) => $p->has('grupos', 1));

        $this->post('/app/caja/cartera/1/utilizar')->assertRedirect();
        $this->assertSame(1, (int) DB::table('movimiento')->where('movimiento_id', 1)->value('utilizado'));
        $this->get('/app/caja/cartera')->assertOk()->assertInertia(fn (Assert $p) => $p->has('grupos', 1));
    }

    public function test_arqueo_del_dia_con_saldo_inicial_movimientos_y_saldo_final(): void
    {
        $this->get('/app/caja/arqueo')->assertOk()->assertInertia(fn (Assert $p) => $p
            ->has('grupos', 1)
            ->where('grupos.0.clave', 'Caja (ARS)')
            ->has('grupos.0.filas', 4)
            ->where('grupos.0.filas.0.comprobante', 'SALDO INICIAL')->where('grupos.0.filas.0.saldo', 1000)
            ->where('grupos.0.filas.1.comprobante', 'RECIBO #R-70')->where('grupos.0.filas.1.debe', 200)->where('grupos.0.filas.1.saldo', 200)
            ->where('grupos.0.filas.2.comprobante', 'MOVIMIENTO# MF-1')->where('grupos.0.filas.2.haber', 50)->where('grupos.0.filas.2.saldo', 150)
            ->where('grupos.0.filas.3.comprobante', 'SALDO FINAL')->where('grupos.0.filas.3.saldo', 1150)
            ->where('grupos.0.totales.debe', 200)
        );
        $this->get('/app/caja/arqueo?fecha='.now()->subDay()->toDateString())->assertOk()->assertInertia(fn (Assert $p) => $p
            ->has('grupos.0.filas', 3)->where('grupos.0.filas.0.saldo', 0)->where('grupos.0.filas.2.saldo', 1000));
    }

    public function test_solicitudes_pendientes_por_defecto_con_acciones_al_legacy(): void
    {
        $this->get('/app/config/solicitudes')->assertOk()->assertInertia(fn (Assert $p) => $p
            ->has('grupos.0.filas', 1)
            ->where('grupos.0.filas.0.apellido', 'Gómez')
            ->where('grupos.0.filas.0.acciones.1.label', 'Aprobar')
            ->where('grupos.0.filas.0.acciones.1.href', '/configuracion/solicitud/aprobar/1'));
        $this->get('/app/config/solicitudes?status=TODAS')->assertOk()->assertInertia(fn (Assert $p) => $p->has('grupos.0.filas', 2));
        $this->get('/app/config/solicitudes?status=TODAS&texto=tours')->assertOk()->assertInertia(fn (Assert $p) => $p->has('grupos.0.filas', 1)->where('grupos.0.filas.0.status', 'APROBADA'));
    }
}
