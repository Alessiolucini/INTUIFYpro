<?php
/**
 * IntuiFy Admin — Authentication Middleware
 * Include this at the top of every admin page (except login).
 */

declare(strict_types=1);

session_start();

/**
 * Check if user is authenticated. If not, redirect to login.
 */
function requireAuth(): void
{
    if (!isset($_SESSION['admin_authenticated']) || $_SESSION['admin_authenticated'] !== true) {
        header('Location: /admin/index.php');
        exit;
    }
    
    // Session timeout: 8 hours
    if (isset($_SESSION['admin_login_time']) && (time() - $_SESSION['admin_login_time']) > 28800) {
        session_destroy();
        header('Location: /admin/index.php?expired=1');
        exit;
    }
}

/**
 * Attempt login with username and password.
 */
function attemptLogin(string $username, string $password, array $config): bool
{
    if ($username === $config['admin_username']) {
        $hashValid = password_verify($password, $config['admin_password_hash']);
        $plainValid = !$hashValid && isset($config['admin_password_plain']) && $password === $config['admin_password_plain'];
        
        if ($hashValid || $plainValid) {
            $_SESSION['admin_authenticated'] = true;
            $_SESSION['admin_username'] = $username;
            $_SESSION['admin_login_time'] = time();
            return true;
        }
    }
    return false;
}

/**
 * Get current admin username.
 */
function getAdminUser(): string
{
    return $_SESSION['admin_username'] ?? 'Admin';
}
