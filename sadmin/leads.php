<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/sadmin_staff.php';

$user = require_login('superadmin');
sadmin_staff_ensure_tables();
$message = $_SESSION['sadmin_lead_message'] ?? '';
$error = $_SESSION['sadmin_lead_error'] ?? '';
unset($_SESSION['sadmin_lead_message'], $_SESSION['sadmin_lead_error']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['csrf_token'] ?? null)) {
        $_SESSION['sadmin_lead_error'] = 'Security token expired.';
    } else {
        try {
            if (isset($_POST['lead_update'])) {
                sadmin_staff_update_lead(
                    (int) ($_POST['lead_id'] ?? 0),
                    (int) ($user['id'] ?? 0),
                    (string) ($_POST['status'] ?? 'follow_up'),
                    trim((string) ($_POST['next_followup'] ?? '')) ?: null,
                    substr(trim((string) ($_POST['note'] ?? 'Updated by Super Admin.')), 0, 1000),
                    false
                );
                $_SESSION['sadmin_lead_message'] = 'Lead updated.';
                redirect('sadmin/leads');
            }

            $ownerId = (int) ($_POST['owner_user_id'] ?? 0) ?: null;
            $institutionId = (int) ($_POST['institution_account_id'] ?? 0) ?: null;
            $leadName = substr(trim((string) ($_POST['lead_name'] ?? '')), 0, 160);
            if ($leadName === '') {
                throw new RuntimeException('Lead name is required.');
            }
            $contact = substr(trim((string) ($_POST['contact_person'] ?? '')), 0, 120);
            $phone = substr(trim((string) ($_POST['phone'] ?? '')), 0, 30);
            $email = substr(trim((string) ($_POST['email'] ?? '')), 0, 160);
            $type = substr(trim((string) ($_POST['institution_type'] ?? '')), 0, 60);
            $source = substr(trim((string) ($_POST['source'] ?? '')), 0, 80);
            $expected = (float) ($_POST['expected_amount'] ?? 0);
            $paid = (float) ($_POST['paid_amount'] ?? 0);
            $status = in_array($_POST['status'] ?? '', ['new','follow_up','demo_done','proposal','paid','unpaid','lost'], true) ? (string) $_POST['status'] : 'new';
            $follow = trim((string) ($_POST['next_followup'] ?? '')) ?: null;
            $notes = substr(trim((string) ($_POST['notes'] ?? '')), 0, 1000);
            $stmt = db()->prepare("INSERT INTO sadmin_sales_leads
                (owner_user_id, institution_account_id, lead_name, contact_person, phone, email, institution_type, source, expected_amount, paid_amount, status, next_followup, notes)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param('iissssssddsss', $ownerId, $institutionId, $leadName, $contact, $phone, $email, $type, $source, $expected, $paid, $status, $follow, $notes);
            $stmt->execute();
            $_SESSION['sadmin_lead_message'] = 'Lead saved.';
        } catch (Throwable $e) {
            $_SESSION['sadmin_lead_error'] = $e->getMessage();
        }
    }
    redirect('sadmin/leads');
}

