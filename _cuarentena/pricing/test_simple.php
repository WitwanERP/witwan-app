<?php

require_once __DIR__.'/vendor/autoload.php';

echo "🧪 Test Básico de Pricing\n";
echo "========================\n\n";

// Cargar configuración de Laravel
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

try {
    echo "📋 1. Probando conexión a BD...\n";

    // Test básico de productos
    $products = DB::table('producto')->join('vigencia', 'producto.producto_id', '=', 'vigencia.fk_producto_id')->where('habilitar', 'Y')->where('fk_tipoproducto_id', 'HOT')->where('vigencia.vigencia_ini', '<=', now())->where('vigencia.vigencia_fin', '>=', now())->limit(3)->orderBy('producto_id', 'desc')->get();
    echo '✅ Conexión exitosa. Productos encontrados: '.count($products)."\n\n";

    if (count($products) > 0) {
        $product = $products[0];
        echo "📦 Producto de prueba: {$product->producto_nombre} (ID: {$product->producto_id})\n\n";

        echo "📋 2. Probando búsqueda de tarifas...\n";

        // Buscar tarifas para este producto
        $tariffs = DB::table('tarifa')
            ->join('vigencia', 'tarifa.fk_vigencia_id', '=', 'vigencia.vigencia_id')
            ->where('vigencia.fk_producto_id', $product->producto_id)
            ->where('tarifa.fk_tarifario_id', 0)
            ->limit(3)
            ->get();

        echo '✅ Tarifas encontradas: '.count($tariffs)."\n\n";

        if (count($tariffs) > 0) {
            $tariff = $tariffs[0];
            echo "💰 Tarifa de prueba:\n";
            echo '   - Costo: '.($tariff->costo ?? 'N/A')."\n";
            echo '   - Moneda: '.($tariff->moneda_costo ?? 'N/A')."\n";
            echo '   - Vigencia: '.($tariff->vigencia_ini ?? 'N/A').' al '.($tariff->vigencia_fin ?? 'N/A')."\n\n";

            echo "📋 3. Probando servicio simplificado...\n";

            $service = new App\Domain\Pricing\Services\SimpleTariffService;

            $params = [
                'product_id' => $product->producto_id,
                'check_in' => '2025-10-15',
                'check_out' => '2025-10-18',
                'adults' => 2,
                'children' => 1,
                'client_id' => 2106, // Cliente de prueba
            ];

            $result = $service->calculateTariff($params);

            if ($result['ok'] === 1) {
                echo "✅ Cálculo exitoso!\n";
                echo "📊 Resultados:\n";
                echo '   - Producto: '.$result['producto']['nombre']."\n";
                echo '   - Tipo: '.$result['producto']['tipo']."\n";
                echo '   - Precio Total: '.$result['pricing']['total_price'].' '.$result['pricing']['currency']."\n";
                echo '   - Noches: '.$result['date_range']['nights']."\n";
                echo '   - Adultos: '.$result['pax_breakdown']['adults']."\n";
                echo '   - Niños: '.$result['pax_breakdown']['children']."\n\n";

                echo "📝 Breakdown de precio:\n";
                foreach ($result['pricing']['breakdown'] as $line) {
                    echo "   - $line\n";
                }
            } else {
                echo '❌ Error en cálculo: '.$result['error']."\n";
            }
        } else {
            echo "⚠️  No se encontraron tarifas para el producto\n";
        }
    } else {
        echo "⚠️  No se encontraron productos activos\n";
    }
} catch (Exception $e) {
    echo '❌ Error: '.$e->getMessage()."\n";
    echo 'Archivo: '.$e->getFile().':'.$e->getLine()."\n";
    echo "\nTrace:\n".$e->getTraceAsString()."\n";
}

echo "\n🏁 Test completado\n";
