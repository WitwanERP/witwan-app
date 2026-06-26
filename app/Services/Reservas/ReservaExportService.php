<?php

namespace App\Services\Reservas;

use App\Models\User;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Exporta el listado de reservas (todo el resultset filtrado, sin paginar).
 *
 * NOTA DE FIDELIDAD: CI genera un .xls (PHPExcel/BIFF). El entorno PHP de este
 * proyecto no tiene ext-gd/ext-zip, así que PhpSpreadsheet (xlsx) no es
 * instalable. Se exporta CSV UTF-8 (BOM + delimitador ';', que Excel abre como
 * columnas) con EXACTAMENTE las mismas columnas que CI (reserva.php:6058-6078).
 * Es la única diferencia respecto del legacy.
 */
class ReservaExportService
{
    /** Columnas en el mismo orden que el export de CI. */
    private const COLUMNAS = [
        'File', 'Codigo externo', 'Negocio', 'Facturado', 'Status', 'Cliente', 'Agente',
        'Usuario Cliente', 'Titular', 'Servicios', 'Dev. IVA', 'Alta', 'Vencimiento',
        'Fecha in', 'Fecha Out', 'Moneda', 'Total', 'Saldo', 'Cobrado',
    ];

    public function __construct(private ReservaListadoService $listado) {}

    public function descargar(array $filtros, ?User $usuario): StreamedResponse
    {
        $resultado = $this->listado->todos($filtros, $usuario);
        $filas = $resultado['registros'];

        $nombre = 'RESERVAS'.now()->format('YmdHis').'.csv';

        return new StreamedResponse(function () use ($filas) {
            $out = fopen('php://output', 'w');
            // BOM UTF-8 para que Excel respete los acentos.
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, self::COLUMNAS, ';');

            foreach ($filas as $f) {
                fputcsv($out, [
                    $f['codigo'],
                    $f['codigo_externo'],
                    $f['negocio_nombre'],
                    $f['status_factura'],
                    $f['status'],
                    $f['cliente_nombre'],
                    $f['nagente'],
                    '', // Usuario Cliente: query por fila en CI; se omite (fase 2)
                    $f['titular'],
                    implode(' // ', $f['serviciostxt']),
                    '', // Dev. IVA: depende de extra rg1043 del servicio (fase 2)
                    $f['fecha_alta'],
                    $f['fecha_vencimiento'],
                    $f['fecha_in'],
                    $f['fecha_out'],
                    $f['moneda'],
                    number_format((float) $f['total'], 2, ',', '.'),
                    number_format((float) $f['saldo'], 2, ',', '.'),
                    number_format((float) $f['cobrado'], 2, ',', '.'),
                ], ';');
            }

            fclose($out);
        }, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$nombre}\"",
        ]);
    }
}
