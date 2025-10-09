<?php

namespace App\Domain\Pricing\Services;

use App\Domain\Pricing\Enums\ProductType;
use App\Domain\Pricing\Repositories\ProductRepositoryInterface;
use App\Domain\Pricing\ValueObjects\DateRange;
use App\Domain\Pricing\ValueObjects\PaxConfiguration;

class ProductSearchService
{
    private ProductRepositoryInterface $productRepository;
    private AvailabilityChecker $availabilityChecker;
    private PricingContextService $pricingContextService;
    private PricingService $pricingService;

    public function __construct(
        ProductRepositoryInterface $productRepository,
        AvailabilityChecker $availabilityChecker,
        PricingContextService $pricingContextService,
        PricingService $pricingService
    ) {
        $this->productRepository = $productRepository;
        $this->availabilityChecker = $availabilityChecker;
        $this->pricingContextService = $pricingContextService;
        $this->pricingService = $pricingService;
    }

    /**
     * Búsqueda de productos con pricing integrado
     */
    public function searchWithPricing(array $searchParams): array
    {
        // Validar parámetros de búsqueda
        $validatedParams = $this->validateSearchParams($searchParams);

        // Buscar productos base
        $products = $this->findProducts($validatedParams);

        if (empty($products)) {
            return $this->emptySearchResult($validatedParams);
        }

        // Calcular pricing para cada producto si está habilitado
        $resultsWithPricing = [];
        if ($validatedParams['include_pricing']) {
            $resultsWithPricing = $this->calculatePricingForProducts(
                $products,
                $validatedParams
            );
        } else {
            $resultsWithPricing = array_map(function($product) {
                return [
                    'product' => $product,
                    'pricing' => null,
                    'availability' => null
                ];
            }, $products);
        }

        // Aplicar filtros de precio si existen
        if (isset($validatedParams['filters']['price_range'])) {
            $resultsWithPricing = $this->filterByPriceRange(
                $resultsWithPricing,
                $validatedParams['filters']['price_range']
            );
        }

        // Ordenar resultados
        $sortedResults = $this->sortResults(
            $resultsWithPricing,
            $validatedParams['filters']['sort_by'] ?? 'price_asc'
        );

        // Paginar
        $paginatedResults = $this->paginateResults(
            $sortedResults,
            $validatedParams['pagination']
        );

        return $this->formatSearchResponse($paginatedResults, $validatedParams);
    }

    /**
     * Búsqueda por ID específico con pricing
     */
    public function searchByIdWithPricing(array $searchParams): array
    {
        $validatedParams = $this->validateSearchByIdParams($searchParams);

        $product = $this->productRepository->findById($validatedParams['product_id']);

        if (!$product) {
            return $this->notFoundResponse($validatedParams['product_id']);
        }

        $result = [
            'product' => $product,
            'pricing' => null,
            'availability' => null
        ];

        // Calcular pricing si está solicitado
        if ($validatedParams['include_pricing']) {
            $paxConfig = new PaxConfiguration(
                $validatedParams['adults'],
                $validatedParams['children'] ?? 0,
                $validatedParams['children_ages'] ?? [],
                $validatedParams['infants'] ?? 0
            );

            $dateRange = new DateRange(
                new \DateTime($validatedParams['check_in']),
                new \DateTime($validatedParams['check_out'])
            );

            $result['pricing'] = $this->calculateProductPricing(
                $product,
                $paxConfig,
                $dateRange,
                $validatedParams['client_id'] ?? null,
                $validatedParams['pricing_level'] ?? 'full'
            );
        }

        // Verificar disponibilidad si está solicitado
        if ($validatedParams['check_availability']) {
            $result['availability'] = $this->availabilityChecker->checkProductAvailability(
                $validatedParams['product_id'],
                new DateRange(
                    new \DateTime($validatedParams['check_in']),
                    new \DateTime($validatedParams['check_out'])
                ),
                new PaxConfiguration(
                    $validatedParams['adults'],
                    $validatedParams['children'] ?? 0,
                    $validatedParams['children_ages'] ?? [],
                    $validatedParams['infants'] ?? 0
                )
            );
        }

        return $this->formatSingleProductResponse($result, $validatedParams);
    }

    private function findProducts(array $params): array
    {
        $filters = [];

        // Filtro por tipo de producto
        if (isset($params['product_type'])) {
            $filters['type'] = $params['product_type'];
        }

        // Filtro por destino
        if (isset($params['destination'])) {
            if (is_numeric($params['destination'])) {
                $filters['destination'] = $params['destination'];
            } else {
                // Buscar ciudad por nombre
                $cityId = $this->findCityIdByName($params['destination']);
                if ($cityId) {
                    $filters['destination'] = $cityId;
                }
            }
        }

        // Filtros adicionales
        if (isset($params['filters']['provider_id'])) {
            $filters['provider'] = $params['filters']['provider_id'];
        }

        // Solo productos activos
        $filters['active'] = true;

        return $this->productRepository->findByFilters($filters);
    }

