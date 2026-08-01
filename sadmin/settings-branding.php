<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/includes/settings_helpers.php';

$user = require_login('superadmin');
$pageTitle = 'Branding Settings';
$pageSubtitle = 'Manage logo, public links and referral commission rules.';
$activePage = 'settings';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['csrf_token'] ?? null)) {
        $_SESSION['settings_error'] = 'Security token expired.';
        redirect('sadmin/settings-branding');
    }

    try {
        save_settings_keys([
            'facebook_url',
            'instagram_url',
            'youtube_url',
            'playstore_url',
            'instructor_referral_commission_type',
            'instructor_referral_commission_value',
            'user_referral_commission_type',
            'user_referral_commission_value',
        ]);

        $logo = store_uploaded_setting_file('logo_file', ['image/png' => 'png', 'image/jpeg' => 'jpg', 'image/webp' => 'webp']);
        $appLogo = store_uploaded_setting_file('app_logo_file', ['image/png' => 'png', 'image/jpeg' => 'jpg', 'image/webp' => 'webp']);
        $appIcon = store_uploaded_setting_file('app_icon_file', ['image/png' => 'png', 'image/jpeg' => 'jpg', 'image/webp' => 'webp']);
        $websiteLogo = store_uploaded_setting_file('website_logo_file', ['image/png' => 'png', 'image/jpeg' => 'jpg', 'image/webp' => 'webp']);
        $favicon = store_uploaded_setting_file('favicon_file', ['image/png' => 'png', 'image/jpeg' => 'jpg', 'image/webp' => 'webp', 'image/x-icon' => 'ico', 'image/vnd.microsoft.icon' => 'ico']);

        if ($logo !== null) save_setting('logo_path', $logo);
        if ($appLogo !== null) save_setting('app_logo_path', $appLogo);
        if ($appIcon !== null) save_setting('app_icon_path', $appIcon);
        if ($websiteLogo !== null) save_setting('website_logo_path', $websiteLogo);
        if ($favicon !== null) save_setting('favicon_path', $favicon);

        $_SESSION['settings_message'] = 'Branding updated.';
    } catch (Throwable $e) {
        $_SESSION['settings_error'] = $e->getMessage();
    }

    redirect('sadmin/settings-branding');
}

