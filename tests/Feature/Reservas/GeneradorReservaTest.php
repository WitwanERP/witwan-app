<?php

namespace Tests\Feature\Reservas;

use App\Helpers\SysconfigHelper;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\CreaEsquemaConfiguracion;
use Tests\TestCase;

/** Generador de reservas v1: alta manual de file + servicios con controles de integridad. */
class GeneradorReservaTest extends TestCase
{
    use CreaEsquemaConfiguracion;

    protected function setUp(): void
    {
        parent::setUp();
        $this->crearEsquemaConfiguracion();
        $this->autenticarPow();
        SysconfigHelper::olvidar('tasageneral');

        DB::table('sistema')->insert(['sistema_id' => 2, 'sistema_nombre' => 'Mayorista', 'sistema_codigo' => 'MA']);
        DB::table('moneda')->insert([['moneda_id' => 'ARS', 'moneda_nombre' => 'Peso', 'moneda_basica' => 'Y'], ['moneda_id' => 'USD', 'moneda_nombre' => 'Dólar', 'moneda_basica' => 'N']]);
        DB::table('cotizacion')->insert(['cotizacion_moneda' => 'USD', 'cotizacion_fecha' => '2026-01-01', 'cotizacion_relacion' => 1000, 'cotizacion_costo' => 990]);
        DB::table('cliente')->insert([
            ['cliente_id' => 3, 'cliente_nombre' => 'AGENCIA SOL', 'cliente_razonsocial' => 'Sol SA', 'cuit' => '1', 'limite_credito' => 0, 'credito_habilitado' => 0, 'fk_usuario_vendedor' => 7, 'fk_usuario_promotor1' => 9],
            ['cliente_id' => 4, 'cliente_nombre' => 'CON CREDITO', 'cliente_razonsocial' => 'CC', 'cuit' => '2', 'limite_credito' => 100000, 'credito_habilitado' => 1, 'fk_usuario_vendedor' => 0, 'fk_usuario_promotor1' => 0],
            ['cliente_id' => 5, 'cliente_nombre' => 'OTRA AREA', 'cliente_razonsocial' => 'OA', 'cuit' => '3', 'limite_credito' => 0, 'credito_habilitado' => 0, 'fk_usuario_vendedor' => 0, 'fk_usuario_promotor1' => 0],
        ]);
        DB::table('rel_clientesistema')->insert([
            ['fk_cliente_id' => 3, 'fk_sistema_id' => 2, 'fk_tarifario_id' => 1],
            ['fk_cliente_id' => 4, 'fk_sistema_id' => 2, 'fk_tarifario_id' => 1],
            ['fk_cliente_id' => 5, 'fk_sistema_id' => 1, 'fk_tarifario_id' => 1],
        ]);
        DB::table('proveedor')->insert([['proveedor_id' => 5, 'proveedor_nombre' => 'Hotel Sol', 'habilita' => 'Y'], ['proveedor_id' => 6, 'proveedor_nombre' => 'Baja', 'habilita' => 'N']]);
        DB::table('submodulo')->insert([['tipoproducto_id' => 'HTL', 'tipoproducto_nombre' => 'Hotel', 'submodulo_id' => 'HTL'], ['tipoproducto_id' => 'TRS', 'tipoproducto_nombre' => 'Traslado', 'submodulo_id' => 'TRS']]);
        DB::table('reserva')->insert(['reserva_id' => 21, 'tipocodigo' => 'MA', 'codigo' => '1500', 'fk_cliente_id' => 3, 'fk_usuario_id' => 7, 'agente' => 7, 'fk_sistema_id' => 2, 'escotizacion' => 0]);
    }

    private function payload(array $extra = [], array $servicio = []): array
    {
        $ini = now()->addDays(10)->toDateString();

        return $extra + [
            'fk_cliente_id' => 3, 'titular_nombre' => 'ana', 'titular_apellido' => 'pérez', 'titular_email' => 'ana@x.com', 'fk_moneda_id' => 'USD',
            'servicios' => [$servicio + [
                'fk_tipoproducto_id' => 'HTL', 'servicio_nombre' => 'Hotel Sol 3 noches', 'fk_proveedor_id' => 5, 'vigencia_ini' => $ini, 'vigencia_fin' => now()->addDays(13)->toDateString(),
                'adultos' => 2, 'menores' => 0, 'fk_moneda_id' => 'USD', 'moneda_costo' => 'USD', 'total' => 300, 'costo' => 200, 'iva' => 0, 'iva_costo' => 0, 'impuestos' => 10, 'status' => 'CO',
                'nro_confirmacion' => 'ABC', 'pasajeros' => [['apellido' => 'pérez', 'nombre' => 'ana', 'tipopax' => 'ADT', 'documento' => '1'], ['apellido' => '', 'nombre' => '']],
            ]],
        ];
    }

