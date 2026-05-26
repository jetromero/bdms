<?php
/**
 * Register donors (staff may add; only administrators may delete donor records).
 */
ob_start();
require_once __DIR__ . '/require_login.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/bdms_nav.php';
require_once __DIR__ . '/bdms_audit_log_helpers.php';
require_once __DIR__ . "/donor_helpers.php";

$showAlert = "";

$validationErrors = [];
$duplicateDonorId = null;

if ($_SERVER["REQUEST_METHOD"] === "POST") {
  $check = donor_validate_registration_inputs($_POST);
  if (!$check["ok"]) {
    $validationErrors = $check["errors"];
    $showAlert = "validation";
  } else {
    $name = trim((string) $_POST["name"]);
    $birthdate = trim((string) $_POST["birthdate"]);
    $address = trim((string) $_POST["address"]);
    $blood_type = (string) $_POST["blood_type"];
    $civil_status = (string) $_POST["civil_status"];
    $donation_history = (string) $_POST["donation_history"];
    $classification = (string) $_POST["classification"];
    $contact_number = (string) $_POST["contact_number"];
    $gender = (string) $_POST["gender"];
    $blood_quantity = (int) $_POST["blood_quantity"];
    $collection_date = trim((string) $_POST["collection_date"]);
    $email = trim((string) $_POST["email"]);
    $donation_type = (string) $_POST["donation_type"];
    $donation_location = (string) $_POST["donation_location"];

    $birthdateObj = DateTimeImmutable::createFromFormat("Y-m-d", $birthdate);
    $birthYmd = $birthdateObj !== false ? $birthdateObj->format("Y-m-d") : $birthdate;
    $current_date = new DateTimeImmutable();
    $age = $birthdateObj !== false ? $current_date->diff($birthdateObj)->y : 0;

    $dupId = donor_find_duplicate_id($conn, $email, $contact_number, $birthYmd);
    if ($dupId !== null) {
      $showAlert = "duplicate";
      $duplicateDonorId = $dupId;
    } else {
      $donation_status = "Active";
      $initialDonationCount = 1;
      $sql = "INSERT INTO donors (
        name, birthdate, address, blood_type, civil_status, donation_history,
        classification, contact_number, gender, blood_quantity, collection_date,
        email, age, donation_type, donation_location, donation_status, number_of_donations
      ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

      $stmt = $conn->prepare($sql);
      if ($stmt === false) {
        $showAlert = "error";
      } else {
        $stmt->bind_param(
          "sssssssssississsi",
          $name, $birthYmd, $address, $blood_type, $civil_status, $donation_history,
          $classification, $contact_number, $gender, $blood_quantity, $collection_date,
          $email, $age, $donation_type, $donation_location, $donation_status, $initialDonationCount
        );
        if ($stmt->execute()) {
          $newDonorId = (int) $conn->insert_id;
          bdms_audit_log_insert($conn, 'add_donor', 'New donor', $newDonorId, [
            'donor_id' => $newDonorId,
            'name' => $name,
            'blood_type' => $blood_type,
            'email' => $email,
            'classification' => $classification,
          ]);
          if (!empty($_POST['_from_modal'])) {
            $conn->close();
            header('Location: donors_list.php?added=1&new_id=' . $newDonorId);
            exit();
          }
          $showAlert = "success";
        } else {
          $showAlert = "error";
        }
        $stmt->close();
      }
    }
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
  <title>Add Donor | BDMS</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <link rel="stylesheet" href="assets/bdms.css">
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
  <?php bdms_profile_bar_print_styles(); ?>
</head>
<body class="app">

  <?php bdms_nav_render('Add Donor'); ?>

  <main class="page" id="page">
    <div class="page-header">
      <div>
        <h1 class="page-title"><i class="fas fa-user-plus"></i> Register new donor</h1>
        <p class="page-sub">Fill in the donor profile. Duplicates are detected by email or contact + birth date.</p>
      </div>
      <div class="flex gap-2">
        <button type="button" class="btn btn-ghost" onclick="clearForm();">
          <i class="fas fa-eraser"></i> Clear
        </button>
        <a href="donors_list.php" class="btn btn-ghost"><i class="fas fa-list"></i> Donors list</a>
        <button type="submit" form="form" class="btn btn-primary"><i class="fas fa-check"></i> Save donor</button>
      </div>
    </div>

    <div class="card">
      <form id="form" method="POST" action="" class="form-grid">

        <div class="form-group col-2 col-full">
          <label>Full name</label>
          <input type="text" name="name" required>
        </div>

        <div class="form-group">
          <label>Birth date</label>
          <input type="date" name="birthdate" required>
        </div>

        <div class="form-group">
          <label>Gender</label>
          <select name="gender" required>
            <option value="" hidden>Select</option>
            <option value="Male">Male</option>
            <option value="Female">Female</option>
            <option value="Other">Other</option>
          </select>
        </div>

        <div class="form-group">
          <label>Civil status</label>
          <select name="civil_status" required>
            <option value="" hidden>Select</option>
            <option value="Single">Single</option>
            <option value="Married">Married</option>
            <option value="Widowed">Widowed</option>
          </select>
        </div>

        <div class="form-group">
          <label>Classification</label>
          <select name="classification" required>
            <option value="" hidden>Select</option>
            <option value="Student">Student</option>
            <option value="Staff">Staff</option>
            <option value="Public">Public</option>
          </select>
        </div>

        <div class="form-group">
          <label>Blood type</label>
          <select name="blood_type" required>
            <option value="" hidden>Select</option>
            <?php foreach (['A+','A-','B+','B-','AB+','AB-','O+','O-'] as $bt): ?>
              <option value="<?php echo $bt; ?>"><?php echo $bt; ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="form-group">
          <label>Donation history</label>
          <select name="donation_history" required>
            <option value="" hidden>Select</option>
            <option value="First Time">First time</option>
            <option value="Regular Donor">Regular donor</option>
            <option value="Occasional Donor">Occasional donor</option>
          </select>
        </div>

        <div class="form-group">
          <label>Contact number</label>
          <input type="text" name="contact_number" required>
        </div>

        <div class="form-group-full">
          <label>Email address</label>
          <input type="email" name="email" required>
        </div>

        <div class="form-group-full">
          <label>Address</label>
          <input type="text" name="address" required>
        </div>

        <div class="form-group">
          <label>Blood quantity (mL)</label>
          <input type="number" name="blood_quantity" required>
        </div>

        <div class="form-group">
          <label>Collection date</label>
          <input type="date" name="collection_date" required>
        </div>

        <div class="form-group">
          <label>Type of donation</label>
          <select name="donation_type" required>
            <option value="" hidden>Select</option>
            <option value="In House">In house</option>
            <option value="Walk-In/Voluntary">Walk-in / Voluntary</option>
            <option value="Replacement">Replacement</option>
            <option value="Patient-Directed">Patient-directed</option>
          </select>
        </div>

        <div class="form-group">
          <label>Location of donation</label>
          <select name="donation_location" required>
            <option value="" hidden>Select</option>
            <option value="Red Cross Area">Red Cross</option>
            <option value="School">School</option>
          </select>
        </div>

      </form>
    </div>
  </main>

  <?php if ($showAlert === "success"): ?>
    <script>
      Swal.fire({
        title: 'Donor registered',
        text: 'The new donor has been added.',
        icon: 'success',
        confirmButtonColor: '#9b1c1c'
      });
    </script>
  <?php elseif ($showAlert === "duplicate" && $duplicateDonorId !== null): ?>
    <script>
      Swal.fire({
        title: 'Duplicate donor',
        html: <?php echo json_encode(
          'A donor with this email or the same contact number and birth date is already registered (Donor ID: ' . $duplicateDonorId . ').',
          JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
        ); ?>,
        icon: 'warning',
        confirmButtonColor: '#9b1c1c'
      });
    </script>
  <?php elseif ($showAlert === "validation" && $validationErrors !== []): ?>
    <script>
      Swal.fire({
        title: 'Please check the form',
        html: <?php echo json_encode('<ul style="text-align:left;margin:0;padding-left:1.2em;">' . implode('', array_map(static function (string $e): string {
          return '<li>' . htmlspecialchars($e, ENT_QUOTES, 'UTF-8') . '</li>';
        }, $validationErrors)) . '</ul>', JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>,
        icon: 'info',
        confirmButtonColor: '#9b1c1c'
      });
    </script>
  <?php elseif ($showAlert === "error"): ?>
    <script>
      Swal.fire({
        title: 'Could not save',
        text: 'Failed to add donor.',
        icon: 'error',
        confirmButtonColor: '#9b1c1c'
      });
    </script>
  <?php endif; ?>

  <script src="assets/bdms.js"></script>
  <script>
    function clearForm() {
      Swal.fire({
        title: 'Clear the form?',
        text: 'All entered values will be reset.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Yes, clear',
        cancelButtonText: 'Cancel',
        reverseButtons: true,
        confirmButtonColor: '#9b1c1c'
      }).then(function (r) {
        if (r.isConfirmed) document.getElementById('form').reset();
      });
    }
  </script>

</body>
</html>
<?php ob_end_flush(); ?>
