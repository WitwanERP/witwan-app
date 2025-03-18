<?php

namespace App\Http\Controllers\Productos;

use App\Http\Controllers\Controller;
use App\Models\Productos\Submodulo;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class SubmoduloController extends Controller
{
    public function index(Request $request)
    {
        $perPage = $request->get('per_page', 15);
        $submodulos = Submodulo::with(['modulo'])
            ->paginate($perPage);
            return response()->json($submodulos);


    }
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'submodulo_nombre' => 'required|string|max:100',
            'fk_plancuenta_id' => 'required|exists:plancuenta,plancuenta_id',
            'tipoproducto_activo' => 'boolean',
            'tipoproducto_relacionado' => 'boolean',
            'tipproducto_tipo' => 'nullable|string|max:1|in:S,I,E',
            'campoendocumento' => 'nullable|string|max:100',
        ]);
    }

}
/**
es eventual?
fk_plancuenta_id:  relacion con la tabla plan de cuentas para el costo del servicio
cuenta_renta: relacion con la tabla plan de cuentas para la renta del servicio
fk_proveedor_id: relacion con la tabla proveedores, puede ser nulo
tipoproducto_relacionado: va en la tabla de relacionados en vez de la tabla de eventuales?
tipoproducto_activo: booleano
tipproducto_tipo: string (S:servicio,I: ingreso, E:egreso)
campoendocumento: string sirve para saber a que campo de la tabla FC/NC va a ir el costo
 *
 */
