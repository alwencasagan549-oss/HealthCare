<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/middleware.php';
require_once __DIR__ . '/../includes/audit.php';

Middleware::nurse();

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';

$pdo = Database::getConnection();
$districtId = (int)($_SESSION['district_id'] ?? 0);

// District stats
$patientCount = (int)$pdo->query("SELECT COUNT(*) FROM patients WHERE district_id = {$districtId} AND status = 'active'")->fetchColumn();
$assessmentCount = (int)$pdo->query("SELECT COUNT(*) FROM risk_assessments WHERE district_id = {$districtId}")->fetchColumn();
$pendingReviews = (int)$pdo->query("SELECT COUNT(*) FROM risk_assessments WHERE district_id = {$districtId} AND status = 'pending'")->fetchColumn();
$flaggedCount = (int)$pdo->query("SELECT COUNT(*) FROM risk_assessments WHERE district_id = {$districtId} AND status = 'flagged'")->fetchColumn();
$dpwhCount = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE district_id = {$districtId} AND role = 'dpwh' AND status = 'active'")->fetchColumn();

// District info
$stmt = $pdo->prepare("SELECT district_name, district_code FROM districts WHERE district_id = :id LIMIT 1");
$stmt->execute([':id' => $districtId]);
$district = $stmt->fetch();

// Recent assessments
$stmtResults = $pdo->prepare("
    SELECT ra.assessment_id, ra.status, ra.created_at, ra.assessment_date,
           CONCAT(p.first_name, ' ', p.last_name) AS patient_name,
           CONCAT(u.full_name, ' (', u.username, ')') AS conducted_by
    FROM risk_assessments ra
    JOIN patients p ON p.patient_id = ra.patient_id
    JOIN users u ON u.user_id = ra.conducted_by
    WHERE ra.district_id = :district_id
    ORDER BY ra.assessment_date DESC
    LIMIT 8
");
$stmtResults->execute([':district_id' => $districtId]);
$results = $stmtResults->fetchAll();

$pageTitle = 'Dashboard';
require_once __DIR__ . '/../includes/sidebar.php';
?>

<div class="main-content">
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 gap-2">
        <div>
            <h2 class="dashboard-section-title">Dashboard</h2>
            <p class="dashboard-section-subtitle"><?= e($district['district_name'] ?? '') ?> overview</p>
        </div>
        <span class="text-muted small">
            <i class="bi bi-calendar3 me-1"></i><?= e(date('F j, Y')) ?>
        </span>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-md-3 col-sm-6">
            <div class="card card-stat h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <p class="text-muted mb-1 small text-uppercase fw-semibold">Patients</p>
                            <h3 class="mb-0 dashboard-stat-value"><?= e((string)$patientCount) ?></h3>
                        </div>
                        <div class="icon bg-info bg-opacity-10 text-info"><i class="bi bi-people"></i></div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-sm-6">
            <div class="card card-stat h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <p class="text-muted mb-1 small text-uppercase fw-semibold">Assessments</p>
                            <h3 class="mb-0 dashboard-stat-value"><?= e((string)$assessmentCount) ?></h3>
                        </div>
                        <div class="icon bg-primary bg-opacity-10 text-primary"><i class="bi bi-clipboard-data"></i></div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-sm-6">
            <div class="card card-stat h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <p class="text-muted mb-1 small text-uppercase fw-semibold">Pending Reviews</p>
                            <h3 class="mb-0 dashboard-stat-value"><?= e((string)$pendingReviews) ?></h3>
                        </div>
                        <div class="icon bg-warning bg-opacity-10 text-warning"><i class="bi bi-hourglass-split"></i></div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-sm-6">
            <div class="card card-stat h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <p class="text-muted mb-1 small text-uppercase fw-semibold">Flagged</p>
                            <h3 class="mb-0 dashboard-stat-value text-danger"><?= e((string)$flaggedCount) ?></h3>
                        </div>
                        <div class="icon bg-danger bg-opacity-10 text-danger"><i class="bi bi-flag"></i></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3">
        <div class="col-lg-8">
            <div class="dashboard-card">
                <div class="dashboard-card-header">
                    <h5 class="mb-0">Recent Assessments</h5>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table dashboard-table table-hover align-middle mb-0">
                            <thead>
                                <tr>
                                    <th>Patient</th>
                                    <th>Conducted By</th>
                                    <th>Status</th>
                                    <th>Date</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!$results): ?>
                                    <tr><td colspan="4" class="dashboard-empty">No assessments found.</td></tr>
                                <?php else: ?>
                                    <?php foreach ($results as $r): ?>
                                        <tr>
                                            <td class="fw-semibold"><?= e($r['patient_name']) ?></td>
                                            <td class="small"><?= e($r['conducted_by']) ?></td>
                                            <td>
                                                <span class="badge bg-<?= $r['status'] === 'pending' ? 'warning' : ($r['status'] === 'reviewed' ? 'success' : 'danger') ?>">
                                                    <?= e(ucfirst($r['status'])) ?>
                                                </span>
                                            </td>
                                            <td class="small text-muted"><?= e(format_date($r['assessment_date'])) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="dashboard-card mb-3">
                <div class="dashboard-card-header">
                    <h5 class="mb-0">District Info</h5>
                </div>
                <div class="dashboard-card-body">
                    <div class="dashboard-list-item">
                        <span class="dashboard-meta">Name</span>
                        <span class="fw-semibold"><?= e($district['district_name'] ?? '') ?></span>
                    </div>
                    <div class="dashboard-list-item">
                        <span class="dashboard-meta">Code</span>
                        <span><?= e($district['district_code'] ?? '') ?></span>
                    </div>
                    <div class="dashboard-list-item">
                        <span class="dashboard-meta">DPWH Accounts</span>
                        <span><?= e((string)$dpwhCount) ?></span>
                    </div>
                </div>
            </div>

            <div class="dashboard-card">
                <div class="dashboard-card-header">
                    <h5 class="mb-0">Quick Actions</h5>
                </div>
                <div class="dashboard-card-body d-grid gap-2">
                    <a href="<?= e(url('nurse/patients.php')) ?>" class="dashboard-quick-action">
                        <i class="bi bi-people"></i> Manage Patients
                    </a>
                    <a href="<?= e(url('nurse/survey_results.php')) ?>" class="dashboard-quick-action">
                        <i class="bi bi-clipboard-data"></i> Review Assessments
                    </a>
                    <a href="<?= e(url('nurse/dpwh_accounts.php')) ?>" class="dashboard-quick-action">
                        <i class="bi bi-person-gear"></i> DPWH Accounts
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
