<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Domain\Pricing\Services\ProductSearchService;
use App\Domain\Pricing\Services\LegacyTariffReplacementService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

/**
 * Controller unificado para búsqueda y cotización de productos
 * Maneja el flujo completo del carrito de compras
 *
 * @OA\Tag(name="Cotizaciones", description="Búsqueda y cotización de productos turísticos")
 */
class QuoteController extends Controller
{
    private ProductSearchService $searchService;
    private LegacyTariffReplacementService $tariffService;

    public function __construct(
        ProductSearchService $searchService,
        LegacyTariffReplacementService $tariffService
    ) {
        $this->searchService = $searchService;
        $this->tariffService = $tariffService;
    }

    /**
     * Búsqueda de productos con pricing integrado
     *
     * @OA\Post(
     *     path="/api/quote/search",
     *     summary="Buscar productos con precios calculados",
     *     description="Busca productos turísticos y calcula precios en tiempo real basado en los criterios de búsqueda",
     *     tags={"Cotizaciones"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"check_in", "check_out", "adults"},
     *             @OA\Property(property="destination", type="string", example="Punta Cana", description="Destino o city_id"),
     *             @OA\Property(property="product_type", type="string", example="HOT", description="Tipo de producto: HOT, EXC, TRN, etc."),
     *             @OA\Property(property="check_in", type="string", format="date", example="2024-01-07"),
     *             @OA\Property(property="check_out", type="string", format="date", example="2024-01-14"),
     *             @OA\Property(property="adults", type="integer", example=2),
     *             @OA\Property(property="children", type="integer", example=1),
     *             @OA\Property(property="children_ages", type="array", @OA\Items(type="integer"), example={8}),
     *             @OA\Property(property="infants", type="integer", example=0),
     *             @OA\Property(property="client_id", type="integer", example=456),
     *             @OA\Property(property="include_pricing", type="boolean", example=true),
     *             @OA\Property(property="pricing_level", type="string", example="full", description="estimate o full"),
     *             @OA\Property(
     *                 property="filters",
     *                 type="object",
     *                 @OA\Property(
     *                     property="price_range",
     *                     type="object",
     *                     @OA\Property(property="min", type="number", example=100),
     *                     @OA\Property(property="max", type="number", example=1000),
     *                     @OA\Property(property="include_markup", type="boolean", example=true)
     *                 ),
     *                 @OA\Property(property="provider_id", type="integer", example=123),
     *                 @OA\Property(property="rating", type="integer", example=4),
     *                 @OA\Property(property="available_only", type="boolean", example=true),
     *                 @OA\Property(property="sort_by", type="string", example="price_asc", description="price_asc, price_desc, rating, name")
     *             ),
     *             @OA\Property(
     *                 property="pagination",
     *                 type="object",
     *                 @OA\Property(property="page", type="integer", example=1),
     *                 @OA\Property(property="per_page", type="integer", example=20)
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Productos encontrados con pricing",
     *         @OA\JsonContent(
     *             @OA\Property(property="ok", type="integer", example=1),
     *             @OA\Property(property="search_params", type="object"),
     *             @OA\Property(property="pricing_context", type="object"),
     *             @OA\Property(property="products", type="array", @OA\Items(type="object")),
     *             @OA\Property(property="summary", type="object"),
     *             @OA\Property(property="pagination", type="object")
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Parámetros de búsqueda inválidos"
     *     )
     * )
     */
    public function search(Request $request): JsonResponse
    {
        try {
            $searchParams = $this->validateSearchParams($request);
            $result = $this->searchService->searchWithPricing($searchParams);
            return response()->json($result);

        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'ok' => 0,
                'error' => $e->getMessage(),
                'products' => []
            ], 400);

        } catch (\Exception $e) {
            \Log::error('Error in product search', [
                'message' => $e->getMessage(),
                'params' => $request->all(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'ok' => 0,
                'error' => 'Error interno en la búsqueda de productos',
                'products' => []
            ], 500);
        }
    }

    /**
     * Búsqueda por ID específico con pricing
     *
     * @OA\Post(
     *     path="/api/quote/search-by-id",
     *     summary="Buscar producto específico por ID con pricing",
     *     description="Obtiene detalles completos de un producto específico incluyendo pricing calculado",
     *     tags={"Cotizaciones"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"product_id", "check_in", "check_out", "adults"},
     *             @OA\Property(property="product_id", type="integer", example=123),
     *             @OA\Property(property="check_in", type="string", format="date", example="2024-01-07"),
     *             @OA\Property(property="check_out", type="string", format="date", example="2024-01-14"),
     *             @OA\Property(property="adults", type="integer", example=2),
     *             @OA\Property(property="children", type="integer", example=1),
     *             @OA\Property(property="children_ages", type="array", @OA\Items(type="integer"), example={8}),
     *             @OA\Property(property="infants", type="integer", example=0),
     *             @OA\Property(property="client_id", type="integer", example=456),
     *             @OA\Property(property="include_pricing", type="boolean", example=true),
     *             @OA\Property(property="check_availability", type="boolean", example=true),
     *             @OA\Property(property="pricing_level", type="string", example="full"),
     *             @OA\Property(property="room_type", type="string", example="DBL")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Producto encontrado con pricing",
     *         @OA\JsonContent(
     *             @OA\Property(property="ok", type="integer", example=1),
     *             @OA\Property(property="search_type", type="string", example="by_id"),
     *             @OA\Property(property="product", type="object"),
     *             @OA\Property(property="search_params", type="object")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Producto no encontrado"
     *     )
     * )
     */
    public function searchById(Request $request): JsonResponse
    {
        try {
            $searchParams = $this->validateSearchByIdParams($request);
            $result = $this->searchService->searchByIdWithPricing($searchParams);

            $status = $result['ok'] === 1 ? 200 : 404;
            return response()->json($result, $status);

        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'ok' => 0,
                'error' => $e->getMessage(),
                'product' => null
            ], 400);

        } catch (\Exception $e) {
            \Log::error('Error in product search by ID', [
                'message' => $e->getMessage(),
                'params' => $request->all(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'ok' => 0,
                'error' => 'Error interno en la búsqueda del producto',
                'product' => null
            ], 500);
        }
    }

    /**
     * Cotización final de productos seleccionados
     *
     * @OA\Post(
     *     path="/api/quote/calculate",
     *     summary="Calcular cotización final",
     *     description="Calcula la cotización final para productos ya seleccionados",
     *     tags={"Cotizaciones"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"product_id", "check_in", "check_out", "adults"},
     *             @OA\Property(property="product_id", type="integer", example=123),
     *             @OA\Property(property="check_in", type="string", format="date", example="2024-01-07"),
     *             @OA\Property(property="check_out", type="string", format="date", example="2024-01-14"),
     *             @OA\Property(property="adults", type="integer", example=2),
     *             @OA\Property(property="children", type="integer", example=1),
     *             @OA\Property(property="children_ages", type="array", @OA\Items(type="integer"), example={8}),
     *             @OA\Property(property="infants", type="integer", example=0),
     *             @OA\Property(property="client_id", type="integer", example=456),
     *             @OA\Property(property="room_type", type="string", example="DBL"),
     *             @OA\Property(property="special_requests", type="string", example="Vista al mar")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Cotización calculada exitosamente",
     *         @OA\JsonContent(
     *             @OA\Property(property="ok", type="integer", example=1),
     *             @OA\Property(property="quote_type", type="string", example="single"),
     *             @OA\Property(property="product", type="object"),
     *             @OA\Property(property="pricing", type="object"),
     *             @OA\Property(property="availability", type="object")
     *         )
     *     )
     * )
     */
    public function calculate(Request $request): JsonResponse
    {
        try {
            $params = $this->validateCalculateParams($request);

            // Usar el servicio existente de tarifación
            $result = $this->tariffService->calculateTariff($params);

            // Formatear respuesta para ser consistente con el nuevo formato
            if ($result['ok'] === 1) {
                $formatted = $this->formatCalculateResponse($result);
                return response()->json($formatted);
            } else {
                return response()->json($result, 400);
            }

        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'ok' => 0,
                'error' => $e->getMessage()
            ], 400);

        } catch (\Exception $e) {
            \Log::error('Error in quote calculation', [
                'message' => $e->getMessage(),
                'params' => $request->all(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'ok' => 0,
                'error' => 'Error interno en el cálculo de la cotización'
            ], 500);
        }
    }

    /**
     * Cotización múltiple para varios productos
     *
     * @OA\Post(
     *     path="/api/quote/calculate-multiple",
     *     summary="Calcular cotización para múltiples productos",
     *     description="Calcula cotización para una lista de productos seleccionados",
     *     tags={"Cotizaciones"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"products", "check_in", "check_out", "adults"},
     *             @OA\Property(
     *                 property="products",
     *                 type="array",
     *                 @OA\Items(
     *                     type="object",
     *                     @OA\Property(property="product_id", type="integer", example=123),
     *                     @OA\Property(property="quantity", type="integer", example=1),
     *                     @OA\Property(property="room_type", type="string", example="DBL")
     *                 )
     *             ),
     *             @OA\Property(property="check_in", type="string", format="date", example="2024-01-07"),
     *             @OA\Property(property="check_out", type="string", format="date", example="2024-01-14"),
     *             @OA\Property(property="adults", type="integer", example=2),
     *             @OA\Property(property="children", type="integer", example=1),
     *             @OA\Property(property="client_id", type="integer", example=456)
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Cotización múltiple calculada",
     *         @OA\JsonContent(
     *             @OA\Property(property="ok", type="integer", example=1),
     *             @OA\Property(property="quote_type", type="string", example="multiple"),
     *             @OA\Property(property="products", type="array", @OA\Items(type="object")),
     *             @OA\Property(property="summary", type="object")
     *         )
     *     )
     * )
     */
    public function calculateMultiple(Request $request): JsonResponse
    {
        try {
            $params = $this->validateCalculateMultipleParams($request);

            $products = $params['products'];
            $commonParams = $params;
            unset($commonParams['products']);

            $results = [];
            $totalAmount = 0;
            $successfulCalculations = 0;

            foreach ($products as $productRequest) {
                $productParams = array_merge($commonParams, [
                    'product_id' => $productRequest['product_id'],
                    'room_type' => $productRequest['room_type'] ?? null,
                    'quantity' => $productRequest['quantity'] ?? 1
                ]);

                $calculation = $this->tariffService->calculateTariff($productParams);

                if ($calculation['ok'] === 1) {
                    $successfulCalculations++;
                    $totalAmount += $calculation['pricing']['total_price'];
                }

                $results[] = [
                    'product_id' => $productRequest['product_id'],
                    'quantity' => $productRequest['quantity'] ?? 1,
                    'calculation' => $calculation
                ];
            }

            return response()->json([
                'ok' => 1,
                'quote_type' => 'multiple',
                'products' => $results,
                'summary' => [
                    'total_products' => count($products),
                    'successful_calculations' => $successfulCalculations,
                    'total_amount' => $totalAmount,
                    'currency' => 'USD' // TODO: Obtener de contexto
                ],
                'search_params' => $commonParams
            ]);

        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'ok' => 0,
                'error' => $e->getMessage()
            ], 400);

        } catch (\Exception $e) {
            \Log::error('Error in multiple quote calculation', [
                'message' => $e->getMessage(),
                'params' => $request->all(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'ok' => 0,
                'error' => 'Error interno en el cálculo de cotización múltiple'
            ], 500);
        }
    }

    /**
     * Verificar disponibilidad sin calcular pricing
     *
     * @OA\Post(
     *     path="/api/quote/availability",
     *     summary="Verificar disponibilidad de productos",
     *     description="Verifica solo la disponibilidad de productos sin calcular precios",
     *     tags={"Cotizaciones"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"product_id", "check_in", "check_out", "adults"},
     *             @OA\Property(property="product_id", type="integer", example=123),
     *             @OA\Property(property="check_in", type="string", format="date", example="2024-01-07"),
     *             @OA\Property(property="check_out", type="string", format="date", example="2024-01-14"),
     *             @OA\Property(property="adults", type="integer", example=2),
     *             @OA\Property(property="children", type="integer", example=1)
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Estado de disponibilidad",
     *         @OA\JsonContent(
     *             @OA\Property(property="ok", type="integer", example=1),
     *             @OA\Property(property="product_id", type="integer", example=123),
     *             @OA\Property(property="availability", type="object")
     *         )
     *     )
     * )
     */
    public function availability(Request $request): JsonResponse
    {
        try {
            $params = $this->validateAvailabilityParams($request);

            // Búsqueda por ID sin pricing, solo disponibilidad
            $searchParams = array_merge($params, [
                'include_pricing' => false,
                'check_availability' => true
            ]);

            $result = $this->searchService->searchByIdWithPricing($searchParams);

            if ($result['ok'] === 1) {
                return response()->json([
                    'ok' => 1,
                    'product_id' => $params['product_id'],
                    'availability' => $result['product']['availability'] ?? null,
                    'search_params' => $params
                ]);
            } else {
                return response()->json($result, 404);
            }

        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'ok' => 0,
                'error' => $e->getMessage()
            ], 400);

        } catch (\Exception $e) {
            \Log::error('Error checking availability', [
                'message' => $e->getMessage(),
                'params' => $request->all(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'ok' => 0,
                'error' => 'Error interno al verificar disponibilidad'
            ], 500);
        }
    }

    // Métodos de validación privados

    private function validateSearchParams(Request $request): array
    {
        return $request->validate([
            'destination' => 'nullable|string|max:255',
            'product_type' => 'nullable|string|max:10',
            'check_in' => 'required|date|after_or_equal:today',
            'check_out' => 'required|date|after:check_in',
            'adults' => 'required|integer|min:1|max:20',
            'children' => 'integer|min:0|max:10',
            'children_ages' => 'array',
            'children_ages.*' => 'integer|min:0|max:17',
            'infants' => 'integer|min:0|max:5',
            'client_id' => 'nullable|integer|min:1',
            'include_pricing' => 'boolean',
            'pricing_level' => 'in:estimate,full',
            'filters' => 'array',
            'filters.price_range' => 'array',
            'filters.price_range.min' => 'numeric|min:0',
            'filters.price_range.max' => 'numeric|min:0',
            'filters.price_range.include_markup' => 'boolean',
            'filters.provider_id' => 'integer|min:1',
            'filters.rating' => 'integer|min:1|max:5',
            'filters.available_only' => 'boolean',
            'filters.sort_by' => 'in:price_asc,price_desc,name,rating',
            'pagination' => 'array',
            'pagination.page' => 'integer|min:1',
            'pagination.per_page' => 'integer|min:1|max:100'
        ]);
    }

    private function validateSearchByIdParams(Request $request): array
    {
        return $request->validate([
            'product_id' => 'required|integer|min:1',
            'check_in' => 'required|date|after_or_equal:today',
            'check_out' => 'required|date|after:check_in',
            'adults' => 'required|integer|min:1|max:20',
            'children' => 'integer|min:0|max:10',
            'children_ages' => 'array',
            'children_ages.*' => 'integer|min:0|max:17',
            'infants' => 'integer|min:0|max:5',
            'client_id' => 'nullable|integer|min:1',
            'include_pricing' => 'boolean',
            'check_availability' => 'boolean',
            'pricing_level' => 'in:estimate,full',
            'room_type' => 'nullable|string|max:10'
        ]);
    }

    private function validateCalculateParams(Request $request): array
    {
        return $request->validate([
            'product_id' => 'required|integer|min:1',
            'check_in' => 'required|date|after_or_equal:today',
            'check_out' => 'required|date|after:check_in',
            'adults' => 'required|integer|min:1|max:20',
            'children' => 'integer|min:0|max:10',
            'children_ages' => 'array',
            'children_ages.*' => 'integer|min:0|max:17',
            'infants' => 'integer|min:0|max:5',
            'client_id' => 'nullable|integer|min:1',
            'room_type' => 'nullable|string|max:10',
            'special_requests' => 'nullable|string|max:500'
        ]);
    }

    private function validateCalculateMultipleParams(Request $request): array
    {
        return $request->validate([
            'products' => 'required|array|min:1|max:10',
            'products.*.product_id' => 'required|integer|min:1',
            'products.*.quantity' => 'integer|min:1|max:10',
            'products.*.room_type' => 'nullable|string|max:10',
            'check_in' => 'required|date|after_or_equal:today',
            'check_out' => 'required|date|after:check_in',
            'adults' => 'required|integer|min:1|max:20',
            'children' => 'integer|min:0|max:10',
            'children_ages' => 'array',
            'children_ages.*' => 'integer|min:0|max:17',
            'infants' => 'integer|min:0|max:5',
            'client_id' => 'nullable|integer|min:1'
        ]);
    }

    private function validateAvailabilityParams(Request $request): array
    {
        return $request->validate([
            'product_id' => 'required|integer|min:1',
            'check_in' => 'required|date|after_or_equal:today',
            'check_out' => 'required|date|after:check_in',
            'adults' => 'required|integer|min:1|max:20',
            'children' => 'integer|min:0|max:10',
            'children_ages' => 'array',
            'children_ages.*' => 'integer|min:0|max:17',
            'infants' => 'integer|min:0|max:5'
        ]);
    }

    private function formatCalculateResponse(array $legacyResult): array
    {
        return [
            'ok' => 1,
            'quote_type' => 'single',
            'product' => $legacyResult['producto'],
            'pricing' => $legacyResult['pricing'],
            'availability' => [
                'status' => 'confirmed',
                'valid_until' => now()->addHours(2)->toISOString()
            ],
            'pax_breakdown' => $legacyResult['pax_breakdown'],
            'date_range' => $legacyResult['date_range']
        ];
    }
}