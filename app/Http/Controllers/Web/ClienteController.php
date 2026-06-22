<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Services\ClienteService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Páginas Inertia de Clientes. Comparte el core de listado con la API
 * a través de ClienteService (sin duplicar la lógica de búsqueda/paginación).
 */
class ClienteController extends Controller
{
    public function index(Request $request, ClienteService $clientes): Response
    {
        $filtros = array_merge(
            ['search' => '', 'estado' => '', 'sort' => 'cliente_nombre', 'dir' => 'asc'],
            $request->only(['search', 'estado', 'sort', 'dir']),
        );

        $paginador = $clientes->listar($filtros, (int) $request->get('per_page', 20))
            ->through(fn ($c) => [
                'id' => $c->cliente_id,
                'nombre' => $c->cliente_nombre,
                'razonSocial' => $c->cliente_razonsocial,
                'cuit' => $c->cuit,
                'email' => $c->cliente_email,
                'telefono' => $c->cliente_telefono,
                'ciudad' => $c->cliente_ciudad,
                'habilitado' => $c->habilita === 'S',
            ]);

        return Inertia::render('Clientes/Index', [
            'clientes' => $paginador,
            'filtros' => $filtros,
        ]);
    }
}
