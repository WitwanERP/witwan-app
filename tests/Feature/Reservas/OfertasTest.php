<?php

namespace Tests\Feature\Reservas;

use App\Models\User;
use App\Services\MenuService;
use App\Services\Vigencias\VigenciaService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\CreaEsquemaProductos;
use Tests\TestCase;

/**
 * Generador v2, ofertas sobre el producto elegido: fechas cercanas más baratas
 * (respetando la fecha mínima), promociones vigentes del destino (vigencias y
 * destacados) y lo que el cliente pagó por lo mismo.
 */
class OfertasTest extends TestCase
{
    use CreaEsquemaProductos;

    private int $playa;

    private int $centro;

    private string $from;

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
        DB::table('ciudad')->insert([['ciudad_id' => 1, 'ciudad_nombre' => 'Buenos Aires', 'fk_pais_id' => 10]]);
        DB::table('proveedor')->insert([['proveedor_id' => 1, 'proveedor_nombre' => 'Marriott']]);
        DB::table('submodulo')->insert([['tipoproducto_id' => 'HOT', 'tipoproducto_nombre' => 'Hoteles']]);
        DB::table('regimen')->insert([['regimen_id' => 3, 'regimen_nombre' => 'Desayuno']]);
        DB::table('tarifacategoria')->insert([['tarifacategoria_id' => 7, 'fk_submodulo_id' => 'HOT', 'tarifacategoria_nombre' => 'Standard']]);
        DB::table('tarifario')->insert([['tarifario_id' => 3, 'fk_sistema_id' => 2, 'tarifario_nombre' => 'Agencias', 'fk_moneda_id' => 'USD', 'cotizacion' => 0]]);
        DB::table('tarifariocomision')->insert(['fk_tarifario_id' => 3, 'fk_submodulo_id' => '0', 'divisor_markup' => 0.8, 'porcentaje_comision' => 0]);
        DB::table('rel_clientesistema')->insert(['fk_cliente_id' => 50, 'fk_sistema_id' => 2, 'fk_tarifario_id' => 3]);
        DB::table('iva')->insert(['fk_sistema_id' => 0, 'fk_submodulo_id' => '', 'fk_pais_id' => 0, 'fk_ciudad_id' => 0, 'fk_producto_id' => 0, 'iva_costo' => 0, 'iva_valor' => 0, 'fk_modoivaventa_id' => 1]);

        // Interno: fecha mínima = hoy. La estadía arranca mañana, así ±2 y ±3 hacia atrás caen antes de la mínima.
        $this->from = now()->addDay()->toDateString();
        $this->playa = $this->hotel('Hotel Playa', 50);
        $this->centro = $this->hotel('Hotel Centro', 80);
        // Vigencia promocional de mayor prioridad para Playa: cubre from+2..from+4 a 40 con 3x2.
        app(VigenciaService::class)->guardar($this->playa, [
            'vigencia_ini' => now()->addDays(3)->toDateString(), 'vigencia_fin' => now()->addDays(5)->toDateString(), 'dias_semana' => [1, 2, 3, 4, 5, 6, 7], 'fk_regimen_id' => 3,
            'vigencia_prioridad' => 1, 'promocional' => 1, 'nota_promocion' => 'Oferta primavera', 'promo_noches' => 3, 'promo_pornoches' => 2,
            'moneda_costo' => 'USD', 'redondeo' => ' ', 'residente' => '', 'cargamanual' => 0, 'impuestos' => 0,
            'tarifas' => [['categoria' => 7, 'base' => '1', 'costo' => 60], ['categoria' => 7, 'base' => '2', 'costo' => 40]],
        ]);
        DB::table('destacado')->insert(['destacado_nombre' => 'Escapada a Buenos Aires', 'fk_producto_id' => $this->centro]);

