<?php

namespace Tests\Feature\Productos;

use App\Models\User;
use App\Services\MenuService;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\CreaEsquemaProductos;
use Tests\TestCase;

/**
 * Smoke de la capa HTTP: cada pantalla Inertia responde 200 con su componente
 * y sus props, los POST redirigen o devuelven errores con la clave del campo, y
 * los endpoints JSON responden lo que consume el Vue.
 *
 * Se entra por `localhost` (ResolveTenant no consulta brain) con un usuario
 * POW autenticado en el guard web (AuthenticateFromCiSession no redirige) y
 * las secciones fijadas por config (Secciones no consulta brain).
 */
class PantallasTest extends TestCase
{
    use CreaEsquemaProductos;

    private int $hotel;

    protected function setUp(): void
    {
        parent::setUp();
        $this->crearEsquemaProductos();

        app()->instance('tenant', (object) ['base' => 'witwan_rays', 'pais' => 'AR', 'licencia' => 1, 'row' => (object) ['licencia_nombre' => 'Rays']]);
        config([
            'secciones.overrides' => ['productos/hotel' => 901, 'productos/excursion' => 902, 'vigencia' => 903, 'tarifario' => 904, 'producto/vercupo' => 905],
            'productos.permisos_estrictos' => true,
            'ci.redirect_guests' => false,
            // El repo usa resources/js/Pages (mayúscula); el default de inertia es js/pages.
            'inertia.pages.paths' => [resource_path('js/Pages')],
        ]);
        $this->mock(MenuService::class)->shouldReceive('forUser')->andReturn([]);

        DB::table('tipousuario')->insert(['tipousuario_id' => 'POW', 'tipousuario_nombre' => 'Superadmin']);
        DB::table('usuario')->insert(['usuario_id' => 7, 'usuario_nombre' => 'Ana', 'usuario_mail' => 'ana@x.com', 'fk_tipousuario_id' => 'POW']);
        $this->actingAs(User::find(7), 'web');

        DB::table('moneda')->insert([['moneda_id' => 'ARS', 'moneda_nombre' => 'Peso', 'moneda_basica' => 'Y'], ['moneda_id' => 'USD', 'moneda_nombre' => 'Dólar', 'moneda_basica' => 'N']]);
        DB::table('proveedor')->insert(['proveedor_id' => 10, 'proveedor_nombre' => 'Marriott']);
        DB::table('ciudad')->insert(['ciudad_id' => 1, 'ciudad_nombre' => 'Buenos Aires', 'fk_pais_id' => 10]);
        DB::table('pais')->insert(['pais_id' => 10, 'pais_nombre' => 'Argentina']);
        DB::table('submodulo')->insert(['tipoproducto_id' => 'HOT', 'tipoproducto_nombre' => 'Hoteles']);
        DB::table('tarifacategoria')->insert(['tarifacategoria_id' => 7, 'fk_submodulo_id' => 'HOT', 'tarifacategoria_nombre' => 'Standard']);
        DB::table('tarifario')->insert(['tarifario_id' => 3, 'fk_sistema_id' => 1, 'tarifario_nombre' => 'Agencias', 'fk_moneda_id' => 'USD']);
        DB::table('tarifariocomision')->insert(['fk_tarifario_id' => 3, 'fk_submodulo_id' => '0', 'divisor_markup' => 0.8, 'porcentaje_comision' => 10]);

        $this->hotel = $this->productoDePrueba(['producto_nombre' => 'Hotel Centro', 'fk_proveedor_id' => 10], ['edad_infoa' => 2, 'edad_menor1' => 11]);
        DB::table('rel_productociudad')->insert(['fk_producto_id' => $this->hotel, 'fk_ciudad_id' => 1]);
        DB::table('alojamientohabitacion')->insert(['fk_producto_id' => $this->hotel, 'fk_tarifacategoria_id' => 7, 'max_child' => 1, 'habilitar' => 1]);
    }

    // ------------------------------------------------------------------ productos

    public function test_listado_de_productos(): void
    {
        $this->get('/app/productos/receptivo/hotel?producto_nombre=centro')
            ->assertOk()
            ->assertInertia(fn (Assert $p) => $p
                ->component('Productos/Index')
                ->where('config.tipo', 'HOT')
                ->where('config.baseUrl', '/app/productos/receptivo/hotel')
                ->where('config.permisos.alta', true)
                ->where('filtros.producto_nombre', 'centro')
                ->has('registros.data', 1)
                ->where('registros.data.0.proveedor_nombre', 'Marriott')
                ->has('opciones.proveedores', 1));
    }

