<?php

namespace App\Http\Controllers\Web\Documentos;

use App\Exceptions\Documentos\FacturaproveedorException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Documentos\FacturaproveedorRequest;
use App\Services\CotizacionService;
use App\Services\Documentos\AdjuntoFacturaService;
use App\Services\Documentos\FacturaproveedorCalculo;
use App\Services\Documentos\FacturaproveedorExportService;
use App\Services\Documentos\FacturaproveedorListadoService;
use App\Services\Documentos\FacturaproveedorOcupacionService;
use App\Services\Documentos\FacturaproveedorService;
use App\Support\Permisos;
use App\Support\Secciones;
use Illuminate\Http\Request;
use Inertia\Inertia;

/**
 * Facturas de Tercero — front Inertia bajo /app.
 *
 * Reemplaza a `administracion/factura3ero` del CI legacy
 * (application/controllers/administracion/factura3ero.php). Toda la lógica de
 * negocio vive en los services de App\Services\Documentos, que comparte con el
 * controller de la API (Admin\Documentos\FacturaproveedorController): el front
 * NO consume la API por HTTP.
 *
 * A diferencia del legacy, todas las acciones verifican permiso. En el CI los
 * métodos create/edit/save/save_after_edit sobreescribían los del
 * Admin_Controller y nunca llamaban a `_check_perm()`, así que cualquier usuario
 * con acceso podía dar de alta por URL directa. Ver App\Support\Permisos para el
 * modo de despliegue.
 */
class FacturaproveedorController extends Controller
{
    private const RUTA = '/app/facturas-proveedor';

    public function __construct(
        private FacturaproveedorService $facturas,
        private FacturaproveedorListadoService $listado,
    ) {}

    // ------------------------------------------------------------------
    // Listado
    // ------------------------------------------------------------------

    public function index(Request $request)
    {
        $this->autorizar('acceso');

        $filtros = $this->filtros($request);

        return Inertia::render('Documentos/FacturasProveedor/Index', [
            'config' => $this->config('listado'),
            'opciones' => $this->opcionesFiltro(),
            'filtros' => $filtros,
            'registros' => $this->listado->hayFiltros($filtros)
                ? $this->listado->listar($filtros)
                : $this->vacio(),
            'totales' => $this->listado->hayFiltros($filtros)
                ? $this->listado->totales($filtros)
                : ['porTipo' => [], 'general' => null],
        ]);
    }

    /**
     * Subdiario de Compras: la misma consulta con otro juego de columnas
     * (factura3ero.php:1580-1687).
     */
    public function subdiario(Request $request)
    {
        $this->autorizar('acceso');

        $filtros = $this->filtros($request);

        return Inertia::render('Documentos/FacturasProveedor/Subdiario', [
            'config' => $this->config('subdiario'),
            'opciones' => $this->opcionesFiltro(),
            'filtros' => $filtros,
            'registros' => $this->listado->hayFiltros($filtros)
                ? $this->listado->listar($filtros)
                : $this->vacio(),
            'totales' => $this->listado->hayFiltros($filtros)
                ? $this->listado->totales($filtros)
                : ['porTipo' => [], 'general' => null],
        ]);
    }

    public function exportar(Request $request, FacturaproveedorExportService $export)
    {
        $this->autorizar('acceso');

        $vista = $request->get('vista') === 'subdiario' ? 'subdiario' : 'listado';

        return $export->descargar($this->filtros($request), $vista);
    }

    // ------------------------------------------------------------------
    // Detalle
    // ------------------------------------------------------------------

    public function show(int $id, Request $request)
    {
        $this->autorizar('view');

        $datos = $this->facturas->paraVer($id);
        abort_if($datos === null, 404);

        return Inertia::render('Documentos/FacturasProveedor/Ver', [
            'detalle' => $datos,
            'embed' => $request->boolean('embed'),
            'baseUrl' => self::RUTA,
        ]);
    }

    // ------------------------------------------------------------------
    // Alta
    // ------------------------------------------------------------------

    public function create()
    {
        $this->autorizar('alta');

        return Inertia::render('Documentos/FacturasProveedor/Form', [
            'modo' => 'crear',
            'registro' => null,
            'opciones' => $this->facturas->opcionesFormulario(),
            'monedas' => \App\Models\Moneda::opciones(),
            'baseUrl' => self::RUTA,
        ]);
    }

