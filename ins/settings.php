<?php
require_once __DIR__ . '/includes/functions.php';
$user = instructor_user();
ensure_instructor_erp_tables();
$instructorId = (int) $user['id'];
$settings = instructor_setting_row($instructorId);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['csrf_token'] ?? null)) {
        $_SESSION['ins_error'] = 'Security token expired.';
        redirect('ins/settings');
    }

    try {
        $fullName = substr(trim((string) ($_POST['full_name'] ?? $user['full_name'])), 0, 120);
        $emailAddress = substr(trim((string) ($_POST['email'] ?? $user['email'])), 0, 160);
        if ($fullName === '') {
            throw new RuntimeException('Full name required.');
        }
        if ($emailAddress === '' || !filter_var($emailAddress, FILTER_VALIDATE_EMAIL)) {
            throw new RuntimeException('Valid email required.');
        }

        $stmt = db()->prepare('SELECT id FROM users WHERE email = ? AND id <> ? LIMIT 1');
        $stmt->bind_param('si', $emailAddress, $instructorId);
        $stmt->execute();
        if ($stmt->get_result()->fetch_assoc()) {
            throw new RuntimeException('This email is already used by another account.');
        }

        $mode = in_array($_POST['default_class_mode'] ?? '', ['online', 'offline', 'hybrid'], true) ? (string) $_POST['default_class_mode'] : 'online';
        $public = isset($_POST['public_profile']) ? 1 : 0;
        $recording = isset($_POST['auto_recording']) ? 1 : 0;
        $emailNotify = isset($_POST['notification_email']) ? 1 : 0;
        $timezone = substr(trim((string) ($_POST['teaching_timezone'] ?? 'Asia/Kolkata')), 0, 80);
        $contactNumber = substr(preg_replace('/\D+/', '', (string) ($_POST['contact_number'] ?? '')), 0, 10);
        $whatsappNumber = substr(preg_replace('/\D+/', '', (string) ($_POST['whatsapp_number'] ?? '')), 0, 10);
        $headline = substr(trim((string) ($_POST['profile_headline'] ?? '')), 0, 160);
        $bio = substr(trim((string) ($_POST['profile_bio'] ?? '')), 0, 1500);
        $expertise = substr(trim((string) ($_POST['expertise'] ?? '')), 0, 255);
        $qualification = substr(trim((string) ($_POST['qualification'] ?? '')), 0, 255);
        $supportEmail = substr(trim((string) ($_POST['support_email'] ?? '')), 0, 160);
        $telegram = substr(trim((string) ($_POST['telegram_channel'] ?? '')), 0, 255);
        $instagram = substr(trim((string) ($_POST['instagram_url'] ?? '')), 0, 255);
        $youtube = substr(trim((string) ($_POST['youtube_channel'] ?? '')), 0, 255);
        $livePlatform = in_array($_POST['live_platform'] ?? '', ['google_meet', 'youtube_live'], true) ? (string) $_POST['live_platform'] : 'google_meet';
        $googleMeetLink = substr(trim((string) ($_POST['google_meet_link'] ?? '')), 0, 255);
        $youtubeLiveLink = substr(trim((string) ($_POST['youtube_live_link'] ?? '')), 0, 255);
        $kycType = in_array($_POST['kyc_document_type'] ?? '', ['aadhaar', 'pan', 'passport', 'driving_license', 'voter_id'], true) ? (string) $_POST['kyc_document_type'] : '';
        $kycNumber = substr(trim((string) ($_POST['kyc_document_number'] ?? '')), 0, 80);

        if ($supportEmail !== '' && !filter_var($supportEmail, FILTER_VALIDATE_EMAIL)) {
            throw new RuntimeException('Valid support email required.');
        }
        foreach (['telegram' => $telegram, 'instagram' => $instagram, 'youtube' => $youtube, 'google meet' => $googleMeetLink, 'youtube live' => $youtubeLiveLink] as $label => $url) {
            if ($url !== '' && !filter_var($url, FILTER_VALIDATE_URL)) {
                throw new RuntimeException('Valid ' . ucfirst($label) . ' URL required.');
            }
        }

        $imageTypes = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
        $docTypes = [
            'application/pdf' => 'pdf',
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
        ];
        $logoPath = save_instructor_setting_file('profile_logo', (string) $instructorId, $imageTypes, 2) ?: (string) ($settings['profile_logo_path'] ?? '');
        $bannerPath = save_instructor_setting_file('profile_banner', (string) $instructorId, $imageTypes, 4) ?: (string) ($settings['profile_banner_path'] ?? '');
        $kycPath = save_instructor_setting_file('kyc_document', (string) $instructorId . '/kyc', $docTypes, 5) ?: (string) ($settings['kyc_document_path'] ?? '');
        $kycStatus = $kycPath !== '' ? 'pending' : 'not_submitted';
        if ($kycPath !== '' && (string) ($settings['kyc_document_path'] ?? '') === $kycPath && ($settings['kyc_status'] ?? '') !== '') {
            $kycStatus = (string) $settings['kyc_status'];
        }

        if (($kycType !== '' || $kycNumber !== '') && $kycPath === '') {
            throw new RuntimeException('Please upload one valid KYC document.');
        }

        $stmt = db()->prepare('UPDATE users SET full_name = ?, email = ? WHERE id = ?');
        $stmt->bind_param('ssi', $fullName, $emailAddress, $instructorId);
        $stmt->execute();

        $newPassword = (string) ($_POST['new_password'] ?? '');
        $confirmPassword = (string) ($_POST['confirm_password'] ?? '');
        if ($newPassword !== '' || $confirmPassword !== '') {
            if (strlen($newPassword) < 8) {
                throw new RuntimeException('Password must be at least 8 characters.');
            }
            if ($newPassword !== $confirmPassword) {
                throw new RuntimeException('Password confirmation does not match.');
            }
            $hash = password_hash($newPassword, PASSWORD_DEFAULT);
            $stmt = db()->prepare('UPDATE users SET password_hash = ? WHERE id = ?');
            $stmt->bind_param('si', $hash, $instructorId);
            $stmt->execute();
        }

        $stmt = db()->prepare('INSERT IGNORE INTO instructor_settings (instructor_id) VALUES (?)');
        $stmt->bind_param('i', $instructorId);
        $stmt->execute();

        $stmt = db()->prepare("
            UPDATE instructor_settings SET
                default_class_mode = ?,
                contact_number = ?,
                whatsapp_number = ?,
                profile_headline = ?,
                profile_bio = ?,
                expertise = ?,
                qualification = ?,
                profile_logo_path = ?,
                profile_banner_path = ?,
                support_email = ?,
                telegram_channel = ?,
                instagram_url = ?,
                youtube_channel = ?,
                live_platform = ?,
                google_meet_link = ?,
                youtube_live_link = ?,
                kyc_document_type = ?,
                kyc_document_number = ?,
                kyc_document_path = ?,
                kyc_status = ?,
                public_profile = ?,
                auto_recording = ?,
                notification_email = ?,
                teaching_timezone = ?
            WHERE instructor_id = ?
        ");
        $stmt->bind_param(
            'ssssssssssssssssssssiiisi',
            $mode,
            $contactNumber,
            $whatsappNumber,
            $headline,
            $bio,
            $expertise,
            $qualification,
            $logoPath,
            $bannerPath,
            $supportEmail,
            $telegram,
            $instagram,
            $youtube,
            $livePlatform,
            $googleMeetLink,
            $youtubeLiveLink,
            $kycType,
            $kycNumber,
            $kycPath,
            $kycStatus,
            $public,
            $recording,
            $emailNotify,
            $timezone,
            $instructorId
        );
        $stmt->execute();

        $_SESSION['user']['full_name'] = $fullName;
        $_SESSION['user']['email'] = $emailAddress;
        $_SESSION['ins_success'] = 'Profile settings saved.';
    } catch (Throwable $e) {
        $_SESSION['ins_error'] = $e->getMessage();
    }

    redirect('ins/settings');
}

