<?php
require_once __DIR__ . '/includes/functions.php';
$user = instructor_user();
ensure_instructor_erp_tables();
$instructorId = (int) $user['id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['csrf_token'] ?? null)) {
        $_SESSION['ins_error'] = 'Security token expired.';
        redirect('ins/courses');
    }

    $courseId = (int) ($_POST['course_id'] ?? 0);
    $categoryId = (int) ($_POST['category_id'] ?? 0);
    $subcategoryId = (int) ($_POST['subcategory_id'] ?? 0);
    $title = substr(trim((string) ($_POST['title'] ?? '')), 0, 180);
    $price = max(0, (float) ($_POST['price'] ?? 0));
    $originalPrice = max(0, (float) ($_POST['original_price'] ?? 0));
    if ($originalPrice <= $price && $price > 0) {
        $originalPrice = round($price * 1.25, 2);
    }
    $priceUnit = substr(trim((string) ($_POST['price_unit'] ?? 'course')), 0, 40);
    $mode = in_array($_POST['learning_mode'] ?? '', ['online', 'offline', 'hybrid', 'recorded'], true) ? (string) $_POST['learning_mode'] : 'online';
    $level = in_array($_POST['course_level'] ?? '', ['beginner', 'intermediate', 'advanced', 'all'], true) ? (string) $_POST['course_level'] : 'beginner';
    $language = in_array($_POST['course_language'] ?? '', ['hindi', 'english'], true) ? (string) $_POST['course_language'] : 'hindi';
    $duration = substr(trim((string) ($_POST['duration'] ?? '')), 0, 80);
    $city = substr(trim((string) ($_POST['city'] ?? '')), 0, 80);
    $locality = substr(trim((string) ($_POST['locality'] ?? '')), 0, 120);
    $featured = isset($_POST['featured']) ? 1 : 0;
    $isFree = isset($_POST['is_free']) && $price <= 0 ? 1 : 0;
    $status = in_array($_POST['status'] ?? '', ['draft', 'published', 'paused'], true) ? (string) $_POST['status'] : 'draft';
    $description = substr(trim((string) ($_POST['short_description'] ?? '')), 0, 1200);
    $address = substr(trim((string) ($_POST['address'] ?? '')), 0, 600);
    $instructorSettings = instructor_setting_row($instructorId);
    $call = substr(trim((string) ($instructorSettings['contact_number'] ?? '')), 0, 30);
    $whatsapp = substr(trim((string) ($instructorSettings['whatsapp_number'] ?? '')), 0, 30);

    if ($title === '') {
        $_SESSION['ins_error'] = 'Course title required.';
        redirect('ins/courses');
    }

    if (!valid_course_category_pair($categoryId, $subcategoryId)) {
        $_SESSION['ins_error'] = 'Please select valid category and subcategory.';
        redirect('ins/courses');
    }

    $stmt = db()->prepare('SELECT id FROM instructor_courses WHERE instructor_id = ? AND LOWER(TRIM(title)) = LOWER(TRIM(?)) AND id <> ? LIMIT 1');
    $stmt->bind_param('isi', $instructorId, $title, $courseId);
    $stmt->execute();
    if ($stmt->get_result()->fetch_assoc()) {
        $_SESSION['ins_error'] = 'This course already exists.';
        redirect('ins/courses');
    }

    $categoryName = '';
    $stmt = db()->prepare('SELECT name FROM course_categories WHERE id = ? LIMIT 1');
    $stmt->bind_param('i', $categoryId);
    $stmt->execute();
    $categoryName = (string) ($stmt->get_result()->fetch_assoc()['name'] ?? '');

    try {
        $uploadedThumbnail = save_course_thumbnail_file('course_thumbnail');
    } catch (RuntimeException $exception) {
        $_SESSION['ins_error'] = $exception->getMessage();
        redirect('ins/courses');
    }

    if ($courseId > 0) {
        $stmt = db()->prepare('UPDATE instructor_courses SET category_id = ?, subcategory_id = ?, title = ?, category = ?, price = ?, original_price = ?, price_unit = ?, learning_mode = ?, course_level = ?, course_language = ?, duration = ?, city = ?, locality = ?, featured = ?, is_free = ?, status = ?, short_description = ?, address = ?, call_number = ?, whatsapp_number = ? WHERE id = ? AND instructor_id = ?');
        $stmt->bind_param('iissddsssssssiisssssii', $categoryId, $subcategoryId, $title, $categoryName, $price, $originalPrice, $priceUnit, $mode, $level, $language, $duration, $city, $locality, $featured, $isFree, $status, $description, $address, $call, $whatsapp, $courseId, $instructorId);
        $stmt->execute();
        if ($uploadedThumbnail !== '') {
            $stmt = db()->prepare('UPDATE instructor_courses SET thumbnail_path = ? WHERE id = ? AND instructor_id = ?');
            $stmt->bind_param('sii', $uploadedThumbnail, $courseId, $instructorId);
            $stmt->execute();
        }
        $_SESSION['ins_success'] = 'Course updated successfully.';
    } else {
        $stmt = db()->prepare('INSERT INTO instructor_courses (instructor_id, category_id, subcategory_id, title, category, price, original_price, price_unit, learning_mode, course_level, course_language, duration, city, locality, featured, is_free, status, short_description, address, call_number, whatsapp_number) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
        $stmt->bind_param('iiissddsssssssiisssss', $instructorId, $categoryId, $subcategoryId, $title, $categoryName, $price, $originalPrice, $priceUnit, $mode, $level, $language, $duration, $city, $locality, $featured, $isFree, $status, $description, $address, $call, $whatsapp);
        $stmt->execute();
        $courseId = (int) db()->insert_id;
        if ($uploadedThumbnail !== '') {
            $stmt = db()->prepare('UPDATE instructor_courses SET thumbnail_path = ? WHERE id = ? AND instructor_id = ?');
            $stmt->bind_param('sii', $uploadedThumbnail, $courseId, $instructorId);
            $stmt->execute();
        } else {
            ensure_course_thumbnail([
                'id' => $courseId,
                'title' => $title,
                'category' => $categoryName,
                'category_name' => $categoryName,
                'course_level' => $level,
            ]);
        }
        $_SESSION['ins_success'] = 'Course added successfully.';
    }

    redirect('ins/courses');
}

