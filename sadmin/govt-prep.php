<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/govt_exam_prep.php';

$user = require_login('superadmin');
gov_exam_ensure_tables();
$pageTitle = 'Government Exam Preparation';
$pageSubtitle = 'Documents, live classes and mock tests category-wise.';
$activePage = 'govt-prep';

function gov_admin_scalar(string $sql): int
{
    $result = db()->query($sql);
    if (!$result) return 0;
    $row = $result->fetch_assoc();
    return (int) ($row['total'] ?? 0);
}

function gov_admin_rows(string $sql): array
{
    $result = db()->query($sql);
    return $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
}

$counts = [
    'Main Categories' => gov_admin_scalar('SELECT COUNT(*) total FROM gov_exam_categories WHERE parent_id IS NULL'),
    'Sub Categories' => gov_admin_scalar('SELECT COUNT(*) total FROM gov_exam_categories WHERE parent_id IS NOT NULL'),
    'Documents' => gov_admin_scalar('SELECT COUNT(*) total FROM gov_exam_documents'),
    'Live Sessions' => gov_admin_scalar('SELECT COUNT(*) total FROM gov_exam_live_sessions'),
    'Mock Tests' => gov_admin_scalar('SELECT COUNT(*) total FROM gov_exam_mock_tests'),
    'Questions' => gov_admin_scalar('SELECT COUNT(*) total FROM gov_exam_mock_questions'),
];

$statusStats = [
    'Categories' => [
        'Active' => gov_admin_scalar("SELECT COUNT(*) total FROM gov_exam_categories WHERE status = 'active'"),
        'Inactive' => gov_admin_scalar("SELECT COUNT(*) total FROM gov_exam_categories WHERE status = 'inactive'"),
    ],
    'Documents' => [
        'Published' => gov_admin_scalar("SELECT COUNT(*) total FROM gov_exam_documents WHERE status = 'published'"),
        'Draft' => gov_admin_scalar("SELECT COUNT(*) total FROM gov_exam_documents WHERE status = 'draft'"),
        'Free' => gov_admin_scalar('SELECT COUNT(*) total FROM gov_exam_documents WHERE price <= 0'),
        'Paid' => gov_admin_scalar('SELECT COUNT(*) total FROM gov_exam_documents WHERE price > 0'),
    ],
    'Live' => [
        'Scheduled' => gov_admin_scalar("SELECT COUNT(*) total FROM gov_exam_live_sessions WHERE status = 'scheduled'"),
        'Live Now' => gov_admin_scalar("SELECT COUNT(*) total FROM gov_exam_live_sessions WHERE status = 'live'"),
        'Completed' => gov_admin_scalar("SELECT COUNT(*) total FROM gov_exam_live_sessions WHERE status = 'completed'"),
        'Cancelled' => gov_admin_scalar("SELECT COUNT(*) total FROM gov_exam_live_sessions WHERE status = 'cancelled'"),
    ],
    'Mocks' => [
        'Published' => gov_admin_scalar("SELECT COUNT(*) total FROM gov_exam_mock_tests WHERE status = 'published'"),
        'Draft' => gov_admin_scalar("SELECT COUNT(*) total FROM gov_exam_mock_tests WHERE status = 'draft'"),
        'Paused' => gov_admin_scalar("SELECT COUNT(*) total FROM gov_exam_mock_tests WHERE status = 'paused'"),
        'No Questions' => gov_admin_scalar('SELECT COUNT(*) total FROM (SELECT m.id FROM gov_exam_mock_tests m LEFT JOIN gov_exam_mock_questions q ON q.mock_test_id = m.id GROUP BY m.id HAVING COUNT(q.id) = 0) x'),
    ],
];

$coverageTab = (string) ($_GET['tab'] ?? 'categories');
if (!in_array($coverageTab, ['categories', 'subcategories'], true)) {
    $coverageTab = 'categories';
}
$coveragePerPageOptions = [10, 25, 50, 100];
$coveragePerPage = (int) ($_GET['per_page'] ?? 10);
if (!in_array($coveragePerPage, $coveragePerPageOptions, true)) {
    $coveragePerPage = 10;
}
$coveragePage = max(1, (int) ($_GET['page'] ?? 1));
$coverageTotalRows = $coverageTab === 'subcategories' ? $counts['Sub Categories'] : $counts['Main Categories'];
$coverageTotalPages = max(1, (int) ceil($coverageTotalRows / $coveragePerPage));
if ($coveragePage > $coverageTotalPages) {
    $coveragePage = $coverageTotalPages;
}
$coverageOffset = ($coveragePage - 1) * $coveragePerPage;
$coverageStart = $coverageTotalRows > 0 ? $coverageOffset + 1 : 0;
$coverageEnd = min($coverageOffset + $coveragePerPage, $coverageTotalRows);
$coveragePageFrom = max(1, $coveragePage - 2);
$coveragePageTo = min($coverageTotalPages, $coveragePage + 2);

