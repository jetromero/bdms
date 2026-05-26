<?php
declare(strict_types=1);

$bdmsHost = getenv('BDMS_DB_HOST') ?: '127.0.0.1';
$bdmsUser = getenv('BDMS_DB_USER') ?: 'u749494134_bdms';
$bdmsPass = getenv('BDMS_DB_PASS') !== false ? getenv('BDMS_DB_PASS') : 'Bdms2025';
$bdmsName = getenv('BDMS_DB_NAME') ?: 'u749494134_bdms';

$conn = new mysqli($bdmsHost, $bdmsUser, $bdmsPass, $bdmsName);
$conn->set_charset('utf8mb4');
