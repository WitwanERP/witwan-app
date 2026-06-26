<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\PasajeroRequest;
use App\Models\Pais;
use App\Services\PasajeroService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Páginas Inertia de Pasajeros (réplica de configuracion/rup de CI).
 * Primera pasada: datos personales + documento principal + domicilio fiscal +
 * fiscal + gastos, y el tilde para crear un cliente a partir del pasajero.
 */
class PasajeroController extends Controller
{
    private const FILTROS = [
        'pasajero_id', 'pasajero_apellido', 'pasajero_nombre', 'pasajero_email', 'sort', 'dir',
    ];

    public function index(Request $request, PasajeroService $pasajeros): Response
    {
        $filtros = $request->only(self::FILTROS);

        $paginador = $pasajeros->listar($filtros, (int) $request->get('per_page', 80))
            ->through(fn ($p) => [
                'id' => $p->pasajero_id,
                'apellido' => $p->pasajero_apellido,
                'nombre' => $p->pasajero_nombre,
                'email' => $p->pasajero_email,
                'esCliente' => (int) $p->fk_cliente_id > 0,
            ]);

        return Inertia::render('Pasajeros/Index', [
            'pasajeros' => $paginador,
            'filtros' => $filtros,
        ]);
    }

    public function create(Request $request): Response
    {
        return Inertia::render('Pasajeros/Form', [
            'opciones' => $this->opciones(),
            'ciudades' => $this->ciudades((int) $request->integer('pais_id')),
        ]);
    }

    public function store(PasajeroRequest $request, PasajeroService $pasajeros): RedirectResponse
    {
        $id = $pasajeros->crear(
            $request->validated(),
            (int) auth()->id(),
            (int) (app()->bound('tenant') ? app('tenant')->licencia : 0),
        );

        return redirect()
            ->route('pasajeros.index')
            ->with('success', "Pasajero #{$id} creado correctamente.");
    }

    public function edit(int $pasajero, PasajeroService $pasajeros): Response
    {
        $datos = $pasajeros->paraEditar($pasajero);

        abort_if($datos === null, 404);

        return Inertia::render('Pasajeros/Form', [
            'pasajero' => $datos,
            'opciones' => $this->opciones(),
            'ciudades' => $this->ciudades((int) ($datos['fk_pais_id'] ?? 0)),
        ]);
    }

    public function update(PasajeroRequest $request, int $pasajero, PasajeroService $pasajeros): RedirectResponse
    {
        $pasajeros->actualizar(
            $pasajero,
            $request->validated(),
            (int) auth()->id(),
            (int) (app()->bound('tenant') ? app('tenant')->licencia : 0),
        );

        return redirect()
            ->route('pasajeros.index')
            ->with('success', "Pasajero #{$pasajero} actualizado correctamente.");
    }

    /** Ciudades de un país (select dependiente). */
    private function ciudades(int $paisId): array
    {
        if ($paisId <= 0) {
            return [];
        }

        return DB::table('ciudad')
            ->where('fk_pais_id', $paisId)
            ->where('ciudad_activo', 1)
            ->orderBy('ciudad_nombre')
            ->get(['ciudad_id', 'ciudad_nombre'])
            ->all();
    }

    /** Datos de referencia para los selects del form. */
    private function opciones(): array
    {
        return [
            'paises' => Pais::orderBy('pais_nombre')->get(['pais_id', 'pais_nombre']),
            'vendedores' => DB::table('usuario')
                ->where('usuario_interno', 'Y')
                ->orderBy('usuario_nombre')
                ->selectRaw("usuario_id, CONCAT(usuario_nombre,' ',usuario_apellido) AS nombre")
                ->get(),
            'condicionesIva' => DB::table('condicioniva')->orderBy('condicioniva_nombre')->get(['condicioniva_id', 'condicioniva_nombre']),
            'tiposClaveFiscal' => DB::table('tipoclavefiscal')->orderBy('tipoclavefiscal_nombre')->get(['tipoclavefiscal_id', 'tipoclavefiscal_nombre']),
            'tarifarios' => DB::table('tarifario')->orderBy('orden')->orderBy('tarifario_nombre')->get(['tarifario_id', 'tarifario_nombre', 'fk_sistema_id']),
            'monedas' => DB::table('moneda')->orderBy('moneda_nombre')->get(['moneda_id', 'moneda_nombre']),
        ];
    }
}
