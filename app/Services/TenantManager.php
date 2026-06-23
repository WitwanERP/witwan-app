<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Resuelve y configura el tenant (licencia) en tiempo de ejecución a partir del
 * host de la request. La fuente de verdad es la tabla `licencia` de la BD central
 * `witwan_brain` (conexión `license`): cada licencia define en qué base de datos
 * vive su data (licencia_base) y en qué servidor (host_db).
 *
 * Un mismo tenant se accede por dos URLs:
 *   - app_url      => dominio backend de API   (ej. app-rays.witwan.com)
 *   - licencia_url => dominio web "real"        (ej. rays.witwan.com)
 * Ambas resuelven a la misma fila de `licencia`, así que ambas usan el mismo .env
 * lógico (la misma BD del tenant) sin necesidad de archivos .env.app-* por dominio.
 *
 * Las credenciales (usuario/clave) son compartidas y viven en el .env base; solo
 * se usan user_db/pass_db de la licencia si están poblados (override por tenant).
 */
class TenantManager
{
    /**
     * Busca la licencia que corresponde a un host, matcheando contra app_url
     * (dominio API) o licencia_url (dominio web). Devuelve null si no hay match
     * o si la fila no tiene base de datos asociada.
     */
    public static function resolveFromHost(string $host): ?object
    {
        $host = strtolower(trim($host));

        if ($host === '') {
            return null;
        }

        try {
            $licencia = DB::connection('license')
                ->table('licencia')
                ->where(function ($q) use ($host) {
                    $q->whereRaw('LOWER(TRIM(app_url)) = ?', [$host])
                        ->orWhereRaw('LOWER(TRIM(licencia_url)) = ?', [$host]);
                })
                ->orderByDesc('prioridad')
                ->first();
        } catch (\Throwable $e) {
            // La brain no está disponible: no resolvemos tenant (la request caerá en 404).
            Log::error("ResolveTenant: error consultando licencias para '{$host}': {$e->getMessage()}");

            return null;
        }

        if (! $licencia || trim((string) ($licencia->licencia_base ?? '')) === '') {
            return null;
        }

        return $licencia;
    }

    /**
     * Apunta la conexión por defecto (mysql) a la base de datos del tenant y
     * deja la licencia disponible en el contenedor como `tenant`.
     */
    public static function configure(object $licencia): void
    {
        $default = config('database.default');
        $base = config("database.connections.{$default}");

        $userDb = trim((string) ($licencia->user_db ?? ''));
        $passDb = (string) ($licencia->pass_db ?? '');
        $hostDb = trim((string) ($licencia->host_db ?? ''));

        // Host y credenciales van acoplados (cada servidor tiene su propio usuario/clave).
        // Por eso solo usamos host_db/credenciales de la licencia cuando trae un override
        // COMPLETO (usuario + clave). Si no, los tenants comparten host+usuario+clave del
        // .env base (todos viven en el mismo servidor) y solo cambia el nombre de la BD.
        $fullOverride = $userDb !== '' && $passDb !== '';

        config(["database.connections.{$default}" => array_merge($base, [
            'host'     => $fullOverride && $hostDb !== '' ? $hostDb : $base['host'],
            'database' => trim((string) $licencia->licencia_base),
            'username' => $fullOverride ? $userDb : $base['username'],
            'password' => $fullOverride ? $passDb : $base['password'],
        ])]);

        // Reabrir la conexión con la nueva configuración.
        DB::purge($default);
        DB::reconnect($default);

        app()->instance('tenant', (object) [
            'licencia' => $licencia->licencia_id,
            'pais'     => $licencia->licencia_pais ?? null,
            'base'     => $licencia->licencia_base,
            'row'      => $licencia,
        ]);
    }
}
