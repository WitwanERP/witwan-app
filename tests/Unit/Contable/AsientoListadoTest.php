<?php

namespace Tests\Unit\Contable;

use App\Services\Contable\AsientoListadoService;
use App\Support\Contable\TipoAsiento;
use Illuminate\Support\Carbon;
use Tests\Concerns\CompilaSqlDeMysql;
use Tests\TestCase;

/**
 * Mapeo filtro -> SQL del listado de asientos, sin ejecutar contra la base (se
 * compila la query con toSql/getBindings). Mismo enfoque que
 * FacturaproveedorListadoTest y ReservaFiltroBuilderTest.
 */
class AsientoListadoTest extends TestCase
{
    use CompilaSqlDeMysql;

    protected function setUp(): void
    {
        parent::setUp();

        $this->usarGramaticaMysql();
    }

    private function servicio(): AsientoListadoService
    {
        return new AsientoListadoService;
    }

    private function tipo(string $slug = 'contable'): TipoAsiento
    {
        return TipoAsiento::desde($slug);
    }

    private function sql(array $filtros, string $slug = 'contable'): string
    {
        return $this->servicio()->query($this->tipo($slug), $filtros)->toSql();
    }

    private function bindings(array $filtros, string $slug = 'contable'): array
    {
        return $this->servicio()->query($this->tipo($slug), $filtros)->getBindings();
    }

    // ------------------------------------------------------------------
    // Tipo
    // ------------------------------------------------------------------

    /**
     * Los tres módulos del CI son la misma tabla discriminada por `tipo`. Si el
     * where se pierde, el listado de fondos muestra asientos contables.
     */
    public function test_cada_tipo_filtra_por_su_codigo_de_ordenadmin(): void
    {
        foreach (['contable' => 'A', 'cuenta-corriente' => 'C', 'fondos' => 'M'] as $slug => $codigo) {
            $filtros = ['numero' => '1'];

            $this->assertStringContainsString('`ordenadmin`.`tipo` = ?', $this->sql($filtros, $slug));
            $this->assertContains($codigo, $this->bindings($filtros, $slug));
        }
    }

    public function test_slug_desconocido_no_se_resuelve(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        TipoAsiento::desde('recibos');
    }

    // ------------------------------------------------------------------
    // Filtros
    // ------------------------------------------------------------------

    public function test_sin_filtros_no_se_consulta(): void
    {
        $svc = $this->servicio();

        $this->assertFalse($svc->hayFiltros([]));
        $this->assertFalse($svc->hayFiltros(['numero' => '', 'usuario' => null]));
        $this->assertTrue($svc->hayFiltros(['numero' => '17']));
        $this->assertTrue($svc->hayFiltros(['fecha_desde' => '2026-01-01']));
    }

    /**
     * El filtro genérico del Admin_Controller sobre campos de texto es LIKE
     * (Form.php:312-327). Traducirlo como igualdad devuelve de menos.
     */
    public function test_numero_y_observaciones_filtran_por_coincidencia_parcial(): void
    {
        $this->assertStringContainsString('`ordenadmin`.`nropago` like ?', $this->sql(['numero' => '17']));
        $this->assertContains('%17%', $this->bindings(['numero' => '17']));

        $this->assertStringContainsString('`ordenadmin`.`observaciones` like ?', $this->sql(['observaciones' => 'ajuste']));
        $this->assertContains('%ajuste%', $this->bindings(['observaciones' => 'ajuste']));
    }

    public function test_usuario_moneda_estado_y_proyecto_filtran_por_valor_exacto(): void
    {
        $this->assertStringContainsString('`ordenadmin`.`fk_usuario_id` = ?', $this->sql(['usuario' => '42']));
        $this->assertContains(42, $this->bindings(['usuario' => '42']));

        $this->assertStringContainsString('`ordenadmin`.`fk_moneda_id` = ?', $this->sql(['moneda' => 'USD']));
        $this->assertContains('USD', $this->bindings(['moneda' => 'USD']));

        $this->assertStringContainsString('`ordenadmin`.`status` = ?', $this->sql(['status' => 'AN']));
        $this->assertContains('AN', $this->bindings(['status' => 'AN']));

        $this->assertStringContainsString('`ordenadmin`.`fk_proyecto_id` = ?', $this->sql(['proyecto' => '3'], 'fondos'));
    }

