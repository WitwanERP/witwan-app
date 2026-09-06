<?php

namespace App\Http\Controllers\Web\Config;

use App\Http\Controllers\Controller;
use App\Services\Config\SeccionesPermisosService;
use App\Support\Licencia;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Configuración > Usuarios > Tipos de usuario (CI: configuracion/tipousuario +
 * views/users/tipousuario.php). El tipo es el rol: un id de 3 letras, un nombre
 * y la matriz de permisos por sección (`permisogrupo`), que se reemplaza
 * completa en cada guardado como en el legacy. "Copiar" abre el alta con los
 * permisos de otro tipo precargados.
 */
class TipousuarioController extends Controller
{
    private const BASE = '/app/config/tipos-usuario';

    public function __construct(private SeccionesPermisosService $permisos) {}

    public function index(Request $request): Response
    {
        $query = DB::table('tipousuario as t')
            ->leftJoin('usuario as u', 'u.fk_tipousuario_id', '=', 't.tipousuario_id')
            ->select('t.tipousuario_id', 't.tipousuario_nombre', 't.inicio', DB::raw('COUNT(u.usuario_id) AS usuarios'))
            ->groupBy('t.tipousuario_id', 't.tipousuario_nombre', 't.inicio');

        $nombre = trim((string) $request->get('tipousuario_nombre', ''));
        if ($nombre !== '') {
            $query->where('t.tipousuario_nombre', 'LIKE', "%{$nombre}%");
        }

        $registros = $query->orderBy('t.tipousuario_nombre')->paginate(100)->withQueryString();

        return Inertia::render('Abm/Index', [
            'config' => $this->config(),
            'registros' => $registros,
            'filtros' => $request->only(['tipousuario_nombre']),
        ]);
    }

    public function create(Request $request): Response
    {
        // ?copiar=XXX precarga los permisos de otro tipo (acción "copy" del CI).
        $copiar = (string) $request->get('copiar', '');
        $origen = $copiar !== '' ? DB::table('tipousuario')->where('tipousuario_id', $copiar)->first() : null;

        return $this->form(null, $origen ? $this->permisos->activos($origen->tipousuario_id) : [], $origen);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'tipousuario_id' => ['required', 'string', 'size:3', 'alpha_num', Rule::unique('tipousuario', 'tipousuario_id')],
            'tipousuario_nombre' => 'required|string|max:150',
            'inicio' => 'nullable|string|max:150',
            'perms' => 'nullable|array',
        ]);
        $id = strtoupper($data['tipousuario_id']);

        DB::transaction(function () use ($data, $id) {
            DB::table('tipousuario')->insert([
                'tipousuario_id' => $id,
                'tipousuario_nombre' => $data['tipousuario_nombre'],
                'inicio' => $data['inicio'] ?? '',
            ]);
            $this->permisos->guardar($id, (array) ($data['perms'] ?? []));
        });

        return redirect(self::BASE)->with('success', "Tipo de usuario {$id} creado correctamente.");
    }

    public function edit(string $id): Response
    {
        $registro = DB::table('tipousuario')->where('tipousuario_id', $id)->first();
        abort_if($registro === null, 404);

        return $this->form($registro, $this->permisos->activos($id));
    }

    public function update(Request $request, string $id): RedirectResponse
    {
        abort_if(DB::table('tipousuario')->where('tipousuario_id', $id)->doesntExist(), 404);

        $data = $request->validate([
            'tipousuario_nombre' => 'required|string|max:150',
            'inicio' => 'nullable|string|max:150',
            'perms' => 'nullable|array',
        ]);

        DB::transaction(function () use ($data, $id) {
            DB::table('tipousuario')->where('tipousuario_id', $id)->update([
                'tipousuario_nombre' => $data['tipousuario_nombre'],
                'inicio' => $data['inicio'] ?? '',
            ]);
            $this->permisos->guardar($id, (array) ($data['perms'] ?? []));
        });

        return redirect(self::BASE)->with('success', "Tipo de usuario {$id} actualizado correctamente.");
    }

    public function destroy(string $id): RedirectResponse
    {
        // Réplica de _before_delete: en Tower los roles base no se borran.
        if (Licencia::es('witwan_tower', 'witwan_tower_dev') && in_array($id, ['POW', 'CLI', 'CL', 'CLM'], true)) {
            return redirect(self::BASE)->with('error', 'Ese tipo de usuario no puede ser eliminado.');
        }

        if ($id === 'POW') {
            return redirect(self::BASE)->with('error', 'El tipo POW (superadministrador) no se puede eliminar.');
        }

        $enUso = DB::table('usuario')->where('fk_tipousuario_id', $id)->count();
        if ($enUso > 0) {
            return redirect(self::BASE)->with('error', "No se puede eliminar: hay {$enUso} usuario(s) con ese tipo. Reasígnelos primero.");
        }

        DB::transaction(function () use ($id) {
            DB::table('tipousuario')->where('tipousuario_id', $id)->delete();
            DB::table('permisogrupo')->where('fk_tipousuario_id', $id)->delete();
        });

        return redirect(self::BASE)->with('success', "Tipo de usuario {$id} eliminado.");
    }

    private function form(?object $registro, array $activos, ?object $copiaDe = null): Response
    {
        return Inertia::render('Config/TipousuarioForm', [
            'registro' => $registro,
            'copiaDe' => $copiaDe ? ['id' => $copiaDe->tipousuario_id, 'nombre' => $copiaDe->tipousuario_nombre] : null,
            'arbol' => $this->permisos->arbol((int) app('tenant')->licencia),
            'activos' => $activos,
            'permisosRaiz' => SeccionesPermisosService::PERMISOS_RAIZ,
            'baseUrl' => self::BASE,
        ]);
    }

    private function config(): array
    {
        return [
            'titulo' => 'Tipos de usuario',
            'singular' => 'Tipo de usuario',
            'area' => 'Configuración',
            'baseUrl' => self::BASE,
            'pk' => 'tipousuario_id',
            'acciones' => ['crear', 'editar', 'eliminar'],
            'extras' => [['label' => 'Copiar', 'href' => self::BASE.'/create?copiar={id}']],
            'columnas' => [
                ['campo' => 'tipousuario_id', 'label' => 'ID'],
                ['campo' => 'tipousuario_nombre', 'label' => 'Nombre'],
                ['campo' => 'inicio', 'label' => 'Inicio'],
                ['campo' => 'usuarios', 'label' => 'Usuarios'],
            ],
            'filtrosLike' => ['tipousuario_nombre'],
            'filtrosSelect' => [],
            'opcionesFiltro' => [],
            'campos' => [],
            'opciones' => [],
        ];
    }
}
