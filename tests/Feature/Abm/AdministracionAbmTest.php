<?php

namespace Tests\Feature\Abm;

use App\Services\Admin\PlanCuentaReplicador;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\CreaEsquemaConfiguracion;
use Tests\TestCase;

/**
 * Smoke HTTP de las pantallas chicas de Administración: monedas (PK de texto),
 * cotizaciones (sólo alta), tipo de cambio, tabla de IVA, plan de cuentas,
 * parámetros contables, perfiles/modelos de comisión y modelos de fee.
 */
class AdministracionAbmTest extends TestCase
{
    use CreaEsquemaConfiguracion;

    protected function setUp(): void
    {
        parent::setUp();
        $this->crearEsquemaConfiguracion();
        $this->autenticarPow(['secciones.overrides' => ['administracion/cambio/ultimas' => 104]]);

        // El replicador consulta brain: se anula en los tests.
        $this->mock(PlanCuentaReplicador::class)->shouldIgnoreMissing();

        DB::table('moneda')->insert([
            ['moneda_id' => 'ARS', 'moneda_nombre' => 'Peso', 'moneda_basica' => 'Y', 'orden' => 1],
            ['moneda_id' => 'USD', 'moneda_nombre' => 'Dólar', 'moneda_basica' => 'N', 'orden' => 2],
        ]);
        DB::table('submodulo')->insert([['tipoproducto_id' => 'HOT', 'tipoproducto_nombre' => 'Hoteles'], ['tipoproducto_id' => 'AER', 'tipoproducto_nombre' => 'Aéreos']]);
        DB::table('pais')->insert(['pais_id' => 10, 'pais_nombre' => 'Argentina']);
        DB::table('ciudad')->insert(['ciudad_id' => 1, 'ciudad_nombre' => 'Buenos Aires', 'fk_pais_id' => 10]);
        DB::table('modoivaventa')->insert(['modoivaventa_id' => 2, 'modoivaventa_nombre' => 'IVA sobre renta']);
        DB::table('plancuenta')->insert(['plancuenta_id' => 1, 'plancuenta_nombre' => 'Activo', 'plancuenta_codigo' => '1', 'plancuenta_titulo' => 1]);
        DB::table('plancuenta')->insert(['plancuenta_id' => 54, 'plancuenta_nombre' => 'Caja', 'plancuenta_codigo' => '1.1.1', 'fk_plancuenta_id' => 1, 'arqueo' => 1]);
        DB::table('cliente')->insert(['cliente_id' => 3, 'cliente_nombre' => 'Agencia Sol']);
        DB::table('region')->insert(['region_id' => 4, 'region_nombre' => 'Caribe']);
        DB::table('sistema')->insert([['sistema_id' => 1, 'sistema_nombre' => 'Receptivo', 'sistema_codigo' => 'RE', 'texto_extra3' => 'GR,MI']]);
        DB::table('modelocomision')->insert(['modelocomision_id' => 9, 'modelocomision_nombre' => 'Vendedores']);
        DB::table('reserva')->insert([['reserva_id' => 1, 'tipocodigo' => 'RE', 'codigo' => '1'], ['reserva_id' => 2, 'tipocodigo' => 'OP', 'codigo' => '2']]);
    }

    public function test_monedas_con_pk_de_texto(): void
    {
        $this->get('/app/admin/monedas')
            ->assertOk()
            ->assertInertia(fn (Assert $p) => $p->component('Abm/Index')
                ->where('config.area', 'Administración')
                ->where('registros.total', 2)
                ->where('registros.data.0.moneda_basica', 'Sí'));

        $this->post('/app/admin/monedas', ['moneda_id' => 'eur', 'moneda_nombre' => 'Euro', 'iso_code' => 'EUR', 'orden' => 3])->assertRedirect('/app/admin/monedas');
        $this->assertDatabaseHas('moneda', ['moneda_id' => 'EUR', 'moneda_nombre' => 'Euro']);

        // La PK debe tener 3 caracteres y ser única.
        $this->from('/app/admin/monedas/create')->post('/app/admin/monedas', ['moneda_id' => 'USD', 'moneda_nombre' => 'Otra'])->assertSessionHasErrors('moneda_id');
        $this->from('/app/admin/monedas/create')->post('/app/admin/monedas', ['moneda_id' => 'PESOS', 'moneda_nombre' => 'Otra'])->assertSessionHasErrors('moneda_id');

        $this->get('/app/admin/monedas/EUR/edit')->assertOk()->assertInertia(fn (Assert $p) => $p->where('registro.moneda_id', 'EUR'));
        $this->put('/app/admin/monedas/EUR', ['moneda_nombre' => 'Euro zona', 'orden' => 5])->assertRedirect();
        $this->assertDatabaseHas('moneda', ['moneda_id' => 'EUR', 'moneda_nombre' => 'Euro zona', 'orden' => 5]);

        $this->delete('/app/admin/monedas/EUR')->assertRedirect();
        $this->assertDatabaseMissing('moneda', ['moneda_id' => 'EUR']);
    }

