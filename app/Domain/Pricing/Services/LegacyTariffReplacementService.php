<?php

namespace App\Domain\Pricing\Services;

use App\Domain\Pricing\Entities\Product;
use App\Domain\Pricing\Enums\ClientType;
use App\Domain\Pricing\Enums\ProductType;
use App\Domain\Pricing\Strategies\AsistenciaViajeroStrategy;
use App\Domain\Pricing\Strategies\CupoAereoStrategy;
use App\Domain\Pricing\Strategies\CupoTicketsStrategy;
use App\Domain\Pricing\Strategies\ExcursionPricingStrategy;
use App\Domain\Pricing\Strategies\HotelPricingStrategy;
use App\Domain\Pricing\Strategies\TransferPricingStrategy;
use App\Domain\Pricing\ValueObjects\DateRange;
use App\Domain\Pricing\ValueObjects\PaxConfiguration;
use App\Domain\Pricing\Repositories\ProductRepositoryInterface;
use App\Domain\Pricing\Repositories\TariffRepositoryInterface;
use App\Domain\Pricing\Repositories\QuotaRepositoryInterface;
use App\Domain\Pricing\Repositories\CommissionRepositoryInterface;

/**
 * Servicio que reemplaza el método tarifar() obsoleto
 * Utiliza el Domain/Pricing existente con las nuevas estrategias
 */
class LegacyTariffReplacementService
{
    private PricingService $pricingService;
    private ProductRepositoryInterface $productRepository;
    private TariffRepositoryInterface $tariffRepository;
    private QuotaRepositoryInterface $quotaRepository;
    private CommissionRepositoryInterface $commissionRepository;

    public function __construct(
        PricingService $pricingService,
        ProductRepositoryInterface $productRepository,
        TariffRepositoryInterface $tariffRepository,
        QuotaRepositoryInterface $quotaRepository,
        CommissionRepositoryInterface $commissionRepository
    ) {
        $this->pricingService = $pricingService;
        $this->productRepository = $productRepository;
        $this->tariffRepository = $tariffRepository;
        $this->quotaRepository = $quotaRepository;
        $this->commissionRepository = $commissionRepository;

        $this->registerStrategies();
    }

    /**
     * Método principal que reemplaza al tarifar() obsoleto
     */
    public function calculateTariff(array $params): array
    {
        try {
            $validatedParams = $this->validateAndNormalizeParams($params);

            $productData = $this->productRepository->findById($validatedParams['product_id']);
            if (!$productData) {
                return $this->errorResponse('Producto no encontrado');
            }

            // Por ahora retornamos un resultado básico hasta que se implemente completamente
            return $this->formatBasicResponse($productData, $validatedParams);

        } catch (\InvalidArgumentException $e) {
            return $this->errorResponse($e->getMessage());
        } catch (\Exception $e) {
            \Log::error('Error in tariff calculation', [
                'message' => $e->getMessage(),
                'params' => $params,
                'trace' => $e->getTraceAsString()
            ]);

            return $this->errorResponse('Error interno en el cálculo de tarifas');
        }
    }

    /**
     * Cálculo masivo para múltiples productos (reemplaza lógicas complejas del método original)
     */
    public function calculateBulkTariffs(array $productIds, array $params): array
    {
        $results = [];
        $validatedParams = $this->validateAndNormalizeParams($params);

        foreach ($productIds as $productId) {
            $productParams = array_merge($validatedParams, ['product_id' => $productId]);
            $results[] = $this->calculateTariff($productParams);
        }

        return [
            'ok' => 1,
            'bulk_results' => $results,
            'total_products' => count($productIds),
            'successful_calculations' => count(array_filter($results, fn($r) => $r['ok'] === 1)),
            'params' => $validatedParams
        ];
    }

