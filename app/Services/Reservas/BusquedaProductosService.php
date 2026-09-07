<?php

namespace App\Services\Reservas;

use App\Models\User;
use App\Services\Reservas\Busqueda\BuscadorTipo;
use App\Services\Reservas\Busqueda\Presupuesto;
use App\Support\Licencia;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Orquestador de la búsqueda del generador: decide qué pestañas (tipos de
 * producto) se muestran, arma el contexto de venta, delega en el buscador del
 * tipo (config/reservas_busqueda.php) y ordena/cachea la respuesta.
 */
class BusquedaProductosService
{
    public function __construct(private ContextoVentaService $contexto, private GeneradorReservaService $generador) {}

    /**
     * Pestañas del paso "Buscar": tipos registrados en config que tengan productos
     * habilitados en el sistema (réplica del switch de reserva.php:4634-4736, que
     * decide por `SELECT DISTINCT fk_tipoproducto_id FROM producto`).
     *
     * @return list<array{tipo:string,nombre:string,campos:list<string>,requiere:list<string>,pickup:bool}>
     */
    public function pestanas(int $sistemaProductos, bool $interno): array
    {
        $cfg = (array) config('reservas_busqueda.tipos', []);
        $q = DB::table('producto')->where('habilitar', 1)->where('eliminar', 0)->where('fk_sistema_id', $sistemaProductos)->whereIn('fk_tipoproducto_id', array_keys($cfg));
        if (! $interno) {
            $q->where('aparece_tarifario', 1);
        }
        $tipos = $q->distinct()->pluck('fk_tipoproducto_id')->map(fn ($t) => (string) $t)->all();
        if ($tipos === []) {
            return [];
        }
        $nombres = DB::table('submodulo')->whereIn('tipoproducto_id', $tipos)->where('tipoproducto_activo', 1)->pluck('tipoproducto_nombre', 'tipoproducto_id');
        $conPickup = (array) config('reservas_busqueda.con_pickup', []);

        $out = [];
        foreach ($tipos as $t) {
            if (! isset($nombres[$t]) || $this->buscador($t) === null) {
                continue;
            }
            $out[] = [
                'tipo' => $t,
                'nombre' => (string) $nombres[$t],
                'orden' => (int) ($cfg[$t]['orden'] ?? 99),
                'campos' => array_values((array) ($cfg[$t]['campos'] ?? [])),
                'requiere' => array_values((array) ($cfg[$t]['requiere'] ?? [])),
                'pickup' => in_array($t, $conPickup, true),
            ];
        }
        usort($out, fn ($a, $b) => [$a['orden'], $a['nombre']] <=> [$b['orden'], $b['nombre']]);

        return $out;
    }

    /** Reglas de validación específicas del tipo (obligatorios del config + las del buscador). */
    public function reglas(string $tipo): array
    {
        $cfg = (array) config("reservas_busqueda.tipos.{$tipo}", []);
        $reglas = [];
        foreach ((array) ($cfg['requiere'] ?? []) as $r) {
            if (! str_contains($r, '|')) {
                $reglas[$r === 'producto' ? 'producto_id' : $r] = $r === 'habitaciones' ? 'required|array|min:1' : 'required';
            }
        }
        $buscador = $this->buscador($tipo);

        return $buscador ? array_merge($reglas, $buscador->reglas($tipo)) : $reglas;
    }

    /** Grupos "a|b|c" del config: al menos uno tiene que venir. @return list<list<string>> */
    public function alternativas(string $tipo): array
    {
        $out = [];
        foreach ((array) config("reservas_busqueda.tipos.{$tipo}.requiere", []) as $r) {
            if (str_contains($r, '|')) {
                $out[] = array_map(fn ($c) => $c === 'producto' ? 'producto_id' : $c, explode('|', $r));
            }
        }

        return $out;
    }

    /**
     * Contexto de venta que necesitan los buscadores: tarifario resuelto para el
     * cliente (rel_clientesistema con fallback), tipo de pasajero, usuario interno.
     */
    public function contexto(User $usuario, int $idsistema, int $clienteId, string $residente): array
    {
        $tarifario = $clienteId > 0 ? $this->contexto->tarifario($clienteId, $idsistema) : null;

        return [
            'idsistema' => $idsistema,
            'sistema_productos' => $idsistema === 3 ? 2 : $idsistema,
            'tarifario_id' => (int) ($tarifario['id'] ?? 0),
            'cliente_id' => $clienteId,
            'residente' => $residente === 'R' ? 'R' : 'N',
            'interno' => $this->esInterno($usuario),
            'hoy' => now()->toDateString(),
            'fecha_minima' => $this->generador->fechaMinima($usuario),
        ];
    }

