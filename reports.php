<?php
declare(strict_types=1);

require_once __DIR__ . '/require_login.php';
require_once __DIR__ . '/bdms_nav.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/reports_helpers.php';

$filters = reports_parse_filters($_GET);

$donorRows = [];
$donorSummary = ['total' => 0, 'by_blood' => []];
$invData = [
    'stock_by_type' => [],
    'donations_period' => [],
    'totals' => ['available_ml' => 0, 'expired_ml' => 0],
];

if ($filters['report'] === 'donor') {
    $donorRows = reports_fetch_donor_rows($conn, $filters);
    $donorSummary = reports_donor_summary($donorRows);
} else {
    $invData = reports_fetch_inventory_data($conn, $filters);
}

$conn->close();

$csvUrl = 'reports_export.php?' . http_build_query(array_merge($filters, ['format' => 'csv']));
$pdfUrl = 'reports_export.php?' . http_build_query(array_merge($filters, ['format' => 'pdf']));
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link rel="icon" type="image/png" href="evsulogo.png">
  <title>Reports | BDMS</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <link rel="stylesheet" href="assets/bdms.css">
  <?php bdms_profile_bar_print_styles(); ?>
  <style>
    .filters-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
      gap: 14px 16px;
      align-items: end;
    }
    .filters-grid .form-actions {
      grid-column: 1 / -1;
      display: flex;
      gap: 8px;
      flex-wrap: wrap;
    }
    .summary-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
      gap: 12px;
      margin: 18px 0;
    }
    .summary-tile {
      background: var(--surface);
      border: 1px solid var(--border);
      border-radius: var(--radius-md);
      padding: 14px 16px;
      box-shadow: var(--shadow-1);
    }
    .summary-tile strong {
      display: block;
      color: var(--text-muted);
      font-size: 11.5px;
      font-weight: 500;
      letter-spacing: 0.04em;
      text-transform: uppercase;
      margin-bottom: 4px;
    }
    .summary-tile span {
      font-size: 22px;
      font-weight: 600;
      color: var(--text);
    }
    @media (max-width: 960px) {
      .reports-filters-card .bdms-date-menu {
        left: 0;
        right: 0;
        width: 100%;
      }

      .reports-table-wrap {
        overflow-x: auto;
        -webkit-overflow-scrolling: touch;
      }

      .reports-donor-table {
        min-width: 820px;
      }

      .reports-stock-table {
        min-width: 560px;
      }

      .reports-donations-table {
        min-width: 520px;
      }
    }
  </style>
