<?php

namespace Tests\Feature\Productos;

use App\Services\Pricing\Tarifador;
use App\Services\Vigencias\VigenciaService;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\CreaEsquemaProductos;
use Tests\TestCase;

/**
 * Port parcial de Tarifa_model::tarifar(). Los datos se cargan con
 * VigenciaService (lo que escribe Laravel es lo que lee el tarifador) y las
 * cifras esperadas se derivan a mano de la fórmula de la cabecera de
 * tarifa_model.php. Pendiente: fixtures generados desde el CI para los mismos
 * inputs (doc §4.7, fase 4).
 */
class TarifadorTest extends TestCase
{
    use CreaEsquemaProductos;

    private int $hotel;

    protected function setUp(): void
    {
        parent::setUp();
        $this->crearEsquemaProductos();
        app()->instance('tenant', (object) ['base' => 'witwan_rays', 'pais' => 'AR', 'licencia' => 1]);

        DB::table('moneda')->insert([['moneda_id' => 'ARS', 'moneda_basica' => 'Y'], ['moneda_id' => 'USD', 'moneda_basica' => 'N']]);
        DB::table('cotizacion')->insert(['cotizacion_moneda' => 'USD', 'cotizacion_fecha' => '2026-01-01', 'cotizacion_costo' => 1000, 'cotizacion_relacion' => 1000]);
        DB::table('sistema')->insert([['sistema_id' => 1, 'extra1' => 0, 'extra2' => 0], ['sistema_id' => 2, 'extra1' => 1.2, 'extra2' => 0]]);
        DB::table('ciudad')->insert(['ciudad_id' => 1, 'ciudad_nombre' => 'Buenos Aires', 'fk_pais_id' => 10]);
        DB::table('tarifacategoria')->insert([['tarifacategoria_id' => 7, 'fk_submodulo_id' => 'HOT', 'tarifacategoria_nombre' => 'Standard'], ['tarifacategoria_id' => 8, 'fk_submodulo_id' => 'HOT', 'tarifacategoria_nombre' => 'Suite']]);
        DB::table('tarifario')->insert([['tarifario_id' => 3, 'fk_sistema_id' => 1, 'tarifario_nombre' => 'Agencias', 'fk_moneda_id' => 'USD', 'cotizacion' => 0]]);
        DB::table('tarifariocomision')->insert(['fk_tarifario_id' => 3, 'fk_submodulo_id' => '0', 'divisor_markup' => 0.8, 'porcentaje_comision' => 10]);
        DB::table('rel_clientesistema')->insert(['fk_cliente_id' => 50, 'fk_sistema_id' => 1, 'fk_tarifario_id' => 3]);
        DB::table('iva')->insert(['fk_sistema_id' => 0, 'fk_submodulo_id' => '', 'fk_pais_id' => 0, 'fk_ciudad_id' => 0, 'fk_producto_id' => 0, 'iva_costo' => 21, 'iva_valor' => 21, 'fk_modoivaventa_id' => 2]);

        $this->hotel = $this->productoDePrueba(['producto_nombre' => 'Hotel Centro', 'modotarifa' => 'P', 'disponibilidad' => ''], ['edad_infoa' => 2, 'edad_menor1' => 11, 'edad_junior' => 17]);
        DB::table('rel_productociudad')->insert(['fk_producto_id' => $this->hotel, 'fk_ciudad_id' => 1]);
        DB::table('rel_productobase')->insert([['fk_producto_id' => $this->hotel, 'fk_base_id' => 1], ['fk_producto_id' => $this->hotel, 'fk_base_id' => 2]]);
        DB::table('alojamientohabitacion')->insert([
            ['fk_producto_id' => $this->hotel, 'fk_tarifacategoria_id' => 7, 'alojamientohabitacion_nombre' => '', 'capacidad' => 3, 'min_adultos' => 1, 'max_adultos' => 2, 'max_child' => 2, 'max_adultos_child' => 2, 'min_adultos_child' => 1, 'habilitar' => 1, 'orden' => 1],
            ['fk_producto_id' => $this->hotel, 'fk_tarifacategoria_id' => 8, 'alojamientohabitacion_nombre' => 'Suite', 'capacidad' => 2, 'min_adultos' => 1, 'max_adultos' => 2, 'max_child' => 0, 'max_adultos_child' => 0, 'min_adultos_child' => 0, 'habilitar' => 1, 'orden' => 2],
        ]);
    }

