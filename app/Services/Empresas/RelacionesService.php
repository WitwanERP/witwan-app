<?php

namespace App\Services\Empresas;

use Illuminate\Support\Facades\DB;

/**
 * Lo que el form de cliente/pasajero del CI guardaba además de la fila principal
 * y de contactos/tarjetas: tags (`rel_clientetag` / `rel_pasajerotag`),
 * tarifarios por sistema (`rel_clientesistema`, rearmada desde fk_tarifario1..3),
 * los extras JSON del pasajero (documentos, domicilios, visas, teléfonos,
 * emails, frecuentes) y las relaciones recíprocas cliente ↔ pasajero y
 * cliente ↔ cliente en `*_extra`.
 *
 * Cada bloque se sincroniza sólo si su clave viene en $data, para que los
 * clientes de la API que no mandan esas claves no borren nada.
 */
class RelacionesService
{
    public const EXTRAS_PASAJERO = [
        'documentos' => ['pasajero_doc_tipo', 'pasajero_doc_nro', 'pasajero_doc_paisemisor', 'pasajero_doc_emisorfecha', 'pasajero_doc_vencimiento'],
        'domicilios' => ['pasajero_dom_tipo', 'pasajero_direccion', 'pasajero_codigopostal', 'pasajero_dom_pais', 'pasajero_provincia', 'pasajero_ciudad'],
        'visas' => ['visa_nro', 'visa_pais', 'visa_fecha', 'visa_vence'],
        'telefonos' => ['pasajero_tel_tipo', 'pasajero_tel_codpais', 'pasajero_tel_codarea', 'pasajero_telefono'],
        'emails' => ['pasajero_correo_tipo', 'pasajero_email', 'pasajero_correo_noenviar'],
        'frecuentes' => ['paxfrec_nombre', 'paxfrec_num', 'paxfrec_pin'],
    ];

    /** Claves de $data que consume este servicio (para sacarlas antes del INSERT/UPDATE de la fila). */
    public const CLAVES_CLIENTE = ['tags', 'pax_relacionados', 'cliente_relacionados'];

    public const CLAVES_PASAJERO = ['tags', 'pax_relacionados', 'cliente_relacionados', 'documentos', 'domicilios', 'visas', 'telefonos', 'emails', 'frecuentes'];

    public function __construct(private ExtrasService $extras) {}

    /** @return array{tags:int[],pax_relacionados:array,cliente_relacionados:array} */
    public function leerCliente(int $id): array
    {
        $x = $this->extras->leer('cliente_extra', 'fk_cliente_id', $id);

        return [
            'tags' => DB::table('rel_clientetag')->where('fk_cliente_id', $id)->pluck('fk_tag_id')->map(fn ($t) => (int) $t)->all(),
            'pax_relacionados' => $x['pax_relacionados'] ?? [],
            'cliente_relacionados' => $x['cliente_relacionados'] ?? [],
        ];
    }

    public function sincronizarCliente(int $id, array $data): void
    {
        if (array_key_exists('tags', $data)) {
            $this->tags('rel_clientetag', 'fk_cliente_id', $id, $data['tags']);
        }
        if (array_key_exists('fk_tarifario1_id', $data) || array_key_exists('fk_tarifario2_id', $data) || array_key_exists('fk_tarifario3_id', $data)) {
            $actual = (array) DB::table('cliente')->where('cliente_id', $id)->first(['fk_tarifario1_id', 'fk_tarifario2_id', 'fk_tarifario3_id']);
            DB::table('rel_clientesistema')->where('fk_cliente_id', $id)->delete();
            foreach ([1 => 'fk_tarifario1_id', 2 => 'fk_tarifario2_id', 3 => 'fk_tarifario3_id'] as $sistema => $campo) {
                $t = (int) ($actual[$campo] ?? 0);
                if ($t !== 0) {
                    DB::table('rel_clientesistema')->insert(['fk_cliente_id' => $id, 'fk_sistema_id' => $sistema, 'fk_tarifario_id' => $t]);
                }
            }
        }
        if (array_key_exists('pax_relacionados', $data)) {
            $pax = array_values(array_filter(ExtrasService::filas($data['pax_relacionados'], ['paxrel_id', 'paxrel_vinculo']), fn ($p) => (int) $p['paxrel_id'] > 0));
            $this->extras->quitarReferencias('pasajero_extra', 'fk_pasajero_id', 'cliente_relacionados', 'clienterel_id', $id);
            $this->reemplazarExtra('cliente_extra', 'fk_cliente_id', $id, 'pax_relacionados', $pax);
            foreach ($pax as $p) {
                $this->extras->agregarReferencia('pasajero_extra', 'fk_pasajero_id', 'cliente_relacionados', (int) $p['paxrel_id'], ['clienterel_id' => (string) $id, 'clienterel_vinculo' => ''], 'clienterel_id');
            }
        }
        if (array_key_exists('cliente_relacionados', $data)) {
            $cli = array_values(array_filter(ExtrasService::filas($data['cliente_relacionados'], ['clienterel_id', 'clienterel_vinculo']), fn ($c) => (int) $c['clienterel_id'] > 0 && (int) $c['clienterel_id'] !== $id));
            $this->extras->quitarReferencias('cliente_extra', 'fk_cliente_id', 'cliente_relacionados', 'clienterel_id', $id);
            $this->reemplazarExtra('cliente_extra', 'fk_cliente_id', $id, 'cliente_relacionados', $cli);
            foreach ($cli as $c) {
                $this->extras->agregarReferencia('cliente_extra', 'fk_cliente_id', 'cliente_relacionados', (int) $c['clienterel_id'], ['clienterel_id' => (string) $id, 'clienterel_vinculo' => ''], 'clienterel_id');
            }
        }
    }

