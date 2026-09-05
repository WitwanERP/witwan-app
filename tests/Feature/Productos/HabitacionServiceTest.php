<?php

namespace Tests\Feature\Productos;

use App\Exceptions\Productos\ProductoException;
use App\Services\Productos\HabitacionService;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\CreaEsquemaProductos;
use Tests\TestCase;

/** La regla "no se borra ni deshabilita una categoría con tarifas" vive en el service, no en un botón. */
class HabitacionServiceTest extends TestCase
{
    use CreaEsquemaProductos;

    private int $producto;

    protected function setUp(): void
    {
        parent::setUp();
        $this->crearEsquemaProductos();
        $this->producto = $this->productoDePrueba();
        DB::table('tarifacategoria')->insert([
            ['tarifacategoria_id' => 7, 'fk_submodulo_id' => 'HOT', 'tarifacategoria_nombre' => 'Standard'],
            ['tarifacategoria_id' => 8, 'fk_submodulo_id' => 'HOT', 'tarifacategoria_nombre' => 'Suite'],
        ]);
    }

    private function conTarifa(int $categoria): void
    {
        DB::table('vigencia')->insert(['vigencia_id' => 1, 'fk_producto_id' => $this->producto]);
        DB::table('tarifa')->insert(['fk_vigencia_id' => 1, 'fk_tarifacategoria_id' => $categoria, 'fk_base_id' => '1', 'costo' => 10]);
    }

    public function test_alta_y_nombre_efectivo(): void
    {
        $svc = new HabitacionService;
        $ids = $svc->sincronizar($this->producto, [
            ['fk_tarifacategoria_id' => 7, 'nombre' => '', 'orden' => 2],
            ['fk_tarifacategoria_id' => 8, 'nombre' => 'Suite Premium', 'orden' => 1, 'max_child' => 2],
        ]);

        $this->assertCount(2, $ids);
        $lista = $svc->listar($this->producto);
        $this->assertSame(['Suite Premium', 'Standard'], array_column($lista, 'nombre'), 'orden por `orden`; sin nombre propio usa el del catálogo');
        $this->assertSame(2, $lista[0]['max_child']);
    }

    public function test_no_borra_ni_deshabilita_con_tarifas(): void
    {
        $svc = new HabitacionService;
        [$std, $suite] = $svc->sincronizar($this->producto, [['fk_tarifacategoria_id' => 7], ['fk_tarifacategoria_id' => 8]]);
        $this->conTarifa(7);

        $this->assertTrue($svc->listar($this->producto)[0]['tiene_tarifas']);
        $this->assertFalse($svc->listar($this->producto)[1]['tiene_tarifas']);

        try {
            $svc->sincronizar($this->producto, [['alojamientohabitacion_id' => $suite, 'fk_tarifacategoria_id' => 8]]);
            $this->fail('debía fallar');
        } catch (ProductoException $e) {
            $this->assertArrayHasKey('habitaciones', $e->errores());
        }
        $this->assertSame(2, DB::table('alojamientohabitacion')->where('fk_producto_id', $this->producto)->count());

        try {
            $svc->sincronizar($this->producto, [['alojamientohabitacion_id' => $std, 'fk_tarifacategoria_id' => 7, 'habilitar' => 0], ['alojamientohabitacion_id' => $suite, 'fk_tarifacategoria_id' => 8]]);
            $this->fail('debía fallar');
        } catch (ProductoException $e) {
            $this->assertArrayHasKey('habitaciones.0.habilitar', $e->errores());
        }
        $this->assertSame(1, (int) DB::table('alojamientohabitacion')->where('alojamientohabitacion_id', $std)->value('habilitar'));
    }

    public function test_borra_sin_tarifas_y_no_cambia_la_categoria_al_editar(): void
    {
        $svc = new HabitacionService;
        [$std, $suite] = $svc->sincronizar($this->producto, [['fk_tarifacategoria_id' => 7], ['fk_tarifacategoria_id' => 8]]);

        $svc->sincronizar($this->producto, [['alojamientohabitacion_id' => $std, 'fk_tarifacategoria_id' => 8, 'nombre' => 'Renombrada']]);

        $filas = DB::table('alojamientohabitacion')->where('fk_producto_id', $this->producto)->get();
        $this->assertCount(1, $filas);
        $this->assertSame(7, (int) $filas[0]->fk_tarifacategoria_id, 'la categoría no se cambia una vez creada');
        $this->assertSame('Renombrada', $filas[0]->alojamientohabitacion_nombre);
    }
}
