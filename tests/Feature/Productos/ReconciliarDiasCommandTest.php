<?php

namespace Tests\Feature\Productos;

use App\Support\Productos\DiasSemana;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\CreaEsquemaProductos;
use Tests\TestCase;

/** vigencias:reconciliar-dias reporta y sólo corrige con --aplicar (bug B5). */
class ReconciliarDiasCommandTest extends TestCase
{
    use CreaEsquemaProductos;

    protected function setUp(): void
    {
        parent::setUp();
        $this->crearEsquemaProductos();

        // 1: coinciden. 2: difieren. 3: sin rel (no es conflicto). 4: difiere, otro producto.
        foreach ([1 => [1, [1, 2, 3, 4, 5]], 2 => [1, [1, 2, 3, 4, 5]], 3 => [1, [1]], 4 => [2, [6, 7]]] as $id => [$producto, $dias]) {
            DB::table('vigencia')->insert(['vigencia_id' => $id, 'fk_producto_id' => $producto, 'weekdays' => DiasSemana::valorParaGuardar($dias)]);
        }
        foreach ([1, 2, 3, 4, 5] as $d) {
            DB::table('rel_vigenciadia')->insert(['fk_vigencia_id' => 1, 'fk_dia_id' => $d]);
        }
        foreach ([6, 7] as $d) {
            DB::table('rel_vigenciadia')->insert(['fk_vigencia_id' => 2, 'fk_dia_id' => $d]);
        }
        DB::table('rel_vigenciadia')->insert(['fk_vigencia_id' => 4, 'fk_dia_id' => 1]);
    }

    public function test_reporta_sin_tocar(): void
    {
        $this->artisan('vigencias:reconciliar-dias')
            ->expectsOutputToContain('2 vigencia(s) con diferencias.')
            ->expectsOutputToContain('Nada modificado')
            ->assertSuccessful();

        $this->assertSame('1111100', $this->bitsDeVigencia(2));
        $this->assertSame([6, 7], DB::table('rel_vigenciadia')->where('fk_vigencia_id', 2)->pluck('fk_dia_id')->map(fn ($d) => (int) $d)->all());
    }

    public function test_aplica_tomando_weekdays(): void
    {
        $this->artisan('vigencias:reconciliar-dias', ['--aplicar' => true, '--fuente' => 'weekdays', '--producto' => 1])->assertSuccessful();

        $this->assertSame([1, 2, 3, 4, 5], DB::table('rel_vigenciadia')->where('fk_vigencia_id', 2)->orderBy('fk_dia_id')->pluck('fk_dia_id')->map(fn ($d) => (int) $d)->all());
        $this->assertSame('1111100', $this->bitsDeVigencia(2));
        $this->assertSame([1], DB::table('rel_vigenciadia')->where('fk_vigencia_id', 4)->pluck('fk_dia_id')->map(fn ($d) => (int) $d)->all(), 'fuera del --producto no se toca');
    }

    public function test_aplica_tomando_rel(): void
    {
        $this->artisan('vigencias:reconciliar-dias', ['--aplicar' => true, '--fuente' => 'rel'])->assertSuccessful();

        $this->assertSame('0000011', $this->bitsDeVigencia(2));
        $this->assertSame('1000000', $this->bitsDeVigencia(4));
        $this->assertSame('1000000', $this->bitsDeVigencia(3), 'sin rel no hay conflicto');
        $this->artisan('vigencias:reconciliar-dias')->expectsOutputToContain('Sin diferencias')->assertSuccessful();
    }

    public function test_fuente_invalida(): void
    {
        $this->artisan('vigencias:reconciliar-dias', ['--fuente' => 'otra'])->assertFailed();
    }
}
