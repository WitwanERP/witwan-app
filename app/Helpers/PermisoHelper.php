<?php

namespace App\Helpers;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class PermisoHelper
{
    public const ALCANCE_TODOS = 'all';

    public const ALCANCE_CLIENTE = 'cliente';

    public const ALCANCE_CADENA = 'cadena';

    /**
     * Réplica fiel de user_model::can() de CI (user_model.php:301): los permisos
     * se cargan primero por GRUPO (permisogrupo, por rol) y luego por USUARIO
     * (permiso, individual); can() devuelve el PRIMER match, así que el permiso
     * de grupo tiene precedencia sobre el individual cuando ambos existen.
     */
    public static function tienePermiso($seccionId, $valor)
    {
        $usuario = Auth::user();
        if (! $usuario) {
            return false;
        }

        if ($usuario->fk_tipousuario_id == 'POW') {
            return true; // Superadmin tiene todos los permisos
        }

        // 1) Permiso por grupo (rol). Si la fila existe, su valor manda (aunque sea 0).
        $grupo = DB::table('permisogrupo')
            ->where('fk_tipousuario_id', $usuario->fk_tipousuario_id)
            ->where('fk_seccion_id', $seccionId)
            ->where('permisogrupo_nombre', $valor)
            ->value('permisogrupo_valor');

        if ($grupo !== null) {
            return (int) $grupo === 1;
        }

        // 2) Override por usuario individual (tabla permiso).
        $individual = DB::table('permiso')
            ->where('fk_usuario_id', $usuario->usuario_id)
            ->where('fk_seccion_id', $seccionId)
            ->where('permiso_nombre', $valor)
            ->value('permiso_valor');

        if ($individual !== null) {
            return (int) $individual === 1;
        }

        return false;
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
        if (! $usuario) {
            return ['tipo' => self::ALCANCE_TODOS, 'id' => null];
        }

        if ($usuario->fk_tipousuario_id === 'POW') {
            return ['tipo' => self::ALCANCE_TODOS, 'id' => null];
        }

        if (! empty($usuario->fk_cadenacliente_id)) {
            return ['tipo' => self::ALCANCE_CADENA, 'id' => (int) $usuario->fk_cadenacliente_id];
        }

        if (! empty($usuario->fk_cliente_id)) {
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
