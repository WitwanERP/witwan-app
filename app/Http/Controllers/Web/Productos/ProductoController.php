<?php

namespace App\Http\Controllers\Web\Productos;

use App\Exceptions\Productos\ProductoException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Productos\ProductoRequest;
use App\Services\Productos\HabitacionService;
use App\Services\Productos\ProductoFormulario;
use App\Services\Productos\ProductoService;
use App\Services\Vigencias\VigenciaService;
use App\Support\Permisos;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

/**
 * Productos — front Inertia bajo /app/productos/{sistema}/{tipo}.
 *
 * Un solo controller para los 18 `productos/*.php` del CI: el {tipo} de la URL
 * (slug del legacy: hotel, excursion, circuito...) se resuelve contra
 * config/productos.php y de ahí sale el formulario. Toda la lógica vive en
 * App\Services\Productos.
 *
 * A diferencia del legacy, todas las acciones verifican permiso (ningún
 * controlador de productos llamaba a `_check_perm()`). El módulo arranca en
 * modo observación: ver App\Support\Permisos y productos.permisos_estrictos.
 */
class ProductoController extends Controller
{
    public function __construct(
        private ProductoService $productos,
        private ProductoFormulario $formulario,
    ) {}

    public function index(string $sistema, string $tipo, Request $request)
    {
        [$sistemaId, $codigo, $cfg] = $this->contexto($sistema, $tipo);
        $this->autorizar($cfg, 'acceso');

        $filtros = array_filter($request->only(ProductoService::FILTROS), fn ($v) => $v !== null && $v !== '');

        return Inertia::render('Productos/Index', [
            'config' => $this->config($sistema, $tipo, $cfg),
            'filtros' => $filtros,
            'registros' => $this->productos->listar($sistemaId, $codigo, $filtros, $this->proveedorDelUsuario()),
            'opciones' => [
                'proveedores' => $this->opcion('proveedor', 'proveedor_id', 'proveedor_nombre'),
                'ciudades' => $this->opcion('ciudad', 'ciudad_id', 'ciudad_nombre'),
            ],
        ]);
    }

    public function create(string $sistema, string $tipo)
    {
        [$sistemaId, $codigo, $cfg] = $this->contexto($sistema, $tipo);
        $this->autorizar($cfg, 'alta');

        return Inertia::render('Productos/Form', [
            'config' => $this->config($sistema, $tipo, $cfg),
            'campos' => $this->formulario->campos($codigo, $sistemaId),
            'opciones' => $this->opciones($codigo, $sistemaId),
            'registro' => null,
            'vigencias' => [],
        ]);
    }

    public function store(string $sistema, string $tipo, ProductoRequest $request)
    {
        [$sistemaId, $codigo, $cfg] = $this->contexto($sistema, $tipo);
        $this->autorizar($cfg, 'alta');

        try {
            $id = $this->productos->guardar($codigo, $sistemaId, $request->validated(), (int) auth()->id());
        } catch (ProductoException $e) {
            return $this->falla($e);
        }

        return redirect("/app/productos/{$sistema}/{$tipo}/{$id}/edit")->with('success', 'Producto creado.');
    }

    public function edit(string $sistema, string $tipo, int $id, VigenciaService $vigencias)
    {
        [$sistemaId, $codigo, $cfg] = $this->contexto($sistema, $tipo);
        $this->autorizar($cfg, 'edicion');

        $registro = $this->productos->cargar($id);
        abort_if($registro === null || $registro['fk_tipoproducto_id'] !== $codigo || (int) $registro['fk_sistema_id'] !== $sistemaId, 404);

        return Inertia::render('Productos/Form', [
            'config' => $this->config($sistema, $tipo, $cfg),
            'campos' => $this->formulario->campos($codigo, $sistemaId),
            'opciones' => $this->opciones($codigo, $sistemaId),
            'registro' => $registro,
            'vigencias' => $vigencias->listar($id),
        ]);
    }

    public function update(string $sistema, string $tipo, int $id, ProductoRequest $request)
    {
        [$sistemaId, $codigo, $cfg] = $this->contexto($sistema, $tipo);
        $this->autorizar($cfg, 'edicion');

        try {
            $this->productos->guardar($codigo, $sistemaId, $request->validated(), (int) auth()->id(), $id);
        } catch (ProductoException $e) {
            return $this->falla($e);
        }

        return back()->with('success', 'Producto actualizado.');
    }

    public function clonar(string $sistema, string $tipo, int $id, Request $request, VigenciaService $vigencias)
    {
        [, , $cfg] = $this->contexto($sistema, $tipo);
        $this->autorizar($cfg, 'alta');

        $opciones = $request->validate([
            'con_vigencias' => 'nullable|boolean',
            'nombre' => 'nullable|string|max:255',
        ]);

        try {
            $nuevo = $this->productos->clonar($id, (int) auth()->id(), $opciones, $vigencias);
        } catch (ProductoException $e) {
            return $this->falla($e);
        }

        return redirect("/app/productos/{$sistema}/{$tipo}/{$nuevo}/edit")->with('success', "Producto clonado como #{$nuevo}.");
    }

