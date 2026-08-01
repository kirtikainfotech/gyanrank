<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/govt_exam_prep.php';

$user = require_login('superadmin');
gov_exam_ensure_tables();
$pageTitle = 'Govt Exam Categories';
$pageSubtitle = 'Category and subcategory master.';
$activePage = 'govt-prep-categories';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['csrf_token'] ?? null)) {
        $_SESSION['gov_exam_error'] = 'Security token expired.';
        redirect('sadmin/govt-prep-categories');
    }
    try {
        $id = (int) ($_POST['id'] ?? 0);
        $parentId = (int) ($_POST['parent_id'] ?? 0);
        $parentValue = $parentId > 0 ? $parentId : null;
        $name = substr(trim((string) ($_POST['name'] ?? '')), 0, 160);
        $description = substr(trim((string) ($_POST['description'] ?? '')), 0, 255);
        $status = in_array($_POST['status'] ?? '', ['active', 'inactive'], true) ? (string) $_POST['status'] : 'active';
        $sortOrder = max(1, (int) ($_POST['sort_order'] ?? 1));
        if ($name === '') throw new RuntimeException('Name required.');
        if ($id > 0 && $parentValue === $id) throw new RuntimeException('Category cannot be its own parent.');
        $slug = gov_slug($name);
        if ($id > 0) {
            $stmt = db()->prepare('UPDATE gov_exam_categories SET parent_id=?, name=?, slug=?, description=?, status=?, sort_order=? WHERE id=?');
            $stmt->bind_param('issssii', $parentValue, $name, $slug, $description, $status, $sortOrder, $id);
            $stmt->execute();
            $_SESSION['gov_exam_message'] = 'Category updated.';
        } else {
            $stmt = db()->prepare('INSERT INTO gov_exam_categories (parent_id, name, slug, description, status, sort_order) VALUES (?, ?, ?, ?, ?, ?)');
            $stmt->bind_param('issssi', $parentValue, $name, $slug, $description, $status, $sortOrder);
            $stmt->execute();
            $_SESSION['gov_exam_message'] = 'Category added.';
        }
    } catch (Throwable $e) {
        $_SESSION['gov_exam_error'] = $e->getMessage();
    }
    redirect('sadmin/govt-prep-categories');
}
$allRows = gov_exam_categories(false);
$totalRows = count($allRows);
$perPageOptions = [10, 25, 50];
$perPage = (int) ($_GET['per_page'] ?? 10);
if (!in_array($perPage, $perPageOptions, true)) {
    $perPage = 10;
}
$page = max(1, (int) ($_GET['page'] ?? 1));
$totalPages = max(1, (int) ceil($totalRows / $perPage));
if ($page > $totalPages) {
    $page = $totalPages;
}
$offset = ($page - 1) * $perPage;
$rows = array_slice($allRows, $offset, $perPage);
$pageStart = $totalRows > 0 ? $offset + 1 : 0;
$pageEnd = min($offset + $perPage, $totalRows);
$activeRows = count(array_filter($allRows, static fn(array $row): bool => (string) ($row['status'] ?? '') === 'active'));
$mainRows = count(array_filter($allRows, static fn(array $row): bool => empty($row['parent_id'])));
[$message, $error] = gov_exam_flash();
?>
<?php include __DIR__ . '/includes/header.php'; ?>
<?php include __DIR__ . '/includes/sidebar.php'; ?>
<main class="main sadmin-govcat-main">
<?php include __DIR__ . '/includes/topbar.php'; ?>
<section class="content govt-prep-page">
<?php if ($message): ?><div class="notice"><?= h($message); ?></div><?php endif; ?><?php if ($error): ?><div class="notice danger"><?= h($error); ?></div><?php endif; ?>
<section class="card custom-card govcat-hero-card">
    <div class="card-header justify-content-between">
        <div>
            <span class="text-primary fs-11 fw-semibold text-uppercase d-block mb-1">Govt Exam Prep</span>
            <h5 class="mb-1 fw-semibold">Categories</h5>
            <p class="mb-0 text-muted fs-12">Main category and subcategory for documents, live and mock tests.</p>
        </div>
        <a class="btn btn-primary btn-wave" href="#add-category">Add Category</a>
    </div>
    <div class="card-body">
        <div class="govcat-mini-stats">
            <article><span>Total</span><strong><?= h((string) $totalRows); ?></strong></article>
            <article><span>Active</span><strong><?= h((string) $activeRows); ?></strong></article>
            <article><span>Main</span><strong><?= h((string) $mainRows); ?></strong></article>
            <article><span>Sub</span><strong><?= h((string) max(0, $totalRows - $mainRows)); ?></strong></article>
        </div>
    </div>
