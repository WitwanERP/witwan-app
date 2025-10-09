<?php

namespace App\Domain\Pricing\Strategies;

use App\Domain\Pricing\Entities\Product;
use App\Domain\Pricing\Entities\PriceCalculation;
use App\Domain\Pricing\Enums\ProductType;
use App\Domain\Pricing\ValueObjects\DateRange;
use App\Domain\Pricing\ValueObjects\Money;
use App\Domain\Pricing\ValueObjects\PaxConfiguration;
use Illuminate\Support\Facades\DB;

class HotelPricingStrategy implements PricingStrategyInterface
{
    public function calculate(
        Product $product,
        PaxConfiguration $paxConfiguration,
        DateRange $dateRange
    ): PriceCalculation {
        $nights = $dateRange->getDurationInDays();
        $productId = $product->getId();

        // Buscar tarifas válidas para el rango de fechas
        $validTariffs = $this->findValidTariffs($productId, $dateRange);

        if (empty($validTariffs)) {
            throw new \Exception("No se encontraron tarifas válidas para el producto {$productId} en las fechas solicitadas");
        }

        // Calcular precio base por noche según vigencias
        $baseCalculation = $this->calculateNightlyRates($validTariffs, $dateRange, $paxConfiguration);

        $calculation = new PriceCalculation(
            $product,
            $paxConfiguration,
            $dateRange,
            new Money($baseCalculation['total_amount'], $baseCalculation['currency'])
        );

        // Aplicar ajustes específicos de hoteles
        $this->applyHotelSpecificAdjustments($calculation, $productId, $paxConfiguration, $dateRange);
        $this->applyPaxAdjustments($calculation, $paxConfiguration, $baseCalculation);
        $this->applySeasonalPriority($calculation, $validTariffs, $dateRange);
        $this->applyRoomOccupancyRules($calculation, $paxConfiguration);

        $calculation->addAppliedRule('hotel_pricing_strategy', [
            'nights' => $nights,
            'tariffs_used' => count($validTariffs),
            'total_pax' => $paxConfiguration->getTotalPax(),
            'calculation_method' => 'vigencia_based'
        ]);

        return $calculation;
    }

    /**
     * Busca tarifas válidas para el producto en el rango de fechas
     */
    private function findValidTariffs(int $productId, DateRange $dateRange): array
    {
        return DB::table('tarifa')
            ->join('vigencia', 'tarifa.fk_vigencia_id', '=', 'vigencia.vigencia_id')
            ->join('tarifario', 'tarifa.fk_tarifario_id', '=', 'tarifario.tarifario_id')
            ->where('vigencia.fk_producto_id', $productId)
            ->where('vigencia.vigencia_ini', '<=', $dateRange->getEndDate()->format('Y-m-d'))
            ->where('vigencia.vigencia_fin', '>=', $dateRange->getStartDate()->format('Y-m-d'))
            ->orderBy('vigencia.vigencia_prioridad', 'desc')
            ->orderBy('tarifa.fk_tipopax_id', 'asc')
            ->select([
                'tarifa.*',
                'vigencia.vigencia_ini',
                'vigencia.vigencia_fin',
                'vigencia.vigencia_prioridad',
                'vigencia.promocional',
                'vigencia.noches_minimas',
                'tarifario.cotizacion',
                'tarifario.fk_moneda_id'
            ])
            ->get()
            ->toArray();
    }

    /**
     * Calcula precios por noche según las vigencias
     */
    private function calculateNightlyRates(array $tariffs, DateRange $dateRange, PaxConfiguration $paxConfiguration): array
    {
        $totalAmount = 0;
        $currency = $tariffs[0]->fk_moneda_id ?? 'USD';
        $nights = $dateRange->getDurationInDays();

        // Verificar noches mínimas
        $minNights = $this->getMinimumNights($tariffs);
        if ($nights < $minNights) {
            throw new \Exception("El producto requiere mínimo {$minNights} noches");
        }

        // Calcular por cada noche usando las vigencias correspondientes
        $currentDate = clone $dateRange->getStartDate();

        for ($i = 0; $i < $nights; $i++) {
            $nightlyRate = $this->calculateSingleNightRate($tariffs, $currentDate, $paxConfiguration);
            $totalAmount += $nightlyRate;
            $currentDate->add(new \DateInterval('P1D'));
        }

        return [
            'total_amount' => $totalAmount,
            'currency' => $currency,
            'nightly_breakdown' => $this->getNightlyBreakdown($tariffs, $dateRange, $paxConfiguration)
        ];
    }

