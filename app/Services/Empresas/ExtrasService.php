<?php

namespace App\Services\Empresas;

use Illuminate\Support\Facades\DB;

/**
 * Acceso a las tablas `cliente_extra` / `pasajero_extra` del legacy: pares
 * (extra_nombre, extra_valor) por entidad, donde los valores compuestos son
 * JSON (contactos, documentos, pax_relacionados, cliente_relacionados…).
 *
 * El CI mantenía las relaciones recíprocas (cliente ↔ pasajero, cliente ↔
 * cliente) editando el JSON con str_replace; acá se decodifica y re-encodea.
 */
class ExtrasService
{
    /** Extras compuestos que se guardan como JSON. */
    public const JSON = ['contactos', 'tarjetas', 'pax_relacionados', 'cliente_relacionados', 'documentos', 'domicilios', 'visas', 'telefonos', 'emails', 'frecuentes'];

    /** @return array<string,mixed> extra_nombre => valor (decodificado si es JSON) */
    public function leer(string $tabla, string $fk, int $id): array
    {
        $out = [];
        foreach (DB::table($tabla)->where($fk, $id)->get(['extra_nombre', 'extra_valor']) as $r) {
            $out[$r->extra_nombre] = in_array($r->extra_nombre, self::JSON, true)
                ? (json_decode((string) $r->extra_valor, true) ?: [])
                : $r->extra_valor;
        }

        return $out;
    }

    /** Reemplaza todos los extras de la entidad (como el DELETE + INSERT del legacy). Omite vacíos. */
    public function reemplazar(string $tabla, string $fk, int $id, array $extras): void
    {
        DB::table($tabla)->where($fk, $id)->delete();
        foreach ($extras as $nombre => $valor) {
            if ($valor === null || $valor === '' || $valor === []) {
                continue;
            }
            DB::table($tabla)->insert([
                $fk => $id,
                'extra_nombre' => $nombre,
                'extra_valor' => is_array($valor) ? json_encode(array_values($valor), JSON_UNESCAPED_UNICODE) : (string) $valor,
            ]);
        }
    }

    /** Quita de todas las filas de `$extraNombre` los ítems cuyo `$clave` apunte a `$id`. */
    public function quitarReferencias(string $tabla, string $fk, string $extraNombre, string $clave, int $id): void
    {
        foreach (DB::table($tabla)->where('extra_nombre', $extraNombre)->get([$fk, 'extra_valor']) as $r) {
            $items = json_decode((string) $r->extra_valor, true);
            if (! is_array($items)) {
                continue;
            }
            $filtrados = array_values(array_filter($items, fn ($i) => (int) ($i[$clave] ?? 0) !== $id));
            if (count($filtrados) === count($items)) {
                continue;
            }
            $q = DB::table($tabla)->where($fk, $r->{$fk})->where('extra_nombre', $extraNombre);
            $filtrados === [] ? $q->delete() : $q->update(['extra_valor' => json_encode($filtrados, JSON_UNESCAPED_UNICODE)]);
        }
    }

    /** Agrega `$item` al extra `$extraNombre` de la entidad `$destino` si no estaba. */
    public function agregarReferencia(string $tabla, string $fk, string $extraNombre, int $destino, array $item, string $clave): void
    {
        $actual = DB::table($tabla)->where($fk, $destino)->where('extra_nombre', $extraNombre)->value('extra_valor');
        $items = $actual !== null ? (json_decode((string) $actual, true) ?: []) : [];
        foreach ($items as $i) {
            if ((int) ($i[$clave] ?? 0) === (int) $item[$clave]) {
                return;
            }
        }
        $items[] = $item;
        $valor = json_encode(array_values($items), JSON_UNESCAPED_UNICODE);
        if ($actual === null) {
            DB::table($tabla)->insert([$fk => $destino, 'extra_nombre' => $extraNombre, 'extra_valor' => $valor]);
        } else {
            DB::table($tabla)->where($fk, $destino)->where('extra_nombre', $extraNombre)->update(['extra_valor' => $valor]);
        }
    }

    /** Filas de un editor repetible: descarta las totalmente vacías y normaliza a string. */
    public static function filas(mixed $filas, array $claves): array
    {
        $out = [];
        foreach (is_array($filas) ? $filas : [] as $fila) {
            $item = [];
            $vacia = true;
            foreach ($claves as $k) {
                $v = $fila[$k] ?? '';
                $v = is_bool($v) ? ($v ? 1 : 0) : (is_scalar($v) ? trim((string) $v) : '');
                $item[$k] = $v;
                if ($v !== '' && $v !== '0' && $v !== 0) {
                    $vacia = false;
                }
            }
            if (! $vacia) {
                $out[] = $item;
            }
        }

        return $out;
    }
}