$courses = instructor_courses($instructorId);
$courseCategories = course_parent_categories(true);
$courseSubcategories = course_sub_categories(true);
$published = count(array_filter($courses, fn($course) => $course['status'] === 'published'));
$drafts = count(array_filter($courses, fn($course) => $course['status'] === 'draft'));
$featured = count(array_filter($courses, fn($course) => (int) $course['featured'] === 1));
$freeCourses = count(array_filter($courses, fn($course) => (int) ($course['is_free'] ?? 0) === 1));
$message = $_SESSION['ins_success'] ?? '';
$error = $_SESSION['ins_error'] ?? '';
unset($_SESSION['ins_success'], $_SESSION['ins_error']);
$pageTitle = 'Courses';
$pageSubtitle = 'Create and manage course records.';
$activePage = 'courses';
?>
<?php include __DIR__ . '/includes/header.php'; ?>
<?php include __DIR__ . '/includes/sidebar.php'; ?>
<main class="main instructor-courses-page">
    <?php include __DIR__ . '/includes/topbar.php'; ?>
    <section class="content instructor-content">
        <?php if ($message): ?><div class="notice"><?= h($message); ?></div><?php endif; ?>
        <?php if ($error): ?><div class="notice danger"><?= h($error); ?></div><?php endif; ?>

        <div class="row g-3">
            <div class="col-12">
                <div class="card custom-card bg-primary-gradient border-0 text-fixed-white">
                    <div class="card-body d-flex flex-wrap align-items-center justify-content-between gap-3">
                        <div>
                            <span class="badge bg-white-1 text-fixed-white mb-2">Course Console</span>
                            <h4 class="fw-semibold mb-1 text-fixed-white">Courses, pricing and publishing</h4>
                            <p class="mb-0 op-8">Course create karein, category-price set karein, phir chapters aur documents connect karein.</p>
                        </div>
                        <div class="btn-list">
                            <a class="btn btn-light btn-wave" href="#add-course">New Course</a>
                            <a class="btn btn-outline-light btn-wave" href="<?= h(app_url('ins/chapters')); ?>">Chapters</a>
                            <a class="btn btn-outline-light btn-wave" href="<?= h(app_url('ins/documents')); ?>">Documents</a>
                        </div>
                    </div>
                </div>
            </div>

            <?php
            $courseMetrics = [
                ['label' => 'Total', 'value' => count($courses), 'icon' => 'bx bx-book-open', 'tone' => 'primary'],
                ['label' => 'Published', 'value' => $published, 'icon' => 'bx bx-check-circle', 'tone' => 'success'],
                ['label' => 'Draft', 'value' => $drafts, 'icon' => 'bx bx-edit', 'tone' => 'warning'],
                ['label' => 'Featured', 'value' => $featured, 'icon' => 'bx bx-star', 'tone' => 'info'],
                ['label' => 'Free', 'value' => $freeCourses, 'icon' => 'bx bx-gift', 'tone' => 'secondary'],
            ];
            ?>
            <?php foreach ($courseMetrics as $metric): ?>
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
                            <div class="card-title mb-0">Course Inventory</div>
                            <p class="mb-0 text-muted fs-12">All courses with price, mode, language and publishing status.</p>
                        </div>
                        <a class="btn btn-sm btn-primary btn-wave" href="#add-course">New Course</a>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-sm table-hover align-middle mb-0 gr-register-table gr-course-inventory-table">
                                <colgroup><col style="width: 6%;"><col style="width: 32%;"><col style="width: 18%;"><col style="width: 9%;"><col style="width: 12%;"><col style="width: 10%;"><col style="width: 13%;"></colgroup>
                                <thead><tr><th>Thumb</th><th>Course</th><th>Category</th><th>Price</th><th>Mode</th><th>Status</th><th class="text-end">Action</th></tr></thead>
                                <tbody>
                                    <?php if (!$courses): ?><tr><td colspan="7" class="text-muted py-4">No course found.</td></tr><?php endif; ?>
                                    <?php foreach ($courses as $course): ?>
                                        <?php $courseTone = $course['status'] === 'published' ? 'success' : 'warning'; ?>
                                        <tr>
                                            <td>
                                                <?php if (!empty($course['thumbnail_path'])): ?>
                                                    <img class="rounded object-fit-cover gr-table-thumb" src="<?= h(app_url($course['thumbnail_path'])); ?>" alt="<?= h($course['title']); ?>">
                                                <?php else: ?>
                                                    <span class="avatar avatar-sm bg-light text-muted"><i class="bx bx-image"></i></span>
                                                <?php endif; ?>
                                            </td>
                                            <td><span class="fw-semibold text-truncate gr-cell-title"><?= h($course['title']); ?></span><span class="text-muted fs-12"><?= h($course['duration'] ?: 'Duration not set'); ?></span></td>
                                            <td><span class="text-truncate gr-cell-title"><?= h($course['category_name'] ?: ($course['category'] ?: 'Category not set')); ?></span><span class="text-muted fs-12"><?= h($course['subcategory_name'] ?: 'No subcategory'); ?></span></td>
                                            <td><?= ((float) $course['price'] <= 0 && (int) $course['is_free'] === 1) ? 'Free' : 'Rs ' . h(number_format((float) $course['price'], 0)); ?></td>
                                            <td><?= h(ucfirst((string) ($course['content_mode'] ?? 'recorded'))); ?> <span class="text-muted fs-12">/ <?= h(ucfirst((string) ($course['course_language'] ?? 'hindi'))); ?></span></td>
                                            <td><span class="badge bg-<?= h($courseTone); ?>-transparent text-<?= h($courseTone); ?>"><?= h(ucfirst($course['status'])); ?></span></td>
                                            <td class="text-end">
                                                <div class="gr-table-actions">
                                                    <a class="btn btn-sm btn-icon btn-light btn-wave" href="#view-course-<?= (int) $course['id']; ?>" title="View"><i class="bx bx-list-ul"></i></a>
                                                    <a class="btn btn-sm btn-icon btn-light btn-wave" href="#edit-course-<?= (int) $course['id']; ?>" title="Edit"><i class="bx bx-pencil"></i></a>
                                                    <a class="btn btn-sm btn-icon btn-light btn-wave" href="<?= h(app_url('ins/chapters')); ?>" title="Chapters"><i class="bx bx-book-content"></i></a>
                                                </div>
                                            </td>
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

    <div id="add-course" class="modal-overlay">
        <form class="modal-box wide-modal course-modal ins-modal" method="post" enctype="multipart/form-data">
            <input type="hidden" name="csrf_token" value="<?= h(csrf_token()); ?>">
            <div class="modal-head"><h2>Add New Course</h2><a class="modal-close" href="#" aria-label="Close">x</a></div>
            <?php $modalCourse = null; include __DIR__ . '/includes/course_form.php'; ?>
            <div class="modal-actions"><button type="submit">Save Course</button></div>
        </form>
    </div>

    <?php foreach ($courses as $course): ?>
        <div id="view-course-<?= (int) $course['id']; ?>" class="modal-overlay">
            <div class="modal-box wide-modal course-modal ins-modal detail-view-modal">
                <div class="modal-head"><h2>Course Details</h2><a class="modal-close" href="#" aria-label="Close">x</a></div>
                <div class="detail-view-grid">
                    <div class="detail-view-media">
                        <?php if (!empty($course['thumbnail_path'])): ?>
                            <img src="<?= h(app_url($course['thumbnail_path'])); ?>" alt="<?= h($course['title']); ?>">
                        <?php else: ?>
                            <span>No thumbnail</span>
                        <?php endif; ?>
                    </div>
                    <div>
                        <span class="detail-kicker"><?= h($course['category_name'] ?: ($course['category'] ?: 'Course')); ?></span>
                        <h3><?= h($course['title']); ?></h3>
                        <p><?= h($course['short_description'] ?: 'Description not added yet.'); ?></p>
                        <div class="detail-chip-row">
                            <span><?= h(ucfirst($course['learning_mode'])); ?></span>
                            <span><?= h(ucfirst($course['course_level'])); ?></span>
                            <span><?= h(ucfirst($course['course_language'] ?? 'hindi')); ?></span>
                            <span><?= h(ucfirst($course['status'])); ?></span>
                        </div>
                    </div>
                </div>
                <div class="detail-info-table">
                    <div><span>Subcategory</span><strong><?= h($course['subcategory_name'] ?: 'Not set'); ?></strong></div>
                    <div><span>Price</span><strong><?= ((float) $course['price'] <= 0 && (int) $course['is_free'] === 1) ? 'Free' : 'Rs ' . h(number_format((float) $course['price'], 0)); ?></strong></div>
                    <div><span>Original Price</span><strong><?= h(number_format((float) ($course['original_price'] ?? 0), 0)); ?></strong></div>
                    <div><span>Duration</span><strong><?= h($course['duration'] ?: 'Not set'); ?></strong></div>
                    <div><span>Location</span><strong><?= h(trim(($course['city'] ?? '') . ' ' . ($course['locality'] ?? '')) ?: 'Online'); ?></strong></div>
                    <div><span>Featured</span><strong><?= (int) $course['featured'] === 1 ? 'Yes' : 'No'; ?></strong></div>
                    <div><span>Call</span><strong><?= h($course['call_number'] ?: 'Not set'); ?></strong></div>
                    <div><span>WhatsApp</span><strong><?= h($course['whatsapp_number'] ?: 'Not set'); ?></strong></div>
                </div>
                <?php if (!empty($course['address'])): ?><div class="detail-note"><?= h($course['address']); ?></div><?php endif; ?>
                <div class="modal-actions">
                    <a class="modal-button ghost" href="#edit-course-<?= (int) $course['id']; ?>">Edit Course</a>
                    <a class="modal-button" href="<?= h(app_url('ins/chapters')); ?>">Manage Chapters</a>
                </div>
            </div>
        </div>
        <div id="edit-course-<?= (int) $course['id']; ?>" class="modal-overlay">
            <form class="modal-box wide-modal course-modal ins-modal" method="post" enctype="multipart/form-data">
                <input type="hidden" name="csrf_token" value="<?= h(csrf_token()); ?>">
                <input type="hidden" name="course_id" value="<?= (int) $course['id']; ?>">
                <div class="modal-head"><h2>Edit Course</h2><a class="modal-close" href="#" aria-label="Close">x</a></div>
                <?php $modalCourse = $course; include __DIR__ . '/includes/course_form.php'; ?>
                <div class="modal-actions"><button type="submit">Update Course</button></div>
            </form>
        </div>
    <?php endforeach; ?>

    <script>
        const filterSubcategories = (form) => {
            const category = form.querySelector('[data-category-select]');
            const subcategory = form.querySelector('[data-subcategory-select]');
            if (!category || !subcategory) return;
            const selectedParent = category.value;
            subcategory.querySelectorAll('option[data-parent]').forEach((option) => {
                const visible = !selectedParent || option.dataset.parent === selectedParent;
                option.hidden = !visible;
                if (!visible && option.selected) subcategory.value = '';
            });
        };

        document.querySelectorAll('form').forEach((form) => {
            filterSubcategories(form);
            const category = form.querySelector('[data-category-select]');
            if (category) category.addEventListener('change', () => filterSubcategories(form));

            const price = form.querySelector('input[name="price"]');
            const free = form.querySelector('input[name="is_free"]');
            if (price && free) {
                const syncFree = () => {
                    const paid = parseFloat(price.value || '0') > 0;
                    if (paid) free.checked = false;
                    free.disabled = paid;
                    free.closest('.free-course-toggle')?.classList.toggle('is-disabled', paid);
                };
                price.addEventListener('input', syncFree);
                syncFree();
            }
        });
    </script>
    <style>
        .instructor-courses-page .instructor-content > .row > .col-12:first-child,
        .instructor-courses-page .instructor-content > .row > .col-6 {
            display: none !important;
        }
        .instructor-courses-page .instructor-content {
            padding-top: 0 !important;
        }
        .instructor-courses-page .custom-card {
            border-radius: 4px !important;
            border-top: 3px solid #f68a00 !important;
        }
        .instructor-courses-page .card-header {
            min-height: 58px !important;
            padding: 10px 12px !important;
            background: #fff !important;
        }
        .instructor-courses-page .card-title {
            color: #111827 !important;
            font-size: 18px !important;
            font-weight: 600 !important;
            line-height: 1.2 !important;
        }
        .instructor-courses-page .card-header p {
            margin-top: 3px !important;
            color: #6b7280 !important;
            font-size: 13px !important;
        }
        .instructor-courses-page .card-header .btn {
            min-height: 30px !important;
            padding: 5px 10px !important;
            border-radius: 3px !important;
            background: #06345f !important;
            color: #fff !important;
            font-size: 12px !important;
            font-weight: 700 !important;
        }
        .instructor-courses-page .gr-course-inventory-table thead th {
            height: 34px !important;
            padding: 8px 12px !important;
            background: #fff !important;
            border-bottom: 1px solid #d6e4f0 !important;
            color: #003763 !important;
            font-size: 13px !important;
            font-weight: 700 !important;
            text-transform: none !important;
            letter-spacing: 0 !important;
        }
        .instructor-courses-page .gr-course-inventory-table tbody td {
            height: 39px !important;
            padding: 7px 12px !important;
            border-bottom: 1px solid #edf2f7 !important;
            color: #111827 !important;
            font-size: 13px !important;
            line-height: 1.2 !important;
        }
        .instructor-courses-page .gr-course-inventory-table tbody tr:nth-child(even) td {
            background: #fff !important;
        }
        .instructor-courses-page .gr-course-inventory-table .gr-cell-title,
        .instructor-courses-page .gr-course-inventory-table .fw-semibold {
            font-size: 14px !important;
            font-weight: 600 !important;
        }
        .instructor-courses-page .gr-course-inventory-table .text-muted,
        .instructor-courses-page .gr-course-inventory-table .fs-12 {
            color: #6b7280 !important;
            font-size: 12px !important;
        }
        .instructor-courses-page .gr-table-thumb {
            width: 44px !important;
            height: 26px !important;
            border-radius: 2px !important;
        }
        .instructor-courses-page .gr-table-actions {
            gap: 8px !important;
        }
        .instructor-courses-page .gr-table-actions .btn {
            width: auto !important;
            min-width: 0 !important;
            height: auto !important;
            min-height: 0 !important;
            padding: 0 !important;
            border: 0 !important;
            background: transparent !important;
            color: #1f2937 !important;
            box-shadow: none !important;
            font-size: 15px !important;
        }
        .instructor-courses-page .gr-table-actions .btn:hover {
            color: #f68a00 !important;
        }
        .instructor-courses-page .gr-table-actions .btn:nth-child(3) {
            flex-basis: auto !important;
        }
        .instructor-courses-page .badge {
            min-height: 18px !important;
            padding: 3px 8px !important;
            border-radius: 4px !important;
            font-size: 11px !important;
        }
    </style>
<?php include __DIR__ . '/includes/footer.php'; ?>

