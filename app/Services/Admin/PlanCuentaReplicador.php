<?php

namespace App\Services\Admin;

use App\Support\Licencia;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Réplica del plan de cuentas hacia las bases "hijas" de una colectora
 * (brain.copy_licencia.base_colectora = licencia actual), tal como lo hacen
 * los hooks _after_create/_after_edit/after_delete de administracion/plancuenta.php.
 *
 * Se conservan las condiciones del legacy, que no son simétricas:
 *  - alta:   replica la fila completa salvo en licencias CL.
 *  - edición: replica un subconjunto de columnas salvo en mundotour_sdg,
 *             witwan_rays y witwan_tower.
 *  - baja:   borra en las hijas salvo en licencias CL.
 *
 * Las hijas viven en el mismo servidor MySQL, así que se escriben con
 * `base.tabla` sobre la conexión del tenant, igual que el CI. Un fallo acá no
 * debe tirar abajo el alta local: se loguea y sigue.
 */
class PlanCuentaReplicador
{
    private const COLUMNAS_EDICION = [
        'plancuenta_id', 'fk_moneda_id', 'plancuenta_nombre', 'fk_plancuenta_id', 'plancuenta_codigo',
        'plancuenta_titulo', 'plancuenta_saldo', 'arqueo', 'conceptos_adicionales', 'plancuenta_cli', 'cuentagasto',
    ];

    public function alta(int $id): void
    {
        if (Licencia::pais() === 'CL') {
            return;
        }

        foreach ($this->hijas() as $base) {
            $this->intentar(fn () => DB::statement(
                "REPLACE INTO `{$base}`.`plancuenta` SELECT * FROM `plancuenta` WHERE plancuenta_id = ?", [$id]
            ), $base, 'alta', $id);
        }
    }

    public function edicion(int $id): void
    {
        if (Licencia::es('mundotour_sdg', 'witwan_rays', 'witwan_tower')) {
            return;
        }

        $cols = implode(', ', array_map(fn ($c) => "`{$c}`", self::COLUMNAS_EDICION));

        foreach ($this->hijas() as $base) {
            $this->intentar(fn () => DB::statement(
                "REPLACE INTO `{$base}`.`plancuenta` ({$cols}) SELECT {$cols} FROM `plancuenta` WHERE plancuenta_id = ?", [$id]
            ), $base, 'edicion', $id);
        }
    }

    public function baja(int $id): void
    {
        if (Licencia::pais() === 'CL') {
            return;
        }

        foreach ($this->hijas() as $base) {
            $this->intentar(fn () => DB::statement(
                "DELETE FROM `{$base}`.`plancuenta` WHERE plancuenta_id = ?", [$id]
            ), $base, 'baja', $id);
        }
    }

    /** @return list<string> bases hijas (sin witwan_essential, como el CI). */
    public function hijas(): array
    {
        try {
            return DB::connection('license')
                ->table('copy_licencia')
                ->where('base_colectora', Licencia::base())
                ->where('licencia_base', '<>', 'witwan_essential')
                ->pluck('licencia_base')
                ->filter(fn ($b) => preg_match('/^[a-z0-9_]+$/i', (string) $b) === 1)
                ->values()
                ->all();
        } catch (Throwable $e) {
            Log::warning('plancuenta: no se pudo leer copy_licencia', ['error' => $e->getMessage()]);

            return [];
        }
    }

    private function intentar(callable $fn, string $base, string $accion, int $id): void
    {
        try {
            $fn();
        } catch (Throwable $e) {
            Log::warning('plancuenta: fallo la replica a base hija', [
                'base' => $base, 'accion' => $accion, 'plancuenta_id' => $id, 'error' => $e->getMessage(),
            ]);
        }
    }
}
