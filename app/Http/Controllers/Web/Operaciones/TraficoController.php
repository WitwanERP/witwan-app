<?php

namespace App\Http\Controllers\Web\Operaciones;

use App\Services\Operaciones\ServicioExtraService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Operaciones > Tráfico (CI: operaciones/trafico): servicios del área con
 * sus datos de pickup/dropoff/vuelo/idioma editables en línea, cambio de
 * proveedor y prorrateo de un costo entre servicios.
 *
 * No se porta "reasignar" (recotiza con el tarifador del CI al cambiar de
 * proveedor): acá el cambio de proveedor se guarda sin recotizar.
 */
class TraficoController extends OperacionesController
{
    public function __construct(private ServicioExtraService $extras) {}

    public function lista(Request $request, string $area): Response
    {
        $sistema = $this->sistema($area);
        $f = [
            'from' => $this->iso($request->get('from')),
            'to' => $this->iso($request->get('to')),
            'tipocodigo' => trim((string) $request->get('tipocodigo', '')),
            'tipo' => array_values(array_filter((array) $request->get('tipo', []), fn ($x) => $x !== '')),
            'cliente' => (string) $request->get('cliente', ''),
            'producto' => (string) $request->get('producto', ''),
            'proveedor' => (string) $request->get('proveedor', ''),
            'titular' => trim((string) $request->get('titular', '')),
            'codigo' => trim((string) $request->get('codigo', '')),
            'negocio' => (string) $request->get('negocio', ''),
            'orden' => (string) $request->get('orden', ''),
        ];
        $mostrar = collect($f)->except('orden')->contains(fn ($v) => $v !== '' && $v !== []);

        $filas = $mostrar ? $this->consultar($sistema, $f) : [];

        return Inertia::render('Operaciones/Trafico', [
            'area' => $area,
            'filtros' => $f,
            'consultado' => $mostrar,
            'opciones' => [
                'proveedores' => $this->proveedoresDelSistema($sistema),
                'clientes' => $this->clientesDelSistema($sistema),
                'negocios' => DB::table('negocio')->orderBy('negocio_nombre')->get(['negocio_id', 'negocio_nombre'])
                    ->map(fn ($n) => ['value' => (int) $n->negocio_id, 'label' => $n->negocio_nombre])->all(),
                'productos' => $f['proveedor'] !== '' ? $this->productosDelProveedor((int) $f['proveedor']) : [],
                'tipos' => DB::table('submodulo')->orderBy('tipoproducto_nombre')->get(['tipoproducto_id', 'tipoproducto_nombre'])
                    ->map(fn ($t) => ['value' => $t->tipoproducto_id, 'label' => "{$t->tipoproducto_id} - {$t->tipoproducto_nombre}"])->all(),
                'monedas' => DB::table('moneda')->orderBy('orden')->pluck('moneda_id')->unique()->map(fn ($m) => ['value' => $m, 'label' => $m])->values()->all(),
            ],
            'filas' => $filas,
        ]);
    }

    /** Productos (servicios con producto) de un proveedor, para el filtro dependiente. */
    public function productos(string $area, int $proveedor): JsonResponse
    {
        $this->sistema($area);

        return response()->json($this->productosDelProveedor($proveedor));
    }

