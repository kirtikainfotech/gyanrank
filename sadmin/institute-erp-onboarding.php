<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/institute_erp.php';
require_once __DIR__ . '/includes/institute_master.php';

$user = require_login('superadmin');
institute_erp_ensure_tables();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && verify_csrf($_POST['csrf_token'] ?? null)) {
    $tenantId = (int) ($_POST['tenant_id'] ?? 0);
    if (($_POST['action'] ?? '') === 'install_erp' && $tenantId > 0) {
        $result = institute_erp_install_tenant($tenantId);
        $_SESSION['institute_master_' . ($result['ok'] ? 'message' : 'error')] = $result['message'];
    }
    redirect('sadmin/institute-erp-onboarding');
}

$tenants = db()->query("SELECT t.*, a.institution_name, a.email, a.mobile, p.plan_name, s.status AS subscription_status, s.expires_at
    FROM institution_erp_tenants t
    INNER JOIN institution_accounts a ON a.id = t.institution_account_id
    LEFT JOIN institution_erp_subscriptions s ON s.id = (
        SELECT s2.id FROM institution_erp_subscriptions s2
        WHERE s2.institution_account_id = a.id
        ORDER BY s2.expires_at DESC, s2.id DESC
        LIMIT 1
    )
    LEFT JOIN institution_erp_plans p ON p.id = s.plan_id
    WHERE NOT (t.erp_status = 'active' AND t.setup_status = 'installed')
    ORDER BY t.id DESC
    LIMIT 200")->fetch_all(MYSQLI_ASSOC);
[$flashMessage, $flashError] = institute_master_flash();

$pageTitle = 'ERP Onboarding';
$pageSubtitle = 'Install-pending ERP tenants and setup queue.';
$activePage = 'institute-erp-onboarding';
?>
<?php include __DIR__ . '/includes/header.php'; ?>
<?php include __DIR__ . '/includes/sidebar.php'; ?>
<main class="main sadmin-institute-main">
    <?php include __DIR__ . '/includes/topbar.php'; ?>
    <section class="content institute-admin-page">
        <?php institute_master_nav('erp-onboarding'); ?>
        <?php if ($flashMessage !== ''): ?><div class="flash success"><?= h($flashMessage); ?></div><?php endif; ?>
        <?php if ($flashError !== ''): ?><div class="flash danger"><?= h($flashError); ?></div><?php endif; ?>

        <section class="card custom-card institute-register-card">
            <div class="card-header justify-content-between">
                <div>
                    <span class="text-primary fs-11 fw-semibold text-uppercase d-block mb-1">ERP Onboarding Queue</span>
                    <h6 class="mb-1 fw-semibold"><?= count($tenants); ?> tenants need setup</h6>
                    <p class="mb-0 text-muted fs-12">Approve ke baad yahan tenant aata hai. Install ERP click karte hi DB import, branding, demo data aur access gate apply hota hai.</p>
                </div>
                <a class="btn btn-light btn-wave" href="<?= h(app_url('sadmin/institute-erp-accounts')); ?>">Active Accounts</a>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive gr-erp-tenant-scroll">
                    <table class="table table-hover align-middle mb-0 gr-register-table institute-request-table erp-tenant-table">
                        <thead><tr><th>Tenant</th><th>Institute</th><th>Plan</th><th>ERP DB</th><th>Setup</th><th>Access</th><th>Onboard</th></tr></thead>
                        <tbody>
                        <?php if (!$tenants): ?><tr><td colspan="7">No pending ERP onboarding tenants.</td></tr><?php endif; ?>
                        <?php foreach ($tenants as $tenant): ?>
                            <tr>
                                <td>
                                    <span class="gr-cell-title"><?= h($tenant['tenant_code']); ?></span>
                                    <span class="gr-cell-subtitle"><?= h(institute_erp_template_label((string) ($tenant['erp_template_type'] ?? 'school'))); ?></span>
                                    <span class="gr-cell-subtitle"><?= h($tenant['erp_base_path']); ?></span>
                                </td>
                                <td><span class="gr-cell-title"><?= h($tenant['institution_name']); ?></span><span class="gr-cell-subtitle"><?= h($tenant['mobile']); ?> | <?= h($tenant['email']); ?></span></td>
                                <td><span class="gr-cell-title"><?= h($tenant['plan_name'] ?? 'Not assigned'); ?></span><span class="gr-cell-subtitle"><?= h(($tenant['subscription_status'] ?? '') . ' until ' . ($tenant['expires_at'] ?? '')); ?></span></td>
                                <td><span class="gr-cell-title"><?= h($tenant['erp_db_name'] ?: 'Pending'); ?></span></td>
                                <td><span class="edu-status <?= h($tenant['setup_status'] === 'failed' ? 'danger' : 'pending'); ?>"><?= h(ucwords(str_replace('_', ' ', $tenant['setup_status']))); ?></span><span class="gr-cell-subtitle"><?= h($tenant['setup_note'] ?? ''); ?></span></td>
                                <td><span class="edu-status pending"><?= h(ucwords(str_replace('_', ' ', $tenant['erp_status']))); ?></span></td>
                                <td>
                                    <form method="post" class="tenant-onboard-action">
                                        <input type="hidden" name="csrf_token" value="<?= h(csrf_token()); ?>">
                                        <input type="hidden" name="tenant_id" value="<?= (int) $tenant['id']; ?>">
                                        <button class="btn btn-sm btn-primary btn-wave" name="action" value="install_erp" type="submit">Install ERP</button>
                                        <span>DB + config + admin login ready karega.</span>
                                    </form>
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
</main>
<?php include __DIR__ . '/includes/footer.php'; ?>
