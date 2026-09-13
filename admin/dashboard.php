<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/middleware.php';
require_once __DIR__ . '/../includes/audit.php';

Middleware::admin();

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';

$pdo = Database::getConnection();

$totalDistricts = 0;
$totalNurses    = 0;
$totalPatients  = 0;
$totalSurveys   = 0;
$totalDpwh      = 0;
$pendingReviews = 0;
$totalAssessments = 0;
$districts = [];

$pageTitle = 'Dashboard';
$pageStyles = [];
$pageScripts = [];
$dashboardQueryError = null;

try {
    $totalDistricts   = (int)$pdo->query("SELECT COUNT(*) FROM districts WHERE status = 'active'")->fetchColumn();
} catch (Throwable $e) {
    error_log('Dashboard stat error (totalDistricts): ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
}

try {
    $totalNurses      = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role = 'nurse' AND status = 'active'")->fetchColumn();
} catch (Throwable $e) {
    error_log('Dashboard stat error (totalNurses): ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
}

try {
    $totalPatients    = (int)$pdo->query("SELECT COUNT(*) FROM patients WHERE status = 'active'")->fetchColumn();
} catch (Throwable $e) {
    error_log('Dashboard stat error (totalPatients): ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
}

try {
    $totalSurveys     = (int)$pdo->query("SELECT COUNT(*) FROM surveys WHERE is_active = TRUE")->fetchColumn();
} catch (Throwable $e) {
    error_log('Dashboard stat error (totalSurveys): ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
}

try {
    $totalDpwh        = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role = 'dpwh' AND status = 'active'")->fetchColumn();
} catch (Throwable $e) {
    error_log('Dashboard stat error (totalDpwh): ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
}

try {
    $pendingReviews   = (int)$pdo->query("SELECT COUNT(*) FROM risk_assessments WHERE status = 'pending'")->fetchColumn();
} catch (Throwable $e) {
    error_log('Dashboard stat error (pendingReviews): ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
}

try {
    $totalAssessments = (int)$pdo->query("SELECT COUNT(*) FROM risk_assessments")->fetchColumn();
} catch (Throwable $e) {
    error_log('Dashboard stat error (totalAssessments): ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
}

try {
    $stmt = $pdo->query("
        SELECT d.district_id, d.district_name, d.district_code,
               COUNT(DISTINCT p.patient_id) AS patient_count,
               COUNT(DISTINCT u.user_id) AS nurse_count,
               COUNT(DISTINCT dp.user_id) AS dpwh_count
        FROM districts d
        LEFT JOIN patients p ON p.district_id = d.district_id AND p.status = 'active'
        LEFT JOIN users u ON u.district_id = d.district_id AND u.role = 'nurse' AND u.status = 'active'
        LEFT JOIN users dp ON dp.district_id = d.district_id AND dp.role = 'dpwh' AND dp.status = 'active'
        GROUP BY d.district_id
        ORDER BY d.district_name
    ");
    $districts = $stmt->fetchAll();
} catch (Throwable $e) {
    $dashboardQueryError = $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine();
    error_log('Dashboard district breakdown error: ' . $dashboardQueryError);
}
?>

<div class="main-content">
    <?php if ($dashboardQueryError): ?>
        <div class="alert alert-danger">Dashboard query failed: <?= e($dashboardQueryError) ?></div>
    <?php endif; ?>
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 gap-2">
        <div>
            <h2 class="dashboard-section-title">Dashboard</h2>
            <p class="dashboard-section-subtitle">City-wide health management overview</p>
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
                            <p class="text-muted mb-1 small text-uppercase fw-semibold">Districts</p>
                            <h3 class="mb-0 dashboard-stat-value"><?= e((string)$totalDistricts) ?></h3>
                        </div>
                        <div class="icon bg-primary bg-opacity-10 text-primary">
                            <i class="bi bi-geo-alt"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-sm-6">
            <div class="card card-stat h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <p class="text-muted mb-1 small text-uppercase fw-semibold">Nurses</p>
                            <h3 class="mb-0 dashboard-stat-value"><?= e((string)$totalNurses) ?></h3>
                        </div>
                        <div class="icon bg-success bg-opacity-10 text-success">
                            <i class="bi bi-person-vcard"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-sm-6">
            <div class="card card-stat h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <p class="text-muted mb-1 small text-uppercase fw-semibold">Patients</p>
                            <h3 class="mb-0 dashboard-stat-value"><?= e((string)$totalPatients) ?></h3>
                        </div>
                        <div class="icon bg-info bg-opacity-10 text-info">
                            <i class="bi bi-people"></i>
                        </div>
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
                        <div class="icon bg-warning bg-opacity-10 text-warning">
                            <i class="bi bi-clipboard-data"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3">
        <div class="col-lg-8">
            <div class="dashboard-card">
                <div class="dashboard-card-header">
                    <h5 class="mb-0">District Overview</h5>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table dashboard-table table-hover align-middle mb-0">
                            <thead>
                                <tr>
                                    <th>District</th>
                                    <th>Code</th>
                                    <th class="text-center">Nurses</th>
                                    <th class="text-center">Patients</th>
                                    <th class="text-center">DPWH</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!$districts): ?>
                                    <tr><td colspan="5" class="dashboard-empty">No districts found.</td></tr>
                                <?php else: ?>
                                    <?php foreach ($districts as $d): ?>
                                        <tr>
                                            <td class="fw-semibold"><?= e($d['district_name']) ?></td>
                                            <td><span class="badge bg-light text-dark border"><?= e($d['district_code']) ?></span></td>
                                            <td class="text-center"><?= e((string)$d['nurse_count']) ?></td>
                                            <td class="text-center"><?= e((string)$d['patient_count']) ?></td>
                                            <td class="text-center"><?= e((string)$d['dpwh_count']) ?></td>
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
                    <h5 class="mb-0">Quick Stats</h5>
                </div>
                <div class="dashboard-card-body">
                    <div class="d-flex justify-content-between py-2 border-bottom">
                        <span class="text-muted">Total Assessments</span>
                        <span class="fw-semibold"><?= e((string)$totalAssessments) ?></span>
                    </div>
                    <div class="d-flex justify-content-between py-2 border-bottom">
                        <span class="text-muted">DPWH Accounts</span>
                        <span class="fw-semibold"><?= e((string)$totalDpwh) ?></span>
                    </div>
                    <div class="d-flex justify-content-between py-2">
                        <span class="text-muted">Pending Reviews</span>
                        <span class="fw-semibold text-warning"><?= e((string)$pendingReviews) ?></span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
