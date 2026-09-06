<?php

namespace Tests\Feature\Contabilidad;

use App\Helpers\SysconfigHelper;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\CreaEsquemaConfiguracion;
use Tests\TestCase;

class BalanceOchoYEstadoCuentaTest extends TestCase
{
    use CreaEsquemaConfiguracion;

    protected function setUp(): void
    {
        parent::setUp();
        $this->crearEsquemaConfiguracion();
        $this->autenticarPow();
        foreach (['activo', 'pasivo', 'ganancia', 'perdida'] as $k) {
            SysconfigHelper::olvidar($k);
        }
        DB::table('moneda')->insert([['moneda_id' => 'ARS', 'moneda_nombre' => 'Peso', 'moneda_basica' => 'Y'], ['moneda_id' => 'USD', 'moneda_nombre' => 'Dólar', 'moneda_basica' => 'N']]);
    }

    public function test_balance_8_columnas_reparte_saldos_por_tipo_de_raiz_y_calcula_resultado(): void
    {
        DB::table('sysconfig')->insert([['sysconfig_key' => 'activo', 'sysconfig_value' => '1'], ['sysconfig_key' => 'pasivo', 'sysconfig_value' => '2'], ['sysconfig_key' => 'ganancia', 'sysconfig_value' => '4'], ['sysconfig_key' => 'perdida', 'sysconfig_value' => '5']]);
        DB::table('plancuenta')->insert([
            ['plancuenta_id' => 1, 'plancuenta_nombre' => 'ACTIVO', 'plancuenta_codigo' => '1', 'fk_plancuenta_id' => 0],
            ['plancuenta_id' => 10, 'plancuenta_nombre' => 'Caja', 'plancuenta_codigo' => '1.1', 'fk_plancuenta_id' => 1],
            ['plancuenta_id' => 2, 'plancuenta_nombre' => 'PASIVO', 'plancuenta_codigo' => '2', 'fk_plancuenta_id' => 0],
            ['plancuenta_id' => 20, 'plancuenta_nombre' => 'Proveedores', 'plancuenta_codigo' => '2.1', 'fk_plancuenta_id' => 2],
            ['plancuenta_id' => 4, 'plancuenta_nombre' => 'GANANCIAS', 'plancuenta_codigo' => '4', 'fk_plancuenta_id' => 0],
            ['plancuenta_id' => 40, 'plancuenta_nombre' => 'Ventas', 'plancuenta_codigo' => '4.1', 'fk_plancuenta_id' => 4],
            ['plancuenta_id' => 5, 'plancuenta_nombre' => 'PERDIDAS', 'plancuenta_codigo' => '5', 'fk_plancuenta_id' => 0],
        ]);
        DB::table('ordenadmin')->insert(['ordenadmin_id' => 400, 'fk_ordenadmin_id' => 0, 'fk_proveedor_id' => 5, 'fk_usuario_id' => 7, 'fecha' => '2026-08-12', 'nroservicio' => '', 'nropago' => 'X-1', 'tipo' => 'X', 'fk_moneda_id' => 'ARS', 'cotizacion' => 1, 'monto' => 1, 'status' => 'OK']);
        DB::table('movimiento')->insert([
            // Caja: +1000 (débito) => debe 1000, deudor 1000, activo 1000.
            ['movimiento_id' => 1, 'fk_ordenadmin_id' => 0, 'fk_plancuenta_id' => 10, 'cuenta_debito' => 10, 'cuenta_credito' => 0, 'fk_moneda_id' => 'ARS', 'cotizacion_moneda' => 1, 'monto' => 1000, 'fecha' => '2026-08-10'],
            // Proveedores: sólo crédito => -300 => haber 300, acreedor 300, pasivo 300.
            ['movimiento_id' => 2, 'fk_ordenadmin_id' => 0, 'fk_plancuenta_id' => 20, 'cuenta_debito' => 0, 'cuenta_credito' => 20, 'fk_moneda_id' => 'ARS', 'cotizacion_moneda' => 1, 'monto' => 300, 'fecha' => '2026-08-11'],
            // Ventas: crédito 1 USD × 900 => -900 => acreedor 900; raíz 'ganancia' con saldo negativo cruza a PÉRDIDA (regla del CI).
            ['movimiento_id' => 3, 'fk_ordenadmin_id' => 0, 'fk_plancuenta_id' => 40, 'cuenta_debito' => 0, 'cuenta_credito' => 40, 'fk_moneda_id' => 'USD', 'cotizacion_moneda' => 900, 'monto' => 1, 'fecha' => '2026-08-12'],
            // Orden tipo X del mismo año que "hasta": excluida.
            ['movimiento_id' => 4, 'fk_ordenadmin_id' => 400, 'fk_plancuenta_id' => 10, 'cuenta_debito' => 0, 'cuenta_credito' => 0, 'fk_moneda_id' => 'ARS', 'cotizacion_moneda' => 1, 'monto' => 5555, 'fecha' => '2026-08-12'],
        ]);

        $this->get('/app/contabilidad/balance-8')->assertOk()->assertInertia(fn (Assert $p) => $p->has('grupos', 0));
        $this->get('/app/contabilidad/balance-8?fecha=2026-08-01&fecha_to=2026-08-31')->assertOk()->assertInertia(fn (Assert $p) => $p
            ->has('grupos.0.filas', 9)
            ->where('grupos.0.filas.0.nombre', 'ACTIVO')->where('grupos.0.filas.0.debe', null)
            ->where('grupos.0.filas.1.nombre', '  Caja')->where('grupos.0.filas.1.debe', 1000)->where('grupos.0.filas.1.deudor', 1000)->where('grupos.0.filas.1.activo', 1000)
            ->where('grupos.0.filas.3.nombre', '  Proveedores')->where('grupos.0.filas.3.haber', 300)->where('grupos.0.filas.3.acreedor', 300)->where('grupos.0.filas.3.pasivo', 300)
            ->where('grupos.0.filas.5.nombre', '  Ventas')->where('grupos.0.filas.5.haber', 900)->where('grupos.0.filas.5.perdida', 900)->where('grupos.0.filas.5.ganancia', null)
            ->where('grupos.0.filas.7.nombre', 'SUMAS')->where('grupos.0.filas.7.debe', 1000)->where('grupos.0.filas.7.haber', 1200)->where('grupos.0.filas.7.activo', 1000)->where('grupos.0.filas.7.pasivo', 300)->where('grupos.0.filas.7.perdida', 900)
            ->where('grupos.0.filas.8.nombre', 'RESULTADO')->where('grupos.0.filas.8.activo', 700)->where('grupos.0.filas.8.pasivo', null)->where('grupos.0.filas.8.ganancia', 900)->where('grupos.0.filas.8.perdida', null)
        );
    }

