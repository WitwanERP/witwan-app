<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Cadenacliente;
use App\Models\Moneda;
use App\Models\Pais;
use App\Services\ClienteService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Páginas Inertia de Clientes. Comparte el core de listado con la API
 * a través de ClienteService (sin duplicar la lógica de búsqueda/paginación).
 *
 * Columnas y filtros fieles al listado de CI (configuracion/ruc).
 */
class ClienteController extends Controller
{
    /** Filtros aceptados (whitelist) — reflejan los 'display'=>filter de CI. */
    private const FILTROS = [
        'cliente_id',
        'cliente_nombre',
        'cliente_razonsocial',
        'cuit',
        'cliente_ciudad',
        'fk_pais_id',
        'fk_usuario_vendedor',
        'fk_cadenacliente_id',
        'fk_moneda_id',
        'clienteminorista',
        'sort',
        'dir',
    ];

    public function index(Request $request, ClienteService $clientes): Response
    {
        $filtros = $request->only(self::FILTROS);

        $paginador = $clientes->listar($filtros, (int) $request->get('per_page', 80))
            ->through(fn ($c) => [
                'id' => $c->cliente_id,
                'nombre' => $c->cliente_nombre,
                'razonSocial' => $c->cliente_razonsocial,
                'limiteCredito' => (float) $c->limite_credito,
                'cuit' => $c->cuit,
            ]);

        return Inertia::render('Clientes/Index', [
            'clientes' => $paginador,
            'filtros' => $filtros,
            'opciones' => [
                'paises' => Pais::orderBy('pais_nombre')->get(['pais_id', 'pais_nombre']),
                'vendedores' => DB::table('usuario')
                    ->where('usuario_interno', 'Y')
                    ->orderBy('usuario_nombre')
                    ->selectRaw("usuario_id, CONCAT(usuario_nombre,' ',usuario_apellido) AS nombre")
                    ->get(),
                'cadenas' => Cadenacliente::orderBy('cadenacliente_nombre')->get(['cadenacliente_id', 'cadenacliente_nombre']),
                'monedas' => Moneda::orderBy('moneda_nombre')->get(['moneda_id', 'moneda_nombre']),
            ],
        ]);
    }
}
