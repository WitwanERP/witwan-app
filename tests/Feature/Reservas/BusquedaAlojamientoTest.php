<?php

namespace Tests\Feature\Reservas;

use App\Models\User;
use App\Services\MenuService;
use App\Services\Reservas\BusquedaProductosService;
use App\Services\Vigencias\VigenciaService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\CreaEsquemaProductos;
use Tests\TestCase;

/**
 * Generador v2, paso "Buscar": candidatos por ciudad/nombre/estrellas, una
 * cotización por habitación, orden por mejor total, truncado y cache.
 * Las tarifas se cargan con VigenciaService como en TarifadorTest.
 */
class BusquedaAlojamientoTest extends TestCase
{
    use CreaEsquemaProductos;

    private int $centro;

    private int $playa;

    protected function setUp(): void
    {
        parent::setUp();
        $this->crearEsquemaProductos();
        app()->instance('tenant', (object) ['base' => 'witwan_rays', 'pais' => 'AR', 'licencia' => 1, 'row' => (object) ['licencia_nombre' => 'Rays']]);
        config(['ci.redirect_guests' => false, 'inertia.pages.paths' => [resource_path('js/Pages')], 'reservas_busqueda.cache_store' => 'array']);
        $this->mock(MenuService::class)->shouldReceive('forUser')->andReturn([]);
        Cache::store('array')->flush();

        DB::table('tipousuario')->insert(['tipousuario_id' => 'POW', 'tipousuario_nombre' => 'Superadmin']);
        DB::table('usuario')->insert(['usuario_id' => 7, 'usuario_nombre' => 'Ana', 'usuario_mail' => 'ana@x.com', 'fk_tipousuario_id' => 'POW', 'usuario_interno' => 'Y']);
        $this->actingAs(User::find(7), 'web');

        DB::table('moneda')->insert([['moneda_id' => 'ARS', 'moneda_basica' => 'Y'], ['moneda_id' => 'USD', 'moneda_basica' => 'N']]);
        DB::table('cotizacion')->insert(['cotizacion_moneda' => 'USD', 'cotizacion_fecha' => '2026-01-01', 'cotizacion_costo' => 1000, 'cotizacion_relacion' => 1000]);
        DB::table('sistema')->insert([['sistema_id' => 2, 'extra1' => 0, 'extra2' => 0]]);
        DB::table('ciudad')->insert([['ciudad_id' => 1, 'ciudad_nombre' => 'Buenos Aires', 'fk_pais_id' => 10], ['ciudad_id' => 2, 'ciudad_nombre' => 'Córdoba', 'fk_pais_id' => 10]]);
        DB::table('proveedor')->insert([['proveedor_id' => 1, 'proveedor_nombre' => 'Marriott']]);
        DB::table('submodulo')->insert([['tipoproducto_id' => 'HOT', 'tipoproducto_nombre' => 'Hoteles'], ['tipoproducto_id' => 'EXC', 'tipoproducto_nombre' => 'Excursiones']]);
        DB::table('regimen')->insert([['regimen_id' => 0, 'regimen_nombre' => 'Sin régimen'], ['regimen_id' => 3, 'regimen_nombre' => 'Desayuno']]);
        DB::table('hotelcategoria')->insert([['hotelcategoria_id' => 4, 'hotelcategoria_nombre' => '4 estrellas', 'hotelcategoria_stars' => 4], ['hotelcategoria_id' => 5, 'hotelcategoria_nombre' => '5 estrellas', 'hotelcategoria_stars' => 5]]);
        DB::table('tarifacategoria')->insert([['tarifacategoria_id' => 7, 'fk_submodulo_id' => 'HOT', 'tarifacategoria_nombre' => 'Standard'], ['tarifacategoria_id' => 8, 'fk_submodulo_id' => 'HOT', 'tarifacategoria_nombre' => 'Suite']]);
        DB::table('tarifario')->insert([['tarifario_id' => 3, 'fk_sistema_id' => 2, 'tarifario_nombre' => 'Agencias', 'fk_moneda_id' => 'USD', 'cotizacion' => 0]]);
        DB::table('tarifariocomision')->insert(['fk_tarifario_id' => 3, 'fk_submodulo_id' => '0', 'divisor_markup' => 0.8, 'porcentaje_comision' => 0]);
        DB::table('rel_clientesistema')->insert(['fk_cliente_id' => 50, 'fk_sistema_id' => 2, 'fk_tarifario_id' => 3]);
        DB::table('iva')->insert(['fk_sistema_id' => 0, 'fk_submodulo_id' => '', 'fk_pais_id' => 0, 'fk_ciudad_id' => 0, 'fk_producto_id' => 0, 'iva_costo' => 0, 'iva_valor' => 0, 'fk_modoivaventa_id' => 1]);

        // Dos hoteles en Buenos Aires (4 y 5 estrellas), uno en Córdoba, uno deshabilitado y uno no visible para externos.
        $this->centro = $this->hotel('Hotel Centro', 1, 4, ['costo1' => 100, 'costo2' => 80, 'suite' => true]);
        $this->playa = $this->hotel('Hotel Playa', 1, 5, ['costo1' => 60, 'costo2' => 50]);
        $this->hotel('Hotel Córdoba', 2, 4, ['costo1' => 10, 'costo2' => 10]);
        $this->hotel('Hotel Cerrado', 1, 4, ['costo1' => 1, 'costo2' => 1], ['habilitar' => 0]);
        $this->hotel('Hotel Interno', 1, 4, ['costo1' => 5, 'costo2' => 5], ['aparece_tarifario' => 0]);
    }

