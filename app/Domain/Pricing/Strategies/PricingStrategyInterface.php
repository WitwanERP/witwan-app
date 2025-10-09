<?php

namespace App\Domain\Pricing\Strategies;

use App\Domain\Pricing\Entities\Product;
use App\Domain\Pricing\Entities\PriceCalculation;
use App\Domain\Pricing\ValueObjects\DateRange;
use App\Domain\Pricing\ValueObjects\PaxConfiguration;

interface PricingStrategyInterface
{
    public function calculate(
        Product $product,
        PaxConfiguration $paxConfiguration,
        DateRange $dateRange
    ): PriceCalculation;

    public function supports(string $productType): bool;

    public function getStrategyName(): string;
}