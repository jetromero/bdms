<?php
declare(strict_types=1);

session_start();

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/bdms_audit_log_helpers.php';
require_once __DIR__ . '/bdms_password_reset_helpers.php';

header('Content-Type: application/json; charset=UTF-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'message' => 'Method not allowed.']);
    exit;
}

function bdms_reset_finalize_json(array $payload, int $status = 200): never
{
    http_response_code($status);
    echo json_encode($payload);
    exit;
}

$state = bdms_password_reset_get_session_state();
if ($state['reset_id'] <= 0 || $state['staff_id'] <= 0 || $state['email'] === '' || !$state['verified']) {
    bdms_reset_finalize_json(['ok' => false, 'message' => 'Verify the OTP before changing the password.'], 400);
}

$newPassword = (string) ($_POST['new_password'] ?? '');
$confirmPassword = (string) ($_POST['confirm_password'] ?? '');

if ($newPassword === '' || $confirmPassword === '') {
    bdms_reset_finalize_json(['ok' => false, 'message' => 'All password fields are required.'], 422);
}

if (strlen($newPassword) < 8) {
    bdms_reset_finalize_json(['ok' => false, 'message' => 'New password must be at least 8 characters.'], 422);
}

if ($newPassword !== $confirmPassword) {
    bdms_reset_finalize_json(['ok' => false, 'message' => 'Password confirmation does not match.'], 422);
}

$check = $conn->prepare(
    'SELECT id, staff_id, otp_expires_at, consumed_at, verified_at
     FROM staff_password_resets
     WHERE id = ? AND staff_id = ? AND email = ? LIMIT 1'
);
if ($check === false) {
    bdms_reset_finalize_json(['ok' => false, 'message' => 'Unable to update the password right now.'], 500);
}

$check->bind_param('iis', $state['reset_id'], $state['staff_id'], $state['email']);
$check->execute();
$row = $check->get_result()->fetch_assoc();
$check->close();

if (!$row || !empty($row['consumed_at']) || empty($row['verified_at'])) {
    bdms_password_reset_clear_session();
    bdms_reset_finalize_json(['ok' => false, 'message' => 'The OTP session is no longer valid. Please start over.'], 400);
}

$expiresAt = strtotime((string) $row['otp_expires_at']);
if ($expiresAt !== false && $expiresAt < time()) {
    $expireStmt = $conn->prepare('UPDATE staff_password_resets SET consumed_at = NOW() WHERE id = ? AND staff_id = ?');
    if ($expireStmt !== false) {
        $expireStmt->bind_param('ii', $state['reset_id'], $state['staff_id']);
        $expireStmt->execute();
        $expireStmt->close();
    }
    bdms_password_reset_clear_session();
    bdms_reset_finalize_json(['ok' => false, 'message' => 'The OTP has expired. Request a new one.'], 410);
}

$conn->begin_transaction();

$hash = password_hash($newPassword, PASSWORD_DEFAULT);
$upd = $conn->prepare('UPDATE staff_users SET password_hash = ? WHERE id = ? AND email = ? AND is_active = 1');
if ($upd === false) {
    $conn->rollback();
    bdms_reset_finalize_json(['ok' => false, 'message' => 'Unable to update the password right now.'], 500);
}

$upd->bind_param('sis', $hash, $state['staff_id'], $state['email']);
if (!$upd->execute() || $upd->affected_rows <= 0) {
    $upd->close();
    $conn->rollback();
    bdms_reset_finalize_json(['ok' => false, 'message' => 'Password update failed. Please try again.'], 500);
}
$upd->close();

$mark = $conn->prepare('UPDATE staff_password_resets SET consumed_at = NOW() WHERE id = ? AND staff_id = ?');
if ($mark === false) {
    $conn->rollback();
    bdms_reset_finalize_json(['ok' => false, 'message' => 'Unable to finalize password reset.'], 500);
}

$mark->bind_param('ii', $state['reset_id'], $state['staff_id']);
if (!$mark->execute()) {
    $mark->close();
    $conn->rollback();
    bdms_reset_finalize_json(['ok' => false, 'message' => 'Unable to finalize password reset.'], 500);
}
$mark->close();

$conn->commit();
bdms_audit_log_insert($conn, 'password_reset', 'staff_users', $state['staff_id'], [
    'outcome' => 'success',
    'mode' => 'otp',
    'email' => $state['email'],
]);
bdms_password_reset_clear_session();

bdms_reset_finalize_json([
    'ok' => true,
    'message' => 'Your password has been updated successfully. You can now sign in.',
]);
