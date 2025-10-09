<?php

namespace App\Domain\Pricing\Repositories;

use App\Domain\Pricing\ValueObjects\DateRange;

interface QuotaRepositoryInterface
{
    public function findByProduct(int $productId): array;

    public function findByFilters(array $filters): array;

    public function findForDateRange(int $productId, DateRange $dateRange): array;

    public function findByProductAndCategory(int $productId, int $categoryId): array;

    public function getConsumedQuota(int $quotaId): int;

    public function consumeQuota(int $quotaId, int $quantity): bool;

    public function releaseQuota(int $quotaId, int $quantity): bool;

    public function saveQuota(array $quotaData): int;

    public function updateQuota(int $quotaId, array $quotaData): bool;

    public function deleteQuota(int $quotaId): bool;

    public function findQuotaConsumption(int $quotaId): array;

    public function getQuotaUtilization(int $productId, DateRange $dateRange): array;

    public function findExpiredQuotas(int $productId): array;

    public function findQuotasNearRelease(int $daysAhead = 7): array;

    public function bulkUpdateQuotas(array $quotaUpdates): bool;

    public function getQuotaStatistics(int $productId): array;
}