    public function test_estado_de_cuenta_del_cliente_con_documentos_recibos_e_hijos(): void
    {
        DB::table('cliente')->insert([['cliente_id' => 3, 'cliente_nombre' => 'SOL', 'cliente_razonsocial' => 'Sol', 'cuit' => '1'], ['cliente_id' => 4, 'cliente_nombre' => 'OTRO', 'cliente_razonsocial' => 'Otro', 'cuit' => '2']]);
        DB::table('reserva')->insert([
            ['reserva_id' => 21, 'tipocodigo' => 'RE', 'codigo' => '100', 'fk_cliente_id' => 3, 'fk_usuario_id' => 7, 'agente' => 7, 'fk_sistema_id' => 1, 'fk_filestatus_id' => 'CO', 'fecha_alta' => '2026-08-01', 'fk_moneda_id' => 'USD', 'total' => 500, 'cobrado' => 200, 'titular_nombre' => 'Ana', 'titular_apellido' => 'Pérez', 'fk_filepadre_id' => 0],
            ['reserva_id' => 22, 'tipocodigo' => 'DV', 'codigo' => '101', 'fk_cliente_id' => 3, 'fk_usuario_id' => 7, 'agente' => 7, 'fk_sistema_id' => 1, 'fk_filestatus_id' => 'DV', 'fecha_alta' => '2026-08-02', 'fk_moneda_id' => 'USD', 'total' => -50, 'cobrado' => 0, 'titular_nombre' => 'Ana', 'titular_apellido' => 'Pérez', 'fk_filepadre_id' => 21],
            ['reserva_id' => 23, 'tipocodigo' => 'RE', 'codigo' => '102', 'fk_cliente_id' => 4, 'fk_usuario_id' => 7, 'agente' => 7, 'fk_sistema_id' => 1, 'fk_filestatus_id' => 'CO', 'fecha_alta' => '2026-08-03', 'fk_moneda_id' => 'USD', 'total' => 9, 'cobrado' => 0, 'titular_nombre' => 'X', 'titular_apellido' => 'Y', 'fk_filepadre_id' => 0],
        ]);
        foreach ([21 => 11, 22 => 12, 23 => 13] as $rid => $sid) {
            DB::table('servicio')->insert(['servicio_id' => $sid, 'servicio_nombre' => "S{$sid}", 'fk_reserva_id' => $rid, 'fk_proveedor_id' => 5, 'fk_tipoproducto_id' => 'HTL', 'status' => 'CO', 'vigencia_ini' => '2026-12-01', 'adultos' => 2]);
        }
        DB::table('servicio_nomina')->insert([['fk_servicio_id' => 11, 'nombre' => 'ANA', 'apellido' => 'PEREZ'], ['fk_servicio_id' => 11, 'nombre' => 'LUIS', 'apellido' => 'PEREZ']]);
        DB::table('factura')->insert(['factura_id' => 40, 'factura_nro' => '40', 'statusfactura' => 'EM', 'factura_fecha' => '2026-08-11 10:00:00', 'factura_tipo' => 'A', 'fk_cliente_id' => 3, 'fk_file_id' => 21, 'fk_moneda_id' => 'USD']);
        DB::table('notacredito')->insert(['notacredito_id' => 50, 'notacredito_nro' => '50', 'statusfactura' => 'EM', 'notacredito_fecha' => '2026-08-12 09:00:00', 'notacredito_tipo' => 'A', 'fk_cliente_id' => 3, 'fk_factura_id' => 40, 'fk_file_id' => 21, 'fk_moneda_id' => 'USD']);
        DB::table('recibo')->insert(['recibo_id' => 70, 'recibo_tipo' => 'RC', 'recibo_nro' => 'R-70', 'fecha' => '2026-08-10', 'fk_cliente_id' => 3, 'fk_usuario_id' => 7, 'statusrecibo' => 'EM', 'monto' => 200, 'fk_moneda_id' => 'USD']);
        DB::table('rel_filerecibo')->insert(['fk_file_id' => 21, 'fk_recibo_id' => 70]);

        // POW elige cliente.
        $this->get('/app/cuentas/estado')->assertOk()->assertInertia(fn (Assert $p) => $p->where('config.consultado', false)->where('config.filtros.0.campo', 'cliente'));
        $this->get('/app/cuentas/estado?cliente=3')->assertOk()->assertInertia(fn (Assert $p) => $p
            ->has('grupos.0.filas', 2)
            ->where('grupos.0.filas.0.codigo', 'DV-101')
            ->where('grupos.0.filas.1.codigo', 'RE-100')
            ->where('grupos.0.filas.1.saldo', 300)
            ->where('grupos.0.filas.1.pasajeros', "ANA PEREZ\nLUIS PEREZ")
            ->where('grupos.0.filas.1.facturas', "A 40\nNC A 50")
            ->where('grupos.0.filas.1.recibos', 'R-70')
            ->where('grupos.0.filas.1.hijos', 'DV-101')
            ->where('grupos.0.filas.1.agente', 'Ana Pow')
            ->where('grupos.0.totales.saldo', 250)
        );
        $this->get('/app/cuentas/estado?cliente=3&reportesaldo=1')->assertOk()->assertInertia(fn (Assert $p) => $p->has('grupos.0.filas', 1));

        // Usuario cliente: cliente forzado, sin filtro de cliente y consulta directa.
        DB::table('usuario')->where('usuario_id', 7)->update(['fk_tipousuario_id' => 'CLI', 'fk_cliente_id' => 4]);
        auth()->user()->refresh();
        $this->get('/app/cuentas/estado')->assertOk()->assertInertia(fn (Assert $p) => $p
            ->where('config.filtros.0.campo', 'codigo')
            ->has('grupos.0.filas', 1)
            ->where('grupos.0.filas.0.codigo', 'RE-102'));
    }
}
