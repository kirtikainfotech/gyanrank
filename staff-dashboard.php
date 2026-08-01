<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/sadmin_staff.php';
require_once __DIR__ . '/includes/institute_erp.php';

$user = require_login();
if (in_array((string) ($user['role'] ?? ''), ['student', 'instructor'], true)) {
    redirect(dashboard_path_for_role((string) $user['role']));
}

sadmin_staff_ensure_tables();
$userId = (int) ($user['id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['lead_update'])) {
    if (!verify_csrf($_POST['csrf_token'] ?? null)) {
        $_SESSION['staff_dash_error'] = 'Security token expired.';
    } else {
        try {
            sadmin_staff_update_lead(
                (int) ($_POST['lead_id'] ?? 0),
                $userId,
                (string) ($_POST['status'] ?? 'follow_up'),
                trim((string) ($_POST['next_followup'] ?? '')) ?: null,
                substr(trim((string) ($_POST['note'] ?? 'Updated from staff dashboard.')), 0, 1000),
                true
            );
            $_SESSION['staff_dash_message'] = 'Lead updated.';
        } catch (Throwable $e) {
            $_SESSION['staff_dash_error'] = $e->getMessage();
        }
    }
    redirect('staff-dashboard#leads');
}

$stmt = db()->prepare("SELECT a.*, i.institution_name, i.institution_type, i.contact_name, i.mobile, i.email,
        t.erp_base_path, t.setup_status, t.erp_status, t.custom_domain, t.custom_domain_status
    FROM sadmin_staff_institute_assignments a
    INNER JOIN institution_accounts i ON i.id = a.institution_account_id
    LEFT JOIN institution_erp_tenants t ON t.institution_account_id = i.id
    WHERE a.staff_user_id = ?
    ORDER BY a.id DESC");
$stmt->bind_param('i', $userId);
$stmt->execute();
$assignments = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$stmt = db()->prepare("SELECT * FROM sadmin_sales_leads WHERE owner_user_id = ? ORDER BY id DESC LIMIT 50");
$stmt->bind_param('i', $userId);
$stmt->execute();
$leads = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$paidLeads = count(array_filter($leads, static fn(array $row): bool => (string) $row['status'] === 'paid'));
$paidAmount = array_sum(array_map(static fn(array $row): float => (float) $row['paid_amount'], $leads));
$openLeads = count(array_filter($leads, static fn(array $row): bool => !in_array((string) $row['status'], ['paid', 'lost'], true)));
$leadActivities = sadmin_staff_lead_activity_rows($userId, true);
$assignedIds = array_values(array_unique(array_map(static fn(array $row): int => (int) $row['institution_account_id'], $assignments)));
$idList = $assignedIds ? implode(',', array_map('intval', $assignedIds)) : '0';
$supportRows = [];
$invoiceRows = [];
$domainRows = [];
if ($assignedIds) {
    $result = db()->query("SELECT t.*, i.institution_name
        FROM institution_erp_support_tickets t
        INNER JOIN institution_accounts i ON i.id = t.institution_account_id
        WHERE t.institution_account_id IN ($idList)
        ORDER BY FIELD(t.status, 'open', 'in_progress', 'waiting_institute', 'resolved', 'closed'), t.id DESC
        LIMIT 20");
    $supportRows = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];

    $result = db()->query("SELECT inv.*, p.plan_name, i.institution_name
        FROM institution_erp_invoices inv
        LEFT JOIN institution_erp_plans p ON p.id = inv.plan_id
        INNER JOIN institution_accounts i ON i.id = inv.institution_account_id
        WHERE inv.institution_account_id IN ($idList)
        ORDER BY inv.id DESC
        LIMIT 20");
    $invoiceRows = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];

    $result = db()->query("SELECT d.*, i.institution_name
        FROM institution_erp_domain_requests d
        INNER JOIN institution_accounts i ON i.id = d.institution_account_id
        WHERE d.institution_account_id IN ($idList)
        ORDER BY FIELD(d.status, 'pending', 'dns_pending', 'mapped', 'rejected', 'cancelled'), d.id DESC
        LIMIT 20");
    $domainRows = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
}
$canSupport = current_user_can('support.manage', $user);
$canBilling = current_user_can('invoices.manage', $user) || current_user_can('fees.manage', $user);
$canLeads = current_user_can('leads.manage', $user) || current_user_can('calling.manage', $user);
$message = $_SESSION['staff_dash_message'] ?? '';
$error = $_SESSION['staff_dash_error'] ?? '';
unset($_SESSION['staff_dash_message'], $_SESSION['staff_dash_error']);
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Staff Dashboard - <?= h(app_name()); ?></title>
    <link rel="icon" href="<?= h(app_url('assets/applogo.png')); ?>">
    <link rel="stylesheet" href="<?= h(app_url('assets/css/login.css')); ?>">
</head>
<body class="legal-page institution-app-page">
    <main class="institution-app-shell">
        <aside class="institution-app-sidebar">
            <a class="institution-app-logo" href="<?= h(app_url('staff-dashboard')); ?>"><img src="<?= h(app_url('assets/grlogo.png')); ?>" alt="GYAN RANK"></a>
            <div class="institution-app-user">
                <b><?= h(strtoupper(substr((string) ($user['full_name'] ?? 'S'), 0, 1))); ?></b>
                <span><?= h((string) ($user['full_name'] ?? 'Staff')); ?></span>
                <small><?= h(ucwords(str_replace('-', ' ', (string) ($user['role'] ?? 'staff')))); ?></small>
            </div>
            <nav>
                <a class="active" href="#overview">Dashboard</a>
                <a href="#accounts">Assigned Accounts</a>
                <?php if ($canLeads): ?><a href="#leads">My Leads</a><?php endif; ?>
                <?php if ($canSupport): ?><a href="#support">Support Queue</a><?php endif; ?>
                <?php if ($canBilling): ?><a href="#billing">Billing Queue</a><?php endif; ?>
                <?php if ($canSupport): ?><a href="#domains">Domain Queue</a><?php endif; ?>
                <a href="<?= h(app_url('logout')); ?>">Logout</a>
            </nav>
        </aside>
        <section class="institution-app-main">
            <header class="institution-app-topbar">
                <div class="institution-top-title">
                    <b><?= h(strtoupper(substr((string) ($user['full_name'] ?? 'S'), 0, 1))); ?></b>
                    <div>
                        <span>Staff Panel</span>
                        <strong><?= h((string) ($user['full_name'] ?? 'Staff')); ?></strong>
                        <small><?= h(ucwords(str_replace('-', ' ', (string) ($user['role'] ?? 'staff')))); ?></small>
                    </div>
                </div>
                <div class="institution-top-actions"><strong class="institution-status-pill success">Active</strong></div>
            </header>
            <?php if ($message): ?><div class="form-alert form-alert-success"><?= h($message); ?></div><?php endif; ?>
            <?php if ($error): ?><div class="form-alert form-alert-error"><?= h($error); ?></div><?php endif; ?>

            <section id="overview" class="institution-page-heading">
                <div>
                    <span>Work Summary</span>
                    <h1>Assigned operations dashboard</h1>
                    <p>Track institute support, calling, sales and billing work assigned by Super Admin.</p>
                </div>
            </section>

            <div class="institution-kpi-grid">
                <article><span>Assigned Accounts</span><b><?= count($assignments); ?></b><small>Institutes under your care</small></article>
                <article><span>Open Leads</span><b><?= $openLeads; ?></b><small>Follow-up required</small></article>
                <article><span>Paid Leads</span><b><?= $paidLeads; ?></b><small>Converted business</small></article>
                <article><span>Revenue</span><b>Rs <?= h(number_format($paidAmount, 0)); ?></b><small>Collected against your leads</small></article>
            </div>

            <section id="accounts" class="institution-panel-card">
                <div class="institution-card-head"><div><span>Assigned Institutes</span><h2>Support & account ownership</h2></div></div>
                <div class="institution-table-wrap">
                    <table class="institution-mini-table">
                        <thead><tr><th>Institute</th><th>Type</th><th>Work</th><th>ERP</th><th>Contact</th></tr></thead>
                        <tbody>
                        <?php if (!$assignments): ?><tr><td colspan="5">No institute assigned yet.</td></tr><?php endif; ?>
                        <?php foreach ($assignments as $row): ?>
                            <tr>
                                <td><strong><?= h($row['institution_name']); ?></strong><small><?= h((string) ($row['custom_domain'] ?: $row['erp_base_path'] ?: 'ERP pending')); ?></small></td>
                                <td><?= h((string) $row['institution_type']); ?></td>
                                <td><?= h(ucwords(str_replace('_', ' ', (string) $row['assignment_role']))); ?></td>
                                <td><?= h(ucwords(str_replace('_', ' ', (string) ($row['setup_status'] ?: 'queued')))); ?></td>
                                <td><?= h((string) ($row['contact_name'] ?: $row['mobile'] ?: $row['email'])); ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </section>

            <?php if ($canLeads): ?>
            <section id="leads" class="institution-panel-card">
                <div class="institution-card-head"><div><span>My Pipeline</span><h2>Leads & payments</h2></div></div>
                <div class="institution-table-wrap">
                    <table class="institution-mini-table">
                        <thead><tr><th>Lead</th><th>Contact</th><th>Amount</th><th>Status</th><th>Update</th></tr></thead>
                        <tbody>
                        <?php if (!$leads): ?><tr><td colspan="5">No leads assigned yet.</td></tr><?php endif; ?>
                        <?php foreach ($leads as $lead): ?>
                            <tr>
                                <td><strong><?= h($lead['lead_name']); ?></strong><small><?= h((string) $lead['institution_type']); ?></small></td>
                                <td><?= h((string) ($lead['contact_person'] ?: $lead['phone'] ?: $lead['email'])); ?></td>
                                <td>Rs <?= h(number_format((float) $lead['paid_amount'], 2)); ?><small>Expected Rs <?= h(number_format((float) $lead['expected_amount'], 2)); ?></small></td>
                                <td><span class="institution-status-pill <?= $lead['status'] === 'paid' ? 'success' : 'warning'; ?>"><?= h(ucwords(str_replace('_', ' ', (string) $lead['status']))); ?></span><small><?= h((string) ($lead['next_followup'] ?: 'No follow-up')); ?></small></td>
                                <td>
                                    <form method="post" class="institution-inline-payment staff-lead-update">
                                        <input type="hidden" name="csrf_token" value="<?= h(csrf_token()); ?>">
                                        <input type="hidden" name="lead_update" value="1">
                                        <input type="hidden" name="lead_id" value="<?= (int) $lead['id']; ?>">
                                        <select name="status"><option value="follow_up">Follow Up</option><option value="demo_done">Demo Done</option><option value="proposal">Proposal</option><option value="paid">Paid</option><option value="unpaid">Unpaid</option><option value="lost">Lost</option></select>
                                        <input type="date" name="next_followup">
                                        <input name="note" placeholder="Note">
                                        <button type="submit">Save</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </section>
            <?php if ($leadActivities): ?>
            <section class="institution-panel-card">
                <div class="institution-card-head"><div><span>Lead History</span><h2>Recent follow-up activity</h2></div></div>
                <div class="institution-ticket-list">
                    <?php foreach ($leadActivities as $activity): ?>
                        <div><b><?= h($activity['lead_name']); ?></b><span><?= h(ucwords(str_replace('_', ' ', (string) $activity['old_status'])) . ' to ' . ucwords(str_replace('_', ' ', (string) $activity['new_status']))); ?></span><small><?= h((string) $activity['created_at']); ?></small></div>
                    <?php endforeach; ?>
                </div>
            </section>
            <?php endif; ?>
            <?php endif; ?>

            <?php if ($canSupport): ?>
            <section id="support" class="institution-panel-card">
                <div class="institution-card-head"><div><span>Support Queue</span><h2>Assigned institute tickets</h2></div></div>
                <div class="institution-table-wrap">
                    <table class="institution-mini-table">
                        <thead><tr><th>Ticket</th><th>Institute</th><th>Category</th><th>Priority</th><th>Status</th></tr></thead>
                        <tbody>
                        <?php if (!$supportRows): ?><tr><td colspan="5">No assigned support tickets.</td></tr><?php endif; ?>
                        <?php foreach ($supportRows as $ticket): ?>
                            <tr>
                                <td><strong><?= h($ticket['ticket_no']); ?></strong><small><?= h((string) $ticket['subject']); ?></small></td>
                                <td><?= h($ticket['institution_name']); ?></td>
                                <td><?= h(ucwords(str_replace('_', ' ', (string) $ticket['category']))); ?></td>
                                <td><?= h(ucfirst((string) $ticket['priority'])); ?></td>
                                <td><span class="institution-status-pill warning"><?= h(ucwords(str_replace('_', ' ', (string) $ticket['status']))); ?></span></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </section>

            <section id="domains" class="institution-panel-card">
                <div class="institution-card-head"><div><span>Domain Queue</span><h2>Custom domain requests</h2></div></div>
                <div class="institution-table-wrap">
                    <table class="institution-mini-table">
                        <thead><tr><th>Domain</th><th>Institute</th><th>DNS</th><th>SSL</th><th>Status</th></tr></thead>
                        <tbody>
                        <?php if (!$domainRows): ?><tr><td colspan="5">No assigned domain requests.</td></tr><?php endif; ?>
                        <?php foreach ($domainRows as $domain): ?>
                            <tr>
                                <td><strong><?= h($domain['normalized_domain']); ?></strong><small><?= h($domain['requested_domain']); ?></small></td>
                                <td><?= h($domain['institution_name']); ?></td>
                                <td><?= h((string) ($domain['dns_target'] ?: 'Pending')); ?></td>
                                <td><?= h(ucwords(str_replace('_', ' ', (string) $domain['ssl_status']))); ?></td>
                                <td><span class="institution-status-pill warning"><?= h(ucwords(str_replace('_', ' ', (string) $domain['status']))); ?></span></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </section>
            <?php endif; ?>

            <?php if ($canBilling): ?>
            <section id="billing" class="institution-panel-card">
                <div class="institution-card-head"><div><span>Billing Queue</span><h2>Assigned institute invoices</h2></div></div>
                <div class="institution-table-wrap">
                    <table class="institution-mini-table">
                        <thead><tr><th>Invoice</th><th>Institute</th><th>Plan</th><th>Amount</th><th>Status</th></tr></thead>
                        <tbody>
                        <?php if (!$invoiceRows): ?><tr><td colspan="5">No assigned invoices.</td></tr><?php endif; ?>
                        <?php foreach ($invoiceRows as $invoice): ?>
                            <tr>
                                <td><strong><?= h($invoice['invoice_no']); ?></strong><small><?= h((string) $invoice['payment_reference']); ?></small></td>
                                <td><?= h($invoice['institution_name']); ?></td>
                                <td><?= h((string) ($invoice['plan_name'] ?: 'ERP Plan')); ?></td>
                                <td>Rs <?= h(number_format((float) $invoice['total_amount'], 2)); ?></td>
                                <td><span class="institution-status-pill <?= $invoice['payment_status'] === 'paid' ? 'success' : 'warning'; ?>"><?= h(ucfirst((string) $invoice['payment_status'])); ?></span></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </section>
            <?php endif; ?>
        </section>
    </main>
</body>
</html>
