<?php

namespace App\Domain\Pricing\Services;

use Illuminate\Support\Facades\DB;

/**
 * Servicio simplificado para probar las cotizaciones rápidamente
 * Sin dependencias complejas
 */
class SimpleTariffService
{
    public function calculateTariff(array $params): array
    {
        try {
            $validatedParams = $this->validateParams($params);

            // Buscar producto en la base de datos
            $product = $this->getProduct($validatedParams['product_id']);
            if (!$product) {
                return $this->errorResponse('Producto no encontrado');
            }

            // Obtener tarifario del cliente
            $tarifarioId = $this->getClientTarifario($validatedParams['client_id'], $product['fk_sistema_id'] ?? 1);
            if (!$tarifarioId) {
                return $this->errorResponse('Cliente no tiene tarifario configurado');
            }

            // Buscar precio fijo específico para este tarifario
            $fixedPrice = $this->getFixedPrice($validatedParams['product_id'], $tarifarioId, $validatedParams['check_in'], $validatedParams['check_out']);

            if ($fixedPrice) {
                // Usar precio fijo
                $pricing = $this->calculateFixedPricing($fixedPrice, $validatedParams);
            } else {
                // Calcular con markup usando costo base
                $baseTariff = $this->getBaseTariff($validatedParams['product_id'], $validatedParams['check_in'], $validatedParams['check_out']);
                if (!$baseTariff) {
                    return $this->errorResponse('No hay tarifas base disponibles para las fechas seleccionadas');
                }

                // Obtener coeficiente de markup
                $coefficient = $this->getMarkupCoefficient($tarifarioId, $product, $validatedParams['check_in']);
                if (!$coefficient) {
                    return $this->errorResponse("No se encontró configuración de markup para este cliente $tarifarioId");
                }

                $pricing = $this->calculateMarkupPricing($baseTariff, $coefficient, $validatedParams);
            }

            return [
                'ok' => 1,
                'params' => $validatedParams,
                'producto' => [
                    'id' => $product['producto_id'],
                    'nombre' => $product['producto_nombre'],
                    'tipo' => $product['fk_tipoproducto_id'],
                ],
                'pricing' => $pricing,
                'pax_breakdown' => [
                    'adults' => $validatedParams['adults'],
                    'children' => $validatedParams['children'],
                    'infants' => $validatedParams['infants'] ?? 0,
                ],
                'date_range' => [
                    'check_in' => $validatedParams['check_in'],
                    'check_out' => $validatedParams['check_out'],
                    'nights' => $this->calculateNights($validatedParams['check_in'], $validatedParams['check_out']),
                ],
                'tarifario_id' => $tarifarioId,
                'pricing_method' => $fixedPrice ? 'fixed_price' : 'markup_calculation',
            ];
        } catch (\Exception $e) {
            return $this->errorResponse('Error: ' . $e->getMessage());
        }
    }

    private function validateParams(array $params): array
    {
        $required = ['product_id', 'check_in', 'check_out', 'adults', 'client_id'];

        foreach ($required as $field) {
            if (!isset($params[$field]) || empty($params[$field])) {
                throw new \InvalidArgumentException("Campo requerido: {$field}");
            }
        }

        if ($params['adults'] < 1) {
            throw new \InvalidArgumentException('Debe haber al menos 1 adulto');
        }

        return [
            'product_id' => (int) $params['product_id'],
            'check_in' => $params['check_in'],
            'check_out' => $params['check_out'],
            'adults' => (int) $params['adults'],
            'children' => (int) ($params['children'] ?? 0),
            'infants' => (int) ($params['infants'] ?? 0),
            'client_id' => (int) $params['client_id'],
        ];
    }

    private function getProduct(int $productId): ?array
    {
        $product = DB::table('producto')
            ->where('producto_id', $productId)
            ->where('habilitar', 'Y')
            ->first();

        return $product ? (array) $product : null;
    }

    private function getClientTarifario(int $clientId, int $systemId): ?int
    {
        $relation = DB::table('rel_clientesistema')
            ->where('fk_cliente_id', $clientId)
            ->where('fk_sistema_id', $systemId)
            ->first();

        return $relation ? $relation->fk_tarifario_id : null;
    }

