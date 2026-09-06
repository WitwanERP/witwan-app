<?php

namespace App\Http\Controllers\Web\Contabilidad;

use App\Http\Controllers\Web\Reportes\ReporteController;
use Illuminate\Support\Facades\DB;

/**
 * Administración > Contabilidad > IVA Venta (CI: administracion/ivacredito).
 * Subdiario de ventas al estilo AR: facturas, notas de crédito (en negativo) y
 * notas de débito con gravado 21 %, gravado 10,5 %, no gravado, exento, IVA
 * de cada alícuota, RG 3819 (terrestres), IVA TUR (tercer campo del
 * `remitofull`), RG 5272 (impuesto1) y RG aérea; los documentos en USD se
 * llevan a pesos con el `tipo_cambio` del documento. Agrupa por tipo de
 * documento con totales (el CI agrupaba por condición de IVA sólo en secontur;
 * la rama multi-base de secontur no se porta).
 */
class IvaVentaController extends ReporteController
{
    protected string $titulo = 'IVA Venta';

    protected string $ruta = 'contabilidad/iva-venta';

    protected string $grupo = 'Contabilidad';

    protected ?string $agruparPor = 'tipo';

    protected function filtros(): array
    {
        return [
            ['campo' => 'fecha', 'label' => 'Fecha', 'tipo' => 'rango'],
            ['campo' => 'tipo', 'label' => 'Documento', 'tipo' => 'select', 'opciones' => [['value' => 'F', 'label' => 'Facturas'], ['value' => 'NC', 'label' => 'Notas de crédito'], ['value' => 'ND', 'label' => 'Notas de débito']]],
        ];
    }

    protected function columnas(): array
    {
        return [
            ['campo' => 'fecha', 'label' => 'Fecha'],
            ['campo' => 'suc', 'label' => 'Pto Vta.'],
            ['campo' => 'nrof', 'label' => 'Nro'],
            ['campo' => 'ncliente', 'label' => 'Raz. Soc.'],
            ['campo' => 'condicioniva', 'label' => 'C. IVA'],
            ['campo' => 'cuit', 'label' => 'CUIT'],
            ['campo' => 'c21', 'label' => 'Gr. 21', 'tipo' => 'num', 'total' => true],
            ['campo' => 'c10', 'label' => 'Gr. 10.5', 'tipo' => 'num', 'total' => true],
            ['campo' => 'cnc', 'label' => 'No Grav.', 'tipo' => 'num', 'total' => true],
            ['campo' => 'ce', 'label' => 'Exento', 'tipo' => 'num', 'total' => true],
            ['campo' => 'i21', 'label' => 'IVA 21%', 'tipo' => 'num', 'total' => true],
            ['campo' => 'i10', 'label' => 'IVA 10.5%', 'tipo' => 'num', 'total' => true],
            ['campo' => 'rgt', 'label' => 'RG 3819', 'tipo' => 'num', 'total' => true],
            ['campo' => 'rg1043', 'label' => 'IVA TUR', 'tipo' => 'num', 'total' => true],
            ['campo' => 'impuesto1', 'label' => 'RG 5272', 'tipo' => 'num', 'total' => true],
            ['campo' => 'rga', 'label' => 'RG Aérea', 'tipo' => 'num'],
            ['campo' => 'totalfinal', 'label' => 'Total', 'tipo' => 'num', 'total' => true],
        ];
    }

