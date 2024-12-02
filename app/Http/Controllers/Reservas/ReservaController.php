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
        //DB::enableQueryLog();
        $perPage = $request->get('per_page', 20);
        $items = Reserva
        ::where('fk_filestatus_id', '!=', 'AG')
        ->where('reserva_id', '!=', 1)->with([
            'cliente' => function ($query) {
                $query->select('cliente_id', 'cliente_nombre','cuit'); // Selecciona solo los campos necesarios
            },
            'facturara' => function ($query) {
                $query->select('cliente_id', 'cliente_nombre','cuit'); // Selecciona solo los campos necesarios
            },
            'servicios'
        ])
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
}
