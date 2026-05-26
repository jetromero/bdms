<?php
require_once __DIR__ . '/require_login.php';
require_once __DIR__ . '/bdms_nav.php';
require_once __DIR__ . '/bdms_low_inventory_alerts.php';
require_once __DIR__ . '/db.php';

$is_administrator = bdms_is_administrator();

$conn->query("UPDATE donors 
              SET donation_status = 'Expired' 
              WHERE collection_date < CURDATE() - INTERVAL 42 DAY");

$conn->query("UPDATE donors 
              SET donation_status = 'Active' 
              WHERE collection_date >= CURDATE() - INTERVAL 42 DAY");

$sql_donors = 'SELECT COUNT(*) as total_donors FROM donors';
$result_donors = $conn->query($sql_donors);
$total_donors = ($result_donors && $result_donors->num_rows > 0) ? (int) $result_donors->fetch_assoc()['total_donors'] : 0;

$sql_blood = "SELECT COALESCE(SUM(CASE WHEN donation_status = 'Active' THEN blood_quantity ELSE 0 END), 0) AS available_quantity FROM donors";
$result_blood = $conn->query($sql_blood);
$available_blood_units = ($result_blood && $result_blood->num_rows > 0) ? (int) $result_blood->fetch_assoc()['available_quantity'] : 0;

$sql_blood_by_type = "
  SELECT blood_type, 
         COALESCE(SUM(CASE WHEN donation_status = 'Active' THEN blood_quantity ELSE 0 END), 0) AS available_quantity,
         COALESCE(SUM(CASE WHEN donation_status = 'Expired' THEN blood_quantity ELSE 0 END), 0) AS expired_quantity
  FROM donors 
  GROUP BY blood_type 
  ORDER BY blood_type
";
$result_blood_by_type = $conn->query($sql_blood_by_type);

$bloodTypes = [];
$availableQuantities = [];
$expiredQuantities = [];
$bloodByTypeForCards = [];

if ($result_blood_by_type && $result_blood_by_type->num_rows > 0) {
    while ($row = $result_blood_by_type->fetch_assoc()) {
        $bloodTypes[] = $row['blood_type'];
        $availableQuantities[] = (int) $row['available_quantity'];
        $expiredQuantities[] = (int) $row['expired_quantity'];
        $bloodByTypeForCards[] = $row;
    }
}

$sql_today_collection = "
  SELECT
    COALESCE(SUM(blood_quantity), 0) AS total_quantity,
    COUNT(*) AS donor_count
  FROM donors
  WHERE DATE(collection_date) = CURDATE()
    AND donation_status = 'Active'
";
$result_today_collection = $conn->query($sql_today_collection);
$today_collection_row = ($result_today_collection && $result_today_collection->num_rows > 0)
  ? $result_today_collection->fetch_assoc()
  : null;
$today_collection_quantity = $today_collection_row !== null ? (int) $today_collection_row['total_quantity'] : 0;
$today_collection_donors = $today_collection_row !== null ? (int) $today_collection_row['donor_count'] : 0;

$sql_classification = 'SELECT classification, COUNT(*) as total FROM donors GROUP BY classification ORDER BY total DESC';
$result_classification = $conn->query($sql_classification);
$classificationLabels = [];
$classificationValues = [];

if ($result_classification && $result_classification->num_rows > 0) {
    while ($row = $result_classification->fetch_assoc()) {
        $classificationLabels[] = $row['classification'];
        $classificationValues[] = (int) $row['total'];
    }
}

$sql_recent_donors = "
  SELECT id, name, blood_type, classification, blood_quantity, collection_date, donation_status
  FROM donors
  ORDER BY collection_date DESC, id DESC
  LIMIT 8
";
$result_recent_donors = $conn->query($sql_recent_donors);

$low_stock_alerts = bdms_fetch_low_stock_alerts($conn);

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <link rel="icon" type="image/png" href="evsulogo.png">
  <title>Dashboard | BDMS</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet" />
  <link rel="stylesheet" href="assets/bdms.css">
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
  <?php bdms_profile_bar_print_styles(); ?>
</head>
<body class="app app-content-slate">

  <?php bdms_nav_render('Dashboard'); ?>

  <main class="page" id="page">
    <div class="page-header">
      <div>
        <h1 class="page-title"><i class="fas fa-chart-pie"></i> Dashboard</h1>
        <p class="page-sub">Overview of donors, blood inventory, and analytics.</p>
      </div>
    </div>

    <div class="metric-grid">
      <a href="donors_list.php" class="metric metric-accent-red" aria-label="Open donors list">
        <p class="metric-label"><i class="fas fa-user-friends"></i> Total donors</p>
        <p class="metric-value"><?php echo (int) $total_donors; ?></p>
      </a>

      <a href="blood_inventory.php" class="metric" aria-label="Open blood inventory">
        <p class="metric-label"><i class="fas fa-droplet"></i> Available blood (mL)</p>
        <p class="metric-value"><?php echo number_format($available_blood_units); ?></p>
      </a>

      <div class="metric" style="cursor:default;">
        <p class="metric-label"><i class="fas fa-calendar-day"></i> Blood collection today</p>
        <p class="metric-value"><?php echo number_format($today_collection_quantity); ?></p>
        <p class="metric-note"><?php echo number_format($today_collection_donors); ?> donor<?php echo $today_collection_donors === 1 ? '' : 's'; ?> today</p>
      </div>

    </div>

    <div class="dashboard-chart-grid mt-4">
      <!-- Analytics chart -->
      <div class="card dashboard-chart-card dashboard-chart-card--wide">
        <div class="card-title"><i class="fas fa-chart-column"></i> Blood Inventory Chart</div>
        <p class="card-sub">Available and expired blood units (mL) grouped by blood type.</p>
        <div class="dashboard-chart-wrap dashboard-chart-wrap--bar">
          <canvas id="bloodChart" height="210"></canvas>
        </div>
      </div>

      <div class="card dashboard-chart-card dashboard-chart-card--narrow">
        <div class="card-title"><i class="fas fa-user-tag"></i> Donor Classifications</div>
        <p class="card-sub">Donor breakdown by classification.</p>
        <div class="dashboard-chart-wrap dashboard-chart-wrap--pie">
          <canvas id="classificationChart" height="210"></canvas>
        </div>
      </div>
    </div>

    <div class="card dashboard-recent-card mt-4">
      <div class="card-title"><i class="fas fa-user-clock"></i> Recent donors</div>
      <p class="card-sub">Latest donor records added to the system.</p>

      <div class="dashboard-recent-table-wrap">
        <?php if ($result_recent_donors && $result_recent_donors->num_rows > 0): ?>
          <table class="dashboard-recent-table">
            <thead>
              <tr>
                <th>Name</th>
                <th>Blood</th>
                <th>Quantity</th>
                <th>Classification</th>
                <th>Collection date</th>
                <th>Status</th>
              </tr>
            </thead>
            <tbody>
              <?php while ($row = $result_recent_donors->fetch_assoc()): ?>
                <tr>
                  <td><strong><?php echo htmlspecialchars((string) $row['name']); ?></strong></td>
                  <td><span class="badge badge-admin"><?php echo htmlspecialchars((string) $row['blood_type']); ?></span></td>
                  <td><?php echo number_format((int) $row['blood_quantity']); ?> mL</td>
                  <td><?php echo htmlspecialchars((string) $row['classification']); ?></td>
                  <td class="muted"><?php echo htmlspecialchars((string) $row['collection_date']); ?></td>
                  <td><span class="badge <?php echo strtolower((string) $row['donation_status']) === 'active' ? 'badge-active' : 'badge-off'; ?>"><?php echo htmlspecialchars((string) $row['donation_status']); ?></span></td>
                </tr>
              <?php endwhile; ?>
            </tbody>
          </table>
        <?php else: ?>
          <p class="muted" style="padding: 18px 0; text-align: center;">No recent donors found.</p>
        <?php endif; ?>
      </div>
    </div>
  </main>

  <script src="assets/bdms.js?v=20260527"></script>
  <script>
    (function () {
      if (typeof bdmsToast === 'function') {
        var p = new URLSearchParams(window.location.search);
        if (p.get('access') === 'denied' || p.get('reports') === 'denied') {
          bdmsToast('Something went wrong!', 'That page is restricted to administrators.', 'error', 3000);
          if (window.history.replaceState) {
            window.history.replaceState({}, '', 'dashboard.php');
          }
        }

        <?php foreach ($low_stock_alerts as $a): ?>
          bdmsToast(
            'Warning!',
            <?php echo json_encode(
              htmlspecialchars((string) $a['blood_type'], ENT_QUOTES, 'UTF-8') . ' is below the threshold: ' . (int) $a['available_quantity'] . ' mL available',
              JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
            ); ?>,
            'warning',
            3000
          );
        <?php endforeach; ?>
      }
    })();

    /* chart */
    var ctx = document.getElementById('bloodChart').getContext('2d');
    new Chart(ctx, {
      type: 'bar',
      data: {
        labels: <?php echo json_encode($bloodTypes); ?>,
        datasets: [
          {
            label: 'Available (mL)',
            data: <?php echo json_encode($availableQuantities); ?>,
            backgroundColor: '#c41e3a',
            borderRadius: 8,
            maxBarThickness: 28,
            barPercentage: 0.65,
            categoryPercentage: 0.72
          },
          {
            label: 'Expired (mL)',
            data: <?php echo json_encode($expiredQuantities); ?>,
            backgroundColor: '#ede3e3',
            borderRadius: 8,
            maxBarThickness: 28,
            barPercentage: 0.65,
            categoryPercentage: 0.72
          }
        ]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: { position: 'top', labels: { color: '#3d2428', font: { family: 'Inter', size: 13, weight: '600' }, usePointStyle: true, pointStyle: 'circle' } },
          tooltip: { backgroundColor: '#1e0b0e', titleFont: { family: 'Inter', weight: '700' }, bodyFont: { family: 'Inter' }, cornerRadius: 10, padding: 12 }
        },
        scales: {
          x: { grid: { display: false }, ticks: { color: '#7a5f63', font: { family: 'Inter', weight: '600' } } },
          y: {
            beginAtZero: true,
            grid: { color: '#ede3e3' },
            ticks: { color: '#7a5f63', font: { family: 'Inter' } },
            title: { display: true, text: 'Quantity (mL)', color: '#7a5f63', font: { family: 'Inter', size: 12, weight: '600' } }
          }
        }
      }
    });

    var classificationCtx = document.getElementById('classificationChart').getContext('2d');
    new Chart(classificationCtx, {
      type: 'pie',
      data: {
        labels: <?php echo json_encode($classificationLabels); ?>,
        datasets: [{
          data: <?php echo json_encode($classificationValues); ?>,
          backgroundColor: ['#c41e3a', '#e03355', '#9c1631', '#f39cab', '#f1c9d1', '#b61a34'],
          borderColor: '#ffffff',
          borderWidth: 2,
          hoverOffset: 8
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: {
            position: 'bottom',
            labels: {
              color: '#3d2428',
              font: { family: 'Inter', size: 12, weight: '600' },
              usePointStyle: true,
              pointStyle: 'circle',
              boxWidth: 10
            }
          },
          tooltip: {
            backgroundColor: '#1e0b0e',
            titleFont: { family: 'Inter', weight: '700' },
            bodyFont: { family: 'Inter' },
            cornerRadius: 10,
            padding: 12
          }
        }
      }
    });
  </script>

</body>
</html>
