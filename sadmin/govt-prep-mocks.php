<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/govt_exam_prep.php';

$user = require_login('superadmin');
gov_exam_ensure_tables();
$pageTitle = 'Govt Exam Mock Tests';
$pageSubtitle = 'Mock tests.';
$activePage = 'govt-prep-mocks';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['csrf_token'] ?? null)) {
        $_SESSION['gov_exam_error'] = 'Security token expired.';
        redirect('sadmin/govt-prep-mocks');
    }
    try {
        $id = (int) ($_POST['id'] ?? 0);
        $cat = (int) ($_POST['category_id'] ?? 0);
        $sub = (int) ($_POST['subcategory_id'] ?? 0) ?: null;
        $title = substr(trim((string) ($_POST['title'] ?? '')), 0, 180);
        $desc = substr(trim((string) ($_POST['description'] ?? '')), 0, 255);
        $thumbnail = substr(trim((string) ($_POST['thumbnail_path'] ?? '')), 0, 255);
        $dur = max(1, (int) ($_POST['duration_minutes'] ?? 60));
        $q = max(0, (int) ($_POST['total_questions'] ?? 0));
        $marks = max(0, (float) ($_POST['total_marks'] ?? 0));
        $status = in_array($_POST['status'] ?? '', ['draft', 'published', 'paused'], true) ? (string) $_POST['status'] : 'draft';
        if ($cat <= 0 || $title === '') {
            throw new RuntimeException('Category and title required.');
        }
        if ($id > 0) {
            $stmt = db()->prepare('UPDATE gov_exam_mock_tests SET category_id=?,subcategory_id=?,title=?,description=?,thumbnail_path=?,duration_minutes=?,total_questions=?,total_marks=?,status=? WHERE id=?');
            $stmt->bind_param('iisssiidsi', $cat, $sub, $title, $desc, $thumbnail, $dur, $q, $marks, $status, $id);
            $stmt->execute();
            $_SESSION['gov_exam_message'] = 'Mock test updated.';
        } else {
            $stmt = db()->prepare('INSERT INTO gov_exam_mock_tests (category_id,subcategory_id,title,description,thumbnail_path,duration_minutes,total_questions,total_marks,status) VALUES (?,?,?,?,?,?,?,?,?)');
            $stmt->bind_param('iisssiids', $cat, $sub, $title, $desc, $thumbnail, $dur, $q, $marks, $status);
            $stmt->execute();
            $_SESSION['gov_exam_message'] = 'Mock test added.';
        }
    } catch (Throwable $e) {
        $_SESSION['gov_exam_error'] = $e->getMessage();
    }
    redirect('sadmin/govt-prep-mocks');
}