    private function calculatePricingForProducts(array $products, array $params): array
    {
        $paxConfig = new PaxConfiguration(
            $params['adults'],
            $params['children'] ?? 0,
            $params['children_ages'] ?? [],
            $params['infants'] ?? 0
        );

        $dateRange = new DateRange(
            new \DateTime($params['check_in']),
            new \DateTime($params['check_out'])
        );

        $results = [];

        foreach ($products as $product) {
            $result = [
                'product' => $product,
                'pricing' => null,
                'availability' => null,
                'sort_key' => PHP_FLOAT_MAX // Por defecto al final si no tiene precio
            ];

            try {
                // Verificar disponibilidad si está configurado
                if ($params['filters']['available_only'] ?? false) {
                    $availability = $this->availabilityChecker->checkProductAvailability(
                        $product['producto_id'],
                        $dateRange,
                        $paxConfig
                    );

                    $result['availability'] = $availability;

                    if (!$availability['available']) {
                        continue; // Skip producto no disponible
                    }
                }

                // Calcular pricing
                $pricing = $this->calculateProductPricing(
                    $product,
                    $paxConfig,
                    $dateRange,
                    $params['client_id'] ?? null,
                    $params['pricing_level'] ?? 'full'
                );

                $result['pricing'] = $pricing;
                $result['sort_key'] = $pricing['total_price'] ?? PHP_FLOAT_MAX;

            } catch (\Exception $e) {
                \Log::warning('Error calculating pricing for product', [
                    'product_id' => $product['producto_id'],
                    'error' => $e->getMessage()
                ]);

                // En caso de error, mantener el producto pero sin pricing
                $result['pricing'] = [
                    'status' => 'error',
                    'error' => 'No se pudo calcular el precio'
                ];
            }

            $results[] = $result;
        }

        return $results;
    }

    private function calculateProductPricing(
        array $productData,
        PaxConfiguration $paxConfig,
        DateRange $dateRange,
        ?int $clientId,
        string $level = 'full'
    ): array {
        // Determinar contexto de pricing (markup y moneda)
        [$currency, $markup, $pricingRules] = $this->pricingContextService->determinePricingContext(
            $clientId,
            $productData['producto_id']
        );

        // Crear entidad de producto para el cálculo
        $product = $this->createProductEntity($productData);

        // Determinar tipo de cliente
        $clientType = $this->pricingContextService->determineClientType($clientId);

        // Calcular precio base usando las estrategias existentes
        $baseCalculation = $this->pricingService->calculatePrice(
            $product,
            $paxConfig,
            $dateRange,
            $clientType
        );

        // Aplicar markup específico
        $basePriceAmount = $baseCalculation->getTotalPrice()->getAmount();
        $finalPrice = $basePriceAmount * $markup;

        // Convertir moneda si es necesario (placeholder - implementar converter)
        $displayCurrency = $currency;
        $exchangeRate = 1.0; // TODO: Implementar conversión real

        return [
            'status' => 'available',
            'total_price' => $finalPrice,
            'currency' => $displayCurrency,
            'markup_applied' => $markup,
            'base_price_before_markup' => $basePriceAmount,
            'exchange_rate' => $exchangeRate,
            'breakdown' => [
                'base_calculation' => $basePriceAmount,
                'markup' => $finalPrice - $basePriceAmount,
                'taxes' => 0, // TODO: Implementar cálculo de impuestos
                'total' => $finalPrice
            ],
            'pricing_rules' => $pricingRules,
            'valid_until' => now()->addHours(2)->toISOString(), // 2 horas de validez
            'applied_rules' => $baseCalculation->getAppliedRules() ?? []
        ];
    }

    private function createProductEntity(array $productData): \App\Domain\Pricing\Entities\Product
    {
        // TODO: Implementar conversión de array a entidad Product
        // Por ahora, crear una implementación básica
        return new \App\Domain\Pricing\Entities\Product(
            $productData['producto_id'],
            $productData['producto_nombre'],
            ProductType::from($productData['fk_tipoproducto_id']),
            $productData['base_price'] ?? ['amount' => 0, 'currency' => 'USD']
        );
    }

    private function filterByPriceRange(array $results, array $priceRange): array
    {
        $min = $priceRange['min'] ?? 0;
        $max = $priceRange['max'] ?? PHP_FLOAT_MAX;
        $includeMarkup = $priceRange['include_markup'] ?? true;

        return array_filter($results, function($result) use ($min, $max, $includeMarkup) {
            if (!$result['pricing'] || $result['pricing']['status'] !== 'available') {
                return false;
            }

            $price = $includeMarkup
                ? $result['pricing']['total_price']
                : $result['pricing']['base_price_before_markup'];

            return $price >= $min && $price <= $max;
        });
    }

