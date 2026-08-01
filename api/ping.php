<?php
require_once dirname(__DIR__) . '/includes/session.php';
startSecureSession();
// Update session last activity
if (isset($_SESSION['user_id'])) {
    $_SESSION['_last_activity'] = time();
}
header('Content-Type: application/json');
echo json_encode(['ok' => true, 'ts' => time()]);
