<?php
declare(strict_types=1);

ob_start();
require_once __DIR__ . '/require_login.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/donor_helpers.php';

// Make MySQLi throw exceptions on errors so we can surface the real cause.
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$showAlert = '';
$validationErrors = [];
$lastDbError = '';

// Pre-flight schema checks so we can show a precise fix message.
$schemaIssues = [];

$hasColumns = static function (mysqli $conn, string $table, array $columns): array {
    $missing = [];
    $in = "'" . implode("','", array_map(static fn ($c) => $conn->real_escape_string((string) $c), $columns)) . "'";
    $sql = "
        SELECT COLUMN_NAME
        FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = '" . $conn->real_escape_string($table) . "'
          AND COLUMN_NAME IN ($in)
    ";
    $res = $conn->query($sql);
    $found = [];
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $found[(string) $row['COLUMN_NAME']] = true;
        }
    }
    foreach ($columns as $c) {
        if (!isset($found[$c])) {
            $missing[] = $c;
        }
    }
    return $missing;
};

$donationsCheck = $conn->query("SHOW TABLES LIKE 'donations'");
if (!($donationsCheck && $donationsCheck->num_rows > 0)) {
    $schemaIssues[] = 'Missing table: donations (run schema_us02.sql)';
}

$auditCheck = $conn->query("SHOW TABLES LIKE 'audit_log'");
if (!($auditCheck && $auditCheck->num_rows > 0)) {
    $schemaIssues[] = 'Missing table: audit_log (run schema_us02.sql)';
}

// Columns required by this page.
$missingDonorsCols = $hasColumns($conn, 'donors', [
    'id',
    'blood_type',
    'blood_quantity',
    'collection_date',
    'donation_date',
    'donation_status',
    'donation_history',
    'donation_dates',
    'number_of_donations',
    'medical_eligibility',
]);
if ($missingDonorsCols) {
    $schemaIssues[] = 'Missing columns in donors: ' . implode(', ', $missingDonorsCols) . ' (import/apply latest bdms.sql)';
}

$missingInvCols = $hasColumns($conn, 'blood_inventory', [
    'id',
    'blood_type',
    'quantity',
    'status',
    'donated_by',
]);
if ($missingInvCols) {
    $schemaIssues[] = 'Missing columns in blood_inventory: ' . implode(', ', $missingInvCols) . ' (import/apply latest bdms.sql)';
}

