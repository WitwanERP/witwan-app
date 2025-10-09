<?php

namespace App\Infrastructure\Pricing\Repositories;

use App\Domain\Pricing\Repositories\CommissionRepositoryInterface;
use Illuminate\Support\Facades\DB;

class EloquentCommissionRepository implements CommissionRepositoryInterface
{
    public function findCommissionForProduct(int $productId, $productType, ?int $destinationCityId = null, ?int $tariffId = null): ?array
    {
        $query = DB::table('tarifariocomision')
            ->join('tarifario', 'tarifariocomision.fk_tarifario_id', '=', 'tarifario.tarifario_id')
            ->where('tarifariocomision.origen', '');

        // Buscar por producto específico primero
        $query->where(function($subQuery) use ($productId, $productType, $destinationCityId) {
            $subQuery->where('tarifariocomision.fk_producto_id', $productId)
                     ->orWhere(function($orQuery) use ($productType, $destinationCityId) {
                         $orQuery->where('tarifariocomision.fk_producto_id', 0)
                                 ->where(function($typeQuery) use ($productType) {
                                     $typeQuery->where('tarifariocomision.fk_submodulo_id', $productType)
                                              ->orWhere('tarifariocomision.fk_submodulo_id', '')
                                              ->orWhere('tarifariocomision.fk_submodulo_id', '0');
                                 });

                         if ($destinationCityId) {
                             $orQuery->where(function($cityQuery) use ($destinationCityId) {
                                 $cityQuery->where('tarifariocomision.fk_ciudad_id', $destinationCityId)
                                          ->orWhere('tarifariocomision.fk_ciudad_id', 0);
                             });
                         }
                     });
        });

        if ($tariffId) {
            $query->where('tarifariocomision.fk_tarifario_id', $tariffId);
        }

        $commission = $query->orderBy('tarifariocomision.fk_producto_id', 'desc')
                           ->orderBy('tarifariocomision.fk_submodulo_id', 'desc')
                           ->orderBy('tarifariocomision.fk_ciudad_id', 'desc')
                           ->first();

        return $commission ? (array) $commission : null;
    }

    public function findCommissionForClient(int $clientId, int $systemId): ?array
    {
        return DB::table('tarifario')
            ->join('rel_clientesistema', 'tarifario.tarifario_id', '=', 'rel_clientesistema.fk_tarifario_id')
            ->join('tarifariocomision', 'tarifario.tarifario_id', '=', 'tarifariocomision.fk_tarifario_id')
            ->where('rel_clientesistema.fk_cliente_id', $clientId)
            ->where('rel_clientesistema.fk_sistema_id', $systemId)
            ->where('tarifario.fk_sistema_id', $systemId)
            ->where('tarifariocomision.origen', '')
            ->select([
                'tarifariocomision.*',
                'tarifario.divisor_markup',
                'tarifario.fk_moneda_id as tariff_currency'
            ])
            ->first()?->toArray();
    }

    public function findDefaultCommissionForProductType(string $productType): float
    {
        $defaultCommissions = [
            'HOT' => 10.0,
            'EXC' => 15.0,
            'TRN' => 8.0,
            'PAQ' => 12.0,
            'CTK' => 20.0,
            'ASV' => 25.0,
            'CAE' => 18.0,
            'MSC' => 12.0,
            'AEL' => 10.0,
            'TRL' => 8.0,
            'MOT' => 10.0,
        ];

        return $defaultCommissions[$productType] ?? 10.0;
    }

    public function findMarkupForClient(int $clientId, int $productId): ?float
    {
        $tariffData = DB::table('tarifario')
            ->join('rel_clientesistema', 'tarifario.tarifario_id', '=', 'rel_clientesistema.fk_tarifario_id')
            ->where('rel_clientesistema.fk_cliente_id', $clientId)
            ->where('tarifario.activo', 1)
            ->first();

        return $tariffData ? $tariffData->divisor_markup : null;
    }

    public function findExchangeRateForClient(int $clientId): ?float
    {
        $tariffData = DB::table('tarifario')
            ->join('rel_clientesistema', 'tarifario.tarifario_id', '=', 'rel_clientesistema.fk_tarifario_id')
            ->join('tarifariocomision', 'tarifario.tarifario_id', '=', 'tarifariocomision.fk_tarifario_id')
            ->where('rel_clientesistema.fk_cliente_id', $clientId)
            ->where('tarifariocomision.origen', '')
            ->first();

        return $tariffData ? $tariffData->cotizacion : null;
    }

    public function getSystemTaxRates(int $systemId): array
    {
        $systemData = DB::table('sistema')
            ->where('sistema_id', $systemId)
            ->first();

        if (!$systemData) {
            return ['extra1' => 0, 'extra2' => 0];
        }

        return [
            'extra1' => ($systemData->extra1 ?? 0) / 100,
            'extra2' => ($systemData->extra2 ?? 0) / 100,
        ];
    }

    public function getProviderFees(int $providerId): array
    {
        $provider = DB::table('proveedor')
            ->where('proveedor_id', $providerId)
            ->first();

        if (!$provider) {
            return ['percentage' => 0, 'fixed_cost' => 0, 'type' => 'percentage'];
        }

        return [
            'percentage' => $provider->porcentaje_extra ?? 0,
            'fixed_cost' => $provider->costo_extra ?? 0,
            'type' => $provider->tipo_extra ?? 'percentage',
        ];
    }
}