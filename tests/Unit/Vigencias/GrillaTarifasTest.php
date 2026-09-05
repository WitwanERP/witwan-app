<?php

namespace Tests\Unit\Vigencias;

use App\Services\Vigencias\BasesResolver;
use App\Services\Vigencias\GrillaTarifas;
use Tests\TestCase;

/**
 * La grilla se arma en el server: (categorías × bases) o (tramos × tipos de
 * pax), con costo (fk_tarifario_id = 0) y ventas manuales por tarifario.
 */
class GrillaTarifasTest extends TestCase
{
    private function grilla(): GrillaTarifas
    {
        return new GrillaTarifas(new BasesResolver);
    }

    private function tarifarios(): array
    {
        return [
            ['tarifario_id' => 3, 'tarifario_nombre' => 'Agencias', 'fk_moneda_id' => 'USD', 'divisor_markup' => 0.8],
            ['tarifario_id' => 4, 'tarifario_nombre' => 'Directo', 'fk_moneda_id' => 'ARS'],
        ];
    }

    public function test_alojamiento_categorias_por_bases_con_costo_y_venta_manual(): void
    {
        $producto = ['fk_tipoproducto_id' => 'HOT', 'bases' => ['1', '2'], 'edades' => ['edad_infoa' => 2, 'edad_menor1' => 11]];
        $habitaciones = [
            ['fk_tarifacategoria_id' => 7, 'nombre' => 'Standard', 'max_child' => 1],
            ['fk_tarifacategoria_id' => 8, 'nombre' => 'Suite', 'max_child' => 0],
        ];
        $tarifas = [
            ['tarifa_id' => 100, 'fk_tarifario_id' => 0, 'fk_tarifacategoria_id' => 7, 'fk_base_id' => '1', 'costo' => '100.00'],
            ['tarifa_id' => 101, 'fk_tarifario_id' => 0, 'fk_tarifacategoria_id' => 7, 'fk_base_id' => 'MN', 'costo' => '50.00'],
            ['tarifa_id' => 102, 'fk_tarifario_id' => 3, 'fk_tarifacategoria_id' => 7, 'fk_base_id' => '1', 'costo' => '130.00'],
            ['tarifa_id' => 103, 'fk_tarifario_id' => 0, 'fk_tarifacategoria_id' => 8, 'fk_base_id' => '2', 'costo' => '90.00'],
        ];

        $g = $this->grilla()->armar($producto, $habitaciones, $this->tarifarios(), $tarifas);

        $this->assertSame('alojamiento', $g['modo']);
        $this->assertSame([3, 4], array_column($g['tarifarios'], 'id'));
        $this->assertSame(0.8, $g['tarifarios'][0]['divisor_markup']);

        $std = $g['filas'][0];
        $this->assertSame(7, $std['categoria']);
        $this->assertSame(['1', '2', 'INF', 'MN'], $std['bases']);
        $this->assertSame(['base' => '1', 'tarifa_id' => 100, 'costo' => 100.0, 'venta' => [3 => 130.0, 4 => null]], $std['celdas'][0]);
        $this->assertNull($std['celdas'][1]['costo']);
        $this->assertSame(50.0, $std['celdas'][3]['costo']);

        // Suite sin menores: sólo bases numéricas.
        $suite = $g['filas'][1];
        $this->assertSame(['1', '2'], $suite['bases']);
        $this->assertSame(90.0, $suite['celdas'][1]['costo']);
    }

    /** vigencia.php:262-267: sin habitaciones se tarifa una fila "General" con categoría 0. */
    public function test_alojamiento_sin_habitaciones_usa_la_fila_general(): void
    {
        $g = $this->grilla()->armar(['fk_tipoproducto_id' => 'PAQ', 'bases' => [], 'edades' => ['edad_menor1' => 11]], [], [], []);

        $this->assertCount(1, $g['filas']);
        $this->assertSame(0, $g['filas'][0]['categoria']);
        $this->assertSame('General', $g['filas'][0]['nombre']);
        $this->assertSame(['1', '2', '3', 'MN'], $g['filas'][0]['bases']);
    }

    public function test_tramos_ordenados_por_min_max_con_tipos_de_pax_del_tipo(): void
    {
        $tarifas = [
            ['fk_tarifario_id' => 0, 'min_pax' => 3, 'max_pax' => 5, 'fk_tipopax_id' => 'ADU', 'costo' => '80'],
            ['fk_tarifario_id' => 0, 'min_pax' => 1, 'max_pax' => 2, 'fk_tipopax_id' => 'ADU', 'costo' => '100'],
            ['fk_tarifario_id' => 0, 'min_pax' => 1, 'max_pax' => 2, 'fk_tipopax_id' => '', 'costo' => '100'],
            ['fk_tarifario_id' => 0, 'min_pax' => 1, 'max_pax' => 2, 'fk_tipopax_id' => 'CHD', 'costo' => '60'],
            ['fk_tarifario_id' => 3, 'min_pax' => 1, 'max_pax' => 2, 'fk_tipopax_id' => 'ADU', 'costo' => '150'],
        ];

        $g = $this->grilla()->armar(['fk_tipoproducto_id' => 'EXC'], [], $this->tarifarios(), $tarifas);

        $this->assertSame('tramos', $g['modo']);
        $this->assertSame(['ADU', 'CHD', 'INF'], $g['tipos_pax']);
        $this->assertSame([1, 3], array_column($g['filas'], 'min'));
        $this->assertSame(['ADU' => 100.0, 'CHD' => 60.0, 'INF' => null], $g['filas'][0]['costos']);
        $this->assertSame(150.0, $g['filas'][0]['venta'][3]['ADU']);
        $this->assertNull($g['filas'][0]['venta'][4]['ADU']);
    }

    public function test_los_tipos_de_pax_dependen_del_tipo_de_producto(): void
    {
        $this->assertSame(['ADU', 'CHD', 'INF', 'JNR', 'SNR'], $this->grilla()->armar(['fk_tipoproducto_id' => 'TRE'], [], [], [])['tipos_pax']);
        $this->assertSame(['<70', '>70'], $this->grilla()->armar(['fk_tipoproducto_id' => 'ASV'], [], [], [])['tipos_pax']);
    }
}
