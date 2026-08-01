<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/includes/settings_helpers.php';
$user = require_login('superadmin');
$pageTitle = 'General Settings';
$pageSubtitle = 'Institute profile and contact details used everywhere.';
$activePage = 'settings';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['csrf_token'] ?? null)) {
        $_SESSION['settings_error'] = 'Security token expired.';
        redirect('sadmin/settings-general');
    }
    save_setting('google_login_enabled', isset($_POST['google_login_enabled']) ? '1' : '0');
    save_settings_keys([
        'site_name',
        'site_tagline',
        'site_email',
        'support_call_number',
        'support_whatsapp_number',
        'support_email',
        'google_client_id',
        'google_client_secret',
        'google_redirect_uri',
        'site_address',
        'copyright_text',
    ]);
    $_SESSION['settings_message'] = 'General settings updated.';
    redirect('sadmin/settings-general');
}

$settings = all_settings();
[$message, $error] = settings_flash();
?>
<?php include __DIR__ . '/includes/header.php'; ?>
<?php include __DIR__ . '/includes/sidebar.php'; ?>
<main class="main">
    <?php include __DIR__ . '/includes/topbar.php'; ?>
    <section class="content settings-page">
        <?php render_settings_submenu('general'); ?>
        <?php if ($message): ?><div class="notice"><?= h($message); ?></div><?php endif; ?>
        <?php if ($error): ?><div class="notice danger"><?= h($error); ?></div><?php endif; ?>
        <section class="settings-detail-card">
            <div class="detail-head"><div><span>General</span><h2><?= h($settings['site_name']); ?></h2><p><?= h($settings['site_tagline']); ?></p></div><a class="modal-button" href="#edit-general">Edit Settings</a></div>
            <table class="detail-table">
                <tbody>
                    <tr><th>Site Name</th><td><?= h($settings['site_name'] ?: 'Not set'); ?></td><th>Tagline</th><td><?= h($settings['site_tagline'] ?: 'Not set'); ?></td></tr>
                    <tr><th>Email</th><td><?= h($settings['site_email'] ?: 'Not set'); ?></td><th>Support Email</th><td><?= h($settings['support_email'] ?: 'Not set'); ?></td></tr>
                    <tr><th>Support Call</th><td><?= h($settings['support_call_number'] ?: 'Not set'); ?></td><th>WhatsApp</th><td><?= h($settings['support_whatsapp_number'] ?: 'Not set'); ?></td></tr>
                    <tr><th>Google Login</th><td><?= $settings['google_login_enabled'] === '1' ? 'Enabled' : 'Disabled'; ?></td><th>Google Client ID</th><td><?= h($settings['google_client_id'] ?: 'Not set'); ?></td></tr>
                    <tr><th>Redirect URI</th><td colspan="3"><?= h($settings['google_redirect_uri'] ?: 'Not set'); ?></td></tr>
                    <tr><th>Address</th><td colspan="3"><?= h($settings['site_address'] ?: 'Not set'); ?></td></tr>
                    <tr><th>Copyright</th><td colspan="3"><?= h($settings['copyright_text'] ?: 'Not set'); ?></td></tr>
                </tbody>
            </table>
        </section>
    </section>
    <div id="edit-general" class="modal-overlay"><form class="modal-box wide-modal" method="post"><input type="hidden" name="csrf_token" value="<?= h(csrf_token()); ?>"><div class="modal-head"><h2>Edit General Settings</h2><a class="modal-close" href="#" aria-label="Close">×</a></div><div class="form-grid"><label>Site Name<input name="site_name" value="<?= setting_value($settings, 'site_name'); ?>" required></label><label>Tagline<input name="site_tagline" value="<?= setting_value($settings, 'site_tagline'); ?>"></label><label>Email<input type="email" name="site_email" value="<?= setting_value($settings, 'site_email'); ?>"></label><label>Support Call Number<input type="tel" name="support_call_number" value="<?= setting_value($settings, 'support_call_number'); ?>"></label><label>WhatsApp Number<input type="tel" name="support_whatsapp_number" value="<?= setting_value($settings, 'support_whatsapp_number'); ?>"></label><label>Support Email<input type="email" name="support_email" value="<?= setting_value($settings, 'support_email'); ?>"></label><label class="switch-field">Google Login<span><input type="checkbox" name="google_login_enabled" value="1" <?= $settings['google_login_enabled'] === '1' ? 'checked' : ''; ?>><b></b></span></label><label>Google Client ID<input name="google_client_id" value="<?= setting_value($settings, 'google_client_id'); ?>"></label><label>Google Client Secret<input type="password" name="google_client_secret" value="<?= setting_value($settings, 'google_client_secret'); ?>"></label><label class="span-2">Google Redirect URI<input name="google_redirect_uri" value="<?= setting_value($settings, 'google_redirect_uri'); ?>"></label><label class="span-2">Address<textarea name="site_address" rows="1"><?= setting_value($settings, 'site_address'); ?></textarea></label><label class="span-2">Copyright Text<textarea name="copyright_text" rows="1"><?= setting_value($settings, 'copyright_text'); ?></textarea></label></div><div class="modal-actions"><button type="submit">Save Changes</button></div></form></div>
<?php include __DIR__ . '/includes/footer.php'; ?>
