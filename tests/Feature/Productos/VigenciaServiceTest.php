<?php

namespace Tests\Feature\Productos;

use App\Exceptions\Productos\VigenciaException;
use App\Services\Vigencias\VigenciaService;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\CreaEsquemaProductos;
use Tests\TestCase;

/**
 * Guardado transaccional de vigencias, fiel a lo que el CI persiste
 * (vigencia.php:159-517) pero sin sus bugs B1-B5.
 */
class VigenciaServiceTest extends TestCase
{
    use CreaEsquemaProductos;

    private int $hotel;

    protected function setUp(): void
    {
        parent::setUp();
        $this->crearEsquemaProductos();
        app()->instance('tenant', (object) ['base' => 'witwan_rays', 'pais' => 'AR', 'licencia' => 1]);

        DB::table('ciudad')->insert(['ciudad_id' => 1, 'ciudad_nombre' => 'Buenos Aires', 'fk_pais_id' => 10]);
        $this->hotel = $this->productoDePrueba([], ['edad_infoa' => 2, 'edad_menor1' => 11, 'edad_junior' => 17]);
        DB::table('rel_productociudad')->insert(['fk_producto_id' => $this->hotel, 'fk_ciudad_id' => 1, 'tipo' => 'D']);
        DB::table('rel_productobase')->insert([['fk_producto_id' => $this->hotel, 'fk_base_id' => 1], ['fk_producto_id' => $this->hotel, 'fk_base_id' => 2]]);
        DB::table('tarifacategoria')->insert([['tarifacategoria_id' => 7, 'fk_submodulo_id' => 'HOT', 'tarifacategoria_nombre' => 'Standard'], ['tarifacategoria_id' => 8, 'fk_submodulo_id' => 'HOT', 'tarifacategoria_nombre' => 'Suite']]);
        DB::table('alojamientohabitacion')->insert([
            ['fk_producto_id' => $this->hotel, 'fk_tarifacategoria_id' => 7, 'max_child' => 1, 'orden' => 1, 'habilitar' => 1],
            ['fk_producto_id' => $this->hotel, 'fk_tarifacategoria_id' => 8, 'max_child' => 0, 'orden' => 2, 'habilitar' => 1],
        ]);
        DB::table('tarifario')->insert([
            ['tarifario_id' => 3, 'fk_sistema_id' => 1, 'tarifario_nombre' => 'Agencias', 'fk_moneda_id' => 'USD', 'interno' => 0],
            ['tarifario_id' => 4, 'fk_sistema_id' => 1, 'tarifario_nombre' => 'Interno', 'fk_moneda_id' => 'ARS', 'interno' => 1],
        ]);
        DB::table('tarifariocomision')->insert(['fk_tarifario_id' => 3, 'fk_submodulo_id' => '0', 'divisor_markup' => 0.8, 'porcentaje_comision' => 10]);
    }

    private function svc(): VigenciaService
    {
        return app(VigenciaService::class);
    }

    private function payload(array $extra = []): array
    {
        return array_merge([
            'vigencia_ini' => '2026-10-01',
            'vigencia_fin' => '2026-12-31',
            'vigencia_descripcion' => 'Temporada alta',
            'residente' => '',
            'dias_semana' => [1, 2, 3, 4, 5],
            'moneda_costo' => 'USD',
            'impuestos' => 21,
            'impuestos_menor' => 10.5,
            'redondeo' => 'R',
            'cargamanual' => 0,
            'tarifas' => [
                ['categoria' => 7, 'base' => '1', 'costo' => 100, 'venta' => [3 => 150]],
                ['categoria' => 7, 'base' => '2', 'costo' => 80],
                ['categoria' => 7, 'base' => 'MN', 'costo' => 40],
                ['categoria' => 7, 'base' => 'INF', 'costo' => null],
                ['categoria' => 8, 'base' => '1', 'costo' => 200],
            ],
        ], $extra);
    }

