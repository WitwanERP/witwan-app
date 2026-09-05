<?php

namespace App\Console\Commands;

use App\Support\Productos\DiasSemana;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Reconciliación de días de semana entre `vigencia.weekdays` y
 * `rel_vigenciadia` (bug B5 del análisis: el form del CI lee una cosa y el
 * tarifador otra).
 *
 *   php artisan vigencias:reconciliar-dias                    # sólo reporta
 *   php artisan vigencias:reconciliar-dias --aplicar --fuente=weekdays
 *   php artisan vigencias:reconciliar-dias --aplicar --fuente=rel
 *
 * Por defecto no toca nada: lista las vigencias donde las dos fuentes
 * difieren para que el negocio decida cuál es la verdad (doc §4.8.2).
 */
class VigenciasReconciliarDias extends Command
{
    protected $signature = 'vigencias:reconciliar-dias
        {--aplicar : Escribe la fuente elegida en las dos representaciones}
        {--fuente=weekdays : weekdays (lo que usa el tarifador) o rel (lo que muestra el form del CI)}
        {--producto= : Limitar a un producto}';

    protected $description = 'Lista (y opcionalmente corrige) las vigencias cuyos días difieren entre weekdays y rel_vigenciadia';

    public function handle(): int
    {
        $fuente = (string) $this->option('fuente');
        if (! in_array($fuente, ['weekdays', 'rel'], true)) {
            $this->error('--fuente debe ser weekdays o rel');

            return self::INVALID;
        }

        $q = DB::table('vigencia')->select('vigencia.vigencia_id', 'vigencia.fk_producto_id', 'vigencia.vigencia_ini', 'vigencia.vigencia_fin', DiasSemana::columnaSelect('bits'));
        if ($this->option('producto')) {
            $q->where('fk_producto_id', (int) $this->option('producto'));
        }

        $rel = [];
        foreach (DB::table('rel_vigenciadia')->get() as $r) {
            $rel[(int) $r->fk_vigencia_id][] = (int) $r->fk_dia_id;
        }

        $diferencias = [];
        foreach ($q->orderBy('vigencia_id')->get() as $v) {
            $id = (int) $v->vigencia_id;
            $bits = DiasSemana::desdeBits($v->bits);
            $filas = DiasSemana::normalizar($rel[$id] ?? []);

            // Sin filas en rel_vigenciadia el form del CI muestra los bits: no hay conflicto.
            if ($filas === [] || $filas === $bits) {
                continue;
            }

            $diferencias[] = ['id' => $id, 'producto' => (int) $v->fk_producto_id, 'rango' => substr($v->vigencia_ini, 0, 10).' → '.substr($v->vigencia_fin, 0, 10), 'weekdays' => $bits, 'rel' => $filas];
        }

        if ($diferencias === []) {
            $this->info('Sin diferencias: weekdays y rel_vigenciadia coinciden en todas las vigencias.');

            return self::SUCCESS;
        }

        $this->table(['vigencia', 'producto', 'rango', 'weekdays (tarifador)', 'rel_vigenciadia (form)'], array_map(fn ($d) => [
            $d['id'], $d['producto'], $d['rango'], implode(',', $d['weekdays']), implode(',', $d['rel']),
        ], $diferencias));
        $this->line(count($diferencias).' vigencia(s) con diferencias.');

        if (! $this->option('aplicar')) {
            $this->comment('Nada modificado. Use --aplicar --fuente=weekdays|rel para corregir.');

            return self::SUCCESS;
        }

        DB::transaction(function () use ($diferencias, $fuente) {
            foreach ($diferencias as $d) {
                $dias = $fuente === 'rel' ? $d['rel'] : $d['weekdays'];
                DB::table('vigencia')->where('vigencia_id', $d['id'])->update(['weekdays' => DiasSemana::valorParaGuardar($dias)]);
                DB::table('rel_vigenciadia')->where('fk_vigencia_id', $d['id'])->delete();
                foreach ($dias as $dia) {
                    DB::table('rel_vigenciadia')->insert(['fk_vigencia_id' => $d['id'], 'fk_dia_id' => $dia]);
                }
            }
        });

        $this->info(count($diferencias)." vigencia(s) reconciliada(s) tomando '{$fuente}' como verdad.");

        return self::SUCCESS;
    }
}
