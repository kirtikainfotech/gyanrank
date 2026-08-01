<?php
require_once __DIR__ . '/includes/functions.php';
$user = instructor_user();
ensure_instructor_erp_tables();
$instructorId = (int) $user['id'];
$allClasses = instructor_classes($instructorId, 100000);

$page = max(1, (int) ($_GET['page'] ?? 1));
$perPage = (int) ($_GET['per_page'] ?? 10);
if (!in_array($perPage, [10, 25, 50], true)) {
    $perPage = 10;
}
$totalClasses = count($allClasses);
$onlineClasses = count(array_filter($allClasses, static fn($class) => ($class['class_type'] ?? '') === 'online'));
$scheduledClasses = count(array_filter($allClasses, static fn($class) => ($class['class_status'] ?? '') === 'scheduled'));
$completedClasses = count(array_filter($allClasses, static fn($class) => ($class['class_status'] ?? '') === 'completed'));
$totalPages = max(1, (int) ceil($totalClasses / $perPage));
$page = min($page, $totalPages);
$offset = ($page - 1) * $perPage;
$classes = array_slice($allClasses, $offset, $perPage);
$classStart = $totalClasses === 0 ? 0 : $offset + 1;
$classEnd = min($totalClasses, $offset + count($classes));
$attendancePageUrl = static function (array $extra = []) use ($perPage): string {
    $query = ['per_page' => $perPage];
    foreach ($extra as $key => $value) {
        if ($value === null) {
            unset($query[$key]);
        } else {
            $query[$key] = $value;
        }
    }
    return app_url('ins/attendance') . '?' . http_build_query($query);
};

