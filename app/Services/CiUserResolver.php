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
        foreach ((array) config('ci.id_keys', ['usuario_id']) as $key) {
            if (isset($ciData[$key]) && is_numeric($ciData[$key])) {
                $user = User::query()->find((int) $ciData[$key]);
                if ($user !== null) {
                    return $user;
                }
            }
        }

        foreach ((array) config('ci.mail_keys', ['usuario_mail']) as $key) {
            if (! empty($ciData[$key])) {
                $user = User::query()->where('usuario_mail', $ciData[$key])->first();
                if ($user !== null) {
                    return $user;
                }
            }
        }

        return null;
    }
}
