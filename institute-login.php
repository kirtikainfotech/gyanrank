<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/institute_erp.php';
start_secure_session();
institute_erp_ensure_tables();

if (!empty($_SESSION['institution_user'])) {
    redirect('institute-dashboard');
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $identity = trim((string) ($_POST['identity'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');
    if (!verify_csrf($_POST['csrf_token'] ?? null)) {
        $error = 'Security token expired. Please try again.';
    } elseif ($identity === '' || $password === '') {
        $error = 'Enter registered email/mobile and password.';
    } else {
        $mobile = preg_replace('/\D+/', '', $identity);
        $stmt = db()->prepare("SELECT * FROM institution_accounts WHERE (email = ? OR mobile = ?) AND status = 'active' LIMIT 1");
        $stmt->bind_param('ss', $identity, $mobile);
        $stmt->execute();
        $account = $stmt->get_result()->fetch_assoc();
        if (!$account || !password_verify($password, (string) $account['password_hash'])) {
            $error = 'Invalid institute login details.';
        } else {
            session_regenerate_id(true);
            $_SESSION['institution_user'] = [
                'id' => (int) $account['id'],
                'institution_name' => $account['institution_name'],
                'institution_type' => $account['institution_type'],
                'contact_name' => $account['contact_name'],
                'email' => $account['email'],
                'mobile' => $account['mobile'],
            ];
            redirect('institute-dashboard');
        }
    }
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Institute Login - <?= h(app_name()); ?></title>
    <link rel="icon" href="<?= h(app_url('assets/applogo.png')); ?>">
    <link rel="stylesheet" href="<?= h(app_url('assets/css/login.css')); ?>">
</head>
<body class="legal-page">
    <?php require __DIR__ . '/includes/public_header.php'; ?>
    <main class="legal-shell institute-login-shell">
        <section class="institute-login-card">
            <div>
                <span>Institute Access</span>
                <h1>Login to Institute Panel</h1>
                <p>Use the email/mobile and password shared after admin approval.</p>
            </div>
            <?php if ($error): ?><div class="form-alert form-alert-error"><?= h($error); ?></div><?php endif; ?>
            <form method="post">
                <input type="hidden" name="csrf_token" value="<?= h(csrf_token()); ?>">
                <label>Email or Mobile<input name="identity" autocomplete="username" required></label>
                <label>Password<input type="password" name="password" autocomplete="current-password" required></label>
                <button type="submit">Login</button>
                <a class="institute-register-link" href="<?= h(app_url('register-institution')); ?>">Register Institution</a>
            </form>
        </section>
    </main>
    <?php require __DIR__ . '/includes/public_footer.php'; ?>
</body>
</html>
