<?php

namespace App\Services\Productos;

use App\Support\Licencia;
use InvalidArgumentException;

/**
 * Lee config/productos.php y arma la definición del formulario para un
 * tipo/sistema. Port de Producto_model::get_estructura() + el reparto
 * principal/secundario que hace hotel.php:161-171.
 *
 * Clase pura salvo por Licencia::base() (los campos por licencia).
 */
final class ProductoFormulario
{
    /** Tipos de campo que no persisten nada. */
    private const NO_PERSISTEN = ['titulo'];

    /** Tipos con persistencia propia (no van ni a producto ni a producto_extra). */
    private const RELACIONES = ['bases', 'facilidades', 'destinos', 'ciudad'];

    public function tipo(string $tipo): array
    {
        $cfg = config("productos.tipos.{$tipo}");
        if (! is_array($cfg)) {
            throw new InvalidArgumentException("Tipo de producto desconocido: {$tipo}");
        }

        return $cfg + ['codigo' => $tipo, 'tipos_pax' => [], 'max_child' => null];
    }

    public static function tipoPorSlug(string $slug): ?string
    {
        foreach ((array) config('productos.tipos') as $codigo => $cfg) {
            if (($cfg['slug'] ?? '') === $slug) {
                return $codigo;
            }
        }

        return null;
    }

    public static function sistemaId(string $slug): ?int
    {
        $id = config("productos.sistemas.{$slug}");

        return $id === null ? null : (int) $id;
    }

    public static function sistemaSlug(int $id): ?string
    {
        $slug = array_search($id, (array) config('productos.sistemas'), true);

        return $slug === false ? null : (string) $slug;
    }

    /**
     * Campos visibles del formulario para el tipo y sistema, en el orden del CI:
     * comunes → por licencia → del tipo → política de cancelación.
     *
     * @return list<array>
     */
    public function campos(string $tipo, int $sistemaId): array
    {
        $cfg = $this->tipo($tipo);
        $licencia = Licencia::base();

        $todos = array_merge(
            (array) config('productos.campos_comunes.antes'),
            (array) config("productos.campos_por_licencia.{$licencia}", []),
            (array) $cfg['campos'],
            (array) config('productos.campos_comunes.despues'),
        );

        $visibles = [];
        foreach ($todos as $campo) {
            if (isset($campo['sistemas']) && ! in_array($sistemaId, (array) $campo['sistemas'], true)) {
                continue;
            }
            $visibles[] = $campo + ['tabla' => 'producto'];
        }

        return $visibles;
    }

    /**
     * Reparte los campos por destino de persistencia (hotel.php:161-171).
     *
     * @return array{producto: list<array>, producto_extra: list<array>, relaciones: list<array>}
     */
    public function porTabla(string $tipo, int $sistemaId): array
    {
        $out = ['producto' => [], 'producto_extra' => [], 'relaciones' => []];

        foreach ($this->campos($tipo, $sistemaId) as $campo) {
            if (in_array($campo['tipo'], self::NO_PERSISTEN, true)) {
                continue;
            }
            if (in_array($campo['tipo'], self::RELACIONES, true)) {
                $out['relaciones'][] = $campo;
            } elseif (($campo['tabla'] ?? 'producto') === 'producto_extra') {
                $out['producto_extra'][] = $campo;
            } else {
                $out['producto'][] = $campo;
            }
        }

        return $out;
    }

    /** Claves de opciones (selects) que necesita el formulario del tipo. */
    public function opcionesRequeridas(string $tipo, int $sistemaId): array
    {
        $claves = [];
        foreach ($this->campos($tipo, $sistemaId) as $campo) {
            if (! empty($campo['opciones'])) {
                $claves[$campo['opciones']] = true;
            }
        }

        return array_keys($claves);
    }
}
