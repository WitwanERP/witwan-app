<?php

namespace App\Http\Controllers\Web\Documentos\Listados;

use Illuminate\Support\Facades\DB;

/** Administración > Documentos > Notas de crédito (CI: administracion/notacredito, listado). */
class NotasCreditoListadoController extends DocumentoListadoController
{
    protected string $titulo = 'Notas de crédito';

    protected string $ruta = 'documentos/notas-credito';

    protected function filtros(): array
    {
        return [
            ['campo' => 'notacredito_nro', 'label' => 'Número', 'tipo' => 'text'],
            ['campo' => 'notacredito_fecha', 'label' => 'Fecha', 'tipo' => 'rango'],
            ['campo' => 'notacredito_tipo', 'label' => 'Tipo', 'tipo' => 'text'],
            ['campo' => 'statusfactura', 'label' => 'Status', 'tipo' => 'text'],
            ['campo' => 'codigo', 'label' => 'File (código)', 'tipo' => 'text'],
            ['campo' => 'fk_usuario_id', 'label' => 'Usuario', 'tipo' => 'select', 'opciones' => $this->catalogos->usuariosInternos()],
            ['campo' => 'fk_cliente_id', 'label' => 'Cliente', 'tipo' => 'select', 'opciones' => $this->catalogos->clientes()],
            ['campo' => 'fk_moneda_id', 'label' => 'Moneda', 'tipo' => 'select', 'opciones' => $this->catalogos->monedas()],
        ];
    }

    protected function columnas(): array
    {
        return [
            ['campo' => 'numero', 'label' => 'Número'],
            ['campo' => 'fecha', 'label' => 'Fecha'],
            ['campo' => 'tipo', 'label' => 'Tipo'],
            ['campo' => 'status', 'label' => 'Status'],
            ['campo' => 'file', 'label' => 'File'],
            ['campo' => 'usuario', 'label' => 'Usuario'],
            ['campo' => 'cliente', 'label' => 'Cliente'],
            ['campo' => 'moneda', 'label' => 'Moneda'],
            ['campo' => 'total', 'label' => 'Monto', 'tipo' => 'num', 'total' => true],
            ['campo' => 'observaciones', 'label' => 'Obs.', 'tipo' => 'pre'],
            ['campo' => 'acciones', 'label' => 'Acciones', 'tipo' => 'acciones'],
        ];
    }

    protected function accionesGlobales(): array
    {
        return [['label' => 'Nueva nota de crédito (sistema anterior)', 'href' => '/administracion/notacredito/create', 'target' => '_blank']];
    }

    protected function consultar(array $f): array
    {
        $f = $this->rangoPorDefecto($f, 'notacredito_fecha');
        $coef = $this->coef();
        $dec = $this->decimales();

        $q = DB::table('notacredito as nc')
            ->join('cliente as c', 'c.cliente_id', '=', 'nc.fk_cliente_id')
            ->leftJoin('usuario as u', 'u.usuario_id', '=', 'nc.fk_usuario_id')
            ->leftJoin('factura as fc', 'fc.factura_id', '=', 'nc.fk_factura_id')
            ->leftJoin('reserva as r', DB::raw('IFNULL(fc.fk_file_id, nc.fk_file_id)'), '=', 'r.reserva_id')
            ->select('nc.notacredito_id', 'nc.notacredito_nro', 'nc.notacredito_fecha', 'nc.notacredito_tipo', 'nc.statusfactura', 'nc.fk_moneda_id', 'nc.observaciones', 'c.cliente_nombre', 'r.tipocodigo', 'r.codigo',
                DB::raw("CONCAT(u.usuario_nombre, ' ', u.usuario_apellido) AS usuario_nombre"),
                DB::raw("ROUND(nc.notacredito_conceptos_gravados * {$coef} + nc.notacredito_conceptos_gravadosespecial * 1.105 + nc.notacredito_conceptos_exentos + nc.notacredito_conceptos_nogravados + nc.notacredito_rgterrestres + nc.notacredito_impuesto1 + nc.notacredito_impuesto2 + nc.notacredito_impuesto3 + nc.notacredito_impuesto4 + nc.notacredito_impuesto5 + CAST(SUBSTRING_INDEX(SUBSTRING_INDEX(nc.remitofull, ':', 3), ':', -1) AS DECIMAL(12,2)), {$dec}) AS total"))
            ->orderByDesc('nc.notacredito_fecha');

        foreach (['notacredito_tipo', 'statusfactura', 'fk_moneda_id'] as $campo) {
            if ($f[$campo] !== '') {
                $q->where("nc.{$campo}", $f[$campo]);
            }
        }
        if ($f['notacredito_nro'] !== '') {
            $q->where('nc.notacredito_nro', 'LIKE', "%{$f['notacredito_nro']}%");
        }
        if ($f['codigo'] !== '') {
            $q->where('r.codigo', $f['codigo']);
        }
        if ($f['fk_usuario_id'] !== '') {
            $q->where('nc.fk_usuario_id', (int) $f['fk_usuario_id']);
        }
        if ($f['fk_cliente_id'] !== '') {
            $q->where('nc.fk_cliente_id', (int) $f['fk_cliente_id']);
        }
        $this->rango($q, 'nc.notacredito_fecha', $f, 'notacredito_fecha', true);
        $this->limitar($q);

        return $q->get()->map(fn ($x) => [
            'numero' => $x->notacredito_nro,
            'fecha' => $this->dmy((string) $x->notacredito_fecha),
            'tipo' => $x->notacredito_tipo,
            'status' => $x->statusfactura,
            'file' => $x->codigo ? "{$x->tipocodigo}-{$x->codigo}" : '',
            'usuario' => trim((string) $x->usuario_nombre),
            'cliente' => $x->cliente_nombre,
            'moneda' => $x->fk_moneda_id,
            'total' => (float) $x->total,
            'observaciones' => (string) $x->observaciones,
            'acciones' => [
                $this->link('Imprimir', "/administracion/notacredito/imprimir/{$x->notacredito_id}"),
                $this->link('Descargar', "/administracion/notacredito/mpdf/{$x->notacredito_id}"),
                $this->link('Generar ND', "/administracion/notadebito/create?notacredito={$x->notacredito_id}"),
            ],
        ])->all();
    }
}
