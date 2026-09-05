<?php

namespace App\Services\Vigencias;

use Illuminate\Support\Carbon;

/**
 * Validaciones de negocio de una vigencia. Clase pura.
 *
 * El CI no valida nada de esto (vigencia.php:159-260): una fecha inválida se
 * graba como 1970-01-01 (strtotime false → date(0)), nada impide fin < ini,
 * costos negativos ni vigencias de igual prioridad que se pisan.
 *
 * Devuelve dos listas separadas: `errores` (bloquean el guardado) y `avisos`
 * (se muestran pero se puede guardar). El solape entre vigencias es aviso, no
 * error, porque es una práctica real: se cargan temporadas superpuestas y la
 * `vigencia_prioridad` decide cuál gana (tarifa_model.php: ORDER BY
 * vigencia_prioridad DESC).
 */
final class VigenciaReglas
{
    /**
     * @param  array  $datos  cabecera ya normalizada (fechas Y-m-d, dias_semana como array 1..7)
     * @param  array  $celdas  costos a guardar: list<array{costo:?float, venta?:array<int,?float>}>
     * @param  list<array>  $otrasVigencias  otras vigencias del producto: vigencia_id, vigencia_ini, vigencia_fin, vigencia_prioridad, vigencia_descripcion, residente
     * @return array{errores: array<string,string>, avisos: list<string>}
     */
    public function validar(array $datos, array $celdas = [], array $otrasVigencias = []): array
    {
        $errores = [];
        $avisos = [];

        $ini = $this->fecha($datos['vigencia_ini'] ?? null);
        $fin = $this->fecha($datos['vigencia_fin'] ?? null);

        if ($ini === null) {
            $errores['vigencia_ini'] = 'La fecha de inicio es obligatoria.';
        }
        if ($fin === null) {
            $errores['vigencia_fin'] = 'La fecha de fin es obligatoria.';
        }
        if ($ini !== null && $fin !== null && $fin->lt($ini)) {
            $errores['vigencia_fin'] = 'La fecha de fin no puede ser anterior a la de inicio.';
        }

        $ventaIni = $this->fecha($datos['vigencia_ventaini'] ?? null);
        $ventaFin = $this->fecha($datos['vigencia_ventafin'] ?? null);
        if ($ventaIni !== null && $ventaFin !== null && $ventaFin->lt($ventaIni)) {
            $errores['vigencia_ventafin'] = 'El fin de venta no puede ser anterior al inicio de venta.';
        }

        $dias = (array) ($datos['dias_semana'] ?? []);
        if ($dias === []) {
            $errores['dias_semana'] = 'Debe marcar al menos un día de la semana.';
        }

        $noches = (int) ($datos['noches_minimas'] ?? 0);
        if ($noches < 0) {
            $errores['noches_minimas'] = 'Las noches mínimas no pueden ser negativas.';
        }
        if ($noches > 0 && ! in_array((string) ($datos['modo_nochesminimas'] ?? ''), array_keys((array) config('productos.modo_nochesminimas')), true)) {
            $errores['modo_nochesminimas'] = 'Indique cómo se aplican las noches mínimas.';
        }

        $max = (int) config('productos.vencimiento_max', 120);
        foreach (['vencimiento_reserva', 'vencimiento_checkin'] as $campo) {
            $v = (int) ($datos[$campo] ?? 0);
            if ($v < 0 || $v > $max) {
                $errores[$campo] = "Debe estar entre 0 y {$max} días.";
            }
        }

        if (($datos['promo_noches'] ?? 0) > 0 && (int) ($datos['promo_pornoches'] ?? 0) <= 0) {
            $errores['promo_pornoches'] = 'Indique por cuántas noches se aplica la promoción.';
        }

        if (trim((string) ($datos['moneda_costo'] ?? '')) === '') {
            $errores['moneda_costo'] = 'La moneda de costo es obligatoria.';
        }

        if (! in_array((string) ($datos['residente'] ?? ''), array_keys((array) config('productos.residente')), true)) {
            $errores['residente'] = 'Valor de residente inválido.';
        }

        $hayCosto = false;
        foreach ($celdas as $i => $celda) {
            $costo = $celda['costo'] ?? null;
            if ($costo !== null && $costo !== '') {
                $hayCosto = true;
                if (! is_numeric($costo) || (float) $costo < 0) {
                    $errores["tarifas.{$i}.costo"] = 'El costo debe ser un número mayor o igual a cero.';
                }
            }
            foreach ((array) ($celda['venta'] ?? []) as $tarifarioId => $valor) {
                if ($valor !== null && $valor !== '' && (! is_numeric($valor) || (float) $valor < 0)) {
                    $errores["tarifas.{$i}.venta.{$tarifarioId}"] = 'El precio de venta debe ser un número mayor o igual a cero.';
                }
            }
        }
        if ($celdas !== [] && ! $hayCosto) {
            $avisos[] = 'La vigencia se guarda sin costos: no va a tarifar hasta que cargue al menos uno.';
        }

        if ($ini !== null && $fin !== null) {
            foreach ($this->solapes($ini, $fin, $datos, $otrasVigencias) as $aviso) {
                $avisos[] = $aviso;
            }
        }

        return ['errores' => $errores, 'avisos' => $avisos];
    }

