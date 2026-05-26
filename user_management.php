<?php
/**
 * US-06: Administrator-only staff account management (create, update, deactivate, activate, permanent delete).
 */
declare(strict_types=1);

ob_start();
require_once __DIR__ . '/require_admin.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/staff_accounts_helpers.php';

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$tableOk = false;
$chk = $conn->query("SHOW TABLES LIKE 'staff_users'");
if ($chk && $chk->num_rows > 0) {
    $tableOk = true;
}

$flash = '';
$flashType = '';
if (!empty($_SESSION['staff_mgmt_success'])) {
    $flash = (string) $_SESSION['staff_mgmt_success'];
    $flashType = 'success';
    unset($_SESSION['staff_mgmt_success']);
}

$editRow = null;
$editId = isset($_GET['edit']) ? (int) $_GET['edit'] : 0;

if ($tableOk && $editId > 0) {
    $es = $conn->prepare('SELECT id, username, email, display_name, role, is_active FROM staff_users WHERE id = ? LIMIT 1');
    $es->bind_param('i', $editId);
    $es->execute();
    $er = $es->get_result()->fetch_assoc();
    $es->close();
    if ($er) {
        $editRow = $er;
    }
}

$currentStaffId = isset($_SESSION['staff_id']) ? (int) $_SESSION['staff_id'] : 0;

