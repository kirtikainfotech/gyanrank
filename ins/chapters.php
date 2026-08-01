<?php
require_once __DIR__ . '/includes/functions.php';
$user = instructor_user();
ensure_instructor_erp_tables();
$instructorId = (int) $user['id'];
$settings = instructor_setting_row($instructorId);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['csrf_token'] ?? null)) {
        $_SESSION['ins_error'] = 'Security token expired.';
        redirect('ins/chapters');
    }

    try {
        $contentId = (int) ($_POST['content_id'] ?? 0);
        $existingContentId = (int) ($_POST['existing_content_id'] ?? 0);
        $courseId = (int) ($_POST['course_id'] ?? 0);
        $contentTitle = substr(trim((string) ($_POST['content_title'] ?? '')), 0, 180);
        $contentType = in_array($_POST['content_type'] ?? '', ['pdf', 'lecture', 'video_upload', 'live', 'youtube', 'vimeo', 'resource'], true) ? (string) $_POST['content_type'] : 'lecture';
        $resourceUrl = substr(trim((string) ($_POST['resource_url'] ?? '')), 0, 255);
        $uploadedPath = save_course_content_file('resource_file');
        if ($uploadedPath !== '') {
            $resourceUrl = $uploadedPath;
        }
        if ($contentType === 'live' && $resourceUrl === '') {
            $resourceUrl = (($settings['live_platform'] ?? 'google_meet') === 'youtube_live')
                ? (string) ($settings['youtube_live_link'] ?? '')
                : (string) ($settings['google_meet_link'] ?? '');
        }
        $durationMinutes = max(0, min(10000, (int) ($_POST['duration_minutes'] ?? 0)));
        $sortOrder = max(1, min(9999, (int) ($_POST['sort_order'] ?? 1)));
        $isPreview = isset($_POST['is_preview']) ? 1 : 0;
        $contentStatus = in_array($_POST['content_status'] ?? '', ['draft', 'published'], true) ? (string) $_POST['content_status'] : 'draft';
        $instructions = substr(trim((string) ($_POST['instructions'] ?? '')), 0, 1200);

        if ($courseId <= 0) {
            throw new RuntimeException('Course required.');
        }

        $stmt = db()->prepare('SELECT id FROM instructor_courses WHERE id = ? AND instructor_id = ? LIMIT 1');
        $stmt->bind_param('ii', $courseId, $instructorId);
        $stmt->execute();
        if (!$stmt->get_result()->fetch_assoc()) {
            throw new RuntimeException('Invalid course selected.');
        }

        if ($existingContentId > 0 && $contentId === 0) {
            $stmt = db()->prepare("
                SELECT content_title, content_type, resource_url, duration_minutes, is_preview, status, instructions
                FROM instructor_course_contents
                WHERE id = ? AND instructor_id = ?
                LIMIT 1
            ");
            $stmt->bind_param('ii', $existingContentId, $instructorId);
            $stmt->execute();
            $oldContent = $stmt->get_result()->fetch_assoc();
            if (!$oldContent || in_array($oldContent['content_type'], ['quiz', 'assignment'], true)) {
                throw new RuntimeException('Invalid old chapter selected.');
            }
            $contentTitle = (string) $oldContent['content_title'];
            $contentType = (string) $oldContent['content_type'];
            $resourceUrl = (string) $oldContent['resource_url'];
            $durationMinutes = (int) $oldContent['duration_minutes'];
            $isPreview = (int) $oldContent['is_preview'];
            $contentStatus = (string) $oldContent['status'];
            $instructions = (string) $oldContent['instructions'];
        }

        if ($contentTitle === '') {
            throw new RuntimeException('Chapter title required.');
        }

        $stmt = db()->prepare('
            SELECT id
            FROM instructor_course_contents
            WHERE instructor_id = ?
              AND course_id = ?
              AND content_type = ?
              AND LOWER(TRIM(content_title)) = LOWER(TRIM(?))
              AND id <> ?
            LIMIT 1
        ');
        $stmt->bind_param('iissi', $instructorId, $courseId, $contentType, $contentTitle, $contentId);
        $stmt->execute();
        if ($stmt->get_result()->fetch_assoc()) {
            throw new RuntimeException('This chapter already exists in selected course.');
        }

        if ($resourceUrl !== '') {
            $stmt = db()->prepare('
                SELECT id
                FROM instructor_course_contents
                WHERE instructor_id = ?
                  AND course_id = ?
                  AND resource_url = ?
                  AND id <> ?
                LIMIT 1
            ');
            $stmt->bind_param('iisi', $instructorId, $courseId, $resourceUrl, $contentId);
            $stmt->execute();
            if ($stmt->get_result()->fetch_assoc()) {
                throw new RuntimeException('This resource link/file is already added in selected course.');
            }
        }

        if ($contentId > 0) {
            $stmt = db()->prepare('UPDATE instructor_course_contents SET course_id = ?, content_title = ?, content_type = ?, resource_url = ?, duration_minutes = ?, sort_order = ?, is_preview = ?, status = ?, instructions = ? WHERE id = ? AND instructor_id = ?');
            $stmt->bind_param('isssiiissii', $courseId, $contentTitle, $contentType, $resourceUrl, $durationMinutes, $sortOrder, $isPreview, $contentStatus, $instructions, $contentId, $instructorId);
            $stmt->execute();
            $_SESSION['ins_success'] = 'Chapter updated.';
        } else {
            $stmt = db()->prepare('INSERT INTO instructor_course_contents (instructor_id, course_id, content_title, content_type, resource_url, duration_minutes, sort_order, is_preview, status, instructions) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
            $stmt->bind_param('iisssiiiss', $instructorId, $courseId, $contentTitle, $contentType, $resourceUrl, $durationMinutes, $sortOrder, $isPreview, $contentStatus, $instructions);
            $stmt->execute();
            $_SESSION['ins_success'] = 'Chapter added.';
        }
    } catch (Throwable $e) {
        $_SESSION['ins_error'] = $e->getMessage();
    }
    redirect('ins/chapters');
}

$courses = instructor_courses($instructorId);
$contents = array_values(array_filter(
    instructor_course_contents($instructorId),
    fn($item) => !in_array($item['content_type'], ['quiz', 'assignment'], true)
));
$published = count(array_filter($contents, fn($item) => $item['status'] === 'published'));
$freeChapters = count(array_filter($contents, fn($item) => (int) $item['is_preview'] === 1));
$message = $_SESSION['ins_success'] ?? '';
$error = $_SESSION['ins_error'] ?? '';
unset($_SESSION['ins_success'], $_SESSION['ins_error']);
$pageTitle = 'Chapters';
$pageSubtitle = 'Add chapters, files, videos, live links and reusable content.';
$activePage = 'chapters';
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
                            <span class="badge bg-white-1 text-fixed-white mb-2">Chapter Studio</span>
                            <h4 class="fw-semibold mb-1 text-fixed-white">Add or reuse chapters</h4>
                            <p class="mb-0 op-8">Course select karein, type choose karein, files/videos/live links add karein. Duplicate content block rahega.</p>
                        </div>
                        <div class="btn-list">
                            <a class="btn btn-light btn-wave" href="#add-content">Add Chapter</a>
                            <a class="btn btn-outline-light btn-wave" href="<?= h(app_url('ins/courses')); ?>">Open Courses</a>
                            <a class="btn btn-outline-light btn-wave" href="<?= h(app_url('ins/documents')); ?>">Documents</a>
                        </div>
                    </div>
                </div>
            </div>

            <?php
            $chapterMetrics = [
                ['label' => 'Total', 'value' => count($contents), 'icon' => 'bx bx-list-ul', 'tone' => 'primary'],
                ['label' => 'Published', 'value' => $published, 'icon' => 'bx bx-check-circle', 'tone' => 'success'],
                ['label' => 'Free Preview', 'value' => $freeChapters, 'icon' => 'bx bx-show', 'tone' => 'warning'],
                ['label' => 'Courses', 'value' => count($courses), 'icon' => 'bx bx-book-open', 'tone' => 'info'],
            ];
            ?>
            <?php foreach ($chapterMetrics as $metric): ?>
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
                        <div><div class="card-title mb-0">Chapter Library</div><p class="mb-0 text-muted fs-12">All chapters, files, videos and links in a compact register.</p></div>
                        <a class="btn btn-sm btn-primary btn-wave" href="#add-content">Add Chapter</a>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-sm table-hover align-middle mb-0 gr-register-table">
                                <colgroup><col style="width: 28%;"><col style="width: 22%;"><col style="width: 12%;"><col style="width: 20%;"><col style="width: 7%;"><col style="width: 11%;"><col style="width: 10%;"></colgroup>
                                <thead><tr><th>Chapter</th><th>Course</th><th>Type</th><th>Resource</th><th>Order</th><th>Status</th><th class="text-end">Action</th></tr></thead>
                                <tbody>
                                    <?php if (!$contents): ?><tr><td colspan="7" class="text-muted py-4">No chapter found.</td></tr><?php endif; ?>
                                    <?php foreach ($contents as $item): ?>
                                        <?php $chapterTone = $item['status'] === 'published' ? 'success' : 'warning'; ?>
                                        <tr>
                                            <td><span class="fw-semibold text-truncate gr-cell-title"><?= h($item['content_title']); ?></span><span class="text-muted fs-12"><?= h($item['duration_minutes'] . ' min'); ?><?= (int) $item['is_preview'] === 1 ? ' - Free preview' : ''; ?></span></td>
                                            <td><span class="text-truncate gr-cell-title"><?= h($item['course_title']); ?></span></td>
                                            <td><span class="badge bg-primary-transparent text-primary"><?= h(str_replace('_', ' ', ucfirst($item['content_type']))); ?></span></td>
                                            <td><span class="text-muted text-truncate gr-cell-title"><?= h($item['resource_url'] ?: 'No resource/link'); ?></span></td>
                                            <td><?= h((string) $item['sort_order']); ?></td>
                                            <td><span class="badge bg-<?= h($chapterTone); ?>-transparent text-<?= h($chapterTone); ?>"><?= h(ucfirst($item['status'])); ?></span></td>
                                            <td class="text-end"><a class="btn btn-sm btn-light btn-wave" href="#edit-content-<?= (int) $item['id']; ?>">Edit</a></td>
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

    <div id="add-content" class="modal-overlay">
        <form class="modal-box wide-modal course-modal ins-modal" method="post" enctype="multipart/form-data">
            <input type="hidden" name="csrf_token" value="<?= h(csrf_token()); ?>">
            <div class="modal-head"><h2>Add Chapter</h2><a class="modal-close" href="#" aria-label="Close">x</a></div>
            <?php $modalContent = null; include __DIR__ . '/includes/content_form.php'; ?>
            <div class="modal-actions"><button type="submit">Save Chapter</button></div>
        </form>
    </div>

    <?php foreach ($contents as $content): ?>
        <div id="edit-content-<?= (int) $content['id']; ?>" class="modal-overlay">
            <form class="modal-box wide-modal course-modal ins-modal" method="post" enctype="multipart/form-data">
                <input type="hidden" name="csrf_token" value="<?= h(csrf_token()); ?>">
                <input type="hidden" name="content_id" value="<?= (int) $content['id']; ?>">
                <div class="modal-head"><h2>Edit Chapter</h2><a class="modal-close" href="#" aria-label="Close">x</a></div>
                <?php $modalContent = $content; include __DIR__ . '/includes/content_form.php'; ?>
                <div class="modal-actions"><button type="submit">Update Chapter</button></div>
            </form>
        </div>
    <?php endforeach; ?>
    <script>
        const syncContentFields = (modal) => {
            if (!modal) return;
            const type = modal.querySelector('[name="content_type"]')?.value || 'lecture';
            const linkTypes = ['live', 'youtube', 'vimeo', 'lecture'];
            const uploadTypes = ['pdf', 'video_upload', 'resource'];
            const durationTypes = ['lecture', 'video_upload', 'live', 'youtube', 'vimeo'];
            modal.querySelectorAll('[data-content-field]').forEach((field) => {
                const key = field.dataset.contentField;
                const show =
                    key === 'instructions' ||
                    (key === 'link' && linkTypes.includes(type)) ||
                    (key === 'upload' && uploadTypes.includes(type)) ||
                    (key === 'duration' && durationTypes.includes(type));
                field.classList.toggle('content-field-hidden', !show);
            });
        };

        document.querySelectorAll('.modal-overlay').forEach((modal) => {
            syncContentFields(modal);
            const type = modal.querySelector('[name="content_type"]');
            if (type) type.addEventListener('change', () => syncContentFields(modal));

            const oldContent = modal.querySelector('[data-existing-content]');
            if (oldContent) {
                oldContent.addEventListener('change', () => {
                    const option = oldContent.selectedOptions[0];
                    if (!option || !option.value) return;
                    const setValue = (name, value) => {
                        const field = modal.querySelector(`[name="${name}"]`);
                        if (field) field.value = value || '';
                    };
                    setValue('content_title', option.dataset.title);
                    setValue('content_type', option.dataset.type);
                    setValue('resource_url', option.dataset.url);
                    setValue('duration_minutes', option.dataset.duration || '0');
                    setValue('sort_order', option.dataset.order || '1');
                    setValue('content_status', option.dataset.status || 'draft');
                    setValue('instructions', option.dataset.instructions);
                    const preview = modal.querySelector('[name="is_preview"]');
                    if (preview) preview.checked = option.dataset.preview === '1';
                    syncContentFields(modal);
                });
            }
        });

    </script>
<?php include __DIR__ . '/includes/footer.php'; ?>

