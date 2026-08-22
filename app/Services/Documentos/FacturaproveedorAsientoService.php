<?php

namespace App\Services\Documentos;

use App\Support\Contable\CuentasContables;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Facades\DB;

/**
 * Asiento contable de una factura de tercero.
 *
 * Réplica del bloque `foreach ($dividir as $divide)` de `factura3ero::save()`
 * (application/controllers/administracion/factura3ero.php:1115-1402): un asiento
 * con un débito por cada concepto (netos, impuestos, IVA por alícuota) y un
 * crédito por el total a la cuenta del proveedor.
 *
 * El mapa concepto -> cuenta vive en config/facturaproveedor.php en vez de estar
 * hardcodeado en dos `foreach`, y las cuentas se resuelven con CuentasContables,
 * que falla cuando una no está configurada en lugar de escribir el movimiento
 * contra la cuenta 0 como hacía el legacy.
 *
 * Las ramas `witwan_mutual` y `tower` del legacy (factura3ero.php:1126-1168 y
 * :1369-1401) quedan fuera de alcance por estar en desuso, igual que en el port
 * original de la API.
 */
class FacturaproveedorAsientoService
{
    public function __construct(private CuentasContables $cuentas) {}

    /**
     * Genera el asiento en la conexión indicada (null = la del tenant) y devuelve
     * el id del asiento creado.
     *
     * @param  float  $prc  Proporción de la factura que va a esta base (1 = todo).
     *                      Negativa en notas de crédito.
     */
    public function generar(
        ?string $conexion,
        array $datos,
        MontosFactura $m,
        FacturaproveedorCalculo $calculo,
        int $facturaId,
        float $prc,
        int $usuarioId,
    ): int {
        $db = $conexion ? DB::connection($conexion) : DB::connection();

        $fecha = $datos['fechacontable'] ?? $datos['fecha'];
        $moneda = (string) $datos['fk_moneda_id'];
        $cotizacion = (float) ($datos['cotizacion'] ?? 1);
        $proveedorId = (int) $datos['fk_proveedor_id'];
        $tipoMovimiento = (string) $datos['tipomovimiento'];

        $asientoId = (int) $db->table('asientocontable')->insertGetId([
            'asientocontable_fecha' => $fecha,
            'debe' => 0,
            'haber' => 0,
        ], 'asientocontable_id');

        foreach ($this->planDeMovimientos($datos, $m, $calculo, $prc) as $linea) {
            $this->insertarMovimiento(
                $db, $asientoId, $linea['deha'], $linea['cuenta'], $linea['monto'],
                $facturaId, $usuarioId, $proveedorId, $moneda, $cotizacion, $fecha
            );
        }

        return $asientoId;
    }

    /**
     * Arma el asiento sin tocar la base: devuelve las líneas que se van a
     * grabar.
     *
     * Separarlo de la persistencia es lo que permite verificar en un test que el
     * asiento balancea y que cada concepto pega contra la cuenta correcta, sin
     * levantar una base con veinte tablas legacy.
     *
     * @return list<array{deha: string, cuenta: int, monto: float, concepto: string}>
     */
    public function planDeMovimientos(array $datos, MontosFactura $m, FacturaproveedorCalculo $calculo, float $prc = 1.0): array
    {
        $tipoMovimiento = (string) $datos['tipomovimiento'];
        $moneda = (string) $datos['fk_moneda_id'];
        $proveedorId = (int) $datos['fk_proveedor_id'];

        $lineas = [];
        $agregar = function (string $deha, int $cuenta, float $monto, string $concepto) use (&$lineas) {
            $lineas[] = ['deha' => $deha, 'cuenta' => $cuenta, 'monto' => $monto, 'concepto' => $concepto];
        };

        // --- Débitos por montos netos ---------------------------------------
        // El legacy usa la cuenta del formulario para TODOS los netos y sólo cae
        // al mapa de sysconfig si el formulario no trajo cuenta.
        $ctaFormulario = (int) ($datos['fk_plancuenta_id'] ?? 0);
        $netos = (array) config('facturaproveedor.asiento.netos', []);

        foreach ($this->basesPorCampo($m) as $campo => $valor) {
            if ($valor == 0.0) {
                continue;
            }

            $cuenta = $ctaFormulario !== 0
                ? $ctaFormulario
                : $this->cuentas->id($netos[$campo] ?? 'fc3exento');

            $agregar('D', $cuenta, round($valor * $prc, 2), $campo);
        }

        // --- Débitos por impuestos, retenciones y percepciones ---------------
        foreach ((array) config('facturaproveedor.asiento.impuestos', []) as $campo => $def) {
            $valor = $this->valorImpuesto($m, $campo);
            if ($valor == 0.0) {
                continue;
            }

            $signo = (float) ($def['signo'] ?? 1);
            $agregar('D', $this->cuentas->id($def['cuenta']), round($valor * $prc * $signo, 2), $campo);
        }

        // --- Débitos del IVA por alícuota ------------------------------------
        $this->debitarIva($agregar, $m, $calculo, $tipoMovimiento, $prc);

        // --- Crédito por el total a la cuenta del proveedor -------------------
        $agregar('H', $this->cuentaProveedor($tipoMovimiento, $moneda, $proveedorId), $m->montototal * $prc, 'total');

        return $lineas;
    }

