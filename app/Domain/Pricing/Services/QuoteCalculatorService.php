<?php

namespace App\Domain\Pricing\Services;

use App\Domain\Pricing\Entities\Quote;
use App\Domain\Pricing\Entities\Product;
use App\Domain\Pricing\Entities\PriceCalculation;
use App\Domain\Pricing\Enums\ClientType;
use App\Domain\Pricing\ValueObjects\DateRange;
use App\Domain\Pricing\ValueObjects\Money;
use App\Domain\Pricing\ValueObjects\PaxConfiguration;
use DateTime;

class QuoteCalculatorService
{
    private PricingService $pricingService;

    public function __construct(PricingService $pricingService)
    {
        $this->pricingService = $pricingService;
    }

    public function createQuote(
        string $quoteId,
        int $clientId,
        ClientType $clientType,
        DateRange $dateRange,
        PaxConfiguration $paxConfiguration,
        array $products,
        DateTime $validUntil,
        array $metadata = []
    ): Quote {
        $quote = new Quote(
            $quoteId,
            $clientId,
            $clientType,
            $dateRange,
            $paxConfiguration,
            $validUntil,
            $metadata
        );

        foreach ($products as $productData) {
            $product = $productData['product'];
            $quantity = $productData['quantity'] ?? 1;

            $quote->addProduct($product, $quantity);
        }

        $this->calculateQuoteTotals($quote);

        return $quote;
    }

    public function recalculateQuote(Quote $quote): Quote
    {
        $this->calculateQuoteTotals($quote);
        return $quote;
    }

    public function addProductToQuote(Quote $quote, Product $product, int $quantity = 1): Quote
    {
        if ($quote->isConfirmed()) {
            throw new \InvalidArgumentException('Cannot modify confirmed quote');
        }

        $quote->addProduct($product, $quantity);
        $this->calculateQuoteTotals($quote);

        return $quote;
    }

    public function applyCustomDiscount(Quote $quote, Money $discountAmount, string $reason): Quote
    {
        if ($quote->isConfirmed()) {
            throw new \InvalidArgumentException('Cannot modify confirmed quote');
        }

        $quote->addMetadata('custom_discount', [
            'amount' => $discountAmount->getAmount(),
            'currency' => $discountAmount->getCurrency(),
            'reason' => $reason,
            'applied_at' => new DateTime(),
        ]);

        $this->calculateQuoteTotals($quote);

        return $quote;
    }

    public function compareQuotes(Quote $quote1, Quote $quote2): array
    {
        return [
            'quote1' => [
                'id' => $quote1->getId(),
                'total' => $quote1->getTotal()->getAmount(),
                'products_count' => count($quote1->getProducts()),
                'client_type' => $quote1->getClientType()->value,
            ],
            'quote2' => [
                'id' => $quote2->getId(),
                'total' => $quote2->getTotal()->getAmount(),
                'products_count' => count($quote2->getProducts()),
                'client_type' => $quote2->getClientType()->value,
            ],
            'difference' => [
                'amount' => $quote1->getTotal()->subtract($quote2->getTotal())->getAmount(),
                'percentage' => $this->calculatePercentageDifference($quote1->getTotal(), $quote2->getTotal()),
            ],
        ];
    }

    private function calculateQuoteTotals(Quote $quote): void
    {
        $calculations = $this->pricingService->calculateBulkPrice(
            $quote->getProducts(),
            $quote->getPaxConfiguration(),
            $quote->getDateRange(),
            $quote->getClientType()
        );

        $subtotal = new Money(0, 'USD');
        $discounts = new Money(0, 'USD');
        $taxes = new Money(0, 'USD');

        foreach ($calculations as $calculation) {
            $quote->addCalculation($calculation);

            $subtotal = $subtotal->add($calculation->getBasePrice());
            $discounts = $discounts->add($calculation->getDiscounts());
            $taxes = $taxes->add($calculation->getTaxes());
        }

        $customDiscount = $this->getCustomDiscountFromQuote($quote);
        if ($customDiscount) {
            $discounts = $discounts->add($customDiscount);
        }

        $total = $subtotal->subtract($discounts)->add($taxes);

        $quote->updateTotals($subtotal, $discounts, $taxes, $total);
    }

    private function getCustomDiscountFromQuote(Quote $quote): ?Money
    {
        $metadata = $quote->getMetadata();

        if (!isset($metadata['custom_discount'])) {
            return null;
        }

        $discountData = $metadata['custom_discount'];

        return new Money(
            $discountData['amount'],
            $discountData['currency']
        );
    }

    private function calculatePercentageDifference(Money $amount1, Money $amount2): float
    {
        if ($amount2->getAmount() == 0) {
            return $amount1->getAmount() > 0 ? 100 : 0;
        }

        $difference = $amount1->subtract($amount2)->getAmount();
        return round(($difference / $amount2->getAmount()) * 100, 2);
    }

    public function getQuoteSummary(Quote $quote): array
    {
        $products = [];
        foreach ($quote->getProducts() as $productData) {
            $product = $productData['product'];
            $quantity = $productData['quantity'];

            $products[] = [
                'name' => $product->getName(),
                'type' => $product->getType()->getLabel(),
                'quantity' => $quantity,
                'base_price' => $product->getBasePrice()->getAmount(),
            ];
        }

        return [
            'quote_id' => $quote->getId(),
            'client_id' => $quote->getClientId(),
            'client_type' => $quote->getClientType()->getLabel(),
            'date_range' => $quote->getDateRange()->__toString(),
            'pax_configuration' => $quote->getPaxConfiguration()->__toString(),
            'products' => $products,
            'totals' => [
                'subtotal' => $quote->getSubtotal()->getAmount(),
                'discounts' => $quote->getDiscounts()->getAmount(),
                'taxes' => $quote->getTaxes()->getAmount(),
                'total' => $quote->getTotal()->getAmount(),
                'currency' => $quote->getTotal()->getCurrency(),
            ],
            'status' => [
                'is_confirmed' => $quote->isConfirmed(),
                'is_expired' => $quote->isExpired(),
                'valid_until' => $quote->getValidUntil()->format('Y-m-d H:i:s'),
            ],
        ];
    }
}