<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Domain\Pricing\Services\LegacyTariffReplacementService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

/**
 * Controller que reemplaza las llamadas al método tarifar() obsoleto
 */
class TariffController extends Controller
{
    private LegacyTariffReplacementService $tariffService;

    public function __construct(LegacyTariffReplacementService $tariffService)
    {
        $this->tariffService = $tariffService;
    }

    /**
     * Endpoint principal que reemplaza al método tarifar()
     *
     * @OA\Post(
     *     path="/api/tariff/calculate",
     *     summary="Calcular tarifa de producto",
     *     description="Calcula la tarifa para un producto específico con las nuevas reglas de pricing",
     *     tags={"Tarifas"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"product_id", "check_in", "check_out", "adults"},
     *             @OA\Property(property="product_id", type="integer", example=123),
     *             @OA\Property(property="check_in", type="string", format="date", example="2024-03-15"),
     *             @OA\Property(property="check_out", type="string", format="date", example="2024-03-18"),
     *             @OA\Property(property="adults", type="integer", example=2),
     *             @OA\Property(property="children", type="integer", example=1),
     *             @OA\Property(property="children_ages", type="array", @OA\Items(type="integer"), example={8}),
     *             @OA\Property(property="infants", type="integer", example=0),
     *             @OA\Property(property="client_id", type="integer", example=456),
     *             @OA\Property(property="room_type", type="string", example="DBL"),
     *             @OA\Property(property="currency", type="string", example="USD")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Tarifa calculada exitosamente",
     *         @OA\JsonContent(
     *             @OA\Property(property="ok", type="integer", example=1),
     *             @OA\Property(property="pricing", type="object"),
     *             @OA\Property(property="producto", type="object"),
     *             @OA\Property(property="params", type="object")
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Parámetros inválidos",
     *         @OA\JsonContent(
     *             @OA\Property(property="ok", type="integer", example=0),
     *             @OA\Property(property="error", type="string")
     *         )
     *     )
     * )
     */
    public function calculate(Request $request): JsonResponse
    {
        $params = $request->validate([
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
            'markup' => 'nullable|numeric|min:0',
            'currency' => 'nullable|string|size:3',
        ]);

        $result = $this->tariffService->calculateTariff($params);

        $status = $result['ok'] === 1 ? 200 : 400;

        return response()->json($result, $status);
    }

    /**
     * Cálculo masivo de tarifas para múltiples productos
     *
     * @OA\Post(
     *     path="/api/tariff/calculate-bulk",
     *     summary="Calcular tarifas para múltiples productos",
     *     description="Calcula tarifas para una lista de productos con los mismos parámetros",
     *     tags={"Tarifas"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"product_ids", "check_in", "check_out", "adults"},
     *             @OA\Property(property="product_ids", type="array", @OA\Items(type="integer"), example={123, 456, 789}),
     *             @OA\Property(property="check_in", type="string", format="date", example="2024-03-15"),
     *             @OA\Property(property="check_out", type="string", format="date", example="2024-03-18"),
     *             @OA\Property(property="adults", type="integer", example=2),
     *             @OA\Property(property="children", type="integer", example=1),
     *             @OA\Property(property="client_id", type="integer", example=456)
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Tarifas calculadas exitosamente",
     *         @OA\JsonContent(
     *             @OA\Property(property="ok", type="integer", example=1),
     *             @OA\Property(property="bulk_results", type="array", @OA\Items(type="object")),
     *             @OA\Property(property="total_products", type="integer"),
     *             @OA\Property(property="successful_calculations", type="integer")
     *         )
     *     )
     * )
     */
    public function calculateBulk(Request $request): JsonResponse
    {
        $params = $request->validate([
            'product_ids' => 'required|array|min:1|max:50',
            'product_ids.*' => 'integer|min:1',
            'check_in' => 'required|date|after_or_equal:today',
            'check_out' => 'required|date|after:check_in',
            'adults' => 'required|integer|min:1|max:20',
            'children' => 'integer|min:0|max:10',
            'children_ages' => 'array',
            'children_ages.*' => 'integer|min:0|max:17',
            'infants' => 'integer|min:0|max:5',
            'client_id' => 'nullable|integer|min:1',
            'currency' => 'nullable|string|size:3',
        ]);

        $productIds = $params['product_ids'];
        unset($params['product_ids']);

        $result = $this->tariffService->calculateBulkTariffs($productIds, $params);

        return response()->json($result);
    }

    /**
     * Endpoint de compatibilidad para el método obsoleto tarifar()
     * Mantiene la misma estructura de respuesta para transición gradual
     *
     * @deprecated Este endpoint mantiene compatibilidad con el método obsoleto
     */
    public function legacy(Request $request): JsonResponse
    {
        // Mapear parámetros del formato antiguo al nuevo
        $legacyParams = $request->all();

        $newParams = [
            'product_id' => $legacyParams['pr'] ?? null,
            'check_in' => $legacyParams['fecha_ini'] ?? null,
            'check_out' => $legacyParams['fecha_fin'] ?? null,
            'adults' => $legacyParams['ad'] ?? 1,
            'children' => $legacyParams['mn'] ?? 0,
            'children_ages' => $legacyParams['mnage'] ?? [],
            'client_id' => $legacyParams['cliente'] ?? null,
            'room_type' => $legacyParams['tc'] ?? null,
        ];

        $result = $this->tariffService->calculateTariff($newParams);

        // Convertir respuesta al formato esperado por el código legacy
        if ($result['ok'] === 1) {
            $legacyResponse = [
                'ok' => 1,
                'params' => $legacyParams,
                'resultado' => [$this->convertToLegacyFormat($result)],
                'mup' => $result['pricing']['markup'] ?? 1,
                'moneda' => $result['pricing']['currency'] ?? 'USD',
                'total' => $result['pricing']['total_price'],
                'promedio' => $result['pricing']['total_price'] / ($result['date_range']['nights'] ?: 1),
            ];
        } else {
            $legacyResponse = [
                'ok' => 0,
                'error' => $result['error'],
                'resultado' => []
            ];
        }

        return response()->json($legacyResponse);
    }

    private function convertToLegacyFormat(array $modernResult): array
    {
        return [
            'total' => $modernResult['pricing']['total_price'],
            'estadia' => $modernResult['pricing']['total_price'],
            'moneda' => $modernResult['pricing']['currency'],
            'producto' => $modernResult['producto']['nombre'],
            'tipo' => $modernResult['producto']['tipo'],
            'noches' => $modernResult['date_range']['nights'],
            'pax' => $modernResult['pax_breakdown'],
            'breakdown' => $modernResult['pricing']['adjustments'] ?? [],
        ];
    }
}