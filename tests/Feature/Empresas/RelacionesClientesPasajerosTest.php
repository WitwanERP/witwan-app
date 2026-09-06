<?php

namespace Tests\Feature\Empresas;

use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\CreaEsquemaConfiguracion;
use Tests\TestCase;

/**
 * Tags, tarifarios por sistema, extras JSON del pasajero y relaciones
 * recíprocas cliente ↔ pasajero / cliente ↔ cliente sobre las pantallas
 * existentes de /app/clientes y /app/pasajeros.
 */
class RelacionesClientesPasajerosTest extends TestCase
{
    use CreaEsquemaConfiguracion;

    protected function setUp(): void
    {
        parent::setUp();
        $this->crearEsquemaConfiguracion();
        $this->autenticarPow();

        DB::table('pais')->insert(['pais_id' => 1, 'pais_nombre' => 'Argentina']);
        DB::table('ciudad')->insert(['ciudad_id' => 1, 'ciudad_nombre' => 'Buenos Aires', 'fk_pais_id' => 1]);
        DB::table('moneda')->insert(['moneda_id' => 'USD', 'moneda_nombre' => 'Dólar', 'moneda_basica' => 'Y']);
        DB::table('tag')->insert([['tag_id' => 1, 'tag_nombre' => 'VIP', 'tag_ruc' => 1, 'tag_rup' => 1], ['tag_id' => 2, 'tag_nombre' => 'Corporativo', 'tag_ruc' => 1, 'tag_rup' => 0]]);
        DB::table('tarifario')->insert([['tarifario_id' => 10, 'tarifario_nombre' => 'Receptivo std', 'fk_sistema_id' => 1], ['tarifario_id' => 20, 'tarifario_nombre' => 'Mayorista std', 'fk_sistema_id' => 2]]);
        DB::table('condicioniva')->insert(['condicioniva_id' => 1, 'condicioniva_nombre' => 'Resp. Inscripto']);
        DB::table('tipoclavefiscal')->insert(['tipoclavefiscal_id' => 3, 'tipoclavefiscal_nombre' => 'DNI']);
        DB::table('tipofactura')->insert(['tipofactura_id' => 1, 'tipofactura_nombre' => 'A']);
        DB::table('cliente')->insert([
            ['cliente_id' => 3, 'cliente_nombre' => 'AGENCIA SOL', 'cliente_razonsocial' => 'Agencia Sol SA', 'cuit' => '30-71234567-8', 'fk_pais_id' => 1],
            ['cliente_id' => 4, 'cliente_nombre' => 'VIAJES LUNA', 'cliente_razonsocial' => 'Luna SRL', 'cuit' => '30-70000000-1', 'fk_pais_id' => 1],
        ]);
        DB::table('pasajero')->insert([
            ['pasajero_id' => 50, 'pasajero_apellido' => 'PEREZ', 'pasajero_nombre' => 'ANA', 'fk_cliente_id' => 3, 'pasajero_email' => 'ana@x.com'],
            ['pasajero_id' => 51, 'pasajero_apellido' => 'GOMEZ', 'pasajero_nombre' => 'LUIS', 'fk_cliente_id' => 0, 'pasajero_email' => 'luis@x.com'],
        ]);
    }

    private function cliente(array $extra = []): array
    {
        return $extra + [
            'cliente_nombre' => 'NUEVO CLIENTE', 'cliente_razonsocial' => 'Nuevo Cliente SA', 'cliente_direccionfiscal' => 'Calle 1', 'fk_pais_id' => 1, 'fk_ciudad_id' => 1,
            'cliente_email' => 'nuevo@x.com', 'cuit' => '22222222', 'fk_tipoclavefiscal_id' => 3, 'fk_condicioniva_id' => 1, 'fk_tipofactura_id' => 1,
            'fk_tarifario1_id' => 10, 'fk_tarifario2_id' => 20, 'habilita' => 'Y',
        ];
    }