function gov_admin_coverage_url(string $tab, int $page, int $perPage): string
{
    return app_url('sadmin/govt-prep?tab=' . rawurlencode($tab) . '&page=' . max(1, $page) . '&per_page=' . max(1, $perPage));
}
$mockDepth = gov_admin_rows("SELECT m.id, m.title, m.status, m.total_questions AS target_questions, COUNT(q.id) AS added_questions, c.name AS category_name, s.name AS subcategory_name
    FROM gov_exam_mock_tests m
    LEFT JOIN gov_exam_mock_questions q ON q.mock_test_id = m.id
    LEFT JOIN gov_exam_categories c ON c.id = m.category_id
    LEFT JOIN gov_exam_categories s ON s.id = m.subcategory_id
    GROUP BY m.id, m.title, m.status, m.total_questions, c.name, s.name
    ORDER BY added_questions DESC, m.id DESC
    LIMIT 8");

$mainCategoryCoverage = [];
$subCategoryCoverage = [];

if ($coverageTab === 'categories') {
    $mainCategoryCoverage = gov_admin_rows("SELECT p.id, p.name, p.status,
            COUNT(DISTINCT c.id) AS subcategories,
            SUM(CASE WHEN c.status = 'active' THEN 1 ELSE 0 END) AS active_subcategories,
            COALESCE(d.total_docs, 0) AS documents,
            COALESCE(d.published_docs, 0) AS published_documents,
            COALESCE(l.total_live, 0) AS live_sessions,
            COALESCE(l.live_now, 0) AS live_now,
            COALESCE(m.total_mocks, 0) AS mock_tests,
            COALESCE(m.published_mocks, 0) AS published_mocks,
            COALESCE(q.total_questions, 0) AS questions
        FROM gov_exam_categories p
        LEFT JOIN gov_exam_categories c ON c.parent_id = p.id
        LEFT JOIN (
            SELECT category_id, COUNT(*) total_docs, SUM(status = 'published') published_docs
            FROM gov_exam_documents GROUP BY category_id
        ) d ON d.category_id = p.id
        LEFT JOIN (
            SELECT category_id, COUNT(*) total_live, SUM(status = 'live') live_now
            FROM gov_exam_live_sessions GROUP BY category_id
        ) l ON l.category_id = p.id
        LEFT JOIN (
            SELECT category_id, COUNT(*) total_mocks, COUNT(*) published_mocks
            FROM gov_exam_mock_tests
            WHERE status = 'published' AND title NOT LIKE '%Quality Check Set%'
            GROUP BY category_id
        ) m ON m.category_id = p.id
        LEFT JOIN (
            SELECT mt.category_id, COUNT(q.id) total_questions
            FROM gov_exam_mock_tests mt
            LEFT JOIN gov_exam_mock_questions q ON q.mock_test_id = mt.id AND q.status = 'active'
            WHERE mt.status = 'published' AND mt.title NOT LIKE '%Quality Check Set%'
            GROUP BY mt.category_id
        ) q ON q.category_id = p.id
        WHERE p.parent_id IS NULL
        GROUP BY p.id, p.name, p.status, d.total_docs, d.published_docs, l.total_live, l.live_now, m.total_mocks, m.published_mocks, q.total_questions
        ORDER BY p.sort_order, p.name
        LIMIT {$coveragePerPage} OFFSET {$coverageOffset}");
} else {
    $subCategoryCoverage = gov_admin_rows("SELECT s.id, s.name, p.name AS parent_name, s.status,
            COALESCE(d.total_docs, 0) AS documents,
            COALESCE(d.published_docs, 0) AS published_documents,
            COALESCE(l.total_live, 0) AS live_sessions,
            COALESCE(l.live_now, 0) AS live_now,
            COALESCE(m.total_mocks, 0) AS mock_tests,
            COALESCE(m.published_mocks, 0) AS published_mocks,
            COALESCE(q.total_questions, 0) AS questions
        FROM gov_exam_categories s
        LEFT JOIN gov_exam_categories p ON p.id = s.parent_id
        LEFT JOIN (
            SELECT subcategory_id, COUNT(*) total_docs, SUM(status = 'published') published_docs
            FROM gov_exam_documents WHERE subcategory_id IS NOT NULL GROUP BY subcategory_id
        ) d ON d.subcategory_id = s.id
        LEFT JOIN (
            SELECT subcategory_id, COUNT(*) total_live, SUM(status = 'live') live_now
            FROM gov_exam_live_sessions WHERE subcategory_id IS NOT NULL GROUP BY subcategory_id
        ) l ON l.subcategory_id = s.id
        LEFT JOIN (
            SELECT subcategory_id, COUNT(*) total_mocks, COUNT(*) published_mocks
            FROM gov_exam_mock_tests
            WHERE subcategory_id IS NOT NULL AND status = 'published' AND title NOT LIKE '%Quality Check Set%'
            GROUP BY subcategory_id
        ) m ON m.subcategory_id = s.id
        LEFT JOIN (
            SELECT mt.subcategory_id, COUNT(q.id) total_questions
            FROM gov_exam_mock_tests mt
            LEFT JOIN gov_exam_mock_questions q ON q.mock_test_id = mt.id AND q.status = 'active'
            WHERE mt.subcategory_id IS NOT NULL AND mt.status = 'published' AND mt.title NOT LIKE '%Quality Check Set%'
            GROUP BY mt.subcategory_id
        ) q ON q.subcategory_id = s.id
        WHERE s.parent_id IS NOT NULL
        GROUP BY s.id, s.name, p.name, s.status, d.total_docs, d.published_docs, l.total_live, l.live_now, m.total_mocks, m.published_mocks, q.total_questions
        ORDER BY p.sort_order, p.name, s.sort_order, s.name
        LIMIT {$coveragePerPage} OFFSET {$coverageOffset}");
}
$subCategoryDepth = gov_admin_rows("SELECT s.id, s.name, p.name AS parent_name, s.status,
        COALESCE(d.total_docs, 0) AS documents,
        COALESCE(l.total_live, 0) AS live_sessions,
        COALESCE(m.total_mocks, 0) AS mock_tests,
        COALESCE(q.total_questions, 0) AS questions
    FROM gov_exam_categories s
    LEFT JOIN gov_exam_categories p ON p.id = s.parent_id
    LEFT JOIN (SELECT subcategory_id, COUNT(*) total_docs FROM gov_exam_documents WHERE subcategory_id IS NOT NULL GROUP BY subcategory_id) d ON d.subcategory_id = s.id
    LEFT JOIN (SELECT subcategory_id, COUNT(*) total_live FROM gov_exam_live_sessions WHERE subcategory_id IS NOT NULL GROUP BY subcategory_id) l ON l.subcategory_id = s.id
    LEFT JOIN (SELECT subcategory_id, COUNT(*) total_mocks FROM gov_exam_mock_tests WHERE subcategory_id IS NOT NULL GROUP BY subcategory_id) m ON m.subcategory_id = s.id
    LEFT JOIN (
        SELECT mt.subcategory_id, COUNT(q.id) total_questions
            FROM gov_exam_mock_tests mt
            LEFT JOIN gov_exam_mock_questions q ON q.mock_test_id = mt.id AND q.status = 'active'
            WHERE mt.subcategory_id IS NOT NULL AND mt.status = 'published' AND mt.title NOT LIKE '%Quality Check Set%'
            GROUP BY mt.subcategory_id
    ) q ON q.subcategory_id = s.id
    WHERE s.parent_id IS NOT NULL
    ORDER BY (COALESCE(d.total_docs,0) + COALESCE(l.total_live,0) + COALESCE(m.total_mocks,0) + COALESCE(q.total_questions,0)) DESC, s.name
    LIMIT 10");

$unmapped = [
    'Subcategories without content' => gov_admin_scalar('SELECT COUNT(*) total FROM gov_exam_categories s LEFT JOIN gov_exam_documents d ON d.subcategory_id = s.id LEFT JOIN gov_exam_live_sessions l ON l.subcategory_id = s.id LEFT JOIN gov_exam_mock_tests m ON m.subcategory_id = s.id WHERE s.parent_id IS NOT NULL AND d.id IS NULL AND l.id IS NULL AND m.id IS NULL'),
    'Mocks missing questions' => gov_admin_scalar('SELECT COUNT(*) total FROM (SELECT m.id FROM gov_exam_mock_tests m LEFT JOIN gov_exam_mock_questions q ON q.mock_test_id = m.id GROUP BY m.id HAVING COUNT(q.id) = 0) x'),
    'Documents without URL' => gov_admin_scalar("SELECT COUNT(*) total FROM gov_exam_documents WHERE COALESCE(document_url, '') = ''"),
    'Live sessions without URL' => gov_admin_scalar("SELECT COUNT(*) total FROM gov_exam_live_sessions WHERE COALESCE(live_url, '') = ''"),
];

$recentItems = gov_admin_rows("SELECT 'Document' type, title, status, created_at FROM gov_exam_documents
    UNION ALL SELECT 'Live' type, title, status, created_at FROM gov_exam_live_sessions
    UNION ALL SELECT 'Mock' type, title, status, created_at FROM gov_exam_mock_tests
    ORDER BY created_at DESC
    LIMIT 8");
?>
<?php include __DIR__ . '/includes/header.php'; ?>
<?php include __DIR__ . '/includes/sidebar.php'; ?>
<main class="main sadmin-govt-main">
    <?php include __DIR__ . '/includes/topbar.php'; ?>
    <section class="content govt-prep-page">
        <section class="card custom-card gov-prep-card gov-prep-overview">
            <div class="card-header justify-content-between">
                <div>
                    <span class="text-primary fs-11 fw-semibold text-uppercase d-block mb-1">Govt Exam Prep</span>
                    <h5 class="mb-1 fw-semibold">Government Exam Preparation</h5>
                    <p class="mb-0 text-muted fs-12">Category, subcategory, PDFs, live sessions, mock tests and question bank cockpit.</p>
                </div>
                <div class="btn-list">
                    <a class="btn btn-light btn-wave" href="<?= h(app_url('sadmin/govt-prep-mocks')); ?>">Manage Mocks</a>
                    <a class="btn btn-primary btn-wave" href="<?= h(app_url('sadmin/govt-prep-categories')); ?>">Manage Categories</a>
                </div>
            </div>
            <div class="card-body">
                <div class="govt-mini-stats govt-mini-stats-six">
                    <?php foreach ($counts as $label => $total): ?>
                        <article><span><?= h($label); ?></span><strong><?= h((string) $total); ?></strong></article>
                    <?php endforeach; ?>
                </div>
            </div>
        </section>

        <section class="gov-prep-grid-menu">
            <a class="gov-module-card" href="<?= h(app_url('sadmin/govt-prep-categories')); ?>"><span><i class="bx bx-grid-alt"></i></span><div><h3>Categories</h3><p>Main and subcategory master.</p></div><b>Open</b></a>
            <a class="gov-module-card" href="<?= h(app_url('sadmin/govt-prep-documents')); ?>"><span><i class="bx bx-file"></i></span><div><h3>Documents</h3><p>PDF/document links and notes.</p></div><b>Open</b></a>
            <a class="gov-module-card" href="<?= h(app_url('sadmin/govt-prep-live')); ?>"><span><i class="bx bx-video"></i></span><div><h3>Live</h3><p>Scheduled or live sessions.</p></div><b>Open</b></a>
            <a class="gov-module-card" href="<?= h(app_url('sadmin/govt-prep-mocks')); ?>"><span><i class="bx bx-notepad"></i></span><div><h3>Mock Tests</h3><p>Exam mock test master.</p></div><b>Open</b></a>
            <a class="gov-module-card" href="<?= h(app_url('sadmin/govt-prep-questions')); ?>"><span><i class="bx bx-question-mark"></i></span><div><h3>Mock Questions</h3><p>Language-wise questions and answers.</p></div><b>Open</b></a>
        </section>

        <section class="gov-detail-grid">
            <?php foreach ($statusStats as $title => $items): ?>
                <article class="card custom-card gov-prep-card gov-status-card">
                    <div class="card-header"><h6 class="mb-0 fw-semibold"><?= h($title); ?> Status</h6></div>
                    <div class="card-body">
                        <?php foreach ($items as $label => $total): ?>
                            <div class="gov-line-stat"><span><?= h($label); ?></span><b><?= h((string) $total); ?></b></div>
                        <?php endforeach; ?>
                    </div>
                </article>
            <?php endforeach; ?>
        </section>

        <section class="card custom-card gov-prep-card gov-coverage-card">
            <div class="card-header justify-content-between gov-coverage-head">
                <div>
                    <span class="text-primary fs-11 fw-semibold text-uppercase d-block mb-1">Category Coverage</span>
                    <h6 class="mb-1 fw-semibold">All categories and subcategories with content count</h6>
                    <p class="mb-0 text-muted fs-12">Tab view me complete category depth, content count aur status.</p>
                </div>
                <a class="btn btn-sm btn-light btn-wave" href="<?= h(app_url('sadmin/govt-prep-categories')); ?>">Manage Categories</a>
            </div>
            <div class="card-body gov-coverage-toolbar">
                <nav class="gov-coverage-tabs" aria-label="Category coverage tabs">
                    <a class="<?= $coverageTab === 'categories' ? 'active' : ''; ?>" href="<?= h(gov_admin_coverage_url('categories', 1, $coveragePerPage)); ?>">
                        <span>Main Categories</span><em><?= h((string) $counts['Main Categories']); ?></em>
                    </a>
                    <a class="<?= $coverageTab === 'subcategories' ? 'active' : ''; ?>" href="<?= h(gov_admin_coverage_url('subcategories', 1, $coveragePerPage)); ?>">
                        <span>Sub Categories</span><em><?= h((string) $counts['Sub Categories']); ?></em>
                    </a>
                </nav>
                <form class="gov-coverage-size" method="get">
                    <input type="hidden" name="tab" value="<?= h($coverageTab); ?>">
                    <input type="hidden" name="page" value="1">
                    <select class="form-select form-select-sm" name="per_page" onchange="this.form.submit()">
                        <?php foreach ($coveragePerPageOptions as $option): ?>
                            <option value="<?= (int) $option; ?>" <?= $coveragePerPage === $option ? 'selected' : ''; ?>><?= (int) $option; ?> rows</option>
                        <?php endforeach; ?>
                    </select>
                </form>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0 gov-overview-table gov-coverage-table">
                        <?php if ($coverageTab === 'categories'): ?>
                            <thead><tr><th>Category</th><th>Sub Categories</th><th>Documents</th><th>Live</th><th>Mocks</th><th>Questions</th><th>Status</th></tr></thead>
                            <tbody>
                            <?php foreach ($mainCategoryCoverage as $row): ?>
                                <tr>
                                    <td><strong><?= h($row['name']); ?></strong><small>ID #<?= (int) $row['id']; ?></small></td>
                                    <td><b><?= (int) $row['subcategories']; ?></b><small><?= (int) $row['active_subcategories']; ?> active</small></td>
                                    <td><b><?= (int) $row['documents']; ?></b><small><?= (int) $row['published_documents']; ?> published</small></td>
                                    <td><b><?= (int) $row['live_sessions']; ?></b><small><?= (int) $row['live_now']; ?> live now</small></td>
                                    <td><b><?= (int) $row['mock_tests']; ?></b><small><?= (int) $row['published_mocks']; ?> published</small></td>
                                    <td><b><?= (int) $row['questions']; ?></b><small>question bank</small></td>
                                    <td><?= gov_exam_status((string) $row['status']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if (!$mainCategoryCoverage): ?><tr><td colspan="7" class="empty-state premium-empty"><strong>No category data</strong><small>Add main categories to start coverage tracking.</small></td></tr><?php endif; ?>
                            </tbody>
                        <?php else: ?>
                            <thead><tr><th>Sub Category</th><th>Main Category</th><th>Documents</th><th>Live</th><th>Mocks</th><th>Questions</th><th>Status</th></tr></thead>
                            <tbody>
                            <?php foreach ($subCategoryCoverage as $row): ?>
                                <tr>
                                    <td><strong><?= h($row['name']); ?></strong><small>ID #<?= (int) $row['id']; ?></small></td>
                                    <td><span class="gov-parent-name"><?= h($row['parent_name'] ?: 'Main'); ?></span></td>
                                    <td><b><?= (int) $row['documents']; ?></b><small><?= (int) $row['published_documents']; ?> published</small></td>
                                    <td><b><?= (int) $row['live_sessions']; ?></b><small><?= (int) $row['live_now']; ?> live now</small></td>
                                    <td><b><?= (int) $row['mock_tests']; ?></b><small><?= (int) $row['published_mocks']; ?> published</small></td>
                                    <td><b><?= (int) $row['questions']; ?></b><small>question bank</small></td>
                                    <td><?= gov_exam_status((string) $row['status']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if (!$subCategoryCoverage): ?><tr><td colspan="7" class="empty-state premium-empty"><strong>No subcategory data</strong><small>Add subcategories to start coverage tracking.</small></td></tr><?php endif; ?>
                            </tbody>
                        <?php endif; ?>
                    </table>
                </div>
            </div>
            <div class="card-footer gov-coverage-pagination">
                <span><?= h((string) $coverageStart); ?>-<?= h((string) $coverageEnd); ?> of <?= h((string) $coverageTotalRows); ?> <?= $coverageTab === 'categories' ? 'main categories' : 'sub categories'; ?></span>
                <div class="page-links">
                    <a class="<?= $coveragePage <= 1 ? 'disabled' : ''; ?>" href="<?= h(gov_admin_coverage_url($coverageTab, 1, $coveragePerPage)); ?>">First</a>
                    <a class="<?= $coveragePage <= 1 ? 'disabled' : ''; ?>" href="<?= h(gov_admin_coverage_url($coverageTab, max(1, $coveragePage - 1), $coveragePerPage)); ?>">Prev</a>
                    <?php for ($pageNo = $coveragePageFrom; $pageNo <= $coveragePageTo; $pageNo++): ?>
                        <a class="<?= $pageNo === $coveragePage ? 'active' : ''; ?>" href="<?= h(gov_admin_coverage_url($coverageTab, $pageNo, $coveragePerPage)); ?>"><?= h((string) $pageNo); ?></a>
                    <?php endfor; ?>
                    <a class="<?= $coveragePage >= $coverageTotalPages ? 'disabled' : ''; ?>" href="<?= h(gov_admin_coverage_url($coverageTab, min($coverageTotalPages, $coveragePage + 1), $coveragePerPage)); ?>">Next</a>
                    <a class="<?= $coveragePage >= $coverageTotalPages ? 'disabled' : ''; ?>" href="<?= h(gov_admin_coverage_url($coverageTab, $coverageTotalPages, $coveragePerPage)); ?>">Last</a>
                </div>
            </div>
        </section>
        <section class="gov-two-col">
            <article class="card custom-card gov-prep-card">
                <div class="card-header"><div><span class="text-primary fs-11 fw-semibold text-uppercase d-block mb-1">Subcategory Depth</span><h6 class="mb-0 fw-semibold">Top subcategories by content</h6></div></div>
                <div class="card-body gov-list-body">
                    <?php foreach ($subCategoryDepth as $row): ?>
                        <div class="gov-rich-row">
                            <div><strong><?= h($row['name']); ?></strong><small><?= h($row['parent_name'] ?: 'Main'); ?> / <?= h(ucfirst((string) $row['status'])); ?></small></div>
                            <span><?= (int) $row['documents']; ?> PDFs</span><span><?= (int) $row['live_sessions']; ?> Live</span><span><?= (int) $row['mock_tests']; ?> Mocks</span><span><?= (int) $row['questions']; ?> Qs</span>
                        </div>
                    <?php endforeach; ?>
                    <?php if (!$subCategoryDepth): ?><div class="empty-state premium-empty"><strong>No subcategory content</strong><small>Add PDFs, live sessions or mocks under subcategories.</small></div><?php endif; ?>
                </div>
            </article>
            <article class="card custom-card gov-prep-card">
                <div class="card-header"><div><span class="text-primary fs-11 fw-semibold text-uppercase d-block mb-1">Mock Depth</span><h6 class="mb-0 fw-semibold">Mocks with maximum questions</h6></div></div>
                <div class="card-body gov-list-body">
                    <?php foreach ($mockDepth as $row): ?>
                        <?php $target = (int) ($row['target_questions'] ?? 0); $added = (int) ($row['added_questions'] ?? 0); ?>
                        <div class="gov-rich-row mock-row">
                            <div><strong><?= h($row['title']); ?></strong><small><?= h(($row['category_name'] ?? 'Category') . (($row['subcategory_name'] ?? '') ? ' / ' . $row['subcategory_name'] : '')); ?></small></div>
                            <span><?= h(ucfirst((string) $row['status'])); ?></span><span><?= $added; ?> added</span><span><?= $target; ?> target</span><span><?= $target > 0 ? min(100, (int) round(($added / max(1, $target)) * 100)) : 0; ?>%</span>
                        </div>
                    <?php endforeach; ?>
                    <?php if (!$mockDepth): ?><div class="empty-state premium-empty"><strong>No mock tests</strong><small>Create mock tests and add questions.</small></div><?php endif; ?>
                </div>
            </article>
        </section>

        <section class="gov-two-col gov-bottom-grid">
            <article class="card custom-card gov-prep-card">
                <div class="card-header"><div><span class="text-primary fs-11 fw-semibold text-uppercase d-block mb-1">Needs Attention</span><h6 class="mb-0 fw-semibold">Content gaps</h6></div></div>
                <div class="card-body">
                    <?php foreach ($unmapped as $label => $total): ?>
                        <div class="gov-line-stat alert-row"><span><?= h($label); ?></span><b><?= h((string) $total); ?></b></div>
                    <?php endforeach; ?>
                </div>
            </article>
            <article class="card custom-card gov-prep-card">
                <div class="card-header"><div><span class="text-primary fs-11 fw-semibold text-uppercase d-block mb-1">Recent Activity</span><h6 class="mb-0 fw-semibold">Latest govt prep content</h6></div></div>
                <div class="card-body gov-list-body">
                    <?php foreach ($recentItems as $row): ?>
                        <div class="gov-recent-row"><span><?= h($row['type']); ?></span><div><strong><?= h($row['title']); ?></strong><small><?= h(ucfirst((string) $row['status'])); ?> - <?= h((string) $row['created_at']); ?></small></div></div>
                    <?php endforeach; ?>
                    <?php if (!$recentItems): ?><div class="empty-state premium-empty"><strong>No recent content</strong><small>Add documents, live sessions or mocks.</small></div><?php endif; ?>
                </div>
            </article>
        </section>
    </section>
    <style>
        .sadmin-govt-main .govt-prep-page { padding-top: 1.25rem; display: grid; gap: 1rem; }
        .sadmin-govt-main .gov-prep-card { border: 0; border-radius: .7rem; box-shadow: 0 .85rem 1.85rem rgba(15, 23, 42, .045); overflow: hidden; }
        .sadmin-govt-main .gov-prep-card .card-header { padding: .8rem 1rem; border-bottom: 1px solid var(--default-border); background: var(--custom-white); }
        .sadmin-govt-main .govt-mini-stats { display: grid; grid-template-columns: repeat(6, minmax(0, 1fr)); gap: .65rem; }
        .sadmin-govt-main .govt-mini-stats article, .sadmin-govt-main .gov-line-stat { display: grid; gap: .25rem; padding: .75rem .85rem; border: 1px solid var(--default-border); border-radius: .55rem; background: var(--default-background); }
        .sadmin-govt-main .govt-mini-stats span, .sadmin-govt-main .gov-line-stat span { color: var(--text-muted); font-size: .68rem; font-weight: 800; text-transform: uppercase; letter-spacing: .02em; }
        .sadmin-govt-main .govt-mini-stats strong, .sadmin-govt-main .gov-line-stat b { font-size: 1.2rem; line-height: 1; color: var(--default-text-color); }
        .sadmin-govt-main .gov-prep-grid-menu { display: grid; grid-template-columns: repeat(5, minmax(0, 1fr)); gap: .75rem; }
        .sadmin-govt-main .gov-module-card { display: grid; grid-template-columns: 2.35rem minmax(0, 1fr) auto; align-items: center; gap: .75rem; padding: .85rem 1rem; border: 1px solid var(--default-border); border-radius: .65rem; background: var(--custom-white); color: var(--default-text-color); text-decoration: none; box-shadow: 0 .65rem 1.5rem rgba(15, 23, 42, .04); transition: transform .18s ease, border-color .18s ease, box-shadow .18s ease; }
        .sadmin-govt-main .gov-module-card:hover { border-color: rgba(var(--primary-rgb), .28); box-shadow: 0 1rem 2rem rgba(15, 23, 42, .07); transform: translateY(-1px); }
        .sadmin-govt-main .gov-module-card > span { display: inline-flex; align-items: center; justify-content: center; width: 2.35rem; height: 2.35rem; border-radius: .55rem; background: rgba(var(--primary-rgb), .1); color: rgb(var(--primary-rgb)); font-size: 1.15rem; }
        .sadmin-govt-main .gov-module-card h3 { margin: 0; font-size: .88rem; font-weight: 750; }
        .sadmin-govt-main .gov-module-card p { margin: .15rem 0 0; color: var(--text-muted); font-size: .72rem; }
        .sadmin-govt-main .gov-module-card b { display: inline-flex; align-items: center; min-height: 1.55rem; padding: .18rem .55rem; border-radius: .35rem; background: rgba(var(--primary-rgb), .1); color: rgb(var(--primary-rgb)); font-size: .67rem; }
        .sadmin-govt-main .gov-detail-grid { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: .85rem; }
        .sadmin-govt-main .gov-status-card .card-body { display: grid; gap: .55rem; }
        .sadmin-govt-main .gov-coverage-toolbar { display: flex; align-items: center; justify-content: space-between; gap: .75rem; padding: .75rem 1rem; border-bottom: 1px solid var(--default-border); background: var(--default-background); }
        .sadmin-govt-main .gov-coverage-tabs { display: inline-flex; gap: .35rem; padding: .25rem; border: 1px solid var(--default-border); border-radius: .55rem; background: var(--custom-white); }
        .sadmin-govt-main .gov-coverage-tabs a { display: inline-flex; align-items: center; gap: .45rem; min-height: 2rem; padding: .28rem .7rem; border-radius: .42rem; color: var(--default-text-color); text-decoration: none; font-size: .74rem; font-weight: 500; white-space: nowrap; }
        .sadmin-govt-main .gov-coverage-tabs a.active { background: rgb(var(--primary-rgb)); color: #fff; }
        .sadmin-govt-main .gov-coverage-tabs em { min-width: 1.45rem; padding: .05rem .38rem; border-radius: 999px; background: rgba(var(--primary-rgb), .1); color: rgb(var(--primary-rgb)); font-style: normal; font-size: .68rem; font-weight: 500; text-align: center; }
        .sadmin-govt-main .gov-coverage-tabs a.active em { background: rgba(255, 255, 255, .2); color: #fff; }
        .sadmin-govt-main .gov-coverage-size { margin: 0; }
        .sadmin-govt-main .gov-coverage-size .form-select { min-width: 6.6rem; min-height: 2rem; font-size: .74rem; }
        .sadmin-govt-main .gov-coverage-table { min-width: 72rem; }
        .sadmin-govt-main .gov-coverage-table th, .sadmin-govt-main .gov-coverage-table td { padding: .42rem .75rem !important; font-size: .75rem; line-height: 1.18; }
        .sadmin-govt-main .gov-coverage-table td strong, .sadmin-govt-main .gov-coverage-table td b, .sadmin-govt-main .gov-parent-name { font-weight: 500 !important; }
        .sadmin-govt-main .gov-coverage-table .status-pill { min-height: 1.25rem; padding: .12rem .5rem; font-size: .65rem; font-weight: 500; }
        .sadmin-govt-main .gov-coverage-pagination { display: flex; align-items: center; justify-content: space-between; gap: .75rem; padding: .65rem 1rem; border-top: 1px solid var(--default-border); background: var(--custom-white); color: var(--text-muted); font-size: .74rem; }
        .sadmin-govt-main .gov-coverage-pagination .page-links { display: flex; align-items: center; gap: .3rem; flex-wrap: wrap; justify-content: flex-end; }
        .sadmin-govt-main .gov-coverage-pagination a { display: inline-flex; align-items: center; justify-content: center; min-width: 1.75rem; min-height: 1.65rem; padding: .16rem .55rem; border: 1px solid var(--default-border); border-radius: .35rem; background: var(--default-background); color: var(--default-text-color); text-decoration: none; font-size: .68rem; font-weight: 500; }
        .sadmin-govt-main .gov-coverage-pagination a.active { border-color: rgb(var(--primary-rgb)); background: rgb(var(--primary-rgb)); color: #fff; }
        .sadmin-govt-main .gov-coverage-pagination a.disabled { color: var(--text-muted); pointer-events: none; opacity: .5; }
        .sadmin-govt-main .gov-overview-table th { color: var(--text-muted); font-size: .68rem; text-transform: uppercase; white-space: nowrap; }
        .sadmin-govt-main .gov-overview-table td { vertical-align: middle; }
        .sadmin-govt-main .gov-overview-table td strong, .sadmin-govt-main .gov-overview-table td b { display: block; color: var(--default-text-color); }
        .sadmin-govt-main .gov-overview-table td small { display: block; color: var(--text-muted); font-size: .68rem; }
        .sadmin-govt-main .gov-two-col { display: grid; grid-template-columns: minmax(0, 1fr) minmax(0, 1fr); gap: .9rem; }
        .sadmin-govt-main .gov-list-body { display: grid; gap: .55rem; }
        .sadmin-govt-main .gov-rich-row { display: grid; grid-template-columns: minmax(0, 1fr) repeat(4, auto); gap: .55rem; align-items: center; padding: .65rem .75rem; border: 1px solid var(--default-border); border-radius: .55rem; background: var(--default-background); }
        .sadmin-govt-main .gov-rich-row strong { display: block; font-size: .82rem; }
        .sadmin-govt-main .gov-rich-row small { display: block; color: var(--text-muted); font-size: .68rem; margin-top: .12rem; }
        .sadmin-govt-main .gov-rich-row span, .sadmin-govt-main .gov-recent-row span { display: inline-flex; align-items: center; justify-content: center; min-height: 1.5rem; padding: .12rem .5rem; border-radius: 99px; background: rgba(var(--primary-rgb), .1); color: rgb(var(--primary-rgb)); font-size: .67rem; font-weight: 800; white-space: nowrap; }
        .sadmin-govt-main .gov-line-stat.alert-row { grid-template-columns: minmax(0, 1fr) auto; align-items: center; margin-bottom: .55rem; }
        .sadmin-govt-main .gov-recent-row { display: grid; grid-template-columns: auto minmax(0, 1fr); align-items: center; gap: .7rem; padding: .65rem .75rem; border: 1px solid var(--default-border); border-radius: .55rem; background: var(--default-background); }
        .sadmin-govt-main .gov-recent-row strong { display: block; font-size: .82rem; }
        .sadmin-govt-main .gov-recent-row small { display: block; color: var(--text-muted); font-size: .68rem; margin-top: .12rem; }
        .sadmin-govt-main .footer { margin-inline: 0 !important; width: 100%; }
        @media (max-width: 1399.98px) { .sadmin-govt-main .gov-prep-grid-menu, .sadmin-govt-main .govt-mini-stats { grid-template-columns: repeat(3, minmax(0, 1fr)); } .sadmin-govt-main .gov-detail-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
        @media (max-width: 991.98px) { .sadmin-govt-main .gov-two-col { grid-template-columns: 1fr; } .sadmin-govt-main .gov-prep-grid-menu, .sadmin-govt-main .govt-mini-stats { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
        @media (max-width: 767.98px) { .sadmin-govt-main .gov-prep-grid-menu, .sadmin-govt-main .govt-mini-stats, .sadmin-govt-main .gov-detail-grid { grid-template-columns: 1fr; } .sadmin-govt-main .gov-rich-row { grid-template-columns: 1fr 1fr; } .sadmin-govt-main .gov-rich-row div { grid-column: 1 / -1; } .sadmin-govt-main .gov-coverage-toolbar, .sadmin-govt-main .gov-coverage-pagination { align-items: stretch; flex-direction: column; } .sadmin-govt-main .gov-coverage-tabs { display: grid; grid-template-columns: 1fr; } .sadmin-govt-main .gov-coverage-pagination .page-links { justify-content: flex-start; } }
    </style>
</main>
<?php include __DIR__ . '/includes/footer.php'; ?>

