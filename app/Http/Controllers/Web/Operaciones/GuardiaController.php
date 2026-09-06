<?php

namespace App\Http\Controllers\Web\Operaciones;

use App\Services\Operaciones\ServicioExtraService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Operaciones > Planilla de guardia (CI: operaciones/guardia). La lista muestra
 * los files CO/CL del área con servicios en el rango (default hoy + 15 días);
 * se tildan files y se genera la planilla (servicios, proveedor y teléfonos,
 * pickups/vuelos) que también se exporta.
 */
class GuardiaController extends OperacionesController
{
    private const TIPOS_PRODUCTO = ['HOT', 'EXC', 'TRN', 'PAQ', 'AEL', 'CAE', 'TRA'];

    public function __construct(private ServicioExtraService $extras) {}

    public function lista(Request $request, string $area): Response
    {
        $sistema = $this->sistema($area);
        $f = $this->filtros($request, $sistema);

        $q = DB::table('reserva as r')
            ->join('servicio as s', 's.fk_reserva_id', '=', 'r.reserva_id')
            ->leftJoin('servicio_nomina as sn', 'sn.fk_servicio_id', '=', 's.servicio_id')
            ->leftJoin('guia as g', 'g.guia_id', '=', 'r.fk_guia_id')
            ->leftJoin('proveedor as p', 'p.proveedor_id', '=', 's.fk_proveedor_id')
            ->leftJoin('cliente as c', 'c.cliente_id', '=', 'r.fk_cliente_id')
            ->leftJoin('cadenacliente as cc', 'cc.cadenacliente_id', '=', 'c.fk_cadenacliente_id')
            ->where(function ($w) use ($f) {
                $w->whereRaw('? BETWEEN s.vigencia_ini AND s.vigencia_fin', [$f['from']])
                    ->orWhereBetween('s.vigencia_ini', [$f['from'], $f['to']]);
            })
            ->whereIn('r.fk_filestatus_id', ['CO', 'CL'])
            ->where('s.status', '<>', 'CA')
            ->where(fn ($w) => $w->where('r.fk_sistema_id', $sistema)->orWhere('r.fk_sistemaaplicacion_id', $sistema))
            ->groupBy('r.reserva_id')
            ->orderBy(DB::raw('MIN(s.vigencia_ini)'))
            ->select('r.reserva_id', 'r.fk_cliente_id', 'r.codigo', 'r.tipocodigo', 'r.observaciones', 'c.cliente_nombre', 'cc.cadenacliente_nombre',
                DB::raw("MAX(CONCAT(g.guia_apellido, IF(g.guia_nombre <> '', ', ', ''), g.guia_nombre)) AS nguia"),
                DB::raw('MAX(s.adultos) AS maximo'), DB::raw('MAX(s.vigencia_ini) AS mfecha'),
                DB::raw("CONCAT(r.titular_apellido, ', ', r.titular_nombre) AS ntitular"),
                DB::raw('MAX(p.proveedor_telefono) AS proveedor_telefono'), DB::raw('MAX(p.proveedor_telefonoemergencia) AS proveedor_telefonoemergencia'),
                DB::raw('MAX(p.proveedor_nombre) AS proveedor_nombre'));

        if ($f['tipo'] !== []) {
            $q->whereIn('s.fk_tipoproducto_id', $f['tipo']);
        } else {
            $q->whereNotIn('s.fk_tipoproducto_id', ['GRP', 'AER']);
        }
        if ($f['tiporeserva'] !== []) {
            $q->whereIn('r.tipocodigo', $f['tiporeserva']);
        }
        if ($f['cliente'] !== '') {
            $q->where('r.fk_cliente_id', (int) $f['cliente']);
        }
        if ($f['proveedor'] !== '') {
            $q->where('s.fk_proveedor_id', (int) $f['proveedor']);
        }
        if ($f['fk_cadenacliente_id'] !== '') {
            $q->where('c.fk_cadenacliente_id', (int) $f['fk_cadenacliente_id']);
        }
        if ($f['guia'] !== '') {
            $q->where('r.fk_guia_id', (int) $f['guia']);
        }
        if ($f['titular'] !== '') {
            $q->where(fn ($w) => $w->where('r.titular_apellido', 'LIKE', "%{$f['titular']}%")->orWhere('sn.apellido', 'LIKE', "%{$f['titular']}%"));
        }
        if ($f['codigo'] !== '') {
            $q->where('r.codigo', $f['codigo']);
        }

        $filas = $q->get()->map(fn ($r) => [
            'reserva_id' => (int) $r->reserva_id,
            'codigo' => "{$r->tipocodigo}-{$r->codigo}",
            'fecha' => $this->dmy($r->mfecha),
            'titular' => trim((string) $r->ntitular, ', '),
            'pax' => (int) $r->maximo,
            'guia' => trim((string) $r->nguia, ', '),
            'cliente' => $r->cliente_nombre,
            'cadena' => $r->cadenacliente_nombre,
            'proveedor' => $r->proveedor_nombre,
            'telefono' => $r->proveedor_telefono,
            'emergencia' => $r->proveedor_telefonoemergencia,
            'observaciones' => $this->decodificar($r->observaciones),
        ])->all();

        return Inertia::render('Operaciones/Guardia', [
            'area' => $area,
            'filtros' => $f,
            'opciones' => [
                'tipos' => array_map(fn ($t) => ['value' => $t, 'label' => $t], self::TIPOS_PRODUCTO),
                'tiposReserva' => $this->tiposReserva($sistema, $area === 'all'),
                'guias' => $this->guias(),
                'clientes' => $this->clientesConReservas(),
                'proveedores' => $this->proveedoresConServicios(),
                'cadenas' => DB::table('cadenacliente')->orderBy('cadenacliente_nombre')->get(['cadenacliente_id', 'cadenacliente_nombre'])
                    ->map(fn ($c) => ['value' => (int) $c->cadenacliente_id, 'label' => $c->cadenacliente_nombre])->all(),
            ],
            'filas' => $filas,
        ]);
    }

