<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/sadmin_staff.php';

$user = require_login('superadmin');
sadmin_staff_ensure_tables();
$message = $_SESSION['sadmin_staff_message'] ?? '';
$error = $_SESSION['sadmin_staff_error'] ?? '';
unset($_SESSION['sadmin_staff_message'], $_SESSION['sadmin_staff_error']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['csrf_token'] ?? null)) {
        $_SESSION['sadmin_staff_error'] = 'Security token expired.';
    } else {
        try {
            sadmin_staff_create_or_update($_POST);
            $_SESSION['sadmin_staff_message'] = 'Staff profile saved.';
        } catch (Throwable $e) {
            $_SESSION['sadmin_staff_error'] = $e->getMessage();
        }
    }
    redirect('sadmin/staff');
}

$roles = sadmin_staff_roles();
$staffRows = sadmin_staff_rows();
$managers = array_values(array_filter($staffRows, static fn(array $row): bool => in_array((string) $row['role_slug'], ['superadmin', 'operations-manager', 'erp-support-manager'], true)));
$activePage = 'staff';
$pageTitle = 'Staff & Roles';
$pageSubtitle = 'Role-wise team, permissions and staff profiles.';
?>
<?php include __DIR__ . '/includes/header.php'; ?>
<?php include __DIR__ . '/includes/sidebar.php'; ?>
<main class="main sadmin-institute-main">
    <?php include __DIR__ . '/includes/topbar.php'; ?>
    <section class="content institute-admin-page">
        <?php if ($message): ?><div class="flash success"><?= h($message); ?></div><?php endif; ?>
        <?php if ($error): ?><div class="flash danger"><?= h($error); ?></div><?php endif; ?>

        <section class="card custom-card institute-register-card">
            <div class="card-header justify-content-between">
                <div>
                    <span class="text-primary fs-11 fw-semibold text-uppercase d-block mb-1">Team Setup</span>
                    <h6 class="mb-1 fw-semibold">Add role-wise staff</h6>
                    <p class="mb-0 text-muted fs-12">Create managers, calling, support, sales, billing and academic staff. Detailed permissions remain controlled from role settings.</p>
                </div>
            </div>
            <div class="card-body">
                <form method="post" class="gr-compact-form">
                    <input type="hidden" name="csrf_token" value="<?= h(csrf_token()); ?>">
                    <label><span>Full Name</span><input class="form-control" name="full_name" required></label>
                    <label><span>Email</span><input class="form-control" type="email" name="email" required></label>
                    <label><span>Mobile</span><input class="form-control" name="phone"></label>
                    <label><span>Username</span><input class="form-control" name="username" placeholder="Auto: email"></label>
                    <label><span>Role</span><select class="form-control" name="role_id" required><option value="">Select role</option><?php foreach ($roles as $role): ?><option value="<?= (int) $role['id']; ?>"><?= h($role['name']); ?></option><?php endforeach; ?></select></label>
                    <label><span>Department</span><select class="form-control" name="department"><option value="support">Support</option><option value="calling">Calling</option><option value="sales">Sales</option><option value="billing">Billing</option><option value="academic">Academic</option><option value="management">Management</option><option value="operations">Operations</option></select></label>
                    <label><span>Designation</span><input class="form-control" name="designation" placeholder="Support Manager"></label>
                    <label><span>Manager</span><select class="form-control" name="manager_user_id"><option value="">No manager</option><?php foreach ($managers as $manager): ?><option value="<?= (int) $manager['id']; ?>"><?= h($manager['full_name']); ?></option><?php endforeach; ?></select></label>
                    <label><span>Employee Code</span><input class="form-control" name="employee_code"></label>
                    <label><span>Password</span><input class="form-control" name="password" placeholder="Default: Staff@12345"></label>
                    <label><span>Status</span><select class="form-control" name="status"><option value="active">Active</option><option value="pending">Pending</option><option value="inactive">Inactive</option><option value="blocked">Blocked</option></select></label>
                    <div class="gr-check-row">
                        <label><input type="checkbox" name="can_manage_school" value="1"> School</label>
                        <label><input type="checkbox" name="can_manage_degree" value="1"> Degree College</label>
                        <label><input type="checkbox" name="can_manage_institute" value="1"> Institute</label>
                    </div>
                    <label class="span-3"><span>Notes</span><input class="form-control" name="notes" placeholder="Internal remarks"></label>
                    <button class="btn btn-primary btn-wave" type="submit">Save Staff</button>
                </form>
            </div>
        </section>

        <section class="card custom-card institute-register-card">
            <div class="card-header justify-content-between">
                <div>
                    <span class="text-primary fs-11 fw-semibold text-uppercase d-block mb-1">Staff Directory</span>
                    <h6 class="mb-1 fw-semibold"><?= count($staffRows); ?> staff accounts</h6>
                </div>
            </div>
            <div class="card-body p-0">
                <table class="table table-hover align-middle mb-0 gr-register-table">
                    <thead><tr><th>Staff</th><th>Role</th><th>Manager</th><th>Assignments</th><th>Leads</th><th>Status</th></tr></thead>
                    <tbody>
                    <?php foreach ($staffRows as $row): ?>
                        <tr>
                            <td><span class="gr-cell-title"><?= h($row['full_name']); ?></span><span class="gr-cell-subtitle"><?= h(($row['email'] ?? '') . ' | ' . ($row['phone'] ?? '')); ?></span></td>
                            <td><span class="gr-cell-title"><?= h($row['role_name']); ?></span><span class="gr-cell-subtitle"><?= h((string) ($row['designation'] ?: $row['department'])); ?></span></td>
                            <td><?= h((string) ($row['manager_name'] ?: 'Direct')); ?></td>
                            <td><?= (int) $row['assigned_institutes']; ?></td>
                            <td><?= (int) $row['paid_leads']; ?> paid / <?= (int) $row['total_leads']; ?> total</td>
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
