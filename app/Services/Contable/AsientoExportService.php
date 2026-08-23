<?php

namespace App\Services\Contable;

use App\Support\Contable\TipoAsiento;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Export del listado de asientos (todo el conjunto filtrado, no sólo la página).
 *
 * Igual que en facturas de tercero y reservas: sólo CSV UTF-8 (BOM + ';', que
 * Excel abre en columnas), porque este entorno no tiene ext-gd/ext-zip y
 * PhpSpreadsheet no es instalable.
 */
class AsientoExportService
{
    public function __construct(private AsientoListadoService $listado) {}

    public function descargar(TipoAsiento $tipo, array $filtros): StreamedResponse
    {
        $filas = $this->listado->todos($tipo, $filtros);
        $totales = $this->listado->totales($tipo, $filtros);
        $columnas = $this->columnas($tipo);

        $nombre = strtoupper(str_replace('-', '_', $tipo->slug)).now()->format('_Ymd_His').'.csv';

        return new StreamedResponse(function () use ($filas, $totales, $columnas) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");

            fputcsv($out, array_keys($columnas), ';');

            foreach ($filas as $f) {
                fputcsv($out, array_map(fn ($get) => $get($f), $columnas), ';');
            }

            // Pie: un total por moneda, porque sumar monedas distintas no da nada.
            foreach ($totales['porMoneda'] as $t) {
                fputcsv($out, [
                    'Total '.$t['moneda'],
                    $t['cantidad'].' asientos',
                    '', '',
                    self::numero($t['monto']),
                    self::numero($t['montoVigente']),
                ], ';');
            }

            fclose($out);
        }, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$nombre.'"',
        ]);
    }

    /** @return array<string,callable> encabezado => extractor */
    private function columnas(TipoAsiento $tipo): array
    {
        $columnas = [
            'Número' => fn ($f) => $f['numero'],
            'Fecha' => fn ($f) => $f['fecha'],
            'Usuario' => fn ($f) => $f['usuario'],
            'Moneda' => fn ($f) => $f['moneda'],
            'Monto' => fn ($f) => self::numero($f['monto']),
            'Estado' => fn ($f) => $f['estado'],
            'Movimientos' => fn ($f) => $f['movimientos'],
            'Observaciones' => fn ($f) => $f['observaciones'],
        ];

        if ($tipo->usaProyecto()) {
            $columnas['Proyecto'] = fn ($f) => $f['proyecto'] ?? '';
        }

        return $columnas;
    }

    private static function numero(float $valor): string
    {
        return number_format($valor, 2, ',', '.');
    }
}
