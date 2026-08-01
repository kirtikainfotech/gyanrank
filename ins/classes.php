<?php
require_once __DIR__ . '/includes/functions.php';

$user = instructor_user();
ensure_instructor_erp_tables();
$instructorId = (int) $user['id'];
$batches = instructor_batches($instructorId);
$settings = instructor_setting_row($instructorId);
$classes = instructor_classes($instructorId, 100);
$defaultGoogleClassMeetingLink = normalize_live_class_link((string) ($settings['google_meet_link'] ?? ''));
$defaultYoutubeClassMeetingLink = normalize_live_class_link((string) ($settings['youtube_live_link'] ?? ''));
$defaultClassPlatform = in_array(($settings['live_platform'] ?? ''), ['youtube_live', 'google_meet'], true)
    ? (string) ($settings['live_platform'] ?? 'google_meet')
    : 'google_meet';

function normalize_live_class_link(string $rawLink): string
{
    $value = trim($rawLink);
    if ($value === '') {
        return '';
    }

    if (preg_match('/^[A-Za-z0-9_-]{11}$/', $value)) {
        return "https://www.youtube.com/watch?v=$value";
    }

    if (preg_match('/^[a-zA-Z0-9]{3}-[a-zA-Z0-9]{4}-[a-zA-Z0-9]{3}$/', $value)) {
        return 'https://meet.google.com/' . strtolower($value);
    }

    $withScheme = $value;
    if (!preg_match('#^https?://#i', $withScheme)) {
        $withScheme = 'https://' . ltrim($withScheme, '/');
    }

    $parsed = @parse_url($withScheme);
    if (!is_array($parsed) || empty($parsed['host'])) {
        return $withScheme;
    }

    $host = strtolower($parsed['host']);
    $path = strtolower($parsed['path'] ?? '');
    $query = [];
    if (!empty($parsed['query'])) {
        parse_str($parsed['query'], $query);
    }

    if (($host === 'youtu.be' || str_ends_with($host, '.youtu.be')) && !empty($path)) {
        $videoId = ltrim($path, '/');
        if (preg_match('/^[A-Za-z0-9_-]{6,20}$/', $videoId)) {
            return 'https://www.youtube.com/watch?v=' . $videoId;
        }
    }

    if ($host === 'youtube.com' || str_ends_with($host, '.youtube.com')) {
        if (!empty($query['v']) && preg_match('/^[A-Za-z0-9_-]{6,20}$/', (string) $query['v'])) {
            return 'https://www.youtube.com/watch?v=' . $query['v'];
        }
        if (str_starts_with($path, '/live/') && preg_match('/^\\/live\\//', $path)) {
            $parts = explode('/', trim($path, '/'));
            if (!empty($parts[1])) {
                return 'https://www.youtube.com/live/' . $parts[1];
            }
        }
    }

    return $withScheme;
}

function live_class_platform_label(string $rawLink): string
{
    $value = strtolower(trim($rawLink));
    if ($value === '') {
        return 'Not set';
    }
    if (str_contains($value, 'meet.google.com')) {
        return 'Google Meet';
    }
    if (str_contains($value, 'youtube') || str_contains($value, 'youtu.be')) {
        return 'YouTube';
    }
    return 'Custom Link';
}

function detect_live_platform_from_link(string $rawLink): string
{
    $value = strtolower(trim($rawLink));
    if ($value === '') {
        return '';
    }
    if (str_contains($value, 'meet.google.com')) {
        return 'google_meet';
    }
    if (str_contains($value, 'youtube') || str_contains($value, 'youtu.be')) {
        return 'youtube_live';
    }
    return 'custom';
}

function is_google_meet_link(string $link): bool
{
    $value = strtolower(trim($link));
    return $value !== '' && str_contains($value, 'meet.google.com');
}

function is_youtube_link(string $link): bool
{
    $value = strtolower(trim($link));
    return $value !== '' && (str_contains($value, 'youtube') || str_contains($value, 'youtu.be'));
}

function normalize_platform_value(?string $value): string
{
    $platform = strtolower(trim((string) $value));
    if (in_array($platform, ['youtube', 'youtube_live', 'youtube live', 'yt'], true)) {
        return 'youtube_live';
    }
    if (in_array($platform, ['google', 'google_meet', 'google meet', 'meet'], true)) {
        return 'google_meet';
    }
    if ($platform === 'custom') {
        return 'custom';
    }
    return '';
}

