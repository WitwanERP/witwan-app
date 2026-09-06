<?php

namespace Tests\Feature\Reportes;

use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\CreaEsquemaConfiguracion;
use Tests\TestCase;

/**
 * Smoke HTTP de los reportes de Administración portados sobre el motor
 * genérico: canjes, gastos administrativos/bancarios, honorarios, provisión de
 * deuda, pagos, gastos por área y facturas impagas.
 */
class ReportesAdminTest extends TestCase
{
    use CreaEsquemaConfiguracion;

    protected function setUp(): void
    {
        parent::setUp();
        $this->crearEsquemaConfiguracion();
        $this->autenticarPow();

        DB::table('moneda')->insert([
            ['moneda_id' => 'ARS', 'moneda_nombre' => 'Peso', 'moneda_basica' => 'Y', 'orden' => 1],
            ['moneda_id' => 'USD', 'moneda_nombre' => 'Dólar', 'moneda_basica' => 'N', 'orden' => 2],
        ]);
        DB::table('cotizacion')->insert(['cotizacion_moneda' => 'USD', 'cotizacion_fecha' => '2020-01-01', 'cotizacion_relacion' => 1000, 'cotizacion_costo' => 990]);
        DB::table('cliente')->insert(['cliente_id' => 3, 'cliente_nombre' => 'Agencia Sol']);
        DB::table('proveedor')->insert([
            ['proveedor_id' => 5, 'proveedor_nombre' => 'Hotel Sol', 'razonsocial' => 'Sol SA', 'cuit' => '30-1', 'fk_cadenahotelera_id' => 2],
            ['proveedor_id' => 6, 'proveedor_nombre' => 'Guía Luis', 'razonsocial' => 'Luis', 'cuit' => '20-2', 'fk_cadenahotelera_id' => 0],
        ]);
        DB::table('sistema')->insert([
            ['sistema_id' => 1, 'sistema_nombre' => 'Receptivo', 'item_order' => 1],
            ['sistema_id' => 2, 'sistema_nombre' => 'Mayorista', 'item_order' => 2],
        ]);
        DB::table('reserva')->insert([
            ['reserva_id' => 21, 'tipocodigo' => 'RE', 'codigo' => '100', 'fk_cliente_id' => 3, 'agente' => 7, 'fk_usuario_id' => 7, 'fecha_alta' => '2025-03-10', 'titular_apellido' => 'Pérez', 'titular_nombre' => 'Ana', 'fk_moneda_id' => 'USD', 'total' => 1000, 'fk_filestatus_id' => 'CL'],
            ['reserva_id' => 22, 'tipocodigo' => 'RE', 'codigo' => '101', 'fk_cliente_id' => 3, 'agente' => 7, 'fk_usuario_id' => 7, 'fecha_alta' => '2025-03-20', 'titular_apellido' => 'López', 'titular_nombre' => 'Juan', 'fk_moneda_id' => 'ARS', 'total' => 500, 'fk_filestatus_id' => 'CO'],
        ]);
        foreach ([
            // canje del hotel, dentro de los últimos 30 días
            ['servicio_id' => 11, 'servicio_nombre' => 'Canje hotel', 'fk_reserva_id' => 21, 'fk_tipoproducto_id' => 'CAN', 'fk_proveedor_id' => 5, 'status' => 'CO', 'fk_moneda_id' => 'USD', 'moneda_costo' => 'USD', 'total' => 300, 'costo' => 200, 'iva_costo' => 42, 'vigencia_ini' => now()->subDays(5)->toDateString()],
            // canje viejo (fuera del default de 30 días)
            ['servicio_id' => 12, 'servicio_nombre' => 'Canje viejo', 'fk_reserva_id' => 22, 'fk_tipoproducto_id' => 'CAN', 'fk_proveedor_id' => 6, 'status' => 'CO', 'fk_moneda_id' => 'ARS', 'moneda_costo' => 'ARS', 'total' => 50, 'costo' => 10, 'iva_costo' => 0, 'vigencia_ini' => '2024-01-01'],
            // gasto administrativo (GAS) con extra2 cargado
            ['servicio_id' => 13, 'servicio_nombre' => 'Gasto adm', 'fk_reserva_id' => 21, 'fk_tipoproducto_id' => 'GAS', 'fk_proveedor_id' => 5, 'status' => 'CO', 'fk_moneda_id' => 'USD', 'moneda_costo' => 'USD', 'total' => 80, 'extra2' => 25, 'info' => '<submodulo>GAS</submodulo>', 'vigencia_ini' => '2025-04-01'],
            // servicio con orden de pago (para el reporte de pagos)
            ['servicio_id' => 14, 'servicio_nombre' => 'Hotel pagado', 'fk_reserva_id' => 22, 'fk_tipoproducto_id' => 'HOT', 'fk_proveedor_id' => 5, 'status' => 'CO', 'fk_moneda_id' => 'ARS', 'moneda_costo' => 'USD', 'total' => 500, 'costo' => 100, 'iva_costo' => 21, 'vigencia_ini' => '2025-05-01'],
        ] as $fila) {
            DB::table('servicio')->insert($fila);
        }
        DB::table('factura')->insert(['factura_id' => 40, 'factura_nro' => '0001-00000040', 'statusfactura' => 'EM', 'factura_fecha' => '2025-03-15 10:00:00', 'vencimiento_pago' => '2025-04-15 00:00:00', 'factura_conceptos_gravados' => 100, 'factura_conceptos_exentos' => 81, 'fk_cliente_id' => 3, 'fk_file_id' => 21]);
        DB::table('rel_filefactura')->insert(['fk_file_id' => 21, 'fk_factura_id' => 40]);
        DB::table('rel_facturarecibo')->insert(['fk_factura_id' => 40, 'fk_recibo_id' => 70, 'monto' => 50]);
        DB::table('recibo')->insert(['recibo_id' => 70, 'recibo_nro' => 'R-70', 'statusrecibo' => 'EM', 'monto' => 120, 'fk_moneda_id' => 'CLP']);
        DB::table('rel_filerecibo')->insert(['fk_file_id' => 21, 'fk_recibo_id' => 70]);
        DB::table('movimiento')->insert([
            ['movimiento_id' => 1, 'fk_recibo_id' => 70, 'fk_facturaproveedor_id' => 0, 'fk_moneda_id' => 'CLP', 'cotizacion_moneda' => 1, 'monto' => 120, 'fecha' => '2025-03-16'],
            ['movimiento_id' => 2, 'fk_recibo_id' => 0, 'fk_facturaproveedor_id' => 90, 'fk_moneda_id' => 'ARS', 'cotizacion_moneda' => 1, 'monto' => 1000, 'fecha' => '2025-03-18'],
        ]);
        DB::table('facturaproveedor')->insert([
            ['facturaproveedor_id' => 90, 'facturaproveedor_nro' => 'A-90', 'facturaproveedor_tipodocumento' => 'FC', 'fk_proveedor_id' => 6, 'fecha' => '2025-03-18', 'fechacontable' => '2025-03-18', 'fk_moneda_id' => 'ARS', 'montoexento' => 0, 'montogeneral' => 1000, 'retencioniibb' => 30, 'montototal' => 1210, 'cotizacion' => 1, 'tipomovimiento' => 'HONORARIOS', 'imputacion' => json_encode([1 => 60, 2 => 40])],
            ['facturaproveedor_id' => 91, 'facturaproveedor_nro' => 'B-91', 'facturaproveedor_tipodocumento' => 'FC', 'fk_proveedor_id' => 5, 'fecha' => '2025-03-19', 'fechacontable' => '2025-03-19', 'fk_moneda_id' => 'USD', 'montoexento' => 0, 'montogeneral' => 200, 'retencioniibb' => 0, 'montototal' => 242, 'cotizacion' => 1000, 'tipomovimiento' => 'HOTELERIA', 'imputacion' => json_encode([1 => 100])],
        ]);
        DB::table('rel_facturaproveedorocupacion')->insert([
            ['fk_facturaproveedor_id' => 91, 'fk_ocupacion_id' => 11, 'monto' => 242],
            ['fk_facturaproveedor_id' => 91, 'fk_ocupacion_id' => 14, 'monto' => 121],
        ]);
        DB::table('ordenadmin')->insert([
            ['ordenadmin_id' => 300, 'fk_ordenadmin_id' => 0, 'tipo' => 'P', 'cotizacion' => 950],
            ['ordenadmin_id' => 301, 'fk_ordenadmin_id' => 300, 'tipo' => 'M', 'cotizacion' => 950],
        ]);
        DB::table('rel_ordenadminocupacion')->insert(['fk_ordenadmin_id' => 300, 'fk_ocupacion_id' => 14, 'fk_moneda_id' => 'USD', 'monto' => 121]);
    }