// `blood_inventory.id` must be AUTO_INCREMENT because inserts omit `id`.
$invIdOk = false;
$invIdRes = $conn->query("
    SELECT EXTRA
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'blood_inventory'
      AND COLUMN_NAME = 'id'
    LIMIT 1
");
if ($invIdRes && ($r = $invIdRes->fetch_assoc())) {
    $invIdOk = stripos((string)$r['EXTRA'], 'auto_increment') !== false;
}
if (!$invIdOk) {
    $schemaIssues[] = 'Column blood_inventory.id is not AUTO_INCREMENT (import/apply bdms.sql ALTER TABLE for blood_inventory)';
}

$schemaOk = empty($schemaIssues);

$donorsList = $conn->query(
    'SELECT id, name, blood_type, contact_number, email FROM donors ORDER BY name ASC'
);
$donorsRows = [];
if ($donorsList) {
    while ($row = $donorsList->fetch_assoc()) {
        $donorsRows[] = $row;
    }
}

$preselectId = isset($_GET['donor_id']) ? (int) $_GET['donor_id'] : 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$schemaOk) {
        $showAlert = 'schema';
    } else {
        $check = donor_validate_donation_inputs($_POST);
        if (!$check['ok']) {
            $validationErrors = $check['errors'];
            $showAlert = 'validation';
        } else {
            $donorId = (int) $_POST['donor_id'];
            $bloodType = (string) $_POST['blood_type'];
            $quantityMl = (int) $_POST['quantity_ml'];
            $donationDate = trim((string) $_POST['donation_date']);
            $eligibility = (string) $_POST['eligibility_status'];
            $performedBy = isset($_SESSION['user']) ? (string) $_SESSION['user'] : 'staff';

            $sel = $conn->prepare('SELECT id, donation_history, donation_dates, number_of_donations FROM donors WHERE id = ? FOR UPDATE');
            if ($sel === false) {
                $lastDbError = $conn->error;
                $showAlert = 'error';
            } else {
                $sel->bind_param('i', $donorId);
                $conn->begin_transaction();
                try {
                    $sel->execute();
                    $res = $sel->get_result();
                    $donorRow = $res->fetch_assoc();
                    $sel->close();
                    if (!$donorRow) {
                        throw new RuntimeException('Donor not found.');
                    }

                    $prevHist = (string) ($donorRow['donation_history'] ?? '');
                    $prevDates = $donorRow['donation_dates'];
                    $prevCount = (int) ($donorRow['number_of_donations'] ?? 0);

                    $newDates = ($prevDates === null || trim((string) $prevDates) === '')
                        ? $donationDate
                        : trim((string) $prevDates) . ', ' . $donationDate;

                    $newCount = $prevCount + 1;
                    $eligibleForStock = $eligibility === 'Eligible';

                    $newHist = $prevHist;
                    if ($eligibleForStock && $prevHist === 'First Time') {
                        $newHist = 'Regular Donor';
                    }

                    $insDon = $conn->prepare(
                        'INSERT INTO donations (donor_id, donation_date, blood_type, quantity_ml, eligibility_status)
                         VALUES (?, ?, ?, ?, ?)'
                    );
                    if ($insDon === false) {
                        throw new RuntimeException('Prepare failed.');
                    }
                    $insDon->bind_param(
                        'isiss',
                        $donorId,
                        $donationDate,
                        $bloodType,
                        $quantityMl,
                        $eligibility
                    );
                    $insDon->execute();
                    $donationPk = (int) $conn->insert_id;
                    $insDon->close();

                    if ($eligibleForStock) {
                        $upd = $conn->prepare(
                            'UPDATE donors SET
                                blood_type = ?,
                                blood_quantity = blood_quantity + ?,
                                quantity_ml = ?,
                                blood_quantity_ml = ?,
                                collection_date = ?,
                                donation_date = ?,
                                donation_status = \'Active\',
                                number_of_donations = ?,
                                donation_dates = ?,
                                donation_history = ?,
                                medical_eligibility = ?
                             WHERE id = ?'
                        );
                        if ($upd === false) {
                            throw new RuntimeException('Prepare failed.');
                        }
                        $upd->bind_param(
                            'siiississsi',
                            $bloodType,
                            $quantityMl,
                            $quantityMl,
                            $quantityMl,
                            $donationDate,
                            $donationDate,
                            $newCount,
                            $newDates,
                            $newHist,
                            $eligibility,
                            $donorId
                        );
                        $upd->execute();
                        $upd->close();

                        $inv = $conn->prepare(
                            'INSERT INTO blood_inventory (blood_type, quantity, status, donated_by)
                             VALUES (?, ?, \'Available\', ?)'
                        );
                        if ($inv === false) {
                            throw new RuntimeException('Prepare failed.');
                        }
                        $inv->bind_param('sii', $bloodType, $quantityMl, $donorId);
                        $inv->execute();
                        $inv->close();
                    } else {
                        $upd = $conn->prepare(
                            'UPDATE donors SET
                                blood_type = ?,
                                donation_date = ?,
                                number_of_donations = ?,
                                donation_dates = ?,
                                donation_history = ?,
                                medical_eligibility = ?
                             WHERE id = ?'
                        );
                        if ($upd === false) {
                            throw new RuntimeException('Prepare failed.');
                        }
                        $upd->bind_param(
                            'ssisssi',
                            $bloodType,
                            $donationDate,
                            $newCount,
                            $newDates,
                            $newHist,
                            $eligibility,
                            $donorId
                        );
                        $upd->execute();
                        $upd->close();
                    }

                    $details = json_encode(
                        [
                            'donation_id' => $donationPk,
                            'donor_id' => $donorId,
                            'quantity_ml' => $quantityMl,
                            'blood_type' => $bloodType,
                            'donation_date' => $donationDate,
                            'inventory_updated' => $eligibleForStock,
                        ],
                        JSON_UNESCAPED_UNICODE
                    );
                    $action = 'record_donation';
                    $entityType = 'donation';
                    $aud = $conn->prepare(
                        'INSERT INTO audit_log (action, entity_type, entity_id, details, performed_by)
                         VALUES (?, ?, ?, ?, ?)'
                    );
                    if ($aud === false) {
                        throw new RuntimeException('Prepare failed.');
                    }
                    $aud->bind_param('ssiss', $action, $entityType, $donationPk, $details, $performedBy);
                    $aud->execute();
                    $aud->close();

                    $conn->commit();
                    header('Location: view_donor.php?id=' . $donorId . '&recorded=1');
                    exit;
                } catch (Throwable $e) {
                    $conn->rollback();
                    $lastDbError = $e->getMessage();
                    $showAlert = 'error';
                }
            }
        }
    }
}