    public function test_slug_desconocido_es_404(): void
    {
        $this->get('/app/productos/receptivo/pkd')->assertNotFound();
        $this->get('/app/productos/otro/hotel')->assertNotFound();
    }

    public function test_formulario_de_producto_trae_campos_y_registro(): void
    {
        $this->get('/app/productos/receptivo/hotel/create')
            ->assertOk()
            ->assertInertia(fn (Assert $p) => $p->component('Productos/Form')->where('registro', null)->has('campos')->has('opciones.tarifacategorias', 1));

        $this->get("/app/productos/receptivo/hotel/{$this->hotel}/edit")
            ->assertOk()
            ->assertInertia(fn (Assert $p) => $p
                ->component('Productos/Form')
                ->where('registro.producto_nombre', 'Hotel Centro')
                ->where('registro.edades.edad_infoa', 2)
                ->has('registro.habitaciones', 1)
                ->has('vigencias', 0));

        // Un hotel no se edita por la URL de excursiones.
        $this->get("/app/productos/receptivo/excursion/{$this->hotel}/edit")->assertNotFound();
    }

    public function test_alta_y_edicion_de_producto_por_http(): void
    {
        $r = $this->post('/app/productos/receptivo/hotel', [
            'producto_nombre' => 'Hotel Nuevo', 'fk_proveedor_id' => 10, 'modotarifa' => 'H', 'destino' => 1,
            'edad_infoa' => 2, 'edad_menor1' => 11, 'bases' => [1, 2],
            'habitaciones' => [['fk_tarifacategoria_id' => 7, 'nombre' => 'Std', 'capacidad' => 2, 'max_child' => 1]],
        ]);
        $id = (int) DB::table('producto')->where('producto_nombre', 'Hotel Nuevo')->value('producto_id');
        $r->assertRedirect("/app/productos/receptivo/hotel/{$id}/edit")->assertSessionHas('success');
        $this->assertSame(1, DB::table('alojamientohabitacion')->where('fk_producto_id', $id)->count());

        $this->put("/app/productos/receptivo/hotel/{$id}", ['producto_nombre' => 'Hotel Nuevo II', 'fk_proveedor_id' => 10, 'modotarifa' => 'P', 'destino' => 1])
            ->assertRedirect()->assertSessionHas('success');
        $this->assertSame('Hotel Nuevo II', DB::table('producto')->where('producto_id', $id)->value('producto_nombre'));
    }

    public function test_errores_de_forma_y_de_negocio_vuelven_con_la_clave_del_campo(): void
    {
        $this->from('/app/productos/receptivo/hotel/create')
            ->post('/app/productos/receptivo/hotel', ['producto_nombre' => '', 'fk_proveedor_id' => 0, 'edad_infoa' => 99])
            ->assertRedirect('/app/productos/receptivo/hotel/create')
            ->assertSessionHasErrors(['producto_nombre', 'fk_proveedor_id', 'edad_infoa']);

        $this->from('/app/productos/receptivo/hotel/create')
            ->post('/app/productos/receptivo/hotel', ['producto_nombre' => 'X', 'fk_proveedor_id' => 10, 'modotarifa' => 'S', 'destino' => 0])
            ->assertSessionHasErrors(['modotarifa', 'destino']);

        $this->assertSame(1, DB::table('producto')->count());
    }

    public function test_clonar_y_baja_por_http(): void
    {
        $r = $this->post("/app/productos/receptivo/hotel/{$this->hotel}/clonar", ['nombre' => 'Copia']);
        $nuevo = (int) DB::table('producto')->where('producto_nombre', 'Copia')->value('producto_id');
        $r->assertRedirect("/app/productos/receptivo/hotel/{$nuevo}/edit");

        $this->delete("/app/productos/receptivo/hotel/{$nuevo}")->assertRedirect('/app/productos/receptivo/hotel');
        $this->assertSame(1, (int) DB::table('producto')->where('producto_id', $nuevo)->value('eliminar'));

        $this->getJson("/app/productos/receptivo/hotel/{$this->hotel}/habitaciones")->assertOk()->assertJsonPath('0.fk_tarifacategoria_id', 7);
    }

    // ------------------------------------------------------------------ vigencias

