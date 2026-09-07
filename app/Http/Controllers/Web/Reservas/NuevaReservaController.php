<?php

namespace App\Http\Controllers\Web\Reservas;

use App\Http\Controllers\Controller;
use App\Http\Requests\Reservas\NuevaReservaRequest;
use App\Services\CatalogosService;
use App\Services\Pricing\Tarifador;
use App\Services\Reservas\EscritorioService;
use App\Services\Reservas\GeneradorReservaService;
use App\Services\Reservas\ReservaInvalidaException;
use App\Support\Licencia;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Generador de reservas (v1): alta manual de file + servicios por área.
 * Convive con `reserva/nueva/{área}` del CI (que sigue siendo el flujo con
 * carrito y tarifadores); no reemplaza su entrada de menú.
 */
class NuevaReservaController extends Controller
{
    public function __construct(private GeneradorReservaService $generador, private CatalogosService $catalogos, private EscritorioService $escritorios) {}

    public function create(string $area): Response
    {
        $usuario = Auth::user();
        $idsistema = $this->idsistema($area);
        $escritorio = $this->escritorios->paraUsuario($usuario);
        $monedaDefault = (string) (Licencia::sysconfig('moneda_reserva') ?: (Licencia::pais() === 'CL' ? 'CLP' : (Licencia::esFamiliaSecontur() ? 'ARS' : 'USD')));

        return Inertia::render('Reservas/Nueva', [
            'area' => $area,
            'baseUrl' => "/app/reservas/{$area}",
            'idsistema' => $idsistema,
            'fechaMinima' => $this->generador->fechaMinima($usuario),
            'monedaDefault' => $monedaDefault,
            'statusFile' => Licencia::pais() === 'CL' ? 'RQ' : 'CO',
            'puedeForzarCredito' => (string) $usuario->fk_tipousuario_id === 'POW' || \App\Helpers\PermisoHelper::tienePermiso(255, 'cambiar_limite_credito'),
            'opciones' => [
                'clientes' => $this->generador->clientes($idsistema),
                'proveedores' => $this->catalogos->proveedores(),
                'tipos' => DB::table('submodulo')->where('tipoproducto_activo', 1)->orderBy('tipoproducto_nombre')->get()
                    ->map(fn ($t) => ['value' => (string) $t->tipoproducto_id, 'label' => $t->tipoproducto_nombre])->all(),
                'monedas' => $this->catalogos->monedas(),
                'ciudades' => $this->catalogos->ciudades(),
                'paises' => $this->catalogos->paises(),
                'vendedores' => $this->catalogos->usuariosInternos(),
                'escritorio' => $escritorio === [] ? [] : DB::table('usuario')->whereIn('usuario_id', $escritorio)->orderBy('usuario_apellido')->get()
                    ->map(fn ($u) => ['value' => (int) $u->usuario_id, 'label' => trim("{$u->usuario_apellido}, {$u->usuario_nombre}")])->all(),
                'tiposPax' => [['value' => 'ADT', 'label' => 'Adulto'], ['value' => 'CHD', 'label' => 'Menor'], ['value' => 'INF', 'label' => 'Infante'], ['value' => 'JUN', 'label' => 'Junior']],
                // Productos propios cotizables con el Tarifador (tarifas cargadas en /app/productos).
                'productos' => DB::table('producto')->where('habilitar', 1)->where('eliminar', 0)->where('producto_nombre', '<>', '')
                    ->orderBy('producto_nombre')->limit(5000)->get(['producto_id', 'producto_nombre', 'fk_tipoproducto_id', 'fk_proveedor_id', 'fk_sistema_id'])
                    ->map(fn ($p) => ['value' => (int) $p->producto_id, 'label' => $p->producto_nombre, 'tipo' => (string) $p->fk_tipoproducto_id, 'proveedor' => (int) $p->fk_proveedor_id, 'sistema' => (int) $p->fk_sistema_id])->all(),
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

    /**
     * Cotiza un producto propio con el Tarifador para precargar un servicio
     * (precio, costo, IVA, impuestos, vencimiento de pago). Misma entrada que
     * /app/productos/{id}/cotizar pero sin exigir permiso sobre productos.
     */
    public function cotizar(Request $request, string $area, Tarifador $tarifador)
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

    /** Vista previa de validación (sin crear): errores y advertencias para mostrar antes de confirmar. */
    public function validar(Request $request, string $area)
    {
        $data = $request->all();
        $data['fk_sistema_id'] = $this->idsistema($area);

        return response()->json($this->generador->validar($data, array_values($data['servicios'] ?? []), Auth::user()));
    }

    private function idsistema(string $area): int
    {
        return (int) (config('reservas.area_sistema')[$area] ?? config('reservas.area_sistema_default', 10));
    }
}