    private function hotel(string $nombre, int $ciudad, int $estrellas, array $t, array $extraProducto = []): int
    {
        $id = $this->productoDePrueba(array_merge(['producto_nombre' => $nombre, 'fk_sistema_id' => 2, 'modotarifa' => 'P', 'disponibilidad' => '', 'destino' => $ciudad, 'politica_cancelacion' => 'Sin cargo hasta 7 días antes.'], $extraProducto), ['edad_infoa' => 2, 'edad_menor1' => 11, 'edad_junior' => 17, 'fk_hotelcategoria_id' => $estrellas]);
        DB::table('rel_productociudad')->insert(['fk_producto_id' => $id, 'fk_ciudad_id' => $ciudad]);
        DB::table('rel_productobase')->insert([['fk_producto_id' => $id, 'fk_base_id' => 1], ['fk_producto_id' => $id, 'fk_base_id' => 2]]);
        DB::table('alojamientohabitacion')->insert(['fk_producto_id' => $id, 'fk_tarifacategoria_id' => 7, 'alojamientohabitacion_nombre' => '', 'capacidad' => 3, 'min_adultos' => 1, 'max_adultos' => 2, 'max_child' => 2, 'max_adultos_child' => 2, 'min_adultos_child' => 1, 'habilitar' => 1, 'orden' => 1]);
        $tarifas = [
            ['categoria' => 7, 'base' => '1', 'costo' => $t['costo1']], ['categoria' => 7, 'base' => '2', 'costo' => $t['costo2']],
            ['categoria' => 7, 'base' => 'INF', 'costo' => 0], ['categoria' => 7, 'base' => 'MN', 'costo' => 40], ['categoria' => 7, 'base' => 'JNR', 'costo' => 60],
        ];
        if (! empty($t['suite'])) {
            DB::table('alojamientohabitacion')->insert(['fk_producto_id' => $id, 'fk_tarifacategoria_id' => 8, 'alojamientohabitacion_nombre' => 'Suite', 'capacidad' => 2, 'min_adultos' => 1, 'max_adultos' => 2, 'max_child' => 0, 'max_adultos_child' => 0, 'min_adultos_child' => 0, 'habilitar' => 1, 'orden' => 2]);
            $tarifas[] = ['categoria' => 8, 'base' => '1', 'costo' => 200];
            $tarifas[] = ['categoria' => 8, 'base' => '2', 'costo' => 150];
        }
        app(VigenciaService::class)->guardar($id, [
            'vigencia_ini' => '2026-10-01', 'vigencia_fin' => '2026-12-31', 'dias_semana' => [1, 2, 3, 4, 5, 6, 7], 'fk_regimen_id' => 3,
            'moneda_costo' => 'USD', 'redondeo' => ' ', 'residente' => '', 'cargamanual' => 0, 'impuestos' => 0, 'tarifas' => $tarifas,
        ]);

        return $id;
    }

