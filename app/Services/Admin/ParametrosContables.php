<?php

namespace App\Services\Admin;

use App\Helpers\SysconfigHelper;
use App\Support\Licencia;
use Illuminate\Support\Facades\DB;

/**
 * Catálogo de cuentas contables configurables por licencia (el array
 * `_cuentas_` de Admin_Controller.php:140-273) y su persistencia en `sysconfig`.
 *
 * Las claves `titulo_N` separan grupos; `adicionales_fc3` y `adicionales_vta`
 * guardan un JSON {concepto => plancuenta_id} y se editan concepto por concepto.
 */
class ParametrosContables
{
    public const ADICIONALES = ['adicionales_fc3', 'adicionales_vta'];

    /** @return array<string,string> clave => label, en el orden del CI. */
    public function catalogo(): array
    {
        if (Licencia::pais() === 'AR') {
            return [
                'cuentarecibos' => 'Cuenta ventas',
                'cuentarecibosusd' => 'Cuenta ventas USD',
                'anticiporecibos' => 'Cuenta ventas (Anticipo)',
                'anticiporecibosusd' => 'Cuenta ventas USD (Anticipo)',
                'cuentaproveedorvarios' => 'Cuenta compras (no turísticas)',
                'cuentaproveedor' => 'Cuenta compras',
                'anticipoproveedor' => 'Cuenta compras (Anticipo)',
                'cuentaproveedorusd' => 'Cuenta compras USD',
                'anticipoproveedorusd' => 'Cuenta compras USD (Anticipo)',
                'provisionBSP' => 'Provisión BSP',
                'redondeoperdida' => 'Diferencia de redondeo pérdida',
                'redondeo' => 'Diferencia de redondeo ganancia',
                'titulo_1' => 'VENTAS',
                'ventaexento' => 'Exento',
                'ventagral' => 'IVA general',
                'costoaereo' => 'Costos aéreos',
                'costoterrestre' => 'Costos terrestres',
                'ventaespecial' => 'IVA especial',
                'ventanocomputable' => 'No computable',
                'ventaivagral' => 'IVA General',
                'ventaivaespecial' => 'IVA Especial',
                'ventaperciva' => 'Perc. IVA',
                'ventaperciibb' => 'Perc. IIBB',
                'ventaotros' => 'Otros impuestos',
                'ventaivatotal' => 'IVA total',
                'ventarentaa' => 'Renta aéreos',
                'ventarentat' => 'Renta terrestres',
                'ventapercepcion' => 'Percepción 3825',
                'rgqatar' => 'Percepción 5463',
                'ventaimpuestopais' => 'Impuesto País',
                'ventagbancario' => 'Gastos bancarios',
                'ventagtarjeta' => 'Gastos de tarjeta',
                'ventagtransft' => 'Gastos de transferencia',
                'adicionales_vta' => 'Adicionales ventas',
                'titulo_2' => 'COMPRAS',
                'fc3exento' => 'Exento (3ros)',
                'fc3gral' => 'Neto gravado (3ros)',
                'fc3especial' => 'Neto gravado especial (3ros)',
                'fc3monto27' => 'Neto gravado 27% (3ros)',
                'fc3monto25' => 'Neto gravado 2.5% (3ros)',
                'fc3nocomputable' => 'No computable (3ros)',
                'fc3perciva' => 'Perc. IVA (3ros)',
                'fc3retiva' => 'Ret. IVA (3ros)',
                'fc3perciibb' => 'Perc. IIBB (3ros)',
                'fc3retiibb' => 'Ret. IIBB (3ros)',
                'fc3perganancias' => 'Perc. Ganancias (3ros)',
                'fc3retganancias' => 'Ret. Ganancias (3ros)',
                'fc3otros' => 'Otros impuestos (3ros)',
                'fc3ivatotal' => 'IVA general (3ros)',
                'ctaivatur' => 'Ivatur',
                'fc3ivatotal_i' => 'IVA general indirecto (3ros)',
                'fc3ivaespecial' => 'IVA especial (3ros)',
                'fc3ivaespecial_i' => 'IVA especial indirecto (3ros)',
                'fc3iva27' => 'IVA 27% (3ros)',
                'fc3iva25' => 'IVA 2.5% (3ros)',
                'adicionales_fc3' => 'Adicionales compras',
                'titulo_3' => 'BALANCE',
                'activo' => 'Activo',
                'pasivo' => 'Pasivo',
                'ganancia' => 'Ganancia',
                'perdida' => 'Pérdida',
            ];
        }

        $iva = Licencia::pais() === 'DO' ? 'ITBIS' : 'IVA';

        return [
            'cuentarecibos' => 'Cuenta ventas general',
            'cuentarecibosaerolineas' => 'Cuenta ventas aerolíneas',
            'cuentarecibosop' => 'Cuenta ventas operador',
            'anticiporecibos' => 'Cuenta ventas (Anticipo)',
            'anticiporecibosusd' => 'Cuenta ventas USD (Anticipo)',
            'cuentaproveedorvarios' => 'Cuenta compras (no turísticas)',
            'cuentaproveedor' => 'Cuenta compras',
            'anticipoproveedor' => 'Cuenta compras (Anticipo)',
            'cuentaproveedorusd' => 'Cuenta compras USD',
            'anticipoproveedorusd' => 'Cuenta compras USD (Anticipo)',
            'provisionBSP' => 'Provisión BSP',
            'redondeoperdida' => 'Diferencia de redondeo pérdida',
            'redondeo' => 'Diferencia de redondeo ganancia',
            'titulo_1' => 'VENTAS',
            'ventaivagral' => $iva,
            'ventarentaa' => 'Ganancia aéreos',
            'ventarentat' => 'Ganancia terrestres',
            'adicionales_vta' => 'Adicionales ventas',
            'titulo_2' => 'COMPRAS',
            'fc3otros' => 'Otros impuestos (3ros)',
            'fc3ivatotal' => "{$iva} general (3ros)",
            'fc3ivatotal_i' => "{$iva} general indirecto (3ros)",
            'fc3retiibb' => 'Ret. Honorarios',
            'adicionales_fc3' => 'Adicionales compras',
            'titulo_3' => 'BALANCE',
            'activo' => 'Activo',
            'pasivo' => 'Pasivo',
            'ganancia' => 'Ganancia',
            'perdida' => 'Pérdida',
        ];
    }

