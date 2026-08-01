<?php
require_once __DIR__ . '/includes/session.php';
startSecureSession();

if (isset($_SESSION['user_id']) && isset($_SESSION['user_role'])) {
    if ($_SESSION['user_role'] === 'admin') {
        header('Location: /admin/index.php');
    } else {
        header('Location: /secretary/index.php');
    }
    exit;
}
header('Location: /login.php');
exit;
