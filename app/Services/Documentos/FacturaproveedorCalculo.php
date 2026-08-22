<?php

namespace App\Services\Documentos;

use App\Exceptions\Documentos\FacturaproveedorException;
use App\Helpers\SysconfigHelper;
use App\Support\Licencia;

/**
 * Cálculo de los importes de una factura de tercero.
 *
 * Réplica de `factura3ero::save()` (application/controllers/administracion/
 * factura3ero.php:868-880) del CI legacy. Clase pura: no toca la base, no conoce
 * el Request ni al usuario autenticado, y por eso es la única pieza del módulo
 * que se puede testear de punta a punta sin infraestructura.
 *
 * `tasas()` es la ÚNICA fuente de verdad de las alícuotas: el front las recibe
 * desde acá en vez de hardcodearlas. En el legacy no era así —el JS tenía 0.21 /
 * 0.19 / 0.18 a mano (scriptfactura3ro.js:61-71) mientras el PHP usaba
 * sysconfig.tasageneral—, con lo cual una licencia con otra tasa ya divergía
 * entre lo que el usuario veía y lo que se guardaba.
 */
final class FacturaproveedorCalculo
{
    /** Campos de retención/percepción que suman al total sin generar IVA. */
    private const RETENCIONES = [
        'retencioniva',
        'retencioniibb',
        'percepcioniva',
        'percepcioniibb',
        'retencionganancias',
        'percepcionganancias',
        'otrosimpuestos',
    ];

    public function __construct(
        private readonly float $alicuotaGeneral,
        private readonly string $licPais,
        private readonly array $alicuotasFijas,
    ) {}

    /**
     * Instancia para la licencia activa.
     *
     * La alícuota general sale de `sysconfig.tasageneral` (que puede venir como
     * 21 o como 0.21, igual que `factura3ero.php:10`); si no está configurada se
     * cae al valor por país del catálogo.
     */
    public static function paraLicenciaActual(): self
    {
        $pais = Licencia::pais() ?: 'AR';

        $porPais = (float) (config("facturaproveedor.alicuota_general.{$pais}")
            ?? config('facturaproveedor.alicuota_general.AR', 0.21));

        $tasa = SysconfigHelper::get('tasageneral');
        $general = $tasa !== null && $tasa !== '' ? self::normalizarTasa((float) $tasa) : $porPais;

        return new self($general, $pais, (array) config('facturaproveedor.alicuotas_fijas', []));
    }

    /**
     * `tasageneral` se carga indistintamente como porcentaje (19) o como
     * coeficiente (0.19). El legacy resuelve la ambigüedad con el mismo umbral.
     */
    private static function normalizarTasa(float $tasa): float
    {
        return $tasa < 1 ? $tasa : $tasa / 100;
    }

    /**
     * Alícuotas y reglas de redondeo vigentes, para que el front calcule igual
     * que el servidor sin conocer ninguna constante.
     */
    public function tasas(): array
    {
        return [
            'general' => $this->alicuotaGeneral,
            'especial' => $this->alicuota('especial'),
            'monto27' => $this->alicuota('monto27'),
            'monto25' => $this->alicuota('monto25'),
            'decimales' => $this->decimales(),
            'ivatotalEditable' => $this->ivatotalEditable(),
            'ivaGeneralEntero' => in_array($this->licPais, (array) config('facturaproveedor.iva_general_entero', []), true),
        ];
    }

    /** Decimales del redondeo del IVA (0 en Chile, 2 en el resto). */
    public function decimales(): int
    {
        return (int) (config("facturaproveedor.decimales_iva.{$this->licPais}") ?? 2);
    }

    /** True donde el IVA lo carga el usuario y el servidor lo respeta. */
    public function ivatotalEditable(): bool
    {
        return in_array($this->licPais, (array) config('facturaproveedor.ivatotal_editable', []), true);
    }

