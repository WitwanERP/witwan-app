<?php

namespace App\Domain\Pricing\Services;

use App\Domain\Pricing\Enums\ClientType;
use Illuminate\Support\Facades\DB;

/**
 * Servicio para determinar el contexto de pricing: markup y moneda
 * basado en las reglas de negocio del cliente y producto
 */
class PricingContextService
{
    /**
     * Determina el contexto completo de pricing para un cliente/producto
     */
    public function determinePricingContext(?int $clientId, int $productId): array
    {
        $currency = $this->determineCurrency($clientId, $productId);
        $markup = $this->determineMarkup($clientId, $productId);
        $rules = $this->getPricingRules($clientId, $productId);

        return [$currency, $markup, $rules];
    }

    /**
     * Determina la moneda según la cascada: cliente -> producto -> sistema
     */
    public function determineCurrency(?int $clientId, int $productId): string
    {
        // 1. Moneda del cliente (si existe)
        if ($clientId) {
            $clientCurrency = $this->getClientCurrency($clientId);
            if ($clientCurrency) {
                return $clientCurrency;
            }
        }

        // 2. Moneda del producto
        $productCurrency = $this->getProductCurrency($productId);
        if ($productCurrency) {
            return $productCurrency;
        }

        // 3. Moneda del sistema por defecto
        return config('app.default_currency', 'USD');
    }

    /**
     * Determina el markup según la cascada: cliente+producto -> cliente -> producto -> sistema
     */
    public function determineMarkup(?int $clientId, int $productId): float
    {
        // 1. Markup específico cliente+producto (mayor prioridad)
        if ($clientId) {
            $clientProductMarkup = $this->getClientProductMarkup($clientId, $productId);
            if ($clientProductMarkup !== null) {
                return $clientProductMarkup;
            }

            // 2. Markup general del cliente
            $clientMarkup = $this->getClientMarkup($clientId);
            if ($clientMarkup !== null) {
                return $clientMarkup;
            }
        }

        // 3. Markup del producto
        $productMarkup = $this->getProductMarkup($productId);
        if ($productMarkup !== null) {
            return $productMarkup;
        }

        // 4. Sin markup (1.0)
        return 1.0;
    }

    /**
     * Obtiene información del contexto de pricing para el response
     */
    public function getPricingContext(?int $clientId): array
    {
        $baseContext = [
            'base_currency' => config('app.default_currency', 'USD'),
            'pricing_date' => now()->toISOString()
        ];

        if ($clientId) {
            $clientInfo = $this->getClientInfo($clientId);
            $baseContext['client_id'] = $clientId;
            $baseContext['client_type'] = $clientInfo['type'] ?? 'retail';
            $baseContext['display_currency'] = $clientInfo['currency'] ?? $baseContext['base_currency'];
        } else {
            $baseContext['client_id'] = null;
            $baseContext['client_type'] = 'guest';
            $baseContext['display_currency'] = $baseContext['base_currency'];
        }

        return $baseContext;
    }

    /**
     * Determina el tipo de cliente para aplicar descuentos
     */
    public function determineClientType(?int $clientId): ClientType
    {
        if (!$clientId) {
            return ClientType::RETAIL;
        }

        $clientInfo = $this->getClientInfo($clientId);
        $clientType = $clientInfo['type'] ?? 'retail';

        return match($clientType) {
            'wholesale', 'mayorista' => ClientType::WHOLESALE,
            'group', 'grupo' => ClientType::GROUP,
            'corporate', 'corporativo' => ClientType::CORPORATE,
            default => ClientType::RETAIL
        };
    }

    /**
     * Obtiene las reglas aplicadas para transparencia
     */
    private function getPricingRules(?int $clientId, int $productId): array
    {
        $rules = [
            'currency_source' => 'system',
            'markup_source' => 'system',
            'markup_percentage' => 0.0
        ];

        // Determinar fuente de moneda
        if ($clientId && $this->getClientCurrency($clientId)) {
            $rules['currency_source'] = 'client';
        } elseif ($this->getProductCurrency($productId)) {
            $rules['currency_source'] = 'product';
        }

        // Determinar fuente de markup
        $markup = $this->determineMarkup($clientId, $productId);
        if ($clientId && $this->getClientProductMarkup($clientId, $productId) !== null) {
            $rules['markup_source'] = 'client_product';
        } elseif ($clientId && $this->getClientMarkup($clientId) !== null) {
            $rules['markup_source'] = 'client';
        } elseif ($this->getProductMarkup($productId) !== null) {
            $rules['markup_source'] = 'product';
        }

        $rules['markup_percentage'] = ($markup - 1) * 100;

        return $rules;
    }

