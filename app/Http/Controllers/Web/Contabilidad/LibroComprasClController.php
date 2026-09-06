<?php

namespace App\Http\Controllers\Web\Contabilidad;

use App\Helpers\SysconfigHelper;
use App\Http\Controllers\Web\Reportes\ReporteController;
use Illuminate\Support\Facades\DB;

/**
 * Administración > Contabilidad > Libro de compras (CI: administracion/libros/comprascl).
 * Facturas de proveedor (Factura/Boleta/Nota de Crédito, sin retención IIBB)
 * por fecha contable, en el formato de importación del SII: tipo de documento
 * según electrónica/tipo, NC en negativo, tipo de transacción según
 * `tipomovimiento` (activo fijo e IVA no recuperable con sus columnas).
 */
class LibroComprasClController extends ReporteController
{
    protected string $titulo = 'Libro de compras';

    protected string $ruta = 'contabilidad/libro-compras';

    protected string $grupo = 'Contabilidad';

    protected ?string $agruparPor = null;

    protected function filtros(): array
    {
        return [['campo' => 'fecha', 'label' => 'Fecha contable', 'tipo' => 'rango']];
    }

    protected function columnas(): array
    {
        return [
            ['campo' => 'tipo_doc', 'label' => 'Tipo Doc'],
            ['campo' => 'folio', 'label' => 'Folio'],
            ['campo' => 'rut', 'label' => 'Rut Contraparte'],
            ['campo' => 'tasa', 'label' => 'Tasa Impuesto'],
            ['campo' => 'razon_social', 'label' => 'Razón Social Contraparte'],
            ['campo' => 'tipo_impuesto', 'label' => 'Tipo Impuesto'],
            ['campo' => 'fecha', 'label' => 'Fecha Emisión'],
            ['campo' => 'exento', 'label' => 'Monto Exento', 'tipo' => 'num', 'total' => true],
            ['campo' => 'neto', 'label' => 'Monto Neto', 'tipo' => 'num', 'total' => true],
            ['campo' => 'iva', 'label' => 'Monto IVA (Recuperable)', 'tipo' => 'num', 'total' => true],
            ['campo' => 'cod_iva_no_rec', 'label' => 'Cod IVA no Rec'],
            ['campo' => 'iva_no_rec', 'label' => 'Monto IVA no Rec', 'tipo' => 'num', 'total' => true],
            ['campo' => 'iva_uso_comun', 'label' => 'IVA Uso Común'],
            ['campo' => 'activo_fijo', 'label' => 'Monto Activo Fijo', 'tipo' => 'num'],
            ['campo' => 'iva_activo_fijo', 'label' => 'Monto IVA Activo Fijo', 'tipo' => 'num'],
            ['campo' => 'iva_no_retenido', 'label' => 'IVA No Retenido'],
            ['campo' => 'sucursal', 'label' => 'Código sucursal SII'],
            ['campo' => 'interno', 'label' => 'Número Interno'],
            ['campo' => 'emisor', 'label' => 'Emisor/Receptor'],
            ['campo' => 'total', 'label' => 'Monto Total', 'tipo' => 'num', 'total' => true],
            ['campo' => 'transaccion', 'label' => 'Tipo Transacción'],
        ];
    }

    protected function consultar(array $f): array
    {
        $tasa = (float) SysconfigHelper::get('tasageneral', 19);
        $iva = $tasa < 1 ? $tasa : $tasa / 100;

        $q = DB::table('facturaproveedor as fp')->join('proveedor as p', 'p.proveedor_id', '=', 'fp.fk_proveedor_id')
            ->where('fp.facturaproveedor_id', '<>', 0)->where('fp.retencioniibb', 0)
            ->whereIn('fp.facturaproveedor_tipodocumento', ['Factura', 'Nota de Credito', 'Boleta'])
            ->orderBy('fp.fechacontable')->orderBy('fp.facturaproveedor_id')
            ->select('fp.*', 'p.cuit', 'p.razonsocial');
        $this->rango($q, 'fp.fechacontable', $f, 'fecha');

        return $q->get()->map(function ($r) use ($iva) {
            $prf = 1;
            $electronica = (string) $r->electronica === 'Y';
            if ((string) $r->facturaproveedor_tipodocumento === 'Nota de Credito') {
                $prf = -1;
                $tipo = $electronica ? 60 : 55;
            } else {
                $tipo = $electronica ? ((string) $r->facturaproveedor_tipofactura === 'A' ? 34 : 33) : 30;
            }
            $neto = (float) $r->montogeneral;
            $exento = (float) $r->montoexento;
            $montoIva = round($neto * $iva);
            $ivaNo = 0.0;
            $activoTotal = 0.0;
            $activoIva = 0.0;
            $tipot = match ((string) $r->tipomovimiento) {
                'Activo Fijo' => 4, 'Bienes Raices' => 3, 'Supermercado' => 2, 'IVA no recuperable' => 6, default => 1,
            };
            if ($tipot === 4) {
                $activoTotal = ($neto + $exento) * $prf;
                $activoIva = round($neto * $iva * $prf);
            }
            if ($tipot === 6) {
                $ivaNo = round($neto * $iva);
                $montoIva = 0.0;
            }

            return [
                'tipo_doc' => $tipo, 'folio' => (string) $r->facturaproveedor_nro, 'rut' => (string) $r->cuit, 'tasa' => $iva * 100, 'razon_social' => (string) $r->razonsocial,
                'tipo_impuesto' => '1', 'fecha' => $this->dmy((string) $r->fecha), 'exento' => $exento * $prf, 'neto' => $neto * $prf, 'iva' => $montoIva * $prf,
                'cod_iva_no_rec' => '', 'iva_no_rec' => $ivaNo * $prf, 'iva_uso_comun' => $ivaNo != 0 ? 9 : '', 'activo_fijo' => $activoTotal, 'iva_activo_fijo' => $activoIva,
                'iva_no_retenido' => '', 'sucursal' => '', 'interno' => (int) $r->facturaproveedor_id, 'emisor' => '', 'total' => (float) $r->montototal * $prf, 'transaccion' => $tipot,
            ];
        })->all();
    }
}
