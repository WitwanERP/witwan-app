<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Services\CotizacionService;
use App\Support\Permisos;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Administración > Monedas > Tipo de cambio (CI: administracion/cambio/ultimas).
 * Muestra la cotización vigente (venta y costo) de cada moneda y permite
 * actualizar la de hoy; la moneda básica vale 1 y no se edita. Editar requiere
 * el permiso 'edicion' de la sección (104 en brain), como en el legacy.
 */
class TipoCambioController extends Controller
{
    public const SECCION = 'administracion/cambio/ultimas';

    public function index(CotizacionService $cotizaciones): Response
    {
        $basica = $cotizaciones->monedaBasica();

        $monedas = DB::table('moneda')
            ->orderBy('orden')
            ->orderBy('moneda_id')
            ->get(['moneda_id', 'moneda_nombre', 'moneda_basica'])
            ->unique('moneda_id')
            ->map(fn ($m) => [
                'moneda' => $m->moneda_id,
                'nombre' => $m->moneda_nombre,
                'basica' => $m->moneda_id === $basica,
                'venta' => $m->moneda_id === $basica ? 1 : $cotizaciones->aLaVenta($m->moneda_id),
                'costo' => $m->moneda_id === $basica ? 1 : $cotizaciones->alCosto($m->moneda_id),
            ])
            ->values()
            ->all();

        return Inertia::render('Admin/TipoCambio', [
            'monedas' => $monedas,
            'monedaBasica' => $basica,
            'puedeEditar' => Permisos::tiene(self::SECCION, 'edicion'),
            'fecha' => now()->format('d/m/Y'),
        ]);
    }

    /** Réplica de cambio.php::guardarcambio(): REPLACE de la cotización de HOY para la moneda. */
    public function guardar(Request $request, CotizacionService $cotizaciones): RedirectResponse
    {
        Permisos::exigir(self::SECCION, 'edicion', 'tipo de cambio', 'admin.permisos_estrictos');

        $data = $request->validate([
            'moneda' => 'required|string|max:10',
            'valor' => 'required|numeric|min:0',
            'valor2' => 'nullable|numeric|min:0',
        ]);

        abort_if($data['moneda'] === $cotizaciones->monedaBasica(), 422, 'La moneda básica no se cotiza.');

        DB::table('cotizacion')->updateOrInsert(
            ['cotizacion_moneda' => $data['moneda'], 'cotizacion_fecha' => now()->toDateString()],
            ['cotizacion_relacion' => $data['valor'], 'cotizacion_costo' => $data['valor2'] ?? $data['valor']],
        );

        return back()->with('success', "Cotización de {$data['moneda']} actualizada.");
    }
}
