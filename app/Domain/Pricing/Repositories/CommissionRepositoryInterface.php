<?php

namespace App\Domain\Pricing\Repositories;

use App\Domain\Pricing\Enums\ProductType;

interface CommissionRepositoryInterface
{
    public function findCommissionForProduct(int $productId, ProductType $productType, int $cityId, int $tariffId): ?array;

    public function findByTariff(int $tariffId): array;

    public function findByProductType(ProductType $productType): array;

    public function findByCity(int $cityId): array;

    public function findActiveCommissions(): array;

    public function saveCommission(array $commissionData): int;

    public function updateCommission(int $commissionId, array $commissionData): bool;

    public function deleteCommission(int $commissionId): bool;

    public function findCommissionHistory(int $productId): array;

    public function bulkUpdateCommissions(array $commissionUpdates): bool;

    public function getCommissionStatistics(): array;
}