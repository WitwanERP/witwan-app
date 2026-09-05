<?php

namespace App\Http\Controllers\Web\Productos;

use App\Exceptions\Productos\TarifarioException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Productos\TarifarioRequest;
use App\Models\Moneda;
use App\Services\Productos\ProductoFormulario;
use App\Services\Tarifarios\TarifarioService;
use App\Support\Permisos;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

/** Tarifarios y comisiones — /app/tarifarios/{sistema}. Reemplaza a controllers/tarifario.php. */
class TarifarioController extends Controller
{
    public function __construct(private TarifarioService $tarifarios) {}

    public function index(string $sistema)
    {
        $sistemaId = $this->sistema($sistema);
        $this->autorizar('acceso');

        return Inertia::render('Tarifarios/Index', [
            'config' => $this->config($sistema),
            'registros' => $this->tarifarios->listar($sistemaId),
        ]);
    }

    public function create(string $sistema)
    {
        $this->sistema($sistema);
        $this->autorizar('alta');

        return Inertia::render('Tarifarios/Form', ['config' => $this->config($sistema), 'registro' => null] + $this->catalogos());
    }

    public function store(string $sistema, TarifarioRequest $request)
    {
        $sistemaId = $this->sistema($sistema);
        $this->autorizar('alta');

        try {
            $id = $this->tarifarios->guardar($sistemaId, $request->validated());
        } catch (TarifarioException $e) {
            return $this->falla($e);
        }

        return redirect("/app/tarifarios/{$sistema}/{$id}/edit")->with('success', 'Tarifario creado.');
    }

    public function edit(string $sistema, int $id)
    {
        $sistemaId = $this->sistema($sistema);
        $this->autorizar('edicion');

        $registro = $this->tarifarios->cargar($id);
        abort_if($registro === null || (int) $registro['fk_sistema_id'] !== $sistemaId, 404);

        return Inertia::render('Tarifarios/Form', ['config' => $this->config($sistema), 'registro' => $registro] + $this->catalogos());
    }

    public function update(string $sistema, int $id, TarifarioRequest $request)
    {
        $sistemaId = $this->sistema($sistema);
        $this->autorizar('edicion');

        try {
            $this->tarifarios->guardar($sistemaId, $request->validated(), $id);
        } catch (TarifarioException $e) {
            return $this->falla($e);
        }

        return back()->with('success', 'Tarifario guardado.');
    }

    public function destroy(string $sistema, int $id)
    {
        $this->sistema($sistema);
        $this->autorizar('borrado');

        try {
            $this->tarifarios->eliminar($id);
        } catch (TarifarioException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect("/app/tarifarios/{$sistema}")->with('success', 'Tarifario eliminado.');
    }

    // ------------------------------------------------------------------

    private function sistema(string $slug): int
    {
        $id = ProductoFormulario::sistemaId($slug);
        abort_if($id === null, 404);

        return $id;
    }

    private function config(string $sistema): array
    {
        $s = (string) config('productos.secciones.tarifario');

        return [
            'sistema' => $sistema,
            'titulo' => 'Tarifarios',
            'baseUrl' => "/app/tarifarios/{$sistema}",
            'permisos' => ['alta' => Permisos::tiene($s, 'alta'), 'edicion' => Permisos::tiene($s, 'edicion'), 'borrado' => Permisos::tiene($s, 'borrado')],
        ];
    }

    private function catalogos(): array
    {
        return [
            'monedas' => Moneda::opciones(),
            'submodulos' => DB::table('submodulo')->orderBy('submodulo_orden')->get(['tipoproducto_id', 'tipoproducto_nombre'])
                ->map(fn ($r) => ['value' => $r->tipoproducto_id, 'label' => $r->tipoproducto_nombre])->all(),
            'paises' => DB::table('pais')->orderBy('pais_nombre')->get(['pais_id', 'pais_nombre'])
                ->map(fn ($r) => ['value' => (int) $r->pais_id, 'label' => $r->pais_nombre])->all(),
        ];
    }

    private function autorizar(string $accion): void
    {
        Permisos::exigir((string) config('productos.secciones.tarifario'), $accion, 'Tarifarios', 'productos.permisos_estrictos');
    }

    private function falla(TarifarioException $e)
    {
        return $e->errores() !== []
            ? back()->withInput()->withErrors($e->errores())
            : back()->withInput()->with('error', $e->getMessage());
    }
}
