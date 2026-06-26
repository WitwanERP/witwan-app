<?php

namespace Tests\Feature\Reservas;

use App\Support\Licencia;
use Tests\TestCase;

/** Resolución de ramas por licencia (config/reservas.php) vía Licencia::flag(). */
class LicenciaTest extends TestCase
{
    private function tenant(string $base): void
    {
        app()->instance('tenant', (object) ['base' => $base, 'pais' => 'AR', 'licencia' => 1]);
    }

    public function test_base_y_es(): void
    {
        $this->tenant('witwan_rays');
        $this->assertSame('witwan_rays', Licencia::base());
        $this->assertTrue(Licencia::es('witwan_rays', 'mundotour_sdg'));
        $this->assertFalse(Licencia::es('witwan_med'));
    }

    public function test_flag_lista_de_pertenencia(): void
    {
        $this->tenant('witwan_rays');
        $this->assertTrue(Licencia::flag('eliminar_forzado_off')); // rays está en la lista
        $this->assertFalse(Licencia::flag('pago_online_on'));       // rays no

        $this->tenant('witwan_med');
        $this->assertTrue(Licencia::flag('pago_online_on'));
        $this->assertFalse(Licencia::flag('eliminar_forzado_off'));
        $this->assertTrue(Licencia::flag('facturado_med'));
    }

    public function test_flag_mapa_licencia_valor(): void
    {
        $this->tenant('witwan_tower');
        $this->assertSame('2016-10-12', Licencia::flag('fecha_alta_min'));

        $this->tenant('witwan_med');
        $this->assertSame('2016-10-27', Licencia::flag('fecha_alta_min'));

        $this->tenant('witwan_rays');
        $this->assertFalse(Licencia::flag('fecha_alta_min')); // sin entrada -> default false

        $this->tenant('witwan_mybeds');
        $this->assertSame(71756, Licencia::flag('mybeds_corte_servicio'));
    }

    public function test_sin_tenant_no_rompe(): void
    {
        app()->forgetInstance('tenant');
        $this->assertSame('', Licencia::base());
        $this->assertFalse(Licencia::es('witwan_rays'));
    }
}