    private function vigencia(array $extra = [], array $tarifas = []): int
    {
        return app(VigenciaService::class)->guardar($this->hotel, array_merge([
            'vigencia_ini' => '2026-10-01', 'vigencia_fin' => '2026-12-31', 'dias_semana' => [1, 2, 3, 4, 5, 6, 7],
            'moneda_costo' => 'USD', 'redondeo' => ' ', 'residente' => '', 'cargamanual' => 0, 'impuestos' => 0,
            'tarifas' => $tarifas ?: [
                ['categoria' => 7, 'base' => '1', 'costo' => 100], ['categoria' => 7, 'base' => '2', 'costo' => 80],
                ['categoria' => 7, 'base' => 'INF', 'costo' => 0], ['categoria' => 7, 'base' => 'MN', 'costo' => 40], ['categoria' => 7, 'base' => 'JNR', 'costo' => 60],
                ['categoria' => 8, 'base' => '1', 'costo' => 200], ['categoria' => 8, 'base' => '2', 'costo' => 150],
            ],
        ], $extra))['id'];
    }

    private function cotizar(array $q): array
    {
        return app(Tarifador::class)->cotizar(array_merge(['producto_id' => $this->hotel, 'fecha_ini' => '2026-10-05', 'fecha_fin' => '2026-10-08', 'adultos' => 2, 'tarifario_id' => 3, 'hoy' => '2026-09-01'], $q));
    }

    /** 2 adultos, 3 noches, costo 80 p/p/noche, IVA costo 21 %, ×1000 a ARS, ÷0,8, comisión 10 %. */
    public function test_hotel_por_persona_con_markup_y_comision(): void
    {
        $this->vigencia();

        $r = $this->cotizar([]);

        $this->assertTrue($r['ok']);
        $this->assertSame(3, $r['params']['noches']);
        $std = $r['resultado'][7][0];
        $this->assertSame('Standard', $std['nombre']);
        $this->assertEquals(240, $std['costoadt'], '80 × 3 noches');
        $this->assertEquals(480, $std['costosiniva'], '× 2 adultos');
        $this->assertEquals(100.8, $std['ivacosto']);
        $this->assertEquals(580.8, $std['costo']);
        $this->assertEquals(580.8, $std['costoenmoneda'], 'el tarifario vende en USD: sin conversión');
        $this->assertEquals(726, $std['venta'], '÷ 0,8, entero, múltiplo de 2 adultos');
        $this->assertEquals(72.6, $std['comision']);
        // Modo IVA 2: sobre la renta = venta − costo en moneda − comisión.
        $this->assertEquals(round((726 - 580.8 - 72.6) * 0.21, 2), $std['iva']);
        $this->assertEquals(726 + $std['iva'], $std['total']);
        $this->assertEquals($std['total'] - 72.6, $std['totalapagar']);
        $this->assertEquals(121, $std['promedio'], 'por persona y noche');
        $this->assertSame(0, $std['cupo'], 'sin cupo cargado el CI deja 0 (a requerir)');

        $this->assertSame(7, $r['mejor']['categoria'], 'la Suite (150) es más cara');
        $this->assertEquals(150 * 3 * 2, $r['resultado'][8][0]['costosiniva']);
    }

    /** Tarifario en pesos: el costo USD se convierte con la cotización del día antes del markup. */
    public function test_conversion_a_la_moneda_del_tarifario(): void
    {
        DB::table('tarifario')->where('tarifario_id', 3)->update(['fk_moneda_id' => 'ARS']);
        $this->vigencia();

        $std = $this->cotizar([])['resultado'][7][0];
        $this->assertEquals(580800, $std['costoenmoneda'], 'USD → ARS a 1000');
        $this->assertEquals(726000, $std['venta']);
        $this->assertEquals(1000, $std['cotizacion']);
    }

    public function test_por_habitacion_no_multiplica_por_adultos(): void
    {
        DB::table('producto')->where('producto_id', $this->hotel)->update(['modotarifa' => 'H']);
        $this->vigencia();

        $r = $this->cotizar(['tarifario_id' => 0]);

        $this->assertEquals(240, $r['resultado'][7][0]['costosiniva']);
        $this->assertEquals(1.0, $r['pricing']['mup'], 'sin tarifario: markup 1');
        $this->assertEquals(0, $r['pricing']['comision']);
    }

    public function test_menores_por_edad_con_bases_inf_mn_y_jnr(): void
    {
        $this->vigencia();

        $r = $this->cotizar(['adultos' => 1, 'menores' => [1, 8]]);

        $this->assertTrue($r['ok']);
        $std = $r['resultado'][7][0];
        $this->assertEquals(300, $std['costoadt'], 'single 100 × 3');
        $this->assertEquals(120, $std['costomen'], 'INF 0 + MN 40, × 3 noches');
        $this->assertSame([0.0, 40.0], $std['dias'][0]['menores']);
        $this->assertArrayNotHasKey(8, $r['resultado'], 'la Suite no admite menores (max_child 0)');

        // Segundo menor de la misma franja: base MN2, que no está cargada → la categoría no tarifa.
        $r = $this->cotizar(['adultos' => 1, 'menores' => [8, 9]]);
        $this->assertFalse($r['ok']);

        // Edad fuera de todos los cortes → no tarifa.
        $this->assertFalse($this->cotizar(['adultos' => 1, 'menores' => [30]])['ok']);
    }

