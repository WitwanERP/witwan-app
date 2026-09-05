<?php

namespace Tests\Unit\Vigencias;

use App\Support\Productos\DiasSemana;
use Illuminate\Database\Query\Expression;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\CompilaSqlDeMysql;
use Tests\TestCase;

/**
 * Conversión entre `vigencia.weekdays` (bit(7) lunes→domingo) y
 * `rel_vigenciadia` (fk_dia_id 1..7). Es la única fuente de las dos
 * representaciones que el CI escribía por separado (bug B5).
 */
class DiasSemanaTest extends TestCase
{
    use CompilaSqlDeMysql;

    public function test_lunes_es_el_bit_mas_significativo(): void
    {
        $this->assertSame('1000000', DiasSemana::aBits([1]));
        $this->assertSame('0000001', DiasSemana::aBits([7]));
        $this->assertSame('1111100', DiasSemana::aBits([1, 2, 3, 4, 5]));
        $this->assertSame('1111111', DiasSemana::aBits(DiasSemana::TODOS));
    }

    public function test_ida_y_vuelta(): void
    {
        foreach ([[1], [2, 4, 6], [1, 2, 3, 4, 5, 6, 7], [7]] as $dias) {
            $this->assertSame($dias, DiasSemana::desdeBits(DiasSemana::aBits($dias)));
        }
    }

    /** producto_model.php:632-640: weekdays = 0 se lee como todos los días. */
    public function test_sin_bits_se_lee_como_todos_los_dias(): void
    {
        $this->assertSame(DiasSemana::TODOS, DiasSemana::desdeBits('0000000'));
        $this->assertSame(DiasSemana::TODOS, DiasSemana::desdeBits(0));
        $this->assertSame(DiasSemana::TODOS, DiasSemana::desdeBits(null));
        $this->assertSame(DiasSemana::TODOS, DiasSemana::aBits([]) === '0000000' ? DiasSemana::TODOS : []);
    }

    /** Un driver que no expande el bit devuelve el entero: 124 = b1111100. */
    public function test_acepta_el_valor_entero_del_bit(): void
    {
        $this->assertSame([1, 2, 3, 4, 5], DiasSemana::desdeBits(124));
        $this->assertSame([1, 2, 3, 4, 5], DiasSemana::desdeBits('124'));
    }

    public function test_normaliza_el_array_del_formulario(): void
    {
        $this->assertSame([1, 3, 7], DiasSemana::normalizar(['7', 3, '1', 3, 0, 8, 'x']));
    }

    public function test_en_mysql_escribe_el_literal_binario(): void
    {
        $this->usarGramaticaMysql();

        $valor = DiasSemana::valorParaGuardar([1, 2]);

        $this->assertInstanceOf(Expression::class, $valor);
        $this->assertSame("b'1100000'", (string) $valor->getValue(DB::connection()->getQueryGrammar()));

        $select = (string) DiasSemana::columnaSelect()->getValue(DB::connection()->getQueryGrammar());
        $this->assertStringContainsString('LPAD(BIN(vigencia.weekdays), 7', $select);
    }

    /** Con un driver sin bit(7) (SQLite) el valor viaja como string, no como expresión. */
    public function test_fuera_de_mysql_escribe_el_string(): void
    {
        config(['database.default' => 'sqlite']);
        DB::purge();

        $this->assertSame('1100000', DiasSemana::valorParaGuardar([1, 2]));
    }
}
