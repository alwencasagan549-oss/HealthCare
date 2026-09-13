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

$stmt = $pdo->query("
    SELECT u.user_id, u.username, u.full_name, u.email, u.contact_number,
           u.status, u.last_login, u.created_at,
           d.district_name, d.district_code
    FROM users u
    LEFT JOIN districts d ON d.district_id = u.district_id
    WHERE u.role = 'nurse'
    ORDER BY u.status DESC, u.full_name
");
$nurses = $stmt->fetchAll();

$pageTitle = 'Nurses';
?>

<div class="main-content">
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 gap-2">
        <div>
            <h2 class="mb-0">Nurses</h2>
            <p class="text-muted mb-0">District nurse accounts</p>
        </div>
        <a href="<?= e(url('admin/districts.php')) ?>" class="btn btn-primary">
            <i class="bi bi-geo-alt me-1"></i> Manage Districts
        </a>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Nurse</th>
                            <th>Username</th>
                            <th>District</th>
                            <th>Contact</th>
                            <th>Status</th>
                            <th>Last Login</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!$nurses): ?>
                            <tr><td colspan="6" class="text-center text-muted py-4">No nurses found.</td></tr>
                        <?php else: ?>
                            <?php foreach ($nurses as $n): ?>
                                <tr>
                                    <td>
                                        <div class="d-flex align-items-center gap-2">
                                            <div class="avatar bg-primary text-white rounded-circle d-flex align-items-center justify-content-center" style="width:36px;height:36px;font-size:0.9rem;">
                                                <?= e(strtoupper(mb_substr($n['full_name'], 0, 1))) ?>
                                            </div>
                                            <span class="fw-semibold"><?= e($n['full_name']) ?></span>
                                        </div>
                                    </td>
                                    <td><?= e($n['username']) ?></td>
                                    <td><?= e($n['district_name'] ?? 'Unassigned') ?></td>
                                    <td>
                                        <div class="small"><?= e($n['contact_number'] ?? '') ?></div>
                                        <div class="small text-muted"><?= e($n['email'] ?? '') ?></div>
                                    </td>
                                    <td>
                                        <span class="badge bg-<?= $n['status'] === 'active' ? 'success' : 'secondary' ?>">
                                            <?= e(ucfirst($n['status'])) ?>
                                        </span>
                                    </td>
                                    <td class="small text-muted"><?= e($n['last_login'] ? format_datetime($n['last_login']) : 'Never') ?></td>
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
