<?php
declare(strict_types=1);

// ============================================================
// IPIHRS-CHC Application Configuration
// ============================================================

// Application
define('APP_NAME', 'Integrated Networked System for Patient Information and Record Entry');
define('APP_SHORT_NAME', 'INSPIRE');
$appUrl = rtrim((string)($_ENV['APP_URL'] ?? $_SERVER['APP_URL'] ?? getenv('APP_URL') ?: 'http://localhost/Health-Report-Sytem'), '/');
define('APP_URL', $appUrl);
define('APP_VERSION', '1.0.0');

// Environment
$appEnv = strtolower((string)($_ENV['APP_ENV'] ?? $_SERVER['APP_ENV'] ?? getenv('APP_ENV') ?: 'production'));
define('APP_ENV', $appEnv);
define('APP_DEBUG', $appEnv !== 'production');

// Paths
define('BASE_PATH', __DIR__ . '/..');
define('INCLUDES_PATH', BASE_PATH . '/includes');
define('ASSETS_PATH', BASE_PATH . '/assets');

// Security
define('SESSION_NAME', 'ipihrs_chc_session');
define('CSRF_TOKEN_LIFETIME', 1800); // 30 minutes
define('LOGIN_MAX_ATTEMPTS', 5);
define('LOGIN_LOCKOUT_WINDOW', 900); // 15 minutes
define('PASSWORD_MIN_LENGTH', 8);
define('BCRYPT_COST', 12);
define('SESSION_TIMEOUT', 900); // 15 minutes of inactivity

// Pagination
define('DEFAULT_PAGE_LIMIT', 20);
define('MAX_PAGE_LIMIT', 100);

// Audit
define('AUDIT_ACTIONS', ['CREATE','UPDATE','DELETE','LOGIN','LOGOUT','VIEW','OTHER']);

// Timezone
date_default_timezone_set('Asia/Manila');

// Session configuration (must be set before session_start)
$sessionSecure = (string)($_ENV['SESSION_SECURE_COOKIE'] ?? $_SERVER['SESSION_SECURE_COOKIE'] ?? getenv('SESSION_SECURE_COOKIE') ?: '0');
$isHttps = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' || str_starts_with((string)($appUrl), 'https://');
ini_set('session.cookie_httponly', '1');
ini_set('session.cookie_samesite', 'Strict');
ini_set('session.cookie_secure', $isHttps || $sessionSecure === '1' ? '1' : '0');
ini_set('session.use_only_cookies', '1');

if (session_status() === PHP_SESSION_NONE) {
    session_name(SESSION_NAME);
    session_start();
}

// Error reporting
if (APP_DEBUG) {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
    ini_set('display_startup_errors', '1');
} else {
    error_reporting(E_ALL & ~E_NOTICE & ~E_DEPRECATED);
    ini_set('display_errors', '0');
    ini_set('display_startup_errors', '0');
    ini_set('log_errors', '1');
    ini_set('error_log', BASE_PATH . '/logs/php-error.log');
}
