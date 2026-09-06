<?php

namespace Tests\Feature\Operaciones;

use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\CreaEsquemaConfiguracion;
use Tests\TestCase;

/**
 * Smoke HTTP de Operaciones: autorizar reservas (lista, detalle, actualizar,
 * autorizar), planilla de guardia (lista, planilla, export) y tráfico (lista,
 * guardar cambios, prorratear, confirmar aviso).
 */
class OperacionesTest extends TestCase
{
    use CreaEsquemaConfiguracion;

    protected function setUp(): void
    {
        parent::setUp();
        $this->crearEsquemaConfiguracion();
        $this->autenticarPow();

        DB::table('cliente')->insert(['cliente_id' => 3, 'cliente_nombre' => 'Agencia Sol', 'cliente_telefono' => '11-5555']);
        DB::table('proveedor')->insert([
            ['proveedor_id' => 5, 'proveedor_nombre' => 'Hotel Sol', 'proveedor_telefono' => '4444', 'proveedor_telefonoemergencia' => '9999', 'habilita' => 'Y'],
            ['proveedor_id' => 6, 'proveedor_nombre' => 'Transfers SA', 'proveedor_telefono' => '3333', 'proveedor_telefonoemergencia' => '8888', 'habilita' => 'Y'],
        ]);
        DB::table('guia')->insert(['guia_id' => 2, 'guia_nombre' => 'Marta', 'guia_apellido' => 'Ruiz', 'fk_ciudad_id' => 0]);
        DB::table('sistema')->insert(['sistema_id' => 1, 'sistema_nombre' => 'Receptivo']);
        DB::table('moneda')->insert(['moneda_id' => 'USD', 'moneda_nombre' => 'Dólar', 'moneda_basica' => 'N']);
        DB::table('submodulo')->insert([['tipoproducto_id' => 'HOT', 'tipoproducto_nombre' => 'Hoteles'], ['tipoproducto_id' => 'TRN', 'tipoproducto_nombre' => 'Traslados']]);

        $hoy = now()->toDateString();
        DB::table('reserva')->insert([
            ['reserva_id' => 21, 'tipocodigo' => 'RE', 'codigo' => '100', 'fk_cliente_id' => 3, 'fk_usuario_id' => 7, 'agente' => 7, 'fk_sistema_id' => 1, 'fk_filestatus_id' => 'CL', 'titular_apellido' => 'Pérez', 'titular_nombre' => 'Ana', 'fk_moneda_id' => 'USD', 'total' => 1000, 'autorizado' => 0, 'observaciones' => 'Llegan tarde'],
            ['reserva_id' => 22, 'tipocodigo' => 'RE', 'codigo' => '101', 'fk_cliente_id' => 3, 'fk_usuario_id' => 7, 'agente' => 7, 'fk_sistema_id' => 2, 'fk_filestatus_id' => 'CO', 'titular_apellido' => 'López', 'titular_nombre' => 'Juan', 'fk_moneda_id' => 'USD', 'total' => 500, 'autorizado' => 0, 'observaciones' => ''],
            ['reserva_id' => 23, 'tipocodigo' => 'RE', 'codigo' => '102', 'fk_cliente_id' => 3, 'fk_usuario_id' => 7, 'agente' => 7, 'fk_sistema_id' => 1, 'fk_filestatus_id' => 'CL', 'titular_apellido' => 'Viejo', 'titular_nombre' => 'File', 'fk_moneda_id' => 'USD', 'total' => 100, 'autorizado' => 1, 'observaciones' => ''],
        ]);
        foreach ([
            ['servicio_id' => 11, 'servicio_nombre' => 'Hotel Sol dbl', 'fk_reserva_id' => 21, 'fk_tipoproducto_id' => 'HOT', 'fk_producto_id' => 9, 'fk_proveedor_id' => 5, 'status' => 'CO', 'vigencia_ini' => now()->addDays(3)->toDateString(), 'vigencia_fin' => now()->addDays(6)->toDateString(), 'adultos' => 2, 'menores' => 1, 'costo' => 700, 'moneda_costo' => 'USD', 'nro_confirmacion' => 'CONF1', 'comentarios' => base64_encode('Habitación alta')],
            ['servicio_id' => 12, 'servicio_nombre' => 'Traslado in', 'fk_reserva_id' => 21, 'fk_tipoproducto_id' => 'TRN', 'fk_producto_id' => 0, 'fk_proveedor_id' => 6, 'status' => 'CO', 'vigencia_ini' => now()->addDays(3)->toDateString(), 'vigencia_fin' => now()->addDays(3)->toDateString(), 'adultos' => 3, 'menores' => 0, 'costo' => 50, 'moneda_costo' => 'USD'],
            ['servicio_id' => 13, 'servicio_nombre' => 'Cancelado', 'fk_reserva_id' => 21, 'fk_tipoproducto_id' => 'HOT', 'fk_producto_id' => 0, 'fk_proveedor_id' => 5, 'status' => 'CA', 'vigencia_ini' => $hoy, 'vigencia_fin' => $hoy, 'adultos' => 1],
            ['servicio_id' => 14, 'servicio_nombre' => 'Hotel otro sistema', 'fk_reserva_id' => 22, 'fk_tipoproducto_id' => 'HOT', 'fk_producto_id' => 0, 'fk_proveedor_id' => 5, 'status' => 'CO', 'vigencia_ini' => now()->addDays(2)->toDateString(), 'vigencia_fin' => now()->addDays(4)->toDateString(), 'adultos' => 2],
            ['servicio_id' => 15, 'servicio_nombre' => 'Hotel viejo', 'fk_reserva_id' => 23, 'fk_tipoproducto_id' => 'HOT', 'fk_producto_id' => 0, 'fk_proveedor_id' => 5, 'status' => 'CO', 'vigencia_ini' => '2024-01-01', 'vigencia_fin' => '2024-01-03', 'adultos' => 1],
        ] as $fila) {
            DB::table('servicio')->insert($fila);
        }
        DB::table('servicio_extra')->insert([
            ['fk_servicio_id' => 12, 'extra_nombre' => 'pickup_nvuelo', 'extra_valor' => 'AR1234'],
            ['fk_servicio_id' => 12, 'extra_nombre' => 'pickup_horario', 'extra_valor' => '08:30'],
            ['fk_servicio_id' => 12, 'extra_nombre' => 'idioma', 'extra_valor' => 'ES'],
        ]);
        DB::table('producto')->insert(['producto_id' => 9, 'producto_nombre' => 'Hotel Sol dbl', 'fk_proveedor_id' => 5]);
        DB::table('vigencia')->insert(['fk_producto_id' => 9, 'vigencia_ini' => '2020-01-01', 'vigencia_fin' => '2030-12-31']);
    }

