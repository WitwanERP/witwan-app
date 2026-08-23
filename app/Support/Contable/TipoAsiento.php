<?php

namespace App\Support\Contable;

use App\Support\Licencia;

/**
 * Uno de los tres tipos de asiento de administración, resuelto desde
 * config/asientos.php.
 *
 * El CI tiene tres controllers casi idénticos (asientocontable, asientocta,
 * fondos) que sólo se diferencian en el `ordenadmin.tipo`, en un par de columnas
 * de la grilla y en qué hacen al anular. Esta clase concentra esas diferencias
 * para que el resto del módulo no vuelva a bifurcarse en tres.
 *
 * El slug es lo que viaja por la URL (`/app/contabilidad/asientos/{slug}`); el
 * código de una letra es lo que va a la base y NO se puede cambiar.
 */
final class TipoAsiento
{
    private function __construct(
        public readonly string $slug,
        private readonly array $def,
    ) {}

    /** @throws \InvalidArgumentException si el slug no está declarado. */
    public static function desde(string $slug): self
    {
        $def = config("asientos.tipos.{$slug}");

        if (! is_array($def)) {
            throw new \InvalidArgumentException("Tipo de asiento desconocido: '{$slug}'.");
        }

        return new self($slug, $def);
    }

    /** @return list<string> */
    public static function slugs(): array
    {
        return array_keys((array) config('asientos.tipos', []));
    }

    /** Expresión para el `where` de la ruta: 'contable|cuenta-corriente|fondos'. */
    public static function patronDeRuta(): string
    {
        return implode('|', self::slugs());
    }

    /** Valor de `ordenadmin.tipo` ('A', 'C' o 'M'). */
    public function codigo(): string
    {
        return (string) $this->def['codigo'];
    }

    public function titulo(): string
    {
        return (string) $this->def['titulo'];
    }

    public function singular(): string
    {
        return (string) $this->def['singular'];
    }

    /** URI del CI con la que se resuelve el `fk_seccion_id` de permisos. */
    public function seccion(): string
    {
        return (string) $this->def['seccion'];
    }

    /** Ruta del módulo equivalente en el CI legacy (para el link "ver en el sistema viejo"). */
    public function rutaLegacy(): string
    {
        return (string) $this->def['legacy'];
    }

    public function baseUrl(): string
    {
        return '/app/contabilidad/asientos/'.$this->slug;
    }

    /** 'debe-haber' (contable y cuenta corriente) o 'ingreso-egreso' (fondos). */
    public function grilla(): string
    {
        return (string) $this->def['grilla'];
    }

    public function esDebeHaber(): bool
    {
        return $this->grilla() === 'debe-haber';
    }

    /** Columnas de imputación extra de la grilla: cliente, proveedor, file. */
    public function imputa(string $que): bool
    {
        return in_array($que, (array) $this->def['imputa'], true);
    }

    /** @return list<string> */
    public function imputaciones(): array
    {
        return array_values((array) $this->def['imputa']);
    }

    /** `movimiento.tipo` de las líneas del debe. Ver la nota en config/asientos.php. */
    public function tipoDebe(): ?string
    {
        return $this->def['tipo_debe'];
    }

    public function usaArqueo(): bool
    {
        return (bool) $this->def['arqueo'];
    }

    public function usaProyecto(): bool
    {
        return (bool) $this->def['proyecto'];
    }

    /**
     * El campo "afecta cobranza" sólo existe en cuenta corriente y sólo si la
     * licencia lo tiene prendido (`botonafectacobranza`, formasientocta.php:66).
     */
    public function usaAfectaCobranza(): bool
    {
        return (bool) $this->def['afecta_cobranza']
            && (string) \App\Helpers\SysconfigHelper::get('botonafectacobranza', '') === '1';
    }

    /**
     * Valor por defecto de "afecta cobranza". mundotour_sdg arranca en No, el
     * resto en Sí (formasientocta.php:69-72).
     */
    public function afectaCobranzaPorDefecto(): bool
    {
        return ! Licencia::es('mundotour_sdg');
    }

    public function borraMovimientosAlAnular(): bool
    {
        return (bool) $this->def['borra_movimientos'];
    }

    public function liberaUtilizadoAlAnular(): bool
    {
        return (bool) $this->def['libera_utilizado'];
    }

    /**
     * El control de conciliación bancaria al anular está gateado por licencia en
     * el legacy (asientocontable.php:238, asientocta.php:242). Sólo aplica a los
     * asientos de debe/haber; fondos no lo tiene.
     */
    public function controlaConciliacion(): bool
    {
        return $this->esDebeHaber() && Licencia::es('mundotour_sdg');
    }

    /** Lo que consume el front. */
    public function aArray(): array
    {
        return [
            'slug' => $this->slug,
            'codigo' => $this->codigo(),
            'titulo' => $this->titulo(),
            'singular' => $this->singular(),
            'baseUrl' => $this->baseUrl(),
            'rutaLegacy' => $this->rutaLegacy(),
            'grilla' => $this->grilla(),
            'imputa' => $this->imputaciones(),
            'usaArqueo' => $this->usaArqueo(),
            'usaProyecto' => $this->usaProyecto(),
            'usaAfectaCobranza' => $this->usaAfectaCobranza(),
            'afectaCobranzaPorDefecto' => $this->afectaCobranzaPorDefecto(),
        ];
    }
}
