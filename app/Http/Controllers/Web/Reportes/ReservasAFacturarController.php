<?php

namespace App\Http\Controllers\Web\Reportes;

use App\Helpers\SysconfigHelper;
use App\Services\CatalogosService;
use App\Support\Licencia;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Administración > Documentos > Reservas a facturar (CI: administracion/reportes/afacturar)
 * y Facturación acumulada (afacturarpp, por período).
 *
 * Files cerrados (CL; con `sysconfig.facturapracial=1` también los que tengan
 * algún servicio CL) con sus facturas/NC relacionadas por servicio y el botón
 * FACTURAR (al legacy) cuando queda algo por facturar. Sin filtros muestra los
 * files que vencen en los próximos 7 días, salvo `sysconfig.factura_vertodos`:
 * 1 = todos los no facturados, 2 = ninguno. En modo período sólo se listan
 * clientes con `facturacion_periodo=1` y es obligatorio elegir cliente.
 */
class ReservasAFacturarController extends ReporteController
{
    protected string $titulo = 'Reservas a facturar';

    protected string $ruta = 'admin/reportes/reservas-a-facturar';

    protected string $grupo = 'Documentos';

    protected bool $requiereFiltros = false;

    protected bool $porPeriodo = false;

    public function __construct(protected CatalogosService $catalogos) {}

    protected function preparar(Request $request): void
    {
        $this->porPeriodo = str_ends_with((string) $request->route()?->uri(), 'facturacion-acumulada') || str_contains((string) $request->route()?->uri(), 'facturacion-acumulada/');
        if ($this->porPeriodo) {
            $this->titulo = 'Facturación acumulada';
            $this->ruta = 'admin/reportes/facturacion-acumulada';
            $this->requiereFiltros = true;
            $this->ayuda = 'Elegí el cliente: se listan los files con servicios sin facturar de clientes con facturación por período.';
        }
    }

    protected function filtros(): array
    {
        $clientes = $this->porPeriodo
            ? DB::table('cliente')->where('tipofacturacion', '<>', 0)->where('facturacion_periodo', 1)->whereIn('cliente_id', fn ($q) => $q->select('facturar_a')->from('reserva'))
                ->orderBy('cliente_nombre')->get()->map(fn ($c) => ['value' => (int) $c->cliente_id, 'label' => $c->cliente_nombre])->all()
            : $this->catalogos->clientes();

        return [
            ['campo' => 'codigo', 'label' => 'Código (usar * para varios)', 'tipo' => 'text'],
            ['campo' => 'filestatus', 'label' => 'Status file', 'tipo' => 'select', 'opciones' => [['value' => 'CO', 'label' => 'CO'], ['value' => 'CL', 'label' => 'CL']]],
            ['campo' => 'tipocodigo', 'label' => 'Tipo de reserva', 'tipo' => 'select', 'opciones' => $this->catalogos->tiposCodigo()],
            ['campo' => 'fk_cliente_id', 'label' => 'Cliente (facturar a)', 'tipo' => 'select', 'opciones' => $clientes],
            ['campo' => 'titular', 'label' => 'Titular (apellido)', 'tipo' => 'text'],
            ['campo' => 'fk_usuario_id', 'label' => 'Vendedor', 'tipo' => 'select', 'opciones' => $this->catalogos->usuariosInternos()],
            ['campo' => 'fecha_alta', 'label' => 'Fecha alta', 'tipo' => 'rango'],
            ['campo' => 'vigencia_ini', 'label' => 'Fecha in', 'tipo' => 'rango'],
            ['campo' => 'fecha_emision', 'label' => 'Fecha emisión (aéreos)', 'tipo' => 'rango'],
            ['campo' => 'fk_sistema_id', 'label' => 'Área', 'tipo' => 'select', 'opciones' => $this->catalogos->sistemas()],
            ['campo' => 'facturado', 'label' => 'Facturados', 'tipo' => 'select', 'opciones' => [['value' => '1', 'label' => 'Sólo facturados'], ['value' => '0', 'label' => 'Sólo sin facturar']]],
            ['campo' => 'reportesaldo', 'label' => 'Saldo', 'tipo' => 'select', 'opciones' => [['value' => '1', 'label' => 'Deudor (total > cobrado)'], ['value' => '2', 'label' => 'A favor (total < cobrado)'], ['value' => '3', 'label' => 'Igual a 0']]],
            ['campo' => 'precision', 'label' => 'Precisión diferencia', 'tipo' => 'text'],
            ['campo' => 'reporteventa', 'label' => 'Venta distinta de 0', 'tipo' => 'bool'],
        ];
    }