$staffRows = sadmin_staff_rows();
$leadRows = sadmin_sales_lead_rows();
$institutes = db()->query("SELECT id, institution_name, institution_type FROM institution_accounts ORDER BY id DESC LIMIT 500")->fetch_all(MYSQLI_ASSOC);
$totalExpected = array_sum(array_map(static fn(array $row): float => (float) $row['expected_amount'], $leadRows));
$totalPaid = array_sum(array_map(static fn(array $row): float => (float) $row['paid_amount'], $leadRows));
$paidCount = count(array_filter($leadRows, static fn(array $row): bool => (string) $row['status'] === 'paid'));
$activePage = 'leads';
$pageTitle = 'Sales Leads';
$pageSubtitle = 'Track business brought by calling and sales teams.';
?>
<?php include __DIR__ . '/includes/header.php'; ?>
<?php include __DIR__ . '/includes/sidebar.php'; ?>
<main class="main sadmin-institute-main">
    <?php include __DIR__ . '/includes/topbar.php'; ?>
    <section class="content institute-admin-page">
        <?php if ($message): ?><div class="flash success"><?= h($message); ?></div><?php endif; ?>
        <?php if ($error): ?><div class="flash danger"><?= h($error); ?></div><?php endif; ?>
        <div class="domain-summary-grid">
            <section class="card custom-card domain-mini-card"><span>Total Leads</span><strong><?= count($leadRows); ?></strong><small>All sources</small></section>
            <section class="card custom-card domain-mini-card"><span>Paid Leads</span><strong><?= $paidCount; ?></strong><small>Converted accounts</small></section>
            <section class="card custom-card domain-mini-card"><span>Revenue</span><strong>Rs <?= h(number_format($totalPaid, 0)); ?></strong><small>Expected Rs <?= h(number_format($totalExpected, 0)); ?></small></section>
        </div>
        <section class="card custom-card institute-register-card">
            <div class="card-header"><div><span class="text-primary fs-11 fw-semibold text-uppercase d-block mb-1">Lead Capture</span><h6 class="mb-1 fw-semibold">Add business lead</h6></div></div>
            <div class="card-body">
                <form method="post" class="gr-compact-form">
                    <input type="hidden" name="csrf_token" value="<?= h(csrf_token()); ?>">
                    <label><span>Lead / Institute</span><input class="form-control" name="lead_name" required></label>
                    <label><span>Owner</span><select class="form-control" name="owner_user_id"><option value="">Unassigned</option><?php foreach ($staffRows as $staff): ?><option value="<?= (int) $staff['id']; ?>"><?= h($staff['full_name'] . ' / ' . $staff['role_name']); ?></option><?php endforeach; ?></select></label>
                    <label><span>Linked Account</span><select class="form-control" name="institution_account_id"><option value="">No account yet</option><?php foreach ($institutes as $inst): ?><option value="<?= (int) $inst['id']; ?>"><?= h($inst['institution_name']); ?></option><?php endforeach; ?></select></label>
                    <label><span>Contact Person</span><input class="form-control" name="contact_person"></label>
                    <label><span>Mobile</span><input class="form-control" name="phone"></label>
                    <label><span>Email</span><input class="form-control" type="email" name="email"></label>
                    <label><span>Type</span><select class="form-control" name="institution_type"><option>School / College</option><option>Degree College</option><option>Institute / Coaching Center</option></select></label>
                    <label><span>Source</span><input class="form-control" name="source" placeholder="Referral, calling, website"></label>
                    <label><span>Expected</span><input class="form-control" type="number" step="0.01" name="expected_amount"></label>
                    <label><span>Paid</span><input class="form-control" type="number" step="0.01" name="paid_amount"></label>
                    <label><span>Status</span><select class="form-control" name="status"><option value="new">New</option><option value="follow_up">Follow Up</option><option value="demo_done">Demo Done</option><option value="proposal">Proposal</option><option value="paid">Paid</option><option value="unpaid">Unpaid</option><option value="lost">Lost</option></select></label>
                    <label><span>Next Follow-up</span><input class="form-control" type="date" name="next_followup"></label>
                    <label class="span-3"><span>Notes</span><input class="form-control" name="notes"></label>
                    <button class="btn btn-primary btn-wave" type="submit">Save Lead</button>
                </form>
            </div>
        </section>
        <section class="card custom-card institute-register-card">
            <div class="card-header"><div><span class="text-primary fs-11 fw-semibold text-uppercase d-block mb-1">Pipeline</span><h6 class="mb-1 fw-semibold">Business performance</h6></div></div>
            <div class="card-body p-0">
                <table class="table table-hover align-middle mb-0 gr-register-table">
                    <thead><tr><th>Lead</th><th>Owner</th><th>Amount</th><th>Status</th><th>Update</th></tr></thead>
                    <tbody>
                    <?php foreach ($leadRows as $row): ?>
                        <tr>
                            <td><span class="gr-cell-title"><?= h($row['lead_name']); ?></span><span class="gr-cell-subtitle"><?= h(($row['contact_person'] ?? '') . ' | ' . ($row['phone'] ?? '')); ?></span></td>
                            <td><?= h((string) ($row['owner_name'] ?: 'Unassigned')); ?></td>
                            <td><span class="gr-cell-title">Rs <?= h(number_format((float) $row['paid_amount'], 2)); ?></span><span class="gr-cell-subtitle">Expected Rs <?= h(number_format((float) $row['expected_amount'], 2)); ?></span></td>
                            <td><span class="edu-status <?= $row['status'] === 'paid' ? 'success' : 'pending'; ?>"><?= h(ucwords(str_replace('_', ' ', (string) $row['status']))); ?></span><span class="gr-cell-subtitle"><?= h((string) ($row['next_followup'] ?: 'No follow-up')); ?></span></td>
                            <td>
                                <form method="post" class="staff-lead-update">
                                    <input type="hidden" name="csrf_token" value="<?= h(csrf_token()); ?>">
                                    <input type="hidden" name="lead_update" value="1">
                                    <input type="hidden" name="lead_id" value="<?= (int) $row['id']; ?>">
                                    <select name="status"><option value="follow_up">Follow Up</option><option value="demo_done">Demo Done</option><option value="proposal">Proposal</option><option value="paid">Paid</option><option value="unpaid">Unpaid</option><option value="lost">Lost</option></select>
                                    <input type="date" name="next_followup">
                                    <input name="note" placeholder="Note">
                                    <button class="btn btn-primary btn-sm" type="submit">Save</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>
    </section>
</main>
<?php include __DIR__ . '/includes/footer.php'; ?>
