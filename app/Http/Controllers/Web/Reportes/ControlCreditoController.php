<?php

namespace App\Http\Controllers\Web\Reportes;

use App\Helpers\SysconfigHelper;
use App\Services\CatalogosService;
use App\Services\CotizacionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Administración > Reportes > Control de crédito (CI: reportes/controlcredito).
 *
 * Por cada cliente con límite de crédito (o el elegido) calcula lo utilizado:
 * facturas no anuladas (total menos lo aplicado por recibos) más los files
 * CO/CL con servicios sin facturar (total menos cobrado, convertido a la moneda
 * básica con la última cotización) y el crédito extra cargado para hoy en
 * `creditoextra`. El total de factura usa el coeficiente de IVA general del
 * tenant (el legacy tenía 1.19 fijo: mismo resultado en CL).
 *
 * No portado: el ajuste por movimientos de cartera exclusivo de mundotour_sdg.
 */
class ControlCreditoController extends ReporteController
{
    protected string $titulo = 'Control de crédito';

    protected string $ruta = 'admin/reportes/control-credito';

    protected ?string $agruparPor = null;

    protected bool $requiereFiltros = false;

    protected string $ayuda = 'Disponible = límite + extra del día − utilizado. El crédito extra vale sólo para hoy.';

    public function __construct(protected CatalogosService $catalogos, protected CotizacionService $cotizaciones) {}

    protected function filtros(): array
    {
        return [['campo' => 'cliente', 'label' => 'Cliente', 'tipo' => 'select', 'opciones' => $this->catalogos->clientes()]];
    }

    protected function columnas(): array
    {
        return [
            ['campo' => 'id', 'label' => 'ID'],
            ['campo' => 'cliente', 'label' => 'Cliente'],
            ['campo' => 'extra', 'label' => 'Crédito extra (hoy)', 'tipo' => 'num'],
            ['campo' => 'limite', 'label' => 'Límite', 'tipo' => 'num', 'total' => true],
            ['campo' => 'utilizado', 'label' => 'Utilizado', 'tipo' => 'num', 'total' => true],
            ['campo' => 'disponible', 'label' => 'Disponible', 'tipo' => 'num', 'total' => true],
            ['campo' => 'acciones', 'label' => 'Acciones', 'tipo' => 'acciones'],
        ];
    }

    protected function consultar(array $f): array
    {
        $q = DB::table('cliente')->orderBy('cliente_nombre');
        $f['cliente'] !== '' ? $q->where('cliente_id', (int) $f['cliente']) : $q->where('limite_credito', '<>', 0);

        $tasa = (float) SysconfigHelper::get('tasageneral', 19);
        $coef = 1 + ($tasa < 1 ? $tasa : $tasa / 100);
        $hoy = now()->toDateString();
        $extras = DB::table('creditoextra')->where('creditoextra_fecha', $hoy)->pluck('creditoextra_monto', 'fk_cliente_id');
        $ctz = [];

        return $q->get()->map(function ($c) use ($coef, $extras, &$ctz) {
            $usado = 0.0;
            $facturas = DB::table('factura')->where('fk_cliente_id', $c->cliente_id)->whereNotIn('statusfactura', ['AN', 'NU'])
                ->select([
                    DB::raw("ROUND(factura_conceptos_gravados * {$coef} + factura_conceptos_gravadosespecial * 1.105 + factura_conceptos_exentos + factura_conceptos_nogravados + factura_impuesto1, 0)
                        + IF(factura_fecha > '2015-12-17', factura_rgterrestres, 0)
                        - CAST(SUBSTRING_INDEX(SUBSTRING_INDEX(factura.remitofull, ':', 3), ':', -1) AS DECIMAL(12,2)) AS total"),
                    DB::raw('(SELECT COALESCE(SUM(rf.monto), 0) FROM rel_facturarecibo rf WHERE rf.fk_factura_id = factura.factura_id) AS aplicado'),
                ])->get();
            foreach ($facturas as $fc) {
                $usado += (float) $fc->total - (float) $fc->aplicado;
            }

            $files = DB::table('reserva as r')
                ->join('servicio as s', 's.fk_reserva_id', '=', 'r.reserva_id')
                ->where('r.fk_cliente_id', $c->cliente_id)->whereIn('r.fk_filestatus_id', ['CL', 'CO'])
                ->whereColumn('r.total', '<>', 'r.cobrado')
                ->where('s.status', '<>', 'CA')
                ->whereNotIn('s.servicio_id', fn ($sub) => $sub->selectRaw('DISTINCT fk_servicio_id')->from('rel_serviciofactura'))
                ->groupBy('r.reserva_id')
                ->get(['r.reserva_id', 'r.total', 'r.cobrado', 'r.fk_moneda_id']);
            foreach ($files as $fl) {
                $moneda = (string) $fl->fk_moneda_id;
                $ctz[$moneda] ??= $this->cotizaciones->aLaVenta($moneda);
                $usado += ((float) $fl->total - (float) $fl->cobrado) * $ctz[$moneda];
            }

            $extra = (float) ($extras[$c->cliente_id] ?? 0);
            $limite = (float) $c->limite_credito;
            $base = "/app/{$this->ruta}/{$c->cliente_id}/extra";

            return [
                'id' => (int) $c->cliente_id,
                'cliente' => $c->cliente_nombre,
                'extra' => $extra,
                'limite' => $limite,
                'utilizado' => round($usado, 2),
                'disponible' => round($limite + $extra - $usado, 2),
                'acciones' => $extra == 0.0
                    ? [['label' => 'Cargar crédito extra', 'href' => $base, 'method' => 'post', 'prompt' => "Crédito extra de hoy para {$c->cliente_nombre}:", 'campo' => 'valor']]
                    : [['label' => 'Quitar crédito extra', 'href' => $base, 'method' => 'post', 'datos' => ['valor' => 'delete'], 'confirmar' => "¿Quitar el crédito extra de hoy de {$c->cliente_nombre}?", 'peligro' => true]],
            ];
        })->all();
    }

    /** Réplica del POST del legacy: REPLACE del extra de hoy, o borrado con valor 'delete'. */
    public function extra(Request $request, int $cliente): RedirectResponse
    {
        abort_if(DB::table('cliente')->where('cliente_id', $cliente)->doesntExist(), 404);
        $valor = trim((string) $request->input('valor', ''));
        $hoy = now()->toDateString();

        if ($valor === 'delete') {
            DB::table('creditoextra')->where('creditoextra_fecha', $hoy)->where('fk_cliente_id', $cliente)->delete();

            return back()->with('success', "Crédito extra de hoy quitado al cliente #{$cliente}.");
        }
        abort_unless(is_numeric($valor), 422, 'El monto debe ser numérico.');
        DB::table('creditoextra')->where('creditoextra_fecha', $hoy)->where('fk_cliente_id', $cliente)->delete();
        DB::table('creditoextra')->insert([
            'regdate' => now(), 'creditoextra_fecha' => $hoy, 'fk_cliente_id' => $cliente,
            'creditoextra_monto' => (float) $valor, 'fk_usuario_id' => (int) auth()->id(),
        ]);

        return back()->with('success', "Crédito extra de hoy cargado al cliente #{$cliente}.");
    }
}
