<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/includes/settings_helpers.php';

$user = require_login('superadmin');
$pageTitle = 'Settings';
$pageSubtitle = 'Choose a setting area. Each area opens on its own clean page with modal editing.';
$activePage = 'settings';
[$message, $error] = settings_flash();
$settings = all_settings();
$settingInventory = [
    'General' => [
        ['Site Name', 'site_name', 'Application/institute display name.', app_url('sadmin/settings-general')],
        ['Tagline', 'site_tagline', 'Short public and dashboard subtitle.', app_url('sadmin/settings-general')],
        ['Site Email', 'site_email', 'Primary support/admin email.', app_url('sadmin/settings-general')],
        ['Support Call', 'support_call_number', 'Public support calling number.', app_url('sadmin/settings-general')],
        ['WhatsApp Number', 'support_whatsapp_number', 'Public WhatsApp support number.', app_url('sadmin/settings-general')],
        ['Support Email', 'support_email', 'Public support email address.', app_url('sadmin/settings-general')],
        ['Google Login Status', 'google_login_enabled', 'Enable direct Google login for app and website.', app_url('sadmin/settings-general')],
        ['Google Client ID', 'google_client_id', 'OAuth client id for Google login.', app_url('sadmin/settings-general')],
        ['Google Client Secret', 'google_client_secret', 'OAuth client secret for Google login.', app_url('sadmin/settings-general')],
        ['Google Redirect URI', 'google_redirect_uri', 'Authorized redirect URI for Google OAuth.', app_url('sadmin/settings-general')],
        ['Address', 'site_address', 'Institute/company address.', app_url('sadmin/settings-general')],
        ['Copyright Text', 'copyright_text', 'Copyright text shown in footer.', app_url('sadmin/settings-general')],
    ],
    'Branding' => [
        ['Sidebar Logo', 'logo_path', 'Logo used in admin sidebar.', app_url('sadmin/settings-branding')],
        ['App Logo', 'app_logo_path', 'Logo used in app panels and future mobile layout.', app_url('sadmin/settings-branding')],
        ['App Icon', 'app_icon_path', 'Square app icon for compact UI areas.', app_url('sadmin/settings-branding')],
        ['Website Logo', 'website_logo_path', 'Public website logo.', app_url('sadmin/settings-branding')],
        ['Favicon', 'favicon_path', 'Browser tab icon.', app_url('sadmin/settings-branding')],
        ['Facebook URL', 'facebook_url', 'Official Facebook page link.', app_url('sadmin/settings-branding')],
        ['Instagram URL', 'instagram_url', 'Official Instagram profile link.', app_url('sadmin/settings-branding')],
        ['YouTube URL', 'youtube_url', 'Official YouTube channel link.', app_url('sadmin/settings-branding')],
        ['Play Store URL', 'playstore_url', 'Android app download link.', app_url('sadmin/settings-branding')],
        ['Instructor Referral Type', 'instructor_referral_commission_type', 'Commission mode for instructor referral signup.', app_url('sadmin/settings-branding')],
        ['Instructor Referral Value', 'instructor_referral_commission_value', 'Commission value for instructor referral signup.', app_url('sadmin/settings-branding')],
        ['User Referral Type', 'user_referral_commission_type', 'Commission mode for user referral signup.', app_url('sadmin/settings-branding')],
        ['User Referral Value', 'user_referral_commission_value', 'Commission value for user referral signup.', app_url('sadmin/settings-branding')],
    ],
    'Email & Notification' => [
        ['Mail Driver', 'mail_driver', 'Mail transport method.', app_url('sadmin/settings-mail')],
        ['Mail Host', 'mail_host', 'SMTP host/server.', app_url('sadmin/settings-mail')],
        ['Mail Port', 'mail_port', 'SMTP port.', app_url('sadmin/settings-mail')],
        ['Mail Username', 'mail_username', 'SMTP username.', app_url('sadmin/settings-mail')],
        ['Mail Password', 'mail_password', 'SMTP password/secret.', app_url('sadmin/settings-mail')],
        ['Mail Encryption', 'mail_encryption', 'TLS/SSL encryption mode.', app_url('sadmin/settings-mail')],
        ['From Email', 'mail_from_email', 'Sender email address.', app_url('sadmin/settings-mail')],
        ['From Name', 'mail_from_name', 'Sender display name.', app_url('sadmin/settings-mail')],
        ['Firebase Status', 'firebase_enabled', 'Enable/disable Firebase notifications.', app_url('sadmin/settings-mail')],
        ['Firebase Project ID', 'firebase_project_id', 'Firebase project identifier.', app_url('sadmin/settings-mail')],
        ['Firebase API Key', 'firebase_api_key', 'Firebase web API key.', app_url('sadmin/settings-mail')],
        ['Firebase Auth Domain', 'firebase_auth_domain', 'Firebase auth domain.', app_url('sadmin/settings-mail')],
        ['Firebase Storage Bucket', 'firebase_storage_bucket', 'Firebase storage bucket.', app_url('sadmin/settings-mail')],
        ['Firebase Sender ID', 'firebase_messaging_sender_id', 'Firebase messaging sender id.', app_url('sadmin/settings-mail')],
        ['Firebase App ID', 'firebase_app_id', 'Firebase application id.', app_url('sadmin/settings-mail')],
        ['Firebase VAPID Key', 'firebase_vapid_key', 'Web push VAPID key.', app_url('sadmin/settings-mail')],
        ['Firebase Server Key', 'firebase_server_key', 'Server key for notification sending.', app_url('sadmin/settings-mail')],
    ],
    'Billing & Tax' => [
        ['Invoice Prefix', 'invoice_prefix', 'Invoice number prefix.', app_url('sadmin/settings-billing')],
        ['Invoice Format', 'invoice_format', 'Token based invoice number format.', app_url('sadmin/settings-billing')],
        ['Invoice Starting No', 'invoice_starting_no', 'First invoice number for a financial year.', app_url('sadmin/settings-billing')],
        ['Invoice Current No', 'invoice_current_no', 'Current running invoice number.', app_url('sadmin/settings-billing')],
        ['Financial Year Start', 'financial_year_start', 'Start date for current billing financial year.', app_url('sadmin/settings-billing')],
        ['FY Close Reset', 'financial_year_close_reset', 'Reset numbering when financial year closes.', app_url('sadmin/settings-billing')],
        ['GST Number', 'gst_number', 'GST/tax registration number.', app_url('sadmin/settings-billing')],
        ['Billing Address', 'billing_address', 'Registered billing address printed on invoice.', app_url('sadmin/settings-billing')],
        ['Billing State Code', 'billing_state_code', 'GST state code for seller billing address.', app_url('sadmin/settings-billing')],
        ['Billing State Name', 'billing_state_name', 'GST state name for seller billing address.', app_url('sadmin/settings-billing')],
        ['Supply State Code', 'default_supply_state_code', 'Default buyer/supply state used for CGST or IGST.', app_url('sadmin/settings-billing')],
        ['Tax Rate', 'tax_rate', 'Default tax percentage.', app_url('sadmin/settings-billing')],
        ['Currency', 'currency', 'Billing currency.', app_url('sadmin/settings-billing')],
        ['Currency Symbol', 'currency_symbol', 'Symbol printed beside invoice amounts.', app_url('sadmin/settings-billing')],
        ['Invoice Terms', 'invoice_terms', 'Terms and conditions printed on invoice.', app_url('sadmin/settings-billing')],
        ['Thank You Note', 'invoice_thank_you_note', 'Thank you note printed on invoice.', app_url('sadmin/settings-billing')],
        ['Invoice Footer', 'invoice_footer', 'Default invoice footer note.', app_url('sadmin/settings-billing')],
        ['PhonePe Status', 'phonepe_enabled', 'Enable PhonePe checkout for paid course/PDF purchase.', app_url('sadmin/settings-billing')],
        ['PhonePe Merchant ID', 'phonepe_merchant_id', 'Merchant identifier for PhonePe gateway.', app_url('sadmin/settings-billing')],
        ['PhonePe Environment', 'phonepe_environment', 'Sandbox or live PhonePe endpoint.', app_url('sadmin/settings-billing')],
    ],
    'Email Templates' => [
        ['Template Header', 'email_template_header', 'Default email header content.', app_url('sadmin/settings-templates')],
        ['Template Footer', 'email_template_footer', 'Default email footer content.', app_url('sadmin/settings-templates')],
        ['Welcome Email', 'email_welcome_subject', 'New account welcome template.', app_url('sadmin/settings-templates')],
        ['Referral Email', 'email_referral_subject', 'Referral invite template.', app_url('sadmin/settings-templates')],
        ['Forgot Password', 'email_forgot_password_subject', 'Password reset email template.', app_url('sadmin/settings-templates')],
        ['Email Verification', 'email_verification_subject', 'Email verification template.', app_url('sadmin/settings-templates')],
        ['Referral Signup', 'email_referral_signup_subject', 'Referral signup notification template.', app_url('sadmin/settings-templates')],
        ['Instructor Signup', 'email_instructor_signup_subject', 'Instructor signup confirmation template.', app_url('sadmin/settings-templates')],
        ['Instructor Approval', 'email_instructor_approval_subject', 'Instructor approval template.', app_url('sadmin/settings-templates')],
        ['Student Enrollment', 'email_student_enrollment_subject', 'Course enrollment template.', app_url('sadmin/settings-templates')],
        ['Payment Success', 'email_payment_success_subject', 'Payment success template.', app_url('sadmin/settings-templates')],
        ['License Renewal', 'email_license_renewal_subject', 'License renewal reminder template.', app_url('sadmin/settings-templates')],
        ['License Expired', 'email_license_expired_subject', 'License expired notification template.', app_url('sadmin/settings-templates')],
        ['Support Assigned', 'email_support_assigned_subject', 'Support assignment template.', app_url('sadmin/settings-templates')],
    ],
    'Student Plans' => [
        ['Free Plan', 'student_plan_free', 'Default free access with limited content usage.', app_url('sadmin/settings-plans')],
        ['Silver Plan', 'student_plan_silver', 'Paid monthly plan for larger video, PDF and exam access.', app_url('sadmin/settings-plans')],
        ['Gold Plan', 'student_plan_gold', 'Premium long validity plan with maximum access.', app_url('sadmin/settings-plans')],
    ],
    'Important Pages' => [
        ['About Us', 'important_page_about_body', 'Public about page content.', app_url('sadmin/settings-pages')],
        ['Privacy Policy', 'important_page_privacy_body', 'Public privacy policy content.', app_url('sadmin/settings-pages')],
        ['Account Delete Policy', 'important_page_account_delete_policy_body', 'Account deletion policy content.', app_url('sadmin/settings-pages')],
        ['Cancellation Refund', 'important_page_cancellation_refund_body', 'Cancellation and refund policy content.', app_url('sadmin/settings-pages')],
        ['Terms Conditions', 'important_page_terms_and_conditions_body', 'Registration terms and conditions content.', app_url('sadmin/settings-pages')],
    ],
    'Staff & Roles' => [
        ['Instructor Commission Type', 'instructor_commission_type', 'Percent or fixed commission mode.', app_url('sadmin/settings-staff')],
        ['Instructor Commission Value', 'instructor_commission_value', 'Default instructor payout value.', app_url('sadmin/settings-staff')],
        ['Default Role', 'default_role', 'Default user role.', app_url('sadmin/settings-staff')],
        ['Instructor Support Role', 'default_instructor_support_role', 'Default role assigned to support instructors.', app_url('sadmin/settings-staff')],
        ['Student Support Role', 'default_student_support_role', 'Default role assigned to support students.', app_url('sadmin/settings-staff')],
        ['Support Assignment Mode', 'support_assignment_mode', 'Manual, round robin or role based assignment.', app_url('sadmin/settings-staff')],
        ['Auto Support Assignment', 'support_auto_assign_enabled', 'Automatically assign support staff when possible.', app_url('sadmin/settings-staff')],
        ['Max Students Per Support', 'max_students_per_support', 'Capacity limit for student support assignment.', app_url('sadmin/settings-staff')],
        ['Max Instructors Per Support', 'max_instructors_per_support', 'Capacity limit for instructor support assignment.', app_url('sadmin/settings-staff')],
        ['Support Escalation Hours', 'support_escalation_hours', 'Hours before unattended support case escalates.', app_url('sadmin/settings-staff')],
        ['Salary Cycle', 'salary_cycle', 'Staff salary calculation cycle.', app_url('sadmin/settings-staff')],
        ['Salary Pay Day', 'salary_pay_day', 'Default monthly salary payout day.', app_url('sadmin/settings-staff')],
        ['Probation Days', 'probation_days', 'Default staff probation duration.', app_url('sadmin/settings-staff')],
        ['Monthly Paid Leaves', 'monthly_paid_leaves', 'Default paid leaves per month.', app_url('sadmin/settings-staff')],
        ['Attendance Mode', 'attendance_mode', 'Attendance tracking mode.', app_url('sadmin/settings-staff')],
        ['Attendance Grace Minutes', 'attendance_grace_minutes', 'Late grace time for attendance.', app_url('sadmin/settings-staff')],
        ['Half Day After Minutes', 'half_day_after_minutes', 'Minutes threshold for half-day attendance.', app_url('sadmin/settings-staff')],
        ['Report Approval Flow', 'report_approval_flow', 'Approval flow for staff reports.', app_url('sadmin/settings-staff')],
        ['Report Timezone', 'report_timezone', 'Timezone used in reports.', app_url('sadmin/settings-staff')],
    ],
];
$totalSettings = array_sum(array_map('count', $settingInventory));
$notSetCount = 0;
foreach ($settingInventory as $rows) {
    foreach ($rows as $row) {
        if (trim((string) ($settings[$row[1]] ?? '')) === '') {
            $notSetCount++;
        }
    }
}
?>
<?php include __DIR__ . '/includes/header.php'; ?>
<?php include __DIR__ . '/includes/sidebar.php'; ?>
<main class="main sadmin-settings-main">
    <?php include __DIR__ . '/includes/topbar.php'; ?>

    <section class="content settings-overview-page">
        <?php if ($message): ?><div class="notice"><?= h($message); ?></div><?php endif; ?>
        <?php if ($error): ?><div class="notice danger"><?= h($error); ?></div><?php endif; ?>

        <section class="settings-command-card">
            <div>
                <span>System Settings</span>
                <h1>Manage platform configuration</h1>
                <p>Branding, billing, PhonePe, plans, email, legal pages and staff controls are grouped into clean sections.</p>
            </div>
            <div class="settings-health">
                <strong><?= h((string) ($totalSettings - $notSetCount)); ?></strong>
                <span>configured</span>
                <b><?= h((string) $notSetCount); ?> missing</b>
            </div>
        </section>

        <?php render_settings_submenu('overview'); ?>

        <section class="card custom-card settings-register-card">
            <div class="card-header justify-content-between">
                <div>
                    <h6 class="mb-1 fw-semibold">Settings Register</h6>
                    <p class="mb-0 text-muted fs-12">All configuration sections in one simple table.</p>
                </div>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0 gr-register-table settings-register-table">
                        <thead>
                        <tr>
                            <th>Section</th>
                            <th>Description</th>
                            <th>Total</th>
                            <th>Set</th>
                            <th>Missing</th>
                            <th class="text-end">Action</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach (settings_sections() as $section): ?>
                            <?php
                            $rowsForSection = [];
                            foreach ($settingInventory as $groupRows) {
                                foreach ($groupRows as $row) {
                                    if ($row[3] === $section['url']) {
                                        $rowsForSection[] = $row;
                                    }
                                }
                            }
                            $sectionTotal = count($rowsForSection);
                            $sectionMissing = 0;
                            foreach ($rowsForSection as $row) {
                                if (trim((string) ($settings[$row[1]] ?? '')) === '') {
                                    $sectionMissing++;
                                }
                            }
                            $sectionReady = max(0, $sectionTotal - $sectionMissing);
                            ?>
                            <tr>
                                <td><span class="gr-cell-title"><?= h($section['title']); ?></span></td>
                                <td><span class="gr-cell-subtitle"><?= h($section['subtitle']); ?></span></td>
                                <td><?= h((string) $sectionTotal); ?></td>
                                <td><span class="status-pill ready"><?= h((string) $sectionReady); ?> Set</span></td>
                                <td><span class="status-pill <?= $sectionMissing > 0 ? 'empty' : 'ready'; ?>"><?= h((string) $sectionMissing); ?> Missing</span></td>
                                <td class="text-end"><a class="btn btn-sm btn-light btn-wave" href="<?= h($section['url']); ?>">Manage</a></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </section>

        <div class="settings-area-grid">
            <?php foreach (settings_sections() as $section): ?>
                <?php
                $rowsForSection = [];
                foreach ($settingInventory as $groupRows) {
                    foreach ($groupRows as $row) {
                        if ($row[3] === $section['url']) {
                            $rowsForSection[] = $row;
                        }
                    }
                }
                $sectionTotal = count($rowsForSection);
                $sectionMissing = 0;
                foreach ($rowsForSection as $row) {
                    if (trim((string) ($settings[$row[1]] ?? '')) === '') {
                        $sectionMissing++;
                    }
                }
                $sectionReady = max(0, $sectionTotal - $sectionMissing);
                ?>
                <a class="settings-area-card" href="<?= h($section['url']); ?>">
                    <span class="settings-area-icon"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="<?= h($section['icon']); ?>"></path></svg></span>
                    <div>
                        <h3><?= h($section['title']); ?></h3>
                        <p><?= h($section['subtitle']); ?></p>
                    </div>
                    <div class="settings-area-stats">
                        <span><b><?= h((string) $sectionReady); ?></b> Set</span>
                        <span><b><?= h((string) $sectionMissing); ?></b> Missing</span>
                    </div>
                    <em>Manage</em>
                </a>
            <?php endforeach; ?>
        </div>

        <div class="settings-table-grid">
            <?php foreach ($settingInventory as $group => $rows): ?>
                <section class="settings-mini-table">
                    <div class="mini-table-title">
                        <h3><?= h($group); ?></h3>
                        <a href="<?= h($rows[0][3]); ?>">Manage</a>
                    </div>
                    <table>
                        <thead>
                            <tr>
                        <th>Setting</th>
                        <th>Status</th>
                        <th>Edit</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($rows as $row): ?>
                                <?php
                                $value = trim((string) ($settings[$row[1]] ?? ''));
                                $displayValue = $value === '' ? 'Not Set' : 'Set';
                                ?>
                                <tr>
                                    <td title="<?= h($row[2]); ?>"><?= h($row[0]); ?></td>
                                    <td><span class="status-pill <?= $value === '' ? 'empty' : 'ready'; ?>"><?= h($displayValue); ?></span></td>
                                    <td><a class="table-edit-icon" href="<?= h($row[3]); ?>" aria-label="Edit <?= h($row[0]); ?>">✎</a></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </section>
            <?php endforeach; ?>
        </div>
    </section>
    <style>
        .sadmin-settings-main .settings-overview-page { padding-top: 1.25rem; }
        .sadmin-settings-main .settings-submenu,
        .sadmin-settings-main .settings-area-grid,
        .sadmin-settings-main .settings-table-grid {
            display: none !important;
        }
        .sadmin-settings-main .settings-register-card {
            border: 0;
            border-radius: .65rem;
            box-shadow: 0 .85rem 1.85rem rgba(15, 23, 42, .045);
            overflow: hidden;
        }
        .sadmin-settings-main .settings-register-card .card-header {
            padding: .75rem 1rem;
            border-bottom: 1px solid var(--default-border);
            background: var(--custom-white);
        }
        .sadmin-settings-main .settings-register-table {
            min-width: 48rem;
        }
        .sadmin-settings-main .settings-register-table th,
        .sadmin-settings-main .settings-register-table td {
            padding: .42rem .65rem !important;
            font-size: .72rem;
            line-height: 1.2;
            vertical-align: middle;
        }
        .sadmin-settings-main .settings-register-table th {
            background: var(--default-background);
            font-size: .65rem;
            letter-spacing: .025em;
            text-transform: uppercase;
        }
        .sadmin-settings-main .settings-register-table .gr-cell-title {
            display: block;
            max-width: 16rem;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            font-size: .74rem;
            font-weight: 700;
        }
        .sadmin-settings-main .settings-register-table .gr-cell-subtitle {
            display: block;
            max-width: 22rem;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            color: var(--text-muted);
            font-size: .66rem;
        }
        .sadmin-settings-main .settings-register-table .status-pill {
            min-height: 1.2rem;
            padding: .15rem .45rem;
            border-radius: 999px;
            font-size: .61rem;
            font-weight: 800;
        }
        .sadmin-settings-main .settings-register-table .btn-sm {
            min-height: 1.55rem;
            padding: .18rem .55rem;
            font-size: .67rem;
        }
        .sadmin-settings-main .settings-command-card,
        .sadmin-settings-main .settings-area-card,
        .sadmin-settings-main .settings-mini-table {
            border: 0;
            border-radius: .65rem;
            background: var(--custom-white);
            box-shadow: 0 .85rem 1.85rem rgba(15, 23, 42, .045);
        }
        .sadmin-settings-main .settings-command-card {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
            padding: 1rem 1.15rem;
            margin-bottom: 1rem;
        }
        .sadmin-settings-main .settings-command-card span,
        .sadmin-settings-main .settings-area-card em {
            color: rgb(var(--primary-rgb));
            font-size: .68rem;
            font-weight: 800;
            letter-spacing: .04em;
            text-transform: uppercase;
            font-style: normal;
        }
        .sadmin-settings-main .settings-command-card h1 {
            margin: .15rem 0 .2rem;
            color: var(--default-text-color);
            font-size: 1.25rem;
            font-weight: 800;
            letter-spacing: 0;
        }
        .sadmin-settings-main .settings-command-card p {
            max-width: 48rem;
            margin: 0;
            color: var(--text-muted);
            font-size: .82rem;
        }
        .sadmin-settings-main .settings-health {
            display: grid;
            min-width: 8.5rem;
            padding: .75rem .9rem;
            border: 1px solid var(--default-border);
            border-radius: .55rem;
            text-align: center;
            background: var(--default-background);
        }
        .sadmin-settings-main .settings-health strong { font-size: 1.45rem; line-height: 1; }
        .sadmin-settings-main .settings-health span { color: var(--text-muted); font-size: .66rem; }
        .sadmin-settings-main .settings-health b { color: rgb(var(--warning-rgb)); font-size: .7rem; }
        .sadmin-settings-main .settings-area-grid {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: .75rem;
            margin: 1rem 0;
        }
        .sadmin-settings-main .settings-area-card {
            display: grid;
            grid-template-columns: 2.25rem minmax(0, 1fr) auto;
            align-items: center;
            gap: .65rem;
            min-height: 4.75rem;
            padding: .75rem .85rem;
            color: var(--default-text-color);
            text-decoration: none;
        }
        .sadmin-settings-main .settings-area-icon {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 2.25rem;
            height: 2.25rem;
            border-radius: .5rem;
            background: rgba(var(--primary-rgb), .09);
        }
        .sadmin-settings-main .settings-area-icon svg {
            width: 1.05rem;
            height: 1.05rem;
            fill: none;
            stroke: currentColor;
            stroke-width: 2;
            stroke-linecap: round;
            stroke-linejoin: round;
            color: rgb(var(--primary-rgb));
        }
        .sadmin-settings-main .settings-area-icon svg,
        .sadmin-settings-main .settings-submenu svg {
            display: block !important;
            width: 1.05rem !important;
            height: 1.05rem !important;
            max-width: 1.05rem !important;
            max-height: 1.05rem !important;
            flex: 0 0 1.05rem;
            overflow: visible;
        }
        .sadmin-settings-main .settings-area-icon svg path,
        .sadmin-settings-main .settings-submenu svg path {
            fill: none !important;
            stroke: currentColor !important;
            stroke-width: 2 !important;
            stroke-linecap: round !important;
            stroke-linejoin: round !important;
        }
        .sadmin-settings-main .settings-area-card h3 {
            margin: 0;
            overflow: hidden;
            color: var(--default-text-color);
            text-overflow: ellipsis;
            white-space: nowrap;
            font-size: .82rem;
            font-weight: 800;
        }
        .sadmin-settings-main .settings-area-card p {
            margin: .12rem 0 0;
            overflow: hidden;
            color: var(--text-muted);
            text-overflow: ellipsis;
            white-space: nowrap;
            font-size: .68rem;
        }
        .sadmin-settings-main .settings-area-stats {
            display: flex;
            grid-column: 2 / 4;
            gap: .35rem;
            color: var(--text-muted);
            font-size: .66rem;
        }
        .sadmin-settings-main .settings-area-stats span {
            padding: .13rem .45rem;
            border-radius: 999px;
            background: var(--default-background);
        }
        .sadmin-settings-main .settings-table-grid {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: .85rem;
        }
        .sadmin-settings-main .settings-mini-table { overflow: hidden; }
        .sadmin-settings-main .mini-table-title {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: .75rem;
            padding: .7rem .85rem;
            border-bottom: 1px solid var(--default-border);
            background: var(--custom-white);
        }
        .sadmin-settings-main .mini-table-title h3 {
            margin: 0;
            color: var(--default-text-color);
            font-size: .85rem;
            font-weight: 800;
        }
        .sadmin-settings-main .mini-table-title a {
            padding: .2rem .55rem;
            border-radius: .4rem;
            background: rgba(var(--primary-rgb), .1);
            color: rgb(var(--primary-rgb));
            font-size: .68rem;
            font-weight: 800;
            text-decoration: none;
        }
        .sadmin-settings-main .settings-mini-table table { width: 100%; border-collapse: collapse; }
        .sadmin-settings-main .settings-mini-table th,
        .sadmin-settings-main .settings-mini-table td {
            padding: .4rem .7rem;
            border-bottom: 1px solid var(--default-border);
            font-size: .71rem;
            line-height: 1.15;
            vertical-align: middle;
        }
        .sadmin-settings-main .settings-mini-table th {
            background: var(--default-background);
            color: var(--default-text-color);
            font-size: .62rem;
            font-weight: 800;
            letter-spacing: .035em;
            text-transform: uppercase;
        }
        .sadmin-settings-main .settings-mini-table td:first-child {
            max-width: 12rem;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            font-weight: 600;
        }
        .sadmin-settings-main .settings-mini-table .status-pill {
            min-height: 1.2rem;
            padding: .15rem .45rem;
            border-radius: 999px;
            font-size: .61rem;
            font-weight: 800;
        }
        .sadmin-settings-main .table-edit-icon {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 1.55rem;
            height: 1.55rem;
            border-radius: .38rem;
            background: rgba(var(--primary-rgb), .08);
            color: rgb(var(--primary-rgb));
            text-decoration: none;
        }
        .sadmin-settings-main .footer { margin-inline: 0 !important; width: 100%; }
        @media (max-width: 1399.98px) {
            .sadmin-settings-main .settings-area-grid,
            .sadmin-settings-main .settings-table-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
        }
        @media (max-width: 767.98px) {
            .sadmin-settings-main .settings-command-card { align-items: stretch; flex-direction: column; }
            .sadmin-settings-main .settings-health { min-width: 0; }
            .sadmin-settings-main .settings-area-grid,
            .sadmin-settings-main .settings-table-grid { grid-template-columns: 1fr; }
        }
    </style>
<?php include __DIR__ . '/includes/footer.php'; ?>
