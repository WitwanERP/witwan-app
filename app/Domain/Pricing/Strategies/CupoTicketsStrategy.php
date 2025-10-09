<?php

namespace App\Domain\Pricing\Strategies;

use App\Domain\Pricing\Entities\Product;
use App\Domain\Pricing\Entities\PriceCalculation;
use App\Domain\Pricing\ValueObjects\DateRange;
use App\Domain\Pricing\ValueObjects\Money;
use App\Domain\Pricing\ValueObjects\PaxConfiguration;
use App\Domain\Pricing\Repositories\QuotaRepositoryInterface;

class CupoTicketsStrategy implements PricingStrategyInterface
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
        $availableTickets = $this->getAvailableTickets($product);

        if (empty($availableTickets)) {
            throw new \InvalidArgumentException('No hay tickets disponibles para este producto');
        }

        $bestTicket = $this->selectBestTicketOption($availableTickets, $paxConfiguration);
        $totalPax = $paxConfiguration->getTotalPax();

        $baseCost = new Money(
            $bestTicket['costo'] + $bestTicket['cobertura'],
            $bestTicket['currency'] ?? 'USD'
        );

        $calculation = new PriceCalculation(
            $product,
            $paxConfiguration,
            $dateRange,
            $baseCost
        );

        $this->applyTicketSpecificAdjustments($calculation, $bestTicket, $totalPax);
        $this->applyAvailabilityConstraints($calculation, $bestTicket, $totalPax);

        $calculation->addAppliedRule('ctk_pricing_strategy', [
            'ticket_type' => $bestTicket['nombre'],
            'ticket_category' => $bestTicket['categoria'],
            'total_pax' => $totalPax,
            'available_quota' => $bestTicket['disponibles'],
        ]);

        return $calculation;
    }

    public function supports(string $productType): bool
    {
        return $productType === 'CTK';
    }

    public function getStrategyName(): string
    {
        return 'Cupo Tickets Pricing Strategy';
    }

    private function getAvailableTickets(Product $product): array
    {
        return $this->quotaRepository->findAvailableTickets($product->getId());
    }

    private function selectBestTicketOption(array $tickets, PaxConfiguration $paxConfiguration): array
    {
        $totalPax = $paxConfiguration->getTotalPax();

        // Filtrar tickets que pueden acomodar la cantidad de pasajeros
        $suitableTickets = array_filter($tickets, function($ticket) use ($totalPax) {
            return $ticket['capacidad'] >= $totalPax && $ticket['disponibles'] >= $totalPax;
        });

        if (empty($suitableTickets)) {
            throw new \InvalidArgumentException('No hay tickets con capacidad suficiente');
        }

        // Seleccionar el ticket con mejor precio
        usort($suitableTickets, function($a, $b) {
            $costA = $a['costo'] + $a['cobertura'];
            $costB = $b['costo'] + $b['cobertura'];
            return $costA <=> $costB;
        });

        return $suitableTickets[0];
    }

    private function applyTicketSpecificAdjustments(PriceCalculation $calculation, array $ticket, int $totalPax): void
    {
        // Aplicar markup específico del ticket si existe
        if (isset($ticket['utilidad']) && $ticket['utilidad'] > 0) {
            if ($ticket['utilidad'] > 1) {
                // Markup fijo
                $markupAdjustment = new Money($ticket['utilidad'], $ticket['currency'] ?? 'USD');
                $calculation->addAdjustment(
                    $markupAdjustment,
                    'Markup fijo del ticket'
                );
            } else {
                // Markup porcentual
                $markupAmount = $calculation->getBasePrice()->multiply($ticket['utilidad']);
                $calculation->addAdjustment(
                    $markupAmount,
                    'Markup porcentual del ticket'
                );
            }
        }

        // Multiplicar por cantidad de pasajeros si es necesario
        if ($totalPax > 1) {
            $additionalCost = $calculation->getBasePrice()->multiply($totalPax - 1);
            $calculation->addAdjustment(
                $additionalCost,
                "Costo adicional por {$totalPax} pasajeros"
            );
        }
    }

    private function applyAvailabilityConstraints(PriceCalculation $calculation, array $ticket, int $totalPax): void
    {
        $availableQuota = $ticket['disponibles'];

        if ($availableQuota < $totalPax) {
            throw new \InvalidArgumentException(
                "Cupo insuficiente. Disponible: {$availableQuota}, Requerido: {$totalPax}"
            );
        }

        // Aplicar recargo si queda poco cupo
        if ($availableQuota <= 5 && $availableQuota > $totalPax) {
            $scarcitySurcharge = $calculation->getBasePrice()->multiply(0.1);
            $calculation->addAdjustment(
                $scarcitySurcharge,
                'Recargo por disponibilidad limitada'
            );
        }
    }
}