<?php

namespace App\Domain\Pricing\Strategies;

use App\Domain\Pricing\Entities\Product;
use App\Domain\Pricing\Entities\PriceCalculation;
use App\Domain\Pricing\Enums\ProductType;
use App\Domain\Pricing\ValueObjects\DateRange;
use App\Domain\Pricing\ValueObjects\Money;
use App\Domain\Pricing\ValueObjects\PaxConfiguration;
use Illuminate\Support\Facades\DB;

class ExcursionPricingStrategy implements PricingStrategyInterface
{
    public function calculate(
        Product $product,
        PaxConfiguration $paxConfiguration,
        DateRange $dateRange
    ): PriceCalculation {
        $productId = $product->getId();
        $excursionDate = $dateRange->getStartDate();

        // Buscar tarifas válidas para la fecha de excursión
        $validTariffs = $this->findValidTariffs($productId, $excursionDate);

        if (empty($validTariffs)) {
            throw new \Exception("No se encontraron tarifas válidas para la excursión en la fecha {$excursionDate->format('Y-m-d')}");
        }

        // Calcular precio base por PAX
        $baseCalculation = $this->calculateExcursionRate($validTariffs, $paxConfiguration);

        $calculation = new PriceCalculation(
            $product,
            $paxConfiguration,
            $dateRange,
            new Money($baseCalculation['total_amount'], $baseCalculation['currency'])
        );

        // Aplicar ajustes específicos de excursiones
        $this->applyExcursionSpecificAdjustments($calculation, $productId, $excursionDate);
        $this->applyPaxCalculations($calculation, $paxConfiguration);
        $this->applyDateSpecificRules($calculation, $excursionDate);
        $this->applyGroupDiscounts($calculation, $paxConfiguration);

        $calculation->addAppliedRule('excursion_pricing_strategy', [
            'excursion_date' => $excursionDate->format('Y-m-d'),
            'total_pax' => $paxConfiguration->getTotalPax(),
            'tariffs_used' => count($validTariffs),
            'calculation_method' => 'per_person'
        ]);

        return $calculation;
    }

    /**
     * Busca tarifas válidas para la fecha de excursión
     */
    private function findValidTariffs(int $productId, \DateTime $excursionDate): array
    {
        $dateStr = $excursionDate->format('Y-m-d');

        return DB::table('tarifa')
            ->join('vigencia', 'tarifa.fk_vigencia_id', '=', 'vigencia.vigencia_id')
            ->join('tarifario', 'tarifa.fk_tarifario_id', '=', 'tarifario.tarifario_id')
            ->where('vigencia.fk_producto_id', $productId)
            ->where('vigencia.vigencia_ini', '<=', $dateStr)
            ->where('vigencia.vigencia_fin', '>=', $dateStr)
            ->orderBy('vigencia.vigencia_prioridad', 'desc')
            ->orderBy('tarifa.fk_tipopax_id', 'asc')
            ->select([
                'tarifa.*',
                'vigencia.vigencia_ini',
                'vigencia.vigencia_fin',
                'vigencia.vigencia_prioridad',
                'vigencia.promocional',
                'vigencia.weekdays',
                'tarifario.cotizacion',
                'tarifario.fk_moneda_id'
            ])
            ->get()
            ->toArray();
    }

    /**
     * Calcula tarifa de excursión por persona
     */
    private function calculateExcursionRate(array $tariffs, PaxConfiguration $paxConfiguration): array
    {
        $adults = $paxConfiguration->getAdults();
        $children = $paxConfiguration->getChildren();
        $totalPax = $adults + $children;

        // Buscar tarifa aplicable por tipo de PAX
        $adultTariff = $this->findTariffByPaxType($tariffs, 'ADT', $totalPax);
        $childTariff = $this->findTariffByPaxType($tariffs, 'CHD', $totalPax);

        if (!$adultTariff) {
            throw new \Exception("No se encontró tarifa para adultos");
        }

        $currency = $adultTariff->fk_moneda_id ?? 'USD';

        // Calcular precio total
        $adultPrice = $adultTariff->costo * $adults;
        $childPrice = 0;
        $childTariffUsed = null;

        if ($children > 0) {
            if ($childTariff) {
                // Existe tarifa específica para niños - usarla
                $childPrice = $childTariff->costo * $children;
                $childTariffUsed = $childTariff->costo;
            } else {
                // NO existe tarifa CHD - los niños pagan como adultos
                $childPrice = $adultTariff->costo * $children;
                $childTariffUsed = $adultTariff->costo;
            }
        }

        $totalAmount = $adultPrice + $childPrice;

        // Aplicar cotización si existe
        $cotizacion = $adultTariff->cotizacion ?? 1;
        if ($cotizacion != 1) {
            $totalAmount *= $cotizacion;
        }

        return [
            'total_amount' => $totalAmount,
            'currency' => $currency,
            'adult_price' => $adultPrice,
            'child_price' => $childPrice,
            'adult_tariff' => $adultTariff->costo,
            'child_tariff' => $childTariffUsed,
            'has_child_tariff' => $childTariff !== null,
            'children_as_adults' => $childTariff === null && $children > 0
        ];
    }

