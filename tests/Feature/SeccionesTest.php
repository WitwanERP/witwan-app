<?php

namespace Tests\Feature;

use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\CreaEsquemaConfiguracion;
use Tests\TestCase;

/** Vista temporal /app/secciones: índice de pantallas leído del router. */
class SeccionesTest extends TestCase
{
    use CreaEsquemaConfiguracion;

    public function test_lista_las_rutas_get_de_app_agrupadas_y_expande_las_areas(): void
    {
        $this->crearEsquemaConfiguracion();
        $this->autenticarPow();

        $this->get('/app/secciones')->assertOk()->assertInertia(fn (Assert $p) => $p
            ->component('Sistema/Secciones')
            ->has('grupos.reservas')
            ->has('grupos.config')
            ->where('grupos.reservas', fn ($items) => collect($items)->contains(fn ($it) => $it['uri'] === '/app/reservas/{area}/nueva' && count($it['links']) >= 8 && $it['links'][0]['href'] === '/app/reservas/corporativo/nueva'))
            ->where('grupos.reservas', fn ($items) => ! collect($items)->contains(fn ($it) => str_contains($it['uri'], 'nueva/clientes')), 'los autocompletes no se listan')
            ->where('grupos.config', fn ($items) => collect($items)->contains(fn ($it) => str_ends_with($it['uri'], '/{id}/edit') && $it['links'] === []), 'las rutas con id quedan sin link')
            ->where('total', fn ($t) => $t > 150)
        );
    }
}
