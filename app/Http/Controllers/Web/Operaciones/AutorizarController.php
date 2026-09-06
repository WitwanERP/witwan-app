<?php

namespace App\Http\Controllers\Web\Operaciones;

use App\Services\Operaciones\ServicioExtraService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Operaciones > Autorizar reservas (CI: operaciones/autorizar). Lista los
 * files CL/CO/RQ del área con servicios próximos (sin filtros: no autorizados
 * con in en los próximos 21 días); el detalle permite asignar guía, proveedor
 * y datos de pickup por servicio, y autorizar el file.
 */
class AutorizarController extends OperacionesController
{
    public function __construct(private ServicioExtraService $extras) {}

    public function lista(Request $request, string $area): Response
    {
        $sistema = $this->sistema($area);
        $f = [
            'codigo' => trim((string) $request->get('codigo', '')),
            'titular' => trim((string) $request->get('titular', '')),
            'cliente' => (string) $request->get('cliente', ''),
            'fk_vendedor_id' => (string) $request->get('fk_vendedor_id', ''),
            'autorizado' => (string) $request->get('autorizado', ''),
        ];
        $hayFiltros = collect($f)->contains(fn ($v) => $v !== '') || $request->has('buscar');

        $q = DB::table('reserva as r')
            ->join('cliente as c', 'c.cliente_id', '=', 'r.fk_cliente_id')
            ->join('servicio as s', 's.fk_reserva_id', '=', 'r.reserva_id')
            ->where(fn ($w) => $w->where('r.fk_sistema_id', $sistema)->orWhere('r.fk_sistemaaplicacion_id', $sistema))
            ->whereIn('r.fk_filestatus_id', ['CL', 'CO', 'RQ'])
            ->groupBy('r.reserva_id')
            ->orderByDesc('r.codigo')
            ->select('r.reserva_id', 'r.tipocodigo', 'r.codigo', 'r.fk_moneda_id', 'r.total', 'r.autorizado', 'c.cliente_nombre',
                DB::raw("CONCAT(r.titular_apellido, ', ', r.titular_nombre) AS ntitular"),
                DB::raw('MIN(s.vigencia_ini) AS mfecha'), DB::raw('MAX(s.adultos) AS maximo'));

        if ($hayFiltros) {
            if ($f['cliente'] !== '') {
                $q->where('r.fk_cliente_id', (int) $f['cliente']);
            }
            if ($f['titular'] !== '') {
                $q->where(fn ($w) => $w->where('r.titular_nombre', 'LIKE', "%{$f['titular']}%")->orWhere('r.titular_apellido', 'LIKE', "%{$f['titular']}%"));
            }
            if ($f['codigo'] !== '') {
                // Varios códigos separados por * como en el CI.
                $q->whereIn('r.codigo', array_filter(array_map('trim', explode('*', $f['codigo']))));
            }
            if ($f['fk_vendedor_id'] !== '') {
                $q->where('r.fk_usuario_id', (int) $f['fk_vendedor_id']);
            }
            if ($f['autorizado'] !== '') {
                $q->where('r.autorizado', (int) $f['autorizado']);
                $q->limit(100);
            }
        } else {
            $q->where('r.autorizado', 0)
                ->where('s.vigencia_ini', '>=', now()->toDateString())
                ->where('s.vigencia_ini', '<=', now()->addDays(21)->toDateString());
        }

        $filas = $q->get()->map(fn ($r) => [
            'reserva_id' => (int) $r->reserva_id,
            'codigo' => "{$r->tipocodigo}-{$r->codigo}",
            'fecha' => $this->dmy($r->mfecha),
            'titular' => trim((string) $r->ntitular, ', '),
            'pax' => (int) $r->maximo,
            'cliente' => $r->cliente_nombre,
            'moneda' => $r->fk_moneda_id,
            'total' => (float) $r->total,
            'autorizado' => (int) $r->autorizado === 1,
            'link' => "/app/operaciones/autorizar/{$area}/{$r->reserva_id}",
        ])->all();

        return Inertia::render('Operaciones/Autorizar', [
            'area' => $area,
            'filtros' => $f,
            'conFiltros' => $hayFiltros,
            'opciones' => [
                'clientes' => $this->clientesConReservas(),
                'vendedores' => $this->vendedores(),
            ],
            'filas' => $filas,
        ]);
    }

