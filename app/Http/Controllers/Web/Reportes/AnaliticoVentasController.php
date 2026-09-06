<?php

namespace App\Http\Controllers\Web\Reportes;

use App\Services\CatalogosService;
use Illuminate\Support\Facades\DB;

/**
 * Administración > Reportes > Analítico de ventas (CI: reportes/analiticovta).
 * En el legacy era sólo un CSV; acá se ve en pantalla y se exporta igual. Un
 * renglón por servicio "madre" (excluye los hijos de rel_servicio) con sus
 * fees/OBT/TBK/ancillaries/multas asociados.
 */
class AnaliticoVentasController extends ReporteController
{
    protected string $titulo = 'Analítico de ventas';

    protected string $ruta = 'admin/reportes/analitico-ventas';

    protected ?string $agruparPor = null;

    protected int $limite = 2000;

    public function __construct(private CatalogosService $catalogos) {}

    protected function filtros(): array
    {
        return [
            ['campo' => 'codigo', 'label' => 'Código', 'tipo' => 'text'],
            ['campo' => 'fecha_alta', 'label' => 'Fecha de alta', 'tipo' => 'rango'],
            ['campo' => 'fk_cliente_id', 'label' => 'Cliente', 'tipo' => 'select', 'opciones' => $this->catalogos->clientes()],
            ['campo' => 'fk_vendedor_id', 'label' => 'Vendedor', 'tipo' => 'select', 'opciones' => $this->catalogos->usuariosInternos()],
            ['campo' => 'fk_usuario_id', 'label' => 'Reserva efectuada por', 'tipo' => 'select', 'opciones' => $this->catalogos->usuariosInternos()],
            ['campo' => 'ver_adma', 'label' => 'Ver files AD y MA', 'tipo' => 'bool', 'default' => '0'],
        ];
    }

    protected function columnas(): array
    {
        return [
            ['campo' => 'file', 'label' => 'File'],
            ['campo' => 'codigo_externo', 'label' => 'Código externo'],
            ['campo' => 'area', 'label' => 'Área'],
            ['campo' => 'file_status', 'label' => 'Status file'],
            ['campo' => 'servicio_status', 'label' => 'Status servicio'],
            ['campo' => 'razon_social', 'label' => 'Razón social'],
            ['campo' => 'tipo', 'label' => 'Tipo'],
            ['campo' => 'efectuada_por', 'label' => 'Efectuada por'],
            ['campo' => 'servicio', 'label' => 'Servicio'],
            ['campo' => 'proveedor', 'label' => 'Proveedor'],
            ['campo' => 'prestador', 'label' => 'Prestador'],
            ['campo' => 'ruta', 'label' => 'Ruta'],
            ['campo' => 'reemision', 'label' => 'Reemisión'],
            ['campo' => 'pax', 'label' => 'Pax'],
            ['campo' => 'recloc', 'label' => 'RECLOC'],
            ['campo' => 'fecha_emision', 'label' => 'Fecha emisión'],
            ['campo' => 'salida', 'label' => 'Salida'],
            ['campo' => 'retorno', 'label' => 'Retorno'],
            ['campo' => 'impuestos', 'label' => 'Impuestos', 'tipo' => 'num', 'total' => true],
            ['campo' => 'renta', 'label' => 'Renta', 'tipo' => 'num', 'total' => true],
            ['campo' => 'iva', 'label' => 'IVA', 'tipo' => 'num', 'total' => true],
            ['campo' => 'total', 'label' => 'Total servicio', 'tipo' => 'num', 'total' => true],
            ['campo' => 'fee', 'label' => 'FEE', 'tipo' => 'num', 'total' => true],
            ['campo' => 'q_fee', 'label' => 'Q FEE', 'tipo' => 'num'],
            ['campo' => 'obt', 'label' => 'OBT', 'tipo' => 'num', 'total' => true],
            ['campo' => 'q_obt', 'label' => 'Q OBT', 'tipo' => 'num'],
            ['campo' => 'tbk', 'label' => 'TBK', 'tipo' => 'num', 'total' => true],
            ['campo' => 'q_tbk', 'label' => 'Q TBK', 'tipo' => 'num'],
            ['campo' => 'ancillaries', 'label' => 'Ancillaries', 'tipo' => 'num', 'total' => true],
            ['campo' => 'q_ancillaries', 'label' => 'Q Anc.', 'tipo' => 'num'],
            ['campo' => 'multas', 'label' => 'Multas', 'tipo' => 'num', 'total' => true],
            ['campo' => 'q_multas', 'label' => 'Q Multas', 'tipo' => 'num'],
            ['campo' => 'moneda', 'label' => 'Moneda vta'],
            ['campo' => 'tc', 'label' => 'TC vta', 'tipo' => 'num'],
        ];
    }

