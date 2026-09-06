<?php

namespace App\Http\Controllers\Web\Admin;

use App\Helpers\SysconfigHelper;
use App\Http\Controllers\Controller;
use App\Services\Admin\ParametrosContables;
use App\Services\CatalogosService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Administración > Contabilidad > Parámetros contables (CI: administracion/Parametros).
 * Asigna una cuenta del plan a cada clave contable de `sysconfig`; el catálogo
 * de claves depende del país de la licencia (Admin_Controller.php:140-273).
 */
class ParametrosContablesController extends Controller
{
    public function index(ParametrosContables $parametros, CatalogosService $catalogos): Response
    {
        return Inertia::render('Admin/ParametrosContables', [
            'grupos' => $parametros->grupos(),
            'cuentas' => $catalogos->planCuentas(),
        ]);
    }

    public function guardar(Request $request, ParametrosContables $parametros): RedirectResponse
    {
        $valores = (array) $request->input('valores', []);
        $adicionales = (array) $request->input('adicionales', []);

        $parametros->guardar($valores, $adicionales);
        SysconfigHelper::olvidar();

        return redirect('/app/admin/parametros-contables')->with('success', 'Parámetros contables guardados.');
    }
}
