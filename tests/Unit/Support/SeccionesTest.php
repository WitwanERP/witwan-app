<?php

namespace Tests\Unit\Support;

use App\Support\Secciones;
use RuntimeException;
use Tests\TestCase;

/**
 * Resolución del fk_seccion_id que usa el sistema de permisos del CI.
 *
 * En el legacy el id no está escrito en ningún lado: se deduce comparando la URI
 * contra el sidebar que baja de brain (Admin_Controller.php:658-676).
 */
class SeccionesTest extends TestCase
{
    public function test_el_override_de_config_gana_y_no_consulta_brain(): void
    {
        // Sin tenant resuelto: si intentara ir a la base, fallaría.
        $this->assertSame(72, Secciones::id(Secciones::FACTURA_TERCERO));
    }

    public function test_tolera_la_barra_inicial_de_las_uris_de_brain(): void
    {
        $this->assertSame(72, Secciones::id('/administracion/factura3ero'));
    }

    /**
     * La carga múltiple y el subdiario tienen sección propia en brain (270 y 284)
     * pero ninguna fila en `permisogrupo`, así que en el CI quedan gobernadas por
     * la sección madre. Se replica ese comportamiento a propósito.
     */
    public function test_la_carga_multiple_y_el_subdiario_se_gatean_con_la_seccion_madre(): void
    {
        $this->assertSame(72, Secciones::id(Secciones::FACTURA_TERCERO_MULTIPLE));
        $this->assertSame(72, Secciones::id(Secciones::SUBDIARIO_COMPRA));
    }

    /**
     * Fail-closed: devolver 0 sería peor que fallar, porque
     * PermisoHelper::tienePermiso(0, ...) deniega a todos salvo POW y eso se
     * leería como "el usuario no tiene permiso" en vez de "está mal configurado".
     */
    public function test_una_seccion_desconocida_sin_tenant_falla_en_vez_de_devolver_cero(): void
    {
        $this->expectException(RuntimeException::class);

        Secciones::id('administracion/seccion-inexistente');
    }

    public function test_la_variante_tolerante_devuelve_null(): void
    {
        $this->assertNull(Secciones::idONull('administracion/seccion-inexistente'));
    }
}
