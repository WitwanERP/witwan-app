<?php

namespace Tests\Feature\Reservas;

use App\Models\User;
use App\Services\Reservas\MapaContable\MapaContableFileService;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\CreaEsquemaConfiguracion;
use Tests\TestCase;

/**
 * Mapa contable de un file sobre el esquema legacy real: un file en dólares con
 * factura, recibo, factura de proveedor, OS, OP y sus asientos, a cotizaciones
 * distintas en cada paso. Los importes esperados están hechos a mano en
 * EconomiaFileTest; acá se verifica que la carga los arme igual desde la base.
 */
class MapaContableTest extends TestCase
{
    use CreaEsquemaConfiguracion;

    protected function setUp(): void
    {
        parent::setUp();
        $this->crearEsquemaConfiguracion();
        $this->autenticarPow();

        DB::table('sysconfig')->insert([
            ['sysconfig_key' => 'mapa_contable_file', 'sysconfig_value' => '1'],
            ['sysconfig_key' => 'tasageneral', 'sysconfig_value' => '21'],
            ['sysconfig_key' => 'cuentarecibos', 'sysconfig_value' => '54'],
            ['sysconfig_key' => 'ventarentaa', 'sysconfig_value' => '300'],
        ]);
        DB::table('moneda')->insert([
            ['moneda_id' => 'ARS', 'moneda_nombre' => 'Peso', 'moneda_basica' => 'Y', 'orden' => 1],
            ['moneda_id' => 'USD', 'moneda_nombre' => 'Dólar', 'moneda_basica' => 'N', 'orden' => 2],
        ]);
        DB::table('cotizacion')->insert(['cotizacion_moneda' => 'USD', 'cotizacion_fecha' => '2025-01-01', 'cotizacion_relacion' => 1000, 'cotizacion_costo' => 990]);
        DB::table('plancuenta')->insert([
            ['plancuenta_id' => 1, 'plancuenta_codigo' => '1111', 'plancuenta_nombre' => 'CAJA USD'],
            ['plancuenta_id' => 54, 'plancuenta_codigo' => '1131', 'plancuenta_nombre' => 'DEUDORES POR VENTAS'],
            ['plancuenta_id' => 117, 'plancuenta_codigo' => '2111', 'plancuenta_nombre' => 'PROVEEDORES'],
            ['plancuenta_id' => 300, 'plancuenta_codigo' => '4101', 'plancuenta_nombre' => 'RENTA AGENCIA'],
            ['plancuenta_id' => 310, 'plancuenta_codigo' => '2121', 'plancuenta_nombre' => 'COSTO SERVICIOS A PAGAR'],
        ]);
        DB::table('cliente')->insert(['cliente_id' => 3, 'cliente_nombre' => 'Agencia Sol']);
        DB::table('proveedor')->insert(['proveedor_id' => 9, 'proveedor_nombre' => 'Hotel Sur']);

        DB::table('reserva')->insert([
            ['reserva_id' => 21, 'tipocodigo' => 'RE', 'codigo' => '100', 'fk_cliente_id' => 3, 'fecha_alta' => '2025-03-01', 'fk_moneda_id' => 'USD', 'fk_filestatus_id' => 'OK', 'titular_apellido' => 'Pérez', 'titular_nombre' => 'Ana'],
            ['reserva_id' => 22, 'tipocodigo' => 'RE', 'codigo' => '101', 'fk_cliente_id' => 3, 'fecha_alta' => '2025-03-01', 'fk_moneda_id' => 'USD', 'fk_filestatus_id' => 'OK', 'titular_apellido' => 'Gómez', 'titular_nombre' => 'Luis'],
        ]);
        DB::table('servicio')->insert([
            // Facturado, con factura de proveedor y pagado.
            ['servicio_id' => 501, 'fk_reserva_id' => 21, 'servicio_nombre' => 'Hotel Sur 3 noches', 'fk_tipoproducto_id' => 'HOT', 'fk_proveedor_id' => 9, 'status' => 'OK',
                'fk_moneda_id' => 'USD', 'moneda_costo' => 'USD', 'total' => 1100, 'iva' => 100, 'costo' => 800, 'iva_costo' => 0, 'impuestos' => 0,
                'cotventa' => 1000, 'cotcosto' => 1000, 'facturado' => 1],
            // Sin cotización propia (cae a la tabla), marcado facturado pero sin factura.
            ['servicio_id' => 502, 'fk_reserva_id' => 21, 'servicio_nombre' => 'Excursión', 'fk_tipoproducto_id' => 'EXC', 'fk_proveedor_id' => 9, 'status' => 'OK',
                'fk_moneda_id' => 'USD', 'moneda_costo' => 'USD', 'total' => 200, 'iva' => 0, 'costo' => 150, 'iva_costo' => 0, 'impuestos' => 0,
                'cotventa' => 0, 'cotcosto' => 0, 'facturado' => 1],
        ]);

        // Factura USD 1.100 a 1.050, con renta congelada de USD 180 y asentada con renta USD 200.
        DB::table('factura')->insert(['factura_id' => 40, 'factura_tipo' => 'E', 'factura_nro' => '0003-00000040', 'statusfactura' => 'OK', 'factura_fecha' => '2025-03-10 10:00:00',
            'fk_file_id' => 21, 'fk_cliente_id' => 3, 'fk_moneda_id' => 'USD', 'factura_tipo_cambio' => 1050, 'factura_conceptos_exentos' => 1100, 'factura_total' => 1100]);
        DB::table('rel_filefactura')->insert(['fk_file_id' => 21, 'fk_factura_id' => 40]);
        DB::table('rel_serviciofactura')->insert(['fk_servicio_id' => 501, 'fk_factura_id' => 40, 'tipodocumento' => 1]);
        DB::table('serviciofactura')->insert(['fk_servicio_id' => 501, 'fk_factura_id' => 40, 'tipodocumento' => 1, 'renta' => 180, 'activo' => 1]);

        // Recibo USD 1.100 cobrado a 1.100.
        DB::table('recibo')->insert(['recibo_id' => 70, 'recibo_nro' => 'R-70', 'fecha' => '2025-03-20', 'fk_cliente_id' => 3, 'fk_moneda_id' => 'USD', 'monto' => 1100, 'statusrecibo' => 'OK']);
        DB::table('rel_filerecibo')->insert(['fk_file_id' => 21, 'fk_recibo_id' => 70, 'fecha' => '2025-03-20', 'fk_moneda_id' => 'USD', 'monto' => 1100]);

        // Factura de proveedor USD 800 a 1.020; OS a 1.070 y OP a 1.080.
        DB::table('facturaproveedor')->insert(['facturaproveedor_id' => 80, 'facturaproveedor_nro' => '0001-00000080', 'facturaproveedor_tipodocumento' => 'Factura', 'facturaproveedor_tipofactura' => 'A',
            'fk_proveedor_id' => 9, 'fecha' => '2025-03-15', 'fk_moneda_id' => 'USD', 'cotizacion' => 1020, 'montototal' => 800]);
        DB::table('rel_facturaproveedorocupacion')->insert(['fk_facturaproveedor_id' => 80, 'fk_ocupacion_id' => 501, 'monto' => 800]);
        DB::table('ordenadmin')->insert([
            ['ordenadmin_id' => 90, 'fk_ordenadmin_id' => 91, 'tipo' => 'S', 'status' => 'PR', 'nroservicio' => 'OS-90', 'fecha' => '2025-03-16', 'fk_moneda_id' => 'USD', 'cotizacion' => 1070, 'fk_proveedor_id' => 9],
            ['ordenadmin_id' => 91, 'fk_ordenadmin_id' => 0, 'tipo' => 'P', 'status' => 'OK', 'nropago' => '91', 'fecha' => '2025-03-25', 'fk_moneda_id' => 'USD', 'cotizacion' => 1080, 'fk_proveedor_id' => 9],
        ]);
        DB::table('rel_ordenadminocupacion')->insert([
            ['fk_ordenadmin_id' => 90, 'fk_ocupacion_id' => 501, 'fk_moneda_id' => 'USD', 'monto' => 800, 'status' => 'A'],
            ['fk_ordenadmin_id' => 91, 'fk_ocupacion_id' => 501, 'fk_moneda_id' => 'USD', 'monto' => 800, 'status' => 'A'],
        ]);

        // Inserts multi-fila: todas las filas con las mismas claves.
        $mov = fn (array $m) => array_merge(['fk_file_id' => 0, 'fk_moneda_id' => 'USD', 'fecha' => '2025-03-10', 'cuenta_debito' => 0, 'cuenta_credito' => 0,
            'fk_factura_id' => 0, 'fk_recibo_id' => 0, 'fk_facturaproveedor_id' => 0, 'fk_ordenadmin_id' => 0, 'fk_proveedor_id' => 0], $m);
        DB::table('movimiento')->insert([
            // Factura 40
            $mov(['fk_factura_id' => 40, 'fk_file_id' => 21, 'fk_plancuenta_id' => 54, 'cuenta_debito' => 54, 'deha' => 'D', 'monto' => 1100, 'cotizacion_moneda' => 1050]),
            $mov(['fk_factura_id' => 40, 'fk_file_id' => 21, 'fk_plancuenta_id' => 310, 'cuenta_credito' => 310, 'deha' => 'H', 'monto' => 900, 'cotizacion_moneda' => 1050]),
            $mov(['fk_factura_id' => 40, 'fk_file_id' => 21, 'fk_plancuenta_id' => 300, 'cuenta_credito' => 300, 'deha' => 'H', 'monto' => 200, 'cotizacion_moneda' => 1050]),
            // Recibo 70
            $mov(['fk_recibo_id' => 70, 'fk_file_id' => 21, 'fk_plancuenta_id' => 54, 'cuenta_debito' => 54, 'deha' => 'H', 'monto' => 1100, 'cotizacion_moneda' => 1100, 'fecha' => '2025-03-20']),
            $mov(['fk_recibo_id' => 70, 'fk_file_id' => 21, 'fk_plancuenta_id' => 1, 'cuenta_debito' => 1, 'deha' => 'D', 'monto' => 1100, 'cotizacion_moneda' => 1100, 'fecha' => '2025-03-20']),
            // Factura de proveedor 80
            $mov(['fk_facturaproveedor_id' => 80, 'fk_plancuenta_id' => 310, 'cuenta_debito' => 310, 'deha' => 'D', 'monto' => 800, 'cotizacion_moneda' => 1020, 'fecha' => '2025-03-15']),
            $mov(['fk_facturaproveedor_id' => 80, 'fk_plancuenta_id' => 117, 'cuenta_credito' => 117, 'deha' => 'H', 'monto' => 800, 'cotizacion_moneda' => 1020, 'fecha' => '2025-03-15']),
            // OP 91
            $mov(['fk_ordenadmin_id' => 91, 'fk_proveedor_id' => 9, 'fk_plancuenta_id' => 117, 'cuenta_debito' => 117, 'deha' => 'D', 'monto' => 800, 'cotizacion_moneda' => 1080, 'fecha' => '2025-03-25']),
            $mov(['fk_ordenadmin_id' => 91, 'fk_proveedor_id' => 9, 'fk_plancuenta_id' => 1, 'cuenta_credito' => 1, 'deha' => 'H', 'monto' => 800, 'cotizacion_moneda' => 1080, 'fecha' => '2025-03-25']),
        ]);
    }

