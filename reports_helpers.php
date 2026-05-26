<?php
declare(strict_types=1);

/**
 * @return array{date_from: string, date_to: string, blood_type: string, classification: string, report: string}
 */
function reports_parse_filters(array $src): array
{
    $today = new DateTimeImmutable('today');
    $defaultFrom = $today->modify('-90 days')->format('Y-m-d');
    $defaultTo = $today->format('Y-m-d');

    $df = trim((string) ($src['date_from'] ?? ''));
    $dt = trim((string) ($src['date_to'] ?? ''));
    if ($df === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $df)) {
        $df = $defaultFrom;
    }
    if ($dt === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dt)) {
        $dt = $defaultTo;
    }
    $dfObj = DateTimeImmutable::createFromFormat('Y-m-d', $df);
    $dtObj = DateTimeImmutable::createFromFormat('Y-m-d', $dt);
    if ($dfObj === false || $dtObj === false || $dfObj > $dtObj) {
        $df = $defaultFrom;
        $dt = $defaultTo;
    }

    $blood = (string) ($src['blood_type'] ?? '');
    $allowedBlood = ['', 'A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'];
    if (!in_array($blood, $allowedBlood, true)) {
        $blood = '';
    }

    $class = (string) ($src['classification'] ?? '');
    $allowedClass = ['', 'Student', 'Staff', 'Public'];
    if (!in_array($class, $allowedClass, true)) {
        $class = '';
    }

    $report = (string) ($src['report'] ?? 'donor');
    if ($report !== 'donor' && $report !== 'inventory') {
        $report = 'donor';
    }

    return [
        'date_from' => $df,
        'date_to' => $dt,
        'blood_type' => $blood,
        'classification' => $class,
        'report' => $report,
    ];
}

function reports_has_donations_table(mysqli $conn): bool
{
    $r = $conn->query("SHOW TABLES LIKE 'donations'");
    return $r && $r->num_rows > 0;
}

/**
 * @return list<array<string, mixed>>
 */
function reports_fetch_donor_rows(mysqli $conn, array $filters): array
{
    $df = $filters['date_from'];
    $dt = $filters['date_to'];
    $blood = $filters['blood_type'];
    $class = $filters['classification'];
    $hasDon = reports_has_donations_table($conn);

    $sql = 'SELECT DISTINCT d.id, d.name, d.email, d.contact_number, d.blood_type, d.classification,
                   d.collection_date, d.donation_date, d.number_of_donations, d.donation_status,
                   d.gender, d.age
            FROM donors d';
    $types = '';
    $params = [];

    if ($hasDon) {
        $sql .= ' WHERE (
            (d.collection_date BETWEEN ? AND ?)
            OR EXISTS (
                SELECT 1 FROM donations x WHERE x.donor_id = d.id AND x.donation_date BETWEEN ? AND ?
            )
        )';
        $types .= 'ssss';
        array_push($params, $df, $dt, $df, $dt);
    } else {
        $sql .= ' WHERE d.collection_date BETWEEN ? AND ?';
        $types .= 'ss';
        array_push($params, $df, $dt);
    }

    if ($blood !== '') {
        $sql .= ' AND d.blood_type = ?';
        $types .= 's';
        $params[] = $blood;
    }
    if ($class !== '') {
        $sql .= ' AND d.classification = ?';
        $types .= 's';
        $params[] = $class;
    }

    $sql .= ' ORDER BY d.name ASC';

    $stmt = $conn->prepare($sql);
    if ($stmt === false) {
        return [];
    }
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $res = $stmt->get_result();
    $rows = [];
    while ($row = $res->fetch_assoc()) {
        $rows[] = $row;
    }
    $stmt->close();
    return $rows;
}

/**
 * Summary counts for donor report.
 *
 * @param list<array<string, mixed>> $rows
 * @return array{total: int, by_blood: array<string, int>}
 */
