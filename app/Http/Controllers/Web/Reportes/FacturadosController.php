<?php

namespace App\Http\Controllers\Web\Reportes;

use App\Helpers\SysconfigHelper;
use App\Services\CatalogosService;
use App\Services\CotizacionService;
use Illuminate\Support\Facades\DB;

/**
 * Administración > Reportes > Facturados (CI: dashboard/facturadosmt, que
 * volcaba toda la tabla a un CSV para contabilidad externa).
 *
 * Una fila por servicio facturado (FC o NC vía rel_serviciofactura) con el
 * estado de cobro de la factura (P pendiente / A parcial / C cobrada, según
 * los recibos aplicados), la renta y los códigos SII de tipo de documento
 * (33/34/61). Diferencias: el IVA usa la tasa general del tenant (el legacy
 * tenía 19 % fijo) y la moneda básica reemplaza al "CLP" fijo; en pantalla se
 * corta en $limite y sin filtros se muestran los últimos 3 meses de factura.
 */
class FacturadosController extends ReporteController
{
    protected string $titulo = 'Facturados';

    protected string $ruta = 'admin/reportes/facturados';

    protected bool $requiereFiltros = false;

    protected int $limite = 500;

    public function __construct(protected CatalogosService $catalogos, protected CotizacionService $cotizaciones) {}

    protected function filtros(): array
    {
        return [
            ['campo' => 'factura_fecha', 'label' => 'Fecha de factura', 'tipo' => 'rango'],
            ['campo' => 'fk_cliente_id', 'label' => 'Cliente', 'tipo' => 'select', 'opciones' => $this->catalogos->clientes()],
            ['campo' => 'codigo', 'label' => 'File (código)', 'tipo' => 'text'],
            ['campo' => 'estado', 'label' => 'Estado de cobro', 'tipo' => 'select', 'opciones' => [['value' => 'P', 'label' => 'Pendiente'], ['value' => 'A', 'label' => 'Parcial'], ['value' => 'C', 'label' => 'Cobrada']]],
        ];
    }

    protected function columnas(): array
    {
        return [
            ['campo' => 'tipo_doc', 'label' => 'Tipo Doc'],
            ['campo' => 'nro', 'label' => 'Nro Factura'],
            ['campo' => 'fecha', 'label' => 'Fecha Factura'],
            ['campo' => 'estado', 'label' => 'Status Factura'],
            ['campo' => 'fecha_cobro', 'label' => 'Fecha cobro'],
            ['campo' => 'codigo', 'label' => 'Código', 'link' => 'file_link'],
            ['campo' => 'subtipo', 'label' => 'Subtipo'],
            ['campo' => 'vendedor', 'label' => 'Vendedor'],
            ['campo' => 'cliente', 'label' => 'Cliente'],
            ['campo' => 'rut_cliente', 'label' => 'RUT Cliente'],
            ['campo' => 'razon_social', 'label' => 'Razón social'],
            ['campo' => 'exento', 'label' => 'Exento Factura', 'tipo' => 'num', 'total' => true],
            ['campo' => 'afecto', 'label' => 'Afecto Factura', 'tipo' => 'num', 'total' => true],
            ['campo' => 'iva', 'label' => 'IVA Factura', 'tipo' => 'num', 'total' => true],
            ['campo' => 'total_factura', 'label' => 'Total Factura', 'tipo' => 'num', 'total' => true],
            ['campo' => 'status_servicio', 'label' => 'Status servicio'],
            ['campo' => 'tipo_servicio', 'label' => 'Tipo Servicio'],
            ['campo' => 'nombre_tipo', 'label' => 'Nombre Tipo Servicio'],
            ['campo' => 'servicio_id', 'label' => 'ID Servicio'],
            ['campo' => 'cuenta', 'label' => 'Cuenta Provisión'],
            ['campo' => 'cuenta_nombre', 'label' => 'Nombre Cuenta Provisión'],
            ['campo' => 'fecha_emision', 'label' => 'Fecha de emisión'],
            ['campo' => 'servicio', 'label' => 'Servicio'],
            ['campo' => 'ultima_mod', 'label' => 'Últ. mod. línea'],
            ['campo' => 'creado_por', 'label' => 'Creado por'],
            ['campo' => 'alta_file', 'label' => 'Alta File'],
            ['campo' => 'proveedor', 'label' => 'Proveedor'],
            ['campo' => 'proveedor_rut', 'label' => 'RUT Proveedor'],
            ['campo' => 'iva_costo', 'label' => 'IVA Costo', 'tipo' => 'num'],
            ['campo' => 'tc_costo', 'label' => 'Tipo cambio Costo', 'tipo' => 'num'],
            ['campo' => 'moneda_servicio', 'label' => 'Moneda Servicio'],
            ['campo' => 'total_servicio', 'label' => 'Total Servicio', 'tipo' => 'num'],
            ['campo' => 'tipo_cambio', 'label' => 'Tipo de Cambio', 'tipo' => 'num'],
            ['campo' => 'venta', 'label' => 'Venta', 'tipo' => 'num', 'total' => true],
            ['campo' => 'costo', 'label' => 'Costo', 'tipo' => 'num', 'total' => true],
            ['campo' => 'renta', 'label' => 'Renta', 'tipo' => 'num', 'total' => true],
            ['campo' => 'confirmacion', 'label' => 'Confirmación'],
            ['campo' => 'negocio', 'label' => 'Negocio'],
        ];
    }

