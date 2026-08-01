<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/govt_exam_prep.php';

$user = require_login('superadmin');
gov_exam_ensure_tables();
$pageTitle = 'Govt Exam Live';
$pageSubtitle = 'Live sessions.';
$activePage = 'govt-prep-live';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['csrf_token'] ?? null)) {
        $_SESSION['gov_exam_error'] = 'Security token expired.';
        redirect('sadmin/govt-prep-live');
    }
    try {
        $id = (int) ($_POST['id'] ?? 0);
        $cat = (int) ($_POST['category_id'] ?? 0);
        $sub = (int) ($_POST['subcategory_id'] ?? 0) ?: null;
        $title = substr(trim((string) ($_POST['title'] ?? '')), 0, 180);
        $desc = substr(trim((string) ($_POST['description'] ?? '')), 0, 255);
        $url = substr(trim((string) ($_POST['live_url'] ?? '')), 0, 255);
        $scheduled = trim((string) ($_POST['scheduled_at'] ?? ''));
        $scheduled = $scheduled !== '' ? str_replace('T', ' ', $scheduled) . ':00' : null;
        $status = in_array($_POST['status'] ?? '', ['scheduled', 'live', 'completed', 'cancelled'], true) ? (string) $_POST['status'] : 'scheduled';
        if ($cat <= 0 || $title === '') {
            throw new RuntimeException('Category and title required.');
        }
        if ($id > 0) {
            $stmt = db()->prepare('UPDATE gov_exam_live_sessions SET category_id=?,subcategory_id=?,title=?,description=?,live_url=?,scheduled_at=?,status=? WHERE id=?');
            $stmt->bind_param('iisssssi', $cat, $sub, $title, $desc, $url, $scheduled, $status, $id);
            $stmt->execute();
            $_SESSION['gov_exam_message'] = 'Live session updated.';
        } else {
            $stmt = db()->prepare('INSERT INTO gov_exam_live_sessions (category_id,subcategory_id,title,description,live_url,scheduled_at,status) VALUES (?,?,?,?,?,?,?)');
            $stmt->bind_param('iisssss', $cat, $sub, $title, $desc, $url, $scheduled, $status);
            $stmt->execute();
            $_SESSION['gov_exam_message'] = 'Live session added.';
        }
    } catch (Throwable $e) {
        $_SESSION['gov_exam_error'] = $e->getMessage();
    }
    redirect('sadmin/govt-prep-live');
}

