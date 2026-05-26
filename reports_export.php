<?php
declare(strict_types=1);

require_once __DIR__ . '/require_login.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/reports_helpers.php';
require_once __DIR__ . '/lib/fpdf.php';

$format = strtolower(trim((string) ($_GET['format'] ?? '')));
if ($format !== 'csv' && $format !== 'pdf') {
    header('HTTP/1.1 400 Bad Request');
    echo 'Invalid format. Use csv or pdf.';
    exit;
}

if ($format === 'pdf' && function_exists('set_time_limit')) {
    @set_time_limit(120);
}

$filters = reports_parse_filters($_GET);

$rows = [];
$summary = ['total' => 0, 'by_blood' => []];
$invData = [
    'stock_by_type' => [],
    'donations_period' => [],
    'totals' => ['available_ml' => 0, 'expired_ml' => 0],
];

if ($filters['report'] === 'donor') {
    $rows = reports_fetch_donor_rows($conn, $filters);
    $summary = reports_donor_summary($rows);
} else {
    $invData = reports_fetch_inventory_data($conn, $filters);
}

$conn->close();

$stamp = date('Y-m-d_His');
$safeUser = preg_replace('/[^a-zA-Z0-9_-]/', '_', (string) ($_SESSION['user'] ?? 'admin'));

/**
 * @param mixed $s
 */
function reports_pdf_cell_str($s): string
{
    $t = (string) $s;
    $o = @iconv('UTF-8', 'windows-1252//TRANSLIT', $t);
    return $o !== false ? $o : $t;
}

if ($format === 'csv') {
    $fname = $filters['report'] === 'donor'
        ? "donor-report_{$stamp}.csv"
        : "inventory-report_{$stamp}.csv";

    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $fname . '"');
    header('Cache-Control: no-store');

    $out = fopen('php://output', 'w');
    fprintf($out, chr(0xEF) . chr(0xBB) . chr(0xBF));

    if ($filters['report'] === 'donor') {
        fputcsv($out, ['EVSU-OC BDMS — Donor activity report']);
        fputcsv($out, ['Generated', date('c'), 'User', $safeUser]);
        fputcsv($out, ['Date from', $filters['date_from'], 'Date to', $filters['date_to']]);
        fputcsv($out, ['Blood type filter', $filters['blood_type'] ?: 'All', 'Classification', $filters['classification'] ?: 'All']);
        fputcsv($out, ['Total donors', (string) $summary['total']]);
        fputcsv($out, []);
        fputcsv($out, ['Name', 'Email', 'Contact', 'Classification', 'Blood type', 'Gender', 'Age', 'Collection date', 'Donation date', '# Donations', 'Status']);
        foreach ($rows as $r) {
            fputcsv($out, [
                $r['name'] ?? '',
                $r['email'] ?? '',
                $r['contact_number'] ?? '',
                $r['classification'] ?? '',
                $r['blood_type'] ?? '',
                $r['gender'] ?? '',
                (string) ($r['age'] ?? ''),
                $r['collection_date'] ?? '',
                $r['donation_date'] ?? '',
                (string) ($r['number_of_donations'] ?? ''),
                $r['donation_status'] ?? '',
            ]);
        }
    } else {
        fputcsv($out, ['EVSU-OC BDMS — Blood inventory report']);
        fputcsv($out, ['Generated', date('c'), 'User', $safeUser]);
        fputcsv($out, ['Date from', $filters['date_from'], 'Date to', $filters['date_to']]);
        fputcsv($out, ['Blood type filter', $filters['blood_type'] ?: 'All', 'Classification', $filters['classification'] ?: 'All']);
        fputcsv($out, ['Total available ml', (string) $invData['totals']['available_ml'], 'Total expired ml', (string) $invData['totals']['expired_ml']]);
        fputcsv($out, []);
        fputcsv($out, ['Section', 'Blood type', 'Available ml', 'Expired ml', 'Donor rows', 'Donations vol ml', 'Donation events']);
        foreach ($invData['stock_by_type'] as $s) {
            fputcsv($out, [
                'Current stock',
                $s['blood_type'],
                (string) $s['ml_available'],
                (string) $s['ml_expired'],
                (string) $s['donor_rows'],
                '',
                '',
            ]);
        }
        foreach ($invData['donations_period'] as $d) {
            fputcsv($out, [
                'Donations in period',
                $d['blood_type'],
                '',
                '',
                '',
                (string) $d['total_ml'],
                (string) $d['donation_count'],
            ]);
        }
    }

    fclose($out);
    exit;
}

// PDF
$pdf = new FPDF('P', 'mm', 'A4');
$pdf->SetAuthor('EVSU-OC BDMS');
$pdf->SetCreator('EVSU-OC BDMS');
$pdf->SetTitle($filters['report'] === 'donor' ? 'Donor report' : 'Inventory report');
$pdf->SetMargins(12, 12, 12);
$pdf->SetAutoPageBreak(true, 14);

