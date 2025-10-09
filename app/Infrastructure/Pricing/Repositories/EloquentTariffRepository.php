<?php

namespace App\Infrastructure\Pricing\Repositories;

use App\Domain\Pricing\Repositories\TariffRepositoryInterface;
use App\Domain\Pricing\ValueObjects\DateRange;
use Illuminate\Support\Facades\DB;

class EloquentTariffRepository implements TariffRepositoryInterface
{
    public function findVigenciasByProduct(int $productId): array
    {
        return DB::table('vigencia')
            ->where('fk_producto_id', $productId)
            ->get()
            ->toArray();
    }

    public function findVigenciasByFilters(array $filters): array
    {
        $query = DB::table('vigencia');

        if (isset($filters['product_id'])) {
            $query->where('fk_producto_id', $filters['product_id']);
        }

        if (isset($filters['active_only']) && $filters['active_only']) {
            $query->where('vigencia_ini', '<=', now())
                  ->where('vigencia_fin', '>=', now());
        }

        return $query->get()->toArray();
    }

    public function findValidVigenciasForDateRange(int $productId, DateRange $dateRange, string $resident = 'N'): array
    {
        return DB::table('vigencia')
            ->where('fk_producto_id', $productId)
            ->where('vigencia_ini', '<=', $dateRange->getEndDate()->format('Y-m-d'))
            ->where('vigencia_fin', '>=', $dateRange->getStartDate()->format('Y-m-d'))
            ->where(function($query) use ($resident) {
                $query->where('residente', $resident)
                      ->orWhere('residente', 'O')
                      ->orWhere('residente', '');
            })
            ->orderBy('vigencia_prioridad', 'desc')
            ->get()
            ->toArray();
    }

    public function findTariffsByVigencia(int $vigenciaId): array
    {
        return DB::table('tarifa')
            ->where('fk_vigencia_id', $vigenciaId)
            ->get()
            ->toArray();
    }

    public function findTariffsByFilters(array $filters): array
    {
        $query = DB::table('tarifa')
            ->join('vigencia', 'tarifa.fk_vigencia_id', '=', 'vigencia.vigencia_id');

        if (isset($filters['product_id'])) {
            $query->where('vigencia.fk_producto_id', $filters['product_id']);
        }

        if (isset($filters['tarifario_id'])) {
            $query->where('tarifa.fk_tarifario_id', $filters['tarifario_id']);
        }

        return $query->get()->toArray();
    }

    public function findTariffsForPaxRange(int $vigenciaId, int $minPax, int $maxPax): array
    {
        return DB::table('tarifa')
            ->where('fk_vigencia_id', $vigenciaId)
            ->where('min_pax', '<=', $maxPax)
            ->where('max_pax', '>=', $minPax)
            ->get()
            ->toArray();
    }

    public function saveTariff(array $tariffData): int
    {
        return DB::table('tarifa')->insertGetId($tariffData);
    }

    public function updateTariff(int $tariffId, array $tariffData): bool
    {
        return DB::table('tarifa')
            ->where('tarifa_id', $tariffId)
            ->update($tariffData) > 0;
    }

    public function deleteTariff(int $tariffId): bool
    {
        return DB::table('tarifa')
            ->where('tarifa_id', $tariffId)
            ->delete() > 0;
    }

    public function saveVigencia(array $vigenciaData): int
    {
        return DB::table('vigencia')->insertGetId($vigenciaData);
    }

    public function updateVigencia(int $vigenciaId, array $vigenciaData): bool
    {
        return DB::table('vigencia')
            ->where('vigencia_id', $vigenciaId)
            ->update($vigenciaData) > 0;
    }

    public function deleteVigencia(int $vigenciaId): bool
    {
        return DB::table('vigencia')
            ->where('vigencia_id', $vigenciaId)
            ->delete() > 0;
    }

