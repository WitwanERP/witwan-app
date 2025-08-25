<?php

namespace App\Helpers;

use Illuminate\Support\Facades\Auth;

class PermisoHelper
{
    public static function tienePermiso($seccionId, $valor)
    {

        $usuario = Auth::user();
        if (!$usuario) return false;

        if ($usuario->fk_tipousuario_id == 'POW') {
            return true; // Superadmin tiene todos los permisos
        }

        return $usuario->permisogrupos()
            ->where('fk_seccion_id', $seccionId)
            ->where('permisogrupo_nombre', $valor)
            ->where('permisogrupo_valor', '=', 1)
            ->exists();
    }
}
