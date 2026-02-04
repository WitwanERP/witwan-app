<?php

namespace App\Http\Controllers\Reservas;

use App\Http\Controllers\Controller;
use App\Models\Pnraereo;
use App\Models\Reserva;
use App\Models\Servicio;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;

class ReservaController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $startTime = microtime(true);
        $validatedParams = $this->validateParams($request);
        $perPage = $request->get('per_page', 20);

        $query = Reserva::query()
            ->where('fk_filestatus_id', '!=', 'AG')
            ->where('reserva_id', '!=', 1);

        // Filtro por código (múltiple con asterisco: "123*456*789")
        if (! empty($validatedParams['codigo'])) {
            $codigos = array_map('trim', explode('*', $validatedParams['codigo']));
            $query->whereIn('codigo', $codigos);
        }

        // Filtro por tipocodigo (múltiples valores separados por coma)
        if (! empty($validatedParams['tipocodigo'])) {
            $tipos = array_map('trim', explode(',', $validatedParams['tipocodigo']));
            $query->whereIn('tipocodigo', $tipos);
        }

        // Filtro por fk_filestatus_id (múltiples valores separados por coma)
        if (! empty($validatedParams['fk_filestatus_id'])) {
            $statuses = array_map('trim', explode(',', $validatedParams['fk_filestatus_id']));
            $query->whereIn('fk_filestatus_id', $statuses);
        }

        // Filtro por fk_cliente_id (múltiples valores separados por coma)
        if (! empty($validatedParams['fk_cliente_id'])) {
            $clientes = array_map('intval', explode(',', $validatedParams['fk_cliente_id']));
            $query->whereIn('fk_cliente_id', $clientes);
        }

        // Filtro por agente (vendedor)
        if (! empty($validatedParams['agente'])) {
            $query->where('agente', $validatedParams['agente']);
        }

        // Filtro por fk_usuario_id (creador)
        if (! empty($validatedParams['fk_usuario_id'])) {
            $query->where('fk_usuario_id', $validatedParams['fk_usuario_id']);
        }

        // Filtro por promotor
        if (! empty($validatedParams['promotor'])) {
            $query->where('promotor', $validatedParams['promotor']);
        }

        // Filtro por fk_proveedor_id del servicio (el que cobra) - usando subquery optimizada
        if (! empty($validatedParams['fk_proveedor_id'])) {
            $reservaIds = Servicio::where('fk_proveedor_id', $validatedParams['fk_proveedor_id'])
                ->pluck('fk_reserva_id');
            $query->whereIn('reserva_id', $reservaIds);
        }

        // Filtro por fk_prestador_id del servicio (el que presta) - usando subquery optimizada
        if (! empty($validatedParams['fk_prestador_id'])) {
            $reservaIds = Servicio::where('fk_prestador_id', $validatedParams['fk_prestador_id'])
                ->pluck('fk_reserva_id');
            $query->whereIn('reserva_id', $reservaIds);
        }

        // Filtro por fk_cadenahotelera_id del proveedor - usando JOIN optimizado
        if (! empty($validatedParams['fk_cadenahotelera_id'])) {
            $reservaIds = DB::table('servicio')
                ->join('proveedor', 'servicio.fk_proveedor_id', '=', 'proveedor.proveedor_id')
                ->where('proveedor.fk_cadenahotelera_id', $validatedParams['fk_cadenahotelera_id'])
                ->pluck('servicio.fk_reserva_id');
            $query->whereIn('reserva_id', $reservaIds);
        }

        // Filtro por titular (nombre + apellido en un solo campo)
        if (! empty($validatedParams['titular'])) {
            $titular = $validatedParams['titular'];
            $query->where(function ($q) use ($titular) {
                $q->whereRaw("CONCAT(titular_nombre, ' ', titular_apellido) LIKE ?", ["%{$titular}%"])
                    ->orWhere('titular_nombre', 'LIKE', "%{$titular}%")
                    ->orWhere('titular_apellido', 'LIKE', "%{$titular}%");
            });
        }

        // Filtro por ticket (pnraereo_tkt) - búsqueda exacta
        if (! empty($validatedParams['ticket'])) {
            $reservaIds = DB::table('pnraereo')
                ->join('servicio', 'pnraereo.fk_ocupacion_id', '=', 'servicio.servicio_id')
                ->where('pnraereo.pnraereo_tkt', $validatedParams['ticket'])
                ->pluck('servicio.fk_reserva_id');
            $query->whereIn('reserva_id', $reservaIds);
        }

        // Filtro por codigo_recloc - búsqueda exacta
        if (! empty($validatedParams['codigo_recloc'])) {
            $reservaIds = DB::table('pnraereo')
                ->join('servicio', 'pnraereo.fk_ocupacion_id', '=', 'servicio.servicio_id')
                ->where('pnraereo.codigo_recloc', $validatedParams['codigo_recloc'])
                ->pluck('servicio.fk_reserva_id');
            $query->whereIn('reserva_id', $reservaIds);
        }

        // Filtro por nro_confirmacion del servicio - búsqueda exacta
        if (! empty($validatedParams['nro_confirmacion'])) {
            $reservaIds = Servicio::where('nro_confirmacion', $validatedParams['nro_confirmacion'])
                ->pluck('fk_reserva_id');
            $query->whereIn('reserva_id', $reservaIds);
        }

        // Filtro por rango de fechas dinámico
        $this->applyDateFilters($query, $validatedParams);

        // Filtro por fk_tipoproducto_id del servicio - usando subquery optimizada
        if (! empty($validatedParams['fk_tipoproducto_id'])) {
            $tipos = array_map('trim', explode(',', $validatedParams['fk_tipoproducto_id']));
            $reservaIds = Servicio::whereIn('fk_tipoproducto_id', $tipos)
                ->pluck('fk_reserva_id');
            $query->whereIn('reserva_id', $reservaIds);
        }

        // Filtro por codigo_externo
        if (! empty($validatedParams['codigo_externo'])) {
            $query->where('codigo_externo', 'LIKE', "%{$validatedParams['codigo_externo']}%");
        }

        // Filtro por status_factura
        if (! empty($validatedParams['status_factura'])) {
            $query->where('status_factura', $validatedParams['status_factura']);
        }

        // Filtro por cobrado (0 = sin cobrar, 1 = con cobro)
        if (isset($validatedParams['cobrado']) && $validatedParams['cobrado'] !== null && $validatedParams['cobrado'] !== '') {
            if ($validatedParams['cobrado'] == '0') {
                $query->where('cobrado', 0);
            } else {
                $query->where('cobrado', '!=', 0);
            }
        }

        // Filtro por auditado
        if (isset($validatedParams['auditado']) && $validatedParams['auditado'] !== null && $validatedParams['auditado'] !== '') {
            $query->where('auditado', $validatedParams['auditado']);
        }

        // Cargar relaciones si se solicita
        if ($request->get('withRelations') === 'true') {
            $query->with([
                'cliente' => fn ($q) => $q->select('cliente_id', 'cliente_nombre', 'cuit'),
                'facturara' => fn ($q) => $q->select('cliente_id', 'cliente_nombre', 'cuit'),
                'clienteOrigen' => fn ($q) => $q->select('cliente_id', 'cliente_nombre', 'cuit'),
                'vendedor' => fn ($q) => $q->select('usuario_id', 'usuario_nombre', 'usuario_apellido'),
                'servicios',
            ]);
        }

        $items = $query
            ->orderByDesc('fecha_alta')
            ->paginate($perPage);

        $endTime = microtime(true);

        return response()->json([
            'data' => $items,
            'processing_time' => $endTime - $startTime,
        ]);
    }

    /**
     * Aplica filtros de fecha según el tipo seleccionado.
     * fecha_tipo: fecha_alta, pnraereo_fechaemision, inicio, vencimiento
     */
    private function applyDateFilters($query, array $params): void
    {
        $fechaTipo = $params['fecha_tipo'] ?? null;
        $fechaDesde = $params['fecha_desde'] ?? null;
        $fechaHasta = $params['fecha_hasta'] ?? null;

        if (! $fechaTipo || (! $fechaDesde && ! $fechaHasta)) {
            return;
        }

        switch ($fechaTipo) {
            case 'fecha_alta':
                if ($fechaDesde) {
                    $query->where('fecha_alta', '>=', $fechaDesde);
                }
                if ($fechaHasta) {
                    $query->where('fecha_alta', '<=', $fechaHasta);
                }
                break;

            case 'pnraereo_fechaemision':
                // Optimizado con JOIN en lugar de whereHas anidado
                $pnrQuery = DB::table('pnraereo')
                    ->join('servicio', 'pnraereo.fk_ocupacion_id', '=', 'servicio.servicio_id');
                if ($fechaDesde) {
                    $pnrQuery->where('pnraereo.pnraereo_fechaemision', '>=', $fechaDesde);
                }
                if ($fechaHasta) {
                    $pnrQuery->where('pnraereo.pnraereo_fechaemision', '<=', $fechaHasta);
                }
                $reservaIds = $pnrQuery->pluck('servicio.fk_reserva_id');
                $query->whereIn('reserva_id', $reservaIds);
                break;

            case 'inicio':
                if ($fechaDesde) {
                    $query->where('inicio', '>=', $fechaDesde);
                }
                if ($fechaHasta) {
                    $query->where('inicio', '<=', $fechaHasta);
                }
                break;

            case 'vencimiento':
                if ($fechaDesde) {
                    $query->where('fecha_vencimiento', '>=', $fechaDesde);
                }
                if ($fechaHasta) {
                    $query->where('fecha_vencimiento', '<=', $fechaHasta);
                }
                break;
        }
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            // Agregar reglas de validación aquí
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $data = $request->all();
        $model = new Reserva;
        $tableColumns = collect(Schema::getColumnListing($model->getTable()));

        // Agregar campos automáticos si existen
        if ($tableColumns->contains('fechacarga')) {
            $data['fechacarga'] = now();
        }
        if ($tableColumns->contains('um')) {
            $data['um'] = now();
        }
        if ($tableColumns->contains('fk_usuario_id')) {
            $data['fk_usuario_id'] = auth()->id();
        }

        $item = Reserva::create($data);

        return response()->json($item, 201);
    }

    /**
     * Display the specified resource.
     */
    public function show($id)
    {
        try {
            $item = Reserva::findOrFail($id);

            return response()->json($item);
        } catch (ModelNotFoundException $e) {
            return response()->json(['message' => 'Registro no encontrado'], 404);
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $id)
    {
        try {
            $item = Reserva::findOrFail($id);

            $validator = Validator::make($request->all(), [
                // Agregar reglas de validación aquí
            ]);

            if ($validator->fails()) {
                return response()->json(['errors' => $validator->errors()], 422);
            }

            $data = $request->all();
            $tableColumns = collect(Schema::getColumnListing($item->getTable()));

            // Actualizar campos automáticos
            if ($tableColumns->contains('um')) {
                $data['um'] = now();
            }
            if ($tableColumns->contains('fk_usuario_id')) {
                $data['fk_usuario_id'] = auth()->id();
            }

            $item->update($data);

            return response()->json($item);
        } catch (ModelNotFoundException $e) {
            return response()->json(['message' => 'Registro no encontrado'], 404);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id)
    {
        try {
            $item = Reserva::findOrFail($id);
            $item->delete();

            return response()->json(null, 204);
        } catch (ModelNotFoundException $e) {
            return response()->json(['message' => 'Registro no encontrado'], 404);
        }
    }

    /**
     * Search resources.
     */
    public function search(Request $request)
    {
        $query = Reserva::query();
        $perPage = $request->get('per_page', 100);

        if ($request->has('q') && ! empty($request->q)) {
            $searchTerm = $request->q;
            // Implementar búsqueda en campos principales
        }

        return response()->json($query->paginate($perPage));
    }

    private function validateParams(Request $request)
    {
        return $request->validate([
            'per_page' => 'integer|min:1|max:100',

            // Filtros de reserva
            'codigo' => 'nullable|string',
            'tipocodigo' => 'nullable|string',
            'fk_filestatus_id' => 'nullable|string',
            'fk_cliente_id' => 'nullable|string',
            'agente' => 'nullable|integer',
            'fk_usuario_id' => 'nullable|integer',
            'promotor' => 'nullable|integer',
            'titular' => 'nullable|string|max:255',
            'codigo_externo' => 'nullable|string|max:255',
            'status_factura' => 'nullable|string|max:10|in:pendiente,facturado,parcial',
            'cobrado' => 'nullable|in:0,1',
            'auditado' => 'nullable|in:0,1',

            // Filtros de servicio
            'fk_proveedor_id' => 'nullable|integer',
            'fk_prestador_id' => 'nullable|integer',
            'fk_cadenahotelera_id' => 'nullable|integer',
            'nro_confirmacion' => 'nullable|string|max:255',
            'fk_tipoproducto_id' => 'nullable|string',

            // Filtros de pnraereo
            'ticket' => 'nullable|string|max:255',
            'codigo_recloc' => 'nullable|string|max:255',

            // Filtros de fecha dinámicos
            'fecha_tipo' => 'nullable|in:fecha_alta,pnraereo_fechaemision,inicio,vencimiento',
            'fecha_desde' => 'nullable|date',
            'fecha_hasta' => 'nullable|date|after_or_equal:fecha_desde',
        ]);
    }

    public function reservar(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'fk_cliente_id' => 'required|integer|exists:cliente,cliente_id',
            'fk_vendedor_id' => 'nullable|integer|exists:usuario,usuario_id',
            'comentarios' => 'nullable|string',
            'servicios.*' => 'required|array|min:1',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $data = $request->all();

        // Lógica para crear la reserva y los servicios asociados
        // ...

        return response()->json(['message' => 'Reserva creada exitosamente'], 201);
    }
}