    public function findTariffHistory(int $productId, DateRange $dateRange): array
    {
        return DB::table('tarifa')
            ->join('vigencia', 'tarifa.fk_vigencia_id', '=', 'vigencia.vigencia_id')
            ->where('vigencia.fk_producto_id', $productId)
            ->whereBetween('vigencia.vigencia_ini', [
                $dateRange->getStartDate()->format('Y-m-d'),
                $dateRange->getEndDate()->format('Y-m-d')
            ])
            ->orderBy('vigencia.vigencia_ini', 'desc')
            ->get()
            ->toArray();
    }

    public function findActiveTariffs(int $productId): array
    {
        return DB::table('tarifa')
            ->join('vigencia', 'tarifa.fk_vigencia_id', '=', 'vigencia.vigencia_id')
            ->where('vigencia.fk_producto_id', $productId)
            ->where('vigencia.vigencia_ini', '<=', now())
            ->where('vigencia.vigencia_fin', '>=', now())
            ->get()
            ->toArray();
    }

    public function findExpiredVigencias(int $productId): array
    {
        return DB::table('vigencia')
            ->where('fk_producto_id', $productId)
            ->where('vigencia_fin', '<', now())
            ->get()
            ->toArray();
    }

    public function bulkUpdateTariffs(array $tariffUpdates): bool
    {
        DB::beginTransaction();
        try {
            foreach ($tariffUpdates as $update) {
                DB::table('tarifa')
                    ->where('tarifa_id', $update['id'])
                    ->update($update['data']);
            }
            DB::commit();
            return true;
        } catch (\Exception $e) {
            DB::rollBack();
            return false;
        }
    }

