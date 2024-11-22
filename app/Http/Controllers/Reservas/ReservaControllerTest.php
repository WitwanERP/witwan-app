<?php
namespace Tests\Unit\Http\Controllers\Reservas;

use Tests\TestCase;
use App\Models\Reservas\Reserva;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;

class ReservaControllerTest extends TestCase
{

    public function testIndex()
    {
        Reserva::factory()->count(5)->create();

        $response = $this->getJson('/api/reservas');

        $response->assertStatus(200)
                 ->assertJsonStructure([
                     'data' => [
                         '*' => [
                             'id', 'cliente', 'facturara', 'servicios', 'fecha_alta'
                         ]
                     ],
                     'processing_time'
                 ]);
    }

    public function testStore()
    {
        $data = [
            // Datos de prueba
        ];

        $response = $this->postJson('/api/reservas', $data);

        $response->assertStatus(201)
                 ->assertJsonStructure([
                     'id', 'cliente', 'facturara', 'servicios', 'fecha_alta'
                 ]);
    }

    public function testShow()
    {
        $reserva = Reserva::factory()->create();

        $response = $this->getJson("/api/reservas/{$reserva->id}");

        $response->assertStatus(200)
                 ->assertJson($reserva->toArray());
    }

    public function testUpdate()
    {
        $reserva = Reserva::factory()->create();
        $data = [
            // Datos de prueba para actualizar
        ];

        $response = $this->putJson("/api/reservas/{$reserva->id}", $data);

        $response->assertStatus(200)
                 ->assertJson($reserva->fresh()->toArray());
    }

    public function testCancel()
    {
        $reserva = Reserva::factory()->create();

        $response = $this->putJson("/api/reservas/cancel/{$reserva->id}");

        $response->assertStatus(200)
                 ->assertJson([
                     'id' => $reserva->id,
                     'fk_filestatus_id' => 'CA'
                 ]);
    }

    public function testDestroy()
    {
        $reserva = Reserva::factory()->create();

        $response = $this->deleteJson("/api/reservas/{$reserva->id}");

        $response->assertStatus(204);
        $this->assertDatabaseMissing('reservas', ['id' => $reserva->id]);
    }
}
