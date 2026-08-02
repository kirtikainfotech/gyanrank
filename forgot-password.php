<?php
require_once __DIR__ . '/config.php';
start_secure_session();

function ensure_password_reset_tokens_table(): void
{
    db()->query("
        CREATE TABLE IF NOT EXISTS password_reset_tokens (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            token_hash CHAR(64) NOT NULL,
            expires_at DATETIME NOT NULL,
            used_at DATETIME NULL,
            ip_address VARCHAR(45) NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_token_hash (token_hash),
            INDEX idx_user_expires (user_id, expires_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
}

function find_password_reset_user(string $identity): ?array
{
    ensure_users_phone_column();
    $conn = db();
    $stmt = $conn->prepare("
        SELECT u.id, u.full_name, u.username, u.email, u.phone
        FROM users u
        LEFT JOIN instructor_profiles ip ON ip.user_id = u.id
        WHERE u.username = ? OR u.email = ? OR u.phone = ? OR ip.phone = ?
        LIMIT 1
    ");
    $stmt->bind_param('ssss', $identity, $identity, $identity, $identity);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    return $user ?: null;
}

function send_password_reset_email(array $user, string $resetUrl): bool
{
    $email = trim((string) ($user['email'] ?? ''));
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return false;
    }

    $supportEmail = app_setting('support_email', 'support@gyanrank.in') ?: 'support@gyanrank.in';
    $subject = 'Reset your Gyan Rank password';
    $message = "Hello " . ($user['full_name'] ?: 'Learner') . ",\n\n"
        . "Use this secure link to reset your Gyan Rank password:\n"
        . $resetUrl . "\n\n"
        . "This link will expire in 30 minutes. If you did not request this, please ignore this email.\n\n"
        . "Gyan Rank Support";
    $headers = "From: Gyan Rank <{$supportEmail}>\r\n"
        . "Reply-To: {$supportEmail}\r\n"
        . "Content-Type: text/plain; charset=UTF-8\r\n";

    return @mail($email, $subject, $message, $headers);
}

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['csrf_token'] ?? null)) {
        $error = 'Security token expired. Please try again.';
    } else {
        $identity = substr(trim((string) ($_POST['identity'] ?? '')), 0, 120);
        if ($identity === '') {
            $error = 'Enter your email, mobile number or username.';
        } else {
            try {
                ensure_password_reset_tokens_table();
                $user = find_password_reset_user($identity);
                if ($user) {
                    $token = bin2hex(random_bytes(32));
                    $tokenHash = hash('sha256', $token);
                    $expiresAt = date('Y-m-d H:i:s', time() + 1800);
                    $ip = client_ip();

                    $stmt = db()->prepare("INSERT INTO password_reset_tokens (user_id, token_hash, expires_at, ip_address) VALUES (?, ?, ?, ?)");
                    $userId = (int) $user['id'];
                    $stmt->bind_param('isss', $userId, $tokenHash, $expiresAt, $ip);
                    $stmt->execute();

                    $resetUrl = app_url('reset-password?token=' . rawurlencode($token));
                    send_password_reset_email($user, $resetUrl);
                }

                $message = 'If the account exists, password reset instructions have been sent. You can also contact support on WhatsApp for account verification.';
            } catch (Throwable $e) {
                $error = 'Password reset service is not ready. Please contact support.';
            }
        }
    }
}

$whatsapp = 'https://wa.me/918299442665?text=' . rawurlencode('Hello Gyan Rank support, I need help resetting my password.');
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Forgot Password | Gyan Rank</title>
    <?php if (asset_or_default('favicon_path') !== ''): ?>
        <link rel="icon" href="<?= h(asset_or_default('favicon_path')); ?>">
    <?php endif; ?>
    <link rel="stylesheet" href="<?= app_url('assets/css/login.css'); ?>">
    <style>
        .auth-card.reset-card{max-width:560px}.reset-copy{margin:0 0 22px;color:#5f6e83;line-height:1.55}.reset-actions{display:flex;gap:10px;flex-wrap:wrap;margin-top:14px}.reset-actions a{flex:1;text-align:center;padding:13px 16px;border-radius:14px;border:1px solid #cfd9e8;font-weight:800}.reset-actions .wa{background:#25d366;color:#fff;border-color:#25d366}.reset-message{margin:0 0 16px;padding:12px 14px;border-radius:14px;background:#ecfff5;color:#087247;border:1px solid #bff3d4}.reset-error{margin:0 0 16px;padding:12px 14px;border-radius:14px;background:#fff2f2;color:#b42318;border:1px solid #ffd0d0}
    </style>
</head>
<body class="auth-page">
    <main class="auth-shell">
        <section class="auth-card reset-card" aria-label="Forgot password form">
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
                <p>Account recovery</p>
                <h1>Forgot password?</h1>
            </div>
            <p class="reset-copy">Enter the email, mobile number or username linked with your Gyan Rank account. We will send a secure reset link if the account is available.</p>
            <?php if ($message): ?><p class="reset-message"><?= h($message); ?></p><?php endif; ?>
            <?php if ($error): ?><p class="reset-error"><?= h($error); ?></p><?php endif; ?>
            <form action="<?= app_url('forgot-password'); ?>" method="post" autocomplete="off">
                <input type="hidden" name="csrf_token" value="<?= h(csrf_token()); ?>">
                <label class="field">
                    <span class="icon" aria-hidden="true">
                        <svg viewBox="0 0 24 24" focusable="false"><path d="M20 21a8 8 0 0 0-16 0"></path><circle cx="12" cy="7" r="4"></circle></svg>
                    </span>
                    <input type="text" name="identity" placeholder="Email, mobile or username" maxlength="120" required>
                </label>
                <button type="submit">Send Reset Link</button>
            </form>
            <div class="reset-actions">
                <a href="<?= app_url('#/login'); ?>">Login</a>
                <a class="wa" href="<?= h($whatsapp); ?>" target="_blank" rel="noopener">WhatsApp Support</a>
            </div>
        </section>
    </main>
</body>
</html>