    protected function columnas(): array
    {
        return [
            ['campo' => 'tipo', 'label' => 'Tipo'],
            ['campo' => 'codigo', 'label' => 'Código', 'link' => 'file_link'],
            ['campo' => 'id', 'label' => 'ID'],
            ['campo' => 'status', 'label' => 'Status'],
            ['campo' => 'titular', 'label' => 'Titular'],
            ['campo' => 'cliente_reserva', 'label' => 'Cliente'],
            ['campo' => 'facturar_a', 'label' => 'Facturar a'],
            ['campo' => 'agente', 'label' => 'Agente'],
            ['campo' => 'fecha_alta', 'label' => 'Fecha reserva'],
            ['campo' => 'fecha_in', 'label' => 'Fecha in'],
            ['campo' => 'moneda', 'label' => 'Moneda'],
            ['campo' => 'venta', 'label' => 'Venta', 'tipo' => 'num', 'total' => true],
            ['campo' => 'saldo', 'label' => 'Saldo', 'tipo' => 'num', 'total' => true],
            ['campo' => 'renta', 'label' => 'Renta', 'tipo' => 'num', 'total' => true],
            ['campo' => 'facturas', 'label' => 'Factura'],
            ['campo' => 'acciones', 'label' => 'Acciones', 'tipo' => 'acciones'],
        ];
    }