    /**
     * Busca tarifa por tipo de PAX
     */
    private function findTariffByPaxType(array $tariffs, string $paxType, int $totalPax)
    {
        foreach ($tariffs as $tariff) {
            if ($tariff->fk_tipopax_id === $paxType &&
                $totalPax >= $tariff->min_pax &&
                $totalPax <= $tariff->max_pax) {
                return $tariff;
            }
        }

        // Si no encuentra tarifa específica, buscar la primera que permita la cantidad de PAX
        foreach ($tariffs as $tariff) {
            if ($totalPax >= $tariff->min_pax && $totalPax <= $tariff->max_pax) {
                return $tariff;
            }
        }

        return null;
    }

    /**
     * Aplica ajustes específicos de excursiones
     */
    private function applyExcursionSpecificAdjustments(PriceCalculation $calculation, int $productId, \DateTime $excursionDate): void
    {
        // Buscar información específica de la excursión
        $excursionInfo = DB::table('excursion')
            ->where('fk_producto_id', $productId)
            ->first();

        if ($excursionInfo) {
            // Verificar horario de pickup
            $this->validatePickupTime($excursionInfo, $excursionDate);

            // Aplicar suplementos por origen/destino si aplica
            $this->applyOriginDestinationSupplements($calculation, $excursionInfo);
        }
    }

    /**
     * Valida horario de pickup
     */
    private function validatePickupTime($excursionInfo, \DateTime $excursionDate): void
    {
        // Validación básica - se puede extender
        if (empty($excursionInfo->horario_pickup)) {
            throw new \Exception("La excursión no tiene horario de pickup definido");
        }
    }

    /**
     * Aplica suplementos por origen/destino
     */
    private function applyOriginDestinationSupplements(PriceCalculation $calculation, $excursionInfo): void
    {
        // Si la excursión tiene origen específico, podría aplicar suplemento
        if ($excursionInfo->origen) {
            // Buscar si hay costos adicionales por origen
            $originSupplement = DB::table('producto_origen_suplemento')
                ->where('fk_producto_id', $excursionInfo->fk_producto_id)
                ->where('fk_ciudad_origen', $excursionInfo->origen)
                ->first();

            if ($originSupplement && $originSupplement->suplemento > 0) {
                $supplement = new Money($originSupplement->suplemento, 'USD');
                $calculation->addAdjustment(
                    $supplement,
                    "Suplemento por zona de origen"
                );
            }
        }
    }

    /**
     * Aplica cálculos específicos por PAX
     */
    private function applyPaxCalculations(PriceCalculation $calculation, PaxConfiguration $paxConfiguration): void
    {
        $infants = $paxConfiguration->getInfants();

        // Los infantes no pagan en excursiones
        if ($infants > 0) {
            $calculation->addAppliedRule('infants_free', [
                'infants_count' => $infants,
                'message' => "Infantes (0-2 años) no pagan en excursiones"
            ]);
        }
    }

    /**
     * Aplica reglas específicas por fecha
     */
    private function applyDateSpecificRules(PriceCalculation $calculation, \DateTime $excursionDate): void
    {
        $dayOfWeek = (int) $excursionDate->format('N'); // 1=Monday, 7=Sunday
        $isWeekend = in_array($dayOfWeek, [6, 7]);

        // Suplemento fin de semana
        if ($isWeekend) {
            $weekendSupplement = $calculation->getBasePrice()->multiply(0.15);
            $calculation->addAdjustment(
                $weekendSupplement,
                "Suplemento fin de semana (15%)"
            );
        }

        // Verificar si es día feriado
        $isHoliday = $this->isHoliday($excursionDate);
        if ($isHoliday) {
            $holidaySupplement = $calculation->getBasePrice()->multiply(0.25);
            $calculation->addAdjustment(
                $holidaySupplement,
                "Suplemento día feriado (25%)"
            );
        }
    }

    /**
     * Aplica descuentos por grupo
     */
    private function applyGroupDiscounts(PriceCalculation $calculation, PaxConfiguration $paxConfiguration): void
    {
        $totalPax = $paxConfiguration->getTotalPax();

        if ($totalPax >= 15) {
            $groupDiscount = $calculation->getBasePrice()->multiply(0.15);
            $calculation->addDiscount(
                $groupDiscount,
                "Descuento grupo grande (15+ personas, 15%)"
            );
        } elseif ($totalPax >= 10) {
            $groupDiscount = $calculation->getBasePrice()->multiply(0.10);
            $calculation->addDiscount(
                $groupDiscount,
                "Descuento grupo mediano (10+ personas, 10%)"
            );
        } elseif ($totalPax >= 6) {
            $groupDiscount = $calculation->getBasePrice()->multiply(0.05);
            $calculation->addDiscount(
                $groupDiscount,
                "Descuento grupo pequeño (6+ personas, 5%)"
            );
        }
    }

    /**
     * Verifica si la fecha es feriado
     */
    private function isHoliday(\DateTime $date): bool
    {
        $dateStr = $date->format('Y-m-d');

        $holiday = DB::table('feriado')
            ->where('fecha', $dateStr)
            ->where('activo', 1)
            ->first();

        return $holiday !== null;
    }

    public function supports(string $productType): bool
    {
        return $productType === 'EXC';
    }

    public function getStrategyName(): string
    {
        return 'Excursion Pricing Strategy';
    }
}