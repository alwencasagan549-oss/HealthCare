<?php
declare(strict_types=1);

// ============================================================
// Login Page
// ============================================================

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/middleware.php';
require_once __DIR__ . '/includes/audit.php';

if (session_status() === PHP_SESSION_NONE) {
    session_name(SESSION_NAME);
    session_start();
}

Middleware::guest();

$pdo = Database::getConnection();
$auth = new Auth($pdo);

$error = '';

if (isset($_GET['expired'])) {
    $error = 'Your session has expired. Please sign in again.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim((string)($_POST['username'] ?? ''));
    $password = (string)($_POST['password'] ?? '');
    $csrfToken = (string)($_POST['csrf_token'] ?? '');

    if (!verify_csrf($csrfToken)) {
        $error = 'Invalid request. Please try again.';
    } elseif (empty($username) || empty($password)) {
        $error = 'Please enter both username and password.';
    } elseif ($auth->isBlocked($username)) {
        $error = 'Too many failed login attempts. Please try again in 15 minutes.';
    } else {
        $user = $auth->attempt($username, $password);

        if ($user) {
            $auth->clearFailedAttempts($username);
            $auth->login($user);

            $audit = new AuditLogger($pdo);
            $audit->login($user['user_id']);

            // DPWH first login redirect
            if ($user['force_password_change']) {
                redirect(url('change_password.php'));
            }

            $dashboard = match ($user['role']) {
                'admin' => url('admin/dashboard.php'),
                'nurse' => url('nurse/dashboard.php'),
                'dpwh'  => url('dpwh/dashboard.php'),
                default => url('login.php'),
            };

            redirect($dashboard);
        } else {
            $auth->recordFailedAttempt($username);
            $error = 'Invalid username or password.';
        }
    }
}

$pageTitle = 'Login';
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
    <main class="auth-page">
        <div class="auth-brand">
            <div class="auth-brand-mark" aria-hidden="true">
                <i class="bi bi-heart-pulse"></i>
            </div>
            <h1 class="auth-brand-title"><?= e(APP_SHORT_NAME) ?></h1>
            <p class="auth-brand-subtitle">City Health Center health reporting</p>
        </div>

        <div class="auth-card">
            <div class="auth-header">
                <a class="auth-back" href="<?= e(url('index.php')) ?>">
                    <i class="bi bi-house-door" aria-hidden="true"></i>
                    <span>Back to home</span>
                </a>
                <h2 class="auth-header-title">Sign in</h2>
                <p class="auth-header-subtitle">Use your assigned account to continue.</p>
            </div>

            <div class="auth-body">
                <?php if ($error): ?>
                    <div class="alert alert-danger auth-alert" role="alert" tabindex="-1" aria-labelledby="login-error">
                        <p id="login-error" class="mb-0 fw-semibold">Sign-in failed</p>
                        <p class="mb-0"><?= e($error) ?></p>
                    </div>
                <?php endif; ?>

                <form method="POST" action="" novalidate class="auth-form">
                    <?= csrf_field() ?>

                    <div class="mb-3">
                        <label for="username" class="form-label">Username</label>
                        <div class="input-group auth-input-group">
                            <span class="input-group-text" aria-hidden="true"><i class="bi bi-person"></i></span>
                            <input type="text"
                                   class="form-control"
                                   id="username"
                                   name="username"
                                   value="<?= e(old('username')) ?>"
                                   required
                                   autofocus
                                   autocomplete="username"
                                   aria-describedby="username-help">
                        </div>
                        <div id="username-help" class="form-text">Enter your assigned username.</div>
                    </div>

                    <div class="mb-4">
                        <label for="password" class="form-label">Password</label>
                        <div class="input-group auth-input-group">
                            <span class="input-group-text" aria-hidden="true"><i class="bi bi-lock"></i></span>
                            <input type="password"
                                   class="form-control"
                                   id="password"
                                   name="password"
                                   required
                                   autocomplete="current-password"
                                   aria-describedby="password-help">
                        </div>
                        <div id="password-help" class="form-text">Enter your current password.</div>
                    </div>

                    <button type="submit" class="btn btn-primary w-100 auth-submit">
                        <i class="bi bi-box-arrow-in-right me-2" aria-hidden="true"></i>Sign In
                    </button>
                </form>

                <div class="auth-footer">
                    <small>
                        Need help? Contact your system administrator.
                    </small>
                </div>
            </div>
        </div>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
