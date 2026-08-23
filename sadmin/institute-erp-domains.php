<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/institute_erp.php';
require_once __DIR__ . '/includes/institute_master.php';

$user = require_login('superadmin');
institute_erp_ensure_tables();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && verify_csrf($_POST['csrf_token'] ?? null)) {
    $action = (string) ($_POST['action'] ?? '');
    if ($action === 'create_domain_request') {
        $result = institute_erp_create_domain_request(
            (int) ($_POST['tenant_id'] ?? 0),
            (string) ($_POST['domain'] ?? ''),
            (int) ($user['id'] ?? 0),
            trim((string) ($_POST['admin_note'] ?? ''))
        );
        $_SESSION['institute_master_' . ($result['ok'] ? 'message' : 'error')] = $result['message'];
    } elseif ($action === 'update_domain_request') {
        $result = institute_erp_update_domain_request(
            (int) ($_POST['request_id'] ?? 0),
            (string) ($_POST['status'] ?? 'pending'),
            trim((string) ($_POST['dns_target'] ?? '')),
            trim((string) ($_POST['server_docroot'] ?? '')),
            (string) ($_POST['ssl_status'] ?? 'pending'),
            trim((string) ($_POST['admin_note'] ?? '')),
            (int) ($user['id'] ?? 0)
        );
        $_SESSION['institute_master_' . ($result['ok'] ? 'message' : 'error')] = $result['message'];
    } else {
        $_SESSION['institute_master_error'] = 'Invalid domain action.';
    }
    redirect('sadmin/institute-erp-domains');
}

