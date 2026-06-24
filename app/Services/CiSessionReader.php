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

    /**
     * Reporte de cada etapa del pseudo-SSO para diagnóstico (lo usa /app/_probe).
     * Devuelve contexto seguro (sin valores sensibles) y una pista de en qué etapa
     * queda la resolución del usuario.
     */
    public function diagnose(Request $request, CiUserResolver $resolver): array
    {
        $cookieName = (string) config('ci.cookie_name', 'ci_session');
        $cookiePresente = is_string($request->cookie($cookieName)) && $request->cookie($cookieName) !== '';
        $keySeteada = (string) config('ci.encryption_key') !== '';

        $sessionId = $this->sessionIdFromCookie($request);
        $userData = $this->userData($request);
        $user = is_array($userData) ? $resolver->fromCiData($userData) : null;

        if (! $cookiePresente) {
            $etapa = "la cookie '{$cookieName}' no llega a Laravel (¿nombre correcto? ¿la descartó EncryptCookies?)";
        } elseif (config('ci.encrypt_cookie')) {
            $etapa = 'CI_SESS_ENCRYPT_COOKIE=true: el reader no decodifica cookies cifradas';
        } elseif (! $keySeteada) {
            $etapa = 'falta CI_ENCRYPTION_KEY';
        } elseif ($sessionId === null) {
            $etapa = 'la cookie no verifica (revisar CI_ENCRYPTION_KEY, CI_COOKIE_HASH o CI_COOKIE_HMAC)';
        } elseif ($userData === null) {
            $etapa = 'session_id OK pero no hay fila vigente en ci_sessions (¿BD del tenant? ¿sesión vencida?)';
        } elseif ($user === null) {
            $etapa = 'user_data OK pero ningún usuario matcheó (ajustar id_keys/mail_keys en config/ci.php)';
        } else {
            $etapa = 'OK: usuario resuelto';
        }

        return [
            'diagnostico' => $etapa,
            'cookie_name' => $cookieName,
            'cookie_presente' => $cookiePresente,
            'encryption_key_seteada' => $keySeteada,
            'encrypt_cookie' => (bool) config('ci.encrypt_cookie'),
            'cookie_hash' => (string) config('ci.cookie_hash', 'md5'),
            'cookie_hmac' => (bool) config('ci.cookie_hmac'),
            'session_id' => $sessionId,
            'user_data_keys' => is_array($userData) ? array_keys($userData) : null,
            'usuario' => $user ? [
                'usuario_id' => $user->usuario_id,
                'usuario_nombre' => $user->usuario_nombre,
                'usuario_mail' => $user->usuario_mail,
            ] : null,
        ];
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
