<?php

namespace Tests\Unit\Documentos;

use App\Exceptions\Documentos\FacturaproveedorException;
use App\Services\Documentos\FacturaproveedorService;
use App\Services\Documentos\ProveedorBsp;
use Tests\TestCase;

/**
 * Reglas de negocio que el legacy validaba sólo en el navegador.
 */
class FacturaproveedorReglasTest extends TestCase
{
    private function tenant(string $base = 'licencia_test', string $pais = 'AR'): void
    {
        app()->instance('tenant', (object) ['base' => $base, 'pais' => $pais, 'licencia' => 1]);
    }

    private function servicio(): FacturaproveedorService
    {
        return app(FacturaproveedorService::class);
    }

    public function test_la_imputacion_debe_sumar_cien(): void
    {
        $this->tenant();

        $this->servicio()->validarImputacion(['areaimputacion' => [1 => 60, 2 => 40]]);

        $this->expectException(FacturaproveedorException::class);
        $this->servicio()->validarImputacion(['areaimputacion' => [1 => 60, 2 => 30]]);
    }

    public function test_una_imputacion_toda_en_cero_se_considera_no_cargada(): void
    {
        $this->tenant();

        $this->servicio()->validarImputacion(['areaimputacion' => [1 => 0, 2 => 0]]);

        $this->assertTrue(true, 'No debe exigir suma 100 si no se imputó nada');
    }

    /** En Chile el legacy acepta 100 o 200 (scriptfactura3ro.js:318-323). */
    public function test_chile_acepta_cien_o_doscientos(): void
    {
        $this->tenant('mundotour_sdg', 'CL');

        $this->servicio()->validarImputacion(['areaimputacion' => [1 => 100]]);
        $this->servicio()->validarImputacion(['areaimputacion' => [1 => 100, 2 => 100]]);

        $this->expectException(FacturaproveedorException::class);
        $this->servicio()->validarImputacion(['areaimputacion' => [1 => 150]]);
    }

    public function test_sin_imputacion_no_valida_nada(): void
    {
        $this->tenant();

        $this->servicio()->validarImputacion([]);

        $this->assertTrue(true);
    }

    public function test_el_proveedor_bsp_sale_de_la_familia_secontur(): void
    {
        // La familia incluye maldivas, morisan y alternativasur, no sólo
        // witwan_secontur (Admin_Controller.php:682).
        $this->tenant('witwan_alternativasur');
        $this->assertSame(370, ProveedorBsp::id());
        $this->assertTrue(ProveedorBsp::es(370));
        $this->assertFalse(ProveedorBsp::es(107));

        $this->tenant('witwan_hayland');
        $this->assertSame(107, ProveedorBsp::id());

        $this->tenant('mundotour_sdg');
        $this->assertNull(ProveedorBsp::id());
        $this->assertFalse(ProveedorBsp::es(370));
    }
}
