<?php

namespace App\Domain\Pricing\Entities;

use App\Domain\Pricing\Enums\ProductType;
use App\Domain\Pricing\ValueObjects\Money;
use App\Domain\Pricing\ValueObjects\DateRange;

class Product
{
    private int $id;
    private string $name;
    private string $description;
    private ProductType $type;
    private Money $basePrice;
    private ?DateRange $availabilityPeriod;
    private array $metadata;
    private bool $isActive;

    public function __construct(
        int $id,
        string $name,
        string $description,
        ProductType $type,
        Money $basePrice,
        ?DateRange $availabilityPeriod = null,
        array $metadata = [],
        bool $isActive = true
    ) {
        $this->id = $id;
        $this->name = $name;
        $this->description = $description;
        $this->type = $type;
        $this->basePrice = $basePrice;
        $this->availabilityPeriod = $availabilityPeriod;
        $this->metadata = $metadata;
        $this->isActive = $isActive;
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function getType(): ProductType
    {
        return $this->type;
    }

    public function getBasePrice(): Money
    {
        return $this->basePrice;
    }

    public function getAvailabilityPeriod(): ?DateRange
    {
        return $this->availabilityPeriod;
    }

    public function getMetadata(): array
    {
        return $this->metadata;
    }

    public function getMetadataValue(string $key, mixed $default = null): mixed
    {
        return $this->metadata[$key] ?? $default;
    }

    public function isActive(): bool
    {
        return $this->isActive;
    }

    public function isAvailableForPeriod(DateRange $period): bool
    {
        if (!$this->isActive) {
            return false;
        }

        if ($this->availabilityPeriod === null) {
            return true;
        }

        return $this->availabilityPeriod->overlaps($period);
    }

    public function updateBasePrice(Money $newPrice): void
    {
        $this->basePrice = $newPrice;
    }

    public function updateAvailability(DateRange $period): void
    {
        $this->availabilityPeriod = $period;
    }

    public function activate(): void
    {
        $this->isActive = true;
    }

    public function deactivate(): void
    {
        $this->isActive = false;
    }

    public function setMetadata(array $metadata): void
    {
        $this->metadata = $metadata;
    }

    public function addMetadata(string $key, mixed $value): void
    {
        $this->metadata[$key] = $value;
    }
}