    // ------------------------------------------------------------------
    // Rango de fechas
    // ------------------------------------------------------------------

    public function test_el_rango_de_fechas_aplica_las_dos_cotas(): void
    {
        $sql = $this->sql(['fecha_desde' => '2026-01-01', 'fecha_hasta' => '2026-01-31']);

        $this->assertStringContainsString('date(`ordenadmin`.`fecha`) >= ?', $sql);
        $this->assertStringContainsString('date(`ordenadmin`.`fecha`) <= ?', $sql);

        $bindings = $this->bindings(['fecha_desde' => '2026-01-01', 'fecha_hasta' => '2026-01-31']);
        $this->assertContains('2026-01-01', $bindings);
        $this->assertContains('2026-01-31', $bindings);
    }

    /**
     * Un "desde" suelto se cierra en hoy. Sin esto la consulta queda abierta
     * hacia adelante, que no es lo que el usuario pidió al cargar sólo el desde.
     */
    public function test_desde_sin_hasta_cierra_el_rango_en_hoy(): void
    {
        Carbon::setTestNow('2026-08-23');

        $bindings = $this->bindings(['fecha_desde' => '2026-01-01']);

        $this->assertContains('2026-01-01', $bindings);
        $this->assertContains('2026-08-23', $bindings);

        Carbon::setTestNow();
    }

    /**
     * Un "hasta" suelto queda como cota superior abierta. El legacy descartaba
     * el filtro ENTERO cuando el desde venía vacío (`!empty($value)` en
     * Form.php:1112) y devolvía todo sin avisar: ese es el resultado engañoso
     * que este port evita.
     */
    public function test_hasta_sin_desde_se_aplica_como_cota_superior(): void
    {
        $sql = $this->sql(['fecha_hasta' => '2024-12-31']);

        $this->assertStringContainsString('date(`ordenadmin`.`fecha`) <= ?', $sql);
        $this->assertStringNotContainsString('date(`ordenadmin`.`fecha`) >= ?', $sql);
        $this->assertContains('2024-12-31', $this->bindings(['fecha_hasta' => '2024-12-31']));
    }

    /** A la query se llega también por URL (link guardado, export) con el formato del CI. */
    public function test_acepta_fechas_en_formato_del_legacy(): void
    {
        $this->assertContains('2026-01-31', $this->bindings(['fecha_hasta' => '31/01/2026']));
        $this->assertContains('2026-01-31', $this->bindings(['fecha_hasta' => '2026-01-31']));
    }

    // ------------------------------------------------------------------
    // Forma de la query
    // ------------------------------------------------------------------

    /**
     * `movimiento` es 1:N: si la cuenta saliera de un join, cada asiento
     * aparecería repetido una vez por movimiento.
     */
    public function test_la_cantidad_de_movimientos_sale_de_una_subconsulta_y_no_de_un_join(): void
    {
        $sql = $this->sql(['numero' => '1']);

        $this->assertStringContainsStringIgnoringCase('select count(*) from movimiento m', $sql);
        $this->assertStringNotContainsString('join `movimiento`', $sql);
    }

    /**
     * El legacy ordena sólo por fecha. Con paginación real eso hace que filas
     * con la misma fecha se repitan o se salteen entre páginas.
     */
    public function test_ordena_por_fecha_y_desempata_por_id(): void
    {
        $sql = $this->sql(['numero' => '1']);

        $this->assertStringContainsString('order by `ordenadmin`.`fecha` desc, `ordenadmin`.`ordenadmin_id` desc', $sql);
    }
}
