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
$audit = new AuditLogger($pdo);

$pageTitle = 'Districts';
$districtQueryError = null;
$districts = [];

try {
    // List districts with counts
    $stmt = $pdo->query("
        SELECT d.district_id, d.district_name, d.district_code, d.address, d.contact_number,
               d.email, d.status, d.created_at,
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
    $districtQueryError = $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine();
    error_log('Districts query error: ' . $districtQueryError);
}
?>

<div class="main-content">
    <?php if ($districtQueryError): ?>
        <div class="alert alert-danger">Districts list failed: <?= e($districtQueryError) ?></div>
    <?php endif; ?>
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 gap-2">
        <div>
            <h2 class="mb-0">Districts</h2>
            <p class="text-muted mb-0">Manage District Health Centers</p>
        </div>
        <a href="<?= e(url('admin/district_form.php')) ?>" class="btn btn-primary">
            <i class="bi bi-plus-circle me-1"></i> Add District
        </a>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>District</th>
                            <th>Code</th>
                            <th>Contact</th>
                            <th class="text-center">Patients</th>
                            <th class="text-center">Nurse</th>
                            <th class="text-center">DPWH</th>
                            <th>Status</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!$districts): ?>
                            <tr><td colspan="8" class="text-center text-muted py-4">No districts found.</td></tr>
                        <?php else: ?>
                            <?php foreach ($districts as $d): ?>
                                <tr>
                                    <td>
                                        <div class="fw-semibold"><?= e($d['district_name']) ?></div>
                                        <div class="small text-muted"><?= e($d['address'] ?? '') ?></div>
                                    </td>
                                    <td><span class="badge bg-light text-dark border"><?= e($d['district_code']) ?></span></td>
                                    <td>
                                        <div class="small"><?= e($d['contact_number'] ?? '') ?></div>
                                        <div class="small text-muted"><?= e($d['email'] ?? '') ?></div>
                                    </td>
                                    <td class="text-center"><?= e((string)$d['patient_count']) ?></td>
                                    <td class="text-center"><?= e((string)$d['nurse_count']) ?></td>
                                    <td class="text-center"><?= e((string)$d['dpwh_count']) ?></td>
                                    <td>
                                        <span class="badge bg-<?= $d['status'] === 'active' ? 'success' : 'secondary' ?>">
                                            <?= e(ucfirst($d['status'])) ?>
                                        </span>
                                    </td>
                                    <td class="text-end">
                                        <a href="<?= e(url('admin/district_view.php?id=' . $d['district_id'])) ?>" class="btn btn-sm btn-outline-secondary">
                                            <i class="bi bi-eye"></i>
                                        </a>
                                        <a href="<?= e(url('admin/district_form.php?id=' . $d['district_id'])) ?>" class="btn btn-sm btn-outline-primary">
                                            <i class="bi bi-pencil"></i>
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
