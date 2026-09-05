<?php

namespace Tests\Unit\Productos;

use App\Services\Productos\ProductoFormulario;
use InvalidArgumentException;
use Tests\TestCase;

/**
 * Port de Producto_model::get_estructura() y del reparto principal/secundario
 * de hotel.php:161-171.
 */
class ProductoFormularioTest extends TestCase
{
    private function tenant(string $base = 'witwan_rays'): void
    {
        app()->instance('tenant', (object) ['base' => $base, 'pais' => 'AR', 'licencia' => 1]);
    }

    public function test_resuelve_tipo_por_slug_y_sistema_por_slug(): void
    {
        $this->assertSame('HOT', ProductoFormulario::tipoPorSlug('hotel'));
        $this->assertSame('PAQ', ProductoFormulario::tipoPorSlug('circuito'));
        $this->assertNull(ProductoFormulario::tipoPorSlug('inexistente'));

        $this->assertSame(1, ProductoFormulario::sistemaId('receptivo'));
        $this->assertSame(7, ProductoFormulario::sistemaId('nacional'));
        $this->assertNull(ProductoFormulario::sistemaId('otro'));
        $this->assertSame('mayorista', ProductoFormulario::sistemaSlug(2));
    }

    public function test_tipo_desconocido_lanza(): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new ProductoFormulario)->tipo('ZZZ');
    }

    /** get_estructura(): nombre PT y descripciones EN/PT sólo en sistemas 1 y 2. */
    public function test_visibilidad_por_sistema(): void
    {
        $this->tenant();
        $f = new ProductoFormulario;

        $receptivo = array_column($f->campos('HOT', 1), 'campo');
        $minorista = array_column($f->campos('HOT', 3), 'campo');

        $this->assertContains('producto_nombre_pt', $receptivo);
        $this->assertContains('politica_ocupacionen', $receptivo);
        $this->assertNotContains('producto_nombre_pt', $minorista);
        $this->assertNotContains('politica_ocupacionen', $minorista);
        $this->assertContains('producto_nombre', $minorista);
    }

    public function test_orden_comunes_tipo_y_politica_de_cancelacion_al_final(): void
    {
        $this->tenant();
        $campos = array_column((new ProductoFormulario)->campos('EXC', 1), 'campo');

        $this->assertSame('producto_nombre', $campos[0]);
        $this->assertSame('politica_cancelacionpt', end($campos));
        $this->assertLessThan(array_search('disponibilidad', $campos), array_search('aparece_excel', $campos));
    }

    /** producto_model.php:92-103: propina sólo en expert, IIBB sólo en tower. */
    public function test_campos_por_licencia(): void
    {
        $f = new ProductoFormulario;

        $this->tenant('witwan_expert');
        $this->assertContains('propina', array_column($f->campos('HOT', 1), 'campo'));
        $this->assertNotContains('iibbprovincial', array_column($f->campos('HOT', 1), 'campo'));

        $this->tenant('witwan_tower');
        $this->assertContains('iibbprovincial', array_column($f->campos('HOT', 1), 'campo'));

        $this->tenant('witwan_rays');
        $this->assertNotContains('propina', array_column($f->campos('HOT', 1), 'campo'));
    }

    public function test_reparte_por_tabla_como_hotel_php(): void
    {
        $this->tenant();
        $r = (new ProductoFormulario)->porTabla('HOT', 1);

        $producto = array_column($r['producto'], 'campo');
        $extra = array_column($r['producto_extra'], 'campo');
        $relaciones = array_column($r['relaciones'], 'campo');

        $this->assertContains('producto_nombre', $producto);
        $this->assertContains('gmaps', $producto);
        $this->assertContains('politica_cancelacion', $producto);
        $this->assertNotContains('!edadesmenores', $producto, 'los títulos no persisten');

        $this->assertContains('zona', $extra);
        $this->assertContains('edad_infoa', $extra);
        $this->assertContains('aparece_excel', $extra);
        $this->assertContains('politica_cancelacionen', $extra);

        $this->assertSame(['bases', 'destino', 'facilidades'], array_values(array_intersect(['bases', 'destino', 'facilidades'], $relaciones)));
    }

    public function test_opciones_requeridas_del_tipo(): void
    {
        $this->tenant();
        $claves = (new ProductoFormulario)->opcionesRequeridas('HOT', 1);

        $this->assertContains('proveedores', $claves);
        $this->assertContains('ciudades', $claves);
        $this->assertContains('facilidades', $claves);
        $this->assertContains('alojamientotipos', $claves);
        $this->assertNotContains('regiones', $claves);
    }
}
