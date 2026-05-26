<?php
declare(strict_types=1);

require_once __DIR__ . '/require_login.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'administrator') {
    header('Location: dashboard.php?access=denied');
    exit();
}
