<?php
declare(strict_types=1);

require_once __DIR__ . '/require_login.php';
require_once __DIR__ . '/db.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'superadmin') {
    header('Location: dashboard.php');
    exit;
}

/** User-facing label for the Action column (stored value stays the machine key). */
function audit_log_action_display(string $action): string
{
    static $labels = [
        'add_donor' => 'New donor registered',
        'update_donor' => 'Donor Info Update',
        'delete_donor' => 'Donor record deleted',
        'record_donation' => 'Blood donation recorded',
        'login_success' => 'Login successful',
        'login_failure' => 'Login attempt failed',
    ];

    if (isset($labels[$action])) {
        return $labels[$action];
    }

    $t = trim($action);
    if ($t === '') {
        return '—';
    }

    return ucwords(str_replace('_', ' ', $t));
}

/**
 * Turn stored JSON or plain text into readable lines for the Details column.
 */
function audit_log_format_details_cell(?string $raw, string $action): string
{
    $raw = trim((string) $raw);
    if ($raw === '') {
        return '—';
    }

    $decoded = json_decode($raw, true);
    if (!is_array($decoded)) {
        return htmlspecialchars($raw, ENT_QUOTES, 'UTF-8');
    }

    $lines = [];
    $known = [];

    if ($action === 'record_donation' || isset($decoded['donation_id'])) {
        if (isset($decoded['donation_id'])) {
            $lines[] = 'Donation ID: ' . (int) $decoded['donation_id'];
            $known['donation_id'] = true;
        }
        if (isset($decoded['donor_id'])) {
            $lines[] = 'Donor ID: ' . (int) $decoded['donor_id'];
            $known['donor_id'] = true;
        }
        if (isset($decoded['quantity_ml'])) {
            $lines[] = 'Quantity: ' . (int) $decoded['quantity_ml'] . ' mL';
            $known['quantity_ml'] = true;
        }
        if (isset($decoded['blood_type'])) {
            $lines[] = 'Blood type: ' . (string) $decoded['blood_type'];
            $known['blood_type'] = true;
        }
        if (isset($decoded['donation_date'])) {
            $lines[] = 'Donation date: ' . (string) $decoded['donation_date'];
            $known['donation_date'] = true;
        }
        if (array_key_exists('inventory_updated', $decoded)) {
            $iu = $decoded['inventory_updated'];
            $lines[] = 'Inventory updated: ' . ($iu === true || $iu === 1 || $iu === '1' ? 'Yes' : 'No');
            $known['inventory_updated'] = true;
        }
    } elseif (in_array($action, ['add_donor', 'update_donor', 'delete_donor'], true)) {
        $labels = [
            'donor_id' => 'Donor ID',
            'name' => 'Name',
            'blood_type' => 'Blood type',
            'email' => 'Email',
            'classification' => 'Classification',
        ];
        foreach (['donor_id', 'name', 'blood_type', 'email', 'classification'] as $key) {
            if (!isset($decoded[$key])) {
                continue;
            }
            $known[$key] = true;
            $label = $labels[$key] ?? ucfirst(str_replace('_', ' ', $key));
            $lines[] = $label . ': ' . (string) $decoded[$key];
        }
    }

    foreach ($decoded as $key => $value) {
        if (isset($known[(string) $key])) {
            continue;
        }
        $label = ucfirst(str_replace('_', ' ', (string) $key));
        if (is_bool($value)) {
            $lines[] = $label . ': ' . ($value ? 'Yes' : 'No');
        } elseif (is_scalar($value)) {
            $lines[] = $label . ': ' . $value;
        }
    }

    if ($lines === []) {
        return htmlspecialchars(json_encode($decoded, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), ENT_QUOTES, 'UTF-8');
    }

    return htmlspecialchars(implode("\n", $lines), ENT_QUOTES, 'UTF-8');
}

$tableOk = false;
$rows = [];

$chk = $conn->query("SHOW TABLES LIKE 'audit_log'");
if ($chk && $chk->num_rows > 0) {
    $tableOk = true;
    $staff_users_ok = false;
    $su = $conn->query("SHOW TABLES LIKE 'staff_users'");
    if ($su && $su->num_rows > 0) {
        $staff_users_ok = true;
    }
    if ($staff_users_ok) {
        $res = $conn->query(
            'SELECT a.id, a.action, a.entity_type, a.entity_id, a.details, a.performed_by, a.created_at,
                    s.display_name AS performer_display_name
             FROM audit_log a
             LEFT JOIN staff_users s ON s.username = a.performed_by
             ORDER BY a.created_at DESC, a.id DESC
             LIMIT 300'
        );
    } else {
        $res = $conn->query(
            'SELECT id, action, entity_type, entity_id, details, performed_by, created_at,
                    NULL AS performer_display_name
             FROM audit_log
             ORDER BY created_at DESC, id DESC
             LIMIT 300'
        );
    }
    if ($res) {
        while ($r = $res->fetch_assoc()) {
            $rows[] = $r;
        }
    }
}

