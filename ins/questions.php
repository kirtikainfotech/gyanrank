<?php
require_once __DIR__ . '/includes/functions.php';
$user = instructor_user();
ensure_instructor_erp_tables();
$instructorId = (int) $user['id'];
$courses = instructor_courses($instructorId);
$contents = instructor_course_contents($instructorId);

function question_csv_headers(): array
{
    return [
        'course_title',
        'chapter_title',
        'q_type',
        'question_en',
        'question_hi',
        'option_a_en',
        'option_a_hi',
        'option_b_en',
        'option_b_hi',
        'option_c_en',
        'option_c_hi',
        'option_d_en',
        'option_d_hi',
        'correct_key',
        'marks',
        'solution',
        'status',
    ];
}

function send_question_csv(string $filename, array $headers, callable $writer)
{
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF");
    fputcsv($out, $headers);
    $writer($out);
    fclose($out);
    exit;
}

function normalize_question_key(string $value): string
{
    return strtolower(trim(preg_replace('/\s+/', ' ', $value)));
}

function ins_mysqli_bind_dynamic(mysqli_stmt $stmt, string $types, array $params): void
{
    $refs = [$types];
    foreach ($params as $key => $value) {
        $params[$key] = $value;
        $refs[] = &$params[$key];
    }
    $stmt->bind_param(...$refs);
}

if (($_GET['action'] ?? '') === 'sample_csv') {
    send_question_csv('question-import-sample.csv', question_csv_headers(), static function ($out): void {
        fputcsv($out, [
            'CCC Live Class',
            'Introduction',
            'MCQ',
            'What does CPU stand for?',
            'CPU ka full form kya hai?',
            'Central Processing Unit',
            'Central Processing Unit',
            'Computer Personal Unit',
            'Computer Personal Unit',
            'Central Print Unit',
            'Central Print Unit',
            'Control Processing User',
            'Control Processing User',
            'A',
            '1',
            'CPU is the main processing unit of a computer.',
            'active',
        ]);
        fputcsv($out, [
            'O Level M1-R5 Live Class',
            '',
            'TF',
            'HTML is used to structure web pages.',
            'HTML web pages ka structure banane ke liye use hota hai.',
            '',
            '',
            '',
            '',
            '',
            '',
            '',
            '',
            'TRUE',
            '1',
            '',
            'active',
        ]);
    });
}

