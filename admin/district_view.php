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

$districtId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($districtId <= 0) {
    redirect(url('admin/districts.php'));
}

$stmt = $pdo->prepare("SELECT * FROM districts WHERE district_id = :id LIMIT 1");
$stmt->execute([':id' => $districtId]);
$district = $stmt->fetch();

if (!$district) {
    redirect(url('admin/districts.php'));
}

// Nurse
$stmtNurse = $pdo->prepare("
    SELECT user_id, username, full_name, email, contact_number, last_login
    FROM users
    WHERE district_id = :district_id AND role = 'nurse' AND status = 'active'
    LIMIT 1
");
$stmtNurse->execute([':district_id' => $districtId]);
$nurse = $stmtNurse->fetch();

// Patient count
$patientCount = (int)$pdo->prepare("SELECT COUNT(*) FROM patients WHERE district_id = :district_id AND status = 'active'")
    ->execute([':district_id' => $districtId]) ? (int)$pdo->query("SELECT COUNT(*) FROM patients WHERE district_id = {$districtId} AND status = 'active'")->fetchColumn() : 0;

// Survey count
$surveyCount = (int)$pdo->query("SELECT COUNT(*) FROM risk_assessments WHERE district_id = {$districtId}")->fetchColumn();

// DPWH count
$dpwhCount = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE district_id = {$districtId} AND role = 'dpwh' AND status = 'active'")->fetchColumn();

// Recent survey results
$stmtResults = $pdo->prepare("
    SELECT ra.assessment_id, ra.status, ra.created_at, ra.assessment_date,
           CONCAT(p.first_name, ' ', p.last_name) AS patient_name,
           CONCAT(u.full_name, ' (', u.username, ')') AS conducted_by
    FROM risk_assessments ra
    JOIN patients p ON p.patient_id = ra.patient_id
    JOIN users u ON u.user_id = ra.conducted_by
    WHERE ra.district_id = :district_id
    ORDER BY ra.assessment_date DESC
    LIMIT 10
");
$stmtResults->execute([':district_id' => $districtId]);
$results = $stmtResults->fetchAll();

$pageTitle = $district['district_name'];
?>

<div class="main-content">
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 gap-2">
        <div>
            <nav aria-label="breadcrumb" class="mb-2">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="<?= e(url('admin/dashboard.php')) ?>">Dashboard</a></li>
                    <li class="breadcrumb-item"><a href="<?= e(url('admin/districts.php')) ?>">Districts</a></li>
                    <li class="breadcrumb-item active" aria-current="page"><?= e($district['district_name']) ?></li>
                </ol>
            </nav>
            <h2 class="mb-0"><?= e($district['district_name']) ?></h2>
            <p class="text-muted mb-0"><?= e($district['address'] ?? '') ?></p>
        </div>
        <div class="d-flex gap-2">
            <a href="<?= e(url('admin/district_form.php?id=' . $district['district_id'])) ?>" class="btn btn-primary">
                <i class="bi bi-pencil me-1"></i> Edit
            </a>
            <?php if (!$nurse): ?>
                <a href="<?= e(url('admin/assign_nurse.php?district_id=' . $district['district_id'])) ?>" class="btn btn-success">
                    <i class="bi bi-person-plus me-1"></i> Assign Nurse
                </a>
            <?php endif; ?>
        </div>
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
                            <p class="text-muted mb-1 small text-uppercase fw-semibold">Assessments</p>
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
                            <p class="text-muted mb-1 small text-uppercase fw-semibold">DPWH</p>
                            <h3 class="mb-0 fw-bold"><?= e((string)$dpwhCount) ?></h3>
                        </div>
                        <div class="icon bg-success bg-opacity-10 text-success"><i class="bi bi-person-gear"></i></div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-sm-6">
            <div class="card card-stat h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <p class="text-muted mb-1 small text-uppercase fw-semibold">Status</p>
                            <h3 class="mb-0 fw-bold text-<?= $district['status'] === 'active' ? 'success' : 'secondary' ?>">
                                <?= e(ucfirst($district['status'])) ?>
                            </h3>
                        </div>
                        <div class="icon bg-<?= $district['status'] === 'active' ? 'success' : 'secondary' ?> bg-opacity-10 text-<?= $district['status'] === 'active' ? 'success' : 'secondary' ?>">
                            <i class="bi bi-<?= $district['status'] === 'active' ? 'check-circle' : 'x-circle' ?>"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3">
        <div class="col-lg-4">
            <div class="card border-0 shadow-sm mb-3">
                <div class="card-header bg-white border-0 py-3">
                    <h5 class="mb-0">Assigned Nurse</h5>
                </div>
                <div class="card-body">
                    <?php if ($nurse): ?>
                        <div class="d-flex align-items-center gap-3">
                            <div class="avatar bg-primary text-white rounded-circle d-flex align-items-center justify-content-center" style="width:48px;height:48px;font-size:1.25rem;">
                                <?= e(strtoupper(mb_substr($nurse['full_name'], 0, 1))) ?>
                            </div>
                            <div>
                                <div class="fw-semibold"><?= e($nurse['full_name']) ?></div>
                                <div class="small text-muted">@<?= e($nurse['username']) ?></div>
                                <div class="small text-muted"><?= e($nurse['email'] ?? '') ?></div>
                            </div>
                        </div>
                    <?php else: ?>
                        <p class="text-muted mb-2">No nurse assigned to this district.</p>
                        <a href="<?= e(url('admin/assign_nurse.php?district_id=' . $district['district_id'])) ?>" class="btn btn-sm btn-primary">
                            <i class="bi bi-person-plus me-1"></i> Assign Nurse
                        </a>
                    <?php endif; ?>
                </div>
            </div>

            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white border-0 py-3">
                    <h5 class="mb-0">Contact Information</h5>
                </div>
                <div class="card-body">
                    <div class="mb-2">
                        <div class="small text-muted">Code</div>
                        <div class="fw-semibold"><?= e($district['district_code']) ?></div>
                    </div>
                    <div class="mb-2">
                        <div class="small text-muted">Contact</div>
                        <div><?= e($district['contact_number'] ?? 'N/A') ?></div>
                    </div>
                    <div class="mb-2">
                        <div class="small text-muted">Email</div>
                        <div><?= e($district['email'] ?? 'N/A') ?></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-8">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white border-0 py-3 d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Recent Assessments</h5>
                    <span class="badge bg-light text-dark border">Last 10</span>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Patient</th>
                                    <th>Conducted By</th>
                                    <th>Status</th>
                                    <th>Date</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!$results): ?>
                                    <tr><td colspan="4" class="text-center text-muted py-4">No assessments yet.</td></tr>
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
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
