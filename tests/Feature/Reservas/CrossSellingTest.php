<?php

namespace Tests\Feature\Reservas;

use App\Models\User;
use App\Services\MenuService;
use App\Services\Reservas\CrossSellingService;
use App\Services\Vigencias\VigenciaService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\CreaEsquemaProductos;
use Tests\TestCase;

/**
 * Generador v2, "completar el viaje": complementarios del destino cotizados para
 * las fechas del servicio agregado, primero los que históricamente se vendieron
 * junto (co-ocurrencia en `servicio`), después el resto del destino.
 */
class CrossSellingTest extends TestCase
{
    use CreaEsquemaProductos;

    private int $playa;

    private int $cityTour;

    private int $tigre;

    private int $traslado;

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
        DB::table('submodulo')->insert([['tipoproducto_id' => 'HOT', 'tipoproducto_nombre' => 'Hoteles'], ['tipoproducto_id' => 'EXC', 'tipoproducto_nombre' => 'Excursiones'], ['tipoproducto_id' => 'TRN', 'tipoproducto_nombre' => 'Traslados'], ['tipoproducto_id' => 'ASV', 'tipoproducto_nombre' => 'Asistencia'], ['tipoproducto_id' => 'GUI', 'tipoproducto_nombre' => 'Guías']]);
        DB::table('regimen')->insert([['regimen_id' => 0, 'regimen_nombre' => '']]);
        DB::table('tarifario')->insert([['tarifario_id' => 3, 'fk_sistema_id' => 2, 'tarifario_nombre' => 'Agencias', 'fk_moneda_id' => 'USD', 'cotizacion' => 0]]);
        DB::table('tarifariocomision')->insert(['fk_tarifario_id' => 3, 'fk_submodulo_id' => '0', 'divisor_markup' => 0.8, 'porcentaje_comision' => 0]);
        DB::table('rel_clientesistema')->insert(['fk_cliente_id' => 50, 'fk_sistema_id' => 2, 'fk_tarifario_id' => 3]);
        DB::table('iva')->insert(['fk_sistema_id' => 0, 'fk_submodulo_id' => '', 'fk_pais_id' => 0, 'fk_ciudad_id' => 0, 'fk_producto_id' => 0, 'iva_costo' => 0, 'iva_valor' => 0, 'fk_modoivaventa_id' => 1]);

        $this->playa = $this->producto('HOT', 'Hotel Playa');
        $this->cityTour = $this->tramos('EXC', 'City tour', 100);
        $this->tigre = $this->tramos('EXC', 'Delta del Tigre', 60);
        $this->traslado = $this->tramos('TRN', 'Traslado aeropuerto', 30, ['origen' => 2, 'destino' => 1]);
        $this->tramos('EXC', 'Colonia (otra ciudad)', 10, ['destino' => 2]);

