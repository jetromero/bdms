<?php
ob_start();
require_once __DIR__ . '/require_login.php';
require_once __DIR__ . '/bdms_nav.php';
require_once __DIR__ . '/db.php';

$can_delete_donor = bdms_is_administrator();

$sql = "SELECT * FROM donors ORDER BY id DESC";
$result = $conn->query($sql);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link rel="icon" type="image/png" href="evsulogo.png">
  <title>Donors | BDMS</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <link rel="stylesheet" href="assets/bdms.css">
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
  <?php bdms_profile_bar_print_styles(); ?>
  <style>
    .add-donor-overlay {
      display: none;
      position: fixed;
      inset: 0;
      z-index: 1200;
      background: rgba(30,11,14,0.55);
      backdrop-filter: blur(6px);
      align-items: flex-start;
      justify-content: center;
      padding: 40px 16px;
      overflow-y: auto;
    }
    .add-donor-overlay.is-open { display: flex; }
    .add-donor-panel {
      background: var(--surface);
      border-radius: var(--radius-xl);
      border: 1.5px solid var(--border);
      box-shadow: var(--shadow-3);
      width: 100%;
      max-width: 820px;
      padding: 28px 28px 24px;
      position: relative;
    }
    .add-donor-panel h2 {
      font-size: 18px;
      font-weight: 800;
      margin: 0 0 4px;
      display: flex;
      align-items: center;
      gap: 10px;
    }
    .add-donor-panel h2 i { color: var(--brand); }
    .add-donor-panel .sub { color: var(--text-muted); font-size: 13px; margin: 0 0 20px; }
    .add-donor-close {
      position: absolute;
      top: 16px; right: 16px;
      background: var(--surface-2);
      border: 1.5px solid var(--border);
      border-radius: var(--radius-pill);
      width: 32px; height: 32px;
      display: flex;
      align-items: center;
      justify-content: center;
      cursor: pointer;
      color: var(--text-muted);
      font-size: 14px;
      transition: background 120ms, color 120ms;
    }
    .add-donor-close:hover { background: var(--danger-soft); color: var(--danger); }
    .add-donor-actions {
      display: flex;
      gap: 10px;
      justify-content: flex-end;
      margin-top: 18px;
      padding-top: 16px;
      border-top: 1px solid var(--border);
    }
    @media (max-width: 960px) {
      .add-donor-overlay .bdms-date-menu {
        left: 0;
        right: 0;
        width: 100%;
      }

      .table-actions {
        gap: 8px;
      }
      .table-actions a {
        width: auto !important;
        min-width: 0 !important;
        padding: 4px 11px !important;
        justify-content: center;
        font-size: 12px !important;
        line-height: 1;
        flex: 0 0 auto;
      }
      .table-actions a i {
        display: none !important;
      }
      .table-actions a > .action-label {
        display: inline !important;
      }
    }
  </style>
