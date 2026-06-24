<?php

declare(strict_types=1);

require_once __DIR__ . '/auth.php';

if (
    $_SERVER['REQUEST_METHOD'] !== 'POST'
    || !verifyAdminCsrfToken(
        isset($_POST['csrf_token']) && is_string($_POST['csrf_token'])
            ? $_POST['csrf_token']
            : null
    )
) {
    header('Location: index.php');
    exit;
}

$_SESSION = [];

if (ini_get('session.use_cookies')) {
    $cookieParameters = session_get_cookie_params();

    setcookie(
        session_name(),
        '',
        [
            'expires' => time() - 42000,
            'path' => $cookieParameters['path'],
            'domain' => $cookieParameters['domain'],
            'secure' => $cookieParameters['secure'],
            'httponly' => $cookieParameters['httponly'],
            'samesite' => 'Strict',
        ]
    );
}

session_destroy();

header('Location: connexion.php?logout=1');
exit;
