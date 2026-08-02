<?php
require_once __DIR__ . '/config.php';
start_secure_session();

function password_reset_lookup(string $token): ?array
{
    if ($token === '' || strlen($token) < 32) {
        return null;
    }
    $tokenHash = hash('sha256', $token);
    $stmt = db()->prepare("
        SELECT pr.id, pr.user_id, u.full_name
        FROM password_reset_tokens pr
        INNER JOIN users u ON u.id = pr.user_id
        WHERE pr.token_hash = ?
          AND pr.used_at IS NULL
          AND pr.expires_at >= NOW()
        LIMIT 1
    ");
    $stmt->bind_param('s', $tokenHash);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    return $row ?: null;
}

$token = trim((string) ($_GET['token'] ?? $_POST['token'] ?? ''));
$error = '';
$success = '';
$reset = null;

try {
    $reset = password_reset_lookup($token);
} catch (Throwable $e) {
    $error = 'Reset link could not be verified. Please request a new link.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$error) {
    if (!verify_csrf($_POST['csrf_token'] ?? null)) {
        $error = 'Security token expired. Please try again.';
    } elseif (!$reset) {
        $error = 'This reset link is invalid or expired.';
    } else {
        $password = (string) ($_POST['password'] ?? '');
        $confirm = (string) ($_POST['confirm_password'] ?? '');
        if (strlen($password) < 8) {
            $error = 'Password must be at least 8 characters.';
        } elseif ($password !== $confirm) {
            $error = 'Password confirmation does not match.';
        } else {
            try {
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $conn = db();
                $conn->begin_transaction();
                $stmt = $conn->prepare('UPDATE users SET password_hash = ? WHERE id = ?');
                $userId = (int) $reset['user_id'];
                $stmt->bind_param('si', $hash, $userId);
                $stmt->execute();

                $stmt = $conn->prepare('UPDATE password_reset_tokens SET used_at = NOW() WHERE id = ?');
                $resetId = (int) $reset['id'];
                $stmt->bind_param('i', $resetId);
                $stmt->execute();
                $conn->commit();

                $success = 'Password updated successfully. You can login with your new password.';
                $reset = null;
            } catch (Throwable $e) {
                if (isset($conn) && $conn instanceof mysqli) {
                    $conn->rollback();
                }
                $error = 'Password could not be updated. Please try again.';
            }
        }
    }
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Reset Password | Gyan Rank</title>
    <?php if (asset_or_default('favicon_path') !== ''): ?>
        <link rel="icon" href="<?= h(asset_or_default('favicon_path')); ?>">
    <?php endif; ?>
    <link rel="stylesheet" href="<?= app_url('assets/css/login.css'); ?>">
    <style>
        .auth-card.reset-card{max-width:560px}.reset-copy{margin:0 0 22px;color:#5f6e83;line-height:1.55}.reset-actions{display:flex;gap:10px;flex-wrap:wrap;margin-top:14px}.reset-actions a{flex:1;text-align:center;padding:13px 16px;border-radius:14px;border:1px solid #cfd9e8;font-weight:800}.reset-message{margin:0 0 16px;padding:12px 14px;border-radius:14px;background:#ecfff5;color:#087247;border:1px solid #bff3d4}.reset-error{margin:0 0 16px;padding:12px 14px;border-radius:14px;background:#fff2f2;color:#b42318;border:1px solid #ffd0d0}
    </style>
</head>
<body class="auth-page">
    <main class="auth-shell">
        <section class="auth-card reset-card" aria-label="Reset password form">
            <a class="back-link" href="<?= app_url('#/login'); ?>">Back to Login</a>
            <div class="auth-brand">
                <span class="logo-mark">
                    <?php if (asset_or_default('app_logo_path') !== ''): ?>
                        <img src="<?= h(asset_or_default('app_logo_path')); ?>" alt="<?= h(app_name()); ?>">
                    <?php elseif (asset_or_default('logo_path') !== ''): ?>
                        <img src="<?= h(asset_or_default('logo_path')); ?>" alt="<?= h(app_name()); ?>">
                    <?php else: ?>
                        GR
                    <?php endif; ?>
                </span>
                <p>Secure recovery</p>
                <h1>Reset password</h1>
            </div>
            <?php if ($success): ?><p class="reset-message"><?= h($success); ?></p><?php endif; ?>
            <?php if ($error): ?><p class="reset-error"><?= h($error); ?></p><?php endif; ?>
            <?php if ($reset): ?>
                <p class="reset-copy">Create a new password for <?= h((string) ($reset['full_name'] ?? 'your account')); ?>.</p>
                <form action="<?= app_url('reset-password'); ?>" method="post" autocomplete="off">
                    <input type="hidden" name="csrf_token" value="<?= h(csrf_token()); ?>">
                    <input type="hidden" name="token" value="<?= h($token); ?>">
                    <label class="field">
                        <span class="icon" aria-hidden="true"><svg viewBox="0 0 24 24" focusable="false"><rect x="4" y="10" width="16" height="10" rx="2"></rect><path d="M8 10V7a4 4 0 0 1 8 0v3"></path><path d="M12 14v2"></path></svg></span>
                        <input type="password" name="password" placeholder="New password" minlength="8" maxlength="128" required>
                    </label>
                    <label class="field">
                        <span class="icon" aria-hidden="true"><svg viewBox="0 0 24 24" focusable="false"><rect x="4" y="10" width="16" height="10" rx="2"></rect><path d="M8 10V7a4 4 0 0 1 8 0v3"></path><path d="M12 14v2"></path></svg></span>
                        <input type="password" name="confirm_password" placeholder="Confirm password" minlength="8" maxlength="128" required>
                    </label>
                    <button type="submit">Update Password</button>
                </form>
            <?php else: ?>
                <p class="reset-copy">If your reset link expired, request a fresh secure link.</p>
                <div class="reset-actions">
                    <a href="<?= app_url('forgot-password'); ?>">Request New Link</a>
                    <a href="<?= app_url('#/login'); ?>">Login</a>
                </div>
            <?php endif; ?>
        </section>
    </main>
</body>
</html>
