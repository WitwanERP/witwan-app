<?php

namespace App\Http\Controllers\Web\Cuentas;

use App\Helpers\PermisoHelper;
use App\Http\Controllers\Controller;
use App\Services\CotizacionService;
use App\Services\Cuentas\CuentaCorrienteClienteService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Administración > Cuentas > Cuenta corriente cliente (CI: administracion/cuentas/cliente).
 * Los usuarios de tipo cliente (CLI/CLM) sólo ven su propia cuenta, como en el
 * legacy.
 */
class CuentaCorrienteController extends Controller
{
    public function __construct(private CuentaCorrienteClienteService $clientes, private CotizacionService $cotizaciones) {}

    public function cliente(Request $request): Response|StreamedResponse
    {
        [$filtros, $empresas] = $this->contexto($request);
        $cuenta = $filtros['empresa'] !== 0
            ? $this->clientes->cuenta($filtros['empresa'], $filtros['from'], $filtros['to'], $filtros['tiporeporte'], $filtros['monedareporte'])
            : null;

        if ($cuenta && (string) $request->get('export', '') === '1') {
            $nombre = collect($empresas)->firstWhere('value', $filtros['empresa'])['label'] ?? '';

            return $this->exportar($cuenta, $nombre);
        }

        return Inertia::render('Cuentas/CuentaCorriente', [
            'titulo' => 'Cuenta corriente cliente',
            'baseUrl' => '/app/cuentas/cliente',
            'filtros' => $filtros,
            'empresas' => $empresas,
            'monedaBasica' => $this->cotizaciones->monedaBasica(),
            'cuenta' => $cuenta,
        ]);
    }

    /** @return array{0:array,1:list<array{value:int,label:string}>} */
    private function contexto(Request $request): array
    {
        $usuario = $request->user();
        $alcance = PermisoHelper::alcanceCliente();
        $q = DB::table('cliente')->orderBy('cliente_nombre');
        if ($alcance['tipo'] === PermisoHelper::ALCANCE_CLIENTE || in_array($usuario?->fk_tipousuario_id, ['CLI', 'CLM'], true)) {
            $q->where('cliente_id', (int) $usuario->fk_cliente_id);
        }
        $empresas = $q->get(['cliente_id', 'cliente_nombre'])->map(fn ($c) => ['value' => (int) $c->cliente_id, 'label' => $c->cliente_nombre])->all();

        $empresa = (int) $request->get('empresa', 0);
        if (in_array($usuario?->fk_tipousuario_id, ['CLI', 'CLM'], true)) {
            $empresa = (int) $usuario->fk_cliente_id;
        }

        return [[
            'empresa' => $empresa,
            'from' => $this->iso($request->get('from')),
            'to' => $this->iso($request->get('to')),
            'tiporeporte' => (string) $request->get('tiporeporte', 'C') === 'D' ? 'D' : 'C',
            'monedareporte' => (string) $request->get('monedareporte', '') ?: $this->cotizaciones->monedaBasica(),
        ], $empresas];
    }

    private function exportar(array $cuenta, string $nombre): StreamedResponse
    {
        return response()->streamDownload(function () use ($cuenta, $nombre) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, [$nombre], ';');
            fputcsv($out, ['FECHA', 'CUENTA', 'DEBE', 'HABER', 'SALDO'], ';');
            fputcsv($out, ['SALDO ANTERIOR', '', '', '', $cuenta['anterior']], ';');
            foreach ($cuenta['grupos'] as $g) {
                foreach ($g['movimientos'] as $m) {
                    fputcsv($out, [$m['fecha'], implode(' // ', array_column($m['concepto'], 'texto')), $m['d'] ?: '', $m['h'] ?: '', $m['saldo']], ';');
                }
            }
            fputcsv($out, ['TOTALES', '', $cuenta['totales']['d'], $cuenta['totales']['h'], $cuenta['totales']['saldo']], ';');
            fclose($out);
        }, 'cuenta-corriente-cliente-'.now()->format('Ymd-His').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function iso(mixed $valor): string
    {
        $valor = trim((string) $valor);
        if (preg_match('#^(\d{2})/(\d{2})/(\d{4})$#', $valor, $m)) {
            return "{$m[3]}-{$m[2]}-{$m[1]}";
        }

        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $valor) ? $valor : '';
    }
}
