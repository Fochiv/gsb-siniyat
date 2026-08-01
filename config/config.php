<?php
/**
 * Global configuration
 * Groupe Scolaire Bilingue SINIYAT
 */

define('APP_NAME_FR', 'Groupe Scolaire Bilingue SINIYAT');
define('APP_NAME_EN', 'Siniyat Bilingual School Group');
define('APP_YEAR',    '2026-2027');
define('SESSION_TIMEOUT', 600); // 10 minutes in seconds

// Discount rates
define('REDUCTION_PAIEMENT_COMPLET', 2); // 2%
define('REDUCTION_FRATRIE',          2); // 2% per additional sibling

// Bank details (default)
define('BANQUE_NOM',    'Rural Investment Credit SA');
define('BANQUE_COMPTE', '37183-01000-016892-01');

// Paths
define('ROOT_PATH',   dirname(__DIR__));
define('ASSETS_PATH', '/assets');
define('UPLOAD_PATH', ROOT_PATH . '/uploads');

// Error reporting (set to 0 in production)
error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');
