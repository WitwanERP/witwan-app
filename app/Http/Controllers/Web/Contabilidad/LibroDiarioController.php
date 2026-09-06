<?php

namespace App\Http\Controllers\Web\Contabilidad;

use App\Http\Controllers\Web\Reportes\ReporteController;
use App\Services\CatalogosService;
use App\Services\Contabilidad\LibroDiarioService;
use App\Services\CotizacionService;

/** Administración > Contabilidad > Libro diario (CI: administracion/libros/diario). */
class LibroDiarioController extends ReporteController
{
    protected string $titulo = 'Libro diario';

    protected string $ruta = 'contabilidad/libro-diario';

    protected string $grupo = 'Contabilidad';

    protected ?string $agruparPor = 'asiento_clave';

    protected string $ayuda = 'Indicá un rango de fechas (ambas) o un número de asiento. Los importes se convierten a la moneda elegida (por defecto la básica).';

    public function __construct(protected CatalogosService $catalogos, protected LibroDiarioService $diario, protected CotizacionService $cotizaciones) {}

    protected function filtros(): array
    {
        return [
            ['campo' => 'fecha', 'label' => 'Fecha', 'tipo' => 'rango'],
            ['campo' => 'nroasiento', 'label' => 'Nro. de asiento', 'tipo' => 'text'],
            ['campo' => 'moneda', 'label' => 'Moneda', 'tipo' => 'select', 'opciones' => $this->catalogos->monedas()],
            ['campo' => 'facturas', 'label' => 'Sólo movimientos de facturas', 'tipo' => 'bool'],
        ];
    }

    protected function columnas(): array
    {
        return [
            ['campo' => 'asiento', 'label' => 'Asiento'],
            ['campo' => 'fecha', 'label' => 'Fecha'],
            ['campo' => 'comprobante', 'label' => 'Comprobante'],
            ['campo' => 'cuenta', 'label' => 'Cuenta'],
            ['campo' => 'file', 'label' => 'File'],
            ['campo' => 'debe', 'label' => 'Debe', 'tipo' => 'num', 'total' => true],
            ['campo' => 'haber', 'label' => 'Haber', 'tipo' => 'num', 'total' => true],
            ['campo' => 'descripcion', 'label' => 'Descripción'],
        ];
    }

    protected function consultar(array $f): array
    {
        $nro = ctype_digit((string) $f['nroasiento']) ? (int) $f['nroasiento'] : 0;
        if (($f['fecha'] === '' || $f['fecha_to'] === '') && $nro === 0) {
            return [];
        }
        $moneda = $f['moneda'] !== '' ? $f['moneda'] : $this->cotizaciones->monedaBasica();

        return $this->diario->filas($f['fecha'], $f['fecha_to'], $nro, $moneda, $f['facturas'] === '1');
    }
}
