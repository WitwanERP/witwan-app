<?php

namespace App\Http\Controllers\Web\Documentos\Listados;

use Illuminate\Support\Facades\DB;

/** Administración > Documentos > Notas de débito (CI: administracion/notadebito, listado). */
class NotasDebitoListadoController extends DocumentoListadoController
{
    protected string $titulo = 'Notas de débito';

    protected string $ruta = 'documentos/notas-debito';

    protected function filtros(): array
    {
        return [
            ['campo' => 'notadebito_nro', 'label' => 'Número', 'tipo' => 'text'],
            ['campo' => 'notadebito_fecha', 'label' => 'Fecha', 'tipo' => 'rango'],
            ['campo' => 'notadebito_tipo', 'label' => 'Tipo', 'tipo' => 'text'],
            ['campo' => 'statusfactura', 'label' => 'Status', 'tipo' => 'text'],
            ['campo' => 'fk_cliente_id', 'label' => 'Cliente', 'tipo' => 'select', 'opciones' => $this->catalogos->clientes()],
        ];
    }

    protected function columnas(): array
    {
        return [
            ['campo' => 'numero', 'label' => 'Número'],
            ['campo' => 'fecha', 'label' => 'Fecha'],
            ['campo' => 'tipo', 'label' => 'Tipo'],
            ['campo' => 'status', 'label' => 'Status'],
            ['campo' => 'cliente', 'label' => 'Cliente'],
            ['campo' => 'moneda', 'label' => 'Moneda'],
            ['campo' => 'total', 'label' => 'Monto', 'tipo' => 'num', 'total' => true],
            ['campo' => 'acciones', 'label' => 'Acciones', 'tipo' => 'acciones'],
        ];
    }

    protected function consultar(array $f): array
    {
        $f = $this->rangoPorDefecto($f, 'notadebito_fecha');
        $coef = $this->coef();
        $dec = $this->decimales();

        $q = DB::table('notadebito as nd')
            ->join('cliente as c', 'c.cliente_id', '=', 'nd.fk_cliente_id')
            ->select('nd.notadebito_id', 'nd.notadebito_nro', 'nd.notadebito_fecha', 'nd.notadebito_tipo', 'nd.statusfactura', 'nd.fk_moneda_id', 'c.cliente_nombre',
                DB::raw("ROUND(nd.notadebito_conceptos_gravados * {$coef} + nd.notadebito_conceptos_gravadosespecial * 1.105 + nd.notadebito_conceptos_exentos + nd.notadebito_conceptos_nogravados + nd.notadebito_rgterrestres, {$dec}) AS total"))
            ->orderByDesc('nd.notadebito_fecha');

        foreach (['notadebito_tipo', 'statusfactura'] as $campo) {
            if ($f[$campo] !== '') {
                $q->where("nd.{$campo}", $f[$campo]);
            }
        }
        if ($f['notadebito_nro'] !== '') {
            $q->where('nd.notadebito_nro', 'LIKE', "%{$f['notadebito_nro']}%");
        }
        if ($f['fk_cliente_id'] !== '') {
            $q->where('nd.fk_cliente_id', (int) $f['fk_cliente_id']);
        }
        $this->rango($q, 'nd.notadebito_fecha', $f, 'notadebito_fecha', true);
        $this->limitar($q);

        return $q->get()->map(fn ($x) => [
            'numero' => $x->notadebito_nro,
            'fecha' => $this->dmy((string) $x->notadebito_fecha),
            'tipo' => $x->notadebito_tipo,
            'status' => $x->statusfactura,
            'cliente' => $x->cliente_nombre,
            'moneda' => $x->fk_moneda_id,
            'total' => (float) $x->total,
            'acciones' => [$this->link('Imprimir', "/administracion/notadebito/imprimir/{$x->notadebito_id}")],
        ])->all();
    }
}