    private function sortResults(array $results, string $sortBy): array
    {
        usort($results, function($a, $b) use ($sortBy) {
            return match($sortBy) {
                'price_asc' => $a['sort_key'] <=> $b['sort_key'],
                'price_desc' => $b['sort_key'] <=> $a['sort_key'],
                'name' => strcmp($a['product']['producto_nombre'], $b['product']['producto_nombre']),
                'rating' => ($b['product']['rating'] ?? 0) <=> ($a['product']['rating'] ?? 0),
                default => $a['sort_key'] <=> $b['sort_key']
            };
        });

        return $results;
    }

    private function paginateResults(array $results, array $pagination): array
    {
        $page = $pagination['page'] ?? 1;
        $perPage = $pagination['per_page'] ?? 20;

        $total = count($results);
        $offset = ($page - 1) * $perPage;

        return [
            'data' => array_slice($results, $offset, $perPage),
            'pagination' => [
                'total' => $total,
                'page' => $page,
                'per_page' => $perPage,
                'pages' => ceil($total / $perPage)
            ]
        ];
    }

    private function formatSearchResponse(array $paginatedResults, array $params): array
    {
        $data = $paginatedResults['data'];
        $pagination = $paginatedResults['pagination'];

        // Calcular estadísticas de precios
        $prices = array_filter(array_map(function($item) {
            return $item['pricing']['total_price'] ?? null;
        }, $data));

        $priceStats = empty($prices) ? null : [
            'min' => min($prices),
            'max' => max($prices),
            'average' => array_sum($prices) / count($prices)
        ];

        return [
            'ok' => 1,
            'search_params' => $params,
            'pricing_context' => $this->pricingContextService->getPricingContext($params['client_id'] ?? null),
            'products' => array_map([$this, 'formatProductForResponse'], $data),
            'summary' => [
                'total_found' => $pagination['total'],
                'with_pricing' => count(array_filter($data, fn($item) => $item['pricing'] && $item['pricing']['status'] === 'available')),
                'price_range' => $priceStats
            ],
            'pagination' => $pagination
        ];
    }

    private function formatSingleProductResponse(array $result, array $params): array
    {
        return [
            'ok' => 1,
            'search_type' => 'by_id',
            'product' => $this->formatDetailedProduct($result),
            'search_params' => $params
        ];
    }

    private function formatProductForResponse(array $result): array
    {
        $product = $result['product'];
        $pricing = $result['pricing'];
        $availability = $result['availability'];

        return [
            'product_id' => $product['producto_id'],
            'name' => $product['producto_nombre'],
            'type' => $product['fk_tipoproducto_id'],
            'provider' => [
                'id' => $product['fk_proveedor_id'] ?? null,
                'name' => $product['proveedor_nombre'] ?? null
            ],
            'destination' => [
                'city_id' => $product['fk_ciudad_id'] ?? null,
                'city' => $product['ciudad_nombre'] ?? null
            ],
            'rating' => $product['rating'] ?? null,
            'thumbnail' => $product['thumbnail'] ?? null,
            'pricing' => $pricing,
            'availability' => $availability
        ];
    }

    private function formatDetailedProduct(array $result): array
    {
        $basic = $this->formatProductForResponse($result);

        // Agregar información detallada
        $basic['description'] = $result['product']['producto_descripcion'] ?? '';
        $basic['features'] = []; // TODO: Implementar features
        $basic['gallery'] = []; // TODO: Implementar galería

        return $basic;
    }

    private function validateSearchParams(array $params): array
    {
        $required = ['check_in', 'check_out', 'adults'];

        foreach ($required as $field) {
            if (!isset($params[$field]) || empty($params[$field])) {
                throw new \InvalidArgumentException("Campo requerido faltante: {$field}");
            }
        }

        return array_merge([
            'children' => 0,
            'children_ages' => [],
            'infants' => 0,
            'include_pricing' => true,
            'pricing_level' => 'full',
            'filters' => [],
            'pagination' => ['page' => 1, 'per_page' => 20]
        ], $params);
    }

    private function validateSearchByIdParams(array $params): array
    {
        if (!isset($params['product_id'])) {
            throw new \InvalidArgumentException("product_id es requerido");
        }

        return array_merge([
            'include_pricing' => true,
            'check_availability' => true,
            'pricing_level' => 'full'
        ], $params);
    }

    private function findCityIdByName(string $cityName): ?int
    {
        // TODO: Implementar búsqueda de ciudad por nombre
        return null;
    }

    private function emptySearchResult(array $params): array
    {
        return [
            'ok' => 1,
            'search_params' => $params,
            'products' => [],
            'summary' => [
                'total_found' => 0,
                'with_pricing' => 0,
                'price_range' => null
            ],
            'pagination' => [
                'total' => 0,
                'page' => 1,
                'per_page' => $params['pagination']['per_page'],
                'pages' => 0
            ]
        ];
    }

    private function notFoundResponse(int $productId): array
    {
        return [
            'ok' => 0,
            'error' => "Producto no encontrado: {$productId}",
            'product' => null
        ];
    }
}