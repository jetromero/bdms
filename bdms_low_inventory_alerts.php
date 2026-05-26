<?php
/**
 * US-04: Compute low blood stock rows using `blood_thresholds` when present, else a default threshold (mL).
 */
declare(strict_types=1);

/**
 * @return list<array{blood_type: string, available_quantity: int, threshold_ml: int}>
 */
function bdms_fetch_low_stock_alerts(mysqli $conn): array
{
    $default_threshold_ml = 500;
    $alerts = [];

    $check_thresholds = $conn->query("SHOW TABLES LIKE 'blood_thresholds'");
    $thresholds_table_exists = $check_thresholds && $check_thresholds->num_rows > 0;

    if ($thresholds_table_exists) {
        $sql = "
            SELECT sub.blood_type, sub.available_quantity, sub.threshold_ml
            FROM (
                SELECT
                    d.blood_type AS blood_type,
                    COALESCE(SUM(CASE WHEN d.donation_status = 'Active' THEN d.blood_quantity ELSE 0 END), 0) AS available_quantity,
                    COALESCE(MAX(t.threshold_ml), {$default_threshold_ml}) AS threshold_ml
                FROM donors d
                LEFT JOIN blood_thresholds t ON t.blood_type = d.blood_type
                GROUP BY d.blood_type
            ) AS sub
            WHERE sub.available_quantity < sub.threshold_ml
            ORDER BY sub.available_quantity ASC, sub.blood_type ASC
        ";
        $res = $conn->query($sql);
        if ($res) {
            while ($row = $res->fetch_assoc()) {
                $alerts[] = [
                    'blood_type' => (string) $row['blood_type'],
                    'available_quantity' => (int) $row['available_quantity'],
                    'threshold_ml' => (int) $row['threshold_ml'],
                ];
            }
        }
        return $alerts;
    }

    $res_types = $conn->query("
        SELECT blood_type,
               COALESCE(SUM(CASE WHEN donation_status = 'Active' THEN blood_quantity ELSE 0 END), 0) AS available_quantity
        FROM donors
        GROUP BY blood_type
        ORDER BY blood_type
    ");
    if ($res_types && $res_types->num_rows > 0) {
        while ($row = $res_types->fetch_assoc()) {
            $bt = (string) $row['blood_type'];
            $available = (int) $row['available_quantity'];
            if ($available < $default_threshold_ml) {
                $alerts[] = [
                    'blood_type' => $bt,
                    'available_quantity' => $available,
                    'threshold_ml' => $default_threshold_ml,
                ];
            }
        }
    }

    return $alerts;
}
