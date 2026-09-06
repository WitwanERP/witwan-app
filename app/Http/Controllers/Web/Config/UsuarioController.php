<?php

namespace App\Http\Controllers\Web\Config;

use App\Http\Controllers\Controller;
use App\Services\CatalogosService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Configuración > Usuarios > Usuarios (CI: configuracion/usuario + views/users/form.php).
 *
 * Diferencias deliberadas con el legacy:
 *  - La contraseña se guarda sólo hasheada (bcrypt, que el phpass del CI
 *    verifica con crypt()); el CI además copiaba el texto plano en
 *    `usuario_clave`, que ningún login lee.
 *  - Editar un usuario NO borra sus permisos individuales (`permiso`): el CI
 *    los borraba siempre porque su form nunca los reenviaba.
 */
class UsuarioController extends Controller
{
    private const BASE = '/app/config/usuarios';

    private const CAMPOS = [
        'fk_tipousuario_id', 'usuario_nombre', 'usuario_apellido', 'usuario_mail', 'fk_proveedor_id', 'fk_cliente_id',
        'fk_cadenacliente_id', 'fk_operador_id', 'usuario_interno', 'fk_idioma_id', 'solocotiza', 'usuario_promo',
        'usuario_telefono', 'usuario_celular', 'usuario_sexo', 'usuario_domicilio', 'usuario_responsable', 'ciudad',
        'firma_amadeus', 'firma_sabre', 'habilitar',
    ];

    public function __construct(private CatalogosService $catalogos) {}

    public function index(Request $request): Response
    {
        $query = DB::table('usuario as u')
            ->leftJoin('tipousuario as t', 't.tipousuario_id', '=', 'u.fk_tipousuario_id')
            ->leftJoin('cliente as c', 'c.cliente_id', '=', 'u.fk_cliente_id')
            ->select('u.usuario_id', 'u.usuario_nombre', 'u.usuario_apellido', 'u.usuario_mail', 'u.usuario_apikey',
                'u.habilitar', 'u.usuario_interno', 't.tipousuario_nombre', 'c.cliente_nombre');

        foreach (['usuario_nombre', 'usuario_apellido', 'usuario_mail'] as $campo) {
            $valor = trim((string) $request->get($campo, ''));
            if ($valor !== '') {
                $query->where("u.{$campo}", 'LIKE', "%{$valor}%");
            }
        }
        foreach (['fk_tipousuario_id', 'habilitar', 'fk_cliente_id', 'fk_proveedor_id', 'fk_cadenacliente_id'] as $campo) {
            $valor = (string) $request->get($campo, '');
            if ($valor !== '') {
                $query->where("u.{$campo}", $valor);
            }
        }
        $id = trim((string) $request->get('usuario_id', ''));
        if ($id !== '' && ctype_digit($id)) {
            $query->where('u.usuario_id', (int) $id);
        }

        $registros = $query->orderBy('u.fk_tipousuario_id')->orderBy('u.usuario_apellido')->paginate(200)->withQueryString()
            ->through(function ($r) {
                $r = (array) $r;
                $r['habilitar'] = in_array($r['habilitar'], ['Y', '1', 1], true) ? 'Sí' : 'No';
                $r['usuario_interno'] = in_array($r['usuario_interno'], ['Y', '1', 1], true) ? 'Interno' : 'Externo';

                return $r;
            });

        return Inertia::render('Abm/Index', [
            'config' => $this->config(),
            'registros' => $registros,
            'filtros' => $request->only(['usuario_nombre', 'usuario_apellido', 'usuario_mail', 'fk_tipousuario_id', 'habilitar', 'fk_cliente_id', 'fk_proveedor_id', 'fk_cadenacliente_id', 'usuario_id']),
        ]);
    }

    public function create(): Response
    {
        return $this->form(null);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validar($request, null);
        $data['usuario_password'] = Hash::make($request->input('usuario_password'));

        $id = DB::table('usuario')->insertGetId($this->completar($data), 'usuario_id');

        return redirect(self::BASE)->with('success', "Usuario #{$id} creado correctamente.");
    }

