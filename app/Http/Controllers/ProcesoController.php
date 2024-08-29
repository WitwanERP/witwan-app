<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use Illuminate\Support\Facades\Hash;
use Illuminate\Contracts\Hashing\Hasher;

class ProcesoController extends Controller
{
    protected $hasher;

    public function __construct(Hasher $hasher)
    {
        $this->hasher = $hasher;
    }

    public function ejecutar()
    {
        // Aquí va la lógica de tu proceso
        // Por ejemplo, ejecutar un trabajo en background o cualquier tarea necesaria

        // Retorna una respuesta simple
        $contraseña = Hash::make('rays2020+');
        $contraseña2 = $this->hasher->make('rays2020+');
        return response()->json(['mensaje' => 'Proceso ejecutado correctamente: '.$contraseña, 'p1' => $contraseña, 'p2' => $contraseña2]);
    }
}
