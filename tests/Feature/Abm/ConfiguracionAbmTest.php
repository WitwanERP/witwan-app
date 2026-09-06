<?php

namespace Tests\Feature\Abm;

use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\CreaEsquemaConfiguracion;
use Tests\TestCase;

/**
 * Smoke HTTP de los ABMs config-driven de Configuración: cada uno lista con
 * el componente genérico, crea, edita y elimina sobre su tabla legacy.
 */
class ConfiguracionAbmTest extends TestCase
{
    use CreaEsquemaConfiguracion;

    protected function setUp(): void
    {
        parent::setUp();
        $this->crearEsquemaConfiguracion();
        $this->autenticarPow();

        DB::table('submodulo')->insert([
            ['tipoproducto_id' => 'HOT', 'tipoproducto_nombre' => 'Hoteles'],
            ['tipoproducto_id' => 'EXC', 'tipoproducto_nombre' => 'Excursiones'],
        ]);
        DB::table('pais')->insert(['pais_id' => 10, 'pais_nombre' => 'Argentina']);
        DB::table('ciudad')->insert([
            ['ciudad_id' => 1, 'ciudad_nombre' => 'Buenos Aires', 'fk_pais_id' => 10, 'fk_ciudad_id' => 0],
            ['ciudad_id' => 2, 'ciudad_nombre' => 'Palermo', 'fk_pais_id' => 10, 'fk_ciudad_id' => 1],
        ]);
        DB::table('proveedor')->insert([['proveedor_id' => 5, 'proveedor_nombre' => 'Hotelbeds']]);
        DB::table('plancuenta')->insert([['plancuenta_id' => 54, 'plancuenta_nombre' => 'Caja', 'plancuenta_codigo' => '1.1.1']]);
    }

    /** @return array<string, array{string,string,string,array<string,mixed>,array<string,mixed>}> */
    public static function abms(): array
    {
        return [
            'tipos de alojamiento' => ['config/tipos-alojamiento', 'alojamientotipo', 'alojamientotipo_id', ['alojamientotipo_nombre' => 'Hostel'], ['alojamientotipo_nombre' => 'Hostal']],
            'tarjetas' => ['config/tarjetas-credito', 'tarjetacredito', 'tarjetacredito_id', ['tarjetacredito_nombre' => 'VISA'], ['tarjetacredito_nombre' => 'AMEX']],
            'grupos pais' => ['config/grupos-pais', 'grupopais', 'grupopais_id', ['grupopais_nombre' => 'Mercosur'], ['grupopais_nombre' => 'Caribe']],
            'regimenes' => ['config/regimenes', 'regimen', 'regimen_id', ['regimen_nombre' => 'Desayuno', 'regimen_nombre_en' => 'Breakfast'], ['regimen_nombre' => 'Media pensión']],
            'cadenas cliente' => ['config/cadenas-cliente', 'cadenacliente', 'cadenacliente_id', ['cadenacliente_nombre' => 'Despegar'], ['cadenacliente_nombre' => 'Almundo']],
            'cadenas hoteleras' => ['config/cadenas-hoteleras', 'cadenahotelera', 'cadenahotelera_id', ['cadenahotelera_nombre' => 'Hilton'], ['cadenahotelera_nombre' => 'Marriott']],
            'centros costo' => ['config/centros-costo', 'centrocosto', 'centrocosto_id', ['centrocosto_nombre' => 'Ventas', 'centrocosto_codigo' => 'V1', 'centrocosto_activo' => 1], ['centrocosto_nombre' => 'Compras', 'centrocosto_codigo' => 'C1', 'centrocosto_activo' => 0]],
            'tipos habitacion' => ['config/tipos-habitacion', 'tarifacategoria', 'tarifacategoria_id', ['tarifacategoria_nombre' => 'Standard', 'fk_submodulo_id' => 'HOT'], ['tarifacategoria_nombre' => 'Superior', 'fk_submodulo_id' => 'EXC']],
            'facilidades' => ['config/facilidades', 'alojamientofacilidad', 'alojamientofacilidad_id', ['alojamientofacilidad_nombre' => 'Wifi', 'fk_submodulo_id' => 'HOT'], ['alojamientofacilidad_nombre' => 'Pileta', 'fk_submodulo_id' => 'HOT']],
            'tags' => ['config/tags', 'tag', 'tag_id', ['tag_nombre' => 'VIP', 'tag_ruc' => 1, 'tag_rup' => 0], ['tag_nombre' => 'Corporativo', 'tag_ruc' => 1, 'tag_rup' => 1]],
            'formas de pago' => ['config/formas-pago', 'formapago', 'formapago_id', ['formapago_nombre' => 'Efectivo', 'fk_plancuenta_id' => 54], ['formapago_nombre' => 'Transferencia', 'fk_plancuenta_id' => 54]],
            'guias' => ['config/guias', 'guia', 'guia_id', ['guia_nombre' => 'Juan', 'guia_apellido' => 'Pérez', 'fk_ciudad_id' => 1], ['guia_nombre' => 'Ana', 'guia_apellido' => 'López', 'fk_ciudad_id' => 1]],
            'interfases' => ['config/interfases', 'interfases', 'interfases_id', ['interfases_nombre' => 'HB', 'fk_proveedor_id' => 5, 'interfases_mup' => 0.85, 'interfases_activo' => 1, 'interfases_release' => 3, 'interfases_penalidad' => 0, 'interfases_receptivo' => 0, 'interfases_mayorista' => 1], ['interfases_nombre' => 'Hotelbeds', 'fk_proveedor_id' => 5, 'interfases_mup' => 0.8, 'interfases_activo' => 1, 'interfases_release' => 2, 'interfases_penalidad' => 10, 'interfases_receptivo' => 1, 'interfases_mayorista' => 1]],
        ];
    }

