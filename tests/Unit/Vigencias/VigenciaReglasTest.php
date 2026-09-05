<?php

namespace Tests\Unit\Vigencias;

use App\Services\Vigencias\VigenciaReglas;
use Tests\TestCase;

/**
 * Validaciones que el CI no hacía (vigencia.php:159-260): fechas, venta, días,
 * noches mínimas, vencimientos, costos y solapes por prioridad.
 */
class VigenciaReglasTest extends TestCase
{
    private function base(array $extra = []): array
    {
        return array_merge([
            'vigencia_ini' => '2026-10-01',
            'vigencia_fin' => '2026-12-31',
            'vigencia_ventaini' => '',
            'vigencia_ventafin' => '',
            'dias_semana' => [1, 2, 3, 4, 5, 6, 7],
            'noches_minimas' => 0,
            'modo_nochesminimas' => 'X',
            'vencimiento_reserva' => 0,
            'vencimiento_checkin' => 0,
            'promo_noches' => 0,
            'promo_pornoches' => 0,
            'moneda_costo' => 'USD',
            'residente' => '',
            'vigencia_prioridad' => 0,
        ], $extra);
    }

    public function test_una_vigencia_bien_cargada_no_tiene_errores(): void
    {
        $r = (new VigenciaReglas)->validar($this->base(), [['costo' => 100]]);

        $this->assertSame([], $r['errores']);
        $this->assertSame([], $r['avisos']);
    }

    public function test_fin_anterior_a_inicio(): void
    {
        $r = (new VigenciaReglas)->validar($this->base(['vigencia_fin' => '2026-09-30']));

        $this->assertArrayHasKey('vigencia_fin', $r['errores']);
    }

    /** El legacy grababa 1970-01-01 (strtotime(false) → date(0)). */
    public function test_fecha_invalida_es_error_y_no_1970(): void
    {
        $r = (new VigenciaReglas)->validar($this->base(['vigencia_ini' => '31/31/2026']));

        $this->assertArrayHasKey('vigencia_ini', $r['errores']);
    }

    public function test_acepta_fechas_dd_mm_yyyy_como_el_ci(): void
    {
        $r = (new VigenciaReglas)->validar($this->base(['vigencia_ini' => '01/10/2026', 'vigencia_fin' => '31/12/2026']));

        $this->assertSame([], $r['errores']);
    }

    public function test_ventas_coherentes(): void
    {
        $r = (new VigenciaReglas)->validar($this->base(['vigencia_ventaini' => '2026-09-10', 'vigencia_ventafin' => '2026-09-01']));

        $this->assertArrayHasKey('vigencia_ventafin', $r['errores']);
    }

    public function test_al_menos_un_dia(): void
    {
        $r = (new VigenciaReglas)->validar($this->base(['dias_semana' => []]));

        $this->assertArrayHasKey('dias_semana', $r['errores']);
    }

    public function test_noches_minimas_requieren_modo(): void
    {
        $r = (new VigenciaReglas)->validar($this->base(['noches_minimas' => 3, 'modo_nochesminimas' => '']));
        $this->assertArrayHasKey('modo_nochesminimas', $r['errores']);

        $r = (new VigenciaReglas)->validar($this->base(['noches_minimas' => 3, 'modo_nochesminimas' => 'T']));
        $this->assertSame([], $r['errores']);
    }

    public function test_vencimientos_entre_0_y_120(): void
    {
        $r = (new VigenciaReglas)->validar($this->base(['vencimiento_reserva' => 121, 'vencimiento_checkin' => -1]));

        $this->assertArrayHasKey('vencimiento_reserva', $r['errores']);
        $this->assertArrayHasKey('vencimiento_checkin', $r['errores']);
    }

    public function test_promocion_necesita_por_cuantas_noches(): void
    {
        $r = (new VigenciaReglas)->validar($this->base(['promo_noches' => 1, 'promo_pornoches' => 0]));

        $this->assertArrayHasKey('promo_pornoches', $r['errores']);
    }

