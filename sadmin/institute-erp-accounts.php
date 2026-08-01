<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/institute_erp.php';
require_once __DIR__ . '/includes/institute_master.php';

$user = require_login('superadmin');
institute_erp_ensure_tables();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && verify_csrf($_POST['csrf_token'] ?? null)) {
    $tenantId = (int) ($_POST['tenant_id'] ?? 0);
    $action = (string) ($_POST['action'] ?? '');
    if ($action === 'backup_erp' && $tenantId > 0) {
        $result = institute_erp_backup_tenant_database($tenantId, (int) ($user['id'] ?? 0));
        $_SESSION['institute_master_' . ($result['ok'] ? 'message' : 'error')] = $result['message'];
    } elseif (in_array($action, ['renew_erp', 'expire_erp'], true) && $tenantId > 0) {
        $stmt = db()->prepare('SELECT institution_account_id FROM institution_erp_tenants WHERE id = ? LIMIT 1');
        $stmt->bind_param('i', $tenantId);
        $stmt->execute();
        $accountId = (int) ($stmt->get_result()->fetch_assoc()['institution_account_id'] ?? 0);
        if ($accountId > 0 && $action === 'expire_erp') {
            institute_erp_expire_latest_subscription($accountId, (int) ($user['id'] ?? 0));
            $_SESSION['institute_master_message'] = 'ERP subscription expired and tenant moved to read-only.';
        } elseif ($accountId > 0) {
            $planId = (int) ($_POST['plan_id'] ?? 0);
            $days = max(1, (int) ($_POST['validity_days'] ?? 365));
            $amount = max(0, (float) ($_POST['amount'] ?? 0));
            $reference = trim((string) ($_POST['payment_reference'] ?? 'manual-' . date('YmdHis')));
            if ($planId > 0) {
                institute_erp_extend_subscription($accountId, $planId, $days, $amount, 'paid', $reference, (int) ($user['id'] ?? 0));
                $_SESSION['institute_master_message'] = 'ERP subscription renewed and tenant access activated.';
            } else {
                $_SESSION['institute_master_error'] = 'Select ERP plan before renewal.';
            }
        }
    }
    redirect('sadmin/institute-erp-accounts');
}

