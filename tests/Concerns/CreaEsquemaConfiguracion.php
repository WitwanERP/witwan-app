<?php

namespace Tests\Concerns;

use App\Models\User;
use App\Services\MenuService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Esquema mínimo de los catálogos de Configuración / Administración que
 * manejan los ABMs config-driven (Web\Abm\*) y las pantallas chicas de
 * administración, para los tests HTTP contra MySQL (ver phpunit.xml).
 *
 * Las columnas replican witwan_rays.sql en lo que los ABMs leen o escriben;
 * TablaLegacyService completa el resto de NOT NULL con defaults por tipo, así
 * que acá se declaran con default para que el insert no dependa del sql_mode.
 */
trait CreaEsquemaConfiguracion
{
    private const TABLAS_CONFIG = [
        'alojamientotipo', 'tarjetacredito', 'grupopais', 'regimen', 'cadenacliente', 'cadenahotelera', 'centrocosto',
        'tarifacategoria', 'alojamientofacilidad', 'tag', 'formapago', 'guia', 'interfases', 'ciudad', 'pais', 'filearchivo',
        'reserva', 'usuario', 'tipousuario', 'submodulo', 'plancuenta', 'proveedor', 'permisogrupo', 'permiso', 'moneda',
        'cotizacion', 'iva', 'modoivaventa', 'producto', 'sysconfig', 'rel_usuariomodelocomision', 'modelocomision',
    ];

