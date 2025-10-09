<?php

namespace App\Domain\Pricing\Services;

use App\Domain\Pricing\ValueObjects\ProductQuery;
use App\Domain\Pricing\ValueObjects\Money;
use App\Domain\Pricing\Repositories\CommissionRepositoryInterface;
use DateTime;

class PricingEngine
{
    private TariffFinder $tariffFinder;
    private AvailabilityChecker $availabilityChecker;
    private CommissionRepositoryInterface $commissionRepository;

    public function __construct(
        TariffFinder $tariffFinder,
        AvailabilityChecker $availabilityChecker,
        CommissionRepositoryInterface $commissionRepository
    ) {
        $this->tariffFinder = $tariffFinder;
        $this->availabilityChecker = $availabilityChecker;
        $this->commissionRepository = $commissionRepository;
    }

    public function calculatePriceForProduct(int $productId, ProductQuery $query): array
    {
        $availability = $this->availabilityChecker->checkAvailability($productId, $query);

        if (!$availability['available']) {
            return [
                'success' => false,
                'product_id' => $productId,
                'error' => 'Product not available',
                'availability' => $availability,
                'price_data' => null,
            ];
        }

        $tariffData = $this->tariffFinder->findBestTariffForProduct($productId, $query);

        if (!$tariffData) {
            return [
                'success' => false,
                'product_id' => $productId,
                'error' => 'No valid tariff found',
                'availability' => $availability,
                'price_data' => null,
            ];
        }

        $finalPricing = $this->calculateFinalPricing($tariffData, $query);

        return [
            'success' => true,
            'product_id' => $productId,
            'product' => $tariffData['product'],
            'availability' => $availability,
            'price_data' => $finalPricing,
            'tariff_breakdown' => $this->tariffFinder->getTariffBreakdown($tariffData['tariff'], $query),
        ];
    }

    public function calculatePricesForMultipleProducts(array $productIds, ProductQuery $query): array
    {
        $results = [];

        foreach ($productIds as $productId) {
            $results[] = $this->calculatePriceForProduct($productId, $query);
        }

        return [
            'query_summary' => $query->toArray(),
            'products_count' => count($productIds),
            'available_products' => count(array_filter($results, fn($r) => $r['success'])),
            'results' => $results,
            'cheapest_option' => $this->findCheapestOption($results),
            'most_expensive_option' => $this->findMostExpensiveOption($results),
        ];
    }

    public function searchAndPriceProducts(ProductQuery $query): array
    {
        $allTariffs = $this->tariffFinder->findAllValidTariffsForQuery($query);

        if (empty($allTariffs)) {
            return [
                'success' => false,
                'query_summary' => $query->toArray(),
                'message' => 'No products found matching the query criteria',
                'results' => [],
            ];
        }

        $results = [];
        foreach ($allTariffs as $tariffData) {
            $productId = $tariffData['product']['producto_id'];
            $availability = $this->availabilityChecker->checkAvailability($productId, $query);

            if ($availability['available']) {
                $finalPricing = $this->calculateFinalPricing($tariffData, $query);

                $results[] = [
                    'success' => true,
                    'product_id' => $productId,
                    'product' => $tariffData['product'],
                    'availability' => $availability,
                    'price_data' => $finalPricing,
                    'tariff_breakdown' => $this->tariffFinder->getTariffBreakdown($tariffData['tariff'], $query),
                ];
            }
        }

        return [
            'success' => !empty($results),
            'query_summary' => $query->toArray(),
            'products_found' => count($allTariffs),
            'available_products' => count($results),
            'results' => $results,
            'price_range' => $this->calculatePriceRange($results),
        ];
    }

    public function generateQuote(array $productIds, ProductQuery $query, array $metadata = []): array
    {
        $quoteId = $this->generateQuoteId();
        $products = [];
        $totalNetPrice = 0;
        $totalSalePrice = 0;
        $totalCommission = 0;

        foreach ($productIds as $productId) {
            $productPricing = $this->calculatePriceForProduct($productId, $query);

            if ($productPricing['success']) {
                $products[] = $productPricing;
                $priceData = $productPricing['price_data'];

                $totalNetPrice += $priceData['net_price'];
                $totalSalePrice += $priceData['sale_price'];
                $totalCommission += $priceData['commission_amount'];
            }
        }

        if (empty($products)) {
            return [
                'success' => false,
                'message' => 'No products available for quote generation',
            ];
        }

        return [
            'success' => true,
            'quote_id' => $quoteId,
            'client_id' => $query->getClientId(),
            'created_at' => new DateTime(),
            'valid_until' => $this->calculateQuoteValidUntil(),
            'query_details' => $query->toArray(),
            'products' => $products,
            'totals' => [
                'net_price' => $totalNetPrice,
                'sale_price' => $totalSalePrice,
                'commission_amount' => $totalCommission,
                'commission_percentage' => $totalNetPrice > 0 ? ($totalCommission / $totalNetPrice) * 100 : 0,
            ],
            'metadata' => $metadata,
        ];
    }