    public function file(string $area, int $id): Response
    {
        $this->sistema($area);
        $reserva = DB::table('reserva as r')
            ->join('cliente as c', 'c.cliente_id', '=', 'r.fk_cliente_id')
            ->leftJoin('sistema as si', 'si.sistema_id', '=', 'r.fk_sistema_id')
            ->where('r.reserva_id', $id)
            ->first(['r.reserva_id', 'r.tipocodigo', 'r.codigo', 'r.titular_nombre', 'r.titular_apellido', 'r.fk_guia_id', 'r.autorizado', 'r.fk_sistema_id', 'r.observaciones', 'c.cliente_nombre', 'si.sistema_nombre']);
        abort_if($reserva === null, 404);

        $servicios = DB::table('servicio as s')
            ->leftJoin('proveedor as p', 'p.proveedor_id', '=', 's.fk_proveedor_id')
            ->leftJoin('producto as pr', 'pr.producto_id', '=', 's.fk_producto_id')
            ->where('s.fk_reserva_id', $id)
            ->where('s.status', '<>', 'CA')
            ->orderBy('s.vigencia_ini')
            ->get(['s.servicio_id', 's.servicio_nombre', 's.fk_tipoproducto_id', 's.fk_producto_id', 's.fk_proveedor_id', 's.vigencia_ini', 's.vigencia_fin', 's.costo', 's.moneda_costo', 's.status', 's.adultos', 's.menores', 's.nro_confirmacion', 'p.proveedor_nombre', 'pr.producto_nombre']);
        $extras = $this->extras->deServicios($servicios->pluck('servicio_id')->all());

        return Inertia::render('Operaciones/AutorizarFile', [
            'area' => $area,
            'reserva' => [
                'id' => (int) $reserva->reserva_id,
                'codigo' => "{$reserva->tipocodigo}-{$reserva->codigo}",
                'titular' => trim("{$reserva->titular_apellido}, {$reserva->titular_nombre}", ', '),
                'cliente' => $reserva->cliente_nombre,
                'sistema' => $reserva->sistema_nombre,
                'fk_guia_id' => (int) $reserva->fk_guia_id,
                'autorizado' => (int) $reserva->autorizado === 1,
                'observaciones' => $this->decodificar($reserva->observaciones),
            ],
            'servicios' => $servicios->map(function ($s) use ($extras) {
                $e = $extras[(int) $s->servicio_id] ?? [];
                $editable = in_array($s->fk_tipoproducto_id, ['TRN', 'EXC', 'EVT'], true) || (int) $s->fk_producto_id === 0;

                return [
                    'servicio_id' => (int) $s->servicio_id,
                    'nombre' => $s->servicio_nombre !== '' ? $s->servicio_nombre : (string) $s->producto_nombre,
                    'tipo' => $s->fk_tipoproducto_id,
                    'fk_proveedor_id' => (int) $s->fk_proveedor_id,
                    'proveedor' => $s->proveedor_nombre,
                    'in' => $this->dmy($s->vigencia_ini),
                    'out' => $this->dmy($s->vigencia_fin),
                    'costo' => (float) $s->costo,
                    'moneda_costo' => $s->moneda_costo,
                    'status' => $s->status,
                    'pax' => (int) $s->adultos + (int) $s->menores,
                    'nro_confirmacion' => $s->nro_confirmacion,
                    'editable' => $editable,
                    'pide_vuelo' => in_array($s->fk_tipoproducto_id, ['TRN', 'EVT'], true) || (int) $s->fk_producto_id === 0,
                    'ocultaritinerario' => ($e['ocultaritinerario'] ?? '') === '1' ? 1 : 0,
                    'horario' => $e['pickup_horario'] ?? ($e['pickup_hora'] ?? ''),
                    'vuelo' => $e['pickup_nvuelo'] ?? '',
                ];
            })->all(),
            'opciones' => [
                'guias' => $this->guias(),
                'proveedores' => $this->proveedoresConServicios(),
            ],
        ]);
    }

    /** Réplica de autorizar.php::actualizardatos(): guía del file y datos por servicio. */
    public function actualizar(Request $request, string $area, int $id): RedirectResponse
    {
        $this->sistema($area);
        abort_if(DB::table('reserva')->where('reserva_id', $id)->doesntExist(), 404);

        $data = $request->validate([
            'fk_guia_id' => 'nullable|integer',
            'servicios' => 'nullable|array',
            'servicios.*.servicio_id' => 'required|integer',
            'servicios.*.ocultaritinerario' => 'nullable|integer|in:0,1',
            'servicios.*.fk_proveedor_id' => 'nullable|integer',
            'servicios.*.horario' => 'nullable|string|max:100',
            'servicios.*.vuelo' => 'nullable|string|max:100',
        ]);

        DB::transaction(function () use ($data, $id) {
            DB::table('reserva')->where('reserva_id', $id)->update(['fk_guia_id' => (int) ($data['fk_guia_id'] ?? 0)]);

            foreach ((array) ($data['servicios'] ?? []) as $s) {
                $sid = (int) $s['servicio_id'];
                if (DB::table('servicio')->where('servicio_id', $sid)->where('fk_reserva_id', $id)->doesntExist()) {
                    continue;
                }
                if ((int) ($s['ocultaritinerario'] ?? 0) === 1) {
                    $this->extras->set($sid, 'ocultaritinerario', '1');
                } else {
                    $this->extras->borrar($sid, 'ocultaritinerario');
                }
                if (! empty($s['fk_proveedor_id'])) {
                    DB::table('servicio')->where('servicio_id', $sid)->update(['fk_proveedor_id' => (int) $s['fk_proveedor_id']]);
                }
                if (array_key_exists('vuelo', $s)) {
                    $this->extras->set($sid, 'pickup_nvuelo', $s['vuelo']);
                }
                if (array_key_exists('horario', $s)) {
                    $this->extras->set($sid, 'pickup_horario', $s['horario']);
                }
            }
        });

        return back()->with('success', 'Los datos fueron actualizados con éxito.');
    }

    /** Réplica de autorizar.php::autorizaraccion(). */
    public function autorizar(string $area, int $id): RedirectResponse
    {
        $this->sistema($area);
        abort_if(DB::table('reserva')->where('reserva_id', $id)->doesntExist(), 404);

        DB::transaction(function () use ($id) {
            DB::table('reserva')->where('reserva_id', $id)->update(['autorizado' => 1]);
            DB::table('reserva_extra')->where('fk_reserva_id', $id)->whereIn('extra_nombre', ['autorizado', 'autorizadofecha'])->delete();
            DB::table('reserva_extra')->insert([
                ['fk_reserva_id' => $id, 'extra_nombre' => 'autorizadofecha', 'extra_valor' => (string) time(), 'regdate' => now()->toDateTimeString()],
                ['fk_reserva_id' => $id, 'extra_nombre' => 'autorizado', 'extra_valor' => (string) auth()->id(), 'regdate' => now()->toDateTimeString()],
            ]);
        });

        return redirect("/app/operaciones/autorizar/{$area}")->with('success', 'Se ha autorizado el file con éxito.');
    }
}
