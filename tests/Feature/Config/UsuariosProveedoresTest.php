<?php

namespace Tests\Feature\Config;

use App\Services\Admin\ReplicadorColectora;
use App\Services\Config\SeccionesPermisosService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia as Assert;
use Mockery;
use Tests\Concerns\CreaEsquemaConfiguracion;
use Tests\TestCase;

/**
 * Smoke HTTP de Configuración > Usuarios (tipos de usuario con matriz de
 * permisos, usuarios con API key) y Proveedores / Prestadores.
 */
class UsuariosProveedoresTest extends TestCase
{
    use CreaEsquemaConfiguracion;

    /** Árbol de secciones que devolvería brain para la licencia. */
    private const ARBOL = [[
        'sistema_id' => 6, 'sistema' => 'Configuración', 'color' => '#FF9900',
        'grupos' => [[
            'grupo' => 'Usuarios',
            'secciones' => [
                ['id' => 121, 'label' => 'Usuarios', 'raiz' => true, 'key' => ''],
                ['id' => 208, 'label' => 'Inicio Vencimientos y alertas', 'raiz' => false, 'key' => 'inicio_vencimientos'],
            ],
        ]],
    ]];

    protected function setUp(): void
    {
        parent::setUp();
        $this->crearEsquemaConfiguracion();
        $this->autenticarPow();

        // brain no está en los tests: el árbol se inyecta y el resto del servicio es real.
        $this->partialMock(SeccionesPermisosService::class, function ($mock) {
            $mock->shouldReceive('arbol')->andReturn(self::ARBOL);
        });
        $this->mock(ReplicadorColectora::class)->shouldIgnoreMissing();

        DB::table('tipousuario')->insert(['tipousuario_id' => 'VEN', 'tipousuario_nombre' => 'Vendedor']);
        DB::table('permisogrupo')->insert([
            ['fk_tipousuario_id' => 'VEN', 'fk_seccion_id' => 121, 'permisogrupo_nombre' => 'acceso', 'permisogrupo_valor' => 1],
            ['fk_tipousuario_id' => 'VEN', 'fk_seccion_id' => 121, 'permisogrupo_nombre' => 'alta', 'permisogrupo_valor' => 1],
            ['fk_tipousuario_id' => 'VEN', 'fk_seccion_id' => 208, 'permisogrupo_nombre' => 'inicio_vencimientos', 'permisogrupo_valor' => 1],
        ]);
        DB::table('pais')->insert(['pais_id' => 10, 'pais_nombre' => 'Argentina']);
        DB::table('ciudad')->insert(['ciudad_id' => 1, 'ciudad_nombre' => 'Buenos Aires', 'fk_pais_id' => 10]);
        DB::table('idioma')->insert(['idioma_id' => 'es', 'idioma_nombre' => 'Español']);
    }

    // ------------------------------------------------------------ tipos de usuario

    public function test_tipos_de_usuario_listan_con_cantidad_de_usuarios(): void
    {
        $this->get('/app/config/tipos-usuario')
            ->assertOk()
            ->assertInertia(fn (Assert $p) => $p->component('Abm/Index')
                ->where('config.extras.0.label', 'Copiar')
                ->where('registros.total', 2)
                ->where('registros.data.0.tipousuario_id', 'POW')->where('registros.data.0.usuarios', 1)
                ->where('registros.data.1.tipousuario_id', 'VEN')->where('registros.data.1.usuarios', 0));
    }

    public function test_editar_tipo_trae_el_arbol_y_los_permisos_activos(): void
    {
        $this->get('/app/config/tipos-usuario/VEN/edit')
            ->assertOk()
            ->assertInertia(fn (Assert $p) => $p->component('Config/TipousuarioForm')
                ->where('registro.tipousuario_id', 'VEN')
                ->where('arbol.0.grupos.0.secciones.1.key', 'inicio_vencimientos')
                ->where('activos', ['121-acceso', '121-alta', '208-inicio_vencimientos'])
                ->where('permisosRaiz', ['acceso', 'alta', 'edicion', 'borrado']));
    }

