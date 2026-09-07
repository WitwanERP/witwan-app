<?php

namespace App\Services\Reservas\Busqueda;

/**
 * Excursiones, guías, trenes, traslados, autos y cruceros: tarifa por tramos de
 * pax (min_pax–max_pax × tipo de pax), una cotización por producto con toda la
 * composición (filtro_excursiones/traslados/trenes.php). Traslados filtran por
 * ciudad de origen (obligatoria) y destino; autos y cruceros llevan from→to; el
 * resto es de un día. Excursiones, guías y traslados piden pick-up / drop-off
 * al agregar (reserva.php:2717-2724).
 */
class BuscadorTramos extends BuscadorAlojamiento
{
    public function reglas(string $tipo): array
    {
        return ['ad' => 'required|integer|min:1'];
    }

    public function buscar(string $tipo, array $p, array $ctx, Presupuesto $presupuesto): array
    {
        // Una sola "habitación" con toda la composición; el Tarifador resuelve el tramo por cantidad de adultos.
        $p['habitaciones'] = [['ad' => (int) ($p['ad'] ?? 1), 'mn' => array_values(array_map('intval', (array) ($p['mn'] ?? [])))]];
        if ($tipo === 'TRN') {
            $p['ciudad'] = 0;
        }

        return parent::buscar($tipo, $p, $ctx, $presupuesto);
    }

    protected function filtrosCandidatos(string $tipo, array $p, ?string $to): array
    {
        $f = parent::filtrosCandidatos($tipo, $p, $to);
        if ($tipo === 'TRN') {
            $f['origen'] = (int) ($p['origen'] ?? 0);
            $f['destino'] = (int) ($p['destino'] ?? 0);
            $f['ciudades'] = [];
        }
        // Un día: alcanza con que la vigencia cubra el inicio.
        if (! in_array($tipo, ['AUT', 'CRU'], true)) {
            $f['to'] = null;
        }

        return $f;
    }

    protected function fechaFin(string $tipo, array $p): ?string
    {
        return in_array($tipo, ['AUT', 'CRU'], true) && ! empty($p['to']) ? (string) $p['to'] : null;
    }

    protected function extra(object $cand, array $producto, array $cotizadas, array $p): array
    {
        $e = parent::extra($cand, $producto, $cotizadas, $p);
        $e['pickup'] = in_array((string) $producto['fk_tipoproducto_id'], (array) config('reservas_busqueda.con_pickup', []), true);
        if ((string) $producto['fk_tipoproducto_id'] === 'TRN' && ! empty($cand->ciudad_origen)) {
            // La ciudad del servicio de traslado es la de origen (filtro_traslados.php).
            $e['ciudad'] = $cand->ciudad_origen;
        }
        // Un día: el servicio termina el mismo día (el Tarifador devuelve fin = ini + 1).
        if (! in_array((string) $producto['fk_tipoproducto_id'], ['AUT', 'CRU'], true)) {
            $e['vigencia_fin'] = (string) $p['from'];
        }

        return $e;
    }
}
