<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

use Dotenv\Dotenv;

if (isset($_SERVER['SERVER_NAME'])) {
    $host = $_SERVER['SERVER_NAME'];
    $subdomain = explode('.', $host)[0];
} else {
    $subdomain = null;
}

// Load the default environment variables
$dotenv = Dotenv::createImmutable(dirname(__DIR__));
$dotenv->load();

// Load additional environment variables based on the subdomain, if any
if ($subdomain) {
    $subdomainEnvFile =  '.env.' . $subdomain;
    if (file_exists(dirname(__DIR__) ."/". $subdomainEnvFile)) {
        $dotenv = Dotenv::createImmutable(dirname(__DIR__), ['.env',$subdomainEnvFile],false);
        $dotenv->load();
    }else{
        $dotenv = Dotenv::createImmutable(dirname(__DIR__));
        $dotenv->load();
    }
}else{
    $dotenv = Dotenv::createImmutable(dirname(__DIR__));
    $dotenv->load();
}

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        //
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
