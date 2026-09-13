<?php
declare(strict_types=1);

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/middleware.php';
require_once __DIR__ . '/includes/audit.php';

$pdo = Database::getConnection();

header('Content-Type: text/plain; charset=utf-8');
?>
=== DEBUG REPORT ===
Date: <?= date('Y-m-d H:i:s') ?>
APP_ENV: <?= e(APP_ENV) ?>
APP_DEBUG: <?= APP_DEBUG ? 'true' : 'false' ?>
APP_URL: <?= e(APP_URL) ?>
REQUEST_METHOD: <?= e((string)$_SERVER['REQUEST_METHOD']) ?>
REQUEST_URI: <?= e((string)$_SERVER['REQUEST_URI']) ?>
HTTPS: <?= e((string)($_SERVER['HTTPS'] ?? 'n/a')) ?>
REMOTE_ADDR: <?= e((string)($_SERVER['REMOTE_ADDR'] ?? 'n/a')) ?>

=== SESSION ===
session_status: <?= session_status() === PHP_SESSION_ACTIVE ? 'active' : 'inactive' ?>
session_name: <?= e(session_name()) ?>
session_id: <?= e((string)($_COOKIE[session_name()] ?? session_id() ?? 'none')) ?>
session_cookie_params: <?= json_encode(session_get_cookie_params(), JSON_PRETTY_PRINT) ?>
=== SESSION VARS ===
<?php if (empty($_SESSION)): ?>
(empty)
<?php else: ?>
<?php foreach ($_SESSION as $k => $v): ?>
<?= e((string)$k) ?>: <?= is_array($v) ? json_encode($v, JSON_PRETTY_PRINT) : (is_bool($v) ? ($v ? 'true' : 'false') : (is_null($v) ? 'null' : e((string)$v))) ?>
<?php endforeach; ?>
<?php endif; ?>
=== AUTH ===
is_logged_in: <?= is_logged_in() ? 'true' : 'false' ?>
current_user: <?php $u = current_user(); echo $u ? json_encode($u, JSON_PRETTY_PRINT) : 'none'; ?>
is_admin: <?= is_admin() ? 'true' : 'false' ?>
is_nurse: <?= is_nurse() ? 'true' : 'false' ?>
is_dpwh: <?= is_dpwh() ? 'true' : 'false' ?>
=== USERS ===
<?php
$users = $pdo->query("SELECT user_id, username, role, district_id, status, force_password_change, last_login FROM users ORDER BY role, username")->fetchAll();
foreach ($users as $u):
?>
user_id=<?= e((string)$u['user_id']) ?> username=<?= e($u['username']) ?> role=<?= e($u['role']) ?> district_id=<?= e((string)($u['district_id'] ?? 'null')) ?> status=<?= e($u['status']) ?> force_password_change=<?= $u['force_password_change'] ? 'true' : 'false' ?> last_login=<?= e((string)($u['last_login'] ?? 'never')) ?>
<?php endforeach; ?>
=== DISTRICTS ===
<?php foreach ($pdo->query('SELECT district_id, district_name, status FROM districts ORDER BY district_name') as $d): ?>
district_id=<?= e((string)$d['district_id']) ?> name=<?= e($d['district_name']) ?> status=<?= e($d['status']) ?>
<?php endforeach; ?>
=== DATABASE CONNECTION ===
driver: pgsql
host: <?= e((string)($_ENV['DB_HOST'] ?? 'n/a')) ?>
port: <?= e((string)($_ENV['DB_PORT'] ?? 'n/a')) ?>
dbname: <?= e((string)($_ENV['DB_NAME'] ?? 'n/a')) ?>
user: <?= e((string)($_ENV['DB_USER'] ?? 'n/a')) ?>
=== END ===
