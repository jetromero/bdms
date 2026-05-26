<?php
/**
 * Update low-inventory threshold values per blood type.
 */
declare(strict_types=1);

require_once __DIR__ . '/require_login.php';
require_once __DIR__ . '/bdms_nav.php';
require_once __DIR__ . '/db.php';

$messages = [];
$errors = [];

$known_blood_types = ['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'];

$create_table_sql = "
    CREATE TABLE IF NOT EXISTS blood_thresholds (
        blood_type VARCHAR(10) NOT NULL,
        threshold_ml INT NOT NULL DEFAULT 500,
        updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (blood_type)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
";
if (!$conn->query($create_table_sql)) {
    $errors[] = 'Unable to prepare threshold storage right now. Please try again.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $errors === []) {
    $selected_blood_type = isset($_POST['blood_type']) ? trim((string) $_POST['blood_type']) : '';
    $threshold_raw = isset($_POST['threshold_ml']) ? trim((string) $_POST['threshold_ml']) : '';

    if (!in_array($selected_blood_type, $known_blood_types, true)) {
        $errors[] = 'Please select a valid blood type.';
    }

    if ($threshold_raw === '' || filter_var($threshold_raw, FILTER_VALIDATE_INT) === false) {
        $errors[] = 'Threshold must be a whole number.';
    } else {
        $threshold_ml = (int) $threshold_raw;
        if ($threshold_ml < 0 || $threshold_ml > 100000) {
            $errors[] = 'Threshold must be between 0 and 100000 mL.';
        }
    }

    if ($errors === []) {
        $upsert_sql = "
            INSERT INTO blood_thresholds (blood_type, threshold_ml)
            VALUES (?, ?)
            ON DUPLICATE KEY UPDATE threshold_ml = VALUES(threshold_ml)
        ";
        $stmt = $conn->prepare($upsert_sql);
        if (!$stmt) {
            $errors[] = 'Failed to prepare update statement. Please try again.';
        } else {
            $stmt->bind_param('si', $selected_blood_type, $threshold_ml);
            if ($stmt->execute()) {
                $messages[] = 'Threshold updated for ' . $selected_blood_type . '.';
            } else {
                $errors[] = 'Failed to update threshold. Please try again.';
            }
            $stmt->close();
        }
    }
}

$threshold_map = [];
$res_thresholds = $conn->query("SELECT blood_type, threshold_ml FROM blood_thresholds");
if ($res_thresholds) {
    while ($row = $res_thresholds->fetch_assoc()) {
        $threshold_map[(string) $row['blood_type']] = (int) $row['threshold_ml'];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link rel="icon" type="image/png" href="evsulogo.png">
  <title>Thresholds | BDMS</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <link rel="stylesheet" href="assets/bdms.css">
  <?php bdms_profile_bar_print_styles(); ?>
  <style>
    .threshold-form {
      display: grid;
      grid-template-columns: 1fr 1fr auto;
      gap: 12px;
      align-items: end;
    }
    @media (max-width: 700px) {
      .threshold-form { grid-template-columns: 1fr; }
      .threshold-form button { width: 100%; }
    }
  </style>
</head>
<body class="app app-content-slate">

  <?php bdms_nav_render('Thresholds'); ?>

  <main class="page" id="page">
    <div class="page-header">
      <div>
        <h1 class="page-title"><i class="fas fa-sliders"></i> Low-inventory thresholds</h1>
        <p class="page-sub">Set the minimum available volume per blood type. Below this value triggers an alert.</p>
      </div>
    </div>

    <?php foreach ($messages as $message): ?>
      <div class="alert alert-success"><i class="fas fa-circle-check"></i><div><?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?></div></div>
    <?php endforeach; ?>

    <?php foreach ($errors as $error): ?>
      <div class="alert alert-error"><i class="fas fa-circle-exclamation"></i><div><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div></div>
    <?php endforeach; ?>

    <div class="card">
      <div class="card-title">Update threshold</div>
      <form method="post" action="update_threshold.php" class="threshold-form">
        <div>
          <label for="blood_type">Blood type</label>
          <select id="blood_type" name="blood_type" required>
            <option value="">— Choose blood type —</option>
            <?php foreach ($known_blood_types as $blood_type): ?>
              <?php $selected = (isset($_POST['blood_type']) && $_POST['blood_type'] === $blood_type) ? 'selected' : ''; ?>
              <option value="<?php echo htmlspecialchars($blood_type, ENT_QUOTES, 'UTF-8'); ?>" <?php echo $selected; ?>>
                <?php echo htmlspecialchars($blood_type, ENT_QUOTES, 'UTF-8'); ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div>
          <label for="threshold_ml">Threshold (mL)</label>
          <input id="threshold_ml" name="threshold_ml" type="number" min="0" max="100000" required
            value="<?php echo isset($_POST['threshold_ml']) ? htmlspecialchars((string) $_POST['threshold_ml'], ENT_QUOTES, 'UTF-8') : ''; ?>">
        </div>
        <button type="submit" class="btn btn-primary"><i class="fas fa-floppy-disk"></i> Save</button>
      </form>
    </div>

    <div class="card mt-4">
      <div class="card-title">Current thresholds</div>
      <div class="table-wrap" style="border:0; box-shadow:none;">
        <table>
          <thead>
            <tr>
              <th>Blood type</th>
              <th>Threshold (mL)</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($known_blood_types as $blood_type): ?>
              <tr>
                <td><span class="badge badge-admin"><?php echo htmlspecialchars($blood_type, ENT_QUOTES, 'UTF-8'); ?></span></td>
                <td><?php echo isset($threshold_map[$blood_type]) ? (int) $threshold_map[$blood_type] : 500; ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </main>

  <script src="assets/bdms.js?v=20260527"></script>
</body>
</html>
<?php $conn->close(); ?>
