<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Escribe registros en la tabla de auditoría del tenant (`auditoria`), replicando
 * el formato que usa CodeIgniter (acciones tipo ANULAR sobre recibo, etc.).
 *
 * Todas las columnas de `auditoria` son NOT NULL sin default:
 *   tabla_relacionada, id_relacionado, accion, usuario_id,
 *   valor_antiguo (JSON), valor_nuevo (JSON), usuario_ip, regdate.
 *
 * La auditoría es "best effort": si falla, se loguea pero NO se propaga el error
 * (no debe abortar la operación de negocio que la disparó), igual criterio que
 * el logCreditLimit de la API.
 */
class AuditoriaService
{
    /**
     * Registra una acción auditable.
     *
     * @param  array<string,mixed>|string  $valorAntiguo  estado previo (se serializa a JSON si es array)
     * @param  array<string,mixed>|string  $valorNuevo    estado nuevo / cambios
     */
    public function registrar(
        string $tabla,
        int $idRelacionado,
        string $accion,
        array|string $valorAntiguo,
        array|string $valorNuevo,
        ?int $usuarioId = null,
        ?string $ip = null,
    ): void {
        try {
            DB::table('auditoria')->insert([
                'tabla_relacionada' => $tabla,
                'id_relacionado' => $idRelacionado,
                'accion' => $accion,
                'usuario_id' => $usuarioId ?? (int) (auth()->id() ?? 0),
                'valor_antiguo' => $this->aJson($valorAntiguo),
                'valor_nuevo' => $this->aJson($valorNuevo),
                'usuario_ip' => (string) ($ip ?? request()->ip()),
                'regdate' => now(),
            ]);
        } catch (\Throwable $e) {
            Log::error("Auditoria: no se pudo registrar '{$accion}' sobre {$tabla}#{$idRelacionado}: {$e->getMessage()}");
        }
    }

    /** @param  array<string,mixed>|string  $valor */
    private function aJson(array|string $valor): string
    {
        return is_array($valor)
            ? (string) json_encode($valor, JSON_UNESCAPED_UNICODE)
            : $valor;
    }
}