    private function vigencia(array $extra = []): array
    {
        return array_merge([
            'vigencia_ini' => '2026-10-01', 'vigencia_fin' => '2026-12-31', 'dias_semana' => [1, 2, 3, 4, 5, 6, 7],
            'moneda_costo' => 'USD', 'redondeo' => 'R', 'residente' => '', 'cargamanual' => 1,
            'tarifas' => [['categoria' => 7, 'base' => '1', 'costo' => 100, 'venta' => [3 => 150]], ['categoria' => 7, 'base' => 'MN', 'costo' => 40]],
        ], $extra);
    }

    public function test_pantallas_de_vigencia(): void
    {
        $this->get("/app/productos/{$this->hotel}/vigencias")
            ->assertOk()
            ->assertInertia(fn (Assert $p) => $p->component('Productos/Vigencia/Index')->where('producto.url', "/app/productos/receptivo/hotel/{$this->hotel}/edit")->has('vigencias', 0));

        $this->get("/app/productos/{$this->hotel}/vigencias/create")
            ->assertOk()
            ->assertInertia(fn (Assert $p) => $p
                ->component('Productos/Vigencia/Form')
                ->where('vigencia.vigencia_id', 0)
                ->where('grilla.modo', 'alojamiento')
                ->where('grilla.filas.0.bases', ['1', '2', '3', 'INF', 'MN'])
                ->where('grilla.tarifarios.0.divisor_markup', 0.8)
                ->has('monedas', 2)
                ->where('catalogos.redondeo.R', 'Por aproximación'));

        $this->get('/app/productos/9999/vigencias/create')->assertNotFound();
    }

    public function test_alta_edicion_preview_y_baja_de_vigencia_por_http(): void
    {
        $r = $this->post("/app/productos/{$this->hotel}/vigencias", $this->vigencia());
        $vid = (int) DB::table('vigencia')->value('vigencia_id');
        $r->assertRedirect("/app/productos/{$this->hotel}/vigencias/{$vid}/edit")->assertSessionHas('success');
        $this->assertSame(3, DB::table('tarifa')->where('fk_vigencia_id', $vid)->count());

        $this->get("/app/productos/{$this->hotel}/vigencias/{$vid}/edit")
            ->assertOk()
            ->assertInertia(fn (Assert $p) => $p->component('Productos/Vigencia/Form')->where('vigencia.moneda_costo', 'USD')->where('grilla.filas.0.celdas.0.costo', fn ($v) => (float) $v === 100.0)->where('grilla.filas.0.celdas.0.venta.3', fn ($v) => (float) $v === 150.0));

        $this->put("/app/productos/{$this->hotel}/vigencias/{$vid}", $this->vigencia(['tarifas' => [['categoria' => 7, 'base' => '1', 'costo' => 110]]]))
            ->assertRedirect()->assertSessionHas('success', fn ($m) => str_contains($m, '1 modificadas, 2 borradas'));

        $this->from("/app/productos/{$this->hotel}/vigencias/{$vid}/edit")
            ->put("/app/productos/{$this->hotel}/vigencias/{$vid}", $this->vigencia(['vigencia_fin' => '2026-01-01', 'tarifas' => [['categoria' => 7, 'base' => 'JNR', 'costo' => 1]]]))
            ->assertSessionHasErrors(['vigencia_fin', 'tarifas.0']);

        $this->postJson("/app/productos/{$this->hotel}/vigencias/preview-venta", ['moneda_costo' => 'USD', 'residente' => '', 'redondeo' => 'R', 'costos' => [['clave' => '7_1', 'costo' => 100]]])
            ->assertOk()
            ->assertJsonPath('tarifarios.3.divisor_markup', 0.8)
            ->assertJsonPath('celdas.7_1.3.venta', 125);

        $this->post("/app/productos/{$this->hotel}/vigencias/{$vid}/clonar", ['desplazar_meses' => 12]);
        $this->assertSame(2, DB::table('vigencia')->count());

        $this->delete("/app/productos/{$this->hotel}/vigencias/{$vid}")->assertRedirect("/app/productos/receptivo/hotel/{$this->hotel}/edit");
        $this->assertSame(0, DB::table('tarifa')->where('fk_vigencia_id', $vid)->count());
    }

    // ------------------------------------------------------------------ tarifarios

