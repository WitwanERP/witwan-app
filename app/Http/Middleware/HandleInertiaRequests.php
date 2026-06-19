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
            // Usuario logueado (sesión web). Null mientras el auth no esté cableado.
            'auth' => [
                'user' => $request->user()?->only(['usuario_id', 'usuario_nombre', 'usuario_mail']),
            ],

            // Tenant resuelto por el middleware ResolveTenant (reemplaza LICENCIA/LICPAIS de CI).
            'tenant' => app()->bound('tenant') ? [
                'licencia' => app('tenant')->licencia ?? null,
                'pais'     => app('tenant')->pais ?? null,
            ] : null,

            // Mensajes flash (success/error) para toasts.
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error'   => fn () => $request->session()->get('error'),
            ],
        ]);
    }
}