    public function guardar(Request $request, string $area): RedirectResponse
    {
        $this->sistema($area);
        $accion = (string) $request->input('accion', '');
        $ids = array_map('intval', (array) $request->input('ids', []));
        $volver = "/app/operaciones/trafico/{$area}?".http_build_query((array) $request->input('qst', []));

        if ($ids === []) {
            return redirect($volver)->with('error', 'No hay servicios seleccionados.');
        }

        if ($accion === 'prorratear') {
            $data = $request->validate([
                'pro_proveedor' => 'required|integer|min:1',
                'pro_costo' => 'required|numeric|min:0',
                'pro_ivacosto' => 'nullable|numeric|min:0',
                'pro_moneda' => 'required|string|max:3',
            ]);
            $this->prorratear($ids, $data);

            return redirect($volver)->with('success', 'Costo prorrateado entre los servicios seleccionados.');
        }

        if ($accion === 'guardarcambios') {
            $cambios = (array) $request->input('servicios', []);
            DB::transaction(function () use ($ids, $cambios) {
                foreach ($ids as $sid) {
                    $c = (array) ($cambios[$sid] ?? []);
                    foreach (['idioma' => 'idioma', 'pickup' => 'pickup', 'dropoff' => 'dropoff', 'dropoff_hora' => 'dropoff_horario', 'pickup_hora' => 'pickup_horario', 'codigo_vuelo' => 'codigo_vuelo', 'dropoff_nvuelo' => 'dropoff_nvuelo', 'trafico_contacto' => 'trafico_contacto'] as $campo => $extra) {
                        if (array_key_exists($campo, $c)) {
                            $this->extras->set($sid, $extra, $c[$campo]);
                        }
                    }
                    if (array_key_exists('comentario', $c)) {
                        DB::table('servicio')->where('servicio_id', $sid)->update(['comentarios' => (string) $c['comentario']]);
                    }
                    if (! empty($c['proveedor'])) {
                        DB::table('servicio')->where('servicio_id', $sid)->update(['fk_proveedor_id' => (int) $c['proveedor']]);
                    }
                }
            });

            return redirect($volver)->with('success', 'Cambios guardados.');
        }

        if ($accion === 'confirmar') {
            $data = $request->validate(['pickup_avisado_entre' => 'nullable|string|max:100', 'pickup_avisado_hasta' => 'nullable|string|max:100', 'trafico_contacto' => 'nullable|string|max:150']);
            DB::transaction(function () use ($ids, $data) {
                foreach ($ids as $sid) {
                    $this->extras->set($sid, 'pickup_avisado_entre', $data['pickup_avisado_entre'] ?? '');
                    $this->extras->set($sid, 'pickup_avisado_hasta', $data['pickup_avisado_hasta'] ?? '');
                    $this->extras->set($sid, 'trafico_contacto', $data['trafico_contacto'] ?? '');
                }
            });

            return redirect($volver)->with('success', 'Aviso de pickup registrado.');
        }

        return redirect($volver)->with('error', 'Acción desconocida.');
    }

    /** Réplica de trafico.php::guardar() accion=prorratear: reparte costo e IVA por pax. */
    private function prorratear(array $ids, array $data): void
    {
        $servicios = DB::table('servicio')->whereIn('servicio_id', $ids)->get(['servicio_id', DB::raw('adultos + menores + juniors AS total')]);
        $totalPax = (int) $servicios->sum('total');
        if ($totalPax <= 0) {
            return;
        }
        $porPax = round((float) $data['pro_costo'] / $totalPax, 2);
        $ivaPorPax = round((float) ($data['pro_ivacosto'] ?? 0) / $totalPax, 2);

        DB::transaction(function () use ($servicios, $porPax, $ivaPorPax, $data) {
            foreach ($servicios as $s) {
                DB::table('servicio')->where('servicio_id', $s->servicio_id)->update([
                    'costo' => round($porPax * (int) $s->total, 2),
                    'iva_costo' => round($ivaPorPax * (int) $s->total, 2),
                    'fk_proveedor_id' => (int) $data['pro_proveedor'],
                    'moneda_costo' => $data['pro_moneda'],
                ]);
            }
        });
    }

