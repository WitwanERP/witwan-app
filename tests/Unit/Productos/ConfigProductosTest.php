<?php

namespace Tests\Unit\Productos;

use Tests\TestCase;

/**
 * Integridad de config/productos.php: es el reemplazo de get_estructura() y de
 * los 18 controladores, así que un error acá rompe el form de un tipo entero.
 */
class ConfigProductosTest extends TestCase
{
    private const TIPOS_LEGACY = ['HOT', 'MSC', 'AEL', 'MOT', 'AUT', 'PAQ', 'TRL', 'TRE', 'EXC', 'GUI', 'TRN', 'CRU', 'ASV'];

    /** vigencia.php:274: estos tipos tarifan por categorías × bases. */
    private const ALOJAMIENTO_LEGACY = ['HOT', 'AEL', 'MOT', 'MSC', 'PAQ', 'TRL'];

    public function test_estan_todos_los_tipos_del_ci_con_slug_unico(): void
    {
        $tipos = (array) config('productos.tipos');

        foreach (self::TIPOS_LEGACY as $t) {
            $this->assertArrayHasKey($t, $tipos, "falta el tipo {$t}");
        }

        $slugs = array_column($tipos, 'slug');
        $this->assertSame(count($slugs), count(array_unique($slugs)), 'slugs repetidos');
    }

    public function test_la_grilla_de_alojamiento_coincide_con_vigencia_php(): void
    {
        foreach ((array) config('productos.tipos') as $codigo => $cfg) {
            $this->assertSame(
                in_array($codigo, self::ALOJAMIENTO_LEGACY, true),
                (bool) $cfg['alojamiento'],
                "alojamiento de {$codigo} no coincide con vigencia.php:274"
            );
            if (! $cfg['alojamiento']) {
                $this->assertNotEmpty($cfg['tipos_pax'] ?? [], "{$codigo} tarifa por tramos y necesita tipos_pax");
                foreach ($cfg['tipos_pax'] as $pax) {
                    $this->assertContains($pax, config('productos.tipos_pax'), "tipo de pax {$pax} desconocido en {$codigo}");
                }
            }
        }
    }

    public function test_cada_campo_esta_bien_formado(): void
    {
        $tiposCampo = ['text', 'number', 'url', 'textarea', 'select', 'radio', 'boolean', 'titulo', 'bases', 'facilidades', 'destinos', 'ciudad', 'itinerario'];
        $tablas = ['producto', 'producto_extra', 'rel_productobase', 'rel_productoalojamientofacilidad', 'rel_productociudad'];

        $todos = array_merge(
            (array) config('productos.campos_comunes.antes'),
            (array) config('productos.campos_comunes.despues'),
            ...array_values(array_map(fn ($c) => $c['campos'], (array) config('productos.tipos'))),
            ...array_values((array) config('productos.campos_por_licencia')),
        );

        foreach ($todos as $campo) {
            $this->assertArrayHasKey('campo', $campo);
            $this->assertArrayHasKey('label', $campo, $campo['campo']);
            $this->assertContains($campo['tipo'], $tiposCampo, "tipo de campo inválido en {$campo['campo']}");
            $this->assertContains($campo['tabla'] ?? 'producto', $tablas, "tabla inválida en {$campo['campo']}");
            if (in_array($campo['tipo'], ['select', 'radio'], true)) {
                $this->assertTrue(isset($campo['opciones']) || isset($campo['lista']), "{$campo['campo']} necesita opciones o lista");
            }
            if (isset($campo['sistemas'])) {
                foreach ($campo['sistemas'] as $s) {
                    $this->assertContains($s, array_values((array) config('productos.sistemas')));
                }
            }
        }
    }

    public function test_los_campos_de_producto_existen_en_la_tabla(): void
    {
        $columnas = (new \App\Models\Producto)->getFillable();

        foreach ((array) config('productos.tipos') as $codigo => $cfg) {
            $campos = array_merge((array) config('productos.campos_comunes.antes'), $cfg['campos'], (array) config('productos.campos_comunes.despues'));
            foreach ($campos as $campo) {
                if (($campo['tabla'] ?? 'producto') === 'producto' && ! in_array($campo['tipo'], ['titulo', 'bases', 'facilidades', 'destinos', 'ciudad'], true)) {
                    $this->assertContains($campo['campo'], $columnas, "{$codigo}: {$campo['campo']} no es columna de producto");
                }
            }
        }
    }

    /** Las edades generan las columnas de la grilla: tienen que ir a producto_extra con esas claves exactas. */
    public function test_las_edades_van_a_producto_extra(): void
    {
        foreach (['HOT', 'PAQ', 'EXC', 'AEL'] as $tipo) {
            $porCampo = array_column(config("productos.tipos.{$tipo}.campos"), null, 'campo');
            foreach (['edad_infoa', 'edad_menor1'] as $edad) {
                $this->assertArrayHasKey($edad, $porCampo, "{$tipo} sin {$edad}");
                $this->assertSame('producto_extra', $porCampo[$edad]['tabla']);
            }
        }
    }

    public function test_sistemas_y_catalogos_de_vigencia(): void
    {
        $this->assertSame(['receptivo' => 1, 'mayorista' => 2, 'minorista' => 3, 'consolidador' => 4, 'nacional' => 7], config('productos.sistemas'));
        $this->assertSame([' ', 'R', 'C'], array_keys(config('productos.redondeo')));
        $this->assertSame(['', 'R', 'O'], array_keys(config('productos.residente')));
        $this->assertSame([1, 2, 3, 4, 5, 6, 7], array_keys(config('productos.dias')));
    }
}
