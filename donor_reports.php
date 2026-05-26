<?php
require_once __DIR__ . '/require_login.php';
require_once __DIR__ . '/bdms_nav.php';
require_once __DIR__ . "/db.php";

$selected_month = isset($_POST['month']) ? $_POST['month'] : 'all';

$sql = "SELECT
            name,
            contact_number,
            email,
            age,
            gender,
            blood_type,
            collection_date,
            civil_status AS status,
            COUNT(*) AS donation_count,
            CASE
                WHEN COUNT(*) = 1 THEN 'Once'
                WHEN COUNT(*) > 1 THEN 'Multiple Times'
                ELSE 'Not Available'
            END AS donation_frequency
        FROM donors";

if ($selected_month !== 'all') {
    $sql .= " WHERE MONTH(collection_date) = '$selected_month'";
}

$sql .= " GROUP BY
            name,
            contact_number,
            email,
            age,
            gender,
            blood_type,
            civil_status,
            collection_date";

$result = $conn->query($sql);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/png" href="evsulogo.png">
    <title>Donor Report | BDMS</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="assets/bdms.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <?php bdms_profile_bar_print_styles(); ?>
    <style>
      .contact-list { display: flex; flex-direction: column; gap: 2px; font-size: 12.5px; }
      .contact-list i { color: var(--brand); margin-right: 6px; }
      .name-cell { display: inline-flex; align-items: center; gap: 8px; }
      .female-icon { color: #db2777; }
      .male-icon { color: #2563eb; }
      .signature-print { display: none; margin-top: 36px; font-weight: 600; text-align: right; }
      @media print {
        .appbar, .button-wrapper, .no-print { display: none !important; }
        body { background: #fff; padding: 0; }
        .page { padding: 0; }
        .signature-print { display: block; color: var(--brand-strong); }
      }
    </style>
</head>
<body class="app">

<?php bdms_nav_render('Donor Report'); ?>

<main class="page" id="page">
  <div class="page-header">
    <div>
      <h1 class="page-title"><i class="fas fa-droplet"></i> Donor report</h1>
      <p class="page-sub">Donor activity grouped by month. Use the new <a href="reports.php">Reports</a> page for filtered CSV/PDF export.</p>
    </div>
    <div class="flex gap-2 no-print">
      <form method="POST" style="display:inline-flex; align-items:center; gap:8px;">
        <label for="month" style="margin:0; font-size:13px; color: var(--text-soft);"><i class="fas fa-calendar"></i> Month</label>
        <select name="month" id="month" onchange="this.form.submit()" style="max-width: 180px;">
          <option value="all" <?php if ($selected_month == 'all') echo 'selected'; ?>>All months</option>
          <?php
          for ($m = 1; $m <= 12; $m++) {
              $selected = ($selected_month == $m) ? 'selected' : '';
              $monthName = date('F', mktime(0, 0, 0, $m, 10));
              echo "<option value='$m' $selected>$monthName</option>";
          }
          ?>
        </select>
      </form>
      <button class="btn btn-ghost" onclick="window.print();"><i class="fas fa-print"></i> Print</button>
    </div>
  </div>

  <div class="table-wrap">
    <table>
      <thead>
        <tr>
          <th>Name</th>
          <th>Contact</th>
          <th>Age</th>
          <th>Gender</th>
          <th>Blood</th>
          <th>Date(s)</th>
          <th>Donations</th>
          <th>Status</th>
          <th>Eligibility</th>
          <th>Frequency</th>
        </tr>
      </thead>
      <tbody>
        <?php
        if ($result && $result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                $phone = !empty($row["contact_number"]) ? $row["contact_number"] : "Not provided";
                $email = !empty($row["email"]) ? $row["email"] : "Not provided";
                $iconColor = $row["gender"] === "Female" ? "female-icon" : "male-icon";
                $formatted_date = date("m-d-Y", strtotime($row["collection_date"]));

                echo "<tr>";
                echo "<td><span class='name-cell'><i class='fas fa-user $iconColor'></i><strong>" . htmlspecialchars($row["name"]) . "</strong></span></td>";
                echo "<td><div class='contact-list'>
                          <span><i class='fas fa-phone'></i>" . htmlspecialchars($phone) . "</span>
                          <span class='muted'><i class='fas fa-envelope'></i>" . htmlspecialchars($email) . "</span>
                        </div></td>";
                echo "<td>" . htmlspecialchars((string) $row["age"]) . "</td>";
                echo "<td>" . htmlspecialchars((string) $row["gender"]) . "</td>";
                echo "<td><span class='badge badge-admin'>" . htmlspecialchars((string) $row["blood_type"]) . "</span></td>";
                echo "<td class='muted'>" . htmlspecialchars($formatted_date) . "</td>";
                echo "<td>" . (int) $row["donation_count"] . "</td>";
                echo "<td>" . htmlspecialchars((string) $row["status"]) . "</td>";
                echo "<td>" . ($row["donation_count"] > 0 ? "Eligible" : "Not yet eligible") . "</td>";
                echo "<td>" . htmlspecialchars((string) $row["donation_frequency"]) . "</td>";
                echo "</tr>";
            }
        } else {
            echo '<tr><td colspan="10" class="muted" style="text-align:center;padding:24px;">No donor data available.</td></tr>';
        }
        $conn->close();
        ?>
      </tbody>
    </table>
  </div>

  <div class="signature-print">Organizer: Mr. Bernie Palacio</div>
</main>

<script src="assets/bdms.js"></script>
</body>
</html>
