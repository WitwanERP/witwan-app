<?php

namespace App\Http\Middleware;

use App\Services\CotizacionService;
use App\Services\MenuService;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * La vista raíz (Blade) que monta Inertia.
     */
    protected $rootView = 'app';

    /**
     * Props compartidas con TODAS las páginas Inertia.
     */
    public function share(Request $request): array
    {
        return array_merge(parent::share($request), [
            // Usuario logueado (sesión web compartida con el CI vía AuthenticateFromCiSession).
            'auth' => [
                'user' => $this->userPayload($request),
            ],

            // Tenant resuelto por el middleware ResolveTenant (reemplaza LICENCIA/LICPAIS de CI).
            'tenant' => app()->bound('tenant') ? [
                'licencia' => app('tenant')->licencia ?? null,
                // Nombre comercial de la licencia: por ahora es el title de todas
                // las páginas (ver app.blade.php / app.js).
                'nombre' => app('tenant')->row->licencia_nombre ?? null,
                'pais' => app('tenant')->pais ?? null,
                'base' => app('tenant')->base ?? null,
                // Logo de la licencia: lo sirve el CI desde el mismo dominio
                // (/upfiles/{licencia_base}/logos/logo.png), igual que la
                // cabecera legacy. Si no existe, el front cae al texto.
                'logo' => ($base = trim((string) (app('tenant')->base ?? ''))) !== ''
                    ? "/upfiles/{$base}/logos/logo.png"
                    : null,
            ] : null,

            // Botonera por sistema → grupo → ítems (filtrada por permisos del rol).
            'menu' => fn () => $this->menu($request),

            // Cotizaciones del día para el header (dropdown de monedas del CI).
            'cotizaciones' => fn () => $this->cotizaciones($request),

            // Mensajes flash (success/error) para toasts.
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
            ],
        ]);
    }

    /**
     * Datos del usuario logueado para el front (null si no hay sesión).
     * Incluye el rol (tipousuario) para mostrarlo en el dashboard/navbar.
     */
    private function userPayload(Request $request): ?array
    {
        $user = $request->user();

        if ($user === null) {
            return null;
        }

        return [
            'usuario_id' => $user->usuario_id,
            'usuario_nombre' => $user->usuario_nombre,
            'usuario_apellido' => $user->usuario_apellido ?? null,
            'usuario_mail' => $user->usuario_mail,
            'usuario_login' => $user->usuario_login ?? null,
            'rol' => $user->tipousuario?->tipousuario_nombre,
            'interno' => ($user->usuario_interno ?? null) === 'Y',
        ];
    }

    /**
     * Cotizaciones del día para el header. Null si no hay sesión, o si la tabla
     * no está disponible: el header es chrome, no puede tumbar la página.
     */
    private function cotizaciones(Request $request): ?array
    {
        if ($request->user() === null) {
            return null;
        }

        try {
            return app(CotizacionService::class)->ultimas();
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Botonera del usuario logueado (vacía si no hay sesión o tenant).
     */
    private function menu(Request $request): array
    {
        $user = $request->user();

        if ($user === null || ! app()->bound('tenant')) {
            return [];
        }

        return app(MenuService::class)->forUser($user, (int) app('tenant')->licencia);
    }
}
