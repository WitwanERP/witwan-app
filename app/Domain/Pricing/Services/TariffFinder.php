<?php

namespace App\Domain\Pricing\Services;

use App\Domain\Pricing\ValueObjects\ProductQuery;
use App\Domain\Pricing\ValueObjects\Money;
use App\Domain\Pricing\Repositories\TariffRepositoryInterface;
use App\Domain\Pricing\Repositories\ProductRepositoryInterface;
use DateTime;

class TariffFinder
{
    private TariffRepositoryInterface $tariffRepository;
    private ProductRepositoryInterface $productRepository;

    public function __construct(
        TariffRepositoryInterface $tariffRepository,
        ProductRepositoryInterface $productRepository
    ) {
        $this->tariffRepository = $tariffRepository;
        $this->productRepository = $productRepository;
    }

    public function findBestTariffForProduct(int $productId, ProductQuery $query): ?array
    {
        $product = $this->productRepository->findById($productId);
        if (!$product || !$product->isActive()) {
            return null;
        }

        $validVigencias = $this->findValidVigencias($productId, $query);
        if (empty($validVigencias)) {
            return null;
        }

        $bestVigencia = $this->selectBestVigencia($validVigencias);

        $tariff = $this->findTariffForVigencia($bestVigencia, $query);
        if (!$tariff) {
            return null;
        }

        return [
            'product' => $product,
            'vigencia' => $bestVigencia,
            'tariff' => $tariff,
            'calculated_price' => $this->calculatePrice($tariff, $query),
        ];
    }

    public function findAllValidTariffsForQuery(ProductQuery $query): array
    {
        $products = $this->findMatchingProducts($query);
        $validTariffs = [];

        foreach ($products as $product) {
            $tariffData = $this->findBestTariffForProduct($product['producto_id'], $query);
            if ($tariffData) {
                $validTariffs[] = $tariffData;
            }
        }

        return $this->sortTariffsByPrice($validTariffs);
    }

    private function findMatchingProducts(ProductQuery $query): array
    {
        $filters = [
            'fk_tipoproducto_id' => $query->getProductType()->value,
            'destino' => $query->getDestinationCityId(),
            'habilitar' => 1,
        ];

        if (!$query->getProductType()->hasFixedDates()) {
            $filters['origen'] = $query->getOriginCityId();
        }

        if ($query->hasSpecificProduct()) {
            $filters['producto_id'] = $query->getSpecificProductId();
        }

        return $this->productRepository->findByFilters($filters);
    }

    private function findValidVigencias(int $productId, ProductQuery $query): array
    {
        $travelStart = $query->getTravelDates()->getStartDate();
        $travelEnd = $query->getTravelDates()->getEndDate();
        $today = new DateTime();

        $vigenciaFilters = [
            'fk_producto_id' => $productId,
            'residente' => $query->getResidentCode(),
        ];

        $vigencias = $this->tariffRepository->findVigenciasByFilters($vigenciaFilters);

        return array_filter($vigencias, function ($vigencia) use ($travelStart, $travelEnd, $today) {
            $vigenciaStart = new DateTime($vigencia['vigencia_ini']);
            $vigenciaEnd = new DateTime($vigencia['vigencia_fin']);
            $saleStart = new DateTime($vigencia['vigencia_ventaini']);
            $saleEnd = new DateTime($vigencia['vigencia_ventafin']);

            $travelInRange = $travelStart >= $vigenciaStart && $travelEnd <= $vigenciaEnd;
            $saleInRange = $today >= $saleStart && $today <= $saleEnd;

            if (isset($vigencia['noches_minimas']) && $vigencia['noches_minimas'] > 0) {
                $actualNights = $query->getTravelDates()->getDurationInDays();
                if ($actualNights < $vigencia['noches_minimas']) {
                    return false;
                }
            }

            return $travelInRange && $saleInRange;
        });
    }

    private function selectBestVigencia(array $vigencias): array
    {
        usort($vigencias, function ($a, $b) {
            $priorityA = $a['vigencia_prioridad'] ?? 0;
            $priorityB = $b['vigencia_prioridad'] ?? 0;

            if ($priorityA === $priorityB) {
                return strtotime($b['vigencia_ini']) <=> strtotime($a['vigencia_ini']);
            }

            return $priorityB <=> $priorityA;
        });

        return $vigencias[0];
    }