$perPageOptions = [10, 25, 50];
$perPage = (int) ($_GET['per_page'] ?? 10);
if (!in_array($perPage, $perPageOptions, true)) {
    $perPage = 10;
}
$page = max(1, (int) ($_GET['page'] ?? 1));
$summary = db()->query("SELECT COUNT(*) total, SUM(status='scheduled') scheduled, SUM(status='live') live_total, SUM(status='completed') completed FROM gov_exam_live_sessions")->fetch_assoc() ?: [];
$totalRows = (int) ($summary['total'] ?? 0);
$scheduledRows = (int) ($summary['scheduled'] ?? 0);
$liveRows = (int) ($summary['live_total'] ?? 0);
$completedRows = (int) ($summary['completed'] ?? 0);
$totalPages = max(1, (int) ceil($totalRows / $perPage));
if ($page > $totalPages) {
    $page = $totalPages;
}
$offset = ($page - 1) * $perPage;
$stmt = db()->prepare("SELECT l.*, c.name category_name, s.name subcategory_name FROM gov_exam_live_sessions l LEFT JOIN gov_exam_categories c ON c.id=l.category_id LEFT JOIN gov_exam_categories s ON s.id=l.subcategory_id ORDER BY COALESCE(l.scheduled_at,l.created_at) DESC LIMIT ? OFFSET ?");
$stmt->bind_param('ii', $perPage, $offset);
$stmt->execute();
$rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$modalRows = db()->query("SELECT l.*, c.name category_name, s.name subcategory_name FROM gov_exam_live_sessions l LEFT JOIN gov_exam_categories c ON c.id=l.category_id LEFT JOIN gov_exam_categories s ON s.id=l.subcategory_id ORDER BY COALESCE(l.scheduled_at,l.created_at) DESC LIMIT 500")->fetch_all(MYSQLI_ASSOC);
$pageStart = $totalRows > 0 ? $offset + 1 : 0;
$pageEnd = min($offset + $perPage, $totalRows);
[$message, $error] = gov_exam_flash();
?>
<?php include __DIR__ . '/includes/header.php'; ?>
<?php include __DIR__ . '/includes/sidebar.php'; ?>
<main class="main sadmin-govlive-main">
    <?php include __DIR__ . '/includes/topbar.php'; ?>
    <section class="content govt-prep-page">
        <?php if ($message): ?><div class="notice"><?= h($message); ?></div><?php endif; ?>
        <?php if ($error): ?><div class="notice danger"><?= h($error); ?></div><?php endif; ?>
        <section class="card custom-card govlive-hero-card">
            <div class="card-header justify-content-between">
                <div>
                    <span class="text-primary fs-11 fw-semibold text-uppercase d-block mb-1">Govt Exam Prep</span>
                    <h5 class="mb-1 fw-semibold">Live Classes</h5>
                    <p class="mb-0 text-muted fs-12">Schedule live sessions category/subcategory wise.</p>
                </div>
                <a class="btn btn-primary btn-wave" href="#add-live">Add Live</a>
            </div>
            <div class="card-body">
                <div class="govlive-mini-stats">
                    <article><span>Total</span><strong><?= h((string) $totalRows); ?></strong></article>
                    <article><span>Scheduled</span><strong><?= h((string) $scheduledRows); ?></strong></article>
                    <article><span>Live</span><strong><?= h((string) $liveRows); ?></strong></article>
                    <article><span>Completed</span><strong><?= h((string) $completedRows); ?></strong></article>
                </div>
            </div>
        </section>

        <section class="card custom-card govlive-register-card">
            <div class="card-header justify-content-between">
                <div>
                    <span class="text-primary fs-11 fw-semibold text-uppercase d-block mb-1">Live Register</span>
                    <h6 class="mb-1 fw-semibold">Showing <?= h((string) $pageStart); ?>-<?= h((string) $pageEnd); ?> of <?= h((string) $totalRows); ?> records</h6>
                    <p class="mb-0 text-muted fs-12">Compact table with schedule, category and status.</p>
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
                <table class="table table-hover align-middle mb-0 gr-register-table govlive-table">
                    <thead><tr><th>Live</th><th>Category</th><th>Schedule</th><th>Status</th><th class="text-end">Edit</th></tr></thead>
                    <tbody>
                    <?php foreach ($rows as $row): ?>
                        <tr>
                            <td><span class="gr-cell-title"><?= h($row['title']); ?></span><span class="gr-cell-subtitle"><?= h($row['live_url'] ?: 'No URL'); ?></span></td>
                            <td><span class="gr-cell-title"><?= h($row['category_name'] ?: '-'); ?></span><?= $row['subcategory_name'] ? '<span class="gr-cell-subtitle">' . h($row['subcategory_name']) . '</span>' : ''; ?></td>
                            <td><span class="gr-cell-title"><?= h((string) ($row['scheduled_at'] ?: 'Not set')); ?></span></td>
                            <td><?= gov_exam_status($row['status']); ?></td>
                            <td class="text-end"><a class="btn btn-sm btn-light btn-wave" href="#edit-live-<?= (int) $row['id']; ?>">Edit</a></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$rows): ?><tr><td colspan="5" class="empty-state premium-empty"><strong>No live sessions yet</strong><small>Schedule your first government exam live class.</small></td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
            </div>
            <div class="card-footer sadmin-pagination">
                <span><?= h((string) $pageStart); ?>-<?= h((string) $pageEnd); ?> of <?= h((string) $totalRows); ?> records</span>
                <div>
                    <a class="btn btn-sm btn-light <?= $page <= 1 ? 'disabled' : ''; ?>" href="<?= h(app_url('sadmin/govt-prep-live?page=' . max(1, $page - 1) . '&per_page=' . $perPage)); ?>">Prev</a>
                    <span class="page-chip">Page <?= h((string) $page); ?> / <?= h((string) $totalPages); ?></span>
                    <a class="btn btn-sm btn-light <?= $page >= $totalPages ? 'disabled' : ''; ?>" href="<?= h(app_url('sadmin/govt-prep-live?page=' . min($totalPages, $page + 1) . '&per_page=' . $perPage)); ?>">Next</a>
                </div>
            </div>
        </section>
        <div id="add-live" class="modal-overlay"><form class="modal-box wide-modal premium-admin-modal" method="post"><input type="hidden" name="csrf_token" value="<?= h(csrf_token()); ?>"><div class="modal-head"><h2>Add Live</h2><a href="#">&times;</a></div><div class="form-grid"><label>Category<select name="category_id" required><?= gov_exam_category_options(); ?></select></label><label>Subcategory<select name="subcategory_id"><?= gov_exam_category_options(); ?></select></label><label>Title<input name="title" required></label><label>Live URL<input name="live_url"></label><label>Scheduled At<input type="datetime-local" name="scheduled_at"></label><label>Status<select name="status"><option value="scheduled">Scheduled</option><option value="live">Live</option><option value="completed">Completed</option><option value="cancelled">Cancelled</option></select></label><label class="span-2">Description<textarea name="description" rows="2"></textarea></label></div><div class="modal-actions"><button type="submit">Save</button></div></form></div>
        <?php foreach ($modalRows as $row): $dt = $row['scheduled_at'] ? date('Y-m-d\TH:i', strtotime($row['scheduled_at'])) : ''; ?><div id="edit-live-<?= (int) $row['id']; ?>" class="modal-overlay"><form class="modal-box wide-modal premium-admin-modal" method="post"><input type="hidden" name="csrf_token" value="<?= h(csrf_token()); ?>"><input type="hidden" name="id" value="<?= (int) $row['id']; ?>"><div class="modal-head"><h2>Edit Live</h2><a href="#">&times;</a></div><div class="form-grid"><label>Category<select name="category_id" required><?= gov_exam_category_options((int) $row['category_id']); ?></select></label><label>Subcategory<select name="subcategory_id"><?= gov_exam_category_options((int) ($row['subcategory_id'] ?? 0)); ?></select></label><label>Title<input name="title" value="<?= h($row['title']); ?>" required></label><label>Live URL<input name="live_url" value="<?= h($row['live_url']); ?>"></label><label>Scheduled At<input type="datetime-local" name="scheduled_at" value="<?= h($dt); ?>"></label><label>Status<select name="status"><?php foreach (['scheduled', 'live', 'completed', 'cancelled'] as $s): ?><option value="<?= $s; ?>" <?= $row['status'] === $s ? 'selected' : ''; ?>><?= ucfirst($s); ?></option><?php endforeach; ?></select></label><label class="span-2">Description<textarea name="description" rows="2"><?= h($row['description']); ?></textarea></label></div><div class="modal-actions"><button type="submit">Update</button></div></form></div><?php endforeach; ?>
    </section>
    <style>
        .sadmin-govlive-main .govt-prep-page { padding-top: 1.25rem; }
        .sadmin-govlive-main .govlive-hero-card,
        .sadmin-govlive-main .govlive-register-card {
            border: 0;
            border-radius: .65rem;
            box-shadow: 0 .85rem 1.85rem rgba(15, 23, 42, .045);
            overflow: hidden;
        }
        .sadmin-govlive-main .govlive-hero-card .card-header,
        .sadmin-govlive-main .govlive-register-card .card-header {
            padding: .75rem 1rem;
            border-bottom: 1px solid var(--default-border);
            background: var(--custom-white);
        }
        .sadmin-govlive-main .govlive-mini-stats {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: .65rem;
        }
        .sadmin-govlive-main .govlive-mini-stats article {
            display: grid;
            gap: .25rem;
            padding: .7rem .85rem;
            border: 1px solid var(--default-border);
            border-radius: .5rem;
            background: var(--default-background);
        }
        .sadmin-govlive-main .govlive-mini-stats span {
            color: var(--text-muted);
            font-size: .68rem;
            font-weight: 700;
            text-transform: uppercase;
        }
        .sadmin-govlive-main .govlive-mini-stats strong { font-size: 1.15rem; line-height: 1; }
        .sadmin-govlive-main .sadmin-page-size { margin: 0; }
        .sadmin-govlive-main .sadmin-page-size .form-select {
            min-width: 6.8rem;
            min-height: 2rem;
            font-size: .74rem;
        }
        .sadmin-govlive-main .govlive-table { min-width: 56rem; }
        .sadmin-govlive-main .govlive-table th,
        .sadmin-govlive-main .govlive-table td {
            padding: .42rem .65rem !important;
            font-size: .73rem;
            line-height: 1.2;
            vertical-align: middle;
        }
        .sadmin-govlive-main .govlive-table th {
            background: var(--default-background);
            font-size: .65rem;
            letter-spacing: .025em;
        }
        .sadmin-govlive-main .govlive-table .gr-cell-title { font-size: .74rem; line-height: 1.2; }
        .sadmin-govlive-main .govlive-table .gr-cell-subtitle { font-size: .67rem; line-height: 1.2; }
        .sadmin-govlive-main .govlive-table .status-pill,
        .sadmin-govlive-main .govlive-table .badge {
            min-height: 1.2rem;
            padding: .15rem .45rem;
            border-radius: 999px;
            font-size: .62rem;
            font-weight: 700;
        }
        .sadmin-govlive-main .govlive-table .btn-sm,
        .sadmin-govlive-main .sadmin-pagination .btn {
            min-height: 1.55rem;
            padding: .18rem .55rem;
            font-size: .67rem;
        }
        .sadmin-govlive-main .sadmin-pagination {
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
        .sadmin-govlive-main .sadmin-pagination > div {
            display: flex;
            align-items: center;
            gap: .35rem;
        }
        .sadmin-govlive-main .page-chip {
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
        .sadmin-govlive-main .footer { margin-inline: 0 !important; width: 100%; }
        @media (max-width: 767.98px) {
            .sadmin-govlive-main .govlive-mini-stats { grid-template-columns: repeat(2, minmax(0, 1fr)); }
        }
    </style>
</main>
<?php include __DIR__ . '/includes/footer.php'; ?>