    public function test_alta_con_copia_y_matriz_reemplaza_permisogrupo(): void
    {
        $this->get('/app/config/tipos-usuario/create?copiar=VEN')
            ->assertOk()
            ->assertInertia(fn (Assert $p) => $p->where('copiaDe.id', 'VEN')->where('activos', ['121-acceso', '121-alta', '208-inicio_vencimientos']));

        $this->post('/app/config/tipos-usuario', [
            'tipousuario_id' => 'sup',
            'tipousuario_nombre' => 'Supervisor',
            'perms' => [
                121 => ['acceso' => 1, 'alta' => 0, 'edicion' => 1, 'borrado' => 0],
                208 => ['inicio_vencimientos' => 1],
                999 => ['acceso' => 1],
            ],
        ])->assertRedirect('/app/config/tipos-usuario');

        $this->assertDatabaseHas('tipousuario', ['tipousuario_id' => 'SUP', 'tipousuario_nombre' => 'Supervisor']);
        $this->assertSame(
            ['121-acceso', '121-edicion', '208-inicio_vencimientos', '999-acceso'],
            DB::table('permisogrupo')->where('fk_tipousuario_id', 'SUP')->orderBy('fk_seccion_id')->orderBy('permisogrupo_nombre')
                ->get()->map(fn ($p) => "{$p->fk_seccion_id}-{$p->permisogrupo_nombre}")->all(),
        );

        // Editar vuelve a reemplazar la matriz completa (como el CI: DELETE + INSERT de los marcados).
        $this->put('/app/config/tipos-usuario/SUP', ['tipousuario_nombre' => 'Supervisores', 'perms' => [121 => ['borrado' => 1]]])->assertRedirect();
        $this->assertSame(1, DB::table('permisogrupo')->where('fk_tipousuario_id', 'SUP')->count());
        $this->assertDatabaseHas('permisogrupo', ['fk_tipousuario_id' => 'SUP', 'fk_seccion_id' => 121, 'permisogrupo_nombre' => 'borrado']);

        // El id debe ser único y de 3 letras.
        $this->from('/app/config/tipos-usuario/create')->post('/app/config/tipos-usuario', ['tipousuario_id' => 'VEN', 'tipousuario_nombre' => 'x'])->assertSessionHasErrors('tipousuario_id');
        $this->from('/app/config/tipos-usuario/create')->post('/app/config/tipos-usuario', ['tipousuario_id' => 'LARGO', 'tipousuario_nombre' => 'x'])->assertSessionHasErrors('tipousuario_id');
    }

    public function test_baja_de_tipo_protege_pow_y_tipos_en_uso(): void
    {
        $this->delete('/app/config/tipos-usuario/POW')->assertRedirect()->assertSessionHas('error');
        $this->assertDatabaseHas('tipousuario', ['tipousuario_id' => 'POW']);

        DB::table('usuario')->insert(['usuario_id' => 8, 'usuario_nombre' => 'Vera', 'usuario_mail' => 'v@x.com', 'fk_tipousuario_id' => 'VEN']);
        $this->delete('/app/config/tipos-usuario/VEN')->assertRedirect()->assertSessionHas('error');
        $this->assertDatabaseHas('tipousuario', ['tipousuario_id' => 'VEN']);

        DB::table('usuario')->where('usuario_id', 8)->delete();
        $this->delete('/app/config/tipos-usuario/VEN')->assertRedirect()->assertSessionHas('success');
        $this->assertDatabaseMissing('tipousuario', ['tipousuario_id' => 'VEN']);
        $this->assertSame(0, DB::table('permisogrupo')->where('fk_tipousuario_id', 'VEN')->count());
    }

    // ---------------------------------------------------------------------- usuarios

    public function test_usuarios_listan_con_tipo_y_filtran(): void
    {
        DB::table('usuario')->insert(['usuario_id' => 8, 'usuario_nombre' => 'Vera', 'usuario_apellido' => 'Sosa', 'usuario_mail' => 'v@x.com', 'fk_tipousuario_id' => 'VEN', 'habilitar' => 'N']);

        $this->get('/app/config/usuarios?fk_tipousuario_id=VEN')
            ->assertOk()
            ->assertInertia(fn (Assert $p) => $p->component('Abm/Index')
                ->where('registros.total', 1)
                ->where('registros.data.0.tipousuario_nombre', 'Vendedor')
                ->where('registros.data.0.habilitar', 'No')
                ->where('registros.data.0.usuario_interno', 'Externo')
                ->where('config.extras.0.method', 'post'));
    }

    public function test_alta_de_usuario_hashea_la_clave_y_no_guarda_texto_plano(): void
    {
        $this->post('/app/config/usuarios', [
            'fk_tipousuario_id' => 'VEN', 'usuario_nombre' => 'Vera', 'usuario_apellido' => 'Sosa', 'usuario_mail' => 'vera@x.com',
            'usuario_password' => 'secreto1', 'habilitar' => 'Y', 'usuario_interno' => 'Y', 'usuario_responsable' => 1,
        ])->assertRedirect('/app/config/usuarios');

        $u = DB::table('usuario')->where('usuario_mail', 'vera@x.com')->first();
        $this->assertNotNull($u);
        $this->assertTrue(Hash::check('secreto1', $u->usuario_password));
        // Compatible con el phpass del CI (crypt sobre bcrypt).
        $this->assertSame($u->usuario_password, crypt('secreto1', $u->usuario_password));
        $this->assertSame('', $u->usuario_clave);
        $this->assertSame('vera@x.com', $u->usuario_login);
        $this->assertSame(1, (int) $u->usuario_responsable);

        // Mail repetido y clave corta rebotan; en edición la clave es opcional.
        $this->from('/app/config/usuarios/create')->post('/app/config/usuarios', [
            'fk_tipousuario_id' => 'VEN', 'usuario_nombre' => 'Otra', 'usuario_apellido' => 'X', 'usuario_mail' => 'vera@x.com',
            'usuario_password' => '123', 'habilitar' => 'Y', 'usuario_interno' => 'N',
        ])->assertSessionHasErrors(['usuario_mail', 'usuario_password']);

        $this->get("/app/config/usuarios/{$u->usuario_id}/edit")
            ->assertOk()
            ->assertInertia(fn (Assert $p) => $p->component('Config/UsuarioForm')->where('registro.usuario_mail', 'vera@x.com')->missing('registro.usuario_password'));

        $this->put("/app/config/usuarios/{$u->usuario_id}", [
            'fk_tipousuario_id' => 'VEN', 'usuario_nombre' => 'Vera', 'usuario_apellido' => 'Sosa Díaz', 'usuario_mail' => 'vera@x.com',
            'habilitar' => 'N', 'usuario_interno' => 'Y',
        ])->assertRedirect();
        $editado = DB::table('usuario')->where('usuario_id', $u->usuario_id)->first();
        $this->assertSame('Sosa Díaz', $editado->usuario_apellido);
        $this->assertSame('N', $editado->habilitar);
        $this->assertSame($u->usuario_password, $editado->usuario_password);
    }

