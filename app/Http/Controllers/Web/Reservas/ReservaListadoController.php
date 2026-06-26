<?php

namespace App\Http\Controllers\Web\Reservas;

use App\Helpers\PermisoHelper;
use App\Http\Controllers\Controller;
use App\Services\Reservas\ReservaCobradoService;
use App\Services\Reservas\ReservaExportService;
use App\Services\Reservas\ReservaListadoService;
use App\Support\Licencia;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

/**
 * Listado de reservas en /app (Inertia), réplica del CI legacy
 * (reserva.php::lista() + views/reserva/lista.php). El núcleo de la query vive en
 * ReservaListadoService; acá se arma el contexto (área→idsistema, filtros,
 * opciones de selects, flags de permiso/licencia) y se renderiza la pantalla.
 */
class ReservaListadoController extends Controller
{
    /** Claves de filtro que se leen del query-string (resto de _pst de CI). */
    private const FILTROS_HTTP = [
        'codigo', 'tipo', 'status', 'titular', 'tipofecha', 'from', 'to',
        'facturafrom', 'facturato', 'factura', 'solopagos', 'ticket', 'recloc',
        'solofacturado', 'soloocultas', 'soloovencidas', 'negocio', 'responsable',
        'nro_confirmacion', 'residente', 'codigo_externo', 'mostrarreprogramados',
        'auditado', 'vendedor', 'fk_vendedor_id', 'usuario', 'proveedor', 'prestador',
        'cadenacliente', 'representante', 'operativo', 'tipoproducto', 'pago',
        'fecha_alta', 'fecha_alta_to',
    ];

    public function index(Request $request, string $area, ReservaListadoService $svc)
    {
        $usuario = Auth::user();
        $idsistema = $this->idsistema($area);
        $filtros = $this->filtros($request, $area, $idsistema, $usuario);

        ['registros' => $registros, 'totales' => $totales] = $svc->listar($filtros, $usuario);

        return Inertia::render('Reservas/Listado', [
            'config' => $this->config($area, $idsistema, $usuario),
            'opciones' => $this->opciones($area, $idsistema),
            'registros' => $registros,
            'totales' => $totales,
            'filtros' => $filtros,
        ]);
    }

    public function exportar(Request $request, string $area, ReservaExportService $export)
    {
        $usuario = Auth::user();
        $filtros = $this->filtros($request, $area, $this->idsistema($area), $usuario);

        return $export->descargar($filtros, $usuario);
    }

    public function resumen(int $id, ReservaListadoService $svc, ReservaCobradoService $cobrados)
    {
        $usuario = Auth::user();
        $resultado = $svc->todos(['status' => '', 'id' => $id, 'cliente' => 0], $usuario);
        $fila = collect($resultado['registros'])->firstWhere('id', $id);

        abort_if($fila === null, 404);

        $historial = DB::table('historialfile')
            ->where('fk_reserva_id', $id)
            ->orderByDesc('historial_date')
            ->limit(100)
            ->get();

        return response()->json([
            'reserva' => $fila,
            'historial' => $historial,
        ]);
    }

    /** Autocomplete de clientes (reemplazo de /ajax/cliente). */
    public function clientesAutocomplete(Request $request)
    {
        $q = trim((string) $request->input('q', ''));
        if ($q === '') {
            return response()->json([]);
        }

        $clientes = DB::table('cliente')
            ->where('habilita', 'Y')
            ->where('cliente_nombre', 'LIKE', "%{$q}%")
            ->orderBy('cliente_nombre')
            ->limit(30)
            ->get(['cliente_id', 'cliente_nombre']);

        return response()->json(
            $clientes->map(fn ($c) => ['value' => $c->cliente_id, 'label' => $c->cliente_nombre])
        );
    }

    /**
     * Eliminar reservas seleccionadas. Replica el gate de CI (permiso 262 +
     * licencias que lo fuerzan a off). La baja efectiva con sus chequeos de
     * cobros/facturas (reserva.php::delete() 9439) queda pendiente de portar:
     * por seguridad NO se borra hasta replicar esas validaciones.
     */
    public function eliminar(Request $request, string $area)
    {
        if (! $this->puedeEliminar()) {
            abort(403, 'No tiene permiso para eliminar reservas.');
        }

        // TODO(fase escritura): portar reserva.php::delete() (chequeo de cobros,
        // facturas, comisiones) antes de habilitar la baja efectiva.
        return back()->with('error', 'La baja de reservas aún no está habilitada en /app (pendiente de portar las validaciones de CI).');
    }

