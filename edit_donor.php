<?php
ob_start();
require_once __DIR__ . '/require_login.php';
require_once __DIR__ . '/bdms_nav.php';
require_once __DIR__ . '/bdms_audit_log_helpers.php';
require_once __DIR__ . "/db.php";

$showAlert = "";
$id = $_GET['id'] ?? null;

if (!$id) {
  die("Invalid donor ID.");
}

$sql = "SELECT * FROM donors WHERE id = '$id'";
$result = $conn->query($sql);

if ($result->num_rows == 0) {
  die("Donor not found.");
}

$row = $result->fetch_assoc();

if ($_SERVER["REQUEST_METHOD"] == "POST") {
  $name = $_POST["name"];
  $birthdate = $_POST["birthdate"];
  $address = $_POST["address"];
  $blood_type = $_POST["blood_type"];
  $civil_status = $_POST["civil_status"];
  $donation_history = $_POST["donation_history"];
  $classification = $_POST["classification"];
  $contact_number = $_POST["contact_number"];
  $gender = $_POST["gender"];
  $blood_quantity = $_POST["blood_quantity"];
  $collection_date = $_POST["collection_date"];
  $email = $_POST["email"];
  $donation_type = $_POST["donation_type"];
  $donation_location = $_POST["donation_location"];

  $birthdateObj = new DateTime($birthdate);
  $current_date = new DateTime();
  $age = $current_date->diff($birthdateObj)->y;

  $update_sql = "UPDATE donors SET
    name = '$name',
    birthdate = '".$birthdateObj->format('Y-m-d')."',
    address = '$address',
    blood_type = '$blood_type',
    civil_status = '$civil_status',
    donation_history = '$donation_history',
    classification = '$classification',
    contact_number = '$contact_number',
    gender = '$gender',
    blood_quantity = '$blood_quantity',
    collection_date = '$collection_date',
    email = '$email',
    age = '$age',
    donation_type = '$donation_type',
    donation_location = '$donation_location'
    WHERE id = '$id'";

  if ($conn->query($update_sql) === TRUE) {
    $donorIdInt = (int) $id;
    bdms_audit_log_insert($conn, 'update_donor', 'donor', $donorIdInt, [
      'donor_id' => $donorIdInt,
      'name' => $name,
      'blood_type' => $blood_type,
      'email' => $email,
      'classification' => $classification,
    ]);
    $showAlert = "success";
    $row = $_POST;
  } else {
    $showAlert = "error";
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
  <title>Edit Donor | BDMS</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <link rel="stylesheet" href="assets/bdms.css">
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
  <?php bdms_profile_bar_print_styles(); ?>
</head>
<body class="app app-content-slate">

  <?php bdms_nav_render('Edit Donor'); ?>

  <main class="page" id="page">
    <div class="page-header">
      <div>
        <h1 class="page-title"><i class="fas fa-user-pen"></i> Edit donor</h1>
        <p class="page-sub">Update the donor profile. Changes are recorded in the audit log.</p>
      </div>
      <div class="flex gap-3">
        <a href="donors_list.php" class="btn btn-outline"><i class="fas fa-arrow-left"></i> Back</a>
        <button type="button" id="clearBtn" class="btn btn-primary"><i class="fas fa-eraser"></i> Clear</button>
        <a href="view_donor.php?id=<?= (int) $id ?>" class="btn btn-primary"><i class="fas fa-eye"></i> View profile</a>
        <button type="submit" form="form" class="btn btn-primary"><i class="fas fa-floppy-disk"></i> Save changes</button>
      </div>
    </div>

    <div class="card">
      <form id="form" method="POST" action="" class="form-grid">

        <div class="form-group-full">
          <label>Full name</label>
          <input type="text" name="name" value="<?= htmlspecialchars((string) ($row['name'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" required>
        </div>

        <div class="form-group">
          <label>Birth date</label>
          <input type="date" name="birthdate" value="<?= htmlspecialchars((string) ($row['birthdate'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" required>
        </div>

        <div class="form-group">
          <label>Gender</label>
          <select name="gender" required>
            <?php foreach (['Male','Female','Other'] as $g): ?>
              <option value="<?= $g ?>" <?= ($row['gender'] ?? '') === $g ? 'selected' : '' ?>><?= $g ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="form-group">
          <label>Civil status</label>
          <select name="civil_status" required>
            <?php foreach (['Single','Married','Widowed'] as $status): ?>
              <option value="<?= $status ?>" <?= ($row['civil_status'] ?? '') === $status ? 'selected' : '' ?>><?= $status ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="form-group">
          <label>Classification</label>
          <select name="classification" required>
            <?php foreach (['Student','Staff','Public'] as $class): ?>
              <option value="<?= $class ?>" <?= ($row['classification'] ?? '') === $class ? 'selected' : '' ?>><?= $class ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="form-group">
          <label>Blood type</label>
          <select name="blood_type" required>
            <option value="" hidden>Select</option>
            <?php foreach (['A+','A-','B+','B-','AB+','AB-','O+','O-'] as $type): ?>
              <option value="<?= $type ?>" <?= ($row['blood_type'] ?? '') === $type ? 'selected' : '' ?>><?= $type ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="form-group">
          <label>Donation history</label>
          <select name="donation_history" required>
            <?php foreach (['First Time','Regular Donor','Occasional Donor'] as $hist): ?>
              <option value="<?= $hist ?>" <?= ($row['donation_history'] ?? '') === $hist ? 'selected' : '' ?>><?= $hist ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="form-group">
          <label>Contact number</label>
          <input type="text" name="contact_number" value="<?= htmlspecialchars((string) ($row['contact_number'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" required>
        </div>

        <div class="form-group-full">
          <label>Email address</label>
          <input type="email" name="email" value="<?= htmlspecialchars((string) ($row['email'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" required>
        </div>

        <div class="form-group-full">
          <label>Address</label>
          <input type="text" name="address" value="<?= htmlspecialchars((string) ($row['address'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" required>
        </div>

        <div class="form-group">
          <label>Blood quantity (mL)</label>
          <input type="number" name="blood_quantity" value="<?= htmlspecialchars((string) ($row['blood_quantity'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" required>
        </div>

        <div class="form-group">
          <label>Collection date</label>
          <input type="date" name="collection_date" value="<?= htmlspecialchars((string) ($row['collection_date'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" required>
        </div>

        <div class="form-group">
          <label>Type of donation</label>
          <select name="donation_type" required>
            <?php foreach (['In House','Walk-In/Voluntary','Replacement','Patient-Directed'] as $type): ?>
              <option value="<?= $type ?>" <?= ($row['donation_type'] ?? '') === $type ? 'selected' : '' ?>><?= $type ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="form-group">
          <label>Location of donation</label>
          <select name="donation_location" required>
            <?php foreach (['Red Cross Area','School'] as $loc): ?>
              <option value="<?= $loc ?>" <?= ($row['donation_location'] ?? '') === $loc ? 'selected' : '' ?>><?= $loc ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </form>
    </div>
  </main>

  <?php if ($showAlert === "success"): ?>
    <script>
      Swal.fire({
        title: 'Saved',
        text: 'Donor information updated successfully.',
        icon: 'success',
        confirmButtonColor: '#9b1c1c'
      }).then(function () {
        window.location.href = 'donors_list.php';
      });
    </script>
  <?php elseif ($showAlert === "error"): ?>
    <script>
      Swal.fire({
        title: 'Could not save',
        text: 'Failed to update the donor record.',
        icon: 'error',
        confirmButtonColor: '#9b1c1c'
      });
    </script>
  <?php endif; ?>

  <script src="assets/bdms.js?v=20260527"></script>
  <script>
    document.getElementById('clearBtn').addEventListener('click', function () {
      Swal.fire({
        title: 'Clear the form?',
        text: 'All entered values will be cleared. Unsaved changes will be lost.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Yes, clear',
        cancelButtonText: 'Cancel',
        reverseButtons: true,
        confirmButtonColor: '#9b1c1c'
      }).then(function (r) {
        if (r.isConfirmed) {
          document.getElementById('form').querySelectorAll('input, select').forEach(function (el) {
            if (el.type !== 'submit' && el.type !== 'button') el.value = '';
          });
        }
      });
    });
  </script>

</body>
</html>
<?php ob_end_flush(); ?>
