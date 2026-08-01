<?php
require_once __DIR__ . '/includes/functions.php';

$user = instructor_user();
ensure_instructor_erp_tables();

$instructorId = (int) $user['id'];
$counts = instructor_counts($instructorId);
$classes = instructor_classes($instructorId, 10);
$batches = instructor_batches($instructorId);
$courses = instructor_courses($instructorId);
$exams = instructor_exams($instructorId);
$questionCount = instructor_question_count($instructorId);
$recentQuestions = instructor_questions($instructorId, 6, 0);

$today = date('Y-m-d');
$upcomingClasses = [];
$liveNow = [];
$recentCompleted = [];
$totalEnrolledStudents = 0;
$activeEnrolledStudents = 0;
$instructorFollowerCount = 0;
$followersToday = 0;
$followersThisWeek = 0;
$courseEnrollStats = [];
$recentFollowers = [];

foreach ($classes as $classRow) {
    $status = (string) ($classRow['class_status'] ?? '');
    if ($status === 'live') {
        $liveNow[] = $classRow;
        continue;
    }
    if ($status === 'completed') {
        if (count($recentCompleted) < 4) {
            $recentCompleted[] = $classRow;
        }
        continue;
    }
    if (in_array($status, ['scheduled', 'upcoming'], true) || (string) ($classRow['class_date'] ?? '') >= $today) {
        $upcomingClasses[] = $classRow;
    }
}

