<?php

namespace Tests\Unit\Documentos;

use App\Http\Controllers\Admin\Documentos\FacturaproveedorController as ApiController;
use App\Http\Controllers\Web\Documentos\DteChileController;
use App\Http\Controllers\Web\Documentos\FacturaproveedorController as WebController;
use App\Http\Controllers\Web\Documentos\FacturaproveedorMultipleController;
use App\Services\Documentos\DteChileService;
use App\Services\Documentos\FacturaproveedorAsientoService;
use App\Services\Documentos\FacturaproveedorExportService;
use App\Services\Documentos\FacturaproveedorListadoService;
use App\Services\Documentos\FacturaproveedorMultipleService;
use App\Services\Documentos\FacturaproveedorOcupacionService;
use App\Services\Documentos\FacturaproveedorService;
use App\Services\Documentos\FacturaproveedorSplitService;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * El contenedor tiene que poder construir todo el módulo sin argumentos.
 *
 * No es un test de ceremonia: una dependencia con un parámetro primitivo en el
 * constructor (le pasó a CuentasContables) revienta recién al resolver el
 * controller, es decir en el primer request real. Acá se detecta en CI.
 */
class CableadoTest extends TestCase
{
    public static function clases(): array
    {
        return array_map(fn ($c) => [$c], [
            FacturaproveedorService::class,
            FacturaproveedorListadoService::class,
            FacturaproveedorExportService::class,
            FacturaproveedorAsientoService::class,
            FacturaproveedorSplitService::class,
            FacturaproveedorOcupacionService::class,
            FacturaproveedorMultipleService::class,
            DteChileService::class,
            WebController::class,
            ApiController::class,
            FacturaproveedorMultipleController::class,
            DteChileController::class,
        ]);
    }

    #[DataProvider('clases')]
    public function test_el_contenedor_puede_construirlo(string $clase): void
    {
        $this->assertInstanceOf($clase, app($clase));
    }

    /** Sin ext-soap o sin credenciales, la pantalla avisa en vez de reventar. */
    public function test_el_servicio_de_dte_declara_si_esta_disponible(): void
    {
        config(['dte_chile.ambiente' => null]);

        $this->assertFalse(app(DteChileService::class)->configurado());
        $this->assertFalse(app(DteChileService::class)->disponible());
    }
}
