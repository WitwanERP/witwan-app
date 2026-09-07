<?php

namespace App\Http\Controllers\Web\Reservas;

use App\Http\Controllers\Controller;
use App\Http\Requests\Reservas\NuevaReservaRequest;
use App\Services\CatalogosService;
use App\Services\CotizacionService;
use App\Services\Pricing\Tarifador;
use App\Services\Reservas\ContextoVentaService;
use App\Services\Reservas\EscritorioService;
use App\Services\Reservas\GeneradorReservaService;
use App\Services\Reservas\ReservaInvalidaException;
use App\Support\Licencia;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Generador de reservas (v2): asistente de venta producto-primero.
 * Pasos: contexto (cliente, tipo de pasajero, tarifario) → búsqueda por tipo de
 * producto → carrito con nómina única → confirmación. El alta la hace
 * GeneradorReservaService (mismo contrato que la v1). Reemplazará al menú
 * `reserva/nueva/{área}` del CI cuando cubra lo que usa el tenant.
 */
class NuevaReservaController extends Controller
{
    public function __construct(
        private GeneradorReservaService $generador,
        private ContextoVentaService $contexto,
        private CatalogosService $catalogos,
        private EscritorioService $escritorios,
        private CotizacionService $cotizaciones,
    ) {}

    public function create(string $area): Response
    {
        $usuario = Auth::user();
        $idsistema = $this->idsistema($area);
        $escritorio = $this->escritorios->paraUsuario($usuario);
        $monedaDefault = (string) (Licencia::sysconfig('moneda_reserva') ?: (Licencia::pais() === 'CL' ? 'CLP' : (Licencia::esFamiliaSecontur() ? 'ARS' : 'USD')));
        $cotizaciones = [];
        foreach ($this->cotizaciones->ultimas()['monedas'] as $c) {
            $cotizaciones[$c['moneda']] = (float) $c['valor'];
        }

        return Inertia::render('Reservas/Nueva', [
            'area' => $area,
            'baseUrl' => "/app/reservas/{$area}",
            'idsistema' => $idsistema,
            'fechaMinima' => $this->generador->fechaMinima($usuario),
            'monedaDefault' => $monedaDefault,
            'monedaBasica' => $this->cotizaciones->monedaBasica(),
            'cotizaciones' => $cotizaciones,
            'statusFile' => Licencia::pais() === 'CL' ? 'RQ' : 'CO',
            'esInterno' => $this->esInterno($usuario),
            'puedeForzarCredito' => (string) $usuario->fk_tipousuario_id === 'POW' || \App\Helpers\PermisoHelper::tienePermiso(255, 'cambiar_limite_credito'),
            'pestanas' => [],
            'opciones' => [
                'tipos' => DB::table('submodulo')->where('tipoproducto_activo', 1)->orderBy('tipoproducto_nombre')->get()
                    ->map(fn ($t) => ['value' => (string) $t->tipoproducto_id, 'label' => $t->tipoproducto_nombre])->all(),
                'monedas' => $this->catalogos->monedas(),
                'vendedores' => $this->catalogos->usuariosInternos(),
                'escritorio' => $escritorio === [] ? [] : DB::table('usuario')->whereIn('usuario_id', $escritorio)->orderBy('usuario_apellido')->get()
                    ->map(fn ($u) => ['value' => (int) $u->usuario_id, 'label' => trim("{$u->usuario_apellido}, {$u->usuario_nombre}")])->all(),
                'tiposPax' => [['value' => 'ADT', 'label' => 'Adulto'], ['value' => 'CHD', 'label' => 'Menor'], ['value' => 'INF', 'label' => 'Infante'], ['value' => 'JUN', 'label' => 'Junior']],
            ],
        ]);
    }

    public function store(NuevaReservaRequest $request, string $area): RedirectResponse
    {
        $usuario = Auth::user();
        $data = $request->validated();
        $data['fk_sistema_id'] = $this->idsistema($area);

        try {
            $r = $this->generador->crear($data, array_values($data['servicios']), $usuario);
        } catch (ReservaInvalidaException $e) {
            return back()->withErrors($e->errores)->withInput();
        }

        $msg = "Reserva {$r['tipocodigo']}-{$r['codigo']} creada (#{$r['reserva_id']}).";
        if ($r['advertencias'] !== []) {
            $msg .= ' Avisos: '.implode(' ', $r['advertencias']);
        }

        return redirect("/app/reservas/{$area}?codigo={$r['codigo']}")->with('success', $msg);
    }

    /** Vista previa de validación (sin crear): errores y advertencias para mostrar antes de confirmar. */
    public function validar(Request $request, string $area): JsonResponse
    {
        $data = $request->all();
        $data['fk_sistema_id'] = $this->idsistema($area);

        return response()->json($this->generador->validar($data, array_values($data['servicios'] ?? []), Auth::user()));
    }

    // ---- Paso 1: contexto de venta -------------------------------------------------

    /** Autocomplete de clientes habilitados para reservar en el área. */
    public function clientes(Request $request, string $area): JsonResponse
    {
        return response()->json($this->contexto->clientes($this->idsistema($area), (string) $request->input('q', '')));
    }

    /** Tarifario, crédito e historial del cliente elegido. */
    public function cliente(string $area, int $id): JsonResponse
    {
        $ctx = $this->contexto->resolver($id, $this->idsistema($area));
        if ($ctx === null) {
            return response()->json(['message' => 'El cliente no existe o no está habilitado para reservar en esta área.'], 404);
        }

        return response()->json($ctx);
    }

    // ---- Autocompletes del carrito / búsqueda ----------------------------------------

    public function proveedores(Request $request): JsonResponse
    {
        return response()->json($this->contexto->proveedores((string) $request->input('q', '')));
    }

    public function ciudades(Request $request, string $area): JsonResponse
    {
        return response()->json($this->contexto->ciudades((string) $request->input('q', ''), (string) $request->input('tipo', ''), $this->sistemaProductos($area)));
    }

    /**
     * Cotiza un producto propio con el Tarifador (recotizar una línea del carrito
     * o cotizar un producto puntual). Misma entrada que /app/productos/{id}/cotizar
     * pero sin exigir permiso sobre productos.
     */
    public function cotizar(Request $request, string $area, Tarifador $tarifador): JsonResponse
    {
        $datos = $request->validate([
            'producto_id' => 'required|integer|min:1',
            'fecha_ini' => 'required|date_format:Y-m-d',
            'fecha_fin' => 'nullable|date_format:Y-m-d',
            'adultos' => 'required|integer|min:1|max:20',
            'menores' => 'nullable|array|max:9',
            'menores.*' => 'integer|min:0|max:17',
            'residente' => 'nullable|string|in:,R,N',
            'cliente_id' => 'nullable|integer|min:0',
            'categoria_id' => 'nullable|integer|min:0',
        ]);

        return response()->json($tarifador->cotizar($datos));
    }

    private function idsistema(string $area): int
    {
        return (int) (config('reservas.area_sistema')[$area] ?? config('reservas.area_sistema_default', 10));
    }

    /** Sistema cuyos productos se venden en el área: minorista (3) vende los de mayorista (2), como reserva.php:3905. */
    private function sistemaProductos(string $area): int
    {
        $id = $this->idsistema($area);

        return $id === 3 ? 2 : $id;
    }

    private function esInterno($usuario): bool
    {
        return in_array((string) ($usuario->usuario_interno ?? 'N'), ['Y', '1'], true) || (string) $usuario->fk_tipousuario_id === 'POW';
    }
}