    /**
     * Diferencia entre débitos y créditos del plan. Debería ser 0.
     *
     * OJO: no lo es cuando monto25 != 0 y el flag `iva_monto25_al_debe` está
     * apagado — el legacy suma ese IVA al crédito del proveedor pero nunca lo
     * debita (factura3ero.php:869 vs :1281-1339). Está cubierto por test.
     */
    public function descuadre(array $lineas): float
    {
        $debe = 0.0;
        $haber = 0.0;

        foreach ($lineas as $l) {
            if ($l['deha'] === 'D') {
                $debe += $l['monto'];
            } else {
                $haber += $l['monto'];
            }
        }

        return round($debe - $haber, 2);
    }

    /**
     * Rehace el asiento de una factura: borra el anterior y genera uno nuevo.
     *
     * El legacy NO recontabiliza al editar (`factura3ero::save_after_edit()` sólo
     * hace un UPDATE de la cabecera), así que cambiar la cuenta contable dejaba
     * el movimiento apuntando a la cuenta vieja. El hermano de carga múltiple sí
     * lo hace (factura3erom.php:801-825) y se toma como especificación.
     */
    public function regenerar(
        array $datos,
        MontosFactura $m,
        FacturaproveedorCalculo $calculo,
        int $facturaId,
        float $prc,
        int $usuarioId,
    ): int {
        $this->eliminarDe($facturaId);

        return $this->generar(null, $datos, $m, $calculo, $facturaId, $prc, $usuarioId);
    }

    /**
     * Borra los movimientos de la factura y, si está habilitado, los asientos que
     * quedan sin movimientos.
     *
     * El legacy sólo limpia `movimiento` (factura3ero.php:1546-1550) y deja las
     * filas de `asientocontable` huérfanas acumulándose. Limpiarlas toca datos
     * históricos, así que va detrás de un flag.
     */
    public function eliminarDe(int $facturaId): void
    {
        $asientos = DB::table('movimiento')
            ->where('fk_facturaproveedor_id', $facturaId)
            ->distinct()
            ->pluck('fk_asientocontable_id')
            ->filter()
            ->all();

        DB::table('movimiento')->where('fk_facturaproveedor_id', $facturaId)->delete();

        if (! config('facturaproveedor.limpiar_asiento_huerfano', false) || $asientos === []) {
            return;
        }

        // Sólo los que no quedaron con movimientos de otra factura.
        $conMovimientos = DB::table('movimiento')
            ->whereIn('fk_asientocontable_id', $asientos)
            ->distinct()
            ->pluck('fk_asientocontable_id')
            ->all();

        $huerfanos = array_diff($asientos, $conMovimientos);

        if ($huerfanos !== []) {
            DB::table('asientocontable')->whereIn('asientocontable_id', $huerfanos)->delete();
        }
    }

    /** Bases imponibles indexadas por el nombre de campo del formulario. */
    private function basesPorCampo(MontosFactura $m): array
    {
        return [
            'exento' => $m->exento,
            'general' => $m->general,
            'especial' => $m->especial,
            'nocomputable' => $m->nocomputable,
            'monto27' => $m->monto27,
            'monto25' => $m->monto25,
        ];
    }