</section>
<section class="card custom-card govcat-register-card">
    <div class="card-header justify-content-between">
        <div>
            <span class="text-primary fs-11 fw-semibold text-uppercase d-block mb-1">Category Register</span>
            <h6 class="mb-1 fw-semibold">Showing <?= h((string) $pageStart); ?>-<?= h((string) $pageEnd); ?> of <?= h((string) $totalRows); ?> records</h6>
            <p class="mb-0 text-muted fs-12">Compact category list with edit action.</p>
        </div>
        <form class="sadmin-page-size" method="get">
            <input type="hidden" name="page" value="1">
            <select class="form-select form-select-sm" name="per_page" onchange="this.form.submit()">
                <?php foreach ($perPageOptions as $option): ?>
                    <option value="<?= (int) $option; ?>" <?= $perPage === $option ? 'selected' : ''; ?>><?= (int) $option; ?> rows</option>
                <?php endforeach; ?>
            </select>
        </form>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 gr-register-table govcat-table">
                <thead><tr><th>Name</th><th>Parent</th><th>Description</th><th>Status</th><th>Order</th><th class="text-end">Edit</th></tr></thead>
                <tbody>
                <?php foreach ($rows as $row): ?>
                    <tr>
                        <td><span class="gr-cell-title"><?= h($row['name']); ?></span><span class="gr-cell-subtitle"><?= h($row['slug']); ?></span></td>
                        <td><span class="gr-cell-title"><?= h($row['parent_name'] ?: 'Main category'); ?></span></td>
                        <td><span class="gr-cell-title"><?= h($row['description'] ?: '-'); ?></span></td>
                        <td><?= gov_exam_status($row['status']); ?></td>
                        <td><span class="badge bg-primary-transparent text-primary">#<?= (int) $row['sort_order']; ?></span></td>
                        <td class="text-end"><a class="btn btn-sm btn-light btn-wave" href="#edit-category-<?= (int) $row['id']; ?>">Edit</a></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$rows): ?><tr><td colspan="6" class="empty-state premium-empty"><strong>No categories yet</strong><small>Add category to start exam preparation content.</small></td></tr><?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    <div class="card-footer sadmin-pagination">
        <span><?= h((string) $pageStart); ?>-<?= h((string) $pageEnd); ?> of <?= h((string) $totalRows); ?> records</span>
        <div>
            <a class="btn btn-sm btn-light <?= $page <= 1 ? 'disabled' : ''; ?>" href="<?= h(app_url('sadmin/govt-prep-categories?page=' . max(1, $page - 1) . '&per_page=' . $perPage)); ?>">Prev</a>
            <span class="page-chip">Page <?= h((string) $page); ?> / <?= h((string) $totalPages); ?></span>
            <a class="btn btn-sm btn-light <?= $page >= $totalPages ? 'disabled' : ''; ?>" href="<?= h(app_url('sadmin/govt-prep-categories?page=' . min($totalPages, $page + 1) . '&per_page=' . $perPage)); ?>">Next</a>
        </div>
    </div>
