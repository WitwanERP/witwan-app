<?php

namespace App\Http\Controllers\Web\Documentos\Listados;

use App\Support\Licencia;
use Illuminate\Support\Facades\DB;

/** Administración > Documentos > Recibos (CI: administracion/recibo, listado). */
class RecibosListadoController extends DocumentoListadoController
{
    protected string $titulo = 'Recibos';

    protected string $ruta = 'documentos/recibos';

    protected function filtros(): array
    {
        return [
            ['campo' => 'recibo_nro', 'label' => 'Número', 'tipo' => 'text'],
            ['campo' => 'codigo', 'label' => 'Código reserva', 'tipo' => 'text'],
            ['campo' => 'fecha', 'label' => 'Fecha', 'tipo' => 'rango'],
            ['campo' => 'recibo_tipo', 'label' => 'Tipo', 'tipo' => 'text'],
            ['campo' => 'statusrecibo', 'label' => 'Estado', 'tipo' => 'text'],
            ['campo' => 'fk_cliente_id', 'label' => 'Cliente', 'tipo' => 'select', 'opciones' => $this->catalogos->clientes()],
            ['campo' => 'fk_moneda_id', 'label' => 'Moneda', 'tipo' => 'select', 'opciones' => $this->catalogos->monedas()],
        ];
    }

    protected function columnas(): array
    {
        $cols = [
            ['campo' => 'numero', 'label' => 'Número'],
            ['campo' => 'reserva', 'label' => 'Reserva'],
            ['campo' => 'fecha', 'label' => 'Fecha'],
            ['campo' => 'tipo', 'label' => 'Tipo'],
            ['campo' => 'estado', 'label' => 'Estado'],
            ['campo' => 'cliente', 'label' => 'Cliente'],
            ['campo' => 'moneda', 'label' => 'Moneda'],
            ['campo' => 'monto', 'label' => 'Monto', 'tipo' => 'num', 'total' => true],
            ['campo' => 'tipocambio', 'label' => 'Tipo cambio', 'tipo' => 'num'],
        ];
        if (Licencia::es('mundotour_sdg')) {
            $cols[] = ['campo' => 'aplicado', 'label' => 'Monto aplicado', 'tipo' => 'num'];
            $cols[] = ['campo' => 'facturas', 'label' => 'Facturas aplicadas'];
        }

        return array_merge($cols, [
            ['campo' => 'creadopor', 'label' => 'Creado por'],
            ['campo' => 'observaciones', 'label' => 'Obs.', 'tipo' => 'pre'],
            ['campo' => 'acciones', 'label' => 'Acciones', 'tipo' => 'acciones'],
        ]);
    }

    protected function accionesGlobales(): array
    {
        return [['label' => 'Nuevo recibo (sistema anterior)', 'href' => '/administracion/recibo/create', 'target' => '_blank']];
    }

    protected function consultar(array $f): array
    {
        $f = $this->rangoPorDefecto($f, 'fecha');

        $q = DB::table('recibo as rc')
            ->join('cliente as c', 'c.cliente_id', '=', 'rc.fk_cliente_id')
            ->join('rel_filerecibo as rfr', 'rfr.fk_recibo_id', '=', 'rc.recibo_id')
            ->join('reserva as r', 'r.reserva_id', '=', 'rfr.fk_file_id')
            ->leftJoin('usuario as u', 'u.usuario_id', '=', 'rc.fk_usuario_id')
            ->groupBy('rc.recibo_id')
            ->orderByDesc('rc.fecha')
            ->select('rc.recibo_id', 'rc.recibo_nro', 'rc.fecha', 'rc.recibo_tipo', 'rc.statusrecibo', 'rc.fk_moneda_id', 'rc.monto', 'rc.observaciones', 'c.cliente_nombre', 'c.cuit',
                DB::raw('MAX(r.tipocodigo) AS tipocodigo'), DB::raw('MAX(r.codigo) AS codigo'),
                DB::raw("CONCAT(u.usuario_apellido, ', ', u.usuario_nombre) AS creadopor"),
                DB::raw('(SELECT m.cotizacion_moneda FROM movimiento m WHERE m.fk_recibo_id = rc.recibo_id LIMIT 1) AS tipocambio'),
                DB::raw('(SELECT SUM(x.monto) FROM rel_facturarecibo x WHERE x.fk_recibo_id = rc.recibo_id) AS aplicado'),
                DB::raw('(SELECT GROUP_CONCAT(fc.factura_nro) FROM factura fc JOIN rel_facturarecibo y ON fc.factura_id = y.fk_factura_id WHERE y.fk_recibo_id = rc.recibo_id) AS facturasn'));

        foreach (['recibo_tipo', 'statusrecibo', 'fk_moneda_id'] as $campo) {
            if ($f[$campo] !== '') {
                $q->where("rc.{$campo}", $f[$campo]);
            }
        }
        if ($f['recibo_nro'] !== '') {
            $q->where('rc.recibo_nro', 'LIKE', "%{$f['recibo_nro']}%");
        }
        if ($f['codigo'] !== '') {
            $q->where('r.codigo', $f['codigo']);
        }
        if ($f['fk_cliente_id'] !== '') {
            $q->where('rc.fk_cliente_id', (int) $f['fk_cliente_id']);
        }
        $this->rango($q, 'rc.fecha', $f, 'fecha');
        $this->limitar($q);

        return $q->get()->map(fn ($x) => [
            'numero' => $x->recibo_nro,
            'reserva' => $x->codigo ? "{$x->tipocodigo}-{$x->codigo}" : '',
            'fecha' => $this->dmy((string) $x->fecha),
            'tipo' => $x->recibo_tipo,
            'estado' => $x->statusrecibo,
            'cliente' => $x->cliente_nombre,
            'moneda' => $x->fk_moneda_id,
            'monto' => (float) $x->monto,
            'tipocambio' => (float) $x->tipocambio,
            'aplicado' => (float) $x->aplicado,
            'facturas' => (string) $x->facturasn,
            'creadopor' => trim((string) $x->creadopor, ', '),
            'observaciones' => (string) $x->observaciones,
            'acciones' => [
                $this->link('Imprimir', "/administracion/recibo/imprimir/{$x->recibo_id}"),
                $this->link('Editar', "/administracion/recibo/edit/{$x->recibo_id}"),
                $this->link('Anular', "/administracion/recibo/anular/{$x->recibo_id}", true),
            ],
        ])->all();
    }
}
