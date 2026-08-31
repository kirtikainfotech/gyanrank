<?php
$publicNavItems = [
    ['label' => 'Courses', 'url' => app_url('index#courses')],
    ['label' => 'Live Classes', 'url' => app_url('index#live')],
    ['label' => 'Mock Tests', 'url' => app_url('index#tests')],
    ['label' => 'Categories', 'url' => app_url('index#categories')],
    ['label' => 'Instructors', 'url' => app_url('index#instructors')],
    ['label' => 'Institute Manage', 'url' => app_url('register-institution')],
];
?>
<header class="site-header">
    <div class="site-header-inner">
        <a href="<?= h(app_url('index')); ?>" class="site-header-brand" aria-label="<?= h(app_name()); ?>">
            <img src="<?= h(app_url('assets/grlogo.png')); ?>" alt="GYAN NEXA">
        </a>
        <nav class="site-header-nav" aria-label="Main navigation">
            <?php foreach ($publicNavItems as $item): ?>
                <a href="<?= h($item['url']); ?>"><?= h($item['label']); ?></a>
            <?php endforeach; ?>
        </nav>
        <a class="site-header-action" href="<?= h(app_url('login')); ?>">Login</a>
    </div>
</header>
