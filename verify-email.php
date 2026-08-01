<?php
require_once __DIR__ . '/config.php';

$token = (string) ($_GET['token'] ?? '');
$message = 'Invalid verification link.';
$ok = false;

if ($token !== '') {
    $hash = hash('sha256', $token);
    try {
        $stmt = db()->prepare("SELECT ev.id, ev.user_id, ev.expires_at, ev.verified_at, r.slug AS role_slug FROM email_verifications ev INNER JOIN users u ON u.id = ev.user_id INNER JOIN roles r ON r.id = u.role_id WHERE ev.token_hash = ? LIMIT 1");
        $stmt->bind_param('s', $hash);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();

        if ($row && !$row['verified_at'] && strtotime($row['expires_at']) >= time()) {
            db()->begin_transaction();
            $now = date('Y-m-d H:i:s');
            $stmt = db()->prepare('UPDATE email_verifications SET verified_at = ? WHERE id = ?');
            $stmt->bind_param('si', $now, $row['id']);
            $stmt->execute();

            $stmt = db()->prepare("UPDATE users SET status = 'active' WHERE id = ?");
            $stmt->bind_param('i', $row['user_id']);
            $stmt->execute();

            if ($row['role_slug'] === 'instructor') {
                $stmt = db()->prepare("UPDATE instructor_profiles SET approval_status = 'approved' WHERE user_id = ?");
                $stmt->bind_param('i', $row['user_id']);
                $stmt->execute();
            }
            db()->commit();
            $ok = true;
            $message = 'Email verified successfully. Your account is now active.';
        } elseif ($row && $row['verified_at']) {
            $ok = true;
            $message = 'Email already verified. You can login now.';
        } elseif ($row) {
            $message = 'Verification link expired. Please contact support.';
        }
    } catch (Throwable $e) {
        if (db()->errno === 0) {
            // no-op
        }
        $message = 'Verification failed. Please try again later.';
    }
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Email Verification - <?= h(app_name()); ?></title>
    <link rel="stylesheet" href="<?= app_url('assets/css/login.css'); ?>">
</head>
<body class="legal-page">
    <main class="legal-shell">
        <article class="legal-card">
            <span>Email Verification</span>
            <h1><?= $ok ? 'Verified' : 'Verification Issue'; ?></h1>
            <p><?= h($message); ?></p>
            <p><a class="primary-link" href="<?= h(app_url('login')); ?>">Go to Login</a></p>
        </article>
    </main>
</body>
</html>
