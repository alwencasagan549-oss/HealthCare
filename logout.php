<?php
declare(strict_types=1);

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/middleware.php';
require_once __DIR__ . '/includes/audit.php';

Middleware::authenticated();

$pdo = Database::getConnection();
$audit = new AuditLogger($pdo);

$userId = $_SESSION['user_id'] ?? null;
$audit->logout((int)$userId);

$auth = new Auth($pdo);
$auth->logout();

redirect(url('index.php'));
