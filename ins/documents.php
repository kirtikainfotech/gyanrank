<?php
require_once __DIR__ . '/includes/functions.php';
$user = instructor_user();
ensure_instructor_erp_tables();
$instructorId = (int) $user['id'];
$courses = instructor_courses($instructorId);
$exams = instructor_exams($instructorId);
$examCategories = instructor_exam_categories($instructorId);
$courseContents = instructor_course_contents($instructorId);
$chapterRows = array_values(array_filter(
    $courseContents,
    fn($item) => !in_array((string) ($item['content_type'] ?? ''), ['quiz', 'assignment'], true)
));
$chaptersByCourse = [];
$courseTitleById = [];
foreach ($chapterRows as $chapter) {
    $cid = (int) ($chapter['course_id'] ?? 0);
    $chaptersByCourse[$cid][] = $chapter;
}
foreach ($courses as $courseRow) {
    $courseTitleById[(int) $courseRow['id']] = (string) ($courseRow['title'] ?? 'Course');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['csrf_token'] ?? null)) {
        $_SESSION['ins_error'] = 'Security token expired.';
        redirect('ins/documents');
    }

    try {
        $resourceId = (int) ($_POST['resource_id'] ?? 0);
        $documentPurpose = ($_POST['document_purpose'] ?? 'course') === 'exam' ? 'exam' : 'course';
        $courseId = (int) ($_POST['resource_course_id'] ?? 0);
        $examId = (int) ($_POST['resource_exam_id'] ?? 0);
        $examCategoryId = 0;
        if ($documentPurpose === 'exam') {
            $examCategoryId = (int) ($_POST['resource_exam_category_id'] ?? 0);
            if ($examId > 0) {
                $stmt = db()->prepare('SELECT course_id, exam_category_id FROM instructor_exams WHERE id = ? AND instructor_id = ? LIMIT 1');
                $stmt->bind_param('ii', $examId, $instructorId);
                $stmt->execute();
                $examRow = $stmt->get_result()->fetch_assoc();
                if ($examRow) {
                    $courseId = (int) ($examRow['course_id'] ?? 0);
                    $examCategoryId = (int) ($examRow['exam_category_id'] ?? $examCategoryId);
                }
            }
            if ($courseId <= 0) {
                $fallbackCourse = $courses[0]['id'] ?? 0;
                $courseId = (int) $fallbackCourse;
            }
        }
        $examId = $documentPurpose === 'exam' && $examId > 0 ? $examId : null;
        $examCategoryId = $documentPurpose === 'exam' && $examCategoryId > 0 ? $examCategoryId : 0;
        $resourceTitle = substr(trim((string) ($_POST['resource_title'] ?? '')), 0, 180);
        $chapterId = (int) ($_POST['resource_chapter_id'] ?? 0);
        $chapterId = $documentPurpose === 'course' && $chapterId > 0 ? $chapterId : null;
        $chapterBind = $chapterId ?: 0;
        $examBind = $examId ?: 0;
        $price = max(0, min(999999, (float) ($_POST['resource_price'] ?? 0)));
        $sortOrder = max(1, min(9999, (int) ($_POST['resource_sort_order'] ?? 1)));
        $resourceStatus = in_array($_POST['resource_status'] ?? '', ['draft', 'published'], true) ? (string) $_POST['resource_status'] : 'published';

        if ($courseId <= 0 || $resourceTitle === '') {
            throw new RuntimeException('Course/category and PDF title required.');
        }

        $stmt = db()->prepare('SELECT id FROM instructor_courses WHERE id = ? AND instructor_id = ? LIMIT 1');
        $stmt->bind_param('ii', $courseId, $instructorId);
        $stmt->execute();
        if (!$stmt->get_result()->fetch_assoc()) {
            throw new RuntimeException('Invalid course selected.');
        }
        if ($chapterId !== null) {
            $stmt = db()->prepare('SELECT id FROM instructor_course_contents WHERE id = ? AND instructor_id = ? AND course_id = ? LIMIT 1');
            $stmt->bind_param('iii', $chapterId, $instructorId, $courseId);
            $stmt->execute();
            if (!$stmt->get_result()->fetch_assoc()) {
                throw new RuntimeException('Invalid chapter selected for this course.');
            }
        }
        if ($examId !== null) {
            $stmt = db()->prepare('SELECT id FROM instructor_exams WHERE id = ? AND instructor_id = ? LIMIT 1');
            $stmt->bind_param('ii', $examId, $instructorId);
            $stmt->execute();
            if (!$stmt->get_result()->fetch_assoc()) {
                throw new RuntimeException('Invalid exam/mock test selected.');
            }
        }

        $uploadedPath = save_course_content_file('resource_pdf');
        if ($uploadedPath !== '' && strtolower(pathinfo($uploadedPath, PATHINFO_EXTENSION)) !== 'pdf') {
            throw new RuntimeException('Only PDF file allowed here.');
        }
        $thumbnailPath = save_resource_thumbnail_file('resource_thumbnail');

        if ($resourceId > 0) {
            if ($uploadedPath !== '' && $thumbnailPath !== '') {
                $stmt = db()->prepare('UPDATE instructor_course_resources SET course_id = ?, chapter_id = NULLIF(?, 0), document_purpose = ?, exam_category_id = NULLIF(?, 0), exam_id = NULLIF(?, 0), resource_title = ?, file_path = ?, thumbnail_path = ?, price = ?, sort_order = ?, status = ? WHERE id = ? AND instructor_id = ?');
                $stmt->bind_param('iisiisssdisii', $courseId, $chapterBind, $documentPurpose, $examCategoryId, $examBind, $resourceTitle, $uploadedPath, $thumbnailPath, $price, $sortOrder, $resourceStatus, $resourceId, $instructorId);
            } elseif ($uploadedPath !== '') {
                $stmt = db()->prepare('UPDATE instructor_course_resources SET course_id = ?, chapter_id = NULLIF(?, 0), document_purpose = ?, exam_category_id = NULLIF(?, 0), exam_id = NULLIF(?, 0), resource_title = ?, file_path = ?, price = ?, sort_order = ?, status = ? WHERE id = ? AND instructor_id = ?');
                $stmt->bind_param('iisiissdisii', $courseId, $chapterBind, $documentPurpose, $examCategoryId, $examBind, $resourceTitle, $uploadedPath, $price, $sortOrder, $resourceStatus, $resourceId, $instructorId);
            } elseif ($thumbnailPath !== '') {
                $stmt = db()->prepare('UPDATE instructor_course_resources SET course_id = ?, chapter_id = NULLIF(?, 0), document_purpose = ?, exam_category_id = NULLIF(?, 0), exam_id = NULLIF(?, 0), resource_title = ?, thumbnail_path = ?, price = ?, sort_order = ?, status = ? WHERE id = ? AND instructor_id = ?');
                $stmt->bind_param('iisiissdisii', $courseId, $chapterBind, $documentPurpose, $examCategoryId, $examBind, $resourceTitle, $thumbnailPath, $price, $sortOrder, $resourceStatus, $resourceId, $instructorId);
            } else {
                $stmt = db()->prepare('UPDATE instructor_course_resources SET course_id = ?, chapter_id = NULLIF(?, 0), document_purpose = ?, exam_category_id = NULLIF(?, 0), exam_id = NULLIF(?, 0), resource_title = ?, price = ?, sort_order = ?, status = ? WHERE id = ? AND instructor_id = ?');
                $stmt->bind_param('iisiisdisii', $courseId, $chapterBind, $documentPurpose, $examCategoryId, $examBind, $resourceTitle, $price, $sortOrder, $resourceStatus, $resourceId, $instructorId);
            }
            $stmt->execute();
            $_SESSION['ins_success'] = 'Document updated.';
        } else {
            if ($uploadedPath === '') {
                throw new RuntimeException('Please upload PDF file.');
            }
            $stmt = db()->prepare('INSERT INTO instructor_course_resources (instructor_id, course_id, chapter_id, document_purpose, exam_category_id, exam_id, resource_title, resource_type, file_path, thumbnail_path, price, sort_order, status) VALUES (?, ?, NULLIF(?, 0), ?, NULLIF(?, 0), NULLIF(?, 0), ?, "pdf", ?, NULLIF(?, ""), ?, ?, ?)');
            $stmt->bind_param('iiisiisssdis', $instructorId, $courseId, $chapterBind, $documentPurpose, $examCategoryId, $examBind, $resourceTitle, $uploadedPath, $thumbnailPath, $price, $sortOrder, $resourceStatus);
            $stmt->execute();
            $_SESSION['ins_success'] = 'Document added.';
        }
    } catch (Throwable $e) {
        $_SESSION['ins_error'] = $e->getMessage();
    }
    redirect('ins/documents');
}