$conn->close();
ob_end_flush();

require_once __DIR__ . '/bdms_nav.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link rel="icon" type="image/png" href="evsulogo.png">
  <title>Record Donation | BDMS</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <link rel="stylesheet" href="assets/bdms.css">
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
  <?php bdms_profile_bar_print_styles(); ?>
  <style>
    .donation-layout {
      display: flex;
      align-items: stretch;
      gap: 18px;
    }
    .donation-layout > .card:first-child {
      flex: 3 1 0;
      min-width: 0;
      display: flex;
      flex-direction: column;
    }
    .donation-layout > .card:last-child {
      flex: 1 1 0;
      min-width: 0;
      display: flex;
      flex-direction: column;
    }
    .donation-layout > .card + .card {
      margin-top: 0;
    }
    @media (max-width: 900px) {
      .donation-layout {
        display: grid;
        grid-template-columns: 1fr;
        height: auto;
        padding-bottom: 20px;
      }
      .donation-layout > .card {
        min-height: 640px;
      }
      .donation-layout > .card:last-child {
        margin-bottom: 20px;
      }
    }
    .donor-pick-wrap {
      max-height: 520px;
      overflow-y: auto;
      border: 1px solid var(--border);
      border-radius: var(--radius-sm);
    }
    .donation-field-compact {
      margin-bottom: 0 !important;
    }
    /* Fix both cards to the same height so layout stays consistent */
    .donation-layout {
      height: 580px;
    }
    .donation-layout > .card {
      height: 100%;
      display: flex;
      flex-direction: column;
      overflow: hidden;
    }
    /* Reduce spacing between fields in the donation details card */
    .donation-layout > .card:last-child .form-group-full {
      margin-bottom: 8px;
    }
    /* Push the action buttons to the bottom and keep leftover space below them */
    .donation-layout > .card:last-child .flex {
      margin-top: auto !important;
      padding-bottom: 18px;
    }
    @media (max-width: 900px) {
      .page {
        padding-bottom: 48px;
      }
      .donation-layout {
        height: auto;
        padding-bottom: 12px;
      }
      .donation-layout > .card {
        height: auto;
      }
      .donation-layout > .card:last-child {
        margin-bottom: 24px;
      }
    }
    .donor-pick-wrap table { font-size: 13.5px; }
    .donor-pick-wrap td { cursor: pointer; }
    #selectedDonorLabel { margin-top: 10px; font-size: 13px; color: var(--text-soft); }
    #selectedDonorLabel strong { color: var(--brand); }
  </style>
</head>
<body class="app app-content-slate">

<?php bdms_nav_render('Record Donation'); ?>

