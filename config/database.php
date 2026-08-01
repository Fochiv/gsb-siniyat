<?php
/**
 * Database configuration - PostgreSQL via PDO
 * Groupe Scolaire Bilingue SINIYAT
 */

function getDB(): PDO {
    static $pdo = null;
    if ($pdo !== null) return $pdo;

    $dsn = getenv('DATABASE_URL');
    if (!$dsn) {
        // Fallback to individual env vars
        $host = getenv('PGHOST') ?: 'localhost';
        $port = getenv('PGPORT') ?: '5432';
        $dbname = getenv('PGDATABASE') ?: 'replit';
        $user = getenv('PGUSER') ?: 'runner';
        $pass = getenv('PGPASSWORD') ?: '';
        $dsn = "pgsql:host=$host;port=$port;dbname=$dbname";
        $pdo = new PDO($dsn, $user, $pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
    } else {
        // Parse DATABASE_URL: postgres://user:pass@host:port/dbname
        $parsed = parse_url($dsn);
        $pgDsn = "pgsql:host={$parsed['host']};port=" . ($parsed['port'] ?? 5432) . ";dbname=" . ltrim($parsed['path'], '/');
        $pdo = new PDO($pgDsn, $parsed['user'] ?? '', $parsed['pass'] ?? '', [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
    }

    return $pdo;
}