    private function valorImpuesto(MontosFactura $m, string $campo): float
    {
        return match ($campo) {
            'ivatur' => $m->ivatur,
            'retencioniva' => $m->retencioniva,
            'retencioniibb' => $m->retencioniibb,
            'retencionganancias' => $m->retencionganancias,
            'percepcioniibb' => $m->percepcioniibb,
            'percepcioniva' => $m->percepcioniva,
            'percepcionganancias' => $m->percepcionganancias,
            'otrosimpuestos' => $m->otrosimpuestos,
            default => 0.0,
        };
    }

    /**
     * Débitos del IVA. El redondeo va sobre base * alícuota * proporción, no
     * sobre el IVA ya redondeado, para no correr centavos respecto del legacy.
     */
    private function debitarIva(callable $agregar, MontosFactura $m, FacturaproveedorCalculo $calculo, string $tipoMovimiento, float $prc): void
    {
        $tasas = $calculo->tasas();
        $esGasto = $tipoMovimiento === 'Gasto';
        $iva = (array) config('facturaproveedor.asiento.iva', []);

        $cuentaIva = fn (string $clave) => $this->cuentas->id(
            $esGasto ? ($iva[$clave]['gasto'] ?? $iva[$clave]['normal']) : $iva[$clave]['normal']
        );

        // IVA general. En Chile el importe es el que cargó el usuario.
        if (($m->general * $tasas['general']) != 0) {
            $monto = $calculo->ivatotalEditable()
                ? round($m->ivaCalculado * $prc, 2)
                : round($m->general * $tasas['general'] * $prc, 2);

            $agregar('D', $cuentaIva('general'), $monto, 'iva_general');
        }

        if (round($m->especial * $tasas['especial'], 2) != 0) {
            $agregar('D', $cuentaIva('especial'), round($m->especial * $tasas['especial'] * $prc, 2), 'iva_especial');
        }

        if (round($m->monto27 * $tasas['monto27'], 2) != 0) {
            $agregar('D', $cuentaIva('monto27'), round($m->monto27 * $tasas['monto27'] * $prc, 2), 'iva_27');
        }

        // El legacy nunca debita el IVA del 2,5% aunque lo sume al crédito del
        // proveedor: el asiento queda descuadrado en ese importe. Corregirlo
        // cambia saldos históricos, así que está detrás de un flag apagado.
        if (config('facturaproveedor.iva_monto25_al_debe', false)
            && round($m->monto25 * $tasas['monto25'], 2) != 0) {
            $agregar('D', $cuentaIva('monto25'), round($m->monto25 * $tasas['monto25'] * $prc, 2), 'iva_25');
        }
    }

    /**
     * Contrapartida del total (factura3ero.php:1353-1366).
     *
     * El orden importa y es el del legacy: se parte de la cuenta por moneda, la
     * pisa el tipo de movimiento, y al final la pisa todo el proveedor BSP de la
     * licencia. Resolverlo con returns tempranos cambiaría el resultado para una
     * factura de tipo Gasto del proveedor BSP.
     */
    private function cuentaProveedor(string $tipoMovimiento, string $moneda, int $proveedorId): int
    {
        $mapa = (array) config('facturaproveedor.asiento.credito', []);

        $clave = $moneda === 'USD' && isset($mapa['USD']) ? $mapa['USD'] : $mapa['default'];

        if (isset($mapa[$tipoMovimiento])) {
            $clave = $mapa[$tipoMovimiento];
        }

        if (ProveedorBsp::es($proveedorId)) {
            $clave = $mapa['BSP'];
        }

        return $this->cuentas->id($clave);
    }

    private function insertarMovimiento(
        ConnectionInterface $db,
        int $asientoId,
        string $deha,
        int $cuenta,
        float $monto,
        int $facturaId,
        int $usuarioId,
        int $proveedorId,
        string $moneda,
        float $cotizacion,
        $fecha,
    ): void {
        $row = [
            'fk_plancuenta_id' => $cuenta,
            'fk_moneda_id' => $moneda,
            'cotizacion_moneda' => $cotizacion,
            'monto' => $monto,
            'tipo' => 'E',
            'fecha' => $fecha,
            'fecha_acreditacion' => $fecha,
            'fk_usuario_id' => $usuarioId,
            'fk_proveedor_id' => $proveedorId,
            'fk_asientocontable_id' => $asientoId,
            'fk_facturaproveedor_id' => $facturaId,
            'deha' => $deha,
        ];

        $row[$deha === 'D' ? 'cuenta_debito' : 'cuenta_credito'] = $cuenta;

        $db->table('movimiento')->insert($row);
    }
}