</head>
<body class="app app-content-slate">

  <?php bdms_nav_render('Reports'); ?>

  <main class="page" id="page">
    <div class="page-header">
      <div>
        <h1 class="page-title"><i class="fas fa-file-lines"></i> Generate report</h1>
        <p class="page-sub">Choose report type, set filters, review the summary, then export to CSV or PDF.</p>
      </div>
    </div>

    <div class="card mb-4 reports-filters-card">
      <form class="filters-grid" method="get" action="reports.php">
        <div>
          <label for="report">Report type</label>
          <select name="report" id="report">
            <option value="donor" <?php echo $filters['report'] === 'donor' ? 'selected' : ''; ?>>Donor activity</option>
            <option value="inventory" <?php echo $filters['report'] === 'inventory' ? 'selected' : ''; ?>>Blood inventory</option>
          </select>
        </div>
        <div>
          <label for="date_from">Date from</label>
          <input type="date" name="date_from" id="date_from" value="<?php echo htmlspecialchars($filters['date_from']); ?>" required>
        </div>
        <div>
          <label for="date_to">Date to</label>
          <input type="date" name="date_to" id="date_to" value="<?php echo htmlspecialchars($filters['date_to']); ?>" required>
        </div>
        <div>
          <label for="blood_type">Blood type</label>
          <select name="blood_type" id="blood_type">
            <option value="">All types</option>
            <?php foreach (['A+','A-','B+','B-','AB+','AB-','O+','O-'] as $bt): ?>
              <option value="<?php echo $bt; ?>" <?php echo $filters['blood_type'] === $bt ? 'selected' : ''; ?>><?php echo $bt; ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div>
          <label for="classification">Classification</label>
          <select name="classification" id="classification">
            <option value="">All</option>
            <?php foreach (['Student','Staff','Public'] as $c): ?>
              <option value="<?php echo $c; ?>" <?php echo $filters['classification'] === $c ? 'selected' : ''; ?>><?php echo $c; ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-actions">
          <button type="submit" class="btn btn-primary"><i class="fas fa-filter"></i> Apply filters</button>
          <a class="btn btn-outline" href="<?php echo htmlspecialchars($csvUrl); ?>"><i class="fas fa-file-csv"></i> CSV</a>
          <a class="btn btn-outline" href="<?php echo htmlspecialchars($pdfUrl); ?>"><i class="fas fa-file-pdf"></i> PDF</a>
        </div>
      </form>
    </div>

    <?php if ($filters['report'] === 'donor'): ?>
      <div class="summary-grid">
        <div class="summary-tile">
          <strong>Donors in scope</strong>
          <span><?php echo (int) $donorSummary['total']; ?></span>
        </div>
        <?php foreach ($donorSummary['by_blood'] as $bt => $cnt): ?>
          <div class="summary-tile">
            <strong><?php echo htmlspecialchars((string) $bt); ?></strong>
            <span><?php echo (int) $cnt; ?></span>
          </div>
        <?php endforeach; ?>
      </div>
      <p class="muted mb-3">Donors with collection or donation activity between the selected dates and matching filters.</p>
      <div class="table-wrap reports-table-wrap">
        <table class="reports-donor-table">
          <thead>
            <tr>
              <th>Name</th>
              <th>Contact</th>
              <th>Classification</th>
              <th>Blood</th>
              <th>Collection</th>
              <th>Donations</th>
              <th>Status</th>
            </tr>
          </thead>
          <tbody>
            <?php if (count($donorRows) === 0): ?>
              <tr><td colspan="7" class="muted" style="text-align:center;padding:24px;">No records match the filters.</td></tr>
            <?php else: ?>
              <?php foreach ($donorRows as $r): ?>
                <tr>
                  <td><strong><?php echo htmlspecialchars((string) $r['name']); ?></strong></td>
                  <td class="muted"><?php echo htmlspecialchars((string) $r['contact_number']); ?></td>
                  <td><?php echo htmlspecialchars((string) $r['classification']); ?></td>
                  <td><span class="badge badge-admin"><?php echo htmlspecialchars((string) $r['blood_type']); ?></span></td>
                  <td class="muted"><?php echo htmlspecialchars((string) $r['collection_date']); ?></td>
                  <td><?php echo (int) ($r['number_of_donations'] ?? 0); ?></td>
                  <td><?php echo htmlspecialchars((string) ($r['donation_status'] ?? '')); ?></td>
                </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>

    <?php else: ?>
      <div class="summary-grid">
        <div class="summary-tile">
          <strong>Available stock (mL)</strong>
          <span><?php echo number_format((int) $invData['totals']['available_ml']); ?></span>
        </div>
        <div class="summary-tile">
          <strong>Expired (mL)</strong>
          <span><?php echo number_format((int) $invData['totals']['expired_ml']); ?></span>
        </div>
      </div>

      <h3 class="mt-4"><i class="fas fa-layer-group" style="color: var(--brand);"></i> Current stock by type</h3>
      <p class="muted mb-3">Totals from donor records. Respects blood type and classification filters.</p>
      <div class="table-wrap reports-table-wrap mb-4">
        <table class="reports-stock-table">
          <thead>
            <tr>
              <th>Blood type</th>
              <th>Available (mL)</th>
              <th>Expired (mL)</th>
              <th>Donor rows</th>
            </tr>
          </thead>
          <tbody>
            <?php if (count($invData['stock_by_type']) === 0): ?>
              <tr><td colspan="4" class="muted" style="text-align:center;padding:24px;">No inventory rows for these filters.</td></tr>
            <?php else: ?>
              <?php foreach ($invData['stock_by_type'] as $s): ?>
                <tr>
                  <td><strong><?php echo htmlspecialchars((string) $s['blood_type']); ?></strong></td>
                  <td><?php echo (int) $s['ml_available']; ?></td>
                  <td class="muted"><?php echo (int) $s['ml_expired']; ?></td>
                  <td><?php echo (int) $s['donor_rows']; ?></td>
                </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>

      <h3 class="mt-4"><i class="fas fa-calendar-check" style="color: var(--brand);"></i> Donations recorded in range</h3>
      <p class="muted mb-3">From the donations register, if available.</p>
      <div class="table-wrap reports-table-wrap">
        <table class="reports-donations-table">
          <thead>
            <tr>
              <th>Blood type</th>
              <th>Volume (mL)</th>
              <th>Events</th>
            </tr>
          </thead>
          <tbody>
            <?php if (count($invData['donations_period']) === 0): ?>
              <tr><td colspan="3" class="muted" style="text-align:center;padding:24px;">No donation records in range.</td></tr>
            <?php else: ?>
              <?php foreach ($invData['donations_period'] as $d): ?>
                <tr>
                  <td><strong><?php echo htmlspecialchars((string) $d['blood_type']); ?></strong></td>
                  <td><?php echo (int) $d['total_ml']; ?></td>
                  <td><?php echo (int) $d['donation_count']; ?></td>
                </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </main>

  <script src="assets/bdms.js?v=20260527"></script>
</body>
</html>
