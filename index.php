<?php
declare(strict_types=1);

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/middleware.php';
require_once __DIR__ . '/includes/audit.php';

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
            $selectedRole = $_POST['role'] ?? '';

            if ($user['role'] !== $selectedRole) {
                $error = 'Invalid credentials for ' . ucfirst($selectedRole) . ' login.';
            } else {
                $auth->clearFailedAttempts($username);
                $auth->login($user);

                $audit = new AuditLogger($pdo);
                $audit->login($user['user_id']);

                if ($user['force_password_change']) {
                    redirect(url('change_password.php'));
                }

                $dashboard = match ($user['role']) {
                    'admin' => url('admin/dashboard.php'),
                    'nurse' => url('nurse/dashboard.php'),
                    'dpwh'  => url('dpwh/dashboard.php'),
                    default => url('index.php'),
                };
                redirect($dashboard);
            }
        } else {
            $auth->recordFailedAttempt($username);
            $error = 'Invalid username or password.';
        }
    }
}

$activeRole = 'admin';
if (isset($_GET['role']) && in_array($_GET['role'], ['admin', 'nurse', 'dpwh'], true)) {
    $activeRole = $_GET['role'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e(APP_SHORT_NAME . ' - Home') ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Figtree:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="<?= e(asset_path('assets/css/app.css')) ?>" rel="stylesheet">
</head>
<body>
    <div class="landing-hero">
        <div class="hero-inner">
            <img src="<?= e(asset_path('assets/logo/Logo1.png')) ?>" alt="<?= e(APP_SHORT_NAME) ?> logo" class="brand-logo mx-auto d-block">
            <h1 class="hero-title"><?= e(APP_SHORT_NAME) ?></h1>
            <p class="hero-subtitle">Integrated Networked System for Patient Information and Record Entry — City Health Center health reporting and district management.</p>
        </div>
    </div>

    <div class="landing-login-area">
        <div class="landing-login-card">
            <div class="landing-login-header">
                <h2>Sign in to your account</h2>
                <p>Choose your role and enter your credentials to continue.</p>
            </div>

            <div class="landing-login-body">
                <div class="role-tabs" role="tablist" aria-label="Login role">
                    <button class="role-tab" id="tab-admin" type="button" role="tab" data-role="admin" aria-selected="<?= $activeRole === 'admin' ? 'true' : 'false' ?>" aria-controls="login-form-panel">Admin</button>
                    <button class="role-tab" id="tab-nurse" type="button" role="tab" data-role="nurse" aria-selected="<?= $activeRole === 'nurse' ? 'true' : 'false' ?>" aria-controls="login-form-panel">Nurse</button>
                    <button class="role-tab" id="tab-dpwh" type="button" role="tab" data-role="dpwh" aria-selected="<?= $activeRole === 'dpwh' ? 'true' : 'false' ?>" aria-controls="login-form-panel">DPWH</button>
                </div>

                <div class="auth-body" style="padding-top: var(--space-5);">
                    <?php if ($error): ?>
                        <div class="alert alert-danger auth-alert" role="alert" tabindex="-1" aria-labelledby="login-error">
                            <p id="login-error" class="mb-0 fw-semibold">Sign-in failed</p>
                            <p class="mb-0"><?= e($error) ?></p>
                        </div>
                    <?php endif; ?>

                    <form method="POST" action="index.php?role=<?= e($activeRole) ?>" novalidate class="auth-form" id="login-form-panel" role="tabpanel" aria-labelledby="tab-<?= e($activeRole) ?>">
                        <?= csrf_field() ?>
                        <input type="hidden" name="role" value="<?= e($activeRole) ?>">

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
                                <button class="btn btn-outline-secondary auth-password-toggle"
                                        type="button"
                                        aria-label="Show password"
                                        data-target="password"
                                        tabindex="-1">
                                    <i class="bi bi-eye" aria-hidden="true"></i>
                                </button>
                            </div>
                            <div id="password-help" class="form-text">Enter your current password.</div>
                        </div>

                        <button type="submit" class="btn btn-primary w-100 auth-submit">
                            <i class="bi bi-box-arrow-in-right me-2" aria-hidden="true"></i>Sign In
                        </button>
                    </form>

                    <div class="auth-footer">
                        <small>
                            Trouble logging in? Please clear your browser cache, or use incognito mode.
                        </small>
                        <br>
                        <small>
                            Need help? Contact your system administrator.
                        </small>
                    </div>
                </div>
            </div>
        </div>

        <footer class="landing-footer">
            <small>&copy; <?= e(date('Y')) ?> <?= e(APP_SHORT_NAME) ?>. Authorized use only. Contact your administrator for access.</small>
        </footer>
    </div>

    <script>
        (function () {
            const tabs = document.querySelectorAll('.role-tab');
            const roleInput = document.querySelector('input[name="role"]');
            const form = document.querySelector('.auth-form');

            if (!tabs.length || !roleInput || !form) return;

            tabs.forEach(tab => {
                tab.addEventListener('click', () => {
                    tabs.forEach(t => t.setAttribute('aria-selected', 'false'));
                    tab.setAttribute('aria-selected', 'true');
                    roleInput.value = tab.dataset.role || 'admin';
                    form.setAttribute('aria-labelledby', tab.id);
                    form.querySelector('input[type="text"]')?.focus();
                });
            });

            document.querySelectorAll('.auth-password-toggle').forEach(btn => {
                btn.addEventListener('click', () => {
                    const input = document.getElementById(btn.dataset.target);
                    const icon = btn.querySelector('i');
                    if (!input || !icon) return;

                    if (input.type === 'password') {
                        input.type = 'text';
                        icon.classList.replace('bi-eye', 'bi-eye-slash');
                        btn.setAttribute('aria-label', 'Hide password');
                    } else {
                        input.type = 'password';
                        icon.classList.replace('bi-eye-slash', 'bi-eye');
                        btn.setAttribute('aria-label', 'Show password');
                    }
                });
            });
        })();
    </script>
</body>
</html>
