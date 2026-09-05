<?php

namespace Tests\Feature\Productos;

use App\Services\Vigencias\TarifaUpsert;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\CreaEsquemaProductos;
use Tests\TestCase;

/**
 * Escritura por diferencia sobre la clave única `vigenciaunica`. Cubre los
 * bugs B1 (vaciar una celda borraba todas las bases de la categoría) y el
 * cambio de tarifa_id en cada guardado del REPLACE INTO.
 */
class TarifaUpsertTest extends TestCase
{
    use CreaEsquemaProductos;

    protected function setUp(): void
    {
        parent::setUp();
        $this->crearEsquemaProductos();
        DB::table('vigencia')->insert(['vigencia_id' => 1, 'fk_producto_id' => 1]);
    }

    private function celda(int $categoria, string $base, float $costo, int $tarifario = 0): array
    {
        return ['fk_tarifario_id' => $tarifario, 'fk_tarifacategoria_id' => $categoria, 'fk_base_id' => $base, 'costo' => $costo, 'moneda_costo' => 'USD', 'redondear' => ' ', 'impuestos' => 0];
    }

    public function test_inserta_actualiza_y_conserva_ids(): void
    {
        $u = new TarifaUpsert;

        $stats = $u->sincronizar(1, [$this->celda(7, '1', 100), $this->celda(7, '2', 80)]);
        $this->assertSame(['insertadas' => 2, 'actualizadas' => 0, 'borradas' => 0, 'sin_cambios' => 0], $stats);

        $idAntes = DB::table('tarifa')->where('fk_base_id', '1')->value('tarifa_id');

        $stats = $u->sincronizar(1, [$this->celda(7, '1', 110), $this->celda(7, '2', 80)]);
        $this->assertSame(['insertadas' => 0, 'actualizadas' => 1, 'borradas' => 0, 'sin_cambios' => 1], $stats);

        $this->assertSame($idAntes, DB::table('tarifa')->where('fk_base_id', '1')->value('tarifa_id'), 'el tarifa_id se conserva (el REPLACE del CI lo cambiaba)');
        $this->assertEquals(110, DB::table('tarifa')->where('fk_base_id', '1')->value('costo'));
    }

    /** B1: vaciar la base 2 no debe tocar la 1 ni las de menores. */
    public function test_vaciar_una_celda_borra_solo_esa_celda(): void
    {
        $u = new TarifaUpsert;
        $u->sincronizar(1, [$this->celda(7, '1', 100), $this->celda(7, '2', 80), $this->celda(7, 'MN', 40), $this->celda(8, '2', 90)]);

        $stats = $u->sincronizar(1, [$this->celda(7, '1', 100), $this->celda(7, 'MN', 40), $this->celda(8, '2', 90)]);

        $this->assertSame(1, $stats['borradas']);
        $this->assertSame(['1', 'MN'], DB::table('tarifa')->where('fk_tarifacategoria_id', 7)->orderBy('tarifa_id')->pluck('fk_base_id')->all());
        $this->assertSame(1, DB::table('tarifa')->where('fk_tarifacategoria_id', 8)->count());
    }

    public function test_las_ventas_manuales_conviven_con_el_costo_en_la_misma_celda(): void
    {
        $u = new TarifaUpsert;
        $u->sincronizar(1, [$this->celda(7, '1', 100), $this->celda(7, '1', 130, 3), $this->celda(7, '1', 150000, 4)]);

        $this->assertSame(3, DB::table('tarifa')->count());
        $this->assertEquals(130, DB::table('tarifa')->where('fk_tarifario_id', 3)->value('costo'));

        // Sacar la carga manual deja sólo el costo.
        $stats = $u->sincronizar(1, [$this->celda(7, '1', 100)]);
        $this->assertSame(2, $stats['borradas']);
        $this->assertSame([0], DB::table('tarifa')->pluck('fk_tarifario_id')->map(fn ($v) => (int) $v)->all());
    }

    public function test_no_toca_las_tarifas_de_otras_vigencias(): void
    {
        DB::table('vigencia')->insert(['vigencia_id' => 2, 'fk_producto_id' => 1]);
        $u = new TarifaUpsert;
        $u->sincronizar(2, [$this->celda(7, '1', 999)]);

        $u->sincronizar(1, [$this->celda(7, '1', 100)]);
        $u->sincronizar(1, []);

        $this->assertSame(0, DB::table('tarifa')->where('fk_vigencia_id', 1)->count());
        $this->assertSame(1, DB::table('tarifa')->where('fk_vigencia_id', 2)->count());
    }

    public function test_tramos_por_pax_usan_min_max_y_tipopax_en_la_clave(): void
    {
        $u = new TarifaUpsert;
        $filas = [
            ['fk_tarifario_id' => 0, 'min_pax' => 1, 'max_pax' => 2, 'fk_tipopax_id' => 'ADU', 'costo' => 100, 'moneda_costo' => 'USD'],
            ['fk_tarifario_id' => 0, 'min_pax' => 1, 'max_pax' => 2, 'fk_tipopax_id' => 'CHD', 'costo' => 50, 'moneda_costo' => 'USD'],
            ['fk_tarifario_id' => 0, 'min_pax' => 3, 'max_pax' => 5, 'fk_tipopax_id' => 'ADU', 'costo' => 90, 'moneda_costo' => 'USD'],
        ];

        $this->assertSame(3, $u->sincronizar(1, $filas)['insertadas']);
        $this->assertSame(3, $u->sincronizar(1, $filas)['sin_cambios'], 'el bloque corre UNA vez y es idempotente (B4)');
    }

    public function test_una_clave_repetida_en_el_payload_pisa_como_el_replace(): void
    {
        $u = new TarifaUpsert;
        $stats = $u->sincronizar(1, [$this->celda(7, '1', 100), $this->celda(7, '1', 120)]);

        $this->assertSame(1, DB::table('tarifa')->count());
        $this->assertEquals(120, DB::table('tarifa')->value('costo'));
        $this->assertSame(1, $stats['insertadas']);
    }

    public function test_copiar_con_ajuste_porcentual(): void
    {
        DB::table('vigencia')->insert(['vigencia_id' => 2, 'fk_producto_id' => 1]);
        $u = new TarifaUpsert;
        $u->sincronizar(1, [$this->celda(7, '1', 100), $this->celda(7, '2', 80.5)]);

        $this->assertSame(2, $u->copiar(1, 2, 10));

        $this->assertEquals([110, 88.55], DB::table('tarifa')->where('fk_vigencia_id', 2)->orderBy('fk_base_id')->pluck('costo')->map(fn ($c) => (float) $c)->all());
        $this->assertEquals([100, 80.5], DB::table('tarifa')->where('fk_vigencia_id', 1)->orderBy('fk_base_id')->pluck('costo')->map(fn ($c) => (float) $c)->all());
    }
}
