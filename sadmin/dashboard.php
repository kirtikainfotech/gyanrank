<?php
require_once __DIR__ . '/../config.php';

function sadmin_table_exists(string $table): bool
{
    static $cache = [];
    if (array_key_exists($table, $cache)) {
        return $cache[$table];
    }
    $result = db()->query("SHOW TABLES LIKE '" . db()->real_escape_string($table) . "'");
    $cache[$table] = (bool) $result && $result->num_rows > 0;
    return $cache[$table];
}

function sadmin_fetch_all(string $sql, string $types = '', array $params = []): array
{
    try {
        $stmt = db()->prepare($sql);
        if ($stmt === false) {
            return [];
        }
        if ($types !== '' && $params !== []) {
            $bound = [];
            foreach ($params as $key => $value) {
                $bound[$key] = &$params[$key];
            }
            $stmt->bind_param($types, ...$bound);
        }
        $stmt->execute();
        $result = $stmt->get_result();
        if (!$result) {
            return [];
        }
        return $result->fetch_all(MYSQLI_ASSOC);
    } catch (Throwable $e) {
        return [];
    }
}

function sadmin_fetch_one(string $sql, string $types = '', array $params = []): array
{
    $rows = sadmin_fetch_all($sql, $types, $params);
    return $rows[0] ?? [];
}

function sadmin_fetch_count(string $sql, string $types = '', array $params = []): int
{
    $row = sadmin_fetch_one($sql, $types, $params);
    if (!$row) {
        return 0;
    }
    $first = reset($row);
    return (int) ((is_numeric((string) $first) ? (string) $first : 0));
}

$user = require_login('superadmin');

$licenseActive = is_license_active();
$licenseRow = sadmin_fetch_one(
    'SELECT status, starts_at, expires_at FROM licenses WHERE product_name = ? ORDER BY id DESC LIMIT 1',
    's',
    [APP_NAME]
);
$licenseStatus = (string) ($licenseRow['status'] ?? 'inactive');
$licenseStartsAt = (string) ($licenseRow['starts_at'] ?? '');
$licenseExpiresAt = (string) ($licenseRow['expires_at'] ?? '');
$licenseDaysLeft = '';
if ($licenseExpiresAt !== '') {
    $timeLeft = strtotime($licenseExpiresAt) - strtotime(date('Y-m-d'));
    $licenseDaysLeft = (string) max(0, (int) floor($timeLeft / 86400));
}

$pageTitle = 'Super Admin Dashboard';
$pageSubtitle = 'Complete system analytics, security status, enrolment, plans, and content operations.';
$activePage = 'dashboard';

$roleSummary = sadmin_fetch_all('SELECT r.id, r.slug, r.name, COALESCE(COUNT(u.id), 0) AS total_users FROM roles r LEFT JOIN users u ON u.role_id = r.id GROUP BY r.id, r.slug, r.name ORDER BY r.id ASC');
$rolesBySlug = [];
$totalUsers = 0;
foreach ($roleSummary as $row) {
    $slug = (string) ($row['slug'] ?? '');
    $rolesBySlug[$slug] = (int) ($row['id'] ?? 0);
    $totalUsers += (int) ($row['total_users'] ?? 0);
}
$superAdminRoleId = (int) ($rolesBySlug['superadmin'] ?? 0);
$adminRoleId = (int) ($rolesBySlug['admin'] ?? 0);
$instructorRoleId = (int) ($rolesBySlug['instructor'] ?? 0);
$studentRoleId = (int) ($rolesBySlug['student'] ?? 0);

$userStatusRows = sadmin_fetch_all('SELECT status, COUNT(*) AS total FROM users GROUP BY status');
$userStatusSummary = [];
foreach ($userStatusRows as $row) {
    $userStatusSummary[(string) ($row['status'] ?? 'unknown')] = (int) ($row['total'] ?? 0);
}

$usersActive = $userStatusSummary['active'] ?? 0;
$usersBlocked = $userStatusSummary['blocked'] ?? 0;
$usersInactive = $userStatusSummary['inactive'] ?? 0;
$usersPending = $userStatusSummary['pending'] ?? 0;

$totalAdmins = 0;
$totalSuperAdmins = 0;
$totalInstructors = 0;
$totalStudents = 0;
foreach ($roleSummary as $row) {
    $slug = (string) ($row['slug'] ?? '');
    $count = (int) ($row['total_users'] ?? 0);
    if ($slug === 'superadmin' || $slug === 'super_admin') {
        $totalSuperAdmins += $count;
    } elseif ($slug === 'admin') {
        $totalAdmins += $count;
    } elseif ($slug === 'instructor') {
        $totalInstructors += $count;
    } elseif ($slug === 'student') {
        $totalStudents += $count;
    }
}

$categoryRows = sadmin_fetch_one(
    "SELECT COUNT(*) AS total, SUM(status='active') AS active_total FROM course_categories"
);
$totalCategories = (int) ($categoryRows['total'] ?? 0);
$activeCategories = (int) ($categoryRows['active_total'] ?? 0);

$courseRows = sadmin_fetch_one(
    "SELECT COUNT(*) AS total, SUM(status='published') AS published, SUM(status='paused') AS paused, SUM(status='draft') AS draft FROM instructor_courses"
);
$totalCourses = (int) ($courseRows['total'] ?? 0);
$publishedCourses = (int) ($courseRows['published'] ?? 0);
$draftCourses = (int) ($courseRows['draft'] ?? 0);
$pausedCourses = (int) ($courseRows['paused'] ?? 0);

$contentRows = sadmin_fetch_one(
    "SELECT COUNT(*) AS total, SUM(content_type='video_upload') AS videos, SUM(content_type='youtube') AS youtube, SUM(content_type='pdf') AS pdfs, SUM(content_type='live') AS live_sessions FROM instructor_course_contents"
);
$totalContents = (int) ($contentRows['total'] ?? 0);
$videoContents = (int) ($contentRows['videos'] ?? 0);
$youtubeContents = (int) ($contentRows['youtube'] ?? 0);
$pdfContents = (int) ($contentRows['pdfs'] ?? 0);
$liveContents = (int) ($contentRows['live_sessions'] ?? 0);

$batchRows = sadmin_fetch_one(
    "SELECT COUNT(*) AS total, SUM(status='active') AS active, SUM(status='paused') AS paused, SUM(status='completed') AS completed FROM instructor_batches"
);
$totalBatches = (int) ($batchRows['total'] ?? 0);
$activeBatches = (int) ($batchRows['active'] ?? 0);
$pausedBatches = (int) ($batchRows['paused'] ?? 0);
$completedBatches = (int) ($batchRows['completed'] ?? 0);

