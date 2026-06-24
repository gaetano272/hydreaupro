<?php

declare(strict_types=1);

/**
 * Démarre une session administrateur avec des cookies renforcés.
 */
function startAdminSession(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    $isHttps = (
        (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (int) ($_SERVER['SERVER_PORT'] ?? 0) === 443
    );

    session_name('HYDREAUPRO_ADMIN');

    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'domain' => '',
        'secure' => $isHttps,
        'httponly' => true,
        'samesite' => 'Strict',
    ]);

    session_start();

    $lastRegeneration = (int) ($_SESSION['last_regeneration'] ?? 0);

    if ($lastRegeneration === 0 || time() - $lastRegeneration > 1800) {
        session_regenerate_id(true);
        $_SESSION['last_regeneration'] = time();
    }
}

startAdminSession();

$authConfigFile = __DIR__ . '/config-auth.php';

if (!is_file($authConfigFile)) {
    http_response_code(500);
    exit('Configuration administrateur introuvable.');
}

$adminConfig = require $authConfigFile;

if (
    !is_array($adminConfig)
    || empty($adminConfig['username'])
    || empty($adminConfig['password_hash'])
) {
    http_response_code(500);
    exit('Configuration administrateur invalide.');
}

function isAdminAuthenticated(): bool
{
    return isset($_SESSION['admin_authenticated'])
        && $_SESSION['admin_authenticated'] === true
        && isset($_SESSION['admin_username'])
        && is_string($_SESSION['admin_username']);
}

function requireAdmin(): void
{
    if (isAdminAuthenticated()) {
        return;
    }

    $requestedPage = basename((string) ($_SERVER['REQUEST_URI'] ?? 'index.php'));
    $_SESSION['admin_redirect_after_login'] = $requestedPage;

    header('Location: connexion.php');
    exit;
}

function getAdminCsrfToken(): string
{
    if (
        empty($_SESSION['csrf_token'])
        || !is_string($_SESSION['csrf_token'])
    ) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

function verifyAdminCsrfToken(?string $submittedToken): bool
{
    $sessionToken = $_SESSION['csrf_token'] ?? null;

    return is_string($submittedToken)
        && is_string($sessionToken)
        && $submittedToken !== ''
        && $sessionToken !== ''
        && hash_equals($sessionToken, $submittedToken);
}
