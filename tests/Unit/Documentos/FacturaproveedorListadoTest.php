<?php

namespace Tests\Unit\Documentos;

use App\Services\Documentos\FacturaproveedorListadoService;
use Tests\Concerns\CompilaSqlDeMysql;
use Tests\TestCase;

/**
 * Mapeo filtro -> SQL del listado de facturas de tercero, sin ejecutar contra la
 * base (se compila la query con toSql/getBindings). Mismo enfoque que
 * ReservaFiltroBuilderTest.
 *
 * Fiel a la query del listado de factura3ero.php:198-274.
 */
class FacturaproveedorListadoTest extends TestCase
{
    use CompilaSqlDeMysql;

    protected function setUp(): void
    {
        parent::setUp();

        $this->usarGramaticaMysql();
    }

    private function servicio(): FacturaproveedorListadoService
    {
        // Moneda básica y alícuota fijas: si dependieran de la base, este test
        // necesitaría un tenant conectado.
        return new FacturaproveedorListadoService('ARS', 0.21, 2);
    }

    private function sql(array $filtros): string
    {
        return $this->servicio()->query($filtros)->toSql();
    }

    private function bindings(array $filtros): array
    {
        return $this->servicio()->query($filtros)->getBindings();
    }

    public function test_sin_filtros_no_se_consulta(): void
    {
        $svc = $this->servicio();

        $this->assertFalse($svc->hayFiltros([]));
        $this->assertFalse($svc->hayFiltros(['proveedor' => '', 'numero' => null]));
        $this->assertTrue($svc->hayFiltros(['numero' => '0001']));
    }

    public function test_agrupa_por_factura_para_que_los_joins_no_dupliquen_filas(): void
    {
        // Sin este GROUP BY, una factura con 3 servicios aparecería 3 veces y los
        // totales saldrían inflados.
        $this->assertStringContainsString('group by `facturaproveedor`.`facturaproveedor_id`', $this->sql(['numero' => 'x']));
    }

    /**
     * El filtro genérico del Admin_Controller es LIKE (Form.php:312-327). El
     * primer port lo había traducido como igualdad, que devuelve menos.
     */
    public function test_numero_y_codigo_filtran_por_coincidencia_parcial(): void
    {
        $this->assertStringContainsString('`facturaproveedor`.`facturaproveedor_nro` like ?', $this->sql(['numero' => '0001']));
        $this->assertContains('%0001%', $this->bindings(['numero' => '0001']));

        $this->assertStringContainsString('`reserva`.`codigo` like ?', $this->sql(['codigo' => 'ABC']));
        $this->assertContains('%ABC%', $this->bindings(['codigo' => 'ABC']));
    }

    public function test_proveedor_y_proyecto_filtran_por_id_exacto(): void
    {
        $this->assertStringContainsString('`facturaproveedor`.`fk_proveedor_id` = ?', $this->sql(['proveedor' => '42']));
        $this->assertContains(42, $this->bindings(['proveedor' => '42']));

        $this->assertStringContainsString('`facturaproveedor`.`fk_proyecto_id` = ?', $this->sql(['proyecto' => '7']));
    }

    public function test_soporta_los_tres_rangos_de_fecha(): void
    {
        foreach (['fecha', 'fechacarga', 'fechacontable'] as $campo) {
            $sql = $this->sql([$campo.'_desde' => '2026-01-01', $campo.'_hasta' => '2026-01-31']);

            $this->assertStringContainsString("date(`facturaproveedor`.`{$campo}`) >= ?", $sql);
            $this->assertStringContainsString("date(`facturaproveedor`.`{$campo}`) <= ?", $sql);
        }
    }

    /**
     * Un rango a medias es el origen de los "resultados fallidos" del legacy: con
     * el "desde" vacío, Form.php:1112 descarta el filtro entero y devuelve todo,
     * incluido lo posterior al "hasta" que el usuario sí cargó.
     */
    public function test_un_desde_sin_hasta_se_cierra_en_hoy(): void
    {
        $bindings = $this->bindings(['fecha_desde' => '2026-01-01']);

        $this->assertContains(now()->format('Y-m-d'), $bindings);
    }

    public function test_un_hasta_sin_desde_se_aplica_igual_como_cota_superior(): void
    {
        $sql = $this->sql(['fecha_hasta' => '2024-12-31']);

        // El legacy acá devolvía TODO; nosotros respetamos lo que cargó el usuario.
        $this->assertStringContainsString('date(`facturaproveedor`.`fecha`) <= ?', $sql);
        $this->assertStringNotContainsString('date(`facturaproveedor`.`fecha`) >= ?', $sql);
        $this->assertContains('2024-12-31', $this->bindings(['fecha_hasta' => '2024-12-31']));
    }

    public function test_los_tres_rangos_se_normalizan_igual(): void
    {
        foreach (['fecha', 'fechacarga', 'fechacontable'] as $campo) {
            $sql = $this->sql([$campo.'_desde' => '2026-01-01']);

            $this->assertStringContainsString("date(`facturaproveedor`.`{$campo}`) <= ?", $sql,
                "El rango {$campo} debería cerrarse solo");
        }
    }

    public function test_acepta_fechas_en_formato_del_legacy(): void
    {
        // El CI manda dd/mm/YYYY; el front nuevo, ISO. Los dos tienen que andar.
        $this->assertContains('2026-01-31', $this->bindings(['fecha_hasta' => '31/01/2026']));
        $this->assertContains('2026-01-31', $this->bindings(['fecha_hasta' => '2026-01-31']));
    }

    public function test_las_notas_de_credito_se_muestran_en_negativo(): void
    {
        $sql = $this->sql(['numero' => 'x']);

        $this->assertStringContainsString("facturaproveedor.facturaproveedor_tipodocumento = 'Nota de Credito', -1, 1", $sql);
    }

    public function test_convierte_a_moneda_basica_solo_cuando_la_moneda_difiere(): void
    {
        $sql = $this->sql(['numero' => 'x']);

        $this->assertStringContainsString("facturaproveedor.fk_moneda_id != 'ARS', facturaproveedor.cotizacion, 1", $sql);
    }

    public function test_usa_la_alicuota_general_y_los_decimales_de_la_licencia(): void
    {
        $chile = (new FacturaproveedorListadoService('CLP', 0.19, 0))->query(['numero' => 'x'])->toSql();
        $argentina = $this->sql(['numero' => 'x']);

        $this->assertStringContainsString('facturaproveedor.montogeneral * 0.19', $chile);
        $this->assertStringContainsString('facturaproveedor.montogeneral * 0.21', $argentina);

        // Chile redondea el IVA a peso entero; el resto, a dos decimales.
        $this->assertStringContainsString(', 0) AS i21', $chile);
        $this->assertStringContainsString(', 2) AS i21', $argentina);
    }

    public function test_el_subdiario_separa_el_iva_de_los_gastos(): void
    {
        $sql = $this->sql(['numero' => 'x']);

        // iin21 = IVA de los gastos; idi21 = el del resto (factura3ero.php:98-100).
        $this->assertStringContainsString("IF(facturaproveedor.tipomovimiento = 'Gasto'", $sql);
        $this->assertStringContainsString('AS iin21', $sql);
        $this->assertStringContainsString("IF(facturaproveedor.tipomovimiento != 'Gasto'", $sql);
        $this->assertStringContainsString('AS idi21', $sql);
    }

    public function test_totaliza_los_23_campos_del_pie_del_legacy(): void
    {
        $this->assertCount(23, FacturaproveedorListadoService::CAMPOS_TOTALIZABLES);
    }
}