$classRows = sadmin_fetch_one(
    "SELECT COUNT(*) AS total, SUM(class_status='scheduled') AS scheduled, SUM(class_status='live') AS live, SUM(class_status='completed') AS completed, SUM(class_status='cancelled') AS cancelled FROM instructor_classes"
);
$totalClasses = (int) ($classRows['total'] ?? 0);
$liveClasses = (int) ($classRows['live'] ?? 0);
$scheduledClasses = (int) ($classRows['scheduled'] ?? 0);
$completedClasses = (int) ($classRows['completed'] ?? 0);

$planRows = sadmin_fetch_one(
    "SELECT COUNT(*) AS total, SUM(is_active=1) AS active FROM student_membership_plans"
);
$membershipPlans = (int) ($planRows['total'] ?? 0);
$membershipPlansActive = (int) ($planRows['active'] ?? 0);

$activeSubscriptions = sadmin_fetch_count('SELECT COUNT(*) AS total FROM student_plan_subscriptions WHERE status = "active"');
$totalSubscriptions = sadmin_fetch_count('SELECT COUNT(*) AS total FROM student_plan_subscriptions');

$enrollmentRows = sadmin_fetch_one(
    "SELECT COUNT(*) AS total, SUM(status='active') AS active, SUM(status='completed') AS completed FROM student_course_enrollments"
);
$courseEnrollments = (int) ($enrollmentRows['total'] ?? 0);
$activeEnrollments = (int) ($enrollmentRows['active'] ?? 0);
$completedEnrollments = (int) ($enrollmentRows['completed'] ?? 0);

$purchaseRows = sadmin_fetch_one(
    "SELECT COUNT(*) AS total, SUM(payment_status='paid') AS paid, SUM(payment_status='pending') AS pending, SUM(payment_status='failed') AS failed, COALESCE(SUM(amount),0) AS total_amount FROM student_course_purchases"
);
$totalPurchases = (int) ($purchaseRows['total'] ?? 0);
$paidPurchases = (int) ($purchaseRows['paid'] ?? 0);
$pendingPurchases = (int) ($purchaseRows['pending'] ?? 0);
$failedPurchases = (int) ($purchaseRows['failed'] ?? 0);
$purchaseTotalAmount = (float) ($purchaseRows['total_amount'] ?? 0);

$supportRows = sadmin_fetch_one(
    "SELECT COUNT(*) AS total, SUM(status='active') AS active, SUM(status='inactive') AS inactive FROM support_assignments"
);
$supportAssignments = (int) ($supportRows['total'] ?? 0);
$supportActiveAssignments = (int) ($supportRows['active'] ?? 0);
$supportInactiveAssignments = (int) ($supportRows['inactive'] ?? 0);

$assignmentTypeRows = sadmin_fetch_all("SELECT assignment_type, COUNT(*) AS total FROM support_assignments GROUP BY assignment_type");
$supportByType = [];
foreach ($assignmentTypeRows as $row) {
    $supportByType[(string) ($row['assignment_type'] ?? '')] = (int) ($row['total'] ?? 0);
}

$loginAttemptRows = sadmin_fetch_one(
    "SELECT COUNT(*) AS total, SUM(success = 0) AS failed, SUM(success = 1) AS success_count FROM login_attempts WHERE attempted_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)"
);
$loginAttempts24h = (int) ($loginAttemptRows['total'] ?? 0);
$loginFailed24h = (int) ($loginAttemptRows['failed'] ?? 0);
$loginSuccess24h = (int) ($loginAttemptRows['success_count'] ?? 0);

$instructorHealthRows = sadmin_fetch_one(
    "SELECT COUNT(*) AS total_instructors, SUM(COALESCE(approval_status, 'pending') = 'approved') AS approved_instructors, SUM(COALESCE(approval_status, 'pending') = 'pending') AS pending_instructors
     FROM users u
     INNER JOIN roles r ON r.id = u.role_id
     LEFT JOIN instructor_profiles p ON p.user_id = u.id
     WHERE r.slug = 'instructor'"
);
$instructorHealth = [
    'total' => (int) ($instructorHealthRows['total_instructors'] ?? 0),
    'approved' => (int) ($instructorHealthRows['approved_instructors'] ?? 0),
    'pending' => (int) ($instructorHealthRows['pending_instructors'] ?? 0),
];

$systemHealthScore = 0;
if ($licenseActive) {
    $systemHealthScore += 22;
}
if ($totalCourses > 0) {
    $systemHealthScore += 10;
}
if ($publishedCourses > 0) {
    $systemHealthScore += 10;
}
if ($membershipPlans > 0) {
    $systemHealthScore += 10;
}
if ($activeSubscriptions > 0) {
    $systemHealthScore += 10;
}
if ($totalContents > 0) {
    $systemHealthScore += 8;
}
if ($usersBlocked === 0) {
    $systemHealthScore += 8;
}
if ($loginFailed24h <= 2) {
    $systemHealthScore += 10;
}
if ($supportActiveAssignments > 0) {
    $systemHealthScore += 8;
}
if ($systemHealthScore > 100) {
    $systemHealthScore = 100;
}

if ($systemHealthScore >= 80) {
    $systemHealthStatus = 'Excellent';
    $systemHealthClass = 'ready';
} elseif ($systemHealthScore >= 60) {
    $systemHealthStatus = 'Good';
    $systemHealthClass = 'warning';
} else {
    $systemHealthStatus = 'Needs attention';
    $systemHealthClass = 'empty';
}

$systemSnapshotIssues = [];
if (!$licenseActive) {
    $systemSnapshotIssues[] = 'License inactive';
}
if ($usersBlocked > 0) {
    $systemSnapshotIssues[] = 'Some users are blocked';
}
if ($loginFailed24h >= 10) {
    $systemSnapshotIssues[] = 'High login failures in 24h';
}
if (($instructorHealth['pending'] ?? 0) > 0) {
    $systemSnapshotIssues[] = 'Instructor approvals pending';
}
if (($publishedCourses <= 0) && $usersActive > 0) {
    $systemSnapshotIssues[] = 'No published course activity';
}

$systemSnapshotIssueText = $systemSnapshotIssues !== [] ? implode(' | ', $systemSnapshotIssues) : 'No critical issue';

$progressRows = sadmin_fetch_one(
    "SELECT COUNT(*) AS total, COALESCE(ROUND(AVG(progress_percent), 2), 0) AS avg_progress, SUM(completed_at IS NOT NULL) AS completed FROM student_content_progress"
);
$contentProgressCount = (int) ($progressRows['total'] ?? 0);
$avgContentProgress = (float) ($progressRows['avg_progress'] ?? 0);
$completedContentProgress = (int) ($progressRows['completed'] ?? 0);