        // El cliente 50 ya compró Playa hace dos meses: 3 noches, 2 adultos, 700 USD.
        DB::table('reserva')->insert(['reserva_id' => 21, 'tipocodigo' => 'MA', 'codigo' => '900', 'fk_cliente_id' => 50, 'escotizacion' => 0, 'fecha_alta' => now()->subMonths(2)->toDateString(), 'total' => 700, 'fk_moneda_id' => 'USD']);
        DB::table('servicio')->insert(['fk_reserva_id' => 21, 'fk_producto_id' => $this->playa, 'fk_tipoproducto_id' => 'HOT', 'fk_ciudad_id' => 1, 'status' => 'CO', 'vigencia_ini' => '2026-07-10', 'vigencia_fin' => '2026-07-13', 'adultos' => 2, 'menores' => 0, 'total' => 700, 'fk_moneda_id' => 'USD']);
    }

    private function hotel(string $nombre, int $costoDoble): int
    {
        $id = $this->productoDePrueba(['producto_nombre' => $nombre, 'fk_sistema_id' => 2, 'modotarifa' => 'P', 'disponibilidad' => '', 'destino' => 1], ['edad_infoa' => 2, 'edad_menor1' => 11, 'edad_junior' => 17]);
        DB::table('rel_productociudad')->insert(['fk_producto_id' => $id, 'fk_ciudad_id' => 1]);
        DB::table('rel_productobase')->insert([['fk_producto_id' => $id, 'fk_base_id' => 1], ['fk_producto_id' => $id, 'fk_base_id' => 2]]);
        DB::table('alojamientohabitacion')->insert(['fk_producto_id' => $id, 'fk_tarifacategoria_id' => 7, 'alojamientohabitacion_nombre' => '', 'capacidad' => 3, 'min_adultos' => 1, 'max_adultos' => 2, 'max_child' => 2, 'max_adultos_child' => 2, 'min_adultos_child' => 1, 'habilitar' => 1, 'orden' => 1]);
        app(VigenciaService::class)->guardar($id, [
            'vigencia_ini' => now()->subMonth()->toDateString(), 'vigencia_fin' => now()->addMonths(4)->toDateString(), 'dias_semana' => [1, 2, 3, 4, 5, 6, 7], 'fk_regimen_id' => 3,
            'moneda_costo' => 'USD', 'redondeo' => ' ', 'residente' => '', 'cargamanual' => 0, 'impuestos' => 0,
            'tarifas' => [['categoria' => 7, 'base' => '1', 'costo' => $costoDoble + 20], ['categoria' => 7, 'base' => '2', 'costo' => $costoDoble]],
        ]);

        return $id;
    }

    private function params(array $extra = []): array
    {
        return $extra + [
            'tipo' => 'HOT', 'cliente_id' => 50, 'residente' => 'N', 'from' => $this->from, 'to' => now()->addDays(4)->toDateString(), 'ciudad' => 1,
            'habitaciones' => [['ad' => 2, 'mn' => []]], 'producto_id' => $this->playa, 'ciudad_id' => 1, 'elegidas' => [['categoria' => 7, 'regimen' => 3]], 'total_elegido' => 376, 'moneda' => 'USD',
        ];
    }

    public function test_fechas_cercanas_respetan_la_minima_y_detectan_la_promocion(): void
    {
        $r = $this->postJson('/app/reservas/mayorista/nueva/ofertas', $this->params(['que' => ['fechas']]))->assertOk()->json();

        $fechas = collect($r['fechas_cercanas']);
        $this->assertSame([-1, 1, 2, 3], $fechas->pluck('delta')->all(), '-2 y -3 caen antes de la fecha mínima');
        $menos1 = $fechas->firstWhere('delta', -1);
        $this->assertEquals(376, $menos1['total'], '50 × 2 × 3 noches ÷ 0,8 = 375 → 376');
        $this->assertEquals(0, $menos1['diferencia']);
        $this->assertSame('RQ', $menos1['disponibilidad']);
        $mas2 = $fechas->firstWhere('delta', 2);
        // Las 3 noches caen en la vigencia promo: 40 × 2 × 3 = 240 − 3x2 (40 × 2) = 160 ÷ 0,8 = 200.
        $this->assertEquals(200, $mas2['total']);
        $this->assertEquals(-176, $mas2['diferencia']);
        $this->assertSame(now()->addDays(3)->toDateString(), $mas2['from']);
        $this->assertSame(now()->addDays(6)->toDateString(), $mas2['to'], 'misma duración');
        $this->assertSame([], $r['promociones']);
        $this->assertNull($r['historial']);
    }

    public function test_promociones_del_destino_y_destacados(): void
    {
        $r = $this->postJson('/app/reservas/mayorista/nueva/ofertas', $this->params(['que' => ['promociones']]))->assertOk()->json();

        $promos = collect($r['promociones']);
        $this->assertCount(2, $promos);
        $vig = $promos->firstWhere('origen', 'vigencia');
        $this->assertSame('Hotel Playa', $vig['nombre']);
        $this->assertSame('Oferta primavera', $vig['nota']);
        $this->assertSame('3 X 2', $vig['promo']);
        $des = $promos->firstWhere('origen', 'destacado');
        $this->assertSame('Hotel Centro', $des['nombre']);
        $this->assertSame('Escapada a Buenos Aires', $des['nota']);

        // Fuera de las fechas de la promo sólo queda el destacado.
        $r = $this->postJson('/app/reservas/mayorista/nueva/ofertas', $this->params(['que' => ['promociones'], 'from' => now()->addDays(30)->toDateString(), 'to' => now()->addDays(33)->toDateString()]))->assertOk()->json();
        $this->assertSame(['destacado'], array_column($r['promociones'], 'origen'));
    }

    public function test_historial_del_cliente_mismo_producto_y_mismo_tipo_en_la_ciudad(): void
    {
        $r = $this->postJson('/app/reservas/mayorista/nueva/ofertas', $this->params(['que' => ['historial']]))->assertOk()->json();

        $h = $r['historial'];
        $this->assertCount(1, $h['mismo_producto']);
        $this->assertSame('MA-900', $h['mismo_producto'][0]['codigo']);
        $this->assertEquals(700, $h['mismo_producto'][0]['total']);
        $this->assertSame(3, $h['mismo_producto'][0]['noches']);
        $this->assertEquals(116.67, $h['mismo_producto'][0]['por_pax_noche'], '700 / (2 pax × 3 noches)');
        $this->assertSame(1, $h['mismo_tipo_ciudad']['n']);
        $this->assertEquals(700, $h['mismo_tipo_ciudad']['promedio']);
        $this->assertSame('USD', $h['mismo_tipo_ciudad']['moneda']);
        $this->assertEquals(376 - 700, $h['mismo_tipo_ciudad']['diferencia']);

        // Otro cliente sin historial.
        DB::table('rel_clientesistema')->insert(['fk_cliente_id' => 51, 'fk_sistema_id' => 2, 'fk_tarifario_id' => 3]);
        $r = $this->postJson('/app/reservas/mayorista/nueva/ofertas', $this->params(['que' => ['historial'], 'cliente_id' => 51]))->assertOk()->json();
        $this->assertSame([], $r['historial']['mismo_producto']);
        $this->assertNull($r['historial']['mismo_tipo_ciudad']);
    }

    public function test_exige_producto_y_el_resto_de_la_busqueda(): void
    {
        $this->postJson('/app/reservas/mayorista/nueva/ofertas', ['tipo' => 'HOT', 'cliente_id' => 50, 'from' => $this->from, 'to' => now()->addDays(4)->toDateString(), 'habitaciones' => [['ad' => 2]]])
            ->assertStatus(422)->assertJsonValidationErrors(['producto_id']);
    }
}