$domainRequests = institute_erp_domain_request_rows();
$tenants = db()->query("SELECT t.id, t.tenant_code, t.erp_base_path, t.custom_domain, t.custom_domain_status, a.institution_name
    FROM institution_erp_tenants t
    INNER JOIN institution_accounts a ON a.id = t.institution_account_id
    WHERE t.setup_status = 'installed'
    ORDER BY a.institution_name ASC, t.id DESC")->fetch_all(MYSQLI_ASSOC);

$counts = ['pending' => 0, 'dns_pending' => 0, 'mapped' => 0, 'rejected' => 0, 'cancelled' => 0];
foreach ($domainRequests as $request) {
    $key = (string) ($request['status'] ?? 'pending');
    if (isset($counts[$key])) {
        $counts[$key]++;
    }
}

[$flashMessage, $flashError] = institute_master_flash();
$pageTitle = 'ERP Domains';
$pageSubtitle = 'Custom domain request queue for institute ERP tenants.';
$activePage = 'institute-erp-domains';
?>
<?php include __DIR__ . '/includes/header.php'; ?>
<?php include __DIR__ . '/includes/sidebar.php'; ?>
<main class="main sadmin-institute-main">
    <?php include __DIR__ . '/includes/topbar.php'; ?>
    <section class="content institute-admin-page">
        <?php institute_master_nav('erp-domains'); ?>
        <?php if ($flashMessage !== ''): ?><div class="flash success"><?= h($flashMessage); ?></div><?php endif; ?>
        <?php if ($flashError !== ''): ?><div class="flash danger"><?= h($flashError); ?></div><?php endif; ?>

        <div class="domain-summary-grid">
            <section class="card custom-card domain-mini-card">
                <span>Pending</span>
                <strong><?= (int) $counts['pending']; ?></strong>
                <small>New school domain requests</small>
            </section>
            <section class="card custom-card domain-mini-card">
                <span>DNS Pending</span>
                <strong><?= (int) $counts['dns_pending']; ?></strong>
                <small>Waiting for CNAME/A record</small>
            </section>
            <section class="card custom-card domain-mini-card">
                <span>Mapped</span>
                <strong><?= (int) $counts['mapped']; ?></strong>
                <small>Live on own domain</small>
            </section>
        </div>

        <section class="card custom-card institute-register-card">
            <div class="card-header justify-content-between">
                <div>
                    <span class="text-primary fs-11 fw-semibold text-uppercase d-block mb-1">Custom Domain Request</span>
                    <h6 class="mb-1 fw-semibold">Add institute domain request</h6>
                    <p class="mb-0 text-muted fs-12">School domain yahan submit karein. DNS, SSL aur vhost setup team manually complete karegi.</p>
                </div>
            </div>
            <div class="card-body">
                <form method="post" class="domain-request-form">
                    <input type="hidden" name="csrf_token" value="<?= h(csrf_token()); ?>">
                    <input type="hidden" name="action" value="create_domain_request">
                    <label>
                        <span>ERP Tenant</span>
                        <select name="tenant_id" class="form-control" required>
                            <option value="">Select installed ERP tenant</option>
                            <?php foreach ($tenants as $tenant): ?>
                                <option value="<?= (int) $tenant['id']; ?>">
                                    <?= h($tenant['institution_name'] . ' / ' . $tenant['tenant_code']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label>
                        <span>Requested Domain</span>
                        <input name="domain" class="form-control" placeholder="erp.schoolname.com" required>
                    </label>
                    <label>
                        <span>Initial Note</span>
                        <input name="admin_note" class="form-control" placeholder="Client asked to run ERP on their own domain">
                    </label>
                    <button class="btn btn-primary btn-wave" type="submit">Add Request</button>
                </form>
            </div>
        </section>

        <section class="card custom-card institute-register-card">
            <div class="card-header justify-content-between">
                <div>
                    <span class="text-primary fs-11 fw-semibold text-uppercase d-block mb-1">Domain Setup Queue</span>
                    <h6 class="mb-1 fw-semibold"><?= count($domainRequests); ?> custom domain requests</h6>
                    <p class="mb-0 text-muted fs-12">DNS target, document root, SSL status aur final mapping ek hi modal se manage karein.</p>
                </div>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive gr-domain-table-wrap">
                    <table class="table table-hover align-middle mb-0 gr-register-table institute-request-table domain-request-table">
                        <thead>
                            <tr>
                                <th>Domain</th>
                                <th>Institute</th>
                                <th>Tenant</th>
                                <th>Status</th>
                                <th>DNS / SSL</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php if (!$domainRequests): ?>
                            <tr><td colspan="6">No custom domain requests found.</td></tr>
                        <?php endif; ?>
                        <?php foreach ($domainRequests as $request): ?>
                            <?php
                            $modalId = 'domain-request-' . (int) $request['id'];
                            $status = (string) ($request['status'] ?? 'pending');
                            $badge = $status === 'mapped' ? 'success' : ($status === 'rejected' || $status === 'cancelled' ? 'danger' : 'pending');
                            $directUrl = app_url((string) $request['erp_base_path']);
                            ?>
                            <tr>
                                <td>
                                    <span class="gr-cell-title"><?= h($request['normalized_domain']); ?></span>
                                    <span class="gr-cell-subtitle"><?= h($request['requested_domain']); ?></span>
                                </td>
                                <td>
                                    <span class="gr-cell-title"><?= h($request['institution_name']); ?></span>
                                    <span class="gr-cell-subtitle"><?= h($request['mobile'] . ' | ' . $request['email']); ?></span>
                                </td>
                                <td>
                                    <span class="gr-cell-title"><?= h($request['tenant_code']); ?></span>
                                    <span class="gr-cell-subtitle"><a href="<?= h($directUrl); ?>" target="_blank" rel="noopener"><?= h($request['erp_base_path']); ?></a></span>
                                </td>
                                <td><span class="edu-status <?= h($badge); ?>"><?= h(ucwords(str_replace('_', ' ', $status))); ?></span></td>
                                <td>
                                    <span class="gr-cell-title"><?= h($request['dns_target'] ?: 'DNS target pending'); ?></span>
                                    <span class="gr-cell-subtitle"><?= h('SSL: ' . ucwords(str_replace('_', ' ', (string) $request['ssl_status']))); ?></span>
                                </td>
                                <td>
                                    <button class="btn btn-sm btn-primary btn-wave domain-modal-open" type="button" data-domain-modal="<?= h($modalId); ?>">Manage</button>
                                    <div class="tenant-action-modal domain-action-modal" id="<?= h($modalId); ?>" aria-hidden="true">
                                        <div class="tenant-action-panel">
                                            <div class="tenant-action-head">
                                                <div>
                                                    <strong><?= h($request['normalized_domain']); ?></strong>
                                                    <span><?= h($request['institution_name'] . ' / ' . $request['tenant_code']); ?></span>
                                                </div>
                                                <button type="button" class="tenant-modal-close" data-domain-close aria-label="Close"><i class="bx bx-x"></i></button>
                                            </div>
                                            <form method="post" class="domain-manage-form">
                                                <input type="hidden" name="csrf_token" value="<?= h(csrf_token()); ?>">
                                                <input type="hidden" name="action" value="update_domain_request">
                                                <input type="hidden" name="request_id" value="<?= (int) $request['id']; ?>">
                                                <div class="tenant-summary-grid">
                                                    <div><span>Current ERP URL</span><strong><?= h($directUrl); ?></strong></div>
                                                    <div><span>Suggested DocRoot</span><strong><?= h(str_replace('/', DIRECTORY_SEPARATOR, dirname(__DIR__) . '/../' . $request['erp_base_path'])); ?></strong></div>
                                                    <div><span>DNS Option</span><strong>CNAME to app domain or A record to server IP</strong></div>
                                                    <div><span>Final Test</span><strong>Open domain, login, check assets and SSL</strong></div>
                                                </div>
                                                <label>
                                                    <span>Status</span>
                                                    <select name="status" class="form-control">
                                                        <?php foreach (['pending' => 'Pending', 'dns_pending' => 'DNS Pending', 'mapped' => 'Mapped / Live', 'rejected' => 'Rejected', 'cancelled' => 'Cancelled'] as $value => $label): ?>
                                                            <option value="<?= h($value); ?>" <?= $status === $value ? 'selected' : ''; ?>><?= h($label); ?></option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                </label>
                                                <label>
                                                    <span>DNS Target</span>
                                                    <input name="dns_target" class="form-control" value="<?= h($request['dns_target'] ?? ''); ?>" placeholder="CNAME app.gyannexa.com or server IP">
                                                </label>
                                                <label>
                                                    <span>Server DocRoot / Alias Note</span>
                                                    <input name="server_docroot" class="form-control" value="<?= h($request['server_docroot'] ?? ''); ?>" placeholder="Manual vhost/docroot path">
                                                </label>
                                                <label>
                                                    <span>SSL Status</span>
                                                    <select name="ssl_status" class="form-control">
                                                        <?php foreach (['pending' => 'Pending', 'issued' => 'Issued', 'not_required' => 'Not Required', 'failed' => 'Failed'] as $value => $label): ?>
                                                            <option value="<?= h($value); ?>" <?= (string) $request['ssl_status'] === $value ? 'selected' : ''; ?>><?= h($label); ?></option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                </label>
                                                <label class="span-2">
                                                    <span>Team Note</span>
                                                    <textarea name="admin_note" class="form-control" rows="4" placeholder="DNS checked, SSL issued, vhost mapped..."><?= h($request['admin_note'] ?? ''); ?></textarea>
                                                </label>
                                                <div class="tenant-action-buttons span-2">
                                                    <button class="btn btn-primary btn-wave" type="submit">Update Domain</button>
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
    <?php include __DIR__ . '/includes/institute_table_styles.php'; ?>
    <style>
        .sadmin-institute-main .domain-summary-grid { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: .85rem; margin-bottom: 1rem; }
        .sadmin-institute-main .domain-mini-card { border-top: 3px solid #f68a00; padding: .9rem 1rem; }
        .sadmin-institute-main .domain-mini-card span { color: #64748b; font-size: .68rem; font-weight: 900; text-transform: uppercase; }
        .sadmin-institute-main .domain-mini-card strong { display: block; margin-top: .25rem; color: #082f55; font-size: 1.45rem; line-height: 1; }
        .sadmin-institute-main .domain-mini-card small { color: #64748b; font-size: .72rem; }
        .sadmin-institute-main .domain-request-form { display: grid; grid-template-columns: 1.25fr 1fr 1.35fr auto; gap: .75rem; align-items: end; }
        .sadmin-institute-main .domain-request-form label,
        .sadmin-institute-main .domain-manage-form label { display: grid; gap: .25rem; margin: 0; color: #102a43; font-size: .72rem; font-weight: 800; }
        .sadmin-institute-main .domain-request-form .form-control,
        .sadmin-institute-main .domain-manage-form .form-control { min-height: 2.05rem; border-radius: 3px; font-size: .75rem; }
        .sadmin-institute-main .domain-request-form .btn { min-height: 2.05rem; font-size: .74rem; font-weight: 800; white-space: nowrap; }
        .sadmin-institute-main .domain-request-table { table-layout: fixed; min-width: 0; }
        .sadmin-institute-main .domain-request-table th:nth-child(1) { width: 18%; }
        .sadmin-institute-main .domain-request-table th:nth-child(2) { width: 22%; }
        .sadmin-institute-main .domain-request-table th:nth-child(3) { width: 20%; }
        .sadmin-institute-main .domain-request-table th:nth-child(4) { width: 10%; }
        .sadmin-institute-main .domain-request-table th:nth-child(5) { width: 20%; }
        .sadmin-institute-main .domain-request-table th:nth-child(6) { width: 10%; text-align: center; }
        .sadmin-institute-main .domain-request-table td:nth-child(6) { text-align: center; }
        .sadmin-institute-main .domain-action-modal .tenant-action-panel { width: min(760px, 96vw); }
        .sadmin-institute-main .domain-manage-form { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: .65rem; padding: .85rem; }
        .sadmin-institute-main .domain-manage-form .tenant-summary-grid { grid-column: 1 / -1; }
        .sadmin-institute-main .domain-manage-form .span-2 { grid-column: 1 / -1; }
        @media (max-width: 991.98px) {
            .sadmin-institute-main .domain-summary-grid,
            .sadmin-institute-main .domain-request-form,
            .sadmin-institute-main .domain-manage-form { grid-template-columns: 1fr; }
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
            const openButton = event.target.closest('[data-domain-modal]');
            if (openButton) {
                const modal = document.getElementById(openButton.dataset.domainModal);
                if (modal) {
                    modal.classList.add('open');
                    modal.setAttribute('aria-hidden', 'false');
                }
                return;
            }
            if (event.target.closest('[data-domain-close]')) {
                closeModal(event.target.closest('.domain-action-modal'));
                return;
            }
            if (event.target.classList.contains('domain-action-modal')) {
                closeModal(event.target);
            }
        });
        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape') {
                document.querySelectorAll('.domain-action-modal.open').forEach(closeModal);
            }
        });
    })();
    </script>
</main>
<?php include __DIR__ . '/includes/footer.php'; ?>
