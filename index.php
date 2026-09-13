<?php
declare(strict_types=1);

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/helpers.php';
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
                Choose your access type below to continue to the sign-in page.
            </p>
        </div>
    </header>

    <main>
        <div class="section-divider"><hr></div>
        <section class="role-grid" aria-label="Login options">
            <div class="row g-3">
                <div class="col-md-4">
                    <article class="role-card">
                        <div class="role-icon" aria-hidden="true">
                            <i class="bi bi-shield-lock"></i>
                        </div>
                        <h2>Admin</h2>
                        <p>Manage districts, nurses, reports, and system-wide health data oversight.</p>
                        <a class="btn-role btn-role-primary" href="<?= e(url('login.php')) ?>">
                            <i class="bi bi-box-arrow-in-right" aria-hidden="true"></i>
                            Admin Login
                        </a>
                    </article>
                </div>

                <div class="col-md-4">
                    <article class="role-card">
                        <div class="role-icon" aria-hidden="true">
                            <i class="bi bi-person-vcard"></i>
                        </div>
                        <h2>Nurse</h2>
                        <p>Record patients, submit survey results, and monitor district health metrics.</p>
                        <a class="btn-role btn-role-accent" href="<?= e(url('login.php')) ?>">
                            <i class="bi bi-box-arrow-in-right" aria-hidden="true"></i>
                            Nurse Login
                        </a>
                    </article>
                </div>

                <div class="col-md-4">
                    <article class="role-card">
                        <div class="role-icon" aria-hidden="true">
                            <i class="bi bi-truck"></i>
                        </div>
                        <h2>DPWH</h2>
                        <p>Create and track field surveys for health-related infrastructure and cleanup requests.</p>
                        <a class="btn-role btn-role-secondary" href="<?= e(url('login.php')) ?>">
                            <i class="bi bi-box-arrow-in-right" aria-hidden="true"></i>
                            DPWH Login
                        </a>
                    </article>
                </div>
            </div>
        </section>
    </main>

    <footer class="site-footer">
        <small>&copy; <?= e(date('Y')) ?> <?= e(APP_SHORT_NAME) ?>. Authorized use only.</small>
    </footer>
</body>
</html>
