<?php
require_once __DIR__ . '/includes/functions.php';
$user = instructor_user();
ensure_instructor_erp_tables();
$instructorId = (int) $user['id'];
$allBatches = instructor_batches($instructorId);

$page = max(1, (int) ($_GET['page'] ?? 1));
$perPage = (int) ($_GET['per_page'] ?? 10);
if (!in_array($perPage, [10, 25, 50], true)) {
    $perPage = 10;
}
$totalBatches = count($allBatches);
$activeBatches = count(array_filter($allBatches, static fn($batch) => ($batch['status'] ?? '') === 'active'));
$onlineBatches = count(array_filter($allBatches, static fn($batch) => ($batch['mode'] ?? '') === 'online'));
$totalCapacity = array_sum(array_map(static fn($batch) => (int) ($batch['capacity'] ?? 0), $allBatches));
$totalPages = max(1, (int) ceil($totalBatches / $perPage));
$page = min($page, $totalPages);
$offset = ($page - 1) * $perPage;
$batches = array_slice($allBatches, $offset, $perPage);
$batchStart = $totalBatches === 0 ? 0 : $offset + 1;
$batchEnd = min($totalBatches, $offset + count($batches));
$studentPageUrl = static function (array $extra = []) use ($perPage): string {
    $query = ['per_page' => $perPage];
    foreach ($extra as $key => $value) {
        if ($value === null) {
            unset($query[$key]);
        } else {
            $query[$key] = $value;
        }
    }
    return app_url('ins/students') . '?' . http_build_query($query);
};

