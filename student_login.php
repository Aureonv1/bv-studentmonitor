<?php
session_start();
require_once 'maintenance_mode.php';
require_once 'config.php';
require_once 'student_auth.php';
require_once 'footer.php';

if (is_maintenance_enabled()) {
    $maintenance = get_maintenance_state();
    $message = $maintenance['message'] ?? maintenance_default_message();
    $footerSettings = get_site_footer_settings($pdo);
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Student Login - BrightVision</title>
        <link rel="icon" type="image/png" sizes="32x32" href="/Bv-StudentMonitor/icon.png?v=20260424">
    <link rel="shortcut icon" href="/Bv-StudentMonitor/icon.png?v=20260424">
        <link rel="preconnect" href="https://cdnjs.cloudflare.com" crossorigin>
    <link rel="preload" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" as="style" onload="this.onload=null;this.rel='stylesheet'">
    <noscript><link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css"></noscript>
        <link rel="stylesheet" href="css/style.css">
    </head>
    <body class="auth-page">
        <div class="bg-mesh">
            <div class="orb orb-1"></div>
            <div class="orb orb-2"></div>
            <div class="orb orb-3"></div>
        </div>

        <nav class="glass-nav glass-panel">
            <a href="student_login" class="nav-brand logo-only">
                <img src="logo.png" alt="BrightVision English Academy">
            </a>
        </nav>

        <main class="auth-wrap">
            <section class="glass-panel auth-card reveal d1">
                <div class="auth-brand">
                    <img src="logo.png" alt="BrightVision English Academy" class="auth-logo-img">
                    <h1>Student Login Disabled</h1>
                    <p><?= htmlspecialchars($message) ?></p>
                </div>
            </section>
        </main>

        <?php render_portal_footer($footerSettings, ''); ?>
    </body>
    </html>
    <?php
    exit;
}

if (!empty($_SESSION['student_logged_in']) && refresh_student_session($pdo)) {
    header('Location: ' . student_url('student_profile'));
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = strtolower(trim((string) ($_POST['username'] ?? '')));
    $passwordRaw = (string) ($_POST['password'] ?? '');
    $password = trim($passwordRaw);

    if ($username === '' || $password === '') {
        $error = 'Username and password are required.';
    } else {
        $stmt = $pdo->prepare("
            SELECT
                sa.id AS account_id,
                sa.student_id,
                sa.username,
                sa.password_hash,
                sa.is_active,
                s.name,
                c.class_name,
                y.year_name
            FROM student_accounts sa
            JOIN students s ON s.id = sa.student_id
            LEFT JOIN classes c ON c.id = s.class_id
            LEFT JOIN academic_years y ON y.id = s.academic_year_id
            WHERE sa.username = ?
            LIMIT 1
        ");
        $stmt->execute([$username]);
        $row = $stmt->fetch();

        $hash = (string) ($row['password_hash'] ?? '');
        $passwordOk = false;
        if ($row && $hash !== '') {
            $passwordOk = password_verify($passwordRaw, $hash) || password_verify($password, $hash);
        }

        if (!$row || !$passwordOk) {
            $error = 'Invalid username or password.';
        } elseif (empty($row['is_active'])) {
            $error = 'Your account is currently inactive. Please contact the academy.';
        } else {
            set_student_session_state($row);
            $pdo->prepare("UPDATE student_accounts SET last_login_at = NOW() WHERE id = ?")
                ->execute([(int) $row['account_id']]);

            header('Location: ' . student_url('student_profile'));
            exit;
        }
    }
}

$footerSettings = get_site_footer_settings($pdo);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Login - BrightVision</title>
    <link rel="icon" type="image/png" sizes="32x32" href="/Bv-StudentMonitor/icon.png?v=20260424">
    <link rel="shortcut icon" href="/Bv-StudentMonitor/icon.png?v=20260424">
    <link rel="preconnect" href="https://cdnjs.cloudflare.com" crossorigin>
    <link rel="preload" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" as="style" onload="this.onload=null;this.rel='stylesheet'">
    <noscript><link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css"></noscript>
    <link rel="stylesheet" href="css/style.css">
</head>
<body class="auth-page">
    <div class="bg-mesh">
        <div class="orb orb-1"></div>
        <div class="orb orb-2"></div>
        <div class="orb orb-3"></div>
    </div>

    <nav class="glass-nav glass-panel">
        <a href="student_login" class="nav-brand logo-only">
            <img src="logo.png" alt="BrightVision English Academy">
        </a>
    </nav>

    <main class="auth-wrap">
        <section class="glass-panel auth-card reveal d1">
            <div class="auth-brand">
                <img src="logo.png" alt="BrightVision English Academy" class="auth-logo-img">
                <h1>Student Account Login</h1>
                <p>Use the username and password provided by the academy.</p>
            </div>

            <?php if ($error): ?>
                <div class="msg msg-error" style="display:flex;">
                    <i class="fas fa-circle-exclamation"></i>
                    <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>

            <form method="POST" autocomplete="off">
                <div class="form-group">
                    <label class="form-label" for="username">Username</label>
                    <input type="text" id="username" name="username" class="form-control" required>
                </div>
                <div class="form-group">
                    <label class="form-label" for="password">Password</label>
                    <input type="password" id="password" name="password" class="form-control" required>
                </div>
                <button type="submit" class="btn btn-primary btn-block">
                    Sign In
                    <i class="fas fa-arrow-right"></i>
                </button>
            </form>

            <div class="auth-foot">
                <span class="text-muted" style="font-size:0.84rem;">Use only the credentials given by your class teacher.</span>
            </div>
        </section>
    </main>

    <?php render_portal_footer($footerSettings, ''); ?>
</body>
</html>