$classesByBatch = [];
$unassignedClasses = [];
foreach ($classes as $class) {
    $batchId = (int) ($class['batch_id'] ?? 0);
    if ($batchId > 0) {
        if (!isset($classesByBatch[$batchId])) {
            $classesByBatch[$batchId] = [];
        }
        $classesByBatch[$batchId][] = $class;
    } else {
        $unassignedClasses[] = $class;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['csrf_token'] ?? null)) {
        $_SESSION['ins_error'] = 'Security token expired.';
        redirect('ins/classes');
    }

    $formType = (string) ($_POST['form_type'] ?? 'class');

    if ($formType === 'batch') {
        $name = substr(trim((string) ($_POST['batch_name'] ?? '')), 0, 120);
        $course = substr(trim((string) ($_POST['course_title'] ?? '')), 0, 160);
        $teacherName = substr(trim((string) ($_POST['teacher_name'] ?? '')), 0, 120);
        $mode = in_array($_POST['mode'] ?? '', ['online', 'offline', 'hybrid'], true) ? (string) $_POST['mode'] : 'online';
        $startDate = trim((string) ($_POST['start_date'] ?? '')) ?: null;
        $classTime = substr(trim((string) ($_POST['class_time'] ?? '')), 0, 40);
        $capacity = max(1, min(500, (int) ($_POST['capacity'] ?? 30)));
        $batchStatus = in_array($_POST['batch_status'] ?? '', ['active', 'paused', 'completed'], true) ? (string) $_POST['batch_status'] : 'active';

        if ($name === '' || $course === '') {
            $_SESSION['ins_error'] = 'Batch name and course title required.';
            redirect('ins/classes');
        }

        $batchId = (int) ($_POST['batch_id'] ?? 0);
        if ($batchId > 0) {
            $stmt = db()->prepare('UPDATE instructor_batches SET batch_name = ?, course_title = ?, teacher_name = ?, mode = ?, start_date = ?, class_time = ?, capacity = ?, status = ? WHERE id = ? AND instructor_id = ?');
            $stmt->bind_param('ssssssisii', $name, $course, $teacherName, $mode, $startDate, $classTime, $capacity, $batchStatus, $batchId, $instructorId);
            $stmt->execute();
            $_SESSION['ins_success'] = 'Batch updated successfully.';
        } else {
            $stmt = db()->prepare('INSERT INTO instructor_batches (instructor_id, batch_name, course_title, teacher_name, mode, start_date, class_time, capacity, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)');
            $stmt->bind_param('issssssis', $instructorId, $name, $course, $teacherName, $mode, $startDate, $classTime, $capacity, $batchStatus);
            $stmt->execute();
            $_SESSION['ins_success'] = 'Batch created successfully.';
        }

        redirect('ins/classes');
    }

    $batchId = (int) ($_POST['batch_id'] ?? 0);
    $batchId = $batchId > 0 ? $batchId : null;
    $title = substr(trim((string) ($_POST['class_title'] ?? '')), 0, 160);
    $type = in_array($_POST['class_type'] ?? '', ['online', 'offline'], true) ? (string) $_POST['class_type'] : 'online';
    $selectedPlatform = normalize_platform_value($_POST['class_platform'] ?? '');
    $date = trim((string) ($_POST['class_date'] ?? date('Y-m-d')));
    $startsAt = trim((string) ($_POST['starts_at'] ?? '')) ?: null;
    $duration = max(15, min(480, (int) ($_POST['duration_minutes'] ?? 60)));
    $rawMeeting = (string) ($_POST['meeting_link'] ?? '');
    $meeting = normalize_live_class_link(substr(trim($rawMeeting), 0, 255));
    $room = substr(trim((string) ($_POST['room_name'] ?? '')), 0, 120);
    $classStatus = in_array($_POST['class_status'] ?? '', ['scheduled', 'live', 'completed', 'cancelled'], true) ? (string) $_POST['class_status'] : 'scheduled';
    $notes = substr(trim((string) ($_POST['notes'] ?? '')), 0, 1000);

    if ($title === '' || $date === '') {
        $_SESSION['ins_error'] = 'Class title and date required.';
        redirect('ins/classes');
    }

    if ($batchId !== null) {
        $batchCheck = db()->prepare('SELECT id FROM instructor_batches WHERE id = ? AND instructor_id = ? LIMIT 1');
        $batchCheck->bind_param('ii', $batchId, $instructorId);
        $batchCheck->execute();
        if (!$batchCheck->get_result()->fetch_assoc()) {
            $_SESSION['ins_error'] = 'Selected batch does not belong to your instructor account.';
            redirect('ins/classes');
        }
    }

    if ($type !== 'online') {
        $meeting = '';
    } else {
        $detectedPlatform = detect_live_platform_from_link($meeting);
        $platform = $selectedPlatform !== '' ? $selectedPlatform : ($detectedPlatform ?: (($settings['live_platform'] ?? 'google_meet')));
        if ($platform === 'google_meet') {
            $default = (string) ($settings['google_meet_link'] ?? '');
            if (!is_google_meet_link($meeting) || is_youtube_link($meeting)) {
                $meeting = $default;
            }
            if (is_google_meet_link($default)) {
                $meeting = normalize_live_class_link($default);
            }
        } elseif ($platform === 'youtube_live') {
            $default = (string) ($settings['youtube_live_link'] ?? '');
            if (!is_youtube_link($meeting) || is_google_meet_link($meeting)) {
                $meeting = $default;
            }
            if (is_youtube_link($default) || preg_match('/^[A-Za-z0-9_-]{6,20}$/', $default)) {
                $meeting = normalize_live_class_link($default);
            }
        } elseif ($platform === 'custom') {
            // Keep manual/typed link as-is for custom mode.
            // Keep normalized value already prepared above.
        } else {
            if ($meeting === '') {
                if (is_google_meet_link((string) ($settings['google_meet_link'] ?? ''))) {
                    $meeting = normalize_live_class_link((string) ($settings['google_meet_link'] ?? ''));
                } else {
                    $meeting = '';
                }
            }
        }
    }

    $classId = (int) ($_POST['class_id'] ?? 0);
    if ($classId > 0) {
        $stmt = db()->prepare('UPDATE instructor_classes SET batch_id = ?, class_title = ?, class_type = ?, class_date = ?, starts_at = ?, duration_minutes = ?, meeting_link = ?, room_name = ?, class_status = ?, notes = ? WHERE id = ? AND instructor_id = ?');
        $stmt->bind_param('issssissssii', $batchId, $title, $type, $date, $startsAt, $duration, $meeting, $room, $classStatus, $notes, $classId, $instructorId);
        $stmt->execute();
        $_SESSION['ins_success'] = 'Class updated successfully.';
    } else {
        $stmt = db()->prepare('INSERT INTO instructor_classes (instructor_id, batch_id, class_title, class_type, class_date, starts_at, duration_minutes, meeting_link, room_name, class_status, notes) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
        $stmt->bind_param('iissssissss', $instructorId, $batchId, $title, $type, $date, $startsAt, $duration, $meeting, $room, $classStatus, $notes);
        $stmt->execute();
        $_SESSION['ins_success'] = 'Class scheduled successfully.';
    }

    redirect('ins/classes');
}

