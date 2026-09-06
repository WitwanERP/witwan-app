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
        'modelofee', 'region', 'cliente', 'rel_usuariousuario', 'sistema', 'condicioniva', 'idioma', 'servicio', 'negocio', 'pnraereo', 'aerolinea', 'servicio_extra', 'reserva_extra',
        'facturaproveedor', 'movimiento', 'factura', 'rel_serviciofactura', 'reservain', 'rel_facturaproveedorocupacion',
        'rel_ordenadminocupacion', 'ordenadmin', 'rel_facturarecibo', 'rel_filefactura', 'recibo', 'rel_filerecibo', 'servicio_nomina', 'vigencia', 'notacredito', 'notadebito', 'ctz', 'servicioctz', 'imputacion', 'precompra', 'canje', 'sysnotification', 'pasajero', 'cliente_extra', 'pasajero_extra', 'rel_clientetag', 'rel_pasajerotag', 'rel_clientesistema', 'tarifario', 'tipofactura', 'tipoclavefiscal', 'creditoextra', 'feriado', 'historialfile', 'auditoria', 'solicitud', 'cierrecaja', 'rel_servicio', 'filestatus',
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
            fn (Blueprint $t) => $t->string('eliminar', 1)->default('N'),
            fn (Blueprint $t) => $t->integer('fk_cadenahotelera_id')->default(0),
            fn (Blueprint $t) => $t->string('razonsocial', 150)->default(''),
            fn (Blueprint $t) => $t->integer('fk_condicioniva_id')->default(0),
            fn (Blueprint $t) => $t->string('proveedor_legajo', 50)->default(''),
            fn (Blueprint $t) => $t->string('proveedor_telefono', 50)->default(''),
            fn (Blueprint $t) => $t->string('proveedor_telefonoemergencia', 100)->default(''),
            fn (Blueprint $t) => $t->string('proveedor_direccion', 255)->default(''),
            fn (Blueprint $t) => $t->string('proveedor_email', 100)->default(''),
            fn (Blueprint $t) => $t->string('proveedor_emailreservas', 100)->default(''),
            fn (Blueprint $t) => $t->string('proveedor_provincia', 200)->default(''),
            fn (Blueprint $t) => $t->integer('fk_pais_id')->default(0),
            fn (Blueprint $t) => $t->integer('fk_ciudad_id')->default(0),
            fn (Blueprint $t) => $t->string('cuit', 20)->default(''),
            fn (Blueprint $t) => $t->string('iata', 20)->default(''),
            fn (Blueprint $t) => $t->string('edita_tarifa', 1)->default('N'),
            fn (Blueprint $t) => $t->text('comentario')->nullable(),
            fn (Blueprint $t) => $t->string('habilita', 1)->default('Y'),
            fn (Blueprint $t) => $t->integer('fk_usuario_id')->default(0),
            fn (Blueprint $t) => $t->string('fechacarga', 19)->default('2020-01-01 00:00:00'),
            fn (Blueprint $t) => $t->string('um', 19)->default('2020-01-01 00:00:00'),
            fn (Blueprint $t) => $t->string('proveedor_codigopostal', 15)->default(''),
            fn (Blueprint $t) => $t->text('proveedor_infobanco')->nullable(),
            fn (Blueprint $t) => $t->string('moneda_extra', 3)->default(''),
            fn (Blueprint $t) => $t->string('tipo_extra', 2)->default(''),
            fn (Blueprint $t) => $t->decimal('costo_extra', 15, 2)->default(0),
            fn (Blueprint $t) => $t->integer('prestador')->default(0),
            fn (Blueprint $t) => $t->integer('gastoacliente')->default(0),
            fn (Blueprint $t) => $t->integer('voucherpropio')->default(0),
            fn (Blueprint $t) => $t->decimal('porcentaje_extra', 15, 2)->default(0),
            fn (Blueprint $t) => $t->integer('enviadocumentos')->default(0),
            fn (Blueprint $t) => $t->integer('cartaemision')->default(0),
            fn (Blueprint $t) => $t->integer('vencimiento_dias')->default(0),
            fn (Blueprint $t) => $t->string('vencimiento_tipo', 3)->default(''),
            fn (Blueprint $t) => $t->integer('fk_cliente_id')->default(0),
            fn (Blueprint $t) => $t->integer('proveedor_oc')->default(0),
            fn (Blueprint $t) => $t->string('codigo_travelc', 100)->default(''),
        ]);
        $nombre('condicioniva', 'condicioniva');
        Schema::create('idioma', function (Blueprint $t) {
            $t->string('idioma_id', 2)->primary();
            $t->string('idioma_nombre', 50)->default('');
            $t->integer('orden')->default(0);
        });
        $nombre('modoivaventa', 'modoivaventa');
        $nombre('modelocomision', 'modelocomision', [
            fn (Blueprint $t) => $t->string('fk_moneda_id', 3)->default(''),
            fn (Blueprint $t) => $t->string('modelocomision_tipo', 3)->default(''),
            fn (Blueprint $t) => $t->string('modelocomision_esquema', 3)->default(''),
            fn (Blueprint $t) => $t->string('modelocomision_basecomision', 3)->default(''),
            fn (Blueprint $t) => $t->string('modelocomision_basecalculo', 3)->default(''),
            fn (Blueprint $t) => $t->string('modelocomision_asignacion', 3)->default(''),
            fn (Blueprint $t) => $t->integer('fk_usuario_id')->default(0),
            fn (Blueprint $t) => $t->integer('fk_cliente_id')->default(0),
            fn (Blueprint $t) => $t->string('fk_submodulo_id', 3)->default(''),
            fn (Blueprint $t) => $t->string('modelocomision_prefijo', 3)->default(''),
            fn (Blueprint $t) => $t->integer('in1')->default(0),
            fn (Blueprint $t) => $t->integer('out1')->default(0),
            fn (Blueprint $t) => $t->decimal('porcentaje1', 5, 2)->default(0),
            fn (Blueprint $t) => $t->integer('meta_anual')->default(0),
            fn (Blueprint $t) => $t->date('vigencia_in')->nullable(),
            fn (Blueprint $t) => $t->date('vigencia_out')->nullable(),
        ]);

        $nombre('region', 'region');
        $nombre('cliente', 'cliente', [
            fn (Blueprint $t) => $t->string('cliente_razonsocial', 200)->default(''),
            fn (Blueprint $t) => $t->string('cliente_telefono', 50)->default(''),
            fn (Blueprint $t) => $t->integer('fk_cadenacliente_id')->default(0),
            fn (Blueprint $t) => $t->string('cuit', 20)->default(''),
            function (Blueprint $t) {
                foreach (['cliente_legajo', 'cliente_fax', 'cliente_email', 'cliente_email2', 'cliente_emailadmin', 'cliente_ciudad', 'cliente_provincia',
                    'cliente_direccionfiscal', 'cliente_codigopostal', 'nro_clavefiscal', 'iata', 'cliente_logo', 'gastos_fijo_moneda', 'autorizaws',
                    'nombre_representante', 'cuit_internacional', 'tipo_fce', 'idtravelc'] as $c) {
                    $t->string($c, 255)->default('');
                }
                foreach (['fk_tarifario1_id', 'fk_tarifario2_id', 'fk_tarifario3_id', 'clienteminorista', 'facturacion_periodo', 'fk_tipofactura_id',
                    'fk_condicioniva_id', 'fk_pais_id', 'fk_ciudad_id', 'fk_tipoclavefiscal_id', 'fk_usuario_id', 'fk_usuario_promotor1', 'fk_usuario_promotor2',
                    'fk_usuario_promotor3', 'fk_usuario_promotor4', 'fk_usuario_vendedor', 'cliente_promo', 'cliente_web', 'cliente_pasajerodirecto',
                    'plazo_pago', 'idnemo', 'tipofacturacion', 'licencia_id', 'credito_habilitado', 'factura_automatica'] as $c) {
                    $t->integer($c)->default(0);
                }
                foreach (['limite_credito', 'credito_utilizado', 'gastos_porcentaje_1', 'gastos_porcentaje_2', 'gastos_porcentaje_3', 'gastos_fijo_1', 'gastos_fijo_2', 'gastos_fijo_3', 'gastos_iva'] as $c) {
                    $t->decimal($c, 15, 2)->default(0);
                }
                foreach (['consolidador', 'usar_logo', 'habilita', 'freelance', 'representante_geografico'] as $c) {
                    $t->string($c, 1)->default('Y');
                }
                $t->string('fk_idioma_id', 2)->default('es');
                $t->string('fk_moneda_id', 3)->default('');
                $t->text('comentarios')->nullable();
            },
        ]);
        Schema::create('pasajero', function (Blueprint $t) {
            $t->increments('pasajero_id');
            foreach (['pasajero_nombre', 'pasajero_apellido', 'pasajero_apodo', 'pasajero_nacionalidad', 'pasajero_nacimiento', 'pasajero_email', 'pasajero_clave',
                'pasajero_password', 'cargo', 'pasajero_foto', 'tipodoc', 'nrodoc', 'emisorfecha', 'vencimientodoc', 'pasajero_direccionfiscal',
                'pasajero_codigopostal', 'pasajero_ciudad', 'nro_clavefiscal', 'fk_usuario_promotor1'] as $c) {
                $t->string($c, 150)->default('');
            }
            foreach (['fk_cliente_id', 'mostrar_ficha', 'fk_usuario_vendedor', 'emisordoc', 'fk_pais_id', 'fk_ciudad_id', 'fk_tipoclavefiscal_id', 'fk_condicioniva_id', 'fk_tarifario1_id', 'fk_tarifario2_id'] as $c) {
                $t->integer($c)->default(0);
            }
            foreach (['gastos_iva', 'gastos_fijo_1', 'gastos_porcentaje_1'] as $c) {
                $t->decimal($c, 15, 2)->default(0);
            }
            $t->string('pasajero_sexo', 1)->default('');
            $t->string('freelance', 1)->default('');
            $t->string('habilita', 1)->default('Y');
            $t->string('fk_moneda_id', 3)->default('');
            $t->text('cliente_asociado')->nullable();
            $t->text('fotodoc')->nullable();
            $t->text('observaciones')->nullable();
            $t->date('ultimo_mail')->nullable();
        });
        foreach (['cliente_extra' => 'fk_cliente_id', 'pasajero_extra' => 'fk_pasajero_id'] as $tabla => $fk) {
            Schema::create($tabla, function (Blueprint $t) use ($fk) {
                $t->integer($fk);
                $t->string('extra_nombre', 50);
                $t->text('extra_valor');
            });
        }
        Schema::create('cierrecaja', function (Blueprint $t) {
            $t->increments('cierrecaja_id');
            $t->date('cierrecaja_fecha');
            $t->integer('fk_usuario_id')->default(0);
            $t->timestamp('regdate')->nullable();
        });
        Schema::create('solicitud', function (Blueprint $t) {
            $t->increments('solicitud_id');
            foreach (['solicitud_nombre', 'solicitud_apellido', 'solicitud_email', 'solicitud_telefono', 'solicitud_celular', 'solicitud_ciudad', 'solicitud_empresa', 'solicitud_rz', 'solicitud_clavefiscal'] as $c) {
                $t->string($c, 150)->default('');
            }
            $t->string('solicitud_status', 100)->default('PENDIENTE');
            $t->timestamp('solicitud_fecha')->nullable();
        });
        Schema::create('creditoextra', function (Blueprint $t) {
            $t->increments('creditoextra_id');
            $t->date('creditoextra_fecha');
            $t->integer('fk_cliente_id');
            $t->integer('fk_usuario_id')->default(0);
            $t->decimal('creditoextra_monto', 15, 2)->default(0);
            $t->dateTime('regdate')->nullable();
        });
        Schema::create('rel_clientetag', function (Blueprint $t) {
            $t->integer('fk_cliente_id');
            $t->integer('fk_tag_id');
        });
        Schema::create('rel_pasajerotag', function (Blueprint $t) {
            $t->integer('fk_pasajero_id');
            $t->integer('fk_tag_id');
        });
        Schema::create('rel_clientesistema', function (Blueprint $t) {
            $t->integer('fk_cliente_id');
            $t->integer('fk_sistema_id');
            $t->integer('fk_tarifario_id');
        });
        $nombre('tarifario', 'tarifario', [
            fn (Blueprint $t) => $t->integer('fk_sistema_id')->default(1),
            fn (Blueprint $t) => $t->integer('interno')->default(0),
            fn (Blueprint $t) => $t->integer('orden')->default(0),
            fn (Blueprint $t) => $t->string('fk_moneda_id', 3)->default('USD'),
        ]);
        $nombre('tipofactura', 'tipofactura');
        if (! Schema::hasTable('tipoclavefiscal')) {
            $nombre('tipoclavefiscal', 'tipoclavefiscal');
        }
        Schema::create('servicio_nomina', function (Blueprint $t) {
            $t->increments('servicio_nomina_id');
            $t->integer('fk_servicio_id')->default(0);
            $t->string('nombre', 100)->default('');
            $t->string('apellido', 100)->default('');
            foreach (['email', 'documento', 'nacionalidad', 'telefono', 'cuit', 'tipopax', 'edad', 'nacimiento'] as $c) {
                $t->string($c, 100)->default('');
            }
        });
        Schema::create('feriado', function (Blueprint $t) {
            $t->increments('feriado_id');
            $t->date('feriado_fecha');
        });
        Schema::create('historialfile', function (Blueprint $t) {
            $t->increments('historial_id');
            $t->timestamp('historial_date')->nullable();
            $t->string('historial_campo', 150)->default('');
            $t->text('historial_valor')->nullable();
            $t->text('historial_actual')->nullable();
            $t->integer('fk_reserva_id')->default(0);
            $t->integer('fk_servicio_id')->default(0);
            $t->integer('fk_usuario_id')->default(0);
            $t->string('historial_ip', 50)->default('');
        });
        Schema::create('auditoria', function (Blueprint $t) {
            $t->increments('auditoria_id');
            $t->string('tabla_relacionada', 150);
            $t->integer('id_relacionado');
            $t->string('accion', 50);
            $t->integer('usuario_id')->default(0);
            $t->text('valor_antiguo')->nullable();
            $t->text('valor_nuevo')->nullable();
            $t->string('usuario_ip', 50)->default('');
            $t->timestamp('regdate')->nullable();
        });
        Schema::create('vigencia', function (Blueprint $t) {
            $t->increments('vigencia_id');
            $t->integer('fk_producto_id')->default(0);
            $t->date('vigencia_ini')->nullable();
            $t->date('vigencia_fin')->nullable();
        });
        $nombre('sistema', 'sistema', [
            fn (Blueprint $t) => $t->string('sistema_codigo', 3)->default(''),
            fn (Blueprint $t) => $t->string('texto_extra3', 100)->default(''),
            fn (Blueprint $t) => $t->integer('item_order')->default(0),
        ]);
        $nombre('modelofee', 'modelofee', [
            fn (Blueprint $t) => $t->integer('fk_cliente_id')->default(0),
            fn (Blueprint $t) => $t->integer('fk_pais_id')->default(0),
            fn (Blueprint $t) => $t->integer('fk_ciudad_id')->default(0),
            fn (Blueprint $t) => $t->integer('region_origen')->default(0),
            fn (Blueprint $t) => $t->integer('fk_region_id')->default(0),
            fn (Blueprint $t) => $t->string('fk_moneda_id', 3)->default(''),
            fn (Blueprint $t) => $t->string('tipocodigo', 4)->default(''),
            fn (Blueprint $t) => $t->string('modelofee_tipo', 1)->default('P'),
            fn (Blueprint $t) => $t->string('fk_submodulo_id', 3)->default('AER'),
            fn (Blueprint $t) => $t->decimal('modelofee_normal', 15, 2)->default(0),
            fn (Blueprint $t) => $t->decimal('modelofee_offline', 15, 2)->default(0),
            fn (Blueprint $t) => $t->decimal('modelofee_emergencia', 15, 2)->default(0),
            fn (Blueprint $t) => $t->decimal('modelofee_normal_r', 15, 2)->default(0),
            fn (Blueprint $t) => $t->decimal('modelofee_minimo', 15, 2)->default(0),
            fn (Blueprint $t) => $t->decimal('modelofee_maximo', 15, 2)->default(0),
        ]);
        Schema::create('rel_usuariousuario', function (Blueprint $t) {
            $t->increments('usuariousuario_id');
            $t->integer('fk_usuario_id');
            $t->integer('fk_secundario_id');
            $t->integer('tiporelacion')->default(1);
        });
        $nombre('producto', 'producto', [
            fn (Blueprint $t) => $t->integer('habilitar')->default(1),
            fn (Blueprint $t) => $t->integer('eliminar')->default(0),
            fn (Blueprint $t) => $t->integer('fk_proveedor_id')->default(0),
            fn (Blueprint $t) => $t->string('fk_tipoproducto_id', 3)->default(''),
            fn (Blueprint $t) => $t->integer('fk_sistema_id')->default(0),
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
            $t->integer('fk_cliente_id')->default(0);
            $t->integer('fk_sistema_id')->default(0);
            $t->integer('fk_sistemaaplicacion_id')->default(0);
            $t->integer('fk_usuario_id')->default(0);
            $t->integer('agente')->default(0);
            $t->integer('promotor')->default(0);
            $t->string('fk_filestatus_id', 2)->default('');
            $t->integer('fk_guia_id')->default(0);
            $t->integer('fk_negocio_id')->default(0);
            $t->date('fecha_alta')->nullable();
            $t->date('fecha_vencimiento')->nullable();
            $t->date('inicio')->nullable();
            $t->string('titular_nombre', 150)->default('');
            $t->string('titular_apellido', 150)->default('');
            $t->integer('autorizado')->default(0);
            $t->text('observaciones')->nullable();
            $t->string('fk_moneda_id', 3)->default('');
            $t->decimal('total', 15, 2)->default(0);
            $t->decimal('cobrado', 15, 2)->default(0);
            $t->decimal('costo', 15, 2)->default(0);
            $t->decimal('renta', 15, 2)->default(0);
            $t->decimal('impuestos', 15, 2)->default(0);
            $t->decimal('gastos', 15, 2)->default(0);
            $t->integer('fk_agrupado_id')->default(0);
            $t->string('codigo_externo', 50)->default('');
            $t->integer('facturar_a')->default(0);
            $t->integer('fk_filepadre_id')->default(0);
            $t->integer('cliente_usuario')->default(0);
            $t->string('titular_email', 50)->default('');
            $t->string('titular_celular', 50)->default('');
            $t->string('moneda_factura', 3)->default('');
            $t->dateTime('regdate')->nullable();
            $t->integer('escotizacion')->default(0);
            $t->integer('reserva_mayorista')->default(0);
            $t->decimal('totalservicios', 15, 2)->default(0);
            $t->decimal('iva', 15, 2)->default(0);
            $t->decimal('ivacosto', 15, 2)->default(0);
            $t->decimal('comision', 15, 2)->default(0);
        });

        Schema::create('servicio', function (Blueprint $t) {
            $t->increments('servicio_id');
            $t->string('servicio_nombre', 200)->default('');
            $t->integer('fk_reserva_id')->default(0);
            $t->string('fk_tipoproducto_id', 3)->default('');
            $t->integer('fk_producto_id')->default(0);
            $t->integer('fk_proveedor_id')->default(0);
            $t->integer('fk_ciudad_id')->default(0);
            $t->date('vigencia_ini')->nullable();
            $t->date('vigencia_fin')->nullable();
            $t->integer('adultos')->default(0);
            $t->integer('menores')->default(0);
            $t->integer('juniors')->default(0);
            $t->integer('infante')->default(0);
            $t->integer('fk_tarifacategoria_id')->default(0);
            $t->integer('fk_regimen_id')->default(0);
            $t->string('status', 2)->default('');
            $t->string('moneda_costo', 3)->default('');
            $t->decimal('iva', 15, 2)->default(0);
            $t->string('fk_moneda_id', 3)->default('');
            $t->decimal('comision', 15, 2)->default(0);
            $t->decimal('costo', 15, 2)->default(0);
            $t->decimal('iva_costo', 15, 2)->default(0);
            $t->decimal('total', 15, 2)->default(0);
            $t->string('nro_confirmacion', 200)->default('');
            $t->integer('id_devolucion')->default(0);
            $t->text('comentarios')->nullable();
            $t->string('retira_voucher', 255)->default('');
            $t->text('autoriza_evoucher')->nullable();
            $t->decimal('cotcosto', 15, 4)->default(0);
            $t->decimal('cotventa', 15, 4)->default(0);
            $t->decimal('renta', 15, 4)->default(0);
            $t->string('origen', 5)->default('');
            $t->date('vencimiento_proveedor')->nullable();
            $t->decimal('totalservicio', 15, 2)->default(0);
            $t->decimal('comisionproveedor_porcentaje', 7, 5)->default(0);
            $t->text('info')->nullable();
            $t->decimal('impuestos', 15, 2)->default(0);
            $t->string('regdate', 19)->default('2020-01-01 00:00:00');
            $t->integer('fk_prestador_id')->default(0);
            $t->decimal('extra1', 15, 2)->default(0);
            $t->decimal('extra2', 15, 2)->default(0);
        });
        Schema::create('facturaproveedor', function (Blueprint $t) {
            $t->increments('facturaproveedor_id');
            $t->text('descripcion')->nullable();
            $t->string('electronica', 1)->default('N');
            $t->string('facturaproveedor_tipofactura', 2)->default('');
            $t->string('facturaproveedor_nro', 50)->default('');
            $t->string('facturaproveedor_tipodocumento', 50)->default('');
            $t->integer('fk_proveedor_id')->default(0);
            $t->date('fecha')->nullable();
            $t->date('fechacontable')->nullable();
            $t->string('fk_moneda_id', 3)->default('');
            $t->decimal('montoexento', 15, 2)->default(0);
            $t->decimal('montogeneral', 15, 2)->default(0);
            $t->decimal('retencioniibb', 15, 2)->default(0);
            $t->decimal('cotizacion', 15, 4)->default(0);
            $t->decimal('montototal', 15, 2)->default(0);
            $t->string('tipomovimiento', 50)->default('');
            $t->text('imputacion')->nullable();
            $t->string('fechacarga', 19)->default('2020-01-01 00:00:00');
        });
        Schema::create('movimiento', function (Blueprint $t) {
            $t->increments('movimiento_id');
            $t->integer('fk_facturaproveedor_id')->default(0);
            $t->integer('fk_recibo_id')->default(0);
            $t->integer('fk_ordenadmin_id')->default(0);
            $t->integer('fk_cliente_id')->default(0);
            $t->integer('fk_proveedor_id')->default(0);
            $t->integer('fk_file_id')->default(0);
            $t->integer('fk_asientocontable_id')->default(0);
            $t->integer('cuenta_debito')->default(0);
            $t->integer('cuenta_credito')->default(0);
            $t->integer('fk_plancuenta_id')->default(0);
            $t->string('operacion', 100)->default('');
            $t->string('fk_moneda_id', 3)->default('');
            $t->decimal('cotizacion_moneda', 15, 4)->default(1);
            $t->decimal('monto', 15, 2)->default(0);
            $t->date('fecha')->nullable();
            $t->string('statusmovimiento', 2)->default('');
            $t->integer('auxiliar')->default(0);
            $t->integer('fk_notacredito_id')->default(0);
            $t->integer('fk_notadebito_id')->default(0);
            $t->integer('fk_factura_id')->default(0);
            $t->integer('fk_movimiento_id')->default(0);
            $t->integer('utilizado')->default(0);
            $t->text('descripcion')->nullable();
            $t->string('banco', 200)->default('');
            $t->date('fecha_acreditacion')->nullable();
        });
        Schema::create('factura', function (Blueprint $t) {
            $t->increments('factura_id');
            $t->text('observaciones')->nullable();
            $t->string('statusfactura', 2)->default('');
            $t->string('factura_fecha', 19)->default('2020-01-01 00:00:00');
            $t->string('factura_tipo', 1)->default('A');
            $t->string('factura_nro', 20)->default('');
            $t->decimal('factura_conceptos_gravados', 15, 2)->default(0);
            $t->decimal('factura_conceptos_exentos', 15, 2)->default(0);
            $t->integer('fk_cliente_id')->default(0);
            $t->integer('fk_file_id')->default(0);
            $t->integer('fk_usuario_id')->default(0);
            $t->string('fk_moneda_id', 3)->default('');
            $t->string('vencimiento_pago', 19)->default('2020-01-01 00:00:00');
            $t->string('factura_sucursal', 10)->default('');
            $t->decimal('factura_rgaereos', 15, 2)->default(0);
            $t->decimal('factura_conceptos_gravadosespecial', 15, 2)->default(0);
            $t->decimal('factura_conceptos_nogravados', 15, 2)->default(0);
            $t->decimal('factura_rgterrestres', 15, 2)->default(0);
            $t->decimal('factura_impuesto1', 15, 2)->default(0);
            $t->decimal('factura_impuesto2', 15, 2)->default(0);
            $t->decimal('factura_impuesto3', 15, 2)->default(0);
            $t->decimal('factura_impuesto4', 15, 2)->default(0);
            $t->decimal('factura_impuesto5', 15, 2)->default(0);
            $t->string('remitofull', 100)->default('0:0:0');
            $t->decimal('factura_tipo_cambio', 15, 4)->default(0);
        });
        foreach (['notacredito', 'notadebito'] as $tabla) {
            Schema::create($tabla, function (Blueprint $t) use ($tabla) {
                $t->increments("{$tabla}_id");
                $t->string('statusfactura', 2)->default('');
                $t->string("{$tabla}_fecha", 19)->default('2020-01-01 00:00:00');
                $t->string("{$tabla}_tipo", 1)->default('A');
                $t->string("{$tabla}_nro", 20)->default('');
                $t->decimal("{$tabla}_conceptos_gravados", 15, 2)->default(0);
                $t->decimal("{$tabla}_conceptos_gravadosespecial", 15, 2)->default(0);
                $t->decimal("{$tabla}_conceptos_exentos", 15, 2)->default(0);
                $t->decimal("{$tabla}_conceptos_nogravados", 15, 2)->default(0);
                $t->decimal("{$tabla}_rgterrestres", 15, 2)->default(0);
                $t->decimal("{$tabla}_impuesto1", 15, 2)->default(0);
                $t->decimal("{$tabla}_impuesto2", 15, 2)->default(0);
                $t->decimal("{$tabla}_impuesto3", 15, 2)->default(0);
                $t->decimal("{$tabla}_impuesto4", 15, 2)->default(0);
                $t->decimal("{$tabla}_impuesto5", 15, 2)->default(0);
                $t->integer('fk_cliente_id')->default(0);
                $t->integer('fk_usuario_id')->default(0);
                $t->integer('fk_factura_id')->default(0);
                $t->integer('fk_file_id')->default(0);
                $t->string('remitofull', 100)->default('0:0:0');
                $t->text('observaciones')->nullable();
                $t->string('fk_moneda_id', 3)->default('');
                $t->decimal("{$tabla}_tipo_cambio", 15, 4)->default(0);
                $t->string("{$tabla}_sucursal", 10)->default('');
                $t->integer('fk_notacredito_id')->default(0);
            });
        }
        Schema::create('ctz', function (Blueprint $t) {
            $t->increments('ctz_id');
            $t->integer('fk_cliente_id')->default(0);
            $t->integer('fk_agrupado_id')->default(0);
            $t->integer('fk_sistema_id')->default(0);
            $t->integer('fk_sistemaaplicacion_id')->default(0);
            $t->integer('fk_usuario_id')->default(0);
            $t->date('fecha_alta')->nullable();
            $t->string('codigo', 20)->default('');
            $t->string('tipocodigo', 3)->default('');
            $t->string('titular_nombre', 150)->default('');
            $t->string('titular_apellido', 150)->default('');
            $t->string('fk_moneda_id', 3)->default('');
            $t->decimal('total', 15, 2)->default(0);
            $t->string('fk_filestatus_id', 2)->default('');
            $t->date('fecha_vencimiento')->nullable();
        });
        Schema::create('sysnotification', function (Blueprint $t) {
            $t->increments('sysnotification_id');
            $t->integer('fk_usuario_id')->default(0);
            $t->string('sysnotification_date', 19)->default('2020-01-01 00:00:00');
            $t->string('sysnotification_nombre', 255)->default('');
            $t->string('sysnotification_url', 255)->default('');
            $t->string('sysnotification_type', 16)->default('');
            $t->string('sysnotification_icon', 16)->default('');
        });
        Schema::create('servicioctz', function (Blueprint $t) {
            $t->increments('servicio_id');
            $t->string('servicio_nombre', 200)->default('');
            $t->integer('fk_reserva_id')->default(0);
            $t->date('vigencia_ini')->nullable();
            $t->integer('adultos')->default(0);
            $t->integer('menores')->default(0);
        });
        Schema::create('rel_serviciofactura', function (Blueprint $t) {
            $t->integer('fk_servicio_id');
            $t->integer('fk_factura_id');
            $t->integer('tipodocumento')->default(1);
        });
        Schema::create('reservain', function (Blueprint $t) {
            $t->integer('fk_reserva_id');
            $t->date('inicio')->nullable();
        });
        Schema::create('rel_facturaproveedorocupacion', function (Blueprint $t) {
            $t->integer('fk_facturaproveedor_id');
            $t->integer('fk_ocupacion_id');
            $t->decimal('monto', 15, 2)->default(0);
        });
        Schema::create('rel_ordenadminocupacion', function (Blueprint $t) {
            $t->integer('fk_ordenadmin_id');
            $t->integer('fk_ocupacion_id');
            $t->string('fk_moneda_id', 3)->default('');
            $t->decimal('monto', 15, 2)->default(0);
        });
        Schema::create('ordenadmin', function (Blueprint $t) {
            $t->increments('ordenadmin_id');
            $t->integer('fk_ordenadmin_id')->default(0);
            $t->integer('fk_proveedor_id')->default(0);
            $t->integer('fk_usuario_id')->default(0);
            $t->date('fecha')->nullable();
            $t->string('nroservicio', 20)->default('');
            $t->string('nropago', 20)->default('');
            $t->string('tipo', 1)->default('');
            $t->string('fk_moneda_id', 3)->default('');
            $t->decimal('cotizacion', 15, 4)->default(0);
            $t->decimal('monto', 15, 2)->default(0);
            $t->string('status', 2)->default('OK');
            $t->text('observaciones')->nullable();
        });
        Schema::create('rel_facturarecibo', function (Blueprint $t) {
            $t->increments('rel_facturarecibo_id');
            $t->integer('fk_factura_id')->default(0);
            $t->integer('fk_recibo_id')->default(0);
            $t->decimal('monto', 15, 2)->default(0);
        });
        Schema::create('rel_filefactura', function (Blueprint $t) {
            $t->integer('fk_file_id');
            $t->integer('fk_factura_id');
        });
        Schema::create('recibo', function (Blueprint $t) {
            $t->increments('recibo_id');
            $t->string('recibo_tipo', 5)->default('');
            $t->string('recibo_nro', 20)->default('');
            $t->date('fecha')->nullable();
            $t->integer('fk_cliente_id')->default(0);
            $t->integer('fk_usuario_id')->default(0);
            $t->string('statusrecibo', 2)->default('');
            $t->decimal('monto', 15, 2)->default(0);
            $t->string('fk_moneda_id', 3)->default('');
            $t->text('observaciones')->nullable();
        });
        Schema::create('imputacion', function (Blueprint $t) {
            $t->increments('imputacion_id');
            $t->integer('imputacion_orden')->default(0);
            $t->string('imputacion_movimiento1', 50)->default('');
        });
        Schema::create('precompra', function (Blueprint $t) {
            $t->increments('precompra_id');
            $t->string('precompra_tipo', 1)->default('P');
            $t->integer('fk_sistema_id')->default(0);
            $t->integer('fk_producto_id')->default(0);
            $t->string('fk_moneda_id', 3)->default('');
            $t->integer('fk_file_id')->default(0);
            $t->date('precompra_inicio')->nullable();
            $t->integer('fk_proveedor_id')->default(0);
            $t->decimal('precompra_dinero', 10, 2)->default(0);
            $t->decimal('precompra_utilizado', 10, 2)->default(0);
            $t->date('precompra_fin')->nullable();
            $t->integer('fk_usuario_id')->default(0);
            $t->text('observaciones')->nullable();
        });
        Schema::create('canje', function (Blueprint $t) {
            $t->increments('canje_id');
            $t->integer('fk_sistema_id')->default(0);
            $t->integer('fk_producto_id')->default(0);
            $t->string('fk_moneda_id', 3)->default('');
            $t->string('canje_contrato', 50)->default('');
            $t->date('canje_inicio')->nullable();
            $t->integer('fk_proveedor_id')->default(0);
            $t->integer('fk_ciudad_id')->default(0);
            $t->integer('fk_factura_id')->default(0);
            $t->integer('canje_noches')->default(0);
            $t->decimal('canje_dinero', 10, 2)->default(0);
            $t->decimal('canje_utilizado', 10, 2)->default(0);
            $t->date('canje_fin')->nullable();
            $t->text('observaciones')->nullable();
            $t->integer('fk_usuario_id')->default(0);
        });
        Schema::create('rel_filerecibo', function (Blueprint $t) {
            $t->integer('fk_file_id');
            $t->integer('fk_recibo_id');
            $t->date('fecha')->nullable();
            $t->string('fk_moneda_id', 3)->default('');
            $t->decimal('monto', 15, 2)->default(0);
        });
        Schema::create('servicio_extra', function (Blueprint $t) {
            $t->integer('fk_servicio_id');
            $t->string('regdate', 19)->default('2020-01-01 00:00:00');
            $t->string('extra_nombre', 50);
            $t->text('extra_valor');
        });
        Schema::create('reserva_extra', function (Blueprint $t) {
            $t->integer('fk_reserva_id');
            $t->string('regdate', 19)->default('2020-01-01 00:00:00');
            $t->string('extra_nombre', 50);
            $t->text('extra_valor');
        });
        $nombre('negocio', 'negocio');
        $nombre('aerolinea', 'aerolinea');
        Schema::create('pnraereo', function (Blueprint $t) {
            $t->increments('pnraereo_id');
            $t->integer('fk_ocupacion_id')->default(0);
            $t->integer('fk_aerolinea_id')->default(0);
            $t->text('pnraereo_ruta')->nullable();
            $t->string('pnraereo_reemision', 50)->default('');
            $t->string('pnraereo_nombre', 100)->default('');
            $t->string('pnraereo_apellido', 100)->default('');
            $t->string('codigo_recloc', 20)->default('');
            $t->date('pnraereo_fechaemision')->nullable();
        });
        Schema::create('rel_servicio', function (Blueprint $t) {
            $t->integer('servicio_madre');
            $t->integer('servicio_hijo');
        });
        Schema::create('filestatus', function (Blueprint $t) {
            $t->string('filestatus_id', 2)->primary();
            $t->string('filestatus_nombre', 100)->default('');
        });

        Schema::create('submodulo', function (Blueprint $t) {
            $t->string('tipoproducto_id', 3)->primary();
            $t->string('tipoproducto_nombre', 100)->default('');
            $t->string('submodulo_id', 3)->default('');
            $t->string('tipoproducto_tipo', 1)->default('S');
            $t->integer('fk_plancuenta_id')->default(0);
            $t->integer('tipoproducto_activo')->default(1);
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
            $t->integer('fk_operador_id')->default(0);
            $t->string('usuario_password', 255)->default('');
            $t->string('usuario_clave', 30)->default('');
            $t->string('usuario_key', 100)->default('');
            $t->string('usuario_apikey', 100)->default('');
            $t->string('habilitar', 1)->default('Y');
            $t->string('solocotiza', 1)->default('N');
            $t->string('fk_idioma_id', 2)->default('es');
            $t->string('usuario_telefono', 50)->default('');
            $t->string('usuario_celular', 50)->default('');
            $t->string('usuario_fax', 50)->default('');
            $t->string('usuario_domicilio', 255)->default('');
            $t->string('usuario_sexo', 1)->default('M');
            $t->string('nacimiento', 10)->default('0000-00-00');
            $t->string('ciudad', 100)->default('');
            $t->text('notas')->nullable();
            $t->integer('agente')->default(0);
            $t->string('eliminar', 1)->default('N');
            $t->integer('usuario_promo')->default(0);
            $t->integer('usuario_responsable')->default(0);
            $t->string('firma_amadeus', 50)->default('');
            $t->string('firma_sabre', 50)->default('');
            $t->integer('fk_modelocomision_id')->default(0);
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
