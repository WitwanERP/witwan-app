<?php

namespace App\Http\Controllers\Web\Contabilidad;

use App\Helpers\SysconfigHelper;
use App\Http\Controllers\Web\Reportes\ReporteController;
use Illuminate\Support\Facades\DB;

/**
 * Administración > Contabilidad > Libro de ventas (CI: administracion/libros/ventascl).
 * Formato de importación del SII (Chile): facturas 33/34, notas de crédito 60
 * (en negativo, con referencia del `remitofull`) y notas de débito 56. La tasa
 * sale de `sysconfig.tasageneral` (el CI tenía 19 fijo).
 */
class LibroVentasClController extends ReporteController
{
    protected string $titulo = 'Libro de ventas';

    protected string $ruta = 'contabilidad/libro-ventas';

    protected string $grupo = 'Contabilidad';

    protected ?string $agruparPor = null;

    protected function filtros(): array
    {
        return [['campo' => 'fecha', 'label' => 'Fecha de emisión', 'tipo' => 'rango']];
    }

    protected function columnas(): array
    {
        return [
            ['campo' => 'tipo_doc', 'label' => 'Tipo Doc'],
            ['campo' => 'folio', 'label' => 'Folio'],
            ['campo' => 'rut', 'label' => 'Rut Contraparte'],
            ['campo' => 'tasa', 'label' => 'Tasa Impuesto'],
            ['campo' => 'razon_social', 'label' => 'Razón Social Contraparte'],
            ['campo' => 'fecha', 'label' => 'Fecha Emisión'],
            ['campo' => 'exento', 'label' => 'Monto Exento', 'tipo' => 'num', 'total' => true],
            ['campo' => 'neto', 'label' => 'Monto Neto', 'tipo' => 'num', 'total' => true],
            ['campo' => 'iva', 'label' => 'Monto IVA', 'tipo' => 'num', 'total' => true],
            ['campo' => 'reserva', 'label' => 'Reserva'],
            ['campo' => 'sucursal', 'label' => 'Código sucursal SII'],
            ['campo' => 'tipo_ref', 'label' => 'Tipo Doc Referencia'],
            ['campo' => 'folio_ref', 'label' => 'Folio Documento Referencia'],
            ['campo' => 'interno', 'label' => 'Número Interno'],
            ['campo' => 'total', 'label' => 'Monto Total', 'tipo' => 'num', 'total' => true],
            ['campo' => 'transaccion', 'label' => 'Tipo Transacción'],
        ];
    }

    protected function consultar(array $f): array
    {
        $tasa = (float) SysconfigHelper::get('tasageneral', 19);
        $iva = $tasa < 1 ? $tasa : $tasa / 100;
        $out = [];

        $q = DB::table('factura')->join('cliente', 'cliente.cliente_id', '=', 'factura.fk_cliente_id')->where('cliente.cliente_id', '<>', 0)
            ->whereNotIn('factura.factura_tipo', ['X', 'C'])->whereNotIn('factura.statusfactura', ['NU', 'PE'])->orderBy('factura.factura_fecha')
            ->select('factura.*', 'cliente.cuit', 'cliente.cliente_razonsocial');
        $this->rango($q, 'factura.factura_fecha', $f, 'fecha', true);
        foreach ($q->get() as $r) {
            $out[] = $this->fila(match ((string) $r->factura_tipo) {
                'A' => 33, 'B' => 34, default => ''
            }, $r->factura_nro, $r, (string) $r->factura_fecha,
                (float) $r->factura_conceptos_exentos, (float) $r->factura_conceptos_gravados, $iva, 1, (string) $r->factura_sucursal, (int) $r->factura_id, '', '');
        }

        $q = DB::table('notacredito')->join('cliente', 'cliente.cliente_id', '=', 'notacredito.fk_cliente_id')->where('cliente.cliente_id', '<>', 0)
            ->where('notacredito.notacredito_tipo', '<>', 'X')->whereNotIn('notacredito.statusfactura', ['NU', 'PE'])->orderBy('notacredito.notacredito_fecha')
            ->select('notacredito.*', 'cliente.cuit', 'cliente.cliente_razonsocial');
        $this->rango($q, 'notacredito.notacredito_fecha', $f, 'fecha', true);
        foreach ($q->get() as $r) {
            [$tipoRef, $ref] = $this->referencia((string) $r->remitofull);
            $out[] = $this->fila(60, $r->notacredito_nro, $r, (string) $r->notacredito_fecha, (float) $r->notacredito_conceptos_exentos, (float) $r->notacredito_conceptos_gravados, $iva, -1, '', (int) $r->notacredito_id, $tipoRef, $ref);
        }

        $q = DB::table('notadebito')->join('cliente', 'cliente.cliente_id', '=', 'notadebito.fk_cliente_id')->where('cliente.cliente_id', '<>', 0)
            ->whereNotIn('notadebito.statusfactura', ['NU', 'PE'])->orderBy('notadebito.notadebito_fecha')
            ->select('notadebito.*', 'cliente.cuit', 'cliente.cliente_razonsocial');
        $this->rango($q, 'notadebito.notadebito_fecha', $f, 'fecha', true);
        foreach ($q->get() as $r) {
            [$tipoRef, $ref] = $this->referencia((string) $r->remitofull);
            $out[] = $this->fila(56, $r->notadebito_nro, $r, (string) $r->notadebito_fecha, (float) $r->notadebito_conceptos_exentos, (float) $r->notadebito_conceptos_gravados, $iva, 1, '', (int) $r->notadebito_id, $tipoRef, $ref);
        }

        return $out;
    }

    private function fila(int|string $tipo, string $folio, object $r, string $fecha, float $exento, float $neto, float $iva, int $signo, string $sucursal, int $interno, string $tipoRef, string $ref): array
    {
        return [
            'tipo_doc' => $tipo, 'folio' => $folio, 'rut' => (string) $r->cuit, 'tasa' => $iva * 100, 'razon_social' => (string) $r->cliente_razonsocial,
            'fecha' => $this->dmy($fecha), 'exento' => $exento * $signo, 'neto' => $neto * $signo, 'iva' => round($neto * $iva) * $signo,
            'reserva' => (int) $r->fk_file_id, 'sucursal' => $sucursal, 'tipo_ref' => $tipoRef, 'folio_ref' => $ref, 'interno' => $interno,
            'total' => ($exento + round($neto * (1 + $iva))) * $signo, 'transaccion' => 1,
        ];
    }

    /** @return array{0:string,1:string} */
    private function referencia(string $remitofull): array
    {
        $rm = json_decode($remitofull, true);
        $ref = is_array($rm) ? ($rm['referencia'] ?? null) : null;

        return is_array($ref) ? [(string) ($ref['referenciatipo'] ?? ''), (string) ($ref['referencianro'] ?? '')] : ['', ''];
    }
}
