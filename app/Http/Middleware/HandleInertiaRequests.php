<?php

namespace App\Http\Middleware;

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
                'pais' => app('tenant')->pais ?? null,
            ] : null,

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
}
