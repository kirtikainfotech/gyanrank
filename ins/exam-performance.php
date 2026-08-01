<?php
require_once __DIR__ . '/includes/functions.php';
$user = instructor_user();
ensure_instructor_erp_tables();
$instructorId = (int) $user['id'];
$examId = max(0, (int) ($_GET['exam_id'] ?? 0));

if ($examId <= 0) {
    $_SESSION['ins_error'] = 'Exam missing.';
    redirect('ins/exams');
}

$stmt = db()->prepare('
    SELECT e.*, c.title AS course_title
    FROM instructor_exams e
    LEFT JOIN instructor_courses c ON c.id = e.course_id AND c.instructor_id = e.instructor_id
    WHERE e.id = ? AND e.instructor_id = ?
    LIMIT 1
');
$stmt->bind_param('ii', $examId, $instructorId);
$stmt->execute();
$exam = $stmt->get_result()->fetch_assoc();
if (!$exam) {
    $_SESSION['ins_error'] = 'Exam not found.';
    redirect('ins/exams');
}

$courseId = (int) ($exam['course_id'] ?? 0);
$students = [];

if ($courseId > 0) {
    $stmt = db()->prepare('
        SELECT u.id, u.full_name, u.email, u.phone, e.progress_percent, e.status AS enrollment_status, e.enrolled_at
        FROM student_course_enrollments e
        INNER JOIN users u ON u.id = e.student_id
        WHERE e.course_id = ?
        ORDER BY u.full_name ASC, u.id ASC
    ');
    $stmt->bind_param('i', $courseId);
    $stmt->execute();
    foreach ($stmt->get_result()->fetch_all(MYSQLI_ASSOC) as $row) {
        $studentId = (int) $row['id'];
        $students[$studentId] = [
            'student_id' => $studentId,
            'student_name' => (string) ($row['full_name'] ?: 'Student'),
            'email' => (string) ($row['email'] ?? ''),
            'phone' => (string) ($row['phone'] ?? ''),
            'progress_percent' => (float) ($row['progress_percent'] ?? 0),
            'enrollment_status' => (string) ($row['enrollment_status'] ?? 'active'),
            'enrolled_at' => (string) ($row['enrolled_at'] ?? ''),
            'attempts_count' => 0,
            'best_percentage' => null,
            'avg_percentage' => null,
            'latest_attempt_id' => 0,
            'latest_score' => 0,
            'latest_total_marks' => 0,
            'latest_percentage' => 0,
            'latest_correct' => 0,
            'latest_wrong' => 0,
            'latest_skipped' => 0,
            'latest_questions' => 0,
            'latest_submitted_at' => '',
        ];
    }
}

$stmt = db()->prepare('
    SELECT a.student_id,
           COALESCE(u.full_name, "Student") AS student_name,
           COALESCE(u.email, "") AS email,
           COALESCE(u.phone, "") AS phone,
           COUNT(a.id) AS attempts_count,
           MAX(a.percentage) AS best_percentage,
           AVG(a.percentage) AS avg_percentage,
           (
               SELECT la.id
               FROM student_exam_attempts la
               WHERE la.exam_id = a.exam_id AND la.student_id = a.student_id
               ORDER BY la.submitted_at DESC, la.id DESC
               LIMIT 1
           ) AS latest_attempt_id
    FROM student_exam_attempts a
    LEFT JOIN users u ON u.id = a.student_id
    WHERE a.exam_id = ?
    GROUP BY a.student_id, u.full_name, u.email, u.phone
    ORDER BY MAX(a.submitted_at) DESC, a.student_id DESC
');
$stmt->bind_param('i', $examId);
$stmt->execute();
$attemptRows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$latestIds = [];
foreach ($attemptRows as $row) {
    $studentId = (int) $row['student_id'];
    if (!isset($students[$studentId])) {
        $students[$studentId] = [
            'student_id' => $studentId,
            'student_name' => (string) ($row['student_name'] ?: 'Student'),
            'email' => (string) ($row['email'] ?? ''),
            'phone' => (string) ($row['phone'] ?? ''),
            'progress_percent' => 0,
            'enrollment_status' => $courseId > 0 ? 'not enrolled' : 'exam student',
            'enrolled_at' => '',
        ];
    }
    $students[$studentId]['attempts_count'] = (int) $row['attempts_count'];
    $students[$studentId]['best_percentage'] = (float) $row['best_percentage'];
    $students[$studentId]['avg_percentage'] = (float) $row['avg_percentage'];
    $students[$studentId]['latest_attempt_id'] = (int) $row['latest_attempt_id'];
    if ((int) $row['latest_attempt_id'] > 0) {
        $latestIds[] = (int) $row['latest_attempt_id'];
    }
}

if ($latestIds) {
    $idList = implode(',', array_map('intval', array_unique($latestIds)));
    $latestResult = db()->query("
        SELECT id, student_id, score, total_marks, total_questions, correct_count, wrong_count, skipped_count, percentage, submitted_at
        FROM student_exam_attempts
        WHERE id IN ($idList)
    ");
    while ($row = $latestResult->fetch_assoc()) {
        $studentId = (int) $row['student_id'];
        if (!isset($students[$studentId])) {
            continue;
        }
        $students[$studentId]['latest_score'] = (float) $row['score'];
        $students[$studentId]['latest_total_marks'] = (float) $row['total_marks'];
        $students[$studentId]['latest_percentage'] = (float) $row['percentage'];
        $students[$studentId]['latest_correct'] = (int) $row['correct_count'];
        $students[$studentId]['latest_wrong'] = (int) $row['wrong_count'];
        $students[$studentId]['latest_skipped'] = (int) $row['skipped_count'];
        $students[$studentId]['latest_questions'] = (int) $row['total_questions'];
        $students[$studentId]['latest_submitted_at'] = (string) $row['submitted_at'];
    }
}

uasort($students, static function (array $a, array $b): int {
    if ((int) $b['attempts_count'] !== (int) $a['attempts_count']) {
        return (int) $b['attempts_count'] <=> (int) $a['attempts_count'];
    }
    return strcmp((string) $a['student_name'], (string) $b['student_name']);
});

$summary = [
    'students' => count($students),
    'attempted_students' => 0,
    'total_attempts' => 0,
    'avg_percentage' => 0.0,
    'best_percentage' => 0.0,
];
$avgPool = [];
foreach ($students as $student) {
    $attempts = (int) ($student['attempts_count'] ?? 0);
    $summary['total_attempts'] += $attempts;
    if ($attempts > 0) {
        $summary['attempted_students']++;
        $avgPool[] = (float) ($student['latest_percentage'] ?? 0);
        $summary['best_percentage'] = max($summary['best_percentage'], (float) ($student['best_percentage'] ?? 0));
    }
}
$summary['avg_percentage'] = $avgPool ? round(array_sum($avgPool) / count($avgPool), 2) : 0.0;

function ins_exam_perf_date(string $date): string
{
    if ($date === '') {
        return 'Not attempted';
    }
    $time = strtotime($date);
    return $time ? date('d M Y, h:i A', $time) : $date;
}

$pageTitle = 'Exam Performance';
$pageSubtitle = 'Student attempts, score and latest performance.';
$activePage = 'exams';
?>
<?php include __DIR__ . '/includes/header.php'; ?>
<?php include __DIR__ . '/includes/sidebar.php'; ?>
<main class="main">
    <?php include __DIR__ . '/includes/topbar.php'; ?>
    <section class="content instructor-content">
        <section class="settings-detail-card ins-card performance-page">
            <div class="detail-head compact-head">
                <div>
                    <span>Performance Report</span>
                    <h2><?= h((string) $exam['title']); ?></h2>
                    <p><?= h((string) ($exam['course_title'] ?: 'All courses')); ?> - <?= h((string) $exam['total_questions']); ?> questions - <?= h((string) $exam['duration_minutes']); ?> min</p>
                </div>
                <div class="perf-actions">
                    <a class="modal-button ghost" href="<?= h(app_url('ins/exams')); ?>">Back Exams</a>
                    <a class="modal-button" href="<?= h(app_url('ins/exam-questions') . '?exam_id=' . $examId); ?>">Assign Questions</a>
                </div>
            </div>

            <div class="perf-grid">
                <div><span>Students</span><strong><?= h((string) $summary['students']); ?></strong><small><?= h((string) $summary['attempted_students']); ?> attempted</small></div>
                <div><span>Total Attempts</span><strong><?= h((string) $summary['total_attempts']); ?></strong><small>All submitted tests</small></div>
                <div><span>Average</span><strong><?= h(number_format($summary['avg_percentage'], 2)); ?>%</strong><small>Latest attempt avg</small></div>
                <div><span>Best</span><strong><?= h(number_format($summary['best_percentage'], 2)); ?>%</strong><small>Highest student score</small></div>
            </div>

            <table class="role-access-table smart-table perf-table">
                <thead>
                    <tr>
                        <th>Student</th>
                        <th>Contact</th>
                        <th>Course</th>
                        <th>Attempts</th>
                        <th>Best</th>
                        <th>Latest</th>
                        <th>Performance</th>
                        <th>Last Attempt</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!$students): ?>
                        <tr><td colspan="8">No student data available yet.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($students as $student): ?>
                        <?php
                        $attempts = (int) ($student['attempts_count'] ?? 0);
                        $latestPercent = (float) ($student['latest_percentage'] ?? 0);
                        $barClass = $latestPercent >= 75 ? 'good' : ($latestPercent >= 40 ? 'mid' : 'low');
                        ?>
                        <tr>
                            <td>
                                <strong><?= h((string) $student['student_name']); ?></strong>
                                <small>ID #<?= h((string) $student['student_id']); ?></small>
                            </td>
                            <td>
                                <?= h((string) ($student['email'] ?: 'No email')); ?>
                                <small><?= h((string) ($student['phone'] ?: 'No phone')); ?></small>
                            </td>
                            <td>
                                <span class="status-pill <?= ($student['enrollment_status'] ?? '') === 'active' ? 'ready' : 'empty'; ?>"><?= h(ucfirst((string) ($student['enrollment_status'] ?? 'student'))); ?></span>
                                <small><?= h(number_format((float) ($student['progress_percent'] ?? 0), 0)); ?>% course progress</small>
                            </td>
                            <td><strong><?= h((string) $attempts); ?></strong><small><?= $attempts === 1 ? 'attempt' : 'attempts'; ?></small></td>
                            <td><?= $attempts > 0 ? h(number_format((float) $student['best_percentage'], 2)) . '%' : 'Not set'; ?></td>
                            <td>
                                <?php if ($attempts > 0): ?>
                                    <strong><?= h(number_format((float) $student['latest_score'], 2)); ?>/<?= h(number_format((float) $student['latest_total_marks'], 2)); ?></strong>
                                    <small><?= h(number_format($latestPercent, 2)); ?>%</small>
                                <?php else: ?>
                                    <span>Not attempted</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="perf-meter"><i class="<?= h($barClass); ?>" style="width: <?= h((string) min(100, max(0, $latestPercent))); ?>%"></i></div>
                                <small><?= h((string) ($student['latest_correct'] ?? 0)); ?> right, <?= h((string) ($student['latest_wrong'] ?? 0)); ?> wrong, <?= h((string) ($student['latest_skipped'] ?? 0)); ?> skipped</small>
                            </td>
                            <td><?= h(ins_exam_perf_date((string) ($student['latest_submitted_at'] ?? ''))); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </section>
    </section>

    <style>
        .performance-page .compact-head { align-items: flex-start; gap: 12px; }
        .perf-actions { display: flex; gap: 8px; flex-wrap: wrap; justify-content: flex-end; }
        .perf-grid { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 8px; margin: 10px 0 12px; }
        .perf-grid div { border: 1px solid #d8e2f1; background: #f8fbff; padding: 9px 10px; min-height: 68px; }
        .perf-grid span, .perf-grid small, .perf-table small { display: block; color: #64748b; font-size: 11px; line-height: 1.3; }
        .perf-grid strong { display: block; color: #0f172a; font-size: 21px; line-height: 1.05; margin: 3px 0; }
        .perf-table th, .perf-table td { padding: 6px 8px; vertical-align: middle; }
        .perf-table strong { font-size: 13px; }
        .perf-meter { height: 7px; width: 120px; max-width: 100%; background: #e9eef6; border: 1px solid #d8e2f1; overflow: hidden; margin-bottom: 4px; }
        .perf-meter i { display: block; height: 100%; background: #ef4444; }
        .perf-meter i.mid { background: #f59e0b; }
        .perf-meter i.good { background: #16a34a; }
        @media (max-width: 900px) {
            .perf-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
            .perf-actions { justify-content: flex-start; }
        }
    </style>
<?php include __DIR__ . '/includes/footer.php'; ?>
