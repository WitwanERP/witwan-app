<?php

namespace Tests\Feature\Productos;

use App\Exceptions\Productos\TarifarioException;
use App\Services\Pricing\ScopeResolver;
use App\Services\Tarifarios\TarifarioComisionService;
use App\Services\Tarifarios\TarifarioService;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\CreaEsquemaProductos;
use Tests\TestCase;

/** Comisiones por alcance actualizadas por diferencia (el CI hacía DELETE + reinsert: tarifario.php:390). */
class TarifarioComisionServiceTest extends TestCase
{
    use CreaEsquemaProductos;

    protected function setUp(): void
    {
        parent::setUp();
        $this->crearEsquemaProductos();
    }

    private function filas(): array
    {
        return [
            ['fk_submodulo_id' => '', 'divisor_markup' => 0.8, 'porcentaje_comision' => 10],
            ['fk_submodulo_id' => 'HOT', 'divisor_markup' => 0.75, 'porcentaje_comision' => 12],
            ['fk_submodulo_id' => 'HOT', 'fk_pais_id' => 10, 'fk_ciudad_id' => 1, 'divisor_markup' => 0.7, 'porcentaje_comision' => 12, 'vigencia_ini' => '01/10/2026', 'vigencia_fin' => '2026-12-31'],
            ['fk_producto_id' => 55, 'divisor_markup' => 0.9, 'porcentaje_comision' => 5],
        ];
    }

    public function test_alta_edicion_y_baja_conservan_ids(): void
    {
        $svc = new TarifarioComisionService;

        $s = $svc->sincronizar(3, $this->filas());
        $this->assertSame(4, $s['insertadas']);

        $lista = $svc->listar(3);
        $this->assertTrue($lista[0]['general'], 'la general va primero');
        $this->assertSame('0', $lista[0]['fk_submodulo_id'], 'submódulo vacío se graba como "0" (tarifario.php:409)');
        $this->assertSame('2026-10-01', $lista[2]['vigencia_ini']);
        $this->assertSame('', $lista[3]['vigencia_ini']);
        $idHot = (int) $lista[1]['tarifariocomision_id'];

        $s = $svc->sincronizar(3, [
            ['tarifariocomision_id' => (int) $lista[0]['tarifariocomision_id'], 'fk_submodulo_id' => '0', 'divisor_markup' => 0.8, 'porcentaje_comision' => 10],
            ['tarifariocomision_id' => $idHot, 'fk_submodulo_id' => 'HOT', 'divisor_markup' => 0.72, 'porcentaje_comision' => 12],
        ]);
        $this->assertSame(['insertadas' => 0, 'actualizadas' => 1, 'borradas' => 2, 'sin_cambios' => 1], $s);
        $this->assertEquals(0.72, DB::table('tarifariocomision')->where('tarifariocomision_id', $idHot)->value('divisor_markup'));
        $this->assertSame(2, DB::table('tarifariocomision')->where('fk_tarifario_id', 3)->count());
    }

    public function test_valida_divisor_positivo_y_alcance_unico(): void
    {
        $svc = new TarifarioComisionService;
        try {
            $svc->sincronizar(3, [
                ['fk_submodulo_id' => '', 'divisor_markup' => 0],
                ['fk_submodulo_id' => 'HOT', 'divisor_markup' => 0.8, 'porcentaje_comision' => 101],
                ['fk_submodulo_id' => 'HOT', 'divisor_markup' => 0.9],
                ['fk_submodulo_id' => 'EXC', 'divisor_markup' => 0.9, 'vigencia_ini' => '2026-12-01', 'vigencia_fin' => '2026-01-01'],
            ]);
            $this->fail('debía fallar');
        } catch (TarifarioException $e) {
            $this->assertArrayHasKey('comisiones.0.divisor_markup', $e->errores());
            $this->assertArrayHasKey('comisiones.1.porcentaje_comision', $e->errores());
            $this->assertArrayHasKey('comisiones.2', $e->errores());
            $this->assertArrayHasKey('comisiones.3.vigencia_fin', $e->errores());
        }
        $this->assertSame(0, DB::table('tarifariocomision')->count());
    }

