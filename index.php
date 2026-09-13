<?php
declare(strict_types=1);

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/helpers.php';

$activeRole = 'admin';
if (isset($_GET['role']) && in_array($_GET['role'], ['admin', 'nurse', 'dpwh'], true)) {
    $activeRole = $_GET['role'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <title><?= e(APP_SHORT_NAME . ' - Home') ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Figtree:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="<?= e(asset_path('assets/css/app.css')) ?>" rel="stylesheet">
</head>
<body>
    <header class="hero">
        <div class="hero-inner">
            <div class="brand-mark" aria-hidden="true">
                <i class="bi bi-heart-pulse"></i>
            </div>
            <h1 class="h3 mb-3"><?= e(APP_SHORT_NAME) ?></h1>
            <p class="lead">
                A secure health reporting system for the City Health Center.
                Sign in below to continue.
            </p>
        </div>
    </header>

    <main>
        <div class="section-divider"><hr></div>
        <section class="role-grid" aria-label="Login">
            <div class="auth-card">
                <div class="role-tabs" role="tablist" aria-label="Login role">
                    <a class="role-tab" type="button" role="tab" href="?role=admin" aria-selected="<?= $activeRole === 'admin' ? 'true' : 'false' ?>">Admin</a>
                    <a class="role-tab" type="button" role="tab" href="?role=nurse" aria-selected="<?= $activeRole === 'nurse' ? 'true' : 'false' ?>">Nurse</a>
                    <a class="role-tab" type="button" role="tab" href="?role=dpwh" aria-selected="<?= $activeRole === 'dpwh' ? 'true' : 'false' ?>">DPWH</a>
                </div>

                <div class="auth-body">
                    <form method="POST" action="login.php?role=<?= e($activeRole) ?>" novalidate class="auth-form">
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
                            Trouble logging in? Please clear your browser cache, or use incognito mode.
                        </small>
                        <br>
                        <small>
                            Need help? Contact your system administrator.
                        </small>
                    </div>
                </div>
            </div>
        </section>
    </main>

    <footer class="site-footer">
        <small>&copy; <?= e(date('Y')) ?> <?= e(APP_SHORT_NAME) ?>. Authorized use only.</small>
    </footer>
</body>
</html>