    /** @dataProvider abms */
    public function test_ciclo_completo_de_abm(string $slug, string $tabla, string $pk, array $alta, array $edicion): void
    {
        $this->get("/app/{$slug}")
            ->assertOk()
            ->assertInertia(fn (Assert $p) => $p->component('Abm/Index')->where('config.baseUrl', "/app/{$slug}"));

        $this->get("/app/{$slug}/create")
            ->assertOk()
            ->assertInertia(fn (Assert $p) => $p->component('Abm/Form')->has('config.campos'));

        $this->post("/app/{$slug}", $alta)->assertRedirect("/app/{$slug}");
        $this->assertDatabaseHas($tabla, $this->soloTexto($alta));
        $id = DB::table($tabla)->max($pk);

        $this->get("/app/{$slug}/{$id}/edit")
            ->assertOk()
            ->assertInertia(fn (Assert $p) => $p->component('Abm/Form')->where("registro.{$pk}", $id));

        $this->put("/app/{$slug}/{$id}", $edicion)->assertRedirect("/app/{$slug}");
        $this->assertDatabaseHas($tabla, $this->soloTexto($edicion) + [$pk => $id]);

        $this->delete("/app/{$slug}/{$id}")->assertRedirect("/app/{$slug}");
        $this->assertDatabaseMissing($tabla, [$pk => $id]);
    }

    public function test_campo_requerido_vuelve_con_error(): void
    {
        $this->from('/app/config/tags/create')
            ->post('/app/config/tags', ['tag_nombre' => ''])
            ->assertRedirect('/app/config/tags/create')
            ->assertSessionHasErrors('tag_nombre');
    }

    public function test_listado_muestra_labels_de_opciones_y_booleanos(): void
    {
        DB::table('tarifacategoria')->insert(['tarifacategoria_nombre' => 'Doble', 'fk_submodulo_id' => 'HOT']);
        DB::table('tag')->insert(['tag_nombre' => 'VIP', 'tag_ruc' => 1, 'tag_rup' => 0]);

        $this->get('/app/config/tipos-habitacion')
            ->assertInertia(fn (Assert $p) => $p->where('registros.data.0.fk_submodulo_id', 'Hoteles'));

        $this->get('/app/config/tags')
            ->assertInertia(fn (Assert $p) => $p->where('registros.data.0.tag_ruc', 'Sí')->where('registros.data.0.tag_rup', 'No'));

        // El filtro select por FK sigue funcionando sobre el valor crudo.
        $this->get('/app/config/tipos-habitacion?fk_submodulo_id=EXC')
            ->assertInertia(fn (Assert $p) => $p->where('registros.total', 0));
    }

    public function test_puntos_de_interes_solo_lista_subciudades_y_hereda_pais(): void
    {
        $this->get('/app/config/puntos-interes')
            ->assertInertia(fn (Assert $p) => $p
                ->where('registros.total', 1)
                ->where('registros.data.0.ciudad_nombre', 'Palermo')
                ->where('registros.data.0.fk_ciudad_id', 'Buenos Aires'));

        $this->post('/app/config/puntos-interes', ['ciudad_nombre' => 'Aeroparque', 'fk_ciudad_id' => 1])->assertRedirect();
        $this->assertDatabaseHas('ciudad', ['ciudad_nombre' => 'Aeroparque', 'fk_ciudad_id' => 1, 'fk_pais_id' => 10, 'ciudad_activo' => 1]);
    }

    public function test_interfases_oculta_inactivas_como_el_ci(): void
    {
        DB::table('interfases')->insert([
            ['interfases_nombre' => 'Activa', 'interfases_activo' => 1],
            ['interfases_nombre' => 'Apagada', 'interfases_activo' => 0],
        ]);

        $this->get('/app/config/interfases')
            ->assertInertia(fn (Assert $p) => $p->where('registros.total', 1)->where('registros.data.0.interfases_nombre', 'Activa'));
    }

    public function test_archivos_adjuntos_lista_con_codigo_y_usuario_y_solo_elimina(): void
    {
        DB::table('reserva')->insert(['reserva_id' => 30, 'tipocodigo' => 'RE', 'codigo' => '1001']);
        DB::table('filearchivo')->insert(['filearchivo_id' => 3, 'fk_file_id' => 30, 'filearchivo_archivo' => 'voucher.pdf', 'fk_usuario_id' => 7]);

        $this->get('/app/config/archivos-adjuntos?codigo=RE-10')
            ->assertOk()
            ->assertInertia(fn (Assert $p) => $p
                ->where('config.acciones', ['eliminar'])
                ->where('registros.total', 1)
                ->where('registros.data.0.codigo', 'RE-1001')
                ->where('registros.data.0.usuario', 'Ana Pow'));

        $this->get('/app/config/archivos-adjuntos/create')->assertNotFound();
        $this->post('/app/config/archivos-adjuntos', ['filearchivo_archivo' => 'x'])->assertNotFound();

        $this->delete('/app/config/archivos-adjuntos/3')->assertRedirect('/app/config/archivos-adjuntos');
        $this->assertDatabaseMissing('filearchivo', ['filearchivo_id' => 3]);
    }

    /** Sólo las claves comparables con assertDatabaseHas (evita decimales con redondeo). */
    private function soloTexto(array $datos): array
    {
        return array_filter($datos, fn ($v) => ! is_float($v));
    }
}
