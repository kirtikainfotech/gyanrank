<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/institution_module.php';
require_once __DIR__ . '/includes/institute_master.php';

$user = require_login('superadmin');
institution_ensure_tables();
institute_handle_master_post('district', 'sadmin/institute-districts');

$rows = db()->query('SELECT d.id, d.name, d.state_id, d.status, d.created_at, s.name AS state_name FROM institution_districts d LEFT JOIN institution_states s ON s.id = d.state_id ORDER BY d.name ASC LIMIT 1000')->fetch_all(MYSQLI_ASSOC);
[$message, $error] = institute_master_flash();
$pageTitle = 'Institute Districts';
$pageSubtitle = 'Manage city and district master records.';
$activePage = 'institute-districts';
?>
<?php include __DIR__ . '/includes/header.php'; ?>
<?php include __DIR__ . '/includes/sidebar.php'; ?>
<main class="main sadmin-institute-main">
    <?php include __DIR__ . '/includes/topbar.php'; ?>
    <section class="content settings-page institute-admin-page">
        <?php institute_master_nav('districts'); ?>
        <?php if ($message): ?><div class="notice"><?= h($message); ?></div><?php endif; ?>
        <?php if ($error): ?><div class="notice danger"><?= h($error); ?></div><?php endif; ?>
        <section class="settings-summary-table institute-master-table">
            <div class="table-head">
                <div><span>Districts / Cities</span><h2><?= count($rows); ?> latest records</h2><small>Registration dropdown uses a global city/district list.</small></div>
                <a class="modal-button" href="#add-master">Add District</a>
            </div>
            <div class="settings-mini-table">
                <table>
                    <thead><tr><th>ID</th><th>Name</th><th>Internal State</th><th>Status</th><th>Action</th></tr></thead>
                    <tbody>
                    <?php foreach ($rows as $row): ?>
                        <tr>
                            <td>#<?= (int) $row['id']; ?></td>
                            <td><strong><?= h($row['name']); ?></strong></td>
                            <td><?= h((string) ($row['state_name'] ?? '')); ?></td>
                            <td><?= institute_master_status_badge((int) $row['status']); ?></td>
                            <td><a class="table-edit-icon" href="#edit-master" data-master-edit data-id="<?= (int) $row['id']; ?>" data-name="<?= h($row['name']); ?>" data-state-id="<?= (int) $row['state_id']; ?>" data-status="<?= (int) $row['status']; ?>">Edit</a></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>
    </section>
    <div id="add-master" class="modal-overlay">
        <form class="modal-box compact-master-modal" method="post">
            <input type="hidden" name="csrf_token" value="<?= h(csrf_token()); ?>">
            <div class="modal-head"><h2>Add District</h2><a href="#" aria-label="Close">&times;</a></div>
            <div class="form-grid"><label>Internal State<select name="state_id" required><?= institute_state_options(); ?></select></label><label>City / District Name<input name="name" required></label><label class="switch-field">Active<span><input type="checkbox" name="status" value="1" checked><b></b></span></label></div>
            <div class="modal-actions"><button type="submit">Save</button></div>
        </form>
    </div>
    <div id="edit-master" class="modal-overlay">
        <form class="modal-box compact-master-modal" method="post">
            <input type="hidden" name="csrf_token" value="<?= h(csrf_token()); ?>">
            <input type="hidden" name="id" value="">
            <div class="modal-head"><h2>Edit District</h2><a href="#" aria-label="Close">&times;</a></div>
            <div class="form-grid"><label>Internal State<select name="state_id" required><?= institute_state_options(); ?></select></label><label>City / District Name<input name="name" required></label><label class="switch-field">Active<span><input type="checkbox" name="status" value="1"><b></b></span></label></div>
            <div class="modal-actions"><button type="submit">Update</button></div>
        </form>
    </div>
</main>
<?php institute_master_page_styles(); ?>
<?php institute_master_edit_script(); ?>
<?php include __DIR__ . '/includes/footer.php'; ?>
