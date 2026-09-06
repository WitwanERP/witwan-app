<?php

namespace App\Services\Proveedores;

use Illuminate\Support\Facades\DB;

/**
 * Recalcula lo "utilizado" de canjes y pre-compras a partir de los servicios
 * confirmados que las consumen (réplica del bloque del constructor de
 * administracion/canje.php y precompra.php del CI, que lo hacía en cada carga
 * del listado).
 *
 * Un servicio consume un canje/pre-compra cuando su extra `paquete` apunta al
 * id, su tipo de producto es CAN/PRC, está CO/CL, es del mismo proveedor y
 * tiene una orden de servicio. Sólo suma si la moneda de costo coincide.
 */
class SaldosProveedorService
{
    /** Canjes: además exige que la OS no esté anulada (el legacy sólo lo hacía acá). */
    public function recalcularCanjes(): int
    {
        return $this->recalcular('canje', 'canje_id', 'canje_utilizado', ['CAN'], true);
    }

    public function recalcularPrecompras(): int
    {
        return $this->recalcular('precompra', 'precompra_id', 'precompra_utilizado', ['PRC'], false);
    }

    private function recalcular(string $tabla, string $pk, string $campo, array $tipos, bool $sinAnuladas): int
    {
        $consumos = DB::table('servicio as o')
            ->join('rel_ordenadminocupacion as r', 'r.fk_ocupacion_id', '=', 'o.servicio_id')
            ->join('servicio_extra as se', 'se.fk_servicio_id', '=', 'o.servicio_id')
            ->join('ordenadmin as oa', 'oa.ordenadmin_id', '=', 'r.fk_ordenadmin_id')
            ->join("{$tabla} as c", function ($j) use ($pk) {
                $j->on('c.fk_proveedor_id', '=', 'o.fk_proveedor_id')
                    ->on(DB::raw("CAST(se.extra_valor AS UNSIGNED)"), '=', "c.{$pk}");
            })
            ->whereIn('o.fk_tipoproducto_id', $tipos)
            ->whereIn('o.status', ['CO', 'CL'])
            ->where('se.extra_nombre', 'paquete')
            ->where('oa.tipo', 'S')
            ->when($sinAnuladas, fn ($q) => $q->where('oa.status', '<>', 'AN'))
            ->whereColumn('o.moneda_costo', 'c.fk_moneda_id')
            ->groupBy("c.{$pk}", 'o.servicio_id')
            ->select("c.{$pk} as id", 'o.servicio_id', DB::raw('MAX(ABS(o.costo)) AS costo'))
            ->get()
            ->groupBy('id')
            ->map(fn ($filas) => round($filas->sum(fn ($f) => round((float) $f->costo, 2)), 2));

        $actualizados = 0;
        foreach (DB::table($tabla)->get([$pk, $campo]) as $fila) {
            $nuevo = (float) ($consumos[$fila->{$pk}] ?? 0);
            if (abs($nuevo - (float) $fila->{$campo}) > 0.001) {
                DB::table($tabla)->where($pk, $fila->{$pk})->update([$campo => $nuevo]);
                $actualizados++;
            }
        }

        return $actualizados;
    }

    /** Subconsulta de OS que consumen la fila (columna `oservicios` del legacy). */
    public static function subOrdenes(string $tabla, string $pk, array $tipos, bool $sinAnuladas): string
    {
        $in = "'".implode("','", $tipos)."'";
        $an = $sinAnuladas ? "AND oa.status <> 'AN'" : '';

        return "(SELECT GROUP_CONCAT(DISTINCT oa.nroservicio SEPARATOR ',') FROM servicio o
            JOIN rel_ordenadminocupacion r ON r.fk_ocupacion_id = o.servicio_id
            JOIN servicio_extra se ON se.fk_servicio_id = o.servicio_id
            JOIN ordenadmin oa ON oa.ordenadmin_id = r.fk_ordenadmin_id
            WHERE o.fk_tipoproducto_id IN ({$in}) AND o.status IN ('CO','CL')
              AND o.fk_proveedor_id = {$tabla}.fk_proveedor_id
              AND se.extra_valor = {$tabla}.{$pk} AND se.extra_nombre = 'paquete'
              AND oa.tipo = 'S' {$an}) AS oservicios";
    }
}
