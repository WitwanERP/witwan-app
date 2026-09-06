<?php

namespace App\Http\Controllers\Web\Contabilidad;

use App\Http\Controllers\Web\Reportes\ReporteController;
use App\Services\CatalogosService;
use App\Services\Contabilidad\LibroMayorService;
use App\Services\CotizacionService;

/**
 * Administración > Contabilidad > Libro mayor (CI: administracion/libros/mayor).
 * Una fila "SALDO ANTERIOR" por cuenta con lo acumulado antes del "desde";
 * el saldo de las filas es el del período, como en el CI.
 */
class LibroMayorController extends ReporteController
{
    protected string $titulo = 'Libro mayor';

    protected string $ruta = 'contabilidad/libro-mayor';

    protected string $grupo = 'Contabilidad';

    protected ?string $agruparPor = 'cuenta';

    protected string $ayuda = 'Elegí una o más cuentas. "Acumular desde" limita los movimientos considerados para el saldo anterior.';

    public function __construct(protected CatalogosService $catalogos, protected LibroMayorService $mayor, protected CotizacionService $cotizaciones) {}

    protected function filtros(): array
    {
        return [
            ['campo' => 'cuenta', 'label' => 'Cuentas', 'tipo' => 'multi', 'opciones' => $this->catalogos->planCuentas()],
            ['campo' => 'fecha', 'label' => 'Fecha', 'tipo' => 'rango'],
            ['campo' => 'acumulado', 'label' => 'Acumular desde', 'tipo' => 'date'],
            ['campo' => 'moneda', 'label' => 'Moneda', 'tipo' => 'select', 'opciones' => $this->catalogos->monedas()],
        ];
    }

    protected function columnas(): array
    {
        return [
            ['campo' => 'fecha', 'label' => 'Fecha'],
            ['campo' => 'comprobante', 'label' => 'Comprobante', 'link' => 'comprobante_link'],
            ['campo' => 'descripcion', 'label' => 'Descripción'],
            ['campo' => 'asiento', 'label' => 'Asiento'],
            ['campo' => 'file', 'label' => 'File'],
            ['campo' => 'moneda', 'label' => 'Moneda comp.'],
            ['campo' => 'debe', 'label' => 'Debe', 'tipo' => 'num', 'total' => true],
            ['campo' => 'haber', 'label' => 'Haber', 'tipo' => 'num', 'total' => true],
            ['campo' => 'saldo', 'label' => 'Saldo', 'tipo' => 'num'],
        ];
    }

    protected function consultar(array $f): array
    {
        $cuentas = array_map('intval', (array) $f['cuenta']);
        if ($cuentas === []) {
            return [];
        }
        $moneda = $f['moneda'] !== '' ? $f['moneda'] : $this->cotizaciones->monedaBasica();
        ['filas' => $filas, 'anteriores' => $anteriores] = $this->mayor->filas($cuentas, $f['fecha'], $f['fecha_to'], $f['acumulado'], $moneda);

        $nombres = collect($this->catalogos->planCuentas(false))->pluck('label', 'value');
        $out = [];
        foreach ($cuentas as $c) {
            $out[] = ['cuenta' => (string) ($nombres[$c] ?? $c), 'cuenta_id' => $c, 'fecha' => '', 'comprobante' => 'SALDO ANTERIOR', 'comprobante_link' => null, 'descripcion' => $f['fecha'] !== '' ? 'Movimientos anteriores al '.date('d/m/Y', strtotime($f['fecha'])) : '', 'asiento' => '', 'file' => '', 'moneda' => '', 'debe' => 0.0, 'haber' => 0.0, 'saldo' => (float) ($anteriores[$c] ?? 0)];
        }

        return array_merge($out, $filas);
    }
}
