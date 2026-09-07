<?php

namespace App\Services\Reservas;

use App\Services\CotizacionService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Contexto de venta del generador de reservas: lo que el vendedor necesita
 * saber del cliente ANTES de buscar productos.
 *
 *  - Tarifario que va a regir la cotización: `rel_clientesistema.fk_tarifario_id`
 *    y, si no hay fila, la columna `cliente.fk_tarifario{sistema}_id`
 *    (reserva.php:3341-3350). Sin tarifario el CI no cotiza (tarifa_model:1310).
 *  - Crédito: límite, si el control está activo y lo utilizado (mismo cálculo
 *    que control de crédito, CreditoClienteService).
 *  - Historial (12 meses): últimas reservas con sus destinos, destinos y tipos
 *    de producto más comprados y gasto promedio en moneda básica. Sirve para
 *    ofrecer lo que el cliente suele llevar y para comparar precios (OfertasService).
 */
class ContextoVentaService
{
    public const HISTORIAL_MESES = 12;

    public function __construct(
        private GeneradorReservaService $generador,
        private CreditoClienteService $credito,
        private CotizacionService $cotizaciones,
    ) {}

    /**
     * Autocomplete de clientes habilitados para el área (mismas reglas que el combo del CI).
     *
     * @return list<array{id:int,label:string,limite:float,credito_habilitado:int,vendedor:int}>
     */
    public function clientes(int $idsistema, string $q, int $limite = 30): array
    {
        $q = trim($q);
        if ($q === '') {
            return [];
        }
        $query = $this->generador->queryClientes($idsistema)->limit($limite);
        $query->where(function ($w) use ($q) {
            $w->where('cliente.cliente_nombre', 'LIKE', "%{$q}%")->orWhere('cliente.cliente_razonsocial', 'LIKE', "%{$q}%");
            if (ctype_digit($q)) {
                $w->orWhere('cliente.cliente_id', (int) $q);
            }
        });

        return $query->get(['cliente.cliente_id', 'cliente.cliente_nombre', 'cliente.limite_credito', 'cliente.credito_habilitado', 'cliente.fk_usuario_vendedor'])
            ->map(fn ($c) => [
                'id' => (int) $c->cliente_id,
                'label' => trim((string) $c->cliente_nombre),
                'limite' => (float) $c->limite_credito,
                'credito_habilitado' => (int) $c->credito_habilitado,
                'vendedor' => (int) $c->fk_usuario_vendedor,
            ])->all();
    }

    /** Contexto completo de un cliente para el área. Null si el cliente no existe o no está habilitado para el área. */
    public function resolver(int $clienteId, int $idsistema): ?array
    {
        $cliente = $this->generador->queryClientes($idsistema)->where('cliente.cliente_id', $clienteId)->first(['cliente.*']);
        if ($cliente === null) {
            return null;
        }

        $tarifario = $this->tarifario($clienteId, $idsistema);
        $limite = (float) $cliente->limite_credito;
        $habilitado = (int) $cliente->credito_habilitado === 1;
        $utilizado = $habilitado && $limite > 0 ? $this->credito->utilizado($clienteId) : null;

        return [
            'cliente' => [
                'id' => (int) $cliente->cliente_id,
                'nombre' => trim((string) $cliente->cliente_nombre),
                'vendedor' => (int) $cliente->fk_usuario_vendedor,
                'moneda' => (string) $cliente->fk_moneda_id,
                'pasajero_directo' => (int) ($cliente->cliente_pasajerodirecto ?? 0) === 1,
                'email' => (string) ($cliente->cliente_email ?? ''),
                'telefono' => (string) ($cliente->cliente_telefono ?? ''),
            ],
            'tarifario' => $tarifario,
            'credito' => [
                'limite' => $limite,
                'habilitado' => $habilitado,
                'utilizado' => $utilizado,
                'disponible' => $utilizado === null ? null : round($limite - $utilizado, 2),
                'moneda' => $this->cotizaciones->monedaBasica(),
            ],
            'historial' => $this->historial($clienteId),
        ];
    }

