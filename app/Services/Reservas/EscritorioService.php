<?php

namespace App\Services\Reservas;

use App\Models\User;
use App\Support\Licencia;
use Illuminate\Support\Facades\DB;

/**
 * Replica el array `escritorio[]` del CI legacy (user_model.php:42-55): el
 * conjunto de usuarios "supervisados" que ve un usuario cuando tiene el permiso
 * files_solo_usuario (286).
 *
 * OJO de fidelidad: CI SOLO puebla escritorio para las licencias mundotour_sdg
 * y witwan_rays. Para el resto el array queda vacío y el scoping 286 cae al
 * caso "solo mis files" (fk_usuario_id/agente == mi id).
 */
class EscritorioService
{
    /** @return list<int> ids de usuario (incluye al propio); [] si la licencia no usa escritorio. */
    public function paraUsuario(User $usuario): array
    {
        if (! Licencia::flag('escritorio_on')) {
            return [];
        }

        $uid = (int) $usuario->usuario_id;

        // Clave => valor para deduplicar respetando el orden de inserción de CI.
        $set = [$uid => $uid];

        // Yo como supervisor: mis secundarios.
        foreach (DB::table('rel_usuariousuario')->where('fk_usuario_id', $uid)->get() as $rr) {
            $set[(int) $rr->fk_secundario_id] = (int) $rr->fk_secundario_id;
        }

        // Yo como secundario: el supervisor y sus secundarios (bidireccional).
        foreach (DB::table('rel_usuariousuario')->where('fk_secundario_id', $uid)->get() as $rr) {
            $set[(int) $rr->fk_secundario_id] = (int) $rr->fk_secundario_id;
            $set[(int) $rr->fk_usuario_id] = (int) $rr->fk_usuario_id;
        }

        return array_values($set);
    }
}
