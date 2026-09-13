<?php
declare(strict_types=1);

// ============================================================
// Middleware
// ============================================================

class Middleware
{
    public static function guest(): void
    {
        if (is_logged_in()) {
            $role = $_SESSION['role'] ?? 'unknown';
            $dashboard = match ($role) {
                'admin' => url('admin/dashboard.php'),
                'nurse' => url('nurse/dashboard.php'),
                'dpwh'  => url('dpwh/dashboard.php'),
                default => url('index.php'),
            };
            redirect($dashboard);
        }
    }

    public static function authenticated(): void
    {
        if (!is_logged_in()) {
            redirect(url('session_expired.php'));
        }
    }

    public static function dpwhFirstLogin(): void
    {
        if (!is_logged_in() || !is_dpwh()) {
            return;
        }

        if (!empty($_SESSION['force_password_change'])) {
            $currentPath = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
            $changePasswordPath = parse_url(url('change_password.php'), PHP_URL_PATH);

            if ($currentPath !== $changePasswordPath && !str_contains($currentPath, 'logout.php')) {
                redirect(url('change_password.php'));
            }
        }
    }

    public static function admin(): void
    {
        self::authenticated();
        if (!is_admin()) {
            http_response_code(403);
            echo '<h1>403 - Forbidden</h1>';
            exit;
        }
    }

    public static function nurse(): void
    {
        self::authenticated();
        if (!is_nurse()) {
            http_response_code(403);
            echo '<h1>403 - Forbidden</h1>';
            exit;
        }
    }

    public static function dpwh(): void
    {
        self::authenticated();
        if (!is_dpwh()) {
            http_response_code(403);
            echo '<h1>403 - Forbidden</h1>';
            exit;
        }
    }

    public static function role(string ...$roles): void
    {
        self::authenticated();
        if (!in_array($_SESSION['role'], $roles, true)) {
            http_response_code(403);
            echo '<h1>403 - Forbidden</h1>';
            exit;
        }
    }
}
