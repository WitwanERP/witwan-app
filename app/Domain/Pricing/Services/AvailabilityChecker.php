<?php

namespace App\Domain\Pricing\Services;

use App\Domain\Pricing\ValueObjects\ProductQuery;
use App\Domain\Pricing\ValueObjects\DateRange;
use DateTime;

class AvailabilityChecker
{
    private $quotaRepository;

    public function __construct($quotaRepository)
    {
        $this->quotaRepository = $quotaRepository;
    }

    public function checkAvailability(int $productId, ProductQuery $query): array
    {
        if (!$query->getProductType()->requiresQuotaCheck()) {
            return [
                'available' => true,
                'availability_type' => 'no_quota_required',
                'remaining_quota' => null,
                'release_date' => null,
                'message' => 'Producto sin restricción de cupo',
            ];
        }

        $quotas = $this->findRelevantQuotas($productId, $query);

        if (empty($quotas)) {
            return [
                'available' => false,
                'availability_type' => 'no_quota_found',
                'remaining_quota' => 0,
                'release_date' => null,
                'message' => 'No se encontró cupo disponible para las fechas solicitadas',
            ];
        }

        return $this->evaluateQuotas($quotas, $query);
    }

    public function checkMultipleProductsAvailability(array $productIds, ProductQuery $query): array
    {
        $results = [];

        foreach ($productIds as $productId) {
            $results[$productId] = $this->checkAvailability($productId, $query);
        }

        return $results;
    }

    public function getAvailableDatesForProduct(int $productId, ProductQuery $query): array
    {
        $quotas = $this->quotaRepository->findByProduct($productId);
        $availablePeriods = [];

        foreach ($quotas as $quota) {
            if ($this->isQuotaValid($quota, $query)) {
                $availablePeriods[] = [
                    'start_date' => $quota['vigencia_ini'],
                    'end_date' => $quota['vigencia_fin'],
                    'remaining_quota' => $this->calculateRemainingQuota($quota),
                    'release_date' => $quota['release'] ? $this->calculateReleaseDate($quota) : null,
                    'is_freesale' => (bool) $quota['freesale'],
                ];
            }
        }

        return $this->consolidateAvailablePeriods($availablePeriods);
    }

    public function reserveQuota(int $productId, ProductQuery $query, int $requestedQuantity = 1): array
    {
        $availability = $this->checkAvailability($productId, $query);

        if (!$availability['available']) {
            return [
                'success' => false,
                'message' => $availability['message'],
                'reserved_quantity' => 0,
            ];
        }

        $relevantQuotas = $this->findRelevantQuotas($productId, $query);
        $bestQuota = $this->selectBestQuota($relevantQuotas, $query);

        if (!$bestQuota) {
            return [
                'success' => false,
                'message' => 'No se pudo encontrar un cupo adecuado para reservar',
                'reserved_quantity' => 0,
            ];
        }

        return $this->processQuotaReservation($bestQuota, $requestedQuantity, $query);
    }

    private function findRelevantQuotas(int $productId, ProductQuery $query): array
    {
        $travelStart = $query->getTravelDates()->getStartDate();
        $travelEnd = $query->getTravelDates()->getEndDate();

        $quotaFilters = [
            'fk_producto_id' => $productId,
        ];

        $allQuotas = $this->quotaRepository->findByFilters($quotaFilters);

        return array_filter($allQuotas, function ($quota) use ($travelStart, $travelEnd) {
            $quotaStart = new DateTime($quota['vigencia_ini']);
            $quotaEnd = new DateTime($quota['vigencia_fin']);

            return $travelStart >= $quotaStart && $travelEnd <= $quotaEnd;
        });
    }

    private function evaluateQuotas(array $quotas, ProductQuery $query): array
    {
        $totalPax = $query->getPaxConfiguration()->getTotalPax();
        $bestQuota = null;
        $maxAvailable = 0;

        foreach ($quotas as $quota) {
            if (!$this->isQuotaValid($quota, $query)) {
                continue;
            }

            $remainingQuota = $this->calculateRemainingQuota($quota);

            if ($quota['freesale'] || $remainingQuota >= $totalPax) {
                $bestQuota = $quota;
                $maxAvailable = $quota['freesale'] ? 999999 : $remainingQuota;
                break;
            }

            if ($remainingQuota > $maxAvailable) {
                $maxAvailable = $remainingQuota;
                $bestQuota = $quota;
            }
        }

        if (!$bestQuota) {
            return [
                'available' => false,
                'availability_type' => 'no_valid_quota',
                'remaining_quota' => 0,
                'release_date' => null,
                'message' => 'No hay cupos válidos disponibles',
            ];
        }

        $isAvailable = $bestQuota['freesale'] || $maxAvailable >= $totalPax;
        $availabilityType = $bestQuota['freesale'] ? 'freesale' : 'quota_controlled';

        if (!$isAvailable && $maxAvailable > 0) {
            $availabilityType = 'insufficient_quota';
        }

        return [
            'available' => $isAvailable,
            'availability_type' => $availabilityType,
            'remaining_quota' => $maxAvailable,
            'release_date' => $bestQuota['release'] ? $this->calculateReleaseDate($bestQuota) : null,
            'message' => $this->generateAvailabilityMessage($isAvailable, $availabilityType, $maxAvailable, $totalPax),
        ];
    }

