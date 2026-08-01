<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/institution_module.php';
require_once __DIR__ . '/../includes/institute_erp.php';
require_once __DIR__ . '/includes/institute_master.php';

$user = require_login('superadmin');
institute_erp_ensure_tables();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && verify_csrf($_POST['csrf_token'] ?? null)) {
    $requestId = (int) ($_POST['request_id'] ?? 0);
    $tenantId = (int) ($_POST['tenant_id'] ?? 0);
    $action = (string) ($_POST['action'] ?? '');
    $note = trim((string) ($_POST['admin_note'] ?? ''));
    $returnStatus = (string) ($_POST['return_status'] ?? 'pending');
    if (!in_array($returnStatus, ['pending', 'approved', 'rejected', 'all'], true)) {
        $returnStatus = 'pending';
    }
    if ($action === 'install_erp' && $tenantId > 0) {
        $result = institute_erp_install_tenant($tenantId);
        $_SESSION['institute_master_' . ($result['ok'] ? 'message' : 'error')] = $result['message'];
        audit_log('erp_tenant_install', 'institution_erp_tenant', (string) $tenantId, ['ok' => (bool) $result['ok'], 'message' => $result['message']], $user);
    } elseif ($action === 'backup_erp' && $tenantId > 0) {
        $result = institute_erp_backup_tenant_database($tenantId, (int) ($user['id'] ?? 0));
        $_SESSION['institute_master_' . ($result['ok'] ? 'message' : 'error')] = $result['message'];
        audit_log('erp_tenant_backup', 'institution_erp_tenant', (string) $tenantId, ['ok' => (bool) $result['ok'], 'path' => $result['path'] ?? '', 'backup_id' => $result['backup_id'] ?? 0], $user);
    } elseif (in_array($action, ['renew_erp', 'expire_erp'], true) && $tenantId > 0) {
        $stmt = db()->prepare('SELECT institution_account_id FROM institution_erp_tenants WHERE id = ? LIMIT 1');
        $stmt->bind_param('i', $tenantId);
        $stmt->execute();
        $accountId = (int) ($stmt->get_result()->fetch_assoc()['institution_account_id'] ?? 0);
        if ($accountId <= 0) {
            $_SESSION['institute_master_error'] = 'Tenant account not found.';
        } elseif ($action === 'expire_erp') {
            institute_erp_expire_latest_subscription($accountId, (int) ($user['id'] ?? 0));
            $_SESSION['institute_master_message'] = 'ERP subscription expired and tenant moved to read-only.';
            audit_log('erp_subscription_expire', 'institution_account', (string) $accountId, ['tenant_id' => $tenantId], $user);
        } else {
            $planId = (int) ($_POST['plan_id'] ?? 0);
            $days = max(1, (int) ($_POST['validity_days'] ?? 365));
            $amount = max(0, (float) ($_POST['amount'] ?? 0));
            $paymentStatus = (string) ($_POST['payment_status'] ?? 'paid');
            $reference = trim((string) ($_POST['payment_reference'] ?? 'manual-' . date('YmdHis')));
            $renewalRequestId = (int) ($_POST['renewal_request_id'] ?? 0);
            if ($planId <= 0) {
                $_SESSION['institute_master_error'] = 'Select ERP plan before renewal.';
            } else {
                institute_erp_extend_subscription($accountId, $planId, $days, $amount, $paymentStatus, $reference, (int) ($user['id'] ?? 0), $renewalRequestId);
                $_SESSION['institute_master_message'] = 'ERP subscription renewed and tenant access activated.';
                audit_log('erp_subscription_renew', 'institution_account', (string) $accountId, ['tenant_id' => $tenantId, 'plan_id' => $planId, 'days' => $days, 'amount' => $amount, 'reference' => $reference], $user);
            }
        }
    } elseif ($requestId > 0 && $action === 'remark') {
        $adminId = (int) ($user['id'] ?? 0);
        $stmt = db()->prepare('UPDATE institution_registration_requests SET admin_note = ?, reviewed_by = ?, reviewed_at = NOW() WHERE id = ?');
        $stmt->bind_param('sii', $note, $adminId, $requestId);
        $stmt->execute();
        $_SESSION['institute_master_message'] = 'Remark updated.';
        audit_log('institution_request_remark', 'institution_registration_request', (string) $requestId, ['note' => $note], $user);
    } elseif ($requestId > 0 && in_array($action, ['ban_account', 'unban_account'], true)) {
        $adminId = (int) ($user['id'] ?? 0);
        $accountStatus = $action === 'ban_account' ? 'blocked' : 'active';
        $stmt = db()->prepare('UPDATE institution_accounts SET status = ?, updated_at = NOW() WHERE request_id = ?');
        $stmt->bind_param('si', $accountStatus, $requestId);
        $stmt->execute();
        $tenantStatus = $action === 'ban_account' ? 'blocked' : 'active';
        if ($action === 'unban_account') {
            $stmt = db()->prepare("UPDATE institution_erp_tenants t INNER JOIN institution_accounts a ON a.id = t.institution_account_id SET t.erp_status = IF(t.setup_status = 'installed', 'active', 'pending_setup'), t.updated_at = NOW() WHERE a.request_id = ?");
            $stmt->bind_param('i', $requestId);
        } else {
            $stmt = db()->prepare("UPDATE institution_erp_tenants t INNER JOIN institution_accounts a ON a.id = t.institution_account_id SET t.erp_status = ?, t.updated_at = NOW() WHERE a.request_id = ?");
            $stmt->bind_param('si', $tenantStatus, $requestId);
        }
        $stmt->execute();
        $stmt = db()->prepare('UPDATE institution_registration_requests SET admin_note = ?, reviewed_by = ?, reviewed_at = NOW() WHERE id = ?');
        $stmt->bind_param('sii', $note, $adminId, $requestId);
        $stmt->execute();
        $_SESSION['institute_master_message'] = $action === 'ban_account' ? 'Institute account banned.' : 'Institute account unbanned.';
        audit_log('institution_account_' . ($action === 'ban_account' ? 'ban' : 'unban'), 'institution_registration_request', (string) $requestId, ['note' => $note], $user);
    } elseif ($requestId > 0 && in_array($action, ['approved', 'rejected', 'pending'], true)) {
        $stmt = db()->prepare('UPDATE institution_registration_requests SET status = ?, admin_note = ?, reviewed_by = ?, reviewed_at = NOW() WHERE id = ?');
        $adminId = (int) ($user['id'] ?? 0);
        $stmt->bind_param('ssii', $action, $note, $adminId, $requestId);
        $stmt->execute();
        audit_log('institution_request_' . $action, 'institution_registration_request', (string) $requestId, ['note' => $note], $user);
        if ($action === 'approved') {
            $stmt = db()->prepare('SELECT * FROM institution_registration_requests WHERE id = ? LIMIT 1');
            $stmt->bind_param('i', $requestId);
            $stmt->execute();
            $request = $stmt->get_result()->fetch_assoc();
            if ($request) {
                $plainPassword = trim((string) ($_POST['login_password'] ?? ''));
                if ($plainPassword === '') {
                    $plainPassword = 'GR@' . substr((string) $request['mobile'], -4);
                }
                $hash = password_hash($plainPassword, PASSWORD_DEFAULT);
                $accountStatus = 'active';
                $stmt = db()->prepare("INSERT INTO institution_accounts
                    (request_id, institution_type, institution_name, contact_name, email, mobile, password_hash, status)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                    ON DUPLICATE KEY UPDATE institution_name = VALUES(institution_name), contact_name = VALUES(contact_name), email = VALUES(email), mobile = VALUES(mobile), password_hash = VALUES(password_hash), status = VALUES(status), updated_at = NOW()");
                $stmt->bind_param(
                    'isssssss',
                    $requestId,
                    $request['institution_type'],
                    $request['institution_name'],
                    $request['contact_name'],
                    $request['email'],
                    $request['mobile'],
                    $hash,
                    $accountStatus
                );
                $stmt->execute();
                $accountId = (int) (db()->insert_id ?: db()->query('SELECT id FROM institution_accounts WHERE request_id = ' . $requestId . ' LIMIT 1')->fetch_assoc()['id']);
                institute_erp_provision_account($accountId, $requestId, $adminId);
                $loginNote = 'Login mobile/email: ' . $request['mobile'] . ' / ' . $request['email'] . ' | Password: ' . $plainPassword;
                $stmt = db()->prepare('UPDATE institution_registration_requests SET admin_note = ? WHERE id = ?');
                $stmt->bind_param('si', $loginNote, $requestId);
                $stmt->execute();
            }
        }
    }
    redirect('sadmin/institute-manage?status=' . $returnStatus);
}

$status = (string) ($_GET['status'] ?? 'pending');
if (!in_array($status, ['pending', 'approved', 'rejected', 'all'], true)) {
    $status = 'pending';
}

$where = $status === 'all' ? '' : 'WHERE status = ?';
$requests = [];
if ($where === '') {
    $result = db()->query("SELECT r.*, a.id AS account_id, a.status AS account_status
        FROM institution_registration_requests r
        LEFT JOIN institution_accounts a ON a.request_id = r.id
        ORDER BY r.id DESC LIMIT 300");
    $requests = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
} else {
    $stmt = db()->prepare("SELECT r.*, a.id AS account_id, a.status AS account_status
        FROM institution_registration_requests r
        LEFT JOIN institution_accounts a ON a.request_id = r.id
        WHERE r.status = ?
        ORDER BY r.id DESC LIMIT 300");
    $stmt->bind_param('s', $status);
    $stmt->execute();
    $requests = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

$counts = [];
foreach (['pending', 'approved', 'rejected'] as $key) {
    $stmt = db()->prepare('SELECT COUNT(*) AS total FROM institution_registration_requests WHERE status = ?');
    $stmt->bind_param('s', $key);
    $stmt->execute();
    $counts[$key] = (int) (($stmt->get_result()->fetch_assoc()['total'] ?? 0));
}
$masterCounts = institute_master_counts();
$erpPlanCount = (int) (db()->query('SELECT COUNT(*) AS total FROM institution_erp_plans')->fetch_assoc()['total'] ?? 0);
$erpTenantCount = (int) (db()->query('SELECT COUNT(*) AS total FROM institution_erp_tenants')->fetch_assoc()['total'] ?? 0);
$erpPlans = institute_erp_plan_rows(true);
$tenants = db()->query("SELECT t.*, a.institution_name, a.email, a.mobile, p.plan_name, s.plan_id AS subscription_plan_id, s.status AS subscription_status, s.expires_at,
        rr.id AS renewal_request_id, rr.status AS renewal_request_status, rr.billing_cycle AS renewal_billing_cycle, rr.plan_id AS renewal_plan_id, rp.plan_name AS renewal_plan_name
    FROM institution_erp_tenants t
    INNER JOIN institution_accounts a ON a.id = t.institution_account_id
    LEFT JOIN institution_erp_subscriptions s ON s.id = (
        SELECT s2.id FROM institution_erp_subscriptions s2
        WHERE s2.institution_account_id = a.id
        ORDER BY s2.expires_at DESC, s2.id DESC
        LIMIT 1
    )
    LEFT JOIN institution_erp_plans p ON p.id = s.plan_id
    LEFT JOIN institution_erp_renewal_requests rr ON rr.id = (
        SELECT rr2.id FROM institution_erp_renewal_requests rr2
        WHERE rr2.institution_account_id = a.id
        ORDER BY rr2.id DESC
        LIMIT 1
    )
    LEFT JOIN institution_erp_plans rp ON rp.id = rr.plan_id
    ORDER BY t.id DESC
    LIMIT 100")->fetch_all(MYSQLI_ASSOC);
$activeTenants = array_values(array_filter($tenants, static fn($tenant) => (string) ($tenant['erp_status'] ?? '') === 'active' && (string) ($tenant['setup_status'] ?? '') === 'installed'));
$setupTenants = array_values(array_filter($tenants, static fn($tenant) => !((string) ($tenant['erp_status'] ?? '') === 'active' && (string) ($tenant['setup_status'] ?? '') === 'installed')));
[$flashMessage, $flashError] = institute_master_flash();

$pageTitle = 'Institute Manage';
$pageSubtitle = 'School/College, Degree College and Institute registration requests with separate master data.';
$activePage = 'institute-manage';
?>
<?php include __DIR__ . '/includes/header.php'; ?>
<?php include __DIR__ . '/includes/sidebar.php'; ?>
<main class="main sadmin-institute-main">
    <?php include __DIR__ . '/includes/topbar.php'; ?>
    <section class="content institute-admin-page">
        <?php institute_master_nav('requests'); ?>
        <?php if ($flashMessage !== ''): ?><div class="flash success"><?= h($flashMessage); ?></div><?php endif; ?>
        <?php if ($flashError !== ''): ?><div class="flash danger"><?= h($flashError); ?></div><?php endif; ?>
        <section class="card custom-card institute-hero-card">
            <div class="card-header justify-content-between">
                <div>
                    <span class="text-primary fs-11 fw-semibold text-uppercase d-block mb-1">Institute Manage</span>
                    <h5 class="mb-1 fw-semibold"><?= h((string) count($requests)); ?> Requests</h5>
                    <p class="mb-0 text-muted fs-12">School, college aur institute onboarding requests ek clean register me manage karein.</p>
                </div>
                <a class="btn btn-primary btn-wave" href="<?= h(app_url('register-institution')); ?>" target="_blank">Open Form</a>
            </div>
            <div class="card-body">
            <div class="institute-metrics">
                <div class="compact-metric-card"><span>Pending</span><strong><?= h((string) $counts['pending']); ?></strong></div>
                <div class="compact-metric-card"><span>Approved</span><strong><?= h((string) $counts['approved']); ?></strong></div>
                <div class="compact-metric-card"><span>Rejected</span><strong><?= h((string) $counts['rejected']); ?></strong></div>
                <?php foreach ($masterCounts as $label => $total): ?>
                    <div class="compact-metric-card"><span><?= h($label); ?></span><strong><?= h((string) $total); ?></strong></div>
                <?php endforeach; ?>
                <div class="compact-metric-card"><span>ERP Plans</span><strong><?= h((string) $erpPlanCount); ?></strong></div>
                <div class="compact-metric-card"><span>ERP Tenants</span><strong><?= h((string) $erpTenantCount); ?></strong></div>
            </div>
            </div>
        </section>

        <?php foreach ([] as $tenantGroup): ?>
        <?php /*
        <?php foreach ([
            ['label' => 'Active ERP Accounts', 'title' => 'Installed institutes running ERP', 'help' => 'Ye institutes live ERP use kar rahe hain. Yahin se open, backup, renew ya expire kar sakte hain.', 'rows' => $activeTenants],
            ['label' => 'ERP Setup Queue', 'title' => 'Pending setup / read-only / blocked ERP tenants', 'help' => 'Approve ke baad tenant yahan queued hota hai. Install ERP se DB import, config patch aur access control active hota hai.', 'rows' => $setupTenants],
        ] as $tenantGroup): ?>
        */ ?>
        <section class="card custom-card institute-register-card">
            <div class="card-header justify-content-between">
                <div>
                    <span class="text-primary fs-11 fw-semibold text-uppercase d-block mb-1"><?= h($tenantGroup['label']); ?></span>
                    <h6 class="mb-1 fw-semibold"><?= h($tenantGroup['title']); ?></h6>
                    <p class="mb-0 text-muted fs-12"><?= h($tenantGroup['help']); ?></p>
                </div>
                <a class="btn btn-light btn-wave" href="<?= h(app_url('sadmin/institute-erp-plans')); ?>">ERP Plans</a>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive gr-erp-tenant-scroll">
                    <table class="table table-hover align-middle mb-0 gr-register-table institute-request-table erp-tenant-table">
                        <thead>
                        <tr>
                            <th>Tenant</th>
                            <th>Institute</th>
                            <th>Plan</th>
                            <th>ERP DB</th>
                            <th>Setup</th>
                            <th>Access</th>
                            <th>Renewal</th>
                            <th>Action</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php if (!$tenantGroup['rows']): ?>
                            <tr><td colspan="8">No <?= h(strtolower($tenantGroup['label'])); ?> found.</td></tr>
                        <?php endif; ?>
                        <?php foreach ($tenantGroup['rows'] as $tenant): ?>
                            <?php $erpUrl = app_url((string) $tenant['erp_base_path']); ?>
                            <tr>
                                <td><span class="gr-cell-title"><?= h($tenant['tenant_code']); ?></span><span class="gr-cell-subtitle"><?= h($tenant['erp_base_path']); ?></span></td>
                                <td><span class="gr-cell-title"><?= h($tenant['institution_name']); ?></span><span class="gr-cell-subtitle"><?= h($tenant['mobile']); ?> | <?= h($tenant['email']); ?></span></td>
                                <td><span class="gr-cell-title"><?= h($tenant['plan_name'] ?? 'Not assigned'); ?></span><span class="gr-cell-subtitle"><?= h(($tenant['subscription_status'] ?? '') . ' until ' . ($tenant['expires_at'] ?? '')); ?></span></td>
                                <td><span class="gr-cell-title"><?= h($tenant['erp_db_name'] ?: 'Pending'); ?></span></td>
                                <td><span class="edu-status <?= h($tenant['setup_status'] === 'installed' ? 'success' : ($tenant['setup_status'] === 'failed' ? 'danger' : 'pending')); ?>"><?= h(ucwords(str_replace('_', ' ', $tenant['setup_status']))); ?></span><span class="gr-cell-subtitle"><?= h($tenant['setup_note'] ?? ''); ?></span></td>
                                <td><span class="edu-status <?= h($tenant['erp_status'] === 'active' ? 'success' : ($tenant['erp_status'] === 'read_only' ? 'pending' : 'danger')); ?>"><?= h(ucwords(str_replace('_', ' ', $tenant['erp_status']))); ?></span></td>
                                <td>
                                    <span class="gr-cell-title"><?= h(!empty($tenant['renewal_request_id']) ? '#' . $tenant['renewal_request_id'] . ' ' . ucfirst((string) $tenant['renewal_request_status']) : 'No request'); ?></span>
                                    <span class="gr-cell-subtitle"><?= h($tenant['renewal_plan_name'] ?? ''); ?></span>
                                </td>
                                <td>
                                    <div class="tenant-admin-actions">
                                        <?php if ($tenant['setup_status'] !== 'installed'): ?>
                                            <form method="post">
                                                <input type="hidden" name="csrf_token" value="<?= h(csrf_token()); ?>">
                                                <input type="hidden" name="tenant_id" value="<?= (int) $tenant['id']; ?>">
                                                <button class="btn btn-sm btn-primary btn-wave" name="action" value="install_erp" type="submit">Install ERP</button>
                                            </form>
                                        <?php else: ?>
                                            <a class="btn btn-sm btn-light btn-wave" href="<?= h($erpUrl); ?>" target="_blank">Open ERP</a>
                                            <form method="post">
                                                <input type="hidden" name="csrf_token" value="<?= h(csrf_token()); ?>">
                                                <input type="hidden" name="tenant_id" value="<?= (int) $tenant['id']; ?>">
                                                <button class="btn btn-sm btn-light btn-wave" name="action" value="backup_erp" type="submit">Backup DB</button>
                                            </form>
                                        <?php endif; ?>
                                        <form method="post" class="tenant-renew-form">
                                            <input type="hidden" name="csrf_token" value="<?= h(csrf_token()); ?>">
                                            <input type="hidden" name="tenant_id" value="<?= (int) $tenant['id']; ?>">
                                            <input type="hidden" name="renewal_request_id" value="<?= (int) ($tenant['renewal_request_status'] === 'pending' ? $tenant['renewal_request_id'] : 0); ?>">
                                            <select name="plan_id" class="form-control form-control-sm" required>
                                                <option value="">Plan</option>
                                                <?php foreach ($erpPlans as $plan): ?>
                                                    <?php $selectedPlan = (int) ($tenant['renewal_plan_id'] ?: $tenant['subscription_plan_id']); ?>
                                                    <option value="<?= (int) $plan['id']; ?>" <?= $selectedPlan === (int) $plan['id'] ? 'selected' : ''; ?>><?= h($plan['plan_name']); ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                            <input class="form-control form-control-sm" name="validity_days" value="365" inputmode="numeric" title="Days">
                                            <input class="form-control form-control-sm" name="amount" value="<?= h((string) number_format((float) 0, 2, '.', '')); ?>" inputmode="decimal" title="Amount">
                                            <input class="form-control form-control-sm" name="payment_reference" placeholder="Reference">
                                            <input type="hidden" name="payment_status" value="paid">
                                            <button class="btn btn-sm btn-primary btn-wave" name="action" value="renew_erp" type="submit">Renew</button>
                                            <button class="btn btn-sm btn-light btn-wave" name="action" value="expire_erp" type="submit">Expire</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </section>
        <?php endforeach; ?>

        <section class="card custom-card institute-register-card">
            <div class="card-header justify-content-between">
                <div>
                    <span class="text-primary fs-11 fw-semibold text-uppercase d-block mb-1">Registration Requests</span>
                    <h6 class="mb-1 fw-semibold">Manage institute onboarding</h6>
                    <p class="mb-0 text-muted fs-12">Degree college me university, school/college me board, institute me affiliation optional rahegi.</p>
                </div>
                <div class="status-tabs">
                    <?php foreach (['pending' => 'Pending', 'approved' => 'Approved', 'rejected' => 'Rejected', 'all' => 'All'] as $key => $label): ?>
                        <a class="<?= $status === $key ? 'active' : ''; ?>" href="<?= h(app_url('sadmin/institute-manage?status=' . $key)); ?>"><?= h($label); ?></a>
                    <?php endforeach; ?>
                </div>
            </div>
            <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0 gr-register-table institute-request-table">
                    <thead>
                    <tr>
                        <th>ID</th>
                        <th>Institution</th>
                        <th>Type</th>
                        <th>Location</th>
                        <th>Affiliation</th>
                        <th>Contact</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php if (!$requests): ?>
                        <tr><td colspan="8">No requests found.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($requests as $row): ?>
                        <?php
                        $requestModalId = 'request-action-modal-' . (int) $row['id'];
                        $requestStatus = (string) $row['status'];
                        $accountStatus = (string) ($row['account_status'] ?? '');
                        ?>
                        <tr>
                            <td><span class="gr-cell-title">#<?= (int) $row['id']; ?></span></td>
                            <td><span class="gr-cell-title"><?= h($row['institution_name']); ?></span><span class="gr-cell-subtitle"><?= h($row['address']); ?> - <?= h($row['pincode']); ?></span></td>
                            <td><span class="gr-cell-title"><?= h(institution_type_options()[$row['institution_type']] ?? $row['institution_type']); ?></span></td>
                            <td><span class="gr-cell-title"><?= h($row['district_name']); ?></span><span class="gr-cell-subtitle"><?= h($row['state_name']); ?></span></td>
                            <td><span class="gr-cell-title"><?= h($row['university_name'] ?: ($row['board_name'] ?: 'Not required')); ?></span></td>
                            <td><span class="gr-cell-title"><?= h($row['contact_name']); ?></span><span class="gr-cell-subtitle"><?= h($row['mobile']); ?> | <?= h($row['email']); ?></span></td>
                            <td>
                                <span class="edu-status <?= h($requestStatus === 'approved' ? 'success' : ($requestStatus === 'pending' ? 'pending' : 'danger')); ?>"><?= h(ucfirst($requestStatus)); ?></span>
                                <?php if ($requestStatus === 'approved'): ?>
                                    <span class="gr-cell-subtitle"><?= h($accountStatus === 'blocked' ? 'Account banned' : 'Account active'); ?></span>
                                <?php endif; ?>
                            </td>
                            <td class="request-action-cell">
                                <button class="btn btn-sm btn-primary btn-wave request-modal-open" type="button" data-request-modal="<?= h($requestModalId); ?>">Manage</button>
                                <div class="request-action-modal" id="<?= h($requestModalId); ?>" aria-hidden="true">
                                    <div class="request-action-panel">
                                        <div class="request-action-head">
                                            <div>
                                                <strong><?= h($row['institution_name']); ?></strong>
                                                <span>#<?= (int) $row['id']; ?> | <?= h(ucfirst($requestStatus)); ?><?= $accountStatus !== '' ? ' | Account: ' . h(ucfirst($accountStatus)) : ''; ?></span>
                                            </div>
                                            <button type="button" class="request-modal-close" data-request-close aria-label="Close"><i class="bx bx-x"></i></button>
                                        </div>
                                        <form method="post" class="request-action-form">
                                            <input type="hidden" name="csrf_token" value="<?= h(csrf_token()); ?>">
                                            <input type="hidden" name="request_id" value="<?= (int) $row['id']; ?>">
                                            <input type="hidden" name="return_status" value="<?= h($status); ?>">
                                            <label>
                                                <span>Remark</span>
                                                <textarea class="form-control" name="admin_note" rows="4" placeholder="Remark / note"><?= h($row['admin_note'] ?? ''); ?></textarea>
                                            </label>
                                            <?php if ($requestStatus !== 'approved'): ?>
                                                <label>
                                                    <span>Password</span>
                                                    <input class="form-control" name="login_password" placeholder="Auto: GR@last4 mobile">
                                                </label>
                                            <?php endif; ?>
                                            <div class="request-action-buttons">
                                                <button class="btn btn-light btn-wave" name="action" value="remark" type="submit">Save Remark</button>
                                                <?php if ($requestStatus === 'pending'): ?>
                                                    <button class="btn btn-primary btn-wave" name="action" value="approved" type="submit">Approve</button>
                                                    <button class="btn btn-danger btn-wave" name="action" value="rejected" type="submit">Reject</button>
                                                <?php elseif ($requestStatus === 'rejected'): ?>
                                                    <button class="btn btn-primary btn-wave" name="action" value="approved" type="submit">Approve Again</button>
                                                    <button class="btn btn-light btn-wave" name="action" value="pending" type="submit">Move Pending</button>
                                                <?php else: ?>
                                                    <?php if ($accountStatus === 'blocked'): ?>
                                                        <button class="btn btn-primary btn-wave" name="action" value="unban_account" type="submit">Unban Account</button>
                                                    <?php else: ?>
                                                        <button class="btn btn-danger btn-wave" name="action" value="ban_account" type="submit">Ban Account</button>
                                                    <?php endif; ?>
                                                <?php endif; ?>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            </div>
        </section>
    </section>
    <style>
        .sadmin-institute-main .institute-admin-page {
            padding-top: 1.25rem;
        }
        .sadmin-institute-main .institute-hero-card,
        .sadmin-institute-main .institute-register-card {
            border: 0;
            border-radius: .65rem;
            box-shadow: 0 .85rem 1.85rem rgba(15, 23, 42, .045);
            overflow: hidden;
        }
        .sadmin-institute-main .institute-hero-card .card-header,
        .sadmin-institute-main .institute-register-card .card-header {
            padding: .75rem 1rem;
            border-bottom: 1px solid var(--default-border);
            background: var(--custom-white);
        }
        .sadmin-institute-main .institute-metrics {
            display: grid;
            grid-template-columns: repeat(5, minmax(0, 1fr));
            gap: .65rem;
        }
        .sadmin-institute-main .compact-metric-card {
            display: grid;
            gap: .25rem;
            padding: .7rem .85rem;
            border: 1px solid var(--default-border);
            border-radius: .5rem;
            background: var(--default-background);
        }
        .sadmin-institute-main .compact-metric-card span {
            color: var(--text-muted);
            font-size: .68rem;
            font-weight: 700;
            text-transform: uppercase;
        }
        .sadmin-institute-main .compact-metric-card strong {
            font-size: 1.15rem;
            line-height: 1;
        }
        .sadmin-institute-main .status-tabs {
            display: flex;
            flex-wrap: wrap;
            gap: .35rem;
        }
        .sadmin-institute-main .status-tabs a {
            display: inline-flex;
            align-items: center;
            min-height: 1.8rem;
            padding: .22rem .65rem;
            border-radius: .4rem;
            background: var(--default-background);
            color: var(--text-muted);
            font-size: .72rem;
            font-weight: 700;
            text-decoration: none;
        }
        .sadmin-institute-main .status-tabs a.active {
            background: rgba(var(--primary-rgb), .1);
            color: rgb(var(--primary-rgb));
        }
        .sadmin-institute-main .institute-register-card .table-responsive {
            width: 100%;
            overflow-x: hidden !important;
            overflow-y: visible;
        }
        .sadmin-institute-main .institute-request-table {
            width: 100%;
            min-width: 0;
            table-layout: fixed;
        }
        .sadmin-institute-main .institute-register-card .institute-request-table th:nth-child(1) { width: 4%; }
        .sadmin-institute-main .institute-register-card .institute-request-table th:nth-child(2) { width: 18%; }
        .sadmin-institute-main .institute-register-card .institute-request-table th:nth-child(3) { width: 12%; }
        .sadmin-institute-main .institute-register-card .institute-request-table th:nth-child(4) { width: 12%; }
        .sadmin-institute-main .institute-register-card .institute-request-table th:nth-child(5) { width: 11%; }
        .sadmin-institute-main .institute-register-card .institute-request-table th:nth-child(6) { width: 21%; }
        .sadmin-institute-main .institute-register-card .institute-request-table th:nth-child(7) { width: 10%; }
        .sadmin-institute-main .institute-register-card .institute-request-table th:nth-child(8) { width: 12%; }
        .sadmin-institute-main .erp-tenant-table {
            min-width: 104rem;
            table-layout: fixed;
        }
        .sadmin-institute-main .erp-tenant-table th:nth-child(1) { width: 13rem; }
        .sadmin-institute-main .erp-tenant-table th:nth-child(2) { width: 15rem; }
        .sadmin-institute-main .erp-tenant-table th:nth-child(3) { width: 10rem; }
        .sadmin-institute-main .erp-tenant-table th:nth-child(4) { width: 13rem; }
        .sadmin-institute-main .erp-tenant-table th:nth-child(5) { width: 13rem; }
        .sadmin-institute-main .erp-tenant-table th:nth-child(6) { width: 8rem; }
        .sadmin-institute-main .erp-tenant-table th:nth-child(7) { width: 10rem; }
        .sadmin-institute-main .erp-tenant-table th:nth-child(8) { width: 22rem; }
        .sadmin-institute-main .gr-erp-tenant-scroll {
            overflow-x: auto;
        }
        .sadmin-institute-main .institute-request-table th,
        .sadmin-institute-main .institute-request-table td {
            padding: .38rem .5rem !important;
            font-size: .7rem;
            line-height: 1.2;
            vertical-align: middle;
        }
        .sadmin-institute-main .institute-request-table th {
            background: var(--default-background);
            font-size: .65rem;
            letter-spacing: .025em;
        }
        .sadmin-institute-main .institute-request-table .gr-cell-title {
            display: block;
            font-size: .71rem;
            line-height: 1.2;
            white-space: normal !important;
            overflow: visible !important;
            text-overflow: clip !important;
            overflow-wrap: anywhere;
            word-break: normal;
        }
        .sadmin-institute-main .institute-request-table .gr-cell-subtitle {
            display: block;
            margin-top: .15rem;
            font-size: .64rem;
            line-height: 1.2;
            white-space: normal !important;
            overflow: visible !important;
            text-overflow: clip !important;
            overflow-wrap: anywhere;
            word-break: normal;
            color: #64748b;
        }
        .sadmin-institute-main .erp-tenant-table td {
            min-height: 4.25rem;
        }
        .sadmin-institute-main .inline-admin-action {
            display: grid;
            grid-template-columns: minmax(0, 1fr) minmax(0, 1fr);
            align-items: center;
            gap: .3rem;
            margin: 0;
            min-width: 0;
        }
        .sadmin-institute-main .inline-admin-action .form-control {
            min-height: 1.7rem;
            padding: .18rem .45rem;
            font-size: .68rem;
            min-width: 0;
            width: 100%;
        }
        .sadmin-institute-main .inline-admin-action .btn-sm {
            min-height: 1.7rem;
            padding: .18rem .5rem;
            font-size: .68rem;
            width: 100%;
        }
        .sadmin-institute-main .request-action-cell {
            text-align: center;
        }
        .sadmin-institute-main .request-modal-open {
            min-width: 5.5rem;
        }
        .sadmin-institute-main .request-action-modal {
            display: none;
            position: fixed;
            inset: 0;
            z-index: 6000;
            align-items: center;
            justify-content: center;
            padding: 1rem;
            background: rgba(3, 21, 38, .48);
            text-align: left;
        }
        .sadmin-institute-main .request-action-modal.open {
            display: flex;
        }
        .sadmin-institute-main .request-action-panel {
            width: min(520px, 96vw);
            border: 1px solid #c9d9e8;
            border-top: 4px solid #f68a00;
            border-radius: 6px;
            background: #ffffff;
            box-shadow: 0 18px 45px rgba(0, 0, 0, .25);
            overflow: hidden;
        }
        .sadmin-institute-main .request-action-head {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
            padding: .85rem 1rem;
            border-bottom: 1px solid #dbe7f2;
            background: #f6f9fc;
        }
        .sadmin-institute-main .request-action-head strong,
        .sadmin-institute-main .request-action-head span {
            display: block;
        }
        .sadmin-institute-main .request-action-head strong {
            color: #082f55;
            font-size: .95rem;
            line-height: 1.2;
        }
        .sadmin-institute-main .request-action-head span {
            margin-top: .2rem;
            color: #64748b;
            font-size: .72rem;
        }
        .sadmin-institute-main .request-modal-close {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 34px;
            min-height: 34px;
            border: 1px solid #c9d9e8;
            border-radius: 3px;
            background: #ffffff;
            color: #0a3c66;
            font-size: 1.25rem;
        }
        .sadmin-institute-main .request-action-form {
            display: grid;
            gap: .75rem;
            padding: 1rem;
        }
        .sadmin-institute-main .request-action-form label {
            display: grid;
            gap: .3rem;
            margin: 0;
            color: #0f172a;
            font-size: .75rem;
            font-weight: 800;
        }
        .sadmin-institute-main .request-action-form textarea {
            resize: vertical;
        }
        .sadmin-institute-main .request-action-buttons {
            display: flex;
            flex-wrap: wrap;
            justify-content: flex-end;
            gap: .45rem;
            padding-top: .2rem;
        }
        .sadmin-institute-main .request-action-buttons .btn {
            min-height: 2rem;
            font-size: .74rem;
            font-weight: 800;
        }
        .sadmin-institute-main .tenant-admin-actions {
            display: grid;
            gap: .35rem;
            min-width: 0;
        }
        .sadmin-institute-main .tenant-admin-actions > form:first-child {
            margin: 0;
        }
        .sadmin-institute-main .tenant-renew-form {
            display: grid;
            grid-template-columns: 6rem 3.5rem 4.5rem minmax(6rem, 1fr) auto auto;
            gap: .25rem;
            align-items: center;
            margin: 0;
        }
        .sadmin-institute-main .tenant-renew-form .form-control {
            min-height: 1.7rem;
            padding: .18rem .45rem;
            font-size: .68rem;
        }
        .sadmin-institute-main .tenant-renew-form .btn-sm {
            min-height: 1.7rem;
            padding: .18rem .5rem;
            font-size: .68rem;
        }
        .sadmin-institute-main .footer {
            margin-inline: 0 !important;
            width: 100%;
        }
        @media (max-width: 1199.98px) {
            .sadmin-institute-main .institute-metrics {
                grid-template-columns: repeat(3, minmax(0, 1fr));
            }
        }
        @media (max-width: 767.98px) {
            .sadmin-institute-main .institute-metrics {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
            .sadmin-institute-main .inline-admin-action {
                grid-template-columns: 1fr 1fr;
            }
            .sadmin-institute-main .tenant-renew-form {
                grid-template-columns: 1fr 1fr;
            }
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
            const openButton = event.target.closest('[data-request-modal]');
            if (openButton) {
                const modal = document.getElementById(openButton.dataset.requestModal);
                if (modal) {
                    modal.classList.add('open');
                    modal.setAttribute('aria-hidden', 'false');
                    modal.querySelector('textarea, input, button')?.focus();
                }
                return;
            }

            if (event.target.closest('[data-request-close]')) {
                closeModal(event.target.closest('.request-action-modal'));
                return;
            }

            if (event.target.classList.contains('request-action-modal')) {
                closeModal(event.target);
            }
        });

        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape') {
                document.querySelectorAll('.request-action-modal.open').forEach(closeModal);
            }
        });
    })();
    </script>
</main>
<?php include __DIR__ . '/includes/footer.php'; ?>
