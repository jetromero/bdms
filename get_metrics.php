<?php
require_once __DIR__ . '/require_login.php';
require_once __DIR__ . "/db.php";

if ($conn->connect_error) {
    http_response_code(500);
    echo json_encode(["error" => "Database connection failed."]);
    exit();
}

$total_donors = 0;
$result_donors = $conn->query("SELECT COUNT(*) as total_donors FROM donors");
if ($result_donors) {
    $total_donors = $result_donors->fetch_assoc()['total_donors'] ?? 0;
}

$available_blood_units = 0;
$result_blood = $conn->query("SELECT SUM(CASE WHEN donation_status = 'Active' THEN blood_quantity ELSE 0 END) AS available_quantity FROM donors");
if ($result_blood) {
    $available_blood_units = $result_blood->fetch_assoc()['available_quantity'] ?? 0;
}

$blood_by_type = [];
$result_types = $conn->query("SELECT blood_type, SUM(CASE WHEN donation_status = 'Active' THEN blood_quantity ELSE 0 END) AS available_quantity FROM donors GROUP BY blood_type");
if ($result_types) {
    while ($row = $result_types->fetch_assoc()) {
        $blood_by_type[] = $row;
    }
}

$conn->close();

header('Content-Type: application/json');
echo json_encode([
    'total_donors' => $total_donors,
    'available_blood_units' => $available_blood_units,
    'blood_by_type' => $blood_by_type
]);
?>