    private function mapa(int $id = 21): array
    {
        return app(MapaContableFileService::class)->armar($id);
    }

    private function codigos(array $mapa): array
    {
        return array_column($mapa['desvios'], 'codigo');
    }

    public function test_arma_los_dos_circuitos_y_la_renta_real(): void
    {
        $m = $this->mapa();
        $t = $m['economia']['totales'];

        $this->assertSame('RE-100', $m['file']['codigo']);
        $this->assertCount(1, $m['venta']['facturas']);
        $this->assertSame(['fk_file_id', 'rel_filefactura', 'servicio'], $m['venta']['facturas'][0]['caminos']);
        $this->assertSame(1100.0, $m['venta']['facturas'][0]['total']);
        $this->assertSame(1100.0, $m['venta']['recibos'][0]['tasa']);
        $this->assertCount(2, $m['costo']['ordenes']);

        // Servicio 501: igual que el escenario de EconomiaFileTest. Servicio 502: renta 50.000 a la tabla.
        $this->assertSame(250000.0, $t['renta_presupuestada']);
        $this->assertSame(50000.0, $t['dc_servicio_factura']);
        $this->assertSame(55000.0, $t['dc_factura_cobranza']);
        $this->assertSame(-16000.0, $t['dc_servicio_fc3']);
        $this->assertSame(-48000.0, $t['dc_fc3_pago']);
        $this->assertSame(0.0, $t['dif_precio_venta']);
        $this->assertSame(291000.0, $t['renta_real']);
        $this->assertSame(180000.0, $t['renta_congelada']);
        $this->assertSame(1210000.0, $t['cobrado']);
        $this->assertSame(864000.0, $t['pagado']);

        // Contabilidad: renta USD 200 a 1.050; todos los asientos balancean.
        $this->assertSame(210000.0, $m['contable']['renta']);
        $this->assertCount(4, $m['contable']['grupos']);
        foreach ($m['contable']['grupos'] as $g) {
            $this->assertSame(0.0, $g['diferencia'], $g['etiqueta']);
        }
    }

