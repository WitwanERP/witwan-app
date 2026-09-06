<?php

namespace App\Http\Controllers\Web\Reservas;

use App\Http\Controllers\Controller;
use App\Services\CatalogosService;
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
            ],
        ]);
    }

    public function store(Request $request, string $area): RedirectResponse
    {
        $usuario = Auth::user();
        $data = $request->validate([
            'fk_cliente_id' => 'required|integer',
            'titular_nombre' => 'required|string|max:150',
            'titular_apellido' => 'required|string|max:150',
            'titular_email' => 'nullable|email|max:50',
            'titular_celular' => 'nullable|string|max:50',
            'fk_moneda_id' => 'required|string|max:3',
            'agente' => 'nullable|integer',
            'observaciones' => 'nullable|string',
            'fecha_vencimiento' => 'nullable|date_format:Y-m-d',
            'forzar_credito' => 'nullable|boolean',
            'servicios' => 'required|array|min:1',
            'servicios.*.fk_tipoproducto_id' => 'required|string|max:3',
            'servicios.*.servicio_nombre' => 'required|string|max:200',
            'servicios.*.fk_proveedor_id' => 'nullable|integer',
            'servicios.*.fk_ciudad_id' => 'nullable|integer',
            'servicios.*.vigencia_ini' => 'required|date_format:Y-m-d',
            'servicios.*.vigencia_fin' => 'nullable|date_format:Y-m-d',
            'servicios.*.adultos' => 'nullable|integer|min:0',
            'servicios.*.menores' => 'nullable|integer|min:0',
            'servicios.*.infante' => 'nullable|integer|min:0',
            'servicios.*.juniors' => 'nullable|integer|min:0',
            'servicios.*.fk_moneda_id' => 'required|string|max:3',
            'servicios.*.moneda_costo' => 'nullable|string|max:3',
            'servicios.*.total' => 'required|numeric|min:0',
            'servicios.*.costo' => 'nullable|numeric|min:0',
            'servicios.*.iva' => 'nullable|numeric|min:0',
            'servicios.*.iva_costo' => 'nullable|numeric|min:0',
            'servicios.*.impuestos' => 'nullable|numeric|min:0',
            'servicios.*.status' => 'nullable|in:CO,RQ',
            'servicios.*.nro_confirmacion' => 'nullable|string|max:200',
            'servicios.*.comentarios' => 'nullable|string',
            'servicios.*.vencimiento_proveedor' => 'nullable|date_format:Y-m-d',
            'servicios.*.pasajeros' => 'nullable|array',
            'servicios.*.pasajeros.*.nombre' => 'nullable|string|max:100',
            'servicios.*.pasajeros.*.apellido' => 'nullable|string|max:100',
            'servicios.*.pasajeros.*.documento' => 'nullable|string|max:50',
            'servicios.*.pasajeros.*.nacionalidad' => 'nullable|string|max:50',
            'servicios.*.pasajeros.*.tipopax' => 'nullable|string|max:3',
            'servicios.*.pasajeros.*.nacimiento' => 'nullable|string|max:50',
        ]);
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