$achievementRows = sadmin_fetch_one(
    "SELECT COUNT(*) AS total, COALESCE(ROUND(AVG(stars), 2), 0) AS avg_stars, COALESCE(ROUND(AVG(progress_percent), 2), 0) AS avg_progress FROM student_course_achievements"
);
$studentAchievements = (int) ($achievementRows['total'] ?? 0);
$avgStars = (float) ($achievementRows['avg_stars'] ?? 0);
$achievementAvgProgress = (float) ($achievementRows['avg_progress'] ?? 0);

$examRows = sadmin_fetch_one(
    "SELECT COUNT(*) AS total, COALESCE(ROUND(AVG(percentage), 2), 0) AS avg_percentage, SUM(status='submitted') AS submitted, SUM(status='review') AS review FROM student_exam_attempts"
);
$examAttempts = (int) ($examRows['total'] ?? 0);
$avgExamPercent = (float) ($examRows['avg_percentage'] ?? 0);
$examSubmitted = (int) ($examRows['submitted'] ?? 0);
$examInReview = (int) ($examRows['review'] ?? 0);

$pendingInstructorRows = sadmin_fetch_all(
    "SELECT u.id, u.full_name, u.email, u.created_at, p.approval_status
     FROM users u
     INNER JOIN roles r ON r.id = u.role_id
     LEFT JOIN instructor_profiles p ON p.user_id = u.id
     WHERE r.slug = 'instructor'
     ORDER BY (p.approval_status = 'pending') DESC, u.created_at DESC
     LIMIT 10"
);
$recentInstructorApprovals = 0;
foreach ($pendingInstructorRows as $row) {
    if ((string) ($row['approval_status'] ?? '') === 'pending') {
        $recentInstructorApprovals++;
    }
}

$latestUsers = sadmin_fetch_all(
    "SELECT u.id, u.full_name, u.username, u.email, u.created_at, u.status, r.name AS role_name
     FROM users u
     INNER JOIN roles r ON r.id = u.role_id
     ORDER BY u.created_at DESC
     LIMIT 10"
);

$latestEnrollments = sadmin_fetch_all(
    "SELECT e.id, e.enrolled_at, e.status, e.progress_percent, u.full_name AS student_name, u.email AS student_email, c.title AS course_title, iu.full_name AS instructor_name
     FROM student_course_enrollments e
     INNER JOIN users u ON u.id = e.student_id
     INNER JOIN instructor_courses c ON c.id = e.course_id
     LEFT JOIN users iu ON iu.id = c.instructor_id
     ORDER BY e.enrolled_at DESC
     LIMIT 10"
);

$latestPurchases = sadmin_fetch_all(
    "SELECT p.id, p.created_at, p.amount, p.payment_status, p.transaction_no, u.full_name AS student_name, c.title AS course_title, p.payment_method
     FROM student_course_purchases p
     LEFT JOIN users u ON u.id = p.student_id
     LEFT JOIN instructor_courses c ON c.id = p.course_id
     ORDER BY p.created_at DESC
     LIMIT 8"
);

$topCourses = sadmin_fetch_all(
    "SELECT c.id, c.title, u.full_name AS instructor_name,
            COUNT(DISTINCT e.student_id) AS enrolled_count,
            COUNT(DISTINCT e.id) AS active_enrollments,
            COUNT(DISTINCT cc.id) AS total_content
     FROM instructor_courses c
     INNER JOIN users u ON u.id = c.instructor_id
     LEFT JOIN student_course_enrollments e ON e.course_id = c.id
     LEFT JOIN instructor_course_contents cc ON cc.course_id = c.id
     GROUP BY c.id, c.title, u.full_name
     ORDER BY enrolled_count DESC, c.title ASC
     LIMIT 8"
);

$popularContent = sadmin_fetch_all(
    "SELECT ic.id, ic.content_title, ic.content_type, c.title AS course_title, COALESCE(COUNT(l.id), 0) AS like_count
     FROM instructor_course_contents ic
     INNER JOIN instructor_courses c ON c.id = ic.course_id
     LEFT JOIN student_content_likes l ON l.content_id = ic.id
     GROUP BY ic.id, ic.content_title, ic.content_type, c.title
     ORDER BY like_count DESC, ic.id DESC
     LIMIT 8"
);

$nextClasses = sadmin_fetch_all(
    "SELECT ic.id, ic.class_title, ic.class_type, ic.class_status, ic.class_date, ic.starts_at,
            b.batch_name, c.title AS course_title
     FROM instructor_classes ic
     LEFT JOIN instructor_batches b ON b.id = ic.batch_id AND b.instructor_id = ic.instructor_id
     LEFT JOIN instructor_courses c ON c.instructor_id = ic.instructor_id
     WHERE ic.class_status = 'live' OR ic.class_date >= CURDATE()
     ORDER BY ic.class_status = 'live' DESC, ic.class_date ASC, ic.starts_at ASC
     LIMIT 10"
);

$instructorDirectory = sadmin_fetch_all(
    "SELECT u.id, u.full_name, u.username, u.email, u.status AS user_status,
            COALESCE(ip.approval_status, 'pending') AS approval_status,
            (SELECT COUNT(*) FROM instructor_courses c WHERE c.instructor_id = u.id) AS total_courses,
            (SELECT COUNT(*) FROM instructor_batches b WHERE b.instructor_id = u.id) AS total_batches,
            (SELECT COUNT(DISTINCT e.student_id) FROM student_course_enrollments e INNER JOIN instructor_courses c2 ON c2.id = e.course_id WHERE c2.instructor_id = u.id) AS total_students,
            (SELECT COUNT(*) FROM student_instructor_follows f2 WHERE f2.instructor_id = u.id) AS total_followers,
            (SELECT COUNT(*) FROM instructor_classes cl WHERE cl.instructor_id = u.id AND cl.class_status = 'live') AS live_classes,
            (SELECT COUNT(*) FROM instructor_classes cl2 WHERE cl2.instructor_id = u.id AND cl2.class_status = 'scheduled') AS scheduled_classes
     FROM users u
     INNER JOIN roles r ON r.id = u.role_id
     LEFT JOIN instructor_profiles ip ON ip.user_id = u.id
     WHERE r.slug = 'instructor'
     ORDER BY total_followers DESC, total_courses DESC, u.full_name ASC
     LIMIT 12"
);

