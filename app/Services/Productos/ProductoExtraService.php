<?php

namespace App\Services\Productos;

use Illuminate\Support\Facades\DB;

/**
 * EAV `producto_extra` (fk_producto_id, extra_nombre, extra_valor).
 *
 * La tabla no tiene PK ni UNIQUE, así que el `REPLACE INTO` del legacy
 * (hotel.php:257) degenera en INSERT y deja claves repetidas; `byid()` las lee
 * con ORDER BY regdate y se queda con la última (producto_model.php:766).
 * Acá cada clave se escribe por diferencia: si no cambió no se toca, si cambió
 * se borran todas las filas de esa clave y se inserta una, si queda vacía se
 * borra (hotel.php:261 borraba las vacías al final).
 */
class ProductoExtraService
{
    /** @return array<string,string> extra_nombre => extra_valor (la última si hay repetidas) */
    public function leer(int $productoId): array
    {
        $extras = [];
        $filas = DB::table('producto_extra')
            ->where('fk_producto_id', $productoId)
            ->orderBy('regdate')
            ->get(['extra_nombre', 'extra_valor']);

        foreach ($filas as $f) {
            $extras[(string) $f->extra_nombre] = (string) $f->extra_valor;
        }

        return $extras;
    }

    /**
     * Sincroniza las claves indicadas. Las claves que no estén en $valores no se
     * tocan (el CI guarda extras que no están en el form, como `bases` o
     * `textolibre`, y no hay que perderlas).
     *
     * @param  array<string,mixed>  $valores  clave => valor ('' o null = borrar)
     * @return array{insertadas:int, borradas:int, sin_cambios:int}
     */
    public function sincronizar(int $productoId, array $valores): array
    {
        $actuales = $this->leer($productoId);
        $stats = ['insertadas' => 0, 'borradas' => 0, 'sin_cambios' => 0];

        foreach ($valores as $clave => $valor) {
            $nuevo = $this->normalizar($valor);
            $existe = array_key_exists($clave, $actuales);

            if ($nuevo === '') {
                if ($existe) {
                    DB::table('producto_extra')->where('fk_producto_id', $productoId)->where('extra_nombre', $clave)->delete();
                    $stats['borradas']++;
                }

                continue;
            }

            if ($existe && $actuales[$clave] === $nuevo && $this->sinDuplicados($productoId, $clave)) {
                $stats['sin_cambios']++;

                continue;
            }

            DB::table('producto_extra')->where('fk_producto_id', $productoId)->where('extra_nombre', $clave)->delete();
            DB::table('producto_extra')->insert([
                'fk_producto_id' => $productoId,
                'extra_nombre' => $clave,
                'extra_valor' => $nuevo,
                'regdate' => now()->format('Y-m-d H:i:s'),
            ]);
            $stats['insertadas']++;
        }

        return $stats;
    }

    public function borrarTodas(int $productoId): void
    {
        DB::table('producto_extra')->where('fk_producto_id', $productoId)->delete();
    }

    /** Copia todos los extras de un producto a otro (clonar). */
    public function copiar(int $desde, int $hacia): void
    {
        foreach ($this->leer($desde) as $clave => $valor) {
            DB::table('producto_extra')->insert([
                'fk_producto_id' => $hacia,
                'extra_nombre' => $clave,
                'extra_valor' => $valor,
                'regdate' => now()->format('Y-m-d H:i:s'),
            ]);
        }
    }

    private function normalizar(mixed $valor): string
    {
        if ($valor === null || $valor === false) {
            return '';
        }
        if (is_bool($valor)) {
            return '1';
        }
        if (is_array($valor)) {
            return json_encode($valor, JSON_UNESCAPED_UNICODE);
        }

        return trim((string) $valor);
    }

    private function sinDuplicados(int $productoId, string $clave): bool
    {
        return DB::table('producto_extra')->where('fk_producto_id', $productoId)->where('extra_nombre', $clave)->count() === 1;
    }
}