    /**
     * Agrupar reservas. Replica el gate (mismo cliente, sin comisión). La
     * operación efectiva (reserva.php::agruparaction() 9328) queda pendiente.
     */
    public function agrupar(Request $request, string $area)
    {
        // TODO(fase escritura): portar reserva.php::agruparaction().
        return back()->with('error', 'El agrupado de reservas aún no está habilitado en /app (pendiente de portar la lógica de CI).');
    }

    // ---------------------------------------------------------------------
    // Helpers de contexto
    // ---------------------------------------------------------------------

    private function idsistema(string $area): int
    {
        return (int) (config('reservas.area_sistema')[$area] ?? config('reservas.area_sistema_default', 10));
    }

    /** Arma el array de filtros normalizado (incluye scoping de cliente forzado). */
    private function filtros(Request $request, string $area, int $idsistema, $usuario): array
    {
        $filtros = [];
        foreach (self::FILTROS_HTTP as $k) {
            $v = $request->input($k);
            if ($v !== null && $v !== '') {
                $filtros[$k] = $v;
            }
        }

        // status: default CO/RQ/CL (sin CL para áreas de ciertas licencias).
        if (! isset($filtros['status'])) {
            $statusDefault = config('reservas.status_default', ['CO', 'RQ', 'CL']);
            $sinCerrada = config('reservas.status_sin_cerrada.'.Licencia::base(), []);
            if (in_array($area, (array) $sinCerrada, true)) {
                $statusDefault = ['CO', 'RQ'];
            }
            $filtros['status'] = $statusDefault;
        }

        // Sistema/área.
        $filtros['rsv'] = $idsistema;

        // Cliente: forzado para CLI/CLM y para files_cliente_asignado (no POW).
        $cliente = (int) $request->input('cliente', 0);
        if ($usuario) {
            $tipo = $usuario->fk_tipousuario_id;
            if (in_array($tipo, ['CLI', 'CLM'], true)) {
                $cliente = (int) $usuario->fk_cliente_id;
            }
            if ($tipo !== 'POW' && PermisoHelper::tienePermiso(255, 'files_cliente_asignado')) {
                $cliente = (int) $usuario->fk_cliente_id;
            }
        }
        if ($cliente !== 0) {
            $filtros['cliente'] = $cliente;
        }

        return $filtros;
    }

    private function config(string $area, int $idsistema, $usuario): array
    {
        $interno = $usuario && ($usuario->usuario_interno ?? '') === 'Y';

        return [
            'titulo' => 'Reservas',
            'area' => $area,
            'idsistema' => $idsistema,
            'baseUrl' => "/app/reservas/{$area}",
            'interno' => $interno,
            'estados' => config('reservas.estados'),
            'permisos' => [
                'eliminar_reservas' => $this->puedeEliminar(),
                'movercobrados' => $usuario ? PermisoHelper::tienePermiso(318, 'mover_servicio_pago') : false,
            ],
            'flags' => [
                'pago' => (bool) Licencia::flag('pago_online_on'),
                'fileauditado' => (int) Licencia::sysconfig('fileauditado', 0) === 1,
                'marcar_reprogramado' => (int) Licencia::sysconfig('marcar_reprogramado', 0) === 1,
                'operativos' => (bool) Licencia::flag('operativos_on'),
                'cadenacliente_operativo' => (bool) Licencia::flag('cadenacliente_operativo_filtros'),
                'pericia' => (bool) Licencia::flag('pericia'),
                'codigo_externo_visible' => (bool) Licencia::flag('codigo_externo_visible'),
                'facturado_med' => (bool) Licencia::flag('facturado_med'),
                'productos_toggle' => (bool) Licencia::flag('productos_toggle'),
            ],
        ];
    }

    private function puedeEliminar(): bool
    {
        if (Licencia::flag('eliminar_forzado_off')) {
            return false;
        }
        $usuario = Auth::user();

        return $usuario ? PermisoHelper::tienePermiso(262, 'eliminar_reservas') : false;
    }