    public function getTariffStatistics(int $productId): array
    {
        $stats = DB::table('tarifa')
            ->join('vigencia', 'tarifa.fk_vigencia_id', '=', 'vigencia.vigencia_id')
            ->where('vigencia.fk_producto_id', $productId)
            ->selectRaw('
                COUNT(*) as total_tariffs,
                MIN(tarifa.costo) as min_cost,
                MAX(tarifa.costo) as max_cost,
                AVG(tarifa.costo) as avg_cost
            ')
            ->first();

        return (array) $stats;
    }

    public function findTariffsForProduct(int $productId, \DateTime $startDate, \DateTime $endDate): array
    {
        return DB::table('tarifa')
            ->join('vigencia', 'tarifa.fk_vigencia_id', '=', 'vigencia.vigencia_id')
            ->where('vigencia.fk_producto_id', $productId)
            ->where('vigencia.vigencia_ini', '<=', $endDate->format('Y-m-d'))
            ->where('vigencia.vigencia_fin', '>=', $startDate->format('Y-m-d'))
            ->where(function($query) {
                $query->where('vigencia.vigencia_ventaini', '<=', now())
                      ->orWhere('vigencia.vigencia_ventaini', '0000-00-00');
            })
            ->where(function($query) {
                $query->where('vigencia.vigencia_ventafin', '>=', now())
                      ->orWhere('vigencia.vigencia_ventafin', '0000-00-00');
            })
            ->orderBy('vigencia.vigencia_prioridad', 'desc')
            ->orderBy('tarifa.costo', 'asc')
            ->get()
            ->toArray();
    }

    public function findASVTariffsByDuration(int $productId, int $days): array
    {
        // Buscar tarifas de Asistencia al Viajero por duración
        $tariffs = DB::table('vigencia')
            ->join('tarifa', 'vigencia.vigencia_id', '=', 'tarifa.fk_vigencia_id')
            ->where('vigencia.fk_producto_id', $productId)
            ->where('tarifa.fk_tarifario_id', 0)
            ->where('vigencia.vigencia_ini', '<=', now())
            ->where('vigencia.vigencia_fin', '>=', now())
            ->orderBy('tarifa.max_pax', 'desc')
            ->get();

        if ($tariffs->isEmpty()) {
            return [];
        }

        // Procesar tarifas para la duración específica
        $processedTariffs = [];

        foreach ($tariffs as $tariff) {
            $maxDays = $tariff->max_pax; // En ASV, max_pax representa días máximos

            if ($days <= $maxDays) {
                $processedTariffs[] = [
                    'adult_rate' => $tariff->costo,
                    'child_rate' => $tariff->costo * 0.7, // 30% descuento para niños
                    'currency' => $tariff->moneda_costo ?: 'USD',
                    'coverage_type' => 'standard',
                    'coverage_premium' => 0,
                    'max_days' => $maxDays,
                    'vigencia_id' => $tariff->vigencia_id,
                ];
                break;
            }
        }

        // Si no encontramos tarifa exacta, calcular por múltiplos
        if (empty($processedTariffs) && !$tariffs->isEmpty()) {
            $baseTariff = $tariffs->first();
            $maxDays = $baseTariff->max_pax;
            $multiplier = ceil($days / $maxDays);

            $processedTariffs[] = [
                'adult_rate' => $baseTariff->costo * $multiplier,
                'child_rate' => $baseTariff->costo * $multiplier * 0.7,
                'currency' => $baseTariff->moneda_costo ?: 'USD',
                'coverage_type' => 'extended',
                'coverage_premium' => 0,
                'max_days' => $days,
                'vigencia_id' => $baseTariff->vigencia_id,
            ];
        }

        return $processedTariffs;
    }

    public function findTariffsByClientAndProduct(int $clientId, int $productId): array
    {
        return DB::table('tarifario')
            ->join('rel_clientesistema', 'tarifario.tarifario_id', '=', 'rel_clientesistema.fk_tarifario_id')
            ->join('tarifariocomision', 'tarifario.tarifario_id', '=', 'tarifariocomision.fk_tarifario_id')
            ->where('rel_clientesistema.fk_cliente_id', $clientId)
            ->where(function($query) use ($productId) {
                $query->where('tarifariocomision.fk_producto_id', $productId)
                      ->orWhere('tarifariocomision.fk_producto_id', 0);
            })
            ->orderBy('tarifariocomision.fk_producto_id', 'desc')
            ->get()
            ->toArray();
    }

    public function findTariffForDate(int $productId, \DateTime $date, array $filters = []): ?array
    {
        $query = DB::table('tarifa')
            ->join('vigencia', 'tarifa.fk_vigencia_id', '=', 'vigencia.vigencia_id')
            ->where('vigencia.fk_producto_id', $productId)
            ->where('vigencia.vigencia_ini', '<=', $date->format('Y-m-d'))
            ->where('vigencia.vigencia_fin', '>=', $date->format('Y-m-d'));

        // Aplicar filtros adicionales
        if (isset($filters['adults'])) {
            $query->where('tarifa.min_pax', '<=', $filters['adults'])
                  ->where('tarifa.max_pax', '>=', $filters['adults']);
        }

        if (isset($filters['room_type'])) {
            $query->where('tarifa.fk_tarifacategoria_id', $filters['room_type']);
        }

        if (isset($filters['tariff_id'])) {
            $query->where('tarifa.fk_tarifario_id', $filters['tariff_id']);
        }

        // Validar día de la semana
        $dayOfWeek = $date->format('N');
        $query->whereRaw("SUBSTRING(LPAD(CAST(BIN(weekdays) AS CHAR(7)), 7, '0'), {$dayOfWeek}, 1) = '1'");

        $tariff = $query->orderBy('vigencia.vigencia_prioridad', 'desc')
                       ->orderBy('tarifa.costo', 'asc')
                       ->first();

        return $tariff ? (array) $tariff : null;
    }

    public function findPromotionalTariffs(int $productId, \DateTime $startDate, \DateTime $endDate): array
    {
        return DB::table('vigencia')
            ->join('tarifa', 'vigencia.vigencia_id', '=', 'tarifa.fk_vigencia_id')
            ->where('vigencia.fk_producto_id', $productId)
            ->where('vigencia.promocional', 1)
            ->where('vigencia.vigencia_ini', '<=', $endDate->format('Y-m-d'))
            ->where('vigencia.vigencia_fin', '>=', $startDate->format('Y-m-d'))
            ->where(function($query) {
                $query->where('vigencia.vencimiento_promocion', '>=', now())
                      ->orWhere('vigencia.vencimiento_promocion', '0000-00-00')
                      ->orWhereNull('vigencia.vencimiento_promocion');
            })
            ->orderBy('vigencia.vigencia_prioridad', 'desc')
            ->get()
            ->toArray();
    }
}