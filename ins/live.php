<?php
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/../includes/live_streaming.php';

$user = instructor_user();
ensure_instructor_erp_tables();
ensure_live_streaming_tables();

$instructorId = (int) $user['id'];
$channel = live_channel_for_instructor($instructorId);
$courses = instructor_courses($instructorId);
$classes = instructor_classes($instructorId, 100);
$batches = instructor_batches($instructorId);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['csrf_token'] ?? null)) {
        $_SESSION['ins_error'] = 'Security token expired.';
        redirect('ins/live');
    }

    $action = (string) ($_POST['action'] ?? '');
    if ($action === 'rotate_key') {
        $channel = live_rotate_stream_key($instructorId);
        $_SESSION['ins_success'] = 'OBS stream key rotated. Update OBS before the next class.';
        redirect('ins/live');
    }

    if ($action === 'delete_recording') {
        $recordingId = (int) ($_POST['recording_id'] ?? 0);
        if ($recordingId <= 0) {
            $_SESSION['ins_error'] = 'Recording not found.';
            redirect('ins/live');
        }

        $recordingStmt = db()->prepare('SELECT id, file_path FROM live_stream_recordings WHERE id = ? AND instructor_id = ? LIMIT 1');
        $recordingStmt->bind_param('ii', $recordingId, $instructorId);
        $recordingStmt->execute();
        $recordingRow = $recordingStmt->get_result()->fetch_assoc();
        if (!$recordingRow) {
            $_SESSION['ins_error'] = 'Recording not found.';
            redirect('ins/live');
        }

        $filePath = trim((string) ($recordingRow['file_path'] ?? ''));
        if ($filePath !== '' && !preg_match('#^https?://#i', $filePath)) {
            $relativePath = ltrim(str_replace('\\', '/', $filePath), '/');
            $absolutePath = realpath(__DIR__ . '/../' . $relativePath);
            $allowedRoot = realpath(__DIR__ . '/../uploads');
            if ($absolutePath && $allowedRoot && str_starts_with($absolutePath, $allowedRoot) && is_file($absolutePath)) {
                @unlink($absolutePath);
            }
        }

        $deleteStmt = db()->prepare('DELETE FROM live_stream_recordings WHERE id = ? AND instructor_id = ?');
        $deleteStmt->bind_param('ii', $recordingId, $instructorId);
        $deleteStmt->execute();
        $_SESSION['ins_success'] = 'Recording deleted.';
        redirect('ins/live');
    }

    if ($action === 'save_channel') {
        $name = substr(trim((string) ($_POST['channel_name'] ?? '')), 0, 160);
        $status = in_array($_POST['status'] ?? '', ['scheduled', 'offline', 'disabled'], true) ? (string) $_POST['status'] : 'offline';
        $autoRecording = !empty($_POST['auto_recording']) ? 1 : 0;
        $courseId = (int) ($_POST['course_id'] ?? 0);
        $courseIdValue = $courseId > 0 ? $courseId : null;
        $classId = (int) ($_POST['class_id'] ?? 0);
        $classIdValue = $classId > 0 ? $classId : null;
        $batchId = (int) ($_POST['batch_id'] ?? 0);
        $batchIdValue = $batchId > 0 ? $batchId : null;
        $announcementTitle = substr(trim((string) ($_POST['announcement_title'] ?? '')), 0, 180);
        $announcementText = substr(trim((string) ($_POST['announcement_text'] ?? '')), 0, 1200);
        $dailyLiveTimeRaw = trim((string) ($_POST['daily_live_time'] ?? ''));
        $dailyLiveTime = preg_match('/^\d{2}:\d{2}$/', $dailyLiveTimeRaw) ? $dailyLiveTimeRaw . ':00' : null;
        $scheduledRaw = trim((string) ($_POST['scheduled_at'] ?? ''));
        $scheduledAt = null;
        if ($scheduledRaw !== '') {
            $scheduledTime = strtotime($scheduledRaw);
            if ($scheduledTime !== false) {
                $scheduledAt = date('Y-m-d H:i:s', $scheduledTime);
            }
        }

        if ($name === '') {
            $_SESSION['ins_error'] = 'Channel name is required.';
            redirect('ins/live');
        }

        if ($courseIdValue !== null) {
            $courseCheck = db()->prepare('SELECT id FROM instructor_courses WHERE id = ? AND instructor_id = ? LIMIT 1');
            $courseCheck->bind_param('ii', $courseIdValue, $instructorId);
            $courseCheck->execute();
            if (!$courseCheck->get_result()->fetch_assoc()) {
                $_SESSION['ins_error'] = 'Selected course does not belong to your account.';
                redirect('ins/live');
            }
        }

        if ($classIdValue !== null) {
            $classCheck = db()->prepare('SELECT id FROM instructor_classes WHERE id = ? AND instructor_id = ? LIMIT 1');
            $classCheck->bind_param('ii', $classIdValue, $instructorId);
            $classCheck->execute();
            if (!$classCheck->get_result()->fetch_assoc()) {
                $_SESSION['ins_error'] = 'Selected class does not belong to your account.';
                redirect('ins/live');
            }
        }

        if ($batchIdValue !== null) {
            $batchCheck = db()->prepare('SELECT id FROM instructor_batches WHERE id = ? AND instructor_id = ? LIMIT 1');
            $batchCheck->bind_param('ii', $batchIdValue, $instructorId);
            $batchCheck->execute();
            if (!$batchCheck->get_result()->fetch_assoc()) {
                $_SESSION['ins_error'] = 'Selected batch does not belong to your account.';
                redirect('ins/live');
            }
        }

        $thumbnailPath = (string) ($channel['thumbnail_path'] ?? '');
        if (!empty($_FILES['thumbnail']['tmp_name']) && is_uploaded_file((string) $_FILES['thumbnail']['tmp_name'])) {
            $allowedTypes = [
                'image/jpeg' => 'jpg',
                'image/png' => 'png',
                'image/webp' => 'webp',
                'image/gif' => 'gif',
            ];
            $mime = (string) (mime_content_type((string) $_FILES['thumbnail']['tmp_name']) ?: '');
            if (!isset($allowedTypes[$mime])) {
                $_SESSION['ins_error'] = 'Please upload JPG, PNG, WEBP or GIF thumbnail.';
                redirect('ins/live');
            }
            $uploadDir = __DIR__ . '/../uploads/live-thumbnails';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0775, true);
            }
            $fileName = 'live-' . $instructorId . '-' . time() . '.' . $allowedTypes[$mime];
            $target = $uploadDir . '/' . $fileName;
            if (!move_uploaded_file((string) $_FILES['thumbnail']['tmp_name'], $target)) {
                $_SESSION['ins_error'] = 'Thumbnail upload failed.';
                redirect('ins/live');
            }
            $thumbnailPath = 'uploads/live-thumbnails/' . $fileName;
        }

        $channelId = (int) $channel['id'];
        $stmt = db()->prepare('
            UPDATE live_stream_channels
            SET channel_name = ?,
                course_id = ?,
                class_id = ?,
                batch_id = ?,
                status = IF(status = "live", status, ?),
                auto_recording = ?,
                thumbnail_path = ?,
                announcement_title = ?,
                announcement_text = ?,
                scheduled_at = ?,
                daily_live_time = ?
            WHERE id = ? AND instructor_id = ?
        ');
        $stmt->bind_param('siiisisssssii', $name, $courseIdValue, $classIdValue, $batchIdValue, $status, $autoRecording, $thumbnailPath, $announcementTitle, $announcementText, $scheduledAt, $dailyLiveTime, $channelId, $instructorId);
        $stmt->execute();
        $_SESSION['ins_success'] = 'Live room updated.';
        redirect('ins/live');
    }
}

