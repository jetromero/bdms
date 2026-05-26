<?php
/**
 * Validation helpers for US-06 staff account creation and updates.
 */
declare(strict_types=1);

function staff_normalize_email(string $email): string
{
    return strtolower(trim($email));
}

/**
 * @param array<string, mixed> $p
 * @return array{ok: bool, errors: string[]}
 */
function staff_validate_account_fields(array $p, bool $password_required): array
{
    $errors = [];

    $display = trim((string) ($p['display_name'] ?? ''));
    if ($display === '') {
        $errors[] = 'Full name is required.';
    } elseif (mb_strlen($display) > 255) {
        $errors[] = 'Full name is too long.';
    }

    $emailRaw = trim((string) ($p['email'] ?? ''));
    if ($emailRaw === '') {
        $errors[] = 'Email is required.';
    } elseif (mb_strlen($emailRaw) > 255) {
        $errors[] = 'Email is too long.';
    } elseif (!filter_var($emailRaw, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Email format is invalid.';
    }

    $role = (string) ($p['role'] ?? '');
    if (!in_array($role, ['administrator', 'staff'], true)) {
        $errors[] = 'Role must be Administrator or Staff.';
    }

    $password = (string) ($p['password'] ?? '');
    $confirm = (string) ($p['password_confirm'] ?? '');
    if ($password_required) {
        if ($password === '') {
            $errors[] = 'Password is required.';
        } elseif (strlen($password) < 8) {
            $errors[] = 'Password must be at least 8 characters.';
        } elseif ($password !== $confirm) {
            $errors[] = 'Password confirmation does not match.';
        }
    } elseif ($password !== '' || $confirm !== '') {
        if (strlen($password) < 8) {
            $errors[] = 'New password must be at least 8 characters.';
        } elseif ($password !== $confirm) {
            $errors[] = 'Password confirmation does not match.';
        }
    }

    return ['ok' => $errors === [], 'errors' => $errors];
}

/**
 * @param array<string, mixed> $p
 * @return array{ok: bool, errors: string[]}
 */
function staff_validate_new_username(string $username): array
{
    $errors = [];
    $u = trim($username);
    if ($u === '') {
        $errors[] = 'Username is required.';
    } elseif (!preg_match('/^[a-zA-Z0-9_]{3,64}$/', $u)) {
        $errors[] = 'Username must be 3–64 characters (letters, numbers, underscore only).';
    }
    return ['ok' => $errors === [], 'errors' => $errors];
}

/**
 * @param array<string, mixed> $p
 * @return array{ok: bool, errors: string[]}
 */
function staff_validate_create_payload(array $p): array
{
    $errors = [];
    $userCheck = staff_validate_new_username((string) ($p['username'] ?? ''));
    if (!$userCheck['ok']) {
        $errors = array_merge($errors, $userCheck['errors']);
    }
    $base = staff_validate_account_fields($p, true);
    if (!$base['ok']) {
        $errors = array_merge($errors, $base['errors']);
    }
    return ['ok' => $errors === [], 'errors' => $errors];
}

/**
 * @param array<string, mixed> $p
 * @return array{ok: bool, errors: string[]}
 */
function staff_validate_update_payload(array $p): array
{
    return staff_validate_account_fields($p, false);
}

function staff_count_active_admins(mysqli $conn): int
{
    $sql = "SELECT COUNT(*) AS c FROM staff_users WHERE role = 'administrator' AND is_active = 1";
    $res = $conn->query($sql);
    if (!$res) {
        return 0;
    }
    $row = $res->fetch_assoc();
    return (int) ($row['c'] ?? 0);
}

/** All administrator rows (active or inactive), for permanent-delete safety. */
function staff_count_administrator_rows(mysqli $conn): int
{
    $sql = "SELECT COUNT(*) AS c FROM staff_users WHERE role = 'administrator'";
    $res = $conn->query($sql);
    if (!$res) {
        return 0;
    }
    $row = $res->fetch_assoc();
    return (int) ($row['c'] ?? 0);
}
