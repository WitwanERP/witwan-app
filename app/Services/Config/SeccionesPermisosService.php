<?php

namespace App\Services\Config;

use App\Services\CatalogosService;
use Illuminate\Support\Facades\DB;

/**
 * Árbol de secciones de la licencia para la matriz de permisos de Tipos de
 * usuario (views/users/tipousuario.php del CI), y persistencia de esa matriz
 * en `permisogrupo`.
 *
 * El árbol es el "sidebar" completo que baja de brain (brain.php): TODAS las
 * secciones habilitadas para la licencia, incluidas las hijas (fk_seccion_id
 * != 0, sin URI) que funcionan como permisos puntuales identificados por
 * `seccion_key` ("agrupar_reservas", "anular_factura"…). Las secciones raíz
 * llevan los cuatro permisos clásicos; las hijas, uno solo con su key.
 */
class SeccionesPermisosService
{
    public const PERMISOS_RAIZ = ['acceso', 'alta', 'edicion', 'borrado'];

    public function __construct(private CatalogosService $catalogos) {}

    /**
     * @return list<array{sistema_id:int,sistema:string,color:?string,grupos:list<array{grupo:string,secciones:list<array{id:int,label:string,raiz:bool,key:string}>}>}>
     */
    public function arbol(int $licenciaId): array
    {
        $secciones = DB::connection('license')
            ->table('seccion as s')
            ->join('rel_licenciaseccion as r', 'r.fk_seccion_id', '=', 's.seccion_id')
            ->where('r.fk_licencia_id', $licenciaId)
            ->orderBy('s.fk_sistema_id')
            ->orderBy('s.orden')
            ->get(['s.seccion_id', 's.fk_seccion_id', 's.seccion_key', 's.fk_sistema_id', 's.seccion_grupo', 's.seccion_nombre']);

        $nombres = collect($this->catalogos->sistemas())->pluck('label', 'value')->all()
            + (array) config('menu.sistemas', []);
        $colores = (array) config('menu.colores', []);

        $tree = [];
        foreach ($secciones as $sec) {
            $sid = (int) $sec->fk_sistema_id;
            $grupo = trim((string) $sec->seccion_grupo) ?: 'General';

            $tree[$sid] ??= [
                'sistema_id' => $sid,
                'sistema' => $nombres[$sid] ?? "Sistema {$sid}",
                'color' => $colores[$sid] ?? null,
                'grupos' => [],
            ];
            $tree[$sid]['grupos'][$grupo] ??= ['grupo' => $grupo, 'secciones' => []];
            $tree[$sid]['grupos'][$grupo]['secciones'][] = [
                'id' => (int) $sec->seccion_id,
                'label' => trim((string) $sec->seccion_nombre),
                'raiz' => (int) $sec->fk_seccion_id === 0,
                'key' => (string) $sec->seccion_key,
            ];
        }

        $orden = array_flip((array) config('menu.orden_sistemas', []));
        uksort($tree, fn ($a, $b) => ($orden[$a] ?? PHP_INT_MAX) <=> ($orden[$b] ?? PHP_INT_MAX));

        return array_values(array_map(function (array $s) {
            $s['grupos'] = array_values($s['grupos']);

            return $s;
        }, $tree));
    }

    /**
     * Permisos activos de un tipo de usuario como lista "seccion-permiso"
     * (el `$activeperms` de la vista del CI).
     *
     * @return list<string>
     */
    public function activos(string $tipousuarioId): array
    {
        return DB::table('permisogrupo')
            ->where('fk_tipousuario_id', $tipousuarioId)
            ->where('permisogrupo_valor', 1)
            ->get(['fk_seccion_id', 'permisogrupo_nombre'])
            ->map(fn ($p) => "{$p->fk_seccion_id}-{$p->permisogrupo_nombre}")
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Reemplaza los permisos del tipo de usuario por los recibidos, como el CI:
     * DELETE de todas sus filas y INSERT sólo de las marcadas (valor 1).
     *
     * @param  array<int|string,array<string,mixed>>  $perms  [seccion_id => [permiso => 0|1]]
     */
    public function guardar(string $tipousuarioId, array $perms): void
    {
        DB::table('permisogrupo')->where('fk_tipousuario_id', $tipousuarioId)->delete();

        $filas = [];
        foreach ($perms as $seccionId => $permisos) {
            if (! ctype_digit((string) $seccionId)) {
                continue;
            }
            foreach ((array) $permisos as $permiso => $valor) {
                if ((int) $valor === 1 && preg_match('/^[a-z0-9_\-]{1,100}$/i', (string) $permiso)) {
                    $filas[] = [
                        'fk_tipousuario_id' => $tipousuarioId,
                        'fk_seccion_id' => (int) $seccionId,
                        'permisogrupo_nombre' => (string) $permiso,
                        'permisogrupo_valor' => 1,
                    ];
                }
            }
        }

        foreach (array_chunk($filas, 500) as $chunk) {
            DB::table('permisogrupo')->insert($chunk);
        }
    }
}
