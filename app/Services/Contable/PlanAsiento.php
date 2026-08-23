<?php

namespace App\Services\Contable;

use App\Exceptions\Contable\AsientoException;
use App\Support\Contable\TipoAsiento;

/**
 * Traduce lo que se cargó en la grilla a las filas de `movimiento` que hay que
 * grabar, SIN tocar la base.
 *
 * Separarlo de la persistencia es lo que permite verificar en un test que el
 * asiento balancea, que cada línea cae en la columna correcta y que las tres
 * variantes (contable, cuenta corriente, fondos) mantienen sus diferencias, sin
 * levantar una base con veinte tablas legacy. Mismo criterio que
 * FacturaproveedorAsientoService::planDeMovimientos().
 *
 * -----------------------------------------------------------------------------
 * OJO con `cuenta_credito` / `cuenta_debito`: están al revés de lo que sugiere
 * el nombre.
 *
 * En toda la familia de módulos de administración del CI (asientocontable.php:
 * 196-197, asientocta.php:206-207, contables.php:198-220) la línea del DEBE se
 * graba en `cuenta_credito` y la del HABER en `cuenta_debito`. Los reportes
 * (Balance.php:21-24, libros.php) leen esas columnas como "cuál de los dos
 * casilleros está lleno", así que la convención invertida es la correcta para
 * este módulo y darla vuelta rompería el libro mayor y el balance.
 *
 * (Ojo también con la incoherencia interna del legacy: en fondos el "ingreso"
 * también va a `cuenta_credito` pero con deha='H'. No es un error de este port,
 * viene así del CI; ver la nota en lineasDeFondos().)
 * -----------------------------------------------------------------------------
 */
class PlanAsiento
{
    /**
     * @param  array  $datos  cabecera + 'lineas'
     * @return array{
     *     monto: float,
     *     debe: float,
     *     haber: float,
     *     asientos: list<array{movimientos: list<array<string,mixed>>}>
     * }
     */
    public function construir(TipoAsiento $tipo, array $datos): array
    {
        return $tipo->esDebeHaber()
            ? $this->planDebeHaber($tipo, $datos)
            : $this->planFondos($datos);
    }

    // ------------------------------------------------------------------
    // Asiento contable / en cuenta corriente
    // ------------------------------------------------------------------

    /**
     * Un único asiento con una fila de `movimiento` por línea cargada.
     *
     * Diferencia deliberada con el legacy: acá se exige que el asiento balancee
     * y que tenga al menos una línea. En el CI eso lo controlaba únicamente el
     * JS de la grilla, así que un POST directo (o el JS fallando) grababa un
     * asiento descuadrado sin que nada avisara.
     */
    private function planDebeHaber(TipoAsiento $tipo, array $datos): array
    {
        $comunes = $this->comunes($datos);
        $movimientos = [];
        $debe = 0.0;
        $haber = 0.0;

        foreach ($this->lineasUtiles($datos) as $nro => $linea) {
            $cuenta = (int) ($linea['cuenta'] ?? 0);
            $importeDebe = $this->importe($linea['debe'] ?? null);
            $importeHaber = $this->importe($linea['haber'] ?? null);

            if ($cuenta === 0) {
                throw AsientoException::lineaInvalida($nro, 'falta la cuenta contable.');
            }
            if ($importeDebe === null && $importeHaber === null) {
                throw AsientoException::lineaInvalida($nro, 'tiene cuenta pero no tiene importe.');
            }
            if ($importeDebe !== null && $importeHaber !== null) {
                throw AsientoException::lineaInvalida($nro, 'no puede tener importe en el debe y en el haber a la vez.');
            }

            $esDebe = $importeDebe !== null;
            $monto = $esDebe ? $importeDebe : $importeHaber;

            $esDebe ? $debe += $monto : $haber += $monto;

            $mov = $comunes + [
                'fk_plancuenta_id' => $cuenta,
                // Ver la nota de arriba: debe -> cuenta_credito, haber -> cuenta_debito.
                'cuenta_credito' => $esDebe ? $cuenta : 0,
                'cuenta_debito' => $esDebe ? 0 : $cuenta,
                'tipo' => $esDebe ? $tipo->tipoDebe() : 'E',
                'deha' => $esDebe ? 'D' : 'H',
                'monto' => $monto,
                'descripcion' => (string) ($linea['descripcion'] ?? ''),
                'fk_cliente_id' => $tipo->imputa('cliente') ? (int) ($linea['cliente'] ?? 0) : 0,
                'fk_proveedor_id' => $tipo->imputa('proveedor') ? (int) ($linea['proveedor'] ?? 0) : 0,
                'fk_file_id' => $tipo->imputa('file') ? (int) ($linea['file'] ?? 0) : 0,
            ];

            // asientocontable.php:216-218: sin el tilde "tocar arqueo" el
            // movimiento nace fuera del arqueo.
            if ($tipo->usaArqueo() && ! ($datos['arqueo'] ?? true)) {
                $mov['statusmovimiento'] = 'AR';
            }

            // asientocta.php:212-214: la columna se escribe cuando el formulario
            // trajo el campo, o sea cuando la licencia lo muestra. Si no vino, se
            // deja el default de la tabla (1), igual que el legacy.
            if ($tipo->soportaAfectaCobranza() && array_key_exists('afecta_cobranza', $datos)) {
                $mov['afecta_cobranza'] = (int) (bool) $datos['afecta_cobranza'];
            }

            $movimientos[] = $mov;
        }

        if ($movimientos === []) {
            throw AsientoException::sinLineas();
        }

        $debe = round($debe, 2);
        $haber = round($haber, 2);

        if (abs($debe - $haber) >= 0.01) {
            throw AsientoException::noBalancea($debe, $haber);
        }

        return [
            // ordenadmin.monto = total del debe (asientocontable.php:142).
            'monto' => $debe,
            'debe' => $debe,
            'haber' => $haber,
            'asientos' => [['movimientos' => $movimientos]],
        ];
    }

