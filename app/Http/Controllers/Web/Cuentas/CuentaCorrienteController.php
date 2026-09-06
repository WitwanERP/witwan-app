<?php

namespace App\Http\Controllers\Web\Cuentas;

use App\Helpers\PermisoHelper;
use App\Http\Controllers\Controller;
use App\Services\CotizacionService;
use App\Services\Cuentas\CuentaCorrienteClienteService;
use App\Services\Cuentas\CuentaCorrienteProveedorService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Administración > Cuentas > Cuenta corriente cliente / proveedor (CI:
 * administracion/cuentas/cliente y /proveedor). Los usuarios de tipo cliente
 * (CLI/CLM) sólo ven su propia cuenta, como en el legacy.
 */
class CuentaCorrienteController extends Controller
{
    public function __construct(
        private CuentaCorrienteClienteService $clientes,
        private CuentaCorrienteProveedorService $proveedores,
        private CotizacionService $cotizaciones,
    ) {}

    public function cliente(Request $request): Response|StreamedResponse
    {
        $usuario = $request->user();
        $alcance = PermisoHelper::alcanceCliente();
        $q = DB::table('cliente')->orderBy('cliente_nombre');
        $esCliente = in_array($usuario?->fk_tipousuario_id, ['CLI', 'CLM'], true) || $alcance['tipo'] === PermisoHelper::ALCANCE_CLIENTE;
        if ($esCliente) {
            $q->where('cliente_id', (int) $usuario->fk_cliente_id);
        }
        $empresas = $q->get(['cliente_id', 'cliente_nombre'])->map(fn ($c) => ['value' => (int) $c->cliente_id, 'label' => $c->cliente_nombre])->all();
        $filtros = $this->filtros($request);
        if ($esCliente) {
            $filtros['empresa'] = (int) $usuario->fk_cliente_id;
        }

        return $this->responder($request, 'Cuenta corriente cliente', '/app/cuentas/cliente', $filtros, $empresas,
            fn () => $this->clientes->cuenta($filtros['empresa'], $filtros['from'], $filtros['to'], $filtros['tiporeporte'], $filtros['monedareporte']));
    }

    public function proveedor(Request $request): Response|StreamedResponse
    {
        $empresas = DB::table('proveedor')->where('eliminar', '<>', 'Y')->orderBy('proveedor_nombre')
            ->get(['proveedor_id', 'proveedor_nombre'])->map(fn ($p) => ['value' => (int) $p->proveedor_id, 'label' => $p->proveedor_nombre])->all();
        $filtros = $this->filtros($request);

        return $this->responder($request, 'Cuenta corriente proveedor', '/app/cuentas/proveedor', $filtros, $empresas,
            fn () => $this->proveedores->cuenta($filtros['empresa'], $filtros['from'], $filtros['to'], $filtros['tiporeporte'], $filtros['monedareporte']));
    }

    private function responder(Request $request, string $titulo, string $baseUrl, array $filtros, array $empresas, callable $consulta): Response|StreamedResponse
    {
        $cuenta = $filtros['empresa'] !== 0 ? $consulta() : null;

        if ($cuenta && (string) $request->get('export', '') === '1') {
            $nombre = collect($empresas)->firstWhere('value', $filtros['empresa'])['label'] ?? '';

            return $this->exportar($cuenta, $nombre, $titulo);
        }

        return Inertia::render('Cuentas/CuentaCorriente', [
            'titulo' => $titulo,
            'baseUrl' => $baseUrl,
            'filtros' => $filtros,
            'empresas' => $empresas,
            'monedaBasica' => $this->cotizaciones->monedaBasica(),
            'cuenta' => $cuenta,
        ]);
    }

    private function filtros(Request $request): array
    {
        return [
            'empresa' => (int) $request->get('empresa', 0),
            'from' => $this->iso($request->get('from')),
            'to' => $this->iso($request->get('to')),
            'tiporeporte' => (string) $request->get('tiporeporte', 'C') === 'D' ? 'D' : 'C',
            'monedareporte' => (string) $request->get('monedareporte', '') ?: $this->cotizaciones->monedaBasica(),
        ];
    }

    private function exportar(array $cuenta, string $nombre, string $titulo): StreamedResponse
    {
        $archivo = str_replace(' ', '-', mb_strtolower($titulo)).'-'.now()->format('Ymd-His').'.csv';

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
        }, $archivo, ['Content-Type' => 'text/csv; charset=UTF-8']);
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