    public function test_prioridad_y_noche_faltante(): void
    {
        $this->vigencia();
        // Promo con prioridad 5 sólo el 6/10: manda esa noche.
        $this->vigencia(['vigencia_ini' => '2026-10-06', 'vigencia_fin' => '2026-10-06', 'vigencia_prioridad' => 5], [['categoria' => 7, 'base' => '2', 'costo' => 50]]);

        $r = $this->cotizar([]);
        $this->assertEquals([80.0, 50.0, 80.0], array_column($r['resultado'][7][0]['dias'], 'costo'));

        // Sin tarifa el 7/10 la categoría no tarifa; la otra sí.
        DB::table('vigencia')->where('vigencia_prioridad', 0)->update(['vigencia_fin' => '2026-10-06']);
        $r = $this->cotizar([]);
        $this->assertArrayNotHasKey(7, $r['resultado']);
        $this->assertFalse($r['ok']);
        $this->assertNotEmpty($r['motivos']);
    }

    public function test_dias_de_semana_residente_y_ventana_de_venta(): void
    {
        // Sólo viernes/sábado/domingo (5/10 es lunes): no cubre las tres noches.
        $this->vigencia(['dias_semana' => [5, 6, 7]]);
        $this->assertFalse($this->cotizar([])['ok']);

        DB::table('vigencia')->delete();
        DB::table('tarifa')->delete();

        // Sólo residentes.
        $this->vigencia(['residente' => 'R']);
        $this->assertFalse($this->cotizar(['residente' => 'N'])['ok']);
        $this->assertTrue($this->cotizar(['residente' => 'R'])['ok']);

        // Ventana de venta vencida.
        DB::table('vigencia')->update(['residente' => '', 'vigencia_ventaini' => '2026-01-01', 'vigencia_ventafin' => '2026-06-30']);
        $this->assertFalse($this->cotizar(['hoy' => '2026-09-01'])['ok']);
        $this->assertTrue($this->cotizar(['hoy' => '2026-05-01'])['ok']);

        // Noches mínimas.
        DB::table('vigencia')->update(['vigencia_ventaini' => '0000-00-00', 'vigencia_ventafin' => '0000-00-00', 'noches_minimas' => 4, 'modo_nochesminimas' => 'T']);
        $this->assertFalse($this->cotizar([])['ok']);
        $this->assertTrue($this->cotizar(['fecha_fin' => '2026-10-09'])['ok']);
    }

    public function test_cupo_y_soldout(): void
    {
        $this->vigencia();
        DB::table('cupo')->insert(['fk_producto_id' => $this->hotel, 'fk_tarifacategoria_id' => 7, 'cantidad' => 3, 'bases' => '1,2', 'vigencia_ini' => '2026-10-01', 'vigencia_fin' => '2026-10-31', 'release' => 0]);
        DB::table('reserva')->insert(['reserva_id' => 1]);
        DB::table('servicio')->insert(['fk_reserva_id' => 1, 'fk_producto_id' => $this->hotel, 'fk_tarifacategoria_id' => 7, 'vigencia_ini' => '2026-10-06', 'vigencia_fin' => '2026-10-07', 'status' => 'CO']);

        $r = $this->cotizar([]);
        $this->assertSame(2, $r['resultado'][7][0]['cupo'], 'mínimo del rango: 3 − 1 reserva');
        $this->assertSame(0, $r['resultado'][8][0]['cupo']);

        DB::table('cupo')->update(['release' => 60]);
        $this->assertSame(0, $this->cotizar(['hoy' => '2026-09-01'])['resultado'][7][0]['cupo'], 'dentro del release el cupo no cuenta');

        DB::table('soldout')->insert(['fk_producto_id' => $this->hotel, 'fk_tarifacategoria_id' => 7, 'vigencia_ini' => '2026-10-07', 'vigencia_fin' => '2026-10-07']);
        $r = $this->cotizar([]);
        $this->assertSame(1, $r['resultado'][7][0]['soldout']);
        $this->assertSame(0, $r['resultado'][7][0]['cupo']);

        DB::table('producto')->where('producto_id', $this->hotel)->update(['disponibilidad' => 'CI']);
        $this->assertSame(1, $this->cotizar([])['resultado'][8][0]['cupo'], "'CI' fuerza cupo 1");
    }