$perPageOptions = [10, 25, 50];
$perPage = (int) ($_GET['per_page'] ?? 10);
if (!in_array($perPage, $perPageOptions, true)) {
    $perPage = 10;
}
$page = max(1, (int) ($_GET['page'] ?? 1));
$summary = db()->query("SELECT COUNT(*) total, SUM(status='published') published, SUM(status='draft') draft, SUM(status='paused') paused FROM gov_exam_mock_tests")->fetch_assoc() ?: [];
$totalRows = (int) ($summary['total'] ?? 0);
$publishedRows = (int) ($summary['published'] ?? 0);
$draftRows = (int) ($summary['draft'] ?? 0);
$pausedRows = (int) ($summary['paused'] ?? 0);
$totalPages = max(1, (int) ceil($totalRows / $perPage));
if ($page > $totalPages) {
    $page = $totalPages;
}
$offset = ($page - 1) * $perPage;
$stmt = db()->prepare("SELECT m.*, c.name category_name, s.name subcategory_name, (SELECT COUNT(*) FROM gov_exam_mock_questions q WHERE q.mock_test_id=m.id) question_count FROM gov_exam_mock_tests m LEFT JOIN gov_exam_categories c ON c.id=m.category_id LEFT JOIN gov_exam_categories s ON s.id=m.subcategory_id ORDER BY m.id DESC LIMIT ? OFFSET ?");
$stmt->bind_param('ii', $perPage, $offset);
$stmt->execute();
$rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$modalRows = db()->query("SELECT m.*, c.name category_name, s.name subcategory_name, (SELECT COUNT(*) FROM gov_exam_mock_questions q WHERE q.mock_test_id=m.id) question_count FROM gov_exam_mock_tests m LEFT JOIN gov_exam_categories c ON c.id=m.category_id LEFT JOIN gov_exam_categories s ON s.id=m.subcategory_id ORDER BY m.id DESC LIMIT 500")->fetch_all(MYSQLI_ASSOC);
$pageStart = $totalRows > 0 ? $offset + 1 : 0;
$pageEnd = min($offset + $perPage, $totalRows);
[$message, $error] = gov_exam_flash();
?>
<?php include __DIR__ . '/includes/header.php'; ?>
<?php include __DIR__ . '/includes/sidebar.php'; ?>
<main class="main sadmin-govmocks-main">
    <?php include __DIR__ . '/includes/topbar.php'; ?>
    <section class="content govt-prep-page">
        <?php if ($message): ?><div class="notice"><?= h($message); ?></div><?php endif; ?>
        <?php if ($error): ?><div class="notice danger"><?= h($error); ?></div><?php endif; ?>
        <section class="card custom-card govmocks-hero-card">
            <div class="card-header justify-content-between">
                <div>
                    <span class="text-primary fs-11 fw-semibold text-uppercase d-block mb-1">Govt Exam Prep</span>
                    <h5 class="mb-1 fw-semibold">Mock Tests</h5>
                    <p class="mb-0 text-muted fs-12">Mock test master category/subcategory wise.</p>
                </div>
                <a class="btn btn-primary btn-wave" href="#add-mock">Add Mock</a>
            </div>
            <div class="card-body">
                <div class="govmocks-mini-stats">
                    <article><span>Total</span><strong><?= h((string) $totalRows); ?></strong></article>
                    <article><span>Published</span><strong><?= h((string) $publishedRows); ?></strong></article>
                    <article><span>Draft</span><strong><?= h((string) $draftRows); ?></strong></article>
                    <article><span>Paused</span><strong><?= h((string) $pausedRows); ?></strong></article>
                </div>
            </div>
        </section>

        <section class="card custom-card govmocks-register-card">
            <div class="card-header justify-content-between">
                <div>
                    <span class="text-primary fs-11 fw-semibold text-uppercase d-block mb-1">Mock Register</span>
                    <h6 class="mb-1 fw-semibold">Showing <?= h((string) $pageStart); ?>-<?= h((string) $pageEnd); ?> of <?= h((string) $totalRows); ?> records</h6>
                    <p class="mb-0 text-muted fs-12">Compact table with question bank access.</p>
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
                <table class="table table-hover align-middle mb-0 gr-register-table govmocks-table">
                    <thead><tr><th>Mock Test</th><th>Category</th><th>Questions</th><th>Duration</th><th>Status</th><th class="text-end">Action</th></tr></thead>
                    <tbody>
                    <?php foreach ($rows as $row): ?>
                        <tr>
                            <td><span class="gr-cell-title"><?= h($row['title']); ?></span><span class="gr-cell-subtitle"><?= h($row['description'] ?: '-'); ?></span></td>
                            <td><span class="gr-cell-title"><?= h($row['category_name'] ?: '-'); ?></span><?= $row['subcategory_name'] ? '<span class="gr-cell-subtitle">' . h($row['subcategory_name']) . '</span>' : ''; ?></td>
                            <td><span class="gr-cell-title"><?= (int) $row['question_count']; ?> added</span><span class="gr-cell-subtitle">Target <?= (int) $row['total_questions']; ?> / <?= h((string) $row['total_marks']); ?> marks</span></td>
                            <td><span class="badge bg-primary-transparent text-primary"><?= (int) $row['duration_minutes']; ?>m</span></td>
                            <td><?= gov_exam_status($row['status']); ?></td>
                            <td class="text-end"><div class="btn-list justify-content-end"><a class="btn btn-sm btn-light btn-wave" href="<?= h(app_url('sadmin/govt-prep-questions?mock_id=' . (int) $row['id'])); ?>">Questions</a><a class="btn btn-sm btn-light btn-wave" href="#edit-mock-<?= (int) $row['id']; ?>">Edit</a></div></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$rows): ?><tr><td colspan="6" class="empty-state premium-empty"><strong>No mock tests yet</strong><small>Create a mock test, then add language-wise questions.</small></td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
            </div>
            <div class="card-footer sadmin-pagination">
                <span><?= h((string) $pageStart); ?>-<?= h((string) $pageEnd); ?> of <?= h((string) $totalRows); ?> records</span>
                <div>
                    <a class="btn btn-sm btn-light <?= $page <= 1 ? 'disabled' : ''; ?>" href="<?= h(app_url('sadmin/govt-prep-mocks?page=' . max(1, $page - 1) . '&per_page=' . $perPage)); ?>">Prev</a>
                    <span class="page-chip">Page <?= h((string) $page); ?> / <?= h((string) $totalPages); ?></span>
                    <a class="btn btn-sm btn-light <?= $page >= $totalPages ? 'disabled' : ''; ?>" href="<?= h(app_url('sadmin/govt-prep-mocks?page=' . min($totalPages, $page + 1) . '&per_page=' . $perPage)); ?>">Next</a>
                </div>
            </div>
        </section>
        <div id="add-mock" class="modal-overlay"><form class="modal-box wide-modal premium-admin-modal" method="post"><input type="hidden" name="csrf_token" value="<?= h(csrf_token()); ?>"><div class="modal-head"><h2>Add Mock Test</h2><a href="#">&times;</a></div><div class="form-grid"><label>Category<select name="category_id" required><?= gov_exam_category_options(); ?></select></label><label>Subcategory<select name="subcategory_id"><?= gov_exam_category_options(); ?></select></label><label>Title<input name="title" required></label><label>Thumbnail Path / URL<input name="thumbnail_path" placeholder="uploads/gov-mocks/jee-main.svg"></label><label>Duration<input type="number" name="duration_minutes" value="60"></label><label>Questions<input type="number" name="total_questions" value="0"></label><label>Marks<input type="number" step="0.01" name="total_marks" value="0"></label><label>Status<select name="status"><option value="draft">Draft</option><option value="published">Published</option><option value="paused">Paused</option></select></label><label class="span-2">Description<textarea name="description" rows="2"></textarea></label></div><div class="modal-actions"><button type="submit">Save</button></div></form></div>
        <?php foreach ($modalRows as $row): ?><div id="edit-mock-<?= (int) $row['id']; ?>" class="modal-overlay"><form class="modal-box wide-modal premium-admin-modal" method="post"><input type="hidden" name="csrf_token" value="<?= h(csrf_token()); ?>"><input type="hidden" name="id" value="<?= (int) $row['id']; ?>"><div class="modal-head"><h2>Edit Mock Test</h2><a href="#">&times;</a></div><div class="form-grid"><label>Category<select name="category_id" required><?= gov_exam_category_options((int) $row['category_id']); ?></select></label><label>Subcategory<select name="subcategory_id"><?= gov_exam_category_options((int) ($row['subcategory_id'] ?? 0)); ?></select></label><label>Title<input name="title" value="<?= h($row['title']); ?>" required></label><label>Thumbnail Path / URL<input name="thumbnail_path" value="<?= h($row['thumbnail_path'] ?? ''); ?>"></label><label>Duration<input type="number" name="duration_minutes" value="<?= (int) $row['duration_minutes']; ?>"></label><label>Questions<input type="number" name="total_questions" value="<?= (int) $row['total_questions']; ?>"></label><label>Marks<input type="number" step="0.01" name="total_marks" value="<?= h((string) $row['total_marks']); ?>"></label><label>Status<select name="status"><?php foreach (['draft', 'published', 'paused'] as $s): ?><option value="<?= $s; ?>" <?= $row['status'] === $s ? 'selected' : ''; ?>><?= ucfirst($s); ?></option><?php endforeach; ?></select></label><label class="span-2">Description<textarea name="description" rows="2"><?= h($row['description']); ?></textarea></label></div><div class="modal-actions"><button type="submit">Update</button></div></form></div><?php endforeach; ?>
    </section>
    <style>
        .sadmin-govmocks-main .govt-prep-page { padding-top: 1.25rem; }
        .sadmin-govmocks-main .govmocks-hero-card,
        .sadmin-govmocks-main .govmocks-register-card {
            border: 0;
            border-radius: .65rem;
            box-shadow: 0 .85rem 1.85rem rgba(15, 23, 42, .045);
            overflow: hidden;
        }
        .sadmin-govmocks-main .govmocks-hero-card .card-header,
        .sadmin-govmocks-main .govmocks-register-card .card-header {
            padding: .75rem 1rem;
            border-bottom: 1px solid var(--default-border);
            background: var(--custom-white);
        }
        .sadmin-govmocks-main .govmocks-mini-stats {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: .65rem;
        }
        .sadmin-govmocks-main .govmocks-mini-stats article {
            display: grid;
            gap: .25rem;
            padding: .7rem .85rem;
            border: 1px solid var(--default-border);
            border-radius: .5rem;
            background: var(--default-background);
        }
        .sadmin-govmocks-main .govmocks-mini-stats span {
            color: var(--text-muted);
            font-size: .68rem;
            font-weight: 700;
            text-transform: uppercase;
        }
        .sadmin-govmocks-main .govmocks-mini-stats strong { font-size: 1.15rem; line-height: 1; }
        .sadmin-govmocks-main .sadmin-page-size { margin: 0; }
        .sadmin-govmocks-main .sadmin-page-size .form-select {
            min-width: 6.8rem;
            min-height: 2rem;
            font-size: .74rem;
        }
        .sadmin-govmocks-main .govmocks-table { min-width: 66rem; }
        .sadmin-govmocks-main .govmocks-table th,
        .sadmin-govmocks-main .govmocks-table td {
            padding: .42rem .65rem !important;
            font-size: .73rem;
            line-height: 1.2;
            vertical-align: middle;
        }
        .sadmin-govmocks-main .govmocks-table th {
            background: var(--default-background);
            font-size: .65rem;
            letter-spacing: .025em;
        }
        .sadmin-govmocks-main .govmocks-table .gr-cell-title { font-size: .74rem; line-height: 1.2; }
        .sadmin-govmocks-main .govmocks-table .gr-cell-subtitle { font-size: .67rem; line-height: 1.2; }
        .sadmin-govmocks-main .govmocks-table .status-pill,
        .sadmin-govmocks-main .govmocks-table .badge {
            min-height: 1.2rem;
            padding: .15rem .45rem;
            border-radius: 999px;
            font-size: .62rem;
            font-weight: 700;
        }
        .sadmin-govmocks-main .govmocks-table .btn-sm,
        .sadmin-govmocks-main .sadmin-pagination .btn {
            min-height: 1.55rem;
            padding: .18rem .55rem;
            font-size: .67rem;
        }
        .sadmin-govmocks-main .govmocks-table .btn-list { gap: .25rem; }
        .sadmin-govmocks-main .sadmin-pagination {
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
        .sadmin-govmocks-main .sadmin-pagination > div {
            display: flex;
            align-items: center;
            gap: .35rem;
        }
        .sadmin-govmocks-main .page-chip {
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
        .sadmin-govmocks-main .footer { margin-inline: 0 !important; width: 100%; }
        @media (max-width: 767.98px) {
            .sadmin-govmocks-main .govmocks-mini-stats { grid-template-columns: repeat(2, minmax(0, 1fr)); }
        }
    </style>
</main>
<?php include __DIR__ . '/includes/footer.php'; ?>