    public function edit(int $id): Response
    {
        $registro = DB::table('usuario')->where('usuario_id', $id)->first();
        abort_if($registro === null, 404);

        $registro = (array) $registro;
        unset($registro['usuario_password'], $registro['usuario_clave'], $registro['password']);

        return $this->form($registro);
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        abort_if(DB::table('usuario')->where('usuario_id', $id)->doesntExist(), 404);

        $data = $this->validar($request, $id);
        if ((string) $request->input('usuario_password', '') !== '') {
            $data['usuario_password'] = Hash::make($request->input('usuario_password'));
        }

        DB::table('usuario')->where('usuario_id', $id)->update($data);

        return redirect(self::BASE)->with('success', "Usuario #{$id} actualizado correctamente.");
    }

    public function destroy(int $id): RedirectResponse
    {
        abort_if($id === (int) auth()->id(), 422, 'No puede eliminar su propio usuario.');

        DB::table('usuario')->where('usuario_id', $id)->delete();

        return redirect(self::BASE)->with('success', "Usuario #{$id} eliminado.");
    }

    /** Réplica de usuario.php::apikey(): genera una API key nueva (5 bloques de 6 hex). */
    public function apikey(int $id): RedirectResponse
    {
        abort_if(DB::table('usuario')->where('usuario_id', $id)->doesntExist(), 404);

        $key = implode('-', str_split(substr(strtolower(md5(microtime().random_int(1000, 9999))), 0, 30), 6));
        DB::table('usuario')->where('usuario_id', $id)->update(['usuario_apikey' => $key]);

        return back()->with('success', "API key generada para el usuario #{$id}: {$key}");
    }

    private function validar(Request $request, ?int $id): array
    {
        $reglas = [
            'fk_tipousuario_id' => ['required', 'string', 'size:3', Rule::exists('tipousuario', 'tipousuario_id')],
            'usuario_nombre' => 'required|string|max:100',
            'usuario_apellido' => 'required|string|max:100',
            'usuario_mail' => ['required', 'string', 'max:150', 'email', Rule::unique('usuario', 'usuario_mail')->ignore($id, 'usuario_id')],
            'usuario_password' => $id === null ? 'required|string|min:6|max:72' : 'nullable|string|min:6|max:72',
            'habilitar' => 'required|in:Y,N',
            'usuario_interno' => 'required|in:Y,N',
            'solocotiza' => 'nullable|in:Y,N',
            'fk_idioma_id' => 'nullable|string|max:2',
            'fk_cliente_id' => 'nullable|integer',
            'fk_proveedor_id' => 'nullable|integer',
            'fk_cadenacliente_id' => 'nullable|integer',
            'fk_operador_id' => 'nullable|integer',
            'firma_amadeus' => 'nullable|string|max:50',
            'firma_sabre' => 'nullable|string|max:50',
            'usuario_responsable' => 'nullable|integer|in:0,1',
            'usuario_promo' => 'nullable|integer|in:0,1',
            'usuario_telefono' => 'nullable|string|max:50',
            'usuario_celular' => 'nullable|string|max:50',
            'usuario_domicilio' => 'nullable|string|max:255',
            'ciudad' => 'nullable|string|max:100',
            'usuario_sexo' => 'nullable|in:M,F',
        ];

        $data = $request->validate($reglas);
        unset($data['usuario_password']);

        // Defaults del legacy para lo que venga vacío.
        $data['solocotiza'] = $data['solocotiza'] ?? 'N';
        $data['fk_idioma_id'] = $data['fk_idioma_id'] ?? 'es';
        $data['usuario_sexo'] = $data['usuario_sexo'] ?? 'M';
        foreach (['fk_cliente_id', 'fk_proveedor_id', 'fk_cadenacliente_id', 'fk_operador_id', 'usuario_responsable', 'usuario_promo'] as $k) {
            $data[$k] = (int) ($data[$k] ?? 0);
        }
        foreach (['firma_amadeus', 'firma_sabre', 'usuario_telefono', 'usuario_celular', 'usuario_domicilio', 'ciudad'] as $k) {
            $data[$k] = (string) ($data[$k] ?? '');
        }

        return array_intersect_key($data, array_flip(self::CAMPOS)) + array_intersect_key($data, ['usuario_password' => 1]);
    }

