<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/sadmin_staff.php';

$user = require_login('superadmin');
sadmin_staff_ensure_tables();
$message = $_SESSION['sadmin_assign_message'] ?? '';
$error = $_SESSION['sadmin_assign_error'] ?? '';
unset($_SESSION['sadmin_assign_message'], $_SESSION['sadmin_assign_error']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['csrf_token'] ?? null)) {
        $_SESSION['sadmin_assign_error'] = 'Security token expired.';
    } else {
        try {
            $staffId = (int) ($_POST['staff_user_id'] ?? 0);
            $institutionId = (int) ($_POST['institution_account_id'] ?? 0);
            $assignmentRole = in_array($_POST['assignment_role'] ?? '', ['support_manager','implementation','calling','sales','billing','academic','account_manager'], true) ? (string) $_POST['assignment_role'] : 'support_manager';
            $status = in_array($_POST['status'] ?? '', ['active','paused','closed'], true) ? (string) $_POST['status'] : 'active';
            $notes = substr(trim((string) ($_POST['notes'] ?? '')), 0, 1000);
            if ($staffId <= 0 || $institutionId <= 0) {
                throw new RuntimeException('Staff and institute are required.');
            }
            $stmt = db()->prepare("INSERT INTO sadmin_staff_institute_assignments (staff_user_id, institution_account_id, assignment_role, status, assigned_by, notes)
                VALUES (?, ?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE status = VALUES(status), assigned_by = VALUES(assigned_by), notes = VALUES(notes)");
            $adminId = (int) ($user['id'] ?? 0);
            $stmt->bind_param('iissis', $staffId, $institutionId, $assignmentRole, $status, $adminId, $notes);
            $stmt->execute();
            $_SESSION['sadmin_assign_message'] = 'Institute assignment saved.';
        } catch (Throwable $e) {
            $_SESSION['sadmin_assign_error'] = $e->getMessage();
        }
    }
    redirect('sadmin/staff-assignments');
}

$staffRows = sadmin_staff_rows();
$assignments = sadmin_staff_assignment_rows();
$institutes = db()->query("SELECT id, institution_name, institution_type, mobile, email FROM institution_accounts ORDER BY id DESC LIMIT 500")->fetch_all(MYSQLI_ASSOC);
$activePage = 'staff-assignments';
$pageTitle = 'Institute Assignments';
$pageSubtitle = 'Assign support managers, calling, sales and billing staff to institutes.';
?>
<?php include __DIR__ . '/includes/header.php'; ?>
<?php include __DIR__ . '/includes/sidebar.php'; ?>
<main class="main sadmin-institute-main">
    <?php include __DIR__ . '/includes/topbar.php'; ?>
    <section class="content institute-admin-page">
        <?php if ($message): ?><div class="flash success"><?= h($message); ?></div><?php endif; ?>
        <?php if ($error): ?><div class="flash danger"><?= h($error); ?></div><?php endif; ?>
        <section class="card custom-card institute-register-card">
            <div class="card-header"><div><span class="text-primary fs-11 fw-semibold text-uppercase d-block mb-1">Assignment Center</span><h6 class="mb-1 fw-semibold">Assign institute manager/support</h6></div></div>
            <div class="card-body">
                <form method="post" class="gr-compact-form">
                    <input type="hidden" name="csrf_token" value="<?= h(csrf_token()); ?>">
                    <label><span>Staff</span><select class="form-control" name="staff_user_id" required><option value="">Select staff</option><?php foreach ($staffRows as $staff): ?><option value="<?= (int) $staff['id']; ?>"><?= h($staff['full_name'] . ' / ' . $staff['role_name']); ?></option><?php endforeach; ?></select></label>
                    <label><span>Institute</span><select class="form-control" name="institution_account_id" required><option value="">Select institute</option><?php foreach ($institutes as $inst): ?><option value="<?= (int) $inst['id']; ?>"><?= h($inst['institution_name'] . ' / ' . $inst['institution_type']); ?></option><?php endforeach; ?></select></label>
                    <label><span>Work Type</span><select class="form-control" name="assignment_role"><option value="support_manager">Support Manager</option><option value="implementation">Implementation</option><option value="calling">Calling</option><option value="sales">Sales</option><option value="billing">Billing</option><option value="academic">Academic</option><option value="account_manager">Account Manager</option></select></label>
                    <label><span>Status</span><select class="form-control" name="status"><option value="active">Active</option><option value="paused">Paused</option><option value="closed">Closed</option></select></label>
                    <label class="span-2"><span>Notes</span><input class="form-control" name="notes" placeholder="Support scope, SLA or onboarding note"></label>
                    <button class="btn btn-primary btn-wave" type="submit">Save Assignment</button>
                </form>
            </div>
        </section>
        <section class="card custom-card institute-register-card">
            <div class="card-header"><div><span class="text-primary fs-11 fw-semibold text-uppercase d-block mb-1">Assigned Accounts</span><h6 class="mb-1 fw-semibold"><?= count($assignments); ?> assignments</h6></div></div>
            <div class="card-body p-0">
                <table class="table table-hover align-middle mb-0 gr-register-table">
                    <thead><tr><th>Institute</th><th>Staff</th><th>Work</th><th>ERP</th><th>Status</th></tr></thead>
                    <tbody>
                    <?php foreach ($assignments as $row): ?>
                        <tr>
                            <td><span class="gr-cell-title"><?= h($row['institution_name']); ?></span><span class="gr-cell-subtitle"><?= h($row['institution_type'] . ' | ' . $row['mobile']); ?></span></td>
                            <td><span class="gr-cell-title"><?= h($row['staff_name']); ?></span><span class="gr-cell-subtitle"><?= h($row['role_name']); ?></span></td>
                            <td><?= h(ucwords(str_replace('_', ' ', (string) $row['assignment_role']))); ?></td>
                            <td><span class="gr-cell-title"><?= h($row['erp_base_path'] ?: 'Not installed'); ?></span><span class="gr-cell-subtitle"><?= h(ucwords(str_replace('_', ' ', (string) ($row['setup_status'] ?: 'queued')))); ?></span></td>
                            <td><span class="edu-status <?= $row['status'] === 'active' ? 'success' : 'pending'; ?>"><?= h(ucfirst((string) $row['status'])); ?></span></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>
    </section>
</main>
<?php include __DIR__ . '/includes/footer.php'; ?>
