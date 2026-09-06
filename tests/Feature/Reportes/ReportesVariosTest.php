<?php

namespace Tests\Feature\Reportes;

use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\CreaEsquemaConfiguracion;
use Tests\TestCase;

/**
 * Smoke HTTP de: buscar pasajero, servicios sin factura, diferencia de cambio,
 * analítico de ventas, gastos de reserva, OP nacionales y escritorios.
 */
class ReportesVariosTest extends TestCase
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
        DB::table('cliente')->insert(['cliente_id' => 3, 'cliente_nombre' => 'Agencia Sol', 'cliente_razonsocial' => 'Sol; SA']);
        DB::table('proveedor')->insert([
            ['proveedor_id' => 5, 'proveedor_nombre' => 'Hotel Sol', 'fk_pais_id' => 1, 'eliminar' => 'N'],
            ['proveedor_id' => 6, 'proveedor_nombre' => 'Aerolínea', 'fk_pais_id' => 2, 'eliminar' => 'N'],
        ]);
        DB::table('sistema')->insert(['sistema_id' => 1, 'sistema_nombre' => 'Receptivo']);
        DB::table('filestatus')->insert(['filestatus_id' => 'CL', 'filestatus_nombre' => 'Cerrada']);
        DB::table('reserva')->insert([
            ['reserva_id' => 21, 'tipocodigo' => 'RE', 'codigo' => '100', 'fk_cliente_id' => 3, 'fk_usuario_id' => 7, 'agente' => 7, 'fk_sistema_id' => 1, 'fk_sistemaaplicacion_id' => 1, 'fk_filestatus_id' => 'CL', 'fecha_alta' => '2025-03-10', 'titular_apellido' => 'Pérez', 'titular_nombre' => 'Ana', 'fk_moneda_id' => 'USD', 'gastos' => 15, 'observaciones' => ''],
            ['reserva_id' => 22, 'tipocodigo' => 'MA', 'codigo' => '101', 'fk_cliente_id' => 3, 'fk_usuario_id' => 7, 'agente' => 7, 'fk_sistema_id' => 1, 'fk_sistemaaplicacion_id' => 1, 'fk_filestatus_id' => 'CO', 'fecha_alta' => '2025-03-11', 'titular_apellido' => 'López', 'titular_nombre' => 'Juan', 'fk_moneda_id' => 'ARS', 'gastos' => 0, 'observaciones' => ''],
        ]);
        DB::table('reservain')->insert(['fk_reserva_id' => 21, 'inicio' => '2025-05-01']);
        foreach ([
            ['servicio_id' => 11, 'servicio_nombre' => 'Hotel Sol dbl', 'fk_reserva_id' => 21, 'fk_tipoproducto_id' => 'HOT', 'fk_proveedor_id' => 5, 'status' => 'CO', 'vigencia_ini' => '2025-05-01', 'vigencia_fin' => '2025-05-03', 'moneda_costo' => 'USD', 'costo' => 100, 'iva_costo' => 21, 'fk_moneda_id' => 'USD', 'total' => 300, 'renta' => 50, 'iva' => 10, 'impuestos' => 5, 'cotventa' => 1000],
            ['servicio_id' => 12, 'servicio_nombre' => 'Boleto 0571234567890', 'fk_reserva_id' => 22, 'fk_tipoproducto_id' => 'AER', 'fk_proveedor_id' => 6, 'status' => 'CO', 'vigencia_ini' => '2025-06-01', 'vigencia_fin' => '2025-06-10', 'moneda_costo' => 'USD', 'costo' => 500, 'iva_costo' => 0, 'fk_moneda_id' => 'ARS', 'total' => 900, 'renta' => 80, 'iva' => 0, 'impuestos' => 100, 'cotventa' => 1],
            ['servicio_id' => 13, 'servicio_nombre' => 'Fee emisión', 'fk_reserva_id' => 22, 'fk_tipoproducto_id' => 'FEE', 'fk_proveedor_id' => 6, 'status' => 'CO', 'vigencia_ini' => '2025-06-01', 'vigencia_fin' => '2025-06-01', 'moneda_costo' => 'ARS', 'costo' => 0, 'iva_costo' => 0, 'fk_moneda_id' => 'ARS', 'total' => 40, 'renta' => 40, 'iva' => 0, 'impuestos' => 0, 'cotventa' => 1],
        ] as $fila) {
            DB::table('servicio')->insert($fila);
        }
        DB::table('rel_servicio')->insert(['servicio_madre' => 12, 'servicio_hijo' => 13]);
        DB::table('pnraereo')->insert(['fk_ocupacion_id' => 12, 'pnraereo_ruta' => "EZE-MIA\n", 'pnraereo_nombre' => 'Juan', 'pnraereo_apellido' => 'López', 'codigo_recloc' => 'ABC123', 'pnraereo_fechaemision' => '2025-03-12']);
        DB::table('ordenadmin')->insert([
            ['ordenadmin_id' => 300, 'fk_ordenadmin_id' => 0, 'fk_proveedor_id' => 5, 'fk_usuario_id' => 7, 'fecha' => '2025-04-01', 'nroservicio' => '77', 'nropago' => '88', 'tipo' => 'P', 'fk_moneda_id' => 'ARS', 'cotizacion' => 1, 'monto' => 121000, 'status' => 'OK'],
        ]);
        DB::table('rel_ordenadminocupacion')->insert(['fk_ordenadmin_id' => 300, 'fk_ocupacion_id' => 11, 'fk_moneda_id' => 'ARS', 'monto' => 121000]);
        DB::table('facturaproveedor')->insert(['facturaproveedor_id' => 90, 'facturaproveedor_nro' => 'A-90', 'fk_proveedor_id' => 5, 'fecha' => '2025-04-05', 'fechacontable' => '2025-04-05', 'fk_moneda_id' => 'ARS', 'montototal' => 121000, 'cotizacion' => 1, 'fechacarga' => '2025-04-06 10:00:00']);
    }

    public function test_buscar_pasajero_por_titular_o_boleto(): void
    {
        $this->get('/app/reservas-buscar')->assertOk()->assertInertia(fn (Assert $p) => $p->where('config.consultado', false));

        $this->get('/app/reservas-buscar?nrofile=pér')
            ->assertInertia(fn (Assert $p) => $p->has('grupos.0.filas', 1)->where('grupos.0.filas.0.file', 'RE-100')->where('grupos.0.filas.0.titular', 'Pérez, Ana')->where('grupos.0.filas.0.vendedor', 'Ana Pow'));

        $this->get('/app/reservas-buscar?nroboleto=0571234')
            ->assertInertia(fn (Assert $p) => $p->has('grupos.0.filas', 1)->where('grupos.0.filas.0.file', 'MA-101'));
    }

    public function test_servicios_pagados_sin_factura(): void
    {
        // El servicio 11 tiene OP y no tiene factura de proveedor imputada => aparece.
        $this->get('/app/admin/reportes/servicios-sin-factura')
            ->assertOk()
            ->assertInertia(fn (Assert $p) => $p->has('grupos.0.filas', 1)
                ->where('grupos.0.filas.0.codigo', 'RE-100')
                ->where('grupos.0.filas.0.op', '88')
                ->where('grupos.0.filas.0.costo', 100));

        DB::table('rel_facturaproveedorocupacion')->insert(['fk_facturaproveedor_id' => 90, 'fk_ocupacion_id' => 11, 'monto' => 121]);
        $this->get('/app/admin/reportes/servicios-sin-factura')->assertInertia(fn (Assert $p) => $p->where('grupos', []));
    }

    public function test_diferencia_de_cambio_toma_cotizacion_de_costo_cuando_la_op_esta_en_basica(): void
    {
        $this->get('/app/admin/reportes/diferencia-cambio?fecha=2025-04-01')
            ->assertInertia(fn (Assert $p) => $p->where('grupos', []));

        $this->get('/app/admin/reportes/diferencia-cambio?fecha=2025-04-01&fecha_to=2025-04-30')
            ->assertOk()
            ->assertInertia(fn (Assert $p) => $p->has('grupos.0.filas', 1)
                ->where('grupos.0.filas.0.codigo', 'RE-100')
                ->where('grupos.0.filas.0.nropago', '88')
                ->where('grupos.0.filas.0.cotizacion', 990)
                ->where('grupos.0.filas.0.costo', 100));
    }

    public function test_analitico_de_ventas_excluye_hijos_y_suma_fees(): void
    {
        $this->get('/app/admin/reportes/analitico-ventas?buscar=1&ver_adma=1')
            ->assertOk()
            ->assertInertia(fn (Assert $p) => $p->has('grupos.0.filas', 2)
                ->where('grupos.0.filas.0.file', 'RE-100')
                ->where('grupos.0.filas.0.area', 'Receptivo')
                ->where('grupos.0.filas.1.file', 'MA-101')
                ->where('grupos.0.filas.1.razon_social', 'Sol SA')
                ->where('grupos.0.filas.1.ruta', 'EZE-MIA')
                ->where('grupos.0.filas.1.pax', 'López, Juan')
                ->where('grupos.0.filas.1.recloc', 'ABC123')
                ->where('grupos.0.filas.1.fee', 40)
                ->where('grupos.0.filas.1.q_fee', 1));

        // Sin ver_adma se excluyen los files MA/AD.
        $this->get('/app/admin/reportes/analitico-ventas?buscar=1')
            ->assertInertia(fn (Assert $p) => $p->has('grupos.0.filas', 1)->where('grupos.0.filas.0.file', 'RE-100'));

        $csv = $this->get('/app/admin/reportes/analitico-ventas/export?buscar=1&ver_adma=1')->streamedContent();
        $this->assertStringContainsString('MA-101', $csv);
    }

    public function test_gastos_de_reserva_y_op_nacionales(): void
    {
        $this->get('/app/admin/reportes/gastos-reserva?fecha_alta=2025-03-01')
            ->assertOk()
            ->assertInertia(fn (Assert $p) => $p->has('grupos.0.filas', 1)
                ->where('grupos.0.filas.0.codigo', 'RE-100')
                ->where('grupos.0.filas.0.status', 'Cerrada')
                ->where('grupos.0.filas.0.inicio', '01/05/2025')
                ->where('grupos.0.filas.0.gastos', 15));

        $this->get('/app/admin/reportes/op-nacionales?nropago=88')
            ->assertOk()
            ->assertInertia(fn (Assert $p) => $p->has('grupos.0.filas', 1)
                ->where('grupos.0.filas.0.op', '77 - 88')
                ->where('grupos.0.filas.0.codigo', 'RE-100')
                ->where('grupos.0.filas.0.usd', 121)
                ->where('grupos.0.filas.0.ars', 121000)
                ->where('grupos.0.filas.0.factura', 'A-90'));
    }

    public function test_escritorios_arbol_alta_y_baja(): void
    {
        DB::table('usuario')->insert([
            ['usuario_id' => 8, 'usuario_nombre' => 'Vera', 'usuario_apellido' => 'Sosa', 'usuario_mail' => 'v@x.com', 'fk_tipousuario_id' => 'VEN', 'usuario_interno' => 'Y'],
            ['usuario_id' => 9, 'usuario_nombre' => 'Pedro', 'usuario_apellido' => 'Gil', 'usuario_mail' => 'p@x.com', 'fk_tipousuario_id' => 'VEN', 'usuario_interno' => 'N'],
        ]);
        DB::table('rel_usuariousuario')->insert([
            ['fk_usuario_id' => 7, 'fk_secundario_id' => 8, 'tiporelacion' => 1],
            ['fk_usuario_id' => 8, 'fk_secundario_id' => 7, 'tiporelacion' => 1],
            ['fk_usuario_id' => 8, 'fk_secundario_id' => 999, 'tiporelacion' => 1],
        ]);

        // Sin el flag, se ve pero no se escribe.
        $this->get('/app/config/escritorios')
            ->assertOk()
            ->assertInertia(fn (Assert $p) => $p->component('Config/Escritorios')
                ->where('puedeEscribir', false)
                ->where('resumen.escritorios', 2)->where('resumen.relaciones', 3)->where('resumen.huerfanas', 1)
                ->where('arbol.0.nombre', 'Pow, Ana')->where('arbol.0.hijos.0.id', 8)->where('arbol.0.hijos.0.reciproco', true)->where('arbol.0.hijos.0.es_titular', true)
                ->where('arbol.1.hijos.0.existe', false)
                ->has('usuarios', 2));
        $this->post('/app/config/escritorios/agregar', ['titular' => 7, 'secundario' => 9])->assertRedirect()->assertSessionHas('error');

        DB::table('sysconfig')->insert(['sysconfig_key' => 'escritorios_abm', 'sysconfig_value' => '1']);
        \App\Helpers\SysconfigHelper::olvidar('escritorios_abm');
        $this->post('/app/config/escritorios/agregar', ['titular' => 7, 'secundario' => 9, 'inversa' => 1])->assertRedirect()->assertSessionHas('success');
        $this->assertDatabaseHas('rel_usuariousuario', ['fk_usuario_id' => 7, 'fk_secundario_id' => 9]);
        $this->assertDatabaseHas('rel_usuariousuario', ['fk_usuario_id' => 9, 'fk_secundario_id' => 7]);
        $this->post('/app/config/escritorios/agregar', ['titular' => 7, 'secundario' => 9])->assertSessionHas('warning');
        $this->post('/app/config/escritorios/agregar', ['titular' => 7, 'secundario' => 7])->assertSessionHasErrors('secundario');

        $this->post('/app/config/escritorios/quitar', ['titular' => 8, 'secundario' => 999])->assertSessionHas('success');
        $this->assertDatabaseMissing('rel_usuariousuario', ['fk_usuario_id' => 8, 'fk_secundario_id' => 999]);

        // No POW => 403.
        $this->actingAs(\App\Models\User::find(8), 'web');
        $this->get('/app/config/escritorios')->assertForbidden();
    }
}
