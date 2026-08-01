<?php
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/../includes/live_streaming.php';

$user = instructor_user();
ensure_instructor_erp_tables();
ensure_live_streaming_tables();
$instructorId = (int) $user['id'];

$courses = instructor_courses($instructorId);
$documents = instructor_course_resources($instructorId);
$exams = instructor_exams($instructorId);
$liveChannel = live_channel_for_instructor($instructorId, (string) ($user['name'] ?? 'Instructor'));
$liveChannels = $liveChannel ? [$liveChannel] : [];

$courseIds = array_map('intval', array_column($courses, 'id'));
$documentIds = array_map('intval', array_column($documents, 'id'));
$examIds = array_map('intval', array_column($exams, 'id'));
$liveIds = array_map('intval', array_column($liveChannels, 'id'));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['csrf_token'] ?? null)) {
        $_SESSION['ins_error'] = 'Security token expired.';
        redirect('ins/combos');
    }

    try {
        $comboId = (int) ($_POST['combo_id'] ?? 0);
        $name = substr(trim((string) ($_POST['combo_name'] ?? '')), 0, 180);
        $courseId = (int) ($_POST['course_id'] ?? 0);
        $documentId = (int) ($_POST['document_id'] ?? 0);
        $liveChannelId = (int) ($_POST['live_channel_id'] ?? 0);
        $examId = (int) ($_POST['exam_id'] ?? 0);
        $price = max(0, min(999999, (float) ($_POST['price'] ?? 0)));
        $status = in_array($_POST['status'] ?? '', ['draft', 'published', 'paused'], true) ? (string) $_POST['status'] : 'draft';
        $description = substr(trim((string) ($_POST['description'] ?? '')), 0, 1500);

        $courseValue = $courseId > 0 && in_array($courseId, $courseIds, true) ? $courseId : null;
        $documentValue = $documentId > 0 && in_array($documentId, $documentIds, true) ? $documentId : null;
        $liveValue = $liveChannelId > 0 && in_array($liveChannelId, $liveIds, true) ? $liveChannelId : null;
        $examValue = $examId > 0 && in_array($examId, $examIds, true) ? $examId : null;

        if ($name === '') {
            throw new RuntimeException('Combo name required.');
        }
        if ($courseValue === null && $documentValue === null && $liveValue === null && $examValue === null) {
            throw new RuntimeException('Combo me kam se kam ek item select karein.');
        }

        if ($comboId > 0) {
            $stmt = db()->prepare('UPDATE instructor_content_combos SET combo_name = ?, course_id = ?, document_id = ?, live_channel_id = ?, exam_id = ?, price = ?, status = ?, description = ? WHERE id = ? AND instructor_id = ?');
            $stmt->bind_param('siiiidssii', $name, $courseValue, $documentValue, $liveValue, $examValue, $price, $status, $description, $comboId, $instructorId);
            $stmt->execute();
            $_SESSION['ins_success'] = 'Combo updated.';
        } else {
            $stmt = db()->prepare('INSERT INTO instructor_content_combos (instructor_id, combo_name, course_id, document_id, live_channel_id, exam_id, price, status, description) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)');
            $stmt->bind_param('isiiiidss', $instructorId, $name, $courseValue, $documentValue, $liveValue, $examValue, $price, $status, $description);
            $stmt->execute();
            $_SESSION['ins_success'] = 'Combo created.';
        }
    } catch (Throwable $e) {
        $_SESSION['ins_error'] = $e->getMessage();
    }

    redirect('ins/combos');
}

