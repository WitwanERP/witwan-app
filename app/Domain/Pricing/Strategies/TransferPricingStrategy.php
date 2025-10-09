<?php

namespace App\Domain\Pricing\Strategies;

use App\Domain\Pricing\Entities\Product;
use App\Domain\Pricing\Entities\PriceCalculation;
use App\Domain\Pricing\Enums\ProductType;
use App\Domain\Pricing\ValueObjects\DateRange;
use App\Domain\Pricing\ValueObjects\Money;
use App\Domain\Pricing\ValueObjects\PaxConfiguration;

class TransferPricingStrategy implements PricingStrategyInterface
{
    public function calculate(
        Product $product,
        PaxConfiguration $paxConfiguration,
        DateRange $dateRange
    ): PriceCalculation {
        $basePrice = $product->getBasePrice();

        $calculation = new PriceCalculation(
            $product,
            $paxConfiguration,
            $dateRange,
            $basePrice
        );

        $this->applyVehicleTypeAdjustments($calculation, $product, $paxConfiguration);
        $this->applyDistanceAdjustments($calculation, $product);
        $this->applyTimeAdjustments($calculation, $dateRange);

        $calculation->addAppliedRule('transfer_pricing_strategy', [
            'total_pax' => $paxConfiguration->getTotalPax(),
            'base_price' => $basePrice->getAmount(),
        ]);

        return $calculation;
    }

    public function supports(string $productType): bool
    {
        return $productType === ProductType::TRANSFER->value;
    }

    public function getStrategyName(): string
    {
        return 'Transfer Pricing Strategy';
    }

    private function applyVehicleTypeAdjustments(
        PriceCalculation $calculation,
        Product $product,
        PaxConfiguration $paxConfiguration
    ): void {
        $totalPax = $paxConfiguration->getTotalPax();
        $vehicleType = $product->getMetadataValue('vehicle_type', 'sedan');

        $vehicleMultiplier = match ($vehicleType) {
            'sedan' => $totalPax > 3 ? 1.5 : 1.0,
            'van' => $totalPax > 8 ? 1.3 : 1.0,
            'bus' => $totalPax > 25 ? 1.2 : 1.0,
            'luxury' => 2.0,
            'suv' => 1.4,
            default => 1.0,
        };

        if ($vehicleMultiplier > 1.0) {
            $vehicleAdjustment = $calculation->getBasePrice()->multiply($vehicleMultiplier - 1.0);
            $calculation->addAdjustment(
                $vehicleAdjustment,
                "Ajuste por tipo de vehículo: {$vehicleType}"
            );
        }

        if ($totalPax > $this->getVehicleCapacity($vehicleType)) {
            $extraVehicleAdjustment = $calculation->getBasePrice()->multiply(0.8);
            $calculation->addAdjustment(
                $extraVehicleAdjustment,
                "Vehículo adicional requerido"
            );
        }
    }

    private function applyDistanceAdjustments(PriceCalculation $calculation, Product $product): void
    {
        $distance = $product->getMetadataValue('distance_km', 0);

        if ($distance > 100) {
            $longDistanceMultiplier = 1 + (($distance - 100) / 100) * 0.1;
            $longDistanceAdjustment = $calculation->getBasePrice()->multiply($longDistanceMultiplier - 1.0);

            $calculation->addAdjustment(
                $longDistanceAdjustment,
                "Ajuste por distancia larga: {$distance}km"
            );
        }

        if ($distance < 5) {
            $minimumFareAdjustment = new Money(15, $calculation->getBasePrice()->getCurrency());
            $calculation->addAdjustment(
                $minimumFareAdjustment,
                "Tarifa mínima aplicada"
            );
        }
    }

    private function applyTimeAdjustments(PriceCalculation $calculation, DateRange $dateRange): void
    {
        $startHour = (int) $dateRange->getStartDate()->format('H');

        if ($startHour >= 22 || $startHour <= 5) {
            $nightSurcharge = $calculation->getBasePrice()->multiply(0.25);
            $calculation->addAdjustment(
                $nightSurcharge,
                "Recargo nocturno (22:00-05:59)"
            );
        }

        $dayOfWeek = $dateRange->getStartDate()->format('N');
        if (in_array($dayOfWeek, [6, 7])) { // Weekend
            $weekendSurcharge = $calculation->getBasePrice()->multiply(0.15);
            $calculation->addAdjustment(
                $weekendSurcharge,
                "Recargo de fin de semana"
            );
        }
    }

    private function getVehicleCapacity(string $vehicleType): int
    {
        return match ($vehicleType) {
            'sedan' => 4,
            'van' => 8,
            'bus' => 25,
            'luxury' => 4,
            'suv' => 6,
            default => 4,
        };
    }
}