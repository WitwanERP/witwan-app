<?php

namespace App\Services\Documentos;

use App\Support\Licencia;

/**
 * Identifica al proveedor BSP de la licencia.
 *
 * El legacy lo resuelve con una condición encadenada en ajax.php:313:
 *   familia secontur y proveedor 370, o witwan_hayland y proveedor 107, o el
 *   proveedor configurado en `proveedorbsp`.
 *
 * OJO con la familia: es `_t_l == 'secontur'` (que incluye maldivas, morisan y
 * alternativasur), no la licencia `witwan_secontur` a secas.
 */
final class ProveedorBsp
{
    /** Id del proveedor BSP de esta licencia, o null si no aplica. */
    public static function id(): ?int
    {
        $cfg = (array) config('facturaproveedor.proveedor_bsp', []);

        if (Licencia::esFamiliaSecontur() && isset($cfg['familia_secontur'])) {
            return (int) $cfg['familia_secontur'];
        }

        $porLicencia = (array) ($cfg['por_licencia'] ?? []);
        if (isset($porLicencia[Licencia::base()])) {
            return (int) $porLicencia[Licencia::base()];
        }

        return isset($cfg['config_item']) && $cfg['config_item'] !== null && $cfg['config_item'] !== ''
            ? (int) $cfg['config_item']
            : null;
    }

    public static function es(int $proveedorId): bool
    {
        $bsp = self::id();

        return $bsp !== null && $proveedorId === $bsp;
    }
}
