<?php

namespace Tests\Feature\Productos;

use App\Exceptions\Productos\ProductoException;
use App\Services\Productos\ProductoService;
use App\Services\Vigencias\VigenciaService;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\CreaEsquemaProductos;
use Tests\TestCase;

/** Alta/edición/clonado/baja de productos, fiel a hotel.php::save() sin sus REPLACE ni borrados en cascada. */
class ProductoServiceTest extends TestCase
{
    use CreaEsquemaProductos;

    protected function setUp(): void
    {
        parent::setUp();
        $this->crearEsquemaProductos();
        app()->instance('tenant', (object) ['base' => 'witwan_rays', 'pais' => 'AR', 'licencia' => 1]);
        DB::table('proveedor')->insert([['proveedor_id' => 10, 'proveedor_nombre' => 'Marriott'], ['proveedor_id' => 11, 'proveedor_nombre' => 'Hilton']]);
        DB::table('ciudad')->insert([['ciudad_id' => 1, 'ciudad_nombre' => 'Buenos Aires', 'fk_pais_id' => 10], ['ciudad_id' => 2, 'ciudad_nombre' => 'Bariloche', 'fk_pais_id' => 10]]);
        DB::table('tarifacategoria')->insert(['tarifacategoria_id' => 7, 'fk_submodulo_id' => 'HOT', 'tarifacategoria_nombre' => 'Standard']);
    }

    private function svc(): ProductoService
    {
        return app(ProductoService::class);
    }

    private function hotel(array $extra = []): array
    {
        return array_merge([
            'producto_nombre' => 'Hotel Centro',
            'producto_codigo' => 'HC1',
            'fk_proveedor_id' => 10,
            'modotarifa' => 'P',
            'habilitar' => 1,
            'aparece_tarifario' => 1,
            'destino' => 1,
            'zona' => 'Microcentro',
            'edad_infoa' => 2,
            'edad_menor1' => 11,
            'edad_junior' => 0,
            'aparece_excel' => 1,
            'bases' => [1, 2, 3],
            'facilidades' => [5, 6],
            'politica_cancelacion' => '48 hs',
            'politica_cancelacionen' => '48 hours',
            'habitaciones' => [
                ['fk_tarifacategoria_id' => 7, 'nombre' => 'Standard vista', 'capacidad' => 3, 'min_adultos' => 1, 'max_adultos' => 3, 'max_child' => 1, 'max_adultos_child' => 2, 'orden' => 1, 'habilitar' => 1],
            ],
            'galeria' => [['productogaleria_archivo' => 'a.jpg'], ['productogaleria_archivo' => 'b.jpg']],
        ], $extra);
    }

    public function test_alta_de_hotel_escribe_todas_las_tablas(): void
    {
        $id = $this->svc()->guardar('HOT', 1, $this->hotel(), 99);

        $p = DB::table('producto')->where('producto_id', $id)->first();
        $this->assertSame('HOT', $p->fk_tipoproducto_id);
        $this->assertSame(1, (int) $p->fk_sistema_id);
        $this->assertSame(99, (int) $p->fk_usuario_id);
        $this->assertSame(1, (int) $p->destino);
        $this->assertSame('48 hs', $p->politica_cancelacion);

        $extras = DB::table('producto_extra')->where('fk_producto_id', $id)->pluck('extra_valor', 'extra_nombre')->all();
        $this->assertSame('Microcentro', $extras['zona']);
        $this->assertSame('2', $extras['edad_infoa']);
        $this->assertSame('11', $extras['edad_menor1']);
        $this->assertSame('0', $extras['edad_junior'], 'un 0 se graba como "0", igual que el REPLACE del CI; sólo el vacío se borra (hotel.php:261)');
        $this->assertSame('1', $extras['aparece_excel']);
        $this->assertSame('48 hours', $extras['politica_cancelacionen']);
        $this->assertSame('1,2,3', $extras['bases'], 'el CI también lee el extra bases');

        $this->assertSame([1, 2, 3], DB::table('rel_productobase')->where('fk_producto_id', $id)->orderBy('fk_base_id')->pluck('fk_base_id')->map(fn ($b) => (int) $b)->all());
        $this->assertSame([5, 6], DB::table('rel_productoalojamientofacilidad')->where('fk_producto_id', $id)->pluck('fk_alojamientofacilidad_id')->map(fn ($b) => (int) $b)->all());
        $this->assertSame([['fk_ciudad_id' => 1, 'tipo' => 'D']], DB::table('rel_productociudad')->where('fk_producto_id', $id)->get(['fk_ciudad_id', 'tipo'])->map(fn ($r) => ['fk_ciudad_id' => (int) $r->fk_ciudad_id, 'tipo' => $r->tipo])->all(), 'ciudad única: UNA fila (hotel.php:280)');
        $this->assertSame(['a.jpg', 'b.jpg'], DB::table('productogaleria')->where('fk_producto_id', $id)->orderBy('orden')->pluck('productogaleria_archivo')->all());

        $hab = DB::table('alojamientohabitacion')->where('fk_producto_id', $id)->first();
        $this->assertSame('Standard vista', $hab->alojamientohabitacion_nombre);
        $this->assertSame(1, (int) $hab->min_adultos_child, 'guardarhabitacion: min_adultos_child = min_adultos');
    }