    protected function consultar(array $f): array
    {
        $hijos = fn (string $tipo, string $agg, string $alias) => DB::raw("(SELECT {$agg} FROM servicio sfee JOIN rel_servicio rsfee ON rsfee.servicio_hijo = sfee.servicio_id WHERE rsfee.servicio_madre = s.servicio_id AND sfee.fk_tipoproducto_id = '{$tipo}'".($agg === 'COUNT(sfee.servicio_id)' ? '' : " AND sfee.status <> 'CA'").") AS {$alias}");

        $q = DB::table('servicio as s')
            ->join('reserva as r', 'r.reserva_id', '=', 's.fk_reserva_id')
            ->join('cliente as c', 'c.cliente_id', '=', 'r.fk_cliente_id')
            ->leftJoin('pnraereo as p', 'p.fk_ocupacion_id', '=', 's.servicio_id')
            ->leftJoin('sistema as si', 'si.sistema_id', '=', 'r.fk_sistemaaplicacion_id')
            ->leftJoin('proveedor as pr', 'pr.proveedor_id', '=', 's.fk_proveedor_id')
            ->leftJoin('proveedor as pre', 'pre.proveedor_id', '=', 's.fk_prestador_id')
            ->leftJoin('usuario as u', 'u.usuario_id', '=', 'r.fk_usuario_id')
            ->where('s.status', '<>', 'CA')
            ->whereNotIn('s.servicio_id', DB::table('rel_servicio')->select('servicio_hijo'))
            ->groupBy('s.servicio_id')
            ->orderBy('r.codigo')
            ->select([
                'r.tipocodigo', 'r.codigo', 'r.codigo_externo', 'si.sistema_nombre', 'r.fk_filestatus_id', 's.status', 'c.cliente_razonsocial', 's.fk_tipoproducto_id',
                's.servicio_nombre', 'pr.proveedor_nombre', 'pre.proveedor_nombre as prestador', 'p.pnraereo_ruta', 'p.pnraereo_reemision', 'p.pnraereo_nombre', 'p.pnraereo_apellido',
                'r.titular_nombre', 'r.titular_apellido', 'p.codigo_recloc', 'p.pnraereo_fechaemision', 's.regdate', 's.vigencia_ini', 's.vigencia_fin', 's.impuestos', 's.renta', 's.iva', 's.total', 's.fk_moneda_id',
                DB::raw("CONCAT(u.usuario_apellido, ' ', u.usuario_nombre) AS efectuada_por"),
                DB::raw('SUM(s.cotventa) AS tc'),
            ])
            ->addSelect([
                $hijos('FEE', 'SUM(sfee.total)', 'fee'), $hijos('FEE', 'COUNT(sfee.servicio_id)', 'q_fee'),
                $hijos('OBT', 'SUM(sfee.total)', 'obt'), $hijos('OBT', 'COUNT(sfee.servicio_id)', 'q_obt'),
                $hijos('TBK', 'SUM(sfee.total)', 'tbk'), $hijos('TBK', 'COUNT(sfee.servicio_id)', 'q_tbk'),
                $hijos('AST', 'SUM(sfee.total)', 'ancillaries'), $hijos('AST', 'COUNT(sfee.servicio_id)', 'q_ancillaries'),
                $hijos('MUL', 'SUM(sfee.total)', 'multas'), $hijos('MUL', 'COUNT(sfee.servicio_id)', 'q_multas'),
            ]);

        if ($f['codigo'] !== '') {
            $q->where('r.codigo', $f['codigo']);
        }
        $this->rango($q, 'r.fecha_alta', $f, 'fecha_alta');
        if ($f['fk_vendedor_id'] !== '') {
            $q->where('r.agente', (int) $f['fk_vendedor_id']);
        }
        if ($f['fk_usuario_id'] !== '') {
            $q->where('r.fk_usuario_id', (int) $f['fk_usuario_id']);
        }
        if ($f['fk_cliente_id'] !== '') {
            $q->where('r.fk_cliente_id', (int) $f['fk_cliente_id']);
        }
        if ($f['ver_adma'] !== '1') {
            $q->whereNotIn('r.tipocodigo', ['AD', 'MA']);
        }
        $this->limitar($q);

        return $q->get()->map(fn ($x) => [
            'file' => "{$x->tipocodigo}-{$x->codigo}",
            'codigo_externo' => $x->codigo_externo,
            'area' => $x->sistema_nombre,
            'file_status' => $x->fk_filestatus_id,
            'servicio_status' => $x->status,
            'razon_social' => str_replace(';', '', (string) $x->cliente_razonsocial),
            'tipo' => $x->fk_tipoproducto_id,
            'efectuada_por' => trim((string) $x->efectuada_por),
            'servicio' => $x->servicio_nombre,
            'proveedor' => $x->proveedor_nombre,
            'prestador' => $x->prestador,
            'ruta' => str_replace("\n", '', (string) $x->pnraereo_ruta),
            'reemision' => ($x->pnraereo_reemision ?? '') !== '' ? 'SI' : 'NO',
            'pax' => trim(($x->pnraereo_apellido ?? $x->titular_apellido).', '.($x->pnraereo_nombre ?? $x->titular_nombre), ', '),
            'recloc' => $x->codigo_recloc ?? '',
            'fecha_emision' => $this->dmy(substr((string) ($x->pnraereo_fechaemision ?? $x->regdate), 0, 10)),
            'salida' => $this->dmy($x->vigencia_ini),
            'retorno' => $this->dmy($x->vigencia_fin),
            'impuestos' => (float) $x->impuestos,
            'renta' => (float) $x->renta,
            'iva' => (float) $x->iva,
            'total' => (float) $x->total,
            'fee' => (float) $x->fee, 'q_fee' => (int) $x->q_fee,
            'obt' => (float) $x->obt, 'q_obt' => (int) $x->q_obt,
            'tbk' => (float) $x->tbk, 'q_tbk' => (int) $x->q_tbk,
            'ancillaries' => (float) $x->ancillaries, 'q_ancillaries' => (int) $x->q_ancillaries,
            'multas' => (float) $x->multas, 'q_multas' => (int) $x->q_multas,
            'moneda' => $x->fk_moneda_id,
            'tc' => (float) $x->tc,
        ])->all();
    }
}