    private function getFixedPrice(int $productId, int $tarifarioId, string $checkIn, string $checkOut): ?array
    {
        $fixedPrice = DB::table('tarifa')
            ->join('vigencia', 'tarifa.fk_vigencia_id', '=', 'vigencia.vigencia_id')
            ->where('vigencia.fk_producto_id', $productId)
            ->where('vigencia.vigencia_ini', '<=', $checkOut)
            ->where('vigencia.vigencia_fin', '>=', $checkIn)
            ->where('tarifa.fk_tarifario_id', $tarifarioId)
            ->orderBy('vigencia.vigencia_prioridad', 'desc')
            ->first();

        return $fixedPrice ? (array) $fixedPrice : null;
    }

    private function getBaseTariff(int $productId, string $checkIn, string $checkOut): ?array
    {
        $baseTariff = DB::table('tarifa')
            ->join('vigencia', 'tarifa.fk_vigencia_id', '=', 'vigencia.vigencia_id')
            ->where('vigencia.fk_producto_id', $productId)
            ->where('vigencia.vigencia_ini', '<=', $checkOut)
            ->where('vigencia.vigencia_fin', '>=', $checkIn)
            ->where('tarifa.fk_tarifario_id', 0) // Costo base
            ->orderBy('vigencia.vigencia_prioridad', 'desc')
            ->first();

        return $baseTariff ? (array) $baseTariff : null;
    }

