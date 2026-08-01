<?php
require_once __DIR__ . '/config.php';
start_secure_session();

if (current_user()) {
    redirect(dashboard_path_for_role((string) (current_user()['role'] ?? '')));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $ip = client_ip();
    $_SESSION['old_login'] = [
        'username' => substr(trim((string) ($_POST['username'] ?? '')), 0, 120),
        'remember' => isset($_POST['remember']),
    ];

    if (!verify_csrf($_POST['csrf_token'] ?? null)) {
        $_SESSION['login_error'] = 'Security token expired. Please try again.';
        redirect('login');
    }

    $username = substr(trim((string) ($_POST['username'] ?? '')), 0, 120);
    $attemptKey = substr($username, 0, 80);
    $password = (string) ($_POST['password'] ?? '');

    if ($username === '' || $password === '') {
        $_SESSION['login_error'] = 'Please enter email, mobile or username and password.';
        redirect('login');
    }

    if (login_rate_limited($attemptKey, $ip)) {
        $_SESSION['login_error'] = 'Too many attempts. Please try again after 15 minutes.';
        redirect('login');
    }

    try {
        $conn = db();
        ensure_users_phone_column();
        $stmt = $conn->prepare("
            SELECT u.id, u.full_name, u.username, u.email, u.phone, u.password_hash, u.status, r.slug AS role
            FROM users u
            INNER JOIN roles r ON r.id = u.role_id
            LEFT JOIN instructor_profiles ip ON ip.user_id = u.id
            WHERE u.username = ? OR u.email = ? OR u.phone = ? OR ip.phone = ?
            LIMIT 1
        ");
        $stmt->bind_param('ssss', $username, $username, $username, $username);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();
    } catch (Throwable $e) {
        $_SESSION['login_error'] = 'Database is not ready. Please import database/edu.sql first.';
        redirect('login');
    }

    if (!$user || $user['status'] !== 'active' || !password_verify($password, $user['password_hash'])) {
        usleep(300000);
        record_login_attempt($attemptKey, $ip, false);
        $_SESSION['login_error'] = 'Invalid login details.';
        redirect('login');
    }

    record_login_attempt($attemptKey, $ip, true);
    clear_failed_login_attempts($attemptKey, $ip);
    session_regenerate_id(true);
    $_SESSION['user'] = [
        'id' => (int) $user['id'],
        'full_name' => $user['full_name'],
        'username' => $user['username'],
        'role' => $user['role'],
    ];

    unset($_SESSION['old_login']);
    redirect(dashboard_path_for_role((string) $user['role']));
}

$error = $_SESSION['login_error'] ?? '';
$old = $_SESSION['old_login'] ?? ['username' => '', 'remember' => false];
unset($_SESSION['login_error']);
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= h(app_name()); ?> Login</title>
    <?php if (asset_or_default('favicon_path') !== ''): ?>
        <link rel="icon" href="<?= h(asset_or_default('favicon_path')); ?>">
    <?php endif; ?>
    <link rel="stylesheet" href="<?= app_url('assets/css/login.css'); ?>">
</head>
<body class="auth-page">
    <?php if ($error): ?>
        <div class="toast error-toast" role="alert">
            <span class="toast-mark">!</span>
            <div>
                <strong>Login failed</strong>
                <p><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></p>
            </div>
        </div>
    <?php endif; ?>

    <main class="auth-shell">
        <section class="auth-card" aria-label="Login form">
            <a class="back-link" href="<?= app_url('index'); ?>">Back</a>
            <div class="auth-brand">
                <span class="logo-mark">
                    <?php if (asset_or_default('app_logo_path') !== ''): ?>
                        <img src="<?= h(asset_or_default('app_logo_path')); ?>" alt="<?= h(app_name()); ?>">
                    <?php elseif (asset_or_default('logo_path') !== ''): ?>
                        <img src="<?= h(asset_or_default('logo_path')); ?>" alt="<?= h(app_name()); ?>">
                    <?php else: ?>
                        EL
                    <?php endif; ?>
                </span>
                <p>Welcome back</p>
                <h1><?= h(app_name()); ?></h1>
            </div>

            <form action="<?= app_url('login'); ?>" method="post" autocomplete="off" autocapitalize="off" spellcheck="false">
                <input type="text" name="fake_user" tabindex="-1" aria-hidden="true" class="fake-input" autocomplete="username">
                <input type="password" name="fake_pass" tabindex="-1" aria-hidden="true" class="fake-input" autocomplete="current-password">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8'); ?>">

                <label class="field">
                    <span class="icon" aria-hidden="true">
                        <svg viewBox="0 0 24 24" focusable="false">
                            <path d="M20 21a8 8 0 0 0-16 0"></path>
                            <circle cx="12" cy="7" r="4"></circle>
                        </svg>
                    </span>
                    <input
                        type="text"
                        name="username"
                        placeholder="Email, mobile or username"
                        value="<?= htmlspecialchars((string) $old['username'], ENT_QUOTES, 'UTF-8'); ?>"
                        autocomplete="off"
                        inputmode="email"
                        maxlength="120"
                        readonly
                        data-unlock
                        required
                    >
                </label>

                <label class="field">
                    <span class="icon" aria-hidden="true">
                        <svg viewBox="0 0 24 24" focusable="false">
                            <rect x="4" y="10" width="16" height="10" rx="2"></rect>
                            <path d="M8 10V7a4 4 0 0 1 8 0v3"></path>
                            <path d="M12 14v2"></path>
                        </svg>
                    </span>
                    <input
                        type="password"
                        name="password"
                        placeholder="Password"
                        autocomplete="new-password"
                        maxlength="128"
                        readonly
                        data-unlock
                        required
                    >
                </label>

                <div class="form-row">
                    <label class="remember">
                        <input type="checkbox" name="remember" value="1" <?= !empty($old['remember']) ? 'checked' : ''; ?>>
                        <span>Remember</span>
                    </label>
                    <a href="#">Forgot?</a>
                </div>

                <button type="submit">Login</button>
            </form>
        </section>
    </main>
    <script>
        document.querySelectorAll('[data-unlock]').forEach((input) => {
            const unlock = () => input.removeAttribute('readonly');
            input.addEventListener('focus', unlock, { once: true });
            input.addEventListener('touchstart', unlock, { once: true });
        });
    </script>
</body>
</html>
