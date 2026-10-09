<?php

namespace App\Http\Controllers\Web\Reservas;

use App\Http\Controllers\Controller;
use App\Services\Reservas\MapaContable\MapaContableFileService;
use App\Support\Licencia;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

/**
 * Mapa contable de un file: circuito de venta, circuito de costo, contabilidad,
 * cuánto se gana en realidad y desvíos. Sólo lectura; los comprobantes linkean al
 * legacy para imprimir/anular.
 *
 * Detrás de sysconfig.mapa_contable_file (sembrado en 0 por
 * db/updates/0013_mapa_contable_file.sql del CI): mientras esté apagado responde
 * 404. Con ?embed=1 se renderiza sin layout, para abrirlo desde la ficha del CI.
 */
class MapaContableController extends Controller
{
    public function show(int $id, Request $request, MapaContableFileService $mapas)
    {
        abort_unless((int) Licencia::sysconfig('mapa_contable_file', 0) === 1, 404);

        // Expone costos y rentas: no es para los usuarios cliente.
        $usuario = Auth::user();
        abort_if($usuario && in_array((string) $usuario->fk_tipousuario_id, ['CLI', 'CLM'], true), 403);

        $mapa = $mapas->armar($id);
        abort_if($mapa === null, 404);

        return Inertia::render('Reservas/MapaContable', [
            'mapa' => $mapa,
            'embed' => $request->boolean('embed'),
        ]);
    }
}