    public function test_tipo_de_cambio_muestra_vigentes_y_graba_la_de_hoy(): void
    {
        DB::table('cotizacion')->insert(['cotizacion_moneda' => 'USD', 'cotizacion_fecha' => '2020-01-01', 'cotizacion_relacion' => 900, 'cotizacion_costo' => 880]);

        $this->get('/app/admin/tipo-cambio')
            ->assertOk()
            ->assertInertia(fn (Assert $p) => $p->component('Admin/TipoCambio')
                ->where('monedaBasica', 'ARS')
                ->where('puedeEditar', true)
                ->where('monedas.0.moneda', 'ARS')->where('monedas.0.basica', true)->where('monedas.0.venta', 1)
                ->where('monedas.1.moneda', 'USD')->where('monedas.1.venta', 900)->where('monedas.1.costo', 880));

        $this->post('/app/admin/tipo-cambio', ['moneda' => 'USD', 'valor' => 950.5, 'valor2' => 940])->assertRedirect();
        $this->assertDatabaseHas('cotizacion', ['cotizacion_moneda' => 'USD', 'cotizacion_fecha' => now()->toDateString(), 'cotizacion_relacion' => 950.5, 'cotizacion_costo' => 940]);

        // Segunda carga del mismo día pisa la anterior (REPLACE del CI), no duplica.
        $this->post('/app/admin/tipo-cambio', ['moneda' => 'USD', 'valor' => 960, 'valor2' => 955])->assertRedirect();
        $this->assertSame(1, DB::table('cotizacion')->where('cotizacion_fecha', now()->toDateString())->count());

        $this->post('/app/admin/tipo-cambio', ['moneda' => 'ARS', 'valor' => 2])->assertStatus(422);
    }

    public function test_cotizaciones_historicas_solo_listan_y_cargan(): void
    {
        $this->get('/app/admin/cotizaciones')
            ->assertOk()
            ->assertInertia(fn (Assert $p) => $p->where('config.acciones', ['crear']));

        $this->post('/app/admin/cotizaciones', ['cotizacion_fecha' => '2025-03-01', 'cotizacion_moneda' => 'USD', 'cotizacion_relacion' => 1000, 'cotizacion_costo' => 990])->assertRedirect();
        $this->assertDatabaseHas('cotizacion', ['cotizacion_moneda' => 'USD', 'cotizacion_fecha' => '2025-03-01']);

        $id = DB::table('cotizacion')->max('cotizacion_id');
        $this->get("/app/admin/cotizaciones/{$id}/edit")->assertNotFound();
        $this->delete("/app/admin/cotizaciones/{$id}")->assertNotFound();

        $this->get('/app/admin/cotizaciones')
            ->assertInertia(fn (Assert $p) => $p->where('registros.data.0.cotizacion_moneda', 'USD - Dólar'));
    }

