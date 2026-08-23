<?php

namespace App\Http\Controllers\Web\Contabilidad;

use App\Exceptions\Contable\AsientoException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Contable\AsientoRequest;
use App\Models\Moneda;
use App\Models\Proyecto;
use App\Services\Contable\AsientoExportService;
use App\Services\Contable\AsientoListadoService;
use App\Services\Contable\AsientoService;
use App\Services\CotizacionService;
use App\Support\Contable\PeriodoContable;
use App\Support\Contable\TipoAsiento;
use App\Support\Permisos;
use Illuminate\Http\Request;
use Inertia\Inertia;

/**
 * Asientos de administración — front Inertia bajo /app.
 *
 * Un solo controller para los tres módulos que el CI tiene separados:
 *
 *   /app/contabilidad/asientos/contable          <- administracion/asientocontable
 *   /app/contabilidad/asientos/cuenta-corriente  <- administracion/asientocta
 *   /app/contabilidad/asientos/fondos            <- administracion/fondos
 *
 * El `{tipo}` de la URL se resuelve a un TipoAsiento, que es el que sabe qué
 * cambia entre los tres (código de `ordenadmin.tipo`, columnas de la grilla, qué
 * pasa al anular). Toda la lógica de negocio vive en App\Services\Contable.
 *
 * A diferencia del legacy, todas las acciones verifican permiso: en el CI los
 * `guardar()`, `anular()` y `create()` de los tres controllers sobreescribían a
 * los del Admin_Controller sin llamar nunca a `_check_perm()`, así que cualquier
 * usuario con acceso al módulo podía dar de alta o anular por URL directa. Ver
 * App\Support\Permisos para el modo de despliegue (arranca en observación).
 */
class AsientoController extends Controller
{
    public function __construct(
        private AsientoService $asientos,
        private AsientoListadoService $listado,
        private PeriodoContable $periodo,
    ) {}

    // ------------------------------------------------------------------
    // Listado
    // ------------------------------------------------------------------

    public function index(string $tipo, Request $request)
    {
        $t = $this->tipo($tipo);
        $this->autorizar($t, 'acceso');

        $filtros = $this->filtros($request);
        $hay = $this->listado->hayFiltros($filtros);

        return Inertia::render('Contabilidad/Asientos/Index', [
            'config' => $this->config($t),
            'opciones' => $this->opcionesFiltro($t),
            'filtros' => $filtros,
            'registros' => $hay ? $this->listado->listar($t, $filtros) : self::PAGINA_VACIA,
            'totales' => $hay ? $this->listado->totales($t, $filtros) : ['porMoneda' => [], 'cantidad' => 0],
        ]);
    }

    public function exportar(string $tipo, Request $request, AsientoExportService $export)
    {
        $t = $this->tipo($tipo);
        $this->autorizar($t, 'acceso');

        return $export->descargar($t, $this->filtros($request));
    }

    // ------------------------------------------------------------------
    // Detalle
    // ------------------------------------------------------------------

    public function show(string $tipo, int $id)
    {
        $t = $this->tipo($tipo);
        $this->autorizar($t, 'view');

        $detalle = $this->asientos->paraVer($t, $id);
        abort_if($detalle === null, 404);

        return Inertia::render('Contabilidad/Asientos/Ver', [
            'config' => $this->config($t),
        ] + $detalle);
    }

    // ------------------------------------------------------------------
    // Alta
    // ------------------------------------------------------------------

    public function create(string $tipo, CotizacionService $cotizaciones)
    {
        $t = $this->tipo($tipo);
        $this->autorizar($t, 'alta');

        return Inertia::render('Contabilidad/Asientos/Form', [
            'config' => $this->config($t),
            'opciones' => $this->opcionesFormulario($t, $cotizaciones),
        ]);
    }

