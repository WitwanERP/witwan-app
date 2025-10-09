<?php

/**
 * Script para probar el nuevo sistema de pricing
 * Ejecutar con: php test_pricing.php
 */

require_once __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Domain\Pricing\Services\LegacyTariffReplacementService;

echo "🧪 TESTING NUEVO SISTEMA DE PRICING\n";
echo "=====================================\n\n";

try {
    $tariffService = app(LegacyTariffReplacementService::class);
    echo "✅ Servicio cargado correctamente\n\n";

    // Test 1: Producto básico
    echo "📋 TEST 1: Cálculo básico de hotel\n";
    echo "-----------------------------------\n";

    $params1 = [
        'product_id' => 1,
        'check_in' => '2024-03-15',
        'check_out' => '2024-03-18',
        'adults' => 2,
        'children' => 0,
    ];

    $result1 = $tariffService->calculateTariff($params1);
    echo "Resultado: " . ($result1['ok'] ? "SUCCESS" : "ERROR: " . ($result1['error'] ?? 'Unknown')) . "\n";
    if ($result1['ok']) {
        echo "Precio total: " . $result1['pricing']['total_price'] . " " . $result1['pricing']['currency'] . "\n";
        echo "Producto: " . $result1['producto']['nombre'] . " (" . $result1['producto']['tipo'] . ")\n";
    }
    echo "\n";

    // Test 2: Con niños
    echo "📋 TEST 2: Hotel con niños\n";
    echo "-------------------------\n";

    $params2 = [
        'product_id' => 1,
        'check_in' => '2024-03-15',
        'check_out' => '2024-03-18',
        'adults' => 2,
        'children' => 2,
        'children_ages' => [8, 12],
    ];

    $result2 = $tariffService->calculateTariff($params2);
    echo "Resultado: " . ($result2['ok'] ? "SUCCESS" : "ERROR: " . ($result2['error'] ?? 'Unknown')) . "\n";
    if ($result2['ok']) {
        echo "Precio total: " . $result2['pricing']['total_price'] . " " . $result2['pricing']['currency'] . "\n";
        echo "Pax: " . $result2['pax_breakdown']['adults'] . " adultos, " . $result2['pax_breakdown']['children'] . " niños\n";
    }
    echo "\n";

    // Test 3: Cálculo masivo
    echo "📋 TEST 3: Cálculo masivo\n";
    echo "------------------------\n";

    $productIds = [1, 2, 3];
    $paramsBase = [
        'check_in' => '2024-03-15',
        'check_out' => '2024-03-18',
        'adults' => 2,
    ];

    $result3 = $tariffService->calculateBulkTariffs($productIds, $paramsBase);
    echo "Resultado: " . ($result3['ok'] ? "SUCCESS" : "ERROR") . "\n";
    echo "Productos procesados: " . $result3['total_products'] . "\n";
    echo "Cálculos exitosos: " . $result3['successful_calculations'] . "\n";
    echo "\n";

    // Test 4: Diferentes tipos de productos
    echo "📋 TEST 4: Diferentes tipos de productos\n";
    echo "---------------------------------------\n";

    // Buscar productos de diferentes tipos en la BD
    $products = DB::table('producto')
        ->select('producto_id', 'producto_nombre', 'fk_tipoproducto_id')
        ->where('activo', 1)
        ->limit(5)
        ->get();

    foreach ($products as $product) {
        echo "Testing producto #{$product->producto_id} ({$product->fk_tipoproducto_id}): {$product->producto_nombre}\n";

        $params = [
            'product_id' => $product->producto_id,
            'check_in' => '2024-03-15',
            'check_out' => '2024-03-18',
            'adults' => 2,
        ];

        $result = $tariffService->calculateTariff($params);
        echo "  -> " . ($result['ok'] ? "SUCCESS" : "ERROR: " . ($result['error'] ?? 'Unknown')) . "\n";

        if ($result['ok']) {
            echo "  -> Precio: " . $result['pricing']['total_price'] . " " . $result['pricing']['currency'] . "\n";
        }
        echo "\n";
    }

} catch (Exception $e) {
    echo "❌ ERROR: " . $e->getMessage() . "\n";
    echo "Archivo: " . $e->getFile() . ":" . $e->getLine() . "\n";
    echo "Trace:\n" . $e->getTraceAsString() . "\n";
}

echo "\n🏁 Testing completado\n";