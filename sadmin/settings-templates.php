<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/includes/settings_helpers.php';

$user = require_login('superadmin');
$pageTitle = 'Email Templates';
$pageSubtitle = 'Professional HTML templates for account, referral, instructor, billing and license emails.';
$activePage = 'settings';

function email_template_catalog(): array
{
    return [
        ['key' => 'welcome', 'title' => 'Welcome Email', 'purpose' => 'New student/staff account welcome', 'tokens' => '{site_name}, {user_name}, {login_url}'],
        ['key' => 'referral', 'title' => 'Referral Email', 'purpose' => 'Invite a friend or learner', 'tokens' => '{site_name}, {user_name}, {referral_code}, {signup_url}'],
        ['key' => 'forgot_password', 'title' => 'Forgot Password', 'purpose' => 'Password reset link email', 'tokens' => '{site_name}, {user_name}, {reset_url}'],
        ['key' => 'verification', 'title' => 'Email Verification', 'purpose' => 'Verify user email address', 'tokens' => '{site_name}, {user_name}, {verification_url}'],
        ['key' => 'referral_signup', 'title' => 'Referral Signup', 'purpose' => 'Notify referral owner after signup', 'tokens' => '{user_name}, {referral_code}'],
        ['key' => 'instructor_signup', 'title' => 'Instructor Signup', 'purpose' => 'Instructor application confirmation', 'tokens' => '{site_name}, {instructor_name}'],
        ['key' => 'instructor_approval', 'title' => 'Instructor Approval', 'purpose' => 'Instructor account approved', 'tokens' => '{site_name}, {instructor_name}, {login_url}'],
        ['key' => 'student_enrollment', 'title' => 'Student Enrollment', 'purpose' => 'Course enrollment confirmation', 'tokens' => '{user_name}, {course_name}, {course_url}'],
        ['key' => 'payment_success', 'title' => 'Payment Success', 'purpose' => 'Payment and invoice confirmation', 'tokens' => '{user_name}, {amount}, {invoice_no}'],
        ['key' => 'license_renewal', 'title' => 'License Renewal', 'purpose' => 'Annual license renewal reminder', 'tokens' => '{site_name}, {expiry_date}, {renewal_url}'],
        ['key' => 'license_expired', 'title' => 'License Expired', 'purpose' => 'License expired notification', 'tokens' => '{site_name}, {expiry_date}'],
        ['key' => 'support_assigned', 'title' => 'Support Assigned', 'purpose' => 'Student/instructor support staff assignment', 'tokens' => '{user_name}, {support_name}, {support_email}'],
    ];
}

$templates = email_template_catalog();
$templateKeys = ['email_template_header', 'email_template_footer'];
foreach ($templates as $template) {
    $templateKeys[] = 'email_' . $template['key'] . '_subject';
    $templateKeys[] = 'email_' . $template['key'] . '_body';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['csrf_token'] ?? null)) {
        $_SESSION['settings_error'] = 'Security token expired.';
        redirect('sadmin/settings-templates');
    }

    foreach ($templates as $template) {
        $enabledKey = 'email_' . $template['key'] . '_enabled';
        save_setting($enabledKey, isset($_POST[$enabledKey]) ? '1' : '0');
    }
    save_settings_keys($templateKeys);
    $_SESSION['settings_message'] = 'Email templates updated.';
    redirect('sadmin/settings-templates');
}

