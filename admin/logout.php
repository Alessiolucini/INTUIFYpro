<?php
/**
 * IntuiFy Admin — Logout
 */
session_start();
session_destroy();
header('Location: /admin/index.php');
exit;
