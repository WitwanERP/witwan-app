<?php

namespace App\Http\Controllers\Web\Reportes;

use Illuminate\Support\Facades\DB;

/**
 * Administración > Reportes > Productos de integración (CI:
 * administracion/productopororigen): servicios reservados con origen 'UA'
 * (motor de reservas) por fecha de alta del file.
 */
class ProductosPorOrigenController extends ReporteController
{
    protected string $titulo = 'Productos de integración';

    protected string $ruta = 'admin/reportes/productos-origen';

    protected ?string $agruparPor = 'moneda';

    protected function filtros(): array
    {
        return [
            ['campo' => 'fecha', 'label' => 'Fecha de alta', 'tipo' => 'rango'],
        ];
    }

    protected function columnas(): array
    {
        return [
            ['campo' => 'codigo', 'label' => 'Reserva'],
            ['campo' => 'fecha_alta', 'label' => 'Fecha'],
            ['campo' => 'vendedor', 'label' => 'Vendedor'],
            ['campo' => 'usuario', 'label' => 'Usuario'],
            ['campo' => 'voucher', 'label' => 'Voucher'],
            ['campo' => 'moneda', 'label' => 'Moneda'],
            ['campo' => 'total', 'label' => 'Venta', 'tipo' => 'num', 'total' => true],
            ['campo' => 'comisionproveedor_porcentaje', 'label' => 'Comisión %', 'tipo' => 'num'],
            ['campo' => 'status', 'label' => 'Status'],
        ];
    }

    protected function consultar(array $f): array
    {
        $q = DB::table('servicio as s')
            ->join('reserva as r', 'r.reserva_id', '=', 's.fk_reserva_id')
            ->join('cliente as c', 'c.cliente_id', '=', 'r.fk_cliente_id')
            ->join('usuario as u', 'u.usuario_id', '=', 'r.agente')
            ->join('usuario as v', 'v.usuario_id', '=', 'r.fk_usuario_id')
            ->where('s.origen', 'UA')
            ->select([
                'r.reserva_id', 'r.codigo', 'r.tipocodigo', 'r.fecha_alta', 's.nro_confirmacion', 's.comisionproveedor_porcentaje', 's.total', 's.fk_moneda_id', 's.status',
                DB::raw("CONCAT(u.usuario_nombre, ' ', u.usuario_apellido) AS vendedor_nombre"),
                DB::raw("CONCAT(v.usuario_nombre, ' ', v.usuario_apellido) AS usuario_nombre"),
            ]);
        $this->rango($q, 'r.fecha_alta', $f, 'fecha');

        return $q->orderBy('r.fecha_alta')->orderBy('s.servicio_id')->get()->map(fn ($x) => [
            'reserva' => (int) $x->reserva_id,
            'codigo' => "{$x->tipocodigo}-{$x->codigo}",
            'fecha_alta' => $this->dmy($x->fecha_alta),
            'vendedor' => $x->vendedor_nombre,
            'usuario' => $x->usuario_nombre,
            'voucher' => $x->nro_confirmacion,
            'moneda' => $x->fk_moneda_id,
            'total' => (float) $x->total,
            'comisionproveedor_porcentaje' => (float) $x->comisionproveedor_porcentaje,
            'status' => $x->status,
        ])->all();
    }
}
