<?php
require_once __DIR__ . '/config.php';
$user = require_login();

if (($user['role'] ?? '') === 'superadmin') {
    redirect('sadmin/dashboard');
}

if (($user['role'] ?? '') === 'instructor') {
    redirect('ins/dashboard');
}

$roleLabel = ucwords(str_replace('-', ' ', (string) ($user['role'] ?? 'user')));
$adminUser = $_SESSION['admin_user'] ?? null;
$isImpersonating = is_array($adminUser) && ($adminUser['role'] ?? '') === 'superadmin';
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= h(app_name()); ?> Dashboard</title>
    <?php if (asset_or_default('favicon_path') !== ''): ?>
        <link rel="icon" href="<?= h(asset_or_default('favicon_path')); ?>">
    <?php endif; ?>
    <link rel="stylesheet" href="<?= app_url('assets/css/login.css'); ?>">
</head>
<body class="auth-page role-dashboard-page">
    <main class="role-dashboard">
        <section class="role-card">
            <div class="role-top">
                <span class="logo-mark">
                    <?php if (asset_or_default('app_logo_path') !== ''): ?>
                        <img src="<?= h(asset_or_default('app_logo_path')); ?>" alt="<?= h(app_name()); ?>">
                    <?php elseif (asset_or_default('logo_path') !== ''): ?>
                        <img src="<?= h(asset_or_default('logo_path')); ?>" alt="<?= h(app_name()); ?>">
                    <?php else: ?>
                        EL
                    <?php endif; ?>
                </span>
                <div>
                    <p><?= h($roleLabel); ?> Dashboard</p>
                    <h1>Welcome, <?= h((string) ($user['full_name'] ?? 'User')); ?></h1>
                </div>
            </div>
            <div class="role-grid">
                <article><span>Account</span><strong><?= h((string) ($user['username'] ?? '')); ?></strong></article>
                <article><span>Role</span><strong><?= h($roleLabel); ?></strong></article>
                <article><span>Status</span><strong>Active</strong></article>
            </div>
            <div class="role-actions">
                <?php if ($isImpersonating): ?>
                    <form method="post" action="<?= h(app_url('sadmin/back-to-admin')); ?>">
                        <input type="hidden" name="csrf_token" value="<?= h(csrf_token()); ?>">
                        <button class="back-admin-btn" type="submit">Back to Admin</button>
                    </form>
                <?php else: ?>
                    <a href="<?= app_url('index'); ?>">Home</a>
                    <a class="danger" href="<?= app_url('logout'); ?>">Logout</a>
                <?php endif; ?>
            </div>
        </section>
    </main>
</body>
</html>
