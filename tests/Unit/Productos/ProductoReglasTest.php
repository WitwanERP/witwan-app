<?php

namespace Tests\Unit\Productos;

use App\Services\Productos\ProductoReglas;
use Tests\TestCase;

/** Reglas de negocio del producto (doc §4.3). */
class ProductoReglasTest extends TestCase
{
    private function hotel(array $extra = []): array
    {
        return array_merge([
            'producto_nombre' => 'Hotel Test',
            'fk_proveedor_id' => 10,
            'modotarifa' => 'P',
            'destino' => 5,
            'bases' => ['1', '2', '3'],
            'edad_infoa' => 2,
            'edad_menor1' => 11,
            'edad_menor2' => 0,
            'edad_junior' => 17,
            'edad_senior' => 65,
        ], $extra);
    }

    private function tipoHotel(): array
    {
        return config('productos.tipos.HOT');
    }

    public function test_hotel_valido(): void
    {
        $this->assertSame([], (new ProductoReglas)->validar($this->hotel(), $this->tipoHotel()));
    }

    public function test_nombre_y_proveedor_obligatorios(): void
    {
        $e = (new ProductoReglas)->validar($this->hotel(['producto_nombre' => '  ', 'fk_proveedor_id' => 0]), $this->tipoHotel());

        $this->assertArrayHasKey('producto_nombre', $e);
        $this->assertArrayHasKey('fk_proveedor_id', $e);
    }

    public function test_modotarifa_segun_tipo(): void
    {
        $e = (new ProductoReglas)->validar($this->hotel(['modotarifa' => 'S']), $this->tipoHotel());
        $this->assertArrayHasKey('modotarifa', $e);

        $e = (new ProductoReglas)->validar($this->hotel(['modotarifa' => 'H']), $this->tipoHotel());
        $this->assertArrayNotHasKey('modotarifa', $e);

        $exc = (new ProductoReglas)->validar(['producto_nombre' => 'x', 'fk_proveedor_id' => 1, 'modotarifa' => 'S', 'destinos' => [['ciudad_id' => 3, 'tipo' => 'D']]], config('productos.tipos.EXC'));
        $this->assertSame([], $exc);
    }

    public function test_ciudad_unica_obligatoria_en_hotel(): void
    {
        $e = (new ProductoReglas)->validar($this->hotel(['destino' => 0]), $this->tipoHotel());

        $this->assertArrayHasKey('destino', $e);
    }

    public function test_destinos_multiples_con_tipo_valido(): void
    {
        $e = (new ProductoReglas)->validar(
            ['producto_nombre' => 'x', 'fk_proveedor_id' => 1, 'modotarifa' => 'P', 'destinos' => [['ciudad_id' => 0], ['ciudad_id' => 4, 'tipo' => 'X']]],
            config('productos.tipos.EXC'),
        );

        $this->assertArrayHasKey('destinos.0', $e);
        $this->assertArrayHasKey('destinos.1.tipo', $e);
    }

    public function test_bases_son_cantidades_de_adultos(): void
    {
        $e = (new ProductoReglas)->validar($this->hotel(['bases' => ['1', 'MN', '0']]), $this->tipoHotel());

        $this->assertArrayHasKey('bases.1', $e);
        $this->assertArrayHasKey('bases.2', $e);
        $this->assertArrayNotHasKey('bases.0', $e);
    }

    public function test_edades_crecientes(): void
    {
        $r = new ProductoReglas;

        $this->assertArrayHasKey('edad_menor1', $r->edades(['edad_infoa' => 5, 'edad_menor1' => 5]));
        $this->assertArrayHasKey('edad_junior', $r->edades(['edad_menor1' => 11, 'edad_menor2' => 14, 'edad_junior' => 12]));
        $this->assertArrayHasKey('edad_senior', $r->edades(['edad_junior' => 17, 'edad_senior' => 17]));

        // Un 0 no participa: infante 2, menor 2 en 0, junior 17 es válido.
        $this->assertSame([], $r->edades(['edad_infoa' => 2, 'edad_menor1' => 11, 'edad_menor2' => 0, 'edad_junior' => 17]));
        $this->assertSame([], $r->edades([]));
    }
}
