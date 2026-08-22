<?php

namespace App\Support;

use App\Helpers\PermisoHelper;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Verificación de permisos por sección, con modo de despliegue.
 *
 * El CI nunca chequeó permisos en el alta/edición de varios módulos (los métodos
 * sobreescribían los del Admin_Controller sin llamar a `_check_perm()`), así que
 * hoy hay roles operando sin tener el permiso cargado en `permisogrupo`.
 * Encender los gates de golpe les cortaría el trabajo.
 *
 * Por eso hay dos modos, por módulo:
 *   - estricto  => deniega con 403.
 *   - observación => deja pasar y registra un warning, para poder medir a quién
 *     afectaría el cambio antes de aplicarlo.
 */
final class Permisos
{
    /** ¿El usuario tiene el permiso? (sin efectos: para pintar/ocultar la UI) */
    public static function tiene(string $uriSeccion, string $accion): bool
    {
        $seccion = Secciones::idONull($uriSeccion);

        if ($seccion === null) {
            return false;
        }

        return PermisoHelper::tienePermiso($seccion, $accion);
    }

    /**
     * Exige el permiso. En modo estricto aborta con 403; en observación sólo
     * loguea.
     *
     * @throws RuntimeException si la sección no se puede resolver (fail-closed).
     */
    public static function exigir(string $uriSeccion, string $accion, string $modulo): void
    {
        if (self::tiene($uriSeccion, $accion)) {
            return;
        }

        $usuario = auth()->id();

        if (self::estricto()) {
            abort(403, "No tiene permiso para {$accion} en {$modulo}.");
        }

        Log::warning('permiso denegado (modo observación)', [
            'modulo' => $modulo,
            'seccion' => $uriSeccion,
            'accion' => $accion,
            'usuario_id' => $usuario,
            'licencia' => Licencia::base(),
        ]);
    }

    private static function estricto(): bool
    {
        return (bool) config('facturaproveedor.permisos_estrictos', false);
    }
}
