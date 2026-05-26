<?php
declare(strict_types=1);

require_once __DIR__ . '/require_login.php';
require_once __DIR__ . '/bdms_nav.php';
require_once __DIR__ . '/bdms_low_inventory_alerts.php';
require_once __DIR__ . '/db.php';

$conn->query("UPDATE donors 
              SET donation_status = 'Expired' 
              WHERE collection_date < CURDATE() - INTERVAL 42 DAY");

$conn->query("UPDATE donors 
              SET donation_status = 'Active' 
              WHERE collection_date >= CURDATE() - INTERVAL 42 DAY");

$low_stock_alerts = bdms_fetch_low_stock_alerts($conn);
$low_stock_types = [];
foreach ($low_stock_alerts as $la) {
    $low_stock_types[(string) $la['blood_type']] = true;
}

$sql = "SELECT blood_type, 
               SUM(CASE WHEN donation_status = 'Active' THEN blood_quantity ELSE 0 END) AS available_quantity,
               SUM(CASE WHEN donation_status = 'Expired' THEN blood_quantity ELSE 0 END) AS expired_quantity,
               MAX(collection_date + INTERVAL 42 DAY) AS expiration_date,
               MAX(last_updated) AS last_updated,
               donation_status
        FROM donors
        GROUP BY blood_type, donation_status
        ORDER BY blood_type";
$result = $conn->query($sql);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link rel="icon" type="image/png" href="evsulogo.png">
  <title>Blood Inventory | BDMS</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <link rel="stylesheet" href="assets/bdms.css">
  <?php bdms_profile_bar_print_styles(); ?>
  <style>
    .blood-inventory-table-wrap {
      overflow-x: auto;
      -webkit-overflow-scrolling: touch;
    }

    .blood-inventory-table {
      min-width: 760px;
    }

    .blood-inventory-archive-wrap {
      overflow-x: auto;
      -webkit-overflow-scrolling: touch;
    }

    .blood-inventory-archive-table {
      min-width: 520px;
    }

    @media (max-width: 960px) {
      .blood-inventory-actions {
        width: 100%;
        flex-direction: column;
        align-items: stretch;
      }

      .blood-inventory-actions .btn {
        width: 100%;
      }
    }
  </style>
</head>
<body class="app app-content-slate">

  <?php bdms_nav_render('Blood Inventory'); ?>

  <main class="page" id="page">
    <div class="page-header">
      <div>
        <h1 class="page-title"><i class="fas fa-droplet"></i> Blood inventory</h1>
        <p class="page-sub">Aggregated stock per blood type. Units expire 42 days after collection.</p>
      </div>
      <div class="blood-inventory-actions flex gap-2">
        <a href="graph_page.php" class="btn btn-primary"><i class="fas fa-chart-column"></i> Chart view</a>
        <a href="update_threshold.php" class="btn btn-primary"><i class="fas fa-sliders"></i> Thresholds</a>
        <button type="button" class="btn btn-primary" id="archiveBtn"><i class="fas fa-calendar-xmark"></i> Expired stock</button>
      </div>
    </div>

    <div class="table-wrap blood-inventory-table-wrap">
      <table class="blood-inventory-table">
        <thead>
          <tr>
            <th>Blood type</th>
            <th>Available (mL)</th>
            <th>Expired (mL)</th>
            <th>Expiration date</th>
            <th>Last updated</th>
            <th>Status</th>
          </tr>
        </thead>
        <tbody>
          <?php
          if ($result && $result->num_rows > 0) {
              while ($row = $result->fetch_assoc()) {
                  $bt = (string) $row['blood_type'];
                  $isLow = isset($low_stock_types[$bt]) && ($row['donation_status'] === 'Active');
                  $rowClass = $isLow ? " class='low-stock-row'" : '';
                  echo '<tr' . $rowClass . '>';
                  echo '<td><strong>' . htmlspecialchars($bt) . '</strong></td>';
                  echo '<td>' . (int) $row['available_quantity'] . ' mL</td>';
                  echo '<td class="muted">' . (int) $row['expired_quantity'] . ' mL</td>';
                  echo '<td class="muted">' . ($row['expiration_date'] ? date('Y-m-d', strtotime($row['expiration_date'])) : '—') . '</td>';
                  echo '<td class="muted">' . ($row['last_updated'] ? date('Y-m-d H:i', strtotime($row['last_updated'])) : '—') . '</td>';
                  echo '<td><span class="status-' . htmlspecialchars((string) $row['donation_status']) . '">' . htmlspecialchars((string) $row['donation_status']) . '</span></td>';
                  echo '</tr>';
              }
          } else {
              echo '<tr><td colspan="6" class="muted" style="text-align:center;padding:24px;">No blood inventory data available.</td></tr>';
          }
          ?>
        </tbody>
      </table>
    </div>
  </main>

  <div id="archiveModal" class="modal">
    <div class="modal-content">
      <button type="button" class="close" id="closeModal" aria-label="Close">&times;</button>
      <h3><i class="fas fa-calendar-xmark"></i> Expired blood donations</h3>
      <div class="table-wrap blood-inventory-archive-wrap" style="border:0; box-shadow:none;">
        <table class="blood-inventory-archive-table">
          <thead>
            <tr>
              <th>Blood type</th>
              <th>Quantity (mL)</th>
              <th>Expiration date</th>
            </tr>
          </thead>
          <tbody>
            <?php
            $archived = $conn->query("SELECT blood_type, blood_quantity, (collection_date + INTERVAL 42 DAY) AS expiration_date FROM donors WHERE donation_status = 'Expired' ORDER BY expiration_date DESC");
            if ($archived && $archived->num_rows > 0) {
                while ($row = $archived->fetch_assoc()) {
                    echo '<tr>';
                    echo '<td>' . htmlspecialchars((string) $row['blood_type']) . '</td>';
                    echo '<td>' . (int) $row['blood_quantity'] . '</td>';
                    echo '<td class="muted">' . date('Y-m-d', strtotime((string) $row['expiration_date'])) . '</td>';
                    echo '</tr>';
                }
            } else {
                echo '<tr><td colspan="3" class="muted" style="text-align:center;padding:24px;">No archived donations found.</td></tr>';
            }
            ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <script src="assets/bdms.js"></script>
  <script>
    (function () {
      if (typeof bdmsToast !== 'function') return;

      <?php foreach ($low_stock_alerts as $la): ?>
        bdmsToast(
          'Warning!',
          <?php echo json_encode(
            htmlspecialchars((string) $la['blood_type'], ENT_QUOTES, 'UTF-8') . ' is below the threshold: ' . (int) $la['available_quantity'] . ' mL available',
            JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
          ); ?>,
          'warning',
          3000
        );
      <?php endforeach; ?>
    })();

    const archiveBtn = document.getElementById('archiveBtn');
    const archiveModal = document.getElementById('archiveModal');
    const closeModalBtn = document.getElementById('closeModal');

    archiveBtn.addEventListener('click', function () { archiveModal.classList.add('is-open'); });
    closeModalBtn.addEventListener('click', function () { archiveModal.classList.remove('is-open'); });
    archiveModal.addEventListener('click', function (e) {
      if (e.target === archiveModal) archiveModal.classList.remove('is-open');
    });
  </script>

<?php $conn->close(); ?>
</body>
</html>
