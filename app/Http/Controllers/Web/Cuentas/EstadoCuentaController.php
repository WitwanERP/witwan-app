<?php

namespace App\Http\Controllers\Web\Cuentas;

use App\Helpers\PermisoHelper;
use App\Http\Controllers\Web\Reportes\ReporteController;
use App\Services\CatalogosService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Administración > Cuentas > Estado de cuenta (CI: administracion/cuentas/micuenta).
 * Files del cliente del usuario (usuarios CLI/CLM o con "sólo files del cliente
 * asignado") con total, cobrado, saldo, facturas/NC/ND, recibos y files hijos
 * (DV/CM). Los usuarios internos eligen el cliente (el CI mostraba siempre el
 * del usuario, que para internos era 0 y no listaba nada).
 */
class EstadoCuentaController extends ReporteController
{
    protected string $titulo = 'Estado de cuenta';

    protected string $ruta = 'cuentas/estado';

    protected string $grupo = 'Cuentas';

    protected ?string $agruparPor = 'moneda';

    protected int $clienteForzado = 0;

    public function __construct(protected CatalogosService $catalogos) {}

    protected function preparar(Request $request): void
    {
        $u = Auth::user();
        if ($u && (in_array((string) $u->fk_tipousuario_id, ['CLI', 'CLM'], true) || ((string) $u->fk_tipousuario_id !== 'POW' && PermisoHelper::tienePermiso(255, 'files_cliente_asignado')))) {
            $this->clienteForzado = (int) $u->fk_cliente_id;
            $this->requiereFiltros = false;
        }
    }

    protected function filtros(): array
    {
        $f = $this->clienteForzado ? [] : [['campo' => 'cliente', 'label' => 'Cliente', 'tipo' => 'select', 'opciones' => $this->catalogos->clientes()]];

        return array_merge($f, [
            ['campo' => 'codigo', 'label' => 'Código', 'tipo' => 'text'],
            ['campo' => 'titular', 'label' => 'Titular / Pax', 'tipo' => 'text'],
            ['campo' => 'fecha_alta', 'label' => 'Fecha alta', 'tipo' => 'rango'],
            ['campo' => 'vigencia_ini', 'label' => 'Fecha in', 'tipo' => 'rango'],
            ['campo' => 'fecha_emision', 'label' => 'Fecha emisión (aéreos)', 'tipo' => 'rango'],
            ['campo' => 'reportesaldo', 'label' => 'Saldo', 'tipo' => 'select', 'opciones' => [['value' => '1', 'label' => 'Deudor'], ['value' => '2', 'label' => 'A favor'], ['value' => '3', 'label' => 'Distinto de 0']]],
        ]);
    }

    protected function columnas(): array
    {
        return [
            ['campo' => 'codigo', 'label' => 'File', 'link' => 'file_link'],
            ['campo' => 'status', 'label' => 'Status'],
            ['campo' => 'titular', 'label' => 'Titular'],
            ['campo' => 'pasajeros', 'label' => 'Pasajeros', 'tipo' => 'pre'],
            ['campo' => 'fecha_alta', 'label' => 'Alta'],
            ['campo' => 'in', 'label' => 'In'],
            ['campo' => 'out', 'label' => 'Out'],
            ['campo' => 'adultos', 'label' => 'Pax'],
            ['campo' => 'agente', 'label' => 'Agente'],
            ['campo' => 'total', 'label' => 'Total', 'tipo' => 'num', 'total' => true],
            ['campo' => 'cobrado', 'label' => 'Cobrado', 'tipo' => 'num', 'total' => true],
            ['campo' => 'saldo', 'label' => 'Saldo', 'tipo' => 'num', 'total' => true],
            ['campo' => 'facturas', 'label' => 'Facturas', 'tipo' => 'pre'],
            ['campo' => 'recibos', 'label' => 'Recibos', 'tipo' => 'pre'],
            ['campo' => 'hijos', 'label' => 'Files hijos'],
        ];
    }

    protected function consultar(array $f): array
    {
        $cliente = $this->clienteForzado ?: (int) ($f['cliente'] ?? 0);
        if ($cliente === 0) {
            return [];
        }
        $q = DB::table('reserva')
            ->join('cliente', 'cliente.cliente_id', '=', 'reserva.fk_cliente_id')
            ->join('servicio', 'servicio.fk_reserva_id', '=', 'reserva.reserva_id')
            ->leftJoin('pnraereo', 'pnraereo.fk_ocupacion_id', '=', 'servicio.servicio_id')
            ->leftJoin('usuario', 'usuario.usuario_id', '=', 'reserva.agente')
            ->whereNotIn('reserva.fk_filestatus_id', ['CT', 'AG'])
            ->where('cliente.cliente_id', $cliente)
            ->groupBy('reserva.reserva_id')
            ->select('reserva.reserva_id', 'reserva.tipocodigo', 'reserva.codigo', 'reserva.fk_filestatus_id', 'reserva.titular_nombre', 'reserva.titular_apellido', 'reserva.fecha_alta',
                'reserva.fk_moneda_id', 'reserva.total', 'reserva.cobrado', 'cliente.cliente_nombre',
                DB::raw('MAX(servicio.adultos) AS adultos'), DB::raw('MIN(servicio.vigencia_ini) AS vini'), DB::raw('MAX(servicio.vigencia_ini) AS vfin'),
                DB::raw("CONCAT(COALESCE(usuario.usuario_nombre, ''), ' ', COALESCE(usuario.usuario_apellido, '')) AS agente"))
            ->orderByDesc(DB::raw('CAST(reserva.codigo AS UNSIGNED)'));

        $subIn = "(SELECT MIN(oo.vigencia_ini) FROM servicio oo WHERE oo.status <> 'CA' AND oo.fk_reserva_id = reserva.reserva_id)";
        if ($f['vigencia_ini'] !== '') {
            $q->whereRaw("{$subIn} >= ?", [$f['vigencia_ini']]);
        }
        if ($f['vigencia_ini_to'] !== '') {
            $q->whereRaw("{$subIn} <= ?", [$f['vigencia_ini_to']]);
        }
        if ($f['codigo'] !== '') {
            $q->where('reserva.codigo', 'LIKE', trim($f['codigo']));
        }
        if ($f['titular'] !== '') {
            $q->where(fn ($w) => $w->where('reserva.titular_nombre', 'LIKE', "%{$f['titular']}%")->orWhere('reserva.titular_apellido', 'LIKE', "%{$f['titular']}%"));
        }
        $this->rango($q, 'reserva.fecha_alta', $f, 'fecha_alta');
        $this->rango($q, 'pnraereo.pnraereo_fechaemision', $f, 'fecha_emision');
        match ($f['reportesaldo']) {
            '1' => $q->whereRaw('(reserva.total - reserva.cobrado) > 0'),
            '2' => $q->whereRaw('(reserva.total - reserva.cobrado) < 0'),
            '3' => $q->whereRaw('(reserva.total - reserva.cobrado) <> 0'),
            default => null,
        };

        return $q->get()->map(function ($r) {
            $id = (int) $r->reserva_id;
            $pax = DB::table('servicio_nomina as n')->join('servicio as s', 's.servicio_id', '=', 'n.fk_servicio_id')->where('s.fk_reserva_id', $id)
                ->get(['n.nombre', 'n.apellido', 'n.documento'])->map(fn ($p) => trim("{$p->nombre} {$p->apellido}"))->filter()->unique()->implode("\n");
            $docs = [];
            foreach (DB::table('factura')->where('factura_tipo', '<>', 'X')->where('fk_file_id', $id)->get(['factura_id', 'factura_tipo', 'factura_nro']) as $fc) {
                $docs[] = "{$fc->factura_tipo} {$fc->factura_nro}";
                foreach (DB::table('notacredito')->where('fk_factura_id', (int) $fc->factura_id)->get(['notacredito_id', 'notacredito_tipo', 'notacredito_nro']) as $nc) {
                    $docs[] = "NC {$nc->notacredito_tipo} {$nc->notacredito_nro}";
                    foreach (DB::table('notadebito')->where('fk_notacredito_id', (int) $nc->notacredito_id)->get(['notadebito_tipo', 'notadebito_nro']) as $nd) {
                        $docs[] = "ND {$nd->notadebito_tipo} {$nd->notadebito_nro}";
                    }
                }
            }
            $recibos = DB::table('recibo')->join('rel_filerecibo', 'rel_filerecibo.fk_recibo_id', '=', 'recibo.recibo_id')
                ->where('recibo.statusrecibo', '<>', 'AN')->where('rel_filerecibo.fk_file_id', $id)->pluck('recibo.recibo_nro')->implode("\n");
            $hijos = DB::table('reserva')->where('fk_filepadre_id', $id)->whereIn('fk_filestatus_id', ['DV', 'CM'])->selectRaw("CONCAT(tipocodigo, '-', codigo) AS c")->pluck('c')->implode(', ');

            return [
                'codigo' => "{$r->tipocodigo}-{$r->codigo}",
                'file_link' => '/app/reservas/all?codigo='.$r->codigo,
                'status' => $r->fk_filestatus_id,
                'titular' => trim("{$r->titular_apellido} {$r->titular_nombre}"),
                'pasajeros' => $pax,
                'fecha_alta' => $this->dmy((string) $r->fecha_alta),
                'in' => $this->dmy((string) $r->vini),
                'out' => $this->dmy((string) $r->vfin),
                'adultos' => (int) $r->adultos,
                'agente' => trim((string) $r->agente),
                'moneda' => $r->fk_moneda_id,
                'total' => (float) $r->total,
                'cobrado' => (float) $r->cobrado,
                'saldo' => round((float) $r->total - (float) $r->cobrado, 2),
                'facturas' => implode("\n", $docs),
                'recibos' => $recibos,
                'hijos' => $hijos,
            ];
        })->all();
    }
}