$usableWidth = 186;
$logoPath = __DIR__ . '/evsulogo.png';

$renderHeader = function (FPDF $page, array $filters, string $safeUser, string $title) use ($usableWidth, $logoPath): void {
    $headerX = 12;
    $headerY = $page->GetY();
    $logoSize = 11;
    $headerHeight = 18;

    $page->SetFillColor(196, 30, 58);
    $page->SetTextColor(255, 255, 255);
    $page->Rect($headerX, $headerY, $usableWidth, $headerHeight, 'F');

    if (is_file($logoPath)) {
        $page->Image($logoPath, $headerX + 3.5, $headerY + 3.5, $logoSize, $logoSize);
    }

    $page->SetFont('Arial', 'B', 32);
    $page->SetXY($headerX + 1.5 + $logoSize + 3, $headerY + 8);
    $page->Cell(0, 4, reports_pdf_cell_str('EVSU-OC BDMS'), 0, 0, 'L');
    $page->Ln($headerHeight - 8);

    $page->SetFillColor(247, 240, 242);
    $page->SetTextColor(26, 10, 13);
    $page->SetFont('Arial', 'B', 13);
    $page->Cell($usableWidth, 8, reports_pdf_cell_str($title), 1, 1, 'L', true);

    $page->SetFont('Arial', '', 9);
    $page->SetFillColor(253, 248, 248);
    $page->Cell($usableWidth, 7, reports_pdf_cell_str('Generated: ' . date('Y-m-d H:i') . '    User: ' . $safeUser), 1, 1, 'L', true);
    $page->Cell($usableWidth, 7, reports_pdf_cell_str('Period: ' . $filters['date_from'] . ' to ' . $filters['date_to']), 1, 1, 'L', true);
    $page->Cell(
        $usableWidth,
        7,
        reports_pdf_cell_str('Filters: Blood ' . ($filters['blood_type'] ?: 'All') . ' | Classification ' . ($filters['classification'] ?: 'All')),
        1,
        1,
        'L',
        true
    );
    $page->Ln(4);
    $page->SetTextColor(26, 10, 13);
};

$renderTableHeader = function (FPDF $page, array $columns, array $widths): void {
    $page->SetFillColor(196, 30, 58);
    $page->SetTextColor(255, 255, 255);
    $page->SetFont('Arial', 'B', 8.5);
    foreach ($columns as $index => $label) {
        $page->Cell($widths[$index], 7, reports_pdf_cell_str($label), 1, 0, 'C', true);
    }
    $page->Ln();
    $page->SetTextColor(26, 10, 13);
};

$pdf->AddPage();
$renderHeader($pdf, $filters, $safeUser, $filters['report'] === 'donor' ? 'Donor Activity Report' : 'Blood inventory report');

