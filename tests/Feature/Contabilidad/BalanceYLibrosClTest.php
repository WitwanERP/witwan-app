<?php

namespace Tests\Feature\Contabilidad;

use App\Helpers\SysconfigHelper;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\CreaEsquemaConfiguracion;
use Tests\TestCase;

class BalanceYLibrosClTest extends TestCase
{
    use CreaEsquemaConfiguracion;

    protected function setUp(): void
    {
        parent::setUp();
        $this->crearEsquemaConfiguracion();
        $this->autenticarPow();
        SysconfigHelper::olvidar('tasageneral');
        DB::table('sysconfig')->insert(['sysconfig_key' => 'tasageneral', 'sysconfig_value' => '19']);
        DB::table('moneda')->insert([['moneda_id' => 'CLP', 'moneda_nombre' => 'Peso', 'moneda_basica' => 'Y'], ['moneda_id' => 'USD', 'moneda_nombre' => 'Dólar', 'moneda_basica' => 'N']]);
        DB::table('plancuenta')->insert([
            ['plancuenta_id' => 1, 'plancuenta_nombre' => 'ACTIVO', 'plancuenta_codigo' => '1', 'fk_plancuenta_id' => 0, 'plancuenta_titulo' => 1],
            ['plancuenta_id' => 10, 'plancuenta_nombre' => 'Caja', 'plancuenta_codigo' => '1.1', 'fk_plancuenta_id' => 1, 'plancuenta_titulo' => 0],
            ['plancuenta_id' => 20, 'plancuenta_nombre' => 'Banco', 'plancuenta_codigo' => '1.2', 'fk_plancuenta_id' => 1, 'plancuenta_titulo' => 0],
        ]);
        DB::table('ordenadmin')->insert(['ordenadmin_id' => 400, 'fk_ordenadmin_id' => 0, 'fk_proveedor_id' => 5, 'fk_usuario_id' => 7, 'fecha' => '2026-08-12', 'nroservicio' => '', 'nropago' => 'OP-1', 'tipo' => 'P', 'fk_moneda_id' => 'CLP', 'cotizacion' => 1, 'monto' => 50, 'status' => 'OK']);
        // Caja: +1000 en 2020 (anterior), +300 en agosto (debe), orden de 50 sin cuentas débito/crédito: en el balance del CI el signo
        // (−1 por no tener cuentas) × (−1 por ser orden) la deja en el DEBE (el mayor la muestra en el haber: así es el legacy). Banco: 100 USD × 900 = 90000 debe.
        DB::table('movimiento')->insert([
            ['movimiento_id' => 1, 'fk_recibo_id' => 0, 'fk_ordenadmin_id' => 0, 'fk_plancuenta_id' => 10, 'cuenta_debito' => 10, 'cuenta_credito' => 0, 'fk_moneda_id' => 'CLP', 'cotizacion_moneda' => 1, 'monto' => 1000, 'fecha' => '2020-05-05'],
            ['movimiento_id' => 2, 'fk_recibo_id' => 0, 'fk_ordenadmin_id' => 0, 'fk_plancuenta_id' => 10, 'cuenta_debito' => 54, 'cuenta_credito' => 0, 'fk_moneda_id' => 'CLP', 'cotizacion_moneda' => 1, 'monto' => 300, 'fecha' => '2026-08-10'],
            ['movimiento_id' => 3, 'fk_recibo_id' => 0, 'fk_ordenadmin_id' => 400, 'fk_plancuenta_id' => 10, 'cuenta_debito' => 0, 'cuenta_credito' => 0, 'fk_moneda_id' => 'CLP', 'cotizacion_moneda' => 1, 'monto' => 50, 'fecha' => '2026-08-12'],
            ['movimiento_id' => 4, 'fk_recibo_id' => 0, 'fk_ordenadmin_id' => 0, 'fk_plancuenta_id' => 0, 'cuenta_debito' => 20, 'cuenta_credito' => 0, 'fk_moneda_id' => 'USD', 'cotizacion_moneda' => 900, 'monto' => 100, 'fecha' => '2026-08-15'],
        ]);
    }

