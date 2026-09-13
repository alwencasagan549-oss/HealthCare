<?php
declare(strict_types=1);

// ============================================================
// Helper Functions
// ============================================================

if (!function_exists('base_path')) {
    function base_path(string $path = ''): string {
        return rtrim(BASE_PATH, '/\\') . '/' . ltrim($path, '/\\');
    }
}

if (!function_exists('asset_path')) {
    function asset_path(string $path = ''): string {
        return rtrim(APP_URL, '/\\') . '/' . ltrim($path, '/\\');
    }
}

if (!function_exists('url')) {
    function url(string $path = ''): string {
        return rtrim(APP_URL, '/\\') . '/' . ltrim($path, '/\\');
    }
}

if (!function_exists('redirect')) {
    function redirect(string $url, int $statusCode = 302): void {
        header('Location: ' . $url, true, $statusCode);
        exit;
    }
}

if (!function_exists('csrf_token')) {
    function csrf_token(): string {
        if (empty($_SESSION['csrf_token']) || time() - ($_SESSION['csrf_token_time'] ?? 0) > CSRF_TOKEN_LIFETIME) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
            $_SESSION['csrf_token_time'] = time();
        }
        return $_SESSION['csrf_token'];
    }
}

if (!function_exists('csrf_field')) {
    function csrf_field(): string {
        return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') . '">';
    }
}

if (!function_exists('verify_csrf')) {
    function verify_csrf(?string $token): bool {
        if (empty($token) || empty($_SESSION['csrf_token'])) {
            return false;
        }
        return hash_equals($_SESSION['csrf_token'], $token);
    }
}