    public function test_alta_de_cliente_guarda_tags_tarifarios_por_sistema_y_relaciones_reciprocas(): void
    {
        $this->post('/app/clientes', $this->cliente([
            'tags' => [1, 2],
            'pax_relacionados' => [['paxrel_id' => 50, 'paxrel_vinculo' => 'Empleado'], ['paxrel_id' => '', 'paxrel_vinculo' => '']],
            'cliente_relacionados' => [['clienterel_id' => 4, 'clienterel_vinculo' => 'Socio']],
        ]))->assertRedirect('/app/clientes')->assertSessionHas('success');

        $c = DB::table('cliente')->where('cliente_nombre', 'NUEVO CLIENTE')->first();
        $this->assertNotNull($c);
        $this->assertEqualsCanonicalizing([1, 2], DB::table('rel_clientetag')->where('fk_cliente_id', $c->cliente_id)->pluck('fk_tag_id')->map(fn ($x) => (int) $x)->all());
        $this->assertSame([1 => 10, 2 => 20], DB::table('rel_clientesistema')->where('fk_cliente_id', $c->cliente_id)->pluck('fk_tarifario_id', 'fk_sistema_id')->map(fn ($x) => (int) $x)->all());

        $extras = DB::table('cliente_extra')->where('fk_cliente_id', $c->cliente_id)->pluck('extra_valor', 'extra_nombre');
        $this->assertSame([['paxrel_id' => '50', 'paxrel_vinculo' => 'Empleado']], json_decode($extras['pax_relacionados'], true), 'la fila vacía se descarta');
        $this->assertSame('4', json_decode($extras['cliente_relacionados'], true)[0]['clienterel_id']);

        // Recíprocas: el pasajero 50 y el cliente 4 apuntan al nuevo cliente.
        $this->assertSame((string) $c->cliente_id, json_decode(DB::table('pasajero_extra')->where('fk_pasajero_id', 50)->where('extra_nombre', 'cliente_relacionados')->value('extra_valor'), true)[0]['clienterel_id']);
        $this->assertSame((string) $c->cliente_id, json_decode(DB::table('cliente_extra')->where('fk_cliente_id', 4)->where('extra_nombre', 'cliente_relacionados')->value('extra_valor'), true)[0]['clienterel_id']);
    }

    public function test_editar_cliente_reemplaza_relaciones_limpia_reciprocas_viejas_y_el_form_las_muestra(): void
    {
        DB::table('cliente_extra')->insert([
            ['fk_cliente_id' => 3, 'extra_nombre' => 'cliente_relacionados', 'extra_valor' => '[{"clienterel_id":"4","clienterel_vinculo":"Socio"}]'],
            ['fk_cliente_id' => 4, 'extra_nombre' => 'cliente_relacionados', 'extra_valor' => '[{"clienterel_id":"3","clienterel_vinculo":""},{"clienterel_id":"99","clienterel_vinculo":""}]'],
        ]);
        DB::table('rel_clientetag')->insert(['fk_cliente_id' => 3, 'fk_tag_id' => 2]);

        $this->get('/app/clientes/3/edit')->assertOk()->assertInertia(fn (Assert $p) => $p
            ->component('Clientes/Form')
            ->where('cliente.tags', [2])
            ->where('cliente.cliente_relacionados.0.clienterel_id', '4')
            ->has('opciones.tags', 2)
            ->has('opciones.pasajeros', 2)
        );

        $this->put('/app/clientes/3', ['cliente_nombre' => 'AGENCIA SOL', 'tags' => [1], 'cliente_relacionados' => [], 'fk_tarifario1_id' => 10])
            ->assertRedirect('/app/clientes');

        $this->assertSame([1], DB::table('rel_clientetag')->where('fk_cliente_id', 3)->pluck('fk_tag_id')->map(fn ($x) => (int) $x)->all());
        $this->assertSame([1 => 10], DB::table('rel_clientesistema')->where('fk_cliente_id', 3)->pluck('fk_tarifario_id', 'fk_sistema_id')->map(fn ($x) => (int) $x)->all());
        $this->assertNull(DB::table('cliente_extra')->where('fk_cliente_id', 3)->where('extra_nombre', 'cliente_relacionados')->value('extra_valor'));
        $this->assertSame([['clienterel_id' => '99', 'clienterel_vinculo' => '']], json_decode(DB::table('cliente_extra')->where('fk_cliente_id', 4)->where('extra_nombre', 'cliente_relacionados')->value('extra_valor'), true));
    }

    public function test_editar_cliente_sin_mandar_relaciones_no_las_toca(): void
    {
        DB::table('rel_clientetag')->insert(['fk_cliente_id' => 3, 'fk_tag_id' => 2]);
        $this->put('/app/clientes/3', ['cliente_nombre' => 'AGENCIA SOL 2'])->assertRedirect('/app/clientes');
        $this->assertSame([2], DB::table('rel_clientetag')->where('fk_cliente_id', 3)->pluck('fk_tag_id')->map(fn ($x) => (int) $x)->all());
    }