    protected function crearEsquemaConfiguracion(): void
    {
        Schema::disableForeignKeyConstraints();
        foreach (self::TABLAS_CONFIG as $tabla) {
            Schema::dropIfExists($tabla);
        }

        $nombre = function (string $tabla, string $prefijo, array $extra = []) {
            Schema::create($tabla, function (Blueprint $t) use ($prefijo, $extra) {
                $t->increments("{$prefijo}_id");
                $t->string("{$prefijo}_nombre", 150)->default('');
                foreach ($extra as $col) {
                    $col($t);
                }
            });
        };

        $nombre('alojamientotipo', 'alojamientotipo');
        $nombre('tarjetacredito', 'tarjetacredito');
        $nombre('grupopais', 'grupopais');
        $nombre('cadenacliente', 'cadenacliente');
        $nombre('cadenahotelera', 'cadenahotelera');
        $nombre('regimen', 'regimen', [
            fn (Blueprint $t) => $t->string('regimen_nombre_en', 100)->default(''),
            fn (Blueprint $t) => $t->string('regimen_nombre_pg', 100)->default(''),
        ]);
        $nombre('centrocosto', 'centrocosto', [
            fn (Blueprint $t) => $t->string('centrocosto_codigo', 10)->default(''),
            fn (Blueprint $t) => $t->integer('centrocosto_activo')->default(1),
        ]);
        $nombre('tarifacategoria', 'tarifacategoria', [
            fn (Blueprint $t) => $t->string('fk_submodulo_id', 3)->default(''),
            fn (Blueprint $t) => $t->integer('fk_producto_id')->default(0),
            fn (Blueprint $t) => $t->string('tarifacategoria_nombre_en', 100)->default(''),
            fn (Blueprint $t) => $t->string('tarifacategoria_nombre_pg', 100)->default(''),
        ]);
        $nombre('alojamientofacilidad', 'alojamientofacilidad', [
            fn (Blueprint $t) => $t->string('alojamientofacilidad_nombre_en', 100)->default(''),
            fn (Blueprint $t) => $t->string('alojamientofacilidad_nombre_pg', 100)->default(''),
            fn (Blueprint $t) => $t->string('fk_submodulo_id', 3)->default('HOT'),
        ]);
        $nombre('tag', 'tag', [
            fn (Blueprint $t) => $t->integer('tag_rup')->default(0),
            fn (Blueprint $t) => $t->integer('tag_ruc')->default(0),
        ]);
        $nombre('formapago', 'formapago', [
            fn (Blueprint $t) => $t->integer('fk_plancuenta_id')->default(0),
        ]);
        $nombre('guia', 'guia', [
            fn (Blueprint $t) => $t->string('guia_apellido', 150)->default(''),
            fn (Blueprint $t) => $t->integer('fk_ciudad_id')->default(0),
        ]);
        $nombre('interfases', 'interfases', [
            fn (Blueprint $t) => $t->string('interfases_codigo', 3)->default(''),
            fn (Blueprint $t) => $t->string('interfases_libreria', 20)->default(''),
            fn (Blueprint $t) => $t->integer('fk_proveedor_id')->default(0),
            fn (Blueprint $t) => $t->decimal('interfases_mup', 5, 3)->default(0),
            fn (Blueprint $t) => $t->integer('interfases_activo')->default(1),
            fn (Blueprint $t) => $t->integer('interfases_prioridad')->default(0),
            fn (Blueprint $t) => $t->integer('interfases_receptivo')->default(0),
            fn (Blueprint $t) => $t->integer('interfases_mayorista')->default(1),
            fn (Blueprint $t) => $t->integer('interfases_release')->default(0),
            fn (Blueprint $t) => $t->integer('interfases_penalidad')->default(0),
            fn (Blueprint $t) => $t->text('interfases_data')->nullable(),
        ]);
        $nombre('pais', 'pais');
        $nombre('proveedor', 'proveedor', [
            fn (Blueprint $t) => $t->integer('eliminar')->default(0),
        ]);
        $nombre('modoivaventa', 'modoivaventa');
        $nombre('modelocomision', 'modelocomision');
        $nombre('producto', 'producto', [
            fn (Blueprint $t) => $t->integer('habilitar')->default(1),
            fn (Blueprint $t) => $t->integer('eliminar')->default(0),
        ]);

        Schema::create('ciudad', function (Blueprint $t) {
            $t->increments('ciudad_id');
            $t->integer('ciudad_activo')->default(1);
            $t->integer('fk_pais_id')->default(0);
            $t->integer('fk_ciudad_id')->default(0);
            $t->string('ciudad_nombre', 100)->default('');
            $t->string('ciudad_codigo', 20)->default('');
            $t->integer('ap')->default(0);
        });

        Schema::create('filearchivo', function (Blueprint $t) {
            $t->increments('filearchivo_id');
            $t->integer('fk_file_id')->default(0);
            $t->string('filearchivo_archivo', 250)->default('');
            $t->integer('filearchivo_publico')->default(0);
            $t->integer('fk_usuario_id')->default(0);
            $t->string('regdate', 19)->default('2020-01-01 00:00:00');
        });

        Schema::create('reserva', function (Blueprint $t) {
            $t->increments('reserva_id');
            $t->string('tipocodigo', 3)->default('');
            $t->string('codigo', 20)->default('');
        });

        Schema::create('submodulo', function (Blueprint $t) {
            $t->string('tipoproducto_id', 3)->primary();
            $t->string('tipoproducto_nombre', 100)->default('');
        });

        Schema::create('plancuenta', function (Blueprint $t) {
            $t->increments('plancuenta_id');
            $t->string('fk_moneda_id', 3)->default('');
            $t->string('plancuenta_nombre', 150)->default('');
            $t->integer('fk_plancuenta_id')->default(0);
            $t->string('plancuenta_codigo', 50)->default('');
            $t->integer('plancuenta_titulo')->default(0);
            $t->integer('plancuenta_g')->default(1);
            $t->string('plancuenta_cli', 1)->default('N');
            $t->string('plancuenta_saldo', 1)->default('D');
            $t->integer('cuentagasto')->default(0);
            $t->integer('arqueo')->default(0);
            $t->integer('conceptos_adicionales')->default(0);
            $t->integer('cartera')->default(0);
            $t->integer('requiereanalitico')->default(0);
            $t->integer('reportegastos')->default(0);
            $t->integer('ajusteinflacion')->default(0);
        });

        Schema::create('tipousuario', function (Blueprint $t) {
            $t->string('tipousuario_id', 3)->primary();
            $t->string('tipousuario_nombre', 150)->default('');
            $t->string('inicio', 150)->default('');
        });
        Schema::create('usuario', function (Blueprint $t) {
            $t->increments('usuario_id');
            $t->string('usuario_nombre', 100)->default('');
            $t->string('usuario_apellido', 100)->default('');
            $t->string('usuario_mail', 150)->default('');
            $t->string('usuario_login', 100)->default('');
            $t->string('fk_tipousuario_id', 3)->default('');
            $t->string('usuario_interno', 1)->default('N');
            $t->integer('fk_proveedor_id')->default(0);
            $t->integer('fk_cliente_id')->default(0);
            $t->integer('fk_cadenacliente_id')->default(0);
        });
        Schema::create('permisogrupo', function (Blueprint $t) {
            $t->increments('permisogrupo_id');
            $t->string('fk_tipousuario_id', 3);
            $t->integer('fk_seccion_id');
            $t->string('permisogrupo_nombre', 100);
            $t->integer('permisogrupo_valor')->default(0);
        });
        Schema::create('permiso', function (Blueprint $t) {
            $t->increments('permiso_id');
            $t->integer('fk_usuario_id');
            $t->integer('fk_seccion_id');
            $t->string('permiso_nombre', 50);
            $t->integer('permiso_valor')->default(0);
        });

        // moneda no tiene PRIMARY KEY en el legacy (sólo KEY): se replica tal cual.
        Schema::create('moneda', function (Blueprint $t) {
            $t->string('moneda_id', 3)->default('0');
            $t->string('moneda_nombre', 100)->default('');
            $t->string('descripcion', 100)->default('');
            $t->integer('orden')->default(0);
            $t->string('moneda_basica', 1)->default('N');
            $t->string('iso_code', 3)->default('');
            $t->integer('publicaweb')->default(0);
            $t->index('moneda_id');
        });
        Schema::create('cotizacion', function (Blueprint $t) {
            $t->increments('cotizacion_id');
            $t->string('cotizacion_moneda', 10)->default('');
            $t->date('cotizacion_fecha')->nullable();
            $t->decimal('cotizacion_relacion', 11, 4)->default(0);
            $t->decimal('cotizacion_costo', 11, 4)->default(0);
            $t->unique(['cotizacion_moneda', 'cotizacion_fecha']);
        });
        Schema::create('iva', function (Blueprint $t) {
            $t->increments('iva_id');
            $t->integer('fk_sistema_id')->default(0);
            $t->string('fk_submodulo_id', 3)->default('');
            $t->integer('fk_pais_id')->default(0);
            $t->integer('fk_ciudad_id')->default(0);
            $t->integer('fk_producto_id')->default(0);
            $t->integer('fk_modoivaventa_id')->default(0);
            $t->decimal('iva_valor', 10, 2)->default(0);
            $t->decimal('iva_costo', 5, 2)->default(0);
        });
        Schema::create('sysconfig', function (Blueprint $t) {
            $t->increments('sysconfig_id');
            $t->string('sysconfig_key', 64)->unique();
            $t->text('sysconfig_value');
        });
        Schema::create('rel_usuariomodelocomision', function (Blueprint $t) {
            $t->increments('rel_usuariomodelocomision_id');
            $t->integer('fk_usuario_id');
            $t->integer('fk_modelocomision_id');
            $t->unique(['fk_usuario_id', 'fk_modelocomision_id'], 'UNIQUE_umc');
        });
    }

    /**
     * Contexto HTTP de los tests de pantallas: tenant fijado, secciones por
     * config, menú mockeado y usuario POW autenticado en el guard web.
     */
    protected function autenticarPow(array $configExtra = []): void
    {
        app()->instance('tenant', (object) ['base' => 'witwan_rays', 'pais' => 'AR', 'licencia' => 1, 'row' => (object) ['licencia_nombre' => 'Rays']]);
        config(array_merge([
            'ci.redirect_guests' => false,
            'inertia.pages.paths' => [resource_path('js/Pages')],
        ], $configExtra));
        $this->mock(MenuService::class)->shouldReceive('forUser')->andReturn([]);

        DB::table('tipousuario')->insert(['tipousuario_id' => 'POW', 'tipousuario_nombre' => 'Superadmin']);
        DB::table('usuario')->insert(['usuario_id' => 7, 'usuario_nombre' => 'Ana', 'usuario_apellido' => 'Pow', 'usuario_mail' => 'ana@x.com', 'fk_tipousuario_id' => 'POW', 'usuario_interno' => 'Y']);
        $this->actingAs(User::find(7), 'web');
    }
}
