<?php
require_once __DIR__ . '/includes/functions.php';
$user = instructor_user();
ensure_instructor_erp_tables();
$instructorId = (int) $user['id'];
$examId = max(0, (int) ($_GET['exam_id'] ?? ($_POST['exam_id'] ?? 0)));

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

function exam_question_bind(mysqli_stmt $stmt, string $types, array $params): void
{
    $refs = [$types];
    foreach ($params as $key => $value) {
        $params[$key] = $value;
        $refs[] = &$params[$key];
    }
    $stmt->bind_param(...$refs);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['csrf_token'] ?? null)) {
        $_SESSION['ins_error'] = 'Security token expired.';
        redirect('ins/exam-questions?exam_id=' . $examId);
    }

    try {
        $rawIds = $_POST['question_ids'] ?? [];
        $selectedIds = [];
        if (is_array($rawIds)) {
            foreach ($rawIds as $value) {
                $id = (int) $value;
                if ($id > 0) {
                    $selectedIds[$id] = $id;
                }
            }
        }

        $validRows = [];
        if ($selectedIds) {
            $idList = implode(',', array_map('intval', array_values($selectedIds)));
            $stmt = db()->prepare("
                SELECT id, marks
                FROM instructor_questions
                WHERE instructor_id = ? AND id IN ($idList)
                ORDER BY FIELD(id, $idList)
            ");
            $stmt->bind_param('i', $instructorId);
            $stmt->execute();
            $validRows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        }

        db()->begin_transaction();
        $stmt = db()->prepare('DELETE FROM instructor_exam_questions WHERE exam_id = ? AND instructor_id = ?');
        $stmt->bind_param('ii', $examId, $instructorId);
        $stmt->execute();
        $stmt = db()->prepare('DELETE FROM instructor_exam_random_rules WHERE exam_id = ? AND instructor_id = ?');
        $stmt->bind_param('ii', $examId, $instructorId);
        $stmt->execute();

        $totalQuestions = 0;
        $totalMarks = 0.0;
        if ($validRows) {
            $insert = db()->prepare('INSERT INTO instructor_exam_questions (instructor_id, exam_id, question_id, marks, sort_order) VALUES (?, ?, ?, ?, ?)');
            $sort = 1;
            foreach ($validRows as $row) {
                $questionId = (int) $row['id'];
                $marks = (float) $row['marks'];
                $insert->bind_param('iiidi', $instructorId, $examId, $questionId, $marks, $sort);
                $insert->execute();
                $totalQuestions++;
                $totalMarks += $marks;
                $sort++;
            }
        }

        $stmt = db()->prepare('UPDATE instructor_exams SET exam_type = "manual", total_questions = ?, total_marks = ?, updated_at = NOW() WHERE id = ? AND instructor_id = ?');
        $stmt->bind_param('idii', $totalQuestions, $totalMarks, $examId, $instructorId);
        $stmt->execute();
        db()->commit();

        $_SESSION['ins_success'] = "Questions saved. {$totalQuestions} assigned, {$totalMarks} marks.";
        redirect('ins/exam-questions?exam_id=' . $examId);
    } catch (Throwable $e) {
        db()->rollback();
        $_SESSION['ins_error'] = $e->getMessage();
        redirect('ins/exam-questions?exam_id=' . $examId);
    }
}