$planLeaderboard = sadmin_fetch_all(
    "SELECT m.id, m.plan_name, m.plan_slug, m.monthly_price, m.validity_days, m.video_limit, m.pdf_limit, m.exam_limit, m.mock_test_limit,
            COUNT(CASE WHEN s.status = 'active' THEN s.id END) AS active_subscribers
     FROM student_membership_plans m
     LEFT JOIN student_plan_subscriptions s ON s.plan_id = m.id
     GROUP BY m.id, m.plan_name, m.plan_slug, m.monthly_price, m.validity_days, m.video_limit, m.pdf_limit, m.exam_limit, m.mock_test_limit
     ORDER BY active_subscribers DESC, m.sort_order ASC
     LIMIT 8"
);

function dashboard_status_label(int $n): string
{
    return max(0, $n) === 0 ? 'Not Started' : 'Available';
}

$quickActions = [
    ['label' => 'Instructors', 'sub' => 'Manage instructor approvals, KYC and access', 'icon' => 'M16 21v-2a4 4 0 0 0-8 0v2M12 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8m5 2 2 2 4-4', 'url' => app_url('sadmin/instructors')],
    ['label' => 'Categories', 'sub' => 'Master, subcategory and status control', 'icon' => 'M4 5h7l2 2h7v12H4V5Zm3 5h10M7 14h7', 'url' => app_url('sadmin/categories')],
    ['label' => 'Settings', 'sub' => 'General, branding, billing and plans', 'icon' => 'M12 15.5a3.5 3.5 0 1 0 0-7 3.5 3.5 0 0 0 0 7Zm0-13v3m0 13v3M4.2 4.2l2.1 2.1m11.4 11.4 2.1 2.1M1 12h3m16 0h3M4.2 19.8l2.1-2.1M17.7 6.3l2.1-2.1', 'url' => app_url('sadmin/settings')],
    ['label' => 'Staff & Roles', 'sub' => 'Role permissions, salary and support hierarchy', 'icon' => 'M16 21v-2a4 4 0 0 0-8 0v2M12 12a4 4 0 1 0 0-8 4 4 0 0 0 0 8M19 10v6m3-3h-6', 'url' => app_url('sadmin/settings-staff')],
    ['label' => 'Mail & Notifications', 'sub' => 'SMTP, Firebase and test email settings', 'icon' => 'M4 6h16v12H4V6Zm0 2 8 6 8-6', 'url' => app_url('sadmin/settings-mail')],
    ['label' => 'Billing', 'sub' => 'Invoice format, GST and tax rules', 'icon' => 'M7 3h10v18l-3-2-2 2-2-2-3 2V3Zm2 5h6m-6 4h6m-6 4h3', 'url' => app_url('sadmin/settings-billing')],
];
?>
<?php include __DIR__ . '/includes/header.php'; ?>
<?php include __DIR__ . '/includes/sidebar.php'; ?>
<main class="main">
    <?php include __DIR__ . '/includes/topbar.php'; ?>
    <?php include __DIR__ . '/includes/dashboard-cockpit.php'; ?>
<?php include __DIR__ . '/includes/footer.php'; ?>
<?php return; ?>

    <section class="content sadmin-dashboard-page">
        <style>
            .sadmin-dashboard-page {
                --sadmin-pad-x: 12px;
            }
            .sadmin-dashboard-page .notice {
                margin-bottom: 10px;
                padding: 10px 12px;
                border-left-width: 3px;
                border-radius: 7px;
            }
            .sadmin-dashboard-page .stats {
                grid-template-columns: repeat(6, minmax(0, 1fr));
                gap: 9px;
            }
            .sadmin-dashboard-page .stat-card {
                padding: 10px 11px;
            }
            .sadmin-dashboard-page .stat-card span {
                font-size: 11px;
                margin-bottom: 5px;
            }
            .sadmin-dashboard-page .stat-card strong {
                margin-top: 6px;
                font-size: 22px;
            }
            .sadmin-dashboard-page .detail-head {
                padding-bottom: 8px;
                margin-bottom: 9px;
                gap: 9px;
            }
            .sadmin-dashboard-page .detail-head h2 {
                margin: 0;
                font-size: 16px;
            }
            .sadmin-dashboard-page .detail-head p {
                font-size: 12px;
                line-height: 1.35;
            }
            .sadmin-dashboard-page .detail-head span {
                margin-bottom: 3px;
                padding: 3px 7px;
                font-size: 10px;
            }
            .sadmin-dashboard-page .settings-detail-card {
                padding: 12px;
            }
            .sadmin-dashboard-page .settings-grid-menu {
                gap: 10px;
            }
            .sadmin-dashboard-page .settings-card-link {
                padding: 11px 11px 11px 12px;
                gap: 10px;
            }
            .sadmin-dashboard-page .snapshot-inline {
                display: grid;
                grid-template-columns: repeat(3, minmax(0, 1fr));
                gap: 8px;
                margin-bottom: 8px;
            }
            .sadmin-dashboard-page .snapshot-inline .stat-card {
                padding: 8px 9px;
            }
            .sadmin-dashboard-page .snapshot-inline .stat-card span {
                font-size: 10px;
                margin-bottom: 2px;
                text-transform: none;
            }
            .sadmin-dashboard-page .snapshot-inline .stat-card strong {
                margin-top: 0;
                font-size: 18px;
                line-height: 1.2;
            }
            .sadmin-dashboard-page .ins-grid {
                gap: 10px;
            }
            .sadmin-dashboard-page .role-table-wrap {
                max-height: none;
            }
            .sadmin-dashboard-page .role-access-table th,
            .sadmin-dashboard-page .role-access-table td {
                padding: 6px 7px;
                font-size: 12px;
            }
            .sadmin-dashboard-page .role-access-table th {
                font-size: 10px;
            }
            .sadmin-dashboard-page .role-access-table td strong {
                font-size: 12px;
                margin-bottom: 2px;
            }
            .sadmin-dashboard-page .role-access-table td small {
                font-size: 11px;
                max-width: 240px;
            }
            .sadmin-dashboard-page .sadmin-instructor-table th:last-child,
            .sadmin-dashboard-page .sadmin-instructor-table td:last-child {
                width: auto;
                text-align: left;
            }
