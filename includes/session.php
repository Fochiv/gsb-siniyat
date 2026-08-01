<?php
/**
 * Secure session management
 */

require_once dirname(__DIR__) . '/config/config.php';

function startSecureSession(): void {
    if (session_status() === PHP_SESSION_ACTIVE) return;

    $secret = getenv('SESSION_SECRET') ?: 'siniyat-default-secret-2026';

    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'secure'   => isset($_SERVER['HTTPS']),
        'httponly' => true,
        'samesite' => 'Strict',
    ]);
    session_name('SINIYAT_SESSION');
    session_start();

    // Regenerate session ID periodically
    if (!isset($_SESSION['_initiated'])) {
        session_regenerate_id(true);
        $_SESSION['_initiated'] = true;
        $_SESSION['_last_activity'] = time();
    }
}

function checkSessionTimeout(): bool {
    if (!isset($_SESSION['user_id'])) return false;
    $idle = time() - ($_SESSION['_last_activity'] ?? 0);
    if ($idle > SESSION_TIMEOUT) {
        session_unset();
        session_destroy();
        return false;
    }
    $_SESSION['_last_activity'] = time();
    return true;
}

function requireLogin(string $role = ''): array {
    startSecureSession();
    if (!checkSessionTimeout() || !isset($_SESSION['user_id'])) {
        header('Location: /login.php?timeout=1');
        exit;
    }
    if ($role && $_SESSION['user_role'] !== $role && $_SESSION['user_role'] !== 'admin') {
        header('Location: /login.php?forbidden=1');
        exit;
    }
    return $_SESSION;
}

function requireAdmin(): array {
    startSecureSession();
    if (!checkSessionTimeout() || !isset($_SESSION['user_id'])) {
        header('Location: /login.php?timeout=1');
        exit;
    }
    if ($_SESSION['user_role'] !== 'admin') {
        header('Location: /secretary/index.php');
        exit;
    }
    return $_SESSION;
}
