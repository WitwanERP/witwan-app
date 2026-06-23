<?php

namespace App\Services;

use App\Models\CiSession;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Lee la sesión del usuario logueado en el CodeIgniter legacy a partir de la
 * cookie de sesión de CI presente en la request (mismo host que /app).
 *
 * Asume sesiones CI2 con base de datos (sess_use_database = TRUE) y cookie sin
 * cifrar (sess_encrypt_cookie = FALSE): la cookie es `serialize(datos) . hash`,
 * donde `datos` son los campos base {session_id, ip_address, user_agent,
 * last_activity} y los datos de usuario (set_userdata) viven en la columna
 * `user_data` de la tabla `ci_sessions`. El hash al final protege contra
 * manipulación y se verifica con la encryption_key de CI.
 *
 * Cualquier inconsistencia (cookie ausente/manipulada, sesión vencida, fila
 * inexistente) devuelve null: el llamador trata eso como "no autenticado".
 */
class CiSessionReader
{
    /**
     * Devuelve el array deserializado de `user_data` (lo que CI guardó con
     * set_userdata) del usuario logueado, o null si no hay sesión válida.
     */
    public function userData(Request $request): ?array
    {
        $sessionId = $this->sessionIdFromCookie($request);
        if ($sessionId === null) {
            return null;
        }

        /** @var CiSession|null $row */
        $row = CiSession::query()->find($sessionId);
        if ($row === null || ! $this->isFresh($row) || ! $this->matchesClient($request, $row)) {
            return null;
        }

        $data = @unserialize((string) $row->user_data, ['allowed_classes' => false]);

        return is_array($data) ? $data : null;
    }

    /**
     * Decodifica y verifica la cookie de CI2 y devuelve el session_id, o null.
     */
    public function sessionIdFromCookie(Request $request): ?string
    {
        $cookie = $request->cookie(config('ci.cookie_name', 'ci_session'));
        if (! is_string($cookie) || $cookie === '') {
            return null;
        }

        // Cookie cifrada por CI: no soportado por este reader (usar handoff del CI).
        if (config('ci.encrypt_cookie')) {
            Log::warning('CiSessionReader: sess_encrypt_cookie=TRUE no soportado; configurar handoff del lado CI.');

            return null;
        }

        $key = (string) config('ci.encryption_key');
        if ($key === '') {
            Log::warning('CiSessionReader: ci.encryption_key vacío; no se puede verificar la cookie de CI.');

            return null;
        }

        $algo = (string) config('ci.cookie_hash', 'md5');
        $hashLen = strlen(hash($algo, '')); // 32 para md5, 40 para sha1
        if (strlen($cookie) <= $hashLen) {
            return null;
        }

        $payload = substr($cookie, 0, -$hashLen);
        $hash = substr($cookie, -$hashLen);

        if (! hash_equals(hash($algo, $payload.$key), $hash)) {
            return null; // cookie manipulada o encryption_key incorrecta
        }

        $data = @unserialize($payload, ['allowed_classes' => false]);
        if (! is_array($data) || empty($data['session_id'])) {
            return null;
        }

        return (string) $data['session_id'];
    }

    /** La sesión sigue vigente según sess_expiration de CI. */
    private function isFresh(CiSession $row): bool
    {
        $expiration = (int) config('ci.sess_expiration', 7200);
        if ($expiration <= 0) {
            return true;
        }

        return ((int) $row->last_activity + $expiration) > time();
    }

    /** Validaciones opcionales de IP / user-agent (espejo de CI). */
    private function matchesClient(Request $request, CiSession $row): bool
    {
        if (config('ci.match_ip') && (string) $row->ip_address !== (string) $request->ip()) {
            return false;
        }

        if (config('ci.match_useragent')
            && trim((string) $row->user_agent) !== substr((string) $request->userAgent(), 0, 120)) {
            return false;
        }

        return true;
    }
}
