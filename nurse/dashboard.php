<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/middleware.php';
require_once __DIR__ . '/../includes/audit.php';
require_once __DIR__ . '/../includes/header.php';

Middleware::nurse();

$pdo = Database::getConnection();
$districtId = (int)($_SESSION['district_id'] ?? 0);

// District stats
$patientCount = (int)$pdo->query("SELECT COUNT(*) FROM patients WHERE district_id = {$districtId} AND status = 'active'")->fetchColumn();
$surveyCount = (int)$pdo->query("SELECT COUNT(*) FROM survey_results WHERE district_id = {$districtId}")->fetchColumn();
$pendingReviews = (int)$pdo->query("SELECT COUNT(*) FROM survey_results WHERE district_id = {$districtId} AND status = 'pending'")->fetchColumn();
$flaggedCount = (int)$pdo->query("SELECT COUNT(*) FROM survey_results WHERE district_id = {$districtId} AND status = 'flagged'")->fetchColumn();
$dpwhCount = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE district_id = {$districtId} AND role = 'dpwh' AND status = 'active'")->fetchColumn();

// District info
$stmt = $pdo->prepare("SELECT district_name, district_code FROM districts WHERE district_id = :id LIMIT 1");
$stmt->execute([':id' => $districtId]);
$district = $stmt->fetch();

// Recent survey results
$stmtResults = $pdo->prepare("
    SELECT sr.result_id, sr.status, sr.created_at,
           s.survey_name,
           CONCAT(p.first_name, ' ', p.last_name) AS patient_name,
           CONCAT(u.full_name, ' (', u.username, ')') AS conducted_by
    FROM survey_results sr
    JOIN surveys s ON s.survey_id = sr.survey_id
    JOIN patients p ON p.patient_id = sr.patient_id
    JOIN users u ON u.user_id = sr.conducted_by
    WHERE sr.district_id = :district_id
    ORDER BY sr.created_at DESC
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
            <h2 class="mb-0">Dashboard</h2>
            <p class="text-muted mb-0"><?= e($district['district_name'] ?? '') ?> overview</p>
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
                            <h3 class="mb-0 fw-bold"><?= e((string)$patientCount) ?></h3>
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
                            <p class="text-muted mb-1 small text-uppercase fw-semibold">Surveys</p>
                            <h3 class="mb-0 fw-bold"><?= e((string)$surveyCount) ?></h3>
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
                            <h3 class="mb-0 fw-bold"><?= e((string)$pendingReviews) ?></h3>
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
                            <h3 class="mb-0 fw-bold text-danger"><?= e((string)$flaggedCount) ?></h3>
                        </div>
                        <div class="icon bg-danger bg-opacity-10 text-danger"><i class="bi bi-flag"></i></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3">
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white border-0 py-3 d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Recent Survey Results</h5>
                    <a href="<?= e(url('nurse/survey_results.php')) ?>" class="btn btn-sm btn-primary">View All</a>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Survey</th>
                                    <th>Patient</th>
                                    <th>Conducted By</th>
                                    <th>Status</th>
                                    <th>Date</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!$results): ?>
                                    <tr><td colspan="5" class="text-center text-muted py-4">No survey results yet.</td></tr>
                                <?php else: ?>
                                    <?php foreach ($results as $r): ?>
                                        <tr>
                                            <td><?= e($r['survey_name']) ?></td>
                                            <td><?= e($r['patient_name']) ?></td>
                                            <td class="small"><?= e($r['conducted_by']) ?></td>
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

        <div class="col-lg-4">
            <div class="card border-0 shadow-sm mb-3">
                <div class="card-header bg-white border-0 py-3">
                    <h5 class="mb-0">District Info</h5>
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
                    <div class="mb-2">
                        <div class="small text-muted">DPWH Accounts</div>
                        <div><?= e((string)$dpwhCount) ?></div>
                    </div>
                </div>
            </div>

            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white border-0 py-3">
                    <h5 class="mb-0">Quick Actions</h5>
                </div>
                <div class="card-body d-grid gap-2">
                    <a href="<?= e(url('nurse/patients.php')) ?>" class="btn btn-outline-primary text-start">
                        <i class="bi bi-people me-2"></i> Manage Patients
                    </a>
                    <a href="<?= e(url('nurse/survey_results.php')) ?>" class="btn btn-outline-warning text-start">
                        <i class="bi bi-clipboard-data me-2"></i> Review Surveys
                    </a>
                    <a href="<?= e(url('nurse/dpwh_accounts.php')) ?>" class="btn btn-outline-success text-start">
                        <i class="bi bi-person-gear me-2"></i> DPWH Accounts
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