    public function test_edicion_por_diferencia_conserva_extras_ajenos_al_form(): void
    {
        $id = $this->svc()->guardar('HOT', 1, $this->hotel(), 99);
        DB::table('producto_extra')->insert(['fk_producto_id' => $id, 'extra_nombre' => 'textolibre', 'extra_valor' => 'lo carga otra pantalla']);
        $habId = (int) DB::table('alojamientohabitacion')->where('fk_producto_id', $id)->value('alojamientohabitacion_id');

        $this->svc()->guardar('HOT', 1, $this->hotel([
            'producto_nombre' => 'Hotel Centro II',
            'zona' => '',
            'edad_junior' => 17,
            'bases' => [1, 2],
            'facilidades' => [6, 8],
            'destino' => 2,
            'galeria' => [['productogaleria_archivo' => 'b.jpg', 'orden' => 0], ['productogaleria_archivo' => 'c.jpg', 'orden' => 1]],
            'habitaciones' => [
                ['alojamientohabitacion_id' => $habId, 'fk_tarifacategoria_id' => 7, 'nombre' => 'Standard', 'capacidad' => 2, 'max_child' => 0],
                ['fk_tarifacategoria_id' => 7, 'nombre' => 'Otra', 'capacidad' => 4],
            ],
        ]), 99, $id);

        $extras = DB::table('producto_extra')->where('fk_producto_id', $id)->pluck('extra_valor', 'extra_nombre')->all();
        $this->assertSame('lo carga otra pantalla', $extras['textolibre']);
        $this->assertArrayNotHasKey('zona', $extras);
        $this->assertSame('17', $extras['edad_junior']);
        $this->assertSame(1, DB::table('producto_extra')->where('fk_producto_id', $id)->where('extra_nombre', 'edad_infoa')->count(), 'sin duplicados por clave');

        $this->assertSame([1, 2], DB::table('rel_productobase')->where('fk_producto_id', $id)->orderBy('fk_base_id')->pluck('fk_base_id')->map(fn ($b) => (int) $b)->all());
        $this->assertSame([6, 8], DB::table('rel_productoalojamientofacilidad')->where('fk_producto_id', $id)->orderBy('fk_alojamientofacilidad_id')->pluck('fk_alojamientofacilidad_id')->map(fn ($b) => (int) $b)->all());
        $this->assertSame([2], DB::table('rel_productociudad')->where('fk_producto_id', $id)->pluck('fk_ciudad_id')->map(fn ($b) => (int) $b)->all());
        $this->assertSame(2, (int) DB::table('producto')->where('producto_id', $id)->value('destino'));
        $this->assertSame(['b.jpg', 'c.jpg'], DB::table('productogaleria')->where('fk_producto_id', $id)->orderBy('orden')->pluck('productogaleria_archivo')->all());

        $habs = DB::table('alojamientohabitacion')->where('fk_producto_id', $id)->orderBy('alojamientohabitacion_id')->get();
        $this->assertCount(2, $habs);
        $this->assertSame($habId, (int) $habs[0]->alojamientohabitacion_id);
        $this->assertSame('Standard', $habs[0]->alojamientohabitacion_nombre);
    }

