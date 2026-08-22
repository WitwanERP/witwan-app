<?php

namespace App\Support\Contable;

use Carbon\CarbonImmutable;

/**
 * Agrupación semanal de los boletos BSP.
 *
 * Port de la lógica embebida en `ajax.php::servicioproveedor()`
 * (application/controllers/ajax.php:300-370 del CI). Los boletos aéreos se
 * facturan por semana, y "semana" cambió de definición:
 *
 *   - Hasta 2021-12-01 inclusive: la semana calendario, representada por su
 *     lunes.
 *   - Después: cuartos de mes fijos, con corte los días 1, 8, 16 y 23.
 *
 * Rareza del régimen viejo que se replica a propósito: para los domingos el
 * legacy usa `monday last week` en vez de `monday this week`. PHP ya asigna el
 * domingo a la semana lunes-domingo que lo contiene, así que ese caso especial no
 * corrige nada: corre el domingo una semana MÁS atrás (un domingo 28/11 queda en
 * la semana del 15/11, no en la del 22/11). Sólo afecta a datos anteriores a
 * diciembre de 2021, que ya son históricos, y cambiarlo movería boletos viejos de
 * grupo; se mantiene igual y queda cubierto por test.
 *
 * Se separó en su propia clase porque es la regla más idiosincrática del módulo
 * y la que más fácil se rompe al portarla.
 */
final class SemanaBsp
{
    /** Fecha a partir de la cual rigen los cuartos de mes. */
    private const CORTE_REGIMEN = '2021-12-01';

    /** Días de inicio de cada cuarto de mes. */
    private const CORTES_MES = [1, 8, 16, 23];

    /**
     * Devuelve el primer día de la semana (Y-m-d) a la que pertenece la fecha,
     * o null si no hay fecha utilizable ("SIN SEMANA" en el legacy).
     */
    public static function desde(?string $fecha): ?string
    {
        if ($fecha === null || $fecha === '' || str_starts_with($fecha, '0000')) {
            return null;
        }

        try {
            $f = CarbonImmutable::parse($fecha);
        } catch (\Throwable) {
            return null;
        }

        // La comparación del legacy es textual sobre Y-m-d, así que el propio
        // 2021-12-01 cae en el régimen viejo.
        if ($f->format('Y-m-d') > self::CORTE_REGIMEN) {
            return $f->startOfMonth()->addDays(self::inicioDeCuarto($f->day) - 1)->format('Y-m-d');
        }

        // isoWeekday(): 7 es domingo.
        $lunes = $f->isoWeekday() === 7
            ? $f->startOfWeek(CarbonImmutable::MONDAY)->subWeek()
            : $f->startOfWeek(CarbonImmutable::MONDAY);

        return $lunes->format('Y-m-d');
    }

    /** Etiqueta que ve el usuario, igual que en el legacy. */
    public static function etiqueta(?string $semana): string
    {
        if ($semana === null) {
            return 'SIN SEMANA';
        }

        return 'SEMANA '.CarbonImmutable::parse($semana)->format('d/m/Y');
    }

    private static function inicioDeCuarto(int $dia): int
    {
        $inicio = self::CORTES_MES[0];

        foreach (self::CORTES_MES as $corte) {
            if ($dia >= $corte) {
                $inicio = $corte;
            }
        }

        return $inicio;
    }
}
