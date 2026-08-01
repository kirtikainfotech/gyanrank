<!doctype html>
<html lang="en" dir="ltr" data-nav-layout="vertical" data-theme-mode="light" data-header-styles="light" data-menu-styles="dark" data-toggled="close">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <title><?= h($pageTitle ?? 'Instructor'); ?> - <?= h(app_name()); ?></title>
    <?php if (asset_or_default('favicon_path') !== ''): ?>
        <link rel="icon" href="<?= h(asset_or_default('favicon_path')); ?>">
    <?php endif; ?>
    <script src="<?= h(app_url('theme/assets/js/main.js')); ?>"></script>
    <link id="style" href="<?= h(app_url('theme/assets/libs/bootstrap/css/bootstrap.min.css')); ?>" rel="stylesheet">
    <link href="<?= h(app_url('theme/assets/css/styles.min.css')); ?>" rel="stylesheet">
    <link href="<?= h(app_url('theme/assets/css/icons.css')); ?>" rel="stylesheet">
    <link href="<?= h(app_url('theme/assets/libs/node-waves/waves.min.css')); ?>" rel="stylesheet">
    <link href="<?= h(app_url('theme/assets/libs/simplebar/simplebar.min.css')); ?>" rel="stylesheet">
    <link href="<?= h(app_url('theme/assets/libs/sweetalert2/sweetalert2.min.css')); ?>" rel="stylesheet">
    <link href="<?= h(app_url('assets/css/panel-theme.css')) . '?v=' . (string) @filemtime(__DIR__ . '/../../assets/css/panel-theme.css'); ?>" rel="stylesheet">
    <link href="<?= h(app_url('assets/css/instructor-erp-panel.css')) . '?v=' . (string) @filemtime(__DIR__ . '/../../assets/css/instructor-erp-panel.css'); ?>" rel="stylesheet">
</head>
<body>
<div class="page gr-panel-shell gr-instructor-shell">