    private function consultar(int $sistema, array $f): array
    {
        $q = DB::table('servicio as s')
            ->join('reserva as r', 'r.reserva_id', '=', 's.fk_reserva_id')
            ->join('usuario as u', 'u.usuario_id', '=', 'r.fk_usuario_id')
            ->join('cliente as c', 'c.cliente_id', '=', 'r.fk_cliente_id')
            ->join('proveedor as p', 'p.proveedor_id', '=', 's.fk_proveedor_id')
            ->leftJoin('producto as pr', 'pr.producto_id', '=', 's.fk_producto_id')
            ->where('s.status', '<>', 'CA')
            ->whereNotIn('r.fk_filestatus_id', ['PG', 'CA', 'CT', 'DL'])
            ->where(fn ($w) => $w->where(fn ($x) => $x->where('r.fk_sistema_id', $sistema)->where('r.fk_sistemaaplicacion_id', 0))->orWhere('r.fk_sistemaaplicacion_id', $sistema))
            ->groupBy('s.servicio_id')
            ->select('r.reserva_id', 'r.codigo', 'r.tipocodigo', 's.fk_tipoproducto_id', 's.fk_proveedor_id', 's.fk_producto_id', 's.servicio_id', 's.servicio_nombre',
                'c.cliente_nombre', 'p.proveedor_nombre', 'p.proveedor_telefono', 's.vigencia_ini', 's.adultos', 's.menores', 's.infante', 's.costo', 's.iva_costo', 's.moneda_costo', 's.comentarios',
                DB::raw('MAX(s.adultos) AS maximo'),
                DB::raw("CONCAT(r.titular_apellido, ' ', r.titular_nombre) AS ntitular"),
                DB::raw("CONCAT(u.usuario_nombre, ' ', u.usuario_apellido) AS nusuario"));

        if ($f['tipo'] !== []) {
            $q->whereIn('s.fk_tipoproducto_id', $f['tipo']);
        }
        if ($f['tipocodigo'] !== '') {
            $q->where('r.tipocodigo', $f['tipocodigo']);
        }
        if ($f['negocio'] !== '') {
            $q->where('r.fk_negocio_id', (int) $f['negocio']);
        }
        if ($f['titular'] !== '') {
            $q->where(fn ($w) => $w->where('r.titular_apellido', 'LIKE', "%{$f['titular']}%")->orWhere('r.titular_nombre', 'LIKE', "%{$f['titular']}%"));
        }
        if ($f['codigo'] !== '') {
            $q->whereIn('r.codigo', array_filter(array_map('trim', explode('*', $f['codigo']))));
        }
        if ($f['proveedor'] !== '') {
            $q->where('s.fk_proveedor_id', (int) $f['proveedor']);
        }
        if ($f['cliente'] !== '') {
            $q->where('r.fk_cliente_id', (int) $f['cliente']);
        }
        if ($f['producto'] !== '') {
            $q->where('s.fk_producto_id', (int) $f['producto']);
        }
        if ($f['from'] !== '') {
            $q->where('s.vigencia_ini', '>=', $f['from']);
        }
        if ($f['to'] !== '') {
            $q->where('s.vigencia_ini', '<=', $f['to']);
        }
        match ($f['orden']) {
            'fecha' => $q->orderBy('s.vigencia_ini'),
            'cliente' => $q->orderBy('c.cliente_nombre'),
            'proveedor' => $q->orderBy('p.proveedor_nombre'),
            'servicio' => $q->orderBy('pr.producto_nombre'),
            default => $q->orderBy('r.codigo')->orderBy('s.vigencia_ini'),
        };

        $servicios = $q->get();
        $extras = $this->extras->deServicios($servicios->pluck('servicio_id')->all());
        $out = [];
        foreach ($servicios as $s) {
            $e = $extras[(int) $s->servicio_id] ?? [];
            if (($e['ocultaritinerario'] ?? '') === '1') {
                continue;
            }
            $out[] = [
                'servicio_id' => (int) $s->servicio_id,
                'reserva_id' => (int) $s->reserva_id,
                'codigo' => "{$s->tipocodigo}-{$s->codigo}",
                'fecha' => $this->dmy($s->vigencia_ini),
                'titular' => trim((string) $s->ntitular),
                'pax' => (int) $s->maximo,
                'servicio' => $s->servicio_nombre,
                'tipo' => $s->fk_tipoproducto_id,
                'cliente' => $s->cliente_nombre,
                'vendedor' => trim((string) $s->nusuario),
                'fk_proveedor_id' => (int) $s->fk_proveedor_id,
                'proveedor' => $s->proveedor_nombre,
                'proveedores' => $this->proveedoresAlternativos($s),
                'moneda_costo' => $s->moneda_costo,
                'costo' => (float) $s->costo,
                'iva_costo' => (float) $s->iva_costo,
                'idioma' => $e['idioma'] ?? '',
                'pickup' => $e['pickup'] ?? '',
                'dropoff' => $e['dropoff'] ?? '',
                'dropoff_hora' => $e['dropoff_horario'] ?? ($e['pickup_horario'] ?? ($e['hora_pickup'] ?? ($e['horario_pickup'] ?? ($e['horario_dropoff'] ?? '')))),
                'codigo_vuelo' => $e['codigo_vuelo'] ?? ($e['dropoff_nvuelo'] ?? ($e['pickup_nvuelo'] ?? '')),
                'trafico_contacto' => $e['trafico_contacto'] ?? '',
                'pickup_avisado' => trim(($e['pickup_avisado_entre'] ?? '').' - '.($e['pickup_avisado_hasta'] ?? ''), ' -'),
                'comentario' => $this->decodificar($s->comentarios),
            ];
        }

        return $out;
    }

