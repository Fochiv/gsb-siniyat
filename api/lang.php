<?php
require_once dirname(__DIR__) . '/includes/session.php';
require_once dirname(__DIR__) . '/includes/csrf.php';
require_once dirname(__DIR__) . '/includes/functions.php';

startSecureSession();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $body = json_decode(file_get_contents('php://input'), true);
    $lang = $body['lang'] ?? 'fr';
    if (in_array($lang, ['fr','en'], true)) {
        $_SESSION['lang'] = $lang;
    }
    jsonResponse(['ok' => true]);
}

jsonResponse(['lang' => $_SESSION['lang'] ?? 'fr']);