    public function test_detecta_los_desvios_del_file(): void
    {
        $c = $this->codigos($this->mapa());

        foreach (['SRV_SIN_FACTURA', 'SRV_MARCADO_FACTURADO', 'RENTA_CONGELADA_DISTINTA', 'RENTA_CONTABLE_DISTINTA',
            'DIF_CAMBIO_NO_ASENTADA', 'OP_COTIZACION_OS', 'COTIZACION_INFERIDA', 'SALDO_PROVEEDOR'] as $esperado) {
            $this->assertContains($esperado, $c);
        }
        foreach (['COMPROBANTE_SIN_ASIENTO', 'ASIENTO_DESBALANCEADO', 'FACTURA_FUERA_DE_FICHA', 'PAGADO_DE_MAS', 'SALDO_CLIENTE', 'DIF_PRECIO_VENTA'] as $ausente) {
            $this->assertNotContains($ausente, $c);
        }
        $contable = collect($this->mapa()['desvios'])->firstWhere('codigo', 'RENTA_CONTABLE_DISTINTA');
        $this->assertSame(30000.0, $contable['importe']);
    }

    public function test_factura_compartida_y_fuera_de_la_ficha(): void
    {
        // Factura de dos files, vinculada sólo por servicios (como la emite la librería Facturar), sin asiento.
        DB::table('servicio')->insert(['servicio_id' => 601, 'fk_reserva_id' => 22, 'servicio_nombre' => 'Hotel otro file', 'status' => 'OK',
            'fk_moneda_id' => 'USD', 'moneda_costo' => 'USD', 'total' => 200, 'cotventa' => 1000, 'cotcosto' => 1000]);
        DB::table('factura')->insert(['factura_id' => 41, 'factura_tipo' => 'E', 'factura_nro' => '0003-00000041', 'statusfactura' => 'OK', 'factura_fecha' => '2025-03-12 10:00:00',
            'fk_file_id' => 0, 'fk_moneda_id' => 'USD', 'factura_tipo_cambio' => 1000, 'factura_conceptos_exentos' => 400]);
        DB::table('rel_serviciofactura')->insert([
            ['fk_servicio_id' => 502, 'fk_factura_id' => 41, 'tipodocumento' => 1],
            ['fk_servicio_id' => 601, 'fk_factura_id' => 41, 'tipodocumento' => 1],
        ]);

        $m = $this->mapa();
        $f = collect($m['venta']['facturas'])->firstWhere('id', 41);
        $c = $this->codigos($m);

        $this->assertSame(['servicio'], $f['caminos']);
        $this->assertSame(0.5, $f['share']);
        $this->assertSame([601], $f['servicios_ajenos']);
        $this->assertContains('FACTURA_FUERA_DE_FICHA', $c);
        $this->assertContains('COMPROBANTES_COMPARTIDOS', $c);
        $this->assertContains('COMPROBANTE_SIN_ASIENTO', $c);
        $this->assertNotContains('SRV_SIN_FACTURA', $c);
        // La mitad de la factura (USD 200) contra el servicio 502 (USD 200): sin diferencia de precio.
        $this->assertSame(0.0, $m['economia']['venta']['diferencia_bruta']);
    }