$assignedIds = instructor_exam_question_ids($instructorId, $examId);
$assignedMap = array_fill_keys($assignedIds, true);
$assignedRows = [];
if ($assignedIds) {
    $idList = implode(',', array_map('intval', $assignedIds));
    $stmt = db()->prepare("SELECT id, marks FROM instructor_questions WHERE instructor_id = ? AND id IN ($idList)");
    $stmt->bind_param('i', $instructorId);
    $stmt->execute();
    foreach ($stmt->get_result()->fetch_all(MYSQLI_ASSOC) as $row) {
        $assignedRows[(int) $row['id']] = $row;
    }
}
if (!$assignedIds && $exam['exam_type'] === 'random') {
    $stmt = db()->prepare('SELECT content_id, question_limit, only_active FROM instructor_exam_random_rules WHERE instructor_id = ? AND exam_id = ? ORDER BY id ASC');
    $stmt->bind_param('ii', $instructorId, $examId);
    $stmt->execute();
    $rules = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $seen = [];
    foreach ($rules as $rule) {
        $limit = max(1, (int) ($rule['question_limit'] ?? 0));
        $whereRule = ['q.instructor_id = ?'];
        $typesRule = 'i';
        $paramsRule = [$instructorId];
        if ((int) ($exam['course_id'] ?? 0) > 0) {
            $whereRule[] = 'q.course_id = ?';
            $typesRule .= 'i';
            $paramsRule[] = (int) $exam['course_id'];
        }
        if ((int) ($rule['content_id'] ?? 0) > 0) {
            $whereRule[] = 'q.content_id = ?';
            $typesRule .= 'i';
            $paramsRule[] = (int) $rule['content_id'];
        }
        if ($seen) {
            $whereRule[] = 'q.id NOT IN (' . implode(',', array_map('intval', array_keys($seen))) . ')';
        }
        if ((int) ($rule['only_active'] ?? 1) === 1) {
            $whereRule[] = "q.status = 'active'";
        }
        $sqlRule = 'SELECT q.id, q.marks FROM instructor_questions q WHERE ' . implode(' AND ', $whereRule) . ' ORDER BY q.id DESC LIMIT ' . $limit;
        $stmtRule = db()->prepare($sqlRule);
        exam_question_bind($stmtRule, $typesRule, $paramsRule);
        $stmtRule->execute();
        foreach ($stmtRule->get_result()->fetch_all(MYSQLI_ASSOC) as $row) {
            $qid = (int) $row['id'];
            $seen[$qid] = true;
            $assignedIds[] = $qid;
            $assignedRows[$qid] = $row;
        }
    }
    $assignedMap = array_fill_keys($assignedIds, true);
}
$contents = instructor_course_contents($instructorId);

$filterContent = max(0, (int) ($_GET['content_id'] ?? 0));

$where = ['q.instructor_id = ?'];
$types = 'i';
$params = [$instructorId];
if ($filterContent > 0) {
    $where[] = 'q.content_id = ?';
    $types .= 'i';
    $params[] = $filterContent;
}
$perPage = 120;
$page = max(1, (int) ($_GET['page'] ?? 1));
$offset = ($page - 1) * $perPage;

$countSql = '
    SELECT COUNT(*) AS total
    FROM instructor_questions q
    WHERE ' . implode(' AND ', $where);
$countStmt = db()->prepare($countSql);
exam_question_bind($countStmt, $types, $params);
$countStmt->execute();
$totalRows = (int) ($countStmt->get_result()->fetch_assoc()['total'] ?? 0);
$totalPages = max(1, (int) ceil($totalRows / $perPage));
if ($page > $totalPages) {
    $page = $totalPages;
    $offset = ($page - 1) * $perPage;
}

$sql = '
    SELECT q.*, c.title AS course_title, cc.content_title
    FROM instructor_questions q
    INNER JOIN instructor_courses c ON c.id = q.course_id AND c.instructor_id = q.instructor_id
    LEFT JOIN instructor_course_contents cc ON cc.id = q.content_id AND cc.instructor_id = q.instructor_id
    WHERE ' . implode(' AND ', $where) . '
    ORDER BY CASE WHEN q.id IN (' . ($assignedIds ? implode(',', array_map('intval', $assignedIds)) : '0') . ') THEN 0 ELSE 1 END ASC,
             FIELD(q.id, ' . ($assignedIds ? implode(',', array_map('intval', $assignedIds)) : '0') . ') ASC,
             q.id DESC
    LIMIT ' . $perPage . ' OFFSET ' . $offset . '
';
$stmt = db()->prepare($sql);
exam_question_bind($stmt, $types, $params);
$stmt->execute();
$questions = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$visibleIds = [];
foreach ($questions as $question) {
    $visibleIds[(int) $question['id']] = true;
}
$hiddenAssignedRows = [];
foreach ($assignedRows as $qid => $row) {
    if (!isset($visibleIds[$qid])) {
        $hiddenAssignedRows[$qid] = $row;
    }
}
$assignedMarks = 0.0;
$assignedCount = 0;
foreach ($assignedRows as $row) {
    $assignedCount++;
    $assignedMarks += (float) $row['marks'];
}
$pageUrl = static function (int $targetPage) use ($examId, $filterContent): string {
    $query = ['exam_id' => $examId, 'page' => $targetPage];
    if ($filterContent > 0) {
        $query['content_id'] = $filterContent;
    }
    return app_url('ins/exam-questions') . '?' . http_build_query($query);
};