    private function registerStrategies(): void
    {
        $this->pricingService->registerStrategy('HOT', new HotelPricingStrategy());
        $this->pricingService->registerStrategy('EXC', new ExcursionPricingStrategy());
        $this->pricingService->registerStrategy('TRN', new TransferPricingStrategy());
        $this->pricingService->registerStrategy('ASV', new AsistenciaViajeroStrategy($this->tariffRepository));
        $this->pricingService->registerStrategy('CTK', new CupoTicketsStrategy($this->quotaRepository));
        $this->pricingService->registerStrategy('CAE', new CupoAereoStrategy($this->quotaRepository));

        // Alias para otros tipos de productos que usan estrategias existentes
        $this->pricingService->registerStrategy('MOT', new HotelPricingStrategy());
        $this->pricingService->registerStrategy('PAQ', new HotelPricingStrategy());
        $this->pricingService->registerStrategy('TRL', new TransferPricingStrategy());
        $this->pricingService->registerStrategy('AEL', new HotelPricingStrategy());
        $this->pricingService->registerStrategy('MSC', new HotelPricingStrategy());
    }

    private function validateAndNormalizeParams(array $params): array
    {
        $required = ['product_id', 'check_in', 'check_out', 'adults'];

        foreach ($required as $field) {
            if (!isset($params[$field]) || empty($params[$field])) {
                throw new \InvalidArgumentException("Campo requerido faltante: {$field}");
            }
        }

        if ($params['adults'] < 1) {
            throw new \InvalidArgumentException('Debe haber al menos 1 adulto');
        }

        // Normalizar fechas
        if ($params['check_in'] === $params['check_out']) {
            $checkOut = new \DateTime($params['check_in']);
            $checkOut->add(new \DateInterval('P1D'));
            $params['check_out'] = $checkOut->format('Y-m-d');
        }

        return [
            'product_id' => (int) $params['product_id'],
            'check_in' => $params['check_in'],
            'check_out' => $params['check_out'],
            'adults' => (int) $params['adults'],
            'children' => (int) ($params['children'] ?? 0),
            'children_ages' => $params['children_ages'] ?? [],
            'infants' => (int) ($params['infants'] ?? 0),
            'client_id' => $params['client_id'] ?? null,
            'room_type' => $params['room_type'] ?? null,
            'markup' => $params['markup'] ?? null,
            'currency' => $params['currency'] ?? 'USD',
        ];
    }

    private function determineClientType(?int $clientId): ClientType
    {
        if (!$clientId) {
            return ClientType::RETAIL;
        }

        // Aquí iría la lógica para determinar el tipo de cliente
        // basado en la base de datos o configuración
        return ClientType::RETAIL; // Por defecto
    }

    private function formatBasicResponse(array $productData, array $params): array
    {
        // Calcular precio básico desde la base de datos
        $basePrice = $productData['base_price']['amount'] ?? 100;
        $currency = $productData['base_price']['currency'] ?? 'USD';
        $nights = $this->calculateNights($params['check_in'], $params['check_out']);

        // Cálculo básico: precio base * noches * adultos
        $totalPrice = $basePrice * $nights * $params['adults'];

        // Descuento por niños (30% menos)
        if ($params['children'] > 0) {
            $childPrice = $basePrice * $nights * $params['children'] * 0.7;
            $totalPrice += $childPrice;
        }

        return [
            'ok' => 1,
            'params' => $params,
            'producto' => [
                'id' => $productData['producto_id'],
                'nombre' => $productData['producto_nombre'],
                'tipo' => $productData['fk_tipoproducto_id'],
            ],
            'pricing' => [
                'base_price' => $basePrice,
                'total_price' => $totalPrice,
                'currency' => $currency,
                'adjustments' => [],
                'discounts' => [],
                'applied_rules' => ['basic_calculation'],
            ],
            'pax_breakdown' => [
                'adults' => $params['adults'],
                'children' => $params['children'],
                'infants' => $params['infants'] ?? 0,
            ],
            'date_range' => [
                'check_in' => $params['check_in'],
                'check_out' => $params['check_out'],
                'nights' => $nights,
            ],
        ];
    }

    private function errorResponse(string $message): array
    {
        return [
            'ok' => 0,
            'error' => $message,
            'resultado' => []
        ];
    }

    private function calculateNights(string $checkIn, string $checkOut): int
    {
        $start = new \DateTime($checkIn);
        $end = new \DateTime($checkOut);
        return $start->diff($end)->days;
    }
}