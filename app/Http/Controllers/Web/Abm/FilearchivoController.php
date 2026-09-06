<?php

namespace App\Http\Controllers\Web\Abm;

use App\Services\TablaLegacyService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Configuración > Reservas > Archivos adjuntos (CI: configuracion/filearchivo).
 * Listado de los adjuntos de todos los files con el código de reserva y el
 * usuario que lo subió; como en el legacy, la única acción es eliminar.
 */
class FilearchivoController extends AbmController
{
    protected string $tabla = 'filearchivo';

    protected string $pk = 'filearchivo_id';

    protected string $ruta = 'config/archivos-adjuntos';

    protected string $titulo = 'Archivos adjuntos';

    protected string $singular = 'Archivo adjunto';

    protected array $acciones = ['eliminar'];

    protected array $columnasListado = [
        ['campo' => 'filearchivo_id', 'label' => 'ID'],
        ['campo' => 'filearchivo_archivo', 'label' => 'Nombre'],
        ['campo' => 'codigo', 'label' => 'Código'],
        ['campo' => 'usuario', 'label' => 'Usuario'],
        ['campo' => 'regdate', 'label' => 'Fecha'],
    ];

    protected array $filtrosLike = ['filearchivo_archivo', 'codigo', 'usuario'];

    protected string $sortDefault = 'regdate';

    public function index(Request $request, TablaLegacyService $svc): Response
    {
        $query = DB::table('filearchivo as f')
            ->join('reserva as r', 'r.reserva_id', '=', 'f.fk_file_id')
            ->join('usuario as u', 'u.usuario_id', '=', 'f.fk_usuario_id')
            ->select([
                'f.filearchivo_id', 'f.filearchivo_archivo', 'f.regdate',
                DB::raw("CONCAT(r.tipocodigo, '-', r.codigo) AS codigo"),
                DB::raw("CONCAT(u.usuario_nombre, ' ', u.usuario_apellido) AS usuario"),
            ]);

        $archivo = trim((string) $request->get('filearchivo_archivo', ''));
        if ($archivo !== '') {
            $query->where('f.filearchivo_archivo', 'LIKE', "%{$archivo}%");
        }
        $codigo = trim((string) $request->get('codigo', ''));
        if ($codigo !== '') {
            $query->whereRaw("CONCAT(r.tipocodigo, '-', r.codigo) LIKE ?", ["%{$codigo}%"]);
        }
        $usuario = trim((string) $request->get('usuario', ''));
        if ($usuario !== '') {
            $query->whereRaw("CONCAT(u.usuario_nombre, ' ', u.usuario_apellido) LIKE ?", ["%{$usuario}%"]);
        }

        $registros = $query->orderByDesc('f.regdate')->paginate($this->porPagina)->withQueryString();

        return Inertia::render('Abm/Index', [
            'config' => $this->config(),
            'registros' => $registros,
            'filtros' => $request->only(['filearchivo_archivo', 'codigo', 'usuario']),
        ]);
    }

    protected function campos(): array
    {
        return [];
    }
}