    /**
     * Calcula la tarifa para una noche específica
     */
    private function calculateSingleNightRate(array $tariffs, \DateTime $date, PaxConfiguration $paxConfiguration): float
    {
        $dateStr = $date->format('Y-m-d');
        $adults = $paxConfiguration->getAdults();
        $children = $paxConfiguration->getChildren();
        $totalPax = $adults + $children;

        // Buscar tarifas aplicables para esta fecha por tipo de PAX
        $adultTariff = $this->findBestTariffForDate($tariffs, $dateStr, 'ADT', $totalPax);
        $childTariff = $this->findBestTariffForDate($tariffs, $dateStr, 'CHD', $totalPax);

        if (!$adultTariff) {
            throw new \Exception("No se encontró tarifa para adultos en la fecha {$dateStr}");
        }

        // Calcular precio total para la noche
        $nightRate = 0;

        // Precio adultos
        if ($adults > 0) {
            $adultRate = $this->calculatePaxBasedRate($adultTariff,
                new PaxConfiguration($adults, 0, [], 0)
            );
            $nightRate += $adultRate;
        }

        // Precio niños
        if ($children > 0) {
            if ($childTariff) {
                // Existe tarifa específica para niños
                $childRate = $this->calculatePaxBasedRate($childTariff,
                    new PaxConfiguration(0, $children, [], 0)
                );
            } else {
                // No hay tarifa CHD - los niños pagan como adultos
                $childRate = $this->calculatePaxBasedRate($adultTariff,
                    new PaxConfiguration($children, 0, [], 0)
                );
            }
            $nightRate += $childRate;
        }

        // Aplicar cotización si existe
        $cotizacion = $adultTariff->cotizacion ?? 1;
        if ($cotizacion != 1) {
            $nightRate *= $cotizacion;
        }

        return $nightRate;
    }

    /**
     * Busca la mejor tarifa para una fecha y tipo de PAX específico
     */
    private function findBestTariffForDate(array $tariffs, string $dateStr, string $paxType, int $totalPax)
    {
        $applicableTariffs = [];

        // Filtrar tarifas aplicables para esta fecha y tipo de PAX
        foreach ($tariffs as $tariff) {
            if ($dateStr >= $tariff->vigencia_ini &&
                $dateStr <= $tariff->vigencia_fin &&
                $tariff->fk_tipopax_id === $paxType &&
                $totalPax >= $tariff->min_pax &&
                $totalPax <= $tariff->max_pax) {
                $applicableTariffs[] = $tariff;
            }
        }

        if (empty($applicableTariffs)) {
            return null;
        }

        // Ordenar por prioridad y retornar la mejor
        usort($applicableTariffs, function($a, $b) {
            return $b->vigencia_prioridad <=> $a->vigencia_prioridad;
        });

        return $applicableTariffs[0];
    }

