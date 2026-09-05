<?php

namespace App\Support\Productos;

use Illuminate\Database\Query\Expression;
use Illuminate\Support\Facades\DB;

/**
 * Días de semana de una vigencia: `vigencia.weekdays` (bit(7), lunes→domingo)
 * y `rel_vigenciadia` (una fila por fk_dia_id 1..7).
 *
 * El CI escribe las dos cosas desde el mismo POST (vigencia.php:236-256) pero
 * después LEE cada una en un lugar distinto: el form desde `rel_vigenciadia`
 * (producto_model.php:648) y el tarifador desde `weekdays`
 * (tarifa_model.php:2181). Es el bug B5 del análisis. Acá las dos
 * representaciones salen SIEMPRE del mismo array, y este helper es el único
 * lugar que sabe convertir entre ellas.
 *
 * Convenciones del legacy que se respetan:
 *  - `weekdays = 0` (ninguno marcado) se lee como "todos los días"
 *    (producto_model.php:632-640), así que una vigencia sin días no filtra.
 *  - El bit más significativo es el lunes: BIN(weekdays) = "1111100" es
 *    lunes a viernes; el tarifador lo lee con LPAD(...,7,'0') y SUBSTRING(N).
 */
final class DiasSemana
{
    public const TODOS = [1, 2, 3, 4, 5, 6, 7];

    /** [1,2,5] → "1100100" */
    public static function aBits(array $dias): string
    {
        $dias = array_map('intval', $dias);
        $bits = '';
        for ($d = 1; $d <= 7; $d++) {
            $bits .= in_array($d, $dias, true) ? '1' : '0';
        }

        return $bits;
    }

    /**
     * "1100100" → [1,2,5]. Acepta lo que devuelva la base: el string de BIN(),
     * un entero (SQLite / driver que no castea bit) o null.
     * Un valor sin ningún bit se interpreta como todos los días (legacy).
     */
    public static function desdeBits(int|string|null $bits): array
    {
        if ($bits === null) {
            return self::TODOS;
        }

        // MySQL (LPAD(BIN())) y SQLite (string guardado) devuelven 7 caracteres 0/1;
        // cualquier otra cosa es el valor entero del bit(7).
        $cadena = preg_match('/^[01]{7}$/', (string) $bits)
            ? (string) $bits
            : str_pad(decbin((int) $bits), 7, '0', STR_PAD_LEFT);

        $dias = [];
        for ($d = 1; $d <= 7; $d++) {
            if (substr($cadena, $d - 1, 1) === '1') {
                $dias[] = $d;
            }
        }

        return $dias === [] ? self::TODOS : $dias;
    }

    /** Normaliza un array de días del formulario: enteros 1..7, únicos, ordenados. */
    public static function normalizar(array $dias): array
    {
        $dias = array_values(array_unique(array_filter(array_map('intval', $dias), fn ($d) => $d >= 1 && $d <= 7)));
        sort($dias);

        return $dias;
    }

    /**
     * Valor para escribir en `vigencia.weekdays`. En MySQL el bit(7) se carga con
     * el literal b'...' (vigencia.php:250); en SQLite (tests) se guarda el string.
     */
    public static function valorParaGuardar(array $dias): Expression|string
    {
        $bits = self::aBits($dias);

        return self::esMysql() ? DB::raw("b'{$bits}'") : $bits;
    }

    /**
     * Expresión SELECT que devuelve los bits como string de 7 caracteres,
     * independientemente del driver.
     */
    public static function columnaSelect(string $alias = 'weekdays_bits', string $tabla = 'vigencia'): Expression
    {
        return self::esMysql()
            ? DB::raw("LPAD(BIN({$tabla}.weekdays), 7, '0') AS {$alias}")
            : DB::raw("{$tabla}.weekdays AS {$alias}");
    }

    private static function esMysql(): bool
    {
        return in_array(DB::connection()->getDriverName(), ['mysql', 'mariadb'], true);
    }
}