$pageTitle = 'Students';
$pageSubtitle = 'Assigned learners, support follow-up and batch progress.';
$activePage = 'students';
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
                            <span class="badge bg-white-1 text-fixed-white mb-2">Learner Desk</span>
                            <h4 class="fw-semibold mb-1 text-fixed-white">Student management</h4>
                            <p class="mb-0 op-8">Batch-wise learners, capacity, progress aur support follow-up ko ek clean register me dekhein.</p>
                        </div>
                        <div class="btn-list">
                            <a class="btn btn-light btn-wave" href="<?= h(app_url('ins/batches')); ?>">Batches</a>
                            <a class="btn btn-outline-light btn-wave" href="<?= h(app_url('ins/attendance')); ?>">Attendance</a>
                            <a class="btn btn-outline-light btn-wave" href="<?= h(app_url('ins/reports')); ?>">Reports</a>
                        </div>
                    </div>
                </div>
            </div>

            <?php
            $studentMetrics = [
                ['label' => 'Batches', 'value' => $totalBatches, 'icon' => 'bx bx-layer', 'tone' => 'primary'],
                ['label' => 'Active', 'value' => $activeBatches, 'icon' => 'bx bx-check-circle', 'tone' => 'success'],
                ['label' => 'Online', 'value' => $onlineBatches, 'icon' => 'bx bx-video', 'tone' => 'info'],
                ['label' => 'Capacity', 'value' => $totalCapacity, 'icon' => 'bx bx-user-plus', 'tone' => 'warning'],
            ];
            ?>
            <?php foreach ($studentMetrics as $metric): ?>
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
                            <div class="card-title mb-0">Student Batch Register</div>
                            <p class="mb-0 text-muted fs-12">Showing <?= h((string) $batchStart); ?>-<?= h((string) $batchEnd); ?> of <?= h((string) $totalBatches); ?> batches.</p>
                        </div>
                        <form method="get" action="<?= h(app_url('ins/students')); ?>" class="d-flex align-items-center gap-2 m-0">
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
                            <table class="table table-sm table-hover align-middle mb-0 gr-register-table gr-student-table" data-gr-register="1">
                                <colgroup><col style="width: 28%;"><col style="width: 22%;"><col style="width: 10%;"><col style="width: 12%;"><col style="width: 12%;"><col style="width: 10%;"><col style="width: 6%;"></colgroup>
                                <thead><tr><th>Batch</th><th>Course</th><th>Mode</th><th>Capacity</th><th>Progress</th><th>Support</th><th class="text-end">Action</th></tr></thead>
                                <tbody>
                                    <?php if (!$batches): ?><tr><td colspan="7" class="text-muted py-4">Create a batch first to assign students.</td></tr><?php endif; ?>
                                    <?php foreach ($batches as $batch): ?>
                                        <?php
                                        $status = (string) ($batch['status'] ?? 'active');
                                        $statusTone = $status === 'active' ? 'success' : ($status === 'paused' ? 'warning' : 'secondary');
                                        ?>
                                        <tr>
                                            <td>
                                                <span class="fw-semibold text-truncate gr-cell-title"><?= h($batch['batch_name']); ?></span>
                                                <span class="text-muted fs-12 gr-cell-subtitle"><?= h($batch['teacher_name'] ?: ucfirst($status)); ?></span>
                                            </td>
                                            <td>
                                                <span class="fw-semibold text-truncate gr-cell-title"><?= h($batch['course_title'] ?: 'No course'); ?></span>
                                                <span class="text-muted fs-12 gr-cell-subtitle">Batch learning group</span>
                                            </td>
                                            <td><span class="badge bg-primary-transparent text-primary"><?= h(ucfirst((string) ($batch['mode'] ?? 'online'))); ?></span></td>
                                            <td><span class="fw-semibold gr-inline-stat">0 / <?= h((string) ($batch['capacity'] ?? 0)); ?></span><span class="text-muted fs-12 gr-cell-subtitle">enrolled</span></td>
                                            <td><span class="badge bg-<?= h($statusTone); ?>-transparent text-<?= h($statusTone); ?>"><?= h(ucfirst($status)); ?></span><span class="text-muted fs-12 gr-cell-subtitle">Not started</span></td>
                                            <td><span class="text-muted fs-12 gr-cell-subtitle">Ready</span></td>
                                            <td class="text-end"><a class="btn btn-sm btn-light btn-wave" href="<?= h(app_url('ins/batches') . '#edit-batch-' . (int) $batch['id']); ?>" title="Open"><i class="bx bx-edit"></i></a></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 px-3 py-3 border-top">
                            <span class="text-muted fs-12"><?= h((string) $batchStart); ?>-<?= h((string) $batchEnd); ?> of <?= h((string) $totalBatches); ?> records</span>
                            <div class="btn-list mb-0">
                                <a class="btn btn-sm btn-light btn-wave <?= $page <= 1 ? 'disabled' : ''; ?>" href="<?= h($studentPageUrl(['page' => max(1, $page - 1)])); ?>">Prev</a>
                                <span class="badge bg-primary-transparent text-primary align-self-center">Page <?= h((string) $page); ?> / <?= h((string) $totalPages); ?></span>
                                <a class="btn btn-sm btn-light btn-wave <?= $page >= $totalPages ? 'disabled' : ''; ?>" href="<?= h($studentPageUrl(['page' => min($totalPages, $page + 1)])); ?>">Next</a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
    <style>
        .gr-student-table { min-width: 820px !important; }
        .gr-student-table th,
        .gr-student-table td {
            padding: .34rem .55rem !important;
            font-size: .76rem;
            line-height: 1.2;
            vertical-align: middle;
        }
        .gr-student-table thead th {
            font-size: .66rem;
            padding-block: .45rem !important;
            letter-spacing: .02em;
        }
        .gr-student-table .gr-cell-title {
            font-size: .8rem;
            line-height: 1.2;
            margin-bottom: .12rem;
        }
        .gr-student-table .gr-cell-subtitle {
            display: block;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            max-width: 100%;
        }
        .gr-student-table .gr-inline-stat {
            display: block;
            line-height: 1.1;
            margin-bottom: .1rem;
            white-space: nowrap;
        }
        .gr-student-table .badge {
            font-size: .66rem;
            padding: .22rem .42rem;
        }
        .gr-student-table .btn {
            width: 1.65rem;
            height: 1.65rem;
            min-width: 1.65rem;
            padding: 0 !important;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: .82rem;
        }
    </style>
<?php include __DIR__ . '/includes/footer.php'; ?>
