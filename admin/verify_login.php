<?php
session_start();
require_once '../config.php';
require_once 'admin_auth.php';

if (!empty($_SESSION['admin_logged_in']) && refresh_admin_session($pdo)) {
    header('Location: ' . admin_url('dashboard'));
    exit;
}

$pendingAdminId = (int) ($_SESSION['pending_admin_id'] ?? 0);
if ($pendingAdminId <= 0) {
    header('Location: ' . admin_login_url());
    exit;
}

$stmt = $pdo->prepare("
    SELECT
        id,
        username,
        full_name,
        email,
        is_active,
        can_manage_students,
        can_manage_marks,
        can_import_csv,
        can_backup_db,
        can_maintenance_mode,
        can_manage_admins,
        can_manage_site_settings,
        can_view_analytics
    FROM admins
    WHERE id = ?
    LIMIT 1
");
$stmt->execute([$pendingAdminId]);
$admin = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$admin || empty($admin['is_active'])) {
    unset($_SESSION['pending_admin_id'], $_SESSION['pending_admin_started_at']);
    header('Location: ' . admin_login_url());
    exit;
}

$error = '';
$message = '';

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $action = (string) ($_POST['action'] ?? 'verify');

    if ($action === 'resend') {
        if (send_admin_security_code($pdo, $admin, 'login')) {
            $message = 'A new verification code was sent.';
        } else {
            $error = 'Could not send a verification code. Check the admin email and server mail settings.';
        }
    } else {
        $code = trim((string) ($_POST['code'] ?? ''));
        if (verify_admin_security_code($pdo, $pendingAdminId, 'login', $code)) {
            unset($_SESSION['pending_admin_id'], $_SESSION['pending_admin_started_at']);
            set_admin_session_state($admin);
            header('Location: ' . admin_url('dashboard'));
            exit;
        }
        $error = 'Invalid or expired verification code.';
    }
}

$maskedEmail = (string) ($admin['email'] ?? '');
if ($maskedEmail !== '' && strpos($maskedEmail, '@') !== false) {
    [$local, $domain] = explode('@', $maskedEmail, 2);
    $maskedEmail = substr($local, 0, 2) . str_repeat('*', max(1, strlen($local) - 2)) . '@' . $domain;
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verify Login - BrightVision</title>
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
                <h1>Verification Code</h1>
                <p>Enter the 6-digit code sent to <?= htmlspecialchars($maskedEmail) ?>.</p>
            </div>

            <?php if ($error): ?><div class="msg msg-error" style="display:flex;"><i class="fas fa-circle-exclamation"></i><?= htmlspecialchars($error) ?></div><?php endif; ?>
            <?php if ($message): ?><div class="msg msg-success"><i class="fas fa-check-circle"></i><?= htmlspecialchars($message) ?></div><?php endif; ?>
            <form method="POST">
                <input type="hidden" name="action" value="verify">
                <div class="form-group">
                    <label class="form-label" for="code">Code</label>
                    <input type="text" id="code" name="code" class="form-control" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" autocomplete="one-time-code" required>
                </div>
                <button type="submit" class="btn btn-primary btn-block">Verify <i class="fas fa-arrow-right"></i></button>
            </form>

            <form method="POST" class="auth-secondary-action">
                <input type="hidden" name="action" value="resend">
                <button type="submit" class="btn btn-soft btn-block">
                    Resend code
                    <i class="fas fa-paper-plane"></i>
                </button>
            </form>

            <div class="auth-foot"><a href="logout.php"><i class="fas fa-angle-left"></i> Cancel login</a></div>
        </section>
    </main>
</body>
</html>
