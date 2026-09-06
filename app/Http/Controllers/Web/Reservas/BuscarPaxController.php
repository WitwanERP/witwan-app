<?php

namespace App\Http\Controllers\Web\Reservas;

use App\Http\Controllers\Web\Reportes\ReporteController;
use App\Support\Licencia;
use Illuminate\Support\Facades\DB;

/**
 * Administración > Reservas > Buscar (CI: dashboard/buscarpax): busca files
 * por titular o por nombre de servicio (número de boleto). En la colectora
 * witwan_secontur busca en las tres bases hijas, como el legacy.
 */
class BuscarPaxController extends ReporteController
{
    protected string $titulo = 'Buscar pasajero / boleto';

    protected string $ruta = 'reservas-buscar';

    protected string $area = 'Reservas';

    protected string $grupo = '';

    protected ?string $agruparPor = null;

    protected int $limite = 500;

    protected function filtros(): array
    {
        return [
            ['campo' => 'nrofile', 'label' => 'Titular (nombre o apellido)', 'tipo' => 'text'],
            ['campo' => 'nroboleto', 'label' => 'Boleto / servicio', 'tipo' => 'text'],
        ];
    }

    protected function columnas(): array
    {
        return [
            ['campo' => 'base', 'label' => 'Base'],
            ['campo' => 'file', 'label' => 'File'],
            ['campo' => 'titular', 'label' => 'Titular'],
            ['campo' => 'vendedor', 'label' => 'Vendedor'],
        ];
    }

    protected function consultar(array $f): array
    {
        if ($f['nrofile'] === '' && $f['nroboleto'] === '') {
            return [];
        }
        $bases = Licencia::es('witwan_secontur') ? ['witwan_secontur1', 'witwan_secontur2', 'witwan_secontur3'] : [null];
        $filas = [];
        foreach ($bases as $base) {
            $p = $base !== null ? "`{$base}`." : '';
            $q = DB::table(DB::raw("{$p}`reserva`"))
                ->join(DB::raw("{$p}`servicio`"), 'servicio.fk_reserva_id', '=', 'reserva.reserva_id')
                ->leftJoin(DB::raw("{$p}`usuario`"), 'usuario.usuario_id', '=', 'reserva.agente')
                ->where('reserva.fk_filestatus_id', '<>', 'AN')
                ->where('reserva.fk_agrupado_id', 0)
                ->where(function ($w) use ($f) {
                    if ($f['nrofile'] !== '') {
                        $w->where('reserva.titular_nombre', 'LIKE', "%{$f['nrofile']}%")->orWhere('reserva.titular_apellido', 'LIKE', "%{$f['nrofile']}%");
                    }
                    if ($f['nroboleto'] !== '') {
                        $w->orWhere('servicio.servicio_nombre', 'LIKE', "%{$f['nroboleto']}%");
                    }
                })
                ->groupBy('reserva.reserva_id')
                ->select('reserva.reserva_id', 'reserva.codigo', 'reserva.tipocodigo', 'reserva.titular_nombre', 'reserva.titular_apellido', 'usuario.usuario_nombre', 'usuario.usuario_apellido');
            $this->limitar($q);
            foreach ($q->get() as $r) {
                $filas[] = [
                    'base' => $base ?? Licencia::base(),
                    'file' => "{$r->tipocodigo}-{$r->codigo}",
                    'file_link' => $base === null ? "/reserva/editar/{$r->reserva_id}" : null,
                    'titular' => trim("{$r->titular_apellido}, {$r->titular_nombre}", ', '),
                    'vendedor' => trim("{$r->usuario_nombre} {$r->usuario_apellido}"),
                ];
            }
        }

        return $filas;
    }
}