    public function test_formulario_carga_clientes_del_area_tipos_y_fecha_minima(): void
    {
        $this->get('/app/reservas/mayorista/nueva')->assertOk()->assertInertia(fn (Assert $p) => $p
            ->component('Reservas/Nueva')
            ->where('idsistema', 2)
            ->where('fechaMinima', now()->toDateString())
            ->where('statusFile', 'CO')
            ->has('opciones.clientes', 2)
            ->where('opciones.clientes.1.label', 'CON CREDITO (100.000)')
            ->has('opciones.tipos', 2)
            ->where('puedeForzarCredito', true)
        );
    }

    public function test_crea_reserva_con_codigo_siguiente_servicios_nomina_totales_historial_y_auditoria(): void
    {
        $this->post('/app/reservas/mayorista/nueva', $this->payload())
            ->assertRedirect('/app/reservas/mayorista?codigo=1501')
            ->assertSessionHas('success');

        $r = DB::table('reserva')->where('codigo', '1501')->first();
        $this->assertNotNull($r);
        $this->assertSame('MA', $r->tipocodigo);
        $this->assertSame('PÉREZ', $r->titular_apellido);
        $this->assertSame('CO', $r->fk_filestatus_id);
        $this->assertSame(3, (int) $r->facturar_a);
        $this->assertSame(7, (int) $r->agente, 'interno: el agente es el usuario');
        $this->assertSame(9, (int) $r->promotor, 'promotor del cliente');
        $this->assertSame('ARS', $r->moneda_factura);
        $this->assertSame(now()->toDateString(), (string) $r->fecha_alta);
        $this->assertSame(300.0, (float) $r->total, 'total en la moneda del file (USD)');
        $this->assertSame(200.0, (float) $r->costo);
        $this->assertSame(10.0, (float) $r->impuestos);
        // renta = 300*1000 - 0 - 200*990 - 10*990 = 300000 - 207900 = 92100 ARS => 92.1 USD (cot file 1000)
        $this->assertSame(92.1, (float) $r->renta);

        $s = DB::table('servicio')->where('fk_reserva_id', $r->reserva_id)->first();
        $this->assertSame(1000.0, (float) $s->cotventa);
        $this->assertSame(990.0, (float) $s->cotcosto);
        $this->assertSame('APP', $s->origen);
        $this->assertSame('ABC', $s->nro_confirmacion);
        $this->assertSame(1, DB::table('servicio_nomina')->where('fk_servicio_id', $s->servicio_id)->count(), 'la fila vacía de nómina se descarta');
        $this->assertSame('PÉREZ', DB::table('servicio_nomina')->where('fk_servicio_id', $s->servicio_id)->value('apellido'));
        $this->assertDatabaseHas('historialfile', ['fk_reserva_id' => $r->reserva_id, 'historial_campo' => 'alta', 'fk_usuario_id' => 7]);
        $this->assertDatabaseHas('auditoria', ['tabla_relacionada' => 'reserva', 'id_relacionado' => $r->reserva_id, 'accion' => 'ALTA_GENERADOR']);
    }

    public function test_rechaza_cliente_de_otra_area_proveedor_deshabilitado_fechas_y_pax(): void
    {
        $this->from('/app/reservas/mayorista/nueva')->post('/app/reservas/mayorista/nueva', $this->payload(['fk_cliente_id' => 5], [
            'fk_proveedor_id' => 6, 'vigencia_ini' => now()->addDays(5)->toDateString(), 'vigencia_fin' => now()->addDays(2)->toDateString(), 'adultos' => 0, 'fk_tipoproducto_id' => 'XXX',
        ]))->assertRedirect('/app/reservas/mayorista/nueva')
            ->assertSessionHasErrors(['fk_cliente_id', 'servicios.0.fk_proveedor_id', 'servicios.0.vigencia_fin', 'servicios.0.adultos', 'servicios.0.fk_tipoproducto_id']);

        $this->assertSame(1, DB::table('reserva')->count(), 'no se creó nada');
        $this->assertSame(0, DB::table('servicio')->count());
    }

    public function test_fecha_minima_para_usuario_externo_y_bloqueo_por_fecha(): void
    {
        DB::table('usuario')->where('usuario_id', 7)->update(['usuario_interno' => 'N', 'fk_tipousuario_id' => 'CLI']);
        auth()->user()->refresh();

        $minima = now()->addWeekdays(4)->toDateString();
        $this->get('/app/reservas/mayorista/nueva')->assertOk()->assertInertia(fn (Assert $p) => $p->where('fechaMinima', $minima));

        $this->from('/app/reservas/mayorista/nueva')->post('/app/reservas/mayorista/nueva', $this->payload([], ['vigencia_ini' => now()->addDay()->toDateString(), 'vigencia_fin' => now()->addDays(20)->toDateString()]))
            ->assertSessionHasErrors(['servicios.0.vigencia_ini']);
    }

