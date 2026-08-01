<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/course_categories.php';

$user = require_login('superadmin');
ensure_course_category_tables();
$pageTitle = 'Course Categories';
$pageSubtitle = 'Create master categories and subcategories used by instructors.';
$activePage = 'categories';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['csrf_token'] ?? null)) {
        $_SESSION['cat_error'] = 'Security token expired.';
        redirect('sadmin/categories');
    }

    try {
        $id = (int) ($_POST['category_id'] ?? 0);
        $parentId = (int) ($_POST['parent_id'] ?? 0);
        $parentValue = $parentId > 0 ? $parentId : null;
        $name = substr(trim((string) ($_POST['name'] ?? '')), 0, 120);
        $description = substr(trim((string) ($_POST['description'] ?? '')), 0, 255);
        $status = in_array($_POST['status'] ?? '', ['active', 'inactive'], true) ? (string) $_POST['status'] : 'active';
        $sortOrder = max(1, min(9999, (int) ($_POST['sort_order'] ?? 1)));

        if ($name === '') {
            throw new RuntimeException('Category name required.');
        }
        if ($id > 0 && $parentValue === $id) {
            throw new RuntimeException('Category cannot be parent of itself.');
        }

        $slug = course_category_slug($name);
        $suffix = 1;
        while (true) {
            $check = db()->prepare('SELECT id FROM course_categories WHERE slug = ? AND id <> ? LIMIT 1');
            $check->bind_param('si', $slug, $id);
            $check->execute();
            if (!$check->get_result()->fetch_assoc()) {
                break;
            }
            $suffix++;
            $slug = course_category_slug($name) . '-' . $suffix;
        }

        if ($id > 0) {
            $stmt = db()->prepare('UPDATE course_categories SET parent_id = ?, name = ?, slug = ?, description = ?, status = ?, sort_order = ? WHERE id = ?');
            $stmt->bind_param('issssii', $parentValue, $name, $slug, $description, $status, $sortOrder, $id);
            $stmt->execute();
            $_SESSION['cat_message'] = 'Category updated.';
        } else {
            $stmt = db()->prepare('INSERT INTO course_categories (parent_id, name, slug, description, status, sort_order) VALUES (?, ?, ?, ?, ?, ?)');
            $stmt->bind_param('issssi', $parentValue, $name, $slug, $description, $status, $sortOrder);
            $stmt->execute();
            $_SESSION['cat_message'] = 'Category added.';
        }
    } catch (Throwable $e) {
        $_SESSION['cat_error'] = $e->getMessage();
    }
    redirect('sadmin/categories');
}