</section>
<div id="add-category" class="modal-overlay"><form class="modal-box ins-modal premium-admin-modal" method="post"><input type="hidden" name="csrf_token" value="<?= h(csrf_token()); ?>"><div class="modal-head"><h2>Add Category</h2><a href="#">&times;</a></div><div class="form-grid"><label>Name<input name="name" required></label><label>Parent<select name="parent_id"><?= gov_exam_parent_options(); ?></select></label><label>Status<select name="status"><option value="active">Active</option><option value="inactive">Inactive</option></select></label><label>Order<input type="number" name="sort_order" value="1"></label><label class="span-2">Description<textarea name="description" rows="2"></textarea></label></div><div class="modal-actions"><button type="submit">Save</button></div></form></div>
<?php foreach ($allRows as $row): ?><div id="edit-category-<?= (int) $row['id']; ?>" class="modal-overlay"><form class="modal-box ins-modal premium-admin-modal" method="post"><input type="hidden" name="csrf_token" value="<?= h(csrf_token()); ?>"><input type="hidden" name="id" value="<?= (int) $row['id']; ?>"><div class="modal-head"><h2>Edit Category</h2><a href="#">&times;</a></div><div class="form-grid"><label>Name<input name="name" value="<?= h($row['name']); ?>" required></label><label>Parent<select name="parent_id"><?= gov_exam_parent_options((int) ($row['parent_id'] ?? 0), (int) $row['id']); ?></select></label><label>Status<select name="status"><option value="active" <?= $row['status'] === 'active' ? 'selected' : ''; ?>>Active</option><option value="inactive" <?= $row['status'] === 'inactive' ? 'selected' : ''; ?>>Inactive</option></select></label><label>Order<input type="number" name="sort_order" value="<?= (int) $row['sort_order']; ?>"></label><label class="span-2">Description<textarea name="description" rows="2"><?= h($row['description']); ?></textarea></label></div><div class="modal-actions"><button type="submit">Update</button></div></form></div><?php endforeach; ?>
<style>
    .sadmin-govcat-main .govt-prep-page {
        padding-top: 1.25rem;
    }
    .sadmin-govcat-main .govcat-hero-card,
    .sadmin-govcat-main .govcat-register-card {
        border: 0;
        border-radius: .65rem;
        box-shadow: 0 .85rem 1.85rem rgba(15, 23, 42, .045);
        overflow: hidden;
    }
    .sadmin-govcat-main .govcat-hero-card .card-header,
    .sadmin-govcat-main .govcat-register-card .card-header {
        padding: .75rem 1rem;
        border-bottom: 1px solid var(--default-border);
        background: var(--custom-white);
    }
    .sadmin-govcat-main .govcat-mini-stats {
        display: grid;
        grid-template-columns: repeat(4, minmax(0, 1fr));
        gap: .65rem;
    }
    .sadmin-govcat-main .govcat-mini-stats article {
        display: grid;
        gap: .25rem;
        padding: .7rem .85rem;
        border: 1px solid var(--default-border);
        border-radius: .5rem;
        background: var(--default-background);
    }
    .sadmin-govcat-main .govcat-mini-stats span {
        color: var(--text-muted);
        font-size: .68rem;
        font-weight: 700;
        text-transform: uppercase;
    }
    .sadmin-govcat-main .govcat-mini-stats strong {
        font-size: 1.15rem;
        line-height: 1;
    }
    .sadmin-govcat-main .sadmin-page-size {
        margin: 0;
    }
    .sadmin-govcat-main .sadmin-page-size .form-select {
        min-width: 6.8rem;
        min-height: 2rem;
        font-size: .74rem;
    }
    .sadmin-govcat-main .govcat-table {
        min-width: 54rem;
    }
    .sadmin-govcat-main .govcat-table th,
    .sadmin-govcat-main .govcat-table td {
        padding: .42rem .65rem !important;
        font-size: .73rem;
        line-height: 1.2;
        vertical-align: middle;
    }
    .sadmin-govcat-main .govcat-table th {
        background: var(--default-background);
        font-size: .65rem;
        letter-spacing: .025em;
    }
    .sadmin-govcat-main .govcat-table .gr-cell-title {
        font-size: .74rem;
        line-height: 1.2;
    }
    .sadmin-govcat-main .govcat-table .gr-cell-subtitle {
        font-size: .67rem;
        line-height: 1.2;
    }
    .sadmin-govcat-main .govcat-table .badge,
    .sadmin-govcat-main .govcat-table .status-pill {
        min-height: 1.2rem;
        padding: .15rem .45rem;
        border-radius: 999px;
        font-size: .62rem;
        font-weight: 700;
    }
    .sadmin-govcat-main .govcat-table .btn-sm,
    .sadmin-govcat-main .sadmin-pagination .btn {
        min-height: 1.55rem;
        padding: .18rem .55rem;
        font-size: .67rem;
    }
    .sadmin-govcat-main .sadmin-pagination {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: .75rem;
        padding: .65rem 1rem;
        border-top: 1px solid var(--default-border);
        background: var(--custom-white);
        color: var(--text-muted);
        font-size: .74rem;
    }
    .sadmin-govcat-main .sadmin-pagination > div {
        display: flex;
        align-items: center;
        gap: .35rem;
    }
    .sadmin-govcat-main .page-chip {
        display: inline-flex;
        align-items: center;
        min-height: 1.55rem;
        padding: .18rem .55rem;
        border-radius: .35rem;
        background: rgba(var(--primary-rgb), .1);
        color: rgb(var(--primary-rgb));
        font-size: .67rem;
        font-weight: 700;
    }
    .sadmin-govcat-main .footer {
        margin-inline: 0 !important;
        width: 100%;
    }
    @media (max-width: 767.98px) {
        .sadmin-govcat-main .govcat-mini-stats {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }
    }
</style>
</section></main><?php include __DIR__ . '/includes/footer.php'; ?>