$combos = instructor_content_combos($instructorId);
$published = count(array_filter($combos, fn($combo) => ($combo['status'] ?? '') === 'published'));
$message = $_SESSION['ins_success'] ?? '';
$error = $_SESSION['ins_error'] ?? '';
unset($_SESSION['ins_success'], $_SESSION['ins_error']);
$pageTitle = 'Combos';
$pageSubtitle = 'Bundle course, PDFs, live access and mock tests.';
$activePage = 'combos';
?>
<?php include __DIR__ . '/includes/header.php'; ?>
<?php include __DIR__ . '/includes/sidebar.php'; ?>
<main class="main instructor-combos-page">
    <?php include __DIR__ . '/includes/topbar.php'; ?>
    <section class="content instructor-content">
        <?php if ($message): ?><div class="notice"><?= h($message); ?></div><?php endif; ?>
        <?php if ($error): ?><div class="notice danger"><?= h($error); ?></div><?php endif; ?>

        <div class="row g-3">
            <div class="col-12">
                <div class="card custom-card bg-primary-gradient border-0 text-fixed-white">
                    <div class="card-body d-flex flex-wrap align-items-center justify-content-between gap-3">
                        <div>
                            <span class="badge bg-white-1 text-fixed-white mb-2">Combo Builder</span>
                            <h4 class="fw-semibold mb-1 text-fixed-white">Course + PDF + Live + Mock Test</h4>
                            <p class="mb-0 op-8">Student ke liye bundled learning package create karein with course, document, live aur test access.</p>
                        </div>
                        <div class="btn-list">
                            <a class="btn btn-light btn-wave" href="#add-combo">New Combo</a>
                            <a class="btn btn-outline-light btn-wave" href="<?= h(app_url('ins/courses')); ?>">Courses</a>
                            <a class="btn btn-outline-light btn-wave" href="<?= h(app_url('ins/documents')); ?>">Documents</a>
                        </div>
                    </div>
                </div>
            </div>

            <?php
            $comboMetrics = [
                ['label' => 'Total', 'value' => count($combos), 'icon' => 'bx bx-package', 'tone' => 'primary'],
                ['label' => 'Published', 'value' => $published, 'icon' => 'bx bx-check-circle', 'tone' => 'success'],
                ['label' => 'Courses', 'value' => count($courses), 'icon' => 'bx bx-book-open', 'tone' => 'info'],
                ['label' => 'Documents', 'value' => count($documents), 'icon' => 'bx bx-file', 'tone' => 'secondary'],
                ['label' => 'Mock Tests', 'value' => count($exams), 'icon' => 'bx bx-task', 'tone' => 'warning'],
            ];
            ?>
            <?php foreach ($comboMetrics as $metric): ?>
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
                        <div><div class="card-title mb-0">Bundle Inventory</div><p class="mb-0 text-muted fs-12">All combos with linked course, PDF, live and mock test access.</p></div>
                        <a class="btn btn-sm btn-primary btn-wave" href="#add-combo">New Combo</a>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-sm table-hover align-middle mb-0 gr-register-table">
                                <colgroup><col style="width: 28%;"><col style="width: 28%;"><col style="width: 12%;"><col style="width: 12%;"><col style="width: 20%;"></colgroup>
                                <thead><tr><th>Combo</th><th>Linked Items</th><th>Price</th><th>Status</th><th class="text-end">Action</th></tr></thead>
                                <tbody>
                                    <?php if (!$combos): ?><tr><td colspan="5" class="text-muted py-4">No combo created yet.</td></tr><?php endif; ?>
                                    <?php foreach ($combos as $combo): ?>
                                        <?php
                                        $itemCount = (int) (($combo['course_id'] ? 1 : 0) + ($combo['document_id'] ? 1 : 0) + ($combo['live_channel_id'] ? 1 : 0) + ($combo['exam_id'] ? 1 : 0));
                                        $comboTone = (string) $combo['status'] === 'published' ? 'success' : 'warning';
                                        ?>
                                        <tr>
                                            <td><span class="fw-semibold text-truncate gr-cell-title"><?= h($combo['combo_name']); ?></span><span class="text-muted fs-12"><?= h($combo['course_title'] ?: 'Custom bundle'); ?></span></td>
                                            <td><span class="fw-semibold"><?= h((string) $itemCount); ?> linked</span><span class="text-muted fs-12 text-truncate gr-cell-subtitle"><?= h(trim(($combo['document_title'] ?? '') . ' ' . ($combo['exam_title'] ?? '')) ?: 'Optional items not linked'); ?></span></td>
                                            <td>Rs <?= h(number_format((float) ($combo['price'] ?? 0), 0)); ?></td>
                                            <td><span class="badge bg-<?= h($comboTone); ?>-transparent text-<?= h($comboTone); ?>"><?= h(ucfirst((string) $combo['status'])); ?></span></td>
                                            <td class="text-end"><div class="btn-list justify-content-end"><a class="btn btn-sm btn-primary-light btn-wave" href="#view-combo-<?= (int) $combo['id']; ?>">View</a><a class="btn btn-sm btn-light btn-wave" href="#edit-combo-<?= (int) $combo['id']; ?>">Edit</a></div></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <?php if (false): ?>
        <section class="course-console-hero">
            <div>
                <span>Combo Builder</span>
                <h2>Course + PDF + Live + Mock Test</h2>
                <p>Ek package banayein jisme student ko course material, documents, daily live aur test access ek saath mile.</p>
            </div>
            <div class="course-console-actions">
                <a class="modal-button ghost" href="<?= h(app_url('ins/courses')); ?>">Courses</a>
                <a class="modal-button ghost" href="<?= h(app_url('ins/documents')); ?>">Documents</a>
                <a class="modal-button" href="#add-combo">New Combo</a>
            </div>
        </section>

        <div class="course-summary-strip combo-summary-strip">
            <article><span>Total</span><strong><?= h((string) count($combos)); ?></strong><small>Combos</small></article>
            <article><span>Published</span><strong><?= h((string) $published); ?></strong><small>Visible</small></article>
            <article><span>Courses</span><strong><?= h((string) count($courses)); ?></strong><small>Available</small></article>
            <article><span>Documents</span><strong><?= h((string) count($documents)); ?></strong><small>PDFs</small></article>
            <article><span>Mock Tests</span><strong><?= h((string) count($exams)); ?></strong><small>Exams</small></article>
        </div>

        <section class="settings-detail-card ins-card">
            <div class="detail-head compact-head">
                <div><span>Bundle Inventory</span><h2><?= h((string) count($combos)); ?> combos</h2><p>List simple rakhi hai. Full detail View par milegi.</p></div>
                <a class="modal-button" href="#add-combo">New Combo</a>
            </div>
            <table class="role-access-table smart-table compact-manager-table combo-manager-table">
                <thead><tr><th>Combo</th><th>Items</th><th>Price</th><th>Status</th><th>Action</th></tr></thead>
                <tbody>
                    <?php if (!$combos): ?><tr><td colspan="5">No combo created yet.</td></tr><?php endif; ?>
                    <?php foreach ($combos as $combo): ?>
                        <?php $itemCount = (int) (($combo['course_id'] ? 1 : 0) + ($combo['document_id'] ? 1 : 0) + ($combo['live_channel_id'] ? 1 : 0) + ($combo['exam_id'] ? 1 : 0)); ?>
                        <tr>
                            <td><strong><?= h($combo['combo_name']); ?></strong><small><?= h($combo['course_title'] ?: 'Custom bundle'); ?></small></td>
                            <td><?= h((string) $itemCount); ?> linked</td>
                            <td><strong>Rs <?= h(number_format((float) ($combo['price'] ?? 0), 0)); ?></strong></td>
                            <td><?= h(ucfirst((string) $combo['status'])); ?></td>
                            <td><div class="mini-action-stack"><a class="table-edit-icon" href="#view-combo-<?= (int) $combo['id']; ?>">View</a><a class="table-edit-icon" href="#edit-combo-<?= (int) $combo['id']; ?>">Edit</a></div></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </section>
        <?php endif; ?>
    </section>

    <div id="add-combo" class="modal-overlay">
        <form class="modal-box wide-modal course-modal ins-modal" method="post">
            <input type="hidden" name="csrf_token" value="<?= h(csrf_token()); ?>">
            <div class="modal-head"><h2>Create Combo</h2><a class="modal-close" href="#" aria-label="Close">x</a></div>
            <?php $modalCombo = null; include __DIR__ . '/includes/combo_form.php'; ?>
            <div class="modal-actions"><button type="submit">Save Combo</button></div>
        </form>
    </div>

    <?php foreach ($combos as $combo): ?>
        <div id="view-combo-<?= (int) $combo['id']; ?>" class="modal-overlay">
            <div class="modal-box wide-modal course-modal ins-modal detail-view-modal">
                <div class="modal-head"><h2>Combo Details</h2><a class="modal-close" href="#" aria-label="Close">x</a></div>
                <span class="detail-kicker"><?= h(ucfirst((string) $combo['status'])); ?> Bundle</span>
                <h3><?= h($combo['combo_name']); ?></h3>
                <p><?= h($combo['description'] ?: 'Bundle description not added yet.'); ?></p>
                <div class="detail-info-table">
                    <div><span>Course</span><strong><?= h($combo['course_title'] ?: 'Not linked'); ?></strong></div>
                    <div><span>Document</span><strong><?= h($combo['document_title'] ?: 'Not linked'); ?></strong></div>
                    <div><span>Live</span><strong><?= h($combo['live_title'] ?: 'Not linked'); ?></strong></div>
                    <div><span>Mock Test</span><strong><?= h($combo['exam_title'] ?: 'Not linked'); ?></strong></div>
                    <div><span>Price</span><strong>Rs <?= h(number_format((float) ($combo['price'] ?? 0), 0)); ?></strong></div>
                    <div><span>Updated</span><strong><?= h(date('d M Y', strtotime((string) $combo['updated_at']))); ?></strong></div>
                </div>
                <div class="modal-actions"><a class="modal-button" href="#edit-combo-<?= (int) $combo['id']; ?>">Edit Combo</a></div>
            </div>
        </div>
        <div id="edit-combo-<?= (int) $combo['id']; ?>" class="modal-overlay">
            <form class="modal-box wide-modal course-modal ins-modal" method="post">
                <input type="hidden" name="csrf_token" value="<?= h(csrf_token()); ?>">
                <input type="hidden" name="combo_id" value="<?= (int) $combo['id']; ?>">
                <div class="modal-head"><h2>Edit Combo</h2><a class="modal-close" href="#" aria-label="Close">x</a></div>
                <?php $modalCombo = $combo; include __DIR__ . '/includes/combo_form.php'; ?>
                <div class="modal-actions"><button type="submit">Update Combo</button></div>
            </form>
        </div>
    <?php endforeach; ?>
<?php include __DIR__ . '/includes/footer.php'; ?>