$message = $_SESSION['ins_success'] ?? '';
$error = $_SESSION['ins_error'] ?? '';
unset($_SESSION['ins_success'], $_SESSION['ins_error']);
$pageTitle = 'Classes & Batches';
$pageSubtitle = 'Manage batches and class schedules together.';
$activePage = 'classes';
?>
<?php include __DIR__ . '/includes/header.php'; ?>
<?php include __DIR__ . '/includes/sidebar.php'; ?>
<main class="main">
    <?php include __DIR__ . '/includes/topbar.php'; ?>
    <section class="content instructor-content">
        <?php if ($message): ?><div class="notice"><?= h($message); ?></div><?php endif; ?>
        <?php if ($error): ?><div class="notice danger"><?= h($error); ?></div><?php endif; ?>

        <?php
        $liveClassCount = count(array_filter($classes, fn($class) => (string) ($class['class_status'] ?? '') === 'live'));
        $scheduledClassCount = count(array_filter($classes, fn($class) => in_array((string) ($class['class_status'] ?? ''), ['scheduled', 'upcoming'], true)));
        $completedClassCount = count(array_filter($classes, fn($class) => (string) ($class['class_status'] ?? '') === 'completed'));
        $classMetricCards = [
            ['label' => 'Batches', 'value' => count($batches), 'icon' => 'bx bx-layer', 'tone' => 'primary'],
            ['label' => 'Classes', 'value' => count($classes), 'icon' => 'bx bx-calendar-event', 'tone' => 'info'],
            ['label' => 'Live Now', 'value' => $liveClassCount, 'icon' => 'bx bx-broadcast', 'tone' => 'success'],
            ['label' => 'Scheduled', 'value' => $scheduledClassCount, 'icon' => 'bx bx-time-five', 'tone' => 'warning'],
            ['label' => 'Completed', 'value' => $completedClassCount, 'icon' => 'bx bx-check-circle', 'tone' => 'secondary'],
            ['label' => 'Open', 'value' => count($unassignedClasses), 'icon' => 'bx bx-link-alt', 'tone' => 'danger'],
        ];
        ?>
        <div class="row g-3">
            <div class="col-12">
                <div class="card custom-card bg-primary-gradient border-0 text-fixed-white">
                    <div class="card-body d-flex flex-wrap align-items-center justify-content-between gap-3">
                        <div>
                            <span class="badge bg-white-1 text-fixed-white mb-2">Class Operations</span>
                            <h4 class="fw-semibold mb-1 text-fixed-white">Classes & Batches</h4>
                            <p class="mb-0 op-8">Daily batch schedule, live status aur classroom planning ek clean console se manage karein.</p>
                        </div>
                        <div class="btn-list">
                            <a class="btn btn-light btn-wave" href="#add-class">Add Class</a>
                            <a class="btn btn-primary-light bg-white-1 border-0 text-fixed-white btn-wave" href="#add-batch">Add Batch</a>
                            <a class="btn btn-primary-light bg-white-1 border-0 text-fixed-white btn-wave" href="<?= h(app_url('ins/live')); ?>">Live Studio</a>
                        </div>
                    </div>
                </div>
            </div>

            <?php foreach ($classMetricCards as $metric): ?>
                <div class="col-6 col-md-4 col-xl-2">
                    <div class="card custom-card">
                        <div class="card-body d-flex align-items-center gap-3">
                            <span class="avatar avatar-md bg-<?= h($metric['tone']); ?>-transparent text-<?= h($metric['tone']); ?>">
                                <i class="<?= h($metric['icon']); ?> fs-20"></i>
                            </span>
                            <div>
                                <p class="mb-1 text-muted fs-12 fw-semibold text-uppercase"><?= h($metric['label']); ?></p>
                                <h4 class="mb-0 fw-semibold"><?= h((string) $metric['value']); ?></h4>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>

            <div class="col-12">
                <div class="card custom-card">
                    <div class="card-header justify-content-between">
                        <div>
                            <div class="card-title mb-0">Class Schedule</div>
                            <p class="mb-0 text-muted fs-12">All scheduled, live and completed classes in one register.</p>
                        </div>
                        <a class="btn btn-sm btn-primary btn-wave" href="#add-class">New Class</a>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-sm table-hover align-middle mb-0 gr-register-table">
                                <colgroup>
                                    <col style="width: 28%;">
                                    <col style="width: 16%;">
                                    <col style="width: 13%;">
                                    <col style="width: 8%;">
                                    <col style="width: 13%;">
                                    <col style="width: 10%;">
                                    <col style="width: 12%;">
                                </colgroup>
                                <thead>
                                    <tr>
                                        <th>Class</th>
                                        <th>Batch</th>
                                        <th>Date & Time</th>
                                        <th>Mode</th>
                                        <th>Access</th>
                                        <th>Status</th>
                                        <th class="text-end">Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (!$classes): ?>
                                        <tr><td colspan="7" class="text-muted py-4">No class scheduled yet.</td></tr>
                                    <?php endif; ?>
                                    <?php foreach ($classes as $class): ?>
                                        <?php
                                        $status = (string) ($class['class_status'] ?? 'scheduled');
                                        $statusTone = $status === 'live' ? 'success' : ($status === 'completed' ? 'secondary' : ($status === 'cancelled' ? 'danger' : 'warning'));
                                        $batchTitle = 'Open class';
                                        $batchCourse = 'No batch course';
                                        foreach ($batches as $batchLookup) {
                                            if ((int) ($batchLookup['id'] ?? 0) === (int) ($class['batch_id'] ?? 0)) {
                                                $batchTitle = (string) ($batchLookup['batch_name'] ?? 'Batch');
                                                $batchCourse = (string) ($batchLookup['course_title'] ?? 'Course');
                                                break;
                                            }
                                        }
                                        $classAccess = $class['class_type'] === 'online'
                                            ? live_class_platform_label((string) ($class['meeting_link'] ?? ''))
                                            : ((string) ($class['room_name'] ?? '') ?: 'Offline room');
                                        ?>
                                        <tr>
                                            <td>
                                                <div class="d-flex align-items-center gap-2">
                                                    <span class="avatar avatar-sm bg-<?= h($statusTone); ?>-transparent text-<?= h($statusTone); ?>"><i class="bx bx-video"></i></span>
                                                    <div>
                                                        <div class="fw-semibold text-truncate gr-cell-title"><?= h($class['class_title']); ?></div>
                                                        <span class="text-muted fs-12"><?= h((string) $class['duration_minutes']); ?> min</span>
                                                    </div>
                                                </div>
                                            </td>
                                            <td>
                                                <div class="fw-semibold text-truncate gr-cell-title"><?= h($batchTitle); ?></div>
                                                <span class="text-muted fs-12 text-truncate gr-cell-subtitle"><?= h($batchCourse); ?></span>
                                            </td>
                                            <td><span class="d-block"><?= h($class['class_date']); ?></span><span class="text-muted fs-12"><?= h(substr((string) $class['starts_at'], 0, 5) ?: 'Time not set'); ?></span></td>
                                            <td><?= h(ucfirst((string) $class['class_type'])); ?></td>
                                            <td><?= h($classAccess); ?></td>
                                            <td><span class="badge bg-<?= h($statusTone); ?>-transparent text-<?= h($statusTone); ?>"><?= h(ucfirst($status)); ?></span></td>
                                            <td class="text-end">
                                                <div class="btn-list justify-content-end">
                                                    <button type="button" class="btn btn-sm btn-primary-light btn-wave" data-bs-toggle="modal" data-bs-target="#view-class-<?= (int) $class['id']; ?>">View</button>
                                                    <a class="btn btn-sm btn-light btn-wave" href="#edit-class-<?= (int) $class['id']; ?>">Edit</a>
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

            <div class="col-12">
                <div class="card custom-card">
                    <div class="card-header justify-content-between">
                        <div>
                            <div class="card-title mb-0">Batch Board</div>
                            <p class="mb-0 text-muted fs-12">Batch, course, teacher and capacity details.</p>
                        </div>
                        <a class="btn btn-sm btn-primary-light btn-wave" href="#add-batch">New Batch</a>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-sm table-hover align-middle mb-0 gr-register-table">
                                <colgroup>
                                    <col style="width: 22%;">
                                    <col style="width: 22%;">
                                    <col style="width: 16%;">
                                    <col style="width: 9%;">
                                    <col style="width: 13%;">
                                    <col style="width: 9%;">
                                    <col style="width: 9%;">
                                    <col style="width: 12%;">
                                </colgroup>
                                <thead>
                                    <tr>
                                        <th>Batch</th>
                                        <th>Course</th>
                                        <th>Teacher</th>
                                        <th>Mode</th>
                                        <th>Schedule</th>
                                        <th>Capacity</th>
                                        <th>Status</th>
                                        <th class="text-end">Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (!$batches): ?>
                                        <tr><td colspan="8" class="text-muted py-4">No batch created yet.</td></tr>
                                    <?php endif; ?>
                                    <?php foreach ($batches as $batch): ?>
                                        <?php $batchTone = $batch['status'] === 'active' ? 'success' : ($batch['status'] === 'completed' ? 'secondary' : 'warning'); ?>
                                        <tr>
                                            <td>
                                                <div class="fw-semibold text-truncate gr-cell-title"><?= h($batch['batch_name']); ?></div>
                                                <span class="text-muted fs-12"><?= h($batch['start_date'] ?: 'No start date'); ?></span>
                                            </td>
                                            <td><span class="text-truncate gr-cell-title"><?= h($batch['course_title']); ?></span></td>
                                            <td><span class="text-truncate gr-cell-title"><?= h($batch['teacher_name'] ?: 'Teacher not set'); ?></span></td>
                                            <td><?= h(ucfirst((string) $batch['mode'])); ?></td>
                                            <td><?= h($batch['class_time'] ?: 'Time not set'); ?></td>
                                            <td><?= h((string) $batch['capacity']); ?> seats</td>
                                            <td><span class="badge bg-<?= h($batchTone); ?>-transparent text-<?= h($batchTone); ?>"><?= h(ucfirst($batch['status'])); ?></span></td>
                                            <td class="text-end">
                                                <div class="btn-list justify-content-end">
                                                    <button type="button" class="btn btn-sm btn-primary-light btn-wave" data-bs-toggle="modal" data-bs-target="#view-batch-<?= (int) $batch['id']; ?>">View</button>
                                                    <a class="btn btn-sm btn-light btn-wave" href="#edit-batch-<?= (int) $batch['id']; ?>">Edit</a>
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

        <?php foreach ($classes as $class): ?>
            <?php
            $status = (string) ($class['class_status'] ?? 'scheduled');
            $statusTone = $status === 'live' ? 'success' : ($status === 'completed' ? 'secondary' : ($status === 'cancelled' ? 'danger' : 'warning'));
            $batchTitle = 'Open class';
            foreach ($batches as $batchLookup) {
                if ((int) ($batchLookup['id'] ?? 0) === (int) ($class['batch_id'] ?? 0)) {
                    $batchTitle = (string) ($batchLookup['batch_name'] ?? 'Batch');
                    break;
                }
            }
            ?>
            <div class="modal fade" id="view-class-<?= (int) $class['id']; ?>" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog modal-lg modal-dialog-centered">
                    <div class="modal-content">
                        <div class="modal-header">
                            <div>
                                <h6 class="modal-title fw-semibold"><?= h($class['class_title']); ?></h6>
                                <span class="text-muted fs-12"><?= h($batchTitle); ?></span>
                            </div>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <div class="row g-3">
                                <div class="col-md-4"><label class="form-label text-muted">Date</label><div class="fw-semibold"><?= h($class['class_date']); ?></div></div>
                                <div class="col-md-4"><label class="form-label text-muted">Time</label><div class="fw-semibold"><?= h(substr((string) $class['starts_at'], 0, 5) ?: 'Not set'); ?></div></div>
                                <div class="col-md-4"><label class="form-label text-muted">Duration</label><div class="fw-semibold"><?= h((string) $class['duration_minutes']); ?> minutes</div></div>
                                <div class="col-md-4"><label class="form-label text-muted">Mode</label><div class="fw-semibold"><?= h(ucfirst((string) $class['class_type'])); ?></div></div>
                                <div class="col-md-4"><label class="form-label text-muted">Platform</label><div class="fw-semibold"><?= h($class['class_type'] === 'online' ? live_class_platform_label((string) ($class['meeting_link'] ?? '')) : 'Offline'); ?></div></div>
                                <div class="col-md-4"><label class="form-label text-muted">Status</label><div><span class="badge bg-<?= h($statusTone); ?>-transparent text-<?= h($statusTone); ?>"><?= h(ucfirst($status)); ?></span></div></div>
                                <div class="col-12"><label class="form-label text-muted">Access</label><div class="fw-semibold text-break"><?= h($class['class_type'] === 'online' ? ((string) ($class['meeting_link'] ?? '') ?: 'Link not set') : ((string) ($class['room_name'] ?? '') ?: 'Room not set')); ?></div></div>
                                <div class="col-12"><label class="form-label text-muted">Notes</label><div class="text-muted"><?= h((string) ($class['notes'] ?? '') ?: 'No notes added.'); ?></div></div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-light" data-bs-dismiss="modal">Close</button>
                            <a class="btn btn-primary btn-wave" href="#edit-class-<?= (int) $class['id']; ?>" data-bs-dismiss="modal">Edit Class</a>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>

        <?php foreach ($batches as $batch): ?>
            <?php $batchTone = $batch['status'] === 'active' ? 'success' : ($batch['status'] === 'completed' ? 'secondary' : 'warning'); ?>
            <div class="modal fade" id="view-batch-<?= (int) $batch['id']; ?>" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog modal-lg modal-dialog-centered">
                    <div class="modal-content">
                        <div class="modal-header">
                            <div>
                                <h6 class="modal-title fw-semibold"><?= h($batch['batch_name']); ?></h6>
                                <span class="text-muted fs-12"><?= h($batch['course_title']); ?></span>
                            </div>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <div class="row g-3">
                                <div class="col-md-4"><label class="form-label text-muted">Teacher</label><div class="fw-semibold"><?= h($batch['teacher_name'] ?: 'Not set'); ?></div></div>
                                <div class="col-md-4"><label class="form-label text-muted">Mode</label><div class="fw-semibold"><?= h(ucfirst((string) $batch['mode'])); ?></div></div>
                                <div class="col-md-4"><label class="form-label text-muted">Status</label><div><span class="badge bg-<?= h($batchTone); ?>-transparent text-<?= h($batchTone); ?>"><?= h(ucfirst($batch['status'])); ?></span></div></div>
                                <div class="col-md-4"><label class="form-label text-muted">Start Date</label><div class="fw-semibold"><?= h($batch['start_date'] ?: 'Not set'); ?></div></div>
                                <div class="col-md-4"><label class="form-label text-muted">Class Time</label><div class="fw-semibold"><?= h($batch['class_time'] ?: 'Not set'); ?></div></div>
                                <div class="col-md-4"><label class="form-label text-muted">Capacity</label><div class="fw-semibold"><?= h((string) $batch['capacity']); ?> seats</div></div>
                                <div class="col-12"><label class="form-label text-muted">Classes Connected</label><div class="fw-semibold"><?= h((string) count($classesByBatch[(int) $batch['id']] ?? [])); ?> classes</div></div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-light" data-bs-dismiss="modal">Close</button>
                            <a class="btn btn-primary btn-wave" href="#edit-batch-<?= (int) $batch['id']; ?>" data-bs-dismiss="modal">Edit Batch</a>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>

        <?php if (false): ?>
        <div class="ins-grid">
            <section class="settings-detail-card ins-card">
                <div class="detail-head compact-head">
                    <div><span>Batch Register</span><h2><?= count($batches); ?> batches</h2><p>Create and manage instructor batches.</p></div>
                    <a class="modal-button" href="#add-batch">Add Batch</a>
                </div>
                <table class="role-access-table smart-table">
                    <thead>
                        <tr><th>Batch</th><th>Course</th><th>Teacher</th><th>Mode</th><th>Schedule</th><th>Capacity</th><th>Status</th><th>Edit</th></tr>
                    </thead>
                    <tbody>
                        <?php if (!$batches): ?><tr><td colspan="8">No batch created yet.</td></tr><?php endif; ?>
                        <?php foreach ($batches as $batch): ?>
                            <tr>
                                <td><strong><?= h($batch['batch_name']); ?></strong><small><?= h($batch['start_date'] ?: 'No start date'); ?></small></td>
                                <td><?= h($batch['course_title']); ?></td>
                                <td><?= h($batch['teacher_name'] ?: 'Not set'); ?></td>
                                <td><?= h(ucfirst($batch['mode'])); ?></td>
                                <td><?= h($batch['class_time'] ?: 'Not set'); ?></td>
                                <td><?= h((string) $batch['capacity']); ?></td>
                                <td><span class="status-pill <?= $batch['status'] === 'active' ? 'ready' : 'empty'; ?>"><?= h(ucfirst($batch['status'])); ?></span></td>
                                <td><a class="table-edit-icon" href="#edit-batch-<?= (int) $batch['id']; ?>">Edit</a></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </section>

            <section class="settings-detail-card ins-card">
                <div class="detail-head compact-head">
                    <div><span>Class Register</span><h2><?= count($classes); ?> classes</h2><p>Classes are grouped by batch here.</p></div>
                    <a class="modal-button" href="#add-class">Add Class</a>
                </div>

                <?php if (!$batches && !$unassignedClasses): ?>
                    <table class="role-access-table smart-table">
                        <tbody>
                            <tr><td colspan="7">No class scheduled yet. Add a class first.</td></tr>
                        </tbody>
                    </table>
                <?php else: ?>
                    <?php foreach ($batches as $batch): ?>
                        <div class="task-list" style="margin-bottom: 8px;">
                            <strong style="font-size: 12px; color:#0f172a;"><?= h($batch['batch_name']); ?> (<?= h((string) count($classesByBatch[(int) $batch['id']] ?? [])); ?>)</strong>
                            <table class="role-access-table smart-table">
                                <thead>
                                    <tr><th>Class</th><th>Type</th><th>Date</th><th>Access</th><th>Platform</th><th>Status</th><th>Edit</th></tr>
                                </thead>
                                <tbody>
                                    <?php
                                    $batchClasses = $classesByBatch[(int) $batch['id']] ?? [];
                                    if (!$batchClasses): ?>
                            <tr><td colspan="7">No class in this batch yet.</td></tr>
                                    <?php endif; ?>
                                    <?php foreach ($batchClasses as $class): ?>
                                        <tr>
                                            <td><strong><?= h($class['class_title']); ?></strong><small><?= h($class['duration_minutes'] . ' minutes'); ?></small></td>
                                            <td><?= h(ucfirst($class['class_type'])); ?></td>
                                            <td><?= h($class['class_date'] . ' ' . substr((string) $class['starts_at'], 0, 5)); ?></td>
                                        <td><?= $class['class_type'] === 'online' ? h($class['meeting_link'] ?: 'Link not set') : h($class['room_name'] ?: 'Room not set'); ?></td>
                                        <td><?= $class['class_type'] === 'online' ? h(live_class_platform_label((string) ($class['meeting_link'] ?? ''))) : 'Offline'; ?></td>
                                            <td><span class="status-pill <?= $class['class_status'] === 'completed' ? 'ready' : 'empty'; ?>"><?= h(ucfirst($class['class_status'])); ?></span></td>
                                            <td><a class="table-edit-icon" href="#edit-class-<?= (int) $class['id']; ?>">Edit</a></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endforeach; ?>

                    <?php if ($unassignedClasses): ?>
                        <div class="task-list">
                            <strong style="font-size: 12px; color:#0f172a;">Unassigned Classes (<?= h((string) count($unassignedClasses)); ?>)</strong>
                            <table class="role-access-table smart-table">
                                <thead>
                                    <tr><th>Class</th><th>Type</th><th>Date</th><th>Access</th><th>Platform</th><th>Status</th><th>Edit</th></tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($unassignedClasses as $class): ?>
                                        <tr>
                                            <td><strong><?= h($class['class_title']); ?></strong><small><?= h($class['duration_minutes'] . ' minutes'); ?></small></td>
                                            <td><?= h(ucfirst($class['class_type'])); ?></td>
                                            <td><?= h($class['class_date'] . ' ' . substr((string) $class['starts_at'], 0, 5)); ?></td>
                                        <td><?= $class['class_type'] === 'online' ? h($class['meeting_link'] ?: 'Link not set') : h($class['room_name'] ?: 'Room not set'); ?></td>
                                        <td><?= $class['class_type'] === 'online' ? h(live_class_platform_label((string) ($class['meeting_link'] ?? ''))) : 'Offline'; ?></td>
                                            <td><span class="status-pill <?= $class['class_status'] === 'completed' ? 'ready' : 'empty'; ?>"><?= h(ucfirst($class['class_status'])); ?></span></td>
                                            <td><a class="table-edit-icon" href="#edit-class-<?= (int) $class['id']; ?>">Edit</a></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </section>
        </div>
        <?php endif; ?>
    </section>

    <div id="add-batch" class="modal-overlay">
        <form class="modal-box wide-modal ins-modal" method="post">
            <input type="hidden" name="csrf_token" value="<?= h(csrf_token()); ?>">
            <input type="hidden" name="form_type" value="batch">
            <div class="modal-head"><h2>Add Batch</h2><a class="modal-close" href="#" aria-label="Close">x</a></div>
            <div class="form-grid compact-form">
                <label>Batch Name<input name="batch_name" placeholder="Python Morning Batch" required></label>
                <label>Course Title<input name="course_title" placeholder="Python Full Stack" required></label>
                <label>Teacher Name<input name="teacher_name" placeholder="MAHAK MAM / RAVI SIR"></label>
                <label>Mode<select name="mode"><option value="online">Online</option><option value="offline">Offline</option><option value="hybrid">Hybrid</option></select></label>
                <label>Start Date<input type="date" name="start_date"></label>
                <label>Class Time<input name="class_time" placeholder="10:00 AM - 11:00 AM"></label>
                <label>Capacity<input type="number" name="capacity" value="30" min="1" max="500"></label>
                <label>Status<select name="batch_status"><option value="active">Active</option><option value="paused">Paused</option><option value="completed">Completed</option></select></label>
            </div>
            <div class="modal-actions"><button type="submit">Save Batch</button></div>
        </form>
    </div>

    <div id="add-class" class="modal-overlay">
        <form class="modal-box wide-modal ins-modal" method="post">
            <input type="hidden" name="csrf_token" value="<?= h(csrf_token()); ?>">
            <input type="hidden" name="form_type" value="class">
            <div class="modal-head"><h2>Add Class</h2><a class="modal-close" href="#" aria-label="Close">x</a></div>
            <div class="form-grid compact-form">
                <label>Batch<select name="batch_id"><option value="">Open class</option><?php foreach ($batches as $batch): ?><option value="<?= (int) $batch['id']; ?>"><?= h($batch['batch_name']); ?></option><?php endforeach; ?></select></label>
                <label>Class Title<input name="class_title" placeholder="Chapter 1 - Introduction" required></label>
                <label>Type<select name="class_type"><option value="online">Online</option><option value="offline">Offline</option></select></label>
                <label>Date<input type="date" name="class_date" value="<?= h(date('Y-m-d')); ?>" required></label>
                <label>Start Time<input type="time" name="starts_at"></label>
                <label>Duration<input type="number" name="duration_minutes" value="60" min="15" max="480"></label>
                <label>Platform<select name="class_platform" data-google="<?= h($defaultGoogleClassMeetingLink); ?>" data-youtube="<?= h($defaultYoutubeClassMeetingLink); ?>">
                    <option value="" selected>Auto</option>
                    <option value="google_meet" <?= $defaultClassPlatform === 'google_meet' ? 'selected' : ''; ?>>Google Meet</option>
                    <option value="youtube_live" <?= $defaultClassPlatform === 'youtube_live' ? 'selected' : ''; ?>>YouTube Live</option>
                    <option value="custom">Custom Link</option>
                </select></label>
                <label>Live Class Link<input name="meeting_link" placeholder="Google Meet ya YouTube link / id / short link paste karein"></label>
                <label>Status<select name="class_status"><option value="scheduled">Scheduled</option><option value="live">Live</option><option value="completed">Completed</option><option value="cancelled">Cancelled</option></select></label>
                <label class="span-2">Teaching Notes<textarea name="notes" rows="2"></textarea></label>
            </div>
            <div class="modal-actions"><button type="submit">Save Class</button></div>
        </form>
    </div>

    <?php foreach ($batches as $batch): ?>
        <div id="edit-batch-<?= (int) $batch['id']; ?>" class="modal-overlay">
            <form class="modal-box wide-modal ins-modal" method="post">
                <input type="hidden" name="csrf_token" value="<?= h(csrf_token()); ?>">
                <input type="hidden" name="form_type" value="batch">
                <input type="hidden" name="batch_id" value="<?= (int) $batch['id']; ?>">
                <div class="modal-head"><h2>Edit Batch</h2><a class="modal-close" href="#" aria-label="Close">x</a></div>
                <div class="form-grid compact-form">
                    <label>Batch Name<input name="batch_name" value="<?= h($batch['batch_name']); ?>" required></label>
                    <label>Course Title<input name="course_title" value="<?= h($batch['course_title']); ?>" required></label>
                    <label>Teacher Name<input name="teacher_name" value="<?= h((string) ($batch['teacher_name'] ?? '')); ?>"></label>
                    <label>Mode<select name="mode"><option value="online" <?= $batch['mode'] === 'online' ? 'selected' : ''; ?>>Online</option><option value="offline" <?= $batch['mode'] === 'offline' ? 'selected' : ''; ?>>Offline</option><option value="hybrid" <?= $batch['mode'] === 'hybrid' ? 'selected' : ''; ?>>Hybrid</option></select></label>
                    <label>Start Date<input type="date" name="start_date" value="<?= h((string) $batch['start_date']); ?>"></label>
                    <label>Class Time<input name="class_time" value="<?= h((string) $batch['class_time']); ?>"></label>
                    <label>Capacity<input type="number" name="capacity" value="<?= h((string) $batch['capacity']); ?>" min="1" max="500"></label>
                    <label>Status<select name="batch_status"><option value="active" <?= $batch['status'] === 'active' ? 'selected' : ''; ?>>Active</option><option value="paused" <?= $batch['status'] === 'paused' ? 'selected' : ''; ?>>Paused</option><option value="completed" <?= $batch['status'] === 'completed' ? 'selected' : ''; ?>>Completed</option></select></label>
                </div>
                <div class="modal-actions"><button type="submit">Update Batch</button></div>
            </form>
        </div>
    <?php endforeach; ?>

    <?php foreach ($classes as $class): ?>
        <div id="edit-class-<?= (int) $class['id']; ?>" class="modal-overlay">
            <form class="modal-box wide-modal ins-modal" method="post">
                <input type="hidden" name="csrf_token" value="<?= h(csrf_token()); ?>">
                <input type="hidden" name="form_type" value="class">
                <input type="hidden" name="class_id" value="<?= (int) $class['id']; ?>">
                <div class="modal-head"><h2>Edit Class</h2><a class="modal-close" href="#" aria-label="Close">x</a></div>
                <div class="form-grid compact-form">
                    <label>Batch<select name="batch_id"><option value="">Open class</option><?php foreach ($batches as $batch): ?><option value="<?= (int) $batch['id']; ?>" <?= (int) $class['batch_id'] === (int) $batch['id'] ? 'selected' : ''; ?>><?= h($batch['batch_name']); ?></option><?php endforeach; ?></select></label>
                    <label>Class Title<input name="class_title" value="<?= h($class['class_title']); ?>" required></label>
                <label>Type<select name="class_type"><option value="online" <?= $class['class_type'] === 'online' ? 'selected' : ''; ?>>Online</option><option value="offline" <?= $class['class_type'] === 'offline' ? 'selected' : ''; ?>>Offline</option></select></label>
                <label>Date<input type="date" name="class_date" value="<?= h($class['class_date']); ?>" required></label>
                <label>Start Time<input type="time" name="starts_at" value="<?= h(substr((string) $class['starts_at'], 0, 5)); ?>"></label>
                <label>Duration<input type="number" name="duration_minutes" value="<?= h((string) $class['duration_minutes']); ?>" min="15" max="480"></label>
                <?php $classPlatform = detect_live_platform_from_link((string) $class['meeting_link']); ?>
                <label>Platform<select name="class_platform"
                    data-google="<?= h($defaultGoogleClassMeetingLink); ?>"
                    data-youtube="<?= h($defaultYoutubeClassMeetingLink); ?>"
                    data-meeting="<?= h((string) normalize_live_class_link((string) $class['meeting_link'])); ?>"
                    data-platform="<?= h($classPlatform); ?>">
                    <option value="">Auto</option>
                    <option value="google_meet" <?= $classPlatform === 'google_meet' ? 'selected' : ''; ?>>Google Meet</option>
                    <option value="youtube_live" <?= $classPlatform === 'youtube_live' ? 'selected' : ''; ?>>YouTube Live</option>
                    <option value="custom" <?= $classPlatform === 'custom' ? 'selected' : ''; ?>>Custom Link</option>
                </select></label>
                <label>Live Class Link<input name="meeting_link" value="<?= h((string) normalize_live_class_link((string) $class['meeting_link'])); ?>"></label>
                <label>Status<select name="class_status"><option value="scheduled" <?= $class['class_status'] === 'scheduled' ? 'selected' : ''; ?>>Scheduled</option><option value="live" <?= $class['class_status'] === 'live' ? 'selected' : ''; ?>>Live</option><option value="completed" <?= $class['class_status'] === 'completed' ? 'selected' : ''; ?>>Completed</option><option value="cancelled" <?= $class['class_status'] === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option></select></label>
                <label class="span-2">Teaching Notes<textarea name="notes" rows="2"><?= h((string) $class['notes']); ?></textarea></label>
            </div>
                <div class="modal-actions"><button type="submit">Update Class</button></div>
            </form>
        </div>
    <?php endforeach; ?>
    <script>
        (function () {
            const normalize = (v) => (v || '').toString().trim();

            const getPlatformDefault = (platformSelect, platform) => {
                if (platform === 'google_meet') {
                    return normalize(platformSelect.dataset.google);
                }
                if (platform === 'youtube_live') {
                    return normalize(platformSelect.dataset.youtube);
                }
                return '';
            };

            const applyClassPlatform = (platformSelect, opts = {}) => {
                const form = platformSelect.closest('form');
                if (!form) return;

                const meetingInput = form.querySelector('input[name="meeting_link"]');
                const typeSelect = form.querySelector('select[name="class_type"]');
                const selectedType = normalize(typeSelect ? typeSelect.value : '');
                const selectedPlatform = normalize(platformSelect.value);

                if (!meetingInput) {
                    return;
                }

                if (selectedType !== 'online') {
                    if (opts.force && normalize(meetingInput.value) === '') {
                        meetingInput.value = '';
                    }
                    return;
                }

                if (selectedPlatform === 'custom') {
                    if (opts.force) {
                        meetingInput.value = normalize(meetingInput.value);
                    }
                    return;
                }

                const defaultLink = getPlatformDefault(platformSelect, selectedPlatform);
                if (defaultLink !== '') {
                    meetingInput.value = defaultLink;
                    return;
                }

                const savedLink = normalize(platformSelect.dataset.meeting);
                if (opts.force === true && normalize(meetingInput.value) === '' && savedLink !== '') {
                    meetingInput.value = savedLink;
                }
            };

            window.handleClassPlatformChange = (platformSelect) => {
                if (!platformSelect) {
                    return;
                }
                applyClassPlatform(platformSelect, { force: true });
            };

            const applyAll = (force = false) => {
                document.querySelectorAll('select[name="class_platform"]').forEach((select) => {
                    const autoPlatform = normalize(select.dataset.platform);
                    if (autoPlatform && normalize(select.value) === '') {
                        select.value = autoPlatform;
                    }

                    if (force) {
                        applyClassPlatform(select, { force: true });
                    } else {
                        applyClassPlatform(select, { force: false });
                    }
                });
            };

            document.querySelectorAll('select[name="class_platform"]').forEach((select) => {
                select.setAttribute('onchange', 'handleClassPlatformChange(this);');
                select.addEventListener('change', () => {
                    applyClassPlatform(select, { force: true });
                });

                const form = select.closest('form');
                const typeSelect = form ? form.querySelector('select[name="class_type"]') : null;
                if (typeSelect) {
                    typeSelect.addEventListener('change', () => {
                        applyClassPlatform(select, { force: true });
                    });
                }
            });

            window.addEventListener('hashchange', () => {
                applyAll(true);
            });

            applyAll(true);
        })();
    </script>
<?php include __DIR__ . '/includes/footer.php'; ?>