    /**
     * Catálogo agrupado por título con los valores actuales, listo para el form.
     *
     * @return list<array{titulo:string,items:list<array{clave:string,label:string,valor:int|null,adicionales?:list<array{concepto:string,valor:int|null}>}>}>
     */
    public function grupos(): array
    {
        $actual = SysconfigHelper::all() ?: [];
        $grupos = [];
        $grupo = ['titulo' => 'CUENTAS GENERALES', 'items' => []];

        foreach ($this->catalogo() as $clave => $label) {
            if (str_starts_with($clave, 'titulo_')) {
                $grupos[] = $grupo;
                $grupo = ['titulo' => $label, 'items' => []];

                continue;
            }

            if (in_array($clave, self::ADICIONALES, true)) {
                $json = json_decode((string) ($actual[$clave] ?? ''), true);
                $grupo['items'][] = [
                    'clave' => $clave,
                    'label' => $label,
                    'valor' => null,
                    'adicionales' => is_array($json)
                        ? array_map(fn ($c, $v) => ['concepto' => (string) $c, 'valor' => (int) $v ?: null], array_keys($json), $json)
                        : [],
                ];

                continue;
            }

            $valor = (int) ($actual[$clave] ?? 0);
            $grupo['items'][] = ['clave' => $clave, 'label' => $label, 'valor' => $valor ?: null];
        }
        $grupos[] = $grupo;

        return $grupos;
    }

    /**
     * Persiste con REPLACE INTO sysconfig, como el CI. Sólo toca las claves del
     * catálogo (nada de escribir claves arbitrarias en sysconfig).
     *
     * @param  array<string,mixed>  $valores  clave => plancuenta_id ('' para desasignar)
     * @param  array<string,array<string,mixed>>  $adicionales  clave => [concepto => plancuenta_id]
     */
    public function guardar(array $valores, array $adicionales): void
    {
        $catalogo = $this->catalogo();

        DB::transaction(function () use ($valores, $adicionales, $catalogo) {
            foreach ($valores as $clave => $valor) {
                if (! isset($catalogo[$clave]) || str_starts_with($clave, 'titulo_') || in_array($clave, self::ADICIONALES, true)) {
                    continue;
                }
                $this->replace($clave, (string) (int) $valor);
            }

            foreach (self::ADICIONALES as $clave) {
                if (! array_key_exists($clave, $adicionales) || ! isset($catalogo[$clave])) {
                    continue;
                }
                $mapa = [];
                foreach ((array) $adicionales[$clave] as $concepto => $valor) {
                    $mapa[(string) $concepto] = (string) (int) $valor;
                }
                $this->replace($clave, json_encode($mapa));
            }
        });
    }

    private function replace(string $clave, string $valor): void
    {
        DB::table('sysconfig')->updateOrInsert(['sysconfig_key' => $clave], ['sysconfig_value' => $valor]);
    }
}
