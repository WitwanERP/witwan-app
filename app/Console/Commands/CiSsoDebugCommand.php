<?php

namespace App\Console\Commands;

use App\Services\CiSessionReader;
use App\Services\CiUserResolver;
use App\Services\TenantManager;
use Illuminate\Console\Command;
use Illuminate\Http\Request;

/**
 * Diagnóstico del pseudo-SSO sin loop de deploy+login: se le pega el valor crudo
 * de la cookie de sesión de CI (Application > Cookies en el navegador) y reproduce
 * el pipeline completo, imprimiendo en qué etapa corta.
 *
 *   php artisan ci:sso-debug '<valor-cookie>' --host=rays.witwan.com
 *
 * Usar comillas simples: la cookie trae caracteres que el shell interpretaría.
 */
class CiSsoDebugCommand extends Command
{
    protected $signature = 'ci:sso-debug
        {cookie : Valor crudo de la cookie de sesión de CI (copiado del navegador)}
        {--host= : Dominio del tenant, para apuntar ci_sessions a la BD correcta}';

    protected $description = 'Diagnostica el pseudo-SSO: decodifica la cookie de CI y reproduce la resolución del usuario';

    public function handle(CiSessionReader $reader, CiUserResolver $resolver): int
    {
        $this->resolveTenant($this->option('host'));

        $cookieName = (string) config('ci.cookie_name', 'ci_session');

        $this->newLine();
        $this->line(sprintf(
            'config: cookie_name=%s hash=%s encrypt=%s key=%s expiration=%s',
            $cookieName,
            config('ci.cookie_hash', 'md5'),
            var_export((bool) config('ci.encrypt_cookie'), true),
            config('ci.encryption_key') ? 'set' : 'VACÍA',
            config('ci.sess_expiration', 7200),
        ));

        $cookie = $this->argument('cookie');
        $this->detectHashScheme($reader, $cookie, (string) config('ci.encryption_key'));

        // Request sintético con la cookie: reutiliza el reader tal cual corre en prod.
        $request = Request::create('/app', 'GET', [], [$cookieName => $cookie]);

        $sid = $reader->sessionIdFromCookie($request);
        if ($sid === null) {
            $this->error('✗ No se obtuvo session_id de la cookie. Revisá: nombre de cookie, encryption_key, hash (md5/sha1) y sess_encrypt_cookie.');

            return self::FAILURE;
        }
        $this->info("✓ session_id decodificado: {$sid}");

        $userData = $reader->userData($request);
        if ($userData === null) {
            $this->error('✗ Sin user_data: fila ci_sessions inexistente, sesión vencida o user_data ilegible. ¿--host correcto?');

            return self::FAILURE;
        }
        $this->info('✓ user_data leído. Claves: '.implode(', ', array_keys($userData)));

        $user = $resolver->fromCiData($userData);
        if ($user === null) {
            $this->error(sprintf(
                '✗ Ningún User matcheó. id_keys=%s mail_keys=%s. Ajustá config/ci.php a las claves de arriba.',
                json_encode(config('ci.id_keys')),
                json_encode(config('ci.mail_keys')),
            ));

            return self::FAILURE;
        }

        $this->info("✓ Usuario resuelto: #{$user->usuario_id} {$user->usuario_nombre} <{$user->usuario_mail}>");

        return self::SUCCESS;
    }

    /**
     * Prueba las 4 combinaciones (md5/sha1 × plano/hmac) contra el hash al final
     * de la cookie y reporta cuál corresponde, para configurar CI_COOKIE_HASH /
     * CI_COOKIE_HMAC sin adivinar. Necesita la encryption_key cargada.
     */
    private function detectHashScheme(CiSessionReader $reader, string $cookie, string $key): void
    {
        if ($key === '') {
            $this->warn('Sin CI_ENCRYPTION_KEY no se puede autodetectar el esquema de hash de la cookie.');

            return;
        }

        $scheme = $reader->detectHashScheme($cookie, $key);

        if ($scheme === null) {
            $this->error('✗ Ningún esquema (md5/sha1 × plano/hmac) coincide con el hash de la cookie. ¿encryption_key correcta? ¿cookie cifrada?');

            return;
        }

        $modo = $scheme['cookie_hmac'] ? 'HMAC' : 'PLANO';
        $hmac = $scheme['cookie_hmac'] ? 'true' : 'false';
        $this->info("✓ esquema de hash detectado: {$scheme['cookie_hash']} {$modo}  →  CI_COOKIE_HASH={$scheme['cookie_hash']}  CI_COOKIE_HMAC={$hmac}");
    }

    /** Apunta la conexión por defecto a la BD del tenant (donde vive ci_sessions). */
    private function resolveTenant(?string $host): void
    {
        if (! $host) {
            $this->warn('Sin --host: ci_sessions se lee de la BD por defecto del .env (puede no ser la del tenant).');

            return;
        }

        $licencia = TenantManager::resolveFromHost($host);
        if (! $licencia) {
            $this->error("No se resolvió tenant para '{$host}'; ci_sessions usará la BD por defecto del .env.");

            return;
        }

        TenantManager::configure($licencia);
        $this->info("Tenant: licencia={$licencia->licencia_id} base={$licencia->licencia_base}");
    }
}
