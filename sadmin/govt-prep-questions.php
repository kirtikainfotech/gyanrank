<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/govt_exam_prep.php';

$user = require_login('superadmin');
gov_exam_ensure_tables();
$pageTitle = 'Mock Questions';
$pageSubtitle = 'Language-wise question and answer bank.';
$activePage = 'govt-prep-questions';
$mockId = max(0, (int) ($_GET['mock_id'] ?? $_POST['mock_id'] ?? 0));

function gov_question_payload(): array
{
    $answer = strtoupper(trim((string) ($_POST['correct_answer'] ?? 'A')));
    return [
        'id' => (int) ($_POST['id'] ?? 0),
        'mock_test_id' => (int) ($_POST['mock_test_id'] ?? 0),
        'question_en' => trim((string) ($_POST['question_en'] ?? '')),
        'question_hi' => trim((string) ($_POST['question_hi'] ?? '')),
        'option_a_en' => substr(trim((string) ($_POST['option_a_en'] ?? '')), 0, 255),
        'option_b_en' => substr(trim((string) ($_POST['option_b_en'] ?? '')), 0, 255),
        'option_c_en' => substr(trim((string) ($_POST['option_c_en'] ?? '')), 0, 255),
        'option_d_en' => substr(trim((string) ($_POST['option_d_en'] ?? '')), 0, 255),
        'option_e_en' => substr(trim((string) ($_POST['option_e_en'] ?? '')), 0, 255),
        'option_a_hi' => substr(trim((string) ($_POST['option_a_hi'] ?? '')), 0, 255),
        'option_b_hi' => substr(trim((string) ($_POST['option_b_hi'] ?? '')), 0, 255),
        'option_c_hi' => substr(trim((string) ($_POST['option_c_hi'] ?? '')), 0, 255),
        'option_d_hi' => substr(trim((string) ($_POST['option_d_hi'] ?? '')), 0, 255),
        'option_e_hi' => substr(trim((string) ($_POST['option_e_hi'] ?? '')), 0, 255),
        'correct_answer' => in_array($answer, ['A', 'B', 'C', 'D', 'E'], true) ? $answer : 'A',
        'marks' => max(0, (float) ($_POST['marks'] ?? 1)),
        'negative_marks' => max(0, (float) ($_POST['negative_marks'] ?? 0)),
        'explanation_en' => trim((string) ($_POST['explanation_en'] ?? '')),
        'explanation_hi' => trim((string) ($_POST['explanation_hi'] ?? '')),
        'sort_order' => max(1, (int) ($_POST['sort_order'] ?? 1)),
        'status' => in_array($_POST['status'] ?? '', ['active', 'inactive'], true) ? (string) $_POST['status'] : 'active',
    ];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['csrf_token'] ?? null)) {
        $_SESSION['gov_exam_error'] = 'Security token expired.';
        redirect('sadmin/govt-prep-questions' . ($mockId ? '?mock_id=' . $mockId : ''));
    }

    try {
        $data = gov_question_payload();
        if ($data['mock_test_id'] <= 0) {
            throw new RuntimeException('Mock test required.');
        }
        if ($data['question_en'] === '' && $data['question_hi'] === '') {
            throw new RuntimeException('Question English ya Hindi me likhna required hai.');
        }
        if (($data['option_a_en'] === '' && $data['option_a_hi'] === '') || ($data['option_b_en'] === '' && $data['option_b_hi'] === '')) {
            throw new RuntimeException('Option A and B required.');
        }

        if ($data['id'] > 0) {
            $stmt = db()->prepare("UPDATE gov_exam_mock_questions SET mock_test_id=?, question_en=?, question_hi=?, option_a_en=?, option_b_en=?, option_c_en=?, option_d_en=?, option_e_en=?, option_a_hi=?, option_b_hi=?, option_c_hi=?, option_d_hi=?, option_e_hi=?, correct_answer=?, marks=?, negative_marks=?, explanation_en=?, explanation_hi=?, sort_order=?, status=? WHERE id=?");
            $stmt->bind_param('isssssssssssssddssisi', $data['mock_test_id'], $data['question_en'], $data['question_hi'], $data['option_a_en'], $data['option_b_en'], $data['option_c_en'], $data['option_d_en'], $data['option_e_en'], $data['option_a_hi'], $data['option_b_hi'], $data['option_c_hi'], $data['option_d_hi'], $data['option_e_hi'], $data['correct_answer'], $data['marks'], $data['negative_marks'], $data['explanation_en'], $data['explanation_hi'], $data['sort_order'], $data['status'], $data['id']);
            $stmt->execute();
            $_SESSION['gov_exam_message'] = 'Question updated.';
        } else {
            $stmt = db()->prepare("INSERT INTO gov_exam_mock_questions (mock_test_id, question_en, question_hi, option_a_en, option_b_en, option_c_en, option_d_en, option_e_en, option_a_hi, option_b_hi, option_c_hi, option_d_hi, option_e_hi, correct_answer, marks, negative_marks, explanation_en, explanation_hi, sort_order, status) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
            $stmt->bind_param('isssssssssssssddssis', $data['mock_test_id'], $data['question_en'], $data['question_hi'], $data['option_a_en'], $data['option_b_en'], $data['option_c_en'], $data['option_d_en'], $data['option_e_en'], $data['option_a_hi'], $data['option_b_hi'], $data['option_c_hi'], $data['option_d_hi'], $data['option_e_hi'], $data['correct_answer'], $data['marks'], $data['negative_marks'], $data['explanation_en'], $data['explanation_hi'], $data['sort_order'], $data['status']);
            $stmt->execute();
            $_SESSION['gov_exam_message'] = 'Question added.';
        }
        redirect('sadmin/govt-prep-questions?mock_id=' . $data['mock_test_id']);
    } catch (Throwable $e) {
        $_SESSION['gov_exam_error'] = $e->getMessage();
        redirect('sadmin/govt-prep-questions' . ($mockId ? '?mock_id=' . $mockId : ''));
    }
}

