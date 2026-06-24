<?php

namespace App\Services;

use App\Models\User;

/**
 * Encuentra el usuario de Witwan a partir de los datos de sesión de CI.
 *
 * CI y Laravel comparten la misma base de datos del tenant, así que el usuario
 * logueado en CI ya existe en la tabla `usuario`: lo buscamos por usuario_id
 * (PK, lo más confiable) y, si no, por mail. No creamos usuarios desde acá.
 *
 * Las claves a inspeccionar dentro de los datos de CI son configurables
 * (config/ci.php: id_keys / mail_keys) porque dependen de cómo el CI haya
 * armado su set_userdata.
 */
class CiUserResolver
{
    public function fromCiData(array $ciData): ?User
    {
        // El user_data de CI puede tener la identidad en la raíz o anidada en un
        // sub-array (ej. la clave 'user_data'). Buscamos en ambos niveles.
        $scopes = [$ciData];
        foreach ($ciData as $value) {
            if (is_array($value)) {
                $scopes[] = $value;
            }
        }

        $idKeys = (array) config('ci.id_keys', ['usuario_id']);
        $mailKeys = (array) config('ci.mail_keys', ['usuario_mail']);

        // Primero por id (PK usuario_id), que es lo más confiable.
        foreach ($scopes as $scope) {
            foreach ($idKeys as $key) {
                if (isset($scope[$key]) && is_numeric($scope[$key])) {
                    $user = User::query()->find((int) $scope[$key]);
                    if ($user !== null) {
                        return $user;
                    }
                }
            }
        }

        // Si no, por mail.
        foreach ($scopes as $scope) {
            foreach ($mailKeys as $key) {
                if (! empty($scope[$key]) && is_string($scope[$key])) {
                    $user = User::query()->where('usuario_mail', $scope[$key])->first();
                    if ($user !== null) {
                        return $user;
                    }
                }
            }
        }

        return null;
    }
}