    public function test_carga_manual_usa_la_venta_cargada_sin_markup(): void
    {
        $this->vigencia(['cargamanual' => 1], [
            ['categoria' => 7, 'base' => '2', 'costo' => 80, 'venta' => [3 => 130]],
            ['categoria' => 7, 'base' => 'MN', 'costo' => 40, 'venta' => [3 => 50]],
        ]);

        $r = $this->cotizar(['tarifario_id' => 3]);
        $std = $r['resultado'][7][0];
        $this->assertSame(1, $std['cargamanual']);
        $this->assertEquals(130 * 3 * 2, $std['venta'], 'venta manual × noches × adultos, sin markup');

        $r = $this->cotizar(['tarifario_id' => 3, 'adultos' => 2, 'menores' => [8]]);
        $this->assertEquals(130 * 3 * 2 + 50 * 3, $r['resultado'][7][0]['venta']);

        // Otro tarifario sin filas manuales cae al markup.
        DB::table('tarifario')->insert(['tarifario_id' => 4, 'fk_sistema_id' => 1, 'tarifario_nombre' => 'Otro', 'fk_moneda_id' => 'USD']);
        $r = $this->cotizar(['tarifario_id' => 4]);
        $this->assertSame(0, $r['resultado'][7][0]['cargamanual']);
    }

    public function test_promocion_noches_y_tarifario_por_cliente(): void
    {
        $this->vigencia(['promo_noches' => 3, 'promo_pornoches' => 2, 'nota_promocion' => '3x2']);

        $r = $this->cotizar(['tarifario_id' => 0, 'cliente_id' => 50]);
        $this->assertSame(3, $r['params']['tarifario_id'], 'tarifario resuelto por rel_clientesistema');
        $std = $r['resultado'][7][0];
        $this->assertEquals(80 * 2 * 1, $std['descuento'], 'una noche gratis por persona');
        $this->assertSame('3 X 2', $std['textodescuento']);
        $this->assertSame('3x2', $std['nota_promocion']);
    }

    public function test_excursion_por_tramos_con_chd(): void
    {
        $exc = $this->productoDePrueba(['fk_tipoproducto_id' => 'EXC', 'producto_nombre' => 'City tour', 'modotarifa' => 'P']);
        DB::table('rel_productociudad')->insert(['fk_producto_id' => $exc, 'fk_ciudad_id' => 1]);
        app(VigenciaService::class)->guardar($exc, [
            'vigencia_ini' => '2026-10-01', 'vigencia_fin' => '2026-12-31', 'dias_semana' => [1, 2, 3, 4, 5, 6, 7], 'moneda_costo' => 'USD', 'residente' => '',
            'tramos' => [['min' => 1, 'max' => 2, 'costos' => ['ADU' => 100, 'CHD' => 50]], ['min' => 3, 'max' => 9, 'costos' => ['ADU' => 70]]],
        ]);

        $r = app(Tarifador::class)->cotizar(['producto_id' => $exc, 'fecha_ini' => '2026-10-05', 'adultos' => 2, 'menores' => [8], 'tarifario_id' => 3, 'hoy' => '2026-09-01']);
        $this->assertTrue($r['ok']);
        $this->assertSame(1, $r['params']['noches'], 'sin "hasta" es un día');
        $this->assertSame('De 1 a 2 pasajeros', $r['mejor']['nombre']);
        $this->assertEquals(100 * 2 + 50, $r['mejor']['costosiniva']);

        $r = app(Tarifador::class)->cotizar(['producto_id' => $exc, 'fecha_ini' => '2026-10-05', 'adultos' => 4, 'menores' => [8], 'tarifario_id' => 3, 'hoy' => '2026-09-01']);
        $this->assertEquals(70 * 4 + 70, $r['mejor']['costosiniva'], 'tramo 3-9 sin CHD: el menor paga adulto');
    }

    public function test_iva_venta_y_percepciones_en_mayorista(): void
    {
        DB::table('producto')->where('producto_id', $this->hotel)->update(['fk_sistema_id' => 2]);
        DB::table('tarifario')->where('tarifario_id', 3)->update(['fk_sistema_id' => 2]);
        DB::table('iva')->update(['fk_modoivaventa_id' => 0, 'iva_costo' => 0]);
        $this->vigencia();

        $std = $this->cotizar([])['resultado'][7][0];
        $this->assertEquals(0, $std['ivacosto']);
        $this->assertEquals(round($std['venta'] * 0.21, 2), $std['iva'], 'sin IVA de costo, el IVA va sobre la venta');
        $this->assertEquals(round(($std['venta'] + $std['iva'] - $std['comision']) * 0.012, 2), $std['percepcion1'], 'extra1 1,2 % del sistema mayorista');
        $this->assertEquals($std['venta'] + $std['iva'] + $std['percepcion1'], $std['total']);
    }

    public function test_producto_inexistente(): void
    {
        $r = app(Tarifador::class)->cotizar(['producto_id' => 9999, 'fecha_ini' => '2026-10-05']);
        $this->assertFalse($r['ok']);
    }
}