    /** Proveedores que ofrecen el mismo producto con vigencia en la fecha (réplica de `_proveedores_`). */
    private function proveedoresAlternativos(object $s): array
    {
        if ((int) $s->fk_producto_id === 0) {
            return [['value' => (int) $s->fk_proveedor_id, 'label' => (string) $s->proveedor_nombre]];
        }

        return DB::table('producto as pr')
            ->join('proveedor as p', 'p.proveedor_id', '=', 'pr.fk_proveedor_id')
            ->join('vigencia as v', 'v.fk_producto_id', '=', 'pr.producto_id')
            ->where(fn ($w) => $w->where('pr.producto_nombre', $s->servicio_nombre)->orWhere('p.proveedor_id', (int) $s->fk_proveedor_id))
            ->where('v.vigencia_ini', '<=', $s->vigencia_ini)
            ->where('v.vigencia_fin', '>=', $s->vigencia_ini)
            ->groupBy('p.proveedor_id', 'p.proveedor_nombre')
            ->get(['p.proveedor_id', 'p.proveedor_nombre'])
            ->map(fn ($p) => ['value' => (int) $p->proveedor_id, 'label' => $p->proveedor_nombre])
            ->all();
    }

    private function proveedoresDelSistema(int $sistema): array
    {
        return DB::table('proveedor as p')
            ->join('servicio as s', 's.fk_proveedor_id', '=', 'p.proveedor_id')
            ->join('reserva as r', 'r.reserva_id', '=', 's.fk_reserva_id')
            ->whereIn('p.habilita', ['Y', '1'])
            ->where('r.fk_sistema_id', $sistema)
            ->groupBy('p.proveedor_id', 'p.proveedor_nombre')
            ->orderBy('p.proveedor_nombre')
            ->get(['p.proveedor_id', 'p.proveedor_nombre'])
            ->map(fn ($p) => ['value' => (int) $p->proveedor_id, 'label' => $p->proveedor_nombre])
            ->all();
    }

    private function clientesDelSistema(int $sistema): array
    {
        return DB::table('cliente as c')
            ->join('reserva as r', 'r.fk_cliente_id', '=', 'c.cliente_id')
            ->where('r.fk_sistema_id', $sistema)
            ->groupBy('c.cliente_id', 'c.cliente_nombre')
            ->orderBy('c.cliente_nombre')
            ->get(['c.cliente_id', 'c.cliente_nombre'])
            ->map(fn ($c) => ['value' => (int) $c->cliente_id, 'label' => $c->cliente_nombre])
            ->all();
    }

    private function productosDelProveedor(int $proveedor): array
    {
        return DB::table('servicio as s')
            ->join('proveedor as p', 'p.proveedor_id', '=', 's.fk_proveedor_id')
            ->whereIn('p.habilita', ['Y', '1'])
            ->where('p.proveedor_id', $proveedor)
            ->where('s.fk_producto_id', '<>', 0)
            ->groupBy('s.fk_producto_id')
            ->orderBy(DB::raw('MIN(s.servicio_nombre)'))
            ->get(['s.fk_producto_id', DB::raw('MIN(s.servicio_nombre) AS servicio_nombre')])
            ->map(fn ($r) => ['value' => (int) $r->fk_producto_id, 'label' => $r->servicio_nombre])
            ->all();
    }
}