if (($_GET['action'] ?? '') === 'export_csv') {
    $stmt = db()->prepare("
        SELECT q.*, c.title AS course_title, cc.content_title
        FROM instructor_questions q
        INNER JOIN instructor_courses c ON c.id = q.course_id AND c.instructor_id = q.instructor_id
        LEFT JOIN instructor_course_contents cc ON cc.id = q.content_id AND cc.instructor_id = q.instructor_id
        WHERE q.instructor_id = ?
        ORDER BY q.id DESC
    ");
    $stmt->bind_param('i', $instructorId);
    $stmt->execute();
    $exportRows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    send_question_csv('questions-export.csv', question_csv_headers(), static function ($out) use ($exportRows): void {
        foreach ($exportRows as $row) {
            fputcsv($out, [
                $row['course_title'] ?? '',
                $row['content_title'] ?? '',
                $row['q_type'] ?? 'MCQ',
                $row['question_en'] ?? '',
                $row['question_hi'] ?? '',
                $row['option_a_en'] ?? '',
                $row['option_a_hi'] ?? '',
                $row['option_b_en'] ?? '',
                $row['option_b_hi'] ?? '',
                $row['option_c_en'] ?? '',
                $row['option_c_hi'] ?? '',
                $row['option_d_en'] ?? '',
                $row['option_d_hi'] ?? '',
                $row['correct_key'] ?? 'A',
                $row['marks'] ?? '1',
                $row['solution'] ?? '',
                $row['status'] ?? 'active',
            ]);
        }
    });
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['csrf_token'] ?? null)) {
        $_SESSION['ins_error'] = 'Security token expired.';
        redirect('ins/questions');
    }

    try {
        if (($_POST['form_action'] ?? '') === 'import_csv') {
            if (empty($_FILES['question_csv']['tmp_name']) || !is_uploaded_file($_FILES['question_csv']['tmp_name'])) {
                throw new RuntimeException('Please choose a CSV file.');
            }

            $courseMap = [];
            foreach ($courses as $course) {
                $courseMap[normalize_question_key((string) $course['title'])] = (int) $course['id'];
            }

            $chapterMap = [];
            foreach ($contents as $content) {
                $courseIdForChapter = (int) ($content['course_id'] ?? 0);
                $chapterMap[$courseIdForChapter][normalize_question_key((string) $content['content_title'])] = (int) $content['id'];
            }

            $file = fopen($_FILES['question_csv']['tmp_name'], 'r');
            if (!$file) {
                throw new RuntimeException('Unable to read CSV file.');
            }

            $headers = fgetcsv($file);
            if (!$headers) {
                throw new RuntimeException('CSV file is empty.');
            }
            $headers[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string) $headers[0]);
            $headerMap = [];
            foreach ($headers as $index => $header) {
                $headerMap[normalize_question_key((string) $header)] = $index;
            }

            $pick = static function (array $row, array $headerMap, string $key): string {
                $index = $headerMap[normalize_question_key($key)] ?? null;
                return $index === null ? '' : trim((string) ($row[$index] ?? ''));
            };

            $inserted = 0;
            $skipped = 0;
            $stmt = db()->prepare('
                INSERT INTO instructor_questions
                    (instructor_id, course_id, content_id, q_type, question_en, question_hi,
                     option_a_en, option_a_hi, option_b_en, option_b_hi, option_c_en, option_c_hi,
                     option_d_en, option_d_hi, correct_key, marks, solution, status)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ');

            while (($row = fgetcsv($file)) !== false) {
                $courseTitle = $pick($row, $headerMap, 'course_title');
                $courseId = $courseMap[normalize_question_key($courseTitle)] ?? 0;
                $questionEn = $pick($row, $headerMap, 'question_en');
                if ($courseId <= 0 || $questionEn === '') {
                    $skipped++;
                    continue;
                }

                $chapterTitle = $pick($row, $headerMap, 'chapter_title');
                $contentValue = $chapterTitle !== '' ? ($chapterMap[$courseId][normalize_question_key($chapterTitle)] ?? null) : null;
                $qType = strtoupper($pick($row, $headerMap, 'q_type')) === 'TF' ? 'TF' : 'MCQ';
                $correctKey = strtoupper($pick($row, $headerMap, 'correct_key') ?: ($qType === 'TF' ? 'TRUE' : 'A'));
                $validAnswers = $qType === 'TF' ? ['TRUE', 'FALSE'] : ['A', 'B', 'C', 'D'];
                if (!in_array($correctKey, $validAnswers, true)) {
                    $skipped++;
                    continue;
                }

                $questionHi = $pick($row, $headerMap, 'question_hi');
                $optionAEn = $pick($row, $headerMap, 'option_a_en');
                $optionAHi = $pick($row, $headerMap, 'option_a_hi');
                $optionBEn = $pick($row, $headerMap, 'option_b_en');
                $optionBHi = $pick($row, $headerMap, 'option_b_hi');
                $optionCEn = $pick($row, $headerMap, 'option_c_en');
                $optionCHi = $pick($row, $headerMap, 'option_c_hi');
                $optionDEn = $pick($row, $headerMap, 'option_d_en');
                $optionDHi = $pick($row, $headerMap, 'option_d_hi');
                $marks = max(0, (float) ($pick($row, $headerMap, 'marks') ?: 1));
                $solution = $pick($row, $headerMap, 'solution');
                $status = strtolower($pick($row, $headerMap, 'status')) === 'inactive' ? 'inactive' : 'active';

                $stmt->bind_param('iiissssssssssssdss', $instructorId, $courseId, $contentValue, $qType, $questionEn, $questionHi, $optionAEn, $optionAHi, $optionBEn, $optionBHi, $optionCEn, $optionCHi, $optionDEn, $optionDHi, $correctKey, $marks, $solution, $status);
                $stmt->execute();
                $inserted++;
            }
            fclose($file);
            $_SESSION['ins_success'] = "CSV import complete. {$inserted} added, {$skipped} skipped.";
            redirect('ins/questions');
        }

        $questionId = (int) ($_POST['question_id'] ?? 0);
        $courseId = (int) ($_POST['course_id'] ?? 0);
        $contentId = (int) ($_POST['content_id'] ?? 0);
        $contentValue = $contentId > 0 ? $contentId : null;
        $qType = ($_POST['q_type'] ?? 'MCQ') === 'TF' ? 'TF' : 'MCQ';
        $questionEn = trim((string) ($_POST['question_en'] ?? ''));
        $questionHi = trim((string) ($_POST['question_hi'] ?? ''));
        $optionAEn = trim((string) ($_POST['option_a_en'] ?? ''));
        $optionAHi = trim((string) ($_POST['option_a_hi'] ?? ''));
        $optionBEn = trim((string) ($_POST['option_b_en'] ?? ''));
        $optionBHi = trim((string) ($_POST['option_b_hi'] ?? ''));
        $optionCEn = trim((string) ($_POST['option_c_en'] ?? ''));
        $optionCHi = trim((string) ($_POST['option_c_hi'] ?? ''));
        $optionDEn = trim((string) ($_POST['option_d_en'] ?? ''));
        $optionDHi = trim((string) ($_POST['option_d_hi'] ?? ''));
        $correctKey = strtoupper(trim((string) ($_POST['correct_key'] ?? 'A')));
        $marks = max(0, (float) ($_POST['marks'] ?? 1));
        $solution = trim((string) ($_POST['solution'] ?? ''));
        $status = ($_POST['status'] ?? 'active') === 'inactive' ? 'inactive' : 'active';

        if ($courseId <= 0 || $questionEn === '') {
            throw new RuntimeException('Course and question are required.');
        }

        $courseCheck = db()->prepare('SELECT id FROM instructor_courses WHERE id = ? AND instructor_id = ? LIMIT 1');
        $courseCheck->bind_param('ii', $courseId, $instructorId);
        $courseCheck->execute();
        if (!$courseCheck->get_result()->fetch_assoc()) {
            throw new RuntimeException('Invalid course selected.');
        }

        if ($contentValue !== null) {
            $contentCheck = db()->prepare('SELECT id FROM instructor_course_contents WHERE id = ? AND course_id = ? AND instructor_id = ? LIMIT 1');
            $contentCheck->bind_param('iii', $contentValue, $courseId, $instructorId);
            $contentCheck->execute();
            if (!$contentCheck->get_result()->fetch_assoc()) {
                throw new RuntimeException('Selected chapter does not belong to this course.');
            }
        }

        $validAnswers = $qType === 'TF' ? ['TRUE', 'FALSE'] : ['A', 'B', 'C', 'D'];
        if (!in_array($correctKey, $validAnswers, true)) {
            throw new RuntimeException('Correct answer is invalid.');
        }

        if ($questionId > 0) {
            $stmt = db()->prepare('
                UPDATE instructor_questions SET
                    course_id = ?, content_id = ?, q_type = ?, question_en = ?, question_hi = ?,
                    option_a_en = ?, option_a_hi = ?, option_b_en = ?, option_b_hi = ?,
                    option_c_en = ?, option_c_hi = ?, option_d_en = ?, option_d_hi = ?,
                    correct_key = ?, marks = ?, solution = ?, status = ?
                WHERE id = ? AND instructor_id = ?
            ');
            $stmt->bind_param('iissssssssssssdssii', $courseId, $contentValue, $qType, $questionEn, $questionHi, $optionAEn, $optionAHi, $optionBEn, $optionBHi, $optionCEn, $optionCHi, $optionDEn, $optionDHi, $correctKey, $marks, $solution, $status, $questionId, $instructorId);
            $stmt->execute();
            $_SESSION['ins_success'] = 'Question updated.';
        } else {
            $stmt = db()->prepare('
                INSERT INTO instructor_questions
                    (instructor_id, course_id, content_id, q_type, question_en, question_hi,
                     option_a_en, option_a_hi, option_b_en, option_b_hi, option_c_en, option_c_hi,
                     option_d_en, option_d_hi, correct_key, marks, solution, status)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ');
            $stmt->bind_param('iiissssssssssssdss', $instructorId, $courseId, $contentValue, $qType, $questionEn, $questionHi, $optionAEn, $optionAHi, $optionBEn, $optionBHi, $optionCEn, $optionCHi, $optionDEn, $optionDHi, $correctKey, $marks, $solution, $status);
            $stmt->execute();
            $_SESSION['ins_success'] = 'Question added.';
        }
    } catch (Throwable $e) {
        $_SESSION['ins_error'] = $e->getMessage();
    }
    redirect('ins/questions');
}

$language = ($_GET['lang'] ?? 'en') === 'hi' ? 'hi' : 'en';
$selectedExamId = max(0, (int) ($_GET['exam_id'] ?? 0));
$page = max(1, (int) ($_GET['page'] ?? 1));
$perPage = (int) ($_GET['per_page'] ?? 10);
if (!in_array($perPage, [10, 25, 50], true)) {
    $perPage = 10;
}
$selectedExam = null;
$examQuestionMode = '';
$totalQuestions = 0;
$activeQuestions = 0;
$mcqQuestions = 0;
$tfQuestions = 0;
$usesDefaultQuestionBank = false;
if ($selectedExamId > 0) {
    $stmt = db()->prepare('
        SELECT e.*, c.title AS course_title
        FROM instructor_exams e
        LEFT JOIN instructor_courses c ON c.id = e.course_id
        WHERE e.id = ? AND e.instructor_id = ?
        LIMIT 1
    ');
    $stmt->bind_param('ii', $selectedExamId, $instructorId);
    $stmt->execute();
    $selectedExam = $stmt->get_result()->fetch_assoc() ?: null;
}
if ($selectedExam && $selectedExam['exam_type'] === 'manual') {
    $ids = instructor_exam_question_ids($instructorId, $selectedExamId);
    if ($ids) {
        $idList = implode(',', array_map('intval', $ids));
        $stmt = db()->prepare("
            SELECT q.*, c.title AS course_title, cc.content_title
            FROM instructor_questions q
            INNER JOIN instructor_exam_questions eq ON eq.question_id = q.id AND eq.exam_id = ?
            LEFT JOIN instructor_courses c ON c.id = q.course_id
            LEFT JOIN instructor_course_contents cc ON cc.id = q.content_id AND cc.instructor_id = q.instructor_id
            WHERE q.instructor_id = ? AND q.id IN ($idList)
            ORDER BY eq.sort_order ASC, eq.id ASC
        ");
        $stmt->bind_param('ii', $selectedExamId, $instructorId);
        $stmt->execute();
        $questions = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    } else {
        $questions = [];
    }
    $examQuestionMode = 'Manual assigned questions';
} elseif ($selectedExam && $selectedExam['exam_type'] === 'random') {
    $rules = [];
    $stmt = db()->prepare('SELECT * FROM instructor_exam_random_rules WHERE instructor_id = ? AND exam_id = ? ORDER BY id ASC');
    $stmt->bind_param('ii', $instructorId, $selectedExamId);
    $stmt->execute();
    $rules = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $parts = [];
    $types = 'i';
    $params = [$instructorId];
    foreach ($rules as $rule) {
        $where = ['1 = 1'];
        $localParams = [];
        if ((int) ($selectedExam['course_id'] ?? 0) > 0) {
            $where[] = 'q.course_id = ?';
            $types .= 'i';
            $localParams[] = (int) $selectedExam['course_id'];
        }
        if ((int) ($rule['content_id'] ?? 0) > 0) {
            $where[] = 'q.content_id = ?';
            $types .= 'i';
            $localParams[] = (int) $rule['content_id'];
        }
        if ((int) ($rule['only_active'] ?? 1) === 1) {
            $where[] = "q.status = 'active'";
        }
        $parts[] = '(' . implode(' AND ', $where) . ')';
        $params = array_merge($params, $localParams ?? []);
        unset($localParams);
    }
    if (!$parts) {
        $parts[] = '1 = 1';
    }
    $sql = '
        SELECT DISTINCT q.*, c.title AS course_title, cc.content_title
        FROM instructor_questions q
        LEFT JOIN instructor_courses c ON c.id = q.course_id
        LEFT JOIN instructor_course_contents cc ON cc.id = q.content_id AND cc.instructor_id = q.instructor_id
        WHERE q.instructor_id = ? AND (' . implode(' OR ', $parts) . ')
        ORDER BY q.status ASC, q.id DESC
    ';
    $stmt = db()->prepare($sql);
    ins_mysqli_bind_dynamic($stmt, $types, $params);
    $stmt->execute();
    $questions = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $examQuestionMode = 'Random pool questions';
} else {
    $usesDefaultQuestionBank = true;
    $stmt = db()->prepare("
        SELECT
            COUNT(*) AS total,
            COALESCE(SUM(status = 'active'), 0) AS active_total,
            COALESCE(SUM(q_type = 'MCQ'), 0) AS mcq_total,
            COALESCE(SUM(q_type = 'TF'), 0) AS tf_total
        FROM instructor_questions
        WHERE instructor_id = ?
    ");
    $stmt->bind_param('i', $instructorId);
    $stmt->execute();
    $questionSummary = $stmt->get_result()->fetch_assoc() ?: [];
    $totalQuestions = (int) ($questionSummary['total'] ?? 0);
    $activeQuestions = (int) ($questionSummary['active_total'] ?? 0);
    $mcqQuestions = (int) ($questionSummary['mcq_total'] ?? 0);
    $tfQuestions = (int) ($questionSummary['tf_total'] ?? 0);
    $totalPages = max(1, (int) ceil($totalQuestions / $perPage));
    $page = min($page, $totalPages);
    $questions = instructor_questions($instructorId, $perPage, ($page - 1) * $perPage);
}
if (!$usesDefaultQuestionBank) {
    $totalQuestions = count($questions);
    $activeQuestions = count(array_filter($questions, fn($question) => ($question['status'] ?? '') === 'active'));
    $mcqQuestions = count(array_filter($questions, fn($question) => ($question['q_type'] ?? '') === 'MCQ'));
    $tfQuestions = count(array_filter($questions, fn($question) => ($question['q_type'] ?? '') === 'TF'));
    $totalPages = max(1, (int) ceil($totalQuestions / $perPage));
    $page = min($page, $totalPages);
    $questions = array_slice($questions, ($page - 1) * $perPage, $perPage);
}
$questionStart = $totalQuestions === 0 ? 0 : (($page - 1) * $perPage) + 1;
$questionEnd = min($totalQuestions, $page * $perPage);
$questionText = static function (array $question, string $englishKey, string $hindiKey) use ($language): string {
    $primary = trim((string) ($question[$language === 'hi' ? $hindiKey : $englishKey] ?? ''));
    $fallback = trim((string) ($question[$language === 'hi' ? $englishKey : $hindiKey] ?? ''));
    return $primary !== '' ? $primary : $fallback;
};
$questionPageUrl = static function (string $action = '', array $extra = []) use ($language, $selectedExamId, $perPage): string {
    $query = ['lang' => $language, 'per_page' => $perPage];
    if ($selectedExamId > 0) {
        $query['exam_id'] = $selectedExamId;
    }
    if ($action !== '') {
        $query['action'] = $action;
    }
    foreach ($extra as $key => $value) {
        if ($value === null) {
            unset($query[$key]);
        } else {
            $query[$key] = $value;
        }
    }
    return app_url('ins/questions') . '?' . http_build_query($query);
};
$message = $_SESSION['ins_success'] ?? '';
$error = $_SESSION['ins_error'] ?? '';
unset($_SESSION['ins_success'], $_SESSION['ins_error']);
$pageTitle = 'Questions';
$pageSubtitle = 'Manage MCQ, True/False, right answer and solution.';
$activePage = 'questions';
?>
<?php include __DIR__ . '/includes/header.php'; ?>
<?php include __DIR__ . '/includes/sidebar.php'; ?>
<style>
    .question-toolbar {
        display: flex;
        align-items: center;
        justify-content: flex-end;
        gap: 8px;
        flex-wrap: wrap;
        margin: 0;
    }
    .question-actions {
        display: flex;
        align-items: center;
        gap: 8px;
        flex-wrap: wrap;
    }
    .question-language-filter {
        display: flex;
        align-items: center;
        gap: 8px;
        color: #475569;
        font-weight: 700;
        white-space: nowrap;
    }
    .question-language-filter select {
        min-width: 112px;
        border: 1px solid #cbd5e1;
        border-radius: 6px;
        padding: 5px 8px;
        background: #fff;
        color: #0f172a;
        font-weight: 700;
        font-size: 12px;
    }
    .question-mini-button {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-height: 34px;
        border: 1px solid #cbd5e1;
        border-radius: 6px;
        padding: 6px 10px;
        background: #fff;
        color: #0f172a;
        font-size: 12px;
        font-weight: 800;
        text-decoration: none;
        white-space: nowrap;
    }
    .question-mini-button.primary {
        border-color: #1d4ed8;
        background: #1d4ed8;
        color: #fff;
    }
    .question-text-cell {
        min-width: 210px;
        max-width: 300px;
    }
    .question-options-cell {
        min-width: 250px;
        max-width: 360px;
    }
    .question-option-chip {
        display: inline-block;
        margin: 2px 3px 2px 0;
        padding: 3px 6px;
        border: 1px solid #fecaca;
        border-radius: 6px;
        background: #fef2f2;
        color: #991b1b;
        font-size: 11px;
        line-height: 1.25;
        max-width: 100%;
        vertical-align: top;
    }
    .question-option-chip b {
        display: inline-block;
        min-width: 14px;
    }
    .question-option-chip.right {
        border-color: #86efac;
        background: #ecfdf5;
        color: #166534;
    }
    .question-answer-pill {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-width: 34px;
        border-radius: 999px;
        padding: 3px 8px;
        background: #dcfce7;
        color: #166534;
        font-weight: 800;
        font-size: 12px;
    }
    @media (max-width: 760px) {
        .question-toolbar {
            align-items: flex-start;
            flex-direction: column;
        }
    }
</style>
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
                            <span class="badge bg-white-1 text-fixed-white mb-2"><?= $selectedExam ? 'Exam Questions' : 'Question Bank'; ?></span>
                            <h4 class="fw-semibold mb-1 text-fixed-white"><?= h((string) $totalQuestions); ?> questions</h4>
                            <p class="mb-0 op-8">
                                <?php if ($selectedExam): ?>
                                    <?= h((string) $selectedExam['title']); ?> - <?= h($examQuestionMode); ?>
                                <?php else: ?>
                                    Course-wise MCQ/TF questions, answer key, marks aur solution manage karein.
                                <?php endif; ?>
                            </p>
                        </div>
                        <div class="btn-list">
                            <a class="btn btn-light btn-wave" href="#add-question">Add Question</a>
                            <a class="btn btn-outline-light btn-wave" href="#import-questions">Import CSV</a>
                            <a class="btn btn-outline-light btn-wave" href="<?= h($questionPageUrl('export_csv')); ?>">Export CSV</a>
                        </div>
                    </div>
                </div>
            </div>

            <?php
            $questionMetrics = [
                ['label' => 'Total', 'value' => $totalQuestions, 'icon' => 'bx bx-question-mark', 'tone' => 'primary'],
                ['label' => 'Active', 'value' => $activeQuestions, 'icon' => 'bx bx-check-circle', 'tone' => 'success'],
                ['label' => 'MCQ', 'value' => $mcqQuestions, 'icon' => 'bx bx-list-check', 'tone' => 'info'],
                ['label' => 'True/False', 'value' => $tfQuestions, 'icon' => 'bx bx-toggle-right', 'tone' => 'warning'],
                ['label' => 'Courses', 'value' => count($courses), 'icon' => 'bx bx-book-open', 'tone' => 'secondary'],
            ];
            ?>
            <?php foreach ($questionMetrics as $metric): ?>
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
                            <div class="card-title mb-0">Question Register</div>
                            <p class="mb-0 text-muted fs-12">Showing <?= h((string) $questionStart); ?>-<?= h((string) $questionEnd); ?> of <?= h((string) $totalQuestions); ?> questions.</p>
                        </div>
                        <div class="d-flex flex-wrap align-items-center gap-2">
                            <form method="get" action="<?= h(app_url('ins/questions')); ?>" class="d-flex align-items-center gap-2 m-0">
                                <?php if ($selectedExamId > 0): ?><input type="hidden" name="exam_id" value="<?= (int) $selectedExamId; ?>"><?php endif; ?>
                                <input type="hidden" name="page" value="1">
                                <select class="form-select form-select-sm" name="lang" onchange="this.form.submit()" aria-label="Language">
                                    <option value="en" <?= $language === 'en' ? 'selected' : ''; ?>>English</option>
                                    <option value="hi" <?= $language === 'hi' ? 'selected' : ''; ?>>Hindi</option>
                                </select>
                                <select class="form-select form-select-sm" name="per_page" onchange="this.form.submit()" aria-label="Rows per page">
                                    <?php foreach ([10, 25, 50] as $size): ?>
                                        <option value="<?= $size; ?>" <?= $perPage === $size ? 'selected' : ''; ?>><?= $size; ?> rows</option>
                                    <?php endforeach; ?>
                                </select>
                            </form>
                            <?php if ($selectedExam): ?>
                                <a class="btn btn-sm btn-light btn-wave" href="<?= h(app_url('ins/exams') . '#edit-exam-' . (int) $selectedExam['id']); ?>">Back Exam</a>
                                <a class="btn btn-sm btn-light btn-wave" href="<?= h(app_url('ins/questions')); ?>">All Questions</a>
                            <?php endif; ?>
                            <a class="btn btn-sm btn-light btn-wave" href="<?= h($questionPageUrl('sample_csv')); ?>">Sample CSV</a>
                            <a class="btn btn-sm btn-primary btn-wave" href="#add-question">Add Question</a>
                        </div>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-sm table-hover align-middle mb-0 gr-register-table">
                                <colgroup><col style="width: 36%;"><col style="width: 20%;"><col style="width: 9%;"><col style="width: 10%;"><col style="width: 8%;"><col style="width: 8%;"><col style="width: 9%;"></colgroup>
                                <thead><tr><th>Question</th><th>Course</th><th>Type</th><th>Answer</th><th>Marks</th><th>Status</th><th class="text-end">Action</th></tr></thead>
                                <tbody>
                                    <?php if (!$questions): ?><tr><td colspan="7" class="text-muted py-4">No question found.</td></tr><?php endif; ?>
                                    <?php foreach ($questions as $question): ?>
                                        <?php
                                        $correctKey = strtoupper((string) $question['correct_key']);
                                        $questionTone = $question['status'] === 'active' ? 'success' : 'warning';
                                        ?>
                                        <tr>
                                            <td><span class="fw-semibold text-truncate gr-cell-title"><?= h($questionText($question, 'question_en', 'question_hi')); ?></span><span class="text-muted fs-12"><?= h($question['solution'] ? 'Solution added' : 'No solution'); ?></span></td>
                                            <td><span class="text-truncate gr-cell-title"><?= h($question['course_title']); ?></span><span class="text-muted fs-12"><?= h((string) ($question['content_title'] ?? 'Course level')); ?></span></td>
                                            <td><span class="badge bg-primary-transparent text-primary"><?= h($question['q_type']); ?></span></td>
                                            <td><span class="badge bg-success-transparent text-success"><?= h($correctKey); ?></span></td>
                                            <td><?= h((string) $question['marks']); ?></td>
                                            <td><span class="badge bg-<?= h($questionTone); ?>-transparent text-<?= h($questionTone); ?>"><?= h(ucfirst($question['status'])); ?></span></td>
                                            <td class="text-end">
                                                <div class="btn-list justify-content-end mb-0">
                                                    <a class="btn btn-sm btn-primary-light btn-wave" href="#view-question-<?= (int) $question['id']; ?>">View</a>
                                                    <a class="btn btn-sm btn-light btn-wave" href="#edit-question-<?= (int) $question['id']; ?>">Edit</a>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 px-3 py-3 border-top">
                            <span class="text-muted fs-12"><?= h((string) $questionStart); ?>-<?= h((string) $questionEnd); ?> of <?= h((string) $totalQuestions); ?> records</span>
                            <div class="btn-list mb-0">
                                <a class="btn btn-sm btn-light btn-wave <?= $page <= 1 ? 'disabled' : ''; ?>" href="<?= h($questionPageUrl('', ['page' => max(1, $page - 1)])); ?>">Prev</a>
                                <span class="badge bg-primary-transparent text-primary align-self-center">Page <?= h((string) $page); ?> / <?= h((string) $totalPages); ?></span>
                                <a class="btn btn-sm btn-light btn-wave <?= $page >= $totalPages ? 'disabled' : ''; ?>" href="<?= h($questionPageUrl('', ['page' => min($totalPages, $page + 1)])); ?>">Next</a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <div id="import-questions" class="modal-overlay">
        <form class="modal-box course-modal ins-modal" method="post" enctype="multipart/form-data">
            <input type="hidden" name="csrf_token" value="<?= h(csrf_token()); ?>">
            <input type="hidden" name="form_action" value="import_csv">
            <div class="modal-head"><h2>Import Questions CSV</h2><a class="modal-close" href="#" aria-label="Close">x</a></div>
            <div class="form-grid one">
                <label>CSV File
                    <input type="file" name="question_csv" accept=".csv,text/csv" required>
                </label>
                <p class="muted-small">Course title must match your course name. Chapter title is optional.</p>
            </div>
            <div class="modal-actions"><button type="submit">Import CSV</button></div>
        </form>
    </div>

    <?php foreach ($questions as $question): ?>
        <div id="view-question-<?= (int) $question['id']; ?>" class="modal-overlay">
            <div class="modal-box wide-modal course-modal ins-modal">
                <div class="modal-head">
                    <div>
                        <h2>Question Details</h2>
                        <p class="mb-0 text-muted fs-12"><?= h($question['course_title']); ?><?= !empty($question['content_title']) ? ' / ' . h($question['content_title']) : ''; ?></p>
                    </div>
                    <a class="modal-close" href="#" aria-label="Close">x</a>
                </div>
                <div class="p-4">
                    <div class="row g-3">
                        <div class="col-md-8">
                            <div class="card custom-card mb-3">
                                <div class="card-body">
                                    <span class="badge bg-primary-transparent text-primary mb-2"><?= h($question['q_type']); ?></span>
                                    <h5 class="fw-semibold mb-2"><?= h($questionText($question, 'question_en', 'question_hi')); ?></h5>
                                    <?php if (!empty($question['question_hi'])): ?><p class="text-muted mb-0"><?= h($question['question_hi']); ?></p><?php endif; ?>
                                </div>
                            </div>
                            <div class="row g-2">
                                <?php foreach (['a' => 'A', 'b' => 'B', 'c' => 'C', 'd' => 'D'] as $key => $label): ?>
                                    <?php
                                    $optionText = trim((string) ($question['option_' . $key . '_en'] ?? ''));
                                    if ($optionText === '') {
                                        continue;
                                    }
                                    $isCorrect = strtoupper((string) $question['correct_key']) === $label;
                                    ?>
                                    <div class="col-md-6">
                                        <div class="p-3 rounded border <?= $isCorrect ? 'border-success bg-success-transparent' : 'bg-light'; ?>">
                                            <span class="badge <?= $isCorrect ? 'bg-success text-white' : 'bg-primary-transparent text-primary'; ?> me-2"><?= $label; ?></span>
                                            <span class="fw-semibold"><?= h($optionText); ?></span>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            <?php if (!empty($question['solution'])): ?>
                                <div class="card custom-card mt-3 mb-0">
                                    <div class="card-body">
                                        <h6 class="fw-semibold mb-2">Solution</h6>
                                        <p class="mb-0 text-muted"><?= nl2br(h($question['solution'])); ?></p>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="col-md-4">
                            <div class="card custom-card mb-0">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between py-2 border-bottom"><span class="text-muted">Correct</span><strong><?= h(strtoupper((string) $question['correct_key'])); ?></strong></div>
                                    <div class="d-flex justify-content-between py-2 border-bottom"><span class="text-muted">Marks</span><strong><?= h((string) $question['marks']); ?></strong></div>
                                    <div class="d-flex justify-content-between py-2 border-bottom"><span class="text-muted">Status</span><strong><?= h(ucfirst((string) $question['status'])); ?></strong></div>
                                    <div class="d-flex justify-content-between py-2"><span class="text-muted">Language</span><strong><?= h(strtoupper($language)); ?></strong></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-actions">
                    <a class="btn btn-light btn-wave" href="#">Close</a>
                    <a class="btn btn-primary btn-wave" href="#edit-question-<?= (int) $question['id']; ?>">Edit Question</a>
                </div>
            </div>
        </div>
    <?php endforeach; ?>

    <div id="add-question" class="modal-overlay">
        <form class="modal-box wide-modal course-modal ins-modal" method="post">
            <input type="hidden" name="csrf_token" value="<?= h(csrf_token()); ?>">
            <div class="modal-head"><h2>Add Question</h2><a class="modal-close" href="#" aria-label="Close">x</a></div>
            <?php $modalQuestion = null; include __DIR__ . '/includes/question_form.php'; ?>
            <div class="modal-actions"><button type="submit">Save Question</button></div>
        </form>
    </div>

    <?php foreach ($questions as $question): ?>
        <div id="edit-question-<?= (int) $question['id']; ?>" class="modal-overlay">
            <form class="modal-box wide-modal course-modal ins-modal" method="post">
                <input type="hidden" name="csrf_token" value="<?= h(csrf_token()); ?>">
                <input type="hidden" name="question_id" value="<?= (int) $question['id']; ?>">
                <div class="modal-head"><h2>Edit Question</h2><a class="modal-close" href="#" aria-label="Close">x</a></div>
                <?php $modalQuestion = $question; include __DIR__ . '/includes/question_form.php'; ?>
                <div class="modal-actions"><button type="submit">Update Question</button></div>
            </form>
        </div>
    <?php endforeach; ?>
    <script>
        const syncQuestionChapters = (form) => {
            const course = form.querySelector('[data-question-course]');
            const chapter = form.querySelector('[data-question-content]');
            if (!course || !chapter) return;
            chapter.querySelectorAll('option[data-course]').forEach((option) => {
                const show = !course.value || option.dataset.course === course.value;
                option.hidden = !show;
                if (!show && option.selected) chapter.value = '';
            });
        };
        document.querySelectorAll('form').forEach((form) => {
            syncQuestionChapters(form);
            form.querySelector('[data-question-course]')?.addEventListener('change', () => syncQuestionChapters(form));
        });
    </script>
<?php include __DIR__ . '/includes/footer.php'; ?>

