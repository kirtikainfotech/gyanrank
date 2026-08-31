<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/govt_exam_prep.php';

$user = require_login('superadmin');
gov_exam_ensure_tables();
$pageTitle = 'Mock Questions';
$pageSubtitle = 'Language-wise question and answer bank.';
$activePage = 'govt-prep-questions';
$mockId = max(0, (int) ($_GET['mock_id'] ?? $_POST['mock_id'] ?? 0));

function gov_question_bind(mysqli_stmt $stmt, string $types, array &$params): void
{
    if ($types === '') {
        return;
    }
    $refs = [];
    foreach ($params as $key => $value) {
        $refs[$key] = &$params[$key];
    }
    $stmt->bind_param($types, ...$refs);
}
function gov_question_ensure_index(string $name, string $sql): void
{
    $safeName = db()->real_escape_string($name);
    $result = db()->query("SHOW INDEX FROM gov_exam_mock_questions WHERE Key_name = '{$safeName}'");
    if (!$result || $result->num_rows === 0) {
        db()->query($sql);
    }
}
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

gov_question_ensure_index('gov_exam_q_list_idx', 'ALTER TABLE gov_exam_mock_questions ADD INDEX gov_exam_q_list_idx (mock_test_id, sort_order, id)');
gov_question_ensure_index('gov_exam_q_status_idx', 'ALTER TABLE gov_exam_mock_questions ADD INDEX gov_exam_q_status_idx (status, correct_answer, mock_test_id)');

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

$perPageOptions = [10, 25, 50, 100];
$perPage = (int) ($_GET['per_page'] ?? 10);
if (!in_array($perPage, $perPageOptions, true)) {
    $perPage = 10;
}
$page = max(1, (int) ($_GET['page'] ?? 1));
$questionStatus = in_array($_GET['status'] ?? '', ['active', 'inactive'], true) ? (string) $_GET['status'] : '';
$answerFilter = in_array($_GET['answer'] ?? '', ['A', 'B', 'C', 'D', 'E'], true) ? (string) $_GET['answer'] : '';
$search = substr(trim((string) ($_GET['q'] ?? '')), 0, 120);

$whereParts = [];
$whereTypes = '';
$whereParams = [];
if ($mockId > 0) {
    $whereParts[] = 'q.mock_test_id = ?';
    $whereTypes .= 'i';
    $whereParams[] = $mockId;
}
if ($questionStatus !== '') {
    $whereParts[] = 'q.status = ?';
    $whereTypes .= 's';
    $whereParams[] = $questionStatus;
}
if ($answerFilter !== '') {
    $whereParts[] = 'q.correct_answer = ?';
    $whereTypes .= 's';
    $whereParams[] = $answerFilter;
}
if ($search !== '') {
    $like = '%' . $search . '%';
    $whereParts[] = '(q.question_en LIKE ? OR q.question_hi LIKE ? OR q.option_a_en LIKE ? OR q.option_a_hi LIKE ? OR q.option_b_en LIKE ? OR q.option_b_hi LIKE ? OR q.option_c_en LIKE ? OR q.option_c_hi LIKE ? OR q.option_d_en LIKE ? OR q.option_d_hi LIKE ? OR q.option_e_en LIKE ? OR q.option_e_hi LIKE ? OR m.title LIKE ?)';
    $whereTypes .= 'sssssssssssss';
    for ($i = 0; $i < 13; $i++) {
        $whereParams[] = $like;
    }
}
$whereSql = $whereParts ? 'WHERE ' . implode(' AND ', $whereParts) : '';

$totalMocks = (int) (db()->query('SELECT COUNT(*) total FROM gov_exam_mock_tests')->fetch_assoc()['total'] ?? 0);
$summaryRow = db()->query("SELECT COUNT(*) total, SUM(status='active') active_total, SUM(COALESCE(explanation_en, '') = '' AND COALESCE(explanation_hi, '') = '') missing_explanations FROM gov_exam_mock_questions")->fetch_assoc() ?: [];
$totalQuestions = (int) ($summaryRow['total'] ?? 0);
$activeQuestions = (int) ($summaryRow['active_total'] ?? 0);
$inactiveQuestions = max(0, $totalQuestions - $activeQuestions);
$missingExplanations = (int) ($summaryRow['missing_explanations'] ?? 0);
if ($whereParts === []) {
    $filteredQuestions = $totalQuestions;
} else {
    $countSql = "SELECT COUNT(*) total FROM gov_exam_mock_questions q LEFT JOIN gov_exam_mock_tests m ON m.id = q.mock_test_id {$whereSql}";
    $countStmt = db()->prepare($countSql);
    gov_question_bind($countStmt, $whereTypes, $whereParams);
    $countStmt->execute();
    $filteredQuestions = (int) ($countStmt->get_result()->fetch_assoc()['total'] ?? 0);
}
$totalPages = max(1, (int) ceil($filteredQuestions / $perPage));
if ($page > $totalPages) {
    $page = $totalPages;
}
$offset = ($page - 1) * $perPage;
$sql = "
    SELECT q.*, m.title AS mock_title, m.total_questions AS mock_target_questions, c.name AS category_name, s.name AS subcategory_name
    FROM gov_exam_mock_questions q
    LEFT JOIN gov_exam_mock_tests m ON m.id = q.mock_test_id
    LEFT JOIN gov_exam_categories c ON c.id = m.category_id
    LEFT JOIN gov_exam_categories s ON s.id = m.subcategory_id
    {$whereSql}
    ORDER BY q.mock_test_id DESC, q.sort_order ASC, q.id DESC
    LIMIT ? OFFSET ?
