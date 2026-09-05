<?php

namespace Tests\Unit\Pricing;

use App\Services\Pricing\ScopeResolver;
use Tests\Concerns\CompilaSqlDeMysql;
use Tests\TestCase;

/**
 * Una sola cascada producto > ciudad > país > submódulo > general para
 * tarifariocomision e iva (corrige B6/B7: el CI tenía tres ORDER BY distintos,
 * uno con país duplicado y por encima de ciudad).
 */
class ScopeResolverTest extends TestCase
{
    use CompilaSqlDeMysql;

    protected function setUp(): void
    {
        parent::setUp();
        $this->usarGramaticaMysql();
    }

    public function test_comision_filtra_por_alcance_y_ordena_una_sola_vez(): void
    {
        $q = (new ScopeResolver)->queryComision(5, 77, 'HOT', 12, 3);
        $sql = strtolower($q->toSql());

        $this->assertStringContainsString('`fk_tarifario_id` = ?', $sql);
        $this->assertStringContainsString('`origen` = ?', $sql);
        $this->assertStringContainsString('`fk_producto_id` in (?, ?)', $sql);
        $this->assertStringContainsString('`fk_submodulo_id` in (?, ?, ?)', $sql);
        $this->assertStringContainsString('`fk_ciudad_id` in (?, ?)', $sql);
        $this->assertStringContainsString('`fk_pais_id` in (?, ?)', $sql);
        $this->assertStringEndsWith('order by `fk_producto_id` desc, `fk_ciudad_id` desc, `fk_pais_id` desc, `fk_submodulo_id` desc limit 1', $sql);
        $this->assertSame(1, substr_count($sql, '`fk_pais_id` desc'), 'país no debe repetirse en el ORDER BY (B7)');
        $this->assertContains('', $q->getBindings());
        $this->assertContains('0', $q->getBindings());
    }

    /** tarifa_model.php:1289: sin país conocido no se filtra por país. */
    public function test_sin_pais_no_filtra_por_pais(): void
    {
        $sql = strtolower((new ScopeResolver)->queryComision(5, 77, 'HOT', 12, 0)->toSql());

        $this->assertStringNotContainsString('`fk_pais_id` in', $sql);
    }

    /** B8: vigencia_ini/fin no filtran salvo que se pida explícitamente. */
    public function test_la_vigencia_de_la_comision_no_filtra_por_defecto(): void
    {
        $sinFecha = strtolower((new ScopeResolver)->queryComision(5, 77, 'HOT', 12, 3)->toSql());
        $conFecha = strtolower((new ScopeResolver)->queryComision(5, 77, 'HOT', 12, 3, '2026-10-01')->toSql());

        $this->assertStringNotContainsString('vigencia_ini', $sinFecha);
        $this->assertStringContainsString('`vigencia_ini` <= ?', $conFecha);
        $this->assertStringContainsString('`vigencia_fin` >= ?', $conFecha);
    }

    public function test_iva_usa_la_misma_cascada_mas_sistema(): void
    {
        $sql = strtolower((new ScopeResolver)->queryIva(77, 1, 'HOT', 12, 3)->toSql());

        $this->assertStringContainsString('`fk_sistema_id` in (?, ?)', $sql);
        $this->assertStringEndsWith('order by `fk_producto_id` desc, `fk_ciudad_id` desc, `fk_pais_id` desc, `fk_submodulo_id` desc, `fk_sistema_id` desc limit 1', $sql);
    }
}