    public function store(FacturaproveedorRequest $request, AdjuntoFacturaService $adjuntos)
    {
        $this->autorizar('alta');

        $datos = $request->validated();
        unset($datos['archivo']);

        try {
            $id = $this->facturas->crear($datos, (int) auth()->id());
        } catch (FacturaproveedorException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        // El adjunto se guarda después del alta porque el nombre lleva el id de
        // la factura; si falla, la factura ya quedó bien creada.
        if ($request->hasFile('archivo')) {
            $this->facturas->guardarAdjunto($id, $adjuntos->guardar($request->file('archivo'), $id));
        }

        return redirect(self::RUTA.'/'.$id)->with('success', 'Factura de tercero creada.');
    }

    /** Descarga del adjunto. En el legacy el link estaba roto: ver AdjuntoFacturaService. */
    public function archivo(int $id, AdjuntoFacturaService $adjuntos)
    {
        $this->autorizar('view');

        $detalle = $this->facturas->paraVer($id);
        abort_if($detalle === null, 404);

        $ruta = $adjuntos->ruta($detalle['factura']['archivo'] ?? null);
        abort_if($ruta === null, 404, 'La factura no tiene un archivo adjunto.');

        return response()->download($ruta);
    }

    // ------------------------------------------------------------------
    // Edición y baja
    // ------------------------------------------------------------------

    public function edit(int $id)
    {
        $this->autorizar('edicion');

        $registro = $this->facturas->paraEditar($id);
        abort_if($registro === null, 404);

        return Inertia::render('Documentos/FacturasProveedor/Form', [
            'modo' => 'editar',
            'registro' => $registro,
            'opciones' => $this->facturas->opcionesFormulario(),
            'monedas' => \App\Models\Moneda::opciones(),
            'baseUrl' => self::RUTA,
            // El legacy deshabilita todo salvo estos campos.
            'editables' => ['fk_plancuenta_id', 'fk_proyecto_id', 'fk_itemgasto_id', 'facturaproveedor_nro', 'areaimputacion'],
        ]);
    }

    public function update(FacturaproveedorRequest $request, int $id)
    {
        $this->autorizar('edicion');

        try {
            $this->facturas->actualizar($id, $request->validated(), (int) auth()->id());
        } catch (FacturaproveedorException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect(self::RUTA.'/'.$id)->with('success', 'Factura actualizada y recontabilizada.');
    }

    public function destroy(int $id)
    {
        $this->autorizar('borrado');

        try {
            $this->facturas->eliminar($id);
        } catch (FacturaproveedorException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect(self::RUTA)->with('success', 'Factura eliminada.');
    }

    // ------------------------------------------------------------------
    // Endpoints JSON auxiliares (los consume el Vue con fetch, no Inertia)
    // ------------------------------------------------------------------

    public function proveedores(Request $request)
    {
        $this->autorizar('acceso');

        return response()->json($this->facturas->buscarProveedores($request->get('q')));
    }

    public function cotizacion(Request $request, CotizacionService $cotizaciones)
    {
        $this->autorizar('acceso');

        return response()->json([
            'cotizacion' => $cotizaciones->alCosto((string) $request->get('moneda'), $request->get('fecha')),
        ]);
    }

    public function ocupaciones(Request $request, FacturaproveedorOcupacionService $ocupaciones)
    {
        $this->autorizar('acceso');

        return response()->json(
            $ocupaciones->pendientesDe((int) $request->get('proveedor'), $request->get('codigo'))
        );
    }

    /** Chequeo de duplicado en vivo, al salir del campo número. */
    public function duplicado(Request $request)
    {
        $this->autorizar('acceso');

        $existe = $this->facturas->existeDuplicado(
            (string) $request->get('nro'),
            (int) $request->get('proveedor'),
            (string) $request->get('tipo'),
        );

        return response()->json(['ok' => ! $existe]);
    }

    /**
     * Recalcula los importes en el servidor.
     *
     * El Vue calcula localmente para dar respuesta inmediata, pero contrasta
     * contra este endpoint: si difieren, gana el servidor y se avisa. Es la red
     * de seguridad contra que las dos implementaciones se separen, que es
     * exactamente lo que pasó en el legacy entre el JS y el PHP.
     */
    public function calcular(Request $request)
    {
        $this->autorizar('acceso');

        try {
            $montos = FacturaproveedorCalculo::paraLicenciaActual()
                ->calcular($request->all(), $request->input('adicionales', []));
        } catch (FacturaproveedorException $e) {
            return response()->json(['error' => $e->getMessage()], $e->codigoHttp());
        }

        return response()->json($montos->toArray());
    }

    // ------------------------------------------------------------------
    // Internos
    // ------------------------------------------------------------------

    private function autorizar(string $accion): void
    {
        Permisos::exigir(Secciones::FACTURA_TERCERO, $accion, 'Facturas de Terceros');
    }

    private function filtros(Request $request): array
    {
        return array_filter(
            $request->only(FacturaproveedorListadoService::FILTROS),
            fn ($v) => $v !== null && $v !== ''
        );
    }

    private function config(string $vista): array
    {
        return [
            'vista' => $vista,
            'baseUrl' => self::RUTA,
            'titulo' => $vista === 'subdiario' ? 'Subdiario de Compras' : 'Facturas de Terceros',
            'pais' => \App\Support\Licencia::pais(),
            'requiereFiltro' => true,
            'permisos' => [
                'alta' => Permisos::tiene(Secciones::FACTURA_TERCERO, 'alta'),
                'edicion' => Permisos::tiene(Secciones::FACTURA_TERCERO, 'edicion'),
                'borrado' => Permisos::tiene(Secciones::FACTURA_TERCERO, 'borrado'),
                'ver' => Permisos::tiene(Secciones::FACTURA_TERCERO, 'view'),
            ],
            // Lo que sigue viviendo en el CI, para no ser una regresión.
            'enlacesLegacy' => [
                'cargaMultipleLegacy' => '/administracion/factura3erom',
            ],
        ];
    }

    private function opcionesFiltro(): array
    {
        return [
            'tiposDocumento' => (array) config('facturaproveedor.tipos_documento'),
            'monedas' => \App\Models\Moneda::opciones(),
            'proyectos' => \App\Models\Proyecto::orderByRaw('TRIM(proyecto_nombre)')
                ->pluck('proyecto_nombre', 'proyecto_id'),
        ];
    }

    /** Paginador vacío, para cuando todavía no se aplicó ningún filtro. */
    private function vacio(): array
    {
        return ['data' => [], 'links' => [], 'from' => null, 'to' => null, 'total' => 0];
    }
}
