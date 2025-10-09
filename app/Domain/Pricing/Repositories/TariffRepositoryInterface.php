<?php

namespace App\Domain\Pricing\Repositories;

use App\Domain\Pricing\ValueObjects\DateRange;

interface TariffRepositoryInterface
{
    public function findVigenciasByProduct(int $productId): array;

    public function findVigenciasByFilters(array $filters): array;

    public function findValidVigenciasForDateRange(int $productId, DateRange $dateRange, string $resident = 'N'): array;

    public function findTariffsByVigencia(int $vigenciaId): array;

    public function findTariffsByFilters(array $filters): array;

    public function findTariffsForPaxRange(int $vigenciaId, int $minPax, int $maxPax): array;

    public function saveTariff(array $tariffData): int;

    public function updateTariff(int $tariffId, array $tariffData): bool;

    public function deleteTariff(int $tariffId): bool;

    public function saveVigencia(array $vigenciaData): int;

    public function updateVigencia(int $vigenciaId, array $vigenciaData): bool;

    public function deleteVigencia(int $vigenciaId): bool;

    public function findTariffHistory(int $productId, DateRange $dateRange): array;

    public function findActiveTariffs(int $productId): array;

    public function findExpiredVigencias(int $productId): array;

    public function bulkUpdateTariffs(array $tariffUpdates): bool;

    public function getTariffStatistics(int $productId): array;
}