    public function test_balance_suma_por_cuenta_acumula_anterior_y_totaliza_en_la_padre(): void
    {
        $this->get('/app/contabilidad/balance')->assertOk()->assertInertia(fn (Assert $p) => $p->has('grupos', 0));
        $this->get('/app/contabilidad/balance?fecha=2026-08-01&fecha_to=2026-08-31')->assertOk()->assertInertia(fn (Assert $p) => $p
            ->has('grupos.0.filas', 3)
            ->where('grupos.0.filas.1.nombre', 'Caja')
            ->where('grupos.0.filas.1.debe', 350)
            ->where('grupos.0.filas.1.haber', 0)
            ->where('grupos.0.filas.1.anterior', 1000)
            ->where('grupos.0.filas.1.saldo', 1350)
            ->where('grupos.0.filas.2.nombre', 'Banco')
            ->where('grupos.0.filas.2.debe', 90000)
            ->where('grupos.0.filas.0.nombre', '▸ ACTIVO')
            ->where('grupos.0.filas.0.debe', 90350)
            ->where('grupos.0.filas.0.saldo', 91350)
        );
        $this->get('/app/contabilidad/balance?fecha=2026-08-01&fecha_to=2026-08-31&acumulado=2026-01-01&ocultar_ceros=1')->assertOk()->assertInertia(fn (Assert $p) => $p
            ->where('grupos.0.filas.1.anterior', 0)->where('grupos.0.filas.1.saldo', 350));
    }

    public function test_libro_de_ventas_cl_con_facturas_nc_y_nd(): void
    {
        DB::table('cliente')->insert(['cliente_id' => 3, 'cliente_nombre' => 'SOL', 'cliente_razonsocial' => 'Sol SpA', 'cuit' => '76.111.111-1']);
        DB::table('factura')->insert([
            ['factura_id' => 40, 'factura_nro' => '40', 'statusfactura' => 'EM', 'factura_fecha' => '2026-08-11 10:00:00', 'factura_tipo' => 'A', 'factura_conceptos_gravados' => 1000, 'factura_conceptos_exentos' => 500, 'fk_cliente_id' => 3, 'fk_file_id' => 21, 'fk_moneda_id' => 'CLP', 'factura_sucursal' => '1'],
            ['factura_id' => 41, 'factura_nro' => '41', 'statusfactura' => 'NU', 'factura_fecha' => '2026-08-11 10:00:00', 'factura_tipo' => 'A', 'factura_conceptos_gravados' => 1, 'factura_conceptos_exentos' => 0, 'fk_cliente_id' => 3, 'fk_file_id' => 21, 'fk_moneda_id' => 'CLP', 'factura_sucursal' => '1'],
        ]);
        DB::table('notacredito')->insert(['notacredito_id' => 50, 'notacredito_nro' => '50', 'statusfactura' => 'EM', 'notacredito_fecha' => '2026-08-12 09:00:00', 'notacredito_tipo' => 'A', 'notacredito_conceptos_gravados' => 100, 'notacredito_conceptos_exentos' => 0, 'fk_cliente_id' => 3, 'fk_factura_id' => 40, 'fk_moneda_id' => 'CLP', 'remitofull' => '{"referencia":{"referenciatipo":"33","referencianro":"40","referenciafecha":"2026-08-11"}}']);
        DB::table('notadebito')->insert(['notadebito_id' => 60, 'notadebito_nro' => '60', 'statusfactura' => 'EM', 'notadebito_fecha' => '2026-08-13 09:00:00', 'notadebito_tipo' => 'A', 'notadebito_conceptos_gravados' => 200, 'notadebito_conceptos_exentos' => 8, 'fk_cliente_id' => 3, 'fk_moneda_id' => 'CLP']);

        $this->get('/app/contabilidad/libro-ventas?fecha=2026-08-01&fecha_to=2026-08-31')->assertOk()->assertInertia(fn (Assert $p) => $p
            ->has('grupos.0.filas', 3)
            ->where('grupos.0.filas.0.tipo_doc', 33)->where('grupos.0.filas.0.iva', 190)->where('grupos.0.filas.0.total', 1690)->where('grupos.0.filas.0.rut', '76.111.111-1')
            ->where('grupos.0.filas.1.tipo_doc', 60)->where('grupos.0.filas.1.neto', -100)->where('grupos.0.filas.1.total', -119)->where('grupos.0.filas.1.folio_ref', '40')->where('grupos.0.filas.1.tipo_ref', '33')
            ->where('grupos.0.filas.2.tipo_doc', 56)->where('grupos.0.filas.2.total', 246)
            ->where('grupos.0.totales.total', 1817)
        );
    }