<main class="page" id="page">
  <div class="page-header">
    <div>
      <h1 class="page-title"><i class="fas fa-droplet"></i> Record donation</h1>
      <p class="page-sub">Pick a donor, fill in donation details. Inventory and donor profile update automatically.</p>
    </div>
  </div>

  <?php if (!$schemaOk): ?>
    <div class="alert alert-warning">
      <i class="fas fa-triangle-exclamation"></i>
      <div><strong>Database update required.</strong> Run <code>schema_us02.sql</code> on the <code>bdms</code> database, then reload this page.</div>
    </div>
  <?php endif; ?>

  <form method="post" action="" id="donationForm">
    <input type="hidden" name="donor_id" id="donor_id" value="<?php echo $preselectId > 0 ? (int) $preselectId : ''; ?>">

    <div class="donation-layout">
      <div class="card">
        <div class="card-title"><i class="fas fa-users"></i> Select donor</div>
        <p class="card-sub">Click a row to choose a donor.</p>
        <div class="search mb-3" style="max-width:none;">
          <i class="fas fa-magnifying-glass"></i>
          <input type="search" id="searchInput" placeholder="Search by name, email, blood type…" autocomplete="off">
        </div>
        <div class="donor-pick-wrap">
          <table id="donorTable">
            <thead>
              <tr>
                <th style="width:60px;">ID</th>
                <th>Name</th>
                <th style="width:70px;">Type</th>
                <th>Contact</th>
              </tr>
            </thead>
            <tbody id="donorTableBody">
              <?php foreach ($donorsRows as $dr): ?>
                <tr data-id="<?php echo (int) $dr['id']; ?>"
                    data-name="<?php echo htmlspecialchars((string) $dr['name'], ENT_QUOTES, 'UTF-8'); ?>">
                  <td class="muted">#<?php echo (int) $dr['id']; ?></td>
                  <td><strong><?php echo htmlspecialchars((string) $dr['name']); ?></strong></td>
                  <td><span class="badge badge-admin"><?php echo htmlspecialchars((string) $dr['blood_type']); ?></span></td>
                  <td class="muted"><?php echo htmlspecialchars((string) $dr['contact_number']); ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <p id="selectedDonorLabel"><?php if ($preselectId <= 0): ?><span class="hint">No donor selected.</span><?php endif; ?></p>
      </div>

      <div class="card">
        <div class="card-title"><i class="fas fa-clipboard-list"></i> Donation details</div>
        <p class="card-sub">Verify the donor's blood type and quantity before saving.</p>

        <div class="form-group-full mb-3 donation-date-field donation-field-compact">
          <label for="donation_date">Donation date</label>
          <input type="date" name="donation_date" id="donation_date" required
            value="<?php echo htmlspecialchars(bdms_today_ymd(), ENT_QUOTES, 'UTF-8'); ?>"
            max="<?php echo htmlspecialchars(bdms_today_ymd(), ENT_QUOTES, 'UTF-8'); ?>">
        </div>

        <div class="form-group-full mb-3 donation-field-compact">
          <label for="blood_type">Blood type (verified)</label>
          <select name="blood_type" id="blood_type" required>
            <option value="" hidden>Select</option>
            <?php foreach (['A+','A-','B+','B-','AB+','AB-','O+','O-'] as $bt): ?>
              <option value="<?php echo $bt; ?>"><?php echo $bt; ?></option>
            <?php endforeach; ?>
          </select>
          <p class="hint mt-2">Defaults to the donor's registered type when you select them.</p>
        </div>

        <div class="form-group-full mb-3">
          <label for="quantity_ml">Quantity (mL)</label>
          <input type="number" name="quantity_ml" id="quantity_ml" min="1" max="600" value="450" required>
        </div>

        <div class="form-group-full mb-3">
          <label for="eligibility_status">Eligibility status</label>
          <select name="eligibility_status" id="eligibility_status" required>
            <option value="Eligible">Eligible</option>
            <option value="Temporarily Deferred">Temporarily deferred</option>
            <option value="Not Eligible">Not eligible</option>
          </select>
        </div>

        <div class="flex gap-2" style="margin-top: 16px; flex-wrap: wrap;">
          <button type="submit" class="btn btn-primary" <?php echo !$schemaOk ? 'disabled' : ''; ?>>
            <i class="fas fa-floppy-disk"></i> Save donation
          </button>
          <a href="donors_list.php" class="btn btn-ghost"><i class="fas fa-users"></i> Donors list</a>
        </div>
      </div>
    </div>
  </form>
</main>

