<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/includes/settings_helpers.php';

$user = require_login('superadmin');
$pageTitle = 'Email & Notification';
$pageSubtitle = 'SMTP sender, test email and Firebase notification configuration.';
$activePage = 'settings';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['csrf_token'] ?? null)) {
        $_SESSION['settings_error'] = 'Security token expired.';
        redirect('sadmin/settings-mail');
    }

    if (($_POST['form_type'] ?? '') === 'test_email') {
        $to = trim((string) ($_POST['test_email_to'] ?? ''));
        $from = app_setting('mail_from_email') ?: app_setting('site_email');
        $fromName = app_setting('mail_from_name', app_name());

        if ($to === '' || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
            $_SESSION['settings_error'] = 'Test email failed: valid recipient email required.';
        } elseif ($from === '' || !filter_var($from, FILTER_VALIDATE_EMAIL)) {
            $_SESSION['settings_error'] = 'Test email failed: sender email is not configured.';
        } else {
            $subject = app_name() . ' test email';
            $body = "This is a test email from " . app_name() . ".\n\nIf you received this, email delivery is working.";
            $headers = "From: " . $fromName . " <" . $from . ">\r\nReply-To: " . $from . "\r\nContent-Type: text/plain; charset=UTF-8";
            $sent = mail($to, $subject, $body, $headers);
            $_SESSION[$sent ? 'settings_message' : 'settings_error'] = $sent
                ? 'Test email sent successfully to ' . $to . '.'
                : 'Test email failed. Please check server mail/SMTP configuration.';
        }

        redirect('sadmin/settings-mail');
    }

    save_setting('firebase_enabled', isset($_POST['firebase_enabled']) ? '1' : '0');
    save_settings_keys([
        'mail_driver',
        'mail_host',
        'mail_port',
        'mail_username',
        'mail_password',
        'mail_encryption',
        'mail_from_email',
        'mail_from_name',
        'firebase_project_id',
        'firebase_api_key',
        'firebase_auth_domain',
        'firebase_storage_bucket',
        'firebase_messaging_sender_id',
        'firebase_app_id',
        'firebase_vapid_key',
        'firebase_server_key',
    ]);

    $_SESSION['settings_message'] = 'Email & notification settings updated.';
    redirect('sadmin/settings-mail');
}

