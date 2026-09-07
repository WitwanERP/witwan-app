<?php

namespace Tests\Concerns;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Esquema mínimo de las tablas legacy del módulo de productos, para los tests
 * que SÍ ejecutan SQL (SQLite en memoria, ver phpunit.xml).
 *
 * No hay migraciones de estas tablas en el repo: las crea el CI y las comparte
 * con Laravel. Las columnas y claves únicas replican witwan_rays.sql, que es lo
 * que importa para probar el upsert sobre `vigenciaunica` y los borrados por
 * diferencia. Lo que el módulo no toca (galería de PKD, cupos aéreos, etc.) no
 * está.
 */
trait CreaEsquemaProductos
{
    private const TABLAS = [
        'producto', 'producto_extra', 'vigencia', 'tarifa', 'rel_vigenciadia', 'vigenciaalojamiento', 'tarifario',
        'tarifariocomision', 'tarifarioarchivo', 'tarifacategoria', 'alojamientohabitacion', 'rel_productobase',
        'rel_productociudad', 'rel_productoalojamientofacilidad', 'productogaleria', 'cupo', 'soldout', 'iva',
        'ciudad', 'proveedor', 'regimen', 'cotizacion', 'moneda', 'reserva', 'servicio',
        'submodulo', 'pais', 'alojamientofacilidad', 'alojamientotipo', 'region', 'usuario', 'tipousuario', 'permisogrupo', 'permiso',
        'sistema', 'rel_clientesistema', 'productogrupo', 'hotelcategoria', 'destacado',
    ];

