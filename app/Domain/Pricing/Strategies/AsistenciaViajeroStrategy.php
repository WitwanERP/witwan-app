<?php

namespace App\Domain\Pricing\Strategies;

use App\Domain\Pricing\Entities\Product;
use App\Domain\Pricing\Entities\PriceCalculation;
use App\Domain\Pricing\Enums\ProductType;
use App\Domain\Pricing\ValueObjects\DateRange;
use App\Domain\Pricing\ValueObjects\Money;
use App\Domain\Pricing\ValueObjects\PaxConfiguration;
use App\Domain\Pricing\Repositories\TariffRepositoryInterface;

class AsistenciaViajeroStrategy implements PricingStrategyInterface
{
    private TariffRepositoryInterface $tariffRepository;

    public function __construct(TariffRepositoryInterface $tariffRepository)
    {
        $this->tariffRepository = $tariffRepository;
    }

    public function calculate(
        Product $product,
        PaxConfiguration $paxConfiguration,
        DateRange $dateRange
    ): PriceCalculation {
        $days = $this->calculateDays($dateRange);
        $tariffs = $this->getTariffsForDuration($product, $days);

        if (empty($tariffs)) {
            throw new \InvalidArgumentException('No se encontraron tarifas para Asistencia al Viajero');
        }

        $baseCost = $this->calculateBaseCostByAge($tariffs, $paxConfiguration, $days);

        $calculation = new PriceCalculation(
            $product,
            $paxConfiguration,
            $dateRange,
            $baseCost
        );

        $this->applyCoverageAdjustments($calculation, $tariffs);
        $this->applyAgeDiscounts($calculation, $paxConfiguration);

        $calculation->addAppliedRule('asv_pricing_strategy', [
            'days' => $days,
            'adults' => $paxConfiguration->getAdults(),
            'children' => $paxConfiguration->getChildren(),
            'coverage_type' => $tariffs['coverage_type'] ?? 'standard',
        ]);

        return $calculation;
    }

    public function supports(string $productType): bool
    {
        return $productType === 'ASV';
    }

    public function getStrategyName(): string
    {
        return 'Asistencia al Viajero Pricing Strategy';
    }

    private function calculateDays(DateRange $dateRange): int
    {
        $start = $dateRange->getStartDate();
        $end = $dateRange->getEndDate();
        return $start->diff($end)->days + 1;
    }

    private function getTariffsForDuration(Product $product, int $days): array
    {
        // Buscar tarifas por duración específica o calcular por múltiplos
        return $this->tariffRepository->findASVTariffsByDuration($product->getId(), $days);
    }

    private function calculateBaseCostByAge(array $tariffs, PaxConfiguration $paxConfiguration, int $days): Money
    {
        $adults = $paxConfiguration->getAdults();
        $children = $paxConfiguration->getChildren();

        $adultCost = $tariffs['adult_rate'] * $adults * $days;
        $childCost = $tariffs['child_rate'] * $children * $days;

        $totalCost = $adultCost + $childCost;

        return new Money($totalCost, $tariffs['currency'] ?? 'USD');
    }

    private function applyCoverageAdjustments(PriceCalculation $calculation, array $tariffs): void
    {
        if (isset($tariffs['coverage_premium']) && $tariffs['coverage_premium'] > 0) {
            $coverageAdjustment = new Money($tariffs['coverage_premium'], $tariffs['currency'] ?? 'USD');
            $calculation->addAdjustment(
                $coverageAdjustment,
                'Cobertura premium incluida'
            );
        }
    }

    private function applyAgeDiscounts(PriceCalculation $calculation, PaxConfiguration $paxConfiguration): void
    {
        $seniors = $paxConfiguration->getSeniors();

        if ($seniors > 0) {
            $seniorSurcharge = $calculation->getBasePrice()->multiply(0.15 * $seniors);
            $calculation->addAdjustment(
                $seniorSurcharge,
                "Recargo por adultos mayores: {$seniors} personas"
            );
        }
    }
}