if ($tableOk && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string) ($_POST['action'] ?? '');

    if ($action === 'create') {
        $check = staff_validate_create_payload($_POST);
        if (!$check['ok']) {
            $flashType = 'validation';
            $_SESSION['staff_flash_errors'] = $check['errors'];
        } else {
            $username = trim((string) $_POST['username']);
            $email = staff_normalize_email((string) $_POST['email']);
            $displayName = trim((string) $_POST['display_name']);
            $role = (string) $_POST['role'];
            $hash = password_hash((string) $_POST['password'], PASSWORD_DEFAULT);
            $ins = $conn->prepare(
                'INSERT INTO staff_users (username, email, display_name, password_hash, role, is_active)
                 VALUES (?, ?, ?, ?, ?, 1)'
            );
            try {
                $ins->bind_param('sssss', $username, $email, $displayName, $hash, $role);
                $ins->execute();
                $ins->close();
                $_SESSION['staff_mgmt_success'] = 'Staff account created successfully.';
                header('Location: user_management.php');
                exit;
            } catch (mysqli_sql_exception $e) {
                if ($e->getCode() === 1062) {
                    $flash = 'Username or email is already in use.';
                } else {
                    $flash = 'Could not create account. Please try again.';
                }
                $flashType = 'error';
            }
        }
    } elseif ($action === 'update' && $editId > 0) {
        $check = staff_validate_update_payload($_POST);
        if (!$check['ok']) {
            $flashType = 'validation';
            $_SESSION['staff_flash_errors'] = $check['errors'];
        } else {
            $email = staff_normalize_email((string) $_POST['email']);
            $displayName = trim((string) $_POST['display_name']);
            $role = (string) $_POST['role'];
            $newPass = (string) ($_POST['password'] ?? '');
            $cur = $conn->prepare('SELECT role, is_active FROM staff_users WHERE id = ? LIMIT 1');
            $cur->bind_param('i', $editId);
            $cur->execute();
            $curRow = $cur->get_result()->fetch_assoc();
            $cur->close();
            if (!$curRow) {
                $flash = 'Account not found.';
                $flashType = 'error';
            } elseif (
                $curRow['role'] === 'administrator'
                && $role === 'staff'
                && staff_count_administrator_rows($conn) <= 1
            ) {
                $flash = 'Cannot remove the only administrator account from the system.';
                $flashType = 'error';
            } else {
                try {
                    if ($newPass !== '') {
                        $hash = password_hash($newPass, PASSWORD_DEFAULT);
                        $upd = $conn->prepare(
                            'UPDATE staff_users SET email = ?, display_name = ?, role = ?, password_hash = ?
                             WHERE id = ?'
                        );
                        $upd->bind_param('ssssi', $email, $displayName, $role, $hash, $editId);
                    } else {
                        $upd = $conn->prepare(
                            'UPDATE staff_users SET email = ?, display_name = ?, role = ?
                             WHERE id = ?'
                        );
                        $upd->bind_param('sssi', $email, $displayName, $role, $editId);
                    }
                    $upd->execute();
                    $upd->close();
                    $_SESSION['staff_mgmt_success'] = 'Account updated successfully.';
                    header('Location: user_management.php');
                    exit;
                } catch (mysqli_sql_exception $e) {
                    if ($e->getCode() === 1062) {
                        $flash = 'That email is already used by another account.';
                    } else {
                        $flash = 'Could not update account.';
                    }
                    $flashType = 'error';
                }
            }
        }
    } elseif ($action === 'deactivate') {
        $targetId = (int) ($_POST['staff_id'] ?? 0);
        if ($targetId <= 0) {
            $flash = 'Invalid account.';
            $flashType = 'error';
        } elseif ($currentStaffId > 0 && $targetId === $currentStaffId) {
            $flash = 'You cannot deactivate your own account while logged in.';
            $flashType = 'error';
        } else {
            $roleStmt = $conn->prepare('SELECT role FROM staff_users WHERE id = ? AND is_active = 1 LIMIT 1');
            $roleStmt->bind_param('i', $targetId);
            $roleStmt->execute();
            $rr = $roleStmt->get_result()->fetch_assoc();
            $roleStmt->close();
            if (!$rr) {
                $flash = 'Account not found or already inactive.';
                $flashType = 'error';
            } elseif ($rr['role'] === 'administrator' && staff_count_active_admins($conn) <= 1) {
                $flash = 'Cannot deactivate the last active administrator.';
                $flashType = 'error';
            } else {
                $de = $conn->prepare('UPDATE staff_users SET is_active = 0 WHERE id = ?');
                $de->bind_param('i', $targetId);
                $de->execute();
                $de->close();
                $_SESSION['staff_mgmt_success'] = 'Staff account deactivated. That user can no longer sign in.';
                header('Location: user_management.php');
                exit;
            }
        }
    } elseif ($action === 'activate') {
        $targetId = (int) ($_POST['staff_id'] ?? 0);
        if ($targetId <= 0) {
            $flash = 'Invalid account.';
            $flashType = 'error';
        } else {
            $chk = $conn->prepare('SELECT id, username FROM staff_users WHERE id = ? AND is_active = 0 LIMIT 1');
            $chk->bind_param('i', $targetId);
            $chk->execute();
            $inactive = $chk->get_result()->fetch_assoc();
            $chk->close();
            if (!$inactive) {
                $flash = 'Account not found or already active.';
                $flashType = 'error';
            } else {
                try {
                    $act = $conn->prepare('UPDATE staff_users SET is_active = 1 WHERE id = ? AND is_active = 0');
                    $act->bind_param('i', $targetId);
                    $act->execute();
                    $act->close();
                    $_SESSION['staff_mgmt_success'] = 'Account activated. The user can sign in again.';
                    header('Location: user_management.php');
                    exit;
                } catch (mysqli_sql_exception $e) {
                    if ($e->getCode() === 1062) {
                        $flash = 'Cannot activate: username or email conflicts with another account. Edit this account first.';
                    } else {
                        $flash = 'Could not activate account.';
                    }
                    $flashType = 'error';
                }
            }
        }
    } elseif ($action === 'permanent_delete') {
        $targetId = (int) ($_POST['staff_id'] ?? 0);
        if ($targetId <= 0) {
            $flash = 'Invalid account.';
            $flashType = 'error';
        } elseif ($currentStaffId > 0 && $targetId === $currentStaffId) {
            $flash = 'You cannot delete your own account while logged in.';
            $flashType = 'error';
        } else {
            $sel = $conn->prepare('SELECT id, role, is_active FROM staff_users WHERE id = ? LIMIT 1');
            $sel->bind_param('i', $targetId);
            $sel->execute();
            $delRow = $sel->get_result()->fetch_assoc();
            $sel->close();
            if (!$delRow) {
                $flash = 'Account not found.';
                $flashType = 'error';
            } elseif ((int) $delRow['is_active'] === 1) {
                $flash = 'Deactivate the account first, then you can delete it permanently.';
                $flashType = 'error';
            } elseif ($delRow['role'] === 'administrator' && staff_count_administrator_rows($conn) <= 1) {
                $flash = 'Cannot delete the only administrator record in the system.';
                $flashType = 'error';
            } else {
                $del = $conn->prepare('DELETE FROM staff_users WHERE id = ? AND is_active = 0');
                $del->bind_param('i', $targetId);
                $del->execute();
                if ($del->affected_rows === 0) {
                    $flash = 'No row was deleted (account may have been removed or reactivated).';
                    $flashType = 'error';
                } else {
                    $_SESSION['staff_mgmt_success'] = 'Account permanently removed from the database.';
                    header('Location: user_management.php');
                    exit;
                }
                $del->close();
            }
        }
    }
}

