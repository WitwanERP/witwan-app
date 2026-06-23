<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

use Dotenv\Dotenv;

if (isset($_SERVER['SERVER_NAME'])) {
    $host = $_SERVER['SERVER_NAME'];
    $subdomain = explode('.', $host)[0];
} else {
    $subdomain = null;
}

if (isset($_SERVER['HTTP_HOST']) && ($_SERVER['HTTP_HOST'] == 'localhost:8000' || $_SERVER['HTTP_HOST'] == '127.0.0.1:8000')) {
    $subdomain = 'localhost';
}

// Load the default environment variables
$dotenv = Dotenv::createImmutable(dirname(__DIR__));
$dotenv->load();

// Load additional environment variables based on the subdomain, if any
if ($subdomain) {
    $subdomainEnvFile =  '.env.' . $subdomain;
    if (file_exists(dirname(__DIR__) . "/" . $subdomainEnvFile)) {
        $dotenv = Dotenv::createImmutable(dirname(__DIR__), ['.env', $subdomainEnvFile], false);
        $dotenv->load();
    } else {
        $dotenv = Dotenv::createImmutable(dirname(__DIR__));
        $dotenv->load();
    }
} else {
    $dotenv = Dotenv::createImmutable(dirname(__DIR__));
    $dotenv->load();
}

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        api: __DIR__ . '/../routes/api.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->trustProxies(                             // <- acá
            at: ['69.64.95.67', '127.0.0.1'],
            headers: Request::HEADER_X_FORWARDED_FOR
                   | Request::HEADER_X_FORWARDED_HOST
                   | Request::HEADER_X_FORWARDED_PROTO
                   | Request::HEADER_X_FORWARDED_PORT,
        );
        $middleware->alias([
            'jwt.auth' => \Tymon\JWTAuth\Http\Middleware\Authenticate::class,
            'jwt.refresh' => \Tymon\JWTAuth\Http\Middleware\RefreshToken::class,
            'ci.bridge' => \App\Http\Middleware\CodeIgniterBridge::class,
        ]);

        // La cookie de sesión del CI legacy no la cifra Laravel: hay que exceptuarla
        // para que EncryptCookies no la descarte y AuthenticateFromCiSession pueda
        // leerla cruda (pseudo-SSO del front /app).
        $middleware->encryptCookies(except: [
            env('CI_SESSION_COOKIE', 'ci_session'),
        ]);

        // ResolveTenant resuelve la licencia por el host y apunta la conexión por
        // defecto a la BD del tenant. Va al inicio del stack (antes de StartSession).
        $middleware->web(prepend: [
            \App\Http\Middleware\ResolveTenant::class,
        ]);
        $middleware->api(prepend: [
            \App\Http\Middleware\ResolveTenant::class,
        ]);

        // Front Inertia (/app): primero el pseudo-SSO que comparte sesión con el CI
        // legacy (deja autenticado al usuario), luego Inertia comparte auth/tenant/flash.
        // AuthenticateFromCiSession corre tras ResolveTenant y StartSession.
        $middleware->web(append: [
            \App\Http\Middleware\AuthenticateFromCiSession::class,
            \App\Http\Middleware\HandleInertiaRequests::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })
    ->withProviders([
        Tymon\JWTAuth\Providers\LaravelServiceProvider::class,
    ])
    ->create();