    private function params(array $extra = []): array
    {
        return $extra + ['tipo' => 'HOT', 'cliente_id' => 50, 'residente' => 'N', 'from' => '2026-10-05', 'to' => '2026-10-08', 'ciudad' => 1, 'habitaciones' => [['ad' => 2, 'mn' => []], ['ad' => 1, 'mn' => [5]]]];
    }

    public function test_busca_por_ciudad_cotiza_cada_habitacion_y_ordena_por_mejor_total(): void
    {
        $r = $this->postJson('/app/reservas/mayorista/nueva/buscar', $this->params())->assertOk()->json();

        $this->assertSame('HOT', $r['tipo']);
        $this->assertFalse($r['truncado']);
        $this->assertSame(3, $r['candidatos'], 'Centro, Playa e Interno (usuario interno): Córdoba y Cerrado no');
        $this->assertSame(['Hotel Interno', 'Hotel Playa', 'Hotel Centro'], array_column($r['resultados'], 'nombre'), 'ordenados por mejor total');
        $this->assertSame(3, $r['contexto']['tarifario_id']);

        $playa = $r['resultados'][1];
        $this->assertSame(5.0, (float) $playa['estrellas']);
        $this->assertSame('Marriott', $playa['proveedor']['nombre']);
        $this->assertSame(['id' => 1, 'nombre' => 'Buenos Aires'], $playa['ciudad']);
        $this->assertSame('RQ', $playa['disponibilidad'], 'sin cupo cargado ni disponibilidad CI');
        $this->assertSame('USD', $playa['moneda']);
        $this->assertSame(3, $playa['noches']);
        $this->assertSame('2026-10-08', $playa['vigencia_fin']);
        $this->assertCount(2, $playa['habitaciones']);
        // Hab 1: 2 adultos × 50 × 3 noches = 300 ÷ 0,8 = 375 → 376 (modo P redondea a múltiplo de adultos). Hab 2: 1 adulto 60×3=180 + menor 40×3=120 = 300 ÷ 0,8 = 375. Total 751.
        $this->assertEquals(751, $playa['mejor_total']);
        $h2 = $playa['habitaciones'][1];
        $this->assertSame(['adultos' => 1, 'menores' => 1, 'infante' => 0, 'juniors' => 0], $h2['pax']);
        $this->assertSame('1', $h2['fk_base_id']);
        $this->assertSame([5], $h2['edades']);
        $this->assertSame(['categoria' => 7, 'regimen' => 3], $h2['mejor']);
        $this->assertSame('Desayuno', $h2['opciones'][0]['regimen_nombre']);
        $this->assertSame('Standard', $h2['opciones'][0]['nombre']);

        // Centro tiene Suite sólo para la habitación sin menor: 2 opciones en la hab 1, 1 en la hab 2.
        $centro = $r['resultados'][2];
        $this->assertCount(2, $centro['habitaciones'][0]['opciones']);
        $this->assertCount(1, $centro['habitaciones'][1]['opciones']);
        $this->assertSame('Sin cargo hasta 7 días antes.', $centro['politica_cancelacion']);
    }