$channel = live_channel_for_instructor($instructorId);
$recordings = live_recent_recordings($instructorId);
$rtmpServer = live_base_url();
$rtmpServer = preg_replace('#^https?://#', 'rtmp://', $rtmpServer) ?: 'rtmp://live.gyanrank.in';
$rtmpServer .= '/live';
$obsStreamKey = (string) $channel['playback_slug'] . '?key=' . (string) $channel['stream_key'];
$playerUrl = live_base_url() . '/play/' . rawurlencode((string) $channel['playback_slug']);
$hlsTemplate = live_hls_url($channel, 'STUDENT_TOKEN');
$thumbnailPath = trim((string) ($channel['thumbnail_path'] ?? ''));
$thumbnailUrl = $thumbnailPath !== '' ? app_url($thumbnailPath) : '';
$announcementTitle = trim((string) ($channel['announcement_title'] ?? ''));
$announcementText = trim((string) ($channel['announcement_text'] ?? ''));
$scheduledAtValue = '';
if (!empty($channel['scheduled_at'])) {
    $scheduledAtValue = date('Y-m-d\TH:i', strtotime((string) $channel['scheduled_at']));
}
$dailyLiveTimeValue = '';
if (!empty($channel['daily_live_time'])) {
    $dailyLiveTimeValue = substr((string) $channel['daily_live_time'], 0, 5);
}