    /** Planilla de los files tildados (POST ids[], vini, vfin, resumida). */
    public function reporte(Request $request, string $area): Response|StreamedResponse
    {
        $this->sistema($area);
        $data = $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'integer',
            'vini' => 'nullable|string',
            'vfin' => 'nullable|string',
            'resumida' => 'nullable|in:0,1',
            'export' => 'nullable|in:0,1',
        ]);

        $vini = $this->iso($data['vini'] ?? '') ?: now()->toDateString();
        $vfin = $this->iso($data['vfin'] ?? '') ?: $vini;
        $resumida = (string) ($data['resumida'] ?? '0') === '1';
        $planilla = $this->planilla(array_map('intval', $data['ids']), $vini, $vfin, $resumida);

        if ((string) ($data['export'] ?? '0') === '1') {
            return $this->exportar($planilla);
        }

        return Inertia::render('Operaciones/GuardiaReporte', [
            'area' => $area,
            'rango' => ['desde' => $this->dmy($vini), 'hasta' => $this->dmy($vfin)],
            'parametros' => ['ids' => array_map('intval', $data['ids']), 'vini' => $vini, 'vfin' => $vfin, 'resumida' => $resumida ? 1 : 0],
            'planilla' => $planilla,
        ]);
    }

    /** @return list<array<string,mixed>> */
    private function planilla(array $ids, string $vini, string $vfin, bool $resumida): array
    {
        $files = DB::table('reserva as r')
            ->join('cliente as c', 'c.cliente_id', '=', 'r.fk_cliente_id')
            ->join('usuario as u', 'u.usuario_id', '=', 'r.fk_usuario_id')
            ->leftJoin('guia as g', 'g.guia_id', '=', 'r.fk_guia_id')
            ->whereIn('r.fk_filestatus_id', ['CO', 'CL'])
            ->whereIn('r.reserva_id', $ids)
            ->groupBy('r.reserva_id')
            ->get(['r.reserva_id', 'r.tipocodigo', 'r.codigo', 'c.cliente_nombre', 'c.cliente_telefono',
                DB::raw("CONCAT(u.usuario_apellido, ' ', u.usuario_nombre) AS nusuario"),
                DB::raw("CONCAT(g.guia_nombre, ' ', g.guia_apellido) AS nguia"),
                DB::raw("CONCAT(r.titular_nombre, ' ', r.titular_apellido) AS ntitular")]);

        $out = [];
        foreach ($files as $r) {
            $q = DB::table('servicio as s')
                ->join('proveedor as p', 'p.proveedor_id', '=', 's.fk_proveedor_id')
                ->leftJoin('producto as pr', 'pr.producto_id', '=', 's.fk_producto_id')
                ->leftJoin('tarifacategoria as tc', 'tc.tarifacategoria_id', '=', 's.fk_tarifacategoria_id')
                ->leftJoin('regimen as rg', 'rg.regimen_id', '=', 's.fk_regimen_id')
                ->whereIn('s.status', ['CO', 'CL', 'RQ'])
                ->where('s.fk_reserva_id', $r->reserva_id)
                ->orderBy('s.vigencia_ini')
                ->select('s.servicio_id', 's.servicio_nombre', 's.vigencia_ini', 's.vigencia_fin', 's.status', 's.comentarios', 's.nro_confirmacion',
                    'pr.producto_nombre', 'p.proveedor_nombre', 'p.proveedor_telefono', 'p.proveedor_telefonoemergencia', 'tc.tarifacategoria_nombre', 'rg.regimen_nombre',
                    DB::raw('(s.adultos + s.menores) AS paxs'));
            if ($resumida) {
                $q->where(fn ($w) => $w->whereRaw('? BETWEEN s.vigencia_ini AND s.vigencia_fin', [$vini])->orWhereBetween('s.vigencia_ini', [$vini, $vfin]));
            }
            $servicios = $q->get();
            $extras = $this->extras->deServicios($servicios->pluck('servicio_id')->all());

            $file = [
                'reserva_id' => (int) $r->reserva_id,
                'codigo' => "{$r->tipocodigo}-{$r->codigo}",
                'titular' => trim((string) $r->ntitular),
                'cliente' => $r->cliente_nombre,
                'telefono_cliente' => $r->cliente_telefono,
                'vendedor' => trim((string) $r->nusuario),
                'guia' => trim((string) $r->nguia),
                'paxs' => 0,
                'servicios' => [],
            ];

            foreach ($servicios as $s) {
                $e = $extras[(int) $s->servicio_id] ?? [];
                if (($e['ocultaritinerario'] ?? '') === '1') {
                    continue;
                }
                $nombre = $s->servicio_nombre !== '' ? $s->servicio_nombre : (string) $s->producto_nombre;
                if ($s->servicio_nombre === '' && (int) $s->paxs > $file['paxs']) {
                    $file['paxs'] = (int) $s->paxs;
                }
                if ($s->tarifacategoria_nombre) {
                    $nombre .= ' / '.$s->tarifacategoria_nombre;
                }
                if ($s->regimen_nombre) {
                    $nombre .= ' / '.$s->regimen_nombre;
                }
                $ini = (string) $s->vigencia_ini;
                $fin = (string) $s->vigencia_fin !== '0000-00-00' && $s->vigencia_fin ? (string) $s->vigencia_fin : $ini;
                $vuelo = null;
                if (isset($e['pickup_nvuelo']) || isset($e['pickup_horario'])) {
                    $vuelo = [
                        'pickup' => trim(($e['pickup'] ?? '').' '.($e['pickup_horario'] ?? '').($e['pickup_hora'] ?? '').($e['hora_pickup'] ?? '')),
                        'pickup_nvuelo' => $e['pickup_nvuelo'] ?? '',
                        'dropoff' => trim(($e['dropoff'] ?? '').' '.($e['dropoff_horario'] ?? '').($e['dropoff_hora'] ?? '').($e['hora_dropoff'] ?? '')),
                        'dropoff_nvuelo' => $e['dropoff_nvuelo'] ?? '',
                    ];
                }
                $file['servicios'][] = [
                    'servicio_id' => (int) $s->servicio_id,
                    'nombre' => $nombre,
                    'ini' => $this->dmy($ini),
                    'fin' => $this->dmy($fin),
                    'vigencia_ini' => $ini,
                    'vigencia_fin' => $fin,
                    'status' => $s->status,
                    'paxs' => (int) $s->paxs,
                    'cliente' => $r->cliente_nombre,
                    'proveedor' => $s->proveedor_nombre,
                    'telefono' => $s->proveedor_telefono,
                    'emergencia' => $s->proveedor_telefonoemergencia,
                    'nro_confirmacion' => $s->nro_confirmacion,
                    'comentarios' => $this->decodificar($s->comentarios),
                    'en_rango' => $ini <= $vfin && $fin >= $vini,
                    'vuelo' => $vuelo,
                ];
            }
            $out[] = $file;
        }

        return $out;
    }

    private function exportar(array $planilla): StreamedResponse
    {
        return response()->streamDownload(function () use ($planilla) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['RESERVA', 'PAX', 'FECHA IN', 'FECHA OUT', 'SERVICIO', 'CLIENTE', 'PROVEEDOR', 'CODIGO CONFIRMACION', 'TELEFONO PROVEEDOR', 'TELEFONO EMERGENCIA PROVEEDOR'], ';');
            foreach ($planilla as $r) {
                foreach ($r['servicios'] as $s) {
                    fputcsv($out, [$r['codigo'], $r['titular'], $s['ini'], $s['fin'], $s['nombre'], $s['cliente'], $s['proveedor'], $s['nro_confirmacion'], $s['telefono'], $s['emergencia']], ';');
                }
            }
            fclose($out);
        }, 'planilla-de-guardia-'.now()->format('Ymd-His').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function filtros(Request $request, int $sistema): array
    {
        $from = $this->iso($request->get('from')) ?: now()->toDateString();
        $to = $this->iso($request->get('to')) ?: now()->addDays(15)->toDateString();
        $lista = fn ($v) => array_values(array_filter(array_map('strval', (array) $request->get($v, [])), fn ($x) => $x !== ''));

        return [
            'from' => $from,
            'to' => $to,
            'tipo' => $lista('tipo'),
            'tiporeserva' => $lista('tiporeserva'),
            'guia' => (string) $request->get('guia', ''),
            'cliente' => (string) $request->get('cliente', ''),
            'proveedor' => (string) $request->get('proveedor', ''),
            'titular' => trim((string) $request->get('titular', '')),
            'codigo' => trim((string) $request->get('codigo', '')),
            'resumida' => (string) $request->get('resumida', '0') === '1' ? '1' : '0',
            'fk_cadenacliente_id' => (string) $request->get('fk_cadenacliente_id', ''),
        ];
    }

    private function tiposReserva(int $sistema, bool $todas): array
    {
        $q = DB::table('reserva')->where('tipocodigo', '<>', '')->whereNotIn('tipocodigo', ['DV', 'CM'])->where('fk_filestatus_id', '<>', 'CU');
        if (! $todas) {
            $q->where(fn ($w) => $w->where(fn ($x) => $x->where('fk_sistema_id', $sistema)->where('fk_sistemaaplicacion_id', 0))->orWhere('fk_sistemaaplicacion_id', $sistema));
        }

        return $q->distinct()->orderBy('tipocodigo')->pluck('tipocodigo')->map(fn ($t) => ['value' => $t, 'label' => $t])->values()->all();
    }
}
