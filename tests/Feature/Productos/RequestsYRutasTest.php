<?php

namespace Tests\Feature\Productos;

use App\Http\Requests\Productos\CupoRequest;
use App\Http\Requests\Productos\ProductoRequest;
use App\Http\Requests\Productos\SoldoutRequest;
use App\Http\Requests\Productos\TarifarioRequest;
use App\Http\Requests\Productos\VigenciaRequest;
use Illuminate\Routing\Route as RutaInterna;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

/**
 * Forma de los payloads (Form Requests) y contrato de rutas bajo /app.
 * Los controllers se prueban a través de los services; acá se verifica que
 * cada URL exista, con el verbo y el nombre esperados, y que las reglas de
 * validación rechacen lo que el legacy dejaba pasar.
 */
class RequestsYRutasTest extends TestCase
{
    private function errores(string $request, array $datos, array $parametros = []): array
    {
        $r = new $request;
        if ($parametros !== []) {
            $ruta = (new RutaInterna('POST', '/x', fn () => null))->bind(request());
            foreach ($parametros as $k => $v) {
                $ruta->setParameter($k, $v);
            }
            $r->setRouteResolver(fn () => $ruta);
        }

        return Validator::make($datos, $r->rules(), $r->messages())->errors()->keys();
    }

    public function test_producto_request_deriva_reglas_del_tipo(): void
    {
        app()->instance('tenant', (object) ['base' => 'witwan_rays', 'pais' => 'AR', 'licencia' => 1]);
        $p = ['sistema' => 'receptivo', 'tipo' => 'hotel'];

        $this->assertSame([], $this->errores(ProductoRequest::class, ['producto_nombre' => 'H', 'fk_proveedor_id' => 1, 'modotarifa' => 'P', 'edad_infoa' => 2, 'fk_hotelcategoria_id' => 4, 'bases' => [1, 2]], $p));

        $e = $this->errores(ProductoRequest::class, ['producto_nombre' => '', 'fk_proveedor_id' => 0, 'edad_infoa' => 99, 'fk_hotelcategoria_id' => 9, 'bases' => [0, 'MN'], 'destinos' => [['ciudad_id' => 0, 'tipo' => 'Z']]], $p);
        foreach (['producto_nombre', 'fk_proveedor_id', 'edad_infoa', 'fk_hotelcategoria_id', 'bases.0', 'bases.1', 'destinos.0.ciudad_id', 'destinos.0.tipo'] as $campo) {
            $this->assertContains($campo, $e);
        }
    }

    public function test_vigencia_request(): void
    {
        $ok = ['vigencia_ini' => '01/10/2026', 'vigencia_fin' => '2026-12-31', 'dias_semana' => [1, 7], 'moneda_costo' => 'USD', 'redondeo' => 'R',
            'tarifas' => [['categoria' => 7, 'base' => 'MN', 'costo' => 10, 'venta' => [3 => 15]]],
            'tramos' => [['min' => 1, 'max' => 2, 'costos' => ['ADU' => 10], 'venta' => [3 => ['ADU' => 12]]]]];
        $this->assertSame([], $this->errores(VigenciaRequest::class, $ok));

        $e = $this->errores(VigenciaRequest::class, ['vigencia_ini' => '2026-13-45', 'dias_semana' => [], 'moneda_costo' => '', 'redondeo' => 'X', 'vencimiento_reserva' => 121,
            'residente' => 'Z', 'tarifas' => [['categoria' => 7, 'costo' => -1]], 'tramos' => [['min' => 'a', 'costos' => ['ADU' => 'x']]]]);
        foreach (['vigencia_ini', 'vigencia_fin', 'dias_semana', 'moneda_costo', 'redondeo', 'vencimiento_reserva', 'residente', 'tarifas.0.base', 'tarifas.0.costo', 'tramos.0.min', 'tramos.0.max', 'tramos.0.costos.ADU'] as $campo) {
            $this->assertContains($campo, $e);
        }
    }

    public function test_tarifario_cupo_y_soldout_requests(): void
    {
        $this->assertSame([], $this->errores(TarifarioRequest::class, ['tarifario_nombre' => 'A', 'fk_moneda_id' => 'USD', 'comisiones' => [['divisor_markup' => 0.8]]]));
        $e = $this->errores(TarifarioRequest::class, ['tarifario_nombre' => '', 'fk_moneda_id' => 'USDX', 'comisiones' => [['divisor_markup' => 0, 'porcentaje_comision' => 101]]]);
        foreach (['tarifario_nombre', 'fk_moneda_id', 'comisiones.0.divisor_markup', 'comisiones.0.porcentaje_comision'] as $campo) {
            $this->assertContains($campo, $e);
        }

        $this->assertSame([], $this->errores(CupoRequest::class, ['fk_tarifacategoria_id' => 7, 'vigencia_ini' => '2026-10-01', 'vigencia_fin' => '2026-10-31', 'cantidad' => 0]));
        $this->assertContains('cantidad', $this->errores(CupoRequest::class, ['fk_tarifacategoria_id' => 7, 'vigencia_ini' => '2026-10-01', 'vigencia_fin' => '2026-10-31', 'cantidad' => -1]));

        $this->assertSame([], $this->errores(SoldoutRequest::class, ['todos' => 1, 'vigencia_ini' => '2026-10-01', 'vigencia_fin' => '2026-10-31']));
        $this->assertContains('vigencia_fin', $this->errores(SoldoutRequest::class, ['vigencia_ini' => '2026-10-01']));
    }