if (!function_exists('e')) {
    function e(?string $value): string {
        return htmlspecialchars((string)($value ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }
}

if (!function_exists('old')) {
    function old(string $key, ?string $default = null): ?string {
        return $_SESSION['old'][$key] ?? $default ?? null;
    }
}

if (!function_exists('flash')) {
    function flash(string $key, ?string $message = null): ?string {
        if ($message !== null) {
            $_SESSION['flash'][$key] = $message;
            return null;
        }
        $value = $_SESSION['flash'][$key] ?? null;
        unset($_SESSION['flash'][$key]);
        return $value;
    }
}

if (!function_exists('validate_password')) {
    function validate_password(string $password): array {
        $errors = [];

        if (strlen($password) < PASSWORD_MIN_LENGTH) {
            $errors[] = 'Password must be at least ' . PASSWORD_MIN_LENGTH . ' characters.';
        }
        if (!preg_match('/[A-Z]/', $password)) {
            $errors[] = 'Password must contain at least one uppercase letter.';
        }
        if (!preg_match('/[a-z]/', $password)) {
            $errors[] = 'Password must contain at least one lowercase letter.';
        }
        if (!preg_match('/[0-9]/', $password)) {
            $errors[] = 'Password must contain at least one number.';
        }
        if (!preg_match('/[^A-Za-z0-9]/', $password)) {
            $errors[] = 'Password must contain at least one special character.';
        }

        return $errors;
    }
}

if (!function_exists('generate_temporary_password')) {
    function generate_temporary_password(int $length = 10): string {
        $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghjkmnpqrstuvwxyz23456789!@#$%^&*';
        $password = '';
        $max = strlen($chars) - 1;

        for ($i = 0; $i < $length; $i++) {
            $password .= $chars[random_int(0, $max)];
        }

        return $password;
    }
}

if (!function_exists('generate_patient_code')) {
    function generate_patient_code(PDO $pdo, int $districtId, string $districtCode): string {
        $prefix = 'PAT-' . strtoupper($districtCode) . '-';
        $year = date('Y');

        $stmt = $pdo->prepare("
            SELECT COUNT(*) FROM patients
            WHERE district_id = :district_id AND patient_code LIKE :prefix
        ");
        $stmt->execute([
            ':district_id' => $districtId,
            ':prefix'      => $prefix . $year . '%',
        ]);
        $count = (int)$stmt->fetchColumn();

        return $prefix . $year . '-' . str_pad((string)($count + 1), 4, '0', STR_PAD_LEFT);
    }
}

if (!function_exists('format_date')) {
    function format_date(?string $date, string $format = 'F j, Y'): string {
        if (empty($date)) {
            return 'N/A';
        }
        $timestamp = strtotime($date);
        return $timestamp ? date($format, $timestamp) : 'N/A';
    }
}

if (!function_exists('format_datetime')) {
    function format_datetime(?string $datetime, string $format = 'F j, Y g:i A'): string {
        if (empty($datetime)) {
            return 'N/A';
        }
        $timestamp = strtotime($datetime);
        return $timestamp ? date($format, $timestamp) : 'N/A';
    }
}

if (!function_exists('get_client_ip')) {
    function get_client_ip(): ?string {
        $headers = [
            'HTTP_CF_CONNECTING_IP',
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_REAL_IP',
            'REMOTE_ADDR',
        ];

        foreach ($headers as $header) {
            if (!empty($_SERVER[$header])) {
                $ips = explode(',', $_SERVER[$header]);
                return trim($ips[0]);
            }
        }

        return $_SERVER['REMOTE_ADDR'] ?? null;
    }
}

if (!function_exists('get_user_agent')) {
    function get_user_agent(): ?string {
        return $_SERVER['HTTP_USER_AGENT'] ?? null;
    }
}

if (!function_exists('is_logged_in')) {
    function is_logged_in(): bool {
        if (empty($_SESSION['user_id']) || empty($_SESSION['role'])) {
            return false;
        }

        if ($_SESSION['role'] !== 'admin' && empty($_SESSION['district_id'])) {
            return false;
        }

        if (!empty($_SESSION['last_activity']) && (time() - (int)$_SESSION['last_activity']) > SESSION_TIMEOUT) {
            return false;
        }

        $_SESSION['last_activity'] = time();

        return true;
    }
}

if (!function_exists('current_user')) {
    function current_user(): ?array {
        if (!is_logged_in()) {
            return null;
        }

        return [
            'user_id'     => $_SESSION['user_id'],
            'username'    => $_SESSION['username'] ?? null,
            'full_name'   => $_SESSION['full_name'] ?? null,
            'role'        => $_SESSION['role'],
            'district_id' => $_SESSION['district_id'] ?? null,
        ];
    }
}

if (!function_exists('current_district_id')) {
    function current_district_id(): ?int {
        return $_SESSION['district_id'] ?? null;
    }
}

if (!function_exists('is_admin')) {
    function is_admin(): bool {
        return ($_SESSION['role'] ?? '') === 'admin';
    }
}

if (!function_exists('is_nurse')) {
    function is_nurse(): bool {
        return ($_SESSION['role'] ?? '') === 'nurse';
    }
}

if (!function_exists('is_dpwh')) {
    function is_dpwh(): bool {
        return ($_SESSION['role'] ?? '') === 'dpwh';
    }
}

if (!function_exists('require_role')) {
    function require_role(string ...$allowedRoles): void {
        if (!is_logged_in()) {
            redirect(url('index.php'));
        }

        if (!in_array($_SESSION['role'], $allowedRoles, true)) {
            http_response_code(403);
            echo '<h1>403 - Forbidden</h1><p>You do not have permission to access this page.</p>';
            exit;
        }
    }
}

if (!function_exists('require_auth')) {
    function require_auth(): void {
        if (!is_logged_in()) {
            redirect(url('index.php'));
        }
    }
}

if (!function_exists('paginate')) {
    function paginate(int $totalRecords, int $page = 1, int $limit = DEFAULT_PAGE_LIMIT): array {
        $page = max(1, $page);
        $limit = min(MAX_PAGE_LIMIT, max(1, $limit));
        $totalPages = (int)ceil($totalRecords / $limit);

        if ($page > $totalPages && $totalPages > 0) {
            $page = $totalPages;
        }

        $offset = ($page - 1) * $limit;

        return [
            'page'       => $page,
            'limit'      => $limit,
            'total'      => $totalRecords,
            'total_pages'=> $totalPages,
            'offset'     => $offset,
            'has_prev'   => $page > 1,
            'has_next'   => $page < $totalPages,
            'prev_page'  => $page - 1,
            'next_page'  => $page + 1,
        ];
    }
}
