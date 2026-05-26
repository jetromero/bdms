<?php
ob_start();
session_start();

require_once __DIR__ . '/require_login.php';
require_once __DIR__ . '/bdms_audit_log_helpers.php';
require_once __DIR__ . '/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: dashboard.php');
    exit;
}

$staffId = isset($_SESSION['staff_id']) ? (int) $_SESSION['staff_id'] : 0;
$defaultReturn = rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? '/'), '/\\') . '/dashboard.php';
$returnTo = isset($_POST['return_to']) && is_string($_POST['return_to']) ? trim((string) $_POST['return_to']) : $defaultReturn;

if (
    $returnTo === ''
    || str_contains($returnTo, '://')
    || str_starts_with($returnTo, '//')
    || str_contains($returnTo, '\\')
    || ($returnTo[0] ?? '') !== '/'
) {
    $returnTo = $defaultReturn;
}

$status = 'error';
$message = 'Please check your current password and try again.';

$currentPassword = (string) ($_POST['current_password'] ?? '');
$newPassword = (string) ($_POST['new_password'] ?? '');
$confirmPassword = (string) ($_POST['confirm_password'] ?? '');

if ($staffId <= 0) {
    $message = 'This account is not linked to a database user, so password changes are unavailable here.';
} elseif ($currentPassword === '' || $newPassword === '' || $confirmPassword === '') {
    $message = 'All password fields are required.';
} elseif (strlen($newPassword) < 8) {
    $message = 'New password must be at least 8 characters.';
} elseif ($newPassword !== $confirmPassword) {
    $message = 'New password confirmation does not match.';
} else {
    $stmt = $conn->prepare('SELECT password_hash FROM staff_users WHERE id = ? AND is_active = 1 LIMIT 1');
    if ($stmt !== false) {
        $stmt->bind_param('i', $staffId);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result ? $result->fetch_assoc() : null;
        $stmt->close();

        if ($row && password_verify($currentPassword, (string) $row['password_hash'])) {
            $newHash = password_hash($newPassword, PASSWORD_DEFAULT);
            $upd = $conn->prepare('UPDATE staff_users SET password_hash = ? WHERE id = ? AND is_active = 1');
            if ($upd !== false) {
                $upd->bind_param('si', $newHash, $staffId);
                if ($upd->execute()) {
                    bdms_audit_log_insert($conn, 'change_password', 'staff_users', $staffId, [
                        'outcome' => 'success',
                    ]);
                    $status = 'success';
                    $message = 'Your password has been updated successfully.';
                } else {
                    $message = 'Password update failed. Please try again.';
                }
                $upd->close();
            } else {
                $message = 'Unable to update your password right now.';
            }
        } else {
            $message = 'Current password is incorrect.';
        }
    } else {
        $message = 'Unable to start password update right now.';
    }
}

$conn->close();

$separator = str_contains($returnTo, '?') ? '&' : '?';
$redirectTarget = $returnTo . $separator . http_build_query([
    'password' => $status,
    'message' => $message,
]);

header('Location: ' . $redirectTarget);
exit;
