<?php

namespace App\Http\Controllers\Web\Config;

use App\Http\Controllers\Controller;
use App\Support\Licencia;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Configuración > Usuarios > Escritorios (CI: administracion/escritorios, sólo
 * POW). Un escritorio es un titular con sus secundarios en
 * `rel_usuariousuario`; la tabla gobierna qué files ve cada usuario con el
 * permiso files_solo_usuario y el combo "escritorio" de nueva reserva.
 *
 * La escritura se habilita con sysconfig.escritorios_abm = 1, como en el CI.
 * La importación por planilla y los backups por lote del legacy no se portan
 * (el alta/baja de relaciones sí).
 */
class EscritoriosController extends Controller
{
    private const BASE = '/app/config/escritorios';

    public function index(Request $request): Response
    {
        $this->soloPow($request);
        $arbol = $this->arbol();

        return Inertia::render('Config/Escritorios', [
            'arbol' => $arbol,
            'resumen' => $this->resumen($arbol),
            'usuarios' => $this->usuarios(),
            'puedeEscribir' => $this->puedeEscribir(),
            'aplica' => Licencia::es('mundotour_sdg', 'witwan_rays'),
            'baseUrl' => self::BASE,
        ]);
    }

    public function agregar(Request $request): RedirectResponse
    {
        $this->soloPow($request);
        if (! $this->puedeEscribir()) {
            return redirect(self::BASE)->with('error', 'La escritura está deshabilitada en esta licencia (sysconfig.escritorios_abm).');
        }
        $data = $request->validate([
            'titular' => 'required|integer|min:1',
            'secundario' => 'required|integer|min:1|different:titular',
            'inversa' => 'nullable|boolean',
        ]);
        $p = (int) $data['titular'];
        $s = (int) $data['secundario'];
        foreach ([$p, $s] as $uid) {
            if (DB::table('usuario')->where('usuario_id', $uid)->doesntExist()) {
                return redirect(self::BASE)->with('error', "El usuario #{$uid} no existe.");
            }
        }
        $altas = [[$p, $s]];
        if (! empty($data['inversa'])) {
            $altas[] = [$s, $p];
        }
        $hechas = 0;
        foreach ($altas as [$a, $b]) {
            if (DB::table('rel_usuariousuario')->where('fk_usuario_id', $a)->where('fk_secundario_id', $b)->exists()) {
                continue;
            }
            DB::table('rel_usuariousuario')->insert(['fk_usuario_id' => $a, 'fk_secundario_id' => $b, 'tiporelacion' => 1]);
            $hechas++;
        }
        if ($hechas === 0) {
            return redirect(self::BASE)->with('warning', 'Esa relación ya existía. No se agregó nada.');
        }
        Log::info('escritorios: alta', ['titular' => $p, 'secundario' => $s, 'inversa' => ! empty($data['inversa']), 'uid' => auth()->id()]);

        return redirect(self::BASE)->with('success', "Agregado al escritorio #{$p}".(! empty($data['inversa']) ? ' (ida y vuelta)' : '').'.');
    }

    public function quitar(Request $request): RedirectResponse
    {
        $this->soloPow($request);
        if (! $this->puedeEscribir()) {
            return redirect(self::BASE)->with('error', 'La escritura está deshabilitada en esta licencia (sysconfig.escritorios_abm).');
        }
        $data = $request->validate(['titular' => 'required|integer|min:1', 'secundario' => 'required|integer|min:1']);
        $bajas = DB::table('rel_usuariousuario')->where('fk_usuario_id', (int) $data['titular'])->where('fk_secundario_id', (int) $data['secundario'])->delete();
        Log::info('escritorios: baja', ['titular' => $data['titular'], 'secundario' => $data['secundario'], 'uid' => auth()->id()]);

        return redirect(self::BASE)->with($bajas > 0 ? 'success' : 'warning', $bajas > 0 ? "Se quitó #{$data['secundario']} del escritorio #{$data['titular']}." : 'Esa relación ya no existía.');
    }

    private function soloPow(Request $request): void
    {
        abort_unless($request->user()?->fk_tipousuario_id === 'POW', 403, 'Acceso no permitido');
    }