function reports_donor_summary(array $rows): array
{
    $byBlood = [];
    foreach ($rows as $r) {
        $bt = (string) ($r['blood_type'] ?? '');
        if (!isset($byBlood[$bt])) {
            $byBlood[$bt] = 0;
        }
        $byBlood[$bt]++;
    }
    return ['total' => count($rows), 'by_blood' => $byBlood];
}

/**
 * Inventory: current stock from donors + optional donations-in-period from `donations`.
 *
 * @return array{
 *   stock_by_type: list<array{blood_type: string, ml_available: int, ml_expired: int, donor_rows: int}>,
 *   donations_period: list<array{blood_type: string, total_ml: int, donation_count: int}>,
 *   totals: array{available_ml: int, expired_ml: int}
 * }
 */
function reports_fetch_inventory_data(mysqli $conn, array $filters): array
{
    $df = $filters['date_from'];
    $dt = $filters['date_to'];
    $blood = $filters['blood_type'];
    $class = $filters['classification'];

    $whereStock = '1=1';
    $typesS = '';
    $paramsS = [];
    if ($blood !== '') {
        $whereStock .= ' AND blood_type = ?';
        $typesS .= 's';
        $paramsS[] = $blood;
    }
    if ($class !== '') {
        $whereStock .= ' AND classification = ?';
        $typesS .= 's';
        $paramsS[] = $class;
    }

    $sqlStock = "SELECT blood_type,
            SUM(CASE WHEN donation_status = 'Active' THEN blood_quantity ELSE 0 END) AS ml_available,
            SUM(CASE WHEN donation_status = 'Expired' THEN blood_quantity ELSE 0 END) AS ml_expired,
            COUNT(*) AS donor_rows
        FROM donors
        WHERE $whereStock
        GROUP BY blood_type
        ORDER BY blood_type";

    $stockByType = [];
    $stmt = $conn->prepare($sqlStock);
    if ($stmt) {
        if ($typesS !== '') {
            $stmt->bind_param($typesS, ...$paramsS);
        }
        $stmt->execute();
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) {
            $stockByType[] = [
                'blood_type' => (string) $row['blood_type'],
                'ml_available' => (int) $row['ml_available'],
                'ml_expired' => (int) $row['ml_expired'],
                'donor_rows' => (int) $row['donor_rows'],
            ];
        }
        $stmt->close();
    }

    $avail = 0;
    $exp = 0;
    foreach ($stockByType as $s) {
        $avail += $s['ml_available'];
        $exp += $s['ml_expired'];
    }

    $donationsPeriod = [];
    if (reports_has_donations_table($conn)) {
        $whereD = 'donation_date BETWEEN ? AND ?';
        $typesD = 'ss';
        $paramsD = [$df, $dt];
        if ($blood !== '') {
            $whereD .= ' AND blood_type = ?';
            $typesD .= 's';
            $paramsD[] = $blood;
        }
        if ($class !== '') {
            $whereD .= ' AND EXISTS (SELECT 1 FROM donors dd WHERE dd.id = donations.donor_id AND dd.classification = ?)';
            $typesD .= 's';
            $paramsD[] = $class;
        }
        $sqlD = "SELECT blood_type, COALESCE(SUM(quantity_ml),0) AS total_ml, COUNT(*) AS donation_count
                 FROM donations
                 WHERE $whereD
                 GROUP BY blood_type
                 ORDER BY blood_type";
        $st2 = $conn->prepare($sqlD);
        if ($st2) {
            $st2->bind_param($typesD, ...$paramsD);
            $st2->execute();
            $r2 = $st2->get_result();
            while ($row = $r2->fetch_assoc()) {
                $donationsPeriod[] = [
                    'blood_type' => (string) $row['blood_type'],
                    'total_ml' => (int) $row['total_ml'],
                    'donation_count' => (int) $row['donation_count'],
                ];
            }
            $st2->close();
        }
    }

    return [
        'stock_by_type' => $stockByType,
        'donations_period' => $donationsPeriod,
        'totals' => ['available_ml' => $avail, 'expired_ml' => $exp],
    ];
}