$validationErrors = [];
if (!empty($_SESSION['staff_flash_errors'])) {
    $validationErrors = $_SESSION['staff_flash_errors'];
    unset($_SESSION['staff_flash_errors']);
}

$staffList = [];
if ($tableOk) {
    $list = $conn->query(
        'SELECT id, username, email, display_name, role, is_active, created_at
         FROM staff_users ORDER BY is_active DESC, display_name ASC'
    );
    if ($list) {
        while ($row = $list->fetch_assoc()) {
            $staffList[] = $row;
        }
    }
}

$conn->close();
$pageTitle = $editRow ? 'Edit staff account' : 'User management';

require_once __DIR__ . '/bdms_nav.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link rel="icon" type="image/png" href="evsulogo.png">
  <title>User Management | BDMS</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <link rel="stylesheet" href="assets/bdms.css">
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
  <?php bdms_profile_bar_print_styles(); ?>
  <style>
    .grid2 { display: grid; grid-template-columns: 1fr 1fr; gap: 14px 16px; }
    @media (max-width: 700px) { .grid2 { grid-template-columns: 1fr; } }
    .actions { white-space: nowrap; }
    @media (max-width: 960px) {
      .staff-table-wrap {
        overflow-x: auto;
        -webkit-overflow-scrolling: touch;
      }

      .staff-table {
        min-width: 920px;
      }
    }
  </style>