$courseResources = instructor_course_resources($instructorId);
$published = count(array_filter($courseResources, fn($item) => $item['status'] === 'published'));
$drafts = count($courseResources) - $published;
$message = $_SESSION['ins_success'] ?? '';
$error = $_SESSION['ins_error'] ?? '';
unset($_SESSION['ins_success'], $_SESSION['ins_error']);
$pageTitle = 'Documents';
$pageSubtitle = 'Manage course-wise PDF resources and study material.';
$activePage = 'documents';
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
                            <span class="badge bg-white-1 text-fixed-white mb-2">Document Library</span>
                            <h4 class="fw-semibold mb-1 text-fixed-white">Course & Exam PDFs</h4>
                            <p class="mb-0 op-8">Course learning aur exam preparation PDFs ko clean register me manage karein.</p>
                        </div>
                        <div class="btn-list">
                            <a class="btn btn-light btn-wave" href="#add-resource">Add Document</a>
                            <a class="btn btn-outline-light btn-wave" href="<?= h(app_url('ins/chapters')); ?>">Chapter PDFs</a>
                        </div>
                    </div>
                </div>
            </div>

            <?php
            $docMetrics = [
                ['label' => 'Total', 'value' => count($courseResources), 'icon' => 'bx bx-file', 'tone' => 'primary'],
                ['label' => 'Published', 'value' => $published, 'icon' => 'bx bx-check-circle', 'tone' => 'success'],
                ['label' => 'Draft', 'value' => $drafts, 'icon' => 'bx bx-edit', 'tone' => 'warning'],
                ['label' => 'Courses', 'value' => count($courses), 'icon' => 'bx bx-book-open', 'tone' => 'info'],
            ];
            ?>
            <?php foreach ($docMetrics as $metric): ?>
                <div class="col-6 col-md-3">
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
                        <div><div class="card-title mb-0">PDF Manager</div><p class="mb-0 text-muted fs-12">Documents by course, exam preparation type, price and status.</p></div>
                        <a class="btn btn-sm btn-primary btn-wave" href="#add-resource">Add Document</a>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-sm table-hover align-middle mb-0 gr-register-table">
                                <colgroup><col style="width: 8%;"><col style="width: 28%;"><col style="width: 27%;"><col style="width: 10%;"><col style="width: 10%;"><col style="width: 17%;"></colgroup>
                                <thead><tr><th>Thumb</th><th>Document</th><th>Linked With</th><th>Price</th><th>Status</th><th class="text-end">Action</th></tr></thead>
                                <tbody>
                                    <?php if (!$courseResources): ?><tr><td colspan="6" class="text-muted py-4">No document added.</td></tr><?php endif; ?>
                                    <?php foreach ($courseResources as $resource): ?>
                                        <?php $resourceTone = $resource['status'] === 'published' ? 'success' : 'warning'; ?>
                                        <tr>
                                            <td><?php if (!empty($resource['thumbnail_path'])): ?><img class="rounded object-fit-cover gr-table-thumb" src="<?= h(app_url((string) $resource['thumbnail_path'])); ?>" alt="<?= h($resource['resource_title']); ?>"><?php else: ?><span class="avatar avatar-sm bg-primary-transparent text-primary"><i class="bx bx-file"></i></span><?php endif; ?></td>
                                            <td><span class="fw-semibold text-truncate gr-cell-title"><?= h($resource['resource_title']); ?></span><span class="text-muted fs-12"><?= h(strtoupper($resource['resource_type'])); ?> - <?= h(($resource['document_purpose'] ?? 'course') === 'exam' ? 'Exam prep' : 'Course PDF'); ?></span></td>
                                            <td><span class="fw-semibold text-truncate gr-cell-title"><?= h(($resource['document_purpose'] ?? 'course') === 'exam' ? ($resource['exam_category_name'] ?: 'Exam category') : $resource['course_title']); ?></span><span class="text-muted fs-12 text-truncate gr-cell-subtitle"><?= h(($resource['document_purpose'] ?? 'course') === 'exam' ? ($resource['exam_title'] ?: 'General exam prep document') : 'Course document'); ?></span></td>
                                            <td>Rs <?= h(number_format((float) ($resource['price'] ?? 0), 2)); ?></td>
                                            <td><span class="badge bg-<?= h($resourceTone); ?>-transparent text-<?= h($resourceTone); ?>"><?= h(ucfirst($resource['status'])); ?></span></td>
                                            <td class="text-end"><div class="btn-list justify-content-end"><a class="btn btn-sm btn-primary-light btn-wave" href="#view-resource-<?= (int) $resource['id']; ?>">View</a><a class="btn btn-sm btn-light btn-wave" href="#edit-resource-<?= (int) $resource['id']; ?>">Edit</a><a class="btn btn-sm btn-light btn-wave" href="<?= h(app_url((string) $resource['file_path'])); ?>" target="_blank" rel="noopener">Open</a></div></td>
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
                <span>Document Library</span>
                <h2>Course & Exam PDFs</h2>
                <p>Course learning aur exam preparation PDFs ko alag type ke saath manage karein.</p>
            </div>
            <div class="course-console-actions">
                <a class="modal-button ghost" href="<?= h(app_url('ins/chapters')); ?>">Chapter PDFs</a>
                <a class="modal-button" href="#add-resource">Add Document</a>
            </div>
        </section>

        <div class="course-summary-strip document-summary-strip">
            <article><span>Total</span><strong><?= h((string) count($courseResources)); ?></strong><small>Documents</small></article>
            <article><span>Published</span><strong><?= h((string) $published); ?></strong><small>Visible</small></article>
            <article><span>Draft</span><strong><?= h((string) $drafts); ?></strong><small>Hidden</small></article>
            <article><span>Courses</span><strong><?= h((string) count($courses)); ?></strong><small>Linked</small></article>
        </div>

        <section class="settings-detail-card ins-card">
            <div class="detail-head compact-head">
                <div><span>PDF Manager</span><h2><?= count($courseResources); ?> documents</h2><p>Document type ke hisaab se course ya exam preparation flow me dikhengi.</p></div>
                <a class="modal-button" href="#add-resource">Add Document</a>
            </div>
            <table class="role-access-table smart-table document-library-table compact-manager-table">
                <thead><tr><th>Thumb</th><th>Document</th><th>Linked With</th><th>Price</th><th>Status</th><th>Action</th></tr></thead>
                <tbody>
                    <?php if (!$courseResources): ?><tr><td colspan="6">No document added.</td></tr><?php endif; ?>
                    <?php foreach ($courseResources as $resource): ?>
                        <tr>
                            <td class="course-thumb-cell"><?php if (!empty($resource['thumbnail_path'])): ?><img class="course-table-thumb-img" src="<?= h(app_url((string) $resource['thumbnail_path'])); ?>" alt="<?= h($resource['resource_title']); ?>"><?php else: ?><span class="course-thumb-empty">PDF</span><?php endif; ?></td>
                            <td><strong><?= h($resource['resource_title']); ?></strong><small><?= h(strtoupper($resource['resource_type'])); ?> - <?= h(($resource['document_purpose'] ?? 'course') === 'exam' ? 'Exam prep' : 'Course PDF'); ?></small></td>
                            <td><strong><?= h(($resource['document_purpose'] ?? 'course') === 'exam' ? ($resource['exam_category_name'] ?: 'Exam category') : $resource['course_title']); ?></strong><small><?= h(($resource['document_purpose'] ?? 'course') === 'exam' ? ($resource['exam_title'] ?: 'General exam prep document') : 'Course document'); ?></small></td>
                            <td><strong>₹<?= h(number_format((float) ($resource['price'] ?? 0), 2)); ?></strong></td>
                            <td><?= h(ucfirst($resource['status'])); ?></td>
                            <td>
                                <div class="mini-action-stack">
                                    <a class="table-edit-icon" href="#view-resource-<?= (int) $resource['id']; ?>">View</a>
                                    <a class="table-edit-icon" href="#edit-resource-<?= (int) $resource['id']; ?>">Edit</a>
                                    <a class="table-edit-icon" href="<?= h(app_url((string) $resource['file_path'])); ?>" target="_blank" rel="noopener">Open</a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </section>
        <?php endif; ?>
    </section>

    <div id="add-resource" class="modal-overlay">
            <form class="modal-box wide-modal course-modal ins-modal" data-document-resource-form method="post" enctype="multipart/form-data">
                <input type="hidden" name="csrf_token" value="<?= h(csrf_token()); ?>">
            <div class="modal-head"><h2>Add Document</h2><a class="modal-close" href="#" aria-label="Close">x</a></div>
            <?php $modalResource = null; include __DIR__ . '/includes/course_resource_form.php'; ?>
            <div class="modal-actions"><button type="submit">Save Document</button></div>
        </form>
    </div>

    <?php foreach ($courseResources as $resource): ?>
        <div id="view-resource-<?= (int) $resource['id']; ?>" class="modal-overlay">
            <div class="modal-box wide-modal course-modal ins-modal detail-view-modal">
                <div class="modal-head"><h2>Document Details</h2><a class="modal-close" href="#" aria-label="Close">x</a></div>
                <div class="detail-view-grid">
                    <div class="detail-view-media">
                        <?php if (!empty($resource['thumbnail_path'])): ?>
                            <img src="<?= h(app_url((string) $resource['thumbnail_path'])); ?>" alt="<?= h($resource['resource_title']); ?>">
                        <?php else: ?>
                            <span>PDF</span>
                        <?php endif; ?>
                    </div>
                    <div>
                        <span class="detail-kicker"><?= h(($resource['document_purpose'] ?? 'course') === 'exam' ? 'Exam Preparation' : 'Course Document'); ?></span>
                        <h3><?= h($resource['resource_title']); ?></h3>
                        <p><?= h(($resource['document_purpose'] ?? 'course') === 'exam' ? ($resource['exam_title'] ?: 'General exam preparation material') : ($resource['chapter_title'] ?: 'Course level PDF material')); ?></p>
                        <div class="detail-chip-row">
                            <span><?= h(strtoupper($resource['resource_type'])); ?></span>
                            <span><?= h(ucfirst($resource['status'])); ?></span>
                            <span>Order <?= h((string) $resource['sort_order']); ?></span>
                        </div>
                    </div>
                </div>
                <div class="detail-info-table">
                    <div><span>Course</span><strong><?= h($resource['course_title'] ?: 'Not set'); ?></strong></div>
                    <div><span>Exam Category</span><strong><?= h($resource['exam_category_name'] ?: 'Not linked'); ?></strong></div>
                    <div><span>Chapter</span><strong><?= h($resource['chapter_title'] ?: 'Course level'); ?></strong></div>
                    <div><span>Mock Test</span><strong><?= h($resource['exam_title'] ?: 'Not linked'); ?></strong></div>
                    <div><span>Price</span><strong>Rs <?= h(number_format((float) ($resource['price'] ?? 0), 2)); ?></strong></div>
                    <div><span>File</span><strong><?= h(basename((string) $resource['file_path'])); ?></strong></div>
                </div>
                <div class="modal-actions">
                    <a class="modal-button ghost" href="#edit-resource-<?= (int) $resource['id']; ?>">Edit Document</a>
                    <a class="modal-button" href="<?= h(app_url((string) $resource['file_path'])); ?>" target="_blank" rel="noopener">Open PDF</a>
                </div>
            </div>
        </div>
        <div id="edit-resource-<?= (int) $resource['id']; ?>" class="modal-overlay">
            <form class="modal-box wide-modal course-modal ins-modal" data-document-resource-form method="post" enctype="multipart/form-data">
                <input type="hidden" name="csrf_token" value="<?= h(csrf_token()); ?>">
                <input type="hidden" name="resource_id" value="<?= (int) $resource['id']; ?>">
                <div class="modal-head"><h2>Edit Document</h2><a class="modal-close" href="#" aria-label="Close">x</a></div>
                <?php $modalResource = $resource; include __DIR__ . '/includes/course_resource_form.php'; ?>
                <div class="modal-actions"><button type="submit">Update Document</button></div>
            </form>
        </div>
    <?php endforeach; ?>