    private function isQuotaValid(array $quota, ProductQuery $query): bool
    {
        $today = new DateTime();

        if ($quota['release'] > 0) {
            $releaseDate = $this->calculateReleaseDate($quota);
            if ($today > $releaseDate) {
                return false;
            }
        }

        return true;
    }

    private function calculateRemainingQuota(array $quota): int
    {
        $totalQuota = $quota['cantidad'];
        $consumedQuota = $this->quotaRepository->getConsumedQuota($quota['cupo_id']);

        return max(0, $totalQuota - $consumedQuota);
    }

    private function calculateReleaseDate(array $quota): DateTime
    {
        $quotaStart = new DateTime($quota['vigencia_ini']);
        $releaseDate = clone $quotaStart;
        $releaseDate->modify("-{$quota['release']} days");

        return $releaseDate;
    }

    private function selectBestQuota(array $quotas, ProductQuery $query): ?array
    {
        if (empty($quotas)) {
            return null;
        }

        usort($quotas, function ($a, $b) {
            if ($a['freesale'] && !$b['freesale']) {
                return -1;
            }
            if (!$a['freesale'] && $b['freesale']) {
                return 1;
            }

            $aRemaining = $this->calculateRemainingQuota($a);
            $bRemaining = $this->calculateRemainingQuota($b);

            return $bRemaining <=> $aRemaining;
        });

        return $quotas[0];
    }

    private function processQuotaReservation(array $quota, int $requestedQuantity, ProductQuery $query): array
    {
        if ($quota['freesale']) {
            return [
                'success' => true,
                'message' => 'Cupo reservado exitosamente (freesale)',
                'reserved_quantity' => $requestedQuantity,
                'quota_type' => 'freesale',
            ];
        }

        $remainingQuota = $this->calculateRemainingQuota($quota);

        if ($remainingQuota >= $requestedQuantity) {
            $this->quotaRepository->consumeQuota($quota['cupo_id'], $requestedQuantity);

            return [
                'success' => true,
                'message' => "Cupo reservado exitosamente. Quedan {$remainingQuota} disponibles",
                'reserved_quantity' => $requestedQuantity,
                'quota_type' => 'quota_controlled',
                'remaining_after_reservation' => $remainingQuota - $requestedQuantity,
            ];
        }

        return [
            'success' => false,
            'message' => "Cupo insuficiente. Solicitado: {$requestedQuantity}, Disponible: {$remainingQuota}",
            'reserved_quantity' => 0,
            'quota_type' => 'quota_controlled',
            'available_quantity' => $remainingQuota,
        ];
    }

    private function consolidateAvailablePeriods(array $periods): array
    {
        if (empty($periods)) {
            return [];
        }

        usort($periods, function ($a, $b) {
            return strcmp($a['start_date'], $b['start_date']);
        });

        $consolidated = [];
        $current = $periods[0];

        for ($i = 1; $i < count($periods); $i++) {
            $next = $periods[$i];

            if ($this->periodsCanBeMerged($current, $next)) {
                $current = $this->mergePeriods($current, $next);
            } else {
                $consolidated[] = $current;
                $current = $next;
            }
        }

        $consolidated[] = $current;

        return $consolidated;
    }

    private function periodsCanBeMerged(array $period1, array $period2): bool
    {
        $end1 = new DateTime($period1['end_date']);
        $start2 = new DateTime($period2['start_date']);

        $end1->modify('+1 day');

        return $end1 >= $start2;
    }

    private function mergePeriods(array $period1, array $period2): array
    {
        return [
            'start_date' => $period1['start_date'],
            'end_date' => max($period1['end_date'], $period2['end_date']),
            'remaining_quota' => min($period1['remaining_quota'], $period2['remaining_quota']),
            'release_date' => $period1['release_date'] ?: $period2['release_date'],
            'is_freesale' => $period1['is_freesale'] && $period2['is_freesale'],
        ];
    }

    private function generateAvailabilityMessage(bool $isAvailable, string $type, int $available, int $requested): string
    {
        if (!$isAvailable) {
            return match ($type) {
                'no_valid_quota' => 'No hay cupos disponibles para las fechas seleccionadas',
                'insufficient_quota' => "Cupo insuficiente. Disponible: {$available}, Solicitado: {$requested}",
                default => 'Producto no disponible',
            };
        }

        return match ($type) {
            'freesale' => 'Producto disponible sin restricción de cupo',
            'quota_controlled' => "Producto disponible. Cupo restante: {$available}",
            default => 'Producto disponible',
        };
    }
}