    /** @return array<string,mixed> tags + extras JSON + relaciones */
    public function leerPasajero(int $id): array
    {
        $x = $this->extras->leer('pasajero_extra', 'fk_pasajero_id', $id);
        $out = ['tags' => DB::table('rel_pasajerotag')->where('fk_pasajero_id', $id)->pluck('fk_tag_id')->map(fn ($t) => (int) $t)->all()];
        foreach (array_merge(array_keys(self::EXTRAS_PASAJERO), ['pax_relacionados', 'cliente_relacionados']) as $k) {
            $out[$k] = is_array($x[$k] ?? null) ? array_values($x[$k]) : [];
        }

        return $out;
    }

    public function sincronizarPasajero(int $id, array $data): void
    {
        if (array_key_exists('tags', $data)) {
            $this->tags('rel_pasajerotag', 'fk_pasajero_id', $id, $data['tags']);
        }
        foreach (self::EXTRAS_PASAJERO as $nombre => $claves) {
            if (array_key_exists($nombre, $data)) {
                $this->reemplazarExtra('pasajero_extra', 'fk_pasajero_id', $id, $nombre, ExtrasService::filas($data[$nombre], $claves));
            }
        }
        if (array_key_exists('pax_relacionados', $data)) {
            $pax = array_values(array_filter(ExtrasService::filas($data['pax_relacionados'], ['paxrel_id', 'paxrel_vinculo']), fn ($p) => (int) $p['paxrel_id'] > 0 && (int) $p['paxrel_id'] !== $id));
            $this->extras->quitarReferencias('pasajero_extra', 'fk_pasajero_id', 'pax_relacionados', 'paxrel_id', $id);
            $this->reemplazarExtra('pasajero_extra', 'fk_pasajero_id', $id, 'pax_relacionados', $pax);
            foreach ($pax as $p) {
                $this->extras->agregarReferencia('pasajero_extra', 'fk_pasajero_id', 'pax_relacionados', (int) $p['paxrel_id'], ['paxrel_id' => (string) $id, 'paxrel_vinculo' => ''], 'paxrel_id');
            }
        }
        if (array_key_exists('cliente_relacionados', $data)) {
            $cli = array_values(array_filter(ExtrasService::filas($data['cliente_relacionados'], ['clienterel_id', 'clienterel_vinculo']), fn ($c) => (int) $c['clienterel_id'] > 0));
            $this->extras->quitarReferencias('cliente_extra', 'fk_cliente_id', 'pax_relacionados', 'paxrel_id', $id);
            $this->reemplazarExtra('pasajero_extra', 'fk_pasajero_id', $id, 'cliente_relacionados', $cli);
            foreach ($cli as $c) {
                $this->extras->agregarReferencia('cliente_extra', 'fk_cliente_id', 'pax_relacionados', (int) $c['clienterel_id'], ['paxrel_id' => (string) $id, 'paxrel_vinculo' => ''], 'paxrel_id');
            }
        }
    }

    private function tags(string $tabla, string $fk, int $id, mixed $tags): void
    {
        DB::table($tabla)->where($fk, $id)->delete();
        foreach (array_unique(array_map('intval', is_array($tags) ? $tags : [])) as $tag) {
            if ($tag > 0) {
                DB::table($tabla)->insert([$fk => $id, 'fk_tag_id' => $tag]);
            }
        }
    }

    private function reemplazarExtra(string $tabla, string $fk, int $id, string $nombre, array $valor): void
    {
        DB::table($tabla)->where($fk, $id)->where('extra_nombre', $nombre)->delete();
        if ($valor !== []) {
            DB::table($tabla)->insert([$fk => $id, 'extra_nombre' => $nombre, 'extra_valor' => json_encode(array_values($valor), JSON_UNESCAPED_UNICODE)]);
        }
    }
}
