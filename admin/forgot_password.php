<?php
session_start();
require_once '../config.php';
require_once 'admin_auth.php';

if (!empty($_SESSION['admin_logged_in']) && refresh_admin_session($pdo)) {
    header('Location: ' . admin_url('dashboard'));
    exit;
}

$step = (string) ($_SESSION['reset_admin_id'] ?? '') !== '' ? 'reset' : 'request';
$error = '';
$message = '';

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $action = (string) ($_POST['action'] ?? 'request');

    try {
        if ($action === 'request') {
            $identity = trim((string) ($_POST['identity'] ?? ''));
            if ($identity === '') {
                throw new RuntimeException('Enter your username or security email.');
            }

            $stmt = $pdo->prepare('SELECT id, username, full_name, email, is_active FROM admins WHERE username = ? OR email = ? LIMIT 1');
            $stmt->execute([$identity, $identity]);
            $admin = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($admin && !empty($admin['is_active'])) {
                if (!send_admin_security_code($pdo, $admin, 'reset_password')) {
                    throw new RuntimeException('Could not send a reset code. Set Gmail SMTP details in config.php and use a Gmail App Password, then try again.');
                }
                $_SESSION['reset_admin_id'] = (int) $admin['id'];
                $step = 'reset';
            }

            $message = 'If an active admin account with a security email exists, a reset code has been sent.';
        } elseif ($action === 'reset') {
            $adminId = (int) ($_SESSION['reset_admin_id'] ?? 0);
            $code = trim((string) ($_POST['code'] ?? ''));
            $newPassword = (string) ($_POST['new_password'] ?? '');
            $confirmPassword = (string) ($_POST['confirm_password'] ?? '');

            if ($adminId <= 0) {
                throw new RuntimeException('Start the password reset again.');
            }
            if (strlen($newPassword) < 8) {
                throw new RuntimeException('New password must be at least 8 characters.');
            }
            if ($newPassword !== $confirmPassword) {
                throw new RuntimeException('New password and confirmation do not match.');
            }
            if (!verify_admin_security_code($pdo, $adminId, 'reset_password', $code)) {
                throw new RuntimeException('Invalid or expired reset code.');
            }

            $pdo->prepare('UPDATE admins SET password_hash = ? WHERE id = ?')
                ->execute([password_hash($newPassword, PASSWORD_DEFAULT), $adminId]);
            unset($_SESSION['reset_admin_id']);
            $message = 'Password reset successfully. You can log in with the new password.';
            $step = 'done';
        }
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password - BrightVision</title>
    <link rel="icon" type="image/png" sizes="32x32" href="/Bv-StudentMonitor/icon.png?v=20260424">
    <link rel="shortcut icon" href="/Bv-StudentMonitor/icon.png?v=20260424">
    <link rel="preconnect" href="https://cdnjs.cloudflare.com" crossorigin>
    <link rel="preload" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" as="style" onload="this.onload=null;this.rel='stylesheet'">
    <noscript><link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css"></noscript>
    <link rel="stylesheet" href="../css/style.css">
</head>
<body class="auth-page">
    <div class="bg-mesh"><div class="orb orb-1"></div><div class="orb orb-2"></div><div class="orb orb-3"></div></div>
    <main class="auth-wrap">
        <section class="glass-panel auth-card reveal d1">
            <div class="auth-brand">
                <img src="../logo.png" alt="BrightVision English Academy" class="auth-logo-img">
                <h1>Forgot Password</h1>
                <p>Use your security email to receive a reset code.</p>
            </div>

            <?php if ($error): ?><div class="msg msg-error" style="display:flex;"><i class="fas fa-circle-exclamation"></i><?= htmlspecialchars($error) ?></div><?php endif; ?>
            <?php if ($message): ?><div class="msg msg-success"><i class="fas fa-check-circle"></i><?= htmlspecialchars($message) ?></div><?php endif; ?>
            <?php if ($step === 'reset'): ?>
                <form method="POST">
                    <input type="hidden" name="action" value="reset">
                    <div class="form-group">
                        <label class="form-label" for="code">Reset Code</label>
                        <input type="text" id="code" name="code" class="form-control" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" autocomplete="one-time-code" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="new_password">New Password</label>
                        <input type="password" id="new_password" name="new_password" class="form-control" minlength="8" autocomplete="new-password" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="confirm_password">Confirm New Password</label>
                        <input type="password" id="confirm_password" name="confirm_password" class="form-control" minlength="8" autocomplete="new-password" required>
                    </div>
                    <button type="submit" class="btn btn-primary btn-block">Reset Password <i class="fas fa-arrow-right"></i></button>
                </form>
            <?php elseif ($step === 'done'): ?>
                <a href="login" class="btn btn-primary btn-block">Back to Login <i class="fas fa-arrow-right"></i></a>
            <?php else: ?>
                <form method="POST">
                    <input type="hidden" name="action" value="request">
                    <div class="form-group">
                        <label class="form-label" for="identity">Username or Security Email</label>
                        <input type="text" id="identity" name="identity" class="form-control" autocomplete="username" required>
                    </div>
                    <button type="submit" class="btn btn-primary btn-block">Send Reset Code <i class="fas fa-paper-plane"></i></button>
                </form>
            <?php endif; ?>

            <div class="auth-foot"><a href="login"><i class="fas fa-angle-left"></i> Back to login</a></div>
        </section>
    </main>
</body>
</html>