    // -------------------------------------------------------------- autorizar

    public function test_autorizar_sin_filtros_lista_no_autorizados_proximos_21_dias(): void
    {
        $this->get('/app/operaciones/autorizar/receptivo')
            ->assertOk()
            ->assertInertia(fn (Assert $p) => $p->component('Operaciones/Autorizar')
                ->where('conFiltros', false)
                ->has('filas', 1)
                ->where('filas.0.codigo', 'RE-100')
                ->where('filas.0.titular', 'Pérez, Ana')
                ->where('filas.0.pax', 3)
                ->where('filas.0.autorizado', false));

        // Con filtro autorizado=1 aparece el file ya autorizado (sin la ventana de 21 días).
        $this->get('/app/operaciones/autorizar/receptivo?autorizado=1')
            ->assertInertia(fn (Assert $p) => $p->has('filas', 1)->where('filas.0.codigo', 'RE-102'));

        $this->get('/app/operaciones/autorizar/otraarea')->assertNotFound();
    }

    public function test_detalle_actualiza_datos_y_autoriza(): void
    {
        $this->get('/app/operaciones/autorizar/receptivo/21')
            ->assertOk()
            ->assertInertia(fn (Assert $p) => $p->component('Operaciones/AutorizarFile')
                ->where('reserva.codigo', 'RE-100')
                ->where('reserva.observaciones', 'Llegan tarde')
                ->has('servicios', 2)
                ->where('servicios.0.nombre', 'Hotel Sol dbl')->where('servicios.0.editable', false)
                ->where('servicios.1.tipo', 'TRN')->where('servicios.1.editable', true)->where('servicios.1.vuelo', 'AR1234')->where('servicios.1.horario', '08:30'));

        $this->post('/app/operaciones/autorizar/receptivo/21/actualizar', [
            'fk_guia_id' => 2,
            'servicios' => [
                ['servicio_id' => 11, 'ocultaritinerario' => 1],
                ['servicio_id' => 12, 'ocultaritinerario' => 0, 'fk_proveedor_id' => 5, 'horario' => '09:15', 'vuelo' => ''],
            ],
        ])->assertRedirect();

        $this->assertDatabaseHas('reserva', ['reserva_id' => 21, 'fk_guia_id' => 2]);
        $this->assertDatabaseHas('servicio_extra', ['fk_servicio_id' => 11, 'extra_nombre' => 'ocultaritinerario', 'extra_valor' => '1']);
        $this->assertDatabaseHas('servicio', ['servicio_id' => 12, 'fk_proveedor_id' => 5]);
        $this->assertDatabaseHas('servicio_extra', ['fk_servicio_id' => 12, 'extra_nombre' => 'pickup_horario', 'extra_valor' => '09:15']);
        // El vuelo vacío borra la clave (set_extra no guarda vacíos).
        $this->assertDatabaseMissing('servicio_extra', ['fk_servicio_id' => 12, 'extra_nombre' => 'pickup_nvuelo']);

        $this->post('/app/operaciones/autorizar/receptivo/21/autorizar')->assertRedirect('/app/operaciones/autorizar/receptivo');
        $this->assertDatabaseHas('reserva', ['reserva_id' => 21, 'autorizado' => 1]);
        $this->assertDatabaseHas('reserva_extra', ['fk_reserva_id' => 21, 'extra_nombre' => 'autorizado', 'extra_valor' => '7']);
        $this->assertDatabaseHas('reserva_extra', ['fk_reserva_id' => 21, 'extra_nombre' => 'autorizadofecha']);
    }

