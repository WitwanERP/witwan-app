<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Pseudo-SSO con el CodeIgniter legacy
    |--------------------------------------------------------------------------
    |
    | El front Inertia (/app) comparte sesión con el CodeIgniter legacy: vive en
    | el mismo host, así que la cookie de sesión de CI llega sola a Laravel. El
    | middleware AuthenticateFromCiSession la lee, la mapea contra la tabla
    | `ci_sessions` (BD del tenant) y deja autenticado al usuario correspondiente.
    |
    | Estos valores deben coincidir con application/config/config.php del CI.
    |
    */

    // Nombre de la cookie de sesión de CI (incluye cookie_prefix si CI lo usa).
    'cookie_name' => env('CI_SESSION_COOKIE', 'ci_session'),

    // encryption_key del config.php de CI: se usa para verificar el HMAC de la cookie.
    'encryption_key' => env('CI_ENCRYPTION_KEY'),

    // sess_encrypt_cookie de CI. Si es TRUE, la cookie está cifrada con el Encrypt
    // de CI (MCrypt/OpenSSL) y este reader no la decodifica: usar un handoff del CI.
    'encrypt_cookie' => env('CI_SESS_ENCRYPT_COOKIE', false),

    // Algoritmo del hash de integridad que CI agrega al final de la cookie.
    // CI2 por defecto usa md5 (32 chars hex); algunas builds usan sha1 (40 chars).
    'cookie_hash' => env('CI_COOKIE_HASH', 'md5'),

    // sess_expiration de CI, en segundos (0 = no expira por tiempo). Default CI: 7200.
    'sess_expiration' => (int) env('CI_SESS_EXPIRATION', 7200),

    // Validaciones opcionales equivalentes a las de CI (sess_match_ip/useragent).
    'match_ip' => env('CI_SESS_MATCH_IP', false),
    'match_useragent' => env('CI_SESS_MATCH_USERAGENT', false),

    // URL del login del CI legacy: a dónde mandar si no hay sesión válida.
    'login_url' => env('CI_LOGIN_URL'),

    // Si no hay sesión de CI válida, ¿redirigir al login de CI?
    'redirect_guests' => env('CI_REDIRECT_GUESTS', true),

    // Paths (sin slash inicial) que NO requieren sesión de CI (no redirigen).
    'guest_paths' => [
        'app/_probe',
    ],

    // Claves candidatas dentro de user_data para identificar al usuario logueado.
    // Se prueban en orden; la primera que matchee gana. Ajustar a lo que el CI
    // guarda con set_userdata (ver prerrequisitos del plan).
    'id_keys' => ['usuario_id', 'user_id', 'id'],
    'mail_keys' => ['usuario_mail', 'email', 'mail'],

];
