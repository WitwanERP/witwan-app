<?php

namespace App\Http\Middleware;

use App\Services\TenantManager;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resuelve el tenant a partir del host de la request y apunta la conexión por
 * defecto a la base de datos de ese tenant. Debe correr al inicio del stack
 * (antes de StartSession) para que la sesión y todo lo demás use la BD correcta.
 *
 * - Host que matchea una licencia (app_url o licencia_url) => configura el tenant.
 * - localhost / 127.0.0.1 sin match => fallback al .env base (dev).
 * - Cualquier otro host sin match => 404 (aislamiento de tenants).
 */
class ResolveTenant
{
    public function handle(Request $request, Closure $next): Response
    {
        $host = strtolower($request->getHost());

        // Dev: localhost/127.0.0.1 nunca es un tenant -> conexión por defecto del .env
        // base (sin consultar la brain).
        if (in_array($host, ['localhost', '127.0.0.1'], true)) {
            return $next($request);
        }

        $licencia = TenantManager::resolveFromHost($host);

        if ($licencia) {
            TenantManager::configure($licencia);

            return $next($request);
        }

        abort(404, "Tenant no encontrado para el dominio: {$host}");
    }
}