    // ---------------------------------------------------------------- guardia

    public function test_guardia_lista_files_del_area_en_rango_y_genera_planilla(): void
    {
        $this->get('/app/operaciones/guardia/receptivo')
            ->assertOk()
            ->assertInertia(fn (Assert $p) => $p->component('Operaciones/Guardia')
                ->where('filtros.from', now()->toDateString())
                ->has('filas', 1)
                ->where('filas.0.codigo', 'RE-100')
                ->where('filas.0.emergencia', '9999')
                ->where('filas.0.observaciones', 'Llegan tarde'));

        // Filtro por tipo de producto TRN sigue encontrando el file por su traslado.
        $this->get('/app/operaciones/guardia/receptivo?tipo[]=TRN')->assertInertia(fn (Assert $p) => $p->has('filas', 1));
        $this->get('/app/operaciones/guardia/receptivo?tipo[]=EXC')->assertInertia(fn (Assert $p) => $p->has('filas', 0));

        $this->post('/app/operaciones/guardia/receptivo/reporte', ['ids' => [21, 23], 'vini' => now()->toDateString(), 'vfin' => now()->addDays(15)->toDateString(), 'resumida' => 0])
            ->assertOk()
            ->assertInertia(fn (Assert $p) => $p->component('Operaciones/GuardiaReporte')
                ->has('planilla', 2)
                ->where('planilla.0.codigo', 'RE-100')
                ->where('planilla.0.telefono_cliente', '11-5555')
                ->where('planilla.0.guia', '')
                ->has('planilla.0.servicios', 2)
                ->where('planilla.0.servicios.0.comentarios', 'Habitación alta')
                ->where('planilla.0.servicios.0.en_rango', true)
                ->where('planilla.0.servicios.1.vuelo.pickup_nvuelo', 'AR1234')
                ->where('planilla.1.servicios.0.en_rango', false));

        // Resumida: sólo servicios dentro del rango => el file viejo queda sin servicios.
        $this->post('/app/operaciones/guardia/receptivo/reporte', ['ids' => [23], 'vini' => now()->toDateString(), 'vfin' => now()->toDateString(), 'resumida' => 1])
            ->assertInertia(fn (Assert $p) => $p->has('planilla.0.servicios', 0));

        $csv = $this->post('/app/operaciones/guardia/receptivo/reporte', ['ids' => [21], 'vini' => now()->toDateString(), 'vfin' => now()->addDays(15)->toDateString(), 'export' => 1]);
        $csv->assertOk()->assertHeader('content-type', 'text/csv; charset=UTF-8');
        $this->assertStringContainsString('RE-100;"Ana Pérez"', $csv->streamedContent());
        $this->assertStringContainsString('CONF1', $csv->streamedContent());
    }

    // ---------------------------------------------------------------- tráfico

