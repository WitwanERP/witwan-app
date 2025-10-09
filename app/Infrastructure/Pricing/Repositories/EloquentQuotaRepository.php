<?php

namespace App\Infrastructure\Pricing\Repositories;

use App\Domain\Pricing\Repositories\QuotaRepositoryInterface;
use Illuminate\Support\Facades\DB;

class EloquentQuotaRepository implements QuotaRepositoryInterface
{
    public function findAvailableQuota(int $productId, \DateTime $date): ?int
    {
        // Buscar cupo general del producto
        $quota = DB::table('cupo')
            ->where('fk_producto_id', $productId)
            ->where('vigencia_ini', '<=', $date->format('Y-m-d'))
            ->where('vigencia_fin', '>=', $date->format('Y-m-d'))
            ->where(function($query) use ($date) {
                $releaseDays = DB::raw("DATEDIFF('{$date->format('Y-m-d')}', CURDATE())");
                $query->where('release', '<=', $releaseDays)
                      ->orWhere('release', 0);
            })
            ->sum('cantidad');

        if ($quota === null) {
            return null;
        }

        // Restar reservas existentes
        $usedQuota = DB::table('servicio')
            ->join('reserva', 'servicio.fk_reserva_id', '=', 'reserva.reserva_id')
            ->where('servicio.fk_producto_id', $productId)
            ->where('servicio.vigencia_ini', '<=', $date->format('Y-m-d'))
            ->where('servicio.vigencia_fin', '>=', $date->format('Y-m-d'))
            ->whereIn('reserva.fk_filestatus_id', ['RQ', 'CO', 'CL'])
            ->count();

        return max(0, $quota - $usedQuota);
    }

    public function findAvailableTickets(int $productId): array
    {
        $tickets = DB::table('cupotkt')
            ->where('fk_producto_id', $productId)
            ->get();

        $availableTickets = [];

        for ($i = 1; $i <= 4; $i++) {
            $ticketField = "cant_tkt{$i}";
            $nameField = "nombre_tkt{$i}";
            $costField = "costo_tkt{$i}";
            $coverageField = "cobertura_tkt{$i}";
            $utilityField = "utilidad_tkt{$i}";

            foreach ($tickets as $ticket) {
                if (isset($ticket->$ticketField) && $ticket->$ticketField > 0) {
                    $usedTickets = DB::table('servicio')
                        ->join('reserva', 'servicio.fk_reserva_id', '=', 'reserva.reserva_id')
                        ->where('servicio.fk_producto_id', $productId)
                        ->where('servicio.fk_tarifacategoria_id', $i)
                        ->whereNotIn('reserva.fk_filestatus_id', ['CU'])
                        ->count();

                    $available = $ticket->$ticketField - $usedTickets;

                    if ($available > 0) {
                        $availableTickets[] = [
                            'categoria' => $i,
                            'nombre' => $ticket->$nameField ?? "Ticket Tipo {$i}",
                            'capacidad' => $ticket->$ticketField,
                            'disponibles' => $available,
                            'costo' => $ticket->$costField ?? 0,
                            'cobertura' => $ticket->$coverageField ?? 0,
                            'utilidad' => $ticket->$utilityField ?? 0,
                            'currency' => 'USD', // Asumimos USD por defecto
                        ];
                    }
                }
            }
        }

        return $availableTickets;
    }

    public function findFlightQuota(int $productId): ?array
    {
        $flightData = DB::table('cupoaereo')
            ->where('fk_producto_id', $productId)
            ->first();

        if (!$flightData) {
            return null;
        }

        // Calcular cupo disponible
        $usedQuota = DB::table('servicio')
            ->join('reserva', 'servicio.fk_reserva_id', '=', 'reserva.reserva_id')
            ->where('servicio.fk_producto_id', $productId)
            ->whereNotIn('reserva.fk_filestatus_id', ['CU'])
            ->count();

        $availableQuota = $flightData->cantidad - $usedQuota;

        return [
            'destino' => $flightData->destino,
            'cantidad_total' => $flightData->cantidad,
            'disponibles' => max(0, $availableQuota),
            'tarifa_neta' => $flightData->tarifa_neta ?? 0,
            'child_tarifa_neta' => $flightData->child_tarifa_neta ?? $flightData->tarifa_neta ?? 0,
            'impuestos_totales' => $flightData->impuestos_totales ?? 0,
            'child_impuestos_totales' => $flightData->child_impuestos_totales ?? $flightData->impuestos_totales ?? 0,
            'cobertura' => $flightData->cobertura ?? 0,
            'child_cobertura' => $flightData->child_cobertura ?? $flightData->cobertura ?? 0,
            'fk_moneda_id' => $flightData->fk_moneda_id ?? 'USD',
            'markup' => $flightData->markup ?? 1,
        ];
    }

    public function checkRoomAvailability(int $productId, int $roomTypeId, \DateTime $date, int $adults, int $children = 0): bool
    {
        // Verificar capacidad de la habitación
        $room = DB::table('alojamientohabitacion')
            ->where('fk_producto_id', $productId)
            ->where('fk_tarifacategoria_id', $roomTypeId)
            ->where('habilitar', 1)
            ->first();

        if (!$room) {
            return false;
        }

        // Verificar capacidad máxima
        if ($adults > $room->max_adultos) {
            return false;
        }

        if ($children > 0) {
            if ($children > $room->max_child) {
                return false;
            }
            if ($adults > $room->max_adultos_child) {
                return false;
            }
            if ($adults < $room->min_adultos_child) {
                return false;
            }
        }

        // Verificar cupo disponible
        $availableQuota = $this->findAvailableQuota($productId, $date);

        return $availableQuota !== null && $availableQuota > 0;
    }

    public function isSoldOut(int $productId, \DateTime $date): bool
    {
        return DB::table('soldout')
            ->where('fk_producto_id', $productId)
            ->where('vigencia_ini', '<=', $date->format('Y-m-d'))
            ->where('vigencia_fin', '>=', $date->format('Y-m-d'))
            ->exists();
    }
}