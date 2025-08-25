<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Support\Facades\Auth;

class CheckPermiso
{
    public function handle($request, Closure $next, $seccionId, $valor)
    {
        $usuario = Auth::user();
        if (!$usuario) {
            return response()->json(['error' => 'No autenticado'], 401);
        }

        $permiso = $usuario->permisogrupos()
            ->where('fk_seccion_id', $seccionId)
            ->where('permisogrupo_valor', '>=', $valor)
            ->first();

        if (!$permiso) {
            return response()->json(['error' => 'Permiso denegado'], 403);
        }

        return $next($request);
    }
}
