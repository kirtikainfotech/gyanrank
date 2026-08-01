<?php
require_once __DIR__ . '/includes/functions.php';
$user = instructor_user();
ensure_instructor_erp_tables();
$instructorId = (int) $user['id'];
$courses = instructor_courses($instructorId);
$examCategories = instructor_exam_categories($instructorId);
$contents = instructor_course_contents($instructorId);

$courseById = [];
foreach ($courses as $course) {
    $courseById[(int) $course['id']] = $course;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['csrf_token'] ?? null)) {
        $_SESSION['ins_error'] = 'Security token expired.';
        redirect('ins/exams');
    }

    try {
        $examId = (int) ($_POST['exam_id'] ?? 0);
        $courseId = (int) ($_POST['course_id'] ?? 0);
        $courseValue = $courseId > 0 ? $courseId : null;
        $examCategoryId = (int) ($_POST['exam_category_id'] ?? 0);
        $newExamCategory = substr(trim((string) ($_POST['new_exam_category'] ?? '')), 0, 140);
        if ($newExamCategory !== '') {
            $examCategoryId = ensure_exam_category($instructorId, $newExamCategory);
        }
        $examCategoryValue = $examCategoryId > 0 ? $examCategoryId : null;
        $title = substr(trim((string) ($_POST['title'] ?? '')), 0, 180);
        $description = substr(trim((string) ($_POST['description'] ?? '')), 0, 2000);
        $duration = max(1, min(600, (int) ($_POST['duration_minutes'] ?? 60)));
        $examType = ($_POST['exam_type'] ?? 'manual') === 'random' ? 'random' : 'manual';
        $questionLimit = max(1, min(500, (int) ($_POST['question_limit'] ?? 20)));
        $isLive = isset($_POST['is_live']) ? 1 : 0;
        $status = in_array($_POST['status'] ?? '', ['draft', 'published', 'paused'], true) ? (string) $_POST['status'] : 'draft';

        if ($title === '') {
            throw new RuntimeException('Exam title required.');
        }
        if ($courseValue !== null && !isset($courseById[$courseValue])) {
            throw new RuntimeException('Invalid course selected.');
        }
        if ($examCategoryValue !== null) {
            $stmt = db()->prepare('SELECT id FROM instructor_exam_categories WHERE id = ? AND instructor_id = ? AND status = "active" LIMIT 1');
            $stmt->bind_param('ii', $examCategoryValue, $instructorId);
            $stmt->execute();
            if (!$stmt->get_result()->fetch_assoc()) {
                throw new RuntimeException('Invalid exam category selected.');
            }
        }

        if ($examId > 0) {
            $stmt = db()->prepare('UPDATE instructor_exams SET course_id = ?, exam_category_id = ?, title = ?, description = ?, duration_minutes = ?, exam_type = ?, is_live = ?, status = ? WHERE id = ? AND instructor_id = ?');
            $stmt->bind_param('iissisisii', $courseValue, $examCategoryValue, $title, $description, $duration, $examType, $isLive, $status, $examId, $instructorId);
            $stmt->execute();
        } else {
            $stmt = db()->prepare('INSERT INTO instructor_exams (instructor_id, course_id, exam_category_id, title, description, duration_minutes, exam_type, is_live, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)');
            $stmt->bind_param('iiissisis', $instructorId, $courseValue, $examCategoryValue, $title, $description, $duration, $examType, $isLive, $status);
            $stmt->execute();
            $examId = (int) db()->insert_id;
        }

        $stmt = db()->prepare('DELETE FROM instructor_exam_questions WHERE exam_id = ? AND instructor_id = ?');
        $stmt->bind_param('ii', $examId, $instructorId);
        $stmt->execute();
        $stmt = db()->prepare('DELETE FROM instructor_exam_random_rules WHERE exam_id = ? AND instructor_id = ?');
        $stmt->bind_param('ii', $examId, $instructorId);
        $stmt->execute();

        $totalQuestions = 0;
        $totalMarks = 0.0;

        if ($examType === 'manual') {
            $rawIds = trim((string) ($_POST['question_ids'] ?? ''));
            $questionIds = [];
            if ($rawIds !== '') {
                foreach (preg_split('/[\s,]+/', $rawIds) as $value) {
                    $id = (int) $value;
                    if ($id > 0) {
                        $questionIds[$id] = $id;
                    }
                }
            } else {
                if ($courseValue === null) {
                    throw new RuntimeException('Select course or enter question IDs for manual exam.');
                }
                $stmt = db()->prepare('SELECT id FROM instructor_questions WHERE instructor_id = ? AND course_id = ? AND status = "active" ORDER BY id ASC LIMIT ?');
                $stmt->bind_param('iii', $instructorId, $courseValue, $questionLimit);
                $stmt->execute();
                $questionIds = array_map('intval', array_column($stmt->get_result()->fetch_all(MYSQLI_ASSOC), 'id'));
            }

            if ($questionIds) {
                $idList = implode(',', array_map('intval', array_values($questionIds)));
                $stmt = db()->prepare("SELECT id, marks FROM instructor_questions WHERE instructor_id = ? AND id IN ($idList) ORDER BY FIELD(id, $idList)");
                $stmt->bind_param('i', $instructorId);
                $stmt->execute();
                $validRows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

                $insert = db()->prepare('INSERT INTO instructor_exam_questions (instructor_id, exam_id, question_id, marks, sort_order) VALUES (?, ?, ?, ?, ?)');
                $order = 1;
                foreach ($validRows as $row) {
                    $questionId = (int) $row['id'];
                    $marks = (float) $row['marks'];
                    $insert->bind_param('iiidi', $instructorId, $examId, $questionId, $marks, $order);
                    $insert->execute();
                    $totalQuestions++;
                    $totalMarks += $marks;
                    $order++;
                }
            }
        } else {
            $contentId = (int) ($_POST['content_id'] ?? 0);
            $contentValue = $contentId > 0 ? $contentId : null;
            if ($contentValue !== null) {
                $stmt = db()->prepare('SELECT id FROM instructor_course_contents WHERE id = ? AND instructor_id = ? LIMIT 1');
                $stmt->bind_param('ii', $contentValue, $instructorId);
                $stmt->execute();
                if (!$stmt->get_result()->fetch_assoc()) {
                    throw new RuntimeException('Invalid chapter selected.');
                }
            }
            $onlyActive = isset($_POST['only_active']) ? 1 : 0;
            $stmt = db()->prepare('INSERT INTO instructor_exam_random_rules (instructor_id, exam_id, content_id, question_limit, only_active) VALUES (?, ?, ?, ?, ?)');
            $stmt->bind_param('iiiii', $instructorId, $examId, $contentValue, $questionLimit, $onlyActive);
            $stmt->execute();
            $totalQuestions = $questionLimit;
            $totalMarks = (float) $questionLimit;
        }

        $stmt = db()->prepare('UPDATE instructor_exams SET total_questions = ?, total_marks = ? WHERE id = ? AND instructor_id = ?');
        $stmt->bind_param('idii', $totalQuestions, $totalMarks, $examId, $instructorId);
        $stmt->execute();

        $_SESSION['ins_success'] = $examId > 0 ? 'Exam saved successfully.' : 'Exam created successfully.';
    } catch (Throwable $e) {
        $_SESSION['ins_error'] = $e->getMessage();
    }

    redirect('ins/exams');
}

