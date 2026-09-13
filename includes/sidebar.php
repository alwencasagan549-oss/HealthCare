<?php
declare(strict_types=1);

// ============================================================
// Sidebar Include
// ============================================================

$currentPath = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$role       = $_SESSION['role'] ?? '';

function is_active(string $path): string
{
    $currentPath = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    return (str_contains((string)$currentPath, $path)) ? 'active' : '';
}

function nav_item(string $url, string $label, string $icon, array $paths = []): string
{
    $currentPath = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    $active = '';

    foreach ($paths as $path) {
        if (str_contains($currentPath, $path)) {
            $active = 'active';
            break;
        }
    }

    $url = htmlspecialchars($url, ENT_QUOTES, 'UTF-8');
    $label = htmlspecialchars($label, ENT_QUOTES, 'UTF-8');

    return '<li class="nav-item">
        <a class="nav-link ' . $active . '" href="' . $url . '">
            <i class="bi bi-' . $icon . '"></i>' . $label . '
        </a>
    </li>';
}

?>

<div class="sidebar" id="sidebar">
    <?php if ($role === 'admin'): ?>
        <div class="nav-section">Main</div>
        <?= nav_item(url('admin/dashboard.php'), 'Dashboard', 'speedometer2', ['admin/dashboard']) ?>
        <?= nav_item(url('admin/districts.php'), 'Districts', 'geo-alt', ['admin/districts']) ?>
        <?= nav_item(url('admin/nurses.php'), 'Nurses', 'person-vcard', ['admin/nurses', 'admin/assign_nurse']) ?>

        <div class="nav-section">Reports</div>
        <?= nav_item(url('admin/reports.php'), 'Reports', 'bar-chart', ['admin/reports']) ?>
        <?= nav_item(url('admin/account_settings.php'), 'Account Settings', 'gear', ['admin/account_settings']) ?>

    <?php elseif ($role === 'nurse'): ?>
        <div class="nav-section">Main</div>
        <?= nav_item(url('nurse/dashboard.php'), 'Dashboard', 'speedometer2', ['nurse/dashboard']) ?>
        <?= nav_item(url('nurse/patients.php'), 'Patients', 'people', ['nurse/patients']) ?>
        <?= nav_item(url('nurse/survey_results.php'), 'Risk Assessments', 'clipboard-data', ['nurse/survey_results', 'nurse/survey_review']) ?>
        <?= nav_item(url('nurse/dpwh_accounts.php'), 'DPWH Accounts', 'person-gear', ['nurse/dpwh_accounts', 'nurse/dpwh_form']) ?>

        <div class="nav-section">Reports</div>
        <?= nav_item(url('nurse/reports.php'), 'Reports', 'bar-chart', ['nurse/reports']) ?>

    <?php elseif ($role === 'dpwh'): ?>
        <div class="nav-section">Main</div>
        <?= nav_item(url('dpwh/dashboard.php'), 'Dashboard', 'speedometer2', ['dpwh/dashboard']) ?>
        <?= nav_item(url('dpwh/surveys_new.php'), 'New Assessment', 'plus-circle', ['dpwh/surveys_new']) ?>
        <?= nav_item(url('dpwh/surveys_mine.php'), 'My Assessments', 'journal-check', ['dpwh/surveys_mine']) ?>
    <?php endif; ?>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const sidebar = document.getElementById('sidebar');
    const toggle = document.getElementById('sidebarToggle');
    const overlay = document.getElementById('sidebarOverlay');

    if (toggle && sidebar) {
        toggle.addEventListener('click', function () {
            sidebar.classList.toggle('show');
            if (overlay) overlay.classList.toggle('show');
        });
    }

    if (overlay) {
        overlay.addEventListener('click', function () {
            sidebar.classList.remove('show');
            overlay.classList.remove('show');
        });
    }
});
</script>