$perPageOptions = [10, 25, 50];
$perPage = (int) ($_GET['per_page'] ?? 10);
if (!in_array($perPage, $perPageOptions, true)) {
    $perPage = 10;
}
$page = max(1, (int) ($_GET['page'] ?? 1));
$whereSql = $mockId > 0 ? 'WHERE q.mock_test_id = ?' : '';
$countSql = "SELECT COUNT(*) total FROM gov_exam_mock_questions q {$whereSql}";
$countStmt = db()->prepare($countSql);
if ($mockId > 0) {
    $countStmt->bind_param('i', $mockId);
}
$countStmt->execute();
$filteredQuestions = (int) ($countStmt->get_result()->fetch_assoc()['total'] ?? 0);
$totalPages = max(1, (int) ceil($filteredQuestions / $perPage));
if ($page > $totalPages) {
    $page = $totalPages;
}
$offset = ($page - 1) * $perPage;
$sql = "
    SELECT q.*, m.title AS mock_title
    FROM gov_exam_mock_questions q
    LEFT JOIN gov_exam_mock_tests m ON m.id = q.mock_test_id
    {$whereSql}
    ORDER BY q.mock_test_id DESC, q.sort_order ASC, q.id DESC
    LIMIT ? OFFSET ?
";
$stmt = db()->prepare($sql);
if ($mockId > 0) {
    $stmt->bind_param('iii', $mockId, $perPage, $offset);
} else {
    $stmt->bind_param('ii', $perPage, $offset);
}
$stmt->execute();
$rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$modalSql = "
    SELECT q.*, m.title AS mock_title
    FROM gov_exam_mock_questions q
    LEFT JOIN gov_exam_mock_tests m ON m.id = q.mock_test_id
    {$whereSql}
    ORDER BY q.mock_test_id DESC, q.sort_order ASC, q.id DESC
    LIMIT 500
