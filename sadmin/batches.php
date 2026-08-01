<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../ins/includes/functions.php';

$user = require_login('superadmin');
ensure_instructor_erp_tables();

$pageTitle = 'Batches';
$pageSubtitle = 'Instructor batch groups and seat capacity at one glance.';
$activePage = 'batches';

$perPageOptions = [10, 25, 50];
$perPage = (int) ($_GET['per_page'] ?? 10);
if (!in_array($perPage, $perPageOptions, true)) {
    $perPage = 10;
}
$page = max(1, (int) ($_GET['page'] ?? 1));
$summary = db()->query("
    SELECT COUNT(*) total,
           SUM(status = 'active') active,
           SUM(status <> 'active') inactive,
           COALESCE(SUM(capacity), 0) seats
    FROM instructor_batches
")->fetch_assoc() ?: [];
$totalRows = (int) ($summary['total'] ?? 0);
$activeRows = (int) ($summary['active'] ?? 0);
$inactiveRows = (int) ($summary['inactive'] ?? 0);
$seatRows = (int) ($summary['seats'] ?? 0);
$totalPages = max(1, (int) ceil($totalRows / $perPage));
if ($page > $totalPages) {
    $page = $totalPages;
}
$offset = ($page - 1) * $perPage;

$batchStmt = db()->prepare("
    SELECT b.id,
           b.batch_name,
           b.course_title,
           b.teacher_name,
           b.mode,
           b.start_date,
           b.class_time,
           b.capacity,
           b.status,
           u.full_name AS instructor_name
    FROM instructor_batches b
    LEFT JOIN users u ON u.id = b.instructor_id
    ORDER BY b.id DESC
    LIMIT ? OFFSET ?
");
$batchStmt->bind_param('ii', $perPage, $offset);
$batchStmt->execute();
$batches = $batchStmt->get_result()->fetch_all(MYSQLI_ASSOC);
$pageStart = $totalRows > 0 ? $offset + 1 : 0;
$pageEnd = min($offset + $perPage, $totalRows);
?>
<?php include __DIR__ . '/includes/header.php'; ?>
<?php include __DIR__ . '/includes/sidebar.php'; ?>
<main class="main sadmin-batches-main">
    <?php include __DIR__ . '/includes/topbar.php'; ?>
    <section class="content sadmin-batches-page">
        <section class="card custom-card batches-hero-card">
            <div class="card-header justify-content-between">
                <div>
                    <span class="text-primary fs-11 fw-semibold text-uppercase d-block mb-1">Batch Management</span>
                    <h5 class="mb-1 fw-semibold">Batches</h5>
                    <p class="mb-0 text-muted fs-12">Instructor batch groups, timing and seat capacity.</p>
                </div>
            </div>
            <div class="card-body">
                <div class="batches-mini-stats">
                    <article><span>Total</span><strong><?= h((string) $totalRows); ?></strong></article>
                    <article><span>Active</span><strong><?= h((string) $activeRows); ?></strong></article>
                    <article><span>Inactive / Other</span><strong><?= h((string) $inactiveRows); ?></strong></article>
                    <article><span>Total Seats</span><strong><?= h((string) $seatRows); ?></strong></article>
                </div>
            </div>
        </section>

        <section class="card custom-card batches-register-card">
            <div class="card-header justify-content-between">
                <div>
                    <h6 class="mb-1 fw-semibold">Batch Register</h6>
                    <p class="mb-0 text-muted fs-12">Showing <?= h((string) $pageStart); ?>-<?= h((string) $pageEnd); ?> of <?= h((string) $totalRows); ?> batches.</p>
                </div>
                <form class="sadmin-page-size" method="get">
                    <input type="hidden" name="page" value="1">
                    <select class="form-select form-select-sm" name="per_page" onchange="this.form.submit()">
                        <?php foreach ($perPageOptions as $option): ?>
                            <option value="<?= (int) $option; ?>" <?= $perPage === $option ? 'selected' : ''; ?>><?= (int) $option; ?> rows</option>
                        <?php endforeach; ?>
                    </select>
                </form>
            </div>
            <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0 gr-register-table sadmin-batches-table">
                    <thead>
                    <tr>
                        <th>Batch</th>
                        <th>Course</th>
                        <th>Instructor</th>
                        <th>Mode</th>
                        <th>Schedule</th>
                        <th>Capacity</th>
                        <th>Status</th>
                    </tr>
                    </thead>
                    <tbody>
                        <?php if (!$batches): ?>
                            <tr><td colspan="7" class="empty-state premium-empty"><strong>No batches found</strong><small>Instructor batches will appear here after creation.</small></td></tr>
                        <?php endif; ?>
                        <?php foreach ($batches as $batch): ?>
                            <tr>
                                <td><span class="gr-cell-title"><?= h($batch['batch_name']); ?></span><span class="gr-cell-subtitle"><?= h($batch['teacher_name'] ?: 'Teacher not set'); ?></span></td>
                                <td><span class="gr-cell-title"><?= h($batch['course_title']); ?></span></td>
                                <td><span class="gr-cell-title"><?= h($batch['instructor_name'] ?: 'Unassigned'); ?></span></td>
                                <td><span class="badge bg-primary-transparent text-primary"><?= h(ucfirst((string) $batch['mode'])); ?></span></td>
                                <td><span class="gr-cell-title"><?= h((string) ($batch['start_date'] ?: 'N/A')); ?></span><span class="gr-cell-subtitle"><?= h($batch['class_time'] ?: 'Time not set'); ?></span></td>
                                <td><?= h((string) (int) ($batch['capacity'] ?? 0)); ?> seats</td>
                                <td>
                                    <span class="status-pill <?= ((string) ($batch['status'] ?? '') === 'active') ? 'ready' : 'empty'; ?>">
                                        <?= h(ucfirst((string) ($batch['status'] ?? 'draft'))); ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            </div>
            <div class="card-footer sadmin-pagination">
                <span><?= h((string) $pageStart); ?>-<?= h((string) $pageEnd); ?> of <?= h((string) $totalRows); ?> records</span>
                <div>
                    <a class="btn btn-sm btn-light <?= $page <= 1 ? 'disabled' : ''; ?>" href="<?= h(app_url('sadmin/batches?page=' . max(1, $page - 1) . '&per_page=' . $perPage)); ?>">Prev</a>
                    <span class="page-chip">Page <?= h((string) $page); ?> / <?= h((string) $totalPages); ?></span>
                    <a class="btn btn-sm btn-light <?= $page >= $totalPages ? 'disabled' : ''; ?>" href="<?= h(app_url('sadmin/batches?page=' . min($totalPages, $page + 1) . '&per_page=' . $perPage)); ?>">Next</a>
                </div>
            </div>
        </section>
    </section>
    <style>
        .sadmin-batches-main .sadmin-batches-page { padding-top: 1.25rem; }
        .sadmin-batches-main .batches-hero-card,
        .sadmin-batches-main .batches-register-card {
            border: 0;
            border-radius: .65rem;
            box-shadow: 0 .85rem 1.85rem rgba(15, 23, 42, .045);
            overflow: hidden;
        }
        .sadmin-batches-main .batches-hero-card .card-header,
        .sadmin-batches-main .batches-register-card .card-header {
            padding: .75rem 1rem;
            border-bottom: 1px solid var(--default-border);
            background: var(--custom-white);
        }
        .sadmin-batches-main .batches-mini-stats {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: .65rem;
        }
        .sadmin-batches-main .batches-mini-stats article {
            display: grid;
            gap: .25rem;
            padding: .65rem .85rem;
            border: 1px solid var(--default-border);
            border-radius: .5rem;
            background: var(--default-background);
        }
        .sadmin-batches-main .batches-mini-stats span {
            color: var(--text-muted);
            font-size: .68rem;
            font-weight: 700;
            text-transform: uppercase;
        }
        .sadmin-batches-main .batches-mini-stats strong { font-size: 1.1rem; line-height: 1; }
        .sadmin-batches-main .sadmin-page-size { margin: 0; }
        .sadmin-batches-main .sadmin-page-size .form-select {
            min-width: 6.8rem;
            min-height: 2rem;
            font-size: .74rem;
        }
        .sadmin-batches-main .sadmin-batches-table { min-width: 66rem; }
        .sadmin-batches-main .sadmin-batches-table th,
        .sadmin-batches-main .sadmin-batches-table td {
            padding: .42rem .65rem !important;
            font-size: .72rem;
            line-height: 1.2;
            vertical-align: middle;
        }
        .sadmin-batches-main .sadmin-batches-table th {
            background: var(--default-background);
            font-size: .65rem;
            letter-spacing: .025em;
            text-transform: uppercase;
        }
        .sadmin-batches-main .sadmin-batches-table .gr-cell-title {
            display: block;
            max-width: 17rem;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            font-size: .74rem;
            font-weight: 700;
        }
        .sadmin-batches-main .sadmin-batches-table .gr-cell-subtitle {
            display: block;
            max-width: 17rem;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            color: var(--text-muted);
            font-size: .66rem;
        }
        .sadmin-batches-main .sadmin-batches-table .status-pill,
        .sadmin-batches-main .sadmin-batches-table .badge {
            min-height: 1.2rem;
            padding: .15rem .45rem;
            border-radius: 999px;
            font-size: .62rem;
            font-weight: 700;
        }
        .sadmin-batches-main .sadmin-pagination {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: .75rem;
            padding: .65rem 1rem;
            border-top: 1px solid var(--default-border);
            background: var(--custom-white);
            color: var(--text-muted);
            font-size: .74rem;
        }
        .sadmin-batches-main .sadmin-pagination > div {
            display: flex;
            align-items: center;
            gap: .35rem;
        }
        .sadmin-batches-main .sadmin-pagination .btn {
            min-height: 1.55rem;
            padding: .18rem .55rem;
            font-size: .67rem;
        }
        .sadmin-batches-main .page-chip {
            display: inline-flex;
            align-items: center;
            min-height: 1.55rem;
            padding: .18rem .55rem;
            border-radius: .35rem;
            background: rgba(var(--primary-rgb), .1);
            color: rgb(var(--primary-rgb));
            font-size: .67rem;
            font-weight: 700;
        }
        .sadmin-batches-main .footer { margin-inline: 0 !important; width: 100%; }
        @media (max-width: 767.98px) {
            .sadmin-batches-main .batches-mini-stats { grid-template-columns: repeat(2, minmax(0, 1fr)); }
        }
    </style>
</main>
<?php include __DIR__ . '/includes/footer.php'; ?>

