<?php

namespace Tests\Feature\Productos;

use App\Exceptions\Productos\CupoException;
use App\Services\Cupos\CupoService;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\CreaEsquemaProductos;
use Tests\TestCase;

/** Cupos y soldout fieles al contrato del tarifador (una fila por día). */
class CupoServiceTest extends TestCase
{
    use CreaEsquemaProductos;

    private int $producto;

    protected function setUp(): void
    {
        parent::setUp();
        $this->crearEsquemaProductos();
        $this->producto = $this->productoDePrueba();
        DB::table('alojamientohabitacion')->insert([
            ['fk_producto_id' => $this->producto, 'fk_tarifacategoria_id' => 7, 'habilitar' => 1],
            ['fk_producto_id' => $this->producto, 'fk_tarifacategoria_id' => 8, 'habilitar' => 1],
            ['fk_producto_id' => $this->producto, 'fk_tarifacategoria_id' => 9, 'habilitar' => 0],
        ]);
    }

    public function test_cupo_con_cantidad_cero_es_freesale(): void
    {
        $svc = new CupoService;

        $id = $svc->crearCupo(['fk_producto_id' => $this->producto, 'fk_tarifacategoria_id' => 7, 'cantidad' => 0, 'bases' => ['1', '2'], 'vigencia_ini' => '01/10/2026', 'vigencia_fin' => '2026-10-31', 'release' => 3], 5);
        $c = DB::table('cupo')->where('cupo_id', $id)->first();
        $this->assertSame(1, (int) $c->freesale);
        $this->assertSame('1,2', $c->bases);
        $this->assertSame('2026-10-01', $c->vigencia_ini);
        $this->assertSame(5, (int) $c->fk_usuario_id);

        $id = $svc->crearCupo(['fk_producto_id' => $this->producto, 'fk_tarifacategoria_id' => 7, 'cantidad' => 4, 'vigencia_ini' => '2026-10-01', 'vigencia_fin' => '2026-10-31'], 5);
        $this->assertSame(0, (int) DB::table('cupo')->where('cupo_id', $id)->value('freesale'));

        $this->expectException(CupoException::class);
        $svc->crearCupo(['fk_producto_id' => $this->producto, 'cantidad' => 1, 'vigencia_ini' => '2026-10-31', 'vigencia_fin' => '2026-10-01'], 5);
    }

    public function test_soldout_por_rango_graba_una_fila_por_dia_sin_duplicar(): void
    {
        $svc = new CupoService;

        $n = $svc->crearSoldout($this->producto, 7, '2026-10-01', '2026-10-05', 5);
        $this->assertSame(5, $n);
        $filas = DB::table('soldout')->where('fk_tarifacategoria_id', 7)->orderBy('vigencia_ini')->get();
        $this->assertSame(['2026-10-01', '2026-10-02', '2026-10-03', '2026-10-04', '2026-10-05'], $filas->pluck('vigencia_ini')->all());
        $this->assertTrue($filas->every(fn ($f) => $f->vigencia_ini === $f->vigencia_fin), 'vigencia_ini = vigencia_fin (tarifa_model.php:2766)');

        $this->assertSame(2, $svc->crearSoldout($this->producto, 7, '2026-10-04', '2026-10-07', 5), 'los ya bloqueados no se duplican');
    }

    public function test_soldout_todos_bloquea_solo_las_habilitadas(): void
    {
        $n = (new CupoService)->crearSoldout($this->producto, null, '2026-10-01', '2026-10-02', 5);

        $this->assertSame(4, $n);
        $this->assertSame([7, 8], DB::table('soldout')->distinct()->orderBy('fk_tarifacategoria_id')->pluck('fk_tarifacategoria_id')->map(fn ($c) => (int) $c)->all());
    }

    public function test_alternar_bloqueo(): void
    {
        $svc = new CupoService;

        $this->assertTrue($svc->alternarBloqueo($this->producto, 7, '2026-10-10', 5));
        $this->assertSame(1, DB::table('soldout')->count());
        $this->assertFalse($svc->alternarBloqueo($this->producto, 7, '2026-10-10', 5));
        $this->assertSame(0, DB::table('soldout')->count());
    }

