<?php

namespace Tests\Unit\Support;

use App\Support\Contable\SemanaBsp;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Agrupación semanal de boletos BSP, fiel a ajax.php::servicioproveedor()
 * (application/controllers/ajax.php:300-370).
 */
class SemanaBspTest extends TestCase
{
    public static function cuartosDeMes(): array
    {
        return [
            'dia 1 abre el primer cuarto' => ['2024-03-01', '2024-03-01'],
            'dia 4 cae en el primer cuarto' => ['2024-03-04', '2024-03-01'],
            'dia 7 cierra el primer cuarto' => ['2024-03-07', '2024-03-01'],
            'dia 8 abre el segundo cuarto' => ['2024-03-08', '2024-03-08'],
            'dia 15 cierra el segundo' => ['2024-03-15', '2024-03-08'],
            'dia 16 abre el tercero' => ['2024-03-16', '2024-03-16'],
            'dia 22 cierra el tercero' => ['2024-03-22', '2024-03-16'],
            'dia 23 abre el cuarto' => ['2024-03-23', '2024-03-23'],
            'fin de mes sigue en el cuarto' => ['2024-03-31', '2024-03-23'],
            'febrero bisiesto' => ['2024-02-29', '2024-02-23'],
        ];
    }

    #[DataProvider('cuartosDeMes')]
    public function test_despues_de_2021_agrupa_por_cuartos_de_mes(string $fecha, string $esperado): void
    {
        $this->assertSame($esperado, SemanaBsp::desde($fecha));
    }

    public function test_el_1_de_diciembre_de_2021_todavia_usa_el_regimen_viejo(): void
    {
        // La comparación del legacy es textual y estricta (`> '2021-12-01'`), así
        // que ese día puntual sigue agrupando por semana calendario.
        $this->assertSame('2021-11-29', SemanaBsp::desde('2021-12-01'));
        $this->assertSame('2021-12-01', SemanaBsp::desde('2021-12-02'));
    }

    public function test_antes_de_2021_agrupa_por_semana_calendario(): void
    {
        // Miércoles 24/11/2021 -> lunes de esa semana.
        $this->assertSame('2021-11-22', SemanaBsp::desde('2021-11-24'));
        $this->assertSame('2021-11-22', SemanaBsp::desde('2021-11-22'));
    }

    public function test_los_domingos_del_regimen_viejo_caen_una_semana_mas_atras(): void
    {
        // Fidelidad al legacy: para domingos usa 'monday last week', que corre el
        // grupo una semana extra hacia atrás. Ver la nota en SemanaBsp.
        $this->assertSame('2021-11-15', SemanaBsp::desde('2021-11-28'));
    }

    #[DataProvider('sinFecha')]
    public function test_sin_fecha_utilizable_no_hay_semana(?string $fecha): void
    {
        $this->assertNull(SemanaBsp::desde($fecha));
    }

    public static function sinFecha(): array
    {
        return [
            'null' => [null],
            'vacia' => [''],
            'fecha cero' => ['0000-00-00'],
            'no parseable' => ['no es una fecha'],
        ];
    }

    public function test_etiqueta_para_el_usuario(): void
    {
        $this->assertSame('SEMANA 04/03/2024', SemanaBsp::etiqueta('2024-03-04'));
        $this->assertSame('SIN SEMANA', SemanaBsp::etiqueta(null));
    }
}
