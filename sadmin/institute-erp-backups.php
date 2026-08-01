<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/institute_erp.php';
require_once __DIR__ . '/includes/institute_master.php';

$user = require_login('superadmin');
institute_erp_ensure_tables();

function erp_backup_size_label(int $bytes): string
{
    if ($bytes >= 1048576) {
        return number_format($bytes / 1048576, 2) . ' MB';
    }
    if ($bytes >= 1024) {
        return number_format($bytes / 1024, 1) . ' KB';
    }
    return $bytes . ' B';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['csrf_token'] ?? null)) {
        $_SESSION['institute_master_error'] = 'Security token expired.';
        redirect('sadmin/institute-erp-backups');
    }

    $tenantId = (int) ($_POST['tenant_id'] ?? 0);
    $action = (string) ($_POST['action'] ?? '');
    try {
        if ($tenantId <= 0) {
            throw new RuntimeException('Select ERP tenant first.');
        }

        if ($action === 'create_backup') {
            $result = institute_erp_backup_tenant_database($tenantId, (int) ($user['id'] ?? 0));
            audit_log('erp_tenant_backup', 'institution_erp_tenant', (string) $tenantId, ['ok' => (bool) ($result['ok'] ?? false), 'path' => $result['path'] ?? '', 'backup_id' => $result['backup_id'] ?? 0], $user);
        } elseif ($action === 'restore_backup') {
            $backupId = (int) ($_POST['backup_id'] ?? 0);
            $confirmation = (string) ($_POST['restore_confirmation'] ?? '');
            $result = institute_erp_restore_tenant_database($tenantId, $backupId, $confirmation, (int) ($user['id'] ?? 0));
            audit_log('erp_tenant_restore', 'institution_erp_tenant', (string) $tenantId, ['ok' => (bool) ($result['ok'] ?? false), 'backup_id' => $backupId, 'message' => $result['message'] ?? ''], $user);
        } else {
            throw new RuntimeException('Invalid backup action.');
        }

        $_SESSION['institute_master_' . (!empty($result['ok']) ? 'message' : 'error')] = (string) ($result['message'] ?? '');
    } catch (Throwable $e) {
        $_SESSION['institute_master_error'] = $e->getMessage();
    }
    redirect('sadmin/institute-erp-backups');
}

$tenants = db()->query("SELECT t.id, t.tenant_code, t.erp_db_name, t.erp_status, t.setup_status, a.institution_name
    FROM institution_erp_tenants t
    INNER JOIN institution_accounts a ON a.id = t.institution_account_id
    WHERE t.setup_status = 'installed'
    ORDER BY a.institution_name ASC, t.id DESC")->fetch_all(MYSQLI_ASSOC);
$backups = institute_erp_backup_rows();
[$message, $error] = institute_master_flash();

$pageTitle = 'ERP Backups';
$pageSubtitle = 'Tenant database backups with guarded restore control.';
$activePage = 'institute-erp-backups';
?>
<?php include __DIR__ . '/includes/header.php'; ?>
<?php include __DIR__ . '/includes/sidebar.php'; ?>
<main class="main sadmin-institute-main">
    <?php include __DIR__ . '/includes/topbar.php'; ?>
    <section class="content institute-admin-page">
        <?php institute_master_nav('erp-backups'); ?>
        <?php if ($message !== ''): ?><div class="flash success"><?= h($message); ?></div><?php endif; ?>
        <?php if ($error !== ''): ?><div class="flash error"><?= h($error); ?></div><?php endif; ?>

        <div class="card-box institute-card">
            <div class="settings-section-head">
                <div>
                    <h2>Create Tenant Backup</h2>
                    <p>Take a fresh SQL backup before renewals, upgrades or tenant-level changes.</p>
                </div>
            </div>
            <form method="post" class="settings-form-grid">
                <input type="hidden" name="csrf_token" value="<?= h(csrf_token()); ?>">
                <div class="form-group">
                    <label>ERP Tenant</label>
                    <select name="tenant_id" class="form-control" required>
                        <option value="">Select tenant</option>
                        <?php foreach ($tenants as $tenant): ?>
                            <option value="<?= (int) $tenant['id']; ?>">
                                <?= h($tenant['institution_name'] . ' / ' . $tenant['tenant_code']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Status</label>
                    <input class="form-control" value="Backup will be stored under database_exports/erp" readonly>
                </div>
                <div class="form-actions">
                    <button class="btn btn-primary" type="submit" name="action" value="create_backup">Create Backup</button>
                </div>
            </form>
        </div>

        <div class="card-box institute-card">
            <div class="settings-section-head">
                <div>
                    <h2>Backup History</h2>
                    <p>Restore requires exact tenant confirmation and creates a pre-restore backup first.</p>
                </div>
            </div>
            <div class="table-responsive">
                <table class="edu-table">
                    <thead>
                        <tr>
                            <th>Tenant</th>
                            <th>File</th>
                            <th>Size</th>
                            <th>Status</th>
                            <th>Created</th>
                            <th>Restore Guard</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!$backups): ?>
                            <tr><td colspan="6">No ERP backup found.</td></tr>
                        <?php endif; ?>
                        <?php foreach ($backups as $backup): ?>
                            <?php $restoreText = 'RESTORE ' . (string) $backup['tenant_code']; ?>
                            <tr>
                                <td>
                                    <span class="gr-cell-title"><?= h($backup['institution_name']); ?></span>
                                    <span class="gr-cell-subtitle"><?= h($backup['tenant_code'] . ' / ' . $backup['erp_db_name']); ?></span>
                                </td>
                                <td>
                                    <span class="gr-cell-title"><?= h(basename((string) $backup['backup_path'])); ?></span>
                                    <span class="gr-cell-subtitle"><?= h($backup['note'] ?? ''); ?></span>
                                </td>
                                <td><?= h(erp_backup_size_label((int) $backup['file_size'])); ?></td>
                                <td><span class="edu-status <?= h($backup['status'] === 'restored' ? 'pending' : 'success'); ?>"><?= h(ucfirst((string) $backup['status'])); ?></span></td>
                                <td><?= h(date('d M Y, h:i A', strtotime((string) $backup['created_at']))); ?></td>
                                <td>
                                    <form method="post" class="tenant-renew-form" onsubmit="return confirm('Restore this ERP database backup?');">
                                        <input type="hidden" name="csrf_token" value="<?= h(csrf_token()); ?>">
                                        <input type="hidden" name="tenant_id" value="<?= (int) $backup['tenant_id']; ?>">
                                        <input type="hidden" name="backup_id" value="<?= (int) $backup['id']; ?>">
                                        <input class="form-control form-control-sm" name="restore_confirmation" placeholder="<?= h($restoreText); ?>" autocomplete="off">
                                        <button class="btn btn-sm btn-light btn-wave" name="action" value="restore_backup" type="submit">Restore</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </section>
</main>
<?php include __DIR__ . '/includes/footer.php'; ?>
