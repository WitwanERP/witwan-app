<?php

namespace App\Http\Controllers\Reservas;

use App\Http\Controllers\Controller;
use App\Models\Reservas\Reserva;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use App\Services\ReservaService;

class ReservaController extends Controller
{
    protected $reservaService;

    public function __construct(ReservaService $reservaService)
    {
        $this->reservaService = $reservaService;
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $startTime = microtime(true);
        $validatedParams = $this->validateParams($request);
        //DB::enableQueryLog();
        $perPage = $request->get('per_page', 20);

        $items = Reserva
        ::where('fk_filestatus_id', '!=', 'AG')
        ->where('reserva_id', '!=', 1);

        if (isset($validatedParams['fecha_alta_desde']) && $validatedParams['fecha_alta_desde']) {
            $items = $items->where('fecha_alta', '>=', $validatedParams['fecha_alta_desde']);
        }
        if (isset($validatedParams['fecha_alta_hasta']) && $validatedParams['fecha_alta_hasta']) {
            $items = $items->where('fecha_alta', '<=', $validatedParams['fecha_alta_hasta']);
        }
        if (isset($validatedParams['codigo']) && $validatedParams['codigo']) {
            $items = $items->where('codigo', 'IN', $validatedParams['fecha_alta_hasta']);
        }
        if (isset($validatedParams['fk_cliente_id']) && $validatedParams['fk_cliente_id']) {
            $items = $items->where('fk_cliente_id', '=', $validatedParams['fk_cliente_id']);
        }
        $withRelations = [];
        if($request->get('withRelations') && $request->get('withRelations') == 'true'){
            $withRelations = [
                'cliente' => function ($query) {
                    $query->select('cliente_id', 'cliente_nombre','cuit'); // Selecciona solo los campos necesarios
                },
                'facturara' => function ($query) {
                    $query->select('cliente_id', 'cliente_nombre','cuit'); // Selecciona solo los campos necesarios
                },
                'vendedor' => function ($query) {
                    $query->select('usuario_id', 'usuario_nombre','usuario_apellido'); // Selecciona solo los campos necesarios
                },
                'servicios'
            ];
            $items = $items->with($withRelations);
        }
        $items = $items
        ->orderByDesc('fecha_alta')
        ->paginate($perPage);

        $endTime = microtime(true);
        $processingTime = $endTime - $startTime;

        return response()->json([
            'data' => $items,
            'processing_time' => $processingTime
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            // Validaciones
            'fk_cliente_id' => 'required:integer::exists:cliente,cliente_id',
            'fk_filestatus_id' => 'required:string:[RQ,DE,AG,DV,CA,CL]',
        ]);

        $item = Reserva::create($data);
        return response()->json($item, 201);
    }

    // GET /api/nombre_modelo/{id}
    public function show(Reserva $Reserva)
    {
        return response()->json($Reserva);
    }

    // PUT /api/nombre_modelo/{id}
    public function update(Request $request, Reserva $Reserva)
    {

        $data = $request->validate([
            // Validaciones
        ]);

        $Reserva->update($data);
        return response()->json($Reserva);
    }

    // PUT /api/nombre_modelo/cancel{id}
    public function cancel( Reserva $Reserva)
    {
        $Reserva->update(['fk_filestatus_id' => 'CA']);
        return response()->json($Reserva);
    }

    // DELETE /api/nombre_modelo/{id}
    public function destroy(Reserva $Reserva)
    {
        $Reserva->delete();
        return response()->json(null, 204);
    }

    public function reservar(Request $request)
    {
        $data = $request->all();
        $reserva = $this->reservaService->guardarReserva($data);

        return response()->json(['message' => 'Reserva guardada con éxito', 'reserva' => $reserva]);
    }
    private function validateParams(Request $request){
        return $request->validate([
            'per_page' => 'integer|min:1|max:100',
            'fecha_alta_desde' => 'nullable|date',
            'fecha_alta_hasta' => 'nullable|date|after_or_equal:fecha_alta_desde',
            'codigo' => 'nullable|string',
            'fk_cliente_id' => 'nullable|integer|exists:cliente,cliente_id',
        ]);

    }
}