    public function store(string $tipo, AsientoRequest $request)
    {
        $t = $this->tipo($tipo);
        $this->autorizar($t, 'alta');

        try {
            $id = $this->asientos->crear($t, $request->validated(), (int) auth()->id());
        } catch (AsientoException $e) {
            // Vuelve con lo cargado. El legacy redirigía a /dashboard/errormov y
            // el usuario perdía el asiento entero.
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect($t->baseUrl().'/'.$id)->with('success', ucfirst($t->singular()).' grabado correctamente.');
    }

    // ------------------------------------------------------------------
    // Edición (acotada: ver AsientoService::actualizar)
    // ------------------------------------------------------------------

    public function edit(string $tipo, int $id, CotizacionService $cotizaciones)
    {
        $t = $this->tipo($tipo);
        $this->autorizar($t, 'edicion');

        $detalle = $this->asientos->paraEditar($t, $id);
        abort_if($detalle === null, 404);

        // Pantalla aparte y no el mismo Form: el alta es una grilla de carga y la
        // edición es una lista de movimientos ya contabilizados con unos pocos
        // campos abiertos. Meterlas en un componente con `v-if` daba un archivo
        // donde no se entendía qué se podía tocar en cada modo.
        return Inertia::render('Contabilidad/Asientos/Editar', [
            'config' => $this->config($t),
            'opciones' => $this->opcionesFormulario($t, $cotizaciones),
        ] + $detalle);
    }

    public function update(string $tipo, int $id, Request $request)
    {
        $t = $this->tipo($tipo);
        $this->autorizar($t, 'edicion');

        $datos = $request->validate([
            'fecha' => 'required|date_format:Y-m-d,d/m/Y',
            'observaciones' => 'nullable|string|max:2000',
            'fk_proyecto_id' => 'nullable|integer|min:0',
            'movimientos' => 'array',
            'movimientos.*.id' => 'required|integer|min:1',
            'movimientos.*.descripcion' => 'nullable|string|max:500',
            'movimientos.*.banco' => 'nullable|string|max:200',
            'movimientos.*.operacion' => 'nullable|string|max:200',
            'movimientos.*.fechaAcreditacion' => 'nullable|date_format:Y-m-d,d/m/Y',
            'movimientos.*.clienteId' => 'nullable|integer|min:0',
            'movimientos.*.proveedorId' => 'nullable|integer|min:0',
            'movimientos.*.fileId' => 'nullable|integer|min:0',
        ]);

        try {
            $this->asientos->actualizar($t, $id, $datos, (int) auth()->id());
        } catch (AsientoException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect($t->baseUrl().'/'.$id)->with('success', 'Asiento actualizado.');
    }

    // ------------------------------------------------------------------
    // Anulación
    // ------------------------------------------------------------------

    /**
     * POST y no GET a propósito: en el CI anular era un link
     * (`get_conf().'/anular/{id}'`), así que un prefetch del navegador o un
     * `<img>` en un mail alcanzaban para anular un asiento.
     */
    public function anular(string $tipo, int $id)
    {
        $t = $this->tipo($tipo);
        $this->autorizar($t, 'borrado');

        try {
            $this->asientos->anular($t, $id, (int) auth()->id());
        } catch (AsientoException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Asiento anulado.');
    }

    // ------------------------------------------------------------------
    // Endpoints JSON auxiliares (los consume el Vue con fetch, no Inertia)
    // ------------------------------------------------------------------

    public function cuentas(string $tipo, Request $request)
    {
        $t = $this->tipo($tipo);
        $this->autorizar($t, 'acceso');

        return response()->json($this->asientos->cuentas($request->get('q')));
    }

    public function clientes(string $tipo, Request $request)
    {
        $t = $this->tipo($tipo);
        $this->autorizar($t, 'acceso');

        return response()->json($this->asientos->buscarClientes($request->get('q')));
    }

    public function proveedores(string $tipo, Request $request)
    {
        $t = $this->tipo($tipo);
        $this->autorizar($t, 'acceso');

        return response()->json($this->asientos->buscarProveedores($request->get('q')));
    }

    public function files(string $tipo, Request $request)
    {
        $t = $this->tipo($tipo);
        $this->autorizar($t, 'acceso');

        return response()->json($this->asientos->buscarFiles($request->get('q')));
    }

    /** Cotización sugerida para la moneda y la fecha elegidas. */
    public function cotizacion(string $tipo, Request $request, CotizacionService $cotizaciones)
    {
        $t = $this->tipo($tipo);
        $this->autorizar($t, 'acceso');

        return response()->json([
            'cotizacion' => $cotizaciones->aLaVenta((string) $request->get('moneda'), $request->get('fecha')),
        ]);
    }

    // ------------------------------------------------------------------
    // Internos
    // ------------------------------------------------------------------

    private const PAGINA_VACIA = ['data' => [], 'links' => [], 'from' => null, 'to' => null, 'total' => 0];

    private function tipo(string $slug): TipoAsiento
    {
        try {
            return TipoAsiento::desde($slug);
        } catch (\InvalidArgumentException) {
            abort(404);
        }
    }

    private function autorizar(TipoAsiento $tipo, string $accion): void
    {
        Permisos::exigir($tipo->seccion(), $accion, $tipo->titulo(), 'asientos.permisos_estrictos');
    }

    private function filtros(Request $request): array
    {
        return array_filter(
            $request->only(AsientoListadoService::FILTROS),
            fn ($v) => $v !== null && $v !== ''
        );
    }

    private function config(TipoAsiento $tipo): array
    {
        return $tipo->aArray() + [
            // Los tres tipos son la misma tabla: se ofrecen como pestañas para no
            // obligar a volver al menú del CI para saltar de uno a otro.
            'tipos' => array_map(
                fn (string $slug) => [
                    'slug' => $slug,
                    'titulo' => (string) config("asientos.tipos.{$slug}.titulo"),
                    'url' => '/app/contabilidad/asientos/'.$slug,
                    'activo' => $slug === $tipo->slug,
                ],
                TipoAsiento::slugs(),
            ),
            'estados' => (array) config('asientos.estados'),
            'requiereFiltro' => true,
            // Límite del período contable, para que el form lo muestre como
            // mínimo del campo fecha en vez de rechazar recién al grabar.
            'primeraFechaOperable' => $this->periodo->primeraFechaOperable(),
            'permisos' => [
                'alta' => Permisos::tiene($tipo->seccion(), 'alta'),
                'edicion' => Permisos::tiene($tipo->seccion(), 'edicion'),
                'borrado' => Permisos::tiene($tipo->seccion(), 'borrado'),
                'ver' => Permisos::tiene($tipo->seccion(), 'view'),
            ],
        ];
    }

    private function opcionesFiltro(TipoAsiento $tipo): array
    {
        return [
            'monedas' => $this->monedas(),
            'estados' => (array) config('asientos.estados'),
            'usuarios' => \App\Models\Usuario::query()
                ->orderBy('usuario_apellido')
                ->limit(500)
                ->get(['usuario_id', 'usuario_nombre', 'usuario_apellido'])
                ->map(fn ($u) => [
                    'id' => (int) $u->usuario_id,
                    'label' => trim($u->usuario_apellido.', '.$u->usuario_nombre, ' ,'),
                ])
                ->all(),
            'proyectos' => $tipo->usaProyecto() ? $this->proyectos() : [],
        ];
    }

    private function opcionesFormulario(TipoAsiento $tipo, CotizacionService $cotizaciones): array
    {
        $monedas = $this->monedas();
        $basica = $cotizaciones->monedaBasica();

        return [
            'monedas' => $monedas,
            'monedaBasica' => $basica,
            // La grilla de fondos usa selects, no autocomplete: son las mismas
            // cuentas que el legacy precarga en el `$cuentas` del template.
            'cuentas' => $tipo->esDebeHaber() ? [] : $this->asientos->cuentas(null, 2000),
            'proyectos' => $tipo->usaProyecto() ? $this->proyectos() : [],
        ];
    }

    private function monedas(): array
    {
        return Moneda::query()
            ->orderBy('moneda_id')
            ->get(['moneda_id', 'moneda_nombre', 'moneda_basica'])
            ->map(fn ($m) => [
                'id' => $m->moneda_id,
                'label' => $m->moneda_nombre ?: $m->moneda_id,
                'basica' => $m->moneda_basica === 'Y',
            ])
            ->all();
    }

    private function proyectos(): array
    {
        return Proyecto::query()
            ->orderByRaw('TRIM(proyecto_nombre)')
            ->get(['proyecto_id', 'proyecto_nombre'])
            ->map(fn ($p) => ['id' => (int) $p->proyecto_id, 'label' => trim((string) $p->proyecto_nombre)])
            ->all();
    }
}