$conn->close();
require_once __DIR__ . '/bdms_nav.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link rel="icon" type="image/png" href="evsulogo.png">
  <title>Audit Log | BDMS</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <link rel="stylesheet" href="assets/bdms.css">
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
  <?php bdms_profile_bar_print_styles(); ?>
  <style>
    .mono { font-family: var(--font-mono); font-size: 12px; word-break: break-word; max-width: 200px; }
    .audit-details-plain { white-space: pre-line; font-size: 13px; line-height: 1.5; word-break: break-word; max-width: 440px; }
    .action-tag { display: inline-block; background: var(--brand-soft); color: var(--brand-strong); padding: 2px 8px; border-radius: var(--radius-xs); font-size: 11.5px; font-weight: 600; }
  </style>
</head>
<body class="app app-content-slate">

  <?php bdms_nav_render('Audit Log'); ?>

  <main class="page" id="page">
    <div class="page-header">
      <div>
        <h1 class="page-title"><i class="fas fa-clipboard-list"></i> Audit log</h1>
        <p class="page-sub">Read-only record of system actions (latest 300 entries). Newest first.</p>
      </div>
      <?php if ($tableOk && count($rows) > 0): ?>
        <div class="search">
          <i class="fas fa-magnifying-glass"></i>
          <input type="search" id="audit-log-search" placeholder="Search date, action, entity, user, details…" autocomplete="off" spellcheck="false">
        </div>
      <?php endif; ?>
    </div>

    <?php if (!$tableOk): ?>
      <div class="alert alert-warning">
        <i class="fas fa-triangle-exclamation"></i>
        <div><strong>Table missing.</strong> Run <code>schema_us02.sql</code> on the <code>bdms</code> database to create <code>audit_log</code>.</div>
      </div>
    <?php elseif (count($rows) === 0): ?>
      <div class="card center muted">
        No audit entries yet. Actions such as recording donations will appear here.
      </div>
    <?php else: ?>
      <p class="muted text-sm mb-3" id="audit-log-count" aria-live="polite"></p>
      <div class="table-wrap">
        <div class="table-scroll" style="max-height: calc(100vh - 220px);">
          <table>
            <thead>
              <tr>
                <th>When</th>
                <th>Action</th>
                <th>Entity</th>
                <th>By</th>
                <th>Details</th>
              </tr>
            </thead>
            <tbody id="audit-log-tbody">
              <?php foreach ($rows as $row): ?>
                <tr>
                  <td class="mono"><?php echo htmlspecialchars((string) $row['created_at']); ?></td>
                  <td><span class="action-tag" title="<?php echo htmlspecialchars((string) $row['action'], ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars(audit_log_action_display((string) $row['action']), ENT_QUOTES, 'UTF-8'); ?></span></td>
                  <td class="muted"><?php echo htmlspecialchars((string) $row['entity_type']); ?></td>
                  <td>
                    <?php
                      $name = trim((string) ($row['performer_display_name'] ?? ''));
                      $fallback = trim((string) ($row['performed_by'] ?? ''));
                      $show = $name !== '' ? $name : $fallback;
                      echo $show !== '' ? htmlspecialchars($show, ENT_QUOTES, 'UTF-8') : '—';
                    ?>
                  </td>
                  <td><span class="audit-details-plain"><?php echo audit_log_format_details_cell(
                    isset($row['details']) ? (string) $row['details'] : null,
                    (string) ($row['action'] ?? '')
                  ); ?></span></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
      <script>
      (function () {
        var input = document.getElementById('audit-log-search');
        var tbody = document.getElementById('audit-log-tbody');
        var countEl = document.getElementById('audit-log-count');
        if (!tbody) return;
        var rows = tbody.querySelectorAll('tr');

        function updateCount(visible) {
          if (!countEl) return;
          var total = rows.length;
          countEl.textContent = visible === total ? total + ' entries' : visible + ' of ' + total + ' shown';
        }

        function filter() {
          var q = (input.value || '').replace(/\s+/g, ' ').trim().toLowerCase();
          var visible = 0;
          for (var i = 0; i < rows.length; i++) {
            var match = !q || (rows[i].textContent || '').toLowerCase().indexOf(q) !== -1;
            rows[i].style.display = match ? '' : 'none';
            if (match) visible++;
          }
          updateCount(visible);
        }
        if (input) {
          input.addEventListener('input', filter);
          input.addEventListener('search', filter);
        }
        updateCount(rows.length);
      })();
      </script>
    <?php endif; ?>
  </main>

  <script src="assets/bdms.js"></script>
</body>
</html>
