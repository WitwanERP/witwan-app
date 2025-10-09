<?php

namespace App\Domain\Pricing\Entities;

use App\Domain\Pricing\ValueObjects\Money;
use App\Domain\Pricing\ValueObjects\DateRange;
use App\Domain\Pricing\ValueObjects\PaxConfiguration;

class PriceCalculation
{
    private Product $product;
    private PaxConfiguration $paxConfiguration;
    private DateRange $dateRange;
    private Money $basePrice;
    private Money $adjustments;
    private Money $discounts;
    private Money $taxes;
    private Money $totalPrice;
    private array $breakdown;
    private array $appliedRules;

    public function __construct(
        Product $product,
        PaxConfiguration $paxConfiguration,
        DateRange $dateRange,
        Money $basePrice
    ) {
        $this->product = $product;
        $this->paxConfiguration = $paxConfiguration;
        $this->dateRange = $dateRange;
        $this->basePrice = $basePrice;
        $this->adjustments = new Money(0, $basePrice->getCurrency());
        $this->discounts = new Money(0, $basePrice->getCurrency());
        $this->taxes = new Money(0, $basePrice->getCurrency());
        $this->totalPrice = $basePrice;
        $this->breakdown = [];
        $this->appliedRules = [];
    }

    public function getProduct(): Product
    {
        return $this->product;
    }

    public function getPaxConfiguration(): PaxConfiguration
    {
        return $this->paxConfiguration;
    }

    public function getDateRange(): DateRange
    {
        return $this->dateRange;
    }

    public function getBasePrice(): Money
    {
        return $this->basePrice;
    }

    public function getAdjustments(): Money
    {
        return $this->adjustments;
    }

    public function getDiscounts(): Money
    {
        return $this->discounts;
    }

    public function getTaxes(): Money
    {
        return $this->taxes;
    }

    public function getTotalPrice(): Money
    {
        return $this->totalPrice;
    }

    public function getBreakdown(): array
    {
        return $this->breakdown;
    }

    public function getAppliedRules(): array
    {
        return $this->appliedRules;
    }

    public function addAdjustment(Money $amount, string $reason): void
    {
        $this->adjustments = $this->adjustments->add($amount);
        $this->breakdown[] = [
            'type' => 'adjustment',
            'amount' => $amount,
            'reason' => $reason,
        ];
        $this->recalculateTotal();
    }

    public function addDiscount(Money $amount, string $reason): void
    {
        $this->discounts = $this->discounts->add($amount);
        $this->breakdown[] = [
            'type' => 'discount',
            'amount' => $amount,
            'reason' => $reason,
        ];
        $this->recalculateTotal();
    }

    public function addTax(Money $amount, string $taxType): void
    {
        $this->taxes = $this->taxes->add($amount);
        $this->breakdown[] = [
            'type' => 'tax',
            'amount' => $amount,
            'reason' => $taxType,
        ];
        $this->recalculateTotal();
    }

    public function addAppliedRule(string $ruleName, array $ruleData = []): void
    {
        $this->appliedRules[] = [
            'rule' => $ruleName,
            'data' => $ruleData,
            'applied_at' => new \DateTime(),
        ];
    }

    public function getPricePerPax(): Money
    {
        $totalPax = $this->paxConfiguration->getTotalPax();

        if ($totalPax === 0) {
            return new Money(0, $this->totalPrice->getCurrency());
        }

        return $this->totalPrice->divide($totalPax);
    }

    public function getPricePerNight(): Money
    {
        $nights = $this->dateRange->getDurationInDays();

        if ($nights === 0) {
            return $this->totalPrice;
        }

        return $this->totalPrice->divide($nights);
    }

    private function recalculateTotal(): void
    {
        $this->totalPrice = $this->basePrice
            ->add($this->adjustments)
            ->subtract($this->discounts)
            ->add($this->taxes);
    }

    public function getNetPrice(): Money
    {
        return $this->basePrice
            ->add($this->adjustments)
            ->subtract($this->discounts);
    }
}