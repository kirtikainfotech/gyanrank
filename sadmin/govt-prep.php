<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/govt_exam_prep.php';

$user = require_login('superadmin');
gov_exam_ensure_tables();
$pageTitle = 'Government Exam Preparation';
$pageSubtitle = 'Documents, live classes and mock tests category-wise.';
$activePage = 'govt-prep';
$counts = [
    'Categories' => (int) (db()->query('SELECT COUNT(*) total FROM gov_exam_categories')->fetch_assoc()['total'] ?? 0),
    'Documents' => (int) (db()->query('SELECT COUNT(*) total FROM gov_exam_documents')->fetch_assoc()['total'] ?? 0),
    'Live' => (int) (db()->query('SELECT COUNT(*) total FROM gov_exam_live_sessions')->fetch_assoc()['total'] ?? 0),
    'Mock Tests' => (int) (db()->query('SELECT COUNT(*) total FROM gov_exam_mock_tests')->fetch_assoc()['total'] ?? 0),
    'Questions' => (int) (db()->query('SELECT COUNT(*) total FROM gov_exam_mock_questions')->fetch_assoc()['total'] ?? 0),
];
?>
<?php include __DIR__ . '/includes/header.php'; ?>
<?php include __DIR__ . '/includes/sidebar.php'; ?>
<main class="main sadmin-govt-main">
    <?php include __DIR__ . '/includes/topbar.php'; ?>
    <section class="content govt-prep-page">
        <section class="card custom-card gov-prep-card gov-prep-overview">
            <div class="card-header justify-content-between">
                <div>
                    <span class="text-primary fs-11 fw-semibold text-uppercase d-block mb-1">Govt Exam Prep</span>
                    <h5 class="mb-1 fw-semibold">Government Exam Preparation</h5>
                    <p class="mb-0 text-muted fs-12">Category/subcategory wise documents, live classes, mocks and question bank.</p>
                </div>
                <a class="btn btn-primary btn-wave" href="<?= h(app_url('sadmin/govt-prep-categories')); ?>">Manage Categories</a>
            </div>
            <div class="card-body">
            <div class="govt-mini-stats">
                <?php foreach ($counts as $label => $total): ?>
                    <article><span><?= h($label); ?></span><strong><?= h((string) $total); ?></strong></article>
                <?php endforeach; ?>
            </div>
            </div>
        </section>
        <section class="gov-prep-grid-menu">
            <a class="gov-module-card" href="<?= h(app_url('sadmin/govt-prep-categories')); ?>"><span><i class="bx bx-grid-alt"></i></span><div><h3>Categories</h3><p>Exam category and subcategory.</p></div><b>Open</b></a>
            <a class="gov-module-card" href="<?= h(app_url('sadmin/govt-prep-documents')); ?>"><span><i class="bx bx-file"></i></span><div><h3>Documents</h3><p>PDF/document links and notes.</p></div><b>Open</b></a>
            <a class="gov-module-card" href="<?= h(app_url('sadmin/govt-prep-live')); ?>"><span><i class="bx bx-video"></i></span><div><h3>Live</h3><p>Scheduled or live sessions.</p></div><b>Open</b></a>
            <a class="gov-module-card" href="<?= h(app_url('sadmin/govt-prep-mocks')); ?>"><span><i class="bx bx-notepad"></i></span><div><h3>Mock Tests</h3><p>Exam mock test master.</p></div><b>Open</b></a>
            <a class="gov-module-card" href="<?= h(app_url('sadmin/govt-prep-questions')); ?>"><span><i class="bx bx-question-mark"></i></span><div><h3>Mock Questions</h3><p>Language-wise questions and answers.</p></div><b>Open</b></a>
        </section>
    </section>
    <style>
        .sadmin-govt-main .govt-prep-page {
            padding-top: 1.25rem;
        }
        .sadmin-govt-main .gov-prep-card {
            border: 0;
            border-radius: .65rem;
            box-shadow: 0 .85rem 1.85rem rgba(15, 23, 42, .045);
            overflow: hidden;
        }
        .sadmin-govt-main .gov-prep-card .card-header {
            padding: .75rem 1rem;
            border-bottom: 1px solid var(--default-border);
            background: var(--custom-white);
        }
        .sadmin-govt-main .govt-mini-stats {
            display: grid;
            grid-template-columns: repeat(5, minmax(0, 1fr));
            gap: .65rem;
        }
        .sadmin-govt-main .govt-mini-stats article {
            display: grid;
            gap: .25rem;
            padding: .7rem .85rem;
            border: 1px solid var(--default-border);
            border-radius: .5rem;
            background: var(--default-background);
        }
        .sadmin-govt-main .govt-mini-stats span {
            color: var(--text-muted);
            font-size: .68rem;
            font-weight: 700;
            text-transform: uppercase;
        }
        .sadmin-govt-main .govt-mini-stats strong {
            font-size: 1.15rem;
            line-height: 1;
        }
        .sadmin-govt-main .gov-prep-grid-menu {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: .8rem;
        }
        .sadmin-govt-main .gov-module-card {
            display: grid;
            grid-template-columns: 2.35rem minmax(0, 1fr) auto;
            align-items: center;
            gap: .75rem;
            padding: .85rem 1rem;
            border: 1px solid var(--default-border);
            border-radius: .65rem;
            background: var(--custom-white);
            color: var(--default-text-color);
            text-decoration: none;
            box-shadow: 0 .65rem 1.5rem rgba(15, 23, 42, .04);
            transition: transform .18s ease, border-color .18s ease, box-shadow .18s ease;
        }
        .sadmin-govt-main .gov-module-card:hover {
            border-color: rgba(var(--primary-rgb), .28);
            box-shadow: 0 1rem 2rem rgba(15, 23, 42, .07);
            transform: translateY(-1px);
        }
        .sadmin-govt-main .gov-module-card > span {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 2.35rem;
            height: 2.35rem;
            border-radius: .55rem;
            background: rgba(var(--primary-rgb), .1);
            color: rgb(var(--primary-rgb));
            font-size: 1.15rem;
        }
        .sadmin-govt-main .gov-module-card h3 {
            margin: 0;
            font-size: .88rem;
            font-weight: 700;
        }
        .sadmin-govt-main .gov-module-card p {
            margin: .15rem 0 0;
            color: var(--text-muted);
            font-size: .72rem;
        }
        .sadmin-govt-main .gov-module-card b {
            display: inline-flex;
            align-items: center;
            min-height: 1.55rem;
            padding: .18rem .55rem;
            border-radius: .35rem;
            background: rgba(var(--primary-rgb), .1);
            color: rgb(var(--primary-rgb));
            font-size: .67rem;
        }
        .sadmin-govt-main .footer {
            margin-inline: 0 !important;
            width: 100%;
        }
        @media (max-width: 1199.98px) {
            .sadmin-govt-main .govt-mini-stats,
            .sadmin-govt-main .gov-prep-grid-menu {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }
        @media (max-width: 767.98px) {
            .sadmin-govt-main .govt-mini-stats,
            .sadmin-govt-main .gov-prep-grid-menu {
                grid-template-columns: 1fr;
            }
        }
    </style>
</main>
<?php include __DIR__ . '/includes/footer.php'; ?>
