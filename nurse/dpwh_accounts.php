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
$audit = new AuditLogger($pdo);
$districtId = (int)($_SESSION['district_id'] ?? 0);

$stmt = $pdo->prepare("
    SELECT user_id, username, full_name, email, contact_number, status, force_password_change, last_login, created_at
    FROM users
    WHERE district_id = :district_id AND role = 'dpwh'
    ORDER BY status DESC, full_name
");
$stmt->execute([':district_id' => $districtId]);
$dpwhAccounts = $stmt->fetchAll();

$pageTitle = 'DPWH Accounts';
require_once __DIR__ . '/../includes/sidebar.php';
?>

<div class="main-content">
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 gap-2">
        <div>
            <h2 class="mb-0">DPWH Accounts</h2>
            <p class="text-muted mb-0">Data Processors / Health Workers in your district</p>
        </div>
        <a href="<?= e(url('nurse/dpwh_form.php')) ?>" class="btn btn-success">
            <i class="bi bi-person-plus me-1"></i> Create DPWH Account
        </a>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Name</th>
                            <th>Username</th>
                            <th>Contact</th>
                            <th>Status</th>
                            <th>Password Change</th>
                            <th>Last Login</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!$dpwhAccounts): ?>
                            <tr><td colspan="6" class="text-center text-muted py-4">No DPWH accounts found.</td></tr>
                        <?php else: ?>
                            <?php foreach ($dpwhAccounts as $dpwh): ?>
                                <tr>
                                    <td>
                                        <div class="d-flex align-items-center gap-2">
                                            <div class="avatar bg-success text-white rounded-circle d-flex align-items-center justify-content-center" style="width:36px;height:36px;font-size:0.9rem;">
                                                <?= e(strtoupper(mb_substr($dpwh['full_name'], 0, 1))) ?>
                                            </div>
                                            <span class="fw-semibold"><?= e($dpwh['full_name']) ?></span>
                                        </div>
                                    </td>
                                    <td><?= e($dpwh['username']) ?></td>
                                    <td>
                                        <div class="small"><?= e($dpwh['contact_number'] ?? '') ?></div>
                                        <div class="small text-muted"><?= e($dpwh['email'] ?? '') ?></div>
                                    </td>
                                    <td>
                                        <span class="badge bg-<?= $dpwh['status'] === 'active' ? 'success' : 'secondary' ?>">
                                            <?= e(ucfirst($dpwh['status'])) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if ($dpwh['force_password_change']): ?>
                                            <span class="badge bg-warning">Required</span>
                                        <?php else: ?>
                                            <span class="badge bg-success">Done</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="small text-muted"><?= e($dpwh['last_login'] ? format_datetime($dpwh['last_login']) : 'Never') ?></td>
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
