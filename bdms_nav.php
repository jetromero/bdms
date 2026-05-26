<?php
/**
 * Shared navigation component — renders dark appbar + collapsible sidebar.
 * Also injects SweetAlert2 CDN if not already present.
 */
declare(strict_types=1);

if (!function_exists('bdms_is_administrator')) {
    require_once __DIR__ . '/bdms_profile_bar.php';
}

function bdms_nav_render(string $page_title = '', bool $minimal = false): void
{
    $current  = basename($_SERVER['PHP_SELF'] ?? '');
    $is_admin = bdms_is_administrator();
    $is_super = bdms_is_superadmin();

    $role = isset($_SESSION['role']) ? (string) $_SESSION['role'] : 'staff';
    if ($role === 'superadmin') {
        $ring_class      = 'bdms-profile-ring--superadmin';
        $role_text_class = 'bdms-profile-role--superadmin';
        $role_label      = 'Super Admin';
        $minimal         = true;
    } elseif ($role === 'administrator') {
        $ring_class      = 'bdms-profile-ring--admin';
        $role_text_class = 'bdms-profile-role--admin';
        $role_label      = 'Administrator';
    } else {
        $ring_class      = 'bdms-profile-ring--staff';
        $role_text_class = 'bdms-profile-role--staff';
        $role_label      = 'Staff';
    }

    $name = '';
    if (!empty($_SESSION['display_name'])) {
        $name = (string) $_SESSION['display_name'];
    } elseif (!empty($_SESSION['user'])) {
        $name = (string) $_SESSION['user'];
    }
    $initial = $name !== '' ? mb_strtoupper(mb_substr($name, 0, 1)) : '?';
    $return_to = $_SERVER['REQUEST_URI'] ?? 'dashboard.php';
    if (!is_string($return_to) || $return_to === '' || str_contains($return_to, '://')) {
        $return_to = 'dashboard.php';
    }

    $esc = fn(string $s): string => htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
    $active = fn(string $file): string => ($current === $file) ? ' active' : '';

    /* ── appbar ─────────────────────────────────────── */
    echo '<header class="appbar">';
    echo '<div class="appbar-left">';
    echo '  <button class="appbar-nav-toggle" onclick="bdmsToggleSidebar()" aria-label="Toggle navigation">';
    echo '    <i class="fa fa-bars"></i>';
    echo '  </button>';
    echo '  <a class="appbar-brand" href="dashboard.php">';
    echo '    <img src="evsulogo.png" alt="EVSU">';
    echo '    <span>EVSU-OC BDMS</span>';
    echo '  </a>';
    if ($page_title !== '') {
        echo '  <span class="appbar-divider"></span>';
        echo '  <span class="appbar-title">' . $esc($page_title) . '</span>';
    }
    echo '</div>';

    echo '<div class="appbar-right">';
    echo '<div class="bdms-profile-dropdown" data-bdms-profile-dropdown>';
    echo '  <button type="button" class="bdms-profile-menu-toggle" data-bdms-profile-toggle aria-haspopup="menu" aria-expanded="false" aria-label="Open profile menu" onclick="bdmsToggleProfileMenu(event)">';
    echo '    <span class="bdms-profile-bar" role="status" aria-label="Signed in as ' . $esc($role_label) . '">';
    echo '      <span class="bdms-profile-ring ' . $esc($ring_class) . '">' . $esc($initial) . '</span>';
    echo '      <span class="bdms-profile-text">';
    echo '        <span class="bdms-profile-name">' . $esc($name) . '</span>';
    echo '        <span class="bdms-profile-role ' . $esc($role_text_class) . '">' . $esc($role_label) . '</span>';
    echo '      </span>';
    echo '    </span>';
    echo '    <i class="fa fa-chevron-down bdms-profile-menu-arrow" aria-hidden="true"></i>';
    echo '  </button>';
    echo '  <div class="bdms-profile-menu" data-bdms-profile-menu role="menu" aria-label="Profile options">';
    echo '    <button type="button" class="bdms-profile-menu-action" role="menuitem" onclick="bdmsOpenChangePasswordModal(event)"><i class="fa fa-key"></i> Change password</button>';
    echo '    <a href="logout.php" role="menuitem" onclick="confirmLogout(event)"><i class="fa fa-right-from-bracket"></i> Logout</a>';
    echo '  </div>';
    echo '</div>';
    echo '</div>';
    echo '</header>';

    echo '<div class="modal bdms-password-modal" id="bdms-password-modal" data-bdms-password-modal aria-hidden="true">';
    echo '  <div class="modal-content bdms-password-modal-content" role="dialog" aria-modal="true" aria-labelledby="bdms-password-modal-title">';
    echo '    <button type="button" class="close" aria-label="Close password dialog" title="Close" onclick="bdmsCloseChangePasswordModal()">&times;</button>';
    echo '    <h3 id="bdms-password-modal-title"><i class="fas fa-key"></i> Change password</h3>';
    echo '    <p class="card-sub">Update the password for your account. This dialog only closes with the X button.</p>';
    echo '    <form method="POST" action="change_password.php" class="form-grid bdms-password-form">';
    echo '      <input type="hidden" name="return_to" value="' . $esc($return_to) . '">';
    echo '      <div class="form-group-full">';
    echo '        <label for="bdms-current-password">Current password</label>';
    echo '        <div class="password-row">';
    echo '          <input type="password" name="current_password" id="bdms-current-password" autocomplete="current-password" required>';
    echo '          <button type="button" class="password-toggle" data-target="bdms-current-password" aria-label="Show current password" title="Show current password"><i class="fa fa-eye"></i></button>';
    echo '        </div>';
    echo '      </div>';
    echo '      <div class="form-group-full">';
    echo '        <label for="bdms-new-password">New password</label>';
    echo '        <div class="password-row">';
    echo '          <input type="password" name="new_password" id="bdms-new-password" autocomplete="new-password" minlength="8" required>';
    echo '          <button type="button" class="password-toggle" data-target="bdms-new-password" aria-label="Show new password" title="Show new password"><i class="fa fa-eye"></i></button>';
    echo '        </div>';
    echo '        <div class="password-note">Must be at least 8 characters.</div>';
    echo '      </div>';
    echo '      <div class="form-group-full">';
    echo '        <label for="bdms-confirm-password">Confirm new password</label>';
    echo '        <div class="password-row">';
    echo '          <input type="password" name="confirm_password" id="bdms-confirm-password" autocomplete="new-password" minlength="8" required>';
    echo '          <button type="button" class="password-toggle" data-target="bdms-confirm-password" aria-label="Show confirm password" title="Show confirm password"><i class="fa fa-eye"></i></button>';
    echo '        </div>';
    echo '      </div>';
    echo '      <div class="form-group-full mt-2 flex gap-2" style="justify-content:flex-end;">';
    echo '        <button type="button" class="btn btn-ghost" onclick="bdmsCloseChangePasswordModal()"><i class="fas fa-xmark"></i> Close</button>';
    echo '        <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Save changes</button>';
    echo '      </div>';
    echo '    </form>';
    echo '  </div>';
    echo '</div>';

    /* ── sidebar (JS restores default state per viewport) ──── */
    echo '<aside class="sidebar" id="sidebar">';
    echo '<div class="sidebar-body">';

    if (!$minimal) {
        echo '<span class="sidebar-title">Main menu</span>';
        echo '<ul>';
        echo '<li><a href="dashboard.php" class="' . ltrim($active('dashboard.php')) . '">';
        echo '  <i class="fa fa-tachometer-alt"></i> Dashboard</a></li>';
        echo '<li><a href="donors_list.php" class="' . ltrim($active('donors_list.php') ?: $active('add.php')) . '">';
        echo '  <i class="fa fa-users"></i> Donors</a></li>';
        echo '<li><a href="record_donation.php" class="' . ltrim($active('record_donation.php')) . '">';
        echo '  <i class="fa fa-tint"></i> Record Donation</a></li>';
        echo '<li><a href="blood_inventory.php" class="' . ltrim($active('blood_inventory.php')) . '">';
        echo '  <i class="fa fa-flask"></i> Blood Inventory</a></li>';
        echo '<li><a href="reports.php" class="' . ltrim($active('reports.php') ?: $active('donor_reports.php')) . '">';
        echo '  <i class="fa fa-file-alt"></i> Reports</a></li>';
        echo '</ul>';

        if ($is_admin) {
            echo '<div class="sidebar-divider"></div>';
            echo '<span class="sidebar-title">Administration</span>';
            echo '<ul>';
            echo '<li><a href="user_management.php" class="' . ltrim($active('user_management.php')) . '">';
            echo '  <i class="fa fa-user-shield"></i> User Management</a></li>';
            echo '<li><a href="update_threshold.php" class="' . ltrim($active('update_threshold.php')) . '">';
            echo '  <i class="fa fa-sliders-h"></i> Thresholds</a></li>';
            echo '</ul>';
        }
    } else {
        echo '<span class="sidebar-title">System</span>';
        echo '<ul>';
        echo '<li><a href="audit_log.php" class="' . ltrim($active('audit_log.php')) . '">';
        echo '  <i class="fa fa-list-alt"></i> Audit Log</a></li>';
        echo '</ul>';
    }

    echo '</div>'; // .sidebar-body
    echo '<div class="sidebar-divider"></div>';
    echo '<div class="sidebar-footer">';
    echo '<ul>';
    echo '<li><a href="logout.php" onclick="confirmLogout(event)" class="btn-ghost-link">';
    echo '  <i class="fa fa-sign-out-alt"></i> Sign out</a></li>';
    echo '</ul>';
    echo '</div>';

    echo '</aside>';

    if (!empty($_SESSION['bdms_force_sidebar_open'])) {
        unset($_SESSION['bdms_force_sidebar_open']);
        echo '<script>try{localStorage.setItem("bdms_sidebar", window.innerWidth > 960 ? "open" : "closed");}catch(e){};</script>';
    }

    /* inject SweetAlert2 if page didn't load it in <head> */
    echo '<script>if(typeof Swal==="undefined"){var _s=document.createElement("script");_s.src="https://cdn.jsdelivr.net/npm/sweetalert2@11";document.head.appendChild(_s);}</script>';
}
