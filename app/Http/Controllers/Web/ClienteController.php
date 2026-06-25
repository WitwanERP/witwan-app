<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\ClienteRequest;
use App\Models\Cadenacliente;
use App\Models\Moneda;
use App\Models\Pais;
use App\Services\AuditoriaService;
use App\Services\ClienteService;
use Illuminate\Http\RedirectResponse;
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

    /**
     * Form de alta de cliente (réplica de configuracion/ruc de CI).
     * `ciudades` se recarga por partial reload cuando cambia el país.
     */
    public function create(Request $request): Response
    {
        return Inertia::render('Clientes/Form', [
            'opciones' => $this->opciones(),
            'ciudades' => $this->ciudades((int) $request->integer('pais_id')),
        ]);
    }

    /** Persiste el alta y vuelve al listado. */
    public function store(ClienteRequest $request, ClienteService $clientes, AuditoriaService $auditoria): RedirectResponse
    {
        // Clientes ya existentes con el mismo CUIT (normalizado): si el usuario
        // confirmó guardar igual, queda registrado en auditoría más abajo.
        $cuit = str_replace(['-', '.', ' '], '', (string) $request->input('cuit'));
        $duplicados = $cuit === '' ? collect() : DB::table('cliente')
            ->whereRaw(ClienteService::SQL_CUIT_NORMALIZADO.' = ?', [$cuit])
            ->limit(5)
            ->get(['cliente_id', 'cliente_nombre', 'cliente_razonsocial', 'cuit']);

        $id = $clientes->crear(
            $request->validated(),
            (int) auth()->id(),
            (int) (app()->bound('tenant') ? app('tenant')->licencia : 0),
        );

        // Alta forzada sobre CUIT repetido: dejar traza en la tabla de auditoría.
        if ($request->boolean('cuit_confirmado') && $duplicados->isNotEmpty()) {
            $auditoria->registrar(
                'cliente',
                $id,
                'ALTA_CUIT_DUPLICADO',
                ['cuit' => $cuit, 'existentes' => $duplicados],
                [
                    'cliente_id' => $id,
                    'cuit' => $request->input('cuit'),
                    'cliente_razonsocial' => $request->input('cliente_razonsocial'),
                    'cliente_nombre' => $request->input('cliente_nombre'),
                ],
            );
        }

        return redirect()
            ->route('clientes.index')
            ->with('success', "Cliente #{$id} creado correctamente.");
    }

    /**
     * Chequeo liviano de CUIT duplicado para el aviso del form (antes de guardar).
     * Devuelve { existe, nombre, cliente_id }. No bloquea: el front muestra un
     * confirm y, si el usuario acepta, reenvía con cuit_confirmado.
     */
    public function chequearCuit(Request $request): \Illuminate\Http\JsonResponse
    {
        $cuit = str_replace(['-', '.', ' '], '', (string) $request->query('cuit', ''));

        if ($cuit === '') {
            return response()->json(['existe' => false]);
        }

        $excluir = (int) $request->integer('cliente_id');

        $cli = DB::table('cliente')
            ->whereRaw(ClienteService::SQL_CUIT_NORMALIZADO.' = ?', [$cuit])
            ->when($excluir > 0, fn ($q) => $q->where('cliente_id', '!=', $excluir))
            ->first(['cliente_id', 'cliente_nombre', 'cliente_razonsocial']);

        return response()->json([
            'existe' => (bool) $cli,
            'nombre' => $cli ? ($cli->cliente_razonsocial ?: $cli->cliente_nombre) : null,
            'cliente_id' => $cli->cliente_id ?? null,
        ]);
    }

    /** Ciudades de un país (para el select dependiente del form). */
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
            'cadenas' => Cadenacliente::orderBy('cadenacliente_nombre')->get(['cadenacliente_id', 'cadenacliente_nombre']),
            'monedas' => Moneda::orderBy('moneda_nombre')->get(['moneda_id', 'moneda_nombre']),
            'condicionesIva' => DB::table('condicioniva')->orderBy('condicioniva_nombre')->get(['condicioniva_id', 'condicioniva_nombre']),
            'tiposFactura' => DB::table('tipofactura')->orderBy('tipofactura_nombre')->get(['tipofactura_id', 'tipofactura_nombre']),
            'tiposClaveFiscal' => DB::table('tipoclavefiscal')->orderBy('tipoclavefiscal_nombre')->get(['tipoclavefiscal_id', 'tipoclavefiscal_nombre']),
            'tarifarios' => DB::table('tarifario')->orderBy('orden')->orderBy('tarifario_nombre')->get(['tarifario_id', 'tarifario_nombre', 'fk_sistema_id']),
            'idiomas' => DB::table('idioma')->orderBy('orden')->get(['idioma_id', 'idioma_nombre']),
        ];
    }
}