    public function test_moneda_y_residente(): void
    {
        $r = (new VigenciaReglas)->validar($this->base(['moneda_costo' => '', 'residente' => 'Z']));

        $this->assertArrayHasKey('moneda_costo', $r['errores']);
        $this->assertArrayHasKey('residente', $r['errores']);
    }

    public function test_costos_negativos_o_no_numericos(): void
    {
        $r = (new VigenciaReglas)->validar($this->base(), [
            ['costo' => -1],
            ['costo' => 'abc'],
            ['costo' => 10, 'venta' => [3 => -5]],
            ['costo' => null, 'venta' => [3 => '']],
        ]);

        $this->assertArrayHasKey('tarifas.0.costo', $r['errores']);
        $this->assertArrayHasKey('tarifas.1.costo', $r['errores']);
        $this->assertArrayHasKey('tarifas.2.venta.3', $r['errores']);
        $this->assertArrayNotHasKey('tarifas.3.costo', $r['errores']);
    }

    public function test_sin_ningun_costo_es_aviso_no_error(): void
    {
        $r = (new VigenciaReglas)->validar($this->base(), [['costo' => null], ['costo' => '']]);

        $this->assertSame([], $r['errores']);
        $this->assertCount(1, $r['avisos']);
    }

    public function test_solape_avisa_quien_gana_por_prioridad(): void
    {
        $otras = [
            ['vigencia_id' => 10, 'vigencia_ini' => '2026-11-01', 'vigencia_fin' => '2026-11-30', 'vigencia_prioridad' => 5, 'vigencia_descripcion' => 'Promo noviembre', 'residente' => ''],
            ['vigencia_id' => 11, 'vigencia_ini' => '2027-01-01', 'vigencia_fin' => '2027-01-31', 'vigencia_prioridad' => 0, 'vigencia_descripcion' => 'Enero', 'residente' => ''],
            ['vigencia_id' => 12, 'vigencia_ini' => '2026-10-01', 'vigencia_fin' => '2026-10-31', 'vigencia_prioridad' => 0, 'vigencia_descripcion' => 'Misma prioridad', 'residente' => ''],
            ['vigencia_id' => 13, 'vigencia_ini' => '2026-10-01', 'vigencia_fin' => '2026-10-31', 'vigencia_prioridad' => 0, 'vigencia_descripcion' => 'Residentes', 'residente' => 'R'],
        ];

        $r = (new VigenciaReglas)->validar($this->base(['residente' => 'O', 'vigencia_prioridad' => 1]), [], $otras);

        $this->assertSame([], $r['errores']);
        $this->assertCount(2, $r['avisos']);
        $this->assertStringContainsString('gana "Promo noviembre" (prioridad 5 > 1)', $r['avisos'][0]);
        $this->assertStringContainsString('gana esta vigencia (prioridad 1 > 0)', $r['avisos'][1]);
    }

    public function test_misma_prioridad_avisa_que_gana_el_costo_mas_bajo(): void
    {
        $otras = [['vigencia_id' => 12, 'vigencia_ini' => '2026-10-01', 'vigencia_fin' => '2026-10-31', 'vigencia_prioridad' => 0, 'vigencia_descripcion' => '', 'residente' => '']];

        $r = (new VigenciaReglas)->validar($this->base(), [], $otras);

        $this->assertStringContainsString('misma prioridad (0)', $r['avisos'][0]);
        $this->assertStringContainsString('#12', $r['avisos'][0]);
    }

    public function test_no_se_solapa_consigo_misma_al_editar(): void
    {
        $otras = [['vigencia_id' => 12, 'vigencia_ini' => '2026-10-01', 'vigencia_fin' => '2026-10-31', 'vigencia_prioridad' => 0, 'residente' => '']];

        $r = (new VigenciaReglas)->validar($this->base(['vigencia_id' => 12]), [], $otras);

        $this->assertSame([], $r['avisos']);
    }
}
