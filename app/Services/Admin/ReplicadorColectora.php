<?php

namespace App\Services\Admin;

use App\Support\Licencia;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Réplica de filas de catálogos compartidos hacia las bases "hijas" de una
 * licencia colectora (brain.licencia / brain.copy_licencia con base_colectora =
 * licencia actual). Es lo que hacen a mano los hooks _after_create/_after_edit/
 * after_delete de plancuenta.php, proveedor.php y Prestador.php del CI.
 *
 * Las hijas viven en el mismo servidor MySQL, así que se escriben con
 * `base.tabla` sobre la conexión del tenant, igual que el CI. Un fallo acá no
 * debe tirar abajo la escritura local: se loguea y sigue.
 */
class ReplicadorColectora
{
    /** REPLACE de la fila completa (SELECT *) en cada base hija. */
    public function replicarFila(string $tabla, string $pk, int $id, array $hijas): void
    {
        foreach ($hijas as $base) {
            $this->intentar(fn () => DB::statement(
                "REPLACE INTO `{$base}`.`{$tabla}` SELECT * FROM `{$tabla}` WHERE `{$pk}` = ?", [$id]
            ), $base, "replicar {$tabla}", $id);
        }
    }

    /** REPLACE de un subconjunto de columnas en cada base hija. */
    public function replicarColumnas(string $tabla, string $pk, int $id, array $columnas, array $hijas): void
    {
        $cols = implode(', ', array_map(fn ($c) => "`{$c}`", $columnas));

        foreach ($hijas as $base) {
            $this->intentar(fn () => DB::statement(
                "REPLACE INTO `{$base}`.`{$tabla}` ({$cols}) SELECT {$cols} FROM `{$tabla}` WHERE `{$pk}` = ?", [$id]
            ), $base, "replicar columnas {$tabla}", $id);
        }
    }

    public function borrarFila(string $tabla, string $pk, int $id, array $hijas): void
    {
        foreach ($hijas as $base) {
            $this->intentar(fn () => DB::statement(
                "DELETE FROM `{$base}`.`{$tabla}` WHERE `{$pk}` = ?", [$id]
            ), $base, "borrar {$tabla}", $id);
        }
    }

    /**
     * Bases hijas de la licencia actual. El CI consulta `licencia` en unos
     * módulos y `copy_licencia` en otros; acá se unen las dos fuentes.
     *
     * @param  bool  $sinEssential  excluye witwan_essential (como el CI en la mayoría de los casos)
     * @return list<string>
     */
    public function hijas(bool $sinEssential = true): array
    {
        try {
            $bases = [];
            foreach (['licencia', 'copy_licencia'] as $tabla) {
                $bases = array_merge($bases, DB::connection('license')
                    ->table($tabla)
                    ->where('base_colectora', Licencia::base())
                    ->pluck('licencia_base')
                    ->all());
            }

            return collect($bases)
                ->map(fn ($b) => (string) $b)
                ->filter(fn ($b) => preg_match('/^[a-z0-9_]+$/i', $b) === 1)
                ->reject(fn ($b) => $sinEssential && $b === 'witwan_essential')
                ->unique()
                ->values()
                ->all();
        } catch (Throwable $e) {
            Log::warning('colectora: no se pudieron leer las bases hijas', ['error' => $e->getMessage()]);

            return [];
        }
    }

    private function intentar(callable $fn, string $base, string $accion, int $id): void
    {
        try {
            $fn();
        } catch (Throwable $e) {
            Log::warning('colectora: fallo la replica a base hija', [
                'base' => $base, 'accion' => $accion, 'id' => $id, 'error' => $e->getMessage(),
            ]);
        }
    }
}
