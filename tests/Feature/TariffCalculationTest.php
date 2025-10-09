<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Domain\Pricing\Services\LegacyTariffReplacementService;

class TariffCalculationTest extends TestCase
{
    /**
     * Test básico de cálculo de tarifa
     */
    public function test_basic_tariff_calculation()
    {
        $response = $this->postJson('/api/tariff/calculate', [
            'product_id' => 1,
            'check_in' => '2024-03-15',
            'check_out' => '2024-03-18',
            'adults' => 2,
            'children' => 1,
            'children_ages' => [8],
        ]);

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'ok',
                    'params',
                    'producto' => [
                        'id',
                        'nombre',
                        'tipo'
                    ],
                    'pricing' => [
                        'base_price',
                        'total_price',
                        'currency'
                    ],
                    'pax_breakdown',
                    'date_range'
                ]);
    }

    /**
     * Test de validación de parámetros
     */
    public function test_validation_errors()
    {
        $response = $this->postJson('/api/tariff/calculate', [
            'product_id' => 'invalid',
            'adults' => 0,
        ]);

        $response->assertStatus(422)
                ->assertJsonValidationErrors(['product_id', 'check_in', 'check_out', 'adults']);
    }

    /**
     * Test de cálculo masivo
     */
    public function test_bulk_calculation()
    {
        $response = $this->postJson('/api/tariff/calculate-bulk', [
            'product_ids' => [1, 2, 3],
            'check_in' => '2024-03-15',
            'check_out' => '2024-03-18',
            'adults' => 2,
        ]);

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'ok',
                    'bulk_results',
                    'total_products',
                    'successful_calculations'
                ]);
    }

    /**
     * Test del endpoint legacy
     */
    public function test_legacy_compatibility()
    {
        $response = $this->postJson('/api/tariff/legacy', [
            'pr' => 1,
            'fecha_ini' => '2024-03-15',
            'fecha_fin' => '2024-03-18',
            'ad' => 2,
            'mn' => 1,
        ]);

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'ok',
                    'params',
                    'resultado'
                ]);
    }

    /**
     * Test de servicio directo
     */
    public function test_tariff_service_directly()
    {
        $tariffService = app(LegacyTariffReplacementService::class);

        $params = [
            'product_id' => 1,
            'check_in' => '2024-03-15',
            'check_out' => '2024-03-18',
            'adults' => 2,
            'children' => 1,
        ];

        $result = $tariffService->calculateTariff($params);

        $this->assertArrayHasKey('ok', $result);
        $this->assertArrayHasKey('pricing', $result);
        $this->assertArrayHasKey('producto', $result);
    }
}