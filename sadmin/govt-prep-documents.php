<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/govt_exam_prep.php';

$user = require_login('superadmin');
gov_exam_ensure_tables();
$pageTitle = 'Govt Exam Documents';
$pageSubtitle = 'Category wise documents.';
$activePage = 'govt-prep-documents';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['csrf_token'] ?? null)) {
        $_SESSION['gov_exam_error'] = 'Security token expired.';
        redirect('sadmin/govt-prep-documents');
    }
    try {
        $id = (int) ($_POST['id'] ?? 0);
        $categoryId = (int) ($_POST['category_id'] ?? 0);
        $subcategoryId = (int) ($_POST['subcategory_id'] ?? 0) ?: null;
        $title = substr(trim((string) ($_POST['title'] ?? '')), 0, 180);
        $description = substr(trim((string) ($_POST['description'] ?? '')), 0, 255);
        $url = substr(trim((string) ($_POST['document_url'] ?? '')), 0, 255);
        $price = max(0, (float) ($_POST['price'] ?? 0));
        $status = in_array($_POST['status'] ?? '', ['draft', 'published'], true) ? (string) $_POST['status'] : 'published';
        $sort = max(1, (int) ($_POST['sort_order'] ?? 1));
        if ($categoryId <= 0 || $title === '') {
            throw new RuntimeException('Category and title required.');
        }
        if ($id > 0) {
            $stmt = db()->prepare('UPDATE gov_exam_documents SET category_id=?, subcategory_id=?, title=?, description=?, document_url=?, price=?, status=?, sort_order=? WHERE id=?');
            $stmt->bind_param('iisssdsii', $categoryId, $subcategoryId, $title, $description, $url, $price, $status, $sort, $id);
            $stmt->execute();
            $_SESSION['gov_exam_message'] = 'Document updated.';
        } else {
            $stmt = db()->prepare('INSERT INTO gov_exam_documents (category_id, subcategory_id, title, description, document_url, price, status, sort_order) VALUES (?,?,?,?,?,?,?,?)');
            $stmt->bind_param('iisssdsi', $categoryId, $subcategoryId, $title, $description, $url, $price, $status, $sort);
            $stmt->execute();
            $_SESSION['gov_exam_message'] = 'Document added.';
        }
    } catch (Throwable $e) {
        $_SESSION['gov_exam_error'] = $e->getMessage();
    }
    redirect('sadmin/govt-prep-documents');
}

