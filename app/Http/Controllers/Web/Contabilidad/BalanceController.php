<?php

namespace App\Http\Controllers\Web\Contabilidad;

use App\Http\Controllers\Web\Reportes\ReporteController;
use App\Services\Contabilidad\BalanceService;

/** Administración > Contabilidad > Balance de sumas y saldos (CI: administracion/libros/balance). */
class BalanceController extends ReporteController
{
    protected string $titulo = 'Balance de sumas y saldos';

    protected string $ruta = 'contabilidad/balance';

    protected string $grupo = 'Contabilidad';

    protected ?string $agruparPor = null;

    protected string $ayuda = 'Debe/Haber del período, Acumulado = saldo anterior al "desde" (limitado por "Acumular desde"), Saldo = Debe − Haber + Acumulado. Las cuentas hoja suman a sus totalizadoras. Importes en moneda básica.';

    public function __construct(protected BalanceService $balance) {}

    protected function filtros(): array
    {
        return [
            ['campo' => 'fecha', 'label' => 'Período', 'tipo' => 'rango'],
            ['campo' => 'acumulado', 'label' => 'Acumular desde', 'tipo' => 'date'],
            ['campo' => 'ocultar_ceros', 'label' => 'Ocultar cuentas en cero', 'tipo' => 'bool'],
        ];
    }

    protected function columnas(): array
    {
        return [
            ['campo' => 'codigo', 'label' => 'Código'],
            ['campo' => 'nombre', 'label' => 'Cuenta'],
            ['campo' => 'debe', 'label' => 'Debe', 'tipo' => 'num'],
            ['campo' => 'haber', 'label' => 'Haber', 'tipo' => 'num'],
            ['campo' => 'saldo', 'label' => 'Saldo', 'tipo' => 'num'],
            ['campo' => 'anterior', 'label' => 'Acumulado', 'tipo' => 'num'],
        ];
    }

    protected function consultar(array $f): array
    {
        if ($f['fecha'] === '' && $f['fecha_to'] === '') {
            return [];
        }
        $filas = $this->balance->filas($f['fecha'], $f['fecha_to'], $f['acumulado']);
        if ($f['ocultar_ceros'] === '1') {
            $filas = array_values(array_filter($filas, fn ($c) => $c['debe'] != 0 || $c['haber'] != 0 || $c['saldo'] != 0 || $c['anterior'] != 0));
        }

        return array_map(function ($c) {
            $c['nombre'] = ($c['tiene_hijas'] || $c['titulo'] ? '▸ ' : '').$c['nombre'];

            return $c;
        }, $filas);
    }
}