<script src="assets/bdms.js?v=20260527"></script>
<script>
(function () {
  const donorsMeta = <?php echo json_encode(
      array_map(static function ($r) {
          return [
              'id' => (int) $r['id'],
              'blood_type' => $r['blood_type'],
          ];
      }, $donorsRows),
      JSON_UNESCAPED_UNICODE
  ); ?>;

  const preselect = <?php echo (int) $preselectId; ?>;
  const hidden = document.getElementById('donor_id');
  const bloodSel = document.getElementById('blood_type');
  const label = document.getElementById('selectedDonorLabel');

  function setSelected(id, name) {
    hidden.value = id;
    const meta = donorsMeta.find(function (d) { return d.id === id; });
    if (meta && bloodSel) {
      const bt = String(meta.blood_type || '').trim();
      bloodSel.value = bt;
      if (bt && !Array.from(bloodSel.options).some(function (o) { return o.value === bt; })) {
        bloodSel.value = '';
      }
    }
    label.innerHTML = 'Selected: <strong>' + name.replace(/</g, '&lt;') + '</strong> (ID ' + id + ')';
    document.querySelectorAll('#donorTableBody tr').forEach(function (tr) {
      tr.classList.toggle('selected', parseInt(tr.getAttribute('data-id'), 10) === id);
    });
  }

  document.getElementById('donorTableBody').addEventListener('click', function (e) {
    const tr = e.target.closest('tr');
    if (!tr) return;
    const id = parseInt(tr.getAttribute('data-id'), 10);
    const name = tr.getAttribute('data-name') || '';
    setSelected(id, name);
  });

  document.getElementById('searchInput').addEventListener('input', function () {
    const q = this.value.toLowerCase().trim();
    document.querySelectorAll('#donorTableBody tr').forEach(function (tr) {
      const t = tr.innerText.toLowerCase();
      tr.style.display = !q || t.includes(q) ? '' : 'none';
    });
  });

  if (preselect > 0) {
    const row = document.querySelector('#donorTableBody tr[data-id="' + preselect + '"]');
    if (row) {
      setSelected(preselect, row.getAttribute('data-name') || '');
    }
  }

  let allowSubmit = false;
  document.getElementById('donationForm').addEventListener('submit', function (e) {
    if (allowSubmit) {
      return;
    }

    if (!hidden.value) {
      e.preventDefault();
      Swal.fire({
        icon: 'warning',
        title: 'Select a donor',
        text: 'Please choose a donor from the list.',
        confirmButtonColor: '#9b1c1c'
      });
      return;
    }

    e.preventDefault();
    const form = this;

    if (typeof Swal === 'undefined') {
      const shouldSubmit = window.confirm('Are you sure you want to save this donation?');
      if (shouldSubmit) {
        allowSubmit = true;
        form.submit();
      }
      return;
    }

    Swal.fire({
      icon: 'question',
      title: 'Save donation record?',
      text: 'Please confirm before adding this donation data.',
      showCancelButton: true,
      confirmButtonText: 'Yes, save',
      cancelButtonText: 'No, cancel',
      confirmButtonColor: '#9b1c1c',
      cancelButtonColor: '#6b7280',
      reverseButtons: true
    }).then(function (result) {
      if (result.isConfirmed) {
        allowSubmit = true;
        form.submit();
      }
    });
  });
})();

<?php if ($showAlert === 'validation' && $validationErrors): ?>
Swal.fire({
  icon: 'error',
  title: 'Check the form',
  html: <?php echo json_encode('<ul style="text-align:left">' . implode('', array_map(static function ($e) {
      return '<li>' . htmlspecialchars($e, ENT_QUOTES, 'UTF-8') . '</li>';
  }, $validationErrors)) . '</ul>'); ?>,
  confirmButtonColor: '#9b1c1c'
});
<?php elseif ($showAlert === 'schema'): ?>
Swal.fire({
  icon: 'warning',
  title: 'Database update required',
  html: <?php echo json_encode('<ul style="text-align:left">' . implode('', array_map(static function ($e) {
      return '<li>' . htmlspecialchars($e, ENT_QUOTES, 'UTF-8') . '</li>';
  }, $schemaIssues)) . '</ul>'); ?>,
  confirmButtonColor: '#9b1c1c'
});
<?php elseif ($showAlert === 'error'): ?>
Swal.fire({
  icon: 'error',
  title: 'Could not save',
  html: <?php echo json_encode(
      'Please try again.<br><br><strong>Details:</strong><br><code style="white-space:pre-wrap">' .
      htmlspecialchars($lastDbError ?: 'Unknown database error', ENT_QUOTES, 'UTF-8') .
      '</code>',
      JSON_UNESCAPED_UNICODE
  ); ?>,
  confirmButtonColor: '#9b1c1c'
});
<?php endif; ?>
</script>
</body>
</html>
