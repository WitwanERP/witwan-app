<?php

namespace App\Http\Controllers\Web\Documentos\Listados;

use App\Support\Licencia;
use Illuminate\Support\Facades\DB;

/** Administración > Documentos > Facturas (CI: administracion/factura, listado). */
class FacturasListadoController extends DocumentoListadoController
{
    protected string $titulo = 'Facturas';

    protected string $ruta = 'documentos/facturas';

    protected function filtros(): array
    {
        return [
            ['campo' => 'statusfactura', 'label' => 'Estado', 'tipo' => 'text'],
            ['campo' => 'factura_nro', 'label' => 'Número', 'tipo' => 'text'],
            ['campo' => 'codigo', 'label' => 'Reserva (código)', 'tipo' => 'text'],
            ['campo' => 'factura_fecha', 'label' => 'Fecha', 'tipo' => 'rango'],
            ['campo' => 'factura_tipo', 'label' => 'Tipo', 'tipo' => 'text'],
            ['campo' => 'fk_cliente_id', 'label' => 'Cliente', 'tipo' => 'select', 'opciones' => $this->catalogos->clientes()],
            ['campo' => 'cuit', 'label' => Licencia::pais() === 'CL' ? 'RUT' : 'CUIT', 'tipo' => 'text'],
            ['campo' => 'fk_moneda_id', 'label' => 'Moneda', 'tipo' => 'select', 'opciones' => $this->catalogos->monedas()],
        ];
    }

    protected function columnas(): array
    {
        return [
            ['campo' => 'estado', 'label' => 'Estado'],
            ['campo' => 'numero', 'label' => 'Número'],
            ['campo' => 'reserva', 'label' => 'Reserva'],
            ['campo' => 'sucursal', 'label' => 'Suc.'],
            ['campo' => 'fecha', 'label' => 'Fecha'],
            ['campo' => 'tipo', 'label' => 'Tipo'],
            ['campo' => 'cliente', 'label' => 'Cliente'],
            ['campo' => 'cuit', 'label' => Licencia::pais() === 'CL' ? 'RUT' : 'CUIT'],
            ['campo' => 'moneda', 'label' => 'Moneda'],
            ['campo' => 'total', 'label' => 'Monto', 'tipo' => 'num', 'total' => true],
            ['campo' => 'usuario', 'label' => 'Efectuado por'],
            ['campo' => 'acciones', 'label' => 'Acciones', 'tipo' => 'acciones'],
        ];
    }

    protected function accionesGlobales(): array
    {
        return [['label' => 'Nueva factura (sistema anterior)', 'href' => '/administracion/factura/create', 'target' => '_blank']];
    }

    protected function consultar(array $f): array
    {
        $f = $this->rangoPorDefecto($f, 'factura_fecha');
        $coef = $this->coef();
        $dec = $this->decimales();

        $q = DB::table('factura as fc')
            ->leftJoin('cliente as c', 'c.cliente_id', '=', 'fc.fk_cliente_id')
            ->leftJoin('usuario as u', 'u.usuario_id', '=', 'fc.fk_usuario_id')
            ->leftJoin('reserva as r', 'r.reserva_id', '=', 'fc.fk_file_id')
            ->select('fc.factura_id', 'fc.statusfactura', 'fc.factura_nro', 'fc.factura_sucursal', 'fc.factura_fecha', 'fc.factura_tipo', 'fc.fk_moneda_id', 'c.cliente_nombre', 'c.cuit', 'r.tipocodigo', 'r.codigo',
                DB::raw("CONCAT(u.usuario_nombre, ' ', u.usuario_apellido) AS nusuario"),
                DB::raw("ROUND(fc.factura_conceptos_gravados * {$coef} + fc.factura_conceptos_gravadosespecial * 1.105 + fc.factura_conceptos_exentos + fc.factura_conceptos_nogravados + fc.factura_impuesto1 + fc.factura_impuesto2 + fc.factura_impuesto3 + fc.factura_impuesto4 + fc.factura_impuesto5, {$dec}) + IF(fc.factura_fecha > '2015-12-17', fc.factura_rgterrestres, 0) - CAST(SUBSTRING_INDEX(SUBSTRING_INDEX(fc.remitofull, ':', 3), ':', -1) AS DECIMAL(12,2)) AS total"))
            ->orderByDesc('fc.factura_fecha');

        foreach (['statusfactura', 'factura_tipo', 'fk_moneda_id'] as $campo) {
            if ($f[$campo] !== '') {
                $q->where("fc.{$campo}", $f[$campo]);
            }
        }
        if ($f['factura_nro'] !== '') {
            $q->where('fc.factura_nro', 'LIKE', "%{$f['factura_nro']}%");
        }
        if ($f['codigo'] !== '') {
            $q->where('r.codigo', $f['codigo']);
        }
        if ($f['fk_cliente_id'] !== '') {
            $q->where('fc.fk_cliente_id', (int) $f['fk_cliente_id']);
        }
        if ($f['cuit'] !== '') {
            $q->where('c.cuit', 'LIKE', "%{$f['cuit']}%");
        }
        $this->rango($q, 'fc.factura_fecha', $f, 'factura_fecha', true);
        $this->limitar($q);

        return $q->get()->map(fn ($x) => [
            'estado' => $x->statusfactura,
            'numero' => $x->factura_nro,
            'reserva' => $x->codigo ? "{$x->tipocodigo}-{$x->codigo}" : '',
            'sucursal' => $x->factura_sucursal,
            'fecha' => $this->dmy((string) $x->factura_fecha),
            'tipo' => $x->factura_tipo,
            'cliente' => $x->cliente_nombre,
            'cuit' => $x->cuit,
            'moneda' => $x->fk_moneda_id,
            'total' => (float) $x->total,
            'usuario' => trim((string) $x->nusuario),
            'acciones' => [
                $this->link('Imprimir', "/administracion/factura/imprimir/{$x->factura_id}"),
                $this->link('Descargar', "/administracion/factura/mpdf/{$x->factura_id}"),
                $this->link('Remito', "/administracion/factura/remito/{$x->factura_id}"),
                $this->link('Generar NC', "/administracion/notacredito/create?factura={$x->factura_id}"),
                $this->link('Anular', "/administracion/factura/anula?factura={$x->factura_id}", true),
            ],
        ])->all();
    }
}
