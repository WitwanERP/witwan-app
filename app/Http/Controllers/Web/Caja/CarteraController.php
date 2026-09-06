<?php

namespace App\Http\Controllers\Web\Caja;

use App\Http\Controllers\Web\Reportes\ReporteController;
use App\Services\CatalogosService;
use App\Services\CotizacionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Administración > Caja > Cartera (CI: administracion/cartera/lista): valores
 * en cartera (movimientos con banco, no utilizados, de las cuentas con
 * `plancuenta.cartera=1`) agrupados por banco, con el documento que los
 * originó. "Quitar de cartera" marca `utilizado=1` (el `?eliminar=` del CI).
 * Los depósitos/pagos directos (`guardar`, `pagodirecto`) siguen en el legacy.
 *
 * Diferencia: el CI etiquetaba toda orden como "OP" por un switch sin break;
 * acá MF (fondos), AC (asiento) y OP.
 */
class CarteraController extends ReporteController
{
    protected string $titulo = 'Cartera';

    protected string $ruta = 'caja/cartera';

    protected string $grupo = 'Caja';

    protected ?string $agruparPor = 'banco';

    protected bool $requiereFiltros = false;

    public function __construct(protected CatalogosService $catalogos, protected CotizacionService $cotizaciones) {}

    protected function filtros(): array
    {
        $cuentas = DB::table('plancuenta')->where('cartera', 1)->orderBy('plancuenta_nombre')->get()->map(fn ($c) => ['value' => (int) $c->plancuenta_id, 'label' => $c->plancuenta_nombre])->all();
        $cuenta = (int) (request()->get('tipos') ?: $this->cuentaDefault($cuentas));
        $bancos = DB::table('movimiento')->where('fk_movimiento_id', 0)->where('utilizado', 0)->where('fk_plancuenta_id', $cuenta)
            ->where(fn ($w) => $w->where('banco', '<>', '')->orWhere('operacion', '<>', ''))->distinct()->orderBy('banco')->pluck('banco')
            ->map(fn ($b) => ['value' => $b === '' ? 'Sin banco' : mb_strtoupper((string) $b), 'label' => $b === '' ? 'Sin banco' : mb_strtoupper((string) $b)])->unique('value')->values()->all();

        return [
            ['campo' => 'tipos', 'label' => 'Cuenta', 'tipo' => 'select', 'opciones' => $cuentas, 'default' => (string) $cuenta],
            ['campo' => 'banco', 'label' => 'Banco', 'tipo' => 'select', 'opciones' => $bancos],
            ['campo' => 'nrocheque', 'label' => 'Nro. cheque / descripción', 'tipo' => 'text'],
            ['campo' => 'fecha', 'label' => 'Fecha acreditación', 'tipo' => 'rango'],
            ['campo' => 'moneda', 'label' => 'Moneda', 'tipo' => 'select', 'opciones' => $this->catalogos->monedas(), 'default' => $this->cotizaciones->monedaBasica()],
        ];
    }

    protected function columnas(): array
    {
        return [
            ['campo' => 'doc', 'label' => 'Doc', 'link' => 'doc_link'],
            ['campo' => 'nro', 'label' => 'Nro / descripción'],
            ['campo' => 'fecha', 'label' => 'Fecha'],
            ['campo' => 'acreditacion', 'label' => 'Fecha acreditación'],
            ['campo' => 'operacion', 'label' => 'Operación'],
            ['campo' => 'moneda', 'label' => 'Moneda'],
            ['campo' => 'monto', 'label' => 'Monto', 'tipo' => 'num', 'total' => true],
            ['campo' => 'cotizacion', 'label' => 'Cotización', 'tipo' => 'num'],
            ['campo' => 'acciones', 'label' => 'Acciones', 'tipo' => 'acciones'],
        ];
    }