    private function getMarkupCoefficient(int $tarifarioId, array $product, string $checkIn): ?array
    {
        // Obtener ciudad y país del producto
        $productCity = DB::table('rel_productociudad')
            ->join('ciudad', 'rel_productociudad.fk_ciudad_id', '=', 'ciudad.ciudad_id')
            ->where('rel_productociudad.fk_producto_id', $product['producto_id'])
            ->select('ciudad.ciudad_id', 'ciudad.fk_pais_id')
            ->first();

        $ciudadId = $productCity->ciudad_id ?? null;
        $paisId = $productCity->fk_pais_id ?? null;
        $tipoProducto = $product['fk_tipoproducto_id'] ?: '';

        // Una sola consulta con ordenamiento por especificidad
        // Orden: producto específico > ciudad+tipo > país+tipo > tipo genérico
        $query = DB::table('tarifariocomision')
            ->where('fk_tarifario_id', $tarifarioId)
            ->where(function ($query) use ($tipoProducto) {
                $query->where('fk_submodulo_id', $tipoProducto)
                    ->orWhere('fk_submodulo_id', '0', '0')
                    ->orWhereNull('fk_submodulo_id');
            })
            //->where('vigencia_ini', '<=', $checkIn)
            //->where('vigencia_fin', '>=', $checkIn)
            ->where(function ($query) use ($product, $ciudadId, $paisId) {
                // 1. Producto específico
                $query->where(function ($q) use ($product) {
                    $q->where('fk_producto_id', $product['producto_id'])
                        ->where('fk_producto_id', '!=', 0);
                })
                    // 2. Ciudad + tipo
                    ->orWhere(function ($q) use ($ciudadId) {
                        $q->where('fk_ciudad_id', $ciudadId)
                            ->where('fk_ciudad_id', '!=', 0)
                            ->where(fn($sq) => $sq->whereNull('fk_producto_id')->orWhere('fk_producto_id', 0))
                            ->where(fn($sq) => $sq->whereNull('fk_pais_id')->orWhere('fk_pais_id', 0));
                    })
                    // 3. País + tipo
                    ->orWhere(function ($q) use ($paisId) {
                        $q->where('fk_pais_id', $paisId)
                            ->where('fk_pais_id', '!=', 0)
                            ->where(fn($sq) => $sq->whereNull('fk_ciudad_id')->orWhere('fk_ciudad_id', 0))
                            ->where(fn($sq) => $sq->whereNull('fk_producto_id')->orWhere('fk_producto_id', 0));
                    })
                    // 4. Genérico (solo tipo)
                    ->orWhere(function ($q) {
                        $q->where(fn($sq) => $sq->whereNull('fk_producto_id')->orWhere('fk_producto_id', 0))
                            ->where(fn($sq) => $sq->whereNull('fk_ciudad_id')->orWhere('fk_ciudad_id', 0))
                            ->where(fn($sq) => $sq->whereNull('fk_pais_id')->orWhere('fk_pais_id', 0));
                    });
            })
            ->orderByRaw('
                CASE
                    WHEN fk_producto_id IS NOT NULL AND fk_producto_id != 0 THEN 1
                    WHEN fk_ciudad_id IS NOT NULL AND fk_ciudad_id != 0 THEN 2
                    WHEN fk_pais_id IS NOT NULL AND fk_pais_id != 0 THEN 3
                    ELSE 4
                END,
                CASE
                    WHEN fk_submodulo_id IS NOT NULL AND fk_submodulo_id != "" THEN 1
                    ELSE 2
                END
            ');

        // Debug: imprimir la query
        echo "\n=== DEBUG QUERY ===\n";
        echo "SQL: " . $query->toSql() . "\n";
        echo "Bindings: " . json_encode($query->getBindings()) . "\n";
        echo "Params: tarifarioId=$tarifarioId, productoId={$product['producto_id']}, ciudadId=$ciudadId, paisId=$paisId, tipoProducto=$tipoProducto\n";
        echo "===================\n\n";

        $coefficient = $query->first();

        return $coefficient ? (array) $coefficient : null;
    }

    private function calculateFixedPricing(array $fixedPrice, array $params): array
    {
        $fixedCost = $fixedPrice['costo'];
        $currency = $fixedPrice['moneda_costo'] ?? 'USD';
        $nights = $this->calculateNights($params['check_in'], $params['check_out']);

        $adultPrice = $fixedCost * $nights * $params['adults'];
        $childPrice = $fixedCost * $nights * $params['children'] * 0.7;
        $taxes = ($fixedPrice['impuestos'] ?? 0) * ($params['adults'] + $params['children']);
        $totalPrice = $adultPrice + $childPrice + $taxes;

        return [
            'base_price' => $fixedCost,
            'adult_price' => $adultPrice,
            'child_price' => $childPrice,
            'taxes' => $taxes,
            'total_price' => $totalPrice,
            'currency' => $currency,
            'pricing_type' => 'fixed_price',
            'breakdown' => [
                "Precio fijo: {$fixedCost} {$currency} por noche",
                "Adultos: {$params['adults']} x {$fixedCost} x {$nights} = {$adultPrice}",
                "Niños: {$params['children']} x " . ($fixedCost * 0.7) . " x {$nights} = {$childPrice}",
                "Impuestos: {$taxes}",
                "Total: {$totalPrice} {$currency}"
            ]
        ];
    }

    private function calculateMarkupPricing(array $baseTariff, array $coefficient, array $params): array
    {
        $baseCost = $baseTariff['costo'];
        $markup = $coefficient['divisor_markup'];
        $sellingPrice = $baseCost / $markup; // Precio de venta = costo / coeficiente

        $currency = $baseTariff['moneda_costo'] ?? 'USD';
        $nights = $this->calculateNights($params['check_in'], $params['check_out']);

        $adultPrice = $sellingPrice * $nights * $params['adults'];
        $childPrice = $sellingPrice * $nights * $params['children'] * 0.7;
        $taxes = ($baseTariff['impuestos'] ?? 0) * ($params['adults'] + $params['children']);
        $totalPrice = $adultPrice + $childPrice + $taxes;

        return [
            'base_cost' => $baseCost,
            'markup_coefficient' => $markup,
            'selling_price' => $sellingPrice,
            'adult_price' => $adultPrice,
            'child_price' => $childPrice,
            'taxes' => $taxes,
            'total_price' => $totalPrice,
            'currency' => $currency,
            'pricing_type' => 'markup_calculation',
            'commission_percent' => $coefficient['porcentaje_comision'] ?? 0,
            'breakdown' => [
                "Costo base: {$baseCost} {$currency}",
                "Markup: ÷{$markup} = {$sellingPrice} {$currency} por noche",
                "Adultos: {$params['adults']} x {$sellingPrice} x {$nights} = {$adultPrice}",
                "Niños: {$params['children']} x " . ($sellingPrice * 0.7) . " x {$nights} = {$childPrice}",
                "Impuestos: {$taxes}",
                "Total: {$totalPrice} {$currency}"
            ]
        ];
    }

    private function calculateNights(string $checkIn, string $checkOut): int
    {
        $start = new \DateTime($checkIn);
        $end = new \DateTime($checkOut);
        return $start->diff($end)->days;
    }

    private function errorResponse(string $message): array
    {
        return [
            'ok' => 0,
            'error' => $message,
            'producto' => null,
            'pricing' => null,
        ];
    }
}
