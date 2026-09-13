<?php
declare(strict_types=1);

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/middleware.php';
require_once __DIR__ . '/includes/audit.php';

$pdo = Database::getConnection();
$auth = new Auth($pdo);
$audit = new AuditLogger($pdo);

$currentUser = current_user();

header('Content-Type: text/plain; charset=utf-8');
?>
=== DEBUG REPORT ===
Date: <?= date('Y-m-d H:i:s') ?>
APP_ENV: <?= e(APP_ENV) ?>
APP_DEBUG: <?= APP_DEBUG ? 'true' : 'false' ?>
APP_URL: <?= e(APP_URL) ?>

=== SESSION ===
session_status: <?= session_status() === PHP_SESSION_ACTIVE ? 'active' : 'inactive' ?>
session_name: <?= e(session_name()) ?>
session_id: <?= e(session_id() ?? 'none') ?>
=== SESSION VARS ===
<?php foreach ($_SESSION as $k => $v): ?>
<?= e($k) ?>: <?= is_array($v) ? json_encode($v, JSON_PRETTY_PRINT) : (is_bool($v) ? ($v ? 'true' : 'false') : (is_null($v) ? 'null' : e((string)$v))) ?>
<?php endforeach; ?>
=== AUTH ===
is_logged_in: <?= is_logged_in() ? 'true' : 'false' ?>
current_user: <?= $currentUser ? json_encode($currentUser, JSON_PRETTY_PRINT) : 'none' ?>
role: <?= e($currentUser['role'] ?? 'none') ?>
district_id: <?= e((string)($currentUser['district_id'] ?? 'none')) ?>
force_password_change: <?= e((string)($currentUser['force_password_change'] ?? 'none')) ?>
logged_in_at: <?= e((string)($currentUser['logged_in_at'] ?? 'none')) ?>
inactivity_timeout: <?= e((string)SESSION_TIMEOUT) ?>
=== USERS ===
<?php
$users = $pdo->query("SELECT user_id, username, role, district_id, status, force_password_change FROM users ORDER BY role, username")->fetchAll();
foreach ($users as $u):
?>
user_id=<?= e((string)$u['user_id']) ?> username=<?= e($u['username']) ?> role=<?= e($u['role']) ?> district_id=<?= e((string)$u['district_id']) ?> status=<?= e($u['status']) ?> force_password_change=<?= $u['force_password_change'] ? 'true' : 'false' ?>
<?php endforeach; ?>
=== DISTRICTS ===
<?php foreach ($pdo->query('SELECT district_id, district_name, status FROM districts ORDER BY district_name') as $d): ?>
district_id=<?= e((string)$d['district_id']) ?> name=<?= e($d['district_name']) ?> status=<?= e($d['status']) ?>
<?php endforeach; ?>
=== DATABASE ===
driver: pgsql
host: <?= e((string)($_ENV['DB_HOST'] ?? 'n/a')) ?>
port: <?= e((string)($_ENV['DB_PORT'] ?? 'n/a')) ?>
dbname: <?= e((string)($_ENV['DB_NAME'] ?? 'n/a')) ?>
user: <?= e((string)($_ENV['DB_USER'] ?? 'n/a')) ?>
password: <?= e((string)($_ENV['DB_PASSWORD'] ?? 'n/a')) ?>
=== END ===