    /**
     * Calcula tarifa según configuración de pasajeros
     */
    private function calculatePaxBasedRate($tariff, PaxConfiguration $paxConfiguration): float
    {
        $adults = $paxConfiguration->getAdults();
        $children = $paxConfiguration->getChildren();
        $totalPax = $adults + $children;

        // Verificar límites de PAX
        if ($totalPax < $tariff->min_pax || $totalPax > $tariff->max_pax) {
            // Buscar tarifa alternativa o aplicar suplemento
            return $this->calculateOutOfRangeRate($tariff, $totalPax);
        }

        $baseAmount = $tariff->costo;

        // Para hoteles, necesitamos verificar si hay tarifas específicas por tipo de PAX
        if ($tariff->fk_tipopax_id === 'CHD' && $children > 0) {
            // Esta es una tarifa específica para niños
            return $baseAmount * $children;
        } elseif ($tariff->fk_tipopax_id === 'ADT') {
            // Tarifa de adultos - puede aplicar a niños si no hay CHD
            return $baseAmount * $totalPax; // Los niños pagan como adultos si no hay tarifa CHD
        }

        // Aplicar lógica según tipo de habitación
        switch ($tariff->fk_tipopax_id) {
            case 'SGL': // Single
                return $baseAmount * $totalPax; // Habitación single para todos

            case 'DBL': // Doble
                $roomsNeeded = ceil($totalPax / 2);
                return $baseAmount * $roomsNeeded;

            case 'TPL': // Triple
                $roomsNeeded = ceil($totalPax / 3);
                return $baseAmount * $roomsNeeded;

            case 'QUAD': // Cuádruple
                $roomsNeeded = ceil($totalPax / 4);
                return $baseAmount * $roomsNeeded;

            default: // Por persona
                return $baseAmount * $totalPax;
        }
    }

    /**
     * Maneja casos donde PAX está fuera del rango
     */
    private function calculateOutOfRangeRate($tariff, int $totalPax): float
    {
        if ($totalPax < $tariff->min_pax) {
            // Aplicar tarifa mínima con posible suplemento
            return $tariff->costo * ($tariff->min_pax / max(1, $totalPax));
        } else {
            // Supera máximo - calcular proporcional
            return $tariff->costo * ($totalPax / $tariff->max_pax);
        }
    }

    /**
     * Obtiene las noches mínimas requeridas
     */
    private function getMinimumNights(array $tariffs): int
    {
        $minNights = 1;
        foreach ($tariffs as $tariff) {
            if ($tariff->noches_minimas > $minNights) {
                $minNights = $tariff->noches_minimas;
            }
        }
        return $minNights;
    }

    /**
     * Obtiene desglose detallado por noche
     */
    private function getNightlyBreakdown(array $tariffs, DateRange $dateRange, PaxConfiguration $paxConfiguration): array
    {
        $breakdown = [];
        $currentDate = clone $dateRange->getStartDate();
        $nights = $dateRange->getDurationInDays();

        for ($i = 0; $i < $nights; $i++) {
            $dateStr = $currentDate->format('Y-m-d');
            $rate = $this->calculateSingleNightRate($tariffs, $currentDate, $paxConfiguration);

            $breakdown[] = [
                'date' => $dateStr,
                'rate' => $rate,
                'day_of_week' => $currentDate->format('N'), // 1=Monday, 7=Sunday
                'is_weekend' => in_array($currentDate->format('N'), [6, 7])
            ];

            $currentDate->add(new \DateInterval('P1D'));
        }

        return $breakdown;
    }

    /**
     * Aplica ajustes específicos de hoteles (alojamiento)
     */
    private function applyHotelSpecificAdjustments(PriceCalculation $calculation, int $productId, PaxConfiguration $paxConfiguration, DateRange $dateRange): void
    {
        // Buscar información específica del alojamiento
        $hotelInfo = DB::table('alojamiento')
            ->where('fk_producto_id', $productId)
            ->first();

        if ($hotelInfo) {
            // Aplicar políticas de edad
            $this->applyAgePolicy($calculation, $hotelInfo, $paxConfiguration);

            // Verificar horarios de check-in/check-out
            $this->validateCheckInOut($hotelInfo, $dateRange);

            // Aplicar suplementos por categoría de hotel
            $this->applyHotelCategorySupplements($calculation, $hotelInfo);
        }
    }

    /**
     * Aplica políticas de edad específicas del hotel
     */
    private function applyAgePolicy(PriceCalculation $calculation, $hotelInfo, PaxConfiguration $paxConfiguration): void
    {
        $childrenAges = $paxConfiguration->getChildrenAges();
        $maxChildAge = $hotelInfo->edad_menores ?? 12;

        foreach ($childrenAges as $age) {
            if ($age > $maxChildAge) {
                // Niño debe pagar como adulto
                $adultRate = $calculation->getBasePrice()->multiply(0.15); // 15% extra por niño como adulto
                $calculation->addAdjustment(
                    $adultRate,
                    "Menor de {$age} años cobra como adulto (límite: {$maxChildAge} años)"
                );
            }
        }
    }