    protected function crearEsquemaProductos(): void
    {
        // La base de test es compartida entre tests del mismo proceso: se parte de cero.
        Schema::disableForeignKeyConstraints();
        foreach (self::TABLAS as $tabla) {
            Schema::dropIfExists($tabla);
        }

        Schema::create('producto', function (Blueprint $t) {
            $t->increments('producto_id');
            $t->integer('fk_usuario_id')->default(0);
            $t->integer('fk_proveedor_id')->default(0);
            $t->integer('fk_prestador_id')->default(0);
            $t->integer('fk_submodulo_id')->default(0);
            $t->integer('fk_sistema_id')->default(0);
            $t->integer('fk_productogrupo_id')->default(0);
            $t->string('fk_tipoproducto_id', 3)->default('');
            $t->integer('origen')->default(0);
            $t->integer('destino')->default(0);
            $t->string('producto_nombre')->default('');
            $t->string('producto_nombre_en')->default('');
            $t->string('producto_nombre_pt')->default('');
            $t->text('producto_descripcion')->nullable();
            $t->text('producto_descripcion_en')->nullable();
            $t->text('producto_descripcion_pt')->nullable();
            $t->integer('habilitar')->default(1);
            $t->string('modotarifa', 2)->default('');
            $t->text('gmaps')->nullable();
            $t->integer('aparece_tarifario')->default(1);
            $t->string('disponibilidad', 2)->default('');
            $t->string('producto_codigo', 50)->default('');
            $t->integer('eliminar')->default(0);
            $t->integer('destacar')->default(0);
            $t->text('politica_cancelacion')->nullable();
            $t->string('destinos', 150)->default('');
        });

        Schema::create('producto_extra', function (Blueprint $t) {
            $t->integer('fk_producto_id');
            $t->timestamp('regdate')->nullable();
            $t->string('extra_nombre', 50);
            $t->text('extra_valor');
        });
        Schema::create('productogrupo', function (Blueprint $t) {
            $t->increments('productogrupo_id');
            $t->string('productogrupo_nombre', 200)->default('');
            $t->integer('eliminar')->default(0);
        });
        Schema::create('destacado', function (Blueprint $t) {
            $t->increments('destacado_id');
            $t->string('destacado_nombre', 150)->default('');
            $t->integer('fk_producto_id')->default(0);
            $t->integer('fk_vigencia_id')->default(0);
            $t->integer('fk_tarifa_id')->default(0);
            $t->string('fk_moneda_id', 3)->default('');
            $t->string('costo_pkd', 10)->default('');
            $t->string('imagen', 150)->default('');
        });
        Schema::create('hotelcategoria', function (Blueprint $t) {
            $t->increments('hotelcategoria_id');
            $t->string('hotelcategoria_nombre', 100)->default('');
            $t->string('hotelcategoria_label', 255)->default('');
            $t->decimal('hotelcategoria_stars', 2, 1)->default(0);
        });

        Schema::create('vigencia', function (Blueprint $t) {
            $t->increments('vigencia_id');
            $t->integer('fk_producto_id');
            $t->string('residente', 1)->default('');
            $t->string('vigencia_ini', 10)->default('0000-00-00');
            $t->string('vigencia_fin', 10)->default('0000-00-00');
            $t->integer('vigencia_prioridad')->default(0);
            $t->integer('vigencia_promocional')->default(0);
            $t->string('vigencia_ventaini', 10)->default('0000-00-00');
            $t->string('vigencia_ventafin', 10)->default('0000-00-00');
            $t->text('vigencia_descripcion')->nullable();
            $t->integer('fk_regimen_id')->default(0);
            $t->integer('fk_tarifario_id')->default(0);
            $t->integer('vencimiento_checkin')->default(0);
            $t->integer('vencimiento_reserva')->default(0);
            $t->string('vencimiento_promocion', 10)->default('0000-00-00');
            $t->text('nota_promocion')->nullable();
            $t->string('clase', 2)->default('');
            $t->string('weekdate', 7)->default('');
            $t->integer('noches_minimas')->default(0);
            $t->string('modo_nochesminimas', 2)->default('');
            $t->integer('cargamanual')->default(0);
            $t->text('infoadicional')->nullable();
            $t->integer('promocional')->default(0);
            $t->integer('promo_noches')->default(0);
            $t->integer('promo_pornoches')->default(0);
            $t->integer('acumulable')->default(0);
            $t->text('comentarios')->nullable();
            $t->text('comentarios_en')->nullable();
            $t->text('comentarios_pt')->nullable();
            $t->string('weekdays', 7)->default('0000000'); // en MySQL se cambia a bit(7) más abajo
            $t->integer('web')->default(0);
            $t->integer('dias')->default(0);
            $t->integer('noches')->default(0);
            $t->decimal('cotizacionespecial', 15, 2)->default(0);
        });

        if ($this->esMysql()) {
            DB::statement("ALTER TABLE vigencia MODIFY weekdays BIT(7) NOT NULL DEFAULT b'0'");
        }

        Schema::create('tarifa', function (Blueprint $t) {
            $t->increments('tarifa_id');
            $t->integer('fk_costo_id')->default(0);
            $t->integer('fk_vigencia_id');
            $t->integer('fk_tarifario_id')->default(0);
            $t->integer('fk_tarifacategoria_id')->default(0);
            $t->string('fk_base_id', 3)->default('');
            $t->string('moneda_venta', 3)->default('');
            $t->string('moneda_costo', 3)->default('');
            $t->integer('min_pax')->default(0);
            $t->integer('max_pax')->default(0);
            $t->string('fk_tipopax_id', 3)->default('');
            $t->decimal('costo', 15, 2)->default(0);
            $t->decimal('ivacosto', 15, 2)->default(0);
            $t->decimal('impuestos', 15, 2)->default(0);
            $t->string('redondear', 1)->default('');
            $t->unique(['fk_vigencia_id', 'fk_tarifario_id', 'fk_base_id', 'min_pax', 'max_pax', 'fk_tipopax_id', 'fk_tarifacategoria_id'], 'vigenciaunica');
        });

        Schema::create('rel_vigenciadia', function (Blueprint $t) {
            $t->integer('fk_vigencia_id');
            $t->integer('fk_dia_id');
        });

        Schema::create('vigenciaalojamiento', function (Blueprint $t) {
            $t->increments('vigenciaalojamiento_id');
            $t->integer('fk_vigencia_id')->default(0);
            $t->integer('fk_producto_id')->default(0);
            $t->integer('fk_tarifacategoria_id')->default(0);
            $t->integer('fk_regimen_id')->default(0);
            $t->integer('noches')->default(0);
            $t->string('ncategoria')->default('');
        });

        Schema::create('tarifario', function (Blueprint $t) {
            $t->increments('tarifario_id');
            $t->decimal('cotizacion', 15, 5)->default(0);
            $t->string('crol', 1)->default('R');
            $t->integer('fk_sistema_id')->default(0);
            $t->string('tarifario_nombre', 100)->nullable();
            $t->string('fk_moneda_id', 3)->default('');
            $t->integer('orden')->default(0);
            $t->string('archivo', 100)->default('');
            $t->integer('interno')->default(0);
        });

        Schema::create('tarifariocomision', function (Blueprint $t) {
            $t->increments('tarifariocomision_id');
            $t->string('vigencia_ini', 10)->default('0000-00-00');
            $t->string('vigencia_fin', 10)->default('0000-00-00');
            $t->string('oldid', 15)->default('');
            $t->string('crol', 1)->default('R');
            $t->integer('fk_tarifario_id');
            $t->string('fk_submodulo_id', 3)->default('0');
            $t->integer('fk_pais_id')->default(0);
            $t->integer('fk_ciudad_id')->default(0);
            $t->integer('fk_producto_id')->default(0);
            $t->decimal('porcentaje_comision', 6, 4)->default(0);
            $t->decimal('divisor_markup', 6, 4)->default(0);
            $t->string('origen', 3)->default('');
            $t->unique(['fk_tarifario_id', 'fk_submodulo_id', 'fk_ciudad_id', 'fk_producto_id', 'origen'], 'tarifariocomision_unique');
        });

        Schema::create('tarifarioarchivo', function (Blueprint $t) {
            $t->increments('tarifarioarchivo_id');
            $t->integer('fk_tarifario_id');
            $t->string('tarifarioarchivo_archivo', 150)->default('');
            $t->string('tarifarioarchivo_descripcion')->default('');
        });

        Schema::create('tarifacategoria', function (Blueprint $t) {
            $t->increments('tarifacategoria_id');
            $t->string('fk_submodulo_id', 3)->default('');
            $t->string('tarifacategoria_nombre', 100)->default('');
            $t->integer('fk_producto_id')->default(0);
            $t->string('tarifacategoria_nombre_en', 100)->default('');
            $t->string('tarifacategoria_nombre_pg', 100)->default('');
        });

        Schema::create('alojamientohabitacion', function (Blueprint $t) {
            $t->increments('alojamientohabitacion_id');
            $t->integer('fk_producto_id')->default(0);
            $t->integer('fk_tarifacategoria_id');
            $t->string('textolibre')->default('');
            $t->string('alojamientohabitacion_nombre')->default('');
            $t->integer('min_adultos')->default(0);
            $t->integer('max_child')->default(0);
            $t->integer('max_adultos')->default(0);
            $t->integer('min_adultos_child')->default(0);
            $t->integer('capacidad')->default(0);
            $t->integer('max_adultos_child')->default(0);
            $t->integer('orden')->nullable();
            $t->integer('habilitar')->default(1);
        });

        Schema::create('rel_productobase', function (Blueprint $t) {
            $t->integer('fk_producto_id');
            $t->integer('fk_base_id');
            $t->unique(['fk_producto_id', 'fk_base_id']);
        });

        Schema::create('rel_productociudad', function (Blueprint $t) {
            $t->integer('fk_producto_id');
            $t->integer('fk_ciudad_id');
            $t->string('tipo', 1)->default('D');
            $t->unique(['fk_producto_id', 'fk_ciudad_id'], 'rel');
        });

        Schema::create('rel_productoalojamientofacilidad', function (Blueprint $t) {
            $t->integer('fk_producto_id');
            $t->integer('fk_alojamientofacilidad_id');
        });

        Schema::create('productogaleria', function (Blueprint $t) {
            $t->increments('productogaleria_id');
            $t->integer('fk_producto_id');
            $t->string('productogaleria_archivo', 150);
            $t->integer('orden')->default(0);
            $t->unique(['fk_producto_id', 'productogaleria_archivo']);
        });

        Schema::create('cupo', function (Blueprint $t) {
            $t->increments('cupo_id');
            $t->integer('release')->default(0);
            $t->string('vigencia_ini', 10)->default('0000-00-00');
            $t->string('vigencia_fin', 10)->default('0000-00-00');
            $t->integer('cantidad')->default(0);
            $t->integer('fk_producto_id')->default(0);
            $t->integer('fk_tarifacategoria_id')->default(0);
            $t->integer('subcategoria')->default(0);
            $t->integer('freesale')->default(0);
            $t->integer('fk_usuario_id')->default(0);
            $t->string('fecha_alta', 10)->default('0000-00-00');
            $t->integer('fk_cliente_id')->default(0);
            $t->string('bases')->default('');
            $t->integer('fk_tarifario_id')->default(0);
            $t->string('base', 10)->default('');
        });

        Schema::create('soldout', function (Blueprint $t) {
            $t->increments('soldout_id');
            $t->string('vigencia_ini', 10)->default('0000-00-00');
            $t->string('vigencia_fin', 10)->default('0000-00-00');
            $t->integer('fk_producto_id')->default(0);
            $t->string('base', 3)->nullable();
            $t->integer('fk_tarifacategoria_id')->default(0);
            $t->integer('fk_subcategoria_id')->default(0);
            $t->integer('fk_usuario_id')->default(0);
            $t->string('fecha_alta', 19)->default('0000-00-00 00:00:00');
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

        Schema::create('ciudad', function (Blueprint $t) {
            $t->increments('ciudad_id');
            $t->integer('fk_pais_id')->default(0);
            $t->string('ciudad_nombre')->default('');
        });

        Schema::create('proveedor', function (Blueprint $t) {
            $t->increments('proveedor_id');
            $t->string('proveedor_nombre')->default('');
        });

        Schema::create('regimen', function (Blueprint $t) {
            $t->increments('regimen_id');
            $t->string('regimen_nombre', 100)->default('');
        });

        Schema::create('cotizacion', function (Blueprint $t) {
            $t->increments('cotizacion_id');
            $t->string('cotizacion_moneda', 3);
            $t->string('cotizacion_fecha', 10);
            $t->decimal('cotizacion_costo', 15, 5)->default(0);
            $t->decimal('cotizacion_relacion', 15, 5)->default(0);
        });

        Schema::create('moneda', function (Blueprint $t) {
            $t->string('moneda_id', 3);
            $t->string('moneda_nombre', 50)->default('');
            $t->string('moneda_basica', 1)->default('N');
            $t->integer('orden')->default(0);
        });

        // Reservas: sólo lo que lee el calendario de cupos.
        Schema::create('reserva', function (Blueprint $t) {
            $t->increments('reserva_id');
            $t->string('tipocodigo', 3)->default('');
            $t->string('codigo', 20)->default('');
            $t->string('fk_filestatus_id', 3)->default('');
            $t->integer('fk_cliente_id')->default(0);
            $t->integer('fk_sistema_id')->default(0);
            $t->integer('escotizacion')->default(0);
            $t->date('fecha_alta')->nullable();
            $t->date('inicio')->nullable();
            $t->decimal('total', 15, 2)->default(0);
            $t->string('fk_moneda_id', 3)->default('');
            $t->string('titular_nombre', 150)->default('');
            $t->string('titular_apellido', 150)->default('');
        });

        // Catálogos y usuario que consultan los controllers Inertia.
        Schema::create('submodulo', function (Blueprint $t) {
            $t->string('tipoproducto_id', 3)->primary();
            $t->string('tipoproducto_nombre', 100)->default('');
            $t->integer('submodulo_orden')->default(10);
            $t->integer('tipoproducto_activo')->default(1);
        });
        Schema::create('pais', function (Blueprint $t) {
            $t->increments('pais_id');
            $t->string('pais_nombre', 100)->default('');
        });
        Schema::create('alojamientofacilidad', function (Blueprint $t) {
            $t->increments('alojamientofacilidad_id');
            $t->string('alojamientofacilidad_nombre', 100)->default('');
        });
        Schema::create('alojamientotipo', function (Blueprint $t) {
            $t->increments('alojamientotipo_id');
            $t->string('alojamientotipo_nombre', 64)->default('');
        });
        Schema::create('region', function (Blueprint $t) {
            $t->increments('region_id');
            $t->string('region_nombre', 100)->default('');
        });
        Schema::create('tipousuario', function (Blueprint $t) {
            $t->string('tipousuario_id', 3)->primary();
            $t->string('tipousuario_nombre', 100)->default('');
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
            $t->string('fk_tipousuario_id', 3);
            $t->integer('fk_seccion_id');
            $t->string('permisogrupo_nombre', 50);
            $t->integer('permisogrupo_valor')->default(0);
        });
        Schema::create('permiso', function (Blueprint $t) {
            $t->integer('fk_usuario_id');
            $t->integer('fk_seccion_id');
            $t->string('permiso_nombre', 50);
            $t->integer('permiso_valor')->default(0);
        });

        Schema::create('sistema', function (Blueprint $t) {
            $t->increments('sistema_id');
            $t->string('sistema_nombre', 150)->default('');
            $t->decimal('extra1', 10, 5)->default(0);
            $t->decimal('extra2', 10, 5)->default(0);
        });
        Schema::create('rel_clientesistema', function (Blueprint $t) {
            $t->integer('fk_cliente_id');
            $t->integer('fk_sistema_id');
            $t->integer('fk_tarifario_id');
        });

        Schema::create('servicio', function (Blueprint $t) {
            $t->increments('servicio_id');
            $t->integer('fk_reserva_id');
            $t->integer('fk_producto_id')->default(0);
            $t->integer('fk_tarifacategoria_id')->default(0);
            $t->string('vigencia_ini', 10)->default('0000-00-00');
            $t->string('vigencia_fin', 10)->default('0000-00-00');
            $t->string('status', 2)->default('');
            $t->string('servicio_nombre', 200)->default('');
            $t->string('fk_tipoproducto_id', 3)->default('');
            $t->integer('fk_ciudad_id')->default(0);
            $t->integer('adultos')->default(0);
            $t->integer('menores')->default(0);
            $t->decimal('total', 15, 2)->default(0);
            $t->string('fk_moneda_id', 3)->default('');
            $t->string('regdate', 19)->default('2020-01-01 00:00:00');
        });
    }

    protected function esMysql(): bool
    {
        return in_array(DB::connection()->getDriverName(), ['mysql', 'mariadb'], true);
    }

    /** `vigencia.weekdays` como string de 7 bits, independientemente del driver. */
    protected function bitsDeVigencia(int $vigenciaId): string
    {
        $expr = $this->esMysql() ? "LPAD(BIN(weekdays), 7, '0')" : 'weekdays';

        return (string) DB::table('vigencia')->where('vigencia_id', $vigenciaId)->value(DB::raw($expr));
    }

    /** Alta rápida de un producto con extras, para no repetir en cada test. */
    protected function productoDePrueba(array $producto = [], array $extras = []): int
    {
        $id = (int) DB::table('producto')->insertGetId(array_merge([
            'fk_tipoproducto_id' => 'HOT',
            'fk_sistema_id' => 1,
            'fk_proveedor_id' => 1,
            'producto_nombre' => 'Hotel de prueba',
            'modotarifa' => 'P',
            'destino' => 1,
        ], $producto));

        foreach ($extras as $nombre => $valor) {
            DB::table('producto_extra')->insert(['fk_producto_id' => $id, 'extra_nombre' => $nombre, 'extra_valor' => (string) $valor]);
        }

        return $id;
    }
}
