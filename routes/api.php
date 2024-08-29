<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

use App\Http\Controllers\AuthController;
use App\Http\Controllers\Reservas\ReservaController;
use App\Http\Controllers\ProcesoController;

Route::group([
    'middleware' => 'api',
    'prefix' => 'auth'
], function () {
    Route::post('login', [AuthController::class, 'login']);
    Route::post('logout', [AuthController::class, 'logout']);
    Route::post('refresh', [AuthController::class, 'refresh']);
    Route::post('me', [AuthController::class, 'me']);
});

Route::group([
    'middleware' => ['api', 'jwt.auth'],
    'prefix' => 'reservas'
], function () {
    Route::get('/', [ReservaController::class, 'index']);
    Route::post('/', [ReservaController::class, 'store']);
    Route::get('/{Reserva}', [ReservaController::class, 'show']);
    // Agrega más rutas según sea necesario
});

Route::get('/procesos', [ProcesoController::class, 'ejecutar']);
