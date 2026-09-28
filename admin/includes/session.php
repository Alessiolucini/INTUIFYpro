<?php
/**
 * IntuiFy Admin — Session bootstrap
 * Starts the PHP session with hardened cookie flags (HttpOnly, SameSite, Secure behind Traefik).
 */

declare(strict_types=1);

/**
 * True when the browser reached us over HTTPS (directly or via the Traefik proxy).
 */
function isHttpsRequest(): bool
{
    if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
        return true;
    }
    return strtolower($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';
}

/**
 * Start the admin session once, with secure cookie parameters.
 */
function startAdminSession(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    ini_set('session.use_strict_mode', '1');
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'secure'   => isHttpsRequest(),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}