$page = max(1, (int) ($_GET['page'] ?? 1));
$perPage = (int) ($_GET['per_page'] ?? 10);
if (!in_array($perPage, [10, 25, 50], true)) {
    $perPage = 10;
}

$stmt = db()->prepare("
    SELECT
        COUNT(*) AS total,
        COALESCE(SUM(status = 'published'), 0) AS published_total,
        COALESCE(SUM(is_live = 1), 0) AS live_total,
        COALESCE(SUM(exam_type = 'manual'), 0) AS manual_total,
        COALESCE(SUM(exam_type = 'random'), 0) AS random_total
    FROM instructor_exams
    WHERE instructor_id = ?
");
$stmt->bind_param('i', $instructorId);
$stmt->execute();
$examSummary = $stmt->get_result()->fetch_assoc() ?: [];
$totalExams = (int) ($examSummary['total'] ?? 0);
$publishedExams = (int) ($examSummary['published_total'] ?? 0);
$liveExams = (int) ($examSummary['live_total'] ?? 0);
$manualExams = (int) ($examSummary['manual_total'] ?? 0);
$randomExams = (int) ($examSummary['random_total'] ?? 0);
$totalPages = max(1, (int) ceil($totalExams / $perPage));
$page = min($page, $totalPages);
$offset = ($page - 1) * $perPage;

$stmt = db()->prepare("
    SELECT e.*, c.title AS course_title, ec.category_name AS exam_category_name,
        COUNT(DISTINCT eq.id) AS assigned_questions,
        COUNT(DISTINCT rr.id) AS random_rules,
        COUNT(DISTINCT sea.id) AS attempt_count,
        COUNT(DISTINCT sea.student_id) AS attempted_students
    FROM instructor_exams e
    LEFT JOIN instructor_courses c ON c.id = e.course_id AND c.instructor_id = e.instructor_id
    LEFT JOIN instructor_exam_categories ec ON ec.id = e.exam_category_id AND ec.instructor_id = e.instructor_id
    LEFT JOIN instructor_exam_questions eq ON eq.exam_id = e.id
    LEFT JOIN instructor_exam_random_rules rr ON rr.exam_id = e.id
    LEFT JOIN student_exam_attempts sea ON sea.exam_id = e.id
    WHERE e.instructor_id = ?
    GROUP BY e.id, c.title, ec.category_name
    ORDER BY e.updated_at DESC, e.id DESC
    LIMIT ? OFFSET ?
");
$stmt->bind_param('iii', $instructorId, $perPage, $offset);
$stmt->execute();
$exams = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$examStart = $totalExams === 0 ? 0 : $offset + 1;
$examEnd = min($totalExams, $offset + count($exams));
$examPageUrl = static function (array $extra = []) use ($perPage): string {
    $query = ['per_page' => $perPage];
    foreach ($extra as $key => $value) {
        if ($value === null) {
            unset($query[$key]);
        } else {
            $query[$key] = $value;
        }
    }
    return app_url('ins/exams') . '?' . http_build_query($query);
};
$message = $_SESSION['ins_success'] ?? '';
$error = $_SESSION['ins_error'] ?? '';
unset($_SESSION['ins_success'], $_SESSION['ins_error']);
$pageTitle = 'Exams';
$pageSubtitle = 'Create tests, assign questions and publish live exams.';
$activePage = 'exams';
?>
<?php include __DIR__ . '/includes/header.php'; ?>
<?php include __DIR__ . '/includes/sidebar.php'; ?>
<main class="main">
    <?php include __DIR__ . '/includes/topbar.php'; ?>
    <section class="content instructor-content">
        <?php if ($message): ?><div class="notice"><?= h($message); ?></div><?php endif; ?>
        <?php if ($error): ?><div class="notice danger"><?= h($error); ?></div><?php endif; ?>

        <div class="row g-3">
            <div class="col-12">
                <div class="card custom-card bg-primary-gradient border-0 text-fixed-white">
                    <div class="card-body d-flex flex-wrap align-items-center justify-content-between gap-3">
                        <div>
                            <span class="badge bg-white-1 text-fixed-white mb-2">Exam Center</span>
                            <h4 class="fw-semibold mb-1 text-fixed-white"><?= h((string) $totalExams); ?> exams</h4>
                            <p class="mb-0 op-8">Manual/random tests, question assignment, attempts aur result tracking yahan manage karein.</p>
                        </div>
                        <div class="btn-list">
                            <a class="btn btn-light btn-wave" href="#add-exam">Add Exam</a>
                            <a class="btn btn-outline-light btn-wave" href="<?= h(app_url('ins/questions')); ?>">Question Bank</a>
                            <a class="btn btn-outline-light btn-wave" href="<?= h(app_url('ins/exam-management')); ?>">Categories</a>
                        </div>
                    </div>
                </div>
            </div>

            <?php
            $examMetrics = [
                ['label' => 'Total', 'value' => $totalExams, 'icon' => 'bx bx-clipboard', 'tone' => 'primary'],
                ['label' => 'Published', 'value' => $publishedExams, 'icon' => 'bx bx-check-circle', 'tone' => 'success'],
                ['label' => 'Live', 'value' => $liveExams, 'icon' => 'bx bx-broadcast', 'tone' => 'danger'],
                ['label' => 'Manual', 'value' => $manualExams, 'icon' => 'bx bx-list-check', 'tone' => 'info'],
                ['label' => 'Random', 'value' => $randomExams, 'icon' => 'bx bx-shuffle', 'tone' => 'warning'],
            ];
            ?>
            <?php foreach ($examMetrics as $metric): ?>
                <div class="col-6 col-md-4 col-xl">
                    <div class="card custom-card">
                        <div class="card-body d-flex align-items-center gap-3">
                            <span class="avatar avatar-md bg-<?= h($metric['tone']); ?>-transparent text-<?= h($metric['tone']); ?>"><i class="<?= h($metric['icon']); ?> fs-20"></i></span>
                            <div><p class="mb-1 text-muted fs-12 fw-semibold text-uppercase"><?= h($metric['label']); ?></p><h4 class="mb-0 fw-semibold"><?= h((string) $metric['value']); ?></h4></div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>

            <div class="col-12">
                <div class="card custom-card">
                    <div class="card-header justify-content-between">
                        <div>
                            <div class="card-title mb-0">Exam Register</div>
                            <p class="mb-0 text-muted fs-12">Showing <?= h((string) $examStart); ?>-<?= h((string) $examEnd); ?> of <?= h((string) $totalExams); ?> exams.</p>
                        </div>
                        <div class="d-flex flex-wrap align-items-center gap-2">
                            <form method="get" action="<?= h(app_url('ins/exams')); ?>" class="d-flex align-items-center gap-2 m-0">
                                <input type="hidden" name="page" value="1">
                                <select class="form-select form-select-sm" name="per_page" onchange="this.form.submit()" aria-label="Rows per page">
                                    <?php foreach ([10, 25, 50] as $size): ?>
                                        <option value="<?= $size; ?>" <?= $perPage === $size ? 'selected' : ''; ?>><?= $size; ?> rows</option>
                                    <?php endforeach; ?>
                                </select>
                            </form>
                            <a class="btn btn-sm btn-primary btn-wave" href="#add-exam">Add Exam</a>
                        </div>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-sm table-hover align-middle mb-0 gr-register-table gr-exam-table" data-gr-register="1">
                                <colgroup><col style="width: 34%;"><col style="width: 18%;"><col style="width: 8%;"><col style="width: 11%;"><col style="width: 8%;"><col style="width: 8%;"><col style="width: 13%;"></colgroup>
                                <thead><tr><th>Exam</th><th>Source</th><th>Type</th><th>Questions</th><th>Attempts</th><th>Status</th><th class="text-end">Action</th></tr></thead>
                                <tbody>
                                    <?php if (!$exams): ?><tr><td colspan="7" class="text-muted py-4">No exam created yet.</td></tr><?php endif; ?>
                                    <?php foreach ($exams as $exam): ?>
                                        <?php
                                        $statusTone = $exam['status'] === 'published' ? 'success' : ($exam['status'] === 'paused' ? 'warning' : 'secondary');
                                        $typeTone = $exam['exam_type'] === 'manual' ? 'primary' : 'info';
                                        ?>
                                        <tr>
                                            <td>
                                                <span class="fw-semibold text-truncate gr-cell-title"><?= h($exam['title']); ?></span>
                                                <span class="text-muted fs-12"><?= (int) $exam['is_live'] === 1 ? 'Live exam' : 'Offline exam'; ?><?= $exam['source_exam_id'] ? ' - Imported' : ''; ?></span>
                                            </td>
                                            <td>
                                                <span class="fw-semibold text-truncate gr-cell-title"><?= h($exam['exam_category_name'] ?: 'No category'); ?></span>
                                                <span class="text-muted fs-12"><?= h($exam['course_title'] ? $exam['course_title'] : 'No course source'); ?></span>
                                            </td>
                                            <td><span class="badge bg-<?= h($typeTone); ?>-transparent text-<?= h($typeTone); ?>"><?= h(ucfirst($exam['exam_type'])); ?></span></td>
                                            <td><span class="fw-semibold gr-inline-stat"><?= h((string) $exam['total_questions']); ?> Q</span><span class="text-muted fs-12 gr-cell-subtitle"><?= h((string) $exam['duration_minutes']); ?> min / <?= h((string) $exam['total_marks']); ?> marks</span></td>
                                            <td><span class="fw-semibold gr-inline-stat"><?= h((string) ($exam['attempt_count'] ?? 0)); ?></span><span class="text-muted fs-12 gr-cell-subtitle"><?= h((string) ($exam['attempted_students'] ?? 0)); ?> students</span></td>
                                            <td><span class="badge bg-<?= h($statusTone); ?>-transparent text-<?= h($statusTone); ?>"><?= h(ucfirst($exam['status'])); ?></span></td>
                                            <td class="text-end">
                                                <div class="btn-list justify-content-end mb-0">
                                                    <a class="btn btn-sm btn-primary-light btn-wave" href="<?= h(app_url('ins/exam-questions') . '?exam_id=' . (int) $exam['id']); ?>" title="Questions"><i class="bx bx-list-check"></i></a>
                                                    <a class="btn btn-sm btn-light btn-wave" href="<?= h(app_url('ins/exam-performance') . '?exam_id=' . (int) $exam['id']); ?>" title="Report"><i class="bx bx-bar-chart-alt-2"></i></a>
                                                    <a class="btn btn-sm btn-light btn-wave" href="#edit-exam-<?= (int) $exam['id']; ?>" title="Edit"><i class="bx bx-edit"></i></a>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 px-3 py-3 border-top">
                            <span class="text-muted fs-12"><?= h((string) $examStart); ?>-<?= h((string) $examEnd); ?> of <?= h((string) $totalExams); ?> records</span>
                            <div class="btn-list mb-0">
                                <a class="btn btn-sm btn-light btn-wave <?= $page <= 1 ? 'disabled' : ''; ?>" href="<?= h($examPageUrl(['page' => max(1, $page - 1)])); ?>">Prev</a>
                                <span class="badge bg-primary-transparent text-primary align-self-center">Page <?= h((string) $page); ?> / <?= h((string) $totalPages); ?></span>
                                <a class="btn btn-sm btn-light btn-wave <?= $page >= $totalPages ? 'disabled' : ''; ?>" href="<?= h($examPageUrl(['page' => min($totalPages, $page + 1)])); ?>">Next</a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <?php
    $modalExam = null;
    $modalQuestionIds = [];
    $modalRule = null;
    include __DIR__ . '/includes/exam_form.php';
    ?>

    <?php foreach ($exams as $exam): ?>
        <?php
        $modalExam = $exam;
        $modalQuestionIds = instructor_exam_question_ids($instructorId, (int) $exam['id']);
        $modalRule = instructor_exam_random_rule($instructorId, (int) $exam['id']);
        include __DIR__ . '/includes/exam_form.php';
        ?>
    <?php endforeach; ?>
    <script>
        const syncExamChapters = (form) => {
            const course = form.querySelector('[data-exam-course]');
            const chapter = form.querySelector('[data-exam-content]');
            if (!course || !chapter) return;
            chapter.querySelectorAll('option[data-course]').forEach((option) => {
                const show = !course.value || option.dataset.course === course.value;
                option.hidden = !show;
                if (!show && option.selected) chapter.value = '';
            });
        };
        document.querySelectorAll('form').forEach((form) => {
            syncExamChapters(form);
            form.querySelector('[data-exam-course]')?.addEventListener('change', () => syncExamChapters(form));
        });
    </script>
    <style>
        .exam-manage-links { display: flex; gap: 6px; flex-wrap: wrap; align-items: center; }
        .exam-manage-links .table-edit-icon { margin: 0; }
        .gr-exam-table { min-width: 820px !important; }
        .gr-exam-table th,
        .gr-exam-table td {
            padding: .32rem .5rem !important;
            font-size: .76rem;
            line-height: 1.2;
            vertical-align: middle;
        }
        .gr-exam-table thead th {
            font-size: .66rem;
            padding-block: .45rem !important;
            letter-spacing: .02em;
        }
        .gr-exam-table .gr-cell-title {
            font-size: .8rem;
            line-height: 1.2;
            margin-bottom: .12rem;
        }
        .gr-exam-table .gr-cell-subtitle {
            display: block;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            max-width: 100%;
        }
        .gr-exam-table .gr-inline-stat {
            display: block;
            line-height: 1.1;
            margin-bottom: .1rem;
            white-space: nowrap;
        }
        .gr-exam-table .fs-12 {
            font-size: .68rem !important;
            line-height: 1.15;
        }
        .gr-exam-table .badge {
            font-size: .66rem;
            padding: .22rem .42rem;
        }
        .gr-exam-table .btn-list {
            flex-wrap: nowrap !important;
            gap: .2rem;
        }
        .gr-exam-table .btn {
            width: 1.65rem;
            height: 1.65rem;
            min-width: 1.65rem;
            padding: 0 !important;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: .82rem;
        }
    </style>
<?php include __DIR__ . '/includes/footer.php'; ?>

