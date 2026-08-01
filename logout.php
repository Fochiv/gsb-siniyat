<?php
require_once __DIR__ . '/includes/session.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';

startSecureSession();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrfCheck();
    if (isset($_SESSION['user_id'])) {
        auditLog($_SESSION['user_id'], 'DECONNEXION', 'utilisateurs', $_SESSION['user_id'], 'Déconnexion manuelle');
    }
    session_unset();
    session_destroy();
}

header('Location: /login.php');
exit;