$message = $_SESSION['ins_success'] ?? '';
$error = $_SESSION['ins_error'] ?? '';
unset($_SESSION['ins_success'], $_SESSION['ins_error']);
$pageTitle = 'Assign Exam Questions';
$pageSubtitle = 'Assign, remove and calculate real exam question count.';
$activePage = 'exams';
?>
<?php include __DIR__ . '/includes/header.php'; ?>
<?php include __DIR__ . '/includes/sidebar.php'; ?>
<style>
    .assign-toolbar {display:flex;gap:8px;align-items:end;flex-wrap:wrap;margin-top:10px}
    .assign-toolbar label {display:grid;gap:4px;font-size:11px;font-weight:800;color:#64748b}
    .assign-toolbar select,.assign-toolbar input {height:34px;border:1px solid #cbd5e1;border-radius:6px;padding:5px 8px;font-size:12px;color:#0f172a;background:#fff}
    .assign-toolbar button,.assign-action {height:34px;border:1px solid #0f172a;border-radius:6px;padding:0 11px;background:#0f172a;color:#fff;font-size:12px;font-weight:900;text-decoration:none;display:inline-flex;align-items:center}
    .assign-action.ghost {background:#fff;color:#0f172a;border-color:#cbd5e1}
    .assign-stats {display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:8px;margin:10px 0}
    .assign-stats article {border:1px solid #dbe5f4;border-radius:8px;background:#fff;padding:9px}
    .assign-stats span {display:block;font-size:10px;color:#64748b;font-weight:800;text-transform:uppercase}
    .assign-stats strong {display:block;font-size:20px;color:#0f172a;line-height:1.1}
    .assign-table input[type=checkbox] {width:16px;height:16px}
    .assign-question {min-width:260px;max-width:480px}
    .assign-question strong {display:block;font-size:12px;line-height:1.3}
    .assign-question small {display:block;color:#64748b;font-size:10px;margin-top:3px}
    @media(max-width:760px){.assign-stats{grid-template-columns:repeat(2,minmax(0,1fr))}}
</style>
<main class="main">
    <?php include __DIR__ . '/includes/topbar.php'; ?>
    <section class="content instructor-content">
        <?php if ($message): ?><div class="notice"><?= h($message); ?></div><?php endif; ?>
        <?php if ($error): ?><div class="notice danger"><?= h($error); ?></div><?php endif; ?>
        <section class="settings-detail-card ins-card">
            <div class="detail-head compact-head">
                <div>
                    <span>Assign Questions</span>
                    <h2><?= h((string) $exam['title']); ?></h2>
                    <p><?= h($exam['course_title'] ?: 'All courses'); ?> | Current saved: <?= h((string) $exam['total_questions']); ?> questions, <?= h((string) $exam['total_marks']); ?> marks</p>
                </div>
                <div class="question-actions">
                    <a class="assign-action ghost" href="<?= h(app_url('ins/exams') . '#edit-exam-' . (int) $examId); ?>">Back Exam</a>
                    <a class="assign-action ghost" href="<?= h(app_url('ins/questions')); ?>">Question Bank</a>
                </div>
            </div>
            <?php if ($exam['exam_type'] === 'random'): ?>
                <div class="notice danger">Saving selected questions here will convert this random exam into a manual assigned exam.</div>
            <?php endif; ?>
            <form method="get" action="<?= h(app_url('ins/exam-questions')); ?>" class="assign-toolbar">
                <input type="hidden" name="exam_id" value="<?= (int) $examId; ?>">
                <label>Chapter
                    <select name="content_id">
                        <option value="0">All chapters</option>
                        <?php foreach ($contents as $content): ?>
                            <option value="<?= (int) $content['id']; ?>" <?= $filterContent === (int) $content['id'] ? 'selected' : ''; ?>><?= h($content['course_title']); ?> - <?= h($content['content_title']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <button type="submit">Filter</button>
            </form>
            <form method="post" id="assign-question-form">
                <input type="hidden" name="csrf_token" value="<?= h(csrf_token()); ?>">
                <input type="hidden" name="exam_id" value="<?= (int) $examId; ?>">
                <?php foreach ($hiddenAssignedRows as $qid => $row): ?>
                    <input type="checkbox" name="question_ids[]" value="<?= (int) $qid; ?>" data-question-check data-marks="<?= h((string) $row['marks']); ?>" checked hidden>
                <?php endforeach; ?>
                <div class="assign-stats">
                    <article><span>Showing</span><strong><?= h((string) count($questions)); ?></strong></article>
                    <article><span>Total</span><strong><?= h((string) $totalRows); ?></strong></article>
                    <article><span>Selected</span><strong data-selected-count><?= h((string) $assignedCount); ?></strong></article>
                    <article><span>Marks</span><strong data-selected-marks><?= h(number_format($assignedMarks, 2)); ?></strong></article>
                </div>
                <div class="assign-toolbar">
                    <label style="display:flex;align-items:center;gap:6px;color:#0f172a"><input type="checkbox" data-select-visible> Select visible</label>
                    <button type="submit">Save Assigned Questions</button>
                    <span style="font-size:12px;color:#64748b;font-weight:800">Page <?= h((string) $page); ?> / <?= h((string) $totalPages); ?></span>
                    <?php if ($page > 1): ?><a class="assign-action ghost" href="<?= h($pageUrl($page - 1)); ?>">Prev</a><?php endif; ?>
                    <?php if ($page < $totalPages): ?><a class="assign-action ghost" href="<?= h($pageUrl($page + 1)); ?>">Next</a><?php endif; ?>
                </div>
                <table class="role-access-table assign-table">
                    <thead><tr><th>Assign</th><th>Question</th><th>Course</th><th>Chapter</th><th>Answer</th><th>Marks</th><th>Status</th><th>Edit</th></tr></thead>
                    <tbody>
                        <?php if (!$questions): ?><tr><td colspan="8">No questions found for selected filter.</td></tr><?php endif; ?>
                        <?php foreach ($questions as $question): ?>
                            <?php $qid = (int) $question['id']; ?>
                            <tr>
                                <td><input type="checkbox" name="question_ids[]" value="<?= $qid; ?>" data-question-check data-visible-check data-marks="<?= h((string) $question['marks']); ?>" <?= isset($assignedMap[$qid]) ? 'checked' : ''; ?>></td>
                                <td class="assign-question"><strong><?= h(mb_substr((string) ($question['question_en'] ?: $question['question_hi']), 0, 190)); ?></strong><small>ID #<?= $qid; ?> | <?= h((string) $question['q_type']); ?></small></td>
                                <td><?= h((string) $question['course_title']); ?></td>
                                <td><?= h((string) ($question['content_title'] ?: 'No chapter')); ?></td>
                                <td><span class="question-answer-pill"><?= h((string) $question['correct_key']); ?></span></td>
                                <td><?= h((string) $question['marks']); ?></td>
                                <td><span class="status-pill <?= $question['status'] === 'active' ? 'ready' : 'empty'; ?>"><?= h(ucfirst((string) $question['status'])); ?></span></td>
                                <td><a class="table-edit-icon" href="<?= h(app_url('ins/questions') . '#edit-question-' . $qid); ?>">Edit</a></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </form>
        </section>
    </section>
</main>
<script>
    const form = document.getElementById('assign-question-form');
    const countBox = document.querySelector('[data-selected-count]');
    const marksBox = document.querySelector('[data-selected-marks]');
    const updateAssignStats = () => {
        let count = 0;
        let marks = 0;
        document.querySelectorAll('[data-question-check]').forEach((box) => {
            if (box.checked) {
                count++;
                marks += Number(box.dataset.marks || 0);
            }
        });
        countBox.textContent = String(count);
        marksBox.textContent = marks.toFixed(2);
    };
    document.querySelectorAll('[data-question-check]').forEach((box) => box.addEventListener('change', updateAssignStats));
    document.querySelector('[data-select-visible]')?.addEventListener('change', (event) => {
        document.querySelectorAll('[data-visible-check]').forEach((box) => box.checked = event.target.checked);
        updateAssignStats();
    });
    updateAssignStats();
</script>
<?php include __DIR__ . '/includes/footer.php'; ?>