    /**
     * Calcula todos los importes derivados.
     *
     * @param  array<string,mixed>  $datos  Campos del formulario. Se aceptan
     *                                      claves ausentes: valen 0.
     * @param  array<string,mixed>  $adicionales  Conceptos extra de la licencia
     *                                            (sysconfig.adicionales_fc3). Si vienen,
     *                                            su suma REEMPLAZA a `exento`.
     *
     * @throws FacturaproveedorException si el total resulta 0.
     */
    public function calcular(array $datos, array $adicionales = []): MontosFactura
    {
        $exento = $this->exentoEfectivo($datos, $adicionales);

        $nocomputable = $this->num($datos, 'nocomputable');
        $especial = $this->num($datos, 'especial');
        $general = $this->num($datos, 'general');
        $monto27 = $this->num($datos, 'monto27');
        $monto25 = $this->num($datos, 'monto25');
        $ivatur = $this->num($datos, 'ivatur');

        $montosiniva = $nocomputable + $exento + $especial + $general + $monto27 + $monto25;

        // Cada componente se redondea por separado, no la suma: cambiarlo mueve
        // los centavos respecto del legacy (factura3ero.php:869).
        $ivaEspecial = round($especial * $this->alicuota('especial'), 2);
        $ivaGeneral = round($general * $this->alicuotaGeneral, 2);
        $iva27 = round($monto27 * $this->alicuota('monto27'), 2);
        $iva25 = round($monto25 * $this->alicuota('monto25'), 2);

        $ivaCalculado = $ivaEspecial + $ivaGeneral + $iva27 + $iva25;
        $soloiva = $ivaCalculado - round($ivatur, 2);

        // Donde el IVA es editable (Chile) manda lo cargado por el usuario, y el
        // IVA turismo no se descuenta. Fiel a factura3ero.php:870-872.
        if ($this->ivatotalEditable()) {
            $ivaCalculado = $this->num($datos, 'ivatotal');
            $soloiva = round($ivaCalculado, $this->decimales());
        }

        $retper = 0.0;
        $retenciones = [];
        foreach (self::RETENCIONES as $campo) {
            $retenciones[$campo] = $this->num($datos, $campo);
            $retper += $retenciones[$campo];
        }

        $montototal = $montosiniva + $soloiva + $retper;
        $montoperc = $montosiniva + $retper;

        if (round($montototal, 2) == 0.0) {
            throw FacturaproveedorException::totalCero();
        }

        return new MontosFactura(
            exento: $exento,
            nocomputable: $nocomputable,
            especial: $especial,
            general: $general,
            monto27: $monto27,
            monto25: $monto25,
            ivaGeneral: $ivaGeneral,
            ivaEspecial: $ivaEspecial,
            iva27: $iva27,
            iva25: $iva25,
            ivaCalculado: $ivaCalculado,
            ivatur: $ivatur,
            retencioniva: $retenciones['retencioniva'],
            retencioniibb: $retenciones['retencioniibb'],
            percepcioniva: $retenciones['percepcioniva'],
            percepcioniibb: $retenciones['percepcioniibb'],
            retencionganancias: $retenciones['retencionganancias'],
            percepcionganancias: $retenciones['percepcionganancias'],
            otrosimpuestos: $retenciones['otrosimpuestos'],
            montosiniva: $montosiniva,
            soloiva: $soloiva,
            retper: $retper,
            montototal: $montototal,
            montoperc: $montoperc,
        );
    }

    /**
     * Los conceptos adicionales de la licencia REEMPLAZAN al campo exento (no se
     * suman encima). En el legacy lo hacía el navegador: la clase `.sumarexento`
     * pisa el input `exento` con el total de los conceptos
     * (scriptfactura3ro.js:31-38), así que el servidor recibía el valor ya
     * resuelto y nunca lo verificaba. Replicarlo acá cierra el agujero para
     * cualquier cliente que postee los adicionales sin recalcular exento.
     */
    private function exentoEfectivo(array $datos, array $adicionales): float
    {
        if ($adicionales === []) {
            return $this->num($datos, 'exento');
        }

        return array_sum(array_map(fn ($v) => (float) ($v ?: 0), $adicionales));
    }

    private function alicuota(string $clave): float
    {
        return (float) ($this->alicuotasFijas[$clave] ?? 0);
    }

    private function num(array $datos, string $campo): float
    {
        return (float) ($datos[$campo] ?? 0);
    }
}