    /**
     * Tarifario del cliente para el área: rel_clientesistema, o la columna
     * fk_tarifario{sistema}_id del cliente (sólo existe para 1, 2 y 3).
     *
     * @return array{id:int,nombre:string,moneda:string,origen:string}|null
     */
    public function tarifario(int $clienteId, int $idsistema): ?array
    {
        $id = (int) (DB::table('rel_clientesistema')->where('fk_cliente_id', $clienteId)->where('fk_sistema_id', $idsistema)->value('fk_tarifario_id') ?? 0);
        $origen = 'rel_clientesistema';
        if ($id === 0 && in_array($idsistema, [1, 2, 3], true)) {
            $col = "fk_tarifario{$idsistema}_id";
            $tiene = Cache::remember("schema.cliente.{$col}", 3600, fn () => Schema::hasColumn('cliente', $col));
            if ($tiene) {
                $id = (int) (DB::table('cliente')->where('cliente_id', $clienteId)->value($col) ?? 0);
                $origen = 'cliente';
            }
        }
        if ($id === 0) {
            return null;
        }
        $t = DB::table('tarifario')->where('tarifario_id', $id)->first(['tarifario_id', 'tarifario_nombre', 'fk_moneda_id']);
        if ($t === null) {
            return null;
        }

        return ['id' => (int) $t->tarifario_id, 'nombre' => (string) $t->tarifario_nombre, 'moneda' => (string) $t->fk_moneda_id, 'origen' => $origen];
    }

    /**
     * Últimas reservas, destinos y tipos más comprados y gasto promedio (12 meses).
     * Cache corta por cliente: se consulta cada vez que el vendedor lo elige.
     */
    public function historial(int $clienteId): array
    {
        return Cache::remember('generador.historial.'.\App\Support\Licencia::base().".{$clienteId}", 600, function () use ($clienteId) {
            $desde = now()->subMonths(self::HISTORIAL_MESES)->toDateString();
            $basica = $this->cotizaciones->monedaBasica();

            $ultimas = DB::table('reserva')->where('fk_cliente_id', $clienteId)->where('escotizacion', 0)
                ->orderByDesc('fecha_alta')->orderByDesc('reserva_id')->limit(5)
                ->get(['reserva_id', 'tipocodigo', 'codigo', 'fecha_alta', 'inicio', 'total', 'fk_moneda_id', 'titular_nombre', 'titular_apellido', 'fk_filestatus_id']);
            $ids = $ultimas->pluck('reserva_id')->map(fn ($i) => (int) $i)->all();
            $destinosPorReserva = [];
            if ($ids !== []) {
                $filas = DB::table('servicio as s')->join('ciudad as c', 'c.ciudad_id', '=', 's.fk_ciudad_id')
                    ->whereIn('s.fk_reserva_id', $ids)->where('s.status', '<>', 'CA')
                    ->distinct()->get(['s.fk_reserva_id', 'c.ciudad_nombre']);
                foreach ($filas as $f) {
                    $destinosPorReserva[(int) $f->fk_reserva_id][] = (string) $f->ciudad_nombre;
                }
            }

            $base = fn () => DB::table('servicio as s')->join('reserva as r', 'r.reserva_id', '=', 's.fk_reserva_id')
                ->where('r.fk_cliente_id', $clienteId)->where('r.escotizacion', 0)->where('r.fecha_alta', '>=', $desde)->where('s.status', '<>', 'CA');

            $destinos = $base()->join('ciudad as c', 'c.ciudad_id', '=', 's.fk_ciudad_id')->where('s.fk_ciudad_id', '>', 0)
                ->groupBy('s.fk_ciudad_id', 'c.ciudad_nombre')->orderByDesc('n')->limit(5)
                ->get(['s.fk_ciudad_id', 'c.ciudad_nombre', DB::raw('COUNT(*) as n')])
                ->map(fn ($d) => ['ciudad_id' => (int) $d->fk_ciudad_id, 'nombre' => (string) $d->ciudad_nombre, 'n' => (int) $d->n])->all();

            $tipos = $base()->leftJoin('submodulo as sm', 'sm.tipoproducto_id', '=', 's.fk_tipoproducto_id')
                ->groupBy('s.fk_tipoproducto_id', 'sm.tipoproducto_nombre')->orderByDesc('n')->limit(5)
                ->get(['s.fk_tipoproducto_id', 'sm.tipoproducto_nombre', DB::raw('COUNT(*) as n')])
                ->map(fn ($t) => ['tipo' => (string) $t->fk_tipoproducto_id, 'nombre' => (string) ($t->tipoproducto_nombre ?: $t->fk_tipoproducto_id), 'n' => (int) $t->n])->all();

            $reservas = DB::table('reserva')->where('fk_cliente_id', $clienteId)->where('escotizacion', 0)->where('fecha_alta', '>=', $desde)
                ->whereNotIn('fk_filestatus_id', ['CA', 'AN'])->get(['total', 'fk_moneda_id']);
            $suma = 0.0;
            $cot = [];
            foreach ($reservas as $r) {
                $m = (string) $r->fk_moneda_id;
                $cot[$m] ??= $m === $basica || $m === '' ? 1.0 : ($this->cotizaciones->aLaVenta($m) ?: 1.0);
                $suma += (float) $r->total * $cot[$m];
            }

            return [
                'desde' => $desde,
                'ultimas' => $ultimas->map(fn ($r) => [
                    'reserva_id' => (int) $r->reserva_id, 'codigo' => "{$r->tipocodigo}-{$r->codigo}", 'fecha_alta' => substr((string) $r->fecha_alta, 0, 10), 'inicio' => substr((string) $r->inicio, 0, 10),
                    'total' => (float) $r->total, 'moneda' => (string) $r->fk_moneda_id, 'titular' => trim("{$r->titular_apellido}, {$r->titular_nombre}", ', '), 'status' => (string) $r->fk_filestatus_id,
                    'destinos' => array_values(array_unique($destinosPorReserva[(int) $r->reserva_id] ?? [])),
                ])->all(),
                'destinos_top' => $destinos,
                'tipos_top' => $tipos,
                'reservas' => $reservas->count(),
                'gasto_promedio' => ['valor' => $reservas->count() > 0 ? round($suma / $reservas->count(), 2) : 0.0, 'moneda' => $basica],
            ];
        });
    }

