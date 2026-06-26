<?php

namespace App\Services\Reservas;

use App\Helpers\PermisoHelper;
use App\Models\User;
use App\Support\Licencia;
use Illuminate\Database\Eloquent\Builder;

/**
 * Aplica el scoping por permiso/rol del listado de reservas, fiel a
 * reserva_model.php::listar() (líneas 174-212). Asume que el query ya tiene el
 * JOIN a `cliente` (lo necesita el alcance por cadena).
 *
 * Notas de fidelidad:
 *  - El forzado de `_cliente` para CLI/CLM y para files_cliente_asignado lo hace
 *    el controller (igual que CI), y se aplica como filtro reserva.fk_cliente_id.
 *    Acá se replica además la condición de alcance del modelo (cadena/cliente).
 *  - Las cotas de fecha_alta por licencia aplican a usuarios NO internos sin
 *    importar el rol (CI sólo chequea user->interno != 'Y').
 */
class ReservaScopeService
{
    public function __construct(private EscritorioService $escritorios) {}

    public function aplicar(Builder $query, ?User $usuario): void
    {
        if ($usuario === null) {
            return;
        }

        $esPow = $usuario->fk_tipousuario_id === 'POW';
        $interno = (string) ($usuario->usuario_interno ?? '');

        // files_cliente_asignado (255): limita por cadena del cliente o por cliente.
        if (! $esPow && PermisoHelper::tienePermiso(255, 'files_cliente_asignado')) {
            $cadena = (int) ($usuario->fk_cadenacliente_id ?? 0);
            if ($cadena !== 0) {
                $query->where('cliente.fk_cadenacliente_id', $cadena);
            } else {
                $query->where('reserva.fk_cliente_id', (int) ($usuario->fk_cliente_id ?? 0));
            }
        }

        // files_solo_usuario (286): limita a mis files / mi escritorio.
        if (! $esPow && PermisoHelper::tienePermiso(286, 'files_solo_usuario')) {
            $escritorio = $this->escritorios->paraUsuario($usuario);

            if (! empty($escritorio)) {
                $query->where(function (Builder $q) use ($escritorio) {
                    $q->whereIn('reserva.fk_usuario_id', $escritorio)
                        ->orWhereIn('reserva.agente', $escritorio);
                });
            } else {
                $uid = (int) $usuario->usuario_id;
                $query->where(function (Builder $q) use ($uid) {
                    $q->where('reserva.fk_usuario_id', $uid)
                        ->orWhere('reserva.agente', $uid);
                });
            }
        }

        // Cotas de fecha_alta por licencia para usuarios no internos.
        if ($interno !== 'Y') {
            $piso = Licencia::flag('fecha_alta_min', null);
            if (is_string($piso) && $piso !== '') {
                $query->where('reserva.fecha_alta', '>=', $piso);
            }
        }
    }
}
