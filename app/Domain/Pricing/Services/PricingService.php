<?php

namespace App\Domain\Pricing\Services;

use App\Domain\Pricing\Entities\Product;
use App\Domain\Pricing\Entities\PriceCalculation;
use App\Domain\Pricing\Enums\ClientType;
use App\Domain\Pricing\Strategies\PricingStrategyInterface;
use App\Domain\Pricing\ValueObjects\DateRange;
use App\Domain\Pricing\ValueObjects\Money;
use App\Domain\Pricing\ValueObjects\PaxConfiguration;

class PricingService
{
    private array $strategies;

    public function __construct(array $strategies = [])
    {
        $this->strategies = $strategies;
    }

    public function registerStrategy(string $productType, PricingStrategyInterface $strategy): void
    {
        $this->strategies[$productType] = $strategy;
    }

    public function calculatePrice(
        Product $product,
        PaxConfiguration $paxConfiguration,
        DateRange $dateRange,
        ClientType $clientType
    ): PriceCalculation {
        $strategy = $this->getStrategy($product->getType()->value);

        $calculation = $strategy->calculate($product, $paxConfiguration, $dateRange);

        $this->applyClientTypeDiscounts($calculation, $clientType);

        return $calculation;
    }

    public function calculateBulkPrice(
        array $products,
        PaxConfiguration $paxConfiguration,
        DateRange $dateRange,
        ClientType $clientType
    ): array {
        $calculations = [];

        foreach ($products as $productData) {
            $product = $productData['product'];
            $quantity = $productData['quantity'] ?? 1;

            $calculation = $this->calculatePrice($product, $paxConfiguration, $dateRange, $clientType);

            if ($quantity > 1) {
                $this->applyQuantityMultiplier($calculation, $quantity);
            }

            $calculations[] = $calculation;
        }

        $this->applyBulkDiscounts($calculations, $clientType);

        return $calculations;
    }

    public function getEstimatedPrice(
        Product $product,
        PaxConfiguration $paxConfiguration,
        DateRange $dateRange
    ): Money {
        $strategy = $this->getStrategy($product->getType()->value);

        $calculation = $strategy->calculate($product, $paxConfiguration, $dateRange);

        return $calculation->getTotalPrice();
    }

    private function getStrategy(string $productType): PricingStrategyInterface
    {
        if (!isset($this->strategies[$productType])) {
            throw new \InvalidArgumentException("No pricing strategy found for product type: {$productType}");
        }

        return $this->strategies[$productType];
    }

    private function applyClientTypeDiscounts(PriceCalculation $calculation, ClientType $clientType): void
    {
        if (!$clientType->hasSpecialRates()) {
            return;
        }

        $discountPercentage = $clientType->getDiscountPercentage();
        $basePrice = $calculation->getBasePrice();
        $discountAmount = $basePrice->multiply($discountPercentage / 100);

        $calculation->addDiscount(
            $discountAmount,
            "Descuento por tipo de cliente: {$clientType->getLabel()}"
        );

        $calculation->addAppliedRule('client_type_discount', [
            'client_type' => $clientType->value,
            'discount_percentage' => $discountPercentage,
            'discount_amount' => $discountAmount->getAmount(),
        ]);
    }

    private function applyQuantityMultiplier(PriceCalculation $calculation, int $quantity): void
    {
        $currentTotal = $calculation->getTotalPrice();
        $additionalAmount = $currentTotal->multiply($quantity - 1);

        $calculation->addAdjustment(
            $additionalAmount,
            "Multiplicador por cantidad: x{$quantity}"
        );

        $calculation->addAppliedRule('quantity_multiplier', [
            'quantity' => $quantity,
            'multiplier_amount' => $additionalAmount->getAmount(),
        ]);
    }

    private function applyBulkDiscounts(array $calculations, ClientType $clientType): void
    {
        $totalAmount = array_reduce(
            $calculations,
            fn($total, $calc) => $total->add($calc->getTotalPrice()),
            new Money(0, 'USD')
        );

        $minimumOrderAmount = new Money($clientType->getMinimumOrderAmount(), 'USD');

        if ($totalAmount->isGreaterThan($minimumOrderAmount)) {
            $bulkDiscountPercentage = $this->getBulkDiscountPercentage($totalAmount, $clientType);

            if ($bulkDiscountPercentage > 0) {
                foreach ($calculations as $calculation) {
                    $discountAmount = $calculation->getTotalPrice()->multiply($bulkDiscountPercentage / 100);

                    $calculation->addDiscount(
                        $discountAmount,
                        "Descuento por volumen: {$bulkDiscountPercentage}%"
                    );

                    $calculation->addAppliedRule('bulk_discount', [
                        'discount_percentage' => $bulkDiscountPercentage,
                        'total_order_amount' => $totalAmount->getAmount(),
                    ]);
                }
            }
        }
    }

    private function getBulkDiscountPercentage(Money $totalAmount, ClientType $clientType): float
    {
        $amount = $totalAmount->getAmount();

        return match ($clientType) {
            ClientType::WHOLESALE => match (true) {
                $amount >= 50000 => 5.0,
                $amount >= 20000 => 3.0,
                $amount >= 10000 => 2.0,
                default => 0.0,
            },
            ClientType::GROUP => match (true) {
                $amount >= 30000 => 4.0,
                $amount >= 15000 => 2.5,
                $amount >= 8000 => 1.5,
                default => 0.0,
            },
            ClientType::CORPORATE => match (true) {
                $amount >= 25000 => 3.0,
                $amount >= 12000 => 2.0,
                $amount >= 6000 => 1.0,
                default => 0.0,
            },
            default => 0.0,
        };
    }
}