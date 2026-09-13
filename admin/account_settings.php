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
$auth = new Auth($pdo);
$audit = new AuditLogger($pdo);

$currentAdminId = (int)($_SESSION['user_id'] ?? 0);
$errors = [];
$success = flash('success');

// ---------------------------------------------------------------
// Section A — Admin: Update own username
// ---------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form_type'] ?? '') === 'admin_username') {
    $newUsername = trim((string)($_POST['new_username'] ?? ''));
    $csrfToken = (string)($_POST['csrf_token'] ?? '');

    if (!verify_csrf($csrfToken)) {
        $errors[] = 'Invalid request. Please try again.';
    } elseif ($newUsername === '') {
        $errors[] = 'Username cannot be empty.';
    } elseif (strlen($newUsername) < 3) {
        $errors[] = 'Username must be at least 3 characters.';
    } else {
        $stmt = $pdo->prepare("
            UPDATE users
            SET username = :username, updated_at = NOW()
            WHERE user_id = :user_id
        ");
        $stmt->execute([
            ':username' => $newUsername,
            ':user_id'  => $currentAdminId,
        ]);

        $_SESSION['username'] = $newUsername;

        $audit->log('UPDATE', 'users', (string)$currentAdminId, null, ['username' => $newUsername], $currentAdminId);

        $success = 'Username updated successfully.';
    }
}

// ---------------------------------------------------------------
// Section A — Admin: Update own password
// ---------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form_type'] ?? '') === 'admin_password') {
    $currentPassword = (string)($_POST['current_password'] ?? '');
    $newPassword = (string)($_POST['new_password'] ?? '');
    $confirmPassword = (string)($_POST['confirm_password'] ?? '');
    $csrfToken = (string)($_POST['csrf_token'] ?? '');

    if (!verify_csrf($csrfToken)) {
        $errors[] = 'Invalid request. Please try again.';
    } elseif (empty($currentPassword) || empty($newPassword) || empty($confirmPassword)) {
        $errors[] = 'All password fields are required.';
    } elseif ($newPassword !== $confirmPassword) {
        $errors[] = 'New passwords do not match.';
    } else {
        $validationErrors = validate_password($newPassword);
        if (!empty($validationErrors)) {
            $errors = array_merge($errors, $validationErrors);
        }

        $stmt = $pdo->prepare("SELECT password_hash FROM users WHERE user_id = :user_id LIMIT 1");
        $stmt->execute([':user_id' => $currentAdminId]);
        $user = $stmt->fetch();

        if (!$user || !password_verify($currentPassword, $user['password_hash'])) {
            $errors[] = 'Current password is incorrect.';
        }

        if (empty($errors)) {
            $newHash = password_hash($newPassword, PASSWORD_BCRYPT, ['cost' => BCRYPT_COST]);
            $auth->updatePassword($currentAdminId, $newHash);

            $audit->log('UPDATE', 'users', (string)$currentAdminId, null, ['password_hash' => '***'], $currentAdminId);

            $success = 'Password updated successfully.';
        }
    }
}

// ---------------------------------------------------------------
// Section B/C — Admin: Update nurse/DPWH username
// ---------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form_type'] ?? '') === 'user_username') {
    $targetUserId = (int)($_POST['target_user_id'] ?? 0);
    $targetRole = (string)($_POST['target_role'] ?? '');
    $newUsername = trim((string)($_POST['new_username'] ?? ''));
    $csrfToken = (string)($_POST['csrf_token'] ?? '');

    if (!in_array($targetRole, ['nurse', 'dpwh'], true)) {
        $errors[] = 'Invalid user role.';
    } elseif ($targetUserId === 0) {
        $errors[] = 'Invalid user.';
    } elseif (!verify_csrf($csrfToken)) {
        $errors[] = 'Invalid request. Please try again.';
    } elseif ($newUsername === '') {
        $errors[] = 'Username cannot be empty.';
    } elseif (strlen($newUsername) < 3) {
        $errors[] = 'Username must be at least 3 characters.';
    } else {
        $stmt = $pdo->prepare("
            UPDATE users
            SET username = :username, updated_at = NOW()
            WHERE user_id = :user_id AND role = :role
        ");
        $stmt->execute([
            ':username' => $newUsername,
            ':user_id'  => $targetUserId,
            ':role'     => $targetRole,
        ]);

        $audit->log('UPDATE', 'users', (string)$targetUserId, null, ['username' => $newUsername, 'role' => $targetRole], $currentAdminId);

        $success = ucfirst($targetRole) . ' username updated successfully.';
    }
}

