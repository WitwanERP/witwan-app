<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Resolución del `fk_seccion_id` que usa el sistema de permisos del CI.
 *
 * En el legacy el id no está escrito en ningún lado: se deduce en runtime
 * comparando la URI actual contra el árbol del sidebar que baja de brain
 * (`Admin_Controller.php:658-676`). Acá se resuelve contra `brain.seccion`
 * filtrada por las secciones habilitadas para la licencia, con override manual
 * en config/secciones.php.
 *
 * Fail-closed a propósito: si no se puede resolver, lanza. Devolver 0 sería peor,
 * porque `PermisoHelper::tienePermiso(0, ...)` deniega a todos salvo POW y eso se
 * lee como "el usuario no tiene permiso" cuando en realidad la configuración está
 * rota.
 */
final class Secciones
{
    public const FACTURA_TERCERO = 'administracion/factura3ero';

    public const FACTURA_TERCERO_MULTIPLE = 'administracion/factura3erom';

    public const SUBDIARIO_COMPRA = 'administracion/factura3ero/subdiariocompra';

    /**
     * Id de sección para una URI del CI (sin barra inicial).
     *
     * @throws RuntimeException si no hay tenant resuelto o la sección no existe.
     */
    public static function id(string $uriCi): int
    {
        $uri = ltrim(trim($uriCi), '/');

        $override = config("secciones.overrides.{$uri}");
        if ($override !== null) {
            return (int) $override;
        }

        if (! app()->bound('tenant')) {
            throw new RuntimeException("No hay tenant resuelto: no se puede determinar la sección de '{$uri}'.");
        }

        $licenciaId = (int) app('tenant')->licencia;
        $clave = "seccion.{$licenciaId}.{$uri}";

        $id = Cache::remember($clave, (int) config('secciones.cache_ttl', 3600),
            fn () => self::buscarEnBrain($uri, $licenciaId));

        if (! $id) {
            // No cachear el fallo: puede ser configuración a medio cargar.
            Cache::forget($clave);

            throw new RuntimeException("La sección '{$uri}' no está definida en brain para la licencia {$licenciaId}.");
        }

        return $id;
    }

    /** Igual que id() pero devuelve null en lugar de lanzar. */
    public static function idONull(string $uriCi): ?int
    {
        try {
            return self::id($uriCi);
        } catch (RuntimeException) {
            return null;
        }
    }

    /**
     * Busca la sección habilitada para la licencia. Primero por coincidencia
     * exacta (con y sin barra inicial, porque brain guarda ambas formas) y, si no
     * aparece, por la URI más larga que sea prefijo de la buscada —que es el
     * criterio con el que el legacy termina resolviendo las sub-URLs.
     */
    private static function buscarEnBrain(string $uri, int $licenciaId): ?int
    {
        $base = fn () => DB::connection('license')
            ->table('seccion as s')
            ->join('rel_licenciaseccion as r', 'r.fk_seccion_id', '=', 's.seccion_id')
            ->where('r.fk_licencia_id', $licenciaId);

        $exacta = $base()
            ->whereIn('s.seccion_uri', [$uri, '/'.$uri])
            ->value('s.seccion_id');

        if ($exacta) {
            return (int) $exacta;
        }

        $candidatas = $base()
            ->where('s.seccion_uri', '<>', '')
            ->orderByRaw('LENGTH(s.seccion_uri) DESC')
            ->get(['s.seccion_id', 's.seccion_uri']);

        foreach ($candidatas as $sec) {
            if (str_starts_with($uri, ltrim((string) $sec->seccion_uri, '/'))) {
                return (int) $sec->seccion_id;
            }
        }

        return null;
    }
}
