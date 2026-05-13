<?php

namespace App\Models\Concerns;

use App\Helpers\PermisoHelper;
use App\Models\Cliente;

/**
 * Aplica el alcance de visibilidad del usuario logueado sobre la columna
 * cliente del modelo (default: fk_cliente_id). Para sobreescribirla, definir
 *   protected string $alcanceClienteColumn = 'facturar_a';
 * en el modelo.
 */
trait AlcanceCliente
{
    public function scopeVisiblesAlUsuario($query)
    {
        $alcance = PermisoHelper::alcanceCliente();

        if ($alcance['tipo'] === PermisoHelper::ALCANCE_TODOS) {
            return $query;
        }

        $column = $this->alcanceClienteColumn ?? 'fk_cliente_id';

        if ($alcance['tipo'] === PermisoHelper::ALCANCE_CLIENTE) {
            return $query->where($column, $alcance['id']);
        }

        // Alcance por cadena: buscar todos los clientes de la cadena
        $clienteIds = Cliente::where('fk_cadenacliente_id', $alcance['id'])
            ->pluck('cliente_id');

        return $query->whereIn($column, $clienteIds);
    }
}