</head>
<body class="app app-content-slate">

  <?php bdms_nav_render(htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8')); ?>

  <main class="page" id="page">
    <div class="page-header">
      <div>
        <h1 class="page-title"><i class="fas fa-users-gear"></i> <?php echo htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8'); ?></h1>
        <p class="page-sub">Create and update staff accounts. <strong>Deactivate</strong> blocks sign-in; <strong>Activate</strong> restores access. <strong>Delete permanently</strong> removes an <em>inactive</em> row from the database (deactivate first).</p>
      </div>
    </div>

    <?php if (!$tableOk): ?>
      <div class="alert alert-warning">
        <i class="fas fa-triangle-exclamation"></i>
        <div><strong>Database setup required.</strong> Run <code>schema_us06.sql</code> on your <code>bdms</code> database, then reload this page.</div>
      </div>
    <?php else: ?>

      <?php if ($editRow): ?>
        <div class="card">
          <div class="card-title"><i class="fas fa-user-pen"></i> Edit account</div>
          <p class="card-sub">Username: <strong><?php echo htmlspecialchars((string) $editRow['username'], ENT_QUOTES, 'UTF-8'); ?></strong> (cannot be changed)</p>
          <?php if ((int) $editRow['is_active'] === 0): ?>
            <div class="alert alert-warning">
              <i class="fas fa-circle-info"></i>
              <div>This account is <strong>inactive</strong>. Update details if needed, then activate or permanently delete from the list view.</div>
            </div>
          <?php endif; ?>
          <form method="post" action="user_management.php?edit=<?php echo (int) $editRow['id']; ?>">
            <input type="hidden" name="action" value="update">
            <div class="grid2">
              <div>
                <label for="display_name">Full name</label>
                <input type="text" id="display_name" name="display_name" required maxlength="255"
                  value="<?php echo htmlspecialchars((string) $editRow['display_name'], ENT_QUOTES, 'UTF-8'); ?>">
              </div>
              <div>
                <label for="email">Email</label>
                <input type="email" id="email" name="email" required maxlength="255"
                  value="<?php echo htmlspecialchars((string) $editRow['email'], ENT_QUOTES, 'UTF-8'); ?>">
              </div>
              <div>
                <label for="role">Role</label>
                <select id="role" name="role" required>
                  <option value="staff" <?php echo $editRow['role'] === 'staff' ? 'selected' : ''; ?>>Staff</option>
                  <option value="administrator" <?php echo $editRow['role'] === 'administrator' ? 'selected' : ''; ?>>Administrator</option>
                </select>
              </div>
              <div>
                <label for="password">New password (optional)</label>
                <input type="password" id="password" name="password" autocomplete="new-password" minlength="8" placeholder="Leave blank to keep current">
              </div>
              <div>
                <label for="password_confirm">Confirm new password</label>
                <input type="password" id="password_confirm" name="password_confirm" autocomplete="new-password" minlength="8">
              </div>
            </div>
            <div class="flex gap-2 mt-3">
              <button type="submit" class="btn btn-primary"><i class="fas fa-floppy-disk"></i> Save changes</button>
              <a href="user_management.php" class="btn btn-ghost">Cancel</a>
            </div>
          </form>
        </div>
      <?php else: ?>

        <div class="card">
          <div class="card-title"><i class="fas fa-user-plus"></i> Add new staff</div>
          <p class="card-sub">Username is permanent; passwords must be at least 8 characters.</p>
          <form method="post" action="user_management.php">
            <input type="hidden" name="action" value="create">
            <div class="grid2">
              <div>
                <label for="username">Username</label>
                <input type="text" id="username" name="username" required maxlength="64" pattern="[a-zA-Z0-9_]{3,64}" title="3–64 letters, numbers, or underscore">
              </div>
              <div>
                <label for="display_name_create">Full name</label>
                <input type="text" id="display_name_create" name="display_name" required maxlength="255">
              </div>
              <div>
                <label for="email_create">Email</label>
                <input type="email" id="email_create" name="email" required maxlength="255">
              </div>
              <div>
                <label for="role_create">Role</label>
                <select id="role_create" name="role" required>
                  <option value="staff" selected>Staff</option>
                  <option value="administrator">Administrator</option>
                </select>
              </div>
              <div>
                <label for="password_create">Password</label>
                <input type="password" id="password_create" name="password" required minlength="8" autocomplete="new-password">
              </div>
              <div>
                <label for="password_confirm_create">Confirm password</label>
                <input type="password" id="password_confirm_create" name="password_confirm" required minlength="8" autocomplete="new-password">
              </div>
            </div>
            <button type="submit" class="btn btn-primary mt-3"><i class="fas fa-check"></i> Create account</button>
          </form>
        </div>

        <div class="card mt-4">
          <div class="card-title"><i class="fas fa-list"></i> Staff accounts</div>
          <?php if (count($staffList) === 0): ?>
            <p class="muted">No accounts yet.</p>
          <?php else: ?>
            <div class="table-wrap staff-table-wrap" style="border:0; box-shadow:none;">
              <table class="staff-table">
                <thead>
                  <tr>
                    <th>Name</th>
                    <th>Username</th>
                    <th>Email</th>
                    <th>Role</th>
                    <th>Status</th>
                    <th class="actions" style="text-align:right;">Actions</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($staffList as $s): ?>
                    <tr>
                      <td><strong><?php echo htmlspecialchars((string) $s['display_name'], ENT_QUOTES, 'UTF-8'); ?></strong></td>
                      <td class="muted"><?php echo htmlspecialchars((string) $s['username'], ENT_QUOTES, 'UTF-8'); ?></td>
                      <td class="muted"><?php echo htmlspecialchars((string) $s['email'], ENT_QUOTES, 'UTF-8'); ?></td>
                      <td>
                        <?php if ($s['role'] === 'administrator'): ?>
                          <span class="badge badge-admin">Administrator</span>
                        <?php elseif ($s['role'] === 'superadmin'): ?>
                          <span class="badge badge-super">Super admin</span>
                        <?php else: ?>
                          <span class="badge badge-staff">Staff</span>
                        <?php endif; ?>
                      </td>
                      <td>
                        <?php if ((int) $s['is_active'] === 1): ?>
                          <span class="badge badge-active">Active</span>
                        <?php else: ?>
                          <span class="badge badge-off">Inactive</span>
                        <?php endif; ?>
                      </td>
                      <td class="actions" style="text-align:right;">
                        <div class="table-actions" style="justify-content:flex-end;">
                          <a href="user_management.php?edit=<?php echo (int) $s['id']; ?>"><i class="fas fa-pen"></i> Edit</a>
                          <?php if ((int) $s['is_active'] === 1): ?>
                            <form method="post" action="user_management.php" style="display:inline;" class="deactivate-form" data-name="<?php echo htmlspecialchars((string) $s['display_name'], ENT_QUOTES, 'UTF-8'); ?>">
                              <input type="hidden" name="action" value="deactivate">
                              <input type="hidden" name="staff_id" value="<?php echo (int) $s['id']; ?>">
                              <button type="submit" class="btn btn-sm btn-ghost" style="color: var(--danger); border-color: var(--border);"><i class="fas fa-user-slash"></i> Deactivate</button>
                            </form>
                          <?php else: ?>
                            <form method="post" action="user_management.php" style="display:inline;" class="activate-form" data-name="<?php echo htmlspecialchars((string) $s['display_name'], ENT_QUOTES, 'UTF-8'); ?>">
                              <input type="hidden" name="action" value="activate">
                              <input type="hidden" name="staff_id" value="<?php echo (int) $s['id']; ?>">
                              <button type="submit" class="btn btn-sm btn-ghost" style="color: var(--success); border-color: var(--border);"><i class="fas fa-user-check"></i> Activate</button>
                            </form>
                            <form method="post" action="user_management.php" style="display:inline;" class="purge-form" data-name="<?php echo htmlspecialchars((string) $s['display_name'], ENT_QUOTES, 'UTF-8'); ?>" data-username="<?php echo htmlspecialchars((string) $s['username'], ENT_QUOTES, 'UTF-8'); ?>">
                              <input type="hidden" name="action" value="permanent_delete">
                              <input type="hidden" name="staff_id" value="<?php echo (int) $s['id']; ?>">
                              <button type="submit" class="btn btn-sm btn-danger"><i class="fas fa-trash"></i> Delete</button>
                            </form>
                          <?php endif; ?>
                        </div>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          <?php endif; ?>
        </div>
      <?php endif; ?>

    <?php endif; ?>
  </main>

  <script src="assets/bdms.js?v=20260527"></script>
  <script>
  (function () {
    <?php if ($flash !== '' && $flashType === 'success'): ?>
    Swal.fire({ icon: 'success', title: 'Done', text: <?php echo json_encode($flash, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>, confirmButtonColor: '#9b1c1c' });
    <?php elseif ($flash !== '' && $flashType === 'error'): ?>
    Swal.fire({ icon: 'error', title: 'Error', text: <?php echo json_encode($flash, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>, confirmButtonColor: '#9b1c1c' });
    <?php elseif ($flash !== '' && $flashType === 'info'): ?>
    Swal.fire({ icon: 'info', title: 'Notice', text: <?php echo json_encode($flash, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>, confirmButtonColor: '#9b1c1c' });
    <?php elseif (!empty($validationErrors)): ?>
    Swal.fire({
      icon: 'info',
      title: 'Please check the form',
      html: <?php echo json_encode(
          '<ul style="text-align:left;margin:0;padding-left:1.2em;">' . implode('', array_map(static function (string $e): string {
              return '<li>' . htmlspecialchars($e, ENT_QUOTES, 'UTF-8') . '</li>';
          }, $validationErrors)) . '</ul>',
          JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
      ); ?>,
      confirmButtonColor: '#9b1c1c'
    });
    <?php endif; ?>

    document.querySelectorAll('.deactivate-form').forEach(function (form) {
      form.addEventListener('submit', function (e) {
        e.preventDefault();
        var name = form.getAttribute('data-name') || 'this user';
        Swal.fire({
          title: 'Deactivate account?',
          html: 'User <strong>' + name + '</strong> will no longer be able to log in.',
          icon: 'warning',
          showCancelButton: true,
          confirmButtonColor: '#b91c1c',
          cancelButtonColor: '#6b7280',
          confirmButtonText: 'Yes, deactivate'
        }).then(function (r) { if (r.isConfirmed) form.submit(); });
      });
    });

    document.querySelectorAll('.activate-form').forEach(function (form) {
      form.addEventListener('submit', function (e) {
        e.preventDefault();
        var name = form.getAttribute('data-name') || 'this user';
        Swal.fire({
          title: 'Activate account?',
          html: 'User <strong>' + name + '</strong> will be able to sign in again.',
          icon: 'question',
          showCancelButton: true,
          confirmButtonColor: '#047857',
          cancelButtonColor: '#6b7280',
          confirmButtonText: 'Yes, activate'
        }).then(function (r) { if (r.isConfirmed) form.submit(); });
      });
    });

    document.querySelectorAll('.purge-form').forEach(function (form) {
      form.addEventListener('submit', function (e) {
        e.preventDefault();
        var name = form.getAttribute('data-name') || 'this user';
        var username = form.getAttribute('data-username') || '';
        Swal.fire({
          title: 'Delete permanently?',
          html: '<p style="text-align:left;margin:0 0 12px 0;">This will <strong>remove the database row</strong> for <strong>' + name + '</strong>. This cannot be undone.</p><p style="text-align:left;margin:0;font-size:13px;">Type the username exactly to confirm.</p>',
          icon: 'error',
          input: 'text',
          inputPlaceholder: 'Username',
          showCancelButton: true,
          confirmButtonColor: '#b91c1c',
          cancelButtonColor: '#6b7280',
          confirmButtonText: 'Delete forever',
          inputValidator: function (value) {
            if (!value || String(value).trim() !== username) return 'Username must match exactly.';
            return null;
          }
        }).then(function (r) { if (r.isConfirmed) form.submit(); });
      });
    });
  })();
  </script>
</body>
</html>
<?php ob_end_flush(); ?>