    public function test_nota_de_credito_del_modulo_sin_file_y_nota_de_debito(): void
    {
        // NC parcial desde notacredito.php: graba fk_factura_id pero no el file ni el total.
        DB::table('notacredito')->insert(['notacredito_id' => 60, 'notacredito_tipo' => 'E', 'notacredito_nro' => '0003-00000060', 'statusfactura' => 'OK',
            'notacredito_fecha' => '2025-03-18 10:00:00', 'fk_factura_id' => 40, 'fk_file_id' => 0, 'fk_moneda_id' => 'USD',
            'notacredito_tipo_cambio' => 1060, 'notacredito_conceptos_exentos' => 110]);
        DB::table('notadebito')->insert(['notadebito_id' => 61, 'notadebito_tipo' => 'E', 'notadebito_nro' => '0003-00000061', 'statusfactura' => 'OK',
            'notadebito_fecha' => '2025-03-19 10:00:00', 'fk_notacredito_id' => 60, 'fk_moneda_id' => 'USD',
            'notadebito_tipo_cambio' => 1060, 'notadebito_conceptos_exentos' => 110]);

        $m = $this->mapa();
        $c = $this->codigos($m);

        $this->assertSame(110.0, $m['venta']['notas_credito'][0]['total']);
        $this->assertSame(['factura'], $m['venta']['notas_credito'][0]['caminos']);
        $this->assertCount(1, $m['venta']['notas_debito']);
        $this->assertContains('NC_SIN_FILE', $c);
        $this->assertContains('COMPROBANTE_SIN_ASIENTO', $c);
        // La ND revierte la NC: sin diferencia de precio de venta.
        $this->assertSame(0.0, $m['economia']['totales']['dif_precio_venta']);
    }

