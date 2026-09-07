<?php

namespace App\Services\Reservas\Busqueda;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Circuitos (PAQ) y trenes de lujo (TRL): se venden por fecha de salida, con la
 * grilla de alojamiento (categoría × base) pero una sola "noche" en el
 * Tarifador (tarifar() :1225). La fecha de fin del servicio es salida +
 * `vigencia.noches`, como hace el carrito del CI (reserva.php:2191-2196). PAQ
 * sólo lista circuitos (`producto_extra circuito=1`, reserva.php:3892-3894),
 * configurable en `solo_circuito`.
 */
class BuscadorPaquete extends BuscadorAlojamiento
{
    public function reglas(string $tipo): array
    {
        return ['habitaciones' => 'required|array|min:1|max:6'];
    }

    protected function filtrosCandidatos(string $tipo, array $p, ?string $to): array
    {
        $f = parent::filtrosCandidatos($tipo, $p, $to);
        $f['to'] = null; // alcanza con que la vigencia cubra la salida

        return $f;
    }

    protected function fechaFin(string $tipo, array $p): ?string
    {
        return null; // una noche: el Tarifador ya lo fuerza para PAQ/TRL
    }

    protected function extra(object $cand, array $producto, array $cotizadas, array $p): array
    {
        $e = parent::extra($cand, $producto, $cotizadas, $p);
        $vigenciaId = (int) ($cotizadas[0]['cot']['mejor']['vigencia'] ?? 0);
        $noches = (int) (DB::table('vigencia')->where('vigencia_id', $vigenciaId)->value('noches') ?? 0);
        if ($noches === 0) {
            $noches = (int) (DB::table('vigencia')->where('fk_producto_id', (int) $producto['producto_id'])->where('noches', '>', 0)->value('noches') ?? 0);
        }
        $e['vigencia_fin'] = Carbon::createFromFormat('Y-m-d', (string) $p['from'])->addDays($noches)->format('Y-m-d');
        $e['noches'] = $noches;

        return $e;
    }
}