    public function test_tabla_de_iva(): void
    {
        $this->post('/app/admin/tabla-iva', ['fk_submodulo_id' => 'HOT', 'fk_pais_id' => 10, 'fk_ciudad_id' => 1, 'fk_producto_id' => 0, 'fk_modoivaventa_id' => 2, 'iva_costo' => 21, 'iva_valor' => 10.5])->assertRedirect('/app/admin/tabla-iva');
        $this->assertDatabaseHas('iva', ['fk_submodulo_id' => 'HOT', 'fk_pais_id' => 10, 'fk_ciudad_id' => 1, 'iva_costo' => 21, 'iva_valor' => 10.5]);

        $this->get('/app/admin/tabla-iva?fk_pais_id=10')
            ->assertOk()
            ->assertInertia(fn (Assert $p) => $p
                ->where('registros.total', 1)
                ->where('registros.data.0.fk_submodulo_id', 'Hoteles')
                ->where('registros.data.0.fk_pais_id', 'Argentina')
                ->where('registros.data.0.fk_ciudad_id', 'Buenos Aires')
                ->where('registros.data.0.fk_modoivaventa_id', 'IVA sobre renta'));

        // El form trae las ciudades con su país para el select dependiente.
        $this->get('/app/admin/tabla-iva/create')
            ->assertInertia(fn (Assert $p) => $p->where('config.opciones.ciudades.0.padre', 10));
    }

    public function test_plan_de_cuentas_lista_con_totalizadora_y_guarda_flags(): void
    {
        $this->get('/app/admin/plan-cuentas?arqueo=1')
            ->assertOk()
            ->assertInertia(fn (Assert $p) => $p
                ->where('registros.total', 1)
                ->where('registros.data.0.plancuenta_nombre', 'Caja')
                ->where('registros.data.0.totalizadora', 'Activo')
                ->where('registros.data.0.arqueo', 'Sí')
                ->where('registros.data.0.plancuenta_saldo', 'Deudor'));

        $this->post('/app/admin/plan-cuentas', [
            'plancuenta_codigo' => '1.1.2', 'plancuenta_nombre' => 'Banco', 'fk_plancuenta_id' => 1, 'fk_moneda_id' => 'ARS',
            'arqueo' => 1, 'plancuenta_g' => 1, 'cartera' => 0, 'plancuenta_titulo' => 0, 'cuentagasto' => 1,
            'plancuenta_cli' => 'Y', 'conceptos_adicionales' => 0, 'plancuenta_saldo' => 'A',
        ])->assertRedirect('/app/admin/plan-cuentas');
        $this->assertDatabaseHas('plancuenta', ['plancuenta_codigo' => '1.1.2', 'fk_plancuenta_id' => 1, 'cuentagasto' => 1, 'plancuenta_cli' => 'Y', 'plancuenta_saldo' => 'A']);

        // Campos condicionales por licencia: witwan_rays ve "requiere analítico".
        $this->get('/app/admin/plan-cuentas/create')
            ->assertInertia(fn (Assert $p) => $p->where('config.campos', fn ($campos) => collect($campos)->contains('campo', 'requiereanalitico')));
    }

    public function test_parametros_contables_muestra_catalogo_ar_y_guarda_en_sysconfig(): void
    {
        DB::table('sysconfig')->insert([
            ['sysconfig_key' => 'cuentarecibos', 'sysconfig_value' => '54'],
            ['sysconfig_key' => 'adicionales_fc3', 'sysconfig_value' => json_encode(['gastos_bancarios' => 54, 'otros' => 0])],
        ]);

        $this->get('/app/admin/parametros-contables')
            ->assertOk()
            ->assertInertia(fn (Assert $p) => $p->component('Admin/ParametrosContables')
                ->where('grupos.0.titulo', 'CUENTAS GENERALES')
                ->where('grupos.0.items.0.clave', 'cuentarecibos')
                ->where('grupos.0.items.0.valor', 54)
                ->where('grupos.1.titulo', 'VENTAS')
                ->where('grupos.2.titulo', 'COMPRAS')
                ->where('grupos.2.items', fn ($items) => collect($items)->firstWhere('clave', 'adicionales_fc3')['adicionales'][0] === ['concepto' => 'gastos_bancarios', 'valor' => 54])
                ->has('cuentas', 2));

        $this->post('/app/admin/parametros-contables', [
            'valores' => ['cuentaproveedor' => 1, 'cuentarecibos' => '', 'clave_inventada' => 54],
            'adicionales' => ['adicionales_fc3' => ['gastos_bancarios' => 1, 'otros' => '']],
        ])->assertRedirect('/app/admin/parametros-contables');

        $this->assertDatabaseHas('sysconfig', ['sysconfig_key' => 'cuentaproveedor', 'sysconfig_value' => '1']);
        $this->assertDatabaseHas('sysconfig', ['sysconfig_key' => 'cuentarecibos', 'sysconfig_value' => '0']);
        $this->assertDatabaseHas('sysconfig', ['sysconfig_key' => 'adicionales_fc3', 'sysconfig_value' => json_encode(['gastos_bancarios' => '1', 'otros' => '0'])]);
        $this->assertDatabaseMissing('sysconfig', ['sysconfig_key' => 'clave_inventada']);
    }

