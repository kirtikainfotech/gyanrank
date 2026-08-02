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
</head>
<body class="auth-page">
    <main class="recovery-shell">
        <section class="recovery-card" aria-label="Reset password form">
            <aside class="recovery-visual">
                <div>
                    <span class="recovery-logo">
                    <?php if (asset_or_default('app_logo_path') !== ''): ?>
                        <img src="<?= h(asset_or_default('app_logo_path')); ?>" alt="<?= h(app_name()); ?>">
                    <?php elseif (asset_or_default('logo_path') !== ''): ?>
                        <img src="<?= h(asset_or_default('logo_path')); ?>" alt="<?= h(app_name()); ?>">
                    <?php else: ?>
                        GR
                    <?php endif; ?>
                    </span>
                    <p class="recovery-kicker">Protected Reset</p>
                    <h1>Create a fresh password for your Gyan Rank account.</h1>
                    <p>Use a strong password to protect course access, billing history, certificates and progress reports.</p>
                    <div class="recovery-points">
                        <span>Reset link verified securely</span>
                        <span>Password encrypted before saving</span>
                        <span>Old reset link expires after use</span>
                    </div>
                </div>
                <div class="recovery-support-note">For account ownership issues, request a fresh reset link or contact Gyan Rank support.</div>
            </aside>
            <div class="recovery-panel">
                <a class="back-link" href="<?= app_url('#/login'); ?>">Back to Login</a>
                <p class="recovery-eyebrow">Secure Recovery</p>
                <h2>Reset password</h2>
                <?php if ($success): ?><p class="reset-message"><?= h($success); ?></p><?php endif; ?>
                <?php if ($error): ?><p class="reset-error"><?= h($error); ?></p><?php endif; ?>
                <?php if ($reset): ?>
                    <p class="recovery-copy">Create a new password for <?= h((string) ($reset['full_name'] ?? 'your account')); ?>.</p>
                    <form class="recovery-form" action="<?= app_url('reset-password'); ?>" method="post" autocomplete="off">
                        <input type="hidden" name="csrf_token" value="<?= h(csrf_token()); ?>">
                        <input type="hidden" name="token" value="<?= h($token); ?>">
                        <label>
                            New password
                            <input class="recovery-input" type="password" name="password" placeholder="Minimum 8 characters" minlength="8" maxlength="128" required>
                        </label>
                        <label>
                            Confirm password
                            <input class="recovery-input" type="password" name="confirm_password" placeholder="Repeat new password" minlength="8" maxlength="128" required>
                        </label>
                        <button class="recovery-submit" type="submit">Update Password</button>
                    </form>
                <?php else: ?>
                    <p class="recovery-copy">If your reset link expired, request a fresh secure link.</p>
                    <div class="recovery-actions">
                        <a href="<?= app_url('forgot-password'); ?>">Request New Link</a>
                        <a href="<?= app_url('#/login'); ?>">Login</a>
                    </div>
                <?php endif; ?>
            </div>
        </section>
    </main>
</body>
</html>
