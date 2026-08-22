<?php

namespace App\Http\Controllers\Web\Documentos;

use App\Http\Controllers\Controller;
use App\Services\Documentos\DteChileService;
use App\Support\Permisos;
use App\Support\Secciones;
use Illuminate\Http\Request;
use Inertia\Inertia;

/**
 * Listado de documentos tributarios electrónicos recibidos (Chile).
 *
 * Reemplaza a `factura3ero::listadobcn()` del CI. Sólo aplica a licencias
 * chilenas; en el resto la pantalla informa que no corresponde en vez de
 * intentar hablar con el webservice.
 */
class DteChileController extends Controller
{
    public function index(Request $request, DteChileService $dte)
    {
        Permisos::exigir(Secciones::FACTURA_TERCERO, 'acceso', 'Facturas de Terceros');

        $desde = $request->get('desde');
        $hasta = $request->get('hasta');

        $documentos = [];
        $error = null;

        if (! $dte->aplicaALaLicencia()) {
            $error = 'El listado de DTE sólo está disponible para licencias de Chile.';
        } elseif ($desde && $hasta) {
            try {
                $documentos = $dte->recibidos($desde, $hasta);
            } catch (\Throwable $e) {
                // El legacy hacía die() con el mensaje del webservice; acá se
                // muestra en la pantalla sin romper la navegación.
                $error = $e->getMessage();
            }
        }

        return Inertia::render('Documentos/FacturasProveedor/DteChile', [
            'baseUrl' => '/app/facturas-proveedor',
            'filtros' => ['desde' => $desde, 'hasta' => $hasta],
            'documentos' => $documentos,
            'error' => $error,
            'disponible' => $dte->disponible(),
        ]);
    }
}
