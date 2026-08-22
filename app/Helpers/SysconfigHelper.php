<?php

namespace App\Helpers;

use App\Models\Sysconfig;
use App\Support\Licencia;
use Illuminate\Support\Facades\Cache;

/**
 * Acceso cacheado a la tabla `sysconfig` del tenant.
 *
 * OJO: la cache se particiona por licencia. `sysconfig` vive en la BD del tenant
 * (que ResolveTenant reapunta por host), así que una clave global haría que el
 * primer tenant que consulta le sirva sus valores a todos los demás. Eso importa
 * especialmente para las cuentas contables (fc3exento, cuentaproveedor, …), donde
 * una colisión contabiliza la factura de un cliente contra el plan de cuentas de
 * otro.
 */
class SysconfigHelper
{
    /** TTL de la cache, en segundos. */
    private const TTL = 3600;

    /**
     * Get a system configuration value by key
     */
    public static function get($key, $default = null)
    {
        return Cache::remember(self::clave($key), self::TTL, function () use ($key, $default) {
            $config = Sysconfig::where('sysconfig_key', $key)->first();

            return $config ? $config->sysconfig_value : $default;
        });
    }

    /**
     * Get all configurations as array
     */
    public static function all()
    {
        return Cache::remember(self::clave('all'), self::TTL, function () {
            return Sysconfig::pluck('sysconfig_value', 'sysconfig_key')->toArray();
        });
    }

    /** Invalida una clave (o todas) del tenant actual. */
    public static function olvidar(?string $key = null): void
    {
        Cache::forget(self::clave($key ?? 'all'));
    }

    /**
     * Clave de cache namespaceada por licencia. Sin tenant resuelto (consola,
     * tests) se usa '_' para no pisar los valores de ninguna licencia real.
     */
    private static function clave(string $key): string
    {
        $base = Licencia::base();

        return sprintf('sysconfig.%s.%s', $base !== '' ? $base : '_', $key);
    }
}