// ---------------------------------------------------------------
// Section B/C — Admin: Reset nurse/DPWH password
// ---------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form_type'] ?? '') === 'user_password') {
    $targetUserId = (int)($_POST['target_user_id'] ?? 0);
    $targetRole = (string)($_POST['target_role'] ?? '');
    $newPassword = (string)($_POST['new_password'] ?? '');
    $csrfToken = (string)($_POST['csrf_token'] ?? '');

    if (!in_array($targetRole, ['nurse', 'dpwh'], true)) {
        $errors[] = 'Invalid user role.';
    } elseif ($targetUserId === 0) {
        $errors[] = 'Invalid user.';
    } elseif (empty($newPassword)) {
        $errors[] = 'New password is required.';
    } elseif (!verify_csrf($csrfToken)) {
        $errors[] = 'Invalid request. Please try again.';
    } else {
        $validationErrors = validate_password($newPassword);
        if (!empty($validationErrors)) {
            $errors = array_merge($errors, $validationErrors);
        }

        if (empty($errors)) {
            $passwordHash = password_hash($newPassword, PASSWORD_BCRYPT, ['cost' => BCRYPT_COST]);

            $stmt = $pdo->prepare("
                UPDATE users
                SET password_hash = :hash, force_password_change = TRUE, updated_at = NOW()
                WHERE user_id = :user_id AND role = :role
            ");
            $stmt->execute([
                ':hash'  => $passwordHash,
                ':user_id' => $targetUserId,
                ':role'  => $targetRole,
            ]);

            $audit->log('UPDATE', 'users', (string)$targetUserId, null, ['force_password_change' => TRUE, 'role' => $targetRole], $currentAdminId);

            $success = ucfirst($targetRole) . ' password updated successfully.';
        }
    }
}