    /**
     * Obtiene la moneda preferida del cliente
     */
    private function getClientCurrency(int $clientId): ?string
    {
        $client = DB::table('cliente')
            ->where('cliente_id', $clientId)
            ->first(['moneda_preferida']);

        return $client->moneda_preferida ?? null;
    }

    /**
     * Obtiene la moneda base del producto
     */
    private function getProductCurrency(int $productId): ?string
    {
        // Buscar en la tarifa más actual del producto
        $tariff = DB::table('tarifa')
            ->join('vigencia', 'tarifa.fk_vigencia_id', '=', 'vigencia.vigencia_id')
            ->where('vigencia.fk_producto_id', $productId)
            ->where('vigencia.vigencia_ini', '<=', now())
            ->where('vigencia.vigencia_fin', '>=', now())
            ->orderBy('vigencia.vigencia_prioridad', 'desc')
            ->first(['tarifa.moneda_venta']);

        return $tariff->moneda_venta ?? null;
    }

    /**
     * Obtiene markup específico cliente+producto
     */
    private function getClientProductMarkup(int $clientId, int $productId): ?float
    {
        $commission = DB::table('tarifariocomision')
            ->where('fk_cliente_id', $clientId)
            ->where('fk_producto_id', $productId)
            ->where('activo', 1)
            ->where(function($query) {
                $query->whereNull('vigencia_fin')
                      ->orWhere('vigencia_fin', '>=', now());
            })
            ->first(['markup_porcentaje']);

        if ($commission && $commission->markup_porcentaje !== null) {
            return 1 + ($commission->markup_porcentaje / 100);
        }

        return null;
    }

    /**
     * Obtiene markup general del cliente
     */
    private function getClientMarkup(int $clientId): ?float
    {
        $client = DB::table('cliente')
            ->where('cliente_id', $clientId)
            ->first(['markup_default']);

        if ($client && $client->markup_default !== null) {
            return 1 + ($client->markup_default / 100);
        }

        return null;
    }

    /**
     * Obtiene markup del producto
     */
    private function getProductMarkup(int $productId): ?float
    {
        $product = DB::table('producto')
            ->where('producto_id', $productId)
            ->first(['markup_porcentaje']);

        if ($product && $product->markup_porcentaje !== null) {
            return 1 + ($product->markup_porcentaje / 100);
        }

        return null;
    }

    /**
     * Obtiene información básica del cliente
     */
    private function getClientInfo(int $clientId): array
    {
        $client = DB::table('cliente')
            ->where('cliente_id', $clientId)
            ->first([
                'cliente_nombre',
                'tipo_cliente',
                'moneda_preferida',
                'markup_default'
            ]);

        if (!$client) {
            return [];
        }

        return [
            'name' => $client->cliente_nombre,
            'type' => $client->tipo_cliente,
            'currency' => $client->moneda_preferida,
            'markup' => $client->markup_default
        ];
    }

    /**
     * Valida si un cliente puede acceder a un producto específico
     */
    public function canClientAccessProduct(?int $clientId, int $productId): bool
    {
        if (!$clientId) {
            // Clientes anónimos pueden acceder a productos públicos
            return $this->isProductPublic($productId);
        }

        // Verificar restricciones específicas
        $restrictions = DB::table('cliente_producto_restriccion')
            ->where('fk_cliente_id', $clientId)
            ->where('fk_producto_id', $productId)
            ->where('activo', 1)
            ->first();

        if ($restrictions) {
            return $restrictions->permitir === 1;
        }

        // Por defecto, permitir acceso
        return true;
    }

    /**
     * Verifica si un producto es público
     */
    private function isProductPublic(int $productId): bool
    {
        $product = DB::table('producto')
            ->where('producto_id', $productId)
            ->first(['publico', 'aparece_tarifario']);

        return ($product->publico ?? true) && ($product->aparece_tarifario ?? true);
    }

    /**
     * Obtiene el tipo de cambio entre monedas
     */
    public function getExchangeRate(string $fromCurrency, string $toCurrency): float
    {
        if ($fromCurrency === $toCurrency) {
            return 1.0;
        }

        // Buscar tipo de cambio más reciente
        $exchange = DB::table('cotizacion')
            ->join('moneda', 'cotizacion.fk_moneda_id', '=', 'moneda.moneda_id')
            ->where('moneda.moneda_codigo', $fromCurrency)
            ->where('cotizacion.fecha', '<=', now())
            ->orderBy('cotizacion.fecha', 'desc')
            ->first(['cotizacion.valor']);

        return $exchange->valor ?? 1.0;
    }

    /**
     * Convierte un monto entre monedas
     */
    public function convertCurrency(float $amount, string $fromCurrency, string $toCurrency): float
    {
        if ($fromCurrency === $toCurrency) {
            return $amount;
        }

        $exchangeRate = $this->getExchangeRate($fromCurrency, $toCurrency);
        return $amount * $exchangeRate;
    }
}