<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Arma la botonera del front /app replicando la de CI.
 *
 * El árbol de secciones vive en la BD central `brain` (tabla `seccion` filtrada
 * por `rel_licenciaseccion` según la licencia); la visibilidad por usuario sale
 * de `permisogrupo` (BD del tenant, por rol): una sección se muestra si el rol
 * tiene `acceso`=1. POW ve todo. Los links apuntan a las URIs del CI legacy
 * (mismo dominio, raíz), porque la mayoría de secciones no están migradas a /app.
 */
class MenuService
{
    /** Menú agrupado por sistema → grupo → ítems para el usuario y la licencia. */
    public function forUser(User $user, int $licenciaId): array
    {
        $ttl = (int) config('menu.cache_ttl', 0);
        $build = fn () => $this->build($user, $licenciaId);

        // Store explícito (file por defecto): no depende de que el tenant tenga
        // tabla `cache`, y la clave ya separa por licencia+rol.
        return $ttl > 0
            ? Cache::store(config('menu.cache_store', 'file'))
                ->remember("menu:{$licenciaId}:{$user->fk_tipousuario_id}", $ttl, $build)
            : $build();
    }

    private function build(User $user, int $licenciaId): array
    {
        $secciones = DB::connection('license')
            ->table('seccion as s')
            ->join('rel_licenciaseccion as r', 'r.fk_seccion_id', '=', 's.seccion_id')
            ->where('r.fk_licencia_id', $licenciaId)
            ->where('s.seccion_uri', '<>', '')
            ->orderBy('s.fk_sistema_id')
            ->orderBy('s.orden')
            ->get(['s.seccion_id', 's.fk_sistema_id', 's.seccion_grupo', 's.seccion_nombre', 's.seccion_uri', 's.icono']);

        $permitidas = $this->seccionesPermitidas($user); // null = ve todo (POW)
        $sistemasNombre = (array) config('menu.sistemas', []);

        $tree = [];
        foreach ($secciones as $sec) {
            if ($permitidas !== null && ! isset($permitidas[(int) $sec->seccion_id])) {
                continue;
            }

            $sid = (int) $sec->fk_sistema_id;
            $grupo = trim((string) $sec->seccion_grupo) ?: 'General';

            $tree[$sid]['sistema_id'] = $sid;
            $tree[$sid]['sistema'] = $sistemasNombre[$sid] ?? "Sistema {$sid}";
            $tree[$sid]['grupos'][$grupo]['grupo'] = $grupo;
            $tree[$sid]['grupos'][$grupo]['icono'] ??= $this->icon($sec->icono);
            $tree[$sid]['grupos'][$grupo]['items'][] = [
                'label' => trim((string) $sec->seccion_nombre),
                'url' => $this->url($sec->seccion_uri),
                'icono' => $this->icon($sec->icono),
                'seccion_id' => (int) $sec->seccion_id,
            ];
        }

        return $this->ordenar($tree);
    }

    /** Set [seccion_id => true] con acceso=1 para el rol; null si ve todo (POW). */
    private function seccionesPermitidas(User $user): ?array
    {
        if ($user->fk_tipousuario_id === 'POW') {
            return null;
        }

        return DB::table('permisogrupo')
            ->where('fk_tipousuario_id', $user->fk_tipousuario_id)
            ->where('permisogrupo_nombre', config('menu.permiso_acceso', 'acceso'))
            ->where('permisogrupo_valor', 1)
            ->pluck('fk_seccion_id')
            ->flip()
            ->all();
    }

    /** Aplana a arrays indexados y ordena los sistemas según config. */
    private function ordenar(array $tree): array
    {
        $orden = array_flip((array) config('menu.orden_sistemas', []));
        uksort($tree, fn ($a, $b) => ($orden[$a] ?? PHP_INT_MAX) <=> ($orden[$b] ?? PHP_INT_MAX));

        return array_values(array_map(function (array $sistema) {
            $sistema['grupos'] = array_values($sistema['grupos']);

            return $sistema;
        }, $tree));
    }

    private function url(string $uri): string
    {
        $uri = trim($uri);

        return str_starts_with($uri, '/') ? $uri : '/'.$uri;
    }

    /** Normaliza el ícono de CI ('Icon-basket', 'Fa fa-usd', …) a una clave simple. */
    private function icon(?string $ci): string
    {
        $key = strtolower(trim((string) $ci));
        $key = preg_replace('/\b(icon|fa|fa-o)\b/', ' ', $key); // saca prefijos de set
        $key = preg_replace('/[^a-z0-9]+/', '-', (string) $key); // normaliza separadores

        return trim((string) $key, '-');
    }
}
