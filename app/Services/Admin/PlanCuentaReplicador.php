<?php

namespace App\Services\Admin;

use App\Support\Licencia;

/**
 * Réplica del plan de cuentas hacia las bases hijas de la colectora, tal como
 * lo hacen los hooks _after_create/_after_edit/after_delete de
 * administracion/plancuenta.php.
 *
 * Se conservan las condiciones del legacy, que no son simétricas:
 *  - alta:    replica la fila completa salvo en licencias CL.
 *  - edición: replica un subconjunto de columnas salvo en mundotour_sdg,
 *             witwan_rays y witwan_tower.
 *  - baja:    borra en las hijas salvo en licencias CL.
 */
class PlanCuentaReplicador
{
    private const COLUMNAS_EDICION = [
        'plancuenta_id', 'fk_moneda_id', 'plancuenta_nombre', 'fk_plancuenta_id', 'plancuenta_codigo',
        'plancuenta_titulo', 'plancuenta_saldo', 'arqueo', 'conceptos_adicionales', 'plancuenta_cli', 'cuentagasto',
    ];

    public function __construct(private ReplicadorColectora $colectora) {}

    public function alta(int $id): void
    {
        if (Licencia::pais() === 'CL') {
            return;
        }

        $this->colectora->replicarFila('plancuenta', 'plancuenta_id', $id, $this->colectora->hijas());
    }

    public function edicion(int $id): void
    {
        if (Licencia::es('mundotour_sdg', 'witwan_rays', 'witwan_tower')) {
            return;
        }

        $this->colectora->replicarColumnas('plancuenta', 'plancuenta_id', $id, self::COLUMNAS_EDICION, $this->colectora->hijas());
    }

    public function baja(int $id): void
    {
        if (Licencia::pais() === 'CL') {
            return;
        }

        $this->colectora->borrarFila('plancuenta', 'plancuenta_id', $id, $this->colectora->hijas());
    }
}
