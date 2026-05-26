<?php
/**
 * Helpers for the public password-reset flow.
 */
declare(strict_types=1);

function bdms_password_reset_generate_code(): string
{
    return (string) random_int(100000, 999999);
}

function bdms_password_reset_clear_session(): void
{
    unset(
        $_SESSION['bdms_password_reset_id'],
        $_SESSION['bdms_password_reset_staff_id'],
        $_SESSION['bdms_password_reset_email'],
        $_SESSION['bdms_password_reset_verified']
    );
}

/**
 * @return array{reset_id:int,staff_id:int,email:string,verified:bool}
 */
function bdms_password_reset_get_session_state(): array
{
    return [
        'reset_id' => isset($_SESSION['bdms_password_reset_id']) ? (int) $_SESSION['bdms_password_reset_id'] : 0,
        'staff_id' => isset($_SESSION['bdms_password_reset_staff_id']) ? (int) $_SESSION['bdms_password_reset_staff_id'] : 0,
        'email' => isset($_SESSION['bdms_password_reset_email']) ? (string) $_SESSION['bdms_password_reset_email'] : '',
        'verified' => !empty($_SESSION['bdms_password_reset_verified']),
    ];
}

function bdms_password_reset_store_session(int $resetId, int $staffId, string $email): void
{
    $_SESSION['bdms_password_reset_id'] = $resetId;
    $_SESSION['bdms_password_reset_staff_id'] = $staffId;
    $_SESSION['bdms_password_reset_email'] = $email;
    $_SESSION['bdms_password_reset_verified'] = false;
}

function bdms_password_reset_mark_verified(): void
{
    $_SESSION['bdms_password_reset_verified'] = true;
}
