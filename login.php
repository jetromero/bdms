<?php
session_start();
$title = 'Sign in | BDMS';

if (isset($_SESSION['user'])) {
    $to_audit = (isset($_SESSION['role']) && $_SESSION['role'] === 'superadmin')
        || (string) ($_SESSION['user'] ?? '') === 'superadmin';
    header($to_audit ? 'Location: audit_log.php' : 'Location: dashboard.php');
    exit();
}

$login_error_message = null;
if (isset($_SESSION['error'])) {
    $login_error_message = (string) $_SESSION['error'];
    unset($_SESSION['error']);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/png" href="evsulogo.png">
    <title><?php echo $title; ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link rel="stylesheet" href="assets/bdms.css">
    <style>
        html, body {
            margin: 0; padding: 0;
            height: 100%;
            font-family: var(--font-sans);
        }

        /* ── split layout ── */
        .login-page {
            display: grid;
            grid-template-columns: 1.1fr 1fr;
            min-height: 100vh;
        }

        /* ── left: illustration panel ── */
        .login-art {
            position: relative;
            background: url('assets/login-bg.png') no-repeat 0% center / cover;
            background-color: #8b0a1a;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            padding: 36px 40px;
            overflow: hidden;
        }
        .login-art::before {
            content: '';
            position: absolute;
            inset: 0;
            background: linear-gradient(
                160deg,
                rgba(20,4,8,0.20) 0%,
                rgba(20,4,8,0.50) 100%
            );
            pointer-events: none;
        }

        /* floating bubble orbs */
        .login-art-bubble {
            position: absolute;
            border-radius: 50%;
            background: rgba(255,255,255,0.06);
            pointer-events: none;
        }
        .login-art-bubble:nth-child(1) { width: 200px; height: 200px; top: -60px; right: -60px; }
        .login-art-bubble:nth-child(2) { width: 120px; height: 120px; bottom: 80px; left: -30px; background: rgba(255,255,255,0.04); }
        .login-art-bubble:nth-child(3) { width: 60px;  height: 60px;  top: 38%;  left: 12%; }

        .login-art-top,
        .login-art-bottom {
            position: relative;
            z-index: 1;
        }
        .login-art-logo {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .login-art-logo img {
            width: 42px; height: 42px;
            object-fit: contain;
            border-radius: 50%;
            background: rgba(255,255,255,0.15);
            padding: 4px;
        }
        .login-art-logo-name {
            font-weight: 800;
            font-size: 15px;
            color: #fff;
            letter-spacing: 0.01em;
        }

        .login-art-bottom h2 {
            color: #fff;
            font-size: 28px;
            font-weight: 800;
            letter-spacing: -0.02em;
            line-height: 1.2;
            margin: 0 0 10px;
            text-shadow: 0 2px 12px rgba(0,0,0,0.3);
        }
        .login-art-bottom p {
            color: rgba(255,255,255,0.80);
            font-size: 14px;
            line-height: 1.6;
            margin: 0;
            max-width: 38ch;
        }

        /* blood drop badge */
        .login-art-badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: rgba(255,255,255,0.12);
            border: 1px solid rgba(255,255,255,0.20);
            border-radius: var(--radius-pill);
            padding: 6px 14px 6px 6px;
            margin-bottom: 16px;
            width: fit-content;
        }
        .login-art-badge-dot {
            width: 26px; height: 26px;
            background: var(--brand-light);
            border-radius: 50% 50% 50% 0;
            transform: rotate(-45deg);
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 2px 8px rgba(196,30,58,0.50);
        }
        .login-art-badge-dot i {
            transform: rotate(45deg);
            color: #fff;
            font-size: 10px;
        }
        .login-art-badge span {
            color: rgba(255,255,255,0.90);
            font-size: 12px;
            font-weight: 600;
            letter-spacing: 0.03em;
        }

        /* ── right: form panel ── */
        .login-form-panel {
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 48px 40px;
            background: #fff;
            position: relative;
            overflow: hidden;
        }

        /* decorative bubbles on right panel */
        .login-form-panel::before {
            content: '';
            position: absolute;
            width: 260px; height: 260px;
            border-radius: 50%;
            background: var(--brand-soft);
            top: -80px; right: -80px;
            pointer-events: none;
        }
        .login-form-panel::after {
            content: '';
            position: absolute;
            width: 160px; height: 160px;
            border-radius: 50%;
            background: var(--brand-soft);
            bottom: -50px; left: -50px;
            pointer-events: none;
            opacity: 0.7;
        }

        .login-card {
            width: 100%;
            max-width: 380px;
            position: relative;
            z-index: 1;
        }

        /* drop icon header */
        .login-drop-icon {
            width: 56px; height: 56px;
            background: var(--brand);
            border-radius: 50% 50% 50% 0;
            transform: rotate(-45deg);
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 24px;
            box-shadow: 0 4px 20px rgba(196,30,58,0.40);
        }
        .login-drop-icon i {
            transform: rotate(45deg);
            color: #fff;
            font-size: 22px;
        }

        .login-heading {
            font-size: 26px;
            font-weight: 800;
            color: var(--text);
            margin: 0 0 4px;
            letter-spacing: -0.02em;
        }
        .login-sub {
            font-size: 14px;
            color: var(--text-muted);
            margin: 0 0 28px;
            line-height: 1.5;
        }

        .login-field { margin-bottom: 16px; }
        .login-field label {
            display: block;
            font-size: 12px;
            font-weight: 700;
            color: var(--text-soft);
            margin-bottom: 6px;
            letter-spacing: 0.01em;
        }
        .login-field input {
            width: 100%;
            padding: 11px 16px;
            font-family: inherit;
            font-size: 14px;
            color: var(--text);
            background: var(--surface);
            border: 1.5px solid var(--border);
            border-radius: var(--radius-pill);
            transition: border-color 130ms, box-shadow 130ms;
        }
        .login-field input::placeholder { color: var(--text-subtle); }
        .login-field input:focus {
            outline: none;
            border-color: var(--brand);
            box-shadow: 0 0 0 4px var(--brand-ring);
        }

        .login-password-wrap {
            position: relative;
        }
        .login-password-wrap input {
            padding-right: 46px;
        }
        .login-password-toggle {
            position: absolute;
            right: 10px;
            top: 50%;
            transform: translateY(-50%);
            border: none;
            background: transparent;
            color: var(--text-muted);
            cursor: pointer;
            padding: 8px 10px;
            line-height: 1;
            border-radius: var(--radius-md);
            font-size: 15px;
        }
        .login-password-toggle:hover {
            color: var(--text);
            background: rgba(0,0,0,0.04);
        }
        .login-password-toggle:focus {
            outline: none;
            color: var(--brand);
        }

        .login-btn {
            width: 100%;
            padding: 13px 20px;
            font-size: 14.5px;
            font-weight: 700;
            border-radius: var(--radius-pill);
            margin-top: 6px;
            background: var(--brand);
            color: #fff;
            border: none;
            cursor: pointer;
            box-shadow: 0 4px 20px rgba(196,30,58,0.40);
            transition: background 130ms, box-shadow 130ms, transform 130ms;
            font-family: inherit;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 9px;
        }
        .login-btn:hover {
            background: var(--brand-strong);
            box-shadow: 0 6px 28px rgba(196,30,58,0.50);
            transform: translateY(-1px);
        }
        .login-btn:active { transform: translateY(0); }

        .login-footer {
            margin-top: 28px;
            text-align: center;
            color: var(--text-subtle);
            font-size: 12px;
        }

        /* ── responsive ── */
        @media (max-width: 860px) {
            .login-page { grid-template-columns: 1fr; }
            .login-art {
                min-height: 240px;
                padding: 28px 28px 32px;
            }
            .login-art-badge { 
                margin: 10px 0px 10px 0px; 
            }
            .login-form-panel { padding: 36px 24px; }
        }
        @media (max-width: 480px) {
            .login-art { min-height: 200px; }
            .login-art-bottom h2 { font-size: 22px; }
        }
    </style>
</head>
<body>
    <div class="login-page">

        <!-- LEFT: illustration -->
        <aside class="login-art">
            <div class="login-art-bubble"></div>
            <div class="login-art-bubble"></div>
            <div class="login-art-bubble"></div>

            <div class="login-art-top">
                <div class="login-art-logo">
                    <img src="evsulogo.png" alt="EVSU Logo">
                    <span class="login-art-logo-name">EVSU-OC</span>
                </div>
            </div>

            <div class="login-art-bottom">
                <div class="login-art-badge">
                    <div class="login-art-badge-dot"><i class="fa fa-tint"></i></div>
                    <span>Blood Donation Management</span>
                </div>
                <h2>Every drop<br>saves a life.</h2>
                <p>Manage donors, donations, and blood inventory for the EVSU-OC blood program — securely in one place.</p>
            </div>
        </aside>

        <!-- RIGHT: form -->
        <main class="login-form-panel">
            <div class="login-card">

                <div class="login-drop-icon">
                    <i class="fa fa-tint"></i>
                </div>

                <h1 class="login-heading">Sign in</h1>
                <p class="login-sub">Enter your staff or administrator credentials.</p>

                <form id="login-form" action="authenticate.php" method="POST" autocomplete="off">
                    <div class="login-field">
                        <label for="username">Username</label>
                        <input id="username" name="username" type="text" required
                               autocomplete="off" placeholder="Enter your username">
                    </div>
                    <div class="login-field">
                        <label for="password">Password</label>
                        <div class="login-password-wrap">
                            <input id="password" name="password" type="password" required
                                   autocomplete="new-password" placeholder="••••••••">
                            <button type="button" class="login-password-toggle" id="login-password-toggle"
                                    aria-label="Show password" title="Show password">
                                <i class="fa fa-eye-slash" aria-hidden="true"></i>
                            </button>
                        </div>
                    </div>
                    <button type="submit" class="login-btn">
                        <i class="fa fa-sign-in-alt"></i> Sign in
                    </button>
                </form>

                <div class="login-forgot-row">
                    <button type="button" class="login-forgot-btn" id="login-forgot-password">Forgot password?</button>
                </div>

                <p class="login-footer">EVSU-OC Blood Donation Management &copy; <?php echo date('Y'); ?></p>
            </div>
        </main>

    </div>

    <div class="modal bdms-reset-modal" id="password-reset-modal" data-bdms-reset-modal aria-hidden="true">
        <div class="modal-content bdms-reset-modal-content" role="dialog" aria-modal="true" aria-labelledby="password-reset-modal-title">
            <button type="button" class="close" id="password-reset-close" aria-label="Close password reset dialog" title="Close">&times;</button>
            <h3 id="password-reset-modal-title"><i class="fas fa-key"></i> Reset password</h3>
            <p class="card-sub">Enter your email, confirm the OTP, then choose a new password.</p>

            <section class="bdms-reset-step is-active" data-reset-step="request">
                <form id="reset-request-form" class="bdms-reset-fields" autocomplete="off">
                    <div class="form-group-full">
                        <label for="reset-email">Email address</label>
                        <input type="email" id="reset-email" name="email" required autocomplete="email" placeholder="name@email.com">
                    </div>
                    <div class="alert alert-danger bdms-reset-email-alert hidden" id="reset-email-alert" role="alert"></div>
                    <div class="bdms-reset-inline-actions">
                        <button type="submit" class="btn btn-primary" id="reset-send-otp-btn"><i class="fas fa-paper-plane" aria-hidden="true"></i> <span>Send OTP</span></button>
                    </div>
                </form>
            </section>

            <section class="bdms-reset-step" data-reset-step="verify">
                <form id="reset-verify-form" class="bdms-reset-fields" autocomplete="off">
                    <div class="form-group-full">
                        <label for="reset-otp">Enter OTP</label>
                        <input type="text" id="reset-otp" name="otp" required inputmode="numeric" maxlength="6" class="bdms-reset-code" placeholder="000000" autocomplete="one-time-code">
                        <div class="bdms-reset-help">We sent the OTP to <strong data-reset-email-label>your email</strong>.</div>
                    </div>
                    <div class="bdms-reset-inline-actions">
                        <button type="submit" class="btn btn-primary"><i class="fas fa-shield-halved"></i> Verify OTP</button>
                        <button type="button" class="btn btn-ghost-light" data-reset-resend><i class="fas fa-rotate-right"></i> Resend OTP</button>
                    </div>
                </form>
            </section>

            <section class="bdms-reset-step" data-reset-step="change">
                <form id="reset-change-form" class="bdms-reset-fields" autocomplete="off">
                    <div class="form-group-full">
                        <label for="reset-new-password">New password</label>
                        <input type="password" id="reset-new-password" name="new_password" required minlength="8" autocomplete="new-password" placeholder="Create a new password">
                    </div>
                    <div class="form-group-full">
                        <label for="reset-confirm-password">Confirm password</label>
                        <input type="password" id="reset-confirm-password" name="confirm_password" required minlength="8" autocomplete="new-password" placeholder="Repeat the new password">
                    </div>
                    <div class="bdms-reset-inline-actions">
                        <button type="submit" class="btn btn-primary" id="reset-update-password-btn"><i class="fas fa-save" aria-hidden="true"></i> <span>Update password</span></button>
                    </div>
                </form>
            </section>
        </div>
    </div>

    <script src="assets/bdms.js?v=20260527"></script>
    <script>
        <?php if ($login_error_message !== null): ?>
        (function () {
            if (typeof bdmsToast !== 'function') return;
            bdmsToast(
                'Something went wrong!',
                <?php echo json_encode($login_error_message, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>,
                'error',
                3000
            );
        })();
        <?php endif; ?>

        (function () {
            var input = document.getElementById('password');
            var btn = document.getElementById('login-password-toggle');
            if (!input || !btn) return;
            var icon = btn.querySelector('i');
            btn.addEventListener('click', function () {
                var willShow = input.type === 'password';
                input.type = willShow ? 'text' : 'password';
                if (icon) {
                    icon.className = willShow ? 'fa fa-eye' : 'fa fa-eye-slash';
                }
                btn.setAttribute('aria-label', willShow ? 'Hide password' : 'Show password');
                btn.setAttribute('title', willShow ? 'Hide password' : 'Show password');
            });
        })();

        (function () {
            var modal = document.getElementById('password-reset-modal');
            if (!modal) return;

            var openBtn = document.getElementById('login-forgot-password');
            var closeButtons = [
                document.getElementById('password-reset-close')
            ];
            var statusBox = modal.querySelector('[data-reset-status]');
            var requestStep = modal.querySelector('[data-reset-step="request"]');
            var verifyStep = modal.querySelector('[data-reset-step="verify"]');
            var changeStep = modal.querySelector('[data-reset-step="change"]');
            var requestForm = document.getElementById('reset-request-form');
            var verifyForm = document.getElementById('reset-verify-form');
            var changeForm = document.getElementById('reset-change-form');
            var emailInput = document.getElementById('reset-email');
            var emailAlert = document.getElementById('reset-email-alert');
            var otpInput = document.getElementById('reset-otp');
            var emailLabel = modal.querySelector('[data-reset-email-label]');
            var resendButton = modal.querySelector('[data-reset-resend]');
            var sendOtpButton = document.getElementById('reset-send-otp-btn');
            var updatePasswordButton = document.getElementById('reset-update-password-btn');
            var resetEmail = '';

            function setStatus(message, type) {
                if (!statusBox) return;
                statusBox.textContent = message;
                statusBox.className = 'bdms-reset-status';
                if (type) {
                    statusBox.classList.add('is-' + type);
                }
            }

            function showStep(stepName) {
                [requestStep, verifyStep, changeStep].forEach(function (step) {
                    if (!step) return;
                    step.classList.toggle('is-active', step.getAttribute('data-reset-step') === stepName);
                });
            }

            function resetForms() {
                if (requestForm) requestForm.reset();
                if (verifyForm) verifyForm.reset();
                if (changeForm) changeForm.reset();
                setEmailAlert('');
            }

            function setEmailAlert(message) {
                if (!emailAlert) return;
                if (message) {
                    emailAlert.textContent = message;
                    emailAlert.classList.remove('hidden');
                } else {
                    emailAlert.textContent = '';
                    emailAlert.classList.add('hidden');
                }
            }

            function openModal() {
                resetForms();
                resetEmail = '';
                setStatus('Enter your email address to receive a 6-digit OTP.', 'info');
                showStep('request');
                modal.classList.add('is-open');
                modal.setAttribute('aria-hidden', 'false');
                if (emailInput) {
                    setTimeout(function () { emailInput.focus(); }, 0);
                }
            }

            function closeModal() {
                modal.classList.remove('is-open');
                modal.setAttribute('aria-hidden', 'true');
                resetForms();
                resetEmail = '';
                setStatus('Enter your email address to receive a 6-digit OTP.', 'info');
                showStep('request');
            }

            function parseJson(response) {
                return response.text().then(function (text) {
                    try {
                        return JSON.parse(text);
                    } catch (error) {
                        return { ok: false, message: 'Unexpected server response.' };
                    }
                });
            }

            function postForm(url, formData) {
                return fetch(url, {
                    method: 'POST',
                    headers: { 'Accept': 'application/json' },
                    body: formData
                }).then(function (response) {
                    return parseJson(response).then(function (data) {
                        if (!response.ok && (!data || typeof data.ok === 'undefined')) {
                            data = { ok: false, message: 'Request failed.' };
                        }
                        return { data: data, status: response.status };
                    });
                });
            }

            function setSendOtpLoading(isLoading) {
                if (!sendOtpButton) return;
                sendOtpButton.disabled = !!isLoading;
                sendOtpButton.classList.toggle('is-loading', !!isLoading);
                var textSpan = sendOtpButton.querySelector('span');
                var icon = sendOtpButton.querySelector('i');
                if (textSpan) {
                    textSpan.textContent = isLoading ? 'Sending…' : 'Send OTP';
                }
                if (icon) {
                    icon.className = isLoading ? 'bdms-btn-spinner' : 'fas fa-paper-plane';
                    icon.setAttribute('aria-hidden', 'true');
                }
            }

            function setUpdatePasswordLoading(isLoading) {
                if (!updatePasswordButton) return;
                updatePasswordButton.disabled = !!isLoading;
                updatePasswordButton.classList.toggle('is-loading', !!isLoading);
                var textSpan = updatePasswordButton.querySelector('span');
                var icon = updatePasswordButton.querySelector('i');
                if (textSpan) {
                    textSpan.textContent = isLoading ? 'Saving…' : 'Update password';
                }
                if (icon) {
                    icon.className = isLoading ? 'bdms-btn-spinner' : 'fas fa-save';
                    icon.setAttribute('aria-hidden', 'true');
                }
            }

            function handleResponse(data, successType) {
                var type = data && data.ok ? (successType || 'success') : 'error';
                setStatus((data && data.message) ? data.message : 'Request failed.', type);
                if (data && data.ok && typeof bdmsToast === 'function') {
                    bdmsToast(type === 'success' ? 'Success' : 'Notice', data.message || '', type, 3500);
                }
            }

            if (openBtn) {
                openBtn.addEventListener('click', openModal);
            }
            closeButtons.forEach(function (button) {
                if (button) {
                    button.addEventListener('click', closeModal);
                }
            });

            modal.addEventListener('click', function (event) {
                if (event.target === modal) {
                    event.preventDefault();
                    event.stopPropagation();
                }
            });

            document.addEventListener('keydown', function (event) {
                if (modal.classList.contains('is-open') && event.key === 'Escape') {
                    event.preventDefault();
                    event.stopPropagation();
                }
            });

            if (requestForm) {
                requestForm.addEventListener('submit', function (event) {
                    event.preventDefault();
                    setSendOtpLoading(true);
                    var formData = new FormData(requestForm);
                    resetEmail = String(formData.get('email') || '').trim();
                    postForm('forgot_password_request.php', formData).then(function (result) {
                        var data = result.data;
                        var status = result.status;
                        setSendOtpLoading(false);
                        handleResponse(data, 'success');
                        if (data && data.ok) {
                            setEmailAlert('');
                            if (emailLabel) {
                                emailLabel.textContent = resetEmail;
                            }
                            showStep('verify');
                            if (otpInput) {
                                setTimeout(function () { otpInput.focus(); }, 0);
                            }
                        } else if (status === 404) {
                            setEmailAlert(data && data.message ? data.message : 'That email address doesn\'t exist.');
                            if (emailInput) {
                                setTimeout(function () { emailInput.focus(); }, 0);
                            }
                        } else {
                            setEmailAlert('');
                        }
                    }).catch(function () {
                        setSendOtpLoading(false);
                        setEmailAlert('');
                        setStatus('Unable to send OTP right now. Please try again.', 'error');
                    });
                });
            }

            if (resendButton) {
                resendButton.addEventListener('click', function () {
                    var email = resetEmail || (emailInput ? String(emailInput.value || '').trim() : '');
                    if (!email) {
                        setStatus('Enter an email address first.', 'error');
                        return;
                    }
                    var formData = new FormData();
                    formData.append('email', email);
                    postForm('forgot_password_request.php', formData).then(function (result) {
                        handleResponse(result.data, 'success');
                        if (result.data && result.data.ok) {
                            setEmailAlert('');
                            resetEmail = email;
                            if (emailLabel) {
                                emailLabel.textContent = resetEmail;
                            }
                            showStep('verify');
                            if (otpInput) {
                                setTimeout(function () { otpInput.focus(); }, 0);
                            }
                        }
                    });
                });
            }

            if (verifyForm) {
                verifyForm.addEventListener('submit', function (event) {
                    event.preventDefault();
                    postForm('forgot_password_verify.php', new FormData(verifyForm)).then(function (result) {
                        handleResponse(result.data, 'success');
                        if (result.data && result.data.ok) {
                            showStep('change');
                            var firstPassword = document.getElementById('reset-new-password');
                            if (firstPassword) {
                                setTimeout(function () { firstPassword.focus(); }, 0);
                            }
                        }
                    });
                });
            }

            if (changeForm) {
                changeForm.addEventListener('submit', function (event) {
                    event.preventDefault();
                    setUpdatePasswordLoading(true);
                    postForm('forgot_password_reset.php', new FormData(changeForm)).then(function (result) {
                        setUpdatePasswordLoading(false);
                        handleResponse(result.data, 'success');
                        if (result.data && result.data.ok) {
                            closeModal();
                        }
                    }).catch(function () {
                        setUpdatePasswordLoading(false);
                        setStatus('Unable to update the password right now. Please try again.', 'error');
                    });
                });
            }
        })();
    </script>
</body>
</html>