    public function test_recibo_imputado_dos_veces_cuenta_una(): void
    {
        DB::table('rel_filerecibo')->insert(['fk_file_id' => 21, 'fk_recibo_id' => 70, 'fecha' => '2025-03-20', 'fk_moneda_id' => 'USD', 'monto' => 1100]);

        $m = $this->mapa();

        $this->assertCount(1, $m['venta']['recibos']);
        $this->assertSame(1210000.0, $m['economia']['totales']['cobrado']);
        $this->assertContains('RECIBO_IMPUTADO_DOS_VECES', $this->codigos($m));
    }

    public function test_pagina_detras_del_flag_y_no_para_clientes(): void
    {
        $this->get('/app/reservas/mapa-contable/21')->assertOk()->assertInertia(fn (Assert $p) => $p
            ->component('Reservas/MapaContable')
            ->where('mapa.file.codigo', 'RE-100')
            ->where('mapa.economia.totales.renta_real', 291000)
            ->where('embed', false));
        $this->get('/app/reservas/mapa-contable/999')->assertNotFound();

        DB::table('tipousuario')->insert(['tipousuario_id' => 'CLI', 'tipousuario_nombre' => 'Cliente']);
        DB::table('usuario')->insert(['usuario_id' => 8, 'usuario_nombre' => 'Cli', 'fk_tipousuario_id' => 'CLI']);
        $this->actingAs(User::find(8), 'web')->get('/app/reservas/mapa-contable/21')->assertForbidden();
    }

    public function test_flag_apagado_responde_404(): void
    {
        DB::table('sysconfig')->where('sysconfig_key', 'mapa_contable_file')->update(['sysconfig_value' => '0']);
        \Illuminate\Support\Facades\Cache::flush();

        $this->get('/app/reservas/mapa-contable/21')->assertNotFound();
    }
}