    public function test_alta_completa(): void
    {
        $r = $this->svc()->guardar($this->hotel, $this->payload());

        $this->assertGreaterThan(0, $r['id']);
        $this->assertSame(['insertadas' => 4, 'actualizadas' => 0, 'borradas' => 0, 'sin_cambios' => 0], $r['tarifas']);

        $v = DB::table('vigencia')->where('vigencia_id', $r['id'])->first();
        $this->assertSame('2026-10-01', $v->vigencia_ini);
        $this->assertSame('0000-00-00', $v->vigencia_ventaini, 'sin venta se graba 0000-00-00 como el CI');
        $this->assertSame('1111100', $this->bitsDeVigencia($r['id']));
        $this->assertSame([1, 2, 3, 4, 5], DB::table('rel_vigenciadia')->where('fk_vigencia_id', $r['id'])->orderBy('fk_dia_id')->pluck('fk_dia_id')->map(fn ($d) => (int) $d)->all());

        $tarifas = DB::table('tarifa')->where('fk_vigencia_id', $r['id'])->get()->keyBy(fn ($t) => $t->fk_tarifacategoria_id.'_'.$t->fk_base_id);
        $this->assertEquals(21, $tarifas['7_1']->impuestos, 'base numérica lleva impuestos');
        $this->assertEquals(10.5, $tarifas['7_MN']->impuestos, 'pseudo-base lleva impuestos_menor');
        $this->assertSame('USD', $tarifas['7_2']->moneda_costo);
        $this->assertSame('R', $tarifas['8_1']->redondear);
        $this->assertSame(0, (int) $tarifas['7_1']->fk_tarifario_id);
        $this->assertArrayNotHasKey('7_INF', $tarifas->all(), 'celda vacía no genera fila');
        $this->assertSame(0, DB::table('tarifa')->where('fk_tarifario_id', '!=', 0)->count(), 'sin cargamanual no se graban ventas');
    }

    public function test_carga_manual_graba_ventas_por_tarifario_con_su_moneda(): void
    {
        DB::table('tarifario')->where('tarifario_id', 3)->update(['fk_moneda_id' => 'ARS']);

        $r = $this->svc()->guardar($this->hotel, $this->payload(['cargamanual' => 1]));

        $venta = DB::table('tarifa')->where('fk_vigencia_id', $r['id'])->where('fk_tarifario_id', 3)->first();
        $this->assertNotNull($venta);
        $this->assertEquals(150, $venta->costo);
        $this->assertSame('ARS', $venta->moneda_costo, 'la fila manual lleva la moneda del tarifario (vigencia.php:363)');
        $this->assertSame('1', $venta->fk_base_id);
    }

    /** B3: editar no debe pisar columnas que el form no manda. */
    public function test_editar_actualiza_sin_perder_columnas_ajenas_al_form(): void
    {
        $id = $this->svc()->guardar($this->hotel, $this->payload())['id'];
        DB::table('vigencia')->where('vigencia_id', $id)->update(['clase' => 'PR', 'infoadicional' => 'dato', 'cotizacionespecial' => 12.5, 'fk_tarifario_id' => 3]);

        $r = $this->svc()->guardar($this->hotel, $this->payload([
            'vigencia_descripcion' => 'Editada',
            'dias_semana' => [6, 7],
            'tarifas' => [
                ['categoria' => 7, 'base' => '1', 'costo' => 100],
                ['categoria' => 7, 'base' => 'MN', 'costo' => 45],
                ['categoria' => 8, 'base' => '1', 'costo' => 200],
            ],
        ]), $id);

        $this->assertSame($id, $r['id']);
        $v = DB::table('vigencia')->where('vigencia_id', $id)->first();
        $this->assertSame('Editada', $v->vigencia_descripcion);
        $this->assertSame('PR', $v->clase);
        $this->assertSame('dato', $v->infoadicional);
        $this->assertEquals(12.5, $v->cotizacionespecial);
        $this->assertSame(3, (int) $v->fk_tarifario_id);
        $this->assertSame('0000011', $this->bitsDeVigencia($id));
        $this->assertSame([6, 7], DB::table('rel_vigenciadia')->where('fk_vigencia_id', $id)->orderBy('fk_dia_id')->pluck('fk_dia_id')->map(fn ($d) => (int) $d)->all());

        $this->assertSame(['insertadas' => 0, 'actualizadas' => 1, 'borradas' => 1, 'sin_cambios' => 2], $r['tarifas']);
        $this->assertSame(['1', 'MN'], DB::table('tarifa')->where('fk_tarifacategoria_id', 7)->orderBy('tarifa_id')->pluck('fk_base_id')->all(), 'B1: vaciar base 2 no borra la 1 ni MN');
    }

    public function test_una_celda_fuera_de_la_grilla_es_error(): void
    {
        try {
            $this->svc()->guardar($this->hotel, $this->payload(['tarifas' => [['categoria' => 8, 'base' => 'MN', 'costo' => 10]]]));
            $this->fail('debía fallar');
        } catch (VigenciaException $e) {
            $this->assertArrayHasKey('tarifas.0', $e->errores(), 'Suite tiene max_child 0: no admite MN');
        }

        $this->assertSame(0, DB::table('vigencia')->count(), 'nada se escribe si hay errores');
    }