$pageTitle = 'Attendance';
$pageSubtitle = 'Mark attendance for online and offline classes.';
$activePage = 'attendance';
?>
<?php include __DIR__ . '/includes/header.php'; ?>
<?php include __DIR__ . '/includes/sidebar.php'; ?>
<main class="main">
    <?php include __DIR__ . '/includes/topbar.php'; ?>
    <section class="content instructor-content">
        <div class="row g-3">
            <div class="col-12">
                <div class="card custom-card bg-primary-gradient border-0 text-fixed-white">
                    <div class="card-body d-flex flex-wrap align-items-center justify-content-between gap-3">
                        <div>
                            <span class="badge bg-white-1 text-fixed-white mb-2">Attendance Register</span>
                            <h4 class="fw-semibold mb-1 text-fixed-white">Class-wise attendance</h4>
                            <p class="mb-0 op-8">Online/offline classes ke attendance status, batch aur teacher details ek compact view me.</p>
                        </div>
                        <div class="btn-list">
                            <a class="btn btn-light btn-wave" href="<?= h(app_url('ins/classes')); ?>">Classes</a>
                            <a class="btn btn-outline-light btn-wave" href="<?= h(app_url('ins/students')); ?>">Students</a>
                            <a class="btn btn-outline-light btn-wave" href="<?= h(app_url('ins/reports')); ?>">Reports</a>
                        </div>
                    </div>
                </div>
            </div>

            <?php
            $attendanceMetrics = [
                ['label' => 'Classes', 'value' => $totalClasses, 'icon' => 'bx bx-calendar', 'tone' => 'primary'],
                ['label' => 'Online', 'value' => $onlineClasses, 'icon' => 'bx bx-video', 'tone' => 'info'],
                ['label' => 'Scheduled', 'value' => $scheduledClasses, 'icon' => 'bx bx-time-five', 'tone' => 'warning'],
                ['label' => 'Completed', 'value' => $completedClasses, 'icon' => 'bx bx-check-circle', 'tone' => 'success'],
            ];
            ?>
            <?php foreach ($attendanceMetrics as $metric): ?>
                <div class="col-6 col-md-3">
                    <div class="card custom-card">
                        <div class="card-body d-flex align-items-center gap-3">
                            <span class="avatar avatar-md bg-<?= h($metric['tone']); ?>-transparent text-<?= h($metric['tone']); ?>"><i class="<?= h($metric['icon']); ?> fs-20"></i></span>
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
                            <div class="card-title mb-0">Attendance Sheet</div>
                            <p class="mb-0 text-muted fs-12">Showing <?= h((string) $classStart); ?>-<?= h((string) $classEnd); ?> of <?= h((string) $totalClasses); ?> classes.</p>
                        </div>
                        <form method="get" action="<?= h(app_url('ins/attendance')); ?>" class="d-flex align-items-center gap-2 m-0">
                            <input type="hidden" name="page" value="1">
                            <select class="form-select form-select-sm" name="per_page" onchange="this.form.submit()" aria-label="Rows per page">
                                <?php foreach ([10, 25, 50] as $size): ?>
                                    <option value="<?= $size; ?>" <?= $perPage === $size ? 'selected' : ''; ?>><?= $size; ?> rows</option>
                                <?php endforeach; ?>
                            </select>
                        </form>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-sm table-hover align-middle mb-0 gr-register-table gr-attendance-table" data-gr-register="1">
                                <colgroup><col style="width: 30%;"><col style="width: 18%;"><col style="width: 12%;"><col style="width: 10%;"><col style="width: 11%;"><col style="width: 9%;"><col style="width: 10%;"></colgroup>
                                <thead><tr><th>Class</th><th>Batch</th><th>Date</th><th>Mode</th><th>Status</th><th>Present</th><th class="text-end">Action</th></tr></thead>
                                <tbody>
                                    <?php if (!$classes): ?><tr><td colspan="7" class="text-muted py-4">No class found for attendance.</td></tr><?php endif; ?>
                                    <?php foreach ($classes as $class): ?>
                                        <?php
                                        $status = (string) ($class['class_status'] ?? 'scheduled');
                                        $statusTone = $status === 'completed' ? 'success' : ($status === 'scheduled' ? 'warning' : 'secondary');
                                        ?>
                                        <tr>
                                            <td>
                                                <span class="fw-semibold text-truncate gr-cell-title"><?= h($class['class_title']); ?></span>
                                                <span class="text-muted fs-12 gr-cell-subtitle"><?= h((string) ($class['duration_minutes'] ?? 60)); ?> min session</span>
                                            </td>
                                            <td>
                                                <span class="fw-semibold text-truncate gr-cell-title"><?= h($class['batch_name'] ?: 'Open'); ?></span>
                                                <span class="text-muted fs-12 gr-cell-subtitle"><?= h($class['teacher_name'] ?: 'Teacher not set'); ?></span>
                                            </td>
                                            <td><span class="fw-semibold gr-inline-stat"><?= h($class['class_date']); ?></span><span class="text-muted fs-12 gr-cell-subtitle"><?= h(substr((string) ($class['starts_at'] ?? ''), 0, 5)); ?></span></td>
                                            <td><span class="badge bg-primary-transparent text-primary"><?= h(ucfirst((string) ($class['class_type'] ?? 'online'))); ?></span></td>
                                            <td><span class="badge bg-<?= h($statusTone); ?>-transparent text-<?= h($statusTone); ?>"><?= h(ucfirst($status)); ?></span></td>
                                            <td><span class="fw-semibold gr-inline-stat">0</span><span class="text-muted fs-12 gr-cell-subtitle">marked</span></td>
                                            <td class="text-end"><button class="btn btn-sm btn-light btn-wave" type="button" title="Mark attendance"><i class="bx bx-check-square"></i></button></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 px-3 py-3 border-top">
                            <span class="text-muted fs-12"><?= h((string) $classStart); ?>-<?= h((string) $classEnd); ?> of <?= h((string) $totalClasses); ?> records</span>
                            <div class="btn-list mb-0">
                                <a class="btn btn-sm btn-light btn-wave <?= $page <= 1 ? 'disabled' : ''; ?>" href="<?= h($attendancePageUrl(['page' => max(1, $page - 1)])); ?>">Prev</a>
                                <span class="badge bg-primary-transparent text-primary align-self-center">Page <?= h((string) $page); ?> / <?= h((string) $totalPages); ?></span>
                                <a class="btn btn-sm btn-light btn-wave <?= $page >= $totalPages ? 'disabled' : ''; ?>" href="<?= h($attendancePageUrl(['page' => min($totalPages, $page + 1)])); ?>">Next</a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
    <style>
        .gr-attendance-table {
            width: 100% !important;
            min-width: 0 !important;
            table-layout: fixed;
        }
        .gr-attendance-table th,
        .gr-attendance-table td {
            padding: .34rem .45rem !important;
            font-size: .72rem;
            line-height: 1.2;
            vertical-align: middle;
        }
        .gr-attendance-table thead th {
            font-size: .66rem;
            padding-block: .45rem !important;
            letter-spacing: .02em;
        }
        .gr-attendance-table .gr-cell-title {
            font-size: .8rem;
            line-height: 1.2;
            margin-bottom: .12rem;
        }
        .gr-attendance-table .gr-cell-subtitle {
            display: block;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            max-width: 100%;
        }
        .gr-attendance-table .text-truncate {
            white-space: normal !important;
            overflow: visible !important;
            text-overflow: clip !important;
            overflow-wrap: anywhere;
        }
        .gr-attendance-table .gr-inline-stat {
            display: block;
            line-height: 1.1;
            margin-bottom: .1rem;
            white-space: nowrap;
        }
        .gr-attendance-table .badge {
            font-size: .66rem;
            padding: .22rem .42rem;
        }
        .gr-attendance-table .btn {
            width: 1.55rem;
            height: 1.55rem;
            min-width: 1.55rem;
            padding: 0 !important;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: .82rem;
        }
        .gr-attendance-table th.text-end,
        .gr-attendance-table td.text-end {
            text-align: center !important;
        }
    </style>
<?php include __DIR__ . '/includes/footer.php'; ?>
