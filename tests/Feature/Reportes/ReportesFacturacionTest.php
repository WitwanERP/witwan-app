<?php

namespace Tests\Feature\Reportes;

use App\Helpers\SysconfigHelper;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\CreaEsquemaConfiguracion;
use Tests\TestCase;

/** Control de crédito, pendientes de factura y facturados. */
class ReportesFacturacionTest extends TestCase
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
        DB::table('cotizacion')->insert(['cotizacion_moneda' => 'USD', 'cotizacion_fecha' => '2026-01-01', 'cotizacion_relacion' => 900, 'cotizacion_costo' => 890]);
        DB::table('cliente')->insert([
            ['cliente_id' => 3, 'cliente_nombre' => 'AGENCIA SOL', 'cliente_razonsocial' => 'Sol SA', 'cuit' => '76.111.111-1', 'limite_credito' => 1000000],
            ['cliente_id' => 4, 'cliente_nombre' => 'SIN CREDITO', 'cliente_razonsocial' => 'X', 'cuit' => '', 'limite_credito' => 0],
        ]);
        DB::table('proveedor')->insert(['proveedor_id' => 5, 'proveedor_nombre' => 'Hotel Sol', 'cuit' => '77.222.222-2']);
        DB::table('submodulo')->insert(['tipoproducto_id' => 'HTL', 'tipoproducto_nombre' => 'Hotel', 'submodulo_id' => 'HTL']);
        $hoy = now()->toDateString();
        DB::table('reserva')->insert([
            ['reserva_id' => 21, 'tipocodigo' => 'RE', 'codigo' => '100', 'fk_cliente_id' => 3, 'fk_usuario_id' => 7, 'agente' => 7, 'fk_sistema_id' => 1, 'fk_filestatus_id' => 'CO', 'fk_moneda_id' => 'USD', 'total' => 100, 'cobrado' => 0, 'fecha_alta' => "{$hoy} 10:00:00"],
            ['reserva_id' => 22, 'tipocodigo' => 'RE', 'codigo' => '101', 'fk_cliente_id' => 3, 'fk_usuario_id' => 7, 'agente' => 7, 'fk_sistema_id' => 1, 'fk_filestatus_id' => 'CO', 'fk_moneda_id' => 'CLP', 'total' => 50000, 'cobrado' => 0, 'fecha_alta' => "{$hoy} 10:00:00"],
        ]);
        DB::table('servicio')->insert(['servicio_id' => 11, 'servicio_nombre' => 'Hotel sin factura', 'fk_reserva_id' => 21, 'fk_proveedor_id' => 5, 'fk_tipoproducto_id' => 'HTL', 'status' => 'CO', 'fk_moneda_id' => 'USD', 'total' => 100, 'costo' => 60, 'moneda_costo' => 'USD']);
        DB::table('servicio')->insert(['servicio_id' => 12, 'servicio_nombre' => 'Hotel facturado', 'fk_reserva_id' => 22, 'fk_proveedor_id' => 5, 'fk_tipoproducto_id' => 'HTL', 'status' => 'CO', 'fk_moneda_id' => 'CLP', 'total' => 50000, 'costo' => 30000, 'iva_costo' => 0, 'moneda_costo' => 'CLP', 'iva' => 0, 'impuestos' => 0, 'cotventa' => 0, 'cotcosto' => 0]);
        DB::table('servicio')->insert(['servicio_id' => 13, 'servicio_nombre' => 'Cancelado', 'fk_reserva_id' => 22, 'fk_proveedor_id' => 5, 'fk_tipoproducto_id' => 'HTL', 'status' => 'CA', 'fk_moneda_id' => 'CLP', 'total' => 1, 'costo' => 0, 'moneda_costo' => 'CLP']);
        DB::table('factura')->insert(['factura_id' => 40, 'factura_nro' => '0001-40', 'statusfactura' => 'EM', 'factura_fecha' => "{$hoy} 10:00:00", 'factura_tipo' => 'A', 'factura_conceptos_gravados' => 10000, 'factura_conceptos_exentos' => 38100, 'fk_cliente_id' => 3, 'fk_file_id' => 22, 'fk_moneda_id' => 'CLP', 'remitofull' => '0:0:0']);
        DB::table('rel_serviciofactura')->insert(['fk_servicio_id' => 12, 'fk_factura_id' => 40, 'tipodocumento' => 1]);
        DB::table('recibo')->insert(['recibo_id' => 70, 'recibo_tipo' => 'RC', 'recibo_nro' => 'R-70', 'fecha' => '2026-03-01', 'fk_cliente_id' => 3, 'fk_usuario_id' => 7, 'statusrecibo' => 'EM', 'monto' => 20000, 'fk_moneda_id' => 'CLP']);
        DB::table('rel_facturarecibo')->insert(['fk_factura_id' => 40, 'fk_recibo_id' => 70, 'monto' => 20000]);
    }

    public function test_control_de_credito_calcula_utilizado_y_carga_extra_del_dia(): void
    {
        // Factura: 38100 + 10000*1.19 = 50000, aplicado 20000 => 30000. File 21 sin facturar: 100 USD * 900 = 90000. Total 120000.
        $this->get('/app/admin/reportes/control-credito')->assertOk()->assertInertia(fn (Assert $p) => $p
            ->component('Reportes/Listado')
            ->has('grupos.0.filas', 1)
            ->where('grupos.0.filas.0.cliente', 'AGENCIA SOL')
            ->where('grupos.0.filas.0.utilizado', 120000)
            ->where('grupos.0.filas.0.disponible', 880000)
            ->where('grupos.0.filas.0.acciones.0.method', 'post')
        );

        $this->post('/app/admin/reportes/control-credito/3/extra', ['valor' => '5000'])->assertRedirect();
        $this->assertDatabaseHas('creditoextra', ['fk_cliente_id' => 3, 'creditoextra_monto' => 5000, 'fk_usuario_id' => 7]);
        $this->post('/app/admin/reportes/control-credito/3/extra', ['valor' => '7000'])->assertRedirect();
        $this->assertSame(1, DB::table('creditoextra')->where('fk_cliente_id', 3)->count(), 'REPLACE: un solo extra por día');

        $this->get('/app/admin/reportes/control-credito?cliente=3')->assertOk()->assertInertia(fn (Assert $p) => $p
            ->where('grupos.0.filas.0.extra', 7000)
            ->where('grupos.0.filas.0.disponible', 887000)
            ->where('grupos.0.filas.0.acciones.0.label', 'Quitar crédito extra')
        );
        $this->post('/app/admin/reportes/control-credito/3/extra', ['valor' => 'delete'])->assertRedirect();
        $this->assertDatabaseMissing('creditoextra', ['fk_cliente_id' => 3]);
        $this->post('/app/admin/reportes/control-credito/3/extra', ['valor' => 'abc'])->assertStatus(422);
    }

    public function test_pendientes_de_factura_lista_servicios_sin_factura_y_exporta(): void
    {
        $this->get('/app/admin/reportes/pendientes-factura')->assertOk()->assertInertia(fn (Assert $p) => $p
            ->has('grupos.0.filas', 1)
            ->where('grupos.0.filas.0.servicio_id', 11)
            ->where('grupos.0.filas.0.file', 'RE-100')
            ->where('grupos.0.filas.0.proveedor_rut', '77.222.222-2')
            ->where('grupos.0.totales.total', 100)
        );
        $csv = $this->get('/app/admin/reportes/pendientes-factura/export')->assertOk()->streamedContent();
        $this->assertStringContainsString('Hotel sin factura', $csv);
        $this->assertStringNotContainsString('Hotel facturado', $csv);
    }

    public function test_facturados_calcula_estado_de_cobro_codigos_sii_y_renta(): void
    {
        $this->get('/app/admin/reportes/facturados')->assertOk()->assertInertia(fn (Assert $p) => $p
            ->has('grupos.0.filas', 1)
            ->where('grupos.0.filas.0.tipo_doc', 34)
            ->where('grupos.0.filas.0.estado', 'A')
            ->where('grupos.0.filas.0.total_factura', 50000)
            ->where('grupos.0.filas.0.iva', 1900)
            ->where('grupos.0.filas.0.venta', 50000)
            ->where('grupos.0.filas.0.costo', 30000)
            ->where('grupos.0.filas.0.renta', 20000)
            ->where('grupos.0.filas.0.tipo_cambio', 1)
        );
        $this->get('/app/admin/reportes/facturados?estado=C')->assertOk()->assertInertia(fn (Assert $p) => $p->has('grupos', 0));

        DB::table('rel_facturarecibo')->insert(['fk_factura_id' => 40, 'fk_recibo_id' => 70, 'monto' => 30000]);
        $this->get('/app/admin/reportes/facturados?estado=C')->assertOk()->assertInertia(fn (Assert $p) => $p
            ->has('grupos.0.filas', 1)
            ->where('grupos.0.filas.0.fecha_cobro', '01/03/2026')
        );
    }
}