$erpPlans = institute_erp_plan_rows(true);
$tenants = db()->query("SELECT t.*, a.institution_name, a.email, a.mobile, p.plan_name, s.plan_id AS subscription_plan_id, s.status AS subscription_status, s.expires_at
    FROM institution_erp_tenants t
    INNER JOIN institution_accounts a ON a.id = t.institution_account_id
    LEFT JOIN institution_erp_subscriptions s ON s.id = (
        SELECT s2.id FROM institution_erp_subscriptions s2
        WHERE s2.institution_account_id = a.id
        ORDER BY s2.expires_at DESC, s2.id DESC
        LIMIT 1
    )
    LEFT JOIN institution_erp_plans p ON p.id = s.plan_id
    WHERE t.erp_status = 'active' AND t.setup_status = 'installed'
    ORDER BY t.id DESC
    LIMIT 200")->fetch_all(MYSQLI_ASSOC);
[$flashMessage, $flashError] = institute_master_flash();

$pageTitle = 'ERP Accounts';
$pageSubtitle = 'Live installed school ERP accounts.';
$activePage = 'institute-erp-accounts';
?>
<?php include __DIR__ . '/includes/header.php'; ?>
<?php include __DIR__ . '/includes/sidebar.php'; ?>
<main class="main sadmin-institute-main">
    <?php include __DIR__ . '/includes/topbar.php'; ?>
    <section class="content institute-admin-page">
        <?php institute_master_nav('erp-accounts'); ?>
        <?php if ($flashMessage !== ''): ?><div class="flash success"><?= h($flashMessage); ?></div><?php endif; ?>
        <?php if ($flashError !== ''): ?><div class="flash danger"><?= h($flashError); ?></div><?php endif; ?>

        <section class="card custom-card institute-register-card">
            <div class="card-header justify-content-between">
                <div>
                    <span class="text-primary fs-11 fw-semibold text-uppercase d-block mb-1">Active ERP Accounts</span>
                    <h6 class="mb-1 fw-semibold"><?= count($tenants); ?> live ERP accounts</h6>
                    <p class="mb-0 text-muted fs-12">Open ERP, backup database, renew plan or move tenant to read-only.</p>
                </div>
                <div class="d-flex gap-2 flex-wrap">
                    <a class="btn btn-light btn-wave" href="<?= h(app_url('sadmin/institute-erp-domains')); ?>">ERP Domains</a>
                    <a class="btn btn-light btn-wave" href="<?= h(app_url('sadmin/institute-erp-onboarding')); ?>">Onboarding Queue</a>
                </div>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive gr-erp-tenant-scroll">
                    <table class="table table-hover align-middle mb-0 gr-register-table institute-request-table erp-tenant-table">
                        <thead><tr><th>Tenant</th><th>Institute</th><th>Plan</th><th>ERP DB</th><th>Domain</th><th>Access</th><th>Action</th></tr></thead>
                        <tbody>
                        <?php if (!$tenants): ?><tr><td colspan="7">No active ERP accounts found.</td></tr><?php endif; ?>
                        <?php foreach ($tenants as $tenant): ?>
                            <?php
                            $erpUrl = app_url((string) $tenant['erp_base_path']);
                            $accountModalId = 'erp-account-modal-' . (int) $tenant['id'];
                            ?>
                            <tr>
                                <td>
                                    <span class="gr-cell-title"><?= h($tenant['tenant_code']); ?></span>
                                    <span class="gr-cell-subtitle"><?= h(institute_erp_template_label((string) ($tenant['erp_template_type'] ?? 'school'))); ?></span>
                                    <span class="gr-cell-subtitle"><?= h($tenant['erp_base_path']); ?></span>
                                </td>
                                <td><span class="gr-cell-title"><?= h($tenant['institution_name']); ?></span><span class="gr-cell-subtitle"><?= h($tenant['mobile']); ?> | <?= h($tenant['email']); ?></span></td>
                                <td><span class="gr-cell-title"><?= h($tenant['plan_name'] ?? 'Not assigned'); ?></span><span class="gr-cell-subtitle"><?= h(($tenant['subscription_status'] ?? '') . ' until ' . ($tenant['expires_at'] ?? '')); ?></span></td>
                                <td><span class="gr-cell-title"><?= h($tenant['erp_db_name'] ?: 'Pending'); ?></span></td>
                                <td>
                                    <span class="gr-cell-title"><?= h($tenant['custom_domain'] ?: 'Default URL'); ?></span>
                                    <span class="gr-cell-subtitle"><?= h(ucwords(str_replace('_', ' ', (string) ($tenant['custom_domain_status'] ?? 'none')))); ?></span>
                                </td>
                                <td><span class="edu-status success">Active</span></td>
                                <td class="tenant-modal-action-cell">
                                    <div class="tenant-row-actions">
                                        <a class="btn btn-sm btn-light btn-wave" href="<?= h($erpUrl); ?>" target="_blank">Open ERP</a>
                                        <button class="btn btn-sm btn-primary btn-wave tenant-modal-open" type="button" data-tenant-modal="<?= h($accountModalId); ?>">Manage</button>
                                    </div>
                                    <div class="tenant-action-modal" id="<?= h($accountModalId); ?>" aria-hidden="true">
                                        <div class="tenant-action-panel">
                                            <div class="tenant-action-head">
                                                <div>
                                                    <strong><?= h($tenant['institution_name']); ?></strong>
                                                    <span><?= h($tenant['tenant_code']); ?> | <?= h($tenant['plan_name'] ?? 'Not assigned'); ?></span>
                                                </div>
                                                <button type="button" class="tenant-modal-close" data-tenant-close aria-label="Close"><i class="bx bx-x"></i></button>
                                            </div>
                                            <div class="tenant-action-body">
                                                <div class="tenant-summary-grid">
                                                    <div><span>ERP DB</span><strong><?= h($tenant['erp_db_name'] ?: 'Pending'); ?></strong></div>
                                                    <div><span>Valid Until</span><strong><?= h($tenant['expires_at'] ?? 'Not assigned'); ?></strong></div>
                                                    <div><span>Custom Domain</span><strong><?= h($tenant['custom_domain'] ?: 'Not requested'); ?></strong></div>
                                                    <div><span>Mobile</span><strong><?= h($tenant['mobile']); ?></strong></div>
                                                    <div><span>Email</span><strong><?= h($tenant['email']); ?></strong></div>
                                                </div>
                                                <div class="tenant-action-strip">
                                                    <a class="btn btn-light btn-wave" href="<?= h($erpUrl); ?>" target="_blank">Open ERP</a>
                                                    <a class="btn btn-light btn-wave" href="<?= h(app_url('sadmin/institute-erp-domains')); ?>">Domain Setup</a>
                                                    <form method="post">
                                                        <input type="hidden" name="csrf_token" value="<?= h(csrf_token()); ?>">
                                                        <input type="hidden" name="tenant_id" value="<?= (int) $tenant['id']; ?>">
                                                        <button class="btn btn-light btn-wave" name="action" value="backup_erp" type="submit">Backup DB</button>
                                                    </form>
                                                </div>
                                                <form method="post" class="tenant-renew-modal-form">
                                                    <input type="hidden" name="csrf_token" value="<?= h(csrf_token()); ?>">
                                                    <input type="hidden" name="tenant_id" value="<?= (int) $tenant['id']; ?>">
                                                    <label>
                                                        <span>Plan</span>
                                                        <select name="plan_id" class="form-control" required>
                                                            <?php foreach ($erpPlans as $plan): ?>
                                                                <option value="<?= (int) $plan['id']; ?>" <?= (int) $tenant['subscription_plan_id'] === (int) $plan['id'] ? 'selected' : ''; ?>><?= h($plan['plan_name']); ?></option>
                                                            <?php endforeach; ?>
                                                        </select>
                                                    </label>
                                                    <label>
                                                        <span>Validity Days</span>
                                                        <input class="form-control" name="validity_days" value="365">
                                                    </label>
                                                    <label>
                                                        <span>Amount</span>
                                                        <input class="form-control" name="amount" value="0.00">
                                                    </label>
                                                    <label>
                                                        <span>Payment Reference</span>
                                                        <input class="form-control" name="payment_reference" placeholder="Reference">
                                                    </label>
                                                    <div class="tenant-action-buttons">
                                                        <button class="btn btn-primary btn-wave" name="action" value="renew_erp" type="submit">Renew Plan</button>
                                                        <button class="btn btn-danger btn-wave" name="action" value="expire_erp" type="submit">Move Read-only</button>
                                                    </div>
                                                </form>
                                            </div>
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
    <?php include __DIR__ . '/includes/institute_table_styles.php'; ?>
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
                    modal.querySelector('select, input, button')?.focus();
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