    /**
     * Ejecuta la búsqueda del tipo. Ordena por mejor total (sold out al final),
     * cachea la respuesta completa unos minutos y devuelve métricas para tunear.
     *
     * @return array{tipo:string, resultados:list<array>, truncado:bool, candidatos:int, ms:int, cache:bool}
     */
    public function buscar(string $tipo, array $p, array $ctx): array
    {
        $buscador = $this->buscador($tipo);
        if ($buscador === null) {
            return ['tipo' => $tipo, 'resultados' => [], 'truncado' => false, 'candidatos' => 0, 'ms' => 0, 'cache' => false, 'error' => "No hay buscador para el tipo {$tipo}."];
        }
        $params = $this->normalizarParams($p);
        $clave = 'generador.busqueda.'.md5(json_encode([Licencia::base(), $tipo, $params, $ctx['sistema_productos'], $ctx['tarifario_id'], $ctx['residente'], $ctx['interno'], $ctx['hoy']]));
        $store = Cache::store(config('reservas_busqueda.cache_store'));
        $hit = true;
        $out = $store->remember($clave, (int) config('reservas_busqueda.cache_ttl', 120), function () use ($buscador, $tipo, $params, $ctx, &$hit) {
            $hit = false;
            $presupuesto = new Presupuesto((int) config('reservas_busqueda.presupuesto_ms', 8000));
            $r = $buscador->buscar($tipo, $params, $ctx, $presupuesto);
            // Mejor total primero; los sold out al final (reserva.php:3924-3945 los penaliza con +1e9).
            $so = fn (array $f) => $f['disponibilidad'] === 'SO' ? 1 : 0;
            usort($r['resultados'], fn ($a, $b) => [$so($a), (float) $a['mejor_total'], $a['nombre']] <=> [$so($b), (float) $b['mejor_total'], $b['nombre']]);
            $ms = $presupuesto->transcurridoMs();
            if ($ms > (int) config('reservas_busqueda.presupuesto_ms', 8000) / 2) {
                Log::info("Generador: búsqueda {$tipo} lenta ({$ms} ms, {$r['candidatos']} candidatos, ".count($r['resultados']).' resultados'.($r['truncado'] ? ', truncada' : '').')', ['params' => $params]);
            }

            return ['tipo' => $tipo, 'resultados' => $r['resultados'], 'truncado' => $r['truncado'], 'candidatos' => $r['candidatos'], 'ms' => $ms];
        });
        $out['cache'] = $hit;

        return $out;
    }

    public function buscador(string $tipo): ?BuscadorTipo
    {
        $clase = config("reservas_busqueda.tipos.{$tipo}.buscador");

        return $clase && class_exists($clase) ? app($clase) : null;
    }

    private function normalizarParams(array $p): array
    {
        $habs = [];
        foreach ((array) ($p['habitaciones'] ?? []) as $h) {
            $mn = array_values(array_map('intval', (array) ($h['mn'] ?? [])));
            sort($mn);
            $habs[] = ['ad' => (int) ($h['ad'] ?? 1), 'mn' => $mn];
        }
        $mn = array_values(array_map('intval', (array) ($p['mn'] ?? [])));
        sort($mn);
        $stars = array_values(array_unique(array_map('intval', (array) ($p['stars'] ?? []))));
        sort($stars);
        $ids = array_values(array_unique(array_map('intval', (array) ($p['producto_ids'] ?? []))));
        sort($ids);

        return [
            'from' => (string) ($p['from'] ?? ''),
            'to' => (string) ($p['to'] ?? ''),
            'ciudad' => (int) ($p['ciudad'] ?? 0),
            'origen' => (int) ($p['origen'] ?? 0),
            'destino' => (int) ($p['destino'] ?? 0),
            'nombre' => mb_strtolower(trim((string) ($p['nombre'] ?? ''))),
            'producto_id' => (int) ($p['producto_id'] ?? 0),
            'producto_ids' => $ids,
            'stars' => $stars,
            'habitaciones' => $habs,
            'ad' => (int) ($p['ad'] ?? 0),
            'mn' => $mn,
            'mayores70' => (int) ($p['mayores70'] ?? 0),
        ];
    }

    private function esInterno(User $usuario): bool
    {
        return in_array((string) ($usuario->usuario_interno ?? 'N'), ['Y', '1'], true) || (string) $usuario->fk_tipousuario_id === 'POW';
    }
}
