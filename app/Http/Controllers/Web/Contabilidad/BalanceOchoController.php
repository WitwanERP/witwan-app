<?php

namespace App\Http\Controllers\Web\Contabilidad;

use App\Http\Controllers\Web\Reportes\ReporteController;
use App\Services\Contabilidad\BalanceOchoService;

/** Administración > Contabilidad > Balance 8 col. (CI: administracion/Balance). */
class BalanceOchoController extends ReporteController
{
    protected string $titulo = 'Balance de 8 columnas';

    protected string $ruta = 'contabilidad/balance-8';

    protected string $grupo = 'Contabilidad';

    protected ?string $agruparPor = null;

    protected string $ayuda = 'Las columnas patrimoniales salen de las cuentas raíz configuradas en sysconfig (activo, pasivo, ganancia, perdida). Importes en moneda básica.';

    public function __construct(protected BalanceOchoService $balance) {}

    protected function filtros(): array
    {
        return [['campo' => 'fecha', 'label' => 'Período', 'tipo' => 'rango']];
    }

    protected function columnas(): array
    {
        return [
            ['campo' => 'codigo', 'label' => 'Código'],
            ['campo' => 'nombre', 'label' => 'Cuenta', 'tipo' => 'pre'],
            ['campo' => 'debe', 'label' => 'Debe', 'tipo' => 'num'],
            ['campo' => 'haber', 'label' => 'Haber', 'tipo' => 'num'],
            ['campo' => 'deudor', 'label' => 'Deudor', 'tipo' => 'num'],
            ['campo' => 'acreedor', 'label' => 'Acreedor', 'tipo' => 'num'],
            ['campo' => 'activo', 'label' => 'Activo', 'tipo' => 'num'],
            ['campo' => 'pasivo', 'label' => 'Pasivo', 'tipo' => 'num'],
            ['campo' => 'perdida', 'label' => 'Pérdida', 'tipo' => 'num'],
            ['campo' => 'ganancia', 'label' => 'Ganancia', 'tipo' => 'num'],
        ];
    }

    protected function consultar(array $f): array
    {
        return $f['fecha'] === '' ? [] : $this->balance->filas($f['fecha'], $f['fecha_to']);
    }
}