$enrolledStmt = db()->prepare('
    SELECT COUNT(DISTINCT e.student_id) AS total_enrolled,
           SUM(CASE WHEN e.status = \'active\' THEN 1 ELSE 0 END) AS active_enrolled
    FROM student_course_enrollments e
    INNER JOIN instructor_courses c ON c.id = e.course_id
    WHERE c.instructor_id = ?;
');
$enrolledStmt->bind_param('i', $instructorId);
$enrolledStmt->execute();
$enrollRow = $enrolledStmt->get_result()->fetch_assoc();
if ($enrollRow) {
    $totalEnrolledStudents = (int) ($enrollRow['total_enrolled'] ?? 0);
    $activeEnrolledStudents = (int) ($enrollRow['active_enrolled'] ?? 0);
}

$followerStmt = db()->prepare('SELECT COUNT(*) AS total_followers FROM student_instructor_follows WHERE instructor_id = ?');
$followerStmt->bind_param('i', $instructorId);
$followerStmt->execute();
$followerRow = $followerStmt->get_result()->fetch_assoc();
if ($followerRow) {
    $instructorFollowerCount = (int) ($followerRow['total_followers'] ?? 0);
}

$followerTodayStmt = db()->prepare('
    SELECT COUNT(*) AS today_followers
    FROM student_instructor_follows
    WHERE instructor_id = ? AND DATE(created_at) = CURDATE()
');
$followerTodayStmt->bind_param('i', $instructorId);
$followerTodayStmt->execute();
$followerTodayRow = $followerTodayStmt->get_result()->fetch_assoc();
if ($followerTodayRow) {
    $followersToday = (int) ($followerTodayRow['today_followers'] ?? 0);
}

$followerWeekStmt = db()->prepare('
    SELECT COUNT(*) AS week_followers
    FROM student_instructor_follows
    WHERE instructor_id = ? AND created_at >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
');
$followerWeekStmt->bind_param('i', $instructorId);
$followerWeekStmt->execute();
$followerWeekRow = $followerWeekStmt->get_result()->fetch_assoc();
if ($followerWeekRow) {
    $followersThisWeek = (int) ($followerWeekRow['week_followers'] ?? 0);
}

$courseEnrollStmt = db()->prepare('
    SELECT c.id, c.title,
           COUNT(DISTINCT e.student_id) AS enrolled_students,
           SUM(CASE WHEN e.status = \'active\' THEN 1 ELSE 0 END) AS active_enrollments
    FROM instructor_courses c
    LEFT JOIN student_course_enrollments e ON e.course_id = c.id
    WHERE c.instructor_id = ?
    GROUP BY c.id, c.title
    ORDER BY enrolled_students DESC, c.title ASC
');
$courseEnrollStmt->bind_param('i', $instructorId);
$courseEnrollStmt->execute();
$courseEnrollStats = $courseEnrollStmt->get_result()->fetch_all(MYSQLI_ASSOC);

$followerListStmt = db()->prepare("
    SELECT u.full_name, u.email, sif.created_at
    FROM student_instructor_follows sif
    INNER JOIN users u ON u.id = sif.student_id
    WHERE sif.instructor_id = ?
    ORDER BY sif.created_at DESC
    LIMIT 8
");
$followerListStmt->bind_param('i', $instructorId);
$followerListStmt->execute();
$recentFollowers = $followerListStmt->get_result()->fetch_all(MYSQLI_ASSOC);

$followerTrendRows = [];
$followerTrendStmt = db()->prepare("
    SELECT DATE_FORMAT(sif.created_at, '%Y-%m-%d') AS day_key,
           COUNT(*) AS count_day
    FROM student_instructor_follows sif
    WHERE sif.instructor_id = ? AND sif.created_at >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
    GROUP BY DATE(sif.created_at)
    ORDER BY day_key ASC
");
$followerTrendStmt->bind_param('i', $instructorId);
$followerTrendStmt->execute();
$trendMap = [];
foreach ($followerTrendStmt->get_result()->fetch_all(MYSQLI_ASSOC) as $trendRow) {
    $trendMap[(string) $trendRow['day_key']] = (int) ($trendRow['count_day'] ?? 0);
}
$startDate = strtotime('-6 days');
$followerTrendRows = [];
$maxTrendCount = 0;
for ($i = 0; $i < 7; $i++) {
    $day = date('Y-m-d', strtotime('+' . $i . ' day', $startDate));
    $dayCount = (int) ($trendMap[$day] ?? 0);
    $followerTrendRows[] = [
        'day' => $day,
        'count' => $dayCount,
    ];
    if ($dayCount > $maxTrendCount) {
        $maxTrendCount = $dayCount;
    }
}

$topCourseLabels = [];
$topCourseCounts = [];
$maxCourseEnroll = 0;
foreach (array_slice($courseEnrollStats, 0, 3) as $topCourse) {
    $topCourseLabels[] = (string) ($topCourse['title'] ?? 'Course');
    $topCount = (int) ($topCourse['enrolled_students'] ?? 0);
    $topCourseCounts[] = $topCount;
    if ($topCount > $maxCourseEnroll) {
        $maxCourseEnroll = $topCount;
    }
}

$timeAgo = function (string $dateTime): string {
    $time = strtotime($dateTime);
    if ($time === false) {
        return 'recently';
    }

    $seconds = max(0, time() - $time);
    if ($seconds < 60) {
        return 'just now';
    }
    if ($seconds < 3600) {
        $mins = (int) floor($seconds / 60);
        return $mins . ' min' . ($mins === 1 ? '' : 's') . ' ago';
    }
    if ($seconds < 86400) {
        $hours = (int) floor($seconds / 3600);
        return $hours . ' hr' . ($hours === 1 ? '' : 's') . ' ago';
    }

    $days = (int) floor($seconds / 86400);
    if ($days < 30) {
        return $days . ' day' . ($days === 1 ? '' : 's') . ' ago';
    }

    return date('M d, Y', $time);
};

$pageTitle = 'Instructor Dashboard';
$pageSubtitle = 'Complete operations center for courses, classes, batches and daily teaching tasks.';
$activePage = 'dashboard';
?>
<?php include __DIR__ . '/includes/header.php'; ?>
<?php include __DIR__ . '/includes/sidebar.php'; ?>

<main class="main instructor-dashboard">
    <?php include __DIR__ . '/includes/topbar.php'; ?>
    <section class="content instructor-content">
        <div class="row g-3">
            <div class="col-12">
                <div class="card custom-card bg-primary-gradient text-fixed-white border-0">
                    <div class="card-body d-flex flex-wrap align-items-center justify-content-between gap-3">
                        <div>
                            <span class="badge bg-white-1 text-fixed-white mb-2">Instructor Workspace</span>
                            <h4 class="fw-semibold mb-1 text-fixed-white">Namaste, <?= h((string) $user['full_name']); ?></h4>
                            <p class="mb-0 op-8">Live classes, batches, courses aur student activity ko ek clean teaching dashboard se control karein.</p>
                        </div>
                        <div class="btn-list">
                            <a class="btn btn-light btn-wave" href="<?= h(app_url('ins/live')); ?>"><i class="bx bx-video me-1"></i>Live Studio</a>
                            <a class="btn btn-primary-light btn-wave bg-white-1 text-fixed-white border-0" href="<?= h(app_url('ins/classes')); ?>">Schedule</a>
                            <a class="btn btn-primary-light btn-wave bg-white-1 text-fixed-white border-0" href="<?= h(app_url('ins/courses')); ?>">Courses</a>
                        </div>
                    </div>
                </div>
            </div>

            <?php
            $dashboardMetrics = [
                ['Active Batches', (string) $counts['batches'], 'bx bx-layer', 'primary', app_url('ins/batches')],
                ['Classes', (string) $counts['classes'], 'bx bx-calendar', 'info', app_url('ins/classes')],
                ['Live Now', (string) $counts['live'], 'bx bx-broadcast', 'success', app_url('ins/live')],
                ['Courses', (string) count($courses), 'bx bx-book-open', 'warning', app_url('ins/courses')],
                ['Questions', (string) $questionCount, 'bx bx-help-circle', 'danger', app_url('ins/questions')],
                ['Students', (string) $totalEnrolledStudents, 'bx bx-user', 'secondary', app_url('ins/students')],
            ];
            ?>
            <?php foreach ($dashboardMetrics as $metric): ?>
                <div class="col-xxl-2 col-xl-4 col-md-6">
                    <a class="card custom-card mb-0 text-decoration-none" href="<?= h($metric[4]); ?>">
                        <div class="card-body d-flex align-items-center gap-3">
                            <span class="avatar avatar-lg rounded-3 bg-<?= h($metric[3]); ?>-transparent text-<?= h($metric[3]); ?>"><i class="<?= h($metric[2]); ?> fs-22"></i></span>
                            <div>
                                <p class="mb-1 text-muted fs-12 fw-semibold text-uppercase"><?= h($metric[0]); ?></p>
                                <h4 class="mb-0 fw-semibold"><?= h($metric[1]); ?></h4>
                            </div>
                        </div>
                    </a>
                </div>
            <?php endforeach; ?>

            <div class="col-xl-8">
                <div class="card custom-card">
                    <div class="card-header justify-content-between">
                        <div class="card-title">Today & Upcoming</div>
                        <a class="btn btn-sm btn-primary-light btn-wave" href="<?= h(app_url('ins/classes')); ?>">Manage</a>
                    </div>
                    <div class="card-body p-0">
                        <div class="list-group list-group-flush">
                            <?php if (!$classes): ?>
                                <div class="list-group-item text-muted">No class scheduled yet.</div>
                            <?php else: ?>
                                <?php foreach (array_slice($classes, 0, 4) as $class): ?>
                                    <?php
                                    $status = (string) ($class['class_status'] ?? 'scheduled');
                                    $classDate = (string) ($class['class_date'] ?? '');
                                    $classTime = substr((string) ($class['starts_at'] ?? ''), 0, 5);
                                    ?>
                                    <a class="list-group-item list-group-item-action d-flex align-items-center gap-3" href="<?= h(app_url('ins/classes?id=' . (int) ($class['id'] ?? 0))); ?>">
                                        <span class="avatar avatar-sm rounded-circle bg-<?= $status === 'live' ? 'success' : 'primary'; ?>-transparent text-<?= $status === 'live' ? 'success' : 'primary'; ?>"><i class="bx bx-video"></i></span>
                                        <div class="flex-fill min-w-0">
                                            <p class="mb-1 fw-semibold text-truncate"><?= h((string) $class['class_title']); ?></p>
                                            <small class="text-muted"><?= h($class['batch_name'] ?: 'Open Batch'); ?> - <?= h(trim($classDate . ' ' . $classTime) ?: 'Time not set'); ?></small>
                                        </div>
                                        <span class="badge bg-primary-transparent text-primary"><?= h(ucfirst($status)); ?></span>
                                    </a>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-xl-4">
                <div class="card custom-card">
                    <div class="card-header justify-content-between">
                        <div class="card-title">Active Batches</div>
                        <a class="btn btn-sm btn-primary-light btn-wave" href="<?= h(app_url('ins/classes')); ?>">Open</a>
                    </div>
                    <div class="card-body p-0">
                        <div class="list-group list-group-flush">
                            <?php if (!$batches): ?>
                                <div class="list-group-item text-muted">Create your first batch.</div>
                            <?php else: ?>
                                <?php foreach (array_slice($batches, 0, 5) as $batch): ?>
                                    <div class="list-group-item d-flex align-items-center justify-content-between gap-3">
                                        <div class="min-w-0"><p class="mb-1 fw-semibold text-truncate"><?= h((string) $batch['batch_name']); ?></p><small class="text-muted text-truncate d-block"><?= h($batch['course_title'] ?: 'Course not set'); ?></small></div>
                                        <span class="badge bg-secondary-transparent text-secondary"><?= h((string) $batch['capacity']); ?></span>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <?php if (false): ?>
        <div class="premium-instructor-dashboard">
            <section class="pid-hero">
                <div>
                    <span class="pid-kicker">Instructor Workspace</span>
                    <h2>Namaste, <?= h((string) $user['full_name']); ?></h2>
                    <p>Live classes, batches, courses aur student activity ko ek clean teaching dashboard se control karein.</p>
                </div>
                <div class="pid-actions">
                    <a class="btn btn-primary btn-wave" href="<?= h(app_url('ins/live')); ?>"><i class="bx bx-video me-1"></i>Live Studio</a>
                    <a class="btn btn-light btn-wave" href="<?= h(app_url('ins/classes')); ?>">Schedule</a>
                    <a class="btn btn-light btn-wave" href="<?= h(app_url('ins/courses')); ?>">Courses</a>
                </div>
            </section>

            <section class="pid-metrics">
                <a href="<?= h(app_url('ins/batches')); ?>"><i class="bx bx-layer"></i><span>Active Batches</span><strong><?= h((string) $counts['batches']); ?></strong></a>
                <a href="<?= h(app_url('ins/classes')); ?>"><i class="bx bx-calendar"></i><span>Classes</span><strong><?= h((string) $counts['classes']); ?></strong></a>
                <a href="<?= h(app_url('ins/live')); ?>"><i class="bx bx-broadcast"></i><span>Live Now</span><strong><?= h((string) $counts['live']); ?></strong></a>
                <a href="<?= h(app_url('ins/courses')); ?>"><i class="bx bx-book-open"></i><span>Courses</span><strong><?= h((string) count($courses)); ?></strong></a>
                <a href="<?= h(app_url('ins/questions')); ?>"><i class="bx bx-help-circle"></i><span>Questions</span><strong><?= h((string) $questionCount); ?></strong></a>
                <a href="<?= h(app_url('ins/students')); ?>"><i class="bx bx-user"></i><span>Students</span><strong><?= h((string) $totalEnrolledStudents); ?></strong></a>
            </section>

            <section class="pid-grid">
                <article class="pid-card pid-main-card">
                    <div class="pid-card-head">
                        <div><span>Classroom</span><h3>Today & upcoming</h3></div>
                        <a href="<?= h(app_url('ins/classes')); ?>">Manage</a>
                    </div>
                    <div class="pid-list">
                        <?php if (!$classes): ?>
                            <div class="pid-empty">No class scheduled yet.</div>
                        <?php else: ?>
                            <?php foreach (array_slice($classes, 0, 4) as $class): ?>
                                <?php
                                $status = (string) ($class['class_status'] ?? 'scheduled');
                                $classDate = (string) ($class['class_date'] ?? '');
                                $classTime = substr((string) ($class['starts_at'] ?? ''), 0, 5);
                                ?>
                                <a class="pid-list-item" href="<?= h(app_url('ins/classes?id=' . (int) ($class['id'] ?? 0))); ?>">
                                    <span class="pid-dot <?= $status === 'live' ? 'live' : ''; ?>"></span>
                                    <div>
                                        <strong><?= h(mb_strimwidth((string) $class['class_title'], 0, 72, '...')); ?></strong>
                                        <small><?= h($class['batch_name'] ?: 'Open Batch'); ?> · <?= h(trim($classDate . ' ' . $classTime) ?: 'Time not set'); ?></small>
                                    </div>
                                    <em><?= h(ucfirst($status)); ?></em>
                                </a>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </article>

                <article class="pid-card">
                    <div class="pid-card-head">
                        <div><span>Batches</span><h3>Active groups</h3></div>
                        <a href="<?= h(app_url('ins/classes')); ?>">Open</a>
                    </div>
                    <div class="pid-mini-list">
                        <?php if (!$batches): ?>
                            <div class="pid-empty">Create your first batch.</div>
                        <?php else: ?>
                            <?php foreach (array_slice($batches, 0, 5) as $batch): ?>
                                <div class="pid-mini-row">
                                    <div><strong><?= h(mb_strimwidth((string) $batch['batch_name'], 0, 34, '...')); ?></strong><small><?= h($batch['course_title'] ?: 'Course not set'); ?></small></div>
                                    <span><?= h((string) $batch['capacity']); ?></span>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </article>
            </section>

            <section class="pid-grid pid-grid-equal">
                <article class="pid-card">
                    <div class="pid-card-head">
                        <div><span>Courses</span><h3>Recent portfolio</h3></div>
                        <a href="<?= h(app_url('ins/courses')); ?>">View all</a>
                    </div>
                    <div class="pid-course-list">
                        <?php if (!$courses): ?>
                            <div class="pid-empty">No course found.</div>
                        <?php else: ?>
                            <?php foreach (array_slice($courses, 0, 4) as $course): ?>
                                <a class="pid-course-row" href="<?= h(app_url('ins/courses')); ?>">
                                    <span><?= h(substr((string) $course['title'], 0, 1)); ?></span>
                                    <div><strong><?= h(mb_strimwidth((string) $course['title'], 0, 44, '...')); ?></strong><small><?= h($course['category_name'] ?: 'Uncategorized'); ?></small></div>
                                </a>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </article>

                <article class="pid-card">
                    <div class="pid-card-head">
                        <div><span>Assessments</span><h3>Exam activity</h3></div>
                        <a href="<?= h(app_url('ins/exams')); ?>">Manage</a>
                    </div>
                    <div class="pid-mini-list">
                        <?php if (!$exams): ?>
                            <div class="pid-empty">No exam created.</div>
                        <?php else: ?>
                            <?php foreach (array_slice($exams, 0, 4) as $exam): ?>
                                <div class="pid-mini-row">
                                    <div><strong><?= h(mb_strimwidth((string) $exam['title'], 0, 40, '...')); ?></strong><small><?= h($exam['course_title'] ?: 'Unlinked'); ?></small></div>
                                    <span><?= h((string) ((int) ($exam['total_questions'] ?? 0))); ?> Q</span>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </article>

                <article class="pid-card">
                    <div class="pid-card-head">
                        <div><span>Support</span><h3>Student questions</h3></div>
                        <a href="<?= h(app_url('ins/questions')); ?>">Reply</a>
                    </div>
                    <div class="pid-mini-list">
                        <?php if (!$recentQuestions): ?>
                            <div class="pid-empty">No recent questions.</div>
                        <?php else: ?>
                            <?php foreach (array_slice($recentQuestions, 0, 4) as $question): ?>
                                <div class="pid-mini-row">
                                    <div><strong><?= h(mb_strimwidth((string) ($question['question_text'] ?? 'No question text'), 0, 44, '...')); ?></strong><small><?= h((string) ($question['student_name'] ?? 'Student')); ?></small></div>
                                    <span><?= h((string) ($question['status'] ?? 'new')); ?></span>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </article>
            </section>
        </div>

        <?php endif; ?>

        <section class="instructor-dashboard-hero">
            <div class="hero-copy">
                <span>Instructor Command Center</span>
                <h2>Welcome back, <?= h((string) $user['full_name']); ?></h2>
                <p>Today ka teaching control room: live classes, batches, students, questions aur exams ek jagah.</p>
            </div>
            <div class="hero-actions">
                <a class="btn btn-primary btn-wave" href="<?= h(app_url('ins/live')); ?>"><i class="bx bx-video me-1"></i>Live Studio</a>
                <a class="btn btn-primary-light btn-wave" href="<?= h(app_url('ins/classes')); ?>">Schedule Class</a>
                <a class="btn btn-primary-light btn-wave" href="<?= h(app_url('ins/courses')); ?>">Courses</a>
            </div>
        </section>

        <section class="instructor-kpi-grid">
            <a class="instructor-kpi-card" href="<?= h(app_url('ins/batches')); ?>">
                <span class="kpi-icon bg-primary-transparent text-primary"><i class="bx bx-layer"></i></span>
                <div>
                    <small>Active Batches</small>
                    <strong><?= h((string) $counts['batches']); ?></strong>
                </div>
            </a>
            <a class="instructor-kpi-card" href="<?= h(app_url('ins/classes')); ?>">
                <span class="kpi-icon bg-info-transparent text-info"><i class="bx bx-calendar-event"></i></span>
                <div>
                    <small>Total Classes</small>
                    <strong><?= h((string) $counts['classes']); ?></strong>
                </div>
            </a>
            <a class="instructor-kpi-card" href="<?= h(app_url('ins/live')); ?>">
                <span class="kpi-icon bg-success-transparent text-success"><i class="bx bx-broadcast"></i></span>
                <div>
                    <small>Live Now</small>
                    <strong><?= h((string) $counts['live']); ?></strong>
                </div>
            </a>
            <a class="instructor-kpi-card" href="<?= h(app_url('ins/courses')); ?>">
                <span class="kpi-icon bg-warning-transparent text-warning"><i class="bx bx-book-open"></i></span>
                <div>
                    <small>Courses</small>
                    <strong><?= h((string) count($courses)); ?></strong>
                </div>
            </a>
            <a class="instructor-kpi-card" href="<?= h(app_url('ins/questions')); ?>">
                <span class="kpi-icon bg-danger-transparent text-danger"><i class="bx bx-help-circle"></i></span>
                <div>
                    <small>Questions</small>
                    <strong><?= h((string) $questionCount); ?></strong>
                </div>
            </a>
            <a class="instructor-kpi-card" href="<?= h(app_url('ins/students')); ?>">
                <span class="kpi-icon bg-secondary-transparent text-secondary"><i class="bx bx-user"></i></span>
                <div>
                    <small>Students</small>
                    <strong><?= h((string) $totalEnrolledStudents); ?></strong>
                </div>
            </a>
        </section>

        <div class="ins-grid">
            <section class="settings-detail-card ins-card dashboard-classes-card">
                <div class="detail-head compact-head">
                    <div>
                        <span>Class Room</span>
                        <h2>Live and upcoming classes</h2>
                        <p>Online/offline sessions with action access and status timeline.</p>
                    </div>
                    <a class="modal-button" href="<?= h(app_url('ins/classes')); ?>">Manage</a>
                </div>
                <table class="role-access-table smart-table">
                    <thead>
                        <tr><th>Class</th><th>Batch</th><th>Date Time</th><th>Mode</th><th>Status</th><th>Action</th></tr>
                    </thead>
                    <tbody>
                        <?php if (!$classes): ?>
                            <tr><td colspan="6">No class scheduled yet.</td></tr>
                        <?php else: ?>
                            <?php foreach (array_slice($classes, 0, 7) as $class): ?>
                                <?php
                                $status = (string) ($class['class_status'] ?? 'scheduled');
                                $classDate = (string) ($class['class_date'] ?? '');
                                $classTime = substr((string) ($class['starts_at'] ?? ''), 0, 5);
                                $classWhen = trim($classDate . ' ' . $classTime);
                                ?>
                                <tr>
                                    <td><strong><?= h($class['class_title']); ?></strong></td>
                                    <td><?= h($class['batch_name'] ?: 'Open Batch'); ?></td>
                                    <td><?= h($classWhen !== '' ? $classWhen : 'Date not set'); ?></td>
                                    <td><?= h(ucfirst((string) $class['class_type'])); ?></td>
                                    <td><?= h(ucfirst($status)); ?></td>
                                    <td><a href="<?= h(app_url('ins/classes?id=' . (int) ($class['id'] ?? 0))); ?>">Open</a></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </section>

            <section class="settings-detail-card ins-card dashboard-batches-card">
                <div class="detail-head compact-head">
                    <div>
                        <span>Batch Board</span>
                        <h2>My active batches</h2>
                        <p>Capacity and status snapshot.</p>
                    </div>
                    <a class="modal-button ghost" href="<?= h(app_url('ins/batches')); ?>">Open</a>
                </div>
                <div class="simple-table-wrap">
                    <table class="simple-data-table">
                        <thead>
                            <tr>
                                <th>Batch</th>
                                <th>Course</th>
                                <th>Teacher</th>
                                <th>Seats</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!$batches): ?>
                                <tr><td colspan="5" class="empty-cell">Create your first teaching batch.</td></tr>
                            <?php else: ?>
                                <?php foreach (array_slice($batches, 0, 6) as $batch): ?>
                                    <tr>
                                        <td><strong><?= h($batch['batch_name']); ?></strong></td>
                                        <td><?= h($batch['course_title']); ?></td>
                                        <td><?= h($batch['teacher_name'] ?: 'Teacher not set'); ?></td>
                                        <td><?= h((string) $batch['capacity']); ?></td>
                                        <td><?= h(ucfirst((string) $batch['status'])); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </section>
        </div>

        <div class="ins-grid">
            <section class="settings-detail-card ins-card dashboard-courses-card">
                <div class="detail-head compact-head">
                    <div>
                        <span>Courses</span>
                        <h2>Recent course portfolio</h2>
                        <p>Latest created courses and structure.</p>
                    </div>
                    <a class="modal-button ghost" href="<?= h(app_url('ins/courses')); ?>">Open</a>
                </div>
                <div class="simple-table-wrap">
                    <table class="simple-data-table">
                        <thead>
                            <tr>
                                <th>Course</th>
                                <th>Category</th>
                                <th>Sub Category</th>
                                <th>ID</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!$courses): ?>
                                <tr><td colspan="4" class="empty-cell">No course found</td></tr>
                            <?php else: ?>
                                <?php foreach (array_slice($courses, 0, 5) as $course): ?>
                                    <tr>
                                        <td><strong><?= h($course['title']); ?></strong></td>
                                        <td><?= h($course['category_name'] ?: 'Uncategorized'); ?></td>
                                        <td><?= h($course['subcategory_name'] ?: 'No sub-category'); ?></td>
                                        <td><?= h((string) $course['id']); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </section>

            <section class="settings-detail-card ins-card dashboard-exams-card">
                <div class="detail-head compact-head">
                    <div>
                        <span>Exams</span>
                        <h2>Assessment activity</h2>
                        <p>Recent assessments, status and question coverage.</p>
                    </div>
                    <a class="modal-button" href="<?= h(app_url('ins/exams')); ?>">Manage</a>
                </div>
                <div class="simple-table-wrap">
                    <table class="simple-data-table">
                        <thead>
                            <tr>
                                <th>Exam</th>
                                <th>Course</th>
                                <th>Questions</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!$exams): ?>
                                <tr><td colspan="4" class="empty-cell">No exam created</td></tr>
                            <?php else: ?>
                                <?php foreach (array_slice($exams, 0, 5) as $exam): ?>
                                    <?php
                                    $questionCount = (int) ($exam['total_questions'] ?? 0);
                                    $examState = ((($exam['is_live'] ?? 0) ? 'Live' : 'Scheduled'));
                                    $examStatus = (string) ($exam['status'] ?? 'draft');
                                    ?>
                                    <tr>
                                        <td><strong><?= h($exam['title']); ?></strong></td>
                                        <td><?= h($exam['course_title'] ?: 'Unlinked'); ?></td>
                                        <td><?= h((string) $questionCount); ?></td>
                                        <td><?= h($examState); ?> / <?= h(ucfirst($examStatus)); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </section>
        </div>

        <section class="settings-detail-card ins-card">
            <div class="detail-head compact-head">
                <div>
                    <span>Workflow</span>
                    <h2>Instructor task desk</h2>
                    <p>Daily operations to keep classes, batches, and students aligned.</p>
                </div>
            </div>
            <div class="simple-table-wrap">
                <table class="simple-data-table">
                    <thead>
                        <tr><th>Step</th><th>Work</th><th>Details</th></tr>
                    </thead>
                    <tbody>
                        <tr><td>1</td><td><strong>Plan</strong></td><td>Create syllabus, schedule and resources one step ahead.</td></tr>
                        <tr><td>2</td><td><strong>Teach</strong></td><td>Run live/offline sessions with batch support.</td></tr>
                        <tr><td>3</td><td><strong>Track</strong></td><td>Follow up questions, attendance, and progress status.</td></tr>
                        <tr><td>4</td><td><strong>Report</strong></td><td>Review completed sessions and close unresolved items.</td></tr>
                    </tbody>
                </table>
            </div>
        </section>

        <section class="settings-detail-card ins-card">
            <div class="detail-head compact-head">
                <div>
                    <span>Student Questions</span>
                    <h2>Latest support requests</h2>
                    <p>Direct student queries waiting for instructor replies.</p>
                </div>
                <a class="modal-button ghost" href="<?= h(app_url('ins/questions')); ?>">View all</a>
            </div>
            <div class="simple-table-wrap">
                <table class="simple-data-table">
                    <thead>
                        <tr><th>Question</th><th>Course</th><th>Student</th><th>Status</th></tr>
                    </thead>
                    <tbody>
                        <?php if (!$recentQuestions): ?>
                            <tr><td colspan="4" class="empty-cell">No recent student questions in queue.</td></tr>
                        <?php else: ?>
                            <?php foreach ($recentQuestions as $question): ?>
                                <tr>
                                    <td><strong><?= h(mb_strimwidth((string) ($question['question_text'] ?? 'No question text'), 0, 90, '...')); ?></strong></td>
                                    <td><?= h((string) ($question['course_title'] ?? 'Course not set')); ?></td>
                                    <td><?= h((string) ($question['student_name'] ?? 'Student')); ?></td>
                                    <td><?= h((string) ($question['status'] ?? 'new')); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>

        <section class="settings-detail-card ins-card">
            <div class="detail-head compact-head">
                <div>
                    <span>Enrollment & Social</span>
                    <h2>Audience growth snapshot</h2>
                    <p>Live metrics for your teaching panel and reach.</p>
                </div>
            </div>
            <div class="simple-table-wrap">
                <table class="simple-data-table">
                    <thead>
                        <tr><th>Metric</th><th>Total</th><th>Details</th></tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td><strong>Total students enrolled</strong></td>
                            <td><?= h((string) $totalEnrolledStudents); ?></td>
                            <td>Active enrollments: <?= h((string) $activeEnrolledStudents); ?></td>
                        </tr>
                        <tr>
                            <td><strong>Total followers</strong></td>
                            <td><?= h((string) $instructorFollowerCount); ?></td>
                            <td>Students following your instructor profile</td>
                        </tr>
                    </tbody>
                </table>
            </div>

        <div class="detail-head compact-head">
            <div><span>Course Wise Enrollment</span><h2>Breakdown (top 5)</h2></div>
        </div>
        <table class="role-access-table smart-table compact-summary-table">
            <thead>
                <tr>
                    <th class="course-col">Course</th>
                    <th class="total-col">Total</th>
                    <th class="active-col">Active</th>
                    <th class="ratio-col">Active %</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!$courseEnrollStats): ?>
                    <tr><td colspan="4">No course enrollment data yet.</td></tr>
                <?php else: ?>
                    <?php foreach (array_slice($courseEnrollStats, 0, 5) as $courseEnroll): ?>
                        <?php
                        $enrolledTotal = (int) ($courseEnroll['enrolled_students'] ?? 0);
                        $activeCourseEnroll = (int) ($courseEnroll['active_enrollments'] ?? 0);
                        $enrollmentRatio = $enrolledTotal > 0 ? round(($activeCourseEnroll * 100) / $enrolledTotal, 1) : 0;
                        ?>
                        <tr>
                            <td>
                                <strong><?= h($courseEnroll['title']); ?></strong>
                                <small>Course ID: <?= h((string) $courseEnroll['id']); ?></small>
                            </td>
                            <td><?= h((string) $enrolledTotal); ?></td>
                            <td><?= h((string) $activeCourseEnroll); ?></td>
                            <td><?= h((string) $enrollmentRatio); ?>%</td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
        <div class="detail-head compact-head">
            <div><span>Follower Trend</span><h2>Last 7 days</h2></div>
        </div>
        <div class="simple-table-wrap">
            <table class="simple-data-table">
                <thead>
                    <tr><th>Period</th><th>Followers</th><th>Note</th></tr>
                </thead>
                <tbody>
                    <tr><td><strong>Today</strong></td><td><?= h((string) $followersToday); ?></td><td>New profile follows today</td></tr>
                    <tr><td><strong>This week</strong></td><td><?= h((string) $followersThisWeek); ?></td><td>Followers from the last 7 days</td></tr>
                </tbody>
            </table>
        </div>

        <div class="detail-head compact-head">
            <div><span>Follower mini trend</span><h2>Last 7 days</h2></div>
        </div>
        <div class="simple-table-wrap">
            <table class="simple-data-table">
                <thead>
                    <tr><th>Day</th><th>Date</th><th>Follows</th></tr>
                </thead>
                <tbody>
                    <?php if (!$followerTrendRows): ?>
                        <tr><td colspan="3" class="empty-cell">Follower trend will show from this week onward.</td></tr>
                    <?php else: ?>
                        <?php foreach ($followerTrendRows as $trend): ?>
                            <tr>
                                <td><strong><?= h(date('D', strtotime($trend['day']))); ?></strong></td>
                                <td><?= h((string) $trend['day']); ?></td>
                                <td><?= h((string) ((int) ($trend['count'] ?? 0))); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

            <div class="detail-head compact-head">
                <div><span>Recent Followers</span><h2>Latest 8 joins</h2></div>
            </div>
            <div class="simple-table-wrap">
                <table class="simple-data-table">
                    <thead>
                        <tr><th>Name</th><th>Email</th><th>Joined</th></tr>
                    </thead>
                    <tbody>
                        <?php if (!$recentFollowers): ?>
                            <tr><td colspan="3" class="empty-cell">New followers will appear as soon as students follow your profile.</td></tr>
                        <?php else: ?>
                            <?php foreach ($recentFollowers as $follower): ?>
                                <?php $fName = trim((string) ($follower['full_name'] ?: 'Student')); ?>
                                <tr>
                                    <td><strong><?= h($fName); ?></strong></td>
                                    <td><?= h($follower['email'] ?: 'No email'); ?></td>
                                    <td><?= h($timeAgo((string) ($follower['created_at'] ?? ''))); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>

        <section class="settings-detail-card ins-card">
            <div class="detail-head compact-head">
                <div>
                    <span>Live Snapshot</span>
                    <h2>Now and next</h2>
                    <p>Immediate view for classes to launch first.</p>
                </div>
            </div>
            <div class="simple-table-wrap">
                <table class="simple-data-table">
                    <thead>
                        <tr><th>Class</th><th>Batch</th><th>Date Time</th><th>Type</th><th>Status</th></tr>
                    </thead>
                    <tbody>
                        <?php if (!$liveNow && !$upcomingClasses): ?>
                            <tr><td colspan="5" class="empty-cell">Create a live/upcoming session from class manager.</td></tr>
                        <?php else: ?>
                            <?php foreach (array_slice($liveNow, 0, 2) as $liveItem): ?>
                                <tr>
                                    <td><strong><?= h($liveItem['class_title']); ?></strong></td>
                                    <td><?= h($liveItem['batch_name'] ?: 'Open'); ?></td>
                                    <td>Now</td>
                                    <td><?= h($liveItem['class_type']); ?></td>
                                    <td>Live</td>
                                </tr>
                            <?php endforeach; ?>
                            <?php foreach (array_slice($upcomingClasses, 0, 2) as $upItem): ?>
                                <tr>
                                    <td><strong><?= h($upItem['class_title']); ?></strong></td>
                                    <td><?= h($upItem['batch_name'] ?: 'Open'); ?></td>
                                    <td><?= h($upItem['class_date']); ?> <?= h(substr((string) $upItem['starts_at'], 0, 5)); ?></td>
                                    <td><?= h((string) $upItem['class_type']); ?></td>
                                    <td><?= h(ucfirst((string) ($upItem['class_status'] ?: 'scheduled'))); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>
    </section>
</main>
<?php include __DIR__ . '/includes/footer.php'; ?>