if ($filters['report'] === 'donor') {
    $columns = ['Name', 'Contact', 'Class', 'Blood', 'Collection', 'Donations', 'Status'];
    $widths = [53, 26, 20, 16, 26, 22, 23];
    $rowHeight = 6;
    $contentBottom = 14;

    $renderTableHeader($pdf, $columns, $widths);
    $pdf->SetFont('Arial', '', 7.5);
    foreach ($rows as $r) {
        if ($pdf->GetY() + $rowHeight > $pdf->GetPageHeight() - $contentBottom) {
            $pdf->AddPage();
            $renderHeader($pdf, $filters, $safeUser, $filters['report'] === 'donor' ? 'Donor activity report' : 'Blood inventory report');
            $renderTableHeader($pdf, $columns, $widths);
            $pdf->SetFont('Arial', '', 7.5);
        }
        $nm = (string) ($r['name'] ?? '');
        if (function_exists('mb_substr')) {
            $nm = mb_strlen($nm) > 35 ? mb_substr($nm, 0, 32) . '...' : $nm;
        } elseif (strlen($nm) > 35) {
            $nm = substr($nm, 0, 32) . '...';
        }
        $pdf->Cell($widths[0], $rowHeight, reports_pdf_cell_str($nm), 1);
        $pdf->Cell($widths[1], $rowHeight, reports_pdf_cell_str((string) ($r['contact_number'] ?? '')), 1);
        $pdf->Cell($widths[2], $rowHeight, reports_pdf_cell_str((string) ($r['classification'] ?? '')), 1);
        $pdf->Cell($widths[3], $rowHeight, reports_pdf_cell_str((string) ($r['blood_type'] ?? '')), 1);
        $pdf->Cell($widths[4], $rowHeight, reports_pdf_cell_str((string) ($r['collection_date'] ?? '')), 1);
        $pdf->Cell($widths[5], $rowHeight, reports_pdf_cell_str((string) ($r['number_of_donations'] ?? '')), 1, 0, 'C');
        $st = (string) ($r['donation_status'] ?? '');
        if (function_exists('mb_substr')) {
            $st = mb_strlen($st) > 12 ? mb_substr($st, 0, 9) . '...' : $st;
        } elseif (strlen($st) > 12) {
            $st = substr($st, 0, 9) . '...';
        }
        $pdf->Cell($widths[6], $rowHeight, reports_pdf_cell_str($st), 1, 0, 'C');
        $pdf->Ln();
    }
    $pdf->Ln(0);
    $pdf->SetFillColor(247, 240, 242);
    $pdf->SetFont('Arial', 'B', 10);
    $pdf->Cell($usableWidth, 8, reports_pdf_cell_str('Total Donors: ' . $summary['total']), 1, 1, 'L', true);
} else {
    $pdf->SetFillColor(247, 240, 242);
    $pdf->SetFont('Arial', 'B', 10);
    $pdf->Cell($usableWidth, 8, reports_pdf_cell_str('Totals: Available ml ' . $invData['totals']['available_ml'] . ' | Expired ml ' . $invData['totals']['expired_ml']), 1, 1, 'L', true);
    $pdf->Ln(2);

    $stockColumns = ['Blood type', 'Available ml', 'Expired ml', 'Donor rows'];
    $stockWidths = [52, 46, 40, 28];
    $periodColumns = ['Blood type', 'Volume ml', 'Events'];
    $periodWidths = [52, 46, 28];
    $rowHeight = 6;
    $contentBottom = 14;

    $pdf->SetFont('Arial', 'B', 9);
    $pdf->Cell($usableWidth, 7, reports_pdf_cell_str('Current stock by blood type'), 0, 1);
    $renderTableHeader($pdf, $stockColumns, $stockWidths);
    $pdf->SetFont('Arial', '', 8);
    foreach ($invData['stock_by_type'] as $s) {
        if ($pdf->GetY() + $rowHeight > $pdf->GetPageHeight() - $contentBottom) {
            $pdf->AddPage();
            $renderHeader($pdf, $filters, $safeUser, $filters['report'] === 'donor' ? 'Donor activity report' : 'Blood inventory report');
            $pdf->SetFont('Arial', 'B', 9);
            $pdf->Cell($usableWidth, 7, reports_pdf_cell_str('Current stock by blood type'), 0, 1);
            $renderTableHeader($pdf, $stockColumns, $stockWidths);
            $pdf->SetFont('Arial', '', 8);
        }
        $pdf->Cell($stockWidths[0], $rowHeight, reports_pdf_cell_str($s['blood_type']), 1);
        $pdf->Cell($stockWidths[1], $rowHeight, (string) $s['ml_available'], 1, 0, 'R');
        $pdf->Cell($stockWidths[2], $rowHeight, (string) $s['ml_expired'], 1, 0, 'R');
        $pdf->Cell($stockWidths[3], $rowHeight, (string) $s['donor_rows'], 1, 0, 'R');
        $pdf->Ln();
    }
    $pdf->Ln(4);
    $pdf->SetFont('Arial', 'B', 9);
    $pdf->Cell($usableWidth, 7, reports_pdf_cell_str('Donations recorded in period'), 0, 1);
    $renderTableHeader($pdf, $periodColumns, $periodWidths);
    $pdf->SetFont('Arial', '', 8);
    foreach ($invData['donations_period'] as $d) {
        if ($pdf->GetY() + $rowHeight > $pdf->GetPageHeight() - $contentBottom) {
            $pdf->AddPage();
            $renderHeader($pdf, $filters, $safeUser, $filters['report'] === 'donor' ? 'Donor activity report' : 'Blood inventory report');
            $pdf->SetFont('Arial', 'B', 9);
            $pdf->Cell($usableWidth, 7, reports_pdf_cell_str('Donations recorded in period'), 0, 1);
            $renderTableHeader($pdf, $periodColumns, $periodWidths);
            $pdf->SetFont('Arial', '', 8);
        }
        $pdf->Cell($periodWidths[0], $rowHeight, reports_pdf_cell_str($d['blood_type']), 1);
        $pdf->Cell($periodWidths[1], $rowHeight, (string) $d['total_ml'], 1, 0, 'R');
        $pdf->Cell($periodWidths[2], $rowHeight, (string) $d['donation_count'], 1, 0, 'R');
        $pdf->Ln();
    }
}

$outName = $filters['report'] === 'donor' ? "donor-report-{$stamp}.pdf" : "inventory-report-{$stamp}.pdf";
if (ob_get_length() !== false) {
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
}

$pdfData = $pdf->Output('S', $outName);
header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="' . $outName . '"');
header('Content-Length: ' . strlen($pdfData));
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
echo $pdfData;
exit;