    public function test_scope_resolver_elige_la_fila_mas_especifica(): void
    {
        (new TarifarioComisionService)->sincronizar(3, $this->filas());
        $scope = new ScopeResolver;

        $this->assertEquals(0.9, $scope->comision(3, 55, 'EXC', 9, 9)['divisor_markup'], 'producto gana a todo');
        $this->assertEquals(0.7, $scope->comision(3, 1, 'HOT', 1, 10)['divisor_markup'], 'ciudad gana a submódulo');
        $this->assertEquals(0.75, $scope->comision(3, 1, 'HOT', 2, 10)['divisor_markup'], 'submódulo gana a general');
        $this->assertEquals(0.8, $scope->comision(3, 1, 'EXC', 2, 10)['divisor_markup'], 'general');
        $this->assertNull($scope->comision(4, 1, 'EXC', 2, 10));
    }

    public function test_iva_por_cascada(): void
    {
        DB::table('iva')->insert([
            ['fk_sistema_id' => 0, 'fk_submodulo_id' => '', 'fk_pais_id' => 0, 'fk_ciudad_id' => 0, 'iva_costo' => 21, 'iva_valor' => 21, 'fk_modoivaventa_id' => 1],
            ['fk_sistema_id' => 1, 'fk_submodulo_id' => 'HOT', 'fk_pais_id' => 10, 'fk_ciudad_id' => 0, 'iva_costo' => 10.5, 'iva_valor' => 21, 'fk_modoivaventa_id' => 2],
            ['fk_sistema_id' => 1, 'fk_submodulo_id' => 'HOT', 'fk_pais_id' => 10, 'fk_ciudad_id' => 1, 'iva_costo' => 0, 'iva_valor' => 21, 'fk_modoivaventa_id' => 3],
        ]);
        $scope = new ScopeResolver;

        $this->assertEquals(0, $scope->iva(1, 1, 'HOT', 1, 10)['iva_costo']);
        $this->assertEquals(10.5, $scope->iva(1, 1, 'HOT', 2, 10)['iva_costo']);
        $this->assertEquals(21, $scope->iva(1, 2, 'HOT', 1, 10)['iva_costo']);
        $this->assertEquals(21, $scope->iva(1, 1, 'EXC', 1, 10)['iva_costo']);
    }

    public function test_tarifario_service_alta_edicion_listado_y_baja(): void
    {
        $svc = app(TarifarioService::class);

        $id = $svc->guardar(1, ['tarifario_nombre' => ' Agencias ', 'fk_moneda_id' => 'usd', 'cotizacion' => 1000, 'comisiones' => $this->filas()]);
        $t = $svc->cargar($id);
        $this->assertSame('Agencias', $t['tarifario_nombre']);
        $this->assertSame('USD', $t['fk_moneda_id']);
        $this->assertCount(4, $t['comisiones']);

        $lista = $svc->listar(1);
        $this->assertCount(1, $lista);
        $this->assertEquals(0.8, $lista[0]['divisor_markup'], 'el listado muestra el markup general');

        $svc->guardar(1, ['tarifario_nombre' => 'Agencias 2', 'fk_moneda_id' => 'USD'], $id);
        $this->assertSame('Agencias 2', $svc->cargar($id)['tarifario_nombre']);
        $this->assertCount(4, $svc->cargar($id)['comisiones'], 'sin `comisiones` en el payload no se tocan');

        try {
            $svc->guardar(1, ['tarifario_nombre' => '', 'fk_moneda_id' => '']);
            $this->fail('debía fallar');
        } catch (TarifarioException $e) {
            $this->assertArrayHasKey('tarifario_nombre', $e->errores());
        }

        DB::table('vigencia')->insert(['vigencia_id' => 1, 'fk_producto_id' => 1]);
        DB::table('tarifa')->insert(['fk_vigencia_id' => 1, 'fk_tarifario_id' => $id, 'fk_base_id' => '1', 'costo' => 1]);
        try {
            $svc->eliminar($id);
            $this->fail('debía fallar');
        } catch (TarifarioException $e) {
            $this->assertStringContainsString('venta manual', $e->getMessage());
        }

        DB::table('tarifa')->delete();
        $svc->eliminar($id);
        $this->assertNull($svc->cargar($id));
        $this->assertSame(0, DB::table('tarifariocomision')->count());
    }
}
