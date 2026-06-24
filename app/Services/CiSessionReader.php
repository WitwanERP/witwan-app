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
 * Con CI_SSO_DEBUG=true se loguea en cada etapa el motivo del descarte (solo
 * contexto seguro, nunca valores sensibles) para diagnosticar.
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
        if ($row === null) {
            $this->debug('fila ci_sessions no encontrada para el session_id (¿BD del tenant correcta?)', [
                'session_id' => $sessionId,
            ]);

            return null;
        }

        if (! $this->isFresh($row)) {
            $this->debug('sesión de CI vencida', [
                'edad_seg' => time() - (int) $row->last_activity,
                'expiration' => (int) config('ci.sess_expiration', 7200),
            ]);

            return null;
        }

        if (! $this->matchesClient($request, $row)) {
            $this->debug('no matchea ip/user_agent de la sesión de CI (¿proxy?)', [
                'row_ip' => (string) $row->ip_address,
                'req_ip' => (string) $request->ip(),
            ]);

            return null;
        }

        $data = @unserialize((string) $row->user_data, ['allowed_classes' => false]);
        if (! is_array($data)) {
            $this->debug('user_data no deserializa a array', [
                'preview' => substr((string) $row->user_data, 0, 60),
            ]);

            return null;
        }

        $this->debug('user_data OK', ['claves' => array_keys($data)]);

        return $data;
    }

    /**
     * Decodifica y verifica la cookie de CI2 y devuelve el session_id, o null.
     */
    public function sessionIdFromCookie(Request $request): ?string
    {
        $name = (string) config('ci.cookie_name', 'ci_session');
        $cookie = $request->cookie($name);
        if (! is_string($cookie) || $cookie === '') {
            $this->debug('cookie de CI ausente en la request', [
                'cookie_name' => $name,
                'cookies_presentes' => array_keys($request->cookies->all()),
            ]);

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
            $this->debug('cookie más corta que el hash esperado', [
                'largo_cookie' => strlen($cookie),
                'hash_len' => $hashLen,
            ]);

            return null;
        }

        $payload = substr($cookie, 0, -$hashLen);
        $hash = substr($cookie, -$hashLen);

        $expected = config('ci.cookie_hmac')
            ? hash_hmac($algo, $payload, $key)
            : hash($algo, $payload.$key);

        if (! hash_equals($expected, $hash)) {
            $this->debug('hash de la cookie no verifica (¿encryption_key, algo o sess_encrypt_cookie?)', [
                'algo' => $algo,
                'hash_len' => $hashLen,
            ]);

            return null; // cookie manipulada o encryption_key incorrecta
        }

        $data = @unserialize($payload, ['allowed_classes' => false]);
        if (! is_array($data) || empty($data['session_id'])) {
            $this->debug('payload de la cookie sin session_id', [
                'es_array' => is_array($data),
            ]);

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

    /** Log de diagnóstico, solo si CI_SSO_DEBUG está activo. */
    private function debug(string $message, array $context = []): void
    {
        if (config('ci.debug')) {
            Log::debug('[ci-sso] '.$message, $context);
        }
    }
}