    // ------------------------------------------------------------------
    // Movimientos de fondo
    // ------------------------------------------------------------------

    /**
     * Un asiento POR LÍNEA, cada uno con dos movimientos: la cuenta que recibe
     * y la que entrega (fondos.php:218-256).
     */
    private function planFondos(array $datos): array
    {
        $comunes = $this->comunes($datos);
        $descripcion = (string) ($datos['descripcion'] ?? '');
        $asientos = [];
        $total = 0.0;

        foreach ($this->lineasUtiles($datos) as $nro => $linea) {
            $monto = $this->importe($linea['monto'] ?? null);
            $ingreso = (int) ($linea['ingreso'] ?? 0);
            $egreso = (int) ($linea['egreso'] ?? 0);

            if ($monto === null) {
                throw AsientoException::lineaInvalida($nro, 'falta el monto.');
            }
            if ($ingreso === 0 || $egreso === 0) {
                throw AsientoException::lineaInvalida($nro, 'hay que elegir la cuenta de ingreso y la de egreso.');
            }
            if ($ingreso === $egreso) {
                throw AsientoException::lineaInvalida($nro, 'la cuenta de ingreso y la de egreso no pueden ser la misma.');
            }

            $total += $monto;
            $asientos[] = ['movimientos' => $this->lineasDeFondos($comunes, $descripcion, $monto, $ingreso, $egreso)];
        }

        if ($asientos === []) {
            throw AsientoException::sinLineas();
        }

        $total = round($total, 2);

        return [
            'monto' => $total,
            'debe' => $total,
            'haber' => $total,
            'asientos' => $asientos,
        ];
    }

    /**
     * Par ingreso/egreso de una línea de fondos.
     *
     * Dos rarezas del legacy que se replican tal cual:
     *
     *  - `fk_proveedor_id = 1`, hardcodeado (fondos.php:227). Es el proveedor
     *    "interno" con el que el CI marca los movimientos propios.
     *  - El egreso NO setea `fk_plancuenta_id` (queda en 0) y sólo llena
     *    `cuenta_debito`. Las consultas del detalle joinean con
     *    `plancuenta_id = fk_plancuenta_id OR plancuenta_id = cuenta_debito`
     *    justamente por eso (orden_model.php:105-106). Completarlo cambiaría lo
     *    que devuelven esas consultas.
     */
    private function lineasDeFondos(array $comunes, string $descripcion, float $monto, int $ingreso, int $egreso): array
    {
        $base = $comunes + [
            'descripcion' => $descripcion,
            'monto' => $monto,
            'fk_proveedor_id' => 1,
            'fk_cliente_id' => 0,
            'fk_file_id' => 0,
        ];

        return [
            // Ingreso: la cuenta que recibe los fondos.
            $base + [
                'fk_plancuenta_id' => $ingreso,
                'cuenta_credito' => $ingreso,
                'cuenta_debito' => 0,
                'tipo' => 'I',
                'deha' => 'H',
            ],
            // Egreso: la cuenta de la que salen.
            $base + [
                'fk_plancuenta_id' => 0,
                'cuenta_credito' => 0,
                'cuenta_debito' => $egreso,
                'tipo' => 'E',
                'deha' => 'D',
            ],
        ];
    }

    // ------------------------------------------------------------------
    // Internos
    // ------------------------------------------------------------------

    /** Columnas que comparten todos los movimientos del asiento. */
    private function comunes(array $datos): array
    {
        $fecha = (string) $datos['fecha'];

        return [
            'fecha' => $fecha,
            'fecha_acreditacion' => $fecha,
            'fk_moneda_id' => (string) $datos['fk_moneda_id'],
            'cotizacion_moneda' => (float) ($datos['cotizacion'] ?? 1),
            'fk_usuario_id' => (int) ($datos['fk_usuario_id'] ?? 0),
        ];
    }

    /**
     * Líneas con algo cargado, indexadas por su número visible (base 1) para
     * poder nombrarlas en los mensajes de error.
     *
     * @return array<int,array>
     */
    private function lineasUtiles(array $datos): array
    {
        $utiles = [];

        foreach (array_values((array) ($datos['lineas'] ?? [])) as $i => $linea) {
            if ($this->vacia($linea)) {
                continue;
            }

            $utiles[$i + 1] = $linea;
        }

        return $utiles;
    }

    /** Una línea sin cuenta ni importes es una fila en blanco de la grilla. */
    private function vacia(array $linea): bool
    {
        foreach (['cuenta', 'debe', 'haber', 'monto', 'ingreso', 'egreso'] as $campo) {
            $v = $linea[$campo] ?? null;

            if ($v !== null && $v !== '' && (float) $v != 0.0) {
                return false;
            }
        }

        return true;
    }

    /**
     * Importe de una celda: null si está vacía, redondeado a 2 si no.
     *
     * El 0 cuenta como vacío, igual que el `!=''` del legacy combinado con el
     * `doubleval()`: una celda en 0 no genera movimiento.
     */
    private function importe(mixed $valor): ?float
    {
        if ($valor === null || $valor === '') {
            return null;
        }

        $numero = round((float) $valor, 2);

        return $numero == 0.0 ? null : $numero;
    }
}