    public function test_limite_de_credito_bloquea_y_pow_puede_forzar_con_auditoria(): void
    {
        // Cliente 4: límite 100000 ARS con control. Reserva de 300 USD = 300000 ARS > límite.
        $this->from('/app/reservas/mayorista/nueva')->post('/app/reservas/mayorista/nueva', $this->payload(['fk_cliente_id' => 4]))
            ->assertSessionHasErrors(['credito']);
        $this->assertSame(1, DB::table('reserva')->count());

        // Con 50 USD (50000 ARS) entra sin forzar.
        $this->post('/app/reservas/mayorista/nueva', $this->payload(['fk_cliente_id' => 4], ['total' => 50]))->assertRedirect('/app/reservas/mayorista?codigo=1501');

        // Forzado por POW: se crea y queda auditado. Ahora el cliente usa 50000 + 300000.
        $this->post('/app/reservas/mayorista/nueva', $this->payload(['fk_cliente_id' => 4, 'forzar_credito' => 1]))->assertRedirect('/app/reservas/mayorista?codigo=1502');
        $aud = DB::table('auditoria')->where('accion', 'ALTA_GENERADOR')->orderByDesc('auditoria_id')->value('valor_nuevo');
        $this->assertStringContainsString('"forzar_credito":true', $aud);
        $this->assertStringContainsString('supera su l', $aud);

        // Ya excedido, ni 1 USD entra sin forzar.
        $this->from('/app/reservas/mayorista/nueva')->post('/app/reservas/mayorista/nueva', $this->payload(['fk_cliente_id' => 4], ['total' => 1]))->assertSessionHasErrors(['credito']);
    }

    public function test_validacion_previa_devuelve_errores_y_avisos_sin_crear(): void
    {
        $this->postJson('/app/reservas/mayorista/nueva/validar', $this->payload([], ['total' => 0, 'adultos' => 1]))
            ->assertOk()
            ->assertJsonPath('errores', [])
            ->assertJsonCount(1, 'advertencias');
        $this->assertSame(1, DB::table('reserva')->count());
    }

    public function test_codigos_consecutivos_en_altas_sucesivas(): void
    {
        $this->post('/app/reservas/mayorista/nueva', $this->payload())->assertRedirect('/app/reservas/mayorista?codigo=1501');
        $this->post('/app/reservas/mayorista/nueva', $this->payload())->assertRedirect('/app/reservas/mayorista?codigo=1502');
        $this->assertSame(['1500', '1501', '1502'], DB::table('reserva')->orderBy('reserva_id')->pluck('codigo')->all());
    }

    public function test_persiste_base_y_extras_pickup_dropoff_del_servicio(): void
    {
        $this->post('/app/reservas/mayorista/nueva', $this->payload([], [
            'fk_tipoproducto_id' => 'TRS', 'fk_base_id' => '2', 'servicio_extra' => ['pickup' => 'Hotel Sol', 'dropoff' => '', 'hora_pickup' => '08:30', 'otro' => 'no va'],
        ]))->assertRedirect('/app/reservas/mayorista?codigo=1501');

        $s = DB::table('servicio')->where('fk_reserva_id', DB::table('reserva')->where('codigo', '1501')->value('reserva_id'))->first();
        $this->assertSame('2', (string) $s->fk_base_id);
        $extras = DB::table('servicio_extra')->where('fk_servicio_id', $s->servicio_id)->orderBy('extra_nombre')->pluck('extra_valor', 'extra_nombre')->all();
        $this->assertSame(['hora_pickup' => '08:30', 'pickup' => 'Hotel Sol'], $extras, 'los vacíos y los no permitidos no se graban');
    }

    public function test_cotizador_delega_en_el_tarifador_y_el_alta_persiste_producto_categoria_y_regimen(): void
    {
        $this->postJson('/app/reservas/mayorista/nueva/cotizar', ['producto_id' => 999, 'fecha_ini' => now()->addDays(10)->toDateString(), 'adultos' => 2])
            ->assertOk()->assertJsonPath('ok', false);
        $this->postJson('/app/reservas/mayorista/nueva/cotizar', ['producto_id' => 999, 'adultos' => 2])->assertStatus(422);

        $this->post('/app/reservas/mayorista/nueva', $this->payload([], ['fk_producto_id' => 55, 'fk_tarifacategoria_id' => 7, 'fk_regimen_id' => 3]))
            ->assertRedirect('/app/reservas/mayorista?codigo=1501');
        $s = DB::table('servicio')->where('fk_reserva_id', DB::table('reserva')->where('codigo', '1501')->value('reserva_id'))->first();
        $this->assertSame(55, (int) $s->fk_producto_id);
        $this->assertSame(7, (int) $s->fk_tarifacategoria_id);
        $this->assertSame(3, (int) $s->fk_regimen_id);
        $this->assertSame('TAR', $s->origen);
    }
}