$settings = all_settings();
[$message, $error] = settings_flash();
?>
<?php include __DIR__ . '/includes/header.php'; ?>
<?php include __DIR__ . '/includes/sidebar.php'; ?>
<main class="main">
    <?php include __DIR__ . '/includes/topbar.php'; ?>

    <section class="content settings-page">
        <?php if ($message): ?><div class="toast-notice success"><?= h($message); ?></div><?php endif; ?>
        <?php if ($error): ?><div class="toast-notice danger"><?= h($error); ?></div><?php endif; ?>

        <section class="settings-detail-card">
            <div class="detail-head">
                <div>
                    <span>Email & Notification</span>
                    <h2><?= h(strtoupper((string) $settings['mail_driver'])); ?> mail setup</h2>
                    <p>SMTP sender, Firebase notification and test email status.</p>
                </div>
                <a class="modal-button" href="#edit-mail">Edit Settings</a>
            </div>

            <table class="detail-table">
                <tbody>
                    <tr>
                        <th>Mail Driver</th>
                        <td><?= h(strtoupper((string) $settings['mail_driver'])); ?></td>
                        <th>Host</th>
                        <td><?= h($settings['mail_host'] ?: 'Not set'); ?></td>
                    </tr>
                    <tr>
                        <th>Port</th>
                        <td><?= h($settings['mail_port'] ?: 'Not set'); ?></td>
                        <th>Encryption</th>
                        <td><?= h($settings['mail_encryption'] ?: 'Not set'); ?></td>
                    </tr>
                    <tr>
                        <th>From</th>
                        <td colspan="3"><?= h($settings['mail_from_name'] ?: app_name()); ?> &lt;<?= h($settings['mail_from_email'] ?: 'Not set'); ?>&gt;</td>
                    </tr>
                    <tr>
                        <th>Firebase</th>
                        <td><?= $settings['firebase_enabled'] === '1' ? 'Enabled' : 'Disabled'; ?></td>
                        <th>Project ID</th>
                        <td><?= h($settings['firebase_project_id'] ?: 'Not set'); ?></td>
                    </tr>
                    <tr>
                        <th>Sender ID</th>
                        <td><?= h($settings['firebase_messaging_sender_id'] ?: 'Not set'); ?></td>
                        <th>App ID</th>
                        <td><?= h($settings['firebase_app_id'] ?: 'Not set'); ?></td>
                    </tr>
                </tbody>
            </table>

            <form class="test-email-bar" method="post">
                <input type="hidden" name="csrf_token" value="<?= h(csrf_token()); ?>">
                <input type="hidden" name="form_type" value="test_email">
                <label>
                    Send Test Email
                    <input type="email" name="test_email_to" placeholder="email@example.com" required>
                </label>
                <button type="submit">Send Test Email</button>
            </form>
        </section>
    </section>

    <div id="edit-mail" class="modal-overlay">
        <form class="modal-box wide-modal" method="post">
            <input type="hidden" name="csrf_token" value="<?= h(csrf_token()); ?>">
            <div class="modal-head">
                <h2>Edit Email & Notification Setup</h2>
                <a class="modal-close" href="#" aria-label="Close">&times;</a>
            </div>

            <div class="form-grid">
                <label>Driver
                    <select name="mail_driver">
                        <option value="smtp" <?= $settings['mail_driver'] === 'smtp' ? 'selected' : ''; ?>>SMTP</option>
                        <option value="sendmail" <?= $settings['mail_driver'] === 'sendmail' ? 'selected' : ''; ?>>Sendmail</option>
                    </select>
                </label>
                <label>Host<input name="mail_host" value="<?= setting_value($settings, 'mail_host'); ?>"></label>
                <label>Port<input name="mail_port" value="<?= setting_value($settings, 'mail_port'); ?>"></label>
                <label>Encryption
                    <select name="mail_encryption">
                        <option value="tls" <?= $settings['mail_encryption'] === 'tls' ? 'selected' : ''; ?>>TLS</option>
                        <option value="ssl" <?= $settings['mail_encryption'] === 'ssl' ? 'selected' : ''; ?>>SSL</option>
                        <option value="none" <?= $settings['mail_encryption'] === 'none' ? 'selected' : ''; ?>>None</option>
                    </select>
                </label>
                <label>Username<input name="mail_username" value="<?= setting_value($settings, 'mail_username'); ?>"></label>
                <label>Password<input type="password" name="mail_password" value="<?= setting_value($settings, 'mail_password'); ?>" autocomplete="new-password"></label>
                <label>From Email<input type="email" name="mail_from_email" value="<?= setting_value($settings, 'mail_from_email'); ?>"></label>
                <label>From Name<input name="mail_from_name" value="<?= setting_value($settings, 'mail_from_name'); ?>"></label>
                <label class="switch-field">Firebase Enabled<span><input type="checkbox" name="firebase_enabled" value="1" <?= $settings['firebase_enabled'] === '1' ? 'checked' : ''; ?> data-toggle-firebase><b></b></span></label>

                <div class="firebase-fields">
                    <label>Firebase Project ID<input name="firebase_project_id" value="<?= setting_value($settings, 'firebase_project_id'); ?>"></label>
                    <label>Firebase API Key<input name="firebase_api_key" value="<?= setting_value($settings, 'firebase_api_key'); ?>"></label>
                    <label>Auth Domain<input name="firebase_auth_domain" value="<?= setting_value($settings, 'firebase_auth_domain'); ?>"></label>
                    <label>Storage Bucket<input name="firebase_storage_bucket" value="<?= setting_value($settings, 'firebase_storage_bucket'); ?>"></label>
                    <label>Messaging Sender ID<input name="firebase_messaging_sender_id" value="<?= setting_value($settings, 'firebase_messaging_sender_id'); ?>"></label>
                    <label>Firebase App ID<input name="firebase_app_id" value="<?= setting_value($settings, 'firebase_app_id'); ?>"></label>
                    <label>VAPID Key<input name="firebase_vapid_key" value="<?= setting_value($settings, 'firebase_vapid_key'); ?>"></label>
                    <label>Server Key<input name="firebase_server_key" value="<?= setting_value($settings, 'firebase_server_key'); ?>"></label>
                </div>
            </div>

            <div class="modal-actions"><button type="submit">Save Settings</button></div>
        </form>
    </div>

    <script>
        const fb = document.querySelector('[data-toggle-firebase]');
        const box = document.querySelector('.firebase-fields');
        const sync = () => box && box.classList.toggle('show', !!fb?.checked);
        fb?.addEventListener('change', sync);
        sync();
    </script>
<?php include __DIR__ . '/includes/footer.php'; ?>