    public function test_tarifarios_por_http(): void
    {
        $this->get('/app/tarifarios/receptivo')->assertOk()
            ->assertInertia(fn (Assert $p) => $p->component('Tarifarios/Index')->has('registros', 1)->where('registros.0.divisor_markup', fn ($v) => (float) $v === 0.8));

        $this->get('/app/tarifarios/receptivo/create')->assertOk()
            ->assertInertia(fn (Assert $p) => $p->component('Tarifarios/Form')->has('submodulos', 1)->has('paises', 1));

        $this->post('/app/tarifarios/receptivo', ['tarifario_nombre' => 'Directo', 'fk_moneda_id' => 'ARS', 'comisiones' => [['fk_submodulo_id' => '', 'divisor_markup' => 0.9, 'porcentaje_comision' => 0]]])
            ->assertRedirect()->assertSessionHas('success');
        $id = (int) DB::table('tarifario')->where('tarifario_nombre', 'Directo')->value('tarifario_id');
        $this->assertSame(1, DB::table('tarifariocomision')->where('fk_tarifario_id', $id)->count());

        $this->from("/app/tarifarios/receptivo/{$id}/edit")
            ->put("/app/tarifarios/receptivo/{$id}", ['tarifario_nombre' => 'Directo', 'fk_moneda_id' => 'ARS', 'comisiones' => [['divisor_markup' => 0]]])
            ->assertSessionHasErrors(['comisiones.0.divisor_markup']);

        $this->delete("/app/tarifarios/receptivo/{$id}")->assertRedirect('/app/tarifarios/receptivo');
        $this->assertNull(DB::table('tarifario')->where('tarifario_id', $id)->first());
    }

    // ------------------------------------------------------------------ cupos

    public function test_cupos_por_http(): void
    {
        $base = "/app/productos/{$this->hotel}/cupos/7";

        $this->get("{$base}?mes=10&anio=2026")->assertOk()
            ->assertInertia(fn (Assert $p) => $p->component('Productos/Cupos/Calendario')->where('categoria', 7)->has('calendario.dias', 31)->has('habitaciones', 1)->has('tarifarios', 1));

        $this->post("{$base}/cupo", ['fk_tarifacategoria_id' => 7, 'vigencia_ini' => '2026-10-01', 'vigencia_fin' => '2026-10-10', 'cantidad' => 0])->assertRedirect()->assertSessionHas('success');
        $this->post("{$base}/soldout", ['todos' => 1, 'vigencia_ini' => '2026-10-05', 'vigencia_fin' => '2026-10-06'])->assertRedirect()->assertSessionHas('success', '2 día(s) bloqueado(s).');

        $mes = $this->getJson("{$base}/mes?mes=10&anio=2026")->assertOk()->json();
        $this->assertTrue($mes['dias']['2026-10-05']['soldout']);
        $this->assertTrue($mes['dias']['2026-10-01']['freesale']);

        $this->postJson("{$base}/bloqueo", ['fecha' => '2026-10-05'])->assertOk()->assertJsonPath('soldout', false);
        $this->postJson("{$base}/bloqueo", ['fecha' => '2026-10-20'])->assertOk()->assertJsonPath('soldout', true);

        $ids = DB::table('soldout')->pluck('soldout_id')->all();
        $this->post("{$base}/soldout/eliminar", ['ids' => $ids])->assertRedirect()->assertSessionHas('success', count($ids).' bloqueo(s) eliminado(s).');
        $this->assertSame(0, DB::table('soldout')->count());

        $this->from($base)->post("{$base}/cupo", ['fk_tarifacategoria_id' => 7, 'vigencia_ini' => '2026-10-10', 'vigencia_fin' => '2026-10-01', 'cantidad' => 1])->assertSessionHasErrors(['vigencia_fin']);
    }

    /** Sin permiso y en modo estricto, 403 (el CI dejaba pasar por URL directa). */
    public function test_sin_permiso_es_403_en_modo_estricto(): void
    {
        DB::table('tipousuario')->insert(['tipousuario_id' => 'OPE', 'tipousuario_nombre' => 'Operador']);
        DB::table('usuario')->insert(['usuario_id' => 8, 'usuario_nombre' => 'Beto', 'fk_tipousuario_id' => 'OPE']);
        DB::table('permisogrupo')->insert(['fk_tipousuario_id' => 'OPE', 'fk_seccion_id' => 901, 'permisogrupo_nombre' => 'acceso', 'permisogrupo_valor' => 1]);
        $this->actingAs(User::find(8), 'web');

        $this->get('/app/productos/receptivo/hotel')->assertOk();
        $this->get('/app/productos/receptivo/hotel/create')->assertForbidden();
        $this->post("/app/productos/{$this->hotel}/vigencias", $this->vigencia())->assertForbidden();
    }
}