    public function test_filtra_por_nombre_estrellas_y_oculta_a_externos_lo_que_no_aparece_en_tarifario(): void
    {
        $r = $this->postJson('/app/reservas/mayorista/nueva/buscar', $this->params(['nombre' => 'playa']))->assertOk()->json();
        $this->assertSame(['Hotel Playa'], array_column($r['resultados'], 'nombre'));

        $r = $this->postJson('/app/reservas/mayorista/nueva/buscar', $this->params(['stars' => [5]]))->assertOk()->json();
        $this->assertSame(['Hotel Playa'], array_column($r['resultados'], 'nombre'));

        DB::table('usuario')->where('usuario_id', 7)->update(['usuario_interno' => 'N', 'fk_tipousuario_id' => 'AGE']);
        auth()->user()->refresh();
        Cache::store('array')->flush();
        // Externo: fecha mínima +4 hábiles, así que hay que buscar más adelante; "Hotel Interno" desaparece.
        $r = $this->postJson('/app/reservas/mayorista/nueva/buscar', $this->params(['from' => '2026-11-02', 'to' => '2026-11-05']))->assertOk()->json();
        $this->assertSame(['Hotel Playa', 'Hotel Centro'], array_column($r['resultados'], 'nombre'));

        $this->postJson('/app/reservas/mayorista/nueva/buscar', $this->params(['from' => now()->addDay()->toDateString(), 'to' => now()->addDays(2)->toDateString()]))->assertStatus(422)->assertJsonValidationErrors(['from']);
    }

    public function test_valida_obligatorios_por_tipo_y_alternativas(): void
    {
        $this->postJson('/app/reservas/mayorista/nueva/buscar', ['tipo' => 'HOT', 'cliente_id' => 50, 'from' => '2026-10-05', 'to' => '2026-10-08'])
            ->assertStatus(422)->assertJsonValidationErrors(['habitaciones', 'ciudad']);
        $this->postJson('/app/reservas/mayorista/nueva/buscar', $this->params(['tipo' => 'XXX']))->assertStatus(422)->assertJsonValidationErrors(['tipo']);
        $this->postJson('/app/reservas/mayorista/nueva/buscar', $this->params(['to' => '2026-10-01']))->assertStatus(422)->assertJsonValidationErrors(['to']);
    }

    public function test_truncado_por_tope_de_candidatos_y_cache_de_la_respuesta(): void
    {
        config(['reservas_busqueda.max_candidatos' => 1]);
        $r = $this->postJson('/app/reservas/mayorista/nueva/buscar', $this->params())->assertOk()->json();
        $this->assertTrue($r['truncado']);
        $this->assertSame(1, $r['candidatos']);
        $this->assertFalse($r['cache']);

        $r2 = $this->postJson('/app/reservas/mayorista/nueva/buscar', $this->params())->assertOk()->json();
        $this->assertTrue($r2['cache'], 'misma búsqueda, misma respuesta cacheada');
    }

    public function test_pestanas_solo_tipos_con_productos_y_buscador(): void
    {
        $this->productoDePrueba(['producto_nombre' => 'City tour', 'fk_tipoproducto_id' => 'EXC', 'fk_sistema_id' => 2]);
        $this->productoDePrueba(['producto_nombre' => 'Paquete sin buscador registrado', 'fk_tipoproducto_id' => 'ZZZ', 'fk_sistema_id' => 2]);
        $p = app(BusquedaProductosService::class)->pestanas(2, true);
        $tipos = array_column($p, 'tipo');
        $this->assertContains('HOT', $tipos);
        $this->assertNotContains('ZZZ', $tipos);
        $hot = collect($p)->firstWhere('tipo', 'HOT');
        $this->assertSame('Hoteles', $hot['nombre']);
        $this->assertContains('habitaciones', $hot['campos']);
        $this->assertFalse($hot['pickup']);
        $this->assertSame([], app(BusquedaProductosService::class)->pestanas(1, true), 'otro sistema sin productos');

        $this->getJson('/app/reservas/mayorista/nueva/productos?q=hotel&tipo=HOT')->assertOk()->assertJsonCount(4, null)->assertJsonPath('0.label', 'Hotel Centro');
    }
}