    public function test_calendario_mensual(): void
    {
        $svc = new CupoService;
        $svc->crearCupo(['fk_producto_id' => $this->producto, 'fk_tarifacategoria_id' => 7, 'cantidad' => 3, 'vigencia_ini' => '2026-09-25', 'vigencia_fin' => '2026-10-05'], 5);
        $svc->crearCupo(['fk_producto_id' => $this->producto, 'fk_tarifacategoria_id' => 7, 'cantidad' => 0, 'vigencia_ini' => '2026-10-03', 'vigencia_fin' => '2026-10-03'], 5);
        $svc->crearCupo(['fk_producto_id' => $this->producto, 'fk_tarifacategoria_id' => 8, 'cantidad' => 9, 'vigencia_ini' => '2026-10-01', 'vigencia_fin' => '2026-10-31'], 5);
        $svc->crearSoldout($this->producto, 7, '2026-10-04', '2026-10-04', 5);
        DB::table('reserva')->insert([['reserva_id' => 1, 'tipocodigo' => 'R', 'codigo' => '100', 'fk_filestatus_id' => 'CO'], ['reserva_id' => 2, 'tipocodigo' => 'R', 'codigo' => '101', 'fk_filestatus_id' => 'CA']]);
        DB::table('servicio')->insert([
            ['fk_reserva_id' => 1, 'fk_producto_id' => $this->producto, 'fk_tarifacategoria_id' => 7, 'vigencia_ini' => '2026-10-02', 'vigencia_fin' => '2026-10-04', 'status' => 'CO'],
            ['fk_reserva_id' => 2, 'fk_producto_id' => $this->producto, 'fk_tarifacategoria_id' => 7, 'vigencia_ini' => '2026-10-02', 'vigencia_fin' => '2026-10-02', 'status' => 'CA'],
        ]);

        DB::enableQueryLog();
        $cal = $svc->calendario($this->producto, 7, 10, 2026);
        $consultas = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertSame(3, $consultas, 'tres queries por mes, no tres por día');
        $this->assertCount(31, $cal['dias']);
        $this->assertSame(['total' => 3, 'freesale' => false, 'soldout' => false, 'asignado' => 0, 'files' => []], $cal['dias']['2026-10-01']);
        $this->assertSame(4, $cal['dias']['2026-10-03']['total'], 'freesale suma 1 (vercupo :727)');
        $this->assertTrue($cal['dias']['2026-10-03']['freesale']);
        $this->assertTrue($cal['dias']['2026-10-04']['soldout']);
        $this->assertSame(1, $cal['dias']['2026-10-02']['asignado'], 'los servicios cancelados no cuentan');
        $this->assertSame('R-100', $cal['dias']['2026-10-02']['files'][0]['codigo']);
        $this->assertSame(0, $cal['dias']['2026-10-06']['total']);
        $this->assertSame(4, $cal['maximo']);
        $this->assertSame(['mes' => 9, 'anio' => 2026], $cal['anterior']);
        $this->assertSame(['mes' => 11, 'anio' => 2026], $cal['siguiente']);
    }

    /** B10: los ids se validan como enteros antes del IN. */
    public function test_borrados_masivos_validan_ids(): void
    {
        $svc = new CupoService;
        $a = $svc->crearCupo(['fk_producto_id' => $this->producto, 'fk_tarifacategoria_id' => 7, 'cantidad' => 1, 'vigencia_ini' => '2026-10-01', 'vigencia_fin' => '2026-10-02'], 5);
        $b = $svc->crearCupo(['fk_producto_id' => $this->producto, 'fk_tarifacategoria_id' => 7, 'cantidad' => 1, 'vigencia_ini' => '2026-10-01', 'vigencia_fin' => '2026-10-02'], 5);
        $svc->crearSoldout($this->producto, 7, '2026-10-01', '2026-10-02', 5);

        $this->assertSame(1, $svc->eliminarCupos([$a, '1 OR 1=1', 'x']));
        $this->assertSame(1, DB::table('cupo')->count());
        $this->assertSame(2, $svc->eliminarSoldouts(DB::table('soldout')->pluck('soldout_id')->all()));

        $this->expectException(CupoException::class);
        $svc->eliminarCupos(['abc']);
    }
}