    public function test_perfiles_y_modelos_de_comision(): void
    {
        $this->post('/app/admin/perfiles-comision', ['fk_usuario_id' => 7, 'fk_modelocomision_id' => 9])->assertRedirect();
        $this->get('/app/admin/perfiles-comision')
            ->assertInertia(fn (Assert $p) => $p->where('registros.data.0.fk_usuario_id', 'Pow Ana')->where('registros.data.0.fk_modelocomision_id', 'Vendedores'));

        $this->get('/app/admin/modelos-comision/create')
            ->assertOk()
            ->assertInertia(fn (Assert $p) => $p->where('config.opciones.prefijos', [
                ['value' => '', 'label' => 'N/A'], ['value' => 'RE', 'label' => 'RE'], ['value' => 'GR', 'label' => 'GR'], ['value' => 'MI', 'label' => 'MI'],
            ]));

        $this->post('/app/admin/modelos-comision', [
            'modelocomision_nombre' => 'Promotores', 'fk_moneda_id' => 'USD', 'modelocomision_esquema' => 'PRO', 'modelocomision_tipo' => 'XFI',
            'modelocomision_asignacion' => 'PRO', 'modelocomision_basecomision' => 'COB', 'modelocomision_basecalculo' => 'RNE',
            'fk_submodulo_id' => 'HOT', 'modelocomision_prefijo' => 'GR', 'in1' => 0, 'out1' => 1000, 'porcentaje1' => 2.5, 'meta_anual' => 50000,
            'vigencia_in' => '2025-01-01', 'vigencia_out' => '2025-12-31',
        ])->assertRedirect('/app/admin/modelos-comision');
        $this->assertDatabaseHas('modelocomision', ['modelocomision_nombre' => 'Promotores', 'modelocomision_prefijo' => 'GR', 'out1' => 1000, 'meta_anual' => 50000]);
    }

    public function test_modelos_de_fee(): void
    {
        $this->get('/app/admin/modelos-fee/create')
            ->assertOk()
            ->assertInertia(fn (Assert $p) => $p->where('config.opciones.tiposCodigo', [['value' => 'OP', 'label' => 'OP'], ['value' => 'RE', 'label' => 'RE']]));

        $this->post('/app/admin/modelos-fee', [
            'modelofee_nombre' => 'Fee aéreo', 'fk_cliente_id' => 3, 'modelofee_tipo' => 'F', 'tipocodigo' => 'RE',
            'region_origen' => 4, 'fk_region_id' => 4, 'fk_pais_id' => 10, 'fk_ciudad_id' => 1,
            'modelofee_minimo' => 0, 'modelofee_maximo' => 100000, 'fk_submodulo_id' => 'AER', 'fk_moneda_id' => 'USD',
            'modelofee_normal' => 25, 'modelofee_offline' => 20, 'modelofee_emergencia' => 40, 'modelofee_normal_r' => 15,
        ])->assertRedirect('/app/admin/modelos-fee');
        $this->assertDatabaseHas('modelofee', ['modelofee_nombre' => 'Fee aéreo', 'fk_cliente_id' => 3, 'modelofee_tipo' => 'F', 'fk_ciudad_id' => 1, 'modelofee_normal' => 25]);

        $this->get('/app/admin/modelos-fee?fk_cliente_id=3')
            ->assertInertia(fn (Assert $p) => $p->where('registros.total', 1)
                ->where('registros.data.0.fk_cliente_id', 'Agencia Sol')
                ->where('registros.data.0.modelofee_tipo', 'Valor fijo')
                ->where('registros.data.0.fk_region_id', 'Caribe'));
    }
}