    protected function consultar(array $f): array
    {
        $parcial = (int) SysconfigHelper::get('facturapracial', 0) === 1;
        $verTodos = (int) SysconfigHelper::get('factura_vertodos', 0);
        $hayFiltros = false;
        $hayCliente = $f['fk_cliente_id'] !== '';

        $q = DB::table('reserva')
            ->join('cliente', DB::raw('IF(reserva.facturar_a != 0, reserva.facturar_a, reserva.fk_cliente_id)'), '=', 'cliente.cliente_id')
            ->leftJoin('usuario', 'usuario.usuario_id', '=', 'reserva.agente')
            ->leftJoin('servicio', 'servicio.fk_reserva_id', '=', 'reserva.reserva_id')
            ->leftJoin('pnraereo', 'pnraereo.fk_ocupacion_id', '=', 'servicio.servicio_id')
            ->whereIn('servicio.status', ['CO', 'RQ', 'CL', 'DE', 'DV'])
            ->where('reserva.titular_nombre', '<>', 'FXC MIGRADO')
            ->where(fn ($w) => $w->where('reserva.reserva_mayorista', 0)->orWhere(fn ($w2) => $w2->where('reserva.reserva_mayorista', 1)->whereIn('reserva.tipocodigo', ['INT', 'GRP', 'GRV', 'MIC'])))
            ->select('reserva.reserva_id', 'reserva.total', 'reserva.cobrado', 'reserva.renta', 'reserva.codigo', 'reserva.tipocodigo', 'reserva.fk_filestatus_id', 'reserva.fecha_alta', 'reserva.fecha_vencimiento',
                'cliente.cliente_nombre', DB::raw('reserva.fk_moneda_id AS moneda'), DB::raw("CONCAT(reserva.titular_nombre, ' ', reserva.titular_apellido) AS titular"),
                DB::raw("CONCAT(COALESCE(usuario.usuario_nombre, ''), ' ', COALESCE(usuario.usuario_apellido, '')) AS nagente"),
                DB::raw('(SELECT c2.cliente_nombre FROM cliente c2 WHERE c2.cliente_id = reserva.fk_cliente_id) AS cliente_reserva'),
                DB::raw("(SELECT MIN(s2.vigencia_ini) FROM servicio s2 WHERE s2.status IN ('CO','RQ','CL','DE','DV') AND s2.fk_reserva_id = reserva.reserva_id) AS fecha_in"))
            ->groupBy('reserva.reserva_id')
            ->orderByDesc('reserva.tipocodigo')->orderByDesc(DB::raw('CAST(reserva.codigo AS UNSIGNED)'));

        $parcial
            ? $q->where(fn ($w) => $w->where('reserva.fk_filestatus_id', 'CL')->orWhere('servicio.status', 'CL'))
            : $q->where('reserva.fk_filestatus_id', 'CL');
        if (Licencia::esFamiliaSecontur()) {
            $q->where('reserva.observaciones', '<>', 'MIGRADO PITAGORAS')->where('reserva.fecha_alta', '>', '2017-08-06');
        }
        if ($hayCliente) {
            $q->whereRaw('IF(reserva.facturar_a != 0, reserva.facturar_a, reserva.fk_cliente_id) = ?', [(int) $f['fk_cliente_id']]);
        }
        if ($f['filestatus'] !== '') {
            $q->where('reserva.fk_filestatus_id', $f['filestatus']);
            $hayFiltros = true;
        }
        if ($f['codigo'] !== '') {
            $q->where(function ($w) use ($f) {
                foreach (explode('*', $f['codigo']) as $c) {
                    $w->orWhere('reserva.codigo', 'LIKE', '%'.trim($c));
                }
            });
            $hayFiltros = true;
        }
        if ($f['tipocodigo'] !== '') {
            $q->where('reserva.tipocodigo', $f['tipocodigo']);
        }
        if ($f['reportesaldo'] !== '') {
            match ((int) $f['reportesaldo']) {
                1 => $q->whereColumn('reserva.total', '>', 'reserva.cobrado'),
                2 => $q->whereColumn('reserva.total', '<', 'reserva.cobrado'),
                3 => $f['precision'] !== '' ? $q->whereRaw('ABS(reserva.total - reserva.cobrado) <= ?', [abs((float) $f['precision'])]) : $q->whereColumn('reserva.total', '=', 'reserva.cobrado'),
                default => null,
            };
        }
        if ($f['titular'] !== '') {
            $q->where('reserva.titular_apellido', 'LIKE', '%'.$f['titular']);
            $hayFiltros = true;
        }
        if ($f['fk_sistema_id'] !== '') {
            $s = (int) $f['fk_sistema_id'];
            $q->where(fn ($w) => $w->where(fn ($w2) => $w2->where('reserva.fk_sistema_id', $s)->where('reserva.fk_sistemaaplicacion_id', 0))->orWhere('reserva.fk_sistemaaplicacion_id', $s));
            $hayFiltros = true;
        }
        foreach (['fecha_alta' => 'reserva.fecha_alta', 'fecha_emision' => 'pnraereo.pnraereo_fechaemision'] as $campo => $col) {
            if ($f[$campo] !== '' || $f["{$campo}_to"] !== '') {
                $this->rango($q, $col, $f, $campo);
                $hayFiltros = true;
            }
        }
        $subIn = "(SELECT MIN(s3.vigencia_ini) FROM servicio s3 WHERE s3.status IN ('CO','RQ','CL','DE','DV') AND s3.fk_reserva_id = reserva.reserva_id)";
        if ($f['vigencia_ini'] !== '') {
            $q->whereRaw("{$subIn} >= ?", [$f['vigencia_ini']]);
            $hayFiltros = true;
        }
        if ($f['vigencia_ini_to'] !== '') {
            $q->whereRaw("{$subIn} <= ?", [$f['vigencia_ini_to']]);
            $hayFiltros = true;
        }
        if ($f['fk_usuario_id'] !== '') {
            $q->where('reserva.agente', (int) $f['fk_usuario_id']);
            $hayFiltros = true;
        }
        if ($f['reporteventa'] === '1') {
            $q->where('reserva.total', '<>', 0);
            $hayFiltros = true;
        }
        if (Licencia::es('witwan_rays', 'mundotour_sdg')) {
            $q->where('cliente.facturacion_periodo', $this->porPeriodo ? 1 : 0);
        }
        if ($this->porPeriodo && ! $hayCliente) {
            return [];
        }
        $facturado = $f['facturado'];
        if (! $hayFiltros) {
            if ($this->porPeriodo && $hayCliente) {
                $q->whereNotIn('servicio.servicio_id', fn ($s) => $s->selectRaw('DISTINCT fk_servicio_id')->from('rel_serviciofactura'));
            } elseif (Licencia::esFamiliaSecontur() || $verTodos === 1) {
                $facturado = '0';
                $parcial
                    ? $q->whereNotIn('servicio.servicio_id', fn ($s) => $s->selectRaw('DISTINCT fk_servicio_id')->from('rel_serviciofactura'))
                    : $q->whereNotIn('reserva.reserva_id', fn ($s) => $s->selectRaw('DISTINCT fk_file_id')->from('factura')->where('statusfactura', '<>', 'AN'));
            } elseif ($verTodos === 2) {
                return [];
            } else {
                $q->whereBetween('reserva.fecha_vencimiento', [now()->toDateString(), now()->addDays(7)->toDateString()]);
            }
        }

        $filas = [];
        foreach ($q->get() as $r) {
            $docs = DB::table('rel_serviciofactura as rsf')->join('servicio as s', 's.servicio_id', '=', 'rsf.fk_servicio_id')->join('factura as fc', 'fc.factura_id', '=', 'rsf.fk_factura_id')
                ->where('rsf.tipodocumento', 1)->where('s.fk_reserva_id', $r->reserva_id)->groupBy('rsf.fk_factura_id')->get(['fc.factura_id', 'fc.factura_nro'])
                ->map(fn ($d) => ['label' => (string) $d->factura_nro, 'href' => "/administracion/factura/imprimir/{$d->factura_id}"])->all();
            if ($docs === []) {
                $docs = DB::table('rel_serviciofactura as rsf')->join('servicio as s', 's.servicio_id', '=', 'rsf.fk_servicio_id')->join('notacredito as nc', 'nc.notacredito_id', '=', 'rsf.fk_factura_id')
                    ->where('rsf.tipodocumento', 2)->where('s.fk_reserva_id', $r->reserva_id)->groupBy('rsf.fk_factura_id')->get(['nc.notacredito_id', 'nc.notacredito_nro'])
                    ->map(fn ($d) => ['label' => 'NC'.$d->notacredito_nro, 'href' => "/administracion/notacredito/imprimir/{$d->notacredito_id}"])->all();
            }
            $pendientes = DB::table('servicio')->where('status', 'CL')->where('fk_reserva_id', $r->reserva_id)
                ->whereNotIn('servicio_id', fn ($s) => $s->selectRaw('DISTINCT fk_servicio_id')->from('rel_serviciofactura'))->count();
            $habilita = $docs === [] || $pendientes > 0;

            if ($facturado !== '') {
                $tieneDocs = $parcial ? $pendientes === 0 : $docs !== [];
                if (($facturado === '0' && $tieneDocs) || ($facturado === '1' && ! $tieneDocs)) {
                    continue;
                }
            }
            $acciones = $docs;
            if ($habilita) {
                $acciones[] = ['label' => 'FACTURAR', 'href' => "/administracion/factura/facturar/{$r->reserva_id}"];
            }
            $filas[] = [
                'tipo' => $r->tipocodigo,
                'codigo' => "{$r->tipocodigo}-{$r->codigo}",
                'file_link' => '/app/reservas/all?codigo='.$r->codigo,
                'id' => (int) $r->reserva_id,
                'status' => $r->fk_filestatus_id,
                'titular' => trim((string) $r->titular),
                'cliente_reserva' => (string) $r->cliente_reserva,
                'facturar_a' => $r->cliente_nombre,
                'agente' => trim((string) $r->nagente),
                'fecha_alta' => $this->dmy((string) $r->fecha_alta),
                'fecha_in' => $this->dmy((string) $r->fecha_in),
                'moneda' => $r->moneda,
                'venta' => (float) $r->total,
                'saldo' => round((float) $r->total - (float) $r->cobrado, 2),
                'renta' => (float) $r->renta,
                'facturas' => implode(' ', array_column($docs, 'label')),
                'acciones' => $acciones,
            ];
        }

        return $filas;
    }
}