    public function test_errores_de_reglas_no_escriben_nada(): void
    {
        try {
            $this->svc()->guardar($this->hotel, $this->payload(['vigencia_fin' => '2026-01-01', 'dias_semana' => []]));
            $this->fail('debía fallar');
        } catch (VigenciaException $e) {
            $this->assertArrayHasKey('vigencia_fin', $e->errores());
            $this->assertArrayHasKey('dias_semana', $e->errores());
        }

        $this->assertSame(0, DB::table('vigencia')->count());
        $this->assertSame(0, DB::table('tarifa')->count());
    }

    public function test_solape_es_aviso_y_guarda_igual(): void
    {
        $this->svc()->guardar($this->hotel, $this->payload(['vigencia_prioridad' => 5, 'vigencia_descripcion' => 'Promo']));

        $r = $this->svc()->guardar($this->hotel, $this->payload(['vigencia_ini' => '2026-11-01', 'vigencia_fin' => '2027-01-31']));

        $this->assertCount(1, $r['avisos']);
        $this->assertStringContainsString('gana "Promo"', $r['avisos'][0]);
        $this->assertSame(2, DB::table('vigencia')->count());
    }

    public function test_tramos_para_excursion(): void
    {
        $exc = $this->productoDePrueba(['fk_tipoproducto_id' => 'EXC', 'producto_nombre' => 'City tour']);

        $r = $this->svc()->guardar($exc, [
            'vigencia_ini' => '2026-10-01', 'vigencia_fin' => '2026-12-31', 'dias_semana' => [1, 2, 3, 4, 5, 6, 7],
            'moneda_costo' => 'USD', 'redondeo' => ' ', 'cargamanual' => 1, 'residente' => '',
            'tramos' => [
                ['min' => 1, 'max' => 2, 'costos' => ['ADU' => 100, 'CHD' => 50, 'INF' => null], 'venta' => [3 => ['ADU' => 140]]],
                ['min' => 3, 'max' => 9, 'costos' => ['ADU' => 80]],
            ],
        ]);

        $this->assertSame(4, $r['tarifas']['insertadas']);
        $filas = DB::table('tarifa')->where('fk_vigencia_id', $r['id'])->orderBy('tarifa_id')->get();
        $this->assertSame(['ADU', 'ADU', 'CHD', 'ADU'], $filas->pluck('fk_tipopax_id')->all());
        $this->assertSame([[1, 2], [1, 2], [1, 2], [3, 9]], $filas->map(fn ($f) => [(int) $f->min_pax, (int) $f->max_pax])->all());
        $this->assertSame('', $filas[0]->fk_base_id);
        $this->assertSame(3, (int) $filas[1]->fk_tarifario_id, 'la venta manual va junto a su costo');
        $this->assertEquals(140, $filas[1]->costo);

        // Segundo guardado idéntico: nada cambia (B4: no corre 15 veces).
        $this->assertSame(4, $this->svc()->guardar($exc, [
            'vigencia_ini' => '2026-10-01', 'vigencia_fin' => '2026-12-31', 'dias_semana' => [1, 2, 3, 4, 5, 6, 7],
            'moneda_costo' => 'USD', 'redondeo' => ' ', 'cargamanual' => 1, 'residente' => '',
            'tramos' => [
                ['min' => 1, 'max' => 2, 'costos' => ['ADU' => 100, 'CHD' => 50], 'venta' => [3 => ['ADU' => 140]]],
                ['min' => 3, 'max' => 9, 'costos' => ['ADU' => 80]],
            ],
        ], $r['id'])['tarifas']['sin_cambios']);
    }

    public function test_alojamientos_de_paquete_por_diferencia(): void
    {
        $paq = $this->productoDePrueba(['fk_tipoproducto_id' => 'PAQ', 'producto_nombre' => 'Circuito']);
        $base = ['vigencia_ini' => '2026-10-01', 'vigencia_fin' => '2026-12-31', 'dias_semana' => [1], 'moneda_costo' => 'USD', 'residente' => ''];

        $id = $this->svc()->guardar($paq, $base + ['alojamientos' => [
            ['fk_producto_id' => $this->hotel, 'fk_tarifacategoria_id' => 7, 'fk_regimen_id' => 2, 'noches' => 3, 'ncategoria' => 'Std'],
            ['fk_producto_id' => 0, 'fk_tarifacategoria_id' => 0, 'noches' => 1, 'ncategoria' => 'Libre'],
        ]])['id'];

        $vas = DB::table('vigenciaalojamiento')->where('fk_vigencia_id', $id)->orderBy('vigenciaalojamiento_id')->get();
        $this->assertCount(2, $vas);
        $primero = (int) $vas[0]->vigenciaalojamiento_id;

        $this->svc()->guardar($paq, $base + ['alojamientos' => [
            ['vigenciaalojamiento_id' => $primero, 'fk_producto_id' => $this->hotel, 'fk_tarifacategoria_id' => 8, 'fk_regimen_id' => 2, 'noches' => 4, 'ncategoria' => 'Suite'],
        ]], $id);

        $vas = DB::table('vigenciaalojamiento')->where('fk_vigencia_id', $id)->get();
        $this->assertCount(1, $vas);
        $this->assertSame($primero, (int) $vas[0]->vigenciaalojamiento_id, 'conserva el id');
        $this->assertSame(4, (int) $vas[0]->noches);
    }