<script>
document.querySelectorAll('[data-document-resource-form]').forEach((form) => {
    const purposeSelect = form.querySelector('[data-document-purpose]');
    const courseSelect = form.querySelector('select[name="resource_course_id"]');
    const chapterSelect = form.querySelector('select[name="resource_chapter_id"]');
    const examCategorySelect = form.querySelector('[data-exam-doc-category]');
    const examSelect = form.querySelector('[data-exam-doc-exam]');
    const courseFields = form.querySelectorAll('[data-course-doc-field]');
    const examFields = form.querySelectorAll('[data-exam-doc-field]');
    if (!courseSelect || !chapterSelect) return;

    const resetChapters = () => {
        const selectedCourse = Number(courseSelect.value || 0);
        const groups = chapterSelect.querySelectorAll('optgroup');
        groups.forEach((group) => {
            const courseId = Number(group.getAttribute('data-course-id') || 0);
            group.style.display = (!selectedCourse || courseId === selectedCourse) ? '' : 'none';
        });

        if (selectedCourse > 0) {
            const selectedChapterOption = chapterSelect.querySelector(`option[value="${chapterSelect.value}"]`);
            if (!selectedChapterOption || Number(selectedChapterOption.getAttribute('data-course-id') || 0) !== selectedCourse) {
                chapterSelect.value = '';
            }
            return;
        }

        chapterSelect.value = '';
    };
    const syncPurpose = () => {
        const isExam = purposeSelect && purposeSelect.value === 'exam';
        courseFields.forEach((field) => field.style.display = isExam ? 'none' : '');
        examFields.forEach((field) => field.style.display = isExam ? '' : 'none');
        chapterSelect.disabled = isExam;
        if (isExam) {
            chapterSelect.value = '';
            const selectedExam = examSelect?.selectedOptions[0];
            const examCourse = selectedExam ? selectedExam.getAttribute('data-course-id') : '';
            if (examCourse && examCourse !== '0') courseSelect.value = examCourse;
        }
        resetChapters();
    };
    const syncExamOptions = () => {
        if (!examSelect || !examCategorySelect) return;
        const selectedCategory = Number(examCategorySelect.value || 0);
        examSelect.disabled = selectedCategory <= 0;
        examSelect.querySelectorAll('option[data-category-id]').forEach((option) => {
            const optionCategory = Number(option.getAttribute('data-category-id') || 0);
            option.hidden = selectedCategory <= 0 || optionCategory !== selectedCategory;
            if (option.hidden && option.selected) examSelect.value = '';
        });
        const selectedExam = examSelect.selectedOptions[0];
        const examCourse = selectedExam ? selectedExam.getAttribute('data-course-id') : '';
        if (examCourse && examCourse !== '0') courseSelect.value = examCourse;
        resetChapters();
    };

    courseSelect.addEventListener('change', resetChapters);
    purposeSelect?.addEventListener('change', syncPurpose);
    examCategorySelect?.addEventListener('change', syncExamOptions);
    examSelect?.addEventListener('change', () => {
        const selected = examSelect.selectedOptions[0];
        const examCourse = selected ? selected.getAttribute('data-course-id') : '';
        const examCategory = selected ? selected.getAttribute('data-category-id') : '';
        if (examCategory && examCategory !== '0' && examCategorySelect) {
            examCategorySelect.value = examCategory;
        }
        if (examCourse && examCourse !== '0') {
            courseSelect.value = examCourse;
        }
        syncExamOptions();
    });
    syncExamOptions();
    syncPurpose();
    resetChapters();
});
</script>
<?php include __DIR__ . '/includes/footer.php'; ?>