$perPageOptions = [10, 25, 50];
$perPage = (int) ($_GET['per_page'] ?? 10);
if (!in_array($perPage, $perPageOptions, true)) {
    $perPage = 10;
}
$page = max(1, (int) ($_GET['page'] ?? 1));
$summary = db()->query("SELECT COUNT(*) total, SUM(status='published') published, SUM(status='draft') draft, SUM(price > 0) paid FROM gov_exam_documents")->fetch_assoc() ?: [];
$totalRows = (int) ($summary['total'] ?? 0);
$publishedRows = (int) ($summary['published'] ?? 0);
$draftRows = (int) ($summary['draft'] ?? 0);
$paidRows = (int) ($summary['paid'] ?? 0);
$totalPages = max(1, (int) ceil($totalRows / $perPage));
if ($page > $totalPages) {
    $page = $totalPages;
}
$offset = ($page - 1) * $perPage;
$stmt = db()->prepare("SELECT d.*, c.name category_name, s.name subcategory_name FROM gov_exam_documents d LEFT JOIN gov_exam_categories c ON c.id=d.category_id LEFT JOIN gov_exam_categories s ON s.id=d.subcategory_id ORDER BY d.id DESC LIMIT ? OFFSET ?");
$stmt->bind_param('ii', $perPage, $offset);
$stmt->execute();
$rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$modalRows = db()->query("SELECT d.*, c.name category_name, s.name subcategory_name FROM gov_exam_documents d LEFT JOIN gov_exam_categories c ON c.id=d.category_id LEFT JOIN gov_exam_categories s ON s.id=d.subcategory_id ORDER BY d.id DESC LIMIT 500")->fetch_all(MYSQLI_ASSOC);
$pageStart = $totalRows > 0 ? $offset + 1 : 0;
$pageEnd = min($offset + $perPage, $totalRows);
[$message, $error] = gov_exam_flash();
?>
<?php include __DIR__ . '/includes/header.php'; ?>
<?php include __DIR__ . '/includes/sidebar.php'; ?>
<main class="main sadmin-govdocs-main">
    <?php include __DIR__ . '/includes/topbar.php'; ?>
    <section class="content govt-prep-page">
        <?php if ($message): ?><div class="notice"><?= h($message); ?></div><?php endif; ?>
        <?php if ($error): ?><div class="notice danger"><?= h($error); ?></div><?php endif; ?>
        <section class="card custom-card govdocs-hero-card">
            <div class="card-header justify-content-between">
                <div>
                    <span class="text-primary fs-11 fw-semibold text-uppercase d-block mb-1">Govt Exam Prep</span>
                    <h5 class="mb-1 fw-semibold">Documents</h5>
                    <p class="mb-0 text-muted fs-12">PDF/document links category and subcategory wise.</p>
                </div>
                <a class="btn btn-primary btn-wave" href="#add-document">Add Document</a>
            </div>
            <div class="card-body">
                <div class="govdocs-mini-stats">
                    <article><span>Total</span><strong><?= h((string) $totalRows); ?></strong></article>
                    <article><span>Published</span><strong><?= h((string) $publishedRows); ?></strong></article>
                    <article><span>Draft</span><strong><?= h((string) $draftRows); ?></strong></article>
                    <article><span>Paid</span><strong><?= h((string) $paidRows); ?></strong></article>
                </div>
            </div>
        </section>

        <section class="card custom-card govdocs-register-card">
            <div class="card-header justify-content-between">
                <div>
                    <span class="text-primary fs-11 fw-semibold text-uppercase d-block mb-1">Document Register</span>
                    <h6 class="mb-1 fw-semibold">Showing <?= h((string) $pageStart); ?>-<?= h((string) $pageEnd); ?> of <?= h((string) $totalRows); ?> records</h6>
                    <p class="mb-0 text-muted fs-12">Compact table with category, price and status.</p>
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
                <table class="table table-hover align-middle mb-0 gr-register-table govdocs-table">
                    <thead><tr><th>Document</th><th>Category</th><th>Price</th><th>Status</th><th class="text-end">Edit</th></tr></thead>
                    <tbody>
                        <?php foreach ($rows as $row): ?>
                            <tr>
                                <td><span class="gr-cell-title"><?= h($row['title']); ?></span><span class="gr-cell-subtitle"><?= h($row['document_url'] ?: 'No URL'); ?></span></td>
                                <td><span class="gr-cell-title"><?= h($row['category_name'] ?: '-'); ?></span><?= $row['subcategory_name'] ? '<span class="gr-cell-subtitle">' . h($row['subcategory_name']) . '</span>' : ''; ?></td>
                                <td><span class="gr-cell-title">Rs <?= h(number_format((float) $row['price'], 2)); ?></span></td>
                                <td><?= gov_exam_status($row['status']); ?></td>
                                <td class="text-end"><a class="btn btn-sm btn-light btn-wave" href="#edit-document-<?= (int) $row['id']; ?>">Edit</a></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (!$rows): ?><tr><td colspan="5" class="empty-state premium-empty"><strong>No documents yet</strong><small>Add PDF/document links for exam prep.</small></td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
            </div>
            <div class="card-footer sadmin-pagination">
                <span><?= h((string) $pageStart); ?>-<?= h((string) $pageEnd); ?> of <?= h((string) $totalRows); ?> records</span>
                <div>
                    <a class="btn btn-sm btn-light <?= $page <= 1 ? 'disabled' : ''; ?>" href="<?= h(app_url('sadmin/govt-prep-documents?page=' . max(1, $page - 1) . '&per_page=' . $perPage)); ?>">Prev</a>
                    <span class="page-chip">Page <?= h((string) $page); ?> / <?= h((string) $totalPages); ?></span>
                    <a class="btn btn-sm btn-light <?= $page >= $totalPages ? 'disabled' : ''; ?>" href="<?= h(app_url('sadmin/govt-prep-documents?page=' . min($totalPages, $page + 1) . '&per_page=' . $perPage)); ?>">Next</a>
                </div>
            </div>
        </section>

        <div id="add-document" class="modal-overlay"><form class="modal-box wide-modal premium-admin-modal" method="post"><input type="hidden" name="csrf_token" value="<?= h(csrf_token()); ?>"><div class="modal-head"><h2>Add Document</h2><a href="#">&times;</a></div><div class="form-grid"><label>Category<select name="category_id" required><?= gov_exam_category_options(); ?></select></label><label>Subcategory<select name="subcategory_id"><?= gov_exam_category_options(); ?></select></label><label>Title<input name="title" required></label><label>Document URL / Path<input name="document_url"></label><label>Price<input type="number" step="0.01" min="0" name="price" value="0"></label><label>Status<select name="status"><option value="published">Published</option><option value="draft">Draft</option></select></label><label>Order<input type="number" name="sort_order" value="1"></label><label class="span-2">Description<textarea name="description" rows="2"></textarea></label></div><div class="modal-actions"><button type="submit">Save</button></div></form></div>
        <?php foreach ($modalRows as $row): ?><div id="edit-document-<?= (int) $row['id']; ?>" class="modal-overlay"><form class="modal-box wide-modal premium-admin-modal" method="post"><input type="hidden" name="csrf_token" value="<?= h(csrf_token()); ?>"><input type="hidden" name="id" value="<?= (int) $row['id']; ?>"><div class="modal-head"><h2>Edit Document</h2><a href="#">&times;</a></div><div class="form-grid"><label>Category<select name="category_id" required><?= gov_exam_category_options((int) $row['category_id']); ?></select></label><label>Subcategory<select name="subcategory_id"><?= gov_exam_category_options((int) ($row['subcategory_id'] ?? 0)); ?></select></label><label>Title<input name="title" value="<?= h($row['title']); ?>" required></label><label>Document URL / Path<input name="document_url" value="<?= h($row['document_url']); ?>"></label><label>Price<input type="number" step="0.01" min="0" name="price" value="<?= h((string) $row['price']); ?>"></label><label>Status<select name="status"><option value="published" <?= $row['status'] === 'published' ? 'selected' : ''; ?>>Published</option><option value="draft" <?= $row['status'] === 'draft' ? 'selected' : ''; ?>>Draft</option></select></label><label>Order<input type="number" name="sort_order" value="<?= (int) $row['sort_order']; ?>"></label><label class="span-2">Description<textarea name="description" rows="2"><?= h($row['description']); ?></textarea></label></div><div class="modal-actions"><button type="submit">Update</button></div></form></div><?php endforeach; ?>
    </section>
    <style>
        .sadmin-govdocs-main .govt-prep-page { padding-top: 1.25rem; }
        .sadmin-govdocs-main .govdocs-hero-card,
        .sadmin-govdocs-main .govdocs-register-card {
            border: 0;
            border-radius: .65rem;
            box-shadow: 0 .85rem 1.85rem rgba(15, 23, 42, .045);
            overflow: hidden;
        }
        .sadmin-govdocs-main .govdocs-hero-card .card-header,
        .sadmin-govdocs-main .govdocs-register-card .card-header {
            padding: .75rem 1rem;
            border-bottom: 1px solid var(--default-border);
            background: var(--custom-white);
        }
        .sadmin-govdocs-main .govdocs-mini-stats {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: .65rem;
        }
        .sadmin-govdocs-main .govdocs-mini-stats article {
            display: grid;
            gap: .25rem;
            padding: .7rem .85rem;
            border: 1px solid var(--default-border);
            border-radius: .5rem;
            background: var(--default-background);
        }
        .sadmin-govdocs-main .govdocs-mini-stats span {
            color: var(--text-muted);
            font-size: .68rem;
            font-weight: 700;
            text-transform: uppercase;
        }
        .sadmin-govdocs-main .govdocs-mini-stats strong { font-size: 1.15rem; line-height: 1; }
        .sadmin-govdocs-main .sadmin-page-size { margin: 0; }
        .sadmin-govdocs-main .sadmin-page-size .form-select {
            min-width: 6.8rem;
            min-height: 2rem;
            font-size: .74rem;
        }
        .sadmin-govdocs-main .govdocs-table { min-width: 56rem; }
        .sadmin-govdocs-main .govdocs-table th,
        .sadmin-govdocs-main .govdocs-table td {
            padding: .42rem .65rem !important;
            font-size: .73rem;
            line-height: 1.2;
            vertical-align: middle;
        }
        .sadmin-govdocs-main .govdocs-table th {
            background: var(--default-background);
            font-size: .65rem;
            letter-spacing: .025em;
        }
        .sadmin-govdocs-main .govdocs-table .gr-cell-title { font-size: .74rem; line-height: 1.2; }
        .sadmin-govdocs-main .govdocs-table .gr-cell-subtitle { font-size: .67rem; line-height: 1.2; }
        .sadmin-govdocs-main .govdocs-table .status-pill,
        .sadmin-govdocs-main .govdocs-table .badge {
            min-height: 1.2rem;
            padding: .15rem .45rem;
            border-radius: 999px;
            font-size: .62rem;
            font-weight: 700;
        }
        .sadmin-govdocs-main .govdocs-table .btn-sm,
        .sadmin-govdocs-main .sadmin-pagination .btn {
            min-height: 1.55rem;
            padding: .18rem .55rem;
            font-size: .67rem;
        }
        .sadmin-govdocs-main .sadmin-pagination {
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
        .sadmin-govdocs-main .sadmin-pagination > div {
            display: flex;
            align-items: center;
            gap: .35rem;
        }
        .sadmin-govdocs-main .page-chip {
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
        .sadmin-govdocs-main .footer { margin-inline: 0 !important; width: 100%; }
        @media (max-width: 767.98px) {
            .sadmin-govdocs-main .govdocs-mini-stats { grid-template-columns: repeat(2, minmax(0, 1fr)); }
        }
    </style>
</main>
<?php include __DIR__ . '/includes/footer.php'; ?>
