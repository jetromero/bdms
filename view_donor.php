<?php
ob_start();
require_once __DIR__ . '/require_login.php';
require_once __DIR__ . '/bdms_nav.php';
require_once __DIR__ . "/db.php";

if (isset($_GET['id'])) {
    $donor_id = (int) $_GET['id'];

    $sql = "SELECT * FROM donors WHERE id = " . $donor_id;
    $result = $conn->query($sql);

    if ($result->num_rows > 0) {
        $donor = $result->fetch_assoc();
    } else {
        echo "No donor found with this ID.";
        exit;
    }

    $donationHistoryRows = [];
    $histTable = $conn->query("SHOW TABLES LIKE 'donations'");
    if ($histTable && $histTable->num_rows > 0) {
        $hStmt = $conn->prepare(
            'SELECT donation_date, blood_type, quantity_ml, eligibility_status, created_at
             FROM donations WHERE donor_id = ? ORDER BY donation_date DESC, id DESC'
        );
        if ($hStmt) {
            $hStmt->bind_param('i', $donor_id);
            $hStmt->execute();
            $hRes = $hStmt->get_result();
            while ($r = $hRes->fetch_assoc()) {
                $donationHistoryRows[] = $r;
            }
            $hStmt->close();
        }
    }
} else {
    echo "No donor ID provided.";
    exit;
}

$conn->close();
ob_end_flush();

$fields = [
    ['label' => 'Full name',          'icon' => 'fa-user',           'key' => 'name'],
    ['label' => 'Birth date',         'icon' => 'fa-calendar',       'key' => 'birthdate'],
    ['label' => 'Age',                'icon' => 'fa-hourglass-half', 'key' => 'age'],
    ['label' => 'Gender',             'icon' => 'fa-venus-mars',     'key' => 'gender'],
    ['label' => 'Civil status',       'icon' => 'fa-heart',          'key' => 'civil_status'],
    ['label' => 'Classification',     'icon' => 'fa-tag',            'key' => 'classification'],
    ['label' => 'Blood type',         'icon' => 'fa-droplet',        'key' => 'blood_type'],
    ['label' => 'Donation history',   'icon' => 'fa-clock-rotate-left','key' => 'donation_history'],
    ['label' => 'Contact number',     'icon' => 'fa-phone',          'key' => 'contact_number'],
    ['label' => 'Email address',      'icon' => 'fa-envelope',       'key' => 'email'],
    ['label' => 'Address',            'icon' => 'fa-location-dot',   'key' => 'address'],
    ['label' => 'Blood quantity (mL)','icon' => 'fa-flask',          'key' => 'blood_quantity'],
    ['label' => 'Collection date',    'icon' => 'fa-calendar-day',   'key' => 'collection_date'],
    ['label' => 'Type of donation',   'icon' => 'fa-hand-holding-medical','key' => 'donation_type'],
    ['label' => 'Location of donation','icon' => 'fa-map-pin',        'key' => 'donation_location'],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/png" href="evsulogo.png">
    <title>Donor Profile | BDMS</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="assets/bdms.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <?php bdms_profile_bar_print_styles(); ?>
    <style>
      .donor-fields {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
        gap: 12px 24px;
      }
      .donor-field {
        display: grid;
        grid-template-columns: 22px 140px 1fr;
        align-items: center;
        gap: 10px;
        padding: 8px 0;
        border-bottom: 1px solid var(--border);
        font-size: 13.5px;
      }
      .donor-field:last-child { border-bottom: 0; }
      .donor-field i { color: var(--brand); font-size: 13px; text-align: center; }
      .donor-field .label { color: var(--text-muted); }
      .donor-field .value { color: var(--text); font-weight: 500; word-break: break-word; }
    </style>
</head>
<body class="app app-content-slate">

  <?php bdms_nav_render('Donor Profile'); ?>

  <main class="page" id="page">
    <div class="page-header">
      <div>
        <h1 class="page-title"><i class="fas fa-id-card"></i> <?php echo htmlspecialchars((string) $donor['name']); ?></h1>
        <p class="page-sub">
          Donor #<?php echo (int) $donor_id; ?> ·
          <span class="badge badge-admin"><?php echo htmlspecialchars((string) $donor['blood_type']); ?></span>
          <?php if (!empty($donor['donation_status'])): ?>
            <span class="badge <?php echo $donor['donation_status'] === 'Active' ? 'badge-active' : 'badge-off'; ?>"><?php echo htmlspecialchars((string) $donor['donation_status']); ?></span>
          <?php endif; ?>
        </p>
      </div>
      <div class="flex gap-3">
        <a href="donors_list.php" class="btn btn-outline"><i class="fas fa-arrow-left"></i> Back</a>
        <a href="edit_donor.php?id=<?php echo (int) $donor_id; ?>" class="btn btn-primary"><i class="fas fa-user-pen"></i> Edit</a>
        <a href="record_donation.php?donor_id=<?php echo (int) $donor_id; ?>" class="btn btn-primary"><i class="fas fa-droplet"></i> Record donation</a>
      </div>
    </div>

    <div class="card">
      <div class="card-title"><i class="fas fa-circle-info"></i> Profile</div>
      <div class="donor-fields">
        <?php foreach ($fields as $f): ?>
          <div class="donor-field">
            <i class="fas <?php echo $f['icon']; ?>"></i>
            <span class="label"><?php echo $f['label']; ?></span>
            <span class="value"><?php echo htmlspecialchars((string) ($donor[$f['key']] ?? '—')); ?></span>
          </div>
        <?php endforeach; ?>
      </div>
    </div>

    <?php if (!empty($donationHistoryRows)): ?>
    <div class="card mt-4">
      <div class="card-title"><i class="fas fa-clock-rotate-left"></i> Donation history</div>
      <div class="table-wrap" style="border:0; box-shadow:none;">
        <table>
          <thead>
            <tr>
              <th>Date</th>
              <th>Blood type</th>
              <th>Quantity (mL)</th>
              <th>Eligibility</th>
              <th>Recorded</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($donationHistoryRows as $h): ?>
              <tr>
                <td><?php echo htmlspecialchars((string) $h['donation_date']); ?></td>
                <td><?php echo htmlspecialchars((string) $h['blood_type']); ?></td>
                <td><?php echo (int) $h['quantity_ml']; ?></td>
                <td><?php echo htmlspecialchars((string) $h['eligibility_status']); ?></td>
                <td class="muted"><?php echo htmlspecialchars((string) $h['created_at']); ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
    <?php endif; ?>
  </main>

  <?php if (isset($_GET['recorded'])): ?>
  <script>
    Swal.fire({
      icon: 'success',
      title: 'Donation recorded',
      text: 'Inventory and the donor profile have been updated.',
      confirmButtonColor: '#9b1c1c'
    });
    if (window.history.replaceState) {
      const u = new URL(window.location.href);
      u.searchParams.delete('recorded');
      window.history.replaceState({}, '', u.pathname + u.search);
    }
  </script>
  <?php endif; ?>

  <script src="assets/bdms.js"></script>
</body>
</html>
