<?php

namespace App\Services\Documentos;

/**
 * Resultado del cálculo de una factura de tercero.
 *
 * Existe para que cálculo, persistencia y asiento contable compartan un contrato
 * en vez de pasarse floats sueltos: `generarAsiento()` del port original recibía
 * diez argumentos posicionales, y cualquier reordenamiento pasaba desapercibido.
 *
 * Todos los importes están en la moneda de la factura (sin aplicar cotización).
 */
final readonly class MontosFactura
{
    public function __construct(
        // Bases imponibles, ya normalizadas (exento puede venir de los adicionales).
        public float $exento,
        public float $nocomputable,
        public float $especial,
        public float $general,
        public float $monto27,
        public float $monto25,
        // IVA discriminado por alícuota.
        public float $ivaGeneral,
        public float $ivaEspecial,
        public float $iva27,
        public float $iva25,
        /** Suma de los cuatro IVA, SIN restar el IVA turismo. Es lo que va a la columna `ivatotal`. */
        public float $ivaCalculado,
        public float $ivatur,
        // Retenciones y percepciones.
        public float $retencioniva,
        public float $retencioniibb,
        public float $percepcioniva,
        public float $percepcioniibb,
        public float $retencionganancias,
        public float $percepcionganancias,
        public float $otrosimpuestos,
        // Agregados.
        public float $montosiniva,
        /** IVA que entra al total: ivaCalculado - ivatur (o el IVA cargado a mano en Chile). */
        public float $soloiva,
        public float $retper,
        public float $montototal,
        public float $montoperc,
    ) {}

    /** Serialización para el front y para la API. */
    public function toArray(): array
    {
        return [
            'exento' => $this->exento,
            'nocomputable' => $this->nocomputable,
            'especial' => $this->especial,
            'general' => $this->general,
            'monto27' => $this->monto27,
            'monto25' => $this->monto25,
            'ivaGeneral' => $this->ivaGeneral,
            'ivaEspecial' => $this->ivaEspecial,
            'iva27' => $this->iva27,
            'iva25' => $this->iva25,
            'ivaCalculado' => $this->ivaCalculado,
            'ivatur' => $this->ivatur,
            'retencioniva' => $this->retencioniva,
            'retencioniibb' => $this->retencioniibb,
            'percepcioniva' => $this->percepcioniva,
            'percepcioniibb' => $this->percepcioniibb,
            'retencionganancias' => $this->retencionganancias,
            'percepcionganancias' => $this->percepcionganancias,
            'otrosimpuestos' => $this->otrosimpuestos,
            'montosiniva' => $this->montosiniva,
            'soloiva' => $this->soloiva,
            'retper' => $this->retper,
            'montototal' => $this->montototal,
            'montoperc' => $this->montoperc,
        ];
    }

    /**
     * Mapa columna de `facturaproveedor` => valor, para el INSERT.
     *
     * Los nombres de columna no siguen a los del formulario: el campo `exento`
     * se guarda en `montoexento`, `general` en `montogeneral`, etc.
     * (factura3ero.php:905-958).
     */
    public function columnasFactura(): array
    {
        return [
            'montoexento' => $this->exento,
            'montonocomputable' => $this->nocomputable,
            'montoespecial' => $this->especial,
            'montogeneral' => $this->general,
            'monto27' => $this->monto27,
            'monto25' => $this->monto25,
            'ivatotal' => $this->ivaCalculado,
            'ivatur' => $this->ivatur,
            'retencioniva' => $this->retencioniva,
            'retencioniibb' => $this->retencioniibb,
            'percepcioniva' => $this->percepcioniva,
            'percepcioniibb' => $this->percepcioniibb,
            'retencionganancias' => $this->retencionganancias,
            'percepcionganancias' => $this->percepcionganancias,
            'otrosimpuestos' => $this->otrosimpuestos,
            'montototal' => $this->montototal,
        ];
    }
}
