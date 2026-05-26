<?php
/**
 * PHPMailer bootstrap for BDMS.
 */
declare(strict_types=1);

$bdmsMailerAutoload = __DIR__ . '/vendor/autoload.php';
if (is_file($bdmsMailerAutoload)) {
    require_once $bdmsMailerAutoload;
}

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

function bdms_send_password_reset_otp(string $toEmail, string $toName, string $otp): array
{
    $fromEmail = trim((string) (getenv('BDMS_SMTP_FROM_EMAIL') ?: getenv('BDMS_MAIL_FROM_EMAIL') ?: 'evsuoc.bdms@gmail.com'));
    $fromName = trim((string) (getenv('BDMS_SMTP_FROM_NAME') ?: 'EVSU-OC BDMS'));
    $host = trim((string) (getenv('BDMS_SMTP_HOST') ?: 'smtp.gmail.com'));
    $port = (int) (getenv('BDMS_SMTP_PORT') ?: 587);
    $username = trim((string) (getenv('BDMS_SMTP_USERNAME') ?: 'evsuoc.bdms@gmail.com'));
    $password = (string) (getenv('BDMS_SMTP_PASSWORD') ?: 'aklr oxjo jfdj enrw');
    $encryption = strtolower(trim((string) (getenv('BDMS_SMTP_ENCRYPTION') ?: 'tls')));

    if ($fromEmail === '') {
        $fromEmail = $username !== '' ? $username : 'no-reply@localhost';
    }

    $subject = 'EVSU-OC BDMS password reset code';
    $textBody = "Your EVSU-OC BDMS password reset code is: {$otp}\n\nThis code expires in 10 minutes. If you did not request a password reset, ignore this message.";
    $htmlBody = '<p>Your EVSU-OC BDMS password reset code is:</p><p style="font-size:20px;font-weight:700;letter-spacing:0.18em;">' . htmlspecialchars($otp, ENT_QUOTES, 'UTF-8') . '</p><p>This code expires in 10 minutes. If you did not request a password reset, ignore this message.</p>';

    if ($host !== '' && class_exists(PHPMailer::class)) {
        try {
            $mail = new PHPMailer(true);
            $mail->CharSet = 'UTF-8';
            $mail->isSMTP();
            $mail->Host = $host;
            $mail->Port = $port;
            $mail->SMTPAuth = ($username !== '' || $password !== '');
            if ($username !== '') {
                $mail->Username = $username;
            }
            if ($password !== '') {
                $mail->Password = $password;
            }
            if ($encryption === 'ssl') {
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
            } elseif ($encryption === 'tls') {
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            }
            $mail->setFrom($fromEmail, $fromName);
            $mail->addAddress($toEmail, $toName !== '' ? $toName : $toEmail);
            $mail->Subject = $subject;
            $mail->Body = $htmlBody;
            $mail->AltBody = $textBody;
            $mail->isHTML(true);
            $mail->send();

            return [
                'ok' => true,
                'message' => 'OTP email sent.',
            ];
        } catch (Exception $e) {
            return [
                'ok' => false,
                'message' => 'Unable to send OTP email right now.',
            ];
        }
    }

    if (!function_exists('mail')) {
        return [
            'ok' => false,
            'message' => 'Email delivery is not configured on this server.',
        ];
    }

    $headers = [
        'From: ' . $fromName . ' <' . $fromEmail . '>',
        'Reply-To: ' . $fromEmail,
        'MIME-Version: 1.0',
        'Content-Type: text/plain; charset=UTF-8',
    ];

    $sent = mail($toEmail, $subject, $textBody, implode("\r\n", $headers));

    return [
        'ok' => $sent,
        'message' => $sent ? 'OTP email sent.' : 'Unable to send OTP email right now.',
    ];
}
