<?php
require_once __DIR__ . '/includes/functions.php';
$user = instructor_user();
ensure_instructor_erp_tables();
$instructorId = (int) $user['id'];

$page = max(1, (int) ($_GET['page'] ?? 1));
$perPage = (int) ($_GET['per_page'] ?? 10);
if (!in_array($perPage, [10, 25, 50], true)) {
    $perPage = 10;
}

$counts = instructor_counts($instructorId);

$stmt = db()->prepare("
    SELECT
        COUNT(*) AS total_courses,
        COALESCE(SUM(status = 'published'), 0) AS published_courses,
        COALESCE(SUM(is_free = 1), 0) AS free_courses
    FROM instructor_courses
    WHERE instructor_id = ?
");
$stmt->bind_param('i', $instructorId);
$stmt->execute();
$courseSummary = $stmt->get_result()->fetch_assoc() ?: [];

$stmt = db()->prepare("
    SELECT COUNT(*) AS total_enrollments, COALESCE(AVG(sce.progress_percent), 0) AS avg_progress
    FROM student_course_enrollments sce
    INNER JOIN instructor_courses c ON c.id = sce.course_id
    WHERE c.instructor_id = ?
");
$stmt->bind_param('i', $instructorId);
$stmt->execute();
$enrollmentSummary = $stmt->get_result()->fetch_assoc() ?: [];

$stmt = db()->prepare("
    SELECT
        COUNT(sea.id) AS total_attempts,
        COUNT(DISTINCT sea.student_id) AS attempted_students,
        COALESCE(AVG(sea.percentage), 0) AS avg_score
    FROM student_exam_attempts sea
    INNER JOIN instructor_exams e ON e.id = sea.exam_id
    WHERE e.instructor_id = ?
");
$stmt->bind_param('i', $instructorId);
$stmt->execute();
$examAttemptSummary = $stmt->get_result()->fetch_assoc() ?: [];

$stmt = db()->prepare('
    SELECT COUNT(*) AS total
    FROM instructor_classes
    WHERE instructor_id = ?
');
$stmt->bind_param('i', $instructorId);
$stmt->execute();
$totalClassRows = (int) (($stmt->get_result()->fetch_assoc()['total'] ?? 0));
$totalPages = max(1, (int) ceil($totalClassRows / $perPage));
$page = min($page, $totalPages);
$offset = ($page - 1) * $perPage;

$stmt = db()->prepare("
    SELECT c.*, b.batch_name, b.teacher_name
    FROM instructor_classes c
    LEFT JOIN instructor_batches b ON b.id = c.batch_id AND b.instructor_id = c.instructor_id
    WHERE c.instructor_id = ?
    ORDER BY c.class_date DESC, c.starts_at DESC, c.id DESC
    LIMIT ? OFFSET ?
");
$stmt->bind_param('iii', $instructorId, $perPage, $offset);
$stmt->execute();
$classes = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$classStart = $totalClassRows === 0 ? 0 : $offset + 1;
$classEnd = min($totalClassRows, $offset + count($classes));

$stmt = db()->prepare("
    SELECT b.*,
        COUNT(c.id) AS class_count,
        COALESCE(SUM(c.class_status = 'completed'), 0) AS completed_count,
        COALESCE(SUM(c.class_status = 'live'), 0) AS live_count
    FROM instructor_batches b
    LEFT JOIN instructor_classes c ON c.batch_id = b.id AND c.instructor_id = b.instructor_id
    WHERE b.instructor_id = ?
    GROUP BY b.id
    ORDER BY b.id DESC
    LIMIT 8
");
$stmt->bind_param('i', $instructorId);
$stmt->execute();
$batchReports = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$stmt = db()->prepare("
    SELECT e.title, e.status, e.exam_type, e.total_questions, e.total_marks,
        COUNT(sea.id) AS attempts,
        COUNT(DISTINCT sea.student_id) AS students,
        COALESCE(AVG(sea.percentage), 0) AS avg_score
    FROM instructor_exams e
    LEFT JOIN student_exam_attempts sea ON sea.exam_id = e.id
    WHERE e.instructor_id = ?
    GROUP BY e.id
    ORDER BY e.updated_at DESC, e.id DESC
    LIMIT 8
");
$stmt->bind_param('i', $instructorId);
$stmt->execute();
$examReports = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$reportPageUrl = static function (array $extra = []) use ($perPage): string {
    $query = ['per_page' => $perPage];
    foreach ($extra as $key => $value) {
        if ($value === null) {
            unset($query[$key]);
        } else {
            $query[$key] = $value;
        }
    }
    return app_url('ins/reports') . '?' . http_build_query($query);
};

$teachingMinutes = 0;
foreach ($classes as $class) {
    if (($class['class_status'] ?? '') === 'completed') {
        $teachingMinutes += (int) ($class['duration_minutes'] ?? 0);
    }
}
$pageTitle = 'Reports';
$pageSubtitle = 'Teaching, attendance, batch and exam performance reports.';
$activePage = 'reports';
?>
<?php include __DIR__ . '/includes/header.php'; ?>
<?php include __DIR__ . '/includes/sidebar.php'; ?>
<main class="main reports-page">
    <?php include __DIR__ . '/includes/topbar.php'; ?>
    <section class="content instructor-content reports-content">
        <div class="row g-3">
            <div class="col-12">
                <div class="card custom-card bg-primary-gradient border-0 text-fixed-white">
                    <div class="card-body d-flex flex-wrap align-items-center justify-content-between gap-3">
                        <div>
                            <span class="badge bg-white-1 text-fixed-white mb-2">Report Center</span>
                            <h4 class="fw-semibold mb-1 text-fixed-white">Instructor performance</h4>
                            <p class="mb-0 op-8">Teaching activity, batch health, course access aur exam performance ka compact operational view.</p>
                        </div>
                        <div class="btn-list">
                            <a class="btn btn-light btn-wave" href="<?= h(app_url('ins/classes')); ?>">Classes</a>
                            <a class="btn btn-outline-light btn-wave" href="<?= h(app_url('ins/exams')); ?>">Exams</a>
                            <button class="btn btn-outline-light btn-wave" type="button" onclick="window.print()">Print</button>
                        </div>
                    </div>
                </div>
            </div>

            <?php
            $reportMetrics = [
                ['label' => 'Classes', 'value' => $counts['classes'], 'hint' => $counts['completed'] . ' completed', 'icon' => 'bx bx-calendar', 'tone' => 'primary'],
                ['label' => 'Batches', 'value' => $counts['batches'], 'hint' => $counts['live'] . ' live now', 'icon' => 'bx bx-layer', 'tone' => 'info'],
                ['label' => 'Courses', 'value' => (int) ($courseSummary['total_courses'] ?? 0), 'hint' => (int) ($courseSummary['published_courses'] ?? 0) . ' published', 'icon' => 'bx bx-book-open', 'tone' => 'success'],
                ['label' => 'Enrollments', 'value' => (int) ($enrollmentSummary['total_enrollments'] ?? 0), 'hint' => number_format((float) ($enrollmentSummary['avg_progress'] ?? 0), 1) . '% avg progress', 'icon' => 'bx bx-user-check', 'tone' => 'warning'],
                ['label' => 'Attempts', 'value' => (int) ($examAttemptSummary['total_attempts'] ?? 0), 'hint' => number_format((float) ($examAttemptSummary['avg_score'] ?? 0), 1) . '% avg score', 'icon' => 'bx bx-bar-chart-alt-2', 'tone' => 'secondary'],
            ];
            ?>
            <?php foreach ($reportMetrics as $metric): ?>
                <div class="col-6 col-md-4 col-xl">
                    <div class="card custom-card">
                        <div class="card-body d-flex align-items-center gap-3">
                            <span class="avatar avatar-md bg-<?= h($metric['tone']); ?>-transparent text-<?= h($metric['tone']); ?>"><i class="<?= h($metric['icon']); ?> fs-20"></i></span>
                            <div>
                                <p class="mb-1 text-muted fs-12 fw-semibold text-uppercase"><?= h($metric['label']); ?></p>
                                <h4 class="mb-0 fw-semibold"><?= h((string) $metric['value']); ?></h4>
                                <span class="text-muted fs-12"><?= h($metric['hint']); ?></span>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>

            <div class="col-xl-7">
                <div class="card custom-card">
                    <div class="card-header justify-content-between">
                        <div>
                            <div class="card-title mb-0">Teaching Activity</div>
                            <p class="mb-0 text-muted fs-12">Showing <?= h((string) $classStart); ?>-<?= h((string) $classEnd); ?> of <?= h((string) $totalClassRows); ?> classes.</p>
                        </div>
                        <form method="get" action="<?= h(app_url('ins/reports')); ?>" class="d-flex align-items-center gap-2 m-0">
                            <input type="hidden" name="page" value="1">
                            <select class="form-select form-select-sm" name="per_page" onchange="this.form.submit()" aria-label="Rows per page">
                                <?php foreach ([10, 25, 50] as $size): ?>
                                    <option value="<?= $size; ?>" <?= $perPage === $size ? 'selected' : ''; ?>><?= $size; ?> rows</option>
                                <?php endforeach; ?>
                            </select>
                        </form>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-sm table-hover align-middle mb-0 gr-register-table gr-report-table" data-gr-register="1">
                                <colgroup><col style="width: 36%;"><col style="width: 24%;"><col style="width: 14%;"><col style="width: 12%;"><col style="width: 14%;"></colgroup>
                                <thead><tr><th>Class</th><th>Batch</th><th>Date</th><th>Time</th><th>Status</th></tr></thead>
                                <tbody>
                                    <?php if (!$classes): ?><tr><td colspan="5" class="text-muted py-4">No class report found.</td></tr><?php endif; ?>
                                    <?php foreach ($classes as $class): ?>
                                        <?php
                                        $status = (string) ($class['class_status'] ?? 'scheduled');
                                        $statusTone = $status === 'completed' ? 'success' : ($status === 'live' ? 'danger' : ($status === 'cancelled' ? 'secondary' : 'warning'));
                                        ?>
                                        <tr>
                                            <td><span class="fw-semibold text-truncate gr-cell-title"><?= h($class['class_title']); ?></span><span class="text-muted fs-12 gr-cell-subtitle"><?= h((string) ($class['duration_minutes'] ?? 60)); ?> min</span></td>
                                            <td><span class="fw-semibold text-truncate gr-cell-title"><?= h($class['batch_name'] ?: 'Open'); ?></span><span class="text-muted fs-12 gr-cell-subtitle"><?= h($class['teacher_name'] ?: 'Teacher not set'); ?></span></td>
                                            <td><span class="fw-semibold gr-inline-stat"><?= h($class['class_date']); ?></span></td>
                                            <td><span class="text-muted fs-12 gr-cell-subtitle"><?= h(substr((string) ($class['starts_at'] ?? ''), 0, 5) ?: '-'); ?></span></td>
                                            <td><span class="badge bg-<?= h($statusTone); ?>-transparent text-<?= h($statusTone); ?>"><?= h(ucfirst($status)); ?></span></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 px-3 py-3 border-top">
                            <span class="text-muted fs-12"><?= h((string) $classStart); ?>-<?= h((string) $classEnd); ?> of <?= h((string) $totalClassRows); ?> records</span>
                            <div class="btn-list mb-0">
                                <a class="btn btn-sm btn-light btn-wave <?= $page <= 1 ? 'disabled' : ''; ?>" href="<?= h($reportPageUrl(['page' => max(1, $page - 1)])); ?>">Prev</a>
                                <span class="badge bg-primary-transparent text-primary align-self-center">Page <?= h((string) $page); ?> / <?= h((string) $totalPages); ?></span>
                                <a class="btn btn-sm btn-light btn-wave <?= $page >= $totalPages ? 'disabled' : ''; ?>" href="<?= h($reportPageUrl(['page' => min($totalPages, $page + 1)])); ?>">Next</a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-xl-5">
                <div class="card custom-card">
                    <div class="card-header">
                        <div><div class="card-title mb-0">Batch Health</div><p class="mb-0 text-muted fs-12">Latest batches with class completion.</p></div>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-sm table-hover align-middle mb-0 gr-register-table gr-report-table gr-report-side-table" data-gr-register="1">
                                <colgroup><col style="width: 58%;"><col style="width: 22%;"><col style="width: 20%;"></colgroup>
                                <thead><tr><th>Batch</th><th>Classes</th><th>Status</th></tr></thead>
                                <tbody>
                                    <?php if (!$batchReports): ?><tr><td colspan="3" class="text-muted py-4">No batch report found.</td></tr><?php endif; ?>
                                    <?php foreach ($batchReports as $batch): ?>
                                        <?php $percent = (int) $batch['class_count'] > 0 ? round(((int) $batch['completed_count'] / (int) $batch['class_count']) * 100) : 0; ?>
                                        <tr>
                                            <td><span class="fw-semibold text-truncate gr-cell-title"><?= h($batch['batch_name']); ?></span><span class="text-muted fs-12 gr-cell-subtitle"><?= h($batch['course_title']); ?></span></td>
                                            <td><span class="fw-semibold gr-inline-stat"><?= h((string) $batch['class_count']); ?></span><span class="text-muted fs-12 gr-cell-subtitle"><?= h((string) $batch['completed_count']); ?> done</span></td>
                                            <td><span class="badge bg-primary-transparent text-primary"><?= h((string) $percent); ?>%</span></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <div class="card custom-card">
                    <div class="card-header">
                        <div><div class="card-title mb-0">Exam Performance</div><p class="mb-0 text-muted fs-12">Recent exams and attempt quality.</p></div>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-sm table-hover align-middle mb-0 gr-register-table gr-report-table gr-report-side-table" data-gr-register="1">
                                <colgroup><col style="width: 58%;"><col style="width: 22%;"><col style="width: 20%;"></colgroup>
                                <thead><tr><th>Exam</th><th>Attempts</th><th>Score</th></tr></thead>
                                <tbody>
                                    <?php if (!$examReports): ?><tr><td colspan="3" class="text-muted py-4">No exam report found.</td></tr><?php endif; ?>
                                    <?php foreach ($examReports as $exam): ?>
                                        <tr>
                                            <td><span class="fw-semibold text-truncate gr-cell-title"><?= h($exam['title']); ?></span><span class="text-muted fs-12 gr-cell-subtitle"><?= h(ucfirst((string) $exam['status'])); ?> / <?= h(ucfirst((string) $exam['exam_type'])); ?></span></td>
                                            <td><span class="fw-semibold gr-inline-stat"><?= h((string) $exam['attempts']); ?></span><span class="text-muted fs-12 gr-cell-subtitle"><?= h((string) $exam['students']); ?> students</span></td>
                                            <td><span class="badge bg-success-transparent text-success"><?= h(number_format((float) $exam['avg_score'], 1)); ?>%</span></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
    <style>
        .reports-page .reports-content {
            padding-top: 1.25rem;
        }
        .reports-page .footer {
            margin-inline: 0 !important;
            width: 100%;
            text-align: center;
        }
        .reports-page .footer .container-fluid {
            display: flex;
            align-items: center;
            justify-content: center;
            min-width: 0;
        }
        .gr-report-table { min-width: 720px !important; }
        .gr-report-side-table {
            min-width: 0 !important;
            width: 100% !important;
            table-layout: fixed;
        }
        .gr-report-table th,
        .gr-report-table td {
            padding: .34rem .55rem !important;
            font-size: .76rem;
            line-height: 1.2;
            vertical-align: middle;
        }
        .gr-report-table thead th {
            font-size: .66rem;
            padding-block: .45rem !important;
            letter-spacing: .02em;
        }
        .gr-report-table .gr-cell-title {
            font-size: .8rem;
            line-height: 1.2;
            margin-bottom: .12rem;
        }
        .gr-report-table .gr-cell-subtitle,
        .gr-report-table .gr-inline-stat {
            display: block;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            max-width: 100%;
        }
        .gr-report-table .badge {
            font-size: .66rem;
            padding: .22rem .42rem;
            max-width: 100%;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        @media print {
            .app-sidebar,
            .app-header,
            .footer,
            .btn-list,
            .form-select {
                display: none !important;
            }
            .main {
                margin: 0 !important;
            }
            .content {
                padding: 0 !important;
            }
        }
    </style>
<?php include __DIR__ . '/includes/footer.php'; ?>
