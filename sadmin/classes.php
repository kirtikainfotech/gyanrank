<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../ins/includes/functions.php';

$user = require_login('superadmin');
ensure_instructor_erp_tables();

$pageTitle = 'Classes';
$pageSubtitle = 'All instructor classes (scheduled, live, completed).';
$activePage = 'classes';

$perPageOptions = [10, 25, 50];
$perPage = (int) ($_GET['per_page'] ?? 10);
if (!in_array($perPage, $perPageOptions, true)) {
    $perPage = 10;
}
$page = max(1, (int) ($_GET['page'] ?? 1));
$summary = db()->query("
    SELECT COUNT(*) total,
           SUM(class_status = 'scheduled') scheduled,
           SUM(class_status = 'live') live,
           SUM(class_status = 'completed') completed
    FROM instructor_classes
")->fetch_assoc() ?: [];
$totalRows = (int) ($summary['total'] ?? 0);
$scheduledRows = (int) ($summary['scheduled'] ?? 0);
$liveRows = (int) ($summary['live'] ?? 0);
$completedRows = (int) ($summary['completed'] ?? 0);
$totalPages = max(1, (int) ceil($totalRows / $perPage));
if ($page > $totalPages) {
    $page = $totalPages;
}
$offset = ($page - 1) * $perPage;

$classStmt = db()->prepare("
    SELECT cl.id,
           cl.class_title,
           cl.class_type,
           cl.class_date,
           cl.starts_at,
           cl.duration_minutes,
           cl.meeting_link,
           cl.room_name,
           cl.class_status,
           b.batch_name,
           u.full_name AS instructor_name
    FROM instructor_classes cl
    LEFT JOIN instructor_batches b ON b.id = cl.batch_id
    LEFT JOIN users u ON u.id = cl.instructor_id
    ORDER BY cl.class_date DESC, cl.id DESC
    LIMIT ? OFFSET ?
");
$classStmt->bind_param('ii', $perPage, $offset);
$classStmt->execute();
$classes = $classStmt->get_result()->fetch_all(MYSQLI_ASSOC);
$pageStart = $totalRows > 0 ? $offset + 1 : 0;
$pageEnd = min($offset + $perPage, $totalRows);
?>
<?php include __DIR__ . '/includes/header.php'; ?>
<?php include __DIR__ . '/includes/sidebar.php'; ?>
<main class="main sadmin-classes-main">
    <?php include __DIR__ . '/includes/topbar.php'; ?>
    <section class="content sadmin-classes-page">
        <section class="card custom-card classes-hero-card">
            <div class="card-header justify-content-between">
                <div>
                    <span class="text-primary fs-11 fw-semibold text-uppercase d-block mb-1">Class Schedule</span>
                    <h5 class="mb-1 fw-semibold">Classes</h5>
                    <p class="mb-0 text-muted fs-12">Monitor live, scheduled and completed instructor sessions.</p>
                </div>
            </div>
            <div class="card-body">
                <div class="classes-mini-stats">
                    <article><span>Total</span><strong><?= h((string) $totalRows); ?></strong></article>
                    <article><span>Scheduled</span><strong><?= h((string) $scheduledRows); ?></strong></article>
                    <article><span>Live</span><strong><?= h((string) $liveRows); ?></strong></article>
                    <article><span>Completed</span><strong><?= h((string) $completedRows); ?></strong></article>
                </div>
            </div>
        </section>

        <section class="card custom-card classes-register-card">
            <div class="card-header justify-content-between">
                <div>
                    <h6 class="mb-1 fw-semibold">Class Register</h6>
                    <p class="mb-0 text-muted fs-12">Showing <?= h((string) $pageStart); ?>-<?= h((string) $pageEnd); ?> of <?= h((string) $totalRows); ?> classes.</p>
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
                <table class="table table-hover align-middle mb-0 gr-register-table sadmin-classes-table">
                    <thead>
                    <tr>
                        <th>Class</th>
                        <th>Batch</th>
                        <th>Instructor</th>
                        <th>Type</th>
                        <th>Date</th>
                        <th>Start</th>
                        <th>Duration</th>
                        <th>Status</th>
                    </tr>
                    </thead>
                    <tbody>
                        <?php if (!$classes): ?>
                            <tr><td colspan="8" class="empty-state premium-empty"><strong>No classes found</strong><small>Instructor live and scheduled classes will appear here.</small></td></tr>
                        <?php endif; ?>
                        <?php foreach ($classes as $class): ?>
                            <tr>
                                <td><span class="gr-cell-title"><?= h($class['class_title']); ?></span><span class="gr-cell-subtitle"><?= h($class['room_name'] ?: 'room not set'); ?></span></td>
                                <td><span class="gr-cell-title"><?= h($class['batch_name'] ?: 'N/A'); ?></span></td>
                                <td><span class="gr-cell-title"><?= h($class['instructor_name'] ?: 'Unassigned'); ?></span></td>
                                <td><span class="badge bg-primary-transparent text-primary"><?= h(ucfirst((string) $class['class_type'])); ?></span></td>
                                <td><span class="gr-cell-title"><?= h((string) ($class['class_date'] ?: 'N/A')); ?></span></td>
                                <td><?= h((string) ($class['starts_at'] ?: 'N/A')); ?></td>
                                <td><?= h((string) (int) ($class['duration_minutes'] ?? 0)); ?> min</td>
                                <td>
                                    <span class="status-pill <?= ((string) ($class['class_status'] ?? '') === 'scheduled') ? 'ready' : 'empty'; ?>">
                                        <?= h(ucfirst((string) ($class['class_status'] ?? 'draft'))); ?>
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
                    <a class="btn btn-sm btn-light <?= $page <= 1 ? 'disabled' : ''; ?>" href="<?= h(app_url('sadmin/classes?page=' . max(1, $page - 1) . '&per_page=' . $perPage)); ?>">Prev</a>
                    <span class="page-chip">Page <?= h((string) $page); ?> / <?= h((string) $totalPages); ?></span>
                    <a class="btn btn-sm btn-light <?= $page >= $totalPages ? 'disabled' : ''; ?>" href="<?= h(app_url('sadmin/classes?page=' . min($totalPages, $page + 1) . '&per_page=' . $perPage)); ?>">Next</a>
                </div>
            </div>
        </section>
    </section>
    <style>
        .sadmin-classes-main .sadmin-classes-page { padding-top: 1.25rem; }
        .sadmin-classes-main .classes-hero-card,
        .sadmin-classes-main .classes-register-card {
            border: 0;
            border-radius: .65rem;
            box-shadow: 0 .85rem 1.85rem rgba(15, 23, 42, .045);
            overflow: hidden;
        }
        .sadmin-classes-main .classes-hero-card .card-header,
        .sadmin-classes-main .classes-register-card .card-header {
            padding: .75rem 1rem;
            border-bottom: 1px solid var(--default-border);
            background: var(--custom-white);
        }
        .sadmin-classes-main .classes-mini-stats {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: .65rem;
        }
        .sadmin-classes-main .classes-mini-stats article {
            display: grid;
            gap: .25rem;
            padding: .65rem .85rem;
            border: 1px solid var(--default-border);
            border-radius: .5rem;
            background: var(--default-background);
        }
        .sadmin-classes-main .classes-mini-stats span {
            color: var(--text-muted);
            font-size: .68rem;
            font-weight: 700;
            text-transform: uppercase;
        }
        .sadmin-classes-main .classes-mini-stats strong { font-size: 1.1rem; line-height: 1; }
        .sadmin-classes-main .sadmin-page-size { margin: 0; }
        .sadmin-classes-main .sadmin-page-size .form-select {
            min-width: 6.8rem;
            min-height: 2rem;
            font-size: .74rem;
        }
        .sadmin-classes-main .sadmin-classes-table { min-width: 68rem; }
        .sadmin-classes-main .sadmin-classes-table th,
        .sadmin-classes-main .sadmin-classes-table td {
            padding: .42rem .65rem !important;
            font-size: .72rem;
            line-height: 1.2;
            vertical-align: middle;
        }
        .sadmin-classes-main .sadmin-classes-table th {
            background: var(--default-background);
            font-size: .65rem;
            letter-spacing: .025em;
            text-transform: uppercase;
        }
        .sadmin-classes-main .sadmin-classes-table .gr-cell-title {
            display: block;
            max-width: 18rem;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            font-size: .74rem;
            font-weight: 700;
        }
        .sadmin-classes-main .sadmin-classes-table .gr-cell-subtitle {
            display: block;
            max-width: 18rem;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            color: var(--text-muted);
            font-size: .66rem;
        }
        .sadmin-classes-main .sadmin-classes-table .status-pill,
        .sadmin-classes-main .sadmin-classes-table .badge {
            min-height: 1.2rem;
            padding: .15rem .45rem;
            border-radius: 999px;
            font-size: .62rem;
            font-weight: 700;
        }
        .sadmin-classes-main .sadmin-pagination {
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
        .sadmin-classes-main .sadmin-pagination > div {
            display: flex;
            align-items: center;
            gap: .35rem;
        }
        .sadmin-classes-main .sadmin-pagination .btn {
            min-height: 1.55rem;
            padding: .18rem .55rem;
            font-size: .67rem;
        }
        .sadmin-classes-main .page-chip {
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
        .sadmin-classes-main .footer { margin-inline: 0 !important; width: 100%; }
        @media (max-width: 767.98px) {
            .sadmin-classes-main .classes-mini-stats { grid-template-columns: repeat(2, minmax(0, 1fr)); }
        }
    </style>
</main>
<?php include __DIR__ . '/includes/footer.php'; ?>

