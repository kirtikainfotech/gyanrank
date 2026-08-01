<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/institute_erp.php';
start_secure_session();
institute_erp_ensure_tables();

$account = $_SESSION['institution_user'] ?? null;
if (!$account) {
    redirect('institute-login');
}

if (isset($_POST['logout'])) {
    unset($_SESSION['institution_user']);
    redirect('institute-login');
}

$message = '';
$error = '';
$plans = institute_erp_plan_rows(true);
$accountId = (int) ($account['id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['renewal_request'])) {
    if (!verify_csrf($_POST['csrf_token'] ?? null)) {
        $error = 'Security token expired. Please try again.';
    } else {
        try {
            $planId = (int) ($_POST['plan_id'] ?? 0);
            $billingCycle = (string) ($_POST['billing_cycle'] ?? 'yearly');
            if ($planId <= 0) {
                throw new RuntimeException('Please select a renewal plan.');
            }
            $requestId = institute_erp_create_renewal_request($accountId, $planId, $billingCycle, 0, 'Requested from institute dashboard.');
            $invoiceId = institute_erp_create_invoice($accountId, $planId, $billingCycle, $requestId);
            $message = 'Plan upgrade invoice created. Request #' . $requestId . ', Invoice #' . $invoiceId . '.';
        } catch (Throwable $e) {
            $error = $e->getMessage();
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['support_ticket'])) {
    if (!verify_csrf($_POST['csrf_token'] ?? null)) {
        $error = 'Security token expired. Please try again.';
    } else {
        try {
            $ticketId = institute_erp_create_support_ticket(
                $accountId,
                (string) ($_POST['ticket_category'] ?? 'technical'),
                (string) ($_POST['ticket_priority'] ?? 'normal'),
                (string) ($_POST['ticket_subject'] ?? ''),
                (string) ($_POST['ticket_message'] ?? '')
            );
            $message = 'Support ticket created. Ticket #' . $ticketId . '.';
        } catch (Throwable $e) {
            $error = $e->getMessage();
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['payment_reference_submit'])) {
    if (!verify_csrf($_POST['csrf_token'] ?? null)) {
        $error = 'Security token expired. Please try again.';
    } else {
        try {
            $invoiceId = (int) ($_POST['invoice_id'] ?? 0);
            $reference = trim((string) ($_POST['payment_reference'] ?? ''));
            if ($invoiceId <= 0 || $reference === '') {
                throw new RuntimeException('Enter valid payment reference.');
            }
            $status = 'pending';
            $note = 'Reference submitted by institute. Awaiting admin verification.';
            $stmt = db()->prepare("UPDATE institution_erp_invoices SET payment_status = ?, payment_reference = ?, payment_note = ?, updated_at = NOW() WHERE id = ? AND institution_account_id = ?");
            $stmt->bind_param('sssii', $status, $reference, $note, $invoiceId, $accountId);
            $stmt->execute();
            $message = 'Payment reference submitted for verification.';
        } catch (Throwable $e) {
            $error = $e->getMessage();
        }
    }
}

function institute_dashboard_table_count(mysqli $conn, string $dbName, string $table): int
{
    if (!preg_match('/^[A-Za-z0-9_]+$/', $dbName) || !preg_match('/^[A-Za-z0-9_]+$/', $table)) {
        return 0;
    }
    $stmt = $conn->prepare('SELECT COUNT(*) AS total FROM information_schema.TABLES WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ?');
    $stmt->bind_param('ss', $dbName, $table);
    $stmt->execute();
    if ((int) ($stmt->get_result()->fetch_assoc()['total'] ?? 0) <= 0) {
        return 0;
    }
    $result = $conn->query('SELECT COUNT(*) AS total FROM `' . $dbName . '`.`' . $table . '`');
    return (int) ($result->fetch_assoc()['total'] ?? 0);
}

function institute_dashboard_first_count(mysqli $conn, string $dbName, array $tables): int
{
    foreach ($tables as $table) {
        $count = institute_dashboard_table_count($conn, $dbName, (string) $table);
        if ($count > 0) {
            return $count;
        }
    }
    return 0;
}

$access = institute_erp_access_state($accountId);
$latestRenewal = institute_erp_latest_renewal_request($accountId);
$erpUrl = $access['erp_base_path'] !== '' ? app_url($access['erp_base_path']) : '';
$featureLabels = [
    'student' => 'Student',
    'admission' => 'Admission',
    'fees' => 'Fees',
    'attendance' => 'Attendance',
    'exams' => 'Exams',
    'online_exam' => 'Online Exam',
    'academics' => 'Academics',
    'lesson_plan' => 'Lesson Plan',
    'homework' => 'Homework',
    'communicate' => 'Communicate',
    'download_center' => 'Download Center',
    'library' => 'Library',
    'transport' => 'Transport',
    'hostel' => 'Hostel',
    'front_office' => 'Front Office',
    'human_resource' => 'Human Resource',
    'inventory' => 'Inventory',
    'income' => 'Income',
    'expenses' => 'Expenses',
    'reports' => 'Reports',
    'certificate' => 'Certificate',
    'alumni' => 'Alumni',
    'custom_branding' => 'Custom Branding',
    'priority_support' => 'Priority Support',
    'all_modules' => 'All Modules',
];

$daysLeft = '';
if ($access['expires_at'] !== '') {
    $daysLeft = (string) max(0, (int) floor((strtotime($access['expires_at']) - strtotime(date('Y-m-d'))) / 86400));
}

$stmt = db()->prepare('SELECT id, tenant_code, erp_db_name, erp_base_path, erp_status, setup_status, custom_domain, custom_domain_status, updated_at FROM institution_erp_tenants WHERE institution_account_id = ? ORDER BY id DESC LIMIT 1');
$stmt->bind_param('i', $accountId);
$stmt->execute();
$tenant = $stmt->get_result()->fetch_assoc() ?: [];
$tenantDb = (string) ($tenant['erp_db_name'] ?? '');
$tenantId = (int) ($tenant['id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['domain_request'])) {
    if (!verify_csrf($_POST['csrf_token'] ?? null)) {
        $error = 'Security token expired. Please try again.';
    } elseif ($tenantId <= 0) {
        $error = 'ERP tenant is not ready yet. Domain request can be submitted after ERP setup starts.';
    } else {
        $result = institute_erp_create_domain_request(
            $tenantId,
            (string) ($_POST['requested_domain'] ?? ''),
            $accountId,
            trim((string) ($_POST['domain_note'] ?? 'Requested from institute dashboard.'))
        );
        if ($result['ok']) {
            $message = $result['message'];
            $stmt = db()->prepare('SELECT id, tenant_code, erp_db_name, erp_base_path, erp_status, setup_status, custom_domain, custom_domain_status, updated_at FROM institution_erp_tenants WHERE id = ? LIMIT 1');
            $stmt->bind_param('i', $tenantId);
            $stmt->execute();
            $tenant = $stmt->get_result()->fetch_assoc() ?: $tenant;
        } else {
            $error = $result['message'];
        }
    }
}

$reporting = [
    'students' => 0,
    'staff' => 0,
    'fees' => 0,
    'attendance' => 0,
    'exams' => 0,
    'library' => 0,
    'transport' => 0,
    'hostel' => 0,
];
if ($tenantDb !== '') {
    try {
        $reportConn = new mysqli(DB_HOST, DB_USER, DB_PASS);
        $reportConn->set_charset('utf8mb4');
        $reporting['students'] = institute_dashboard_first_count($reportConn, $tenantDb, ['students']);
        $reporting['staff'] = institute_dashboard_first_count($reportConn, $tenantDb, ['staff', 'teachers']);
        $reporting['fees'] = institute_dashboard_first_count($reportConn, $tenantDb, ['student_fees_deposite', 'fee_deposit', 'fee_deposits']);
        $reporting['attendance'] = institute_dashboard_first_count($reportConn, $tenantDb, ['student_attendences', 'student_attendance']);
        $reporting['exams'] = institute_dashboard_first_count($reportConn, $tenantDb, ['exam_group', 'exams']);
        $reporting['library'] = institute_dashboard_first_count($reportConn, $tenantDb, ['books']);
        $reporting['transport'] = institute_dashboard_first_count($reportConn, $tenantDb, ['vehicles', 'transport_route']);
        $reporting['hostel'] = institute_dashboard_first_count($reportConn, $tenantDb, ['hostel_rooms', 'hostel']);
        $reportConn->close();
    } catch (Throwable $e) {
        $reporting = array_map(static fn() => 0, $reporting);
    }
}

$featureCount = count((array) $access['features']);
$readiness = 20;
if ($access['plan_name'] !== '') {
    $readiness += 20;
}
if (($tenant['setup_status'] ?? '') === 'installed') {
    $readiness += 25;
}
if ($access['can_write']) {
    $readiness += 25;
}
if ($featureCount > 0) {
    $readiness += 10;
}
$readiness = min(100, $readiness);
$statusTone = $access['can_write'] ? 'success' : ($access['is_expired'] ? 'danger' : 'warning');
$statusText = $access['can_write'] ? 'Live ERP Active' : ($access['is_expired'] ? 'Plan Expired' : 'Setup Pending');
$typeLabel = institution_type_options()[$account['institution_type']] ?? (string) $account['institution_type'];
$settings = all_settings();
$currencySymbol = (string) ($settings['currency_symbol'] ?? 'Rs');
$gatewayReady = (($settings['phonepe_enabled'] ?? '0') === '1' && (($settings['phonepe_client_id'] ?? '') !== '' || ($settings['phonepe_merchant_id'] ?? '') !== ''));
$stmt = db()->prepare("SELECT i.*, p.plan_name FROM institution_erp_invoices i LEFT JOIN institution_erp_plans p ON p.id = i.plan_id WHERE i.institution_account_id = ? ORDER BY i.id DESC LIMIT 8");
$stmt->bind_param('i', $accountId);
$stmt->execute();
$invoiceRows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt = db()->prepare('SELECT * FROM institution_erp_support_tickets WHERE institution_account_id = ? ORDER BY id DESC LIMIT 6');
$stmt->bind_param('i', $accountId);
$stmt->execute();
$ticketRows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt = db()->prepare('SELECT * FROM institution_erp_domain_requests WHERE institution_account_id = ? ORDER BY id DESC LIMIT 3');
$stmt->bind_param('i', $accountId);
$stmt->execute();
$domainRows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$latestDomain = $domainRows[0] ?? null;
$activeInstitutePage = basename((string) ($_SERVER['SCRIPT_NAME'] ?? 'institute-dashboard.php'), '.php');
$pageHeadings = [
    'institute-dashboard' => ['Dashboard', 'Overview, access status, setup progress, and quick ERP actions.'],
    'institute-plan-upgrade' => ['Plan Upgrade', 'Generate renewal invoices and submit upgrade requests.'],
    'institute-billing' => ['Invoices & Bills', 'Track ERP invoices, payment references, and verification status.'],
    'institute-domain' => ['Domain Setup', 'Request and monitor custom domain mapping for your ERP.'],
    'institute-support' => ['Support Tickets', 'Raise and track support requests for ERP, billing, and domain setup.'],
    'institute-reports' => ['Reports', 'Live reporting snapshot from your ERP tenant database.'],
];
$pageHeading = $pageHeadings[$activeInstitutePage] ?? $pageHeadings['institute-dashboard'];
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Institute Dashboard - <?= h(app_name()); ?></title>
    <link rel="icon" href="<?= h(app_url('assets/applogo.png')); ?>">
    <link rel="stylesheet" href="<?= h(app_url('assets/css/login.css')); ?>">
</head>
<body class="legal-page institution-app-page">
    <main class="institution-app-shell">
        <aside class="institution-app-sidebar">
            <a class="institution-app-logo" href="<?= h(app_url('index')); ?>"><img src="<?= h(app_url('assets/grlogo.png')); ?>" alt="GYAN RANK"></a>
            <div class="institution-app-user">
                <b><?= h(substr((string) ($account['institution_name'] ?? 'I'), 0, 1)); ?></b>
                <span><?= h($account['institution_name'] ?? 'Institute'); ?></span>
                <small><?= h($typeLabel); ?></small>
            </div>
            <nav>
                <a class="<?= $activeInstitutePage === 'institute-dashboard' ? 'active' : ''; ?>" href="<?= h(app_url('institute-dashboard')); ?>">Dashboard</a>
                <a href="<?= h($erpUrl !== '' ? $erpUrl : '#erp-access'); ?>">Open ERP</a>
                <a class="<?= $activeInstitutePage === 'institute-plan-upgrade' ? 'active' : ''; ?>" href="<?= h(app_url('institute-plan-upgrade')); ?>">Plan Upgrade</a>
                <a class="<?= $activeInstitutePage === 'institute-billing' ? 'active' : ''; ?>" href="<?= h(app_url('institute-billing')); ?>">Invoices & Bills</a>
                <a class="<?= $activeInstitutePage === 'institute-domain' ? 'active' : ''; ?>" href="<?= h(app_url('institute-domain')); ?>">Domain Setup</a>
                <a class="<?= $activeInstitutePage === 'institute-support' ? 'active' : ''; ?>" href="<?= h(app_url('institute-support')); ?>">Support Tickets</a>
                <a class="<?= $activeInstitutePage === 'institute-reports' ? 'active' : ''; ?>" href="<?= h(app_url('institute-reports')); ?>">Reports</a>
            </nav>
            <form method="post"><button name="logout" value="1" type="submit">Logout</button></form>
        </aside>

        <section class="institution-app-main">
            <header class="institution-app-topbar">
                <div class="institution-top-title">
                    <b><?= h(strtoupper(substr((string) ($account['institution_name'] ?? 'I'), 0, 1))); ?></b>
                    <div>
                        <span>Institute Panel</span>
                        <strong><?= h($account['institution_name'] ?? 'Institute'); ?></strong>
                        <small><?= h(($access['plan_name'] !== '' ? $access['plan_name'] : 'ERP plan pending') . ' / ' . $typeLabel); ?></small>
                    </div>
                </div>
                <div class="institution-top-actions">
                    <strong class="institution-status-pill <?= h($statusTone); ?>"><?= h($statusText); ?></strong>
                    <?php if ($erpUrl !== ''): ?><a href="<?= h($erpUrl); ?>">Open ERP</a><?php endif; ?>
                </div>
            </header>

            <?php if ($message): ?><div class="form-alert form-alert-success"><?= h($message); ?></div><?php endif; ?>
            <?php if ($error): ?><div class="form-alert form-alert-error"><?= h($error); ?></div><?php endif; ?>

            <section class="institution-page-heading">
                <div>
                    <span>Institute Workspace</span>
                    <h1><?= h($pageHeading[0]); ?></h1>
                    <p><?= h($pageHeading[1]); ?></p>
                </div>
                <strong class="institution-status-pill <?= h($statusTone); ?>"><?= h($statusText); ?></strong>
            </section>

            <?php if ($activeInstitutePage === 'institute-dashboard'): ?>
            <section id="overview" class="institution-control-hero">
                <div>
                    <span>Command Center</span>
                    <h1>Manage ERP access, billing, and support from one workspace.</h1>
                    <p>Track subscription status, setup progress, ERP reporting, plan upgrades, payment references, invoices, and support requests in one professional dashboard.</p>
                </div>
                <div class="institution-hero-actions">
                    <strong class="institution-status-pill <?= h($statusTone); ?>"><?= h($statusText); ?></strong>
                    <?php if ($erpUrl !== ''): ?><a href="<?= h($erpUrl); ?>">Open ERP Panel</a><?php endif; ?>
                </div>
            </section>

            <div class="institution-kpi-grid">
                <article><span>Current Plan</span><b><?= h($access['plan_name'] !== '' ? $access['plan_name'] : 'Not Assigned'); ?></b><small><?= h($access['expires_at'] !== '' ? 'Valid till ' . $access['expires_at'] : 'Activation required'); ?></small></article>
                <article><span>Days Left</span><b><?= h($daysLeft !== '' ? $daysLeft : '0'); ?></b><small><?= h($access['is_expired'] ? 'Renewal required' : 'Active subscription window'); ?></small></article>
                <article><span>ERP Setup</span><b><?= h(ucwords(str_replace('_', ' ', (string) $access['setup_status']))); ?></b><small><?= h((string) ($tenant['tenant_code'] ?? 'Tenant not generated')); ?></small></article>
                <article><span>Readiness</span><b><?= h((string) $readiness); ?>%</b><small><?= h($featureCount . ' modules included'); ?></small></article>
            </div>
            <?php endif; ?>

            <?php if ($activeInstitutePage === 'institute-dashboard'): ?>
            <div class="institution-dashboard-layout">
                <div class="institution-main-column">
                    <section id="erp-access" class="institution-panel-card">
                        <div class="institution-card-head">
                            <div><span>ERP Summary</span><h2>Account & Access</h2></div>
                            <strong class="institution-status-pill <?= h($statusTone); ?>"><?= h($statusText); ?></strong>
                        </div>
                        <div class="institution-info-grid">
                            <div><b>Institution Type</b><span><?= h($typeLabel); ?></span></div>
                            <div><b>Contact Person</b><span><?= h($account['contact_name'] ?? ''); ?></span></div>
                            <div><b>Email</b><span><?= h($account['email'] ?? ''); ?></span></div>
                            <div><b>Mobile</b><span><?= h($account['mobile'] ?? ''); ?></span></div>
                            <div><b>ERP Database</b><span><?= h($tenantDb !== '' ? $tenantDb : 'Not created yet'); ?></span></div>
                            <div><b>ERP URL</b><span><?= h($erpUrl !== '' ? $erpUrl : 'URL will be available after setup'); ?></span></div>
                        </div>
                    </section>
                </div>

                <aside class="institution-side-column">
                    <section class="institution-panel-card institution-progress-card">
                        <span>Setup Progress</span>
                        <h2><?= h((string) $readiness); ?>% Ready</h2>
                        <div class="institution-progress-bar"><i style="width: <?= h((string) $readiness); ?>%;"></i></div>
                        <ul>
                            <li class="<?= $access['plan_name'] !== '' ? 'done' : ''; ?>">Plan assigned</li>
                            <li class="<?= (($tenant['setup_status'] ?? '') === 'installed') ? 'done' : ''; ?>">ERP installed</li>
                            <li class="<?= $access['can_write'] ? 'done' : ''; ?>">Write access active</li>
                            <li class="<?= $featureCount > 0 ? 'done' : ''; ?>">Modules configured</li>
                        </ul>
                    </section>

                    <section class="institution-panel-card institution-action-card">
                        <span>Quick Action</span>
                        <?php if ($access['can_write'] && $erpUrl !== ''): ?>
                            <h2>Your ERP is ready</h2>
                            <p>Open the ERP panel to manage admissions, fees, attendance, examinations, and reports.</p>
                            <a class="institution-primary-action" href="<?= h($erpUrl); ?>">Open ERP Panel</a>
                        <?php elseif ($access['is_expired']): ?>
                            <h2>Plan expired</h2>
                            <p>New entries are locked. Submit a renewal request to restore write access.</p>
                        <?php elseif ($access['can_report']): ?>
                            <h2>Setup queued</h2>
                            <p>The implementation team will complete tenant installation and configuration. Progress will update here.</p>
                        <?php else: ?>
                            <h2>ERP activation needed</h2>
                            <p>ERP onboarding will begin after a plan is assigned to this account.</p>
                        <?php endif; ?>
                    </section>

                    <section class="institution-panel-card">
                        <span>Renewal Status</span>
                        <h2><?= h($latestRenewal ? ucfirst((string) $latestRenewal['status']) . ' #' . $latestRenewal['id'] : 'No Request'); ?></h2>
                        <p><?= h($latestRenewal && !empty($latestRenewal['plan_name']) ? 'Requested plan: ' . $latestRenewal['plan_name'] : 'Submit a renewal request to track approval and payment status here.'); ?></p>
                    </section>
                </aside>
            </div>
            <?php endif; ?>

            <?php if (!$access['can_write'] && $access['is_expired']): ?>
                <div class="form-alert form-alert-error">Plan expired. New entries are locked; reporting/read-only access remains available until renewal.</div>
            <?php elseif (!$access['can_write'] && $access['can_report']): ?>
                <div class="form-alert form-alert-success">ERP setup is queued. The implementation team will complete installation for this institute.</div>
            <?php elseif (!$access['can_write']): ?>
                <div class="form-alert form-alert-error">No active ERP plan assigned. Contact admin to activate ERP.</div>
            <?php endif; ?>

            <?php if ($activeInstitutePage === 'institute-plan-upgrade' && $plans): ?>
                <section id="upgrade" class="institution-panel-card institution-renewal-panel">
                    <div class="institution-card-head">
                        <div><span>Subscription</span><h2>Plan Upgrade & Payment Request</h2></div>
                        <small><?= $gatewayReady ? 'PhonePe configured' : 'Manual payment verification active'; ?></small>
                    </div>
                    <form method="post" class="institution-renewal-form">
                        <input type="hidden" name="csrf_token" value="<?= h(csrf_token()); ?>">
                        <input type="hidden" name="renewal_request" value="1">
                        <label>Plan
                            <select name="plan_id" required>
                                <option value="">Select plan</option>
                                <?php foreach ($plans as $plan): ?>
                                    <option value="<?= (int) $plan['id']; ?>"><?= h($plan['plan_name'] . ' | Monthly Rs ' . number_format((float) $plan['monthly_price'], 2) . ' | Yearly Rs ' . number_format((float) $plan['yearly_price'], 2)); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                        <label>Billing
                            <select name="billing_cycle">
                                <option value="yearly">Yearly</option>
                                <option value="monthly">Monthly</option>
                            </select>
                        </label>
                        <button type="submit">Generate Invoice</button>
                    </form>
                    <div class="institution-payment-note">
                        <b>Payment Processing</b>
                        <span>Select a plan to generate an invoice. Online checkout can be enabled after live gateway credentials are configured. Until then, submit the transaction reference for admin verification and activation.</span>
                    </div>
                </section>
            <?php endif; ?>

            <?php if ($activeInstitutePage === 'institute-billing'): ?>
            <section id="billing" class="institution-panel-card institution-billing-panel">
                <div class="institution-card-head">
                    <div><span>Billing</span><h2>Invoices & Bills</h2></div>
                    <small><?= h(count($invoiceRows) . ' recent'); ?></small>
                </div>
                <div class="institution-table-wrap">
                    <table class="institution-mini-table">
                        <thead><tr><th>Invoice</th><th>Plan</th><th>Amount</th><th>Status</th><th>Reference</th></tr></thead>
                        <tbody>
                        <?php if (!$invoiceRows): ?>
                            <tr><td colspan="5">No invoice generated yet.</td></tr>
                        <?php endif; ?>
                        <?php foreach ($invoiceRows as $invoice): ?>
                            <tr>
                                <td><strong><?= h($invoice['invoice_no']); ?></strong><small><?= h((string) $invoice['created_at']); ?></small></td>
                                <td><?= h((string) ($invoice['plan_name'] ?? 'ERP Plan')); ?><small><?= h(ucfirst((string) $invoice['billing_cycle'])); ?></small></td>
                                <td><?= h($currencySymbol . ' ' . number_format((float) $invoice['total_amount'], 2)); ?></td>
                                <td><span class="institution-status-pill <?= ($invoice['payment_status'] ?? '') === 'paid' ? 'success' : 'warning'; ?>"><?= h(ucfirst((string) $invoice['payment_status'])); ?></span></td>
                                <td>
                                    <?php if (($invoice['payment_status'] ?? '') !== 'paid'): ?>
                                        <form class="institution-inline-payment" method="post">
                                            <input type="hidden" name="csrf_token" value="<?= h(csrf_token()); ?>">
                                            <input type="hidden" name="payment_reference_submit" value="1">
                                            <input type="hidden" name="invoice_id" value="<?= (int) $invoice['id']; ?>">
                                            <input name="payment_reference" placeholder="UTR / Txn ID" value="<?= h((string) ($invoice['payment_reference'] ?? '')); ?>">
                                            <button type="submit">Submit</button>
                                        </form>
                                    <?php else: ?>
                                        <?= h((string) ($invoice['payment_reference'] ?? 'Paid')); ?>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </section>
            <?php endif; ?>

            <?php if ($activeInstitutePage === 'institute-domain'): ?>
            <section id="domain" class="institution-panel-card institution-domain-panel">
                <div class="institution-card-head">
                    <div><span>Custom Domain</span><h2>Run ERP on your own domain</h2></div>
                    <strong class="institution-status-pill <?= !empty($tenant['custom_domain']) ? 'success' : 'warning'; ?>">
                        <?= h(!empty($tenant['custom_domain_status']) ? ucwords(str_replace('_', ' ', (string) $tenant['custom_domain_status'])) : 'Not Requested'); ?>
                    </strong>
                </div>
                <div class="institution-domain-layout">
                    <div class="institution-domain-summary">
                        <b><?= h(!empty($tenant['custom_domain']) ? $tenant['custom_domain'] : 'No custom domain connected'); ?></b>
                        <span>Submit a domain request such as erp.yourschool.com. The GyanRank team will verify DNS, configure the server, issue SSL, and map your ERP safely.</span>
                        <?php if ($latestDomain): ?>
                            <small>Latest request #<?= (int) $latestDomain['id']; ?>: <?= h(ucwords(str_replace('_', ' ', (string) $latestDomain['status']))); ?><?= !empty($latestDomain['dns_target']) ? ' / DNS: ' . h((string) $latestDomain['dns_target']) : ''; ?></small>
                        <?php endif; ?>
                    </div>
                    <form method="post" class="institution-domain-form">
                        <input type="hidden" name="csrf_token" value="<?= h(csrf_token()); ?>">
                        <input type="hidden" name="domain_request" value="1">
                        <label>Requested Domain
                            <input name="requested_domain" placeholder="erp.yourdomain.com" value="<?= h((string) ($tenant['custom_domain'] ?? '')); ?>" required>
                        </label>
                        <label>Note
                            <input name="domain_note" placeholder="Preferred domain or DNS details">
                        </label>
                        <button type="submit">Request Domain Setup</button>
                    </form>
                </div>
            </section>
            <?php endif; ?>

            <?php if ($activeInstitutePage === 'institute-reports'): ?>
            <section id="reports" class="institution-panel-card">
                <div class="institution-card-head">
                    <div><span>Reporting Snapshot</span><h2>Live ERP Data</h2></div>
                    <small><?= h($tenantDb !== '' ? 'From tenant database' : 'Available after ERP install'); ?></small>
                </div>
                <div class="institution-report-grid">
                    <div><b><?= h((string) $reporting['students']); ?></b><span>Students</span></div>
                    <div><b><?= h((string) $reporting['staff']); ?></b><span>Staff</span></div>
                    <div><b><?= h((string) $reporting['fees']); ?></b><span>Fee Records</span></div>
                    <div><b><?= h((string) $reporting['attendance']); ?></b><span>Attendance</span></div>
                    <div><b><?= h((string) $reporting['exams']); ?></b><span>Exams</span></div>
                    <div><b><?= h((string) $reporting['library']); ?></b><span>Library</span></div>
                    <div><b><?= h((string) $reporting['transport']); ?></b><span>Transport</span></div>
                    <div><b><?= h((string) $reporting['hostel']); ?></b><span>Hostel</span></div>
                </div>
            </section>

            <?php if (!empty($access['features'])): ?>
                <section class="institution-panel-card">
                    <div class="institution-card-head">
                        <div><span>Plan Modules</span><h2>Included ERP Features</h2></div>
                        <small><?= h((string) $featureCount); ?> active</small>
                    </div>
                    <div class="institution-module-cloud">
                        <?php foreach ((array) $access['features'] as $feature): ?>
                            <span><?= h($featureLabels[$feature] ?? ucwords(str_replace('_', ' ', (string) $feature))); ?></span>
                        <?php endforeach; ?>
                    </div>
                </section>
            <?php endif; ?>
            <?php endif; ?>

            <?php if ($activeInstitutePage === 'institute-support'): ?>
            <section id="support" class="institution-panel-card institution-support-panel">
                <div class="institution-card-head">
                    <div><span>Helpdesk</span><h2>Support Tickets</h2></div>
                    <small>Technical, billing, setup, domain</small>
                </div>
                <form method="post" class="institution-ticket-form">
                    <input type="hidden" name="csrf_token" value="<?= h(csrf_token()); ?>">
                    <input type="hidden" name="support_ticket" value="1">
                    <label>Category<select name="ticket_category"><option value="technical">Technical</option><option value="billing">Billing</option><option value="erp_setup">ERP Setup</option><option value="domain">Domain</option><option value="training">Training</option><option value="other">Other</option></select></label>
                    <label>Priority<select name="ticket_priority"><option value="normal">Normal</option><option value="high">High</option><option value="urgent">Urgent</option><option value="low">Low</option></select></label>
                    <label class="span-2">Subject<input name="ticket_subject" placeholder="Example: ERP login issue" required></label>
                    <label class="span-2">Message<textarea name="ticket_message" rows="3" placeholder="Issue details"></textarea></label>
                    <button type="submit">Create Ticket</button>
                </form>
                <div class="institution-ticket-list">
                    <?php if (!$ticketRows): ?><p>No support tickets yet.</p><?php endif; ?>
                    <?php foreach ($ticketRows as $ticket): ?>
                        <div><b><?= h($ticket['ticket_no']); ?></b><span><?= h($ticket['subject']); ?></span><small><?= h(ucfirst((string) $ticket['status']) . ' | ' . ucfirst((string) $ticket['priority'])); ?></small></div>
                    <?php endforeach; ?>
                </div>
            </section>
            <?php endif; ?>
        </section>
    </main>
</body>
</html>
