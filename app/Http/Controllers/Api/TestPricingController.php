<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Domain\Pricing\Services\SimpleTariffService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

/**
 * Controller temporal para probar el sistema de pricing
 */
class TestPricingController extends Controller
{
    /**
     * Test endpoint para probar pricing
     */
    public function test(Request $request): JsonResponse
    {
        try {
            $service = new SimpleTariffService();

            $params = $request->validate([
                'product_id' => 'required|integer|min:1',
                'check_in' => 'required|date',
                'check_out' => 'required|date|after:check_in',
                'adults' => 'required|integer|min:1|max:20',
                'children' => 'integer|min:0|max:10',
                'infants' => 'integer|min:0|max:5',
            ]);

            $result = $service->calculateTariff($params);

            return response()->json($result);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'ok' => 0,
                'error' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);

        } catch (\Exception $e) {
            return response()->json([
                'ok' => 0,
                'error' => 'Internal error: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Mock endpoint que simula datos
     */
    public function mock(Request $request): JsonResponse
    {
        $params = $request->validate([
            'product_id' => 'required|integer|min:1',
            'check_in' => 'required|date',
            'check_out' => 'required|date|after:check_in',
            'adults' => 'required|integer|min:1|max:20',
            'children' => 'integer|min:0|max:10',
        ]);

        // Datos simulados
        $baseCost = 120.00;
        $currency = 'USD';
        $nights = (new \DateTime($params['check_in']))->diff(new \DateTime($params['check_out']))->days;

        $adultPrice = $baseCost * $nights * $params['adults'];
        $childPrice = $baseCost * $nights * ($params['children'] ?? 0) * 0.7;
        $taxes = 15 * ($params['adults'] + ($params['children'] ?? 0));
        $totalPrice = $adultPrice + $childPrice + $taxes;

        return response()->json([
            'ok' => 1,
            'params' => $params,
            'producto' => [
                'id' => $params['product_id'],
                'nombre' => 'Hotel Test Plaza',
                'tipo' => 'HOT',
            ],
            'pricing' => [
                'base_price' => $baseCost,
                'adult_price' => $adultPrice,
                'child_price' => $childPrice,
                'taxes' => $taxes,
                'total_price' => $totalPrice,
                'currency' => $currency,
            ],
            'pax_breakdown' => [
                'adults' => $params['adults'],
                'children' => $params['children'] ?? 0,
                'infants' => 0,
            ],
            'date_range' => [
                'check_in' => $params['check_in'],
                'check_out' => $params['check_out'],
                'nights' => $nights,
            ],
            'is_mock' => true,
        ]);
    }

    /**
     * Status endpoint para verificar que el sistema está funcionando
     */
    public function status(): JsonResponse
    {
        return response()->json([
            'status' => 'ok',
            'message' => 'Pricing system is working',
            'timestamp' => now()->toISOString(),
            'endpoints' => [
                'POST /api/pricing/test' => 'Test with database connection',
                'POST /api/pricing/mock' => 'Test with mock data',
                'GET /api/pricing/status' => 'System status',
            ]
        ]);
    }
}