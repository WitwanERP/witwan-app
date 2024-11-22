<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

use App\Http\Controllers\AuthController;
use App\Http\Controllers\Reservas\ReservaController;
use App\Http\Controllers\ProcesoController;
/**Ciudades */
use App\Http\Controllers\Ciudades\CiudadController;
use App\Http\Controllers\Ciudades\PaisController;


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



Route::group([
    'middleware' => ['api', 'jwt.auth'],
    'prefix' => 'ciudad'
], function () {
    Route::get('/', [CiudadController::class, 'index']);
    Route::post('/', [CiudadController::class, 'store']);
    Route::get('/{id}', [CiudadController::class, 'show']);
    Route::put('/{id}', [CiudadController::class, 'update']);
    Route::delete('/{id}', [CiudadController::class, 'destroy']);
    Route::get('/search', [CiudadController::class, 'search']);
});


Route::group([
    'middleware' => ['api', 'jwt.auth'],
    'prefix' => 'pais'
], function () {
    Route::get('/', [PaisController::class, 'index']);
    Route::post('/', [PaisController::class, 'store']);
    Route::get('/{id}', [PaisController::class, 'show']);
    Route::put('/{id}', [PaisController::class, 'update']);
    Route::delete('/{id}', [PaisController::class, 'destroy']);
    Route::get('/search', [PaisController::class, 'search']);
});