    /**
     * Valida horarios de check-in/check-out
     */
    private function validateCheckInOut($hotelInfo, DateRange $dateRange): void
    {
        // Aquí se podrían agregar validaciones de horarios si es necesario
        // Por ahora solo verificamos que existan las fechas
        if (!$dateRange->getStartDate() || !$dateRange->getEndDate()) {
            throw new \Exception("Fechas de check-in y check-out son requeridas");
        }
    }

    /**
     * Aplica suplementos por categoría de hotel
     */
    private function applyHotelCategorySupplements(PriceCalculation $calculation, $hotelInfo): void
    {
        // Buscar categoría del hotel y aplicar suplementos si corresponde
        if ($hotelInfo->fk_hotelcategoria_id) {
            $category = DB::table('hotelcategoria')
                ->where('hotelcategoria_id', $hotelInfo->fk_hotelcategoria_id)
                ->first();

            if ($category && isset($category->premium_supplement) && $category->premium_supplement > 0) {
                $supplement = $calculation->getBasePrice()->multiply($category->premium_supplement / 100);
                $calculation->addAdjustment(
                    $supplement,
                    "Suplemento categoría {$category->hotelcategoria_nombre} ({$category->premium_supplement}%)"
                );
            }
        }
    }

    /**
     * Aplica ajustes de PAX (ya integrados en el cálculo base, pero aquí van extras)
     */
    private function applyPaxAdjustments(PriceCalculation $calculation, PaxConfiguration $paxConfiguration, array $baseCalculation): void
    {
        $infants = $paxConfiguration->getInfants();

        // Los infantes no pagan (solo agregar info)
        if ($infants > 0) {
            $calculation->addAppliedRule('infants_free', [
                'infants_count' => $infants,
                'message' => "Infantes viajan gratis"
            ]);
        }
    }

    /**
     * Aplica prioridad estacional basada en vigencias
     */
    private function applySeasonalPriority(PriceCalculation $calculation, array $tariffs, DateRange $dateRange): void
    {
        $promotionalNights = 0;
        foreach ($tariffs as $tariff) {
            if ($tariff->promocional) {
                $promotionalNights++;
            }
        }

        if ($promotionalNights > 0) {
            $discountPercentage = min(20, $promotionalNights * 2); // Max 20% descuento
            $discount = $calculation->getBasePrice()->multiply($discountPercentage / 100);

            $calculation->addDiscount(
                $discount,
                "Descuento promocional: {$promotionalNights} noches en promoción ({$discountPercentage}%)"
            );
        }
    }

    /**
     * Aplica reglas de ocupación de habitaciones
     */
    private function applyRoomOccupancyRules(PriceCalculation $calculation, PaxConfiguration $paxConfiguration): void
    {
        $totalPax = $paxConfiguration->getTotalPax();
        $adults = $paxConfiguration->getAdults();

        // Suplemento single (1 adulto solo)
        if ($totalPax === 1 && $adults === 1) {
            $singleSupplement = $calculation->getBasePrice()->multiply(0.25); // 25% suplemento
            $calculation->addAdjustment(
                $singleSupplement,
                "Suplemento habitación individual (25%)"
            );
        }

        // Descuento por grupo grande
        if ($totalPax >= 6) {
            $groupDiscount = $calculation->getBasePrice()->multiply(0.08); // 8% descuento
            $calculation->addDiscount(
                $groupDiscount,
                "Descuento por grupo grande (6+ personas, 8%)"
            );
        }
    }

    public function supports(string $productType): bool
    {
        return in_array($productType, ['HOT', 'MOT', 'PAQ', 'AEL', 'MSC']);
    }

    public function getStrategyName(): string
    {
        return 'Hotel & Accommodation Pricing Strategy';
    }
}