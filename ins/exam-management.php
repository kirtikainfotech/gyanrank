<?php
require_once __DIR__ . '/includes/functions.php';
$user = instructor_user();
ensure_instructor_erp_tables();
$instructorId = (int) $user['id'];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['csrf_token'] ?? null)) {
        $_SESSION['ins_error'] = 'Security token expired.';
        redirect('ins/exam-management');
    }
    try {
        $categoryName = substr(trim((string) ($_POST['category_name'] ?? '')), 0, 140);
        $description = substr(trim((string) ($_POST['description'] ?? '')), 0, 1000);
        $sortOrder = max(1, min(9999, (int) ($_POST['sort_order'] ?? 1)));
        if ($categoryName === '') {
            throw new RuntimeException('Exam category name required.');
        }
        $stmt = db()->prepare('INSERT INTO instructor_exam_categories (instructor_id, category_name, description, sort_order, status) VALUES (?, ?, ?, ?, "active") ON DUPLICATE KEY UPDATE description = VALUES(description), sort_order = VALUES(sort_order), status = "active"');
        $stmt->bind_param('issi', $instructorId, $categoryName, $description, $sortOrder);
        $stmt->execute();
        $_SESSION['ins_success'] = 'Exam category saved.';
    } catch (Throwable $e) {
        $_SESSION['ins_error'] = $e->getMessage();
    }
    redirect('ins/exam-management');
}
$courses = instructor_courses($instructorId);
$examCategories = instructor_exam_categories($instructorId);
$classes = instructor_classes($instructorId);
$exams = instructor_exams($instructorId);
$questions = instructor_questions($instructorId, 500, 0);
$publishedExams = count(array_filter($exams, fn($exam) => ($exam['status'] ?? '') === 'published'));
$liveClasses = count(array_filter($classes, fn($class) => in_array(($class['class_status'] ?? ''), ['live', 'scheduled'], true)));
$pageTitle = 'Exam Management';
$pageSubtitle = 'Exam preparation module: categories, classes, question bank and mock tests.';
$activePage = 'exam-management';
$message = $_SESSION['ins_success'] ?? '';
$error = $_SESSION['ins_error'] ?? '';
unset($_SESSION['ins_success'], $_SESSION['ins_error']);
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
                            <span class="badge bg-white-1 text-fixed-white mb-2">Exam Preparation Platform</span>
                            <h4 class="fw-semibold mb-1 text-fixed-white">Exam Management</h4>
                            <p class="mb-0 op-8">Exam categories, preparation classes, question bank, mock tests aur reports ek console se manage karein.</p>
                        </div>
                        <div class="btn-list">
                            <a class="btn btn-light btn-wave" href="<?= h(app_url('ins/exams') . '#add-exam'); ?>">Create Mock Test</a>
                            <a class="btn btn-outline-light btn-wave" href="<?= h(app_url('ins/questions')); ?>">Question Bank</a>
                            <a class="btn btn-outline-light btn-wave" href="<?= h(app_url('ins/documents') . '#add-resource'); ?>">Exam PDFs</a>
                        </div>
                    </div>
                </div>
            </div>

            <?php
            $examMetrics = [
                ['label' => 'Categories', 'value' => count($examCategories), 'icon' => 'bx bx-category', 'tone' => 'primary'],
                ['label' => 'Mock Tests', 'value' => count($exams), 'icon' => 'bx bx-task', 'tone' => 'info'],
                ['label' => 'Published', 'value' => $publishedExams, 'icon' => 'bx bx-check-circle', 'tone' => 'success'],
                ['label' => 'Prep Classes', 'value' => $liveClasses, 'icon' => 'bx bx-video', 'tone' => 'warning'],
                ['label' => 'Questions', 'value' => count($questions), 'icon' => 'bx bx-question-mark', 'tone' => 'secondary'],
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

            <div class="col-xl-4">
                <div class="card custom-card">
                    <div class="card-header">
                        <div><div class="card-title mb-0">Create Exam Category</div><p class="mb-0 text-muted fs-12">CCC, O Level, SSC jaise preparation groups.</p></div>
                    </div>
                    <div class="card-body">
                        <form method="post" class="row g-3">
                            <input type="hidden" name="csrf_token" value="<?= h(csrf_token()); ?>">
                            <div class="col-12"><label class="form-label">Category Name</label><input class="form-control" name="category_name" placeholder="CCC / O Level / SSC" required></div>
                            <div class="col-12"><label class="form-label">Order</label><input class="form-control" type="number" name="sort_order" min="1" value="1"></div>
                            <div class="col-12"><label class="form-label">Description</label><textarea class="form-control" name="description" rows="3" placeholder="Short preparation category note"></textarea></div>
                            <div class="col-12 text-end"><button class="btn btn-primary btn-wave" type="submit">Save Category</button></div>
                        </form>
                    </div>
                </div>
                <div class="card custom-card">
                    <div class="card-header"><div class="card-title mb-0">Quick Workflow</div></div>
                    <div class="card-body p-0">
                        <div class="list-group list-group-flush">
                            <a class="list-group-item list-group-item-action d-flex justify-content-between align-items-center" href="<?= h(app_url('ins/classes')); ?>">Exam Classes <i class="bx bx-chevron-right"></i></a>
                            <a class="list-group-item list-group-item-action d-flex justify-content-between align-items-center" href="<?= h(app_url('ins/questions')); ?>">Question Bank <i class="bx bx-chevron-right"></i></a>
                            <a class="list-group-item list-group-item-action d-flex justify-content-between align-items-center" href="<?= h(app_url('ins/exams')); ?>">Mock Tests <i class="bx bx-chevron-right"></i></a>
                            <a class="list-group-item list-group-item-action d-flex justify-content-between align-items-center" href="<?= h(app_url('ins/documents') . '#add-resource'); ?>">Exam PDFs <i class="bx bx-chevron-right"></i></a>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-xl-8">
                <div class="card custom-card">
                    <div class="card-header">
                        <div><div class="card-title mb-0">Exam Categories</div><p class="mb-0 text-muted fs-12">Categories are separate from courses and used to filter mock tests.</p></div>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-sm table-hover align-middle mb-0 gr-register-table">
                                <colgroup><col style="width: 28%;"><col style="width: 42%;"><col style="width: 14%;"><col style="width: 16%;"></colgroup>
                                <thead><tr><th>Category</th><th>Description</th><th>Mock Tests</th><th>Status</th></tr></thead>
                                <tbody>
                                    <?php if (!$examCategories): ?><tr><td colspan="4" class="text-muted py-4">No exam category created yet.</td></tr><?php endif; ?>
                                    <?php foreach ($examCategories as $category): ?>
                                        <?php $categoryTone = ($category['status'] ?? '') === 'active' ? 'success' : 'warning'; ?>
                                        <tr>
                                            <td><span class="fw-semibold text-truncate gr-cell-title"><?= h($category['category_name']); ?></span><span class="text-muted fs-12">Order <?= h((string) $category['sort_order']); ?></span></td>
                                            <td><span class="text-muted text-truncate gr-cell-title"><?= h((string) ($category['description'] ?? 'No description')); ?></span></td>
                                            <td><?= h((string) ($category['exam_count'] ?? 0)); ?></td>
                                            <td><span class="badge bg-<?= h($categoryTone); ?>-transparent text-<?= h($categoryTone); ?>"><?= h(ucfirst((string) $category['status'])); ?></span></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-12">
                <div class="card custom-card">
                    <div class="card-header justify-content-between">
                        <div><div class="card-title mb-0">Mock Test Register</div><p class="mb-0 text-muted fs-12">All tests with category, duration, question count and build action.</p></div>
                        <a class="btn btn-sm btn-primary btn-wave" href="<?= h(app_url('ins/exams')); ?>">Manage Tests</a>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-sm table-hover align-middle mb-0 gr-register-table">
                                <colgroup><col style="width: 32%;"><col style="width: 22%;"><col style="width: 12%;"><col style="width: 12%;"><col style="width: 10%;"><col style="width: 12%;"></colgroup>
                                <thead><tr><th>Mock Test</th><th>Category</th><th>Type</th><th>Questions</th><th>Status</th><th class="text-end">Action</th></tr></thead>
                                <tbody>
                                    <?php if (!$exams): ?><tr><td colspan="6" class="text-muted py-4">No mock test created yet.</td></tr><?php endif; ?>
                                    <?php foreach ($exams as $exam): ?>
                                        <?php $examTone = ($exam['status'] ?? '') === 'published' ? 'success' : 'warning'; ?>
                                        <tr>
                                            <td><span class="fw-semibold text-truncate gr-cell-title"><?= h($exam['title']); ?></span><span class="text-muted fs-12"><?= h((string) ($exam['duration_minutes'] ?? 0)); ?> min</span></td>
                                            <td><span class="text-truncate gr-cell-title"><?= h($exam['exam_category_name'] ?: 'No category'); ?></span></td>
                                            <td><?= h(ucfirst((string) $exam['exam_type'])); ?></td>
                                            <td><?= h((string) $exam['total_questions']); ?></td>
                                            <td><span class="badge bg-<?= h($examTone); ?>-transparent text-<?= h($examTone); ?>"><?= h(ucfirst((string) $exam['status'])); ?></span></td>
                                            <td class="text-end"><a class="btn btn-sm btn-primary-light btn-wave" href="<?= h(app_url('ins/exam-questions') . '?exam_id=' . (int) $exam['id']); ?>">Build</a></td>
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
<?php include __DIR__ . '/includes/footer.php'; ?>
