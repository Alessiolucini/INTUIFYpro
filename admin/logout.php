<?php
/**
 * IntuiFy Admin — Logout
 */
require_once __DIR__ . '/includes/auth.php';

if (isAdminAuthenticated()) {
    auditLog('logout', 'admin');
}

$_SESSION = [];
session_destroy();
header('Location: /admin/index.php');
exit;
