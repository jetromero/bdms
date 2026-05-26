<?php
declare(strict_types=1);

session_start();

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/bdms_password_reset_helpers.php';
require_once __DIR__ . '/bdms_mailer.php';

header('Content-Type: application/json; charset=UTF-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'message' => 'Method not allowed.']);
    exit;
}

function bdms_reset_json(array $payload, int $status = 200): never
{
    http_response_code($status);
    echo json_encode($payload);
    exit;
}

$email = strtolower(trim((string) ($_POST['email'] ?? '')));
if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    bdms_reset_json(['ok' => false, 'message' => 'Enter a valid email address.'], 422);
}

$stmt = $conn->prepare('SELECT id, username, display_name, email FROM staff_users WHERE email = ? AND is_active = 1 LIMIT 1');
if ($stmt === false) {
    bdms_reset_json(['ok' => false, 'message' => 'Unable to start password reset right now.'], 500);
}

$stmt->bind_param('s', $email);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$row) {
    bdms_password_reset_clear_session();
    bdms_reset_json(['ok' => false, 'message' => 'That email address doesn\'t exist.'], 404);
}

$staffId = (int) $row['id'];
$otp = bdms_password_reset_generate_code();
$otpHash = password_hash($otp, PASSWORD_DEFAULT);
$expiresAt = (new DateTimeImmutable('+10 minutes'))->format('Y-m-d H:i:s');

$conn->begin_transaction();

$delete = $conn->prepare('DELETE FROM staff_password_resets WHERE staff_id = ? AND consumed_at IS NULL');
if ($delete === false) {
    $conn->rollback();
    bdms_reset_json(['ok' => false, 'message' => 'Unable to prepare reset request.'], 500);
}
$delete->bind_param('i', $staffId);
$delete->execute();
$delete->close();

$insert = $conn->prepare(
    'INSERT INTO staff_password_resets (staff_id, email, otp_hash, otp_expires_at, otp_attempts, created_at, updated_at)
     VALUES (?, ?, ?, ?, 0, NOW(), NOW())'
);
if ($insert === false) {
    $conn->rollback();
    bdms_reset_json(['ok' => false, 'message' => 'Unable to prepare reset request.'], 500);
}

$insert->bind_param('isss', $staffId, $row['email'], $otpHash, $expiresAt);

if (!$insert->execute()) {
    $insert->close();
    $conn->rollback();
    bdms_reset_json(['ok' => false, 'message' => 'Unable to create password reset request.'], 500);
}

$resetId = (int) $conn->insert_id;
$insert->close();

$mailResult = bdms_send_password_reset_otp((string) $row['email'], (string) $row['display_name'], $otp);
if (empty($mailResult['ok'])) {
    $conn->rollback();
    bdms_reset_json(['ok' => false, 'message' => (string) ($mailResult['message'] ?? 'Unable to send OTP email right now.')], 500);
}

$conn->commit();
bdms_password_reset_store_session($resetId, $staffId, (string) $row['email']);

bdms_reset_json([
    'ok' => true,
    'message' => 'An OTP has been sent and will expire in 10 minutes.',
]);