// ---------------------------------------------------------------
// Load nurses and DPWH accounts
// ---------------------------------------------------------------
$nurses = [];
try {
    $stmt = $pdo->prepare("
        SELECT user_id, username, full_name, email, status, last_login, district_id
        FROM users
        WHERE role = 'nurse'
        ORDER BY full_name
    ");
    $stmt->execute();
    $nurses = $stmt->fetchAll();
} catch (Throwable $e) {
    error_log('Account settings nurses query error: ' . $e->getMessage());
}

$dpwhAccounts = [];
try {
    $stmt = $pdo->prepare("
        SELECT u.user_id, u.username, u.full_name, u.email, u.status, u.last_login, u.district_id,
               d.district_name, d.district_code
        FROM users u
        LEFT JOIN districts d ON d.district_id = u.district_id
        WHERE u.role = 'dpwh'
        ORDER BY u.full_name
    ");
    $stmt->execute();
    $dpwhAccounts = $stmt->fetchAll();
} catch (Throwable $e) {
    error_log('Account settings DPWH query error: ' . $e->getMessage());
}

$pageTitle = 'Account Settings';
$pageStyles = [];
$pageScripts = [];
?>

<div class="main-content">
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 gap-2">
        <div>
            <h2 class="mb-0">Account Settings</h2>
            <p class="text-muted mb-0">Manage your account and user credentials</p>
        </div>
    </div>

    <?php if ($success): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <?= e($success) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <?php if (!empty($errors)): ?>
        <div class="alert alert-danger" role="alert">
            <p class="mb-2 fw-semibold">Please fix the following:</p>
            <ul class="mb-0">
                <?php foreach ($errors as $err): ?>
                    <li><?= e($err) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <!-- Section A: My Account -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-white border-0 py-3">
            <h5 class="mb-0"><i class="bi bi-person-circle me-2"></i>My Account</h5>
        </div>
        <div class="card-body">
            <div class="row g-4">
                <div class="col-lg-6">
                    <h6 class="fw-semibold mb-3">Change Username</h6>
                    <form method="POST" action="" novalidate>
                        <?= csrf_field() ?>
                        <input type="hidden" name="form_type" value="admin_username">
                        <div class="mb-3">
                            <label for="new_username" class="form-label">New Username</label>
                            <input type="text"
                                   class="form-control"
                                   id="new_username"
                                   name="new_username"
                                   required
                                   minlength="3"
                                   value="<?= e(old('new_username', $_SESSION['username'] ?? '')) ?>">
                        </div>
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-check-circle me-1"></i> Update Username
                        </button>
                    </form>
                </div>

                <div class="col-lg-6">
                    <h6 class="fw-semibold mb-3">Change Password</h6>
                    <form method="POST" action="" novalidate>
                        <?= csrf_field() ?>
                        <input type="hidden" name="form_type" value="admin_password">
                        <div class="mb-3">
                            <label for="current_password" class="form-label">Current Password</label>
                            <input type="password" class="form-control" id="current_password" name="current_password" required>
                        </div>
                        <div class="mb-3">
                            <label for="new_password" class="form-label">New Password</label>
                            <input type="password" class="form-control" id="new_password" name="new_password" required minlength="<?= PASSWORD_MIN_LENGTH ?>">
                        </div>
                        <div class="mb-3">
                            <label for="confirm_password" class="form-label">Confirm New Password</label>
                            <input type="password" class="form-control" id="confirm_password" name="confirm_password" required minlength="<?= PASSWORD_MIN_LENGTH ?>">
                        </div>
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-check-circle me-1"></i> Update Password
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Section B: Nurses -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-white border-0 py-3">
            <h5 class="mb-0"><i class="bi bi-person-vcard me-2"></i>Nurse Accounts</h5>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Full Name</th>
                            <th>Username</th>
                            <th>Email</th>
                            <th>Status</th>
                            <th>Last Login</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!$nurses): ?>
                            <tr><td colspan="6" class="text-center text-muted py-4">No nurse accounts found.</td></tr>
                        <?php else: ?>
                            <?php foreach ($nurses as $n): ?>
                                <tr>
                                    <td class="fw-semibold"><?= e($n['full_name']) ?></td>
                                    <td><?= e($n['username']) ?></td>
                                    <td class="small text-muted"><?= e($n['email'] ?? '') ?></td>
                                    <td>
                                        <span class="badge bg-<?= $n['status'] === 'active' ? 'success' : 'secondary' ?>">
                                            <?= e(ucfirst($n['status'])) ?>
                                        </span>
                                    </td>
                                    <td class="small text-muted"><?= $n['last_login'] ? e(format_datetime($n['last_login'])) : 'Never' ?></td>
                                    <td class="text-end">
                                        <button type="button"
                                                class="btn btn-sm btn-outline-primary me-1"
                                                data-bs-toggle="modal"
                                                data-bs-target="#nurseUsernameModal<?= e((string)$n['user_id']) ?>">
                                            <i class="bi bi-pencil"></i>
                                        </button>
                                        <button type="button"
                                                class="btn btn-sm btn-outline-warning"
                                                data-bs-toggle="modal"
                                                data-bs-target="#nursePasswordModal<?= e((string)$n['user_id']) ?>">
                                            <i class="bi bi-key"></i>
                                        </button>
                                    </td>
                                </tr>

                                <!-- Username Modal -->
                                <div class="modal fade" id="nurseUsernameModal<?= e((string)$n['user_id']) ?>" tabindex="-1" aria-hidden="true">
                                    <div class="modal-dialog">
                                        <div class="modal-content">
                                            <div class="modal-header">
                                                <h5 class="modal-title">Change Nurse Username</h5>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                            </div>
                                            <form method="POST" action="" novalidate>
                                                <div class="modal-body">
                                                    <?= csrf_field() ?>
                                                    <input type="hidden" name="form_type" value="user_username">
                                                    <input type="hidden" name="target_user_id" value="<?= e((string)$n['user_id']) ?>">
                                                    <input type="hidden" name="target_role" value="nurse">
                                                    <div class="mb-3">
                                                        <label for="nurse_username_<?= e((string)$n['user_id']) ?>" class="form-label">Username</label>
                                                        <input type="text" class="form-control" id="nurse_username_<?= e((string)$n['user_id']) ?>" name="new_username" required minlength="3" value="<?= e($n['username']) ?>">
                                                    </div>
                                                </div>
                                                <div class="modal-footer">
                                                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                                                    <button type="submit" class="btn btn-primary">Save Username</button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                </div>

                                <!-- Password Modal -->
                                <div class="modal fade" id="nursePasswordModal<?= e((string)$n['user_id']) ?>" tabindex="-1" aria-hidden="true">
                                    <div class="modal-dialog">
                                        <div class="modal-content">
                                            <div class="modal-header">
                                                <h5 class="modal-title">Reset Nurse Password</h5>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                            </div>
                                            <form method="POST" action="" novalidate>
                                                <div class="modal-body">
                                                    <?= csrf_field() ?>
                                                    <input type="hidden" name="form_type" value="user_password">
                                                    <input type="hidden" name="target_user_id" value="<?= e((string)$n['user_id']) ?>">
                                                    <input type="hidden" name="target_role" value="nurse">
                                                    <p class="text-muted">Enter a new password for this nurse.</p>
                                                    <div class="mt-3">
                                                        <label for="nurse_password_<?= e((string)$n['user_id']) ?>" class="form-label">New Password</label>
                                                        <input type="password" class="form-control" id="nurse_password_<?= e((string)$n['user_id']) ?>" name="new_password" required minlength="<?= PASSWORD_MIN_LENGTH ?>">
                                                    </div>
                                                </div>
                                                <div class="modal-footer">
                                                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                                                    <button type="submit" class="btn btn-warning">Reset Password</button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Section C: DPWH Accounts -->
    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white border-0 py-3">
            <h5 class="mb-0"><i class="bi bi-person-gear me-2"></i>DPWH Accounts</h5>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Full Name</th>
                            <th>Username</th>
                            <th>District</th>
                            <th>Status</th>
                            <th>Last Login</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!$dpwhAccounts): ?>
                            <tr><td colspan="6" class="text-center text-muted py-4">No DPWH accounts found.</td></tr>
                        <?php else: ?>
                            <?php foreach ($dpwhAccounts as $d): ?>
                                <tr>
                                    <td class="fw-semibold"><?= e($d['full_name']) ?></td>
                                    <td><?= e($d['username']) ?></td>
                                    <td class="small text-muted"><?= e($d['district_name'] ?? '') ?> (<?= e($d['district_code'] ?? '') ?>)</td>
                                    <td>
                                        <span class="badge bg-<?= $d['status'] === 'active' ? 'success' : 'secondary' ?>">
                                            <?= e(ucfirst($d['status'])) ?>
                                        </span>
                                    </td>
                                    <td class="small text-muted"><?= $d['last_login'] ? e(format_datetime($d['last_login'])) : 'Never' ?></td>
                                    <td class="text-end">
                                        <button type="button"
                                                class="btn btn-sm btn-outline-primary me-1"
                                                data-bs-toggle="modal"
                                                data-bs-target="#dpwhUsernameModal<?= e((string)$d['user_id']) ?>">
                                            <i class="bi bi-pencil"></i>
                                        </button>
                                        <button type="button"
                                                class="btn btn-sm btn-outline-warning"
                                                data-bs-toggle="modal"
                                                data-bs-target="#dpwhPasswordModal<?= e((string)$d['user_id']) ?>">
                                            <i class="bi bi-key"></i>
                                        </button>
                                    </td>
                                </tr>

                                <!-- Username Modal -->
                                <div class="modal fade" id="dpwhUsernameModal<?= e((string)$d['user_id']) ?>" tabindex="-1" aria-hidden="true">
                                    <div class="modal-dialog">
                                        <div class="modal-content">
                                            <div class="modal-header">
                                                <h5 class="modal-title">Change DPWH Username</h5>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                            </div>
                                            <form method="POST" action="" novalidate>
                                                <div class="modal-body">
                                                    <?= csrf_field() ?>
                                                    <input type="hidden" name="form_type" value="user_username">
                                                    <input type="hidden" name="target_user_id" value="<?= e((string)$d['user_id']) ?>">
                                                    <input type="hidden" name="target_role" value="dpwh">
                                                    <div class="mb-3">
                                                        <label for="dpwh_username_<?= e((string)$d['user_id']) ?>" class="form-label">Username</label>
                                                        <input type="text" class="form-control" id="dpwh_username_<?= e((string)$d['user_id']) ?>" name="new_username" required minlength="3" value="<?= e($d['username']) ?>">
                                                    </div>
                                                </div>
                                                <div class="modal-footer">
                                                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                                                    <button type="submit" class="btn btn-primary">Save Username</button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                </div>

                                <!-- Password Modal -->
                                <div class="modal fade" id="dpwhPasswordModal<?= e((string)$d['user_id']) ?>" tabindex="-1" aria-hidden="true">
                                    <div class="modal-dialog">
                                        <div class="modal-content">
                                            <div class="modal-header">
                                                <h5 class="modal-title">Reset DPWH Password</h5>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                            </div>
                                            <form method="POST" action="" novalidate>
                                                <div class="modal-body">
                                                    <?= csrf_field() ?>
                                                    <input type="hidden" name="form_type" value="user_password">
                                                    <input type="hidden" name="target_user_id" value="<?= e((string)$d['user_id']) ?>">
                                                    <input type="hidden" name="target_role" value="dpwh">
                                                    <p class="text-muted">Enter a new password for this DPWH user.</p>
                                                    <div class="mt-3">
                                                        <label for="dpwh_password_<?= e((string)$d['user_id']) ?>" class="form-label">New Password</label>
                                                        <input type="password" class="form-control" id="dpwh_password_<?= e((string)$d['user_id']) ?>" name="new_password" required minlength="<?= PASSWORD_MIN_LENGTH ?>">
                                                    </div>
                                                </div>
                                                <div class="modal-footer">
                                                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                                                    <button type="submit" class="btn btn-warning">Reset Password</button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
