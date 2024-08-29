<?php

namespace App\Http\Controllers\Reservas;

use App\Http\Controllers\Controller;
use App\Models\Reservas\Reserva;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ReservaController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //DB::enableQueryLog();
        $items = Reserva::where('fk_filestatus_id', '!=', 'AG')->where('reserva_id', '!=', 1)->with(['cliente','facturara'])->orderByDesc('fecha_alta')->paginate(100);
        //dd(DB::getQueryLog());
        return response()->json($items);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            // Validaciones
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
}
