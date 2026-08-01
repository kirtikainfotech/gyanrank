<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/includes/settings_helpers.php';

$user = require_login('superadmin');
$pageTitle = 'Staff & Roles';
$pageSubtitle = 'Role hierarchy, support assignment, salary, attendance and reporting controls.';
$activePage = 'settings';
$permissionGroups = role_permission_groups();

function selected_attr(string $current, string $value): string
{
    return $current === $value ? ' selected' : '';
}

function role_options(array $roles, string $selected, bool $includeBlank = false): string
{
    $html = $includeBlank ? '<option value="">No parent</option>' : '';
    foreach ($roles as $role) {
        $html .= '<option value="' . h((string) $role['slug']) . '"' . selected_attr($selected, (string) $role['slug']) . '>' . h($role['name']) . '</option>';
    }
    return $html;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['csrf_token'] ?? null)) {
        $_SESSION['settings_error'] = 'Security token expired.';
        redirect('sadmin/settings-staff');
    }

    try {
        if (($_POST['form_type'] ?? '') === 'role') {
            save_role_with_permissions(
                ($_POST['role_id'] ?? '') !== '' ? (int) $_POST['role_id'] : null,
                substr(trim((string) $_POST['role_name']), 0, 80),
                ($_POST['role_parent_id'] ?? '') !== '' ? (int) $_POST['role_parent_id'] : null,
                substr(trim((string) ($_POST['role_group'] ?? 'staff')), 0, 40),
                substr(trim((string) ($_POST['role_description'] ?? '')), 0, 255),
                (array) ($_POST['role_permissions'] ?? [])
            );
            $_SESSION['settings_message'] = 'Role saved successfully.';
        } else {
            save_setting('support_auto_assign_enabled', isset($_POST['support_auto_assign_enabled']) ? '1' : '0');
            save_settings_keys([
                'default_role',
                'default_instructor_support_role',
                'default_student_support_role',
                'support_assignment_mode',
                'max_students_per_support',
                'max_instructors_per_support',
                'support_escalation_hours',
                'instructor_commission_type',
                'instructor_commission_value',
                'salary_cycle',
                'salary_pay_day',
                'probation_days',
                'monthly_paid_leaves',
                'attendance_mode',
                'attendance_grace_minutes',
                'half_day_after_minutes',
                'report_approval_flow',
                'report_timezone',
            ]);
            $_SESSION['settings_message'] = 'Staff controls updated.';
        }
    } catch (Throwable $e) {
        $_SESSION['settings_error'] = $e->getMessage();
    }

    redirect('sadmin/settings-staff');
}

