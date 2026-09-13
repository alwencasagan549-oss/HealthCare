<?php
declare(strict_types=1);

// ============================================================
// Change Password - Forced for DPWH first login
// ============================================================

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/middleware.php';
require_once __DIR__ . '/includes/audit.php';

Middleware::authenticated();

$pdo = Database::getConnection();
$auth = new Auth($pdo);
$audit = new AuditLogger($pdo);

$userId = (int)($_SESSION['user_id'] ?? 0);
$errors = [];
$success = '';

// Enforce DPWH first login
if (is_dpwh() && empty($_SESSION['force_password_change'])) {
    redirect(url('dpwh/dashboard.php'));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $currentPassword = (string)($_POST['current_password'] ?? '');
    $newPassword = (string)($_POST['new_password'] ?? '');
    $confirmPassword = (string)($_POST['confirm_password'] ?? '');
    $csrfToken = (string)($_POST['csrf_token'] ?? '');

    if (!verify_csrf($csrfToken)) {
        $errors[] = 'Invalid request. Please try again.';
    } elseif (empty($currentPassword) || empty($newPassword) || empty($confirmPassword)) {
        $errors[] = 'All fields are required.';
    } elseif ($newPassword !== $confirmPassword) {
        $errors[] = 'New passwords do not match.';
    } else {
        $validationErrors = validate_password($newPassword);
        if (!empty($validationErrors)) {
            $errors = array_merge($errors, $validationErrors);
        }

        // Fetch current password hash
        $stmt = $pdo->prepare("SELECT password_hash FROM users WHERE user_id = :user_id LIMIT 1");
        $stmt->execute([':user_id' => $userId]);
        $user = $stmt->fetch();

        if (!$user || !password_verify($currentPassword, $user['password_hash'])) {
            $errors[] = 'Current password is incorrect.';
        }

        if (empty($errors)) {
            $newHash = password_hash($newPassword, PASSWORD_BCRYPT, ['cost' => BCRYPT_COST]);
            $auth->updatePassword($userId, $newHash);

            // Clear force password change flag
            $stmt = $pdo->prepare("UPDATE users SET force_password_change = 0 WHERE user_id = :user_id");
            $stmt->execute([':user_id' => $userId]);

            unset($_SESSION['force_password_change']);

            $audit->log('UPDATE', 'users', (string)$userId, null, ['force_password_change' => 0], $userId);

            $success = 'Password changed successfully. Redirecting...';

            echo '<div class="position-fixed top-0 end-0 p-3" style="z-index: 1050">
                    <div class="toast show align-items-center text-bg-success border-0" role="alert">
                        <div class="d-flex">
                            <div class="toast-body">' . e($success) . '</div>
                            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
                        </div>
                    </div>
                  </div>';

            echo '<script>setTimeout(function(){ window.location.href = ' . json_encode(url('dpwh/dashboard.php')) . '; }, 1500);</script>';
        }
    }
}

$pageTitle = 'Change Password';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($pageTitle . ' - ' . APP_SHORT_NAME) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Figtree:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="<?= e(asset_path('assets/css/app.css')) ?>" rel="stylesheet">
</head>
<body>
    <div class="auth-wrapper">
        <div class="auth-card">
            <div class="auth-header">
                <div class="brand-logo">
                    <i class="bi bi-shield-lock"></i>
                </div>
                <h1 class="h4 fw-bold mt-2 mb-1">Change Your Password</h1>
                <p class="text-muted mb-0">You must change your password before continuing.</p>
            </div>

            <div class="auth-body">
                <?php if (!empty($errors)): ?>
                    <div class="alert alert-danger" role="alert" tabindex="-1" aria-labelledby="password-errors">
                        <p id="password-errors" class="mb-2 fw-semibold">Please fix the following:</p>
                        <ul class="mb-0">
                            <?php foreach ($errors as $err): ?>
                                <li><?= e($err) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <form method="POST" action="" novalidate>
                    <?= csrf_field() ?>

                    <div class="mb-3">
                        <label for="current_password" class="form-label">Current Password</label>
                        <input type="password"
                               class="form-control"
                               id="current_password"
                               name="current_password"
                               required
                               autocomplete="current-password"
                               aria-describedby="current-password-help">
                        <div id="current-password-help" class="form-text">Enter your existing password.</div>
                    </div>

                    <div class="mb-3">
                        <label for="new_password" class="form-label">New Password</label>
                        <input type="password"
                               class="form-control"
                               id="new_password"
                               name="new_password"
                               required
                               autocomplete="new-password"
                               aria-describedby="new-password-help">
                        <div id="new-password-help" class="form-text">
                            Use at least 8 characters, including uppercase, lowercase, number, and special character.
                        </div>
                    </div>

                    <div class="mb-4">
                        <label for="confirm_password" class="form-label">Confirm New Password</label>
                        <input type="password"
                               class="form-control"
                               id="confirm_password"
                               name="confirm_password"
                               required
                               autocomplete="new-password"
                               aria-describedby="confirm-password-help">
                        <div id="confirm-password-help" class="form-text">Reenter your new password.</div>
                    </div>

                    <button type="submit" class="btn btn-primary w-100 py-2">
                        <i class="bi bi-check-circle me-2" aria-hidden="true"></i>Update Password
                    </button>
                </form>
            </div>
        </div>
    </div>

    <footer class="site-footer">
        <small>&copy; <?= e(date('Y')) ?> <?= e(APP_SHORT_NAME) ?>. Authorized use only.</small>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
