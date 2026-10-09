<?php

namespace App\Services\Reservas\MapaContable;

use App\Services\CotizacionService;

/**
 * Tipos de cambio del mapa contable, siempre expresados como "cuántas unidades de
 * moneda básica vale una unidad de la moneda" y siempre con su origen, porque
 * la mitad de los desvíos de cambio se explican por una cotización inferida.
 *
 * Cómo guarda la cotización cada comprobante (relevado en el CI):
 *
 *  - servicio.cotventa / cotcosto: moneda del servicio → básica. En 0 se cae a
 *    la tabla `cotizacion` a la fecha de alta del file, igual que
 *    Analitica_model::sql_c1/sql_c2 (que espeja rentamt).
 *  - factura.factura_tipo_cambio: el TC que se tipeó al facturar
 *    (factura.php:962-971). En una factura en moneda extranjera es esa moneda →
 *    básica; en una factura en moneda básica de servicios en dólares es el TC
 *    con que se convirtieron esos servicios (o 1 si no se tipeó ninguno).
 *  - facturaproveedor.cotizacion: moneda de la factura → básica cuando no es la
 *    básica (factura3ero.php:57, `IF(fk_moneda_id != @monedabasica, cotizacion, 1)`).
 *  - ordenadmin.cotizacion de la OP: el TC del pago, el mismo que se graba en
 *    movimiento.cotizacion_moneda (ordenservicio.php:375 y :451). Si la OP es en
 *    moneda básica y paga un servicio en dólares, es el TC con que se convirtió.
 *  - recibo: no guarda TC; sale de movimiento.cotizacion_moneda del asiento.
 */
final class Tasas
{
    public const BASICA = 'basica';

    public const DOCUMENTO = 'documento';

    public const SERVICIO = 'servicio';

    public const IMPLICITA = 'implicita';

    public const TABLA = 'tabla';

    public const FALTANTE = 'faltante';

    /** @var array<string,float> */
    private array $memo = [];

    public function __construct(private CotizacionService $cotizaciones, public readonly string $basica) {}

    /**
     * Tasa de un comprobante a moneda básica.
     *
     * @return array{0:float,1:string} [tasa, origen]
     */
    public function delDocumento(string $monedaDoc, float $tcDoc, ?string $fecha, bool $alCosto = false): array
    {
        if ($monedaDoc === '' || $monedaDoc === $this->basica) {
            return [1.0, self::BASICA];
        }
        if ($tcDoc > 0) {
            return [$tcDoc, self::DOCUMENTO];
        }

        return $this->deTabla($monedaDoc, $fecha, $alCosto);
    }

    /**
     * Tasa a la que un comprobante convirtió un ítem en $monedaItem.
     *
     * @return array{0:float,1:string}
     */
    public function paraItem(string $monedaItem, string $monedaDoc, float $tcDoc, ?string $fecha, bool $alCosto = false): array
    {
        if ($monedaItem === '' || $monedaItem === $this->basica) {
            return [1.0, self::BASICA];
        }
        if ($monedaItem === $monedaDoc) {
            return $this->delDocumento($monedaDoc, $tcDoc, $fecha, $alCosto);
        }
        // Comprobante en moneda básica de un ítem en moneda extranjera: el TC del
        // comprobante es el que se usó para convertir. Un 1 (o 0) significa que no
        // se tipeó ninguno.
        if ($monedaDoc === $this->basica && $tcDoc > 1) {
            return [$tcDoc, self::DOCUMENTO];
        }

        return $this->deTabla($monedaItem, $fecha, $alCosto);
    }

    /**
     * Tasa de un servicio (cotventa/cotcosto) con el fallback de Analitica_model.
     *
     * @return array{0:float,1:string}
     */
    public function delServicio(string $moneda, float $cotizacion, ?string $fechaAlta): array
    {
        if ($moneda === '' || $moneda === $this->basica) {
            return [1.0, self::BASICA];
        }
        if ($cotizacion != 0) {
            return [$cotizacion, self::SERVICIO];
        }

        // sql_c1/sql_c2 usan cotizacion_relacion tanto para venta como para costo.
        return $this->deTabla($moneda, $fechaAlta, false);
    }

    /** @return array{0:float,1:string} */
    public function deTabla(string $moneda, ?string $fecha, bool $alCosto = false): array
    {
        $clave = ($alCosto ? 'c' : 'v').'|'.$moneda.'|'.substr((string) $fecha, 0, 10);
        if (! array_key_exists($clave, $this->memo)) {
            $this->memo[$clave] = $alCosto
                ? $this->cotizaciones->alCosto($moneda, $fecha ?: null)
                : $this->cotizaciones->aLaVenta($moneda, $fecha ?: null);
        }
        $v = $this->memo[$clave];

        return $v > 0 ? [$v, self::TABLA] : [0.0, self::FALTANTE];
    }

    /**
     * Importe de un comprobante expresado en la moneda de un ítem.
     *
     * @param  float  $tasaDoc  tasa del comprobante a básica
     * @param  float  $tasaItem  tasa del ítem a básica según ese comprobante
     */
    public static function cantidad(float $monto, string $monedaDoc, float $tasaDoc, string $monedaItem, float $tasaItem): float
    {
        if ($monedaDoc === $monedaItem) {
            return $monto;
        }

        return $tasaItem > 0 ? $monto * $tasaDoc / $tasaItem : 0.0;
    }
}