    private function findTariffForVigencia(array $vigencia, ProductQuery $query): ?array
    {
        $totalPax = $query->getPaxConfiguration()->getTotalPax();

        $tariffFilters = [
            'fk_vigencia_id' => $vigencia['vigencia_id'],
        ];

        $tariffs = $this->tariffRepository->findTariffsByFilters($tariffFilters);

        $matchingTariffs = array_filter($tariffs, function ($tariff) use ($totalPax) {
            return $totalPax >= $tariff['min_pax'] && $totalPax <= $tariff['max_pax'];
        });

        if (empty($matchingTariffs)) {
            return null;
        }

        return array_reduce($matchingTariffs, function ($best, $current) use ($totalPax) {
            if (!$best) {
                return $current;
            }

            $currentRange = $current['max_pax'] - $current['min_pax'];
            $bestRange = $best['max_pax'] - $best['min_pax'];

            return $currentRange < $bestRange ? $current : $best;
        });
    }

    private function calculatePrice(array $tariff, ProductQuery $query): Money
    {
        $baseCost = $tariff['costo'];
        $currency = $tariff['moneda_venta'];

        $totalPrice = $this->applyPaxMultiplier($baseCost, $query);
        $totalPrice = $this->applyDurationMultiplier($totalPrice, $query);

        return new Money($totalPrice, $currency);
    }

    private function applyPaxMultiplier(float $basePrice, ProductQuery $query): float
    {
        $paxConfig = $query->getPaxConfiguration();
        $productType = $query->getProductType();

        if ($productType->value === 'HOT') {
            return $basePrice;
        }

        $totalPrice = $basePrice * $paxConfig->getAdults();

        if ($paxConfig->getChildren() > 0) {
            $childrenPrice = $basePrice * 0.75 * $paxConfig->getChildren();
            $totalPrice += $childrenPrice;
        }

        if ($paxConfig->getInfants() > 0) {
            $infantsPrice = $basePrice * 0.25 * $paxConfig->getInfants();
            $totalPrice += $infantsPrice;
        }

        return $totalPrice;
    }

    private function applyDurationMultiplier(float $price, ProductQuery $query): float
    {
        $productType = $query->getProductType();

        if (in_array($productType->value, ['HOT'])) {
            $nights = $query->getTravelDates()->getDurationInDays();
            return $price * $nights;
        }

        return $price;
    }

    private function sortTariffsByPrice(array $tariffs): array
    {
        usort($tariffs, function ($a, $b) {
            $priceA = $a['calculated_price']->getAmount();
            $priceB = $b['calculated_price']->getAmount();

            return $priceA <=> $priceB;
        });

        return $tariffs;
    }

    public function getTariffBreakdown(array $tariff, ProductQuery $query): array
    {
        $paxConfig = $query->getPaxConfiguration();
        $baseCost = $tariff['costo'];
        $currency = $tariff['moneda_venta'];

        $breakdown = [
            'base_price' => $baseCost,
            'currency' => $currency,
            'pax_breakdown' => [],
            'duration_multiplier' => 1,
            'total_before_duration' => 0,
            'final_total' => 0,
        ];

        if ($query->getProductType()->value === 'HOT') {
            $breakdown['pax_breakdown']['rooms'] = $baseCost;
            $breakdown['total_before_duration'] = $baseCost;
        } else {
            if ($paxConfig->getAdults() > 0) {
                $adultPrice = $baseCost * $paxConfig->getAdults();
                $breakdown['pax_breakdown']['adults'] = $adultPrice;
                $breakdown['total_before_duration'] += $adultPrice;
            }

            if ($paxConfig->getChildren() > 0) {
                $childPrice = $baseCost * 0.75 * $paxConfig->getChildren();
                $breakdown['pax_breakdown']['children'] = $childPrice;
                $breakdown['total_before_duration'] += $childPrice;
            }

            if ($paxConfig->getInfants() > 0) {
                $infantPrice = $baseCost * 0.25 * $paxConfig->getInfants();
                $breakdown['pax_breakdown']['infants'] = $infantPrice;
                $breakdown['total_before_duration'] += $infantPrice;
            }
        }

        if (in_array($query->getProductType()->value, ['HOT'])) {
            $nights = $query->getTravelDates()->getDurationInDays();
            $breakdown['duration_multiplier'] = $nights;
            $breakdown['final_total'] = $breakdown['total_before_duration'] * $nights;
        } else {
            $breakdown['final_total'] = $breakdown['total_before_duration'];
        }

        return $breakdown;
    }
}