<?php
/**
 * Remove a donor record — administrators only (staff may not delete donor data).
 */
declare(strict_types=1);

require_once __DIR__ . '/require_login.php';
require_once __DIR__ . '/bdms_profile_bar.php';
require_once __DIR__ . '/bdms_audit_log_helpers.php';
require_once __DIR__ . '/db.php';

if (!bdms_is_administrator()) {
    http_response_code(403);
    header('Content-Type: text/plain; charset=UTF-8');
    echo 'Forbidden: only administrators can delete donor records.';
    exit;
}

if (isset($_GET['id'])) {
    $id = (int) $_GET['id'];
    if ($id <= 0) {
        http_response_code(400);
        echo 'Invalid id';
        exit;
    }

    $snap = $conn->prepare('SELECT id, name, blood_type, email, classification FROM donors WHERE id = ? LIMIT 1');
    if ($snap === false) {
        http_response_code(500);
        echo 'Error';
        exit;
    }
    $snap->bind_param('i', $id);
    $snap->execute();
    $snapRow = $snap->get_result()->fetch_assoc();
    $snap->close();
    if (!$snapRow) {
        http_response_code(404);
        echo 'Not found';
        exit;
    }

    $sql = 'DELETE FROM donors WHERE id = ?';
    $stmt = $conn->prepare($sql);
    if ($stmt === false) {
        http_response_code(500);
        echo 'Error';
        exit;
    }
    $stmt->bind_param('i', $id);

    if ($stmt->execute()) {
        bdms_audit_log_insert($conn, 'delete_donor', 'donor', $id, [
            'donor_id' => $id,
            'name' => (string) ($snapRow['name'] ?? ''),
            'blood_type' => (string) ($snapRow['blood_type'] ?? ''),
            'email' => (string) ($snapRow['email'] ?? ''),
            'classification' => (string) ($snapRow['classification'] ?? ''),
        ]);
        echo 'Success';
    } else {
        echo 'Error';
    }
    $stmt->close();
}

$conn->close();
