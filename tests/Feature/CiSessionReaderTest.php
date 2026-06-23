<?php

namespace Tests\Feature;

use App\Services\CiSessionReader;
use Illuminate\Http\Request;
use Tests\TestCase;

/**
 * Cubre la parte cripto-sensible del pseudo-SSO: decodificar y verificar la
 * cookie de sesión de CodeIgniter 2 (serialize(datos) . md5(datos . key)).
 * No toca la BD (eso lo valida un feature test con la tabla ci_sessions).
 */
class CiSessionReaderTest extends TestCase
{
    private const KEY = 'la-encryption-key-del-ci';

    private const SID = 'a1b2c3d4e5f6a1b2c3d4e5f6a1b2c3d4';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'ci.cookie_name' => 'ci_session',
            'ci.encryption_key' => self::KEY,
            'ci.encrypt_cookie' => false,
            'ci.cookie_hash' => 'md5',
        ]);
    }

    /** Arma una cookie CI2 sin cifrar con la key/hash dados. */
    private function ciCookie(array $base, string $key = self::KEY, string $algo = 'md5'): string
    {
        $payload = serialize($base);

        return $payload.hash($algo, $payload.$key);
    }

    private function requestWithCookie(?string $value): Request
    {
        $cookies = $value === null ? [] : ['ci_session' => $value];

        return Request::create('/app', 'GET', [], $cookies);
    }

    public function test_devuelve_session_id_con_cookie_valida(): void
    {
        $cookie = $this->ciCookie([
            'session_id' => self::SID,
            'ip_address' => '1.2.3.4',
            'user_agent' => 'phpunit',
            'last_activity' => time(),
        ]);

        $sid = (new CiSessionReader)->sessionIdFromCookie($this->requestWithCookie($cookie));

        $this->assertSame(self::SID, $sid);
    }

    public function test_rechaza_cookie_manipulada(): void
    {
        $cookie = $this->ciCookie([
            'session_id' => self::SID,
            'last_activity' => time(),
        ]);
        // Alterar un byte del payload invalida el hash de integridad.
        $tampered = 'x'.substr($cookie, 1);

        $this->assertNull((new CiSessionReader)->sessionIdFromCookie($this->requestWithCookie($tampered)));
    }

    public function test_rechaza_cookie_firmada_con_otra_key(): void
    {
        $cookie = $this->ciCookie(['session_id' => self::SID], 'otra-key-distinta');

        $this->assertNull((new CiSessionReader)->sessionIdFromCookie($this->requestWithCookie($cookie)));
    }

    public function test_null_sin_cookie(): void
    {
        $this->assertNull((new CiSessionReader)->sessionIdFromCookie($this->requestWithCookie(null)));
    }

    public function test_null_si_falta_encryption_key(): void
    {
        config(['ci.encryption_key' => null]);
        $cookie = $this->ciCookie(['session_id' => self::SID]);

        $this->assertNull((new CiSessionReader)->sessionIdFromCookie($this->requestWithCookie($cookie)));
    }

    public function test_null_si_la_cookie_esta_cifrada(): void
    {
        config(['ci.encrypt_cookie' => true]);
        $cookie = $this->ciCookie(['session_id' => self::SID]);

        $this->assertNull((new CiSessionReader)->sessionIdFromCookie($this->requestWithCookie($cookie)));
    }
}