    /** Opciones de los selects del formulario de filtros (reserva.php::lista()). */
    private function opciones(string $area, int $idsistema): array
    {
        // Tipos de código presentes (checkboxes), excluyendo DV/CM/CU.
        $tipos = DB::table('reserva')
            ->where('tipocodigo', '!=', '')
            ->whereNotIn('tipocodigo', ['DV', 'CM', 'CU'])
            ->when($area !== 'all', fn ($q) => $q->where(function ($q2) use ($idsistema) {
                $q2->where(fn ($q3) => $q3->where('fk_sistema_id', $idsistema)->where('fk_sistemaaplicacion_id', 0))
                    ->orWhere('fk_sistemaaplicacion_id', $idsistema);
            }))
            ->distinct()
            ->orderBy('tipocodigo')
            ->pluck('tipocodigo')
            ->all();
        $tipos = array_values(array_unique(array_merge(config('reservas.tipos_default', []), $tipos)));

        $tipoproducto = DB::table('submodulo as s')
            ->whereExists(fn ($q) => $q->select(DB::raw(1))->from('servicio as se')->whereColumn('se.fk_tipoproducto_id', 's.submodulo_id'))
            ->orderBy('s.submodulo_nombre')
            ->get(['s.submodulo_id', 's.submodulo_nombre'])
            ->map(fn ($r) => ['value' => $r->submodulo_id, 'label' => $r->submodulo_nombre])
            ->all();

        $vendedores = DB::table('usuario')
            ->where('usuario_interno', 'Y')->where('habilitar', 'Y')
            ->orderByRaw('TRIM(usuario_nombre), TRIM(usuario_apellido)')
            ->selectRaw("usuario_id as value, CONCAT(usuario_nombre, ' ', usuario_apellido) as label")
            ->get()->map(fn ($r) => (array) $r)->all();

        $responsables = DB::table('usuario')
            ->where('usuario_responsable', 1)->where('habilitar', 'Y')
            ->where(fn ($q) => $q->whereIn('usuario_id', fn ($s) => $s->select('promotor')->from('reserva')->where('promotor', '>', 0))
                ->orWhereIn('usuario_id', fn ($s) => $s->select('promotoraereo')->from('reserva')->where('promotoraereo', '>', 0)))
            ->orderByRaw('TRIM(usuario_apellido), TRIM(usuario_nombre)')
            ->selectRaw("usuario_id as value, CONCAT(usuario_apellido, ', ', usuario_nombre) as label")
            ->get()->map(fn ($r) => (array) $r)->all();

        $proveedores = DB::table('proveedor')
            ->orderByRaw('TRIM(proveedor_nombre)')
            ->selectRaw('proveedor_id as value, proveedor_nombre as label')
            ->get()->map(fn ($r) => (array) $r)->all();

        $cadenas = DB::table('cadenahotelera')
            ->orderBy('cadenahotelera_nombre')
            ->selectRaw('cadenahotelera_id as value, cadenahotelera_nombre as label')
            ->get()->map(fn ($r) => (array) $r)->all();

        $negocios = DB::table('negocio')
            ->orderBy('negocio_nombre')
            ->selectRaw('negocio_id as value, negocio_nombre as label')
            ->get()->map(fn ($r) => (array) $r)->all();

        $representantes = DB::table('cliente')
            ->whereIn('representante_geografico', ['Y', '1'])
            ->orderByRaw('TRIM(cliente_nombre)')
            ->selectRaw('cliente_id as value, cliente_nombre as label')
            ->get()->map(fn ($r) => (array) $r)->all();

        $opciones = [
            'tipos' => $tipos,
            'tipoproducto' => $tipoproducto,
            'estados' => collect(config('reservas.estados'))->map(fn ($e, $k) => ['value' => $k, 'label' => $e['nombre']])->values()->all(),
            'vendedores' => $vendedores,
            'responsables' => $responsables,
            'proveedores' => $proveedores,
            'cadenas' => $cadenas,
            'negocios' => $negocios,
            'representantes' => $representantes,
            'tipofecha' => [
                ['value' => 'alta', 'label' => 'Alta'],
                ['value' => 'emision', 'label' => 'Emisión'],
                ['value' => 'vencimiento', 'label' => 'Vencimiento'],
                ['value' => 'checkin', 'label' => 'Check-in'],
                ['value' => 'checkout', 'label' => 'Check-out'],
                ['value' => 'canceladas', 'label' => 'Canceladas'],
            ],
        ];

        if (Licencia::flag('cadenacliente_operativo_filtros')) {
            $opciones['cadenacliente'] = DB::table('cadenacliente')
                ->orderBy('cadenacliente_nombre')
                ->selectRaw('cadenacliente_id as value, cadenacliente_nombre as label')
                ->get()->map(fn ($r) => (array) $r)->all();
        }

        if (Licencia::flag('operativos_on')) {
            $opciones['operativos'] = DB::table('usuario')
                ->whereIn('usuario_id', fn ($q) => $q->select('operativo')->distinct()->from('reserva')->where('operativo', '>', 0))
                ->orderBy('usuario_nombre')->orderBy('usuario_apellido')
                ->selectRaw("usuario_id as value, CONCAT(usuario_nombre, ' ', usuario_apellido) as label")
                ->get()->map(fn ($r) => (array) $r)->all();
        }

        return $opciones;
    }
}
