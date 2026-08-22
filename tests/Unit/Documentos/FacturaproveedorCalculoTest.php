<?php

namespace Tests\Unit\Documentos;

use App\Exceptions\Documentos\FacturaproveedorException;
use App\Services\Documentos\FacturaproveedorCalculo;
use Illuminate\Cache\ArrayStore;
use Illuminate\Cache\Repository;
use Illuminate\Support\Facades\Cache;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Cálculo de importes de facturas de tercero, fiel a factura3ero::save()
 * (application/controllers/administracion/factura3ero.php:868-880).
 *
 * Los casos viven en tests/Fixtures/facturaproveedor_calculo.json para poder
 * agregarlos sin tocar código y para que sirvan de contrato con el cálculo del
 * front (resources/js/.../calculo.js), que debe dar lo mismo.
 */
class FacturaproveedorCalculoTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Cache::swap(new Repository(new ArrayStore));
    }

    public static function casos(): array
    {
        $json = json_decode(file_get_contents(__DIR__.'/../../Fixtures/facturaproveedor_calculo.json'), true);

        return array_combine(array_column($json, 'nombre'), array_map(fn ($c) => [$c], $json));
    }

    #[DataProvider('casos')]
    public function test_calcula_los_importes_derivados(array $caso): void
    {
        $calculo = $this->calculoPara($caso['pais'], $caso['general']);

        $montos = $calculo->calcular($caso['datos'], $caso['adicionales'] ?? []);

        foreach ($caso['esperado'] as $campo => $valor) {
            $this->assertEqualsWithDelta(
                $valor,
                $montos->$campo,
                0.005,
                "El campo '{$campo}' no coincide en el caso: {$caso['nombre']}"
            );
        }
    }

    public function test_una_factura_sin_importes_no_se_puede_guardar(): void
    {
        $this->expectException(FacturaproveedorException::class);

        $this->calculoPara('AR', 0.21)->calcular(['general' => 0]);
    }

    public function test_el_total_cero_se_detecta_aunque_los_montos_se_cancelen(): void
    {
        // Una nota de crédito mal cargada puede dejar neto + IVA + retención en 0.
        $this->expectException(FacturaproveedorException::class);

        $this->calculoPara('AR', 0.21)->calcular(['exento' => 100, 'otrosimpuestos' => -100]);
    }

    /**
     * `tasageneral` se carga indistintamente como 21 o como 0.21; el legacy
     * resuelve la ambigüedad con el umbral de 1 (factura3ero.php:10).
     */
    public function test_normaliza_la_tasa_general_venga_como_porcentaje_o_coeficiente(): void
    {
        foreach (['21' => 0.21, '0.21' => 0.21, '19' => 0.19] as $configurada => $esperada) {
            $this->tenant('AR');
            Cache::put('sysconfig.base_de_prueba.tasageneral', (string) $configurada, 60);

            $tasas = FacturaproveedorCalculo::paraLicenciaActual()->tasas();

            $this->assertEqualsWithDelta($esperada, $tasas['general'], 0.0001,
                "tasageneral='{$configurada}' debería normalizarse a {$esperada}");
        }
    }

    public function test_sin_tasageneral_configurada_cae_al_valor_del_pais(): void
    {
        $this->tenant('CL');
        Cache::put('sysconfig.base_de_prueba.tasageneral', '', 60);

        $this->assertEqualsWithDelta(0.19, FacturaproveedorCalculo::paraLicenciaActual()->tasas()['general'], 0.0001);
    }

    /** Las tasas que consume el front salen de acá y de ningún otro lado. */
    public function test_expone_las_reglas_de_redondeo_al_front(): void
    {
        $ar = $this->calculoPara('AR', 0.21)->tasas();
        $this->assertSame(2, $ar['decimales']);
        $this->assertFalse($ar['ivatotalEditable']);

        $cl = $this->calculoPara('CL', 0.19)->tasas();
        $this->assertSame(0, $cl['decimales']);
        $this->assertTrue($cl['ivatotalEditable']);
        $this->assertTrue($cl['ivaGeneralEntero']);
    }

    private function calculoPara(string $pais, float $general): FacturaproveedorCalculo
    {
        $this->tenant($pais);

        return new FacturaproveedorCalculo($general, $pais, (array) config('facturaproveedor.alicuotas_fijas'));
    }

    private function tenant(string $pais): void
    {
        app()->instance('tenant', (object) [
            'base' => 'base_de_prueba',
            'pais' => $pais,
            'licencia' => 1,
        ]);
    }
}
