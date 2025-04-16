<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

use App\Http\Controllers\AuthController;
use App\Http\Controllers\Reservas\ReservaController;
use App\Http\Controllers\ProcesoController;

/**Ciudades */

use App\Http\Controllers\Ciudades\CiudadController;
use App\Http\Controllers\Ciudades\PaisController;
use App\Http\Controllers\Ciudades\RegionController;

use App\Http\Controllers\Productos\AerolineaController;

use App\Http\Controllers\Empresas\ClienteController;


use App\Http\Controllers\Configuracion\Admin\CondicionIvaController;
use App\Http\Controllers\Configuracion\Admin\TipoFacturaController;



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
    'prefix' => 'geo'
], function () {
    Route::group([
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
        'prefix' => 'pais'
    ], function () {
        Route::get('/', [PaisController::class, 'index']);
        Route::post('/', [PaisController::class, 'store']);
        Route::get('/{id}', [PaisController::class, 'show']);
        Route::put('/{id}', [PaisController::class, 'update']);
        Route::delete('/{id}', [PaisController::class, 'destroy']);
        Route::get('/search', [PaisController::class, 'search']);
    });

    Route::group([
        'prefix' => 'region'
    ], function () {
        Route::get('/', [RegionController::class, 'index']);
        Route::post('/', [RegionController::class, 'store']);
        Route::get('/{id}', [RegionController::class, 'show']);
        Route::put('/{id}', [RegionController::class, 'update']);
        Route::delete('/{id}', [RegionController::class, 'destroy']);
        Route::get('/search', [RegionController::class, 'search']);
    });
});


Route::group([
    'middleware' => ['api', 'jwt.auth'],
    'prefix' => 'aerolinea'
], function () {
    Route::get('/', [AerolineaController::class, 'index']);
    Route::post('/', [AerolineaController::class, 'store']);
    Route::get('/{id}', [AerolineaController::class, 'show']);
    Route::put('/{id}', [AerolineaController::class, 'update']);
    Route::delete('/{id}', [AerolineaController::class, 'destroy']);
    Route::get('/search', [AerolineaController::class, 'search']);
});

Route::group([
    'middleware' => ['api', 'jwt.auth'],
    'prefix' => 'cliente'
], function () {
    Route::get('/', [ClienteController::class, 'index']);
    Route::post('/', [ClienteController::class, 'store']);
    Route::get('/{id}', [ClienteController::class, 'show']);
    Route::put('/{id}', [ClienteController::class, 'update']);
    Route::delete('/{id}', [ClienteController::class, 'destroy']);
    Route::get('/search', [ClienteController::class, 'search']);
});


Route::group([
    'middleware' => ['api', 'jwt.auth'],
    'prefix' => 'configuracion'
], function () {
    Route::group([
        'prefix' => 'condicioniva'
    ], function () {
        Route::get('/', [CondicionIvaController::class, 'index']);
        Route::post('/', [CondicionIvaController::class, 'store']);
        Route::get('/{id}', [CondicionIvaController::class, 'show']);
        Route::put('/{id}', [CondicionIvaController::class, 'update']);
        Route::delete('/{id}', [CondicionIvaController::class, 'destroy']);
        Route::get('/search', [CondicionIvaController::class, 'search']);
    });
    Route::group([
        'prefix' => 'tipofactura'
    ], function () {
        Route::get('/', [TipoFacturaController::class, 'index']);
        Route::post('/', [TipoFacturaController::class, 'store']);
        Route::get('/{id}', [TipoFacturaController::class, 'show']);
        Route::put('/{id}', [TipoFacturaController::class, 'update']);
        Route::delete('/{id}', [TipoFacturaController::class, 'destroy']);
        Route::get('/search', [TipoFacturaController::class, 'search']);
    });
});