    /** NOT NULL sin default de la tabla legacy que el form no cubre. */
    private function completar(array $data): array
    {
        return $data + [
            'usuario_login' => $data['usuario_mail'],
            'usuario_clave' => '',
            'usuario_key' => '',
            'usuario_fax' => '',
            'nacimiento' => '0000-00-00',
            'notas' => '',
            'agente' => 0,
            'eliminar' => 'N',
            'usuario_apikey' => '',
            'fk_modelocomision_id' => 0,
        ];
    }

    private function form(?array $registro): Response
    {
        return Inertia::render('Config/UsuarioForm', [
            'registro' => $registro,
            'baseUrl' => self::BASE,
            'opciones' => [
                'tipos' => $this->catalogos->tiposUsuario(),
                'idiomas' => $this->catalogos->idiomas(),
                'clientes' => $this->catalogos->clientes(),
                'proveedores' => $this->catalogos->proveedores(),
                'cadenas' => DB::table('cadenacliente')->orderBy('cadenacliente_nombre')->get(['cadenacliente_id', 'cadenacliente_nombre'])
                    ->map(fn ($c) => ['value' => (int) $c->cadenacliente_id, 'label' => $c->cadenacliente_nombre])->all(),
                'operadores' => $this->catalogos->usuariosInternos(),
            ],
        ]);
    }

    private function config(): array
    {
        return [
            'titulo' => 'Usuarios',
            'singular' => 'Usuario',
            'area' => 'Configuración',
            'baseUrl' => self::BASE,
            'pk' => 'usuario_id',
            'acciones' => ['crear', 'editar', 'eliminar'],
            'extras' => [['label' => 'Crear API key', 'href' => self::BASE.'/{id}/apikey', 'method' => 'post', 'confirmar' => '¿Generar una API key nueva para el usuario #{id}? La anterior deja de funcionar.']],
            'columnas' => [
                ['campo' => 'usuario_id', 'label' => 'ID'],
                ['campo' => 'usuario_apellido', 'label' => 'Apellido'],
                ['campo' => 'usuario_nombre', 'label' => 'Nombre'],
                ['campo' => 'usuario_mail', 'label' => 'Email'],
                ['campo' => 'tipousuario_nombre', 'label' => 'Tipo de usuario'],
                ['campo' => 'cliente_nombre', 'label' => 'Cliente'],
                ['campo' => 'usuario_interno', 'label' => 'Interno/Externo'],
                ['campo' => 'habilitar', 'label' => 'Habilitado'],
                ['campo' => 'usuario_apikey', 'label' => 'API key'],
            ],
            'filtrosLike' => ['usuario_nombre', 'usuario_apellido', 'usuario_mail'],
            'filtrosSelect' => [
                ['campo' => 'fk_tipousuario_id', 'label' => 'Tipo de usuario', 'opciones' => 'tipos'],
                ['campo' => 'habilitar', 'label' => 'Habilitado', 'opciones' => 'siNo'],
                ['campo' => 'fk_cliente_id', 'label' => 'Cliente relacionado', 'opciones' => 'clientes'],
                ['campo' => 'fk_proveedor_id', 'label' => 'Proveedor relacionado', 'opciones' => 'proveedores'],
            ],
            'opcionesFiltro' => [
                'tipos' => $this->catalogos->tiposUsuario(),
                'siNo' => [['value' => 'Y', 'label' => 'Sí'], ['value' => 'N', 'label' => 'No']],
                'clientes' => $this->catalogos->clientes(),
                'proveedores' => $this->catalogos->proveedores(),
            ],
            'campos' => [],
            'opciones' => [],
        ];
    }
}
