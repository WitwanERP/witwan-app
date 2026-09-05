<?php

namespace App\Http\Controllers\Web\Productos;

use App\Exceptions\Productos\VigenciaException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Productos\VigenciaRequest;
use App\Models\Moneda;
use App\Services\Pricing\VentaPreview;
use App\Services\Productos\ProductoFormulario;
use App\Services\Productos\ProductoService;
use App\Services\Vigencias\VigenciaService;
use App\Support\Permisos;
use Illuminate\Http\Request;
use Inertia\Inertia;

/**
 * Vigencias y tarifas de un producto — /app/productos/{id}/vigencias.
 * Reemplaza a controllers/vigencia.php. El link de vuelta al producto sale
 * del tipo/sistema del producto, como el `$redirect` del legacy.
 */
class VigenciaController extends Controller
{
    public function __construct(
        private VigenciaService $vigencias,
        private ProductoService $productos,
    ) {}

    public function index(int $producto)
    {
        $this->autorizar('acceso');
        $p = $this->producto($producto);

        return Inertia::render('Productos/Vigencia/Index', [
            'producto' => $this->resumen($p),
            'vigencias' => $this->vigencias->listar($producto),
            'baseUrl' => "/app/productos/{$producto}/vigencias",
        ]);
    }

    public function create(int $producto)
    {
        $this->autorizar('alta');
        $this->producto($producto);

        return $this->form($producto, null);
    }

    public function store(int $producto, VigenciaRequest $request)
    {
        $this->autorizar('alta');

        try {
            $r = $this->vigencias->guardar($producto, $request->validated());
        } catch (VigenciaException $e) {
            return $this->falla($e);
        }

        return redirect("/app/productos/{$producto}/vigencias/{$r['id']}/edit")
            ->with('success', 'Vigencia creada.')
            ->with('avisos', $r['avisos']);
    }

    public function edit(int $producto, int $vigencia)
    {
        $this->autorizar('edicion');

        return $this->form($producto, $vigencia);
    }

    public function update(int $producto, int $vigencia, VigenciaRequest $request)
    {
        $this->autorizar('edicion');

        try {
            $r = $this->vigencias->guardar($producto, $request->validated(), $vigencia);
        } catch (VigenciaException $e) {
            return $this->falla($e);
        }

        $t = $r['tarifas'];

        return back()
            ->with('success', "Vigencia guardada ({$t['insertadas']} tarifas nuevas, {$t['actualizadas']} modificadas, {$t['borradas']} borradas).")
            ->with('avisos', $r['avisos']);
    }

    public function clonar(int $producto, int $vigencia, Request $request)
    {
        $this->autorizar('alta');
        $this->producto($producto);

        $opciones = $request->validate([
            'desplazar_dias' => 'nullable|integer|min:-3660|max:3660',
            'desplazar_meses' => 'nullable|integer|min:-120|max:120',
            'ajustar_pct' => 'nullable|numeric|min:-100|max:1000',
            'descripcion' => 'nullable|string|max:2000',
        ]);

        try {
            $nuevo = $this->vigencias->clonar($vigencia, $opciones);
        } catch (VigenciaException $e) {
            return $this->falla($e);
        }

        return redirect("/app/productos/{$producto}/vigencias/{$nuevo}/edit")->with('success', "Vigencia clonada como #{$nuevo}.");
    }

    public function destroy(int $producto, int $vigencia)
    {
        $this->autorizar('borrado');
        $p = $this->producto($producto);

        $this->vigencias->eliminar($vigencia);

        return redirect($this->urlProducto($p))->with('success', 'Vigencia eliminada.');
    }

    /**
     * JSON: preview de venta por tarifario para las celdas mandadas.
     * Payload: { moneda_costo, residente, redondeo, costos: [{clave, costo}] }.
     */
    public function previewVenta(int $producto, Request $request, VentaPreview $preview)
    {
        $this->autorizar('acceso');
        $p = $this->producto($producto);

        $datos = $request->validate([
            'moneda_costo' => 'required|string|max:3',
            'residente' => 'nullable|string|in:,R,O',
            'redondeo' => 'nullable|string|in: ,R,C',
            'costos' => 'required|array',
            'costos.*.clave' => 'required|string|max:40',
            'costos.*.costo' => 'required|numeric|min:0',
        ]);

        return response()->json($preview->calcular(
            $p, $datos['moneda_costo'], (string) ($datos['residente'] ?? ''), (string) ($datos['redondeo'] ?? ' '),
            $this->vigencias->tarifarios($p), $datos['costos'],
        ));
    }

    // ------------------------------------------------------------------

    private function form(int $producto, ?int $vigencia)
    {
        $datos = $this->vigencias->paraFormulario($producto, $vigencia);
        abort_if($datos === null, 404);

        return Inertia::render('Productos/Vigencia/Form', $datos + [
            'monedas' => Moneda::opciones(),
            'baseUrl' => "/app/productos/{$producto}/vigencias",
            'urlProducto' => $this->urlProducto($this->productos->cargar($producto)),
            'permisos' => [
                'alta' => Permisos::tiene(config('productos.secciones.vigencia'), 'alta'),
                'edicion' => Permisos::tiene(config('productos.secciones.vigencia'), 'edicion'),
                'borrado' => Permisos::tiene(config('productos.secciones.vigencia'), 'borrado'),
            ],
        ]);
    }

    private function producto(int $id): array
    {
        $p = $this->productos->cargar($id);
        abort_if($p === null, 404);

        return $p;
    }

    private function resumen(array $p): array
    {
        return [
            'producto_id' => (int) $p['producto_id'],
            'producto_nombre' => $p['producto_nombre'],
            'fk_tipoproducto_id' => $p['fk_tipoproducto_id'],
            'fk_sistema_id' => (int) $p['fk_sistema_id'],
            'url' => $this->urlProducto($p),
        ];
    }

    private function urlProducto(array $p): string
    {
        $sistema = ProductoFormulario::sistemaSlug((int) $p['fk_sistema_id']) ?? 'receptivo';
        $slug = config("productos.tipos.{$p['fk_tipoproducto_id']}.slug", 'hotel');

        return "/app/productos/{$sistema}/{$slug}/{$p['producto_id']}/edit";
    }

    private function autorizar(string $accion): void
    {
        Permisos::exigir((string) config('productos.secciones.vigencia'), $accion, 'Vigencias', 'productos.permisos_estrictos');
    }

    private function falla(VigenciaException $e)
    {
        if ($e->errores() !== []) {
            return back()->withInput()->withErrors($e->errores());
        }

        return back()->withInput()->with('error', $e->getMessage());
    }
}
