<?php
require_once __DIR__ . '/config.php';
start_secure_session();

$_SESSION = [];

if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    $cookiePaths = array_unique([
        $params['path'] ?: '/',
        APP_BASE !== '' ? APP_BASE : '/',
        '/',
    ]);

    foreach ($cookiePaths as $cookiePath) {
        setcookie(
            session_name(),
            '',
            time() - 42000,
            $cookiePath,
            $params['domain'] ?? '',
            (bool) $params['secure'],
            (bool) $params['httponly']
        );
    }
}

session_destroy();
redirect('login');