    protected function consultar(array $f): array
    {
        $tc = fn (string $t) => "IF({$t}.fk_moneda_id = 'USD', {$t}.{$t}_tipo_cambio, 1)";
        $rem = fn (string $t) => "CAST(SUBSTRING_INDEX(SUBSTRING_INDEX({$t}.remitofull, ':', 3), ':', -1) AS DECIMAL(12,2))";
        $facturas = "SELECT 'F' AS tipo, factura_id AS doc_id, factura_fecha AS fechad, factura_sucursal AS suc,
            CONCAT('FC ', factura_tipo, ' ', factura_sucursal, '-', factura_nro) AS nrof,
            factura_conceptos_gravados * {$tc('factura')} AS c21,
            factura_conceptos_gravadosespecial * {$tc('factura')} AS c10,
            factura_conceptos_exentos * {$tc('factura')} AS ce,
            ROUND(factura_conceptos_nogravados * {$tc('factura')}, 2) AS cnc,
            factura_rgterrestres * {$tc('factura')} AS rgt,
            factura_impuesto1 * {$tc('factura')} AS impuesto1,
            factura_rgaereos * {$tc('factura')} AS rga,
            ROUND(factura_conceptos_gravados * 0.21 * {$tc('factura')}, 2) AS i21,
            ROUND(factura_conceptos_gravadosespecial * 0.105 * {$tc('factura')}, 2) AS i10,
            ROUND((factura_conceptos_gravados * 1.21 + factura_conceptos_gravadosespecial * 1.105 + factura_conceptos_exentos + factura_conceptos_nogravados + factura_impuesto1
                + IF(factura_fecha > '2015-12-17', factura_rgterrestres, 0) - {$rem('factura')}) * {$tc('factura')}, 2) AS totalfinal,
            {$rem('factura')} * -1 * {$tc('factura')} AS rg1043,
            SUBSTRING(cliente.cliente_razonsocial, 1, 20) AS ncliente, condicioniva.condicioniva_nombre, cliente.cuit AS cuit
            FROM factura JOIN cliente ON cliente.cliente_id = factura.fk_cliente_id LEFT JOIN condicioniva ON cliente.fk_condicioniva_id = condicioniva.condicioniva_id
            WHERE factura_id <> 0 AND factura_nro NOT LIKE 'TWR%' AND factura_tipo <> 'X'";
        $nc = "SELECT 'NC' AS tipo, notacredito_id AS doc_id, notacredito_fecha AS fechad, notacredito_sucursal AS suc,
            CONCAT('NC ', notacredito_tipo, ' ', notacredito_sucursal, '-', notacredito_nro) AS nrof,
            notacredito_conceptos_gravados * -1 * {$tc('notacredito')} AS c21,
            notacredito_conceptos_gravadosespecial * -1 * {$tc('notacredito')} AS c10,
            notacredito_conceptos_exentos * -1 * {$tc('notacredito')} AS ce,
            ROUND(notacredito_conceptos_nogravados, 2) * -1 * {$tc('notacredito')} AS cnc,
            notacredito_rgterrestres * -1 * {$tc('notacredito')} AS rgt,
            notacredito_impuesto1 * -1 * {$tc('notacredito')} AS impuesto1,
            0 AS rga,
            ROUND(notacredito_conceptos_gravados * 0.21, 2) * -1 * {$tc('notacredito')} AS i21,
            ROUND(notacredito_conceptos_gravadosespecial * 0.105, 2) * -1 * {$tc('notacredito')} AS i10,
            (ROUND((notacredito_conceptos_gravados * 1.21 + notacredito_conceptos_gravadosespecial * 1.105 + notacredito_conceptos_exentos + notacredito_conceptos_nogravados + notacredito_impuesto1
                + IF(notacredito_fecha > '2015-12-17', notacredito_rgterrestres, 0)) * {$tc('notacredito')}, 2) - ({$rem('notacredito')} * {$tc('notacredito')}) * -1) * -1 AS totalfinal,
            {$rem('notacredito')} * -1 * {$tc('notacredito')} AS rg1043,
            SUBSTRING(cliente.cliente_razonsocial, 1, 20) AS ncliente, condicioniva.condicioniva_nombre, cliente.cuit AS cuit
            FROM notacredito JOIN cliente ON cliente.cliente_id = notacredito.fk_cliente_id LEFT JOIN condicioniva ON cliente.fk_condicioniva_id = condicioniva.condicioniva_id
            WHERE notacredito_id <> 0 AND notacredito_tipo <> 'X'";
        $nd = "SELECT 'ND' AS tipo, notadebito_id AS doc_id, notadebito_fecha AS fechad, notadebito_sucursal AS suc,
            CONCAT('ND ', notadebito_tipo, ' ', notadebito_sucursal, '-', notadebito_nro) AS nrof,
            notadebito_conceptos_gravados * {$tc('notadebito')} AS c21,
            notadebito_conceptos_gravadosespecial * {$tc('notadebito')} AS c10,
            notadebito_conceptos_exentos * {$tc('notadebito')} AS ce,
            ROUND(notadebito_conceptos_nogravados * {$tc('notadebito')}, 2) AS cnc,
            notadebito_rgterrestres * {$tc('notadebito')} AS rgt,
            notadebito_impuesto1 * {$tc('notadebito')} AS impuesto1,
            0 AS rga,
            ROUND(notadebito_conceptos_gravados * 0.21 * {$tc('notadebito')}, 2) AS i21,
            ROUND(notadebito_conceptos_gravadosespecial * 0.105 * {$tc('notadebito')}, 2) AS i10,
            ROUND(((notadebito_conceptos_gravados * 1.21 + notadebito_conceptos_gravadosespecial * 1.105 + notadebito_conceptos_exentos + notadebito_conceptos_nogravados + notadebito_impuesto1)
                + IF(notadebito_fecha > '2015-12-17', notadebito_rgterrestres, 0)) * {$tc('notadebito')}, 2) AS totalfinal,
            0 AS rg1043,
            SUBSTRING(cliente.cliente_razonsocial, 1, 20) AS ncliente, condicioniva.condicioniva_nombre, cliente.cuit AS cuit
            FROM notadebito JOIN cliente ON cliente.cliente_id = notadebito.fk_cliente_id LEFT JOIN condicioniva ON cliente.fk_condicioniva_id = condicioniva.condicioniva_id
            WHERE notadebito_id <> 0 AND notadebito_tipo <> 'X'";

        $where = [];
        $bind = [];
        if ($f['fecha'] !== '') {
            $where[] = 'fechad >= ?';
            $bind[] = $f['fecha'].' 00:00:00';
        }
        if ($f['fecha_to'] !== '') {
            $where[] = 'fechad <= ?';
            $bind[] = $f['fecha_to'].' 23:59:59';
        }
        if ($f['tipo'] !== '') {
            $where[] = 'tipo = ?';
            $bind[] = $f['tipo'];
        }
        $sql = "SELECT * FROM ({$facturas} UNION {$nc} UNION {$nd}) AS un_tabl".($where ? ' WHERE '.implode(' AND ', $where) : '').' ORDER BY fechad, doc_id';

        return array_map(fn ($r) => [
            'tipo' => $r->tipo,
            'fecha' => $this->dmy((string) $r->fechad),
            'suc' => (string) $r->suc,
            'nrof' => (string) $r->nrof,
            'ncliente' => (string) $r->ncliente,
            'condicioniva' => (string) $r->condicioniva_nombre,
            'cuit' => (string) $r->cuit,
            'c21' => round((float) $r->c21, 2), 'c10' => round((float) $r->c10, 2), 'cnc' => round((float) $r->cnc, 2), 'ce' => round((float) $r->ce, 2),
            'i21' => round((float) $r->i21, 2), 'i10' => round((float) $r->i10, 2), 'rgt' => round((float) $r->rgt, 2), 'rg1043' => round((float) $r->rg1043, 2),
            'impuesto1' => round((float) $r->impuesto1, 2), 'rga' => round((float) $r->rga, 2), 'totalfinal' => round((float) $r->totalfinal, 2),
        ], DB::select($sql, $bind));
    }
}