    public function destroy(string $sistema, string $tipo, int $id)
    {
        [, , $cfg] = $this->contexto($sistema, $tipo);
        $this->autorizar($cfg, 'borrado');

        try {
            $this->productos->eliminar($id);
        } catch (ProductoException $e) {
            return $this->falla($e);
        }

        return redirect("/app/productos/{$sistema}/{$tipo}")->with('success', 'Producto dado de baja.');
    }

    /** JSON: habitaciones del producto (para el selector de cupos y la grilla). */
    public function habitaciones(string $sistema, string $tipo, int $id, HabitacionService $habitaciones)
    {
        [, , $cfg] = $this->contexto($sistema, $tipo);
        $this->autorizar($cfg, 'acceso');

        return response()->json($habitaciones->listar($id));
    }

    // ------------------------------------------------------------------

    /** @return array{0:int,1:string,2:array} */
    private function contexto(string $sistema, string $tipo): array
    {
        $sistemaId = ProductoFormulario::sistemaId($sistema);
        $codigo = ProductoFormulario::tipoPorSlug($tipo);
        abort_if($sistemaId === null || $codigo === null, 404);

        return [$sistemaId, $codigo, $this->formulario->tipo($codigo)];
    }

    private function autorizar(array $cfg, string $accion): void
    {
        Permisos::exigir('productos/'.$cfg['slug'], $accion, 'Productos: '.$cfg['nombre'], 'productos.permisos_estrictos');
    }

    private function config(string $sistema, string $tipo, array $cfg): array
    {
        $seccion = 'productos/'.$cfg['slug'];

        return [
            'sistema' => $sistema,
            'sistemaId' => ProductoFormulario::sistemaId($sistema),
            'tipo' => $cfg['codigo'],
            'slug' => $tipo,
            'titulo' => $cfg['nombre'],
            'baseUrl' => "/app/productos/{$sistema}/{$tipo}",
            'alojamiento' => (bool) $cfg['alojamiento'],
            'habitaciones' => (bool) $cfg['habitaciones'],
            'ciudad' => $cfg['ciudad'],
            'permisos' => [
                'alta' => Permisos::tiene($seccion, 'alta'),
                'edicion' => Permisos::tiene($seccion, 'edicion'),
                'borrado' => Permisos::tiene($seccion, 'borrado'),
            ],
        ];
    }

    private function opciones(string $codigo, int $sistemaId): array
    {
        $fuentes = [
            'proveedores' => fn () => $this->opcion('proveedor', 'proveedor_id', 'proveedor_nombre'),
            'ciudades' => fn () => $this->opcion('ciudad', 'ciudad_id', 'ciudad_nombre'),
            'facilidades' => fn () => $this->opcion('alojamientofacilidad', 'alojamientofacilidad_id', 'alojamientofacilidad_nombre'),
            'alojamientotipos' => fn () => $this->opcion('alojamientotipo', 'alojamientotipo_id', 'alojamientotipo_nombre'),
            'regiones' => fn () => $this->opcion('region', 'region_id', 'region_nombre'),
        ];

        $out = [];
        foreach ($this->formulario->opcionesRequeridas($codigo, $sistemaId) as $clave) {
            if (isset($fuentes[$clave])) {
                $out[$clave] = $fuentes[$clave]();
            }
        }
        // Catálogo de categorías del tipo, para dar de alta habitaciones.
        $out['tarifacategorias'] = DB::table('tarifacategoria')->where('fk_submodulo_id', $codigo)->orderBy('tarifacategoria_nombre')
            ->get(['tarifacategoria_id', 'tarifacategoria_nombre'])
            ->map(fn ($r) => ['value' => (int) $r->tarifacategoria_id, 'label' => $r->tarifacategoria_nombre])->all();

        return $out;
    }

    private function opcion(string $tabla, string $id, string $label): array
    {
        return DB::table($tabla)->orderBy($label)->get([$id, $label])
            ->map(fn ($r) => ['value' => (int) $r->$id, 'label' => (string) $r->$label])->all();
    }

    /** hotel.php:20: un usuario de proveedor (ACP) sólo ve sus productos. */
    private function proveedorDelUsuario(): ?int
    {
        $u = auth()->user();

        return $u !== null && ($u->fk_tipousuario_id ?? '') === 'ACP' ? (int) ($u->fk_proveedor_id ?? 0) : null;
    }

    private function falla(ProductoException $e)
    {
        if ($e->errores() !== []) {
            return back()->withInput()->withErrors($e->errores());
        }

        return back()->withInput()->with('error', $e->getMessage());
    }
}