    public function test_reglas_de_negocio_bloquean_sin_escribir(): void
    {
        try {
            $this->svc()->guardar('HOT', 1, $this->hotel(['modotarifa' => 'S', 'destino' => 0, 'edad_menor1' => 2]), 99);
            $this->fail('debía fallar');
        } catch (ProductoException $e) {
            $this->assertArrayHasKey('modotarifa', $e->errores());
            $this->assertArrayHasKey('destino', $e->errores());
            $this->assertArrayHasKey('edad_menor1', $e->errores());
        }

        $this->assertSame(0, DB::table('producto')->count());
    }

    public function test_un_error_en_habitaciones_revierte_toda_la_transaccion(): void
    {
        try {
            $this->svc()->guardar('HOT', 1, $this->hotel(['habitaciones' => [['fk_tarifacategoria_id' => 0, 'nombre' => 'sin categoría']]]), 99);
            $this->fail('debía fallar');
        } catch (ProductoException $e) {
            $this->assertArrayHasKey('habitaciones.0.fk_tarifacategoria_id', $e->errores());
        }

        $this->assertSame(0, DB::table('producto')->count());
        $this->assertSame(0, DB::table('producto_extra')->count());
    }

    public function test_excursion_con_destinos_multiples_y_tipo(): void
    {
        $id = $this->svc()->guardar('EXC', 1, [
            'producto_nombre' => 'City tour', 'fk_proveedor_id' => 11, 'modotarifa' => 'S', 'disponibilidad' => 'RQ', 'origen' => 1,
            'destinos' => [['ciudad_id' => 1, 'tipo' => 'O'], ['ciudad_id' => 2, 'tipo' => 'D']],
            'edad_infoa' => 3, 'edad_menor1' => 10,
        ], 99);

        $rel = DB::table('rel_productociudad')->where('fk_producto_id', $id)->orderBy('fk_ciudad_id')->get();
        $this->assertSame(['O', 'D'], $rel->pluck('tipo')->all());
        $this->assertSame('RQ', DB::table('producto')->where('producto_id', $id)->value('disponibilidad'));

        $this->svc()->guardar('EXC', 1, ['producto_nombre' => 'City tour', 'fk_proveedor_id' => 11, 'modotarifa' => 'S', 'destinos' => [['ciudad_id' => 2, 'tipo' => 'O']]], 99, $id);
        $rel = DB::table('rel_productociudad')->where('fk_producto_id', $id)->get();
        $this->assertCount(1, $rel);
        $this->assertSame('O', $rel[0]->tipo);
    }

    public function test_cargar_devuelve_todo_lo_que_usa_el_form_y_las_vigencias(): void
    {
        $id = $this->svc()->guardar('HOT', 1, $this->hotel(), 99);

        $p = $this->svc()->cargar($id);

        $this->assertSame('Microcentro', $p['zona']);
        $this->assertSame(['edad_infoa' => 2, 'edad_menor1' => 11, 'edad_menor2' => 0, 'edad_junior' => 0, 'edad_senior' => 0], $p['edades']);
        $this->assertSame(['1', '2', '3'], $p['bases']);
        $this->assertSame(1, $p['fk_ciudad_id']);
        $this->assertSame(10, $p['pais']);
        $this->assertSame([5, 6], $p['facilidades']);
        $this->assertCount(1, $p['habitaciones']);
        $this->assertSame('Standard vista', $p['habitaciones'][0]['nombre']);
        $this->assertFalse($p['habitaciones'][0]['tiene_tarifas']);
        $this->assertNull($this->svc()->cargar(9999));
    }

    public function test_bases_caen_al_extra_si_no_hay_rel_productobase(): void
    {
        $id = $this->productoDePrueba([], ['bases' => '3,1']);

        $this->assertSame(['1', '3'], $this->svc()->bases($id));
        $this->assertSame([], $this->svc()->bases($this->productoDePrueba()));
    }