    public function test_canjes_por_defecto_ultimos_30_dias_y_filtra_por_fecha(): void
    {
        $this->get('/app/admin/reportes/canjes')
            ->assertOk()
            ->assertInertia(fn (Assert $p) => $p->component('Reportes/Listado')
                ->where('config.consultado', true)
                ->has('grupos', 1)
                ->where('grupos.0.clave', 'USD')
                ->where('grupos.0.filas.0.proveedor', 'Hotel Sol')
                ->where('grupos.0.filas.0.codigo', 'RE-100')
                ->where('grupos.0.totales.total', 300));

        $this->get('/app/admin/reportes/canjes?vigencia_ini=2023-01-01&vigencia_ini_to=2024-12-31')
            ->assertInertia(fn (Assert $p) => $p->has('grupos', 1)->where('grupos.0.clave', 'ARS')->where('grupos.0.filas.0.servicio', 'Canje viejo'));

        $this->get('/app/admin/reportes/canjes?fk_cadenahotelera_id=2&vigencia_ini=2000-01-01')
            ->assertInertia(fn (Assert $p) => $p->has('grupos', 1)->where('grupos.0.clave', 'USD'));
    }

    public function test_gastos_administrativos_toma_extra2_cuando_esta_cargado(): void
    {
        $this->get('/app/admin/reportes/gastos-administrativos?fecha_alta=2025-01-01')
            ->assertOk()
            ->assertInertia(fn (Assert $p) => $p
                ->has('grupos', 1)
                ->where('grupos.0.filas.0.file', 'RE-100')
                ->where('grupos.0.filas.0.titular', 'Pérez, Ana')
                ->where('grupos.0.filas.0.valor', 25)
                ->where('grupos.0.filas.0.factura_nro', '0001-00000040'));

        $this->get('/app/admin/reportes/gastos-bancarios?fecha_alta=2025-01-01')
            ->assertOk()
            ->assertInertia(fn (Assert $p) => $p->where('grupos', []));
    }

