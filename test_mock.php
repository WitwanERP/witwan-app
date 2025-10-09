<?php

require_once __DIR__ . '/vendor/autoload.php';

echo "🧪 Test Mock de Pricing (Sin BD)\n";
echo "================================\n\n";

class MockTariffService
{
    public function calculateTariff(array $params): array
    {
        try {
            $this->validateParams($params);

            // Datos simulados
            $mockProduct = [
                'producto_id' => $params['product_id'],
                'producto_nombre' => 'Hotel Plaza Test',
                'fk_tipoproducto_id' => 'HOT',
                'activo' => 1
            ];

            $mockTariff = [
                'costo' => 120.00,
                'moneda_costo' => 'USD',
                'impuestos' => 15.00,
                'vigencia_ini' => '2024-01-01',
                'vigencia_fin' => '2024-12-31'
            ];

            $pricing = $this->calculatePricing($mockTariff, $params);

            return [
                'ok' => 1,
                'params' => $params,
                'producto' => [
                    'id' => $mockProduct['producto_id'],
                    'nombre' => $mockProduct['producto_nombre'],
                    'tipo' => $mockProduct['fk_tipoproducto_id'],
                ],
                'pricing' => $pricing,
                'pax_breakdown' => [
                    'adults' => $params['adults'],
                    'children' => $params['children'],
                    'infants' => $params['infants'] ?? 0,
                ],
                'date_range' => [
                    'check_in' => $params['check_in'],
                    'check_out' => $params['check_out'],
                    'nights' => $this->calculateNights($params['check_in'], $params['check_out']),
                ],
                'is_mock' => true,
            ];

        } catch (Exception $e) {
            return [
                'ok' => 0,
                'error' => $e->getMessage(),
            ];
        }
    }

    private function validateParams(array $params): void
    {
        $required = ['product_id', 'check_in', 'check_out', 'adults'];

        foreach ($required as $field) {
            if (!isset($params[$field]) || empty($params[$field])) {
                throw new InvalidArgumentException("Campo requerido: {$field}");
            }
        }

        if ($params['adults'] < 1) {
            throw new InvalidArgumentException('Debe haber al menos 1 adulto');
        }
    }

    private function calculatePricing(array $tariff, array $params): array
    {
        $baseCost = $tariff['costo'];
        $currency = $tariff['moneda_costo'];
        $nights = $this->calculateNights($params['check_in'], $params['check_out']);

        // Cálculo básico
        $adultPrice = $baseCost * $nights * $params['adults'];
        $childPrice = $baseCost * $nights * ($params['children'] ?? 0) * 0.7; // 30% descuento

        $subtotal = $adultPrice + $childPrice;

        // Impuestos
        $taxes = $tariff['impuestos'] * ($params['adults'] + ($params['children'] ?? 0));

        $totalPrice = $subtotal + $taxes;

        return [
            'base_price' => $baseCost,
            'adult_price' => $adultPrice,
            'child_price' => $childPrice,
            'subtotal' => $subtotal,
            'taxes' => $taxes,
            'total_price' => $totalPrice,
            'currency' => $currency,
            'breakdown' => [
                "Precio base: {$baseCost} {$currency} por noche",
                "Adultos: {$params['adults']} x {$baseCost} x {$nights} noches = {$adultPrice}",
                "Niños: " . ($params['children'] ?? 0) . " x " . ($baseCost * 0.7) . " x {$nights} noches = {$childPrice}",
                "Subtotal: {$subtotal}",
                "Impuestos: {$taxes}",
                "Total: {$totalPrice} {$currency}"
            ]
        ];
    }

    private function calculateNights(string $checkIn, string $checkOut): int
    {
        $start = new DateTime($checkIn);
        $end = new DateTime($checkOut);
        return $start->diff($end)->days;
    }
}

try {
    echo "📋 1. Inicializando servicio mock...\n";
    $service = new MockTariffService();
    echo "✅ Servicio mock cargado\n\n";

    echo "📋 2. Probando diferentes escenarios...\n\n";

    $testCases = [
        [
            'name' => 'Familia con 2 adultos y 1 niño',
            'params' => [
                'product_id' => 123,
                'check_in' => '2024-03-15',
                'check_out' => '2024-03-18',
                'adults' => 2,
                'children' => 1,
            ]
        ],
        [
            'name' => 'Pareja sin niños',
            'params' => [
                'product_id' => 456,
                'check_in' => '2024-04-01',
                'check_out' => '2024-04-05',
                'adults' => 2,
                'children' => 0,
            ]
        ],
        [
            'name' => 'Viajero solo',
            'params' => [
                'product_id' => 789,
                'check_in' => '2024-05-10',
                'check_out' => '2024-05-12',
                'adults' => 1,
                'children' => 0,
            ]
        ]
    ];

    foreach ($testCases as $i => $testCase) {
        echo "🧪 Test " . ($i + 1) . ": " . $testCase['name'] . "\n";
        echo str_repeat('-', 50) . "\n";

        $result = $service->calculateTariff($testCase['params']);

        if ($result['ok'] === 1) {
            echo "✅ Cálculo exitoso!\n";
            echo "📊 Resultados:\n";
            echo "   - Producto: " . $result['producto']['nombre'] . " (ID: " . $result['producto']['id'] . ")\n";
            echo "   - Tipo: " . $result['producto']['tipo'] . "\n";
            echo "   - Precio Total: " . $result['pricing']['total_price'] . " " . $result['pricing']['currency'] . "\n";
            echo "   - Noches: " . $result['date_range']['nights'] . "\n";
            echo "   - Pax: " . $result['pax_breakdown']['adults'] . " adultos, " . $result['pax_breakdown']['children'] . " niños\n";

            echo "📝 Breakdown:\n";
            foreach ($result['pricing']['breakdown'] as $line) {
                echo "     $line\n";
            }

        } else {
            echo "❌ Error: " . $result['error'] . "\n";
        }

        echo "\n";
    }

    echo "📋 3. Probando validaciones...\n";

    $invalidCases = [
        ['product_id' => null, 'check_in' => '2024-03-15', 'check_out' => '2024-03-18', 'adults' => 2],
        ['product_id' => 123, 'check_in' => '2024-03-15', 'check_out' => '2024-03-18', 'adults' => 0],
        ['product_id' => 123, 'check_in' => '', 'check_out' => '2024-03-18', 'adults' => 2],
    ];

    foreach ($invalidCases as $i => $invalidParams) {
        echo "❌ Test validación " . ($i + 1) . ": ";
        $result = $service->calculateTariff($invalidParams);
        if ($result['ok'] === 0) {
            echo "✅ Error detectado correctamente: " . $result['error'] . "\n";
        } else {
            echo "⚠️  Error no detectado\n";
        }
    }

} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "Archivo: " . $e->getFile() . ":" . $e->getLine() . "\n";
}

echo "\n🏁 Test mock completado\n";