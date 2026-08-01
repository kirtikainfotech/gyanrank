<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/institute_erp.php';
require_once __DIR__ . '/includes/institute_master.php';

$user = require_login('superadmin');
institute_erp_ensure_tables();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['csrf_token'] ?? null)) {
        $_SESSION['institute_master_error'] = 'Security token expired.';
        redirect('sadmin/institute-erp-support');
    }

    $ticketId = (int) ($_POST['ticket_id'] ?? 0);
    $status = (string) ($_POST['status'] ?? 'open');
    $priority = (string) ($_POST['priority'] ?? 'normal');
    $adminNote = trim((string) ($_POST['admin_note'] ?? ''));
    $allowedStatus = ['open', 'in_progress', 'resolved', 'closed'];
    $allowedPriority = ['low', 'normal', 'high', 'urgent'];
    if (!in_array($status, $allowedStatus, true)) {
        $status = 'open';
    }
    if (!in_array($priority, $allowedPriority, true)) {
        $priority = 'normal';
    }

    if ($ticketId > 0) {
        $stmt = db()->prepare('UPDATE institution_erp_support_tickets SET status = ?, priority = ?, admin_note = ?, updated_at = NOW() WHERE id = ?');
        $stmt->bind_param('sssi', $status, $priority, $adminNote, $ticketId);
        $stmt->execute();
        $_SESSION['institute_master_message'] = 'Support ticket updated.';
    } else {
        $_SESSION['institute_master_error'] = 'Support ticket not found.';
    }
    redirect('sadmin/institute-erp-support');
}

$statusFilter = (string) ($_GET['status'] ?? '');
$allowedFilter = ['open', 'in_progress', 'resolved', 'closed'];
$where = '';
$types = '';
$params = [];
if (in_array($statusFilter, $allowedFilter, true)) {
    $where = 'WHERE st.status = ?';
    $types = 's';
    $params[] = $statusFilter;
}

$sql = "SELECT st.*, a.institution_name, a.email, a.mobile, t.tenant_code, t.erp_base_path
    FROM institution_erp_support_tickets st
    INNER JOIN institution_accounts a ON a.id = st.institution_account_id
    LEFT JOIN institution_erp_tenants t ON t.id = st.tenant_id
    {$where}
    ORDER BY FIELD(st.status, 'open', 'in_progress', 'resolved', 'closed'), FIELD(st.priority, 'urgent', 'high', 'normal', 'low'), st.id DESC
    LIMIT 200";