        // Historial: dos reservas con Playa + City tour, una con Playa + traslado.
        foreach ([[101, [$this->cityTour, 'EXC'], [$this->traslado, 'TRN']], [102, [$this->cityTour, 'EXC']], [103, [$this->traslado, 'TRN']]] as $r) {
            [$id, $a, $b] = [$r[0], $r[1], $r[2] ?? null];
            DB::table('reserva')->insert(['reserva_id' => $id, 'tipocodigo' => 'MA', 'codigo' => (string) $id, 'fk_cliente_id' => 50, 'fecha_alta' => now()->subMonths(3)->toDateString()]);
            $this->servicio($id, $this->playa, 'HOT', 1);
            $this->servicio($id, $a[0], $a[1], 1);
            if ($b) {
                $this->servicio($id, $b[0], $b[1], 1);
            }
        }
        // Reserva vieja (fuera de la ventana de 24 meses): no cuenta.
        DB::table('reserva')->insert(['reserva_id' => 104, 'tipocodigo' => 'MA', 'codigo' => '104', 'fk_cliente_id' => 50, 'fecha_alta' => '2020-01-01']);
        $this->servicio(104, $this->playa, 'HOT', 1, '2020-01-01 10:00:00');
        $this->servicio(104, $this->tigre, 'EXC', 1, '2020-01-01 10:00:00');
    }

    private function producto(string $tipo, string $nombre, array $extra = []): int
    {
        $id = $this->productoDePrueba(array_merge(['fk_tipoproducto_id' => $tipo, 'producto_nombre' => $nombre, 'fk_sistema_id' => 2, 'modotarifa' => 'P', 'destino' => 1], $extra));
        DB::table('rel_productociudad')->insert(['fk_producto_id' => $id, 'fk_ciudad_id' => (int) ($extra['destino'] ?? 1)]);

        return $id;
    }

    private function tramos(string $tipo, string $nombre, int $costo, array $extra = []): int
    {
        $id = $this->producto($tipo, $nombre, $extra);
        app(VigenciaService::class)->guardar($id, ['vigencia_ini' => '2026-09-01', 'vigencia_fin' => '2026-12-31', 'dias_semana' => [1, 2, 3, 4, 5, 6, 7], 'moneda_costo' => 'USD', 'residente' => '', 'tramos' => [['min' => 1, 'max' => 9, 'costos' => ['ADU' => $costo]]]]);

        return $id;
    }

    private function servicio(int $reserva, int $producto, string $tipo, int $ciudad, ?string $regdate = null): void
    {
        DB::table('servicio')->insert(['fk_reserva_id' => $reserva, 'fk_producto_id' => $producto, 'fk_tipoproducto_id' => $tipo, 'fk_ciudad_id' => $ciudad, 'status' => 'CO', 'total' => 100, 'regdate' => $regdate ?? now()->subMonths(3)->format('Y-m-d H:i:s')]);
    }

    public function test_coocurrencia_cuenta_reservas_con_el_producto_o_la_ciudad_dentro_de_la_ventana(): void
    {
        $co = app(CrossSellingService::class)->coocurrencia($this->playa, 1, ['EXC', 'TRN', 'ASV'], 2);
        $this->assertSame(2, $co['EXC'][$this->cityTour]);
        $this->assertSame(2, $co['TRN'][$this->traslado]);
        $this->assertArrayNotHasKey($this->tigre, $co['EXC'], 'la reserva de 2020 queda fuera');
        $this->assertArrayNotHasKey('ASV', $co);
    }

    public function test_sugiere_complementarios_del_destino_por_coocurrencia_y_luego_por_ciudad(): void
    {
        $r = $this->postJson('/app/reservas/mayorista/nueva/cross-selling', [
            'tipo' => 'HOT', 'producto_id' => $this->playa, 'ciudad_id' => 1, 'cliente_id' => 50, 'residente' => 'N',
            'from' => '2026-10-05', 'to' => '2026-10-08', 'habitaciones' => [['ad' => 2, 'mn' => []]],
        ])->assertOk()->json();

        $grupos = collect($r['grupos']);
        $this->assertSame(['TRN', 'EXC'], $grupos->pluck('tipo')->all(), 'ASV y GUI sin productos no aparecen; orden del config');

        $exc = $grupos->firstWhere('tipo', 'EXC');
        $this->assertSame('Excursiones', $exc['nombre']);
        $this->assertSame(['City tour', 'Delta del Tigre'], array_column($exc['items'], 'nombre'), 'primero el que se vendió junto, después el más barato del destino; Colonia es de otra ciudad');
        $this->assertSame(2, $exc['items'][0]['coocurrencia']);
        $this->assertSame(0, $exc['items'][1]['coocurrencia']);
        $this->assertEquals(250, $exc['items'][0]['mejor_total'], '100 × 2 ÷ 0,8');
        $this->assertTrue($exc['items'][0]['pickup']);
        $this->assertSame('2026-10-05', $exc['items'][0]['vigencia_fin'], 'excursión de un día en la fecha de inicio');
        $this->assertSame(['adultos' => 2, 'menores' => 0, 'infante' => 0, 'juniors' => 0], $exc['items'][0]['habitaciones'][0]['pax']);

        $trn = $grupos->firstWhere('tipo', 'TRN');
        $this->assertSame(['Traslado aeropuerto'], array_column($trn['items'], 'nombre'));
        $this->assertSame(2, $trn['items'][0]['coocurrencia']);
    }

    public function test_respeta_el_tope_por_tipo_y_no_sugiere_sin_ciudad(): void
    {
        config(['reservas_busqueda.cross_selling.max_por_tipo' => 1]);
        $r = $this->postJson('/app/reservas/mayorista/nueva/cross-selling', [
            'tipo' => 'HOT', 'producto_id' => $this->playa, 'ciudad_id' => 1, 'cliente_id' => 50, 'from' => '2026-10-05', 'to' => '2026-10-08', 'habitaciones' => [['ad' => 2, 'mn' => []]],
        ])->assertOk()->json();
        $this->assertSame(['City tour'], array_column(collect($r['grupos'])->firstWhere('tipo', 'EXC')['items'], 'nombre'));

        $this->postJson('/app/reservas/mayorista/nueva/cross-selling', ['tipo' => 'HOT', 'cliente_id' => 50, 'from' => '2026-10-05'])->assertStatus(422)->assertJsonValidationErrors(['ciudad_id']);

        // Una excursión agregada sugiere traslados y otras excursiones, pero no la misma.
        $r = $this->postJson('/app/reservas/mayorista/nueva/cross-selling', ['tipo' => 'EXC', 'producto_id' => $this->cityTour, 'ciudad_id' => 1, 'cliente_id' => 50, 'from' => '2026-10-05', 'ad' => 2])->assertOk()->json();
        $exc = collect($r['grupos'])->firstWhere('tipo', 'EXC');
        $this->assertSame(['Delta del Tigre'], array_column($exc['items'], 'nombre'));
    }
}
