<?php

namespace App\Services\Vigencias;

use Illuminate\Support\Facades\DB;

/**
 * Escritura por diferencia de las filas de `tarifa` de una vigencia.
 *
 * El CI hace `REPLACE INTO tarifa` celda por celda (vigencia.php:335-428):
 * cambia el `tarifa_id` en cada guardado y, al vaciar una celda, ejecuta un
 * DELETE cuyo filtro de base quedó dentro del intval() (bug B1: borra todas
 * las bases de la categoría). Además el bloque de tramos corre 15 veces (B4).
 *
 * Acá se lee lo que hay, se compara contra lo deseado por la clave única
 * `vigenciaunica` (fk_vigencia_id, fk_tarifario_id, fk_base_id, min_pax,
 * max_pax, fk_tipopax_id, fk_tarifacategoria_id) y se hace lo mínimo:
 *   - clave nueva            → INSERT
 *   - clave existente igual  → nada (conserva tarifa_id)
 *   - clave existente distinta → UPDATE
 *   - clave que ya no viene  → DELETE de ESA fila
 *
 * `moneda_costo`, `impuestos` y `redondear` van en cada fila porque el CI los
 * lee de ahí (producto_model.php:691-703). Se llama dentro de la transacción
 * de VigenciaService.
 */
class TarifaUpsert
{
    public const CLAVE = ['fk_tarifario_id', 'fk_base_id', 'min_pax', 'max_pax', 'fk_tipopax_id', 'fk_tarifacategoria_id'];

    private const VALORES = ['costo', 'moneda_costo', 'impuestos', 'redondear', 'ivacosto', 'moneda_venta'];

    /**
     * @param  list<array>  $deseadas  filas completas (clave + valores) SIN fk_vigencia_id
     * @return array{insertadas:int, actualizadas:int, borradas:int, sin_cambios:int}
     */
    public function sincronizar(int $vigenciaId, array $deseadas): array
    {
        $existentes = [];
        foreach (DB::table('tarifa')->where('fk_vigencia_id', $vigenciaId)->get() as $fila) {
            $existentes[$this->clave((array) $fila)] = (array) $fila;
        }

        $stats = ['insertadas' => 0, 'actualizadas' => 0, 'borradas' => 0, 'sin_cambios' => 0];
        $vistas = [];

        foreach ($deseadas as $fila) {
            $fila = $this->normalizar($fila);
            $clave = $this->clave($fila);

            if (isset($vistas[$clave])) {
                // Dos celdas con la misma clave en el payload: la última manda,
                // como el REPLACE del CI. Pisa lo recién escrito sin contarlo.
                DB::table('tarifa')->where('tarifa_id', $existentes[$clave]['tarifa_id'])->update($fila);

                continue;
            }
            $vistas[$clave] = true;

            if (! isset($existentes[$clave])) {
                $id = (int) DB::table('tarifa')->insertGetId($fila + ['fk_vigencia_id' => $vigenciaId]);
                $existentes[$clave] = $fila + ['tarifa_id' => $id];
                $stats['insertadas']++;

                continue;
            }

            $actual = $existentes[$clave];
            $cambios = [];
            foreach (self::VALORES as $col) {
                if (! array_key_exists($col, $fila)) {
                    continue;
                }
                if (! $this->igual($actual[$col] ?? null, $fila[$col])) {
                    $cambios[$col] = $fila[$col];
                }
            }

            if ($cambios === []) {
                $stats['sin_cambios']++;

                continue;
            }

            DB::table('tarifa')->where('tarifa_id', $actual['tarifa_id'])->update($cambios);
            $stats['actualizadas']++;
        }

        foreach ($existentes as $clave => $actual) {
            if (! isset($vistas[$clave])) {
                DB::table('tarifa')->where('tarifa_id', $actual['tarifa_id'])->delete();
                $stats['borradas']++;
            }
        }

        return $stats;
    }

    /** Copia las tarifas de una vigencia a otra, opcionalmente ajustando el costo ±%. */
    public function copiar(int $desde, int $hacia, float $ajustePorcentaje = 0): int
    {
        $n = 0;
        foreach (DB::table('tarifa')->where('fk_vigencia_id', $desde)->get() as $fila) {
            $fila = (array) $fila;
            unset($fila['tarifa_id']);
            $fila['fk_vigencia_id'] = $hacia;
            if ($ajustePorcentaje != 0) {
                $fila['costo'] = round((float) $fila['costo'] * (1 + $ajustePorcentaje / 100), 2);
            }
            DB::table('tarifa')->insert($fila);
            $n++;
        }

        return $n;
    }

    public function borrarTodas(int $vigenciaId): int
    {
        return DB::table('tarifa')->where('fk_vigencia_id', $vigenciaId)->delete();
    }

    /** Fila lista para insertar: clave completa con los defaults del legacy. */
    public function normalizar(array $fila): array
    {
        return [
            'fk_tarifario_id' => (int) ($fila['fk_tarifario_id'] ?? 0),
            'fk_tarifacategoria_id' => (int) ($fila['fk_tarifacategoria_id'] ?? 0),
            'fk_base_id' => (string) ($fila['fk_base_id'] ?? ''),
            'min_pax' => (int) ($fila['min_pax'] ?? 0),
            'max_pax' => (int) ($fila['max_pax'] ?? 0),
            'fk_tipopax_id' => (string) ($fila['fk_tipopax_id'] ?? ''),
            'costo' => round((float) ($fila['costo'] ?? 0), 2),
            'moneda_costo' => (string) ($fila['moneda_costo'] ?? ''),
            'moneda_venta' => (string) ($fila['moneda_venta'] ?? ''),
            'impuestos' => round((float) ($fila['impuestos'] ?? 0), 2),
            'ivacosto' => round((float) ($fila['ivacosto'] ?? 0), 2),
            'redondear' => (string) ($fila['redondear'] ?? ''),
            'fk_costo_id' => (int) ($fila['fk_costo_id'] ?? 0),
        ];
    }

    private function clave(array $fila): string
    {
        return implode('|', array_map(fn ($c) => (string) ($fila[$c] ?? ''), self::CLAVE));
    }

    private function igual(mixed $a, mixed $b): bool
    {
        if (is_numeric($a) && is_numeric($b)) {
            return abs((float) $a - (float) $b) < 0.005;
        }

        return (string) $a === (string) $b;
    }
}
