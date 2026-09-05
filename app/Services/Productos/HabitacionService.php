<?php

namespace App\Services\Productos;

use App\Exceptions\Productos\ProductoException;
use Illuminate\Support\Facades\DB;

/**
 * Habitaciones/categorías instanciadas en un producto (`alojamientohabitacion`).
 *
 * En el CI se editan por AJAX aparte del form (producto.php:935,
 * `guardarhabitacion`, inputs `campo_ID`) y se borran con `eliminarhabitacion`
 * sin mirar si tienen tarifas; el flag `ctrl` (producto_model.php:895-900) sólo
 * deshabilitaba el botón en el navegador. Acá la regla "no se puede borrar ni
 * deshabilitar una categoría con tarifas cargadas" vive en el service.
 */
class HabitacionService
{
    /** @return list<array> ordenadas por `orden`, con nombre efectivo y flag tiene_tarifas */
    public function listar(int $productoId, bool $soloHabilitadas = false): array
    {
        $q = DB::table('alojamientohabitacion as h')
            ->leftJoin('tarifacategoria as tc', 'tc.tarifacategoria_id', '=', 'h.fk_tarifacategoria_id')
            ->where('h.fk_producto_id', $productoId)
            ->orderBy('h.orden')
            ->orderBy('h.alojamientohabitacion_id')
            ->select('h.*', 'tc.tarifacategoria_nombre');

        if ($soloHabilitadas) {
            $q->where('h.habilitar', 1);
        }

        $conTarifas = $this->categoriasConTarifas($productoId);

        return $q->get()->map(function ($h) use ($conTarifas) {
            $fila = (array) $h;
            // producto_model.php:889: el nombre propio pisa al del catálogo.
            $fila['nombre'] = trim((string) $h->alojamientohabitacion_nombre) !== '' && $h->alojamientohabitacion_nombre !== '0'
                ? (string) $h->alojamientohabitacion_nombre
                : (string) ($h->tarifacategoria_nombre ?? '');
            $fila['tiene_tarifas'] = in_array((int) $h->fk_tarifacategoria_id, $conTarifas, true);

            return $fila;
        })->all();
    }

    /**
     * Sincroniza la lista completa del formulario: alta (sin id), edición (con
     * id) y baja (ids que ya no vienen). Devuelve los ids resultantes.
     *
     * @param  list<array>  $habitaciones  alojamientohabitacion_id?, fk_tarifacategoria_id, nombre, textolibre, capacidad, min_adultos, max_adultos, max_child, max_adultos_child, orden, habilitar
     * @return list<int>
     */
    public function sincronizar(int $productoId, array $habitaciones): array
    {
        $existentes = DB::table('alojamientohabitacion')->where('fk_producto_id', $productoId)->get()->keyBy('alojamientohabitacion_id');
        $conTarifas = $this->categoriasConTarifas($productoId);
        $vistos = [];
        $errores = [];

        foreach ($habitaciones as $i => $h) {
            $id = (int) ($h['alojamientohabitacion_id'] ?? 0);
            $fila = $this->fila($h);

            if ($id !== 0 && $existentes->has($id)) {
                $actual = $existentes->get($id);
                $tiene = in_array((int) $actual->fk_tarifacategoria_id, $conTarifas, true);
                if ($tiene && (int) $fila['habilitar'] === 0) {
                    $errores["habitaciones.{$i}.habilitar"] = 'No se puede deshabilitar una categoría con tarifas cargadas.';

                    continue;
                }
                // La categoría no se cambia una vez creada (guardarhabitacion la deja comentada).
                unset($fila['fk_tarifacategoria_id']);
                DB::table('alojamientohabitacion')->where('alojamientohabitacion_id', $id)->update($fila);
                $vistos[] = $id;

                continue;
            }

            if ((int) $fila['fk_tarifacategoria_id'] <= 0) {
                $errores["habitaciones.{$i}.fk_tarifacategoria_id"] = 'Debe elegir la categoría.';

                continue;
            }
            $fila['fk_producto_id'] = $productoId;
            $vistos[] = (int) DB::table('alojamientohabitacion')->insertGetId($fila);
        }

        foreach ($existentes as $id => $actual) {
            if (in_array((int) $id, $vistos, true)) {
                continue;
            }
            if (in_array((int) $actual->fk_tarifacategoria_id, $conTarifas, true)) {
                $errores['habitaciones'] = "No se puede borrar la categoría \"{$actual->alojamientohabitacion_nombre}\": tiene tarifas cargadas.";

                continue;
            }
            DB::table('alojamientohabitacion')->where('alojamientohabitacion_id', $id)->delete();
        }

        if ($errores !== []) {
            throw ProductoException::porCampos($errores);
        }

        return $vistos;
    }

    public function copiar(int $desde, int $hacia): void
    {
        foreach (DB::table('alojamientohabitacion')->where('fk_producto_id', $desde)->get() as $h) {
            $fila = (array) $h;
            unset($fila['alojamientohabitacion_id']);
            $fila['fk_producto_id'] = $hacia;
            DB::table('alojamientohabitacion')->insert($fila);
        }
    }

    /** @return list<int> fk_tarifacategoria_id con al menos una tarifa en alguna vigencia del producto */
    public function categoriasConTarifas(int $productoId): array
    {
        return DB::table('tarifa')
            ->join('vigencia', 'vigencia.vigencia_id', '=', 'tarifa.fk_vigencia_id')
            ->where('vigencia.fk_producto_id', $productoId)
            ->distinct()
            ->pluck('tarifa.fk_tarifacategoria_id')
            ->map(fn ($c) => (int) $c)
            ->all();
    }

    private function fila(array $h): array
    {
        $capacidad = (int) ($h['capacidad'] ?? 0);
        $minAd = (int) ($h['min_adultos'] ?? 0);

        // guardarhabitacion: min_adultos_child se graba igual que min_adultos.
        return [
            'fk_tarifacategoria_id' => (int) ($h['fk_tarifacategoria_id'] ?? 0),
            'alojamientohabitacion_nombre' => trim((string) ($h['nombre'] ?? $h['alojamientohabitacion_nombre'] ?? '')),
            'textolibre' => trim((string) ($h['textolibre'] ?? '')),
            'capacidad' => $capacidad,
            'min_adultos' => $minAd,
            'min_adultos_child' => $minAd,
            'max_adultos' => (int) ($h['max_adultos'] ?? 0),
            'max_child' => (int) ($h['max_child'] ?? 0),
            'max_adultos_child' => (int) ($h['max_adultos_child'] ?? 0),
            'orden' => (int) ($h['orden'] ?? 0),
            'habilitar' => (int) ($h['habilitar'] ?? 1) === 1 ? 1 : 0,
        ];
    }
}
