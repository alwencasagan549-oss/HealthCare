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

$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$limit = DEFAULT_PAGE_LIMIT;
$offset = ($page - 1) * $limit;

$totalStmt = $pdo->query("SELECT COUNT(*) FROM audit_logs");
$total = (int)$totalStmt->fetchColumn();
$pagination = paginate($total, $page, $limit);

$stmt = $pdo->prepare("
    SELECT al.log_id, al.action, al.table_affected, al.record_id, al.ip_address, al.created_at,
           u.username, u.full_name, u.role
    FROM audit_logs al
    LEFT JOIN users u ON u.user_id = al.user_id
    ORDER BY al.created_at DESC
    LIMIT :limit OFFSET :offset
");
$stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$logs = $stmt->fetchAll();

$pageTitle = 'Audit Logs';
?>

<div class="main-content">
    <div class="mb-4">
        <h2 class="mb-0">Audit Logs</h2>
        <p class="text-muted mb-0">System activity and change history</p>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Date/Time</th>
                            <th>User</th>
                            <th>Action</th>
                            <th>Table</th>
                            <th>Record ID</th>
                            <th>IP Address</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!$logs): ?>
                            <tr><td colspan="6" class="text-center text-muted py-4">No audit logs found.</td></tr>
                        <?php else: ?>
                            <?php foreach ($logs as $log): ?>
                                <tr>
                                    <td class="small text-muted"><?= e(format_datetime($log['created_at'])) ?></td>
                                    <td>
                                        <?php if ($log['username']): ?>
                                            <div class="fw-semibold"><?= e($log['full_name']) ?></div>
                                            <div class="small text-muted">@<?= e($log['username']) ?></div>
                                        <?php else: ?>
                                            <span class="text-muted">System</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="badge bg-<?= match($log['action']) {
                                            'CREATE' => 'success',
                                            'UPDATE' => 'primary',
                                            'DELETE' => 'danger',
                                            'LOGIN' => 'info',
                                            'LOGOUT' => 'secondary',
                                            default => 'light text-dark'
                                        } ?>">
                                            <?= e($log['action']) ?>
                                        </span>
                                    </td>
                                    <td><?= e($log['table_affected'] ?? '-') ?></td>
                                    <td class="small font-monospace"><?= e($log['record_id'] ?? '-') ?></td>
                                    <td class="small font-monospace"><?= e($log['ip_address'] ?? '-') ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <?php if ($pagination['total_pages'] > 1): ?>
        <nav class="mt-3" aria-label="Page navigation">
            <ul class="pagination">
                <li class="page-item <?= $pagination['has_prev'] ? '' : 'disabled' ?>">
                    <a class="page-link" href="?page=<?= $pagination['prev_page'] ?>">Previous</a>
                </li>
                <?php for ($i = max(1, $page - 2); $i <= min($pagination['total_pages'], $page + 2); $i++): ?>
                    <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                        <a class="page-link" href="?page=<?= $i ?>"><?= $i ?></a>
                    </li>
                <?php endfor; ?>
                <li class="page-item <?= $pagination['has_next'] ? '' : 'disabled' ?>">
                    <a class="page-link" href="?page=<?= $pagination['next_page'] ?>">Next</a>
                </li>
            </ul>
        </nav>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