    public function test_honorarios_lista_facturas_con_retencion(): void
    {
        $this->get('/app/admin/reportes/honorarios?fecha=2025-03-01&fecha_to=2025-03-31')
            ->assertOk()
            ->assertInertia(fn (Assert $p) => $p
                ->has('grupos', 1)
                ->where('grupos.0.clave', 'ARS')
                ->where('grupos.0.filas.0.nro', 'A-90')
                ->where('grupos.0.filas.0.proveedor', 'Luis')
                ->where('grupos.0.filas.0.retencion', 30)
                ->where('grupos.0.filas.0.total', 1210));
    }

    public function test_provision_de_deuda_convierte_con_cotizacion_de_venta_y_resta_facturado(): void
    {
        // El servicio 14 tiene orden de pago: queda afuera. El 11 (USD 242 de costo) se convierte a 1000.
        $this->get('/app/admin/reportes/provision-deuda?buscar=1')
            ->assertOk()
            ->assertInertia(fn (Assert $p) => $p
                ->has('grupos', 1)
                ->has('grupos.0.filas', 3)
                ->where('grupos.0.filas.0.codigo', 'RE-100')
                ->where('grupos.0.filas.0.costo', 242)
                ->where('grupos.0.filas.0.ars', 242000)
                ->where('grupos.0.filas.0.facturas', 'B-91')
                ->where('grupos.0.filas.0.arsfc', 242000)
                ->where('grupos.0.filas.0.provision', 0)
                ->where('grupos.0.filas.2.codigo', 'RE-101')
                ->where('grupos.0.filas.2.provision', 10));
    }

    public function test_pagos_a_proveedores_calcula_pagado_con_cotizacion_de_la_orden(): void
    {
        $this->get('/app/admin/reportes/pagos?buscar=1')
            ->assertOk()
            ->assertInertia(fn (Assert $p) => $p
                ->has('grupos.0.filas', 1)
                ->where('grupos.0.filas.0.codigo', 'RE-101')
                ->where('grupos.0.filas.0.factura', 'B-91')
                ->where('grupos.0.filas.0.pagado', 114950)
                ->where('grupos.0.filas.0.arsfc', 121000)
                ->where('grupos.0.filas.0.provision', -6050));
    }

    public function test_gastos_por_area_distribuye_por_imputacion_y_filtra_area(): void
    {
        $this->get('/app/admin/reportes/gastos-area?fechamovimiento=2025-03-01&fechamovimiento_to=2025-03-31')
            ->assertOk()
            ->assertInertia(fn (Assert $p) => $p
                ->has('grupos', 2)
                ->where('grupos.0.clave', 'ARS')
                ->where('grupos.0.filas.0.factura', 'A-90')
                ->where('grupos.0.filas.0.distribucion', "Receptivo: 726,00\nMayorista: 484,00")
                ->where('grupos.0.totales.monto', 1210));

        // Área 2 (Mayorista): sólo la factura que la tiene imputada.
        $this->get('/app/admin/reportes/gastos-area?fk_sistema_id=2')
            ->assertInertia(fn (Assert $p) => $p->has('grupos', 1)->where('grupos.0.filas.0.factura', 'A-90'));

        $this->get('/app/admin/reportes/gastos-area?tipogasto=HOTELERIA')
            ->assertInertia(fn (Assert $p) => $p->has('grupos', 1)->where('grupos.0.clave', 'USD'));
    }

    public function test_facturas_impagas_con_saldo_y_recibos_pendientes(): void
    {
        // Total = 81 + 100*1.19 = 200; cobrado 50 => saldo 150. Recibo R-70 de 120 con 50 imputados => saldo 70.
        $this->get('/app/admin/reportes/facturas-impagas?cliente=3')
            ->assertOk()
            ->assertInertia(fn (Assert $p) => $p
                ->has('grupos.0.filas', 1)
                ->where('grupos.0.filas.0.factura', '0001-00000040')
                ->where('grupos.0.filas.0.file', 'RE-100')
                ->where('grupos.0.filas.0.monto', 200)
                ->where('grupos.0.filas.0.saldo', 150)
                ->where('grupos.0.filas.0.recibos', 'R-70 (saldo 70)'));

        $this->get('/app/admin/reportes/facturas-impagas?cliente=3&conrecibos=0')
            ->assertInertia(fn (Assert $p) => $p->where('grupos', []));

        $this->get('/app/admin/reportes/facturas-impagas?cliente=3&precision=500')
            ->assertInertia(fn (Assert $p) => $p->where('grupos', []));
    }
}
