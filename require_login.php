<?php
declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

if (!isset($_SESSION["user"])) {
    header("Location: login.php");
    exit();
}

// Legacy sessions: infer role if missing (DB-backed logins set role in authenticate.php).
if (!isset($_SESSION['role'])) {
    if (isset($_SESSION['user']) && $_SESSION['user'] === 'admin') {
        $_SESSION['role'] = 'administrator';
    } elseif (isset($_SESSION['user']) && $_SESSION['user'] === 'superadmin') {
        $_SESSION['role'] = 'superadmin';
    } else {
        $_SESSION['role'] = 'staff';
    }
}

// Superadmin accounts may only open the audit log (and sign out).
if (isset($_SESSION['role']) && $_SESSION['role'] === 'superadmin') {
    $script = basename((string) ($_SERVER['SCRIPT_NAME'] ?? $_SERVER['PHP_SELF'] ?? ''));
    $allowed = ['audit_log.php', 'logout.php'];
    if (!in_array($script, $allowed, true)) {
        header('Location: audit_log.php');
        exit();
    }
}

