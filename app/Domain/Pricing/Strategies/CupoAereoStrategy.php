<?php

namespace App\Domain\Pricing\Strategies;

use App\Domain\Pricing\Entities\Product;
use App\Domain\Pricing\Entities\PriceCalculation;
use App\Domain\Pricing\ValueObjects\DateRange;
use App\Domain\Pricing\ValueObjects\Money;
use App\Domain\Pricing\ValueObjects\PaxConfiguration;
use App\Domain\Pricing\Repositories\QuotaRepositoryInterface;

class CupoAereoStrategy implements PricingStrategyInterface
{
    private QuotaRepositoryInterface $quotaRepository;

    public function __construct(QuotaRepositoryInterface $quotaRepository)
    {
        $this->quotaRepository = $quotaRepository;
    }

    public function calculate(
        Product $product,
        PaxConfiguration $paxConfiguration,
        DateRange $dateRange
    ): PriceCalculation {
        $flightQuota = $this->getFlightQuota($product);

        if (!$flightQuota || $flightQuota['disponibles'] < $paxConfiguration->getTotalPax()) {
            throw new \InvalidArgumentException('No hay cupo aéreo disponible para este producto');
        }

        $baseCost = $this->calculateFlightCost($flightQuota, $paxConfiguration);

        $calculation = new PriceCalculation(
            $product,
            $paxConfiguration,
            $dateRange,
            $baseCost
        );

        $this->applyAirlineTaxes($calculation, $flightQuota, $paxConfiguration);
        $this->applyAgeSpecificRates($calculation, $flightQuota, $paxConfiguration);
        $this->applyCoverageInsurance($calculation, $flightQuota, $paxConfiguration);

        $calculation->addAppliedRule('cae_pricing_strategy', [
            'flight_destination' => $flightQuota['destino'],
            'total_pax' => $paxConfiguration->getTotalPax(),
            'available_quota' => $flightQuota['disponibles'],
            'flight_taxes' => $flightQuota['impuestos_totales'] ?? 0,
        ]);

        return $calculation;
    }

    public function supports(string $productType): bool
    {
        return $productType === 'CAE';
    }

    public function getStrategyName(): string
    {
        return 'Cupo Aéreo Pricing Strategy';
    }

    private function getFlightQuota(Product $product): ?array
    {
        return $this->quotaRepository->findFlightQuota($product->getId());
    }

    private function calculateFlightCost(array $flightQuota, PaxConfiguration $paxConfiguration): Money
    {
        $adults = $paxConfiguration->getAdults();
        $children = $paxConfiguration->getChildren();

        $adultRate = $flightQuota['tarifa_neta'] ?? 0;
        $childRate = $flightQuota['child_tarifa_neta'] ?? $adultRate;

        $totalCost = ($adultRate * $adults) + ($childRate * $children);

        return new Money($totalCost, $flightQuota['fk_moneda_id'] ?? 'USD');
    }

    private function applyAirlineTaxes(PriceCalculation $calculation, array $flightQuota, PaxConfiguration $paxConfiguration): void
    {
        $adults = $paxConfiguration->getAdults();
        $children = $paxConfiguration->getChildren();

        $adultTaxes = $flightQuota['impuestos_totales'] ?? 0;
        $childTaxes = $flightQuota['child_impuestos_totales'] ?? $adultTaxes;

        $totalTaxes = ($adultTaxes * $adults) + ($childTaxes * $children);

        if ($totalTaxes > 0) {
            $taxesAdjustment = new Money($totalTaxes, $flightQuota['fk_moneda_id'] ?? 'USD');
            $calculation->addAdjustment(
                $taxesAdjustment,
                'Impuestos aéreos'
            );
        }
    }

    private function applyAgeSpecificRates(PriceCalculation $calculation, array $flightQuota, PaxConfiguration $paxConfiguration): void
    {
        $infants = $paxConfiguration->getInfants();

        if ($infants > 0) {
            // Los bebés suelen pagar un porcentaje menor
            $infantRate = $flightQuota['infant_rate'] ?? ($flightQuota['tarifa_neta'] * 0.1);
            $infantCost = new Money($infantRate * $infants, $flightQuota['fk_moneda_id'] ?? 'USD');

            $calculation->addAdjustment(
                $infantCost,
                "Tarifa especial para {$infants} bebé(s)"
            );
        }
    }

    private function applyCoverageInsurance(PriceCalculation $calculation, array $flightQuota, PaxConfiguration $paxConfiguration): void
    {
        $adults = $paxConfiguration->getAdults();
        $children = $paxConfiguration->getChildren();

        $adultCoverage = $flightQuota['cobertura'] ?? 0;
        $childCoverage = $flightQuota['child_cobertura'] ?? $adultCoverage;

        $totalCoverage = ($adultCoverage * $adults) + ($childCoverage * $children);

        if ($totalCoverage > 0) {
            $coverageAdjustment = new Money($totalCoverage, $flightQuota['fk_moneda_id'] ?? 'USD');
            $calculation->addAdjustment(
                $coverageAdjustment,
                'Cobertura de seguro incluida'
            );
        }
    }
}