<?php

namespace Tests\Feature\Reservas;

use App\Models\User;
use App\Services\MenuService;
use App\Services\Reservas\Busqueda\BuscadorAsistencia;
use App\Services\Reservas\BusquedaProductosService;
use App\Services\Vigencias\VigenciaService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\CreaEsquemaProductos;
use Tests\TestCase;

/**
 * Generador v2: buscadores por familia además del alojamiento — circuitos
 * (salida + noches), traslados (origen/destino, pick-up), excursiones (tramos
 * con CHD) y asistencia (días de cobertura, <70 / >70).
 */
class BusquedaTiposTest extends TestCase
{
    use CreaEsquemaProductos;

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
        DB::table('ciudad')->insert([['ciudad_id' => 1, 'ciudad_nombre' => 'Buenos Aires', 'fk_pais_id' => 10], ['ciudad_id' => 2, 'ciudad_nombre' => 'Ezeiza', 'fk_pais_id' => 10]]);
        DB::table('proveedor')->insert([['proveedor_id' => 1, 'proveedor_nombre' => 'Operador']]);
        DB::table('submodulo')->insert([['tipoproducto_id' => 'EXC', 'tipoproducto_nombre' => 'Excursiones'], ['tipoproducto_id' => 'TRN', 'tipoproducto_nombre' => 'Traslados'], ['tipoproducto_id' => 'PAQ', 'tipoproducto_nombre' => 'Circuitos'], ['tipoproducto_id' => 'ASV', 'tipoproducto_nombre' => 'Asistencia']]);
        DB::table('regimen')->insert([['regimen_id' => 0, 'regimen_nombre' => '']]);
        DB::table('tarifario')->insert([['tarifario_id' => 3, 'fk_sistema_id' => 2, 'tarifario_nombre' => 'Agencias', 'fk_moneda_id' => 'USD', 'cotizacion' => 0]]);
        DB::table('tarifariocomision')->insert(['fk_tarifario_id' => 3, 'fk_submodulo_id' => '0', 'divisor_markup' => 0.8, 'porcentaje_comision' => 0]);
        DB::table('rel_clientesistema')->insert(['fk_cliente_id' => 50, 'fk_sistema_id' => 2, 'fk_tarifario_id' => 3]);
        DB::table('iva')->insert(['fk_sistema_id' => 0, 'fk_submodulo_id' => '', 'fk_pais_id' => 0, 'fk_ciudad_id' => 0, 'fk_producto_id' => 0, 'iva_costo' => 0, 'iva_valor' => 0, 'fk_modoivaventa_id' => 1]);
    }

    private function producto(string $tipo, string $nombre, array $extraProducto = [], array $extras = []): int
    {
        $id = $this->productoDePrueba(array_merge(['fk_tipoproducto_id' => $tipo, 'producto_nombre' => $nombre, 'fk_sistema_id' => 2, 'modotarifa' => 'P', 'destino' => 1], $extraProducto), $extras);
        DB::table('rel_productociudad')->insert(['fk_producto_id' => $id, 'fk_ciudad_id' => (int) ($extraProducto['destino'] ?? 1)]);

        return $id;
    }

    private function vigencia(int $id, array $datos): void
    {
        app(VigenciaService::class)->guardar($id, array_merge(['vigencia_ini' => '2026-10-01', 'vigencia_fin' => '2026-12-31', 'dias_semana' => [1, 2, 3, 4, 5, 6, 7], 'moneda_costo' => 'USD', 'residente' => '', 'redondeo' => ' ', 'cargamanual' => 0, 'impuestos' => 0], $datos));
    }

    private function buscar(array $p): array
    {
        return $this->postJson('/app/reservas/mayorista/nueva/buscar', $p + ['cliente_id' => 50, 'residente' => 'N', 'from' => '2026-10-05'])->assertOk()->json();
    }

    public function test_excursion_por_tramos_un_dia_con_pickup(): void
    {
        $exc = $this->producto('EXC', 'City tour');
        $this->vigencia($exc, ['tramos' => [['min' => 1, 'max' => 2, 'costos' => ['ADU' => 100, 'CHD' => 50]], ['min' => 3, 'max' => 9, 'costos' => ['ADU' => 70]]]]);

        $r = $this->buscar(['tipo' => 'EXC', 'ciudad' => 1, 'ad' => 2, 'mn' => [8]]);
        $this->assertCount(1, $r['resultados']);
        $f = $r['resultados'][0];
        $this->assertTrue($f['pickup']);
        $this->assertSame('2026-10-05', $f['vigencia_fin'], 'un día');
        $this->assertCount(1, $f['habitaciones']);
        $this->assertSame(['adultos' => 2, 'menores' => 1, 'infante' => 0, 'juniors' => 0], $f['habitaciones'][0]['pax']);
        // (100×2 + 50) ÷ 0,8 = 312,5 → 313 → 314 (múltiplo de adultos en modo P).
        $this->assertEquals(314, $f['mejor_total']);
        $this->assertSame('De 1 a 2 pasajeros', $f['habitaciones'][0]['opciones'][0]['nombre']);
        $this->assertSame('RQ', $f['disponibilidad']);

        $this->postJson('/app/reservas/mayorista/nueva/buscar', ['tipo' => 'EXC', 'cliente_id' => 50, 'from' => '2026-10-05', 'ciudad' => 1])->assertStatus(422)->assertJsonValidationErrors(['ad']);
    }

    public function test_traslado_filtra_por_origen_y_la_ciudad_del_servicio_es_el_origen(): void
    {
        $trn = $this->producto('TRN', 'Traslado Ezeiza → Hotel', ['origen' => 2, 'destino' => 1, 'disponibilidad' => 'CI']);
        $this->vigencia($trn, ['tramos' => [['min' => 1, 'max' => 3, 'costos' => ['ADU' => 30]]]]);

        $r = $this->buscar(['tipo' => 'TRN', 'origen' => 2, 'ad' => 2]);
        $this->assertCount(1, $r['resultados']);
        $f = $r['resultados'][0];
        $this->assertSame(['id' => 2, 'nombre' => 'Ezeiza'], $f['ciudad']);
        $this->assertTrue($f['pickup']);
        $this->assertSame('CI', $f['disponibilidad'], 'disponibilidad inmediata del producto');
        $this->assertEquals(76, $f['mejor_total'], '60 ÷ 0,8 = 75 → 76');

        $this->assertCount(0, $this->buscar(['tipo' => 'TRN', 'origen' => 1, 'ad' => 2])['resultados'], 'otro origen');
        $this->assertCount(1, $this->buscar(['tipo' => 'TRN', 'origen' => 2, 'destino' => 1, 'ad' => 2])['resultados']);
        $this->assertCount(0, $this->buscar(['tipo' => 'TRN', 'origen' => 2, 'destino' => 2, 'ad' => 2])['resultados'], 'otro destino');
        $this->postJson('/app/reservas/mayorista/nueva/buscar', ['tipo' => 'TRN', 'cliente_id' => 50, 'from' => '2026-10-05', 'ad' => 2])->assertStatus(422)->assertJsonValidationErrors(['origen']);
    }

    public function test_circuito_solo_con_flag_circuito_y_fin_por_noches_de_la_vigencia(): void
    {
        $paq = $this->producto('PAQ', 'Circuito Norte', [], ['circuito' => '1']);
        $otro = $this->producto('PAQ', 'Paquete sin flag', []);
        foreach ([$paq, $otro] as $id) {
            $this->vigencia($id, ['noches' => 4, 'tarifas' => [['categoria' => 0, 'base' => '1', 'costo' => 800], ['categoria' => 0, 'base' => '2', 'costo' => 500]]]);
        }

        $r = $this->buscar(['tipo' => 'PAQ', 'ciudad' => 1, 'habitaciones' => [['ad' => 2, 'mn' => []]]]);
        $this->assertSame(['Circuito Norte'], array_column($r['resultados'], 'nombre'), 'sólo circuitos');
        $f = $r['resultados'][0];
        $this->assertSame('2026-10-09', $f['vigencia_fin'], 'salida + 4 noches');
        $this->assertEquals(1250, $f['mejor_total'], '500 × 2 adultos, una noche, ÷ 0,8');
        $this->assertFalse($f['pickup']);

        config(['reservas_busqueda.tipos.PAQ.solo_circuito' => false]);
        Cache::store('array')->flush();
        $this->assertCount(2, $this->buscar(['tipo' => 'PAQ', 'ciudad' => 1, 'habitaciones' => [['ad' => 2, 'mn' => []]]])['resultados']);
    }

    public function test_asistencia_por_dias_de_cobertura_y_mayores_de_70(): void
    {
        $asv = $this->producto('ASV', 'Assist Card');
        $this->vigencia($asv, ['tramos' => [['min' => 1, 'max' => 7, 'costos' => ['<70' => 10, '>70' => 20]], ['min' => 1, 'max' => 15, 'costos' => ['<70' => 18, '>70' => 36]]]]);
        // Otro sistema: no aparece aunque no se filtre por ciudad.
        $this->producto('ASV', 'Otro sistema', ['fk_sistema_id' => 1]);

        // 7 días exactos, 3 adultos de los cuales 1 mayor de 70: (10×2 + 20) ÷ 0,8 = 50.
        $r = $this->buscar(['tipo' => 'ASV', 'to' => '2026-10-11', 'ad' => 3, 'mayores70' => 1]);
        $this->assertSame(['Assist Card'], array_column($r['resultados'], 'nombre'));
        $f = $r['resultados'][0];
        $this->assertEquals(50, $f['mejor_total']);
        $this->assertSame('CI', $f['disponibilidad']);
        $this->assertSame('2026-10-11', $f['vigencia_fin']);
        $this->assertSame(['adultos' => 3, 'menores' => 0, 'infante' => 0, 'juniors' => 0], $f['habitaciones'][0]['pax']);
        $this->assertSame(7, $f['noches']);
        $op = $f['habitaciones'][0]['opciones'][0];
        $this->assertEquals(40, $op['costosiniva']);
        $this->assertEquals(0, $op['iva']);
        $this->assertStringContainsString('1 >70', $op['nombre']);

        // Sin `to` no se puede buscar asistencia.
        $this->postJson('/app/reservas/mayorista/nueva/buscar', ['tipo' => 'ASV', 'cliente_id' => 50, 'from' => '2026-10-05', 'ad' => 2])->assertStatus(422)->assertJsonValidationErrors(['to']);
    }

    public function test_reparto_de_dias_de_cobertura_como_el_ci(): void
    {
        $b = app(BuscadorAsistencia::class);
        $t = [7 => ['<70' => ['costo' => 10.0, 'iva' => 0.0, 'moneda' => 'USD']], 15 => ['<70' => ['costo' => 18.0, 'iva' => 0.0, 'moneda' => 'USD']]];
        $this->assertEquals(10, $b->porDias($t, 7)['<70']['costo'], 'exacto');
        $this->assertEquals(100, $b->porDias($t, 10)['<70']['costo'], 'entre coberturas: la menor cobertura (7) por cada día');
        $this->assertEquals(18, $b->porDias($t, 20)['<70']['costo'], 'más que la máxima: 1 × 15 días; sin tarifa de 1 día no suma el resto');
        $t[1] = ['<70' => ['costo' => 2.0, 'iva' => 0.0, 'moneda' => 'USD']];
        $this->assertEquals(18 + 5 * 2, $b->porDias($t, 20)['<70']['costo'], '15 + 5 días sueltos a la tarifa de 1 día');
        $this->assertEquals(3 * 2, $b->porDias($t, 3)['<70']['costo'], 'menos que 7 días: la menor cobertura (1) por día');
        $this->assertSame([], $b->porDias([], 5));
    }

    public function test_pestanas_incluyen_todas_las_familias_con_productos(): void
    {
        $this->producto('EXC', 'City tour');
        $this->producto('TRN', 'Traslado', ['origen' => 2]);
        $this->producto('ASV', 'Assist');
        $p = collect(app(BusquedaProductosService::class)->pestanas(2, true));
        $this->assertSame(['EXC', 'TRN', 'ASV'], $p->pluck('tipo')->all(), 'orden del config');
        $this->assertTrue($p->firstWhere('tipo', 'TRN')['pickup']);
        $this->assertContains('origen', $p->firstWhere('tipo', 'TRN')['campos']);
        $this->assertContains('mayores70', $p->firstWhere('tipo', 'ASV')['campos']);
    }
}
