<?php
declare(strict_types=1);

session_start();

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/bdms_password_reset_helpers.php';

header('Content-Type: application/json; charset=UTF-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'message' => 'Method not allowed.']);
    exit;
}

function bdms_reset_verify_json(array $payload, int $status = 200): never
{
    http_response_code($status);
    echo json_encode($payload);
    exit;
}

$state = bdms_password_reset_get_session_state();
if ($state['reset_id'] <= 0 || $state['staff_id'] <= 0 || $state['email'] === '') {
    bdms_reset_verify_json(['ok' => false, 'message' => 'Start the password reset request again.'], 400);
}

$otp = preg_replace('/\D+/', '', (string) ($_POST['otp'] ?? ''));
if ($otp === '' || strlen($otp) !== 6) {
    bdms_reset_verify_json(['ok' => false, 'message' => 'Enter the 6-digit OTP from your email.'], 422);
}

$stmt = $conn->prepare(
    'SELECT id, otp_hash, otp_expires_at, otp_attempts, consumed_at, verified_at
     FROM staff_password_resets
     WHERE id = ? AND staff_id = ? AND email = ? LIMIT 1'
);
if ($stmt === false) {
    bdms_reset_verify_json(['ok' => false, 'message' => 'Unable to verify OTP right now.'], 500);
}

$stmt->bind_param('iis', $state['reset_id'], $state['staff_id'], $state['email']);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$row || !empty($row['consumed_at'])) {
    bdms_password_reset_clear_session();
    bdms_reset_verify_json(['ok' => false, 'message' => 'This reset link is no longer valid. Please request a new OTP.'], 400);
}

if (!empty($row['verified_at'])) {
    bdms_password_reset_mark_verified();
    bdms_reset_verify_json(['ok' => true, 'message' => 'OTP already verified. Continue to set a new password.']);
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
    bdms_reset_verify_json(['ok' => false, 'message' => 'That OTP has expired. Please request a new one.'], 410);
}

$attempts = (int) $row['otp_attempts'];
if (!password_verify($otp, (string) $row['otp_hash'])) {
    $attempts += 1;
    $lockOut = $attempts >= 5;
    $upd = $conn->prepare('UPDATE staff_password_resets SET otp_attempts = ?, consumed_at = IF(?, NOW(), consumed_at) WHERE id = ? AND staff_id = ?');
    if ($upd !== false) {
        $lockOutFlag = $lockOut ? 1 : 0;
        $upd->bind_param('iiii', $attempts, $lockOutFlag, $state['reset_id'], $state['staff_id']);
        $upd->execute();
        $upd->close();
    }
    if ($lockOut) {
        bdms_password_reset_clear_session();
        bdms_reset_verify_json(['ok' => false, 'message' => 'Too many incorrect attempts. Request a new OTP.'], 429);
    }

    $remaining = max(0, 5 - $attempts);
    bdms_reset_verify_json(['ok' => false, 'message' => 'Incorrect OTP. ' . $remaining . ' attempt' . ($remaining === 1 ? '' : 's') . ' left.'], 422);
}

$verify = $conn->prepare('UPDATE staff_password_resets SET verified_at = NOW() WHERE id = ? AND staff_id = ?');
if ($verify === false) {
    bdms_reset_verify_json(['ok' => false, 'message' => 'Unable to complete OTP verification.'], 500);
}

$verify->bind_param('ii', $state['reset_id'], $state['staff_id']);
if (!$verify->execute()) {
    $verify->close();
    bdms_reset_verify_json(['ok' => false, 'message' => 'Unable to complete OTP verification.'], 500);
}
$verify->close();

bdms_password_reset_mark_verified();
bdms_reset_verify_json([
    'ok' => true,
    'message' => 'OTP verified. You can now set a new password.',
]);
