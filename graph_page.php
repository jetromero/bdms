<?php
require_once __DIR__ . '/require_login.php';
require_once __DIR__ . '/bdms_nav.php';
require_once __DIR__ . "/db.php";

$sql = "SELECT blood_type, 
               SUM(CASE WHEN donation_status = 'Active' THEN blood_quantity ELSE 0 END) AS available_quantity,
               SUM(CASE WHEN donation_status = 'Expired' THEN blood_quantity ELSE 0 END) AS expired_quantity
        FROM donors
        GROUP BY blood_type
        ORDER BY blood_type";
$result = $conn->query($sql);

$bloodTypes = [];
$availableQuantities = [];
$expiredQuantities = [];

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $bloodTypes[] = $row['blood_type'];
        $availableQuantities[] = (int) $row['available_quantity'];
        $expiredQuantities[] = (int) $row['expired_quantity'];
    }
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link rel="icon" type="image/png" href="evsulogo.png">
  <title>Analytics | BDMS</title>
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
  <link rel="stylesheet" href="assets/bdms.css">
  <?php bdms_profile_bar_print_styles(); ?>
</head>
<body class="app app-content-slate">

<?php bdms_nav_render('Analytics'); ?>

<main class="page" id="page">
  <div class="page-header">
    <div>
      <h1 class="page-title"><i class="fas fa-chart-column"></i> Inventory chart</h1>
      <p class="page-sub">Available and expired blood units (mL) grouped by blood type.</p>
    </div>
    <div class="flex gap-2">
      <a href="blood_inventory.php" class="btn btn-primary"><i class="fas fa-arrow-left"></i> Back</a>
    </div>
  </div>

  <div class="card">
    <canvas id="bloodChart" style="max-height: 420px;"></canvas>
  </div>
</main>

<script src="assets/bdms.js?v=20260527"></script>
<script>
  const ctx = document.getElementById('bloodChart').getContext('2d');
  new Chart(ctx, {
    type: 'bar',
    data: {
      labels: <?php echo json_encode($bloodTypes); ?>,
      datasets: [
        {
          label: 'Available (mL)',
          data: <?php echo json_encode($availableQuantities); ?>,
          backgroundColor: '#9b1c1c',
          borderRadius: 4,
          maxBarThickness: 36
        },
        {
          label: 'Expired (mL)',
          data: <?php echo json_encode($expiredQuantities); ?>,
          backgroundColor: '#e5e7eb',
          borderRadius: 4,
          maxBarThickness: 36
        }
      ]
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      plugins: {
        legend: { position: 'top', labels: { color: '#374151', font: { family: 'Inter', size: 13 } } },
        tooltip: { backgroundColor: '#111827', titleFont: { family: 'Inter' }, bodyFont: { family: 'Inter' } }
      },
      scales: {
        x: { grid: { display: false }, ticks: { color: '#6b7280', font: { family: 'Inter' } } },
        y: {
          beginAtZero: true,
          grid: { color: '#f1f5f9' },
          ticks: { color: '#6b7280', font: { family: 'Inter' } },
          title: { display: true, text: 'Quantity (mL)', color: '#6b7280', font: { family: 'Inter', size: 12 } }
        }
      }
    }
  });
</script>

</body>
</html>
