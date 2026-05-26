<?php
/**
 * Best-effort writes to audit_log (US-02). Ignores failures so donor flows still complete if the table is missing.
 */
declare(strict_types=1);

/**
 * @param array<string, mixed> $details Stored as JSON in audit_log.details
 * @param string|null $performed_by_override Use for events with no session (e.g. failed login); empty string falls through to session/system.
 */
function bdms_audit_log_insert(mysqli $conn, string $action, string $entity_type, int $entity_id, array $details, ?string $performed_by_override = null): void
{
    static $table_ok = null;
    if ($table_ok === false) {
        return;
    }
    if ($table_ok === null) {
        $chk = $conn->query("SHOW TABLES LIKE 'audit_log'");
        $table_ok = ($chk && $chk->num_rows > 0);
        if (!$table_ok) {
            return;
        }
    }

    if ($performed_by_override !== null && $performed_by_override !== '') {
        $performed_by = $performed_by_override;
    } else {
        $performed_by = isset($_SESSION['user']) ? (string) $_SESSION['user'] : 'system';
    }
    $json = json_encode($details, JSON_UNESCAPED_UNICODE);
    if ($json === false) {
        $json = '{}';
    }

    $stmt = $conn->prepare(
        'INSERT INTO audit_log (action, entity_type, entity_id, details, performed_by) VALUES (?, ?, ?, ?, ?)'
    );
    if ($stmt === false) {
        return;
    }

    $stmt->bind_param('ssiss', $action, $entity_type, $entity_id, $json, $performed_by);
    $stmt->execute();
    $stmt->close();
}
