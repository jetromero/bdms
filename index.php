<?php
session_start();

if (isset($_SESSION['user'])) {
    $to_audit = (isset($_SESSION['role']) && $_SESSION['role'] === 'superadmin')
        || (string) ($_SESSION['user'] ?? '') === 'superadmin';
    header($to_audit ? 'Location: audit_log.php' : 'Location: dashboard.php');
    exit();
}

header('Location: login.php');
exit();