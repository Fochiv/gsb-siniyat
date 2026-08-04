<?php
/**
 * Configuration base de données — GSB SINIYAT
 * Compatible MySQL (Aeonfree / Hostinger) ET PostgreSQL (Replit)
 *
 * ┌─────────────────────────────────────────────────────────────────┐
 * │  POUR AEONFREE : remplissez les 4 lignes de la section MySQL   │
 * │  ci-dessous et définissez MYSQL_HOST dans votre environnement  │
 * │  OU décommentez le bloc "AEONFREE DIRECT" plus bas.            │
 * └─────────────────────────────────────────────────────────────────┘
 */

// ══════════════════════════════════════════════════════════════════
//  AEONFREE / HÉBERGEUR MUTUALISÉ — décommentez et remplissez
// ══════════════════════════════════════════════════════════════════
// define('DB_HOST', 'sql308.hstn.me');
// define('DB_NAME', 'mseet_42573994_gsbsiniyat');
// define('DB_USER', 'mseet_42573994');
// define('DB_PASS', 'VOTRE_MOT_DE_PASSE_ICI');
// ══════════════════════════════════════════════════════════════════

function dbIsMySQL(): bool {
    static $v = null;
    if ($v === null) {
        $v = defined('DB_HOST') || (bool)(getenv('MYSQL_HOST') ?: '');
    }
    return $v;
}

function getDB(): PDO {
    static $pdo = null;
    if ($pdo !== null) return $pdo;

    if (dbIsMySQL()) {
        // ── MySQL (Aeonfree / Hostinger) ─────────────────────────────────
        $host = defined('DB_HOST') ? DB_HOST : (getenv('MYSQL_HOST') ?: 'localhost');
        $port = defined('DB_PORT') ? DB_PORT : (getenv('MYSQL_PORT') ?: '3306');
        $db   = defined('DB_NAME') ? DB_NAME : (getenv('MYSQL_DATABASE') ?: getenv('MYSQL_DB') ?: '');
        $user = defined('DB_USER') ? DB_USER : (getenv('MYSQL_USER') ?: '');
        $pass = defined('DB_PASS') ? DB_PASS : (getenv('MYSQL_PASSWORD') ?: getenv('MYSQL_PASS') ?: '');

        $pdo = new PDO(
            "mysql:host=$host;port=$port;dbname=$db;charset=utf8mb4",
            $user, $pass,
            [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]
        );
    } else {
        // ── PostgreSQL (Replit, via DATABASE_URL) ─────────────────────────
        $dsn = getenv('DATABASE_URL');
        if ($dsn) {
            $parsed = parse_url($dsn);
            $pgDsn  = "pgsql:host={$parsed['host']};port=" . ($parsed['port'] ?? 5432)
                    . ";dbname=" . ltrim($parsed['path'], '/');
            $pdo = new PDO($pgDsn, $parsed['user'] ?? '', $parsed['pass'] ?? '', [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
        } else {
            $host   = getenv('PGHOST')     ?: 'localhost';
            $port   = getenv('PGPORT')     ?: '5432';
            $dbname = getenv('PGDATABASE') ?: 'replit';
            $user   = getenv('PGUSER')     ?: 'runner';
            $pass   = getenv('PGPASSWORD') ?: '';
            $pdo    = new PDO("pgsql:host=$host;port=$port;dbname=$dbname", $user, $pass, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
        }
    }

    return $pdo;
}
