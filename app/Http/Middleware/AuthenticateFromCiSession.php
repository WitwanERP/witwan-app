<?php

namespace App\Http\Middleware;

use App\Services\CiSessionReader;
use App\Services\CiUserResolver;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Pseudo-SSO: comparte la sesión del CodeIgniter legacy con el front Inertia (/app).
 *
 * Lee la cookie de sesión de CI (mismo host), la mapea contra `ci_sessions` y deja
 * autenticado en el guard web al usuario correspondiente. El usuario se setea de
 * forma transitoria (por request, sin escribir sesión propia de Laravel), así el
 * estado refleja al instante el logout de CI.
 *
 * Debe correr DESPUÉS de ResolveTenant (la lectura de ci_sessions usa la BD del
 * tenant) y de StartSession (el guard web). La API JWT no pasa por acá.
 */
class AuthenticateFromCiSession
{
    public function __construct(
        private readonly CiSessionReader $reader,
        private readonly CiUserResolver $resolver,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $userData = $this->reader->userData($request);

        if ($userData !== null) {
            $user = $this->resolver->fromCiData($userData);
            if ($user !== null) {
                Auth::guard('web')->setUser($user);
            }
        }

        if (! Auth::guard('web')->check() && $this->shouldRedirectGuest($request)) {
            return redirect()->away((string) config('ci.login_url'));
        }

        return $next($request);
    }

    /** Sin sesión de CI válida: ¿mandamos al usuario al login del CI? */
    private function shouldRedirectGuest(Request $request): bool
    {
        if (! config('ci.redirect_guests', true) || ! config('ci.login_url')) {
            return false;
        }

        foreach ((array) config('ci.guest_paths', []) as $path) {
            if ($request->is($path) || $request->is($path.'/*')) {
                return false;
            }
        }

        return true;
    }
}
