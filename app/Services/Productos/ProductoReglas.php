<?php

namespace App\Services\Productos;

/**
 * Validaciones de negocio del producto (doc §4.3). Clase pura.
 *
 * Lo que valida el Form Request es la forma del payload; acá van las reglas
 * que dependen del tipo: modotarifa permitido, ciudad obligatoria cuando el
 * tipo es de ciudad única, bases coherentes, edades de corte crecientes
 * (infante < menor 1 < menor 2 < junior), porque de esas edades salen las
 * columnas de la grilla de tarifas y el tarifador elige la base por rango de
 * edad (tarifa_model.php:2503-2534): dos cortes iguales dejan un rango vacío.
 *
 * @return array<string,string> campo => mensaje (vacío si es válido)
 */
final class ProductoReglas
{
    public function validar(array $datos, array $tipo): array
    {
        $errores = [];

        if (trim((string) ($datos['producto_nombre'] ?? '')) === '') {
            $errores['producto_nombre'] = 'El nombre es obligatorio.';
        }

        if ((int) ($datos['fk_proveedor_id'] ?? 0) <= 0) {
            $errores['fk_proveedor_id'] = 'Debe seleccionar un proveedor.';
        }

        $modos = (array) ($tipo['modotarifa'] ?? []);
        $modo = (string) ($datos['modotarifa'] ?? '');
        if ($modos !== [] && ! in_array($modo, $modos, true)) {
            $errores['modotarifa'] = 'Modo de tarifa inválido para este tipo de producto ('.implode('/', $modos).').';
        }

        if (($tipo['ciudad'] ?? '') === 'unica' && (int) ($datos['destino'] ?? 0) <= 0) {
            $errores['destino'] = 'Debe seleccionar la ciudad.';
        }

        if (($tipo['ciudad'] ?? '') === 'multiple') {
            foreach ((array) ($datos['destinos'] ?? []) as $i => $d) {
                $ciudad = is_array($d) ? ($d['ciudad_id'] ?? 0) : $d;
                if ((int) $ciudad <= 0) {
                    $errores["destinos.{$i}"] = 'Ciudad inválida.';
                }
                if (is_array($d) && isset($d['tipo']) && ! in_array($d['tipo'], ['D', 'O'], true)) {
                    $errores["destinos.{$i}.tipo"] = 'El tipo de ciudad debe ser D (destino) u O (origen).';
                }
            }
        }

        foreach ((array) ($datos['bases'] ?? []) as $i => $b) {
            if (! ctype_digit((string) $b) || (int) $b < 1 || (int) $b > 9) {
                $errores["bases.{$i}"] = 'Las bases son cantidades de adultos entre 1 y 9.';
            }
        }

        $errores += $this->edades($datos);

        return $errores;
    }

    /** Edades crecientes. Un 0 significa "no aplica" y no participa de la comparación. */
    public function edades(array $datos): array
    {
        $orden = ['edad_infoa' => 'infante', 'edad_menor1' => 'menor 1', 'edad_menor2' => 'menor 2', 'edad_junior' => 'junior'];
        $errores = [];
        $anterior = null;

        foreach ($orden as $campo => $nombre) {
            $valor = (int) ($datos[$campo] ?? 0);
            if ($valor <= 0) {
                continue;
            }
            if ($anterior !== null && $valor <= $anterior[0]) {
                $errores[$campo] = "La edad de {$nombre} debe ser mayor que la de {$anterior[1]}.";
            }
            $anterior = [$valor, $nombre];
        }

        $senior = (int) ($datos['edad_senior'] ?? 0);
        if ($senior > 0 && $anterior !== null && $senior <= $anterior[0]) {
            $errores['edad_senior'] = 'La edad mínima de senior debe superar a todas las de menores.';
        }

        return $errores;
    }
}