    public function test_listar_filtra_como_el_ci_y_excluye_eliminados(): void
    {
        $a = $this->svc()->guardar('HOT', 1, $this->hotel(), 99);
        $b = $this->svc()->guardar('HOT', 1, $this->hotel(['producto_nombre' => 'Hotel Sur', 'fk_proveedor_id' => 11, 'destino' => 2, 'habilitar' => 0]), 99);
        $this->svc()->guardar('HOT', 2, $this->hotel(['producto_nombre' => 'Mayorista']), 99);
        $c = $this->svc()->guardar('HOT', 1, $this->hotel(['producto_nombre' => 'Borrado']), 99);
        $this->svc()->eliminar($c);

        $todos = $this->svc()->listar(1, 'HOT', []);
        $this->assertSame([$a, $b], $todos->pluck('producto_id')->map(fn ($i) => (int) $i)->all());
        $this->assertSame('Marriott', $todos[0]->proveedor_nombre);
        $this->assertSame('Buenos Aires', $todos[0]->ciudad_nombre);

        $this->assertSame([$b], $this->svc()->listar(1, 'HOT', ['producto_nombre' => 'sur'])->pluck('producto_id')->map(fn ($i) => (int) $i)->all());
        $this->assertSame([$b], $this->svc()->listar(1, 'HOT', ['fk_ciudad_id' => 2])->pluck('producto_id')->map(fn ($i) => (int) $i)->all());
        $this->assertSame([$a], $this->svc()->listar(1, 'HOT', ['habilitar' => 1])->pluck('producto_id')->map(fn ($i) => (int) $i)->all());
        $this->assertSame([$b], $this->svc()->listar(1, 'HOT', [], 11)->pluck('producto_id')->map(fn ($i) => (int) $i)->all(), 'usuario ACP: sólo su proveedor');
    }

    public function test_baja_logica(): void
    {
        $id = $this->svc()->guardar('HOT', 1, $this->hotel(), 99);

        $this->svc()->eliminar($id);

        $p = DB::table('producto')->where('producto_id', $id)->first();
        $this->assertSame(1, (int) $p->eliminar);
        $this->assertSame(0, (int) $p->habilitar);
        $this->assertSame(1, DB::table('alojamientohabitacion')->where('fk_producto_id', $id)->count(), 'nada se borra físicamente');

        $this->expectException(ProductoException::class);
        $this->svc()->eliminar(9999);
    }

    public function test_clonar_copia_todo_y_las_vigencias_futuras(): void
    {
        $id = $this->svc()->guardar('HOT', 1, $this->hotel(), 99);
        $vig = app(VigenciaService::class);
        $base = ['dias_semana' => [1, 2, 3], 'moneda_costo' => 'USD', 'residente' => '', 'tarifas' => [['categoria' => 7, 'base' => '1', 'costo' => 100]]];
        $vig->guardar($id, $base + ['vigencia_ini' => '2020-01-01', 'vigencia_fin' => '2020-12-31']);
        $vig->guardar($id, $base + ['vigencia_ini' => '2030-01-01', 'vigencia_fin' => '2030-12-31']);

        $nuevo = $this->svc()->clonar($id, 5, ['con_vigencias' => true], $vig);

        $this->assertNotSame($id, $nuevo);
        $p = DB::table('producto')->where('producto_id', $nuevo)->first();
        $this->assertSame('Hotel Centro (copia)', $p->producto_nombre);
        $this->assertSame(5, (int) $p->fk_usuario_id);
        $this->assertSame(DB::table('producto_extra')->where('fk_producto_id', $id)->count(), DB::table('producto_extra')->where('fk_producto_id', $nuevo)->count());
        $this->assertSame(1, DB::table('alojamientohabitacion')->where('fk_producto_id', $nuevo)->count());
        $this->assertSame(3, DB::table('rel_productobase')->where('fk_producto_id', $nuevo)->count());
        $this->assertSame(2, DB::table('productogaleria')->where('fk_producto_id', $nuevo)->count());

        $vigs = DB::table('vigencia')->where('fk_producto_id', $nuevo)->get();
        $this->assertCount(1, $vigs, 'sólo las vigencias con fin >= hoy');
        $this->assertSame('2030-01-01', $vigs[0]->vigencia_ini);
        $this->assertSame(1, DB::table('tarifa')->where('fk_vigencia_id', $vigs[0]->vigencia_id)->count());

        $sin = $this->svc()->clonar($id, 5, ['nombre' => 'Copia sin tarifas'], $vig);
        $this->assertSame(0, DB::table('vigencia')->where('fk_producto_id', $sin)->count());
    }
}
