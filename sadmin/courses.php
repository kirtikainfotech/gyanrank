<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../ins/includes/functions.php';

$user = require_login('superadmin');
ensure_instructor_erp_tables();

$pageTitle = 'Courses';
$pageSubtitle = 'All instructor courses in a single panel with chapter count.';
$activePage = 'courses';

$perPageOptions = [10, 25, 50];
$perPage = (int) ($_GET['per_page'] ?? 10);
if (!in_array($perPage, $perPageOptions, true)) {
    $perPage = 10;
}
$page = max(1, (int) ($_GET['page'] ?? 1));

$summaryRow = db()->query("
    SELECT COUNT(*) AS total,
           SUM(status='published') AS published,
           SUM(is_free=1) AS free_total,
           COALESCE((SELECT COUNT(*) FROM instructor_course_contents), 0) AS chapters
    FROM instructor_courses
")->fetch_assoc() ?: [];
$totalCourses = (int) ($summaryRow['total'] ?? 0);
$publishedCount = (int) ($summaryRow['published'] ?? 0);
$freeCount = (int) ($summaryRow['free_total'] ?? 0);
$chapterTotal = (int) ($summaryRow['chapters'] ?? 0);
$totalPages = max(1, (int) ceil($totalCourses / $perPage));
if ($page > $totalPages) {
    $page = $totalPages;
}
$offset = ($page - 1) * $perPage;

$courseStmt = db()->prepare("
    SELECT c.id,
           c.title,
           c.status,
           c.is_free,
           c.price,
           c.original_price,
           c.course_level,
           c.course_language,
           c.category,
           c.duration,
           u.full_name AS instructor_name,
           COALESCE((SELECT COUNT(*) FROM instructor_course_contents cc WHERE cc.course_id = c.id), 0) AS chapter_count
    FROM instructor_courses c
    LEFT JOIN users u ON u.id = c.instructor_id
    ORDER BY c.id DESC
    LIMIT ? OFFSET ?
");
$courseStmt->bind_param('ii', $perPage, $offset);
$courseStmt->execute();
$courses = $courseStmt->get_result()->fetch_all(MYSQLI_ASSOC);
$fromRecord = $totalCourses > 0 ? $offset + 1 : 0;
$toRecord = min($offset + $perPage, $totalCourses);
?>
<?php include __DIR__ . '/includes/header.php'; ?>
<?php include __DIR__ . '/includes/sidebar.php'; ?>
<main class="main sadmin-courses-main">
    <?php include __DIR__ . '/includes/topbar.php'; ?>
    <section class="content sadmin-courses-page">
        <section class="card custom-card sadmin-course-hero">
            <div class="card-header justify-content-between">
                <div>
                    <span class="text-primary fs-11 fw-semibold text-uppercase d-block mb-1">Learning Center</span>
                    <h5 class="mb-1 fw-semibold">Course Register</h5>
                    <p class="mb-0 text-muted fs-12">All instructor courses, pricing, language and chapter readiness in one compact view.</p>
                </div>
                <a class="btn btn-primary btn-wave" href="<?= h(app_url('sadmin/instructors')); ?>">Instructors</a>
            </div>
            <div class="card-body">
                <div class="course-mini-stats">
                    <article><span>Total</span><strong><?= h((string) $totalCourses); ?></strong></article>
                    <article><span>Published</span><strong><?= h((string) $publishedCount); ?></strong></article>
                    <article><span>Free</span><strong><?= h((string) $freeCount); ?></strong></article>
                    <article><span>Chapters</span><strong><?= h((string) $chapterTotal); ?></strong></article>
                </div>
            </div>
        </section>

        <section class="card custom-card sadmin-course-card">
            <div class="card-header justify-content-between">
                <div>
                    <span class="text-primary fs-11 fw-semibold text-uppercase d-block mb-1">Course Inventory</span>
                    <h6 class="mb-1 fw-semibold">Showing <?= h((string) $fromRecord); ?>-<?= h((string) $toRecord); ?> of <?= h((string) $totalCourses); ?> records</h6>
                    <p class="mb-0 text-muted fs-12">Compact table: detail ke liye View open karein.</p>
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
                <table class="table table-hover align-middle mb-0 gr-register-table sadmin-course-table">
                    <thead>
                    <tr>
                        <th>Course</th>
                        <th>Instructor</th>
                        <th>Category</th>
                        <th>Pricing</th>
                        <th>Chapters</th>
                        <th>Status</th>
                        <th class="text-end">Action</th>
                    </tr>
                    </thead>
                    <tbody>
                        <?php if (!$courses): ?>
                            <tr><td colspan="7">No courses found. Instructors have not published any course yet.</td></tr>
                        <?php endif; ?>
                        <?php foreach ($courses as $course): ?>
                            <tr>
                                <td>
                                    <span class="gr-cell-title"><?= h($course['title']); ?></span>
                                    <span class="gr-cell-subtitle"><?= h(ucfirst((string) ($course['course_language'] ?: 'Hindi'))); ?> / <?= h((string) ($course['course_level'] ?: 'all')); ?> / <?= h($course['duration'] ?: 'Duration N/A'); ?></span>
                                </td>
                                <td><span class="gr-cell-title"><?= h($course['instructor_name'] ?: 'Unassigned'); ?></span></td>
                                <td><span class="gr-cell-title"><?= h($course['category'] ?: 'No category'); ?></span></td>
                                <td>
                                    <?php $isFree = ((int) ($course['is_free'] ?? 0)) === 1; ?>
                                    <span class="gr-cell-title"><?= $isFree ? 'Free' : h('Rs ' . (string) ($course['price'] ?? '0')); ?></span>
                                    <?php if ((float) ($course['original_price'] ?? 0) > (float) ($course['price'] ?? 0)): ?>
                                        <span class="gr-cell-subtitle">Old: <?= h((string) $course['original_price']); ?></span>
                                    <?php endif; ?>
                                </td>
                                <td><span class="gr-cell-title"><?= (string) (int) ($course['chapter_count'] ?? 0); ?></span></td>
                                <td>
                                    <span class="badge <?= ((string) ($course['status'] ?? '') === 'published') ? 'bg-success-transparent text-success' : 'bg-warning-transparent text-warning'; ?>">
                                        <?= h(ucfirst((string) ($course['status'] ?? 'draft'))); ?>
                                    </span>
                                </td>
                                <td class="text-end">
                                    <a class="btn btn-sm btn-light btn-wave" href="<?= h(app_url('sadmin/courses?course=' . (int) $course['id'])); ?>">View</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            </div>
            <div class="card-footer sadmin-pagination">
                <span><?= h((string) $fromRecord); ?>-<?= h((string) $toRecord); ?> of <?= h((string) $totalCourses); ?> records</span>
                <div>
                    <a class="btn btn-sm btn-light <?= $page <= 1 ? 'disabled' : ''; ?>" href="<?= h(app_url('sadmin/courses?page=' . max(1, $page - 1) . '&per_page=' . $perPage)); ?>">Prev</a>
                    <span class="page-chip">Page <?= h((string) $page); ?> / <?= h((string) $totalPages); ?></span>
                    <a class="btn btn-sm btn-light <?= $page >= $totalPages ? 'disabled' : ''; ?>" href="<?= h(app_url('sadmin/courses?page=' . min($totalPages, $page + 1) . '&per_page=' . $perPage)); ?>">Next</a>
                </div>
            </div>
        </section>
    </section>
    <style>
        .sadmin-courses-main .sadmin-courses-page {
            padding-top: 1.25rem;
        }
        .sadmin-courses-main .sadmin-course-hero,
        .sadmin-courses-main .sadmin-course-card {
            border: 0;
            border-radius: .65rem;
            box-shadow: 0 .85rem 1.85rem rgba(15, 23, 42, .045);
            overflow: hidden;
        }
        .sadmin-courses-main .sadmin-course-hero .card-header,
        .sadmin-courses-main .sadmin-course-card .card-header {
            padding: .75rem 1rem;
            border-bottom: 1px solid var(--default-border);
            background: var(--custom-white);
        }
        .sadmin-courses-main .sadmin-page-size {
            margin: 0;
        }
        .sadmin-courses-main .sadmin-page-size .form-select {
            min-width: 6.8rem;
            min-height: 2rem;
            font-size: .74rem;
        }
        .sadmin-courses-main .course-mini-stats {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: .65rem;
        }
        .sadmin-courses-main .course-mini-stats article {
            display: grid;
            gap: .25rem;
            padding: .7rem .85rem;
            border: 1px solid var(--default-border);
            border-radius: .5rem;
            background: var(--default-background);
        }
        .sadmin-courses-main .course-mini-stats span {
            color: var(--text-muted);
            font-size: .68rem;
            font-weight: 700;
            text-transform: uppercase;
        }
        .sadmin-courses-main .course-mini-stats strong {
            font-size: 1.15rem;
            line-height: 1;
        }
        .sadmin-courses-main .sadmin-course-table {
            min-width: 62rem;
        }
        .sadmin-courses-main .sadmin-course-table th,
        .sadmin-courses-main .sadmin-course-table td {
            padding: .42rem .65rem !important;
            font-size: .73rem;
            line-height: 1.2;
            vertical-align: middle;
        }
        .sadmin-courses-main .sadmin-course-table th {
            background: var(--default-background);
            font-size: .65rem;
            letter-spacing: .025em;
        }
        .sadmin-courses-main .sadmin-course-table .gr-cell-title {
            font-size: .74rem;
            line-height: 1.2;
        }
        .sadmin-courses-main .sadmin-course-table .gr-cell-subtitle {
            font-size: .67rem;
            line-height: 1.2;
        }
        .sadmin-courses-main .sadmin-course-table .badge {
            min-height: 1.2rem;
            padding: .15rem .45rem;
            border-radius: 999px;
            font-size: .62rem;
            font-weight: 700;
        }
        .sadmin-courses-main .sadmin-course-table .btn-sm {
            min-height: 1.55rem;
            padding: .18rem .55rem;
            font-size: .67rem;
        }
        .sadmin-courses-main .sadmin-pagination {
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
        .sadmin-courses-main .sadmin-pagination > div {
            display: flex;
            align-items: center;
            gap: .35rem;
        }
        .sadmin-courses-main .sadmin-pagination .btn {
            min-height: 1.75rem;
            padding: .2rem .65rem;
            font-size: .7rem;
        }
        .sadmin-courses-main .page-chip {
            display: inline-flex;
            align-items: center;
            min-height: 1.75rem;
            padding: .2rem .55rem;
            border-radius: .35rem;
            background: rgba(var(--primary-rgb), .1);
            color: rgb(var(--primary-rgb));
            font-size: .68rem;
            font-weight: 700;
        }
        .sadmin-courses-main .footer {
            margin-inline: 0 !important;
            width: 100%;
        }
        @media (max-width: 767.98px) {
            .sadmin-courses-main .course-mini-stats {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }
    </style>
</main>
<?php include __DIR__ . '/includes/footer.php'; ?>

