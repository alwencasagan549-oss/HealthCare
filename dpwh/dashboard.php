<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/middleware.php';
require_once __DIR__ . '/../includes/audit.php';

Middleware::dpwh();
Middleware::dpwhFirstLogin();

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';

$pdo = Database::getConnection();
$districtId = (int)($_SESSION['district_id'] ?? 0);
$dpwhId = (int)($_SESSION['user_id'] ?? 0);

// Stats
$stmt = $pdo->prepare("SELECT COUNT(*) FROM risk_assessments WHERE conducted_by = :dpwh_id");
$stmt->execute([':dpwh_id' => $dpwhId]);
$mySurveys = (int)$stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COUNT(*) FROM risk_assessments WHERE conducted_by = :dpwh_id AND status = 'pending'");
$stmt->execute([':dpwh_id' => $dpwhId]);
$pendingMine = (int)$stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COUNT(*) FROM risk_assessments WHERE conducted_by = :dpwh_id AND status = 'reviewed'");
$stmt->execute([':dpwh_id' => $dpwhId]);
$reviewedMine = (int)$stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COUNT(*) FROM risk_assessments WHERE conducted_by = :dpwh_id AND status = 'flagged'");
$stmt->execute([':dpwh_id' => $dpwhId]);
$flaggedMine = (int)$stmt->fetchColumn();

// District info
$stmt = $pdo->prepare("SELECT district_name, district_code FROM districts WHERE district_id = :id LIMIT 1");
$stmt->execute([':id' => $districtId]);
$district = $stmt->fetch();

// Active surveys
$stmtSurveys = $pdo->prepare("
    SELECT survey_id, survey_name, survey_type, description
    FROM surveys
    WHERE is_active = TRUE AND (district_id IS NULL OR district_id = :district_id)
    ORDER BY survey_name
");
$stmtSurveys->execute([':district_id' => $districtId]);
$surveys = $stmtSurveys->fetchAll();

// Recent my assessments
$stmtRecent = $pdo->prepare("
    SELECT ra.assessment_id, ra.status, ra.created_at, ra.assessment_date,
           CONCAT(p.first_name, ' ', p.last_name) AS patient_name
    FROM risk_assessments ra
    JOIN patients p ON p.patient_id = ra.patient_id
    WHERE ra.conducted_by = :dpwh_id
    ORDER BY ra.assessment_date DESC
    LIMIT 8
");
$stmtRecent->execute([':dpwh_id' => $dpwhId]);
$recent = $stmtRecent->fetchAll();

$pageTitle = 'Dashboard';
require_once __DIR__ . '/../includes/sidebar.php';
?>

<div class="main-content">
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 gap-2">
        <div>
            <h2 class="dashboard-section-title">Dashboard</h2>
            <p class="dashboard-section-subtitle">Welcome, <?= e($_SESSION['full_name'] ?? '') ?></p>
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
                            <p class="text-muted mb-1 small text-uppercase fw-semibold">My Assessments</p>
                            <h3 class="mb-0 dashboard-stat-value"><?= e((string)$mySurveys) ?></h3>
                        </div>
                        <div class="icon bg-primary bg-opacity-10 text-primary"><i class="bi bi-journal-check"></i></div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-sm-6">
            <div class="card card-stat h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <p class="text-muted mb-1 small text-uppercase fw-semibold">Pending</p>
                            <h3 class="mb-0 dashboard-stat-value"><?= e((string)$pendingMine) ?></h3>
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
                            <p class="text-muted mb-1 small text-uppercase fw-semibold">Reviewed</p>
                            <h3 class="mb-0 dashboard-stat-value"><?= e((string)$reviewedMine) ?></h3>
                        </div>
                        <div class="icon bg-success bg-opacity-10 text-success"><i class="bi bi-check-circle"></i></div>
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
                            <h3 class="mb-0 dashboard-stat-value text-danger"><?= e((string)$flaggedMine) ?></h3>
                        </div>
                        <div class="icon bg-danger bg-opacity-10 text-danger"><i class="bi bi-flag"></i></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3">
        <div class="col-lg-4">
            <div class="dashboard-card mb-3">
                <div class="dashboard-card-header">
                    <h5 class="mb-0">District</h5>
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
                </div>
            </div>

            <div class="dashboard-card">
                <div class="dashboard-card-header">
                    <h5 class="mb-0">Available Assessments</h5>
                </div>
                <div class="dashboard-card-body">
                    <?php if (!$surveys): ?>
                        <p class="dashboard-empty">No active assessment forms available.</p>
                    <?php else: ?>
                        <ul class="list-group list-group-flush">
                            <?php foreach ($surveys as $s): ?>
                                <li class="list-group-item px-0">
                                    <div class="fw-semibold"><?= e($s['survey_name']) ?></div>
                                    <div class="small text-muted"><?= e($s['survey_type'] ?? 'General') ?></div>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="col-lg-8">
            <div class="dashboard-card">
                <div class="dashboard-card-header">
                    <div class="d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">My Recent Assessments</h5>
                        <a href="<?= e(url('dpwh/surveys_new.php')) ?>" class="btn btn-primary btn-sm">
                            <i class="bi bi-plus-circle me-1"></i> New Assessment
                        </a>
                    </div>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table dashboard-table table-hover align-middle mb-0">
                            <thead>
                                <tr>
                                    <th>Patient</th>
                                    <th>Status</th>
                                    <th>Date</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!$recent): ?>
                                    <tr><td colspan="3" class="dashboard-empty">No assessments submitted yet.</td></tr>
                                <?php else: ?>
                                    <?php foreach ($recent as $r): ?>
                                        <tr>
                                            <td class="fw-semibold"><?= e($r['patient_name']) ?></td>
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
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