$settings = instructor_setting_row($instructorId);
$message = $_SESSION['ins_success'] ?? '';
$error = $_SESSION['ins_error'] ?? '';
unset($_SESSION['ins_success'], $_SESSION['ins_error']);
$pageTitle = 'Settings';
$pageSubtitle = 'Profile, KYC, links and security.';
$activePage = 'settings';
$kycStatus = (string) ($settings['kyc_status'] ?? 'not_submitted');
?>
<?php include __DIR__ . '/includes/header.php'; ?>
<?php include __DIR__ . '/includes/sidebar.php'; ?>
<main class="main settings-page">
    <?php include __DIR__ . '/includes/topbar.php'; ?>
    <section class="content instructor-content settings-content">
        <?php if ($message): ?><div class="notice"><?= h($message); ?></div><?php endif; ?>
        <?php if ($error): ?><div class="notice danger"><?= h($error); ?></div><?php endif; ?>
        <div class="card custom-card bg-primary-gradient border-0 text-fixed-white">
            <div class="card-body d-flex flex-wrap align-items-center justify-content-between gap-3">
                <div>
                    <span class="badge bg-white-1 text-fixed-white mb-2">Instructor Settings</span>
                    <h4 class="fw-semibold mb-1 text-fixed-white">Profile, live class and security</h4>
                    <p class="mb-0 op-8">Public profile, support links, KYC, live defaults aur password ek jagah manage karein.</p>
                </div>
                <div class="btn-list">
                    <a class="btn btn-light btn-wave" href="<?= h(app_url('ins/dashboard')); ?>">Dashboard</a>
                    <a class="btn btn-outline-light btn-wave" href="<?= h(app_url('ins/live')); ?>">Live Studio</a>
                </div>
            </div>
        </div>
        <?php
        $settingCards = [
            ['label' => 'KYC', 'value' => ucfirst(str_replace('_', ' ', $kycStatus)), 'icon' => 'bx bx-id-card', 'tone' => $kycStatus === 'verified' ? 'success' : 'warning'],
            ['label' => 'Public', 'value' => (int) ($settings['public_profile'] ?? 0) === 1 ? 'Enabled' : 'Off', 'icon' => 'bx bx-globe', 'tone' => 'primary'],
            ['label' => 'Recording', 'value' => (int) ($settings['auto_recording'] ?? 0) === 1 ? 'Auto' : 'Off', 'icon' => 'bx bx-video-recording', 'tone' => (int) ($settings['auto_recording'] ?? 0) === 1 ? 'success' : 'secondary'],
            ['label' => 'Platform', 'value' => ($settings['live_platform'] ?? 'google_meet') === 'youtube_live' ? 'YouTube' : 'Meet', 'icon' => 'bx bx-broadcast', 'tone' => 'info'],
        ];
        ?>
        <div class="row g-3">
            <?php foreach ($settingCards as $card): ?>
                <div class="col-6 col-md-3">
                    <div class="card custom-card">
                        <div class="card-body d-flex align-items-center gap-3">
                            <span class="avatar avatar-md bg-<?= h($card['tone']); ?>-transparent text-<?= h($card['tone']); ?>"><i class="<?= h($card['icon']); ?> fs-20"></i></span>
                            <div>
                                <p class="mb-1 text-muted fs-12 fw-semibold text-uppercase"><?= h($card['label']); ?></p>
                                <h6 class="mb-0 fw-semibold"><?= h($card['value']); ?></h6>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        <form class="instructor-settings-grid" method="post" enctype="multipart/form-data">
            <input type="hidden" name="csrf_token" value="<?= h(csrf_token()); ?>">

            <section class="settings-detail-card ins-card span-2">
                <div class="detail-head compact-head">
                    <div><span>Profile</span><h2>Basic profile</h2><p>Name, email, headline, expertise and bio.</p></div>
                </div>
                <div class="form-grid compact-form">
                    <label>Full Name<input name="full_name" value="<?= h((string) $user['full_name']); ?>" required></label>
                    <label>Email<input type="email" name="email" value="<?= h((string) ($user['email'] ?? '')); ?>" required></label>
                    <label>Headline<input name="profile_headline" value="<?= h((string) ($settings['profile_headline'] ?? '')); ?>" placeholder="e.g. Python & AI Instructor"></label>
                    <label>Qualification<input name="qualification" value="<?= h((string) ($settings['qualification'] ?? '')); ?>" placeholder="MSc, B.Tech, Certified Trainer..."></label>
                    <label class="span-2">Expertise<input name="expertise" value="<?= h((string) ($settings['expertise'] ?? '')); ?>" placeholder="Python, Digital Marketing, Spoken English"></label>
                    <label class="span-2">Bio<textarea name="profile_bio" rows="3" placeholder="Write a short professional instructor bio..."><?= h((string) ($settings['profile_bio'] ?? '')); ?></textarea></label>
                </div>
            </section>

            <section class="settings-detail-card ins-card">
                <div class="detail-head compact-head">
                    <div><span>Contact</span><h2>Support links</h2><p>Phone, WhatsApp, email and channels.</p></div>
                </div>
                <div class="form-grid compact-form single-column">
                    <label>Contact Number<input name="contact_number" inputmode="numeric" maxlength="10" value="<?= h((string) ($settings['contact_number'] ?? '')); ?>" placeholder="10 digit number"></label>
                    <label>WhatsApp Number<input name="whatsapp_number" inputmode="numeric" maxlength="10" value="<?= h((string) ($settings['whatsapp_number'] ?? '')); ?>" placeholder="10 digit WhatsApp"></label>
                    <label>Support Email<input type="email" name="support_email" value="<?= h((string) ($settings['support_email'] ?? '')); ?>" placeholder="support@example.com"></label>
                    <label>Telegram Channel<input name="telegram_channel" value="<?= h((string) ($settings['telegram_channel'] ?? '')); ?>" placeholder="https://t.me/channel"></label>
                    <label>Instagram<input name="instagram_url" value="<?= h((string) ($settings['instagram_url'] ?? '')); ?>" placeholder="https://instagram.com/profile"></label>
                    <label>YouTube Channel<input name="youtube_channel" value="<?= h((string) ($settings['youtube_channel'] ?? '')); ?>" placeholder="https://youtube.com/@channel"></label>
                </div>
            </section>

            <section class="settings-detail-card ins-card">
                <div class="detail-head compact-head">
                    <div><span>Live Class</span><h2>Meet & YouTube</h2><p>Default live links for online classes.</p></div>
                </div>
                <div class="form-grid compact-form single-column">
                    <label>Default Platform<select name="live_platform"><option value="google_meet" <?= ($settings['live_platform'] ?? 'google_meet') === 'google_meet' ? 'selected' : ''; ?>>Google Meet</option><option value="youtube_live" <?= ($settings['live_platform'] ?? '') === 'youtube_live' ? 'selected' : ''; ?>>YouTube Live</option></select></label>
                    <label>Google Meet Link<input name="google_meet_link" value="<?= h((string) ($settings['google_meet_link'] ?? '')); ?>" placeholder="https://meet.google.com/..."></label>
                    <label>YouTube Live Link<input name="youtube_live_link" value="<?= h((string) ($settings['youtube_live_link'] ?? '')); ?>" placeholder="https://youtube.com/live/..."></label>
                </div>
            </section>

            <section class="settings-detail-card ins-card">
                <div class="detail-head compact-head">
                    <div><span>Media</span><h2>Logo & banner</h2><p>Small avatar and wide banner.</p></div>
                </div>
                <div class="profile-media-grid">
                    <label class="profile-upload-card">
                        <span>Logo / Avatar</span>
                        <?php if (!empty($settings['profile_logo_path'])): ?><img src="<?= h(app_url((string) $settings['profile_logo_path'])); ?>" alt="Logo"><?php else: ?><b>Logo</b><?php endif; ?>
                        <input type="file" name="profile_logo" accept=".jpg,.jpeg,.png,.webp">
                    </label>
                    <label class="profile-upload-card wide">
                        <span>Banner</span>
                        <?php if (!empty($settings['profile_banner_path'])): ?><img src="<?= h(app_url((string) $settings['profile_banner_path'])); ?>" alt="Banner"><?php else: ?><b>Banner</b><?php endif; ?>
                        <input type="file" name="profile_banner" accept=".jpg,.jpeg,.png,.webp">
                    </label>
                </div>
            </section>

            <section class="settings-detail-card ins-card">
                <div class="detail-head compact-head">
                    <div><span>KYC</span><h2>Verification</h2><p>Upload one valid identity document.</p></div>
                    <span class="status-pill <?= $kycStatus === 'verified' ? 'ready' : 'empty'; ?>"><?= h(ucfirst(str_replace('_', ' ', $kycStatus))); ?></span>
                </div>
                <div class="form-grid compact-form single-column">
                    <label>Document Type<select name="kyc_document_type">
                        <option value="">Select Document</option>
                        <?php foreach (['aadhaar' => 'Aadhaar', 'pan' => 'PAN', 'passport' => 'Passport', 'driving_license' => 'Driving License', 'voter_id' => 'Voter ID'] as $key => $label): ?>
                            <option value="<?= h($key); ?>" <?= ($settings['kyc_document_type'] ?? '') === $key ? 'selected' : ''; ?>><?= h($label); ?></option>
                        <?php endforeach; ?>
                    </select></label>
                    <label>Document Number<input name="kyc_document_number" value="<?= h((string) ($settings['kyc_document_number'] ?? '')); ?>" placeholder="Enter document number"></label>
                    <label>KYC Document<input type="file" name="kyc_document" accept=".pdf,.jpg,.jpeg,.png,.webp"></label>
                    <?php if (!empty($settings['kyc_document_path'])): ?><a class="inline-file-link" href="<?= h(app_url((string) $settings['kyc_document_path'])); ?>" target="_blank">View uploaded document</a><?php endif; ?>
                </div>
            </section>

            <section class="settings-detail-card ins-card">
                <div class="detail-head compact-head">
                    <div><span>Preferences</span><h2>Defaults</h2><p>Class and notification defaults.</p></div>
                </div>
                <div class="form-grid compact-form single-column">
                    <label>Default Class Mode<select name="default_class_mode"><option value="online" <?= $settings['default_class_mode'] === 'online' ? 'selected' : ''; ?>>Online</option><option value="offline" <?= $settings['default_class_mode'] === 'offline' ? 'selected' : ''; ?>>Offline</option><option value="hybrid" <?= $settings['default_class_mode'] === 'hybrid' ? 'selected' : ''; ?>>Hybrid</option></select></label>
                    <label>Teaching Timezone<input name="teaching_timezone" value="<?= h((string) $settings['teaching_timezone']); ?>"></label>
                    <label class="switch-field">Public Profile<span><input type="checkbox" name="public_profile" value="1" <?= (int) $settings['public_profile'] === 1 ? 'checked' : ''; ?>><b></b></span></label>
                    <label class="switch-field">Auto Recording<span><input type="checkbox" name="auto_recording" value="1" <?= (int) $settings['auto_recording'] === 1 ? 'checked' : ''; ?>><b></b></span></label>
                    <label class="switch-field">Email Notifications<span><input type="checkbox" name="notification_email" value="1" <?= (int) $settings['notification_email'] === 1 ? 'checked' : ''; ?>><b></b></span></label>
                </div>
            </section>

            <section class="settings-detail-card ins-card">
                <div class="detail-head compact-head">
                    <div><span>Security</span><h2>Password</h2><p>Leave blank to keep current password.</p></div>
                </div>
                <div class="form-grid compact-form single-column">
                    <label>New Password<input type="password" name="new_password" autocomplete="new-password" placeholder="Minimum 8 characters"></label>
                    <label>Confirm Password<input type="password" name="confirm_password" autocomplete="new-password" placeholder="Repeat new password"></label>
                </div>
            </section>

            <div class="sticky-settings-actions span-2"><button class="btn btn-primary btn-wave" type="submit">Save Complete Profile</button></div>
        </form>
    </section>
    <style>
        .settings-page .settings-content {
            padding: 12px 13px 28px !important;
            background: #f3f6fa;
        }
        .settings-page .settings-content > .bg-primary-gradient,
        .settings-page .settings-content > .row.g-3 {
            display: none !important;
        }
        .settings-page .instructor-settings-grid {
            display: grid !important;
            grid-template-columns: 260px minmax(0, 1fr) minmax(0, 1fr);
            grid-auto-flow: dense;
            align-items: start;
            gap: 10px 12px;
            margin: 0;
        }
        .settings-page .settings-detail-card {
            overflow: hidden;
            border: 1px solid #d7e2ec !important;
            border-top: 3px solid #f68a00 !important;
            border-radius: 4px !important;
            background: #ffffff !important;
            box-shadow: 0 1px 5px rgba(0, 0, 0, .16) !important;
        }
        .settings-page .instructor-settings-grid > .settings-detail-card {
            grid-column: auto;
        }
        .settings-page .instructor-settings-grid > .settings-detail-card:nth-of-type(1) {
            grid-column: 2 / -1;
        }
        .settings-page .instructor-settings-grid > .settings-detail-card:nth-of-type(4) {
            grid-column: 1;
            grid-row: 1 / span 5;
            order: -1;
            position: sticky;
            top: 62px;
        }
        .settings-page .instructor-settings-grid > .settings-detail-card:nth-of-type(7) {
            grid-column: 2 / -1;
        }
        .settings-page .instructor-settings-grid > .settings-detail-card:nth-of-type(7) .single-column {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }
        .settings-page .instructor-settings-grid > .sticky-settings-actions {
            grid-column: 2 / -1;
            width: 100%;
        }
        .settings-page .settings-detail-card .detail-head {
            display: flex;
            align-items: center;
            justify-content: space-between;
            min-height: 46px;
            padding: 8px 12px;
            border-bottom: 1px solid #dce6ef;
            background: #ffffff;
        }
        .settings-page .settings-detail-card .detail-head span {
            display: block;
            margin-bottom: 2px;
            color: #f68a00;
            font-size: 10px;
            font-weight: 800;
            letter-spacing: .04em;
            line-height: 1;
            text-transform: uppercase;
        }
        .settings-page .settings-detail-card h2 {
            margin: 0;
            color: #1f2f3d;
            font-size: 15px;
            font-weight: 700;
            line-height: 1.2;
        }
        .settings-page .settings-detail-card p {
            margin: 2px 0 0;
            color: #7a8794;
            font-size: 12px;
            line-height: 1.25;
        }
        .settings-page .form-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 11px 12px;
            padding: 12px;
        }
        .settings-page .single-column {
            grid-template-columns: 1fr;
        }
        .settings-page .form-grid label {
            display: grid;
            gap: 5px;
            margin: 0;
            color: #333333;
            font-size: 13px;
            font-weight: 700;
            line-height: 1.25;
        }
        .settings-page .form-grid label.span-2 {
            grid-column: 1 / -1;
        }
        .settings-page .form-grid input,
        .settings-page .form-grid select,
        .settings-page .form-grid textarea {
            width: 100%;
            min-height: 34px;
            padding: 6px 9px;
            border: 1px solid #c8d5e3;
            border-radius: 0;
            background: #ffffff;
            color: #1f2f3d;
            font-size: 13px;
            line-height: 1.35;
            box-shadow: none;
        }
        .settings-page .form-grid input:focus,
        .settings-page .form-grid select:focus,
        .settings-page .form-grid textarea:focus {
            border-color: #f68a00;
            outline: 0;
            box-shadow: 0 0 0 1px rgba(246, 138, 0, .12);
        }
        .settings-page textarea {
            min-height: 74px;
            resize: vertical;
        }
        .settings-page .profile-media-grid {
            display: grid;
            grid-template-columns: 1fr;
            gap: 11px;
            padding: 12px;
        }
        .settings-page .profile-upload-card {
            display: grid;
            align-content: start;
            gap: 8px;
            min-height: 126px;
            margin: 0;
            padding: 10px;
            border: 1px solid #d7e2ec;
            border-radius: 4px;
            background: #ffffff;
            color: #333333;
            font-size: 13px;
            font-weight: 700;
            text-align: center;
        }
        .settings-page .profile-upload-card img,
        .settings-page .profile-upload-card b {
            display: grid;
            place-items: center;
            width: 100%;
            height: 72px;
            border: 1px solid #edf2f7;
            border-radius: 4px;
            background: #ffffff;
            color: #8a96a3;
            font-size: 15px;
            object-fit: contain;
        }
        .settings-page .profile-upload-card.wide img,
        .settings-page .profile-upload-card.wide b {
            height: 88px;
            object-fit: contain;
        }
        .settings-page .profile-upload-card input[type="file"],
        .settings-page .form-grid input[type="file"] {
            min-height: 33px;
            padding: 5px;
            font-size: 12px;
        }
        .settings-page .status-pill {
            padding: 5px 10px;
            border-radius: 999px;
            background: #eef3f7;
            color: #5f6e7d;
            font-size: 11px;
            font-weight: 800;
            white-space: nowrap;
        }
        .settings-page .status-pill.ready {
            background: #ddf7ed;
            color: #008c59;
        }
        .settings-page .switch-field {
            display: flex !important;
            align-items: center;
            justify-content: space-between;
            min-height: 34px;
            padding: 6px 8px;
            border: 1px solid #d7e2ec;
            background: #fbfdff;
        }
        .settings-page .switch-field span {
            position: relative;
            display: inline-block;
            width: 38px;
            min-width: 38px;
            height: 20px;
        }
        .settings-page .switch-field input {
            position: absolute;
            inset: 0;
            width: 100%;
            height: 100%;
            opacity: 0;
        }
        .settings-page .switch-field b {
            position: absolute;
            inset: 0;
            border-radius: 999px;
            background: #cfd9e4;
            transition: .18s;
        }
        .settings-page .switch-field b::after {
            content: "";
            position: absolute;
            top: 3px;
            left: 3px;
            width: 14px;
            height: 14px;
            border-radius: 50%;
            background: #ffffff;
            box-shadow: 0 1px 3px rgba(0, 0, 0, .18);
            transition: .18s;
        }
        .settings-page .switch-field input:checked + b {
            background: #06345f;
        }
        .settings-page .switch-field input:checked + b::after {
            transform: translateX(18px);
        }
        .settings-page .inline-file-link {
            color: #06345f;
            font-size: 12px;
            font-weight: 700;
            text-decoration: none;
        }
        .settings-page .sticky-settings-actions {
            display: flex;
            justify-content: flex-end;
            position: static;
            margin: 0;
            padding: 10px 0 0;
            border: 0;
            background: transparent;
            box-shadow: none;
        }
        .settings-page .sticky-settings-actions .btn {
            min-width: 150px;
            min-height: 34px;
            padding: 7px 16px;
            border-radius: 3px;
            background: #06345f !important;
            color: #ffffff !important;
            font-size: 13px;
            font-weight: 700;
        }
        .settings-page .footer {
            margin-inline: 0 !important;
            width: 100%;
        }
        @media (max-width: 1199.98px) {
            .settings-page .instructor-settings-grid > .settings-detail-card,
            .settings-page .instructor-settings-grid > .settings-detail-card:nth-of-type(1),
            .settings-page .instructor-settings-grid > .settings-detail-card:nth-of-type(4),
            .settings-page .instructor-settings-grid > .settings-detail-card:nth-of-type(7),
            .settings-page .instructor-settings-grid > .sticky-settings-actions {
                grid-column: 1 / -1;
                grid-row: auto;
                position: static;
            }
        }
        @media (max-width: 575.98px) {
            .settings-page .instructor-settings-grid,
            .settings-page .form-grid,
            .settings-page .profile-media-grid,
            .settings-page .instructor-settings-grid > .settings-detail-card:nth-of-type(7) .single-column {
                grid-template-columns: 1fr;
            }
        }
    </style>
<?php include __DIR__ . '/includes/footer.php'; ?>
