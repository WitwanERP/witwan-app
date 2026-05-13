<?php

namespace App\Helpers;

use Illuminate\Support\Facades\Auth;

class PermisoHelper
{
    public const ALCANCE_TODOS = 'all';
    public const ALCANCE_CLIENTE = 'cliente';
    public const ALCANCE_CADENA = 'cadena';

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

    /**
     * Devuelve el alcance de visibilidad de clientes para el usuario logueado.
     *
     * Retorna un array con:
     *   - tipo: 'all' (sin restricción), 'cadena' o 'cliente'
     *   - id:   id de cadena o cliente cuando aplica
     *
     * POW y usuarios internos sin cliente/cadena asignados ven todo.
     */
    public static function alcanceCliente(): array
    {
        $usuario = Auth::user();
        if (!$usuario) {
            return ['tipo' => self::ALCANCE_TODOS, 'id' => null];
        }

        if ($usuario->fk_tipousuario_id === 'POW') {
            return ['tipo' => self::ALCANCE_TODOS, 'id' => null];
        }

        if (!empty($usuario->fk_cadenacliente_id)) {
            return ['tipo' => self::ALCANCE_CADENA, 'id' => (int) $usuario->fk_cadenacliente_id];
        }

        if (!empty($usuario->fk_cliente_id)) {
            return ['tipo' => self::ALCANCE_CLIENTE, 'id' => (int) $usuario->fk_cliente_id];
        }

        return ['tipo' => self::ALCANCE_TODOS, 'id' => null];
    }

    /**
     * Indica si el usuario logueado tiene alcance restringido (cliente o cadena).
     */
    public static function tieneAlcanceRestringido(): bool
    {
        return self::alcanceCliente()['tipo'] !== self::ALCANCE_TODOS;
    }
}