@media (max-width: 1360px) {
                .sadmin-dashboard-page .stats {
                    grid-template-columns: repeat(4, minmax(0, 1fr));
                }
                .sadmin-dashboard-page .snapshot-inline {
                    grid-template-columns: repeat(2, minmax(0, 1fr));
                }
            }
            @media (max-width: 1000px) {
                .sadmin-dashboard-page .stats {
                    grid-template-columns: repeat(2, minmax(0, 1fr));
                }
            }
        </style>
        <div class="notice <?= $licenseActive ? '' : 'danger'; ?>">
            <?= $licenseActive
                ? 'License active. Expires on ' . h($licenseExpiresAt ?: 'N/A') . ' (' . h($licenseDaysLeft) . ' day' . ($licenseDaysLeft === '1' ? '' : 's') . ' left).'
                : 'License is inactive/expired for ' . h(APP_NAME) . '. Setup blocks are auto-disabled until renewal.'; ?>
        </div>

        <div class="stats">
            <article class="stat-card">
                <span>Total Users</span>
                <strong><?= h((string) $totalUsers); ?></strong>
            </article>
            <article class="stat-card">
                <span>Active / Blocked</span>
                <strong><?= h((string) $usersActive); ?> / <?= h((string) $usersBlocked); ?></strong>
            </article>
            <article class="stat-card">
                <span>Instructors</span>
                <strong><?= h((string) $totalInstructors); ?></strong>
            </article>
            <article class="stat-card">
                <span>Students</span>
                <strong><?= h((string) $totalStudents); ?></strong>
            </article>
            <article class="stat-card">
                <span>Courses</span>
                <strong><?= h((string) $totalCourses); ?></strong>
            </article>
            <article class="stat-card">
                <span>Published Courses</span>
                <strong><?= h((string) $publishedCourses); ?></strong>
            </article>
            <article class="stat-card">
                <span>Course Chapters</span>
                <strong><?= h((string) $totalContents); ?></strong>
            </article>
            <article class="stat-card">
                <span>Categories</span>
                <strong><?= h((string) $totalCategories); ?></strong>
            </article>
            <article class="stat-card">
                <span>Live Classes</span>
                <strong><?= h((string) $liveClasses); ?></strong>
            </article>
            <article class="stat-card">
                <span>Active Enrollments</span>
                <strong><?= h((string) $activeEnrollments); ?></strong>
            </article>
            <article class="stat-card">
                <span>Paid Purchases</span>
                <strong><?= h((string) $paidPurchases); ?></strong>
            </article>
            <article class="stat-card">
                <span>Active Plans</span>
                <strong><?= h((string) $activeSubscriptions); ?></strong>
            </article>
            <article class="stat-card">
                <span>Pending Instructor Approval</span>
                <strong><?= h((string) $recentInstructorApprovals); ?></strong>
            </article>
        </div>

        <section class="settings-detail-card">
            <div class="detail-head compact-head">
                <div>
                    <span>Quick Control</span>
                    <h2>Open every major area in one click</h2>
                    <p>Use these actions to manage users, categories, finance, settings, and communication from one place.</p>
                </div>
            </div>
            <div class="settings-grid-menu">
                <?php foreach ($quickActions as $action): ?>
                    <a class="settings-card-link" href="<?= h($action['url']); ?>">
                        <span class="settings-card-icon"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="<?= h($action['icon']); ?>"></path></svg></span>
                        <div>
                            <h3><?= h($action['label']); ?></h3>
                            <p><?= h($action['sub']); ?></p>
                        </div>
                        <small>Open &rarr;</small>
                    </a>
                <?php endforeach; ?>
            </div>
        </section>

        <section class="settings-detail-card">
            <div class="detail-head compact-head">
                <div>
                    <span>System Snapshot</span>
                    <h2>Role, license, classes and support health</h2>
                    <p>Core operational totals and health state after last background updates.</p>
                </div>
            </div>
            <div class="snapshot-inline">
                <article class="stat-card">
                    <span>System Health</span>
                    <strong><?= h((string) $systemHealthScore); ?>/100</strong>
                    <small><span class="status-pill <?= h($systemHealthClass); ?>"><?= h($systemHealthStatus); ?></span></small>
                </article>
                <article class="stat-card">
                    <span>Instructor Approval</span>
                    <strong><?= h((string) $instructorHealth['approved']); ?> / <?= h((string) $instructorHealth['total']); ?></strong>
                    <small>approved (pending: <?= h((string) $instructorHealth['pending']); ?>)</small>
                </article>
                <article class="stat-card">
                    <span>Activity Window</span>
                    <strong><?= h((string) $loginSuccess24h); ?> / <?= h((string) $loginAttempts24h); ?></strong>
                    <small>24h success / attempts</small>
                </article>
            </div>
            <table class="role-access-table smart-table">
                <thead>
                    <tr><th>Metric</th><th>Value</th><th>Status</th><th>Note</th></tr>
                </thead>
                <tbody>
                    <tr>
                        <td><strong>License</strong><small>Product: <?= h(APP_NAME); ?></small></td>
                        <td><?= h($licenseStatus); ?></td>
                        <td><span class="status-pill <?= $licenseActive ? 'ready' : 'empty'; ?>"><?= $licenseActive ? 'Active' : 'Expired'; ?></span></td>
                        <td><?= h($licenseActive ? 'Expires ' . $licenseExpiresAt . ' / ' . $licenseDaysLeft . ' days left' : 'Renewal required to keep modules active.'); ?></td>
                    </tr>
                    <tr>
                        <td><strong>Snapshot Issues</strong><small>Top risks to watch</small></td>
                        <td colspan="3"><?= h($systemSnapshotIssueText); ?></td>
                    </tr>
                    <tr>
                        <td><strong>Role Distribution</strong><small>Superadmin, Admin, Instructor, Student</small></td>
                        <td><?= h((string) $totalSuperAdmins); ?> / <?= h((string) $totalAdmins); ?> / <?= h((string) $totalInstructors); ?> / <?= h((string) $totalStudents); ?></td>
                        <td><span class="status-pill <?= $totalUsers > 0 ? 'ready' : 'empty'; ?>"><?= $totalUsers > 0 ? 'Loaded' : 'No users'; ?></span></td>
                        <td><?= h('Role map includes ' . count($roleSummary) . ' configured role types.'); ?></td>
                    </tr>
                    <tr>
                        <td><strong>User Status</strong><small>Active / Inactive / Pending / Blocked</small></td>
                        <td><?= h((string) $usersActive); ?> / <?= h((string) $usersInactive); ?> / <?= h((string) $usersPending); ?> / <?= h((string) $usersBlocked); ?></td>
                        <td><span class="status-pill <?= $usersBlocked === 0 ? 'ready' : 'warning'; ?>"><?= $usersBlocked === 0 ? 'Stable' : 'Some blocked'; ?></span></td>
                        <td>System activity uses role-aware policy checks for all user workflows.</td>
                    </tr>
                    <tr>
                        <td><strong>Courses</strong><small>Published / Draft / Paused</small></td>
                        <td><?= h((string) $publishedCourses); ?> / <?= h((string) $draftCourses); ?> / <?= h((string) $pausedCourses); ?></td>
                        <td><span class="status-pill <?= $publishedCourses > 0 ? 'ready' : 'empty'; ?>"><?= $publishedCourses > 0 ? 'Ready' : 'Empty'; ?></span></td>
                        <td>Created categories: <?= h((string) $totalCategories); ?> (active: <?= h((string) $activeCategories); ?>).</td>
                    </tr>
                    <tr>
                        <td><strong>Content Library</strong><small>Video / YouTube / PDF / Live</small></td>
                        <td><?= h((string) $videoContents); ?> / <?= h((string) $youtubeContents); ?> / <?= h((string) $pdfContents); ?> / <?= h((string) $liveContents); ?></td>
                        <td><span class="status-pill <?= $totalContents > 0 ? 'ready' : 'empty'; ?>"><?= $totalContents > 0 ? 'Available' : 'No content'; ?></span></td>
                        <td>Total content rows: <?= h((string) $totalContents); ?>.</td>
                    </tr>
                    <tr>
                        <td><strong>Batches & Classes</strong><small>Active / Scheduled / Live / Completed</small></td>
                        <td><?= h((string) $activeBatches); ?> / <?= h((string) $scheduledClasses); ?> / <?= h((string) $liveClasses); ?> / <?= h((string) $completedClasses); ?></td>
                        <td><span class="status-pill <?= $totalClasses > 0 ? 'ready' : 'empty'; ?>"><?= $totalClasses > 0 ? 'Running' : 'Idle'; ?></span></td>
                        <td>Total batches: <?= h((string) $totalBatches); ?>, completed batches: <?= h((string) $completedBatches); ?>.</td>
                    </tr>
                    <tr>
                        <td><strong>Enrollment & Purchase</strong><small>Active / Completed / Paid / Pending</small></td>
                        <td><?= h((string) $activeEnrollments); ?> / <?= h((string) $completedEnrollments); ?> / <?= h((string) $paidPurchases); ?> / <?= h((string) $pendingPurchases); ?></td>
                        <td><span class="status-pill <?= $paidPurchases > 0 ? 'ready' : 'empty'; ?>"><?= $paidPurchases > 0 ? 'Monetized' : 'No paid sale'; ?></span></td>
                        <td>Course sales value: <?= h(number_format($purchaseTotalAmount, 2)); ?>, failed sales: <?= h((string) $failedPurchases); ?>.</td>
                    </tr>
                    <tr>
                        <td><strong>Membership Plans</strong><small>Active plans / Current subscriptions</small></td>
                        <td><?= h((string) $membershipPlansActive); ?> / <?= h((string) $activeSubscriptions); ?></td>
                        <td><span class="status-pill <?= $membershipPlansActive > 0 ? 'ready' : 'empty'; ?>"><?= $membershipPlansActive > 0 ? 'Configured' : 'Not configured'; ?></span></td>
                        <td>Total plan subscriptions: <?= h((string) $totalSubscriptions); ?>.</td>
                    </tr>
                    <tr>
                        <td><strong>Support Engine</strong><small>Active / Inactive assignments</small></td>
                        <td><?= h((string) $supportActiveAssignments); ?> / <?= h((string) $supportInactiveAssignments); ?></td>
                        <td><span class="status-pill <?= $supportActiveAssignments > 0 ? 'ready' : 'empty'; ?>"><?= $supportActiveAssignments > 0 ? 'Operational' : 'No assignments'; ?></span></td>
                        <td>Students: <?= h((string) ($supportByType['student'] ?? 0)); ?>, instructors: <?= h((string) ($supportByType['instructor'] ?? 0)); ?>.</td>
                    </tr>
                    <tr>
                        <td><strong>Security</strong><small>24h success / failed login</small></td>
                        <td><?= h((string) $loginSuccess24h); ?> / <?= h((string) $loginFailed24h); ?></td>
                        <td><span class="status-pill <?= $loginFailed24h === 0 ? 'ready' : 'warning'; ?>"><?= $loginFailed24h === 0 ? 'Safe' : 'Review'; ?></span></td>
                        <td>Attempts in last 24h: <?= h((string) $loginAttempts24h); ?>.</td>
                    </tr>
                </tbody>
            </table>
        </section>

        <section class="settings-detail-card">
            <div class="detail-head compact-head">
                <div>
                    <span>Learning Progress</span>
                    <h2>Content and assessments progress</h2>
                    <p>Cross-platform summary of learner engagement and learning completion signals.</p>
                </div>
            </div>
            <div class="stats" style="margin-top: 0;">
                <article class="stat-card">
                    <span>Progress Entries</span>
                    <strong><?= h((string) $contentProgressCount); ?></strong>
                </article>
                <article class="stat-card">
                    <span>Avg Content Progress</span>
                    <strong><?= h((string) $avgContentProgress); ?>%</strong>
                </article>
                <article class="stat-card">
                    <span>Completed Content Rows</span>
                    <strong><?= h((string) $completedContentProgress); ?></strong>
                </article>
                <article class="stat-card">
                    <span>Achievements</span>
                    <strong><?= h((string) $studentAchievements); ?></strong>
                </article>
                <article class="stat-card">
                    <span>Avg Stars</span>
                    <strong><?= h((string) $avgStars); ?></strong>
                </article>
                <article class="stat-card">
                    <span>Achievement Progress</span>
                    <strong><?= h((string) $achievementAvgProgress); ?>%</strong>
                </article>
                <article class="stat-card">
                    <span>Exam Attempts</span>
                    <strong><?= h((string) $examAttempts); ?></strong>
                </article>
                <article class="stat-card">
                    <span>Avg Exam Score</span>
                    <strong><?= h((string) $avgExamPercent); ?>%</strong>
                </article>
            </div>
        </section>

        <div class="ins-grid" style="align-items: start;">
            <section class="settings-detail-card ins-card">
                <div class="detail-head compact-head">
                    <div>
                        <span>Course Performance</span>
                        <h2>Top enrolled courses</h2>
                        <p>Published and draft mix with student enrollments & content count.</p>
                    </div>
                    <a class="modal-button ghost" href="<?= h(app_url('sadmin/courses?sort=enrollment')); ?>">View all</a>
                </div>
                <div class="role-table-wrap">
                    <table class="role-access-table smart-table">
                        <thead>
                            <tr>
                                <th>Course</th>
                                <th>Instructor</th>
                                <th>Enrolled</th>
                                <th>Content</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!$topCourses): ?>
                                <tr><td colspan="4">No course activity yet.</td></tr>
                            <?php endif; ?>
                            <?php foreach ($topCourses as $course): ?>
                                <tr>
                                    <td><strong><?= h($course['title']); ?></strong><small>Course ID: <?= h((string) $course['id']); ?></small></td>
                                    <td><?= h((string) ($course['instructor_name'] ?: 'Not assigned')); ?></td>
                                    <td><strong><?= h((string) ($course['enrolled_count'] ?? 0)); ?></strong><small>Active: <?= h((string) ($course['active_enrollments'] ?? 0)); ?></small></td>
                                    <td><?= h((string) ($course['total_content'] ?? 0)); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </section>

            <section class="settings-detail-card ins-card">
                <div class="detail-head compact-head">
                    <div>
                        <span>Top Content Likes</span>
                        <h2>Most liked chapters/content</h2>
                        <p>Useful to decide which material to duplicate or republish.</p>
                    </div>
                    <a class="modal-button ghost" href="<?= h(app_url('sadmin/dashboard')); ?>">Refresh</a>
                </div>
                <div class="role-table-wrap">
                    <table class="role-access-table smart-table">
                        <thead>
                            <tr><th>Content</th><th>Type</th><th>Course</th><th>Likes</th></tr>
                        </thead>
                        <tbody>
                            <?php if (!$popularContent): ?>
                                <tr><td colspan="4">No likes yet.</td></tr>
                            <?php endif; ?>
                            <?php foreach ($popularContent as $content): ?>
                                <tr>
                                    <td><strong><?= h($content['content_title']); ?></strong></td>
                                    <td><?= h((string) ucwords(str_replace('_', ' ', (string) $content['content_type']))); ?></td>
                                    <td><small><?= h((string) $content['course_title']); ?></small></td>
                                    <td><strong><?= h((string) ($content['like_count'] ?? 0)); ?></strong></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </section>
        </div>

        <div class="ins-grid" style="align-items: start;">
            <section class="settings-detail-card ins-card">
                <div class="detail-head compact-head">
                    <div>
                        <span>Upcoming / Live Classes</span>
                        <h2>Next schedule window</h2>
                        <p>Includes live classes and scheduled future classes.</p>
                    </div>
                    <a class="modal-button ghost" href="<?= h(app_url('sadmin/instructors')); ?>">Go Instructors</a>
                </div>
                <div class="role-table-wrap">
                    <table class="role-access-table smart-table">
                        <thead>
                            <tr><th>Class</th><th>Batch</th><th>Course</th><th>Mode</th><th>Status</th><th>Date & Time</th></tr>
                        </thead>
                        <tbody>
                            <?php if (!$nextClasses): ?>
                                <tr><td colspan="6">No upcoming class in queue.</td></tr>
                            <?php endif; ?>
                            <?php foreach ($nextClasses as $class): ?>
                                <?php
                                $dateTime = trim((string) ($class['class_date'] ?? '') . ' ' . substr((string) ($class['starts_at'] ?? ''), 0, 5));
                                ?>
                                <tr>
                                    <td><strong><?= h($class['class_title']); ?></strong></td>
                                    <td><?= h((string) ($class['batch_name'] ?: 'Open Batch')); ?></td>
                                    <td><small><?= h((string) ($class['course_title'] ?: 'N/A')); ?></small></td>
                                    <td><?= h(ucfirst((string) ($class['class_type'] ?? 'online'))); ?></td>
                                    <td><span class="status-pill <?= ($class['class_status'] ?? '') === 'live' ? 'ready' : 'empty'; ?>"><?= h(ucfirst((string) ($class['class_status'] ?? 'scheduled'))); ?></span></td>
                                    <td><?= h($dateTime !== '' ? $dateTime : 'Not scheduled'); ?></td>
                                </tr>
                            <?php endforeach; ?>
                    </table>
                </div>
            </section>

            <section class="settings-detail-card ins-card">
                <div class="detail-head compact-head">
                    <div>
                        <span>Instructor Directory</span>
                        <h2>Instructor profile, content and follow details</h2>
                        <p>Top instructors with followers, courses, students and class status.</p>
                    </div>
                </div>
                <div class="role-table-wrap">
                    <table class="role-access-table smart-table sadmin-instructor-table">
                        <thead>
                            <tr><th>Instructor</th><th>Contact</th><th>Courses</th><th>Followers</th><th>Students</th><th>Live/Sched</th><th>Approval</th></tr>
                        </thead>
                        <tbody>
                            <?php if (!$instructorDirectory): ?>
                                <tr><td colspan="7">No instructor directory data available.</td></tr>
                            <?php endif; ?>
                            <?php foreach ($instructorDirectory as $ins): ?>
                                <tr>
                                    <td>
                                        <strong><?= h((string) $ins['full_name']); ?></strong>
                                        <small>@<?= h((string) $ins['username']); ?></small>
                                    </td>
                                    <td>
                                        <small><?= h((string) $ins['email']); ?></small>
                                        <strong><?= h((string) ucfirst((string) $ins['user_status'])); ?></strong>
                                    </td>
                                    <td>
                                        <strong><?= h((string) $ins['total_courses']); ?></strong>
                                        <small><?= h((string) $ins['total_batches']); ?> batch</small>
                                    </td>
                                    <td><strong><?= h((string) ($ins['total_followers'] ?? 0)); ?></strong></td>
                                    <td><strong><?= h((string) ($ins['total_students'] ?? 0)); ?></strong></td>
                                    <td><strong><?= h((string) ($ins['live_classes'] ?? 0)); ?></strong>/<small><?= h((string) ($ins['scheduled_classes'] ?? 0)); ?></td>
                                    <td>
                                        <span class="status-pill <?= ((string) ($ins['approval_status'] ?? '') === 'approved' ? 'ready' : 'warning'); ?>">
                                            <?= h(ucfirst((string) ($ins['approval_status'] ?? 'pending'))); ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </section>
        </div>

        <div class="ins-grid" style="align-items: start;">
            <section class="settings-detail-card ins-card">
                <div class="detail-head compact-head">
                    <div>
                        <span>Membership Plans</span>
                        <h2>Plan status and subscribers</h2>
                        <p>Plan-based limit control for videos, PDFs, mock tests and exams.</p>
                    </div>
                </div>
                <div class="role-table-wrap">
                    <table class="role-access-table smart-table">
                        <thead>
                            <tr><th>Plan</th><th>Price</th><th>Validity</th><th>Active Subscribers</th></tr>
                        </thead>
                        <tbody>
                            <?php if (!$planLeaderboard): ?>
                                <tr><td colspan="4">No active plans found.</td></tr>
                            <?php endif; ?>
                            <?php foreach ($planLeaderboard as $plan): ?>
                                <tr>
                                    <td><strong><?= h((string) $plan['plan_name']); ?></strong><small><?= h((string) $plan['plan_slug']); ?></small></td>
                                    <td><strong><?= h((string) $plan['monthly_price']); ?></strong></td>
                                    <td><?= h((string) ($plan['validity_days'] ?? 0)); ?> days</td>
                                    <td><strong><?= h((string) ($plan['active_subscribers'] ?? 0)); ?></strong></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </section>

            <section class="settings-detail-card ins-card">
                <div class="detail-head compact-head">
                    <div>
                        <span>Approval Queue</span>
                        <h2>Latest instructor approvals</h2>
                        <p>Priority order (pending first).</p>
                    </div>
                    <a class="modal-button ghost" href="<?= h(app_url('sadmin/instructors')); ?>">Manage</a>
                </div>
                <div class="role-table-wrap">
                    <table class="role-access-table smart-table">
                        <thead>
                            <tr><th>Instructor</th><th>Email</th><th>Approval</th><th>Joined</th></tr>
                        </thead>
                        <tbody>
                            <?php if (!$pendingInstructorRows): ?>
                                <tr><td colspan="4">No pending instructor data.</td></tr>
                            <?php endif; ?>
                            <?php foreach ($pendingInstructorRows as $row): ?>
                                <tr>
                                    <td><strong><?= h((string) $row['full_name']); ?></strong></td>
                                    <td><small><?= h((string) $row['email']); ?></small></td>
                                    <td>
                                        <span class="status-pill <?= ((string) ($row['approval_status'] ?? '') === 'approved' || (string) ($row['approval_status'] ?? '') === '') ? 'ready' : 'warning'; ?>">
                                            <?= h(ucfirst((string) ($row['approval_status'] ?: 'pending'))); ?>
                                        </span>
                                    </td>
                                    <td><small><?= h(date('M d, Y H:i', strtotime((string) $row['created_at']))); ?></small></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </section>
        </div>

        <section class="settings-detail-card">
            <div class="detail-head compact-head">
                <div>
                    <span>Recent Activity</span>
                    <h2>Latest users, enrollments and purchases</h2>
                    <p>Quick observability for operations decisions.</p>
                </div>
            </div>
            <div class="ins-grid">
                <div>
                    <div class="detail-head compact-head"><h2>Latest Users</h2></div>
                    <div class="role-table-wrap">
                        <table class="role-access-table smart-table">
                            <thead>
                                <tr><th>Name</th><th>Role</th><th>Status</th><th>Joined</th></tr>
                            </thead>
                            <tbody>
                                <?php if (!$latestUsers): ?>
                                    <tr><td colspan="4">No recent users.</td></tr>
                                <?php endif; ?>
                                <?php foreach ($latestUsers as $row): ?>
                                    <tr>
                                        <td><strong><?= h((string) $row['full_name']); ?></strong><small><?= h((string) $row['username']); ?></small></td>
                                        <td><small><?= h((string) $row['role_name']); ?></small></td>
                                        <td><span class="status-pill ready"><?= h((string) $row['status']); ?></span></td>
                                        <td><small><?= h(date('M d, Y H:i', strtotime((string) $row['created_at']))); ?></small></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <div>
                    <div class="detail-head compact-head"><h2>Recent Enrollments</h2></div>
                    <div class="role-table-wrap">
                        <table class="role-access-table smart-table">
                            <thead>
                                <tr><th>Student</th><th>Course</th><th>Instructor</th><th>Status</th></tr>
                            </thead>
                            <tbody>
                                <?php if (!$latestEnrollments): ?>
                                    <tr><td colspan="4">No enrollments yet.</td></tr>
                                <?php endif; ?>
                                <?php foreach ($latestEnrollments as $en): ?>
                                    <tr>
                                        <td><strong><?= h((string) $en['student_name']); ?></strong></td>
                                        <td><small><?= h((string) $en['course_title']); ?></small></td>
                                        <td><small><?= h((string) $en['instructor_name']); ?></small></td>
                                        <td><span class="status-pill <?= ($en['status'] ?? '') === 'active' ? 'ready' : 'empty'; ?>"><?= h(ucfirst((string) ($en['status'] ?? 'active'))); ?></span></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <div class="ins-grid" style="margin-top: 10px;">
                <div>
                    <div class="detail-head compact-head"><h2>Recent Purchases</h2></div>
                    <div class="role-table-wrap">
                        <table class="role-access-table smart-table">
                            <thead>
                                <tr><th>Student</th><th>Course</th><th>Status</th><th>Amount</th></tr>
                            </thead>
                            <tbody>
                                <?php if (!$latestPurchases): ?>
                                    <tr><td colspan="4">No purchases yet.</td></tr>
                                <?php endif; ?>
                                <?php foreach ($latestPurchases as $purchase): ?>
                                    <tr>
                                        <td><strong><?= h((string) $purchase['student_name']); ?></strong><small><?= h((string) $purchase['payment_method']); ?></small></td>
                                        <td><small><?= h((string) $purchase['course_title']); ?></small></td>
                                        <td><span class="status-pill <?= ($purchase['payment_status'] ?? '') === 'paid' ? 'ready' : 'warning'; ?>"><?= h(ucfirst((string) $purchase['payment_status'])); ?></span></td>
                                        <td><strong><?= h((string) number_format((float) ($purchase['amount'] ?? 0), 2)); ?></strong></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <div>
                    <div class="detail-head compact-head"><h2>Audit Actions</h2></div>
                    <table class="detail-table">
                        <tbody>
                            <tr><th>License</th><td><?= h($licenseStatus); ?></td><th>Renewal Window</th><td><?= h($licenseStatus === 'active' ? $licenseDaysLeft . ' day(s)' : 'Expired'); ?></td></tr>
                            <tr><th>Security Attempts (24h)</th><td><?= h((string) $loginAttempts24h); ?></td><th>Failed Login</th><td><?= h((string) $loginFailed24h); ?></td></tr>
                            <tr><th>Active Members</th><td><?= h((string) $activeSubscriptions); ?></td><th>Membership Plans</th><td><?= h((string) $membershipPlansActive); ?> active / <?= h((string) $membershipPlans); ?></td></tr>
                            <tr><th>Course Enrollments</th><td><?= h((string) $courseEnrollments); ?></td><th>Completed Enrollments</th><td><?= h((string) $completedEnrollments); ?></td></tr>
                            <tr><th>Exam Attempts</th><td><?= h((string) $examAttempts); ?></td><th>Review Queue</th><td><?= h((string) $examInReview); ?></td></tr>
                            <tr><th>Content Progress</th><td><?= h((string) $avgContentProgress); ?>%</td><th>Completed Rows</th><td><?= h((string) $completedContentProgress); ?></td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </section>
    </section>
<?php include __DIR__ . '/includes/footer.php'; ?>



