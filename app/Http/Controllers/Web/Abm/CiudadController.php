<?php

namespace App\Http\Controllers\Web\Abm;

use App\Services\TablaLegacyService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * ABM de Ciudades, con los DOS niveles de CI: una ciudad "real" tiene
 * fk_ciudad_id = 0; si fk_ciudad_id apunta a otra ciudad, es una sub-ciudad
 * (zona / barrio / aeropuerto / punto de interés) que pertenece a ella.
 *
 * El listado se sobreescribe para mostrar país y ciudad padre (join); el alta y
 * edición usan el form genérico con selects de país y de ciudad padre.
 */
class CiudadController extends AbmController
{
    protected string $tabla = 'ciudad';
    protected string $pk = 'ciudad_id';
    protected string $ruta = 'geo/ciudades';
    protected string $titulo = 'Ciudades';
    protected string $singular = 'Ciudad';
    protected array $columnasListado = [
        ['campo' => 'ciudad_id', 'label' => 'ID'],
        ['campo' => 'ciudad_nombre', 'label' => 'Nombre'],
        ['campo' => 'pais_nombre', 'label' => 'País'],
        ['campo' => 'padre_nombre', 'label' => 'Pertenece a'],
        ['campo' => 'ciudad_codigo', 'label' => 'Código'],
    ];
    protected array $filtrosLike = ['ciudad_nombre'];
    protected string $sortDefault = 'ciudad_nombre';

    /** Listado con país y ciudad padre resueltos por join. */
    public function index(Request $request, TablaLegacyService $svc): Response
    {
        $query = DB::table('ciudad as c')
            ->leftJoin('pais as p', 'p.pais_id', '=', 'c.fk_pais_id')
            ->leftJoin('ciudad as padre', 'padre.ciudad_id', '=', 'c.fk_ciudad_id')
            ->select(
                'c.ciudad_id',
                'c.ciudad_nombre',
                'c.ciudad_codigo',
                'c.ap',
                'p.pais_nombre',
                'padre.ciudad_nombre as padre_nombre',
            );

        $nombre = trim((string) $request->get('ciudad_nombre', ''));
        if ($nombre !== '') {
            $query->where('c.ciudad_nombre', 'LIKE', "%{$nombre}%");
        }

        $id = trim((string) $request->get('ciudad_id', ''));
        if ($id !== '' && ctype_digit($id)) {
            $query->where('c.ciudad_id', (int) $id);
        }

        $registros = $query->orderBy('c.ciudad_nombre')->paginate(50)->withQueryString();

        return Inertia::render('Abm/Index', [
            'config' => $this->config(),
            'registros' => $registros,
            'filtros' => $request->only(['ciudad_nombre', 'ciudad_id']),
        ]);
    }

    protected function campos(): array
    {
        return [
            ['campo' => 'fk_pais_id', 'label' => 'País', 'tipo' => 'select', 'required' => true, 'opciones' => 'paises'],
            ['campo' => 'ciudad_nombre', 'label' => 'Nombre', 'tipo' => 'text', 'required' => true, 'max' => 100],
            ['campo' => 'ciudad_codigo', 'label' => 'Código', 'tipo' => 'text', 'max' => 20],
            ['campo' => 'fk_ciudad_id', 'label' => 'Pertenece a (ciudad)', 'tipo' => 'select', 'opciones' => 'ciudadesPadre', 'ayuda' => 'Dejar vacío si es una ciudad principal. Si se elige, es una zona/barrio/aeropuerto de esa ciudad.'],
            ['campo' => 'ap', 'label' => 'Es aeropuerto', 'tipo' => 'checkbox'],
            ['campo' => 'ciudad_activo', 'label' => 'Activa', 'tipo' => 'checkbox', 'default' => 1],
        ];
    }

    protected function opciones(): array
    {
        return [
            'paises' => DB::table('pais')->orderBy('pais_nombre')
                ->get(['pais_id', 'pais_nombre'])
                ->map(fn ($p) => ['value' => $p->pais_id, 'label' => $p->pais_nombre])
                ->all(),
            // Solo ciudades principales (nivel 1) como posibles padres.
            'ciudadesPadre' => DB::table('ciudad')->where('fk_ciudad_id', 0)
                ->orderBy('ciudad_nombre')
                ->limit(2000)
                ->get(['ciudad_id', 'ciudad_nombre'])
                ->map(fn ($c) => ['value' => $c->ciudad_id, 'label' => $c->ciudad_nombre])
                ->all(),
        ];
    }
}