";
$modalStmt = db()->prepare($modalSql);
if ($mockId > 0) {
    $modalStmt->bind_param('i', $mockId);
}
$modalStmt->execute();
$modalRows = $modalStmt->get_result()->fetch_all(MYSQLI_ASSOC);
$totalMocks = (int) (db()->query('SELECT COUNT(*) total FROM gov_exam_mock_tests')->fetch_assoc()['total'] ?? 0);
$totalQuestions = (int) (db()->query('SELECT COUNT(*) total FROM gov_exam_mock_questions')->fetch_assoc()['total'] ?? 0);
$activeQuestions = (int) (db()->query("SELECT COUNT(*) total FROM gov_exam_mock_questions WHERE status='active'")->fetch_assoc()['total'] ?? 0);
$pageStart = $filteredQuestions > 0 ? $offset + 1 : 0;
$pageEnd = min($offset + $perPage, $filteredQuestions);
$selectedMockTitle = '';
if ($mockId > 0) {
    $stmt = db()->prepare('SELECT title FROM gov_exam_mock_tests WHERE id=? LIMIT 1');
    $stmt->bind_param('i', $mockId);
    $stmt->execute();
    $selectedMockTitle = (string) ($stmt->get_result()->fetch_assoc()['title'] ?? '');
}
[$message, $error] = gov_exam_flash();
?>
<?php include __DIR__ . '/includes/header.php'; ?>
<?php include __DIR__ . '/includes/sidebar.php'; ?>
<main class="main sadmin-govquestions-main">
    <?php include __DIR__ . '/includes/topbar.php'; ?>
    <section class="content govt-question-page">
        <?php if ($message): ?><div class="notice"><?= h($message); ?></div><?php endif; ?>
        <?php if ($error): ?><div class="notice danger"><?= h($error); ?></div><?php endif; ?>
        <section class="question-bank-shell card custom-card">
            <div class="card-header justify-content-between">
                <div>
                    <span class="text-primary fs-11 fw-semibold text-uppercase d-block mb-1">Mock Test Bank</span>
                    <h5 class="mb-1 fw-semibold">Question Bank</h5>
                    <p class="mb-0 text-muted fs-12">Language-wise MCQ, answer key, marking and explanations.</p>
                </div>
                <a class="btn btn-primary btn-wave" href="#add-question">Add Question</a>
            </div>
            <div class="card-body">
                <div class="question-bank-metrics">
                    <div><small>Mock Tests</small><strong><?= h((string) $totalMocks); ?></strong></div>
                    <div><small>Total Questions</small><strong><?= h((string) $totalQuestions); ?></strong></div>
                    <div><small>Active</small><strong><?= h((string) $activeQuestions); ?></strong></div>
                    <div><small>Current View</small><strong><?= h((string) $filteredQuestions); ?></strong></div>
                </div>
            </div>
        </section>

        <section class="question-bank-panel card custom-card">
            <div class="card-header justify-content-between">
                <div>
                    <h6 class="mb-1 fw-semibold">Question Register</h6>
                    <p class="mb-0 text-muted fs-12"><?= h((string) $pageStart); ?>-<?= h((string) $pageEnd); ?> of <?= h((string) $filteredQuestions); ?> records<?= $selectedMockTitle ? ' - ' . h($selectedMockTitle) : ''; ?></p>
                </div>
                <div class="question-toolbar">
                    <form class="question-filter" method="get">
                        <input type="hidden" name="page" value="1">
                        <select class="form-select form-select-sm" name="mock_id" onchange="this.form.submit()"><?= gov_exam_mock_options($mockId ?: null); ?></select>
                        <select class="form-select form-select-sm" name="per_page" onchange="this.form.submit()">
                            <?php foreach ($perPageOptions as $option): ?>
                                <option value="<?= (int) $option; ?>" <?= $perPage === $option ? 'selected' : ''; ?>><?= (int) $option; ?> rows</option>
                            <?php endforeach; ?>
                        </select>
                    </form>
                    <a class="btn btn-sm btn-light btn-wave" href="<?= h(app_url('sadmin/govt-prep-questions')); ?>">All</a>
                    <a class="btn btn-sm btn-light btn-wave" href="<?= h(app_url('sadmin/govt-prep-mocks')); ?>">Mocks</a>
                </div>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive question-table-wrap">
                    <table class="table table-hover align-middle mb-0 gr-register-table gov-question-table">
                        <thead><tr><th>Order</th><th>Question</th><th>Options</th><th>Answer</th><th>Marks</th><th>Status</th><th class="text-end">Action</th></tr></thead>
                        <tbody>
                            <?php foreach ($rows as $row): ?>
                                <tr>
                                    <td><span class="order-chip">#<?= (int) $row['sort_order']; ?></span></td>
                                    <td class="question-copy"><strong><?= h($row['question_en'] ?: $row['question_hi']); ?></strong><small><?= h($row['question_hi'] && $row['question_en'] ? $row['question_hi'] : ($row['mock_title'] ?: '-')); ?></small></td>
                                    <td class="option-stack"><small><b>A</b><?= h($row['option_a_en'] ?: $row['option_a_hi']); ?></small><small><b>B</b><?= h($row['option_b_en'] ?: $row['option_b_hi']); ?></small><small><b>C</b><?= h($row['option_c_en'] ?: $row['option_c_hi'] ?: '-'); ?></small><small><b>D</b><?= h($row['option_d_en'] ?: $row['option_d_hi'] ?: '-'); ?></small><?php if (($row['option_e_en'] ?? '') || ($row['option_e_hi'] ?? '')): ?><small><b>E</b><?= h(($row['option_e_en'] ?? '') ?: ($row['option_e_hi'] ?? '')); ?></small><?php endif; ?></td>
                                    <td><span class="answer-chip"><?= h($row['correct_answer']); ?></span></td>
                                    <td><strong><?= h((string) $row['marks']); ?></strong><small>-<?= h((string) $row['negative_marks']); ?></small></td>
                                    <td><?= gov_exam_status($row['status']); ?></td>
                                    <td class="text-end"><a class="btn btn-sm btn-light btn-wave" href="#edit-question-<?= (int) $row['id']; ?>">Edit</a></td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if (!$rows): ?><tr><td colspan="7" class="empty-state premium-empty"><strong>No questions yet</strong><small>Select a mock test and add your first English/Hindi question.</small></td></tr><?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="card-footer sadmin-pagination">
                <span><?= h((string) $pageStart); ?>-<?= h((string) $pageEnd); ?> of <?= h((string) $filteredQuestions); ?> records</span>
                <div>
                    <?php $filterPart = ($mockId ? '&mock_id=' . $mockId : '') . '&per_page=' . $perPage; ?>
                    <a class="btn btn-sm btn-light <?= $page <= 1 ? 'disabled' : ''; ?>" href="<?= h(app_url('sadmin/govt-prep-questions?page=' . max(1, $page - 1) . $filterPart)); ?>">Prev</a>
                    <span class="page-chip">Page <?= h((string) $page); ?> / <?= h((string) $totalPages); ?></span>
                    <a class="btn btn-sm btn-light <?= $page >= $totalPages ? 'disabled' : ''; ?>" href="<?= h(app_url('sadmin/govt-prep-questions?page=' . min($totalPages, $page + 1) . $filterPart)); ?>">Next</a>
                </div>
            </div>
        </section>

        <div id="add-question" class="modal-overlay">
            <form class="modal-box wide-modal question-modal premium-question-modal" method="post">
                <input type="hidden" name="csrf_token" value="<?= h(csrf_token()); ?>">
                <div class="modal-head question-modal-head"><div><span>Mock Question</span><h2>Add Question</h2></div><a href="#">&times;</a></div>
                <div class="question-form-section">
                    <h3>Mock & Status</h3>
                    <div class="form-grid">
                    <label class="span-2">Mock Test<select name="mock_test_id" required><?= gov_exam_mock_options($mockId ?: null); ?></select></label>
                    <label>Sort Order<input type="number" name="sort_order" value="<?= $filteredQuestions + 1; ?>" min="1"></label>
                    <label>Status<select name="status"><option value="active">Active</option><option value="inactive">Inactive</option></select></label>
                    </div>
                </div>
                <div class="question-form-section">
                    <h3>Question</h3>
                    <div class="form-grid">
                    <label class="span-2">Question English<textarea name="question_en" rows="3"></textarea></label>
                    <label class="span-2">Question Hindi<textarea name="question_hi" rows="3"></textarea></label>
                    </div>
                </div>
                <div class="question-form-section">
                    <h3>Options</h3>
                    <div class="option-form-grid">
                    <label><span>A English</span><input name="option_a_en"></label><label><span>A Hindi</span><input name="option_a_hi"></label>
                    <label><span>B English</span><input name="option_b_en"></label><label><span>B Hindi</span><input name="option_b_hi"></label>
                    <label><span>C English</span><input name="option_c_en"></label><label><span>C Hindi</span><input name="option_c_hi"></label>
                    <label><span>D English</span><input name="option_d_en"></label><label><span>D Hindi</span><input name="option_d_hi"></label>
                    <label><span>E English</span><input name="option_e_en"></label><label><span>E Hindi</span><input name="option_e_hi"></label>
                    </div>
                </div>
                <div class="question-form-section">
                    <h3>Scoring & Explanation</h3>
                    <div class="form-grid">
                    <label>Correct Answer<select name="correct_answer"><option>A</option><option>B</option><option>C</option><option>D</option><option>E</option></select></label>
                    <label>Marks<input type="number" step="0.01" name="marks" value="1"></label>
                    <label>Negative Marks<input type="number" step="0.01" name="negative_marks" value="0"></label>
                    <label class="span-2">Explanation English<textarea name="explanation_en" rows="2"></textarea></label>
                    <label class="span-2">Explanation Hindi<textarea name="explanation_hi" rows="2"></textarea></label>
                    </div>
                </div>
                <div class="modal-actions"><button type="submit">Save Question</button></div>
            </form>
        </div>

        <?php foreach ($modalRows as $row): ?>
            <div id="edit-question-<?= (int) $row['id']; ?>" class="modal-overlay">
                <form class="modal-box wide-modal question-modal premium-question-modal" method="post">
                    <input type="hidden" name="csrf_token" value="<?= h(csrf_token()); ?>">
                    <input type="hidden" name="id" value="<?= (int) $row['id']; ?>">
                    <div class="modal-head question-modal-head"><div><span>Mock Question</span><h2>Edit Question</h2></div><a href="#">&times;</a></div>
                    <div class="question-form-section">
                        <h3>Mock & Status</h3>
                        <div class="form-grid">
                        <label class="span-2">Mock Test<select name="mock_test_id" required><?= gov_exam_mock_options((int) $row['mock_test_id']); ?></select></label>
                        <label>Sort Order<input type="number" name="sort_order" value="<?= (int) $row['sort_order']; ?>" min="1"></label>
                        <label>Status<select name="status"><?php foreach (['active', 'inactive'] as $s): ?><option value="<?= $s; ?>" <?= $row['status'] === $s ? 'selected' : ''; ?>><?= ucfirst($s); ?></option><?php endforeach; ?></select></label>
                        </div>
                    </div>
                    <div class="question-form-section">
                        <h3>Question</h3>
                        <div class="form-grid">
                        <label class="span-2">Question English<textarea name="question_en" rows="3"><?= h($row['question_en']); ?></textarea></label>
                        <label class="span-2">Question Hindi<textarea name="question_hi" rows="3"><?= h($row['question_hi']); ?></textarea></label>
                        </div>
                    </div>
                    <div class="question-form-section">
                        <h3>Options</h3>
                        <div class="option-form-grid">
                        <label><span>A English</span><input name="option_a_en" value="<?= h($row['option_a_en']); ?>"></label><label><span>A Hindi</span><input name="option_a_hi" value="<?= h($row['option_a_hi']); ?>"></label>
                        <label><span>B English</span><input name="option_b_en" value="<?= h($row['option_b_en']); ?>"></label><label><span>B Hindi</span><input name="option_b_hi" value="<?= h($row['option_b_hi']); ?>"></label>
                        <label><span>C English</span><input name="option_c_en" value="<?= h($row['option_c_en']); ?>"></label><label><span>C Hindi</span><input name="option_c_hi" value="<?= h($row['option_c_hi']); ?>"></label>
                        <label><span>D English</span><input name="option_d_en" value="<?= h($row['option_d_en']); ?>"></label><label><span>D Hindi</span><input name="option_d_hi" value="<?= h($row['option_d_hi']); ?>"></label>
                        <label><span>E English</span><input name="option_e_en" value="<?= h($row['option_e_en'] ?? ''); ?>"></label><label><span>E Hindi</span><input name="option_e_hi" value="<?= h($row['option_e_hi'] ?? ''); ?>"></label>
                        </div>
                    </div>
                    <div class="question-form-section">
                        <h3>Scoring & Explanation</h3>
                        <div class="form-grid">
                        <label>Correct Answer<select name="correct_answer"><?php foreach (['A', 'B', 'C', 'D', 'E'] as $a): ?><option value="<?= $a; ?>" <?= $row['correct_answer'] === $a ? 'selected' : ''; ?>><?= $a; ?></option><?php endforeach; ?></select></label>
                        <label>Marks<input type="number" step="0.01" name="marks" value="<?= h((string) $row['marks']); ?>"></label>
                        <label>Negative Marks<input type="number" step="0.01" name="negative_marks" value="<?= h((string) $row['negative_marks']); ?>"></label>
                        <label class="span-2">Explanation English<textarea name="explanation_en" rows="2"><?= h($row['explanation_en']); ?></textarea></label>
                        <label class="span-2">Explanation Hindi<textarea name="explanation_hi" rows="2"><?= h($row['explanation_hi']); ?></textarea></label>
                        </div>
                    </div>
                    <div class="modal-actions"><button type="submit">Update Question</button></div>
                </form>
            </div>
        <?php endforeach; ?>
    </section>
    <style>
        .sadmin-govquestions-main .govt-question-page { padding-top: 1.25rem; }
        .sadmin-govquestions-main .question-bank-shell,
        .sadmin-govquestions-main .question-bank-panel {
            border: 0;
            border-radius: .65rem;
            box-shadow: 0 .85rem 1.85rem rgba(15, 23, 42, .045);
            overflow: hidden;
        }
        .sadmin-govquestions-main .question-bank-shell .card-header,
        .sadmin-govquestions-main .question-bank-panel .card-header {
            padding: .75rem 1rem;
            border-bottom: 1px solid var(--default-border);
            background: var(--custom-white);
        }
        .sadmin-govquestions-main .question-bank-metrics {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: .65rem;
        }
        .sadmin-govquestions-main .question-bank-metrics div {
            display: grid;
            gap: .25rem;
            padding: .65rem .85rem;
            border: 1px solid var(--default-border);
            border-radius: .5rem;
            background: var(--default-background);
        }
        .sadmin-govquestions-main .question-bank-metrics small {
            color: var(--text-muted);
            font-size: .68rem;
            font-weight: 700;
            text-transform: uppercase;
        }
        .sadmin-govquestions-main .question-bank-metrics strong {
            min-width: 0;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            font-size: 1.1rem;
            line-height: 1;
        }
        .sadmin-govquestions-main .question-toolbar,
        .sadmin-govquestions-main .question-filter {
            display: flex;
            align-items: center;
            gap: .45rem;
            flex-wrap: wrap;
        }
        .sadmin-govquestions-main .question-filter { margin: 0; }
        .sadmin-govquestions-main .question-filter .form-select {
            min-width: 7.25rem;
            min-height: 2rem;
            font-size: .74rem;
        }
        .sadmin-govquestions-main .question-filter .form-select:first-of-type { min-width: 15rem; }
        .sadmin-govquestions-main .gov-question-table { min-width: 74rem; }
        .sadmin-govquestions-main .gov-question-table th,
        .sadmin-govquestions-main .gov-question-table td {
            padding: .42rem .65rem !important;
            font-size: .72rem;
            line-height: 1.2;
            vertical-align: middle;
        }
        .sadmin-govquestions-main .gov-question-table th {
            background: var(--default-background);
            font-size: .65rem;
            letter-spacing: .025em;
            text-transform: uppercase;
        }
        .sadmin-govquestions-main .gov-question-table th:nth-child(1) { width: 4.5rem; }
        .sadmin-govquestions-main .gov-question-table th:nth-child(2) { width: 27%; }
        .sadmin-govquestions-main .gov-question-table th:nth-child(3) { width: 31%; }
        .sadmin-govquestions-main .gov-question-table th:nth-child(4) { width: 5.25rem; }
        .sadmin-govquestions-main .gov-question-table th:nth-child(5) { width: 6rem; }
        .sadmin-govquestions-main .gov-question-table th:nth-child(6) { width: 7rem; }
        .sadmin-govquestions-main .question-copy,
        .sadmin-govquestions-main .option-stack {
            min-width: 0;
            max-width: 1px;
        }
        .sadmin-govquestions-main .question-copy strong,
        .sadmin-govquestions-main .question-copy small,
        .sadmin-govquestions-main .option-stack small {
            display: block;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        .sadmin-govquestions-main .question-copy strong {
            color: var(--default-text-color);
            font-size: .75rem;
            font-weight: 700;
        }
        .sadmin-govquestions-main .question-copy small,
        .sadmin-govquestions-main .option-stack small {
            color: var(--text-muted);
            font-size: .66rem;
        }
        .sadmin-govquestions-main .option-stack {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: .1rem .55rem;
        }
        .sadmin-govquestions-main .option-stack b {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 1rem;
            height: 1rem;
            margin-right: .25rem;
            border-radius: 999px;
            background: rgba(var(--primary-rgb), .1);
            color: rgb(var(--primary-rgb));
            font-size: .58rem;
        }
        .sadmin-govquestions-main .order-chip,
        .sadmin-govquestions-main .answer-chip {
            display: inline-flex;
            align-items: center;
            min-height: 1.3rem;
            padding: .12rem .45rem;
            border-radius: 999px;
            background: rgba(var(--primary-rgb), .1);
            color: rgb(var(--primary-rgb));
            font-size: .64rem;
            font-weight: 700;
        }
        .sadmin-govquestions-main .gov-question-table .status-pill,
        .sadmin-govquestions-main .gov-question-table .badge {
            min-height: 1.2rem;
            padding: .15rem .45rem;
            border-radius: 999px;
            font-size: .62rem;
            font-weight: 700;
        }
        .sadmin-govquestions-main .gov-question-table .btn-sm,
        .sadmin-govquestions-main .sadmin-pagination .btn {
            min-height: 1.55rem;
            padding: .18rem .55rem;
            font-size: .67rem;
        }
        .sadmin-govquestions-main .sadmin-pagination {
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
        .sadmin-govquestions-main .sadmin-pagination > div {
            display: flex;
            align-items: center;
            gap: .35rem;
        }
        .sadmin-govquestions-main .page-chip {
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
        .sadmin-govquestions-main .footer { margin-inline: 0 !important; width: 100%; }
        @media (max-width: 991.98px) {
            .sadmin-govquestions-main .question-bank-metrics { grid-template-columns: repeat(2, minmax(0, 1fr)); }
            .sadmin-govquestions-main .question-filter .form-select:first-of-type { min-width: 12rem; }
        }
    </style>
</main>
<?php include __DIR__ . '/includes/footer.php'; ?>
