<?php

namespace App\Services\Vigencias;

/**
 * Arma la grilla de tarifas de una vigencia en el server, para que el Vue sólo
 * pinte y edite celdas. Es la única derivación de filas/columnas: reemplaza a
 * la que la vista PHP hacía por un lado y `vigencia.js` por otro.
 *
 * Dos modos (vigencia.php:274 y :385):
 *
 *   alojamiento  filas = categorías (alojamientohabitacion habilitadas, o
 *                "General" con categoría 0 si no tiene), columnas = bases de
 *                BasesResolver por categoría (cada categoría tiene su max_child).
 *   tramos       filas = tramos min/max pax, columnas = tipos de pax del tipo
 *                de producto (config productos.tipos.*.tipos_pax).
 *
 * Cada celda trae el costo (fk_tarifario_id = 0) y, si la vigencia es de carga
 * manual, un valor de venta por tarifario (fk_tarifario_id = tarifario).
 *
 * Clase pura: recibe arrays ya cargados.
 */
final class GrillaTarifas
{
    public function __construct(private BasesResolver $bases) {}

    /**
     * @param  array  $producto  fk_tipoproducto_id, bases (list), edades (array)
     * @param  list<array>  $habitaciones  fk_tarifacategoria_id, nombre, max_child
     * @param  list<array>  $tarifarios  tarifario_id, tarifario_nombre, fk_moneda_id
     * @param  list<array>  $tarifas  filas de `tarifa` de la vigencia
     */
    public function armar(array $producto, array $habitaciones, array $tarifarios, array $tarifas): array
    {
        $tipo = (string) ($producto['fk_tipoproducto_id'] ?? '');
        $cfg = (array) config("productos.tipos.{$tipo}", []);

        return ($cfg['alojamiento'] ?? false)
            ? $this->alojamiento($producto, $habitaciones, $tarifarios, $tarifas)
            : $this->tramos($tipo, $cfg, $tarifarios, $tarifas);
    }

    private function alojamiento(array $producto, array $habitaciones, array $tarifarios, array $tarifas): array
    {
        $tipo = (string) $producto['fk_tipoproducto_id'];

        // Sin habitaciones cargadas, el legacy tarifa una fila "General" con categoría 0 (vigencia.php:262-267).
        if ($habitaciones === []) {
            $habitaciones = [['fk_tarifacategoria_id' => 0, 'nombre' => 'General', 'max_child' => 0]];
        }

        // Index de tarifas existentes: [tarifario][categoria][base] => fila.
        $idx = [];
        foreach ($tarifas as $t) {
            $idx[(int) $t['fk_tarifario_id']][(int) $t['fk_tarifacategoria_id']][(string) $t['fk_base_id']] = $t;
        }

        $filas = [];
        foreach ($habitaciones as $hab) {
            $categoria = (int) $hab['fk_tarifacategoria_id'];
            $bases = $this->bases->resolver(
                (array) ($producto['bases'] ?? []),
                (array) ($producto['edades'] ?? []),
                (int) ($hab['max_child'] ?? 0),
                $tipo,
            );

            $celdas = [];
            foreach ($bases as $base) {
                $costo = $idx[0][$categoria][$base] ?? null;
                $celda = [
                    'base' => $base,
                    'tarifa_id' => $costo['tarifa_id'] ?? null,
                    'costo' => $costo !== null ? (float) $costo['costo'] : null,
                    'venta' => [],
                ];
                foreach ($tarifarios as $tf) {
                    $tid = (int) $tf['tarifario_id'];
                    $v = $idx[$tid][$categoria][$base] ?? null;
                    $celda['venta'][$tid] = $v !== null ? (float) $v['costo'] : null;
                }
                $celdas[] = $celda;
            }

            $filas[] = [
                'categoria' => $categoria,
                'nombre' => (string) ($hab['nombre'] ?? ''),
                'bases' => $bases,
                'celdas' => $celdas,
            ];
        }

        return [
            'modo' => 'alojamiento',
            'tarifarios' => $this->tarifarios($tarifarios),
            'filas' => $filas,
        ];
    }

    private function tramos(string $tipo, array $cfg, array $tarifarios, array $tarifas): array
    {
        $tiposPax = (array) ($cfg['tipos_pax'] ?? ['ADU', 'CHD', 'INF']);

        // Index: [tarifario][min_max][tipopax] => fila. fk_tipopax_id vacío se lee como ADU (producto_model.php:683).
        $idx = [];
        $orden = [];
        foreach ($tarifas as $t) {
            $clave = (int) $t['min_pax'].'_'.(int) $t['max_pax'];
            $pax = (string) ($t['fk_tipopax_id'] ?? '') ?: 'ADU';
            $idx[(int) $t['fk_tarifario_id']][$clave][$pax] = $t;
            $orden[$clave] = [(int) $t['min_pax'], (int) $t['max_pax']];
        }
        uasort($orden, fn ($a, $b) => $a[0] <=> $b[0] ?: $a[1] <=> $b[1]);

        $filas = [];
        foreach ($orden as $clave => [$min, $max]) {
            $costos = [];
            $venta = [];
            foreach ($tiposPax as $pax) {
                $c = $idx[0][$clave][$pax] ?? null;
                $costos[$pax] = $c !== null ? (float) $c['costo'] : null;
                foreach ($tarifarios as $tf) {
                    $tid = (int) $tf['tarifario_id'];
                    $v = $idx[$tid][$clave][$pax] ?? null;
                    $venta[$tid][$pax] = $v !== null ? (float) $v['costo'] : null;
                }
            }
            $filas[] = ['min' => $min, 'max' => $max, 'costos' => $costos, 'venta' => $venta];
        }

        return [
            'modo' => 'tramos',
            'tipos_pax' => array_values($tiposPax),
            'tarifarios' => $this->tarifarios($tarifarios),
            'filas' => $filas,
        ];
    }

    private function tarifarios(array $tarifarios): array
    {
        return array_values(array_map(fn ($t) => [
            'id' => (int) $t['tarifario_id'],
            'nombre' => (string) ($t['tarifario_nombre'] ?? ''),
            'moneda' => (string) ($t['fk_moneda_id'] ?? ''),
            'divisor_markup' => isset($t['divisor_markup']) ? (float) $t['divisor_markup'] : null,
        ], $tarifarios));
    }
}
