<?php

namespace Tests\Unit\Pricing;

use App\Services\Pricing\MarkupCalculadora;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Costo → venta, fiel al orden de Tarifa_model::tarifar() (tarifa_model.php:1505-1514).
 * Reemplaza al "calcular venta" de vigencia.js. Casos en tests/Fixtures/markup_calculadora.json.
 */
class MarkupCalculadoraTest extends TestCase
{
    public static function casos(): array
    {
        $json = json_decode(file_get_contents(__DIR__.'/../../Fixtures/markup_calculadora.json'), true);

        return array_combine(array_column($json, 'nombre'), array_map(fn ($c) => [$c], $json));
    }

    #[DataProvider('casos')]
    public function test_calcula_la_venta(array $caso): void
    {
        $r = (new MarkupCalculadora)->calcular(
            (float) $caso['costo'], (float) $caso['iva_costo'], (float) $caso['cotizacion'],
            (float) $caso['divisor'], (string) $caso['redondeo'], (float) $caso['comision'],
        );

        foreach ($caso['esperado'] as $campo => $valor) {
            $this->assertEqualsWithDelta($valor, $r[$campo], 0.005, "'{$campo}' en: {$caso['nombre']}");
        }
    }

    /** tarifa_model.php:2716: AR + HOT + no residente + receptivo → sin IVA de costo. */
    public function test_iva_de_costo_para_hoteleria_argentina_no_residente(): void
    {
        $c = new MarkupCalculadora;

        $this->assertSame(0.0, $c->ivaCostoAplicable(21, 'HOT', 'AR', 'O', 1));
        $this->assertSame(21.0, $c->ivaCostoAplicable(21, 'HOT', 'AR', 'R', 1));
        $this->assertSame(21.0, $c->ivaCostoAplicable(21, 'HOT', 'AR', '', 1));
        $this->assertSame(21.0, $c->ivaCostoAplicable(21, 'HOT', 'AR', 'O', 2));
        $this->assertSame(21.0, $c->ivaCostoAplicable(21, 'EXC', 'AR', 'O', 1));
        $this->assertSame(19.0, $c->ivaCostoAplicable(19, 'HOT', 'CL', 'O', 1));
    }

    /** vigencia.js:50-53: misma moneda (o tarifario sin moneda) → factor 1. */
    public function test_factor_de_cotizacion(): void
    {
        $c = new MarkupCalculadora;

        $this->assertSame(1.0, $c->factorCotizacion('USD', 1000, 'USD', 1000));
        $this->assertSame(1.0, $c->factorCotizacion('USD', 1000, '', 0));
        $this->assertEqualsWithDelta(1000, $c->factorCotizacion('USD', 1000, 'ARS', 1), 0.0001);
        $this->assertEqualsWithDelta(0.001, $c->factorCotizacion('ARS', 1, 'USD', 1000), 0.0000001);
        $this->assertSame(0.0, $c->factorCotizacion('USD', 1000, 'EUR', 0), 'sin cotización de venta no inventa un factor');
    }
}
