<?php
session_start();
require_once '../config.php';
require_once 'admin_auth.php';

if (!empty($_SESSION['admin_logged_in']) && refresh_admin_session($pdo)) {
    header('Location: ' . admin_url('dashboard'));
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    $stmt = $pdo->prepare("
        SELECT
            id,
            username,
            full_name,
            password_hash,
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
        WHERE username = ?
        LIMIT 1
    ");
    $stmt->execute([$username]);
    $admin = $stmt->fetch();

    if ($admin && password_verify($password, $admin['password_hash'])) {
        if (empty($admin['is_active'])) {
            $error = 'Your account is currently inactive. Contact a system administrator.';
        } else {
            set_admin_session_state($admin);
            header('Location: ' . admin_url('dashboard'));
            exit;
        }
    } else {
        $error = 'Invalid credentials.';
    }

}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login - BrightVision</title>
    <link rel="icon" type="image/png" sizes="32x32" href="/Bv-StudentMonitor/icon.png?v=20260424">
    <link rel="shortcut icon" href="/Bv-StudentMonitor/icon.png?v=20260424">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="../css/style.css">
</head>
<body class="auth-page">
    <div class="bg-mesh">
        <div class="orb orb-1"></div>
        <div class="orb orb-2"></div>
        <div class="orb orb-3"></div>
    </div>

    <nav class="glass-nav glass-panel">
        <a href="../index.php" class="nav-brand logo-only">
            <img src="../logo.png" alt="BrightVision English Academy">
        </a>
        <div class="nav-links">
            <a href="../index.php" class="nav-link"><i class="fas fa-arrow-left"></i> Portal</a>
        </div>
    </nav>

    <main class="auth-wrap">
        <section class="glass-panel auth-card reveal d1">
            <div class="auth-brand">
                <img src="../logo.png" alt="BrightVision English Academy" class="auth-logo-img">
                <h1>Admin Access</h1>
                <p>Sign in to manage students, marks, and reporting.</p>
            </div>

            <?php if ($error): ?>
                <div class="msg msg-error" style="display:flex;">
                    <i class="fas fa-circle-exclamation"></i>
                    <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>

            <form method="POST">
                <div class="form-group">
                    <label class="form-label" for="username">Username</label>
                    <input type="text" id="username" name="username" class="form-control" autocomplete="off" required>
                </div>
                <div class="form-group">
                    <label class="form-label" for="password">Password</label>
                    <input type="password" id="password" name="password" class="form-control" autocomplete="off" required>
                </div>
                <button type="submit" class="btn btn-primary btn-block">
                    Authenticate
                    <i class="fas fa-arrow-right"></i>
                </button>
            </form>

            <div class="auth-foot">
                <a href="../index.php"><i class="fas fa-angle-left"></i> Return to student portal</a>
            </div>
        </section>
    </main>
</body>
</html>