";
$stmt = db()->prepare($sql);
$listTypes = $whereTypes . 'ii';
$listParams = array_merge($whereParams, [$perPage, $offset]);
gov_question_bind($stmt, $listTypes, $listParams);
$stmt->execute();
$rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$pageStart = $filteredQuestions > 0 ? $offset + 1 : 0;
$pageEnd = min($offset + $perPage, $filteredQuestions);
$selectedMockTitle = '';
if ($mockId > 0) {
    $stmt = db()->prepare('SELECT title FROM gov_exam_mock_tests WHERE id=? LIMIT 1');
    $stmt->bind_param('i', $mockId);
    $stmt->execute();
    $selectedMockTitle = (string) ($stmt->get_result()->fetch_assoc()['title'] ?? '');
}
$filterQuery = [
    'mock_id' => $mockId ?: null,
    'status' => $questionStatus ?: null,
    'answer' => $answerFilter ?: null,
    'q' => $search !== '' ? $search : null,
    'per_page' => $perPage,
];
$filterQuery = array_filter($filterQuery, static fn($value) => $value !== null && $value !== '');
$filterPart = http_build_query($filterQuery);
$filterSuffix = $filterPart !== '' ? '&' . $filterPart : '';
$filterLabelParts = [];
if ($selectedMockTitle !== '') $filterLabelParts[] = $selectedMockTitle;
if ($questionStatus !== '') $filterLabelParts[] = ucfirst($questionStatus);
if ($answerFilter !== '') $filterLabelParts[] = 'Answer ' . $answerFilter;
if ($search !== '') $filterLabelParts[] = 'Search: ' . $search;
$filterLabel = $filterLabelParts ? implode(' / ', $filterLabelParts) : 'All questions';
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
                <div class="question-hero-copy">
                    <span class="question-kicker"><i class="bx bx-help-circle"></i> Mock Test Bank</span>
                    <h5 class="mb-1 fw-semibold">Question Bank</h5>
                    <p class="mb-0">Language-wise MCQ, answer key, marking and explanations.</p>
                </div>
                <div class="question-hero-actions">
                    <a class="btn btn-light btn-wave" href="<?= h(app_url('sadmin/govt-prep-mocks')); ?>"><i class="bx bx-notepad"></i> Mocks</a>
                    <a class="btn btn-primary btn-wave" href="#add-question"><i class="bx bx-plus"></i> Add Question</a>
                </div>
            </div>
            <div class="card-body">
                <div class="question-bank-metrics">
                    <article><span class="metric-icon"><i class="bx bx-layer"></i></span><span class="metric-label">Mock Tests</span><strong><?= h((string) $totalMocks); ?></strong></article>
                    <article><span class="metric-icon"><i class="bx bx-list-check"></i></span><span class="metric-label">Total Questions</span><strong><?= h((string) $totalQuestions); ?></strong></article>
                    <article><span class="metric-icon"><i class="bx bx-check-shield"></i></span><span class="metric-label">Active</span><strong><?= h((string) $activeQuestions); ?></strong></article>
                    <article><span class="metric-icon"><i class="bx bx-hide"></i></span><span class="metric-label">Inactive</span><strong><?= h((string) $inactiveQuestions); ?></strong></article>
                    <article><span class="metric-icon"><i class="bx bx-message-square-x"></i></span><span class="metric-label">No Explanation</span><strong><?= h((string) $missingExplanations); ?></strong></article>
                    <article><span class="metric-icon"><i class="bx bx-filter-alt"></i></span><span class="metric-label">Current View</span><strong><?= h((string) $filteredQuestions); ?></strong></article>
                </div>
            </div>
        </section>

        <section class="question-bank-panel card custom-card">
            <div class="card-header question-register-head">
                <div>
                    <h6 class="mb-1 fw-semibold">Question Register</h6>
                    <p class="mb-0 text-muted fs-12"><?= h((string) $pageStart); ?>-<?= h((string) $pageEnd); ?> of <?= h((string) $filteredQuestions); ?> records - <?= h($filterLabel); ?></p>
                </div>
                <div class="question-quick-actions">
                    <a class="btn btn-sm btn-light btn-wave" href="<?= h(app_url('sadmin/govt-prep-questions')); ?>"><i class="bx bx-reset"></i> All</a>
                    <a class="btn btn-sm btn-light btn-wave" href="<?= h(app_url('sadmin/govt-prep-mocks')); ?>"><i class="bx bx-notepad"></i> Mocks</a>
                </div>
            </div>
            <div class="question-filter-bar">
                <form class="question-filter" method="get">
                    <input type="hidden" name="page" value="1">
                    <label class="filter-field filter-search"><span>Search</span><div class="question-search"><i class="bx bx-search"></i><input name="q" value="<?= h($search); ?>" placeholder="Question, option, mock"></div></label>
                    <label class="filter-field filter-mock"><span>Mock Test</span><select class="form-select form-select-sm" name="mock_id"><?= gov_exam_mock_options($mockId ?: null); ?></select></label>
                    <label class="filter-field"><span>Status</span><select class="form-select form-select-sm" name="status">
                        <option value="">All Status</option>
                        <?php foreach (['active' => 'Active', 'inactive' => 'Inactive'] as $value => $label): ?>
                            <option value="<?= h($value); ?>" <?= $questionStatus === $value ? 'selected' : ''; ?>><?= h($label); ?></option>
                        <?php endforeach; ?>
                    </select></label>
                    <label class="filter-field"><span>Answer</span><select class="form-select form-select-sm" name="answer">
                        <option value="">All Answers</option>
                        <?php foreach (['A', 'B', 'C', 'D', 'E'] as $answer): ?>
                            <option value="<?= h($answer); ?>" <?= $answerFilter === $answer ? 'selected' : ''; ?>>Answer <?= h($answer); ?></option>
                        <?php endforeach; ?>
                    </select></label>
                    <label class="filter-field filter-rows"><span>Rows</span><select class="form-select form-select-sm" name="per_page">
                        <?php foreach ($perPageOptions as $option): ?>
                            <option value="<?= (int) $option; ?>" <?= $perPage === $option ? 'selected' : ''; ?>><?= (int) $option; ?></option>
                        <?php endforeach; ?>
                    </select></label>
                    <button class="btn btn-sm btn-primary btn-wave filter-submit" type="submit"><i class="bx bx-filter"></i> Filter</button>
                </form>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive question-table-wrap">
                    <table class="table table-hover align-middle mb-0 gr-register-table gov-question-table">
                        <thead><tr><th>Order</th><th>Mock</th><th>Question</th><th>Options</th><th>Answer</th><th>Marks</th><th>Status</th><th class="text-end">Action</th></tr></thead>
                        <tbody>
                            <?php foreach ($rows as $row): ?>
                                <tr>
                                    <td><span class="order-chip">#<?= (int) $row['sort_order']; ?></span></td>
                                    <td class="mock-copy"><strong title="<?= h($row['mock_title'] ?: 'Mock #' . (int) $row['mock_test_id']); ?>"><?= h($row['mock_title'] ?: 'Mock #' . (int) $row['mock_test_id']); ?></strong><small title="<?= h(trim(($row['category_name'] ?? 'Category') . (($row['subcategory_name'] ?? '') ? ' / ' . $row['subcategory_name'] : ''))); ?>"><?= h(trim(($row['category_name'] ?? 'Category') . (($row['subcategory_name'] ?? '') ? ' / ' . $row['subcategory_name'] : ''))); ?></small></td>
                                    <td class="question-copy"><strong title="<?= h($row['question_en'] ?: $row['question_hi']); ?>"><?= h($row['question_en'] ?: $row['question_hi']); ?></strong><small><?= $row['question_hi'] && $row['question_en'] ? 'EN + HI' : 'Single language'; ?></small></td>
                                    <td class="option-stack" title="A: <?= h($row['option_a_en'] ?: $row['option_a_hi']); ?> | B: <?= h($row['option_b_en'] ?: $row['option_b_hi']); ?> | C: <?= h($row['option_c_en'] ?: $row['option_c_hi'] ?: '-'); ?> | D: <?= h($row['option_d_en'] ?: $row['option_d_hi'] ?: '-'); ?>"><small><b>A</b><span><?= h($row['option_a_en'] ?: $row['option_a_hi']); ?></span></small><small><b>B</b><span><?= h($row['option_b_en'] ?: $row['option_b_hi']); ?></span></small><small><b>C</b><span><?= h($row['option_c_en'] ?: $row['option_c_hi'] ?: '-'); ?></span></small><small><b>D</b><span><?= h($row['option_d_en'] ?: $row['option_d_hi'] ?: '-'); ?></span></small></td>
                                    <td><span class="answer-chip"><?= h($row['correct_answer']); ?></span></td>
                                    <td class="marks-cell"><strong><?= h((string) $row['marks']); ?></strong><small>-<?= h((string) $row['negative_marks']); ?></small></td>
                                    <td><?= gov_exam_status($row['status']); ?></td>
                                    <td class="text-end"><a class="btn btn-sm btn-light btn-wave edit-question-btn" href="#edit-question" data-question='<?= h(json_encode($row, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)); ?>'><i class="bx bx-edit-alt"></i> Edit</a></td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if (!$rows): ?><tr><td colspan="8" class="empty-state premium-empty"><strong>No questions found</strong><small>Filter change karein ya selected mock test me first English/Hindi question add karein.</small></td></tr><?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="card-footer sadmin-pagination">
                <span><?= h((string) $pageStart); ?>-<?= h((string) $pageEnd); ?> of <?= h((string) $filteredQuestions); ?> records</span>
                <div class="pagination-actions">
                    <?php $pageWindowStart = max(1, $page - 2); $pageWindowEnd = min($totalPages, $page + 2); ?>
                    <a class="btn btn-sm btn-light <?= $page <= 1 ? 'disabled' : ''; ?>" href="<?= h(app_url('sadmin/govt-prep-questions?page=1' . $filterSuffix)); ?>"><i class="bx bx-chevrons-left"></i></a>
                    <a class="btn btn-sm btn-light <?= $page <= 1 ? 'disabled' : ''; ?>" href="<?= h(app_url('sadmin/govt-prep-questions?page=' . max(1, $page - 1) . $filterSuffix)); ?>"><i class="bx bx-chevron-left"></i> Prev</a>
                    <?php for ($pageNumber = $pageWindowStart; $pageNumber <= $pageWindowEnd; $pageNumber++): ?>
                        <a class="page-chip <?= $pageNumber === $page ? 'active' : ''; ?>" href="<?= h(app_url('sadmin/govt-prep-questions?page=' . $pageNumber . $filterSuffix)); ?>"><?= h((string) $pageNumber); ?></a>
                    <?php endfor; ?>
                    <a class="btn btn-sm btn-light <?= $page >= $totalPages ? 'disabled' : ''; ?>" href="<?= h(app_url('sadmin/govt-prep-questions?page=' . min($totalPages, $page + 1) . $filterSuffix)); ?>">Next <i class="bx bx-chevron-right"></i></a>
                    <a class="btn btn-sm btn-light <?= $page >= $totalPages ? 'disabled' : ''; ?>" href="<?= h(app_url('sadmin/govt-prep-questions?page=' . $totalPages . $filterSuffix)); ?>"><i class="bx bx-chevrons-right"></i></a>
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

        <div id="edit-question" class="modal-overlay">
            <form class="modal-box wide-modal question-modal premium-question-modal" method="post">
                <input type="hidden" name="csrf_token" value="<?= h(csrf_token()); ?>">
                <input type="hidden" name="id" value="">
                <div class="modal-head question-modal-head"><div><span>Mock Question</span><h2>Edit Question</h2></div><a href="#">&times;</a></div>
                <div class="question-form-section">
                    <h3>Mock & Status</h3>
                    <div class="form-grid">
                    <label class="span-2">Mock Test<select name="mock_test_id" required><?= gov_exam_mock_options($mockId ?: null); ?></select></label>
                    <label>Sort Order<input type="number" name="sort_order" min="1"></label>
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
                    <label>Marks<input type="number" step="0.01" name="marks"></label>
                    <label>Negative Marks<input type="number" step="0.01" name="negative_marks"></label>
                    <label class="span-2">Explanation English<textarea name="explanation_en" rows="2"></textarea></label>
                    <label class="span-2">Explanation Hindi<textarea name="explanation_hi" rows="2"></textarea></label>
                    </div>
                </div>
                <div class="modal-actions"><button type="submit">Update Question</button></div>
            </form>
        </div>
        <script>
            document.querySelectorAll('.edit-question-btn').forEach((button) => {
                button.addEventListener('click', () => {
                    const data = JSON.parse(button.dataset.question || '{}');
                    const form = document.querySelector('#edit-question form');
                    if (!form) return;
                    ['id','mock_test_id','question_en','question_hi','option_a_en','option_a_hi','option_b_en','option_b_hi','option_c_en','option_c_hi','option_d_en','option_d_hi','option_e_en','option_e_hi','correct_answer','marks','negative_marks','explanation_en','explanation_hi','sort_order','status'].forEach((name) => {
                        const field = form.elements[name];
                        if (field) field.value = data[name] ?? '';
                    });
                });
            });
        </script>
    <style>
        .sadmin-govquestions-main .govt-question-page {
            padding: 1rem .4rem 1.5rem !important;
            display: grid !important;
            gap: 1rem !important;
            background: #f3f6fb !important;
        }
        .sadmin-govquestions-main .question-bank-shell,
        .sadmin-govquestions-main .question-bank-panel {
            border: 1px solid #dbe7f3 !important;
            border-top: 2px solid #ff8a00 !important;
            border-radius: .35rem !important;
            background: #fff !important;
            box-shadow: 0 .45rem 1.1rem rgba(15, 23, 42, .06) !important;
            overflow: hidden !important;
        }
        .sadmin-govquestions-main .question-bank-shell .card-header,
        .sadmin-govquestions-main .question-register-head {
            display: flex !important;
            align-items: center !important;
            justify-content: space-between !important;
            gap: 1rem !important;
            padding: .9rem 1rem !important;
            border-bottom: 1px solid #dbe7f3 !important;
            background: #fff !important;
            color: #07142b !important;
        }
        .sadmin-govquestions-main .question-hero-copy h5,
        .sadmin-govquestions-main .question-register-head h6 {
            margin: 0 0 .25rem !important;
            color: #07142b !important;
            font-size: 1.05rem !important;
            font-weight: 800 !important;
            line-height: 1.15 !important;
        }
        .sadmin-govquestions-main .question-hero-copy p,
        .sadmin-govquestions-main .question-register-head p {
            color: #64748b !important;
            font-size: .8rem !important;
            line-height: 1.35 !important;
        }
        .sadmin-govquestions-main .question-kicker {
            display: inline-flex !important;
            align-items: center !important;
            gap: .3rem !important;
            margin-bottom: .3rem !important;
            color: #f97316 !important;
            font-size: .72rem !important;
            font-weight: 800 !important;
            text-transform: none !important;
            letter-spacing: 0 !important;
        }
        .sadmin-govquestions-main .question-hero-actions,
        .sadmin-govquestions-main .question-quick-actions {
            display: flex !important;
            align-items: center !important;
            justify-content: flex-end !important;
            gap: .45rem !important;
            flex-wrap: wrap !important;
        }
        .sadmin-govquestions-main .question-hero-actions .btn,
        .sadmin-govquestions-main .question-quick-actions .btn,
        .sadmin-govquestions-main .filter-submit,
        .sadmin-govquestions-main .gov-question-table .btn-sm,
        .sadmin-govquestions-main .sadmin-pagination .btn {
            display: inline-flex !important;
            align-items: center !important;
            justify-content: center !important;
            gap: .3rem !important;
            min-height: 2.15rem !important;
            padding: .38rem .7rem !important;
            border-radius: .25rem !important;
            font-size: .78rem !important;
            font-weight: 800 !important;
            white-space: nowrap !important;
        }
        .sadmin-govquestions-main .question-bank-metrics {
            display: grid !important;
            grid-template-columns: repeat(6, minmax(0, 1fr)) !important;
            gap: .8rem !important;
            padding: .75rem 1rem .85rem !important;
        }
        .sadmin-govquestions-main .question-bank-metrics article {
            display: grid !important;
            grid-template-columns: 2rem minmax(0, 1fr) !important;
            grid-template-areas: "icon label" "icon value" !important;
            align-items: center !important;
            gap: .18rem .55rem !important;
            min-height: 4rem !important;
            padding: .72rem .8rem !important;
            border: 1px solid #d7e5f4 !important;
            border-radius: .45rem !important;
            background: #fbfdff !important;
        }
        .sadmin-govquestions-main .metric-icon {
            grid-area: icon !important;
            display: inline-flex !important;
            align-items: center !important;
            justify-content: center !important;
            width: 2rem !important;
            height: 2rem !important;
            border-radius: .45rem !important;
            background: #eef6ff !important;
            color: #0b3d69 !important;
            font-size: 1.05rem !important;
        }
        .sadmin-govquestions-main .metric-label {
            grid-area: label !important;
            display: block !important;
            min-width: 0 !important;
            overflow: hidden !important;
            color: #475569 !important;
            font-size: .74rem !important;
            font-weight: 750 !important;
            line-height: 1.05 !important;
            text-overflow: ellipsis !important;
            white-space: nowrap !important;
        }
        .sadmin-govquestions-main .question-bank-metrics strong {
            grid-area: value !important;
            display: block !important;
            min-width: 0 !important;
            overflow: hidden !important;
            color: #07142b !important;
            font-size: 1.05rem !important;
            font-weight: 900 !important;
            line-height: 1.05 !important;
            text-overflow: ellipsis !important;
            white-space: nowrap !important;
        }
        .sadmin-govquestions-main .question-filter-bar {
            padding: .85rem 1rem !important;
            border-bottom: 1px solid #dbe7f3 !important;
            background: #fbfdff !important;
        }
        .sadmin-govquestions-main .question-filter {
            display: grid !important;
            grid-template-columns: minmax(15rem, 1.35fr) minmax(16rem, 1.8fr) minmax(8rem, .7fr) minmax(8rem, .7fr) 4.7rem auto !important;
            align-items: end !important;
            gap: .65rem !important;
            width: 100% !important;
            margin: 0 !important;
        }
        .sadmin-govquestions-main .filter-field {
            display: grid !important;
            gap: .28rem !important;
            min-width: 0 !important;
            margin: 0 !important;
            color: #475569 !important;
            font-size: .7rem !important;
            font-weight: 800 !important;
        }
        .sadmin-govquestions-main .filter-field > span {
            display: block !important;
            color: #64748b !important;
            font-size: .68rem !important;
            font-weight: 850 !important;
            line-height: 1 !important;
            text-transform: uppercase !important;
        }
        .sadmin-govquestions-main .question-search,
        .sadmin-govquestions-main .question-filter .form-select {
            display: flex !important;
            align-items: center !important;
            width: 100% !important;
            min-width: 0 !important;
            height: 2.25rem !important;
            min-height: 2.25rem !important;
            border: 1px solid #cbd9ea !important;
            border-radius: .25rem !important;
            background-color: #fff !important;
            color: #07142b !important;
            font-size: .8rem !important;
            font-weight: 650 !important;
            box-shadow: none !important;
        }
        .sadmin-govquestions-main .question-search { gap: .35rem !important; padding: 0 .65rem !important; }
        .sadmin-govquestions-main .question-search input {
            flex: 1 1 auto !important;
            width: 100% !important;
            min-width: 0 !important;
            border: 0 !important;
            outline: 0 !important;
            background: transparent !important;
            color: #07142b !important;
            font: inherit !important;
        }
        .sadmin-govquestions-main .question-table-wrap {
            max-height: none !important;
            padding: .75rem 1rem 0 !important;
            background: #fff !important;
        }
        .sadmin-govquestions-main .gov-question-table {
            min-width: 82rem !important;
            width: 100% !important;
            table-layout: fixed !important;
            border: 1px solid #dbe7f3 !important;
            border-collapse: separate !important;
            border-spacing: 0 !important;
        }
        .sadmin-govquestions-main .gov-question-table th,
        .sadmin-govquestions-main .gov-question-table td {
            padding: .7rem .75rem !important;
            border-bottom: 1px solid #dbe7f3 !important;
            color: #07142b !important;
            font-size: .8rem !important;
            line-height: 1.3 !important;
            vertical-align: middle !important;
        }
        .sadmin-govquestions-main .gov-question-table thead th {
            position: sticky !important;
            top: 0 !important;
            z-index: 2 !important;
            background: #edf4fb !important;
            color: #08345f !important;
            font-size: .72rem !important;
            font-weight: 900 !important;
            letter-spacing: 0 !important;
            text-transform: uppercase !important;
        }
        .sadmin-govquestions-main .gov-question-table th:nth-child(1) { width: 5rem !important; }
        .sadmin-govquestions-main .gov-question-table th:nth-child(2) { width: 16rem !important; }
        .sadmin-govquestions-main .gov-question-table th:nth-child(3) { width: 15rem !important; }
        .sadmin-govquestions-main .gov-question-table th:nth-child(4) { width: 20rem !important; }
        .sadmin-govquestions-main .gov-question-table th:nth-child(5) { width: 5rem !important; }
        .sadmin-govquestions-main .gov-question-table th:nth-child(6) { width: 6rem !important; }
        .sadmin-govquestions-main .gov-question-table th:nth-child(7) { width: 7rem !important; }
        .sadmin-govquestions-main .gov-question-table th:nth-child(8) { width: 6rem !important; }
        .sadmin-govquestions-main .gov-question-table tbody tr:hover { background: #f8fbff !important; }
        .sadmin-govquestions-main .mock-copy,
        .sadmin-govquestions-main .question-copy,
        .sadmin-govquestions-main .option-stack { min-width: 0 !important; }
        .sadmin-govquestions-main .mock-copy strong,
        .sadmin-govquestions-main .question-copy strong {
            display: -webkit-box !important;
            overflow: hidden !important;
            -webkit-box-orient: vertical !important;
            -webkit-line-clamp: 2 !important;
            color: #07142b !important;
            font-size: .82rem !important;
            font-weight: 850 !important;
            line-height: 1.22 !important;
        }
        .sadmin-govquestions-main .mock-copy small,
        .sadmin-govquestions-main .question-copy small {
            display: -webkit-box !important;
            overflow: hidden !important;
            margin-top: .2rem !important;
            -webkit-box-orient: vertical !important;
            -webkit-line-clamp: 1 !important;
            color: #64748b !important;
            font-size: .72rem !important;
            line-height: 1.25 !important;
        }
        .sadmin-govquestions-main .option-stack {
            display: grid !important;
            grid-template-columns: repeat(2, minmax(0, 1fr)) !important;
            gap: .28rem .6rem !important;
        }
        .sadmin-govquestions-main .option-stack small {
            display: grid !important;
            grid-template-columns: 1.15rem minmax(0, 1fr) !important;
            align-items: start !important;
            gap: .28rem !important;
            min-width: 0 !important;
            color: #64748b !important;
            font-size: .72rem !important;
            line-height: 1.2 !important;
        }
        .sadmin-govquestions-main .option-stack small span {
            display: -webkit-box !important;
            overflow: hidden !important;
            -webkit-box-orient: vertical !important;
            -webkit-line-clamp: 1 !important;
        }
        .sadmin-govquestions-main .option-stack b {
            display: inline-flex !important;
            align-items: center !important;
            justify-content: center !important;
            width: 1.15rem !important;
            height: 1.15rem !important;
            border-radius: 999px !important;
            background: #eef6ff !important;
            color: #0b3d69 !important;
            font-size: .62rem !important;
            font-weight: 900 !important;
        }
        .sadmin-govquestions-main .order-chip,
        .sadmin-govquestions-main .answer-chip,
        .sadmin-govquestions-main .page-chip {
            display: inline-flex !important;
            align-items: center !important;
            justify-content: center !important;
            min-width: 1.8rem !important;
            min-height: 1.6rem !important;
            padding: .18rem .55rem !important;
            border-radius: 999px !important;
            background: #eef6ff !important;
            color: #0b3d69 !important;
            font-size: .72rem !important;
            font-weight: 900 !important;
            text-decoration: none !important;
            white-space: nowrap !important;
        }
        .sadmin-govquestions-main .answer-chip { background: #e9fbf2 !important; color: #059669 !important; }
        .sadmin-govquestions-main .page-chip.active { background: #0b3d69 !important; color: #fff !important; }
        .sadmin-govquestions-main .marks-cell strong { display: block !important; font-weight: 900 !important; }
        .sadmin-govquestions-main .marks-cell small { display: block !important; color: #64748b !important; font-size: .72rem !important; }
        .sadmin-govquestions-main .gov-question-table .status-pill,
        .sadmin-govquestions-main .gov-question-table .badge {
            min-height: 1.45rem !important;
            padding: .2rem .6rem !important;
            border-radius: 999px !important;
            font-size: .72rem !important;
            font-weight: 900 !important;
        }
        .sadmin-govquestions-main .sadmin-pagination {
            display: flex !important;
            align-items: center !important;
            justify-content: space-between !important;
            gap: .75rem !important;
            padding: .9rem 1rem !important;
            border-top: 1px solid #dbe7f3 !important;
            background: #fff !important;
            color: #07142b !important;
            font-size: .82rem !important;
        }
        .sadmin-govquestions-main .pagination-actions {
            display: flex !important;
            align-items: center !important;
            gap: .3rem !important;
            flex-wrap: wrap !important;
        }
        /* Compact table authority */
        .sadmin-govquestions-main .question-table-wrap { padding: 0 !important; }
        .sadmin-govquestions-main .gov-question-table {
            min-width: 74rem !important;
            border-inline: 0 !important;
            border-bottom: 0 !important;
        }
        .sadmin-govquestions-main .gov-question-table th,
        .sadmin-govquestions-main .gov-question-table td {
            padding: .48rem .65rem !important;
            font-size: .74rem !important;
            line-height: 1.2 !important;
        }
        .sadmin-govquestions-main .gov-question-table thead th {
            font-size: .68rem !important;
            height: 2.15rem !important;
        }
        .sadmin-govquestions-main .gov-question-table th:nth-child(1) { width: 4rem !important; }
        .sadmin-govquestions-main .gov-question-table th:nth-child(2) { width: 15rem !important; }
        .sadmin-govquestions-main .gov-question-table th:nth-child(3) { width: 16rem !important; }
        .sadmin-govquestions-main .gov-question-table th:nth-child(4) { width: 20rem !important; }
        .sadmin-govquestions-main .gov-question-table th:nth-child(5) { width: 4.5rem !important; }
        .sadmin-govquestions-main .gov-question-table th:nth-child(6) { width: 4.8rem !important; }
        .sadmin-govquestions-main .gov-question-table th:nth-child(7) { width: 5.8rem !important; }
        .sadmin-govquestions-main .gov-question-table th:nth-child(8) { width: 5.4rem !important; }
        .sadmin-govquestions-main .mock-copy strong,
        .sadmin-govquestions-main .question-copy strong {
            -webkit-line-clamp: 1 !important;
            font-size: .78rem !important;
            line-height: 1.18 !important;
        }
        .sadmin-govquestions-main .mock-copy small,
        .sadmin-govquestions-main .question-copy small {
            margin-top: .12rem !important;
            font-size: .66rem !important;
            line-height: 1.15 !important;
        }
        .sadmin-govquestions-main .option-stack {
            grid-template-columns: repeat(4, minmax(0, 1fr)) !important;
            gap: .2rem !important;
        }
        .sadmin-govquestions-main .option-stack small {
            grid-template-columns: .9rem minmax(0, 1fr) !important;
            gap: .18rem !important;
            font-size: .66rem !important;
            line-height: 1.1 !important;
        }
        .sadmin-govquestions-main .option-stack b {
            width: .9rem !important;
            height: .9rem !important;
            font-size: .55rem !important;
        }
        .sadmin-govquestions-main .order-chip,
        .sadmin-govquestions-main .answer-chip,
        .sadmin-govquestions-main .page-chip {
            min-width: 1.35rem !important;
            min-height: 1.25rem !important;
            padding: .08rem .38rem !important;
            font-size: .66rem !important;
        }
        .sadmin-govquestions-main .marks-cell strong { font-size: .74rem !important; line-height: 1.05 !important; }
        .sadmin-govquestions-main .marks-cell small { font-size: .64rem !important; line-height: 1.05 !important; }
        .sadmin-govquestions-main .gov-question-table .status-pill,
        .sadmin-govquestions-main .gov-question-table .badge {
            min-height: 1.25rem !important;
            padding: .1rem .5rem !important;
            font-size: .66rem !important;
        }
        .sadmin-govquestions-main .gov-question-table .btn-sm {
            min-height: 1.55rem !important;
            padding: .16rem .45rem !important;
            font-size: .68rem !important;
        }
        /* Keep question table text light and simple */
        .sadmin-govquestions-main .gov-question-table,
        .sadmin-govquestions-main .gov-question-table th,
        .sadmin-govquestions-main .gov-question-table td,
        .sadmin-govquestions-main .gov-question-table strong,
        .sadmin-govquestions-main .gov-question-table b,
        .sadmin-govquestions-main .gov-question-table small,
        .sadmin-govquestions-main .gov-question-table .btn,
        .sadmin-govquestions-main .gov-question-table .status-pill,
        .sadmin-govquestions-main .gov-question-table .badge,
        .sadmin-govquestions-main .order-chip,
        .sadmin-govquestions-main .answer-chip {
            font-weight: 400 !important;
        }
        .sadmin-govquestions-main .gov-question-table thead th {
            font-weight: 500 !important;
        }
        .sadmin-govquestions-main .mock-copy strong,
        .sadmin-govquestions-main .question-copy strong,
        .sadmin-govquestions-main .marks-cell strong {
            font-weight: 500 !important;
        }
        .sadmin-govquestions-main .premium-question-modal {
            border: 0 !important;
            border-radius: .6rem !important;
            box-shadow: 0 1.25rem 3rem rgba(15, 23, 42, .18) !important;
        }
        .sadmin-govquestions-main .question-modal-head {
            margin: -1.25rem -1.25rem 1rem !important;
            padding: 1rem 1.25rem !important;
            background: #0b3d69 !important;
            color: #fff !important;
        }
        .sadmin-govquestions-main .question-modal-head span { color: rgba(255, 255, 255, .72) !important; }
        .sadmin-govquestions-main .question-modal-head h2 { color: #fff !important; }
        .sadmin-govquestions-main .question-modal-head a { color: #fff !important; opacity: .85 !important; }
        .sadmin-govquestions-main .question-form-section {
            margin-bottom: .85rem !important;
            padding: .85rem !important;
            border: 1px solid #dbe7f3 !important;
            border-radius: .45rem !important;
            background: #f8fbff !important;
        }
        .sadmin-govquestions-main .question-form-section h3 {
            margin: 0 0 .7rem !important;
            color: #07142b !important;
            font-size: .78rem !important;
            font-weight: 900 !important;
            text-transform: uppercase !important;
        }
        .sadmin-govquestions-main .premium-empty {
            padding: 2rem 1rem !important;
            text-align: center !important;
            color: #64748b !important;
        }
        .sadmin-govquestions-main .footer { margin-inline: 0 !important; width: 100% !important; }
        @media (max-width: 1399.98px) {
            .sadmin-govquestions-main .question-bank-metrics { grid-template-columns: repeat(3, minmax(0, 1fr)) !important; }
            .sadmin-govquestions-main .question-filter { grid-template-columns: repeat(4, minmax(0, 1fr)) !important; }
            .sadmin-govquestions-main .filter-search,
            .sadmin-govquestions-main .filter-mock { grid-column: span 2 !important; }
        }
        @media (max-width: 991.98px) {
            .sadmin-govquestions-main .question-bank-shell .card-header,
            .sadmin-govquestions-main .question-register-head,
            .sadmin-govquestions-main .sadmin-pagination { align-items: flex-start !important; flex-direction: column !important; }
            .sadmin-govquestions-main .question-bank-metrics { grid-template-columns: repeat(2, minmax(0, 1fr)) !important; }
            .sadmin-govquestions-main .question-filter { grid-template-columns: repeat(2, minmax(0, 1fr)) !important; }
            .sadmin-govquestions-main .filter-search,
            .sadmin-govquestions-main .filter-mock { grid-column: span 2 !important; }
        }
        @media (max-width: 575.98px) {
            .sadmin-govquestions-main .question-bank-metrics,
            .sadmin-govquestions-main .question-filter { grid-template-columns: 1fr !important; }
            .sadmin-govquestions-main .filter-search,
            .sadmin-govquestions-main .filter-mock { grid-column: auto !important; }
            .sadmin-govquestions-main .question-hero-actions,
            .sadmin-govquestions-main .question-quick-actions,
            .sadmin-govquestions-main .filter-submit { width: 100% !important; }
            .sadmin-govquestions-main .question-hero-actions .btn,
            .sadmin-govquestions-main .question-quick-actions .btn,
            .sadmin-govquestions-main .filter-submit { flex: 1 1 auto !important; }
        }
    </style>
</main>
<?php include __DIR__ . '/includes/footer.php'; ?>
