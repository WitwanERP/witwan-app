<?php

namespace App\Services\Contabilidad;

use App\Helpers\SysconfigHelper;

/**
 * Cuentas contables de recibos (constante `ctasrecibos` del Admin_Controller
 * del CI): cuentarecibos, cuentarecibosusd, anticiporecibos, anticiporecibosusd
 * y auxrecibos de `sysconfig`; 54 si ninguna está configurada.
 *
 * @return list<int>
 */
class CuentasRecibosService
{
    public function ids(): array
    {
        $out = [];
        foreach (['cuentarecibos', 'cuentarecibosusd', 'anticiporecibos', 'anticiporecibosusd', 'auxrecibos'] as $k) {
            $v = (int) SysconfigHelper::get($k, 0);
            if ($v !== 0) {
                $out[$v] = $v;
            }
        }

        return $out === [] ? [54] : array_values($out);
    }
}
