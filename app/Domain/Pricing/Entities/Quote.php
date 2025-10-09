<?php

namespace App\Domain\Pricing\Entities;

use App\Domain\Pricing\Enums\ClientType;
use App\Domain\Pricing\ValueObjects\Money;
use App\Domain\Pricing\ValueObjects\DateRange;
use App\Domain\Pricing\ValueObjects\PaxConfiguration;
use DateTime;

class Quote
{
    private string $id;
    private int $clientId;
    private ClientType $clientType;
    private DateRange $dateRange;
    private PaxConfiguration $paxConfiguration;
    private array $products;
    private array $calculations;
    private Money $subtotal;
    private Money $discounts;
    private Money $taxes;
    private Money $total;
    private DateTime $createdAt;
    private DateTime $validUntil;
    private bool $isConfirmed;
    private array $metadata;

    public function __construct(
        string $id,
        int $clientId,
        ClientType $clientType,
        DateRange $dateRange,
        PaxConfiguration $paxConfiguration,
        DateTime $validUntil,
        array $metadata = []
    ) {
        $this->id = $id;
        $this->clientId = $clientId;
        $this->clientType = $clientType;
        $this->dateRange = $dateRange;
        $this->paxConfiguration = $paxConfiguration;
        $this->products = [];
        $this->calculations = [];
        $this->subtotal = new Money(0, 'USD');
        $this->discounts = new Money(0, 'USD');
        $this->taxes = new Money(0, 'USD');
        $this->total = new Money(0, 'USD');
        $this->createdAt = new DateTime();
        $this->validUntil = $validUntil;
        $this->isConfirmed = false;
        $this->metadata = $metadata;
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getClientId(): int
    {
        return $this->clientId;
    }

    public function getClientType(): ClientType
    {
        return $this->clientType;
    }

    public function getDateRange(): DateRange
    {
        return $this->dateRange;
    }

    public function getPaxConfiguration(): PaxConfiguration
    {
        return $this->paxConfiguration;
    }

    public function getProducts(): array
    {
        return $this->products;
    }

    public function getCalculations(): array
    {
        return $this->calculations;
    }

    public function getSubtotal(): Money
    {
        return $this->subtotal;
    }

    public function getDiscounts(): Money
    {
        return $this->discounts;
    }

    public function getTaxes(): Money
    {
        return $this->taxes;
    }

    public function getTotal(): Money
    {
        return $this->total;
    }

    public function getCreatedAt(): DateTime
    {
        return clone $this->createdAt;
    }

    public function getValidUntil(): DateTime
    {
        return clone $this->validUntil;
    }

    public function isConfirmed(): bool
    {
        return $this->isConfirmed;
    }

    public function isExpired(): bool
    {
        return new DateTime() > $this->validUntil;
    }

    public function getMetadata(): array
    {
        return $this->metadata;
    }

    public function addProduct(Product $product, int $quantity = 1): void
    {
        $this->products[] = [
            'product' => $product,
            'quantity' => $quantity,
        ];
    }

    public function addCalculation(PriceCalculation $calculation): void
    {
        $this->calculations[] = $calculation;
    }

    public function updateTotals(Money $subtotal, Money $discounts, Money $taxes, Money $total): void
    {
        $this->subtotal = $subtotal;
        $this->discounts = $discounts;
        $this->taxes = $taxes;
        $this->total = $total;
    }

    public function confirm(): void
    {
        if ($this->isExpired()) {
            throw new \InvalidArgumentException('Cannot confirm expired quote');
        }

        $this->isConfirmed = true;
    }

    public function extendValidity(DateTime $newValidUntil): void
    {
        if ($newValidUntil <= $this->validUntil) {
            throw new \InvalidArgumentException('New validity date must be after current validity date');
        }

        $this->validUntil = $newValidUntil;
    }

    public function addMetadata(string $key, mixed $value): void
    {
        $this->metadata[$key] = $value;
    }
}