    protected function consultar(array $f): array
    {
        $cuenta = (int) $f['tipos'];
        if ($cuenta === 0) {
            return [];
        }
        $moneda = $f['moneda'] !== '' ? $f['moneda'] : $this->cotizaciones->monedaBasica();
        $q = DB::table('movimiento')
            ->where('fk_movimiento_id', 0)->where('utilizado', 0)->where('fk_plancuenta_id', $cuenta)->where('banco', '<>', '')->where('fk_moneda_id', $moneda)
            ->select('movimiento_id', 'cotizacion_moneda', 'fecha', 'fecha_acreditacion', 'operacion', 'banco', 'descripcion', 'fk_moneda_id', 'fk_recibo_id', 'fk_ordenadmin_id',
                DB::raw("(movimiento.monto * IF(movimiento.cuenta_credito <> 0 AND movimiento.cuenta_debito, 1, IF(movimiento.cuenta_credito <> 0, -1, IF(movimiento.cuenta_debito <> 0, 1, -1)))
                    * IF(movimiento.operacion = 'MIGRADO DXP', -1, 1)) * IF(movimiento.fk_ordenadmin_id <> 0, -1, 1) AS monto"))
            ->orderBy('banco')->orderBy('fecha_acreditacion')->orderBy('movimiento_id');
        if ($f['banco'] === 'Sin banco') {
            $q->whereRaw("UPPER(banco) = ''");
        } elseif ($f['banco'] !== '') {
            $q->whereRaw('UPPER(banco) = ?', [mb_strtoupper($f['banco'])]);
        }
        if ($f['nrocheque'] !== '') {
            $q->where('descripcion', $f['nrocheque']);
        }
        $this->rango($q, 'fecha_acreditacion', $f, 'fecha');

        $filas = [];
        foreach ($q->get() as $r) {
            $doc = '';
            $link = null;
            $valido = true;
            if ((int) $r->fk_recibo_id !== 0) {
                $rc = DB::table('recibo')->where('recibo_id', (int) $r->fk_recibo_id)->first(['recibo_nro', 'statusrecibo']);
                if ($rc) {
                    $valido = $rc->statusrecibo !== 'AN';
                    $doc = 'RC '.$rc->recibo_nro;
                    $link = "/administracion/recibo/imprimir/{$r->fk_recibo_id}";
                }
            }
            if ((int) $r->fk_ordenadmin_id !== 0) {
                $oa = DB::table('ordenadmin')->where('ordenadmin_id', (int) $r->fk_ordenadmin_id)->first(['nropago', 'status', 'tipo']);
                if ($oa) {
                    $valido = $oa->status !== 'AN';
                    $doc = match ((string) $oa->tipo) {
                        'M' => 'MF ', 'C' => 'AC ', default => 'OP '
                    }.$oa->nropago;
                    $link = "/administracion/ordenpago/imprimir/{$r->fk_ordenadmin_id}";
                }
            }
            if (! $valido) {
                continue;
            }
            $filas[] = [
                'banco' => (string) $r->banco !== '' ? mb_strtoupper((string) $r->banco) : 'Sin banco',
                'doc' => $doc,
                'doc_link' => $link,
                'nro' => (string) $r->descripcion,
                'fecha' => $this->dmy((string) $r->fecha),
                'acreditacion' => $this->dmy((string) $r->fecha_acreditacion),
                'operacion' => (string) $r->operacion,
                'moneda' => $r->fk_moneda_id,
                'monto' => round((float) $r->monto, 2),
                'cotizacion' => (float) $r->cotizacion_moneda,
                'acciones' => [['label' => 'Quitar de cartera', 'href' => "/app/{$this->ruta}/{$r->movimiento_id}/utilizar", 'method' => 'post', 'confirmar' => "¿Marcar el movimiento #{$r->movimiento_id} como utilizado? Deja de aparecer en cartera.", 'peligro' => true]],
            ];
        }

        return $filas;
    }

    public function utilizar(Request $request, int $movimiento): RedirectResponse
    {
        abort_if(DB::table('movimiento')->where('movimiento_id', $movimiento)->doesntExist(), 404);
        DB::table('movimiento')->where('movimiento_id', $movimiento)->update(['utilizado' => 1]);

        return back()->with('success', "Movimiento #{$movimiento} quitado de cartera.");
    }

    private function cuentaDefault(array $cuentas): int
    {
        foreach ($cuentas as $c) {
            if ($c['value'] === 213101) {
                return 213101;
            }
        }

        return (int) ($cuentas[0]['value'] ?? 0);
    }
}