    public function test_libro_de_compras_cl_clasifica_documentos_y_transacciones(): void
    {
        DB::table('proveedor')->insert(['proveedor_id' => 5, 'proveedor_nombre' => 'Hotel', 'razonsocial' => 'Hotel SpA', 'cuit' => '77.2']);
        DB::table('facturaproveedor')->insert([
            ['facturaproveedor_id' => 1, 'facturaproveedor_nro' => 'F-1', 'facturaproveedor_tipodocumento' => 'Factura', 'fk_proveedor_id' => 5, 'fecha' => '2026-08-01', 'fechacontable' => '2026-08-02', 'montoexento' => 0, 'montogeneral' => 1000, 'montototal' => 1190, 'retencioniibb' => 0, 'tipomovimiento' => 'Servicios', 'electronica' => 'Y', 'facturaproveedor_tipofactura' => 'B'],
            ['facturaproveedor_id' => 2, 'facturaproveedor_nro' => 'NC-2', 'facturaproveedor_tipodocumento' => 'Nota de Credito', 'fk_proveedor_id' => 5, 'fecha' => '2026-08-03', 'fechacontable' => '2026-08-03', 'montoexento' => 0, 'montogeneral' => 100, 'montototal' => 119, 'retencioniibb' => 0, 'tipomovimiento' => 'Gasto', 'electronica' => 'N', 'facturaproveedor_tipofactura' => ''],
            ['facturaproveedor_id' => 3, 'facturaproveedor_nro' => 'F-3', 'facturaproveedor_tipodocumento' => 'Factura', 'fk_proveedor_id' => 5, 'fecha' => '2026-08-04', 'fechacontable' => '2026-08-04', 'montoexento' => 10, 'montogeneral' => 500, 'montototal' => 605, 'retencioniibb' => 0, 'tipomovimiento' => 'Activo Fijo', 'electronica' => 'N', 'facturaproveedor_tipofactura' => ''],
            ['facturaproveedor_id' => 4, 'facturaproveedor_nro' => 'F-4', 'facturaproveedor_tipodocumento' => 'Factura', 'fk_proveedor_id' => 5, 'fecha' => '2026-08-05', 'fechacontable' => '2026-08-05', 'montoexento' => 0, 'montogeneral' => 200, 'montototal' => 238, 'retencioniibb' => 0, 'tipomovimiento' => 'IVA no recuperable', 'electronica' => 'Y', 'facturaproveedor_tipofactura' => 'A'],
            ['facturaproveedor_id' => 5, 'facturaproveedor_nro' => 'R-5', 'facturaproveedor_tipodocumento' => 'Recibo', 'fk_proveedor_id' => 5, 'fecha' => '2026-08-05', 'fechacontable' => '2026-08-05', 'montoexento' => 0, 'montogeneral' => 1, 'montototal' => 1, 'retencioniibb' => 0, 'tipomovimiento' => 'Gasto', 'electronica' => 'N', 'facturaproveedor_tipofactura' => ''],
        ]);
        $this->get('/app/contabilidad/libro-compras?fecha=2026-08-01&fecha_to=2026-08-31')->assertOk()->assertInertia(fn (Assert $p) => $p
            ->has('grupos.0.filas', 4)
            ->where('grupos.0.filas.0.tipo_doc', 33)->where('grupos.0.filas.0.iva', 190)->where('grupos.0.filas.0.transaccion', 1)
            ->where('grupos.0.filas.1.tipo_doc', 55)->where('grupos.0.filas.1.neto', -100)->where('grupos.0.filas.1.total', -119)
            ->where('grupos.0.filas.2.tipo_doc', 30)->where('grupos.0.filas.2.transaccion', 4)->where('grupos.0.filas.2.activo_fijo', 510)->where('grupos.0.filas.2.iva_activo_fijo', 95)
            ->where('grupos.0.filas.3.tipo_doc', 34)->where('grupos.0.filas.3.transaccion', 6)->where('grupos.0.filas.3.iva', 0)->where('grupos.0.filas.3.iva_no_rec', 38)->where('grupos.0.filas.3.iva_uso_comun', 9)
        );
    }
}
