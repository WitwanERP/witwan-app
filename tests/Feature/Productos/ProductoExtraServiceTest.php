<?php

namespace Tests\Feature\Productos;

use App\Services\Productos\ProductoExtraService;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\CreaEsquemaProductos;
use Tests\TestCase;

/** EAV producto_extra sin REPLACE: una fila por clave, sin duplicados, sin tocar lo que no viene. */
class ProductoExtraServiceTest extends TestCase
{
    use CreaEsquemaProductos;

    protected function setUp(): void
    {
        parent::setUp();
        $this->crearEsquemaProductos();
    }

    public function test_sincroniza_por_diferencia(): void
    {
        $svc = new ProductoExtraService;

        $s = $svc->sincronizar(1, ['zona' => 'Centro', 'edad_infoa' => 2, 'vacio' => '', 'nulo' => null, 'flag' => true]);
        $this->assertSame(['insertadas' => 3, 'borradas' => 0, 'sin_cambios' => 0], $s);

        $s = $svc->sincronizar(1, ['zona' => 'Centro', 'edad_infoa' => 3, 'flag' => '']);
        $this->assertSame(['insertadas' => 1, 'borradas' => 1, 'sin_cambios' => 1], $s);

        $this->assertEquals(['edad_infoa' => '3', 'zona' => 'Centro'], $svc->leer(1));
    }

    /** El REPLACE del CI dejaba claves repetidas; byid() se quedaba con la última por regdate. */
    public function test_limpia_duplicados_heredados_y_lee_la_ultima(): void
    {
        DB::table('producto_extra')->insert([
            ['fk_producto_id' => 1, 'extra_nombre' => 'zona', 'extra_valor' => 'Vieja', 'regdate' => '2020-01-01 00:00:00'],
            ['fk_producto_id' => 1, 'extra_nombre' => 'zona', 'extra_valor' => 'Nueva', 'regdate' => '2021-01-01 00:00:00'],
        ]);
        $svc = new ProductoExtraService;

        $this->assertSame('Nueva', $svc->leer(1)['zona']);

        $svc->sincronizar(1, ['zona' => 'Nueva']);
        $this->assertSame(1, DB::table('producto_extra')->where('fk_producto_id', 1)->where('extra_nombre', 'zona')->count());
        $this->assertSame('Nueva', $svc->leer(1)['zona']);
    }

    public function test_arrays_se_guardan_como_json_y_no_mezcla_productos(): void
    {
        $svc = new ProductoExtraService;
        $svc->sincronizar(1, ['itinerario' => [['dia' => 1, 'texto' => 'Llegada']]]);
        $svc->sincronizar(2, ['itinerario' => 'otro']);

        $this->assertSame('[{"dia":1,"texto":"Llegada"}]', $svc->leer(1)['itinerario']);
        $this->assertSame('otro', $svc->leer(2)['itinerario']);

        $svc->copiar(1, 3);
        $this->assertSame($svc->leer(1), $svc->leer(3));
    }
}