    public function test_api_key_y_baja_de_usuario(): void
    {
        DB::table('usuario')->insert(['usuario_id' => 8, 'usuario_nombre' => 'Vera', 'usuario_mail' => 'v@x.com', 'fk_tipousuario_id' => 'VEN']);

        $this->post('/app/config/usuarios/8/apikey')->assertRedirect();
        $key = DB::table('usuario')->where('usuario_id', 8)->value('usuario_apikey');
        $this->assertMatchesRegularExpression('/^[0-9a-f]{6}(-[0-9a-f]{6}){4}$/', $key);

        // No se puede borrar el propio usuario (7); sí otro.
        $this->delete('/app/config/usuarios/7')->assertStatus(422);
        $this->delete('/app/config/usuarios/8')->assertRedirect();
        $this->assertDatabaseMissing('usuario', ['usuario_id' => 8]);
    }

    // ------------------------------------------------------------------- proveedores

    public function test_proveedores_alta_valida_cuit_en_ar_y_lista_con_pais(): void
    {
        $this->from('/app/config/proveedores/create')->post('/app/config/proveedores', [
            'proveedor_nombre' => 'Hotel Sol', 'proveedor_direccion' => 'Av. 1', 'cuit' => '20-11111111-9',
        ])->assertSessionHasErrors('cuit');

        $this->post('/app/config/proveedores', [
            'proveedor_nombre' => 'Hotel Sol', 'razonsocial' => 'Sol SA', 'proveedor_direccion' => 'Av. 1', 'cuit' => '30-71105657-9',
            'fk_pais_id' => 10, 'fk_ciudad_id' => 1, 'habilita' => 'Y', 'edita_tarifa' => 'N', 'prestador' => 0, 'voucherpropio' => 1,
            'vencimiento_dias' => 5, 'vencimiento_tipo' => 'CHI', 'tipo_extra' => 'PP', 'costo_extra' => 10.5,
        ])->assertRedirect('/app/config/proveedores');

        $p = DB::table('proveedor')->where('proveedor_nombre', 'Hotel Sol')->first();
        $this->assertNotNull($p);
        $this->assertSame('N', $p->eliminar);
        $this->assertSame(7, (int) $p->fk_usuario_id);
        $this->assertSame('CHI', $p->vencimiento_tipo);
        $this->assertSame(1, (int) $p->voucherpropio);

        DB::table('proveedor')->insert(['proveedor_nombre' => 'Borrado', 'eliminar' => 'Y']);

        $this->get('/app/config/proveedores?fk_pais_id=10')
            ->assertOk()
            ->assertInertia(fn (Assert $p) => $p->component('Abm/Index')
                ->where('registros.total', 1)
                ->where('registros.data.0.pais_nombre', 'Argentina')
                ->where('registros.data.0.ciudad_nombre', 'Buenos Aires')
                ->where('registros.data.0.habilita', 'Sí'));

        $this->get('/app/config/proveedores')->assertInertia(fn (Assert $p) => $p->where('registros.total', 1));
    }

    public function test_prestadores_solo_listan_prestador_1_y_el_alta_lo_fija(): void
    {
        DB::table('proveedor')->insert([
            ['proveedor_nombre' => 'Proveedor común', 'prestador' => 0],
            ['proveedor_nombre' => 'Guía Juan', 'prestador' => 1],
        ]);

        $this->get('/app/config/prestadores')
            ->assertOk()
            ->assertInertia(fn (Assert $p) => $p->where('registros.total', 1)->where('registros.data.0.proveedor_nombre', 'Guía Juan'));

        $this->post('/app/config/prestadores', ['proveedor_nombre' => 'Chofer Ana', 'proveedor_direccion' => 'Calle 2', 'habilita' => 'Y'])->assertRedirect('/app/config/prestadores');
        $this->assertDatabaseHas('proveedor', ['proveedor_nombre' => 'Chofer Ana', 'prestador' => 1, 'eliminar' => 'N']);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
