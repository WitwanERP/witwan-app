<?php

namespace Tests\Feature\Contabilidad;

use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\CreaEsquemaConfiguracion;
use Tests\TestCase;

class CierreContableTest extends TestCase
{
    use CreaEsquemaConfiguracion;

    protected function setUp(): void
    {
        parent::setUp();
        $this->crearEsquemaConfiguracion();
        $this->autenticarPow();
    }

    public function test_lista_crea_y_abre_cierres(): void
    {
        DB::table('cierrecaja')->insert(['cierrecaja_id' => 1, 'cierrecaja_fecha' => '2026-06-30', 'fk_usuario_id' => 7, 'regdate' => '2026-07-01 10:00:00']);
        $this->get('/app/contabilidad/cierres')->assertOk()->assertInertia(fn (Assert $p) => $p
            ->component('Abm/Index')->has('registros.data', 1)
            ->where('registros.data.0.fk_usuario_id', 'Ana Pow')
            ->where('config.acciones', ['crear', 'eliminar']));

        $this->post('/app/contabilidad/cierres', ['cierrecaja_fecha' => '2026-07-31'])->assertRedirect('/app/contabilidad/cierres');
        $this->assertDatabaseHas('cierrecaja', ['cierrecaja_fecha' => '2026-07-31', 'fk_usuario_id' => 7]);
        $this->from('/app/contabilidad/cierres/create')->post('/app/contabilidad/cierres', ['cierrecaja_fecha' => ''])->assertSessionHasErrors(['cierrecaja_fecha']);

        $this->delete('/app/contabilidad/cierres/1')->assertRedirect('/app/contabilidad/cierres');
        $this->assertDatabaseMissing('cierrecaja', ['cierrecaja_id' => 1]);
    }
}
