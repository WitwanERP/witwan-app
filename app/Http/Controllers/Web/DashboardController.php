<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Services\DashboardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Inicio (CI: dashboard.php). Los widgets se cargan según los permisos
 * "Inicio ..." del rol; los gráficos se piden por JSON con el área y el
 * período elegidos, como los reload del legacy.
 */
class DashboardController extends Controller
{
    public function __construct(private DashboardService $dashboard) {}

    public function index(Request $request): Response
    {
        $usuario = $request->user();
        $modulos = $this->dashboard->modulos($usuario);
        $tiene = fn (string $m) => in_array($m, $modulos, true);

        return Inertia::render('Dashboard', [
            'modulos' => $modulos,
            'notificaciones' => $tiene('inicio_vencimientos') ? $this->dashboard->notificaciones((int) $usuario->usuario_id) : [],
            'misReservas' => $tiene('inicio_ureservas') ? $this->dashboard->misReservas($usuario) : [],
            'operaciones' => $tiene('inicio_operacion') ? $this->dashboard->operaciones($usuario) : [],
            'misCotizaciones' => $tiene('inicio_ucotizaciones') ? $this->dashboard->misCotizaciones($usuario) : [],
            'sistemas' => (array) config('menu.sistemas', []),
        ]);
    }

    public function reservas(Request $request): JsonResponse
    {
        return response()->json($this->dashboard->reservasPorPeriodo($this->sistema($request), $this->periodo($request)));
    }

    public function cobranzas(Request $request): JsonResponse
    {
        return response()->json($this->dashboard->cobranzasPorPeriodo($this->sistema($request), $this->periodo($request)));
    }

    private function sistema(Request $request): ?int
    {
        $s = (string) $request->get('sistema', '');

        return ctype_digit($s) && (int) $s > 0 ? (int) $s : null;
    }

    private function periodo(Request $request): string
    {
        $p = (string) $request->get('periodo', 'anio');

        return isset(DashboardService::PERIODOS[$p]) ? $p : 'anio';
    }
}
