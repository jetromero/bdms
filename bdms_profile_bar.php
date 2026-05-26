<?php
/**
 * Shared profile ring + role label for signed-in pages (Administrator vs Staff).
 */
declare(strict_types=1);

function bdms_is_administrator(): bool
{
    return isset($_SESSION['role']) && $_SESSION['role'] === 'administrator';
}

function bdms_is_superadmin(): bool
{
    return isset($_SESSION['role']) && $_SESSION['role'] === 'superadmin';
}

/**
 * Emit CSS once per page (call from <head>).
 */
function bdms_profile_bar_print_styles(): void
{
    static $printed = false;
    if ($printed) {
        return;
    }
    $printed = true;
    echo <<<'HTML'
<style id="bdms-profile-bar-styles">
.bdms-profile-bar {
  display: inline-flex;
  align-items: center;
  gap: 10px;
  padding: 4px 12px 4px 4px;
  border-radius: 999px;
  background: #fafafa;
  border: 1px solid #e5e7eb;
  max-width: 280px;
}
.bdms-profile-ring {
  width: 30px;
  height: 30px;
  border-radius: 50%;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
  font-weight: 600;
  font-size: 13px;
  letter-spacing: 0;
  border: 1px solid transparent;
}
.bdms-profile-ring--admin {
  background: #fdf3f3;
  color: #7f1818;
  border-color: #f3d3d3;
}
.bdms-profile-ring--staff {
  background: #eff6ff;
  color: #1d4ed8;
  border-color: #d6e3ff;
}
.bdms-profile-ring--superadmin {
  background: #f5f3ff;
  color: #5b21b6;
  border-color: #ddd6fe;
}
.bdms-profile-text {
  display: flex;
  flex-direction: column;
  line-height: 1.15;
  min-width: 0;
  text-align: left;
}
.bdms-profile-name {
  font-size: 13px;
  font-weight: 600;
  color: #111827;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
  max-width: 160px;
}
.bdms-profile-role {
  font-size: 11px;
  font-weight: 500;
  letter-spacing: 0.04em;
  text-transform: uppercase;
  color: #6b7280;
}
.bdms-profile-role--admin { color: #9b1c1c; }
.bdms-profile-role--staff { color: #1d4ed8; }
.bdms-profile-role--superadmin { color: #5b21b6; }
/* light variant retained for back-compat, same look */
.bdms-profile-bar--light {
  background: #ffffff;
  border-color: #e5e7eb;
}
@media (max-width: 960px) {
  .bdms-profile-bar {
    padding: 1px;
    max-width: none;
  }
  .bdms-profile-text {
    display: none;
  }
}
</style>
HTML;
}

function bdms_profile_bar_render(bool $light_variant = false): void
{
    $role = isset($_SESSION['role']) ? (string) $_SESSION['role'] : 'staff';
    if ($role === 'superadmin') {
        $role_label = 'Super Admin';
        $ring_class = 'bdms-profile-ring--superadmin';
        $role_text_class = 'bdms-profile-role--superadmin';
    } elseif ($role === 'administrator') {
        $role_label = 'Administrator';
        $ring_class = 'bdms-profile-ring--admin';
        $role_text_class = 'bdms-profile-role--admin';
    } else {
        $role_label = 'Staff';
        $ring_class = 'bdms-profile-ring--staff';
        $role_text_class = 'bdms-profile-role--staff';
    }
    $name = '';
    if (!empty($_SESSION['display_name'])) {
        $name = (string) $_SESSION['display_name'];
    } elseif (!empty($_SESSION['user'])) {
        $name = (string) $_SESSION['user'];
    }
    $initial = $name !== '' ? mb_strtoupper(mb_substr($name, 0, 1)) : '?';
    $bar_extra = $light_variant ? ' bdms-profile-bar--light' : '';
    echo '<div class="bdms-profile-bar' . $bar_extra . '" role="status" aria-label="Signed in as ' . htmlspecialchars($role_label, ENT_QUOTES, 'UTF-8') . '">';
    echo '<div class="bdms-profile-ring ' . htmlspecialchars($ring_class, ENT_QUOTES, 'UTF-8') . '"><span class="bdms-profile-initial">' . htmlspecialchars($initial, ENT_QUOTES, 'UTF-8') . '</span></div>';
    echo '<div class="bdms-profile-text">';
    echo '<span class="bdms-profile-name">' . htmlspecialchars($name, ENT_QUOTES, 'UTF-8') . '</span>';
    echo '<span class="bdms-profile-role ' . htmlspecialchars($role_text_class, ENT_QUOTES, 'UTF-8') . '">' . htmlspecialchars($role_label, ENT_QUOTES, 'UTF-8') . '</span>';
    echo '</div></div>';
}