$settings = all_settings();
[$message, $error] = settings_flash();
?>
<?php include __DIR__ . '/includes/header.php'; ?>
<?php include __DIR__ . '/includes/sidebar.php'; ?>
<main class="main">
    <?php include __DIR__ . '/includes/topbar.php'; ?>
    <section class="content settings-page">
        <?php render_settings_submenu('branding'); ?>
        <?php if ($message): ?><div class="notice"><?= h($message); ?></div><?php endif; ?>
        <?php if ($error): ?><div class="notice danger"><?= h($error); ?></div><?php endif; ?>

        <section class="settings-detail-card">
            <div class="detail-head">
                <div>
                    <span>Branding</span>
                    <h2>Logo, Social, App & Referral Rules</h2>
                    <p>Upload safe image files, configure public links and referral commission defaults.</p>
                </div>
                <a class="modal-button" href="#edit-branding">Edit Branding</a>
            </div>

            <table class="detail-table">
                <tbody>
                    <tr><th>Sidebar Logo</th><td><?= app_setting('logo_path') !== '' ? 'Uploaded' : 'Not set'; ?></td><th>App Logo</th><td><?= app_setting('app_logo_path') !== '' ? 'Uploaded' : 'Not set'; ?></td></tr>
                    <tr><th>App Icon</th><td><?= app_setting('app_icon_path') !== '' ? 'Uploaded' : 'Not set'; ?></td><th>Website Logo</th><td><?= app_setting('website_logo_path') !== '' ? 'Uploaded' : 'Not set'; ?></td></tr>
                    <tr><th>Favicon</th><td><?= app_setting('favicon_path') !== '' ? 'Uploaded' : 'Not set'; ?></td><th>Play Store</th><td><?= h($settings['playstore_url'] ?: 'Not set'); ?></td></tr>
                    <tr><th>Facebook</th><td><?= h($settings['facebook_url'] ?: 'Not set'); ?></td><th>Instagram</th><td><?= h($settings['instagram_url'] ?: 'Not set'); ?></td></tr>
                    <tr><th>YouTube</th><td><?= h($settings['youtube_url'] ?: 'Not set'); ?></td><th>Instructor Referral</th><td><?= h($settings['instructor_referral_commission_value'] . ' ' . $settings['instructor_referral_commission_type']); ?></td></tr>
                    <tr><th>User Referral</th><td colspan="3"><?= h($settings['user_referral_commission_value'] . ' ' . $settings['user_referral_commission_type']); ?></td></tr>
                </tbody>
            </table>

            <div class="brand-upload compact-preview">
                <?php foreach ([['logo_path', 'Sidebar Logo', 'SL'], ['app_logo_path', 'App Logo', 'AL'], ['app_icon_path', 'App Icon', 'AI'], ['website_logo_path', 'Website Logo', 'WL'], ['favicon_path', 'Favicon', 'F']] as $item): ?>
                    <div class="preview-box">
                        <?php if (asset_or_default($item[0])): ?><img src="<?= h(asset_or_default($item[0])); ?>" alt="<?= h($item[1]); ?>"><?php else: ?><strong><?= h($item[2]); ?></strong><?php endif; ?>
                        <p><?= h($item[1]); ?></p>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>
    </section>

    <div id="edit-branding" class="modal-overlay">
        <form class="modal-box wide-modal" method="post" enctype="multipart/form-data">
            <input type="hidden" name="csrf_token" value="<?= h(csrf_token()); ?>">
            <div class="modal-head"><h2>Edit Branding</h2><a class="modal-close" href="#" aria-label="Close">×</a></div>
            <div class="form-grid">
                <label>Sidebar Logo<input type="file" name="logo_file" accept=".png,.jpg,.jpeg,.webp,image/png,image/jpeg,image/webp"></label>
                <label>App Logo<input type="file" name="app_logo_file" accept=".png,.jpg,.jpeg,.webp,image/png,image/jpeg,image/webp"></label>
                <label>App Icon<input type="file" name="app_icon_file" accept=".png,.jpg,.jpeg,.webp,image/png,image/jpeg,image/webp"></label>
                <label>Website Logo<input type="file" name="website_logo_file" accept=".png,.jpg,.jpeg,.webp,image/png,image/jpeg,image/webp"></label>
                <label>Favicon<input type="file" name="favicon_file" accept=".png,.jpg,.jpeg,.webp,.ico,image/png,image/jpeg,image/webp,image/x-icon"></label>
                <label>Facebook URL<input type="url" name="facebook_url" value="<?= setting_value($settings, 'facebook_url'); ?>"></label>
                <label>Instagram URL<input type="url" name="instagram_url" value="<?= setting_value($settings, 'instagram_url'); ?>"></label>
                <label>YouTube URL<input type="url" name="youtube_url" value="<?= setting_value($settings, 'youtube_url'); ?>"></label>
                <label>Play Store URL<input type="url" name="playstore_url" value="<?= setting_value($settings, 'playstore_url'); ?>"></label>
                <label>Instructor Referral Type<select name="instructor_referral_commission_type"><option value="percent" <?= $settings['instructor_referral_commission_type'] === 'percent' ? 'selected' : ''; ?>>Percent</option><option value="fixed" <?= $settings['instructor_referral_commission_type'] === 'fixed' ? 'selected' : ''; ?>>Fixed</option></select></label>
                <label>Instructor Referral Value<input type="number" step="0.01" min="0" name="instructor_referral_commission_value" value="<?= setting_value($settings, 'instructor_referral_commission_value'); ?>"></label>
                <label>User Referral Type<select name="user_referral_commission_type"><option value="percent" <?= $settings['user_referral_commission_type'] === 'percent' ? 'selected' : ''; ?>>Percent</option><option value="fixed" <?= $settings['user_referral_commission_type'] === 'fixed' ? 'selected' : ''; ?>>Fixed</option></select></label>
                <label>User Referral Value<input type="number" step="0.01" min="0" name="user_referral_commission_value" value="<?= setting_value($settings, 'user_referral_commission_value'); ?>"></label>
            </div>
            <div class="modal-actions"><button type="submit">Save Branding</button></div>
        </form>
    </div>
<?php include __DIR__ . '/includes/footer.php'; ?>