    /** Autocomplete de proveedores habilitados (para el servicio manual del carrito). */
    public function proveedores(string $q, int $limite = 30): array
    {
        $q = trim($q);
        if ($q === '') {
            return [];
        }

        return DB::table('proveedor')->whereIn('habilita', ['Y', '1'])->where('eliminar', '<>', 'Y')
            ->where('proveedor_nombre', 'LIKE', "%{$q}%")->orderBy('proveedor_nombre')->limit($limite)
            ->get(['proveedor_id', 'proveedor_nombre'])
            ->map(fn ($p) => ['id' => (int) $p->proveedor_id, 'label' => trim((string) $p->proveedor_nombre)])->all();
    }

    /** Autocomplete de ciudades activas; con `tipo`, sólo las que tienen productos habilitados de ese tipo en el sistema. */
    public function ciudades(string $q, string $tipo = '', int $sistemaProductos = 0, int $limite = 30): array
    {
        $q = trim($q);
        if ($q === '') {
            return [];
        }
        $query = DB::table('ciudad')->where('ciudad.ciudad_activo', 1)->where('ciudad.ciudad_nombre', 'LIKE', "%{$q}%")->orderBy('ciudad.ciudad_nombre')->limit($limite);
        if ($tipo !== '') {
            $query->whereExists(function ($s) use ($tipo, $sistemaProductos) {
                $s->selectRaw('1')->from('rel_productociudad as rpc')->join('producto as p', 'p.producto_id', '=', 'rpc.fk_producto_id')
                    ->whereColumn('rpc.fk_ciudad_id', 'ciudad.ciudad_id')->where('p.fk_tipoproducto_id', $tipo)->where('p.habilitar', 1)->where('p.eliminar', 0);
                if ($sistemaProductos > 0) {
                    $s->where('p.fk_sistema_id', $sistemaProductos);
                }
            });
        }

        return $query->get(['ciudad.ciudad_id', 'ciudad.ciudad_nombre'])
            ->map(fn ($c) => ['id' => (int) $c->ciudad_id, 'label' => trim((string) $c->ciudad_nombre)])->all();
    }
}
