<?php
/**
 * Validates login credentials against `staff_users` when populated; otherwise legacy admin bootstrap.
 */
declare(strict_types=1);

session_start();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: login.php');
    exit;
}

$username = trim((string) ($_POST['username'] ?? ''));
$password = (string) ($_POST['password'] ?? '');

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/bdms_audit_log_helpers.php';
require_once __DIR__ . '/bdms_password_reset_helpers.php';

$dbAuth = false;
$tableRes = $conn->query("SHOW TABLES LIKE 'staff_users'");
if ($tableRes && $tableRes->num_rows > 0) {
    $cntRes = $conn->query('SELECT COUNT(*) AS c FROM staff_users');
    if ($cntRes) {
        $cntRow = $cntRes->fetch_assoc();
        $dbAuth = isset($cntRow['c']) && (int) $cntRow['c'] > 0;
    }
}

if ($dbAuth) {
    $stmt = $conn->prepare(
        'SELECT id, username, display_name, email, password_hash, role
         FROM staff_users WHERE username = ? AND is_active = 1 LIMIT 1'
    );
    if ($stmt === false) {
        $conn->close();
        $_SESSION['error'] = 'Sign-in is temporarily unavailable. Please try again later.';
        header('Location: login.php');
        exit;
    }
    $stmt->bind_param('s', $username);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();

    if ($row && password_verify($password, (string) $row['password_hash'])) {
        bdms_password_reset_clear_session();
        $_SESSION['user'] = (string) $row['username'];
        $_SESSION['role'] = (string) $row['role'];
        $_SESSION['staff_id'] = (int) $row['id'];
        $_SESSION['display_name'] = (string) $row['display_name'];
        $_SESSION['bdms_force_sidebar_open'] = true;
        bdms_audit_log_insert($conn, 'login_success', 'auth', (int) $row['id'], [
            'outcome' => 'success',
            'role' => (string) $row['role'],
        ]);
        $conn->close();
        if ((string) $row['role'] === 'superadmin') {
            header('Location: audit_log.php');
        } else {
            header('Location: dashboard.php');
        }
        exit;
    }

    $attemptBy = $username !== '' ? $username : 'anonymous';
    bdms_audit_log_insert($conn, 'login_failure', 'auth', 0, [
        'outcome' => 'failure',
        'reason' => 'invalid_credentials',
    ], $attemptBy);
    $conn->close();
    $_SESSION['error'] = 'Invalid username or password!';
    header('Location: login.php');
    exit;
}

// Legacy bootstrap when `staff_users` is empty or table missing (same credentials as before US-06).
if ($username === 'admin' && $password === 'bdms25') {
    bdms_password_reset_clear_session();
    $_SESSION['user'] = 'admin';
    $_SESSION['role'] = 'administrator';
    unset($_SESSION['staff_id'], $_SESSION['display_name']);
    $_SESSION['bdms_force_sidebar_open'] = true;
    bdms_audit_log_insert($conn, 'login_success', 'auth', 0, [
        'outcome' => 'success',
        'role' => 'administrator',
        'mode' => 'legacy_bootstrap',
    ]);
    $conn->close();
    header('Location: dashboard.php');
    exit;
}

$attemptBy = $username !== '' ? $username : 'anonymous';
bdms_audit_log_insert($conn, 'login_failure', 'auth', 0, [
    'outcome' => 'failure',
    'reason' => 'invalid_credentials',
    'mode' => 'legacy_bootstrap',
], $attemptBy);
$conn->close();

$_SESSION['error'] = 'Invalid username or password!';
header('Location: login.php');
exit;
