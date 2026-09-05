<?php

namespace Tests\Unit\Vigencias;

use App\Services\Vigencias\BasesResolver;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Derivación de bases de la grilla de alojamiento, fiel a vigencia.php:276-320.
 *
 * Los casos viven en tests/Fixtures/bases_resolver.json. Es el contrato con el
 * tarifador del CI: busca `tarifa.fk_base_id` por edad del menor
 * (tarifa_model.php:2503-2534), así que el ORDEN y la cantidad exacta de
 * pseudo-bases importan.
 */
class BasesResolverTest extends TestCase
{
    public static function casos(): array
    {
        $json = json_decode(file_get_contents(__DIR__.'/../../Fixtures/bases_resolver.json'), true);

        return array_combine(array_column($json, 'nombre'), array_map(fn ($c) => [$c], $json));
    }

    #[DataProvider('casos')]
    public function test_deriva_las_bases_como_el_legacy(array $caso): void
    {
        $bases = (new BasesResolver)->resolver($caso['bases'], $caso['edades'], $caso['max_child'], $caso['tipo']);

        $this->assertSame($caso['esperado'], $bases, $caso['nombre']);
    }

    public function test_distingue_bases_numericas_de_pseudo_bases(): void
    {
        $this->assertTrue(BasesResolver::esNumerica('2'));
        $this->assertFalse(BasesResolver::esNumerica('MN2'));
        $this->assertFalse(BasesResolver::esNumerica('INF'));
    }

    public function test_el_default_de_bases_sale_de_config(): void
    {
        config(['productos.bases_default' => ['1', '2']]);

        $this->assertSame(['1', '2'], (new BasesResolver)->numericas([]));
        $this->assertSame(['1', '2'], (new BasesResolver)->numericas(['', ' ', 'x']));
    }
}
