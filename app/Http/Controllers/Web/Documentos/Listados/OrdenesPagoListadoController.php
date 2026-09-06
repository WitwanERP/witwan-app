<?php

namespace App\Http\Controllers\Web\Documentos\Listados;

use Illuminate\Support\Facades\DB;

/** Administración > Caja > Órdenes de pago (CI: administracion/ordenpago, listado; ordenadmin.tipo = 'P'). */
class OrdenesPagoListadoController extends DocumentoListadoController
{
    protected string $titulo = 'Órdenes de pago';

    protected string $ruta = 'documentos/ordenes-pago';

    protected string $grupo = 'Caja';

    protected string $tipoOrden = 'P';

    protected function filtros(): array
    {
        return [
            ['campo' => 'numero', 'label' => 'Número', 'tipo' => 'text'],
            ['campo' => 'fecha', 'label' => 'Fecha', 'tipo' => 'rango'],
            ['campo' => 'fk_proveedor_id', 'label' => 'Proveedor', 'tipo' => 'select', 'opciones' => $this->catalogos->proveedores()],
            ['campo' => 'codigo', 'label' => 'File (código)', 'tipo' => 'text'],
            ['campo' => 'fk_usuario_id', 'label' => 'Usuario', 'tipo' => 'select', 'opciones' => $this->catalogos->usuariosInternos()],
            ['campo' => 'fk_moneda_id', 'label' => 'Moneda', 'tipo' => 'select', 'opciones' => $this->catalogos->monedas()],
            ['campo' => 'status', 'label' => 'Status', 'tipo' => 'select', 'opciones' => $this->estados()],
        ];
    }

    protected function estados(): array
    {
        return [['value' => 'AN', 'label' => 'Anulada'], ['value' => 'OK', 'label' => 'Ok'], ['value' => 'PR', 'label' => 'Procesada']];
    }

    protected function columnas(): array
    {
        return [
            ['campo' => 'numero', 'label' => 'Número'],
            ['campo' => 'fecha', 'label' => 'Fecha'],
            ['campo' => 'proveedor', 'label' => 'Proveedor'],
            ['campo' => 'file', 'label' => 'File'],
            ['campo' => 'usuario', 'label' => 'Usuario'],
            ['campo' => 'moneda', 'label' => 'Moneda'],
            ['campo' => 'monto', 'label' => 'Monto', 'tipo' => 'num', 'total' => true],
            ['campo' => 'relacionada', 'label' => 'OS relacionada'],
            ['campo' => 'cotizacion', 'label' => 'Tipo de cambio', 'tipo' => 'num'],
            ['campo' => 'status', 'label' => 'Status'],
            ['campo' => 'acciones', 'label' => 'Acciones', 'tipo' => 'acciones'],
        ];
    }

    protected function consultar(array $f): array
    {
        $f = $this->rangoPorDefecto($f, 'fecha');

        $q = DB::table('ordenadmin as oa')
            ->leftJoin('proveedor as p', 'p.proveedor_id', '=', 'oa.fk_proveedor_id')
            ->leftJoin('usuario as u', 'u.usuario_id', '=', 'oa.fk_usuario_id')
            ->leftJoin('rel_ordenadminocupacion as roa', 'roa.fk_ordenadmin_id', '=', 'oa.ordenadmin_id')
            ->leftJoin('servicio as s', 's.servicio_id', '=', 'roa.fk_ocupacion_id')
            ->leftJoin('reserva as r', 'r.reserva_id', '=', 's.fk_reserva_id')
            ->where('oa.tipo', $this->tipoOrden)
            ->groupBy('oa.ordenadmin_id')
            ->select('oa.ordenadmin_id', 'oa.nropago', 'oa.nroservicio', 'oa.fecha', 'oa.fk_moneda_id', 'oa.monto', 'oa.cotizacion', 'oa.status', 'p.proveedor_nombre',
                DB::raw("CONCAT(u.usuario_nombre, ' ', u.usuario_apellido) AS usuario_nombre"),
                DB::raw("MAX(CONCAT(r.tipocodigo, '-', r.codigo)) AS codigo"), DB::raw('MAX(r.reserva_id) AS reserva_id'),
                DB::raw('(SELECT GROUP_CONCAT(o2.nroservicio) FROM ordenadmin o2 WHERE o2.fk_ordenadmin_id = oa.ordenadmin_id) AS os_relacionada'),
                DB::raw("(SELECT GROUP_CONCAT(op.nropago) FROM ordenadmin op WHERE oa.fk_ordenadmin_id = op.ordenadmin_id AND op.status <> 'AN') AS op_relacionada"));
        $this->ordenar($q);

        if ($f['numero'] !== '') {
            $q->where($this->tipoOrden === 'P' ? 'oa.nropago' : 'oa.nroservicio', $f['numero']);
        }
        if ($f['fk_proveedor_id'] !== '') {
            $q->where('oa.fk_proveedor_id', (int) $f['fk_proveedor_id']);
        }
        if ($f['codigo'] !== '') {
            $q->where('r.codigo', $f['codigo']);
        }
        if ($f['fk_usuario_id'] !== '') {
            $q->where('oa.fk_usuario_id', (int) $f['fk_usuario_id']);
        }
        if ($f['fk_moneda_id'] !== '') {
            $q->where('oa.fk_moneda_id', $f['fk_moneda_id']);
        }
        if ($f['status'] !== '') {
            $q->where('oa.status', $f['status']);
        }
        $this->rango($q, 'oa.fecha', $f, 'fecha');
        $this->limitar($q);

        return $q->get()->map(fn ($x) => $this->fila($x))->all();
    }

    protected function ordenar($q): void
    {
        $q->orderByDesc('oa.nropago');
    }

    protected function fila(object $x): array
    {
        return [
            'numero' => $x->nropago,
            'fecha' => $this->dmy((string) $x->fecha),
            'proveedor' => $x->proveedor_nombre,
            'file' => $x->codigo,
            'usuario' => trim((string) $x->usuario_nombre),
            'moneda' => $x->fk_moneda_id,
            'monto' => (float) $x->monto,
            'relacionada' => (string) $x->os_relacionada,
            'cotizacion' => (float) $x->cotizacion,
            'status' => $x->status,
            'acciones' => [
                $this->link('Ver', "/administracion/ordenpago/view/{$x->ordenadmin_id}"),
                $this->link('Editar', "/administracion/ordenpago/edit/{$x->ordenadmin_id}"),
                $this->link('Imprimir', "/administracion/ordenpago/imprimir/{$x->ordenadmin_id}"),
                $this->link('Anular', "/administracion/ordenpago/anular/{$x->ordenadmin_id}", true),
            ],
        ];
    }
}
