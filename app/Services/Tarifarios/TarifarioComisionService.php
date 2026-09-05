<?php

namespace App\Services\Tarifarios;

use App\Exceptions\Productos\TarifarioException;
use Illuminate\Support\Facades\DB;

/**
 * Filas de markup/comisión de un tarifario (`tarifariocomision`) por alcance:
 * submódulo, país, ciudad, producto, origen.
 *
 * El CI hace `DELETE FROM tarifariocomision WHERE fk_tarifario_id=X` y
 * reinserta todo en cada guardado (tarifario.php:390-449): se pierden los ids
 * y cualquier fila que el form no haya mandado. Acá se actualiza por
 * diferencia usando `tarifariocomision_id`, se valida que el alcance sea único
 * (la UNIQUE de la tabla se comía filas en silencio con INSERT IGNORE) y que
 * `divisor_markup > 0` (un 0 significa "sin markup" en el tarifador y un
 * negativo da precios negativos).
 */
class TarifarioComisionService
{
    private const ALCANCE = ['fk_submodulo_id', 'fk_pais_id', 'fk_ciudad_id', 'fk_producto_id', 'origen'];

    /** @return list<array> */
    public function listar(int $tarifarioId): array
    {
        return DB::table('tarifariocomision')
            ->where('fk_tarifario_id', $tarifarioId)
            ->orderByRaw('(fk_submodulo_id = ? OR fk_submodulo_id = ?) AND fk_pais_id = 0 AND fk_ciudad_id = 0 AND fk_producto_id = 0 DESC', ['0', ''])
            ->orderBy('tarifariocomision_id')
            ->get()
            ->map(function ($f) {
                $fila = (array) $f;
                $fila['general'] = $this->esGeneral($fila);
                foreach (['vigencia_ini', 'vigencia_fin'] as $c) {
                    $fila[$c] = $fila[$c] === '0000-00-00' ? '' : substr((string) $fila[$c], 0, 10);
                }

                return $fila;
            })
            ->all();
    }

    /**
     * Sincroniza la lista completa. Cada fila: tarifariocomision_id?,
     * fk_submodulo_id, fk_pais_id, fk_ciudad_id, fk_producto_id, origen,
     * divisor_markup, porcentaje_comision, vigencia_ini, vigencia_fin.
     *
     * @return array{insertadas:int, actualizadas:int, borradas:int, sin_cambios:int}
     */
    public function sincronizar(int $tarifarioId, array $filas): array
    {
        $errores = [];
        $normalizadas = [];
        $alcances = [];

        foreach ($filas as $i => $f) {
            $n = $this->normalizar($f);
            if ($n['divisor_markup'] <= 0) {
                $errores["comisiones.{$i}.divisor_markup"] = 'El divisor de markup debe ser mayor que 0.';
            }
            if ($n['porcentaje_comision'] < 0 || $n['porcentaje_comision'] > 100) {
                $errores["comisiones.{$i}.porcentaje_comision"] = 'La comisión debe estar entre 0 y 100.';
            }
            if ($n['vigencia_ini'] !== '0000-00-00' && $n['vigencia_fin'] !== '0000-00-00' && $n['vigencia_fin'] < $n['vigencia_ini']) {
                $errores["comisiones.{$i}.vigencia_fin"] = 'El fin de vigencia no puede ser anterior al inicio.';
            }
            $clave = $this->claveAlcance($n);
            if (isset($alcances[$clave])) {
                $errores["comisiones.{$i}"] = 'Hay dos filas con el mismo alcance (submódulo/país/ciudad/producto/origen).';
            }
            $alcances[$clave] = true;
            $normalizadas[$i] = $n;
        }

        if ($errores !== []) {
            throw TarifarioException::porCampos($errores);
        }

        $existentes = DB::table('tarifariocomision')->where('fk_tarifario_id', $tarifarioId)->get()->keyBy('tarifariocomision_id');
        $stats = ['insertadas' => 0, 'actualizadas' => 0, 'borradas' => 0, 'sin_cambios' => 0];
        $vistos = [];

        foreach ($normalizadas as $n) {
            $id = $n['tarifariocomision_id'];
            unset($n['tarifariocomision_id']);

            if ($id !== 0 && $existentes->has($id)) {
                $actual = (array) $existentes->get($id);
                $cambios = array_filter($n, fn ($v, $k) => ! $this->igual($v, $actual[$k] ?? null), ARRAY_FILTER_USE_BOTH);
                if ($cambios !== []) {
                    DB::table('tarifariocomision')->where('tarifariocomision_id', $id)->update($cambios);
                    $stats['actualizadas']++;
                } else {
                    $stats['sin_cambios']++;
                }
                $vistos[] = $id;

                continue;
            }

            $vistos[] = (int) DB::table('tarifariocomision')->insertGetId($n + ['fk_tarifario_id' => $tarifarioId]);
            $stats['insertadas']++;
        }

        foreach ($existentes as $id => $fila) {
            if (! in_array((int) $id, $vistos, true)) {
                DB::table('tarifariocomision')->where('tarifariocomision_id', $id)->delete();
                $stats['borradas']++;
            }
        }

        return $stats;
    }

    public function esGeneral(array $fila): bool
    {
        return in_array((string) $fila['fk_submodulo_id'], ['', '0'], true)
            && (int) $fila['fk_pais_id'] === 0 && (int) $fila['fk_ciudad_id'] === 0
            && (int) $fila['fk_producto_id'] === 0 && (string) $fila['origen'] === '';
    }

    private function normalizar(array $f): array
    {
        $sub = trim((string) ($f['fk_submodulo_id'] ?? ''));

        return [
            'tarifariocomision_id' => (int) ($f['tarifariocomision_id'] ?? 0),
            'fk_submodulo_id' => $sub === '' ? '0' : $sub,
            'fk_pais_id' => (int) ($f['fk_pais_id'] ?? 0),
            'fk_ciudad_id' => (int) ($f['fk_ciudad_id'] ?? 0),
            'fk_producto_id' => (int) ($f['fk_producto_id'] ?? 0),
            'origen' => (string) ($f['origen'] ?? ''),
            'divisor_markup' => round((float) ($f['divisor_markup'] ?? 0), 4),
            'porcentaje_comision' => round((float) ($f['porcentaje_comision'] ?? 0), 4),
            'vigencia_ini' => $this->fecha($f['vigencia_ini'] ?? null),
            'vigencia_fin' => $this->fecha($f['vigencia_fin'] ?? null),
        ];
    }

    private function igual(mixed $a, mixed $b): bool
    {
        if (is_numeric($a) && is_numeric($b)) {
            return abs((float) $a - (float) $b) < 0.00005;
        }

        return (string) $a === (string) $b;
    }

    private function claveAlcance(array $n): string
    {
        return implode('|', array_map(fn ($c) => (string) $n[$c], self::ALCANCE));
    }

    private function fecha(mixed $v): string
    {
        $v = trim((string) $v);
        if ($v === '' || $v === '0000-00-00') {
            return '0000-00-00';
        }
        if (preg_match('#^(\d{2})/(\d{2})/(\d{4})$#', $v, $m)) {
            return "{$m[3]}-{$m[2]}-{$m[1]}";
        }

        return substr($v, 0, 10);
    }
}
