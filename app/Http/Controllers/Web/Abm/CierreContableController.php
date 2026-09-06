<?php

namespace App\Http\Controllers\Web\Abm;

use Illuminate\Support\Facades\DB;

/**
 * Administración > Contabilidad > Cierre contable (CI: administracion/libros/cierrecontable):
 * fechas de cierre de caja/contable (`cierrecaja`). Crear = cerrar hasta esa
 * fecha; eliminar = "abrir" (el `?abrir=` del legacy).
 */
class CierreContableController extends AbmController
{
    protected string $tabla = 'cierrecaja';

    protected string $pk = 'cierrecaja_id';

    protected string $ruta = 'contabilidad/cierres';

    protected string $titulo = 'Cierre contable';

    protected string $singular = 'Cierre';

    protected string $area = 'Administración';

    protected array $acciones = ['crear', 'eliminar'];

    protected array $columnasListado = [
        ['campo' => 'cierrecaja_id', 'label' => 'ID'],
        ['campo' => 'cierrecaja_fecha', 'label' => 'Cerrado hasta'],
        ['campo' => 'regdate', 'label' => 'Cargado el'],
        ['campo' => 'fk_usuario_id', 'label' => 'Usuario'],
    ];

    protected string $sortDefault = 'cierrecaja_fecha';

    protected string $dirDefault = 'desc';

    protected function campos(): array
    {
        return [['campo' => 'cierrecaja_fecha', 'label' => 'Cerrar hasta (inclusive)', 'tipo' => 'date', 'required' => true, 'ayuda' => 'No se pueden cargar ni modificar movimientos con fecha anterior o igual al cierre.']];
    }

    protected function antesDeGuardar(array $data, int|string|null $id): array
    {
        return $data + ['fk_usuario_id' => (int) auth()->id(), 'regdate' => now()->toDateTimeString()];
    }

    protected function presentar($registros)
    {
        $usuarios = DB::table('usuario')->whereIn('usuario_id', collect($registros->items())->pluck('fk_usuario_id')->filter()->unique()->all())
            ->get(['usuario_id', 'usuario_nombre', 'usuario_apellido'])->keyBy('usuario_id');

        return $registros->through(function ($r) use ($usuarios) {
            $r = (array) $r;
            $u = $usuarios[$r['fk_usuario_id']] ?? null;
            $r['fk_usuario_id'] = $u ? trim("{$u->usuario_nombre} {$u->usuario_apellido}") : (string) $r['fk_usuario_id'];
            $r['regdate'] = substr((string) $r['regdate'], 0, 16);

            return $r;
        });
    }
}
