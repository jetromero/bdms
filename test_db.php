<?php
declare(strict_types=1);

// Test DB connection using environment variables. Remove after use.
$bdmsHost = getenv('BDMS_DB_HOST') ?: '127.0.0.1';
$bdmsUser = getenv('BDMS_DB_USER') ?: 'u749494134_bdms';
$bdmsPass = getenv('BDMS_DB_PASS') !== false ? getenv('BDMS_DB_PASS') : '';
$bdmsName = getenv('BDMS_DB_NAME') ?: 'u749494134_bdms';

$conn = @new mysqli($bdmsHost, $bdmsUser, $bdmsPass, $bdmsName);
if ($conn->connect_error) {
    echo 'DB connection failed: ' . htmlspecialchars($conn->connect_error);
    exit(1);
}

echo 'DB connection OK — server: ' . htmlspecialchars($bdmsHost);
$conn->close();
