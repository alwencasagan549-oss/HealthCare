<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/middleware.php';
require_once __DIR__ . '/../includes/audit.php';
require_once __DIR__ . '/../includes/header.php';

Middleware::dpwh();

$pdo = Database::getConnection();
$districtId = (int)($_SESSION['district_id'] ?? 0);
$dpwhId = (int)($_SESSION['user_id'] ?? 0);

// Stats
$stmt = $pdo->prepare("SELECT COUNT(*) FROM survey_results WHERE conducted_by = :dpwh_id");
$stmt->execute([':dpwh_id' => $dpwhId]);
$mySurveys = (int)$stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COUNT(*) FROM survey_results WHERE conducted_by = :dpwh_id AND status = 'pending'");
$stmt->execute([':dpwh_id' => $dpwhId]);
$pendingMine = (int)$stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COUNT(*) FROM survey_results WHERE conducted_by = :dpwh_id AND status = 'reviewed'");
$stmt->execute([':dpwh_id' => $dpwhId]);
$reviewedMine = (int)$stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COUNT(*) FROM survey_results WHERE conducted_by = :dpwh_id AND status = 'flagged'");
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

// Recent my surveys
$stmtRecent = $pdo->prepare("
    SELECT sr.result_id, sr.status, sr.created_at, s.survey_name,
           CONCAT(p.first_name, ' ', p.last_name) AS patient_name
    FROM survey_results sr
    JOIN surveys s ON s.survey_id = sr.survey_id
    JOIN patients p ON p.patient_id = sr.patient_id
    WHERE sr.conducted_by = :dpwh_id
    ORDER BY sr.created_at DESC
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
            <h2 class="mb-0">Dashboard</h2>
            <p class="text-muted mb-0">Welcome, <?= e($_SESSION['full_name'] ?? '') ?></p>
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
                            <p class="text-muted mb-1 small text-uppercase fw-semibold">My Surveys</p>
                            <h3 class="mb-0 fw-bold"><?= e((string)$mySurveys) ?></h3>
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
                            <h3 class="mb-0 fw-bold"><?= e((string)$pendingMine) ?></h3>
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
                            <h3 class="mb-0 fw-bold"><?= e((string)$reviewedMine) ?></h3>
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
                            <h3 class="mb-0 fw-bold text-danger"><?= e((string)$flaggedMine) ?></h3>
                        </div>
                        <div class="icon bg-danger bg-opacity-10 text-danger"><i class="bi bi-flag"></i></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3">
        <div class="col-lg-4">
            <div class="card border-0 shadow-sm mb-3">
                <div class="card-header bg-white border-0 py-3">
                    <h5 class="mb-0">District</h5>
                </div>
                <div class="card-body">
                    <div class="mb-2">
                        <div class="small text-muted">Name</div>
                        <div class="fw-semibold"><?= e($district['district_name'] ?? '') ?></div>
                    </div>
                    <div class="mb-2">
                        <div class="small text-muted">Code</div>
                        <div><?= e($district['district_code'] ?? '') ?></div>
                    </div>
                </div>
            </div>

            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white border-0 py-3">
                    <h5 class="mb-0">Available Surveys</h5>
                </div>
                <div class="card-body">
                    <?php if (!$surveys): ?>
                        <p class="text-muted mb-0 small">No active surveys available.</p>
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
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white border-0 py-3 d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">My Recent Surveys</h5>
                    <a href="<?= e(url('dpwh/surveys_new.php')) ?>" class="btn btn-primary btn-sm">
                        <i class="bi bi-plus-circle me-1"></i> New Survey
                    </a>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Survey</th>
                                    <th>Patient</th>
                                    <th>Status</th>
                                    <th>Date</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!$recent): ?>
                                    <tr><td colspan="4" class="text-center text-muted py-4">No surveys submitted yet.</td></tr>
                                <?php else: ?>
                                    <?php foreach ($recent as $r): ?>
                                        <tr>
                                            <td><?= e($r['survey_name']) ?></td>
                                            <td><?= e($r['patient_name']) ?></td>
                                            <td>
                                                <span class="badge bg-<?= $r['status'] === 'pending' ? 'warning' : ($r['status'] === 'reviewed' ? 'success' : 'danger') ?>">
                                                    <?= e(ucfirst($r['status'])) ?>
                                                </span>
                                            </td>
                                            <td class="small text-muted"><?= e(format_datetime($r['created_at'])) ?></td>
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
