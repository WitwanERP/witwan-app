<?php

namespace App\Services\Vigencias;

/**
 * Bases (columnas de la grilla de tarifas de alojamiento) de un producto.
 *
 * Port EXACTO de vigencia.php:276-320. Es la regla más delicada del módulo
 * porque el tarifador busca la fila de `tarifa` por `fk_base_id` según la edad
 * del menor (tarifa_model.php:2503-2534): si acá se genera una base de más o
 * de menos, el CI no encuentra el costo y el producto no tarifa.
 *
 * Reglas del legacy, en el orden en que PHP las inserta en el array:
 *
 *   1. Bases numéricas del producto ("1,2,3" si no tiene): cantidad de adultos.
 *   2. Para PAQ se fuerza max_child = 1 (una sola columna de menor).
 *   3. Por cada menor a = 1..max_child:
 *        - si a == 1 y edad_infoa > 0     → INF
 *        - si edad_menor1 > 0             → MN, MN2, MN3...
 *   4. Por cada menor a = 1..max_child, si edad_menor2 > 0 → M2, M22, M23...
 *   5. Si max_child > 0 y edad_junior > 0 → JNR
 *
 * O sea: con max_child = 0 no hay NINGUNA base de menor aunque las edades
 * estén cargadas; INF y JNR aparecen una sola vez; MN y M2 se repiten por
 * cantidad de menores admitidos.
 *
 * Clase pura: no toca la base. Recibe lo que ya cargó ProductoService.
 */
final class BasesResolver
{
    /**
     * @param  list<string>  $basesProducto  bases numéricas del producto (rel_productobase o extra `bases`)
     * @param  array{edad_infoa?:int,edad_menor1?:int,edad_menor2?:int,edad_junior?:int}  $edades
     * @param  int  $maxChild  alojamientohabitacion.max_child de la categoría
     * @param  string  $tipoProducto  fk_tipoproducto_id (PAQ fuerza max_child = 1)
     * @return list<string>
     */
    public function resolver(array $basesProducto, array $edades, int $maxChild, string $tipoProducto): array
    {
        $bases = $this->numericas($basesProducto);

        if ($tipoProducto === 'PAQ') {
            $maxChild = 1;
        }

        $infoa = (int) ($edades['edad_infoa'] ?? 0);
        $menor1 = (int) ($edades['edad_menor1'] ?? 0);
        $menor2 = (int) ($edades['edad_menor2'] ?? 0);
        $junior = (int) ($edades['edad_junior'] ?? 0);

        for ($a = 1; $a <= $maxChild; $a++) {
            if ($a === 1 && $infoa > 0) {
                $bases[] = 'INF';
            }
            if ($menor1 > 0) {
                $bases[] = $a > 1 ? 'MN'.$a : 'MN';
            }
        }

        for ($a = 1; $a <= $maxChild; $a++) {
            if ($menor2 > 0) {
                $bases[] = $a > 1 ? 'M2'.$a : 'M2';
            }
        }

        if ($maxChild > 0 && $junior > 0) {
            $bases[] = 'JNR';
        }

        return $bases;
    }

    /**
     * Bases numéricas efectivas del producto: las cargadas, o el default del
     * legacy ("1,2,3") si no tiene ninguna (vigencia.php:278-280).
     *
     * @return list<string>
     */
    public function numericas(array $basesProducto): array
    {
        $bases = array_values(array_filter(array_map(fn ($b) => trim((string) $b), $basesProducto), fn ($b) => $b !== '' && ctype_digit($b)));

        if ($bases === []) {
            return array_map('strval', (array) config('productos.bases_default', ['1', '2', '3']));
        }

        // El legacy ordena las bases del producto con sort() (producto_model.php:937).
        sort($bases, SORT_NUMERIC);

        return array_values(array_unique($bases));
    }

    /** ¿La base es de adultos (numérica) o pseudo-base de menor? */
    public static function esNumerica(string $base): bool
    {
        return ctype_digit($base);
    }
}
