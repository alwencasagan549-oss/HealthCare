<?php
declare(strict_types=1);

// ============================================================
// Header Include
// ============================================================

if (session_status() === PHP_SESSION_NONE) {
    session_name(SESSION_NAME);
    session_start();
}

require_once base_path('config/config.php');
require_once INCLUDES_PATH . '/helpers.php';

$currentUser = current_user();
$pageTitle   = $pageTitle ?? APP_SHORT_NAME;
$pageStyles  = $pageStyles ?? [];
$pageScripts = $pageScripts ?? [];

// Initialize PDO if not already done
$pdo = Database::getConnection();
$auth = new Auth($pdo);
$audit = new AuditLogger($pdo);

// --------------------------------------------------------
// CSRF token init for forms
// --------------------------------------------------------
if (empty($_SESSION['csrf_token'])) {
    csrf_token();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <title><?= e($pageTitle . ' - ' . APP_SHORT_NAME) ?></title>

    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <!-- Global App Styles -->
    <link href="<?= e(asset_path('assets/css/app.css')) ?>" rel="stylesheet">

    <?php foreach ($pageStyles as $style): ?>
        <link href="<?= e($style) ?>" rel="stylesheet">
    <?php endforeach; ?>
</head>
<body>

<nav class="navbar navbar-expand navbar-dark bg-dark fixed-top">
    <div class="container-fluid">
        <button class="btn btn-outline-light btn-sm d-md-none me-2" id="sidebarToggle" type="button">
            <i class="bi bi-list"></i>
        </button>
        <a class="navbar-brand" href="<?= e(asset_path('admin/dashboard.php')) ?>">
            <i class="bi bi-heart-pulse me-2"></i>
            <?= e(APP_SHORT_NAME) ?>
        </a>
        <div class="ms-auto d-flex align-items-center gap-3 text-light small">
            <span class="d-none d-md-inline">
                <i class="bi bi-person-circle me-1"></i>
                <?= e($currentUser['full_name'] ?? '') ?>
            </span>
            <span class="badge bg-primary">
                <?= e(ucfirst($currentUser['role'] ?? '')) ?>
            </span>
            <a href="<?= e(url('logout.php')) ?>" class="text-light text-decoration-none">
                <i class="bi bi-box-arrow-right"></i> Logout
            </a>
        </div>
    </div>
</nav>

<div class="sidebar-overlay" id="sidebarOverlay"></div>
