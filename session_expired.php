<?php
declare(strict_types=1);

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/helpers.php';

// If a valid session still exists, send them to their dashboard instead of showing the expired page.
if (is_logged_in()) {
    $role = (string)($_SESSION['role'] ?? '');
    $dashboard = match ($role) {
        'admin' => url('admin/dashboard.php'),
        'nurse' => url('nurse/dashboard.php'),
        'dpwh'  => url('dpwh/dashboard.php'),
        default => url('index.php'),
    };

    redirect($dashboard);
}

// Expire the timed-out session before showing the expired page.
if (session_status() === PHP_SESSION_ACTIVE) {
    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params['path'],
            $params['domain'],
            $params['secure'],
            $params['httponly']
        );
    }

    session_destroy();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Session Expired - <?= e(APP_SHORT_NAME) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Figtree:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="<?= e(asset_path('assets/css/app.css')) ?>" rel="stylesheet">
</head>
<body>
    <div class="auth-page">
        <div class="container">
            <div class="row justify-content-center align-items-center min-vh-100">
                <div class="col-sm-10 col-md-8 col-lg-5 col-xl-4">
                    <div class="text-center mb-4">
                        <div class="brand-mark mx-auto" aria-hidden="true">
                            <img src="<?= e(asset_path('assets/logo/Logo1.png')) ?>" alt="<?= e(APP_SHORT_NAME) ?> logo" class="brand-logo">
                        </div>
                        <h1 class="h3 fw-bold mb-2"><?= e(APP_SHORT_NAME) ?></h1>
                        <p class="text-muted mb-0">City Health Center health reporting</p>
                    </div>

                    <div class="auth-card">
                        <div class="auth-body text-center">
                            <div class="mb-3" aria-hidden="true">
                                <i class="bi bi-clock-history text-warning" style="font-size: 2.5rem;"></i>
                            </div>
                            <h2 class="h5 fw-semibold mb-2">Session Expired</h2>
                            <p class="text-muted mb-4">
                                Your session has expired due to inactivity.<br>
                                Please sign in again to continue.
                            </p>
                            <a href="<?= e(url('index.php')) ?>" class="btn btn-primary w-100 auth-submit">
                                <i class="bi bi-box-arrow-in-right me-2" aria-hidden="true"></i>Login Again
                            </a>
                            <p class="text-muted mt-3 mb-0 small">
                                For your security, sessions expire after <?= (int)(SESSION_TIMEOUT / 60) ?> minutes of inactivity.
                            </p>
                        </div>
                    </div>

                    <p class="text-center text-muted mt-3 mb-0 small">
                        Authorized use only. Contact your administrator for access.
                    </p>
                </div>
            </div>
        </div>
    </div>

    <footer class="site-footer d-none">
        <small>&copy; <?= e(date('Y')) ?> <?= e(APP_SHORT_NAME) ?>. Authorized use only.</small>
    </footer>
</body>
</html>