    public function test_clonar_desplaza_fechas_y_ajusta_costos(): void
    {
        $id = $this->svc()->guardar($this->hotel, $this->payload(['vigencia_ventaini' => '2026-09-01', 'vigencia_ventafin' => '2026-09-30']))['id'];

        $nuevo = $this->svc()->clonar($id, ['desplazar_meses' => 12, 'desplazar_dias' => 1, 'ajustar_pct' => 10, 'descripcion' => 'Temporada alta 2027']);

        $v = DB::table('vigencia')->where('vigencia_id', $nuevo)->first();
        $this->assertSame('2027-10-02', $v->vigencia_ini);
        $this->assertSame('2028-01-01', $v->vigencia_fin);
        $this->assertSame('2027-09-02', $v->vigencia_ventaini);
        $this->assertSame('0000-00-00', $v->vencimiento_promocion, 'las fechas vacías siguen vacías');
        $this->assertSame('Temporada alta 2027', $v->vigencia_descripcion);
        $this->assertSame('1111100', $this->bitsDeVigencia($nuevo));
        $this->assertSame(5, DB::table('rel_vigenciadia')->where('fk_vigencia_id', $nuevo)->count());
        $this->assertEquals(110, DB::table('tarifa')->where('fk_vigencia_id', $nuevo)->where('fk_base_id', '1')->where('fk_tarifacategoria_id', 7)->value('costo'));
        $this->assertEquals(100, DB::table('tarifa')->where('fk_vigencia_id', $id)->where('fk_base_id', '1')->where('fk_tarifacategoria_id', 7)->value('costo'));
    }

    public function test_eliminar_borra_solo_lo_de_esa_vigencia(): void
    {
        $a = $this->svc()->guardar($this->hotel, $this->payload())['id'];
        $b = $this->svc()->guardar($this->hotel, $this->payload(['vigencia_ini' => '2027-01-01', 'vigencia_fin' => '2027-02-01']))['id'];

        $this->svc()->eliminar($a);

        $this->assertSame(0, DB::table('tarifa')->where('fk_vigencia_id', $a)->count());
        $this->assertSame(0, DB::table('rel_vigenciadia')->where('fk_vigencia_id', $a)->count());
        $this->assertSame(4, DB::table('tarifa')->where('fk_vigencia_id', $b)->count());
    }

    public function test_para_formulario_arma_grilla_tarifarios_y_cabecera(): void
    {
        $id = $this->svc()->guardar($this->hotel, $this->payload())['id'];

        $f = $this->svc()->paraFormulario($this->hotel, $id);

        $this->assertSame('alojamiento', $f['grilla']['modo']);
        $this->assertSame([3], array_column($f['grilla']['tarifarios'], 'id'), 'los tarifarios internos no aparecen (vigencia.php:44)');
        $this->assertSame(0.8, $f['grilla']['tarifarios'][0]['divisor_markup']);
        $this->assertSame(['1', '2', 'INF', 'MN', 'JNR'], $f['grilla']['filas'][0]['bases']);
        $this->assertSame(100.0, $f['grilla']['filas'][0]['celdas'][0]['costo']);
        $this->assertSame('USD', $f['vigencia']['moneda_costo']);
        $this->assertSame('R', $f['vigencia']['redondeo']);
        $this->assertEquals(21, $f['vigencia']['impuestos']);
        $this->assertEquals(10.5, $f['vigencia']['impuestos_menor']);
        $this->assertSame([1, 2, 3, 4, 5], $f['vigencia']['dias_semana']);
        $this->assertSame(10, $f['producto']['pais']);
        $this->assertSame([], $f['otras']);

        $this->assertNull($this->svc()->paraFormulario($this->hotel, 9999));
    }

    public function test_listar_incluye_dias_y_cantidad_de_tarifas(): void
    {
        $this->svc()->guardar($this->hotel, $this->payload());

        $lista = $this->svc()->listar($this->hotel);

        $this->assertCount(1, $lista);
        $this->assertSame(4, (int) $lista[0]['tarifas']);
        $this->assertSame([1, 2, 3, 4, 5], $lista[0]['dias_semana']);
        $this->assertSame('USD', $lista[0]['moneda_costo']);
    }
}
