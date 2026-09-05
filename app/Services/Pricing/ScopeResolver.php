<?php

namespace App\Services\Pricing;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Cascada de alcance para `tarifariocomision` e `iva`:
 *
 *   producto > ciudad > país > submódulo > general
 *
 * El CI tiene la cascada escrita tres veces con órdenes distintos:
 *  - tarifa_model.php:1262-1267 y vigencia.php:52-57: producto, submódulo, país,
 *    ciudad, país (país duplicado y por encima de ciudad: bug B7);
 *  - tarifa_model.php:2703: producto, ciudad, país, submódulo, sistema;
 *  - producto_model.php:867: ciudad, país, submódulo, sistema (sin producto).
 * Por eso el form y el tarifador pueden resolver un IVA distinto para el mismo
 * producto (B6). Acá hay UN solo ORDER BY, el de tarifar() para el IVA, que es
 * el que fija el precio final.
 *
 * Decisión pendiente (doc §4.8.1): `tarifariocomision.vigencia_ini/fin` se
 * graban pero ningún query de pricing las filtra (B8). Se conserva ese
 * comportamiento hasta que el negocio decida; `$porFecha` lo deja listo.
 */
final class ScopeResolver
{
    /** Orden único de la cascada, del más específico al más general. */
    public const ORDEN = ['fk_producto_id', 'fk_ciudad_id', 'fk_pais_id', 'fk_submodulo_id'];

    /**
     * Markup/comisión de un tarifario para un producto.
     *
     * @return array{divisor_markup:float, porcentaje_comision:float, tarifariocomision_id:int}|null
     */
    public function comision(int $tarifarioId, int $productoId, string $tipoProducto, int $ciudadId, int $paisId, ?string $fecha = null): ?array
    {
        $fila = $this->queryComision($tarifarioId, $productoId, $tipoProducto, $ciudadId, $paisId, $fecha)->first();

        return $fila === null ? null : [
            'tarifariocomision_id' => (int) $fila->tarifariocomision_id,
            'divisor_markup' => (float) $fila->divisor_markup,
            'porcentaje_comision' => (float) $fila->porcentaje_comision,
        ];
    }

    public function queryComision(int $tarifarioId, int $productoId, string $tipoProducto, int $ciudadId, int $paisId, ?string $fecha = null): Builder
    {
        $q = DB::table('tarifariocomision')
            ->where('fk_tarifario_id', $tarifarioId)
            ->where('origen', '')
            ->whereIn('fk_producto_id', [0, $productoId])
            ->whereIn('fk_submodulo_id', ['', '0', $tipoProducto])
            ->whereIn('fk_ciudad_id', [0, $ciudadId]);

        if ($paisId !== 0) {
            $q->whereIn('fk_pais_id', [0, $paisId]);
        }

        if ($fecha !== null) {
            $q->where(fn ($w) => $w->where('vigencia_ini', '<=', $fecha)->orWhere('vigencia_ini', '0000-00-00'))
                ->where(fn ($w) => $w->where('vigencia_fin', '>=', $fecha)->orWhere('vigencia_fin', '0000-00-00'));
        }

        return $this->ordenar($q)->limit(1);
    }

    /**
     * IVA de costo/venta para un producto.
     *
     * @return array{iva_costo:float, iva_valor:float, fk_modoivaventa_id:int}|null
     */
    public function iva(int $productoId, int $sistemaId, string $tipoProducto, int $ciudadId, int $paisId): ?array
    {
        $fila = $this->queryIva($productoId, $sistemaId, $tipoProducto, $ciudadId, $paisId)->first();

        return $fila === null ? null : [
            'iva_costo' => (float) $fila->iva_costo,
            'iva_valor' => (float) $fila->iva_valor,
            'fk_modoivaventa_id' => (int) $fila->fk_modoivaventa_id,
        ];
    }

    public function queryIva(int $productoId, int $sistemaId, string $tipoProducto, int $ciudadId, int $paisId): Builder
    {
        $q = DB::table('iva')
            ->whereIn('fk_sistema_id', [0, $sistemaId])
            ->whereIn('fk_submodulo_id', ['', $tipoProducto])
            ->whereIn('fk_producto_id', [0, $productoId])
            ->whereIn('fk_ciudad_id', [0, $ciudadId]);

        if ($paisId !== 0) {
            $q->whereIn('fk_pais_id', [0, $paisId]);
        }

        return $this->ordenar($q)->orderByDesc('fk_sistema_id')->limit(1);
    }

    private function ordenar(Builder $q): Builder
    {
        foreach (self::ORDEN as $columna) {
            $q->orderByDesc($columna);
        }

        return $q;
    }
}