    public function test_rutas_bajo_app(): void
    {
        $esperadas = [
            'productos.index' => ['GET', 'app/productos/{sistema}/{tipo}'],
            'productos.create' => ['GET', 'app/productos/{sistema}/{tipo}/create'],
            'productos.store' => ['POST', 'app/productos/{sistema}/{tipo}'],
            'productos.edit' => ['GET', 'app/productos/{sistema}/{tipo}/{id}/edit'],
            'productos.update' => ['PUT', 'app/productos/{sistema}/{tipo}/{id}'],
            'productos.clonar' => ['POST', 'app/productos/{sistema}/{tipo}/{id}/clonar'],
            'productos.destroy' => ['DELETE', 'app/productos/{sistema}/{tipo}/{id}'],
            'productos.habitaciones' => ['GET', 'app/productos/{sistema}/{tipo}/{id}/habitaciones'],
            'vigencias.index' => ['GET', 'app/productos/{producto}/vigencias'],
            'vigencias.create' => ['GET', 'app/productos/{producto}/vigencias/create'],
            'vigencias.store' => ['POST', 'app/productos/{producto}/vigencias'],
            'vigencias.preview-venta' => ['POST', 'app/productos/{producto}/vigencias/preview-venta'],
            'vigencias.edit' => ['GET', 'app/productos/{producto}/vigencias/{vigencia}/edit'],
            'vigencias.update' => ['PUT', 'app/productos/{producto}/vigencias/{vigencia}'],
            'vigencias.clonar' => ['POST', 'app/productos/{producto}/vigencias/{vigencia}/clonar'],
            'vigencias.destroy' => ['DELETE', 'app/productos/{producto}/vigencias/{vigencia}'],
            'cupos.calendario' => ['GET', 'app/productos/{producto}/cupos/{categoria}'],
            'cupos.mes' => ['GET', 'app/productos/{producto}/cupos/{categoria}/mes'],
            'cupos.cupo.store' => ['POST', 'app/productos/{producto}/cupos/{categoria}/cupo'],
            'cupos.soldout.store' => ['POST', 'app/productos/{producto}/cupos/{categoria}/soldout'],
            'cupos.bloqueo' => ['POST', 'app/productos/{producto}/cupos/{categoria}/bloqueo'],
            'cupos.cupo.eliminar' => ['POST', 'app/productos/{producto}/cupos/{categoria}/cupo/eliminar'],
            'cupos.soldout.eliminar' => ['POST', 'app/productos/{producto}/cupos/{categoria}/soldout/eliminar'],
            'tarifarios.index' => ['GET', 'app/tarifarios/{sistema}'],
            'tarifarios.create' => ['GET', 'app/tarifarios/{sistema}/create'],
            'tarifarios.store' => ['POST', 'app/tarifarios/{sistema}'],
            'tarifarios.edit' => ['GET', 'app/tarifarios/{sistema}/{id}/edit'],
            'tarifarios.update' => ['PUT', 'app/tarifarios/{sistema}/{id}'],
            'tarifarios.destroy' => ['DELETE', 'app/tarifarios/{sistema}/{id}'],
        ];

        $rutas = Route::getRoutes();
        foreach ($esperadas as $nombre => [$verbo, $uri]) {
            $ruta = $rutas->getByName($nombre);
            $this->assertNotNull($ruta, "falta la ruta {$nombre}");
            $this->assertSame($uri, $ruta->uri(), $nombre);
            $this->assertContains($verbo, $ruta->methods(), $nombre);
        }
    }

    /** {sistema} y {tipo} sólo aceptan los slugs de config: cualquier otra cosa es 404, no un producto de tipo inventado. */
    public function test_slugs_de_sistema_y_tipo_restringidos(): void
    {
        $ruta = Route::getRoutes()->getByName('productos.index');

        $this->assertMatchesRegularExpression('/^'.$ruta->wheres['sistema'].'$/', 'receptivo');
        $this->assertDoesNotMatchRegularExpression('/^'.$ruta->wheres['sistema'].'$/', 'otro');
        $this->assertMatchesRegularExpression('/^'.$ruta->wheres['tipo'].'$/', 'circuito');
        $this->assertDoesNotMatchRegularExpression('/^'.$ruta->wheres['tipo'].'$/', 'pkd');

        // El id de producto de vigencias/cupos es numérico: no choca con {sistema}/{tipo}.
        $this->assertSame('[0-9]+', Route::getRoutes()->getByName('vigencias.index')->wheres['producto']);
    }
}