$settings = all_settings();
$roles = fetch_roles();
$roleById = [];
$roleCounts = [];
foreach ($roles as $role) {
    $roleById[(int) $role['id']] = $role;
    $group = (string) ($role['role_group'] ?? 'staff');
    $roleCounts[$group] = ($roleCounts[$group] ?? 0) + 1;
}
[$message, $error] = settings_flash();
?>
<?php include __DIR__ . '/includes/header.php'; ?>
<?php include __DIR__ . '/includes/sidebar.php'; ?>
<main class="main">
    <?php include __DIR__ . '/includes/topbar.php'; ?>
    <section class="content settings-page">
        <?php render_settings_submenu('staff'); ?>
        <?php if ($message): ?><div class="notice"><?= h($message); ?></div><?php endif; ?>
        <?php if ($error): ?><div class="notice danger"><?= h($error); ?></div><?php endif; ?>

        <section class="settings-detail-card">
            <div class="detail-head">
                <div>
                    <span>Staff Control</span>
                    <h2><?= count($roles); ?> roles configured</h2>
                    <p>Fixed support assignment, salary cycle, attendance rules and role permissions are controlled here.</p>
                </div>
                <div class="action-row">
                    <a class="modal-button ghost" href="#edit-controls">Edit Defaults</a>
                    <a class="modal-button" href="#role-new">Add Role</a>
                </div>
            </div>

            <div class="staff-summary-grid">
                <div><strong>Auto Assign</strong><b><?= $settings['support_auto_assign_enabled'] === '1' ? 'Enabled' : 'Disabled'; ?></b></div>
                <div><strong>Student Capacity</strong><b><?= h($settings['max_students_per_support']); ?>/staff</b></div>
                <div><strong>Instructor Capacity</strong><b><?= h($settings['max_instructors_per_support']); ?>/staff</b></div>
                <div><strong>Escalation</strong><b><?= h($settings['support_escalation_hours']); ?> hrs</b></div>
            </div>

            <table class="detail-table">
                <tbody>
                    <tr><th>Default Role</th><td><?= h($settings['default_role']); ?></td><th>Assignment Mode</th><td><?= h($settings['support_assignment_mode']); ?></td></tr>
                    <tr><th>Instructor Support</th><td><?= h($settings['default_instructor_support_role']); ?></td><th>Student Support</th><td><?= h($settings['default_student_support_role']); ?></td></tr>
                    <tr><th>Commission</th><td><?= h($settings['instructor_commission_value'] . ' ' . $settings['instructor_commission_type']); ?></td><th>Salary Cycle</th><td><?= h($settings['salary_cycle'] . ', pay day ' . $settings['salary_pay_day']); ?></td></tr>
                    <tr><th>Probation</th><td><?= h($settings['probation_days']); ?> days</td><th>Paid Leaves</th><td><?= h($settings['monthly_paid_leaves']); ?>/month</td></tr>
                    <tr><th>Attendance</th><td><?= h($settings['attendance_mode']); ?></td><th>Grace / Half Day</th><td><?= h($settings['attendance_grace_minutes']); ?> min / <?= h($settings['half_day_after_minutes']); ?> min</td></tr>
                    <tr><th>Report Approval</th><td><?= h($settings['report_approval_flow']); ?></td><th>Timezone</th><td><?= h($settings['report_timezone']); ?></td></tr>
                </tbody>
            </table>
        </section>

        <section class="settings-detail-card compact-section">
            <div class="detail-head">
                <div>
                    <span>Role Hierarchy</span>
                    <h2>Permission based staff access</h2>
                    <p>Parent-child roles support reporting structure and limited access per department.</p>
                </div>
            </div>
            <div class="role-group-strip">
                <?php foreach ($roleCounts as $group => $count): ?>
                    <span><?= h(ucfirst($group)); ?> <b><?= (int) $count; ?></b></span>
                <?php endforeach; ?>
            </div>
            <div class="role-table-wrap">
                <table class="role-access-table smart-table">
                    <thead>
                        <tr>
                            <th>Role</th>
                            <th>Parent</th>
                            <th>Type</th>
                            <th>Permissions</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($roles as $role): ?>
                            <?php
                            $parentName = $role['parent_id'] && isset($roleById[(int) $role['parent_id']]) ? $roleById[(int) $role['parent_id']]['name'] : 'Root';
                            $permissions = array_filter(explode(',', (string) ($role['permissions'] ?? '')));
                            $roleGroup = (string) ($role['role_group'] ?? 'staff');
                            ?>
                            <tr>
                                <td><strong><?= h($role['name']); ?></strong><small><?= h($role['description'] ?: 'No description'); ?></small></td>
                                <td><?= h($parentName); ?></td>
                                <td><span class="role-type-pill"><?= h(ucfirst($roleGroup)); ?></span></td>
                                <td><?= count($permissions); ?> permissions</td>
                                <td><a class="table-edit-icon" href="#role-<?= (int) $role['id']; ?>" aria-label="Edit <?= h($role['name']); ?>">✎</a></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>
    </section>

    <div id="edit-controls" class="modal-overlay">
        <form class="modal-box wide-modal" method="post">
            <input type="hidden" name="csrf_token" value="<?= h(csrf_token()); ?>">
            <input type="hidden" name="form_type" value="controls">
            <div class="modal-head"><h2>Edit Staff Defaults</h2><a class="modal-close" href="#" aria-label="Close">×</a></div>
            <div class="form-grid">
                <label>Default Role<select name="default_role"><?= role_options($roles, $settings['default_role']); ?></select></label>
                <label>Support Assignment<select name="support_assignment_mode"><option value="manual"<?= selected_attr($settings['support_assignment_mode'], 'manual'); ?>>Manual</option><option value="round_robin"<?= selected_attr($settings['support_assignment_mode'], 'round_robin'); ?>>Round Robin</option><option value="role_based"<?= selected_attr($settings['support_assignment_mode'], 'role_based'); ?>>Role Based</option><option value="load_balanced"<?= selected_attr($settings['support_assignment_mode'], 'load_balanced'); ?>>Load Balanced</option></select></label>
                <label class="switch-field">Auto Assign<span><input type="checkbox" name="support_auto_assign_enabled" value="1" <?= $settings['support_auto_assign_enabled'] === '1' ? 'checked' : ''; ?>><b></b></span></label>
                <label>Instructor Support Role<select name="default_instructor_support_role"><?= role_options($roles, $settings['default_instructor_support_role']); ?></select></label>
                <label>Student Support Role<select name="default_student_support_role"><?= role_options($roles, $settings['default_student_support_role']); ?></select></label>
                <label>Max Students / Staff<input type="number" min="1" name="max_students_per_support" value="<?= setting_value($settings, 'max_students_per_support'); ?>"></label>
                <label>Max Instructors / Staff<input type="number" min="1" name="max_instructors_per_support" value="<?= setting_value($settings, 'max_instructors_per_support'); ?>"></label>
                <label>Escalation Hours<input type="number" min="1" name="support_escalation_hours" value="<?= setting_value($settings, 'support_escalation_hours'); ?>"></label>
                <label>Commission Type<select name="instructor_commission_type"><option value="percent"<?= selected_attr($settings['instructor_commission_type'], 'percent'); ?>>Percent</option><option value="fixed"<?= selected_attr($settings['instructor_commission_type'], 'fixed'); ?>>Fixed</option></select></label>
                <label>Commission Value<input type="number" step="0.01" min="0" name="instructor_commission_value" value="<?= setting_value($settings, 'instructor_commission_value'); ?>"></label>
                <label>Salary Cycle<select name="salary_cycle"><option value="monthly"<?= selected_attr($settings['salary_cycle'], 'monthly'); ?>>Monthly</option><option value="weekly"<?= selected_attr($settings['salary_cycle'], 'weekly'); ?>>Weekly</option><option value="biweekly"<?= selected_attr($settings['salary_cycle'], 'biweekly'); ?>>Biweekly</option></select></label>
                <label>Salary Pay Day<input type="number" min="1" max="31" name="salary_pay_day" value="<?= setting_value($settings, 'salary_pay_day'); ?>"></label>
                <label>Probation Days<input type="number" min="0" name="probation_days" value="<?= setting_value($settings, 'probation_days'); ?>"></label>
                <label>Monthly Paid Leaves<input type="number" min="0" step="0.5" name="monthly_paid_leaves" value="<?= setting_value($settings, 'monthly_paid_leaves'); ?>"></label>
                <label>Attendance Mode<select name="attendance_mode"><option value="daily"<?= selected_attr($settings['attendance_mode'], 'daily'); ?>>Daily</option><option value="shift"<?= selected_attr($settings['attendance_mode'], 'shift'); ?>>Shift Wise</option><option value="biometric"<?= selected_attr($settings['attendance_mode'], 'biometric'); ?>>Biometric</option><option value="app_checkin"<?= selected_attr($settings['attendance_mode'], 'app_checkin'); ?>>App Check-in</option></select></label>
                <label>Grace Minutes<input type="number" min="0" name="attendance_grace_minutes" value="<?= setting_value($settings, 'attendance_grace_minutes'); ?>"></label>
                <label>Half Day After Minutes<input type="number" min="0" name="half_day_after_minutes" value="<?= setting_value($settings, 'half_day_after_minutes'); ?>"></label>
                <label>Report Approval<select name="report_approval_flow"><option value="none"<?= selected_attr($settings['report_approval_flow'], 'none'); ?>>No Approval</option><option value="manager"<?= selected_attr($settings['report_approval_flow'], 'manager'); ?>>Manager Approval</option><option value="hr"<?= selected_attr($settings['report_approval_flow'], 'hr'); ?>>HR Approval</option><option value="multi_level"<?= selected_attr($settings['report_approval_flow'], 'multi_level'); ?>>Multi Level</option></select></label>
                <label>Report Timezone<input name="report_timezone" value="<?= setting_value($settings, 'report_timezone'); ?>"></label>
            </div>
            <div class="modal-actions"><button type="submit">Save Defaults</button></div>
        </form>
    </div>

    <?php
    $modalRoles = array_merge([['id' => '', 'name' => '', 'parent_id' => '', 'role_group' => 'staff', 'description' => '', 'permissions' => '']], $roles);
    foreach ($modalRoles as $modalRole):
        $modalId = $modalRole['id'] === '' ? 'new' : (int) $modalRole['id'];
        $selectedPermissions = array_filter(explode(',', (string) ($modalRole['permissions'] ?? '')));
    ?>
        <div id="role-<?= h((string) $modalId); ?>" class="modal-overlay">
            <form class="modal-box wide-modal" method="post">
                <input type="hidden" name="csrf_token" value="<?= h(csrf_token()); ?>">
                <input type="hidden" name="form_type" value="role">
                <input type="hidden" name="role_id" value="<?= h((string) $modalRole['id']); ?>">
                <div class="modal-head"><h2><?= $modalRole['id'] === '' ? 'Add Role' : 'Edit Role'; ?></h2><a class="modal-close" href="#" aria-label="Close">×</a></div>
                <div class="form-grid">
                    <label>Role Name<input name="role_name" value="<?= h($modalRole['name']); ?>" required></label>
                    <label>Parent Role<select name="role_parent_id"><option value="">No parent</option><?php foreach ($roles as $role): if ((string) $role['id'] === (string) $modalRole['id']) continue; ?><option value="<?= (int) $role['id']; ?>" <?= (string) $modalRole['parent_id'] === (string) $role['id'] ? 'selected' : ''; ?>><?= h($role['name']); ?></option><?php endforeach; ?></select></label>
                    <label>Role Type<select name="role_group"><?php foreach (['management','staff','support','academic','finance','learner','calling','hr'] as $group): ?><option value="<?= h($group); ?>" <?= $modalRole['role_group'] === $group ? 'selected' : ''; ?>><?= h(ucfirst($group)); ?></option><?php endforeach; ?></select></label>
                    <label>Description<input name="role_description" value="<?= h($modalRole['description']); ?>"></label>
                </div>
                <div class="permission-matrix">
                    <?php foreach ($permissionGroups as $groupName => $permissions): ?>
                        <div class="permission-group">
                            <h3><?= h($groupName); ?></h3>
                            <?php foreach ($permissions as $key => $label): ?>
                                <label class="check-pill"><input type="checkbox" name="role_permissions[]" value="<?= h($key); ?>" <?= in_array($key, $selectedPermissions, true) ? 'checked' : ''; ?>><span><?= h($label); ?></span></label>
                            <?php endforeach; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
                <div class="modal-actions"><button type="submit">Save Role</button></div>
            </form>
        </div>
    <?php endforeach; ?>
<?php include __DIR__ . '/includes/footer.php'; ?>