    protected function consultar(array $f): array
    {
        if ($f['factura_fecha'] === '' && $f['factura_fecha_to'] === '' && $f['fk_cliente_id'] === '' && $f['codigo'] === '') {
            $f['factura_fecha'] = now()->subMonths(3)->toDateString();
        }
        $tasa = (float) SysconfigHelper::get('tasageneral', 19);
        $iva = $tasa < 1 ? $tasa : $tasa / 100;
        $basica = $this->cotizaciones->monedaBasica();

        $q = DB::table('servicio as s')
            ->join('reserva as r', 'r.reserva_id', '=', 's.fk_reserva_id')
            ->join('cliente as c', 'c.cliente_id', '=', 'r.fk_cliente_id')
            ->join('usuario as u', 'u.usuario_id', '=', 'r.fk_usuario_id')
            ->join('rel_serviciofactura as rsf', 'rsf.fk_servicio_id', '=', 's.servicio_id')
            ->leftJoin('usuario as v', 'v.usuario_id', '=', 'r.agente')
            ->leftJoin('factura as fc', fn ($j) => $j->on('rsf.fk_factura_id', '=', 'fc.factura_id')->where('rsf.tipodocumento', 1))
            ->leftJoin('notacredito as nc', fn ($j) => $j->on('rsf.fk_factura_id', '=', 'nc.notacredito_id')->where('rsf.tipodocumento', 2))
            ->leftJoin('proveedor as p', 'p.proveedor_id', '=', 's.fk_proveedor_id')
            ->leftJoin('submodulo as sm', 'sm.submodulo_id', '=', 's.fk_tipoproducto_id')
            ->leftJoin('plancuenta as pc', 'pc.plancuenta_id', '=', 'sm.fk_plancuenta_id')
            ->leftJoin('pnraereo as pnr', 'pnr.fk_ocupacion_id', '=', 's.servicio_id')
            ->leftJoin('negocio as n', 'n.negocio_id', '=', 'r.fk_negocio_id')
            ->where('r.fk_filestatus_id', '<>', 'CA')->where('s.status', '<>', 'CA')
            ->where(fn ($w) => $w->whereNull('fc.statusfactura')->orWhere('fc.statusfactura', '<>', 'MI'))
            ->where(fn ($w) => $w->whereNull('nc.statusfactura')->orWhere('nc.statusfactura', '<>', 'MI'))
            ->groupBy('s.servicio_id')
            ->select('r.reserva_id', 'r.tipocodigo', 'r.codigo', 's.fk_tipoproducto_id', 's.servicio_id', 's.servicio_nombre', 's.fk_moneda_id', 's.total', 's.moneda_costo', 's.cotventa', 's.cotcosto',
                's.iva as siva', 's.costo', 's.iva_costo', 's.impuestos', 's.regdate', 's.nro_confirmacion', 's.status', 'r.fecha_alta', 'c.cliente_nombre', 'c.cuit', 'c.cliente_razonsocial',
                'p.proveedor_nombre', 'p.cuit as proveedor_rut', 'sm.tipoproducto_nombre', 'pc.plancuenta_codigo', 'pc.plancuenta_nombre', 'pnr.pnraereo_fechaemision', 'n.negocio_nombre',
                DB::raw("CONCAT(COALESCE(v.usuario_nombre, ''), ' ', COALESCE(v.usuario_apellido, '')) AS vendedor"),
                DB::raw("CONCAT(u.usuario_nombre, ' ', u.usuario_apellido) AS creadopor"),
                DB::raw("IF(ISNULL(fc.factura_nro), 'NC', 'FC') AS tipodocumento"),
                DB::raw('IF(ISNULL(fc.factura_id), nc.notacredito_id, fc.factura_id) AS factura_id'),
                DB::raw('IF(ISNULL(fc.factura_nro), nc.notacredito_nro, fc.factura_nro) AS factura_nro'),
                DB::raw('IF(ISNULL(fc.factura_fecha), nc.notacredito_fecha, fc.factura_fecha) AS factura_fecha'),
                DB::raw('IF(ISNULL(fc.factura_tipo), nc.notacredito_tipo, fc.factura_tipo) AS factura_tipo'),
                DB::raw('IF(ISNULL(fc.factura_conceptos_exentos), nc.notacredito_conceptos_exentos, fc.factura_conceptos_exentos) AS exentos'),
                DB::raw('IF(ISNULL(fc.factura_conceptos_gravados), nc.notacredito_conceptos_gravados, fc.factura_conceptos_gravados) AS gravados'))
            ->orderByDesc(DB::raw('IF(ISNULL(fc.factura_fecha), nc.notacredito_fecha, fc.factura_fecha)'))->orderBy('s.servicio_id');

        if ($f['fk_cliente_id'] !== '') {
            $q->where('r.fk_cliente_id', (int) $f['fk_cliente_id']);
        }
        if ($f['codigo'] !== '') {
            $q->where('r.codigo', $f['codigo']);
        }
        if ($f['factura_fecha'] !== '') {
            $q->whereRaw('IF(ISNULL(fc.factura_fecha), nc.notacredito_fecha, fc.factura_fecha) >= ?', [$f['factura_fecha'].' 00:00:00']);
        }
        if ($f['factura_fecha_to'] !== '') {
            $q->whereRaw('IF(ISNULL(fc.factura_fecha), nc.notacredito_fecha, fc.factura_fecha) <= ?', [$f['factura_fecha_to'].' 23:59:59']);
        }
        if ($f['estado'] === '') {
            $this->limitar($q);
        }

        $filas = [];
        foreach ($q->get() as $x) {
            $totalFactura = (float) $x->exentos + round((float) $x->gravados * (1 + $iva));
            $abonado = 0.0;
            $fechaPago = now()->format('d/m/Y');
            if ($x->tipodocumento === 'FC') {
                $recibos = DB::table('rel_facturarecibo as rf')->leftJoin('recibo as rc', 'rc.recibo_id', '=', 'rf.fk_recibo_id')
                    ->where('rf.fk_factura_id', (int) $x->factura_id)->orderBy('rc.fecha')->get(['rf.monto', 'rc.fecha']);
                foreach ($recibos as $rc) {
                    $fechaPago = $this->dmy((string) $rc->fecha);
                    $abonado += (float) $rc->monto;
                }
            }
            $estado = 'P';
            $fechaCobro = '';
            if ($abonado > 0 && $totalFactura > $abonado) {
                $estado = 'A';
            } elseif ($totalFactura <= $abonado) {
                $estado = 'C';
                $fechaCobro = $fechaPago;
            }
            if ($f['estado'] !== '' && $estado !== $f['estado']) {
                continue;
            }

            $c1 = (float) $x->cotcosto ?: $this->cotizaciones->alCosto((string) $x->moneda_costo, substr((string) $x->fecha_alta, 0, 10));
            $c2 = (float) $x->cotventa ?: $this->cotizaciones->aLaVenta((string) $x->fk_moneda_id, substr((string) $x->fecha_alta, 0, 10));
            if ($x->moneda_costo === $basica) {
                $c1 = 1.0;
            }
            if ($x->fk_moneda_id === $basica) {
                $c2 = 1.0;
            }
            $venta = (float) $x->total * $c2;
            $costo = ((float) $x->costo + (float) $x->iva_costo) * $c1;
            $renta = $venta - (float) $x->siva * $c2 - $costo;
            if ($x->fk_tipoproducto_id !== 'AER') {
                $renta -= (float) $x->impuestos * $c1;
            }

            $filas[] = [
                'tipo_doc' => $x->tipodocumento === 'NC' ? 61 : ($x->factura_tipo === 'A' ? 34 : 33),
                'nro' => (string) $x->factura_nro,
                'fecha' => $this->dmy((string) $x->factura_fecha),
                'estado' => $estado,
                'fecha_cobro' => $fechaCobro,
                'codigo' => (string) $x->codigo,
                'file_link' => "/app/reservas/{$x->reserva_id}",
                'subtipo' => (string) $x->tipocodigo,
                'vendedor' => trim((string) $x->vendedor),
                'cliente' => $x->cliente_nombre,
                'rut_cliente' => (string) $x->cuit,
                'razon_social' => (string) $x->cliente_razonsocial,
                'exento' => (float) $x->exentos,
                'afecto' => (float) $x->gravados,
                'iva' => round((float) $x->gravados * $iva),
                'total_factura' => $totalFactura,
                'status_servicio' => $x->status,
                'tipo_servicio' => $x->fk_tipoproducto_id,
                'nombre_tipo' => (string) $x->tipoproducto_nombre,
                'servicio_id' => (int) $x->servicio_id,
                'cuenta' => (string) $x->plancuenta_codigo,
                'cuenta_nombre' => (string) $x->plancuenta_nombre,
                'fecha_emision' => $this->dmy((string) $x->pnraereo_fechaemision),
                'servicio' => $x->servicio_nombre,
                'ultima_mod' => (string) $x->regdate,
                'creado_por' => trim((string) $x->creadopor),
                'alta_file' => $this->dmy((string) $x->fecha_alta),
                'proveedor' => (string) $x->proveedor_nombre,
                'proveedor_rut' => (string) $x->proveedor_rut,
                'iva_costo' => (float) $x->iva_costo,
                'tc_costo' => (float) $x->cotcosto,
                'moneda_servicio' => $x->fk_moneda_id,
                'total_servicio' => (float) $x->total,
                'tipo_cambio' => $c2,
                'venta' => round($venta, 2),
                'costo' => round($costo, 2),
                'renta' => round($renta, 2),
                'confirmacion' => (string) $x->nro_confirmacion,
                'negocio' => (string) $x->negocio_nombre,
            ];
        }

        return $filas;
    }
}
