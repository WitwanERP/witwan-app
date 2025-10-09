<?php

namespace App\Domain\Pricing\Repositories;

use App\Domain\Pricing\Enums\ProductType;
use App\Domain\Pricing\ValueObjects\DateRange;

interface ProductRepositoryInterface
{
    public function findById(int $id): ?array;

    public function findByType(ProductType $type): array;

    public function findByDestination(int $destinationCityId): array;

    public function findByTypeAndDestination(ProductType $type, int $destinationCityId): array;

    public function findActiveProducts(): array;

    public function findByIds(array $ids): array;

    public function searchByName(string $name): array;

    public function findByFilters(array $filters): array;

    public function findWithVigencias(int $productId): array;

    public function save(array $productData): int;

    public function update(int $id, array $productData): bool;

    public function delete(int $id): bool;

    public function exists(int $id): bool;

    public function count(): int;

    public function countByType(ProductType $type): int;

    public function findByProviderAndType(int $providerId, ProductType $type): array;

    public function findProductsForQuoting(ProductType $type, int $originCityId, int $destinationCityId): array;
}