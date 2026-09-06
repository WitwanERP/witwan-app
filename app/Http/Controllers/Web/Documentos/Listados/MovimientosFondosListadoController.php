<?php

namespace App\Http\Controllers\Web\Documentos\Listados;

use Illuminate\Support\Facades\DB;

/**
 * Administración > Caja > Movimientos de fondos (CI: administracion/mfondos;
 * `ordenadmin.tipo` = 'M'). Anular/imprimir/editar siguen en el legacy: anular
 * además marca `movimiento.statusdocumento = 0`.
 */
class MovimientosFondosListadoController extends DocumentoListadoController
{
    protected string $titulo = 'Movimientos de fondos';

    protected string $ruta = 'documentos/movimientos-fondos';

    protected string $grupo = 'Caja';

    protected function filtros(): array
    {
        return [
            ['campo' => 'nropago', 'label' => 'Número', 'tipo' => 'text'],
            ['campo' => 'fecha', 'label' => 'Fecha', 'tipo' => 'rango'],
            ['campo' => 'fk_usuario_id', 'label' => 'Usuario', 'tipo' => 'select', 'opciones' => $this->catalogos->usuariosInternos()],
            ['campo' => 'fk_moneda_id', 'label' => 'Moneda', 'tipo' => 'select', 'opciones' => $this->catalogos->monedas()],
            ['campo' => 'status', 'label' => 'Status', 'tipo' => 'select', 'opciones' => [['value' => 'AN', 'label' => 'Anulada'], ['value' => 'OK', 'label' => 'Ok'], ['value' => 'PR', 'label' => 'Procesada']]],
        ];
    }

    protected function columnas(): array
    {
        return [
            ['campo' => 'numero', 'label' => 'Número'],
            ['campo' => 'fecha', 'label' => 'Fecha'],
            ['campo' => 'usuario', 'label' => 'Usuario'],
            ['campo' => 'moneda', 'label' => 'Moneda'],
            ['campo' => 'monto', 'label' => 'Monto', 'tipo' => 'num', 'total' => true],
            ['campo' => 'status', 'label' => 'Status'],
            ['campo' => 'observaciones', 'label' => 'Obs.'],
            ['campo' => 'acciones', 'label' => 'Acciones', 'tipo' => 'acciones'],
        ];
    }

    protected function accionesGlobales(): array
    {
        return [['label' => 'Nuevo movimiento', 'href' => '/administracion/mfondos/add']];
    }

    protected function consultar(array $f): array
    {
        $f = $this->rangoPorDefecto($f, 'fecha');
        $estados = ['AN' => 'Anulada', 'OK' => 'Ok', 'PR' => 'Procesada'];

        $q = DB::table('ordenadmin as oa')
            ->leftJoin('usuario as u', 'u.usuario_id', '=', 'oa.fk_usuario_id')
            ->where('oa.tipo', 'M')
            ->select('oa.ordenadmin_id', 'oa.nropago', 'oa.fecha', 'oa.fk_moneda_id', 'oa.monto', 'oa.status', 'oa.observaciones',
                DB::raw("CONCAT(u.usuario_nombre, ' ', u.usuario_apellido) AS usuario_nombre"))
            ->orderByDesc('oa.fecha')->orderByDesc('oa.ordenadmin_id');

        if ($f['nropago'] !== '') {
            $q->where('oa.nropago', $f['nropago']);
        }
        foreach (['fk_usuario_id', 'fk_moneda_id', 'status'] as $campo) {
            if ($f[$campo] !== '') {
                $q->where("oa.{$campo}", $f[$campo]);
            }
        }
        $this->rango($q, 'oa.fecha', $f, 'fecha');
        $this->limitar($q);

        return $q->get()->map(fn ($x) => [
            'numero' => $x->nropago,
            'fecha' => $this->dmy((string) $x->fecha),
            'usuario' => trim((string) $x->usuario_nombre),
            'moneda' => $x->fk_moneda_id,
            'monto' => (float) $x->monto,
            'status' => $estados[$x->status] ?? $x->status,
            'observaciones' => (string) $x->observaciones,
            'acciones' => [
                $this->link('Editar', "/administracion/mfondos/edit/{$x->ordenadmin_id}"),
                $this->link('Imprimir', "/administracion/mfondos/imprimir/{$x->ordenadmin_id}"),
                $this->link('Anular', "/administracion/mfondos/anular/{$x->ordenadmin_id}", true),
            ],
        ])->all();
    }
}