    private function puedeEscribir(): bool
    {
        return (int) Licencia::sysconfig('escritorios_abm', 0) === 1;
    }

    /** Réplica de _arbol(): titulares con sus secundarios, marcando recíprocos y huérfanos. */
    private function arbol(): array
    {
        $filas = DB::table('rel_usuariousuario as r')
            ->leftJoin('usuario as up', 'up.usuario_id', '=', 'r.fk_usuario_id')
            ->leftJoin('usuario as us', 'us.usuario_id', '=', 'r.fk_secundario_id')
            ->orderBy('up.usuario_apellido')->orderBy('up.usuario_nombre')->orderBy('r.fk_usuario_id')
            ->orderBy('us.usuario_apellido')->orderBy('us.usuario_nombre')->orderBy('r.fk_secundario_id')
            ->get(['r.fk_usuario_id as p', 'r.fk_secundario_id as s', 'r.tiporelacion as t',
                'up.usuario_id as pex', 'up.usuario_nombre as pnom', 'up.usuario_apellido as pape', 'up.habilitar as phab',
                'us.usuario_id as sex', 'us.usuario_nombre as snom', 'us.usuario_apellido as sape', 'us.habilitar as shab']);

        $pares = [];
        foreach ($filas as $r) {
            $pares["{$r->p}-{$r->s}"] = true;
        }
        $arbol = [];
        foreach ($filas as $r) {
            $p = (int) $r->p;
            $arbol[$p] ??= [
                'id' => $p,
                'nombre' => $r->pex !== null ? trim(trim((string) $r->pape).', '.trim((string) $r->pnom), ', ') : '',
                'existe' => $r->pex !== null,
                'habilitado' => ! in_array((string) $r->phab, ['N', '0'], true),
                'hijos' => [],
            ];
            $arbol[$p]['hijos'][] = [
                'id' => (int) $r->s,
                'nombre' => $r->sex !== null ? trim(trim((string) $r->sape).', '.trim((string) $r->snom), ', ') : '',
                'existe' => $r->sex !== null,
                'habilitado' => ! in_array((string) $r->shab, ['N', '0'], true),
                'tipo' => (int) $r->t,
                'reciproco' => isset($pares["{$r->s}-{$r->p}"]),
            ];
        }
        foreach ($arbol as $p => $esc) {
            foreach ($esc['hijos'] as $i => $h) {
                $arbol[$p]['hijos'][$i]['es_titular'] = isset($arbol[$h['id']]);
            }
        }

        return array_values($arbol);
    }

    private function resumen(array $arbol): array
    {
        $relaciones = 0;
        $huerfanas = 0;
        $personas = [];
        foreach ($arbol as $esc) {
            $personas[$esc['id']] = true;
            if (! $esc['existe']) {
                $huerfanas++;
            }
            foreach ($esc['hijos'] as $h) {
                $relaciones++;
                $personas[$h['id']] = true;
                if (! $h['existe']) {
                    $huerfanas++;
                }
            }
        }

        return ['escritorios' => count($arbol), 'relaciones' => $relaciones, 'personas' => count($personas), 'huerfanas' => $huerfanas];
    }

    /** Candidatos del selector: internos + cualquiera ya usado en la tabla. */
    private function usuarios(): array
    {
        return DB::table('usuario')
            ->where('eliminar', '<>', 'Y')
            ->where(fn ($w) => $w->where('usuario_interno', 'Y')
                ->orWhereIn('usuario_id', DB::table('rel_usuariousuario')->select('fk_usuario_id'))
                ->orWhereIn('usuario_id', DB::table('rel_usuariousuario')->select('fk_secundario_id')))
            ->orderBy('usuario_apellido')->orderBy('usuario_nombre')
            ->get(['usuario_id', 'usuario_nombre', 'usuario_apellido', 'habilitar'])
            ->map(fn ($u) => [
                'id' => (int) $u->usuario_id,
                'nombre' => trim(trim((string) $u->usuario_apellido).', '.trim((string) $u->usuario_nombre), ', '),
                'habilitado' => ! in_array((string) $u->habilitar, ['N', '0'], true),
            ])
            ->all();
    }
}
