<?php
declare(strict_types=1);

// ============================================================
// Authentication Helper
// ============================================================

class Auth
{
    public function __construct(private PDO $pdo)
    {
    }

    public function attempt(string $username, string $password): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT user_id, username, password_hash, full_name, role, district_id, status, force_password_change
            FROM users
            WHERE username = :username
            LIMIT 1
        ");
        $stmt->execute([':username' => $username]);
        $user = $stmt->fetch();

        if (!$user) {
            return null;
        }

        if (strtolower((string)$user['status']) !== 'active') {
            return null;
        }

        if (!password_verify($password, $user['password_hash'])) {
            return null;
        }

        return [
            'user_id'              => (int)$user['user_id'],
            'username'             => $user['username'],
            'full_name'            => $user['full_name'],
            'role'                 => $user['role'],
            'district_id'          => $user['district_id'] ? (int)$user['district_id'] : null,
            'force_password_change'=> (bool)$user['force_password_change'],
        ];
    }

    public function login(array $user): void
    {
        session_regenerate_id(true);

        $_SESSION['user_id']      = $user['user_id'];
        $_SESSION['username']     = $user['username'];
        $_SESSION['full_name']    = $user['full_name'];
        $_SESSION['role']         = $user['role'];
        $_SESSION['district_id']  = $user['district_id'];
        $_SESSION['logged_in_at'] = time();
        $_SESSION['last_activity'] = time();

        if ($user['force_password_change']) {
            $_SESSION['force_password_change'] = true;
        }

        $this->updateLastLogin((int)$user['user_id']);
    }

    public function logout(): void
    {
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

    public function updateLastLogin(int $userId): void
    {
        $stmt = $this->pdo->prepare("
            UPDATE users SET last_login = NOW() WHERE user_id = :user_id
        ");
        $stmt->execute([':user_id' => $userId]);
    }

    public function updatePassword(int $userId, string $passwordHash): void
    {
        $stmt = $this->pdo->prepare("
            UPDATE users
            SET password_hash = :hash, force_password_change = FALSE, updated_at = NOW()
            WHERE user_id = :user_id
        ");
        $stmt->execute([
            ':hash'     => $passwordHash,
            ':user_id'  => $userId,
        ]);
    }

    public function isBlocked(string $username): bool
    {
        if (empty($_SESSION['login_attempts'][$username])) {
            return false;
        }

        $attempts = $_SESSION['login_attempts'][$username];
        $recentAttempts = array_filter($attempts, function ($timestamp) {
            return ($timestamp + LOGIN_LOCKOUT_WINDOW) > time();
        });

        return count($recentAttempts) >= LOGIN_MAX_ATTEMPTS;
    }

    public function recordFailedAttempt(string $username): void
    {
        if (empty($_SESSION['login_attempts'][$username])) {
            $_SESSION['login_attempts'][$username] = [];
        }

        $_SESSION['login_attempts'][$username][] = time();
    }

    public function clearFailedAttempts(string $username): void
    {
        unset($_SESSION['login_attempts'][$username]);
    }
}