    public function test_trafico_lista_con_extras_y_guarda_cambios(): void
    {
        $this->get('/app/operaciones/trafico/receptivo')
            ->assertOk()
            ->assertInertia(fn (Assert $p) => $p->component('Operaciones/Trafico')->where('consultado', false)->where('filas', []));

        $this->get('/app/operaciones/trafico/receptivo?from='.now()->toDateString())
            ->assertInertia(fn (Assert $p) => $p
                ->where('consultado', true)
                ->has('filas', 2)
                ->where('filas.0.codigo', 'RE-100')
                ->where('filas.0.servicio', 'Hotel Sol dbl')
                ->where('filas.0.proveedores', [['value' => 5, 'label' => 'Hotel Sol']])
                ->where('filas.1.idioma', 'ES')
                ->where('filas.1.codigo_vuelo', 'AR1234')
                ->where('filas.1.dropoff_hora', '08:30'));

        $this->post('/app/operaciones/trafico/receptivo/guardar', [
            'accion' => 'guardarcambios',
            'ids' => [12],
            'servicios' => [12 => ['idioma' => 'EN', 'pickup' => 'Aeropuerto EZE', 'dropoff' => 'Hotel Sol', 'dropoff_hora' => '10:00', 'codigo_vuelo' => 'LA800', 'comentario' => 'Cartel con nombre', 'proveedor' => 6]],
            'qst' => ['from' => now()->toDateString()],
        ])->assertRedirect('/app/operaciones/trafico/receptivo?from='.now()->toDateString());

        $this->assertDatabaseHas('servicio_extra', ['fk_servicio_id' => 12, 'extra_nombre' => 'idioma', 'extra_valor' => 'EN']);
        $this->assertDatabaseHas('servicio_extra', ['fk_servicio_id' => 12, 'extra_nombre' => 'pickup', 'extra_valor' => 'Aeropuerto EZE']);
        $this->assertDatabaseHas('servicio_extra', ['fk_servicio_id' => 12, 'extra_nombre' => 'dropoff_horario', 'extra_valor' => '10:00']);
        $this->assertDatabaseHas('servicio_extra', ['fk_servicio_id' => 12, 'extra_nombre' => 'codigo_vuelo', 'extra_valor' => 'LA800']);
        $this->assertDatabaseHas('servicio', ['servicio_id' => 12, 'comentarios' => 'Cartel con nombre']);
        $this->assertSame(1, DB::table('servicio_extra')->where('fk_servicio_id', 12)->where('extra_nombre', 'idioma')->count());
    }

    public function test_trafico_prorratea_y_confirma_aviso(): void
    {
        // 700 entre 3 pax (servicio 11) + 3 pax (servicio 12) = 116.67 por pax.
        $this->post('/app/operaciones/trafico/receptivo/guardar', [
            'accion' => 'prorratear', 'ids' => [11, 12], 'pro_proveedor' => 6, 'pro_costo' => 700, 'pro_ivacosto' => 60, 'pro_moneda' => 'ARS',
        ])->assertRedirect();

        $s11 = DB::table('servicio')->where('servicio_id', 11)->first();
        $this->assertSame('350.01', (string) $s11->costo);
        $this->assertSame('30.00', (string) $s11->iva_costo);
        $this->assertSame(6, (int) $s11->fk_proveedor_id);
        $this->assertSame('ARS', $s11->moneda_costo);

        $this->post('/app/operaciones/trafico/receptivo/guardar', [
            'accion' => 'confirmar', 'ids' => [12], 'pickup_avisado_entre' => '08:00', 'pickup_avisado_hasta' => '08:30', 'trafico_contacto' => 'Pedro',
        ])->assertRedirect();
        $this->assertDatabaseHas('servicio_extra', ['fk_servicio_id' => 12, 'extra_nombre' => 'trafico_contacto', 'extra_valor' => 'Pedro']);

        $this->post('/app/operaciones/trafico/receptivo/guardar', ['accion' => 'reasignar', 'ids' => [12]])->assertRedirect()->assertSessionHas('error');

        // Tras el prorrateo el servicio 11 quedó en el proveedor 6.
        $this->get('/app/operaciones/trafico/receptivo/productos/6')->assertOk()->assertJson([['value' => 9, 'label' => 'Hotel Sol dbl']]);
        $this->get('/app/operaciones/trafico/receptivo/productos/5')->assertOk()->assertExactJson([]);
    }
}