    /**
     * Solapes con otras vigencias del mismo producto y residente. Dice cuál gana
     * según la prioridad (a igual prioridad el tarifador toma el costo más bajo:
     * ORDER BY vigencia_prioridad DESC, tarifa.costo).
     *
     * @return list<string>
     */
    public function solapes(Carbon $ini, Carbon $fin, array $datos, array $otras): array
    {
        $avisos = [];
        $prioridad = (int) ($datos['vigencia_prioridad'] ?? 0);
        $residente = (string) ($datos['residente'] ?? '');
        $propiaId = (int) ($datos['vigencia_id'] ?? 0);

        foreach ($otras as $otra) {
            if ((int) ($otra['vigencia_id'] ?? 0) === $propiaId && $propiaId !== 0) {
                continue;
            }
            $oIni = $this->fecha($otra['vigencia_ini'] ?? null);
            $oFin = $this->fecha($otra['vigencia_fin'] ?? null);
            if ($oIni === null || $oFin === null) {
                continue;
            }
            if ($oFin->lt($ini) || $oIni->gt($fin)) {
                continue;
            }
            // Residente distinto no compite (el tarifador filtra por residente).
            $oRes = (string) ($otra['residente'] ?? '');
            if ($oRes !== '' && $residente !== '' && $oRes !== $residente) {
                continue;
            }

            $oPrioridad = (int) ($otra['vigencia_prioridad'] ?? 0);
            $nombre = trim((string) ($otra['vigencia_descripcion'] ?? '')) ?: ('#'.($otra['vigencia_id'] ?? '?'));
            $rango = $oIni->format('d/m/Y').' - '.$oFin->format('d/m/Y');

            if ($oPrioridad > $prioridad) {
                $gana = "gana \"{$nombre}\" (prioridad {$oPrioridad} > {$prioridad})";
            } elseif ($oPrioridad < $prioridad) {
                $gana = "gana esta vigencia (prioridad {$prioridad} > {$oPrioridad})";
            } else {
                $gana = "misma prioridad ({$prioridad}): el tarifador toma el costo más bajo";
            }

            $avisos[] = "Se solapa con \"{$nombre}\" ({$rango}): {$gana}.";
        }

        return $avisos;
    }

    private function fecha(mixed $valor): ?Carbon
    {
        $valor = trim((string) $valor);
        if ($valor === '' || $valor === '0000-00-00') {
            return null;
        }

        // Carbon "desborda" fechas imposibles (31/31 → mes siguiente) en vez de
        // fallar; se exige que la fecha se reconstruya idéntica.
        $formato = preg_match('#^\d{2}/\d{2}/\d{4}$#', $valor) ? 'd/m/Y' : 'Y-m-d';
        $valor = $formato === 'Y-m-d' ? substr($valor, 0, 10) : $valor;

        try {
            $fecha = Carbon::createFromFormat($formato, $valor);
        } catch (\Throwable) {
            return null;
        }

        return $fecha !== false && $fecha->format($formato) === $valor ? $fecha->startOfDay() : null;
    }
}