$settings = all_settings();
$enabledCount = 0;
foreach ($templates as $template) {
    if (($settings['email_' . $template['key'] . '_enabled'] ?? '0') === '1') {
        $enabledCount++;
    }
}
[$message, $error] = settings_flash();
?>
<?php include __DIR__ . '/includes/header.php'; ?>
<?php include __DIR__ . '/includes/sidebar.php'; ?>
<main class="main">
    <?php include __DIR__ . '/includes/topbar.php'; ?>
    <section class="content settings-page">
        <?php render_settings_submenu('templates'); ?>
        <?php if ($message): ?><div class="notice"><?= h($message); ?></div><?php endif; ?>
        <?php if ($error): ?><div class="notice danger"><?= h($error); ?></div><?php endif; ?>

        <section class="settings-detail-card">
            <div class="detail-head">
                <div>
                    <span>Email Studio</span>
                    <h2><?= count($templates); ?> professional templates</h2>
                    <p><?= (int) $enabledCount; ?> enabled. Use HTML body with placeholders for dynamic mail rendering.</p>
                </div>
                <a class="modal-button" href="#edit-all-templates">Global Header/Footer</a>
            </div>
            <div class="template-stat-grid">
                <div><strong>Enabled</strong><b><?= (int) $enabledCount; ?></b></div>
                <div><strong>Templates</strong><b><?= count($templates); ?></b></div>
                <div><strong>Editor</strong><b>HTML</b></div>
                <div><strong>Branding</strong><b>{site_name}</b></div>
            </div>
        </section>

        <section class="settings-detail-card compact-section">
            <div class="detail-head">
                <div>
                    <span>Template Library</span>
                    <h2>System email templates</h2>
                    <p>Edit subject, HTML message and active status for every mail sent by the LMS.</p>
                </div>
            </div>
            <div class="email-template-table-wrap">
                <table class="email-template-table">
                    <thead>
                        <tr>
                            <th>Template</th>
                            <th>Subject</th>
                            <th>Status</th>
                            <th>Tokens</th>
                            <th>Edit</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($templates as $template): ?>
                            <?php
                            $prefix = 'email_' . $template['key'];
                            $enabled = ($settings[$prefix . '_enabled'] ?? '0') === '1';
                            ?>
                            <tr>
                                <td><strong><?= h($template['title']); ?></strong><small><?= h($template['purpose']); ?></small></td>
                                <td><?= h($settings[$prefix . '_subject'] ?? 'Not set'); ?></td>
                                <td><span class="status-pill <?= $enabled ? 'ready' : 'empty'; ?>"><?= $enabled ? 'Enabled' : 'Disabled'; ?></span></td>
                                <td><code><?= h($template['tokens']); ?></code></td>
                                <td><a class="table-edit-icon" href="#template-<?= h($template['key']); ?>" aria-label="Edit <?= h($template['title']); ?>">Edit</a></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>
    </section>

    <div id="edit-all-templates" class="modal-overlay">
        <form class="modal-box wide-modal template-editor-form" method="post">
            <input type="hidden" name="csrf_token" value="<?= h(csrf_token()); ?>">
            <?php foreach ($templates as $template): ?>
                <input type="hidden" name="email_<?= h($template['key']); ?>_subject" value="<?= setting_value($settings, 'email_' . $template['key'] . '_subject'); ?>">
                <textarea hidden name="email_<?= h($template['key']); ?>_body"><?= setting_value($settings, 'email_' . $template['key'] . '_body'); ?></textarea>
                <?php if (($settings['email_' . $template['key'] . '_enabled'] ?? '0') === '1'): ?><input type="hidden" name="email_<?= h($template['key']); ?>_enabled" value="1"><?php endif; ?>
            <?php endforeach; ?>
            <div class="modal-head"><h2>Global Email Header/Footer</h2><a class="modal-close" href="#" aria-label="Close">×</a></div>
            <div class="form-grid">
                <label class="span-2">Header HTML<textarea name="email_template_header" rows="3"><?= setting_value($settings, 'email_template_header'); ?></textarea></label>
                <label class="span-2">Footer HTML<textarea name="email_template_footer" rows="3"><?= setting_value($settings, 'email_template_footer'); ?></textarea></label>
            </div>
            <div class="modal-actions"><button type="submit">Save Header/Footer</button></div>
        </form>
    </div>

    <?php foreach ($templates as $template): ?>
        <?php $prefix = 'email_' . $template['key']; ?>
        <div id="template-<?= h($template['key']); ?>" class="modal-overlay">
            <form class="modal-box wide-modal template-editor-form" method="post">
                <input type="hidden" name="csrf_token" value="<?= h(csrf_token()); ?>">
                <input type="hidden" name="email_template_header" value="<?= setting_value($settings, 'email_template_header'); ?>">
                <input type="hidden" name="email_template_footer" value="<?= setting_value($settings, 'email_template_footer'); ?>">
                <?php foreach ($templates as $other): ?>
                    <?php $otherPrefix = 'email_' . $other['key']; ?>
                    <?php if ($other['key'] !== $template['key']): ?>
                        <input type="hidden" name="<?= h($otherPrefix); ?>_subject" value="<?= setting_value($settings, $otherPrefix . '_subject'); ?>">
                        <textarea hidden name="<?= h($otherPrefix); ?>_body"><?= setting_value($settings, $otherPrefix . '_body'); ?></textarea>
                        <?php if (($settings[$otherPrefix . '_enabled'] ?? '0') === '1'): ?><input type="hidden" name="<?= h($otherPrefix); ?>_enabled" value="1"><?php endif; ?>
                    <?php endif; ?>
                <?php endforeach; ?>
                <div class="modal-head"><h2>Edit <?= h($template['title']); ?></h2><a class="modal-close" href="#" aria-label="Close">×</a></div>
                <div class="form-grid">
                    <label class="switch-field">Template Active<span><input type="checkbox" name="<?= h($prefix); ?>_enabled" value="1" <?= ($settings[$prefix . '_enabled'] ?? '0') === '1' ? 'checked' : ''; ?>><b></b></span></label>
                    <label class="span-2">Subject<input name="<?= h($prefix); ?>_subject" value="<?= setting_value($settings, $prefix . '_subject'); ?>"></label>
                </div>
                <div class="template-editor-layout">
                    <div class="template-editor-shell">
                        <div class="editor-toolbar">
                            <button type="button" data-command="bold">B</button>
                            <button type="button" data-command="italic">I</button>
                            <button type="button" data-command="insertUnorderedList">List</button>
                            <button type="button" data-command="createLink">Link</button>
                            <button type="button" data-command="formatBlock" data-value="h2">H2</button>
                            <button type="button" data-command="formatBlock" data-value="p">P</button>
                        </div>
                        <textarea id="body-<?= h($template['key']); ?>" name="<?= h($prefix); ?>_body" hidden><?= setting_value($settings, $prefix . '_body'); ?></textarea>
                        <div class="html-editor" contenteditable="true" data-target="body-<?= h($template['key']); ?>" data-preview="preview-<?= h($template['key']); ?>"><?= $settings[$prefix . '_body'] ?? ''; ?></div>
                        <div class="template-token-row"><strong>Tokens</strong><span><?= h($template['tokens']); ?></span></div>
                    </div>
                    <div class="template-preview-panel">
                        <div class="preview-label">Design Preview</div>
                        <div class="email-design-preview">
                            <div class="email-preview-top">
                                <b><?= h(app_name()); ?></b>
                                <span><?= h($template['title']); ?></span>
                            </div>
                            <div class="email-preview-body" id="preview-<?= h($template['key']); ?>"><?= $settings[$prefix . '_body'] ?? ''; ?></div>
                            <div class="email-preview-foot"><?= $settings['email_template_footer'] ?? 'Regards, {site_name}'; ?></div>
                        </div>
                    </div>
                </div>
                <div class="modal-actions"><button type="submit">Save Template</button></div>
            </form>
        </div>
    <?php endforeach; ?>
<script>
(() => {
    document.querySelectorAll('.template-editor-form').forEach((form) => {
        const sync = () => {
            form.querySelectorAll('.html-editor[data-target]').forEach((editor) => {
                const field = document.getElementById(editor.dataset.target);
                if (field) field.value = editor.innerHTML.trim();
                if (editor.dataset.preview) {
                    const preview = document.getElementById(editor.dataset.preview);
                    if (preview) preview.innerHTML = editor.innerHTML.trim();
                }
            });
        };

        form.querySelectorAll('.editor-toolbar button').forEach((button) => {
            button.addEventListener('click', () => {
                const command = button.dataset.command;
                let value = button.dataset.value || null;
                if (command === 'createLink') {
                    value = window.prompt('Enter link URL', 'https://');
                    if (!value) return;
                }
                document.execCommand(command, false, value);
                sync();
            });
        });

        form.querySelectorAll('.html-editor').forEach((editor) => {
            editor.addEventListener('input', sync);
            editor.addEventListener('blur', sync);
        });
        form.addEventListener('submit', sync);
    });
})();
</script>
<?php include __DIR__ . '/includes/footer.php'; ?>