    private function calculateFinalPricing(array $tariffData, ProductQuery $query): array
    {
        $baseCost = $tariffData['calculated_price']->getAmount();
        $currency = $tariffData['calculated_price']->getCurrency();

        $commission = $this->calculateCommission($tariffData, $query);
        $markup = $this->calculateMarkup($tariffData, $query);

        $netPrice = $baseCost;
        $salePrice = $netPrice + $markup;
        $commissionAmount = $netPrice * ($commission / 100);

        return [
            'base_cost' => $baseCost,
            'net_price' => $netPrice,
            'sale_price' => $salePrice,
            'markup_amount' => $markup,
            'commission_percentage' => $commission,
            'commission_amount' => $commissionAmount,
            'currency' => $currency,
            'profit_margin' => $salePrice - $netPrice - $commissionAmount,
        ];
    }

    private function calculateCommission(array $tariffData, ProductQuery $query): float
    {
        $product = $tariffData['product'];
        $tariff = $tariffData['tariff'];

        $commissionData = $this->commissionRepository->findCommissionForProduct(
            $product['producto_id'],
            $query->getProductType(),
            $query->getDestinationCityId(),
            $tariff['fk_tarifario_id']
        );

        if ($commissionData) {
            return $commissionData['porcentaje_comision'] * 100;
        }

        return $this->getDefaultCommission($query->getProductType());
    }

    private function calculateMarkup(array $tariffData, ProductQuery $query): float
    {
        $product = $tariffData['product'];
        $baseCost = $tariffData['calculated_price']->getAmount();

        $defaultMarkupPercentage = match ($query->getProductType()->value) {
            'HOT' => 15.0,
            'EXC' => 20.0,
            'TRN' => 25.0,
            'PAQ' => 12.0,
            default => 18.0,
        };

        return $baseCost * ($defaultMarkupPercentage / 100);
    }

    private function getDefaultCommission(ProductType $productType): float
    {
        return match ($productType->value) {
            'HOT' => 10.0,
            'EXC' => 15.0,
            'TRN' => 8.0,
            'PAQ' => 12.0,
            'CTK' => 20.0,
            'ASV' => 25.0,
            default => 10.0,
        };
    }

    private function findCheapestOption(array $results): ?array
    {
        $validResults = array_filter($results, fn($r) => $r['success']);

        if (empty($validResults)) {
            return null;
        }

        return array_reduce($validResults, function ($cheapest, $current) {
            if (!$cheapest) {
                return $current;
            }

            $currentPrice = $current['price_data']['sale_price'];
            $cheapestPrice = $cheapest['price_data']['sale_price'];

            return $currentPrice < $cheapestPrice ? $current : $cheapest;
        });
    }

    private function findMostExpensiveOption(array $results): ?array
    {
        $validResults = array_filter($results, fn($r) => $r['success']);

        if (empty($validResults)) {
            return null;
        }

        return array_reduce($validResults, function ($expensive, $current) {
            if (!$expensive) {
                return $current;
            }

            $currentPrice = $current['price_data']['sale_price'];
            $expensivePrice = $expensive['price_data']['sale_price'];

            return $currentPrice > $expensivePrice ? $current : $expensive;
        });
    }

    private function calculatePriceRange(array $results): array
    {
        if (empty($results)) {
            return ['min' => 0, 'max' => 0, 'average' => 0];
        }

        $prices = array_map(fn($r) => $r['price_data']['sale_price'], $results);

        return [
            'min' => min($prices),
            'max' => max($prices),
            'average' => array_sum($prices) / count($prices),
        ];
    }

    private function generateQuoteId(): string
    {
        return 'QT-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -6));
    }

    private function calculateQuoteValidUntil(): DateTime
    {
        $validUntil = new DateTime();
        $validUntil->modify('+7 days');
        return $validUntil;
    }
}