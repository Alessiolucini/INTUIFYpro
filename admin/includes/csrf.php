<?php
/**
 * IntuiFy Admin — CSRF protection
 * One random token per session. Forms send it as `_csrf`, AJAX calls as the
 * `X-CSRF-Token` header, and state-changing GET links as `?_csrf=`.
 */

declare(strict_types=1);

/**
 * Current session token (created on first use).
 */
function csrfToken(): string
{
    if (empty($_SESSION['csrf_token']) || !is_string($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Issue a fresh token (call after login to avoid token fixation).
 */
function rotateCsrfToken(): void
{
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

/**
 * Hidden input for POST forms.
 */
function csrfField(): string
{
    return '<input type="hidden" name="_csrf" value="' . htmlspecialchars(csrfToken()) . '">';
}

/**
 * Query-string fragment for state-changing links, e.g. "?action=delete&id=1&<?= csrfQuery() ?>".
 */
function csrfQuery(): string
{
    return '_csrf=' . urlencode(csrfToken());
}

function csrfIsValid(?string $token): bool
{
    return is_string($token)
        && $token !== ''
        && !empty($_SESSION['csrf_token'])
        && hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Abort with 403 unless the request carries a valid token.
 */
function requireCsrf(): void
{
    $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? $_POST['_csrf'] ?? $_GET['_csrf'] ?? null;
    if (csrfIsValid(is_string($token) ? $token : null)) {
        return;
    }

    error_log('ADMIN [CSRF] Rejected ' . ($_SERVER['REQUEST_METHOD'] ?? '?') . ' ' . ($_SERVER['REQUEST_URI'] ?? ''));
    http_response_code(403);

    $wantsJson = isset($_SERVER['HTTP_X_CSRF_TOKEN'])
        || str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json')
        || str_contains($_SERVER['SCRIPT_NAME'] ?? '', '/api/');

    $msg = 'Richiesta non valida o sessione scaduta. Ricarica la pagina e riprova.';
    if ($wantsJson) {
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode(['error' => $msg]);
    } else {
        header('Content-Type: text/plain; charset=UTF-8');
        echo $msg;
    }
    exit;
}