$allCategories = course_categories_all(false);
$totalCategories = count($allCategories);
$perPageOptions = [10, 25, 50];
$perPage = (int) ($_GET['per_page'] ?? 10);
if (!in_array($perPage, $perPageOptions, true)) {
    $perPage = 10;
}
$page = max(1, (int) ($_GET['page'] ?? 1));
$totalPages = max(1, (int) ceil($totalCategories / $perPage));
if ($page > $totalPages) {
    $page = $totalPages;
}
$offset = ($page - 1) * $perPage;
$categories = array_slice($allCategories, $offset, $perPage);
$pageStart = $totalCategories > 0 ? $offset + 1 : 0;
$pageEnd = min($offset + $perPage, $totalCategories);
$activeCategoryCount = count(array_filter($allCategories, static fn(array $category): bool => (string) ($category['status'] ?? '') === 'active'));
$parentCategoryCount = count(array_filter($allCategories, static fn(array $category): bool => empty($category['parent_id'])));
$parents = course_parent_categories(false);
$message = $_SESSION['cat_message'] ?? '';
$error = $_SESSION['cat_error'] ?? '';
unset($_SESSION['cat_message'], $_SESSION['cat_error']);
?>
<?php include __DIR__ . '/includes/header.php'; ?>
<?php include __DIR__ . '/includes/sidebar.php'; ?>
<main class="main sadmin-categories-main">
    <?php include __DIR__ . '/includes/topbar.php'; ?>
    <section class="content sadmin-categories-page">
        <?php if ($message): ?><div class="notice"><?= h($message); ?></div><?php endif; ?>
        <?php if ($error): ?><div class="notice danger"><?= h($error); ?></div><?php endif; ?>

        <section class="card custom-card sadmin-category-hero">
            <div class="card-header justify-content-between">
                <div>
                    <span class="text-primary fs-11 fw-semibold text-uppercase d-block mb-1">Course Master</span>
                    <h5 class="mb-1 fw-semibold">Category & subcategory control</h5>
                    <p class="mb-0 text-muted fs-12">Instructor course form me sirf active category use hogi.</p>
                </div>
                <a class="btn btn-primary btn-wave" href="#add-category">Add Category</a>
            </div>
            <div class="card-body">
                <div class="category-mini-stats">
                    <article><span>Total</span><strong><?= h((string) $totalCategories); ?></strong></article>
                    <article><span>Active</span><strong><?= h((string) $activeCategoryCount); ?></strong></article>
                    <article><span>Main</span><strong><?= h((string) $parentCategoryCount); ?></strong></article>
                    <article><span>Sub</span><strong><?= h((string) max(0, $totalCategories - $parentCategoryCount)); ?></strong></article>
                </div>
            </div>
        </section>

        <section class="card custom-card sadmin-category-card">
            <div class="card-header justify-content-between">
                <div>
                    <span class="text-primary fs-11 fw-semibold text-uppercase d-block mb-1">Category Register</span>
                    <h6 class="mb-1 fw-semibold">Showing <?= h((string) $pageStart); ?>-<?= h((string) $pageEnd); ?> of <?= h((string) $totalCategories); ?> categories</h6>
                    <p class="mb-0 text-muted fs-12">Compact table: edit se name, parent, status aur order manage karein.</p>
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
            <table class="table table-hover align-middle mb-0 gr-register-table sadmin-category-table">
                <thead><tr><th>Name</th><th>Parent</th><th>Description</th><th>Status</th><th>Order</th><th class="text-end">Edit</th></tr></thead>
                <tbody>
                    <?php if (!$categories): ?>
                        <tr><td colspan="6">No categories found.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($categories as $category): ?>
                        <tr>
                            <td><span class="gr-cell-title"><?= h($category['name']); ?></span><span class="gr-cell-subtitle"><?= h($category['slug']); ?></span></td>
                            <td><span class="gr-cell-title"><?= h($category['parent_name'] ?: 'Main category'); ?></span></td>
                            <td><span class="gr-cell-title"><?= h($category['description'] ?: 'No description'); ?></span></td>
                            <td><span class="badge <?= $category['status'] === 'active' ? 'bg-success-transparent text-success' : 'bg-warning-transparent text-warning'; ?>"><?= h(ucfirst($category['status'])); ?></span></td>
                            <td><span class="gr-cell-title"><?= h((string) $category['sort_order']); ?></span></td>
                            <td class="text-end"><a class="btn btn-sm btn-light btn-wave" href="#edit-category-<?= (int) $category['id']; ?>">Edit</a></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            </div>
            </div>
            <div class="card-footer sadmin-pagination">
                <span><?= h((string) $pageStart); ?>-<?= h((string) $pageEnd); ?> of <?= h((string) $totalCategories); ?> records</span>
                <div>
                    <a class="btn btn-sm btn-light <?= $page <= 1 ? 'disabled' : ''; ?>" href="<?= h(app_url('sadmin/categories?page=' . max(1, $page - 1) . '&per_page=' . $perPage)); ?>">Prev</a>
                    <span class="page-chip">Page <?= h((string) $page); ?> / <?= h((string) $totalPages); ?></span>
                    <a class="btn btn-sm btn-light <?= $page >= $totalPages ? 'disabled' : ''; ?>" href="<?= h(app_url('sadmin/categories?page=' . min($totalPages, $page + 1) . '&per_page=' . $perPage)); ?>">Next</a>
                </div>
            </div>
        </section>
    </section>
    <style>
        .sadmin-categories-main .sadmin-categories-page {
            padding-top: 1.25rem;
        }
        .sadmin-categories-main .sadmin-category-hero,
        .sadmin-categories-main .sadmin-category-card {
            border: 0;
            border-radius: .65rem;
            box-shadow: 0 .85rem 1.85rem rgba(15, 23, 42, .045);
            overflow: hidden;
        }
        .sadmin-categories-main .sadmin-category-hero .card-header,
        .sadmin-categories-main .sadmin-category-card .card-header {
            padding: .75rem 1rem;
            border-bottom: 1px solid var(--default-border);
            background: var(--custom-white);
        }
        .sadmin-categories-main .category-mini-stats {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: .65rem;
        }
        .sadmin-categories-main .category-mini-stats article {
            display: grid;
            gap: .25rem;
            padding: .7rem .85rem;
            border: 1px solid var(--default-border);
            border-radius: .5rem;
            background: var(--default-background);
        }
        .sadmin-categories-main .category-mini-stats span {
            color: var(--text-muted);
            font-size: .68rem;
            font-weight: 700;
            text-transform: uppercase;
        }
        .sadmin-categories-main .category-mini-stats strong {
            font-size: 1.15rem;
            line-height: 1;
        }
        .sadmin-categories-main .sadmin-page-size {
            margin: 0;
        }
        .sadmin-categories-main .sadmin-page-size .form-select {
            min-width: 6.8rem;
            min-height: 2rem;
            font-size: .74rem;
        }
        .sadmin-categories-main .sadmin-category-table {
            min-width: 54rem;
        }
        .sadmin-categories-main .sadmin-category-table th,
        .sadmin-categories-main .sadmin-category-table td {
            padding: .42rem .65rem !important;
            font-size: .73rem;
            line-height: 1.2;
            vertical-align: middle;
        }
        .sadmin-categories-main .sadmin-category-table th {
            background: var(--default-background);
            font-size: .65rem;
            letter-spacing: .025em;
        }
        .sadmin-categories-main .sadmin-category-table .gr-cell-title {
            font-size: .74rem;
            line-height: 1.2;
        }
        .sadmin-categories-main .sadmin-category-table .gr-cell-subtitle {
            font-size: .67rem;
            line-height: 1.2;
        }
        .sadmin-categories-main .sadmin-category-table .badge {
            min-height: 1.2rem;
            padding: .15rem .45rem;
            border-radius: 999px;
            font-size: .62rem;
            font-weight: 700;
        }
        .sadmin-categories-main .sadmin-category-table .btn-sm,
        .sadmin-categories-main .sadmin-pagination .btn {
            min-height: 1.55rem;
            padding: .18rem .55rem;
            font-size: .67rem;
        }
        .sadmin-categories-main .sadmin-pagination {
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
        .sadmin-categories-main .sadmin-pagination > div {
            display: flex;
            align-items: center;
            gap: .35rem;
        }
        .sadmin-categories-main .page-chip {
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
        .sadmin-categories-main .footer {
            margin-inline: 0 !important;
            width: 100%;
        }
        @media (max-width: 767.98px) {
            .sadmin-categories-main .category-mini-stats {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }
    </style>

    <div id="add-category" class="modal-overlay">
        <form class="modal-box ins-modal" method="post">
            <input type="hidden" name="csrf_token" value="<?= h(csrf_token()); ?>">
            <div class="modal-head"><h2>Add Category</h2><a class="modal-close" href="#" aria-label="Close">x</a></div>
            <?php $modalCategory = null; include __DIR__ . '/includes/category_form.php'; ?>
            <div class="modal-actions"><button type="submit">Save Category</button></div>
        </form>
    </div>

    <?php foreach ($allCategories as $category): ?>
        <div id="edit-category-<?= (int) $category['id']; ?>" class="modal-overlay">
            <form class="modal-box ins-modal" method="post">
                <input type="hidden" name="csrf_token" value="<?= h(csrf_token()); ?>">
                <input type="hidden" name="category_id" value="<?= (int) $category['id']; ?>">
                <div class="modal-head"><h2>Edit Category</h2><a class="modal-close" href="#" aria-label="Close">x</a></div>
                <?php $modalCategory = $category; include __DIR__ . '/includes/category_form.php'; ?>
                <div class="modal-actions"><button type="submit">Update Category</button></div>
            </form>
        </div>
    <?php endforeach; ?>
<?php include __DIR__ . '/includes/footer.php'; ?>