$stmt = db()->prepare($sql);
if ($types !== '') {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$tickets = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$countRows = db()->query("SELECT status, COUNT(*) AS total FROM institution_erp_support_tickets GROUP BY status")->fetch_all(MYSQLI_ASSOC);
$counts = ['open' => 0, 'in_progress' => 0, 'resolved' => 0, 'closed' => 0];
foreach ($countRows as $row) {
    $counts[(string) $row['status']] = (int) $row['total'];
}

[$flashMessage, $flashError] = institute_master_flash();
$pageTitle = 'ERP Support Tickets';
$pageSubtitle = 'Institute ERP support queue and ticket status tracking.';
$activePage = 'institute-erp-support';
?>
<?php include __DIR__ . '/includes/header.php'; ?>
<?php include __DIR__ . '/includes/sidebar.php'; ?>
<main class="main sadmin-institute-main">
    <?php include __DIR__ . '/includes/topbar.php'; ?>
    <section class="content institute-admin-page">
        <?php institute_master_nav('erp-support'); ?>
        <?php if ($flashMessage !== ''): ?><div class="flash success"><?= h($flashMessage); ?></div><?php endif; ?>
        <?php if ($flashError !== ''): ?><div class="flash danger"><?= h($flashError); ?></div><?php endif; ?>

        <div class="domain-summary-grid">
            <section class="card custom-card domain-mini-card"><span>Open</span><strong><?= (int) $counts['open']; ?></strong><small>New tickets</small></section>
            <section class="card custom-card domain-mini-card"><span>In Progress</span><strong><?= (int) $counts['in_progress']; ?></strong><small>Team working</small></section>
            <section class="card custom-card domain-mini-card"><span>Resolved</span><strong><?= (int) $counts['resolved']; ?></strong><small>Completed tickets</small></section>
        </div>

        <section class="card custom-card institute-register-card">
            <div class="card-header justify-content-between">
                <div>
                    <span class="text-primary fs-11 fw-semibold text-uppercase d-block mb-1">ERP Helpdesk</span>
                    <h6 class="mb-1 fw-semibold"><?= count($tickets); ?> support tickets</h6>
                    <p class="mb-0 text-muted fs-12">Review institute issues, update priority, add internal note and close resolved tickets.</p>
                </div>
                <div class="d-flex gap-2 flex-wrap">
                    <?php foreach (['' => 'All', 'open' => 'Open', 'in_progress' => 'In Progress', 'resolved' => 'Resolved', 'closed' => 'Closed'] as $key => $label): ?>
                        <a class="btn btn-sm <?= $statusFilter === $key ? 'btn-primary' : 'btn-light'; ?> btn-wave" href="<?= h(app_url('sadmin/institute-erp-support' . ($key !== '' ? '?status=' . $key : ''))); ?>"><?= h($label); ?></a>
                    <?php endforeach; ?>
                </div>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive gr-domain-table-wrap">
                    <table class="table table-hover align-middle mb-0 gr-register-table institute-request-table">
                        <thead>
                            <tr>
                                <th>Ticket</th>
                                <th>Institute</th>
                                <th>Category</th>
                                <th>Priority</th>
                                <th>Status</th>
                                <th>Message</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php if (!$tickets): ?>
                            <tr><td colspan="7">No support tickets found.</td></tr>
                        <?php endif; ?>
                        <?php foreach ($tickets as $ticket): ?>
                            <?php
                            $modalId = 'support-ticket-' . (int) $ticket['id'];
                            $status = (string) $ticket['status'];
                            $priority = (string) $ticket['priority'];
                            $badge = in_array($status, ['resolved', 'closed'], true) ? 'success' : ($priority === 'urgent' ? 'danger' : 'pending');
                            ?>
                            <tr>
                                <td><span class="gr-cell-title"><?= h($ticket['ticket_no']); ?></span><span class="gr-cell-subtitle"><?= h((string) $ticket['created_at']); ?></span></td>
                                <td><span class="gr-cell-title"><?= h($ticket['institution_name']); ?></span><span class="gr-cell-subtitle"><?= h($ticket['mobile'] . ' | ' . $ticket['email']); ?></span></td>
                                <td><?= h(ucwords(str_replace('_', ' ', (string) $ticket['category']))); ?></td>
                                <td><?= h(ucfirst($priority)); ?></td>
                                <td><span class="edu-status <?= h($badge); ?>"><?= h(ucwords(str_replace('_', ' ', $status))); ?></span></td>
                                <td><span class="gr-cell-title"><?= h($ticket['subject']); ?></span><span class="gr-cell-subtitle"><?= h(strlen((string) $ticket['message']) > 90 ? substr((string) $ticket['message'], 0, 90) . '...' : (string) $ticket['message']); ?></span></td>
                                <td><button class="btn btn-sm btn-primary btn-wave tenant-modal-open" type="button" data-tenant-modal="<?= h($modalId); ?>">Manage</button></td>
                            </tr>
                            <tr class="support-modal-holder"><td colspan="7">
                                <div class="tenant-action-modal" id="<?= h($modalId); ?>" aria-hidden="true">
                                    <div class="tenant-action-panel">
                                        <div class="tenant-action-head">
                                            <div>
                                                <strong><?= h($ticket['ticket_no']); ?> - <?= h($ticket['subject']); ?></strong>
                                                <span><?= h($ticket['institution_name']); ?><?= !empty($ticket['tenant_code']) ? ' | ' . h($ticket['tenant_code']) : ''; ?></span>
                                            </div>
                                            <button type="button" class="tenant-modal-close" data-tenant-close aria-label="Close"><i class="bx bx-x"></i></button>
                                        </div>
                                        <form method="post" class="domain-manage-form">
                                            <input type="hidden" name="csrf_token" value="<?= h(csrf_token()); ?>">
                                            <input type="hidden" name="ticket_id" value="<?= (int) $ticket['id']; ?>">
                                            <div class="tenant-summary-grid span-2">
                                                <div><span>Contact</span><strong><?= h($ticket['mobile'] . ' / ' . $ticket['email']); ?></strong></div>
                                                <div><span>ERP URL</span><strong><?= h(!empty($ticket['erp_base_path']) ? app_url($ticket['erp_base_path']) : 'Not installed'); ?></strong></div>
                                                <div><span>Category</span><strong><?= h(ucwords(str_replace('_', ' ', (string) $ticket['category']))); ?></strong></div>
                                                <div><span>Created</span><strong><?= h((string) $ticket['created_at']); ?></strong></div>
                                            </div>
                                            <label>
                                                <span>Status</span>
                                                <select name="status" class="form-control">
                                                    <?php foreach (['open' => 'Open', 'in_progress' => 'In Progress', 'resolved' => 'Resolved', 'closed' => 'Closed'] as $value => $label): ?>
                                                        <option value="<?= h($value); ?>" <?= $status === $value ? 'selected' : ''; ?>><?= h($label); ?></option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </label>
                                            <label>
                                                <span>Priority</span>
                                                <select name="priority" class="form-control">
                                                    <?php foreach (['low' => 'Low', 'normal' => 'Normal', 'high' => 'High', 'urgent' => 'Urgent'] as $value => $label): ?>
                                                        <option value="<?= h($value); ?>" <?= $priority === $value ? 'selected' : ''; ?>><?= h($label); ?></option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </label>
                                            <label class="span-2">
                                                <span>Institute Message</span>
                                                <textarea class="form-control" rows="3" readonly><?= h((string) $ticket['message']); ?></textarea>
                                            </label>
                                            <label class="span-2">
                                                <span>Admin Note</span>
                                                <textarea name="admin_note" class="form-control" rows="4" placeholder="Internal resolution note or response summary"><?= h((string) ($ticket['admin_note'] ?? '')); ?></textarea>
                                            </label>
                                            <div class="tenant-action-buttons span-2">
                                                <button class="btn btn-primary btn-wave" type="submit">Update Ticket</button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </td></tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </section>
    </section>
    <?php include __DIR__ . '/includes/institute_table_styles.php'; ?>
    <style>
        .support-modal-holder > td {
            height: 0 !important;
            padding: 0 !important;
            border: 0 !important;
        }
    </style>
    <script>
    (() => {
        const closeModal = (modal) => {
            if (!modal) return;
            modal.classList.remove('open');
            modal.setAttribute('aria-hidden', 'true');
        };
        document.addEventListener('click', (event) => {
            const openButton = event.target.closest('[data-tenant-modal]');
            if (openButton) {
                const modal = document.getElementById(openButton.dataset.tenantModal);
                if (modal) {
                    modal.classList.add('open');
                    modal.setAttribute('aria-hidden', 'false');
                    modal.querySelector('select, textarea, button')?.focus();
                }
                return;
            }
            if (event.target.closest('[data-tenant-close]')) {
                closeModal(event.target.closest('.tenant-action-modal'));
                return;
            }
            if (event.target.classList.contains('tenant-action-modal')) {
                closeModal(event.target);
            }
        });
        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape') {
                document.querySelectorAll('.tenant-action-modal.open').forEach(closeModal);
            }
        });
    })();
    </script>
</main>
<?php include __DIR__ . '/includes/footer.php'; ?>
