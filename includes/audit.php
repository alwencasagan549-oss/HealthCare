<?php
declare(strict_types=1);

// ============================================================
// Audit Logger
// ============================================================

class AuditLogger
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function log(
        string $action,
        ?string $tableAffected = null,
        ?string $recordId = null,
        ?array $oldValues = null,
        ?array $newValues = null,
        ?int $userId = null
    ): void {
        if (!in_array($action, AUDIT_ACTIONS, true)) {
            $action = 'OTHER';
        }

        $stmt = $this->pdo->prepare("
            INSERT INTO audit_logs
                (user_id, action, table_affected, record_id, old_values, new_values, ip_address, user_agent)
            VALUES
                (:user_id, :action, :table_affected, :record_id, :old_values, :new_values, :ip_address, :user_agent)
        ");

        $stmt->execute([
            ':user_id'       => $userId ?? ($_SESSION['user_id'] ?? null),
            ':action'        => $action,
            ':table_affected'=> $tableAffected,
            ':record_id'     => $recordId,
            ':old_values'    => $oldValues !== null ? json_encode($oldValues, JSON_UNESCAPED_UNICODE) : null,
            ':new_values'    => $newValues !== null ? json_encode($newValues, JSON_UNESCAPED_UNICODE) : null,
            ':ip_address'    => get_client_ip(),
            ':user_agent'    => get_user_agent(),
        ]);
    }

    public function login(?int $userId): void
    {
        $this->log('LOGIN', 'users', (string)($userId ?? ''), null, null, $userId);
    }

    public function logout(?int $userId): void
    {
        $this->log('LOGOUT', 'users', (string)($userId ?? ''), null, null, $userId);
    }
}
