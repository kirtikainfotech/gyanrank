<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/important_pages.php';

$slug = (string) ($pageSlug ?? '');
$catalog = important_page_catalog();

if (!isset($catalog[$slug])) {
    http_response_code(404);
    require __DIR__ . '/404.php';
    exit;
}

$title = important_page_title($slug);
$body = important_page_body($slug);
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= h($title); ?> - <?= h(app_name()); ?></title>
    <?php if (asset_or_default('favicon_path') !== ''): ?><link rel="icon" href="<?= h(asset_or_default('favicon_path')); ?>"><?php endif; ?>
    <link rel="stylesheet" href="<?= app_url('assets/css/login.css'); ?>">
</head>
<body class="legal-page">
    <main class="legal-shell">
        <nav class="legal-nav">
            <a href="<?= app_url('index'); ?>" class="soon-brand"><span class="logo-mark logo-image"><img src="<?= h(app_url('assets/grlogo.png')); ?>" alt="<?= h(app_name()); ?>"></span></a>
            <a href="<?= app_url('instructor-signup'); ?>">Instructor Signup</a>
        </nav>
        <article class="legal-card">
            <span>GYAN RANK</span>
            <h1><?= h($title); ?></h1>
            <div class="legal-content"><?= $body; ?></div>
        </article>
    </main>
    <?php require __DIR__ . '/includes/public_footer.php'; ?>
</body>
</html>
