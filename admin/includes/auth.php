<?php
/**
 * IntuiFy Admin — Authentication Middleware
 * Include this at the top of every admin page (except login).
 *
 * requireAuth() also enforces CSRF on every POST and on state-changing GET
 * actions, and writes those actions to the audit log.
 */

declare(strict_types=1);

require_once __DIR__ . '/session.php';
require_once __DIR__ . '/csrf.php';
require_once __DIR__ . '/audit.php';

startAdminSession();

const ADMIN_SESSION_TTL  = 28800; // 8 hours
const LOGIN_MAX_FAILURES = 5;     // per IP…
const LOGIN_LOCK_WINDOW  = 900;   // …in 15 minutes

/** GET actions that change data: they must carry a CSRF token and are audited. */
const MUTATING_GET_ACTIONS = ['delete', 'status', 'convert', 'ai-reply', 'toggle-renew'];

function isAdminAuthenticated(): bool
{
    return isset($_SESSION['admin_authenticated']) && $_SESSION['admin_authenticated'] === true;
}

function adminSessionExpired(): bool
{
    return isset($_SESSION['admin_login_time']) && (time() - $_SESSION['admin_login_time']) > ADMIN_SESSION_TTL;
}

/**
 * Check if user is authenticated. If not, redirect to login.
 */
function requireAuth(): void
{
    if (!isAdminAuthenticated()) {
        header('Location: /admin/index.php');
        exit;
    }

    if (adminSessionExpired()) {
        session_destroy();
        header('Location: /admin/index.php?expired=1');
        exit;
    }

    enforceCsrfAndAudit();
}

/**
 * Same as requireAuth() for JSON endpoints: answers 401 instead of redirecting.
 */
function requireAuthJson(): void
{
    if (!isAdminAuthenticated() || adminSessionExpired()) {
        http_response_code(401);
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode(['error' => 'Sessione scaduta. Effettua di nuovo il login.']);
        exit;
    }

    enforceCsrfAndAudit();
}

function enforceCsrfAndAudit(): void
{
    $action = $_GET['action'] ?? '';

    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
        requireCsrf();
        return;
    }

    if (in_array($action, MUTATING_GET_ACTIONS, true)) {
        requireCsrf();
        $details = $_GET;
        unset($details['_csrf'], $details['action'], $details['id']);
        auditLog($action, basename($_SERVER['PHP_SELF'] ?? '', '.php'), (string) ($_GET['id'] ?? ''), $details);
    }
}

// =============================================================================
// Login throttling (file-based, per client IP)
// =============================================================================

function loginThrottleFile(): string
{
    return sys_get_temp_dir() . '/intuify_login_' . md5(clientIp()) . '.json';
}

/** @return int[] timestamps of recent failed attempts */
function recentLoginFailures(): array
{
    $file = loginThrottleFile();
    if (!is_file($file)) {
        return [];
    }
    $data = json_decode((string) file_get_contents($file), true) ?: [];
    $now = time();
    return array_values(array_filter($data, fn($ts) => is_int($ts) && $now - $ts < LOGIN_LOCK_WINDOW));
}

/**
 * Seconds until the next login attempt is allowed (0 = not locked).
 */
function loginLockedFor(): int
{
    $failures = recentLoginFailures();
    if (count($failures) < LOGIN_MAX_FAILURES) {
        return 0;
    }
    return max(0, min($failures) + LOGIN_LOCK_WINDOW - time());
}

function recordLoginFailure(): void
{
    $failures = recentLoginFailures();
    $failures[] = time();
    file_put_contents(loginThrottleFile(), json_encode($failures), LOCK_EX);
}

function clearLoginFailures(): void
{
    $file = loginThrottleFile();
    if (is_file($file)) {
        unlink($file);
    }
}

/**
 * Attempt login with username and password.
 * Accepts either ADMIN_PASSWORD_HASH (bcrypt/argon, preferred) or ADMIN_PASSWORD.
 * With neither configured, login is disabled.
 */
function attemptLogin(string $username, string $password, array $config): bool
{
    $expectedUser = (string) ($config['admin_username'] ?? '');
    $hash         = (string) ($config['admin_password_hash'] ?? '');
    $plain        = (string) ($config['admin_password'] ?? '');

    $userOk = $expectedUser !== '' && hash_equals($expectedUser, $username);

    if ($hash !== '') {
        $passOk = password_verify($password, $hash);
    } elseif ($plain !== '') {
        $passOk = hash_equals($plain, $password);
    } else {
        error_log('ADMIN [LOGIN] No ADMIN_PASSWORD_HASH / ADMIN_PASSWORD configured — login disabled');
        $passOk = false;
    }

    if ($userOk && $passOk) {
        session_regenerate_id(true);
        rotateCsrfToken();
        $_SESSION['admin_authenticated'] = true;
        $_SESSION['admin_username'] = $username;
        $_SESSION['admin_login_time'] = time();
        clearLoginFailures();
        auditLog('login', 'admin');
        return true;
    }

    recordLoginFailure();
    auditLog('login_failed', 'admin', '', ['username' => mb_substr($username, 0, 64)]);
    return false;
}

/**
 * Get current admin username.
 */
function getAdminUser(): string
{
    return $_SESSION['admin_username'] ?? 'Admin';
}
