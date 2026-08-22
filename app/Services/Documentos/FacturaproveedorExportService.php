<?php

namespace App\Services\Documentos;

use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Exporta el listado de facturas de tercero (todo el conjunto filtrado).
 *
 * NOTA DE FIDELIDAD: CI ofrece .xls (PHPExcel) y .csv desde el menú del listado
 * (Admin_Controller.php:1317). Este entorno no tiene ext-gd/ext-zip, así que
 * PhpSpreadsheet no es instalable y sólo se genera CSV UTF-8 (BOM + ';', que
 * Excel abre en columnas). Mismo criterio que ReservaExportService.
 *
 * Incluye la fila de totales al pie, como el export del legacy.
 */
class FacturaproveedorExportService
{
    public function __construct(private FacturaproveedorListadoService $listado) {}

    public function descargar(array $filtros, string $vista = 'listado'): StreamedResponse
    {
        $filas = $this->listado->todos($filtros);
        $totales = $this->listado->totales($filtros);

        $columnas = $vista === 'subdiario' ? self::columnasSubdiario() : self::columnasListado();
        $nombre = ($vista === 'subdiario' ? 'SUBDIARIO_COMPRAS' : 'FACTURAS_DE_TERCEROS').now()->format('_Ymd_His').'.csv';

        return new StreamedResponse(function () use ($filas, $totales, $columnas) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");

            fputcsv($out, array_keys($columnas), ';');

            foreach ($filas as $f) {
                fputcsv($out, array_map(fn ($get) => $get($f), $columnas), ';');
            }

            // Pie: un total por tipo de documento y el total general.
            foreach ($totales['porTipo'] as $grupo) {
                fputcsv($out, self::filaTotal($columnas, 'Total '.$grupo['tipodocumento'], $grupo), ';');
            }
            fputcsv($out, self::filaTotal($columnas, 'Total final', $totales['general']), ';');

            fclose($out);
        }, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$nombre.'"',
        ]);
    }

    /** @return array<string,callable> encabezado => extractor */
    private static function columnasListado(): array
    {
        $n = fn ($v) => number_format((float) $v, 2, ',', '.');

        return [
            'Proveedor' => fn ($f) => $f['proveedorNombre'],
            'CUIT' => fn ($f) => $f['cuit'],
            'T.Doc.' => fn ($f) => $f['tipoDocumento'],
            'Tipo' => fn ($f) => $f['tipoFactura'],
            'Numero' => fn ($f) => $f['numero'],
            'Fecha de Factura' => fn ($f) => $f['fecha'],
            'Fecha Contable' => fn ($f) => $f['fechaContable'],
            'Moneda' => fn ($f) => $f['moneda'],
            'Exento' => fn ($f) => $n($f['montos']['exento']),
            'No Comp.' => fn ($f) => $n($f['montos']['noComputable']),
            'Neto Gravado' => fn ($f) => $n($f['montos']['netoGravado']),
            'Monto General' => fn ($f) => $n($f['montos']['general']),
            'Monto 10.5%' => fn ($f) => $n($f['montos']['especial']),
            'Monto 27%' => fn ($f) => $n($f['montos']['monto27']),
            'Monto 2,5%' => fn ($f) => $n($f['montos']['monto25']),
            'IVA General' => fn ($f) => $n($f['ivas']['i21']),
            'IVA 10.5%' => fn ($f) => $n($f['ivas']['i105']),
            'IVA 27%' => fn ($f) => $n($f['ivas']['i27']),
            'IVA 2.5%' => fn ($f) => $n($f['ivas']['i25']),
            'RET. IVA' => fn ($f) => $n($f['retper']['retencionIva']),
            'PER. IVA' => fn ($f) => $n($f['retper']['percepcionIva']),
            'RET. IIBB' => fn ($f) => $n($f['retper']['retencionIibb']),
            'PER. IIBB' => fn ($f) => $n($f['retper']['percepcionIibb']),
            'RET. Gan.' => fn ($f) => $n($f['retper']['retencionGanancias']),
            'PER. Gan.' => fn ($f) => $n($f['retper']['percepcionGanancias']),
            'Otros' => fn ($f) => $n($f['retper']['otrosImpuestos']),
            'IVA TUR' => fn ($f) => $n($f['ivas']['ivaTur']),
            'TOTAL' => fn ($f) => $n($f['total']),
            'Usuario' => fn ($f) => $f['usuario'],
            'Cta Contable' => fn ($f) => $f['cuentaContable'],
            'Descripcion' => fn ($f) => $f['descripcion'],
        ];
    }

    /** Subdiario de compras: otro subconjunto de columnas (factura3ero.php:1580-1687). */
    private static function columnasSubdiario(): array
    {
        $n = fn ($v) => number_format((float) $v, 2, ',', '.');

        return [
            'Proveedor' => fn ($f) => $f['proveedorNombre'],
            'CUIT' => fn ($f) => $f['cuit'],
            'Numero' => fn ($f) => $f['numero'],
            'Fecha' => fn ($f) => $f['fecha'],
            'Exento' => fn ($f) => $n($f['montos']['exento']),
            'No Comp.' => fn ($f) => $n($f['montos']['noComputable']),
            'Neto Gr.' => fn ($f) => $n($f['montos']['netoGravado']),
            'IVA' => fn ($f) => $n($f['ivas']['idi21']),
            'IVA Gasto' => fn ($f) => $n($f['ivas']['iin21']),
            'IVA 10.5%' => fn ($f) => $n($f['ivas']['i105']),
            'IVA 27/2,5%' => fn ($f) => $n($f['ivas']['i2527']),
            'RET. IVA' => fn ($f) => $n($f['retper']['retencionIva']),
            'PER. IVA' => fn ($f) => $n($f['retper']['percepcionIva']),
            'RET. IIBB' => fn ($f) => $n($f['retper']['retencionIibb']),
            'PER. IIBB' => fn ($f) => $n($f['retper']['percepcionIibb']),
            'IVA TUR' => fn ($f) => $n($f['ivas']['ivaTur']),
            'TOTAL' => fn ($f) => $n($f['total']),
        ];
    }

    /**
     * Fila de totales: se alinea con las columnas numéricas por nombre, dejando
     * en blanco las de texto.
     */
    private static function filaTotal(array $columnas, string $etiqueta, array $totales): array
    {
        $mapa = [
            'Exento' => 'montoexento',
            'No Comp.' => 'montonocomputable',
            'Neto Gravado' => 'netogravado',
            'Neto Gr.' => 'netogravado',
            'Monto General' => 'montogeneral',
            'Monto 10.5%' => 'montoespecial',
            'Monto 27%' => 'monto27',
            'Monto 2,5%' => 'monto25',
            'IVA General' => 'i21',
            'IVA' => 'idi21',
            'IVA Gasto' => 'iin21',
            'IVA 10.5%' => 'i105',
            'IVA 27%' => 'i27',
            'IVA 2.5%' => 'i25',
            'IVA 27/2,5%' => 'i2527',
            'RET. IVA' => 'retencioniva',
            'PER. IVA' => 'percepcioniva',
            'RET. IIBB' => 'retencioniibb',
            'PER. IIBB' => 'percepcioniibb',
            'RET. Gan.' => 'retencionganancias',
            'PER. Gan.' => 'percepcionganancias',
            'Otros' => 'otrosimpuestos',
            'IVA TUR' => 'ivatur',
            'TOTAL' => 'montototal',
        ];

        $fila = [];
        $primera = true;
        foreach (array_keys($columnas) as $encabezado) {
            if ($primera) {
                $fila[] = $etiqueta;
                $primera = false;

                continue;
            }

            $clave = $mapa[$encabezado] ?? null;
            $fila[] = $clave !== null && isset($totales[$clave])
                ? number_format((float) $totales[$clave], 2, ',', '.')
                : '';
        }

        return $fila;
    }
}
