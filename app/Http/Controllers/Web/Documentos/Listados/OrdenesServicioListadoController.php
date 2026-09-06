<?php

namespace App\Http\Controllers\Web\Documentos\Listados;

/** Administración > Caja > Órdenes de servicio (CI: administracion/ordenservicio, listado; ordenadmin.tipo = 'S'). */
class OrdenesServicioListadoController extends OrdenesPagoListadoController
{
    protected string $titulo = 'Órdenes de servicio';

    protected string $ruta = 'documentos/ordenes-servicio';

    protected string $tipoOrden = 'S';

    protected function estados(): array
    {
        return [['value' => 'AN', 'label' => 'Anulada'], ['value' => 'OK', 'label' => 'No pago'], ['value' => 'PR', 'label' => 'Pagado']];
    }

    protected function columnas(): array
    {
        return [
            ['campo' => 'numero', 'label' => 'Número de OS'],
            ['campo' => 'file', 'label' => 'Código reserva', 'link' => 'file_link'],
            ['campo' => 'fecha', 'label' => 'Fecha de alta'],
            ['campo' => 'proveedor', 'label' => 'Proveedor'],
            ['campo' => 'moneda', 'label' => 'Moneda'],
            ['campo' => 'usuario', 'label' => 'Creado por'],
            ['campo' => 'monto', 'label' => 'Monto', 'tipo' => 'num', 'total' => true],
            ['campo' => 'cotizacion', 'label' => 'TC', 'tipo' => 'num'],
            ['campo' => 'status', 'label' => 'Estatus'],
            ['campo' => 'relacionada', 'label' => 'OP'],
            ['campo' => 'acciones', 'label' => 'Acciones', 'tipo' => 'acciones'],
        ];
    }

    protected function ordenar($q): void
    {
        $q->orderByDesc('oa.fecha');
    }

    protected function fila(object $x): array
    {
        return [
            'numero' => $x->nroservicio,
            'file' => $x->codigo,
            'file_link' => $x->reserva_id ? "/reserva/editar/{$x->reserva_id}" : null,
            'fecha' => $this->dmy((string) $x->fecha),
            'proveedor' => $x->proveedor_nombre,
            'moneda' => $x->fk_moneda_id,
            'usuario' => trim((string) $x->usuario_nombre),
            'monto' => (float) $x->monto,
            'cotizacion' => (float) $x->cotizacion,
            'status' => $x->status,
            'relacionada' => (string) $x->op_relacionada,
            'acciones' => [
                $this->link('Editar', "/administracion/ordenservicio/edit/{$x->ordenadmin_id}"),
                $this->link('Imprimir', "/administracion/ordenservicio/imprimir/{$x->ordenadmin_id}"),
                $this->link('Anular', "/administracion/ordenservicio/anular/{$x->ordenadmin_id}", true),
            ],
        ];
    }
}