    public function test_alta_de_pasajero_con_extras_json_tags_y_relaciones_reciprocas(): void
    {
        $this->post('/app/pasajeros', [
            'pasajero_apellido' => 'LOPEZ', 'pasajero_nombre' => 'MARIA', 'pasajero_email' => 'maria@x.com', 'fk_usuario_vendedor' => 7, 'habilita' => 'Y', 'tags' => [1],
            'documentos' => [['pasajero_doc_tipo' => 'DNI', 'pasajero_doc_nro' => '33333333', 'pasajero_doc_paisemisor' => 1, 'pasajero_doc_emisorfecha' => '', 'pasajero_doc_vencimiento' => '2030-01-01'], ['pasajero_doc_tipo' => '', 'pasajero_doc_nro' => '']],
            'domicilios' => [['pasajero_dom_tipo' => 'Particular', 'pasajero_direccion' => 'Av. Siempre Viva 742', 'pasajero_codigopostal' => '1000', 'pasajero_dom_pais' => 1, 'pasajero_provincia' => 'CABA', 'pasajero_ciudad' => 'Buenos Aires']],
            'telefonos' => [['pasajero_tel_tipo' => 'celular', 'pasajero_tel_codpais' => '54', 'pasajero_tel_codarea' => '11', 'pasajero_telefono' => '5555-5555']],
            'emails' => [['pasajero_correo_tipo' => 'personal', 'pasajero_email' => 'maria@x.com', 'pasajero_correo_noenviar' => 0]],
            'frecuentes' => [['paxfrec_nombre' => 'Aerolíneas Plus', 'paxfrec_num' => '123', 'paxfrec_pin' => '']],
            'pax_relacionados' => [['paxrel_id' => 51, 'paxrel_vinculo' => 'Cónyuge']],
            'cliente_relacionados' => [['clienterel_id' => 3, 'clienterel_vinculo' => 'Empleado']],
        ])->assertRedirect('/app/pasajeros')->assertSessionHas('success');

        $p = DB::table('pasajero')->where('pasajero_apellido', 'LOPEZ')->first();
        $this->assertNotNull($p);
        $extras = DB::table('pasajero_extra')->where('fk_pasajero_id', $p->pasajero_id)->pluck('extra_valor', 'extra_nombre');
        $this->assertCount(1, json_decode($extras['documentos'], true), 'la fila vacía se descarta');
        $this->assertSame('33333333', json_decode($extras['documentos'], true)[0]['pasajero_doc_nro']);
        $this->assertSame('5555-5555', json_decode($extras['telefonos'], true)[0]['pasajero_telefono']);
        $this->assertSame('Aerolíneas Plus', json_decode($extras['frecuentes'], true)[0]['paxfrec_nombre']);
        $this->assertArrayNotHasKey('visas', $extras->all(), 'los extras vacíos no se insertan');
        $this->assertSame([1], DB::table('rel_pasajerotag')->where('fk_pasajero_id', $p->pasajero_id)->pluck('fk_tag_id')->map(fn ($x) => (int) $x)->all());

        $this->assertSame((string) $p->pasajero_id, json_decode(DB::table('pasajero_extra')->where('fk_pasajero_id', 51)->where('extra_nombre', 'pax_relacionados')->value('extra_valor'), true)[0]['paxrel_id']);
        $this->assertSame((string) $p->pasajero_id, json_decode(DB::table('cliente_extra')->where('fk_cliente_id', 3)->where('extra_nombre', 'pax_relacionados')->value('extra_valor'), true)[0]['paxrel_id']);

        $this->get("/app/pasajeros/{$p->pasajero_id}/edit")->assertOk()->assertInertia(fn (Assert $p2) => $p2
            ->component('Pasajeros/Form')
            ->where('pasajero.tags', [1])
            ->where('pasajero.documentos.0.pasajero_doc_nro', '33333333')
            ->where('pasajero.visas', [])
            ->where('pasajero.pax_relacionados.0.paxrel_id', '51')
        );
    }

    public function test_editar_pasajero_quita_relaciones_y_limpia_reciprocas(): void
    {
        DB::table('pasajero_extra')->insert([
            ['fk_pasajero_id' => 50, 'extra_nombre' => 'pax_relacionados', 'extra_valor' => '[{"paxrel_id":"51","paxrel_vinculo":"Cónyuge"}]'],
            ['fk_pasajero_id' => 51, 'extra_nombre' => 'pax_relacionados', 'extra_valor' => '[{"paxrel_id":"50","paxrel_vinculo":""}]'],
            ['fk_pasajero_id' => 50, 'extra_nombre' => 'telefonos', 'extra_valor' => '[{"pasajero_telefono":"1"}]'],
        ]);
        $this->put('/app/pasajeros/50', ['pasajero_apellido' => 'PEREZ', 'pasajero_nombre' => 'ANA', 'pasajero_email' => 'ana@x.com', 'fk_usuario_vendedor' => 7, 'pax_relacionados' => []])
            ->assertRedirect('/app/pasajeros');
        $this->assertNull(DB::table('pasajero_extra')->where('fk_pasajero_id', 50)->where('extra_nombre', 'pax_relacionados')->value('extra_valor'));
        $this->assertNull(DB::table('pasajero_extra')->where('fk_pasajero_id', 51)->where('extra_nombre', 'pax_relacionados')->value('extra_valor'), 'la recíproca se limpia');
        $this->assertNotNull(DB::table('pasajero_extra')->where('fk_pasajero_id', 50)->where('extra_nombre', 'telefonos')->value('extra_valor'), 'los extras no enviados no se tocan');
    }
}
