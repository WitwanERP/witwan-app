<?php

namespace App\Infrastructure\Pricing\Repositories;

use App\Domain\Pricing\Enums\ProductType;
use App\Domain\Pricing\Repositories\ProductRepositoryInterface;
use Illuminate\Support\Facades\DB;

class EloquentProductRepository implements ProductRepositoryInterface
{
    public function findById(int $id): ?array
    {
        $productData = DB::table('producto')
            ->where('producto_id', $id)
            ->first();

        if (!$productData) {
            return null;
        }

        return $this->mapToArray($productData);
    }

    public function findByIds(array $ids): array
    {
        $productsData = DB::table('producto')
            ->whereIn('producto_id', $ids)
            ->get();

        return $productsData->map(fn($data) => $this->mapToArray($data))->toArray();
    }

    public function findByType(ProductType $type): array
    {
        $productsData = DB::table('producto')
            ->where('fk_tipoproducto_id', $type->value)
            ->where('activo', 1)
            ->get();

        return $productsData->map(fn($data) => $this->mapToArray($data))->toArray();
    }

    public function findByDestination(int $cityId): array
    {
        $productsData = DB::table('producto')
            ->where('fk_ciudad_id', $cityId)
            ->where('activo', 1)
            ->get();

        return $productsData->map(fn($data) => $this->mapToArray($data))->toArray();
    }

    public function findByTypeAndDestination(ProductType $type, int $destinationCityId): array
    {
        $productsData = DB::table('producto')
            ->where('fk_tipoproducto_id', $type->value)
            ->where('fk_ciudad_id', $destinationCityId)
            ->where('activo', 1)
            ->get();

        return $productsData->map(fn($data) => $this->mapToArray($data))->toArray();
    }

    public function findActiveProducts(): array
    {
        $productsData = DB::table('producto')
            ->where('activo', 1)
            ->get();

        return $productsData->map(fn($data) => $this->mapToArray($data))->toArray();
    }

    public function searchByName(string $name): array
    {
        $productsData = DB::table('producto')
            ->where('producto_nombre', 'LIKE', "%{$name}%")
            ->where('activo', 1)
            ->get();

        return $productsData->map(fn($data) => $this->mapToArray($data))->toArray();
    }

    public function findByFilters(array $filters): array
    {
        $query = DB::table('producto')->where('activo', 1);

        if (isset($filters['type'])) {
            $query->where('fk_tipoproducto_id', $filters['type']);
        }

        if (isset($filters['destination'])) {
            $query->where('fk_ciudad_id', $filters['destination']);
        }

        if (isset($filters['provider'])) {
            $query->where('fk_proveedor_id', $filters['provider']);
        }

        $productsData = $query->get();

        return $productsData->map(fn($data) => $this->mapToArray($data))->toArray();
    }

    public function findWithVigencias(int $productId): array
    {
        $productData = DB::table('producto')
            ->leftJoin('vigencia', 'producto.producto_id', '=', 'vigencia.fk_producto_id')
            ->where('producto.producto_id', $productId)
            ->select(['producto.*', 'vigencia.vigencia_ini', 'vigencia.vigencia_fin'])
            ->get();

        return $productData->map(fn($data) => $this->mapToArray($data))->toArray();
    }

    public function save(array $productData): int
    {
        return DB::table('producto')->insertGetId($productData);
    }

    public function update(int $id, array $productData): bool
    {
        return DB::table('producto')
            ->where('producto_id', $id)
            ->update($productData) > 0;
    }

    public function delete(int $id): bool
    {
        return DB::table('producto')
            ->where('producto_id', $id)
            ->update(['activo' => 0]) > 0;
    }

    public function exists(int $id): bool
    {
        return DB::table('producto')
            ->where('producto_id', $id)
            ->exists();
    }

    public function count(): int
    {
        return DB::table('producto')
            ->where('activo', 1)
            ->count();
    }

    public function countByType(ProductType $type): int
    {
        return DB::table('producto')
            ->where('fk_tipoproducto_id', $type->value)
            ->where('activo', 1)
            ->count();
    }

    public function findByProviderAndType(int $providerId, ProductType $type): array
    {
        $productsData = DB::table('producto')
            ->where('fk_proveedor_id', $providerId)
            ->where('fk_tipoproducto_id', $type->value)
            ->where('activo', 1)
            ->get();

        return $productsData->map(fn($data) => $this->mapToArray($data))->toArray();
    }

    public function findProductsForQuoting(ProductType $type, int $originCityId, int $destinationCityId): array
    {
        $productsData = DB::table('producto')
            ->where('fk_tipoproducto_id', $type->value)
            ->where('fk_ciudad_id', $destinationCityId)
            ->where('activo', 1)
            ->get();

        return $productsData->map(fn($data) => $this->mapToArray($data))->toArray();
    }

    private function mapToArray($productData): array
    {
        return [
            'producto_id' => $productData->producto_id,
            'producto_nombre' => $productData->producto_nombre,
            'fk_tipoproducto_id' => $productData->fk_tipoproducto_id,
            'fk_ciudad_id' => $productData->fk_ciudad_id ?? null,
            'fk_proveedor_id' => $productData->fk_proveedor_id ?? null,
            'fk_sistema_id' => $productData->fk_sistema_id ?? null,
            'disponibilidad' => $productData->disponibilidad ?? 'RQ',
            'activo' => $productData->activo ?? 1,
            'producto_descripcion' => $productData->producto_descripcion ?? '',
            'regdate' => $productData->regdate ?? null,
            'base_price' => $this->getBasePrice($productData->producto_id),
        ];
    }

    private function getBasePrice(int $productId): array
    {
        $tariffData = DB::table('tarifa')
            ->join('vigencia', 'tarifa.fk_vigencia_id', '=', 'vigencia.vigencia_id')
            ->where('vigencia.fk_producto_id', $productId)
            ->where('tarifa.fk_tarifario_id', 0) // Tarifa base
            ->where('vigencia.vigencia_ini', '<=', now())
            ->where('vigencia.vigencia_fin', '>=', now())
            ->orderBy('vigencia.vigencia_prioridad', 'desc')
            ->orderBy('tarifa.costo', 'asc')
            ->select('tarifa.costo', 'tarifa.moneda_costo')
            ->first();

        if ($tariffData) {
            return [
                'amount' => $tariffData->costo,
                'currency' => $tariffData->moneda_costo ?: 'USD'
            ];
        }

        // Precio por defecto si no se encuentra tarifa
        return [
            'amount' => 0,
            'currency' => 'USD'
        ];
    }
}