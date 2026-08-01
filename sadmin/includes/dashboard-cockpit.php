<?php
$revenueValue = $purchaseTotalAmount > 0 ? number_format($purchaseTotalAmount, 0) : number_format($paidPurchases, 0);
$scoreValue = number_format($avgExamPercent, 1) . '%';
$metricCards = [
    ['icon' => 'bx bx-wallet', 'tone' => 'primary', 'delta' => '+12.5%', 'delta_note' => 'vs prev', 'label' => 'Revenue', 'value' => 'Rs ' . $revenueValue, 'unit' => $purchaseTotalAmount > 0 ? 'total' : 'paid'],
    ['icon' => 'bx bx-group', 'tone' => 'secondary', 'delta' => '+4.2%', 'delta_note' => 'active', 'label' => 'Total Students', 'value' => number_format($totalStudents), 'unit' => 'users'],
    ['icon' => 'bx bx-book-open', 'tone' => 'tertiary', 'delta' => number_format($publishedCourses), 'delta_note' => 'published', 'label' => 'Courses Sold', 'value' => number_format($paidPurchases), 'unit' => 'units'],
    ['icon' => 'bx bx-trophy', 'tone' => 'amber', 'delta' => '+0.8%', 'delta_note' => 'overall', 'label' => 'Avg Test Score', 'value' => $scoreValue, 'unit' => 'rank A'],
];
$actions = [
    ['label' => 'Verify new instructor credentials', 'meta' => 'Pending: ' . $recentInstructorApprovals . ' - High Priority'],
    ['label' => 'Review active live class queue', 'meta' => 'Live: ' . $liveClasses . ' - Academic Ops'],
];
$calendarDays = ['25','26','27','28','29','30','1','2','3','4','5','6','7','8','9','10','11','12','13','14','15','16','17','18','19','20','21','22','23','24','25','26','27','28','29'];
?>
<section class="edu-cockpit">
    <div class="edu-page-head">
        <div>
            <h2>Admin Overview</h2>
            <p><?= h(number_format($totalUsers)); ?> users, <?= h(number_format($totalCourses)); ?> courses, <?= h(number_format($totalContents)); ?> content items and <?= h(number_format($liveClasses)); ?> live class running.</p>
        </div>
        <div class="edu-head-actions">
            <a href="<?= h(app_url('sadmin/settings')); ?>"><i class="bx bx-filter-alt"></i>Filters</a>
            <a class="primary" href="<?= h(app_url('sadmin/dashboard')); ?>"><i class="bx bx-download"></i>Export</a>
        </div>
    </div>

    <div class="edu-metric-grid">
        <?php foreach ($metricCards as $metric): ?>
            <article class="edu-metric-card <?= h($metric['tone']); ?>">
                <div class="edu-metric-top">
                    <span class="edu-icon"><i class="<?= h($metric['icon']); ?>"></i></span>
                    <span class="edu-delta"><?= h($metric['delta']); ?><small><?= h($metric['delta_note']); ?></small></span>
                </div>
                <div>
                    <p><?= h($metric['label']); ?></p>
                    <strong><?= h($metric['value']); ?></strong>
                    <small><?= h($metric['unit']); ?></small>
                </div>
            </article>
        <?php endforeach; ?>
    </div>

    <div class="edu-main-grid">
        <section class="edu-panel edu-enrollments">
            <div class="edu-panel-head">
                <div>
                    <h3>Recent Enrollments</h3>
                    <p>Latest learner activity from your LMS</p>
                </div>
                <div class="edu-table-tools">
                    <label><i class="bx bx-search"></i><input type="search" placeholder="Filter student..."></label>
                    <a href="<?= h(app_url('sadmin/courses')); ?>">View All</a>
                </div>
            </div>
            <div class="edu-table-wrap">
                <table>
                    <thead>
                        <tr><th>Student</th><th>Email</th><th>Plan</th><th>Course</th><th>Status</th></tr>
                    </thead>
                    <tbody>
                        <?php if (!$latestEnrollments): ?>
                            <tr><td colspan="5">No enrollments yet.</td></tr>
                        <?php endif; ?>
                        <?php foreach (array_slice($latestEnrollments, 0, 5) as $index => $en): ?>
                            <?php
                            $student = (string) ($en['student_name'] ?? 'Student');
                            $nameParts = preg_split('/\s+/', trim($student)) ?: [];
                            $initials = strtoupper(substr($nameParts[0] ?? 'S', 0, 1) . substr($nameParts[1] ?? '', 0, 1));
                            $status = ucfirst((string) ($en['status'] ?? 'active'));
                            ?>
                            <tr>
                                <td><span class="edu-avatar tone-<?= ($index % 5) + 1; ?>"><?= h($initials); ?></span><b><?= h($student); ?></b></td>
                                <td><?= h((string) ($en['student_email'] ?? 'student@edu.local')); ?></td>
                                <td><em><?= h($activeSubscriptions > 0 ? 'Premium' : 'Free Trial'); ?></em></td>
                                <td><?= h((string) ($en['course_title'] ?? 'Course')); ?></td>
                                <td><span class="edu-status <?= strtolower($status) === 'active' ? 'success' : 'pending'; ?>"><?= h($status === 'Active' ? 'Success' : $status); ?></span></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div class="edu-actions">
                <h4><i class="bx bx-list-check"></i>Pending Admin Actions</h4>
                <div>
                    <?php foreach ($actions as $action): ?>
                        <article>
                            <input type="checkbox">
                            <span><b><?= h($action['label']); ?></b><small><?= h($action['meta']); ?></small></span>
                            <i class="bx bx-grid-vertical"></i>
                        </article>
                    <?php endforeach; ?>
                </div>
            </div>
        </section>

        <aside class="edu-side-stack">
            <section class="edu-live-panel">
                <header><h3>Live Classes Today</h3><span>LIVE</span></header>
                <div>
                    <?php if (!$nextClasses): ?>
                        <article><p>No class</p><h4>Schedule queue empty</h4><small>Create class from instructor panel</small></article>
                    <?php endif; ?>
                    <?php foreach (array_slice($nextClasses, 0, 2) as $index => $class): ?>
                        <article class="<?= $index === 0 ? 'now' : ''; ?>">
                            <p><?= $index === 0 ? 'NOW - ' : ''; ?><?= h(substr((string) ($class['starts_at'] ?? '09:00'), 0, 5)); ?></p>
                            <h4><?= h((string) ($class['class_title'] ?? 'Live Class')); ?></h4>
                            <small><?= h((string) ($class['course_title'] ?? 'Course')); ?></small>
                            <?php if ($index === 0): ?><a href="<?= h(app_url('sadmin/classes')); ?>">Join Session</a><?php endif; ?>
                        </article>
                    <?php endforeach; ?>
                </div>
            </section>

            <section class="edu-calendar">
                <div><h3><?= h(date('F Y')); ?></h3><span>&lt; &gt;</span></div>
                <div class="week"><b>M</b><b>T</b><b>W</b><b>T</b><b>F</b><b>S</b><b>S</b></div>
                <div class="days">
                    <?php foreach ($calendarDays as $day): ?><span class="<?= $day === date('j') ? 'active' : ''; ?>"><?= h($day); ?></span><?php endforeach; ?>
                </div>
            </section>
        </aside>
    </div>

    <div class="edu-bottom-grid">
        <article><span><i class="bx bx-globe"></i></span><div><h4>Global Presence</h4><p><?= h((string) $totalUsers); ?> users active</p><i><b style="width:75%"></b></i></div></article>
        <article><span><i class="bx bx-tachometer"></i></span><div><h4>System Health</h4><p><?= h((string) $systemHealthScore); ?>% operational</p><i><b style="width:<?= h((string) min(100, $systemHealthScore)); ?>%"></b></i></div></article>
        <article><span><i class="bx bx-data"></i></span><div><h4>Data Usage</h4><p><?= h((string) $totalContents); ?> content records</p><i><b style="width:48%"></b></i></div></article>
    </div>
</section>