</head>
<body class="app app-content-slate">

  <?php bdms_nav_render('Donors'); ?>

  <main class="page" id="page">
    <div class="page-header">
      <div>
        <h1 class="page-title"><i class="fas fa-users"></i> Donors</h1>
        <p class="page-sub">All registered donors. Use the search box to filter.</p>
      </div>
      <div class="flex gap-2">
        <div class="search">
          <i class="fas fa-magnifying-glass"></i>
          <input type="search" id="searchInput" placeholder="Search by name, blood type, contact…" oninput="searchDonor()" autocomplete="off">
        </div>
        <button class="btn btn-primary" onclick="openAddDonor()"><i class="fas fa-user-plus"></i> Add donor</button>
      </div>
    </div>

    <div class="table-wrap">
      <div class="table-scroll">
      <?php if ($result && $result->num_rows > 0): ?>
        <table>
          <thead>
            <tr>
              <th style="width:60px;">ID</th>
              <th>Name</th>
              <th>Blood</th>
              <th>Contact</th>
              <th>Email</th>
              <th style="text-align:right;">Actions</th>
            </tr>
          </thead>
          <tbody id="donorTable">
            <?php while ($row = $result->fetch_assoc()): ?>
              <tr id="donor-<?php echo (int) $row['id']; ?>">
                <td class="muted">#<?php echo (int) $row['id']; ?></td>
                <td><strong><?php echo htmlspecialchars((string) $row['name']); ?></strong></td>
                <td><span class="badge badge-admin"><?php echo htmlspecialchars((string) $row['blood_type']); ?></span></td>
                <td><?php echo htmlspecialchars((string) $row['contact_number']); ?></td>
                <td class="muted"><?php echo htmlspecialchars((string) $row['email']); ?></td>
                <td style="text-align:right;">
                  <div class="table-actions" style="justify-content:flex-end;">
                    <a href="view_donor.php?id=<?php echo (int) $row['id']; ?>"><i class="fas fa-eye"></i> <span class="action-label">View</span></a>
                    <a href="record_donation.php?donor_id=<?php echo (int) $row['id']; ?>"><i class="fas fa-droplet"></i> <span class="action-label">Donate</span></a>
                    <a href="edit_donor.php?id=<?php echo (int) $row['id']; ?>"><i class="fas fa-user-pen"></i> <span class="action-label">Edit</span></a>
                    <?php if ($can_delete_donor): ?>
                      <a href="javascript:void(0);" class="danger" onclick="deleteDonor(<?php echo (int) $row['id']; ?>)"><i class="fas fa-trash"></i> <span class="action-label">Delete</span></a>
                    <?php endif; ?>
                  </div>
                </td>
              </tr>
            <?php endwhile; ?>
          </tbody>
        </table>
      <?php else: ?>
        <p class="muted" style="padding: 24px; text-align:center;">No donors registered yet.</p>
      <?php endif; ?>
      </div>
    </div>
  </main>

  <!-- Add Donor Modal -->
  <div class="add-donor-overlay" id="addDonorOverlay">
    <div class="add-donor-panel">
      <button class="add-donor-close" onclick="closeAddDonor()" title="Close"><i class="fas fa-times"></i></button>
      <h2><i class="fas fa-user-plus"></i> Register New Donor</h2>
      <p class="sub">Fill in the donor profile. Duplicates are detected by email or contact + birth date.</p>

      <form id="addDonorForm" method="POST" action="add.php" class="form-grid">
        <input type="hidden" name="_from_modal" value="1">
        <div class="form-group" style="flex: 1 1 100%;">
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
        <div class="form-group" style="flex: 1 1 100%;">
          <label>Email address</label>
          <input type="email" name="email" required>
        </div>
        <div class="form-group" style="flex: 1 1 100%;">
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

      <div class="add-donor-actions">
        <button type="button" class="btn" onclick="document.getElementById('addDonorForm').reset();">
          <i class="fas fa-eraser"></i> Clear
        </button>
        <button type="submit" form="addDonorForm" class="btn btn-primary">
          <i class="fas fa-check"></i> Save Donor
        </button>
      </div>
    </div>
  </div>

  <script src="assets/bdms.js"></script>
  <script>
    (function () {
      var p = new URLSearchParams(window.location.search);
      if (p.get('added') === '1') {
        if (typeof bdmsToast === 'function') bdmsToast('Donor registered', 'The new donor has been added successfully.', 'success');
        if (window.history.replaceState) window.history.replaceState({}, '', 'donors_list.php');
      }
    })();

    function searchDonor() {
      var input = document.getElementById('searchInput').value.toLowerCase();
      var rows = document.querySelectorAll('#donorTable tr');
      var tableBody = document.getElementById('donorTable');
      var noResultsRow = document.getElementById('donorNoResultsRow');
      var visibleCount = 0;

      rows.forEach(function (row) {
        if (row.id === 'donorNoResultsRow') return;
        var text = row.innerText.toLowerCase();
        var isVisible = !input || text.includes(input);
        row.style.display = isVisible ? '' : 'none';
        if (isVisible) visibleCount += 1;
      });

      if (tableBody) {
        if (!noResultsRow) {
          noResultsRow = document.createElement('tr');
          noResultsRow.id = 'donorNoResultsRow';
          noResultsRow.style.display = 'none';
          noResultsRow.innerHTML = '<td colspan="6" class="muted" style="padding: 24px; text-align:center;">No blood donors found.</td>';
          tableBody.appendChild(noResultsRow);
        }

        noResultsRow.style.display = (input && visibleCount === 0) ? '' : 'none';
      }
    }

    function openAddDonor() {
      document.getElementById('addDonorOverlay').classList.add('is-open');
      document.body.style.overflow = 'hidden';
    }
    function closeAddDonor() {
      document.getElementById('addDonorOverlay').classList.remove('is-open');
      document.body.style.overflow = '';
    }

    function deleteDonor(id) {
      Swal.fire({
        title: 'Delete this donor?',
        text: 'This action cannot be undone.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Yes, delete',
        cancelButtonText: 'Cancel',
        reverseButtons: true,
        confirmButtonColor: '#c41e3a'
      }).then(function (result) {
        if (!result.isConfirmed) return;
        fetch('delete_donor.php?id=' + id)
          .then(function (response) {
            return response.text().then(function (text) {
              return { ok: response.ok, status: response.status, text: text };
            });
          })
          .then(function (res) {
            if (res.ok && res.text.trim() === 'Success') {
              if (typeof bdmsToast === 'function') {
                bdmsToast('Deleted', 'The donor record has been removed.', 'success');
              }
              var row = document.getElementById('donor-' + id);
              if (row) row.remove();
            } else {
              if (typeof bdmsToast === 'function') {
                bdmsToast(
                  res.status === 403 ? 'Not allowed' : 'Error',
                  res.status === 403 ? 'Only administrators can delete donor records.' : 'Something went wrong.',
                  'error'
                );
              }
            }
          })
          .catch(function () {
            if (typeof bdmsToast === 'function') {
              bdmsToast('Error', 'Something went wrong while deleting.', 'error');
            }
          });
      });
    }
  </script>

  <script>
    // Submit the Add Donor modal via fetch to avoid navigating away from this page.
    (function () {
      var form = document.getElementById('addDonorForm');
      if (!form) return;

      function escapeHtml(value) {
        return String(value)
          .replace(/&/g, '&amp;')
          .replace(/</g, '&lt;')
          .replace(/>/g, '&gt;')
          .replace(/"/g, '&quot;')
          .replace(/'/g, '&#39;');
      }

      function getSelectLabel(selectName) {
        var select = form.querySelector('select[name="' + selectName + '"]');
        if (!select) return '';
        var option = select.options[select.selectedIndex];
        return option ? option.textContent.trim() : '';
      }

      function prependDonorRow(donorId) {
        var tableBody = document.getElementById('donorTable');
        if (!tableBody) return;

        var name = escapeHtml(form.querySelector('input[name="name"]').value.trim());
        var bloodType = escapeHtml(getSelectLabel('blood_type'));
        var contactNumber = escapeHtml(form.querySelector('input[name="contact_number"]').value.trim());
        var email = escapeHtml(form.querySelector('input[name="email"]').value.trim());
        var donorIdText = escapeHtml(String(donorId));

        var row = document.createElement('tr');
        row.id = 'donor-' + donorId;
        row.innerHTML =
          '<td class="muted">#' + donorIdText + '</td>' +
          '<td><strong>' + name + '</strong></td>' +
          '<td><span class="badge badge-admin">' + bloodType + '</span></td>' +
          '<td>' + contactNumber + '</td>' +
          '<td class="muted">' + email + '</td>' +
          '<td style="text-align:right;">' +
            '<div class="table-actions" style="justify-content:flex-end;">' +
              '<a href="view_donor.php?id=' + donorIdText + '"><i class="fas fa-eye"></i> <span class="action-label">View</span></a>' +
              '<a href="record_donation.php?donor_id=' + donorIdText + '"><i class="fas fa-droplet"></i> <span class="action-label">Donate</span></a>' +
              '<a href="edit_donor.php?id=' + donorIdText + '"><i class="fas fa-user-pen"></i> <span class="action-label">Edit</span></a>' +
              <?php if ($can_delete_donor): ?>
              '<a href="javascript:void(0);" class="danger" onclick="deleteDonor(' + donorIdText + ')"><i class="fas fa-trash"></i> <span class="action-label">Delete</span></a>' +
              <?php endif; ?>
            '</div>' +
          '</td>';

        tableBody.insertBefore(row, tableBody.firstChild);
      }

      form.addEventListener('submit', function (e) {
        e.preventDefault();
        var submitBtn = document.querySelector('button[form="addDonorForm"][type="submit"]');
        if (submitBtn) submitBtn.disabled = true;

        var fd = new FormData(form);

        fetch('add.php', {
          method: 'POST',
          body: fd,
          credentials: 'same-origin'
        })
        .then(function (response) {
          return response.text().then(function (text) {
            return {
              ok: response.ok,
              status: response.status,
              text: text,
              url: response.url,
              redirected: response.redirected
            };
          });
        })
        .then(function (res) {
          var success = res.redirected && /donors_list\.php\?added=1/.test(res.url || '');
          if (success) {
            var newIdMatch = (res.url || '').match(/[?&]new_id=(\d+)/);
            var newId = newIdMatch ? newIdMatch[1] : null;

            if (newId) {
              prependDonorRow(newId);
            }

            closeAddDonor();
            if (typeof bdmsToast === 'function') bdmsToast('Donor registered', 'The new donor has been added successfully.', 'success');
            try { if (window.history.replaceState) window.history.replaceState({}, '', 'donors_list.php'); } catch (e) {}
            form.reset();
          } else {
            if (typeof bdmsToast === 'function') bdmsToast('Error', 'Failed to add donor.', 'error');
          }
        })
        .catch(function () {
          if (typeof bdmsToast === 'function') bdmsToast('Error', 'Something went wrong while adding donor.', 'error');
        })
        .finally(function () {
          if (submitBtn) submitBtn.disabled = false;
        });
      });
    })();
  </script>

<?php
$conn->close();
ob_end_flush();
?>
</body>
</html>