$status = (string) ($channel['status'] ?? 'offline');
$statusLabel = ucfirst($status);
$statusClass = $status === 'live' ? 'live' : ($status === 'disabled' ? 'disabled' : 'offline');
$linkedCourseTitle = 'Login only';
foreach ($courses as $course) {
    if ((int) ($course['id'] ?? 0) === (int) ($channel['course_id'] ?? 0)) {
        $linkedCourseTitle = (string) $course['title'];
        break;
    }
}
$selectedClassTitle = 'No class selected';
foreach ($classes as $classRow) {
    if ((int) ($classRow['id'] ?? 0) === (int) ($channel['class_id'] ?? 0)) {
        $selectedClassTitle = (string) $classRow['class_title'];
        break;
    }
}
$selectedBatchTitle = 'No batch selected';
$selectedBatchSchedule = $dailyLiveTimeValue !== '' ? date('h:i A', strtotime($dailyLiveTimeValue)) : 'Daily time not set';
foreach ($batches as $batchRow) {
    if ((int) ($batchRow['id'] ?? 0) === (int) ($channel['batch_id'] ?? 0)) {
        $selectedBatchTitle = (string) $batchRow['batch_name'];
        if ($dailyLiveTimeValue === '' && !empty($batchRow['class_time'])) {
            $selectedBatchSchedule = (string) $batchRow['class_time'];
        }
        break;
    }
}

$pageTitle = 'Live Studio';
$pageSubtitle = 'Secure OBS publishing, HLS playback tokens and class recording controls.';
$activePage = 'live';
?>
<?php include __DIR__ . '/includes/header.php'; ?>
<?php include __DIR__ . '/includes/sidebar.php'; ?>

