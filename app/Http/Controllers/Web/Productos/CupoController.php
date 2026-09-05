<?php

namespace App\Http\Controllers\Web\Productos;

use App\Exceptions\Productos\CupoException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Productos\CupoRequest;
use App\Http\Requests\Productos\SoldoutRequest;
use App\Services\Cupos\CupoService;
use App\Services\Productos\HabitacionService;
use App\Services\Productos\ProductoService;
use App\Support\Permisos;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

/**
 * Cupos y soldout — /app/productos/{id}/cupos/{categoria}.
 * Reemplaza a producto.php::vercupo/guardarcupo/guardarsoldout/bloqueo/delcupo/delsold.
 */
class CupoController extends Controller
{
    public function __construct(
        private CupoService $cupos,
        private ProductoService $productos,
        private HabitacionService $habitaciones,
    ) {}

    public function calendario(int $producto, int $categoria, Request $request)
    {
        $this->autorizar('acceso');
        $p = $this->producto($producto);

        $mes = (int) $request->get('mes', now()->month);
        $anio = (int) $request->get('anio', now()->year);
        abort_if($mes < 1 || $mes > 12 || $anio < 2000 || $anio > 2100, 404);

        return Inertia::render('Productos/Cupos/Calendario', [
            'producto' => ['producto_id' => (int) $p['producto_id'], 'producto_nombre' => $p['producto_nombre'], 'fk_sistema_id' => (int) $p['fk_sistema_id']],
            'categoria' => $categoria,
            'habitaciones' => $this->habitaciones->listar($producto, true),
            'calendario' => $this->cupos->calendario($producto, $categoria, $mes, $anio),
            'cupos' => $this->cupos->cuposVigentes($producto, $categoria),
            'soldouts' => $this->cupos->soldoutsRecientes($producto, $categoria),
            'tarifarios' => DB::table('tarifario')->where('fk_sistema_id', (int) $p['fk_sistema_id'])->orderBy('tarifario_nombre')->get(['tarifario_id', 'tarifario_nombre'])
                ->map(fn ($t) => ['value' => (int) $t->tarifario_id, 'label' => $t->tarifario_nombre])->all(),
            'baseUrl' => "/app/productos/{$producto}/cupos/{$categoria}",
            'permisos' => ['alta' => Permisos::tiene($this->seccion(), 'alta'), 'borrado' => Permisos::tiene($this->seccion(), 'borrado')],
        ]);
    }

    /** JSON: sólo los datos del mes (para navegar sin recargar). */
    public function mes(int $producto, int $categoria, Request $request)
    {
        $this->autorizar('acceso');
        $datos = $request->validate(['mes' => 'required|integer|min:1|max:12', 'anio' => 'required|integer|min:2000|max:2100']);

        return response()->json($this->cupos->calendario($producto, $categoria, (int) $datos['mes'], (int) $datos['anio']));
    }

    public function storeCupo(int $producto, int $categoria, CupoRequest $request)
    {
        $this->autorizar('alta');
        $this->producto($producto);

        try {
            $id = $this->cupos->crearCupo($request->validated() + ['fk_producto_id' => $producto], (int) auth()->id());
        } catch (CupoException $e) {
            return $this->falla($e);
        }

        return back()->with('success', "Cupo #{$id} cargado.");
    }

    public function storeSoldout(int $producto, int $categoria, SoldoutRequest $request)
    {
        $this->autorizar('alta');
        $this->producto($producto);

        $d = $request->validated();
        $cat = ! empty($d['todos']) ? null : (int) ($d['fk_tarifacategoria_id'] ?? $categoria);

        try {
            $n = $this->cupos->crearSoldout($producto, $cat, $d['vigencia_ini'], $d['vigencia_fin'], (int) auth()->id());
        } catch (CupoException $e) {
            return $this->falla($e);
        }

        return back()->with('success', "{$n} día(s) bloqueado(s).");
    }

    /** JSON: toggle de un día desde el calendario. */
    public function bloqueo(int $producto, int $categoria, Request $request)
    {
        $this->autorizar('alta');
        $d = $request->validate(['fecha' => 'required|date_format:Y-m-d']);

        try {
            $bloqueado = $this->cupos->alternarBloqueo($producto, $categoria, $d['fecha'], (int) auth()->id());
        } catch (CupoException $e) {
            return response()->json(['errores' => $e->errores()], 422);
        }

        return response()->json(['fecha' => $d['fecha'], 'soldout' => $bloqueado]);
    }

    public function destroyCupos(int $producto, int $categoria, Request $request)
    {
        $this->autorizar('borrado');
        $d = $request->validate(['ids' => 'required|array|min:1', 'ids.*' => 'integer|min:1']);

        $n = $this->cupos->eliminarCupos($d['ids']);

        return back()->with('success', "{$n} cupo(s) eliminado(s).");
    }

    public function destroySoldouts(int $producto, int $categoria, Request $request)
    {
        $this->autorizar('borrado');
        $d = $request->validate(['ids' => 'required|array|min:1', 'ids.*' => 'integer|min:1']);

        $n = $this->cupos->eliminarSoldouts($d['ids']);

        return back()->with('success', "{$n} bloqueo(s) eliminado(s).");
    }

    // ------------------------------------------------------------------

    private function producto(int $id): array
    {
        $p = $this->productos->cargar($id);
        abort_if($p === null, 404);

        return $p;
    }

    private function seccion(): string
    {
        return (string) config('productos.secciones.cupos');
    }

    private function autorizar(string $accion): void
    {
        Permisos::exigir($this->seccion(), $accion, 'Cupos', 'productos.permisos_estrictos');
    }

    private function falla(CupoException $e)
    {
        return $e->errores() !== []
            ? back()->withInput()->withErrors($e->errores())
            : back()->withInput()->with('error', $e->getMessage());
    }
}