<main class="main instructor-live-page">
    <?php include __DIR__ . '/includes/topbar.php'; ?>
    <section class="content instructor-content">
        <?php if (!empty($_SESSION['ins_success'])): ?>
            <div class="notice"><?= h((string) $_SESSION['ins_success']); unset($_SESSION['ins_success']); ?></div>
        <?php endif; ?>
        <?php if (!empty($_SESSION['ins_error'])): ?>
            <div class="notice danger"><?= h((string) $_SESSION['ins_error']); unset($_SESSION['ins_error']); ?></div>
        <?php endif; ?>

        <div class="row g-3">
            <div class="col-12">
                <div class="card custom-card bg-primary-gradient border-0 text-fixed-white">
                    <div class="card-body d-flex flex-wrap align-items-center justify-content-between gap-3">
                        <div>
                            <span class="badge bg-white-1 text-fixed-white mb-2">Live Studio</span>
                            <h4 class="fw-semibold mb-1 text-fixed-white"><?= h((string) $channel['channel_name']); ?></h4>
                            <p class="mb-0 op-8"><?= h($announcementText !== '' ? $announcementText : 'OBS connect karein, batch/course lock set karein aur students ko secure live class dikhayein.'); ?></p>
                        </div>
                        <div class="btn-list">
                            <a class="btn btn-light btn-wave" href="<?= h($playerUrl); ?>" target="_blank">Teacher Preview</a>
                            <a class="btn btn-outline-light btn-wave" href="#live-settings">Edit Details</a>
                        </div>
                    </div>
                </div>
            </div>

            <?php
            $liveMetricCards = [
                ['label' => 'Status', 'value' => $statusLabel, 'icon' => 'bx bx-broadcast', 'tone' => $status === 'live' ? 'success' : 'warning'],
                ['label' => 'Batch', 'value' => $selectedBatchTitle, 'icon' => 'bx bx-layer', 'tone' => 'primary'],
                ['label' => 'Course Access', 'value' => $linkedCourseTitle, 'icon' => 'bx bx-book-open', 'tone' => 'info'],
                ['label' => 'Daily Time', 'value' => $selectedBatchSchedule, 'icon' => 'bx bx-time-five', 'tone' => 'secondary'],
                ['label' => 'Recording', 'value' => ((int) $channel['auto_recording'] === 1) ? 'Auto' : 'Off', 'icon' => 'bx bx-video-recording', 'tone' => ((int) $channel['auto_recording'] === 1) ? 'success' : 'danger'],
                ['label' => 'Last IP', 'value' => (string) ($channel['last_publish_ip'] ?: 'Waiting'), 'icon' => 'bx bx-wifi', 'tone' => 'dark'],
            ];
            ?>
            <?php foreach ($liveMetricCards as $metric): ?>
                <div class="col-6 col-md-4 col-xl-2">
                    <div class="card custom-card">
                        <div class="card-body d-flex align-items-center gap-3">
                            <span class="avatar avatar-md bg-<?= h($metric['tone']); ?>-transparent text-<?= h($metric['tone']); ?>">
                                <i class="<?= h($metric['icon']); ?> fs-20"></i>
                            </span>
                            <div class="min-w-0">
                                <p class="mb-1 text-muted fs-12 fw-semibold text-uppercase"><?= h($metric['label']); ?></p>
                                <h6 class="mb-0 fw-semibold text-truncate" style="max-width: 10rem;"><?= h((string) $metric['value']); ?></h6>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>

            <div class="col-xl-4">
                <div class="card custom-card">
                    <div class="card-body">
                        <div class="ratio ratio-16x9 rounded overflow-hidden bg-dark mb-3">
                            <?php if ($thumbnailUrl !== ''): ?>
                                <img class="w-100 h-100 object-fit-cover" src="<?= h($thumbnailUrl); ?>" alt="<?= h((string) $channel['channel_name']); ?>">
                            <?php else: ?>
                                <div class="d-flex flex-column align-items-center justify-content-center text-fixed-white">
                                    <span class="avatar avatar-lg bg-primary-transparent text-primary mb-2"><i class="bx bx-image fs-24"></i></span>
                                    <strong><?= h(mb_strimwidth((string) $channel['channel_name'], 0, 42, '...')); ?></strong>
                                    <small class="op-7">Thumbnail not uploaded</small>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="d-flex align-items-center justify-content-between gap-2">
                            <div>
                                <h6 class="mb-1 fw-semibold"><?= h($announcementTitle !== '' ? $announcementTitle : 'Live class announcement'); ?></h6>
                                <p class="mb-0 text-muted fs-12">Last started: <?= h($channel['last_started_at'] ? date('d M, h:i A', strtotime((string) $channel['last_started_at'])) : 'Never'); ?></p>
                            </div>
                            <span class="badge bg-<?= $status === 'live' ? 'success' : 'warning'; ?>-transparent text-<?= $status === 'live' ? 'success' : 'warning'; ?>"><?= h($statusLabel); ?></span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-xl-8">
                <div class="card custom-card">
                    <div class="card-header justify-content-between">
                        <div>
                            <div class="card-title mb-0">OBS Publisher Details</div>
                            <p class="mb-0 text-muted fs-12">Server aur Stream Key ko OBS Settings > Stream me paste karein.</p>
                        </div>
                        <form method="post" class="m-0">
                            <input type="hidden" name="csrf_token" value="<?= h(csrf_token()); ?>">
                            <input type="hidden" name="action" value="rotate_key">
                            <button type="submit" class="btn btn-sm btn-primary-light btn-wave">Rotate Key</button>
                        </form>
                    </div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Server</label>
                                <input class="form-control" type="text" value="<?= h($rtmpServer); ?>" readonly>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Stream Key</label>
                                <input class="form-control" type="text" value="<?= h($obsStreamKey); ?>" readonly>
                            </div>
                            <div class="col-12">
                                <label class="form-label">Student Player URL</label>
                                <input class="form-control" type="text" value="<?= h($playerUrl); ?>" readonly>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-xl-7" id="live-settings">
                <div class="card custom-card">
                    <div class="card-header">
                        <div>
                            <div class="card-title mb-0">Live Class Details</div>
                            <p class="mb-0 text-muted fs-12">Thumbnail, batch/course lock, announcement aur recording preference.</p>
                        </div>
                    </div>
                    <div class="card-body">
                        <form method="post" class="row g-3" enctype="multipart/form-data">
                            <input type="hidden" name="csrf_token" value="<?= h(csrf_token()); ?>">
                            <input type="hidden" name="action" value="save_channel">
                            <div class="col-md-6">
                                <label class="form-label">Channel Name</label>
                                <input class="form-control" type="text" name="channel_name" value="<?= h((string) $channel['channel_name']); ?>" maxlength="160" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Batch</label>
                                <select class="form-select" name="batch_id">
                                    <option value="0">No batch selected</option>
                                    <?php foreach ($batches as $batchRow): ?>
                                        <option value="<?= h((string) $batchRow['id']); ?>" <?= (int) ($channel['batch_id'] ?? 0) === (int) $batchRow['id'] ? 'selected' : ''; ?>><?= h((string) $batchRow['batch_name']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Course Access</label>
                                <select class="form-select" name="course_id">
                                    <option value="0">Login only - no course lock</option>
                                    <?php foreach ($courses as $course): ?>
                                        <option value="<?= h((string) $course['id']); ?>" <?= (int) ($channel['course_id'] ?? 0) === (int) $course['id'] ? 'selected' : ''; ?>><?= h((string) $course['title']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Connected Class</label>
                                <select class="form-select" name="class_id">
                                    <option value="0">No class selected</option>
                                    <?php foreach ($classes as $classRow): ?>
                                        <option value="<?= h((string) $classRow['id']); ?>" <?= (int) ($channel['class_id'] ?? 0) === (int) $classRow['id'] ? 'selected' : ''; ?>><?= h((string) $classRow['class_title']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Daily Live Time</label>
                                <input class="form-control" type="time" name="daily_live_time" value="<?= h($dailyLiveTimeValue); ?>">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Schedule Once</label>
                                <input class="form-control" type="datetime-local" name="scheduled_at" value="<?= h($scheduledAtValue); ?>">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Status</label>
                                <select class="form-select" name="status" <?= $status === 'live' ? 'disabled' : ''; ?>>
                                    <option value="offline" <?= $status === 'offline' ? 'selected' : ''; ?>>Offline</option>
                                    <option value="scheduled" <?= $status === 'scheduled' ? 'selected' : ''; ?>>Scheduled</option>
                                    <option value="disabled" <?= $status === 'disabled' ? 'selected' : ''; ?>>Disabled</option>
                                </select>
                            </div>
                            <div class="col-12">
                                <label class="form-label">Announcement Title</label>
                                <input class="form-control" type="text" name="announcement_title" value="<?= h($announcementTitle); ?>" maxlength="180" placeholder="Example: Python live class starts at 6 PM">
                            </div>
                            <div class="col-12">
                                <label class="form-label">Announcement Message</label>
                                <textarea class="form-control" name="announcement_text" rows="3" maxlength="1200" placeholder="Write what students should know before joining..."><?= h($announcementText); ?></textarea>
                            </div>
                            <div class="col-md-8">
                                <label class="form-label">Thumbnail</label>
                                <input class="form-control" type="file" name="thumbnail" accept="image/png,image/jpeg,image/webp,image/gif">
                                <div class="form-text">Recommended 1280 x 720 image.</div>
                            </div>
                            <div class="col-md-4 d-flex align-items-end">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" id="autoRecording" name="auto_recording" value="1" <?= ((int) $channel['auto_recording'] === 1) ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="autoRecording">Auto recording</label>
                                </div>
                            </div>
                            <div class="col-12 text-end">
                                <button type="submit" class="btn btn-primary btn-wave">Save Live Channel</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <div class="col-xl-5">
                <div class="card custom-card">
                    <div class="card-header">
                        <div>
                            <div class="card-title mb-0">Go Live Checklist</div>
                            <p class="mb-0 text-muted fs-12">Daily class start karne se pehle quick check.</p>
                        </div>
                    </div>
                    <div class="card-body">
                        <ul class="list-group list-group-flush">
                            <li class="list-group-item px-0 d-flex gap-2"><i class="bx bx-check-circle text-success fs-18"></i><span>Thumbnail aur announcement save karein.</span></li>
                            <li class="list-group-item px-0 d-flex gap-2"><i class="bx bx-check-circle text-success fs-18"></i><span>OBS me Server aur Stream Key paste karein.</span></li>
                            <li class="list-group-item px-0 d-flex gap-2"><i class="bx bx-check-circle text-success fs-18"></i><span>OBS me Start Streaming press karein.</span></li>
                            <li class="list-group-item px-0 d-flex gap-2"><i class="bx bx-check-circle text-success fs-18"></i><span>Teacher Preview se video verify karein.</span></li>
                        </ul>
                    </div>
                </div>
            </div>

            <div class="col-12">
                <div class="card custom-card">
                    <div class="card-header justify-content-between">
                        <div>
                            <div class="card-title mb-0">Recent Recordings</div>
                            <p class="mb-0 text-muted fs-12">Class end hone ke baad recordings yahan appear hongi.</p>
                        </div>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-sm table-hover align-middle mb-0 gr-register-table">
                                <colgroup>
                                    <col style="width: 30%;">
                                    <col style="width: 12%;">
                                    <col style="width: 32%;">
                                    <col style="width: 14%;">
                                    <col style="width: 12%;">
                                </colgroup>
                                <thead>
                                    <tr>
                                        <th>Title</th>
                                        <th>Status</th>
                                        <th>File</th>
                                        <th>Created</th>
                                        <th class="text-end">Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (!$recordings): ?>
                                        <tr><td colspan="5" class="text-muted py-4">No recordings yet.</td></tr>
                                    <?php endif; ?>
                                    <?php foreach ($recordings as $recording): ?>
                                        <?php
                                        $recordingFile = trim((string) ($recording['file_path'] ?? ''));
                                        $downloadUrl = $recordingFile !== ''
                                            ? (preg_match('#^https?://#i', $recordingFile) ? $recordingFile : app_url(ltrim($recordingFile, '/')))
                                            : '';
                                        $recordingStatus = (string) ($recording['status'] ?? 'pending');
                                        $recordingTone = $recordingStatus === 'ready' || $recordingStatus === 'completed' ? 'success' : 'warning';
                                        ?>
                                        <tr>
                                            <td><span class="fw-semibold text-truncate gr-cell-title"><?= h((string) $recording['title']); ?></span></td>
                                            <td><span class="badge bg-<?= h($recordingTone); ?>-transparent text-<?= h($recordingTone); ?>"><?= h(ucfirst($recordingStatus)); ?></span></td>
                                            <td><span class="text-muted text-truncate gr-cell-title"><?= h($recordingFile !== '' ? $recordingFile : 'File not available'); ?></span></td>
                                            <td><?= h(date('d M Y, H:i', strtotime((string) $recording['created_at']))); ?></td>
                                            <td class="text-end">
                                                <div class="btn-list justify-content-end">
                                                    <?php if ($downloadUrl !== ''): ?>
                                                        <a class="btn btn-sm btn-primary-light btn-wave" href="<?= h($downloadUrl); ?>" download>Download</a>
                                                    <?php endif; ?>
                                                    <form method="post" class="m-0" onsubmit="return confirm('Delete this recording?');">
                                                        <input type="hidden" name="csrf_token" value="<?= h(csrf_token()); ?>">
                                                        <input type="hidden" name="action" value="delete_recording">
                                                        <input type="hidden" name="recording_id" value="<?= h((string) $recording['id']); ?>">
                                                        <button type="submit" class="btn btn-sm btn-danger-light btn-wave">Delete</button>
                                                    </form>
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
</main>

<?php include __DIR__ . '/includes/footer.php'; ?>
