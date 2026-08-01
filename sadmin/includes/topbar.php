<?php
$pageTitle = $pageTitle ?? 'Dashboard';
$pageSubtitle = $pageSubtitle ?? '';
$userName = (string) ($user['full_name'] ?? 'Super Admin');
$userRole = strtoupper(str_replace('-', ' ', (string) ($user['role'] ?? 'superadmin')));
$avatarUrl = (string) ($user['avatar_url'] ?? '');
$initials = strtoupper(substr(trim($userName), 0, 1) ?: 'S');
?>
<header class="app-header gr-template-header">
    <div class="main-header-container container-fluid">
        <div class="header-content-left">
            <div class="header-element">
                <a aria-label="Hide Sidebar" class="sidemenu-toggle header-link animated-arrow hor-toggle horizontal-navtoggle" data-bs-toggle="sidebar" href="javascript:void(0);">
                    <span></span>
                </a>
            </div>
            <div class="header-element gr-topbar-title">
                <a href="<?= h(app_url('sadmin/dashboard')); ?>">GyanRank</a>
            </div>
        </div>

        <div class="header-content-right">
            <form class="gr-topbar-search d-none d-md-flex" action="<?= h(app_url('sadmin/dashboard')); ?>" method="get" role="search">
                <input type="search" name="q" placeholder="Search By Student Nam" aria-label="Search">
                <button type="submit" aria-label="Search"><i class="bx bx-search"></i></button>
            </form>
            <div class="header-element d-none d-lg-flex">
                <a href="javascript:void(0);" class="header-link gr-india-flag" title="India"><span></span></a>
            </div>
            <div class="header-element d-none d-lg-flex"><a href="<?= h(app_url('sadmin/dashboard')); ?>" class="header-link" title="Calendar"><i class="bx bx-calendar header-link-icon"></i></a></div>
            <div class="header-element d-none d-lg-flex"><a href="<?= h(app_url('sadmin/institute-manage')); ?>" class="header-link" title="Tasks"><i class="bx bx-check-square header-link-icon"></i></a></div>
            <div class="header-element d-none d-lg-flex"><a href="<?= h(app_url('sadmin/settings')); ?>" class="header-link" title="WhatsApp"><i class="bx bxl-whatsapp header-link-icon"></i></a></div>
            <div class="header-element dropdown">
                <a href="javascript:void(0);" class="header-link dropdown-toggle gr-user-trigger" data-bs-toggle="dropdown" data-bs-auto-close="outside" aria-expanded="false">
                    <span class="avatar avatar-md avatar-rounded bg-light text-muted fw-semibold">
                        <?php if ($avatarUrl !== ''): ?>
                            <img src="<?= h($avatarUrl); ?>" alt="<?= h($userName); ?>">
                        <?php else: ?>
                            <?= h($initials); ?>
                        <?php endif; ?>
                    </span>
                    <span class="d-none d-xl-block ms-2 text-start">
                        <span class="d-block fw-semibold lh-1"><?= h($userName); ?></span>
                        <small class="text-muted"><?= h($userRole); ?></small>
                    </span>
                </a>
                <ul class="dropdown-menu dropdown-menu-end gr-profile-menu">
                    <li><a class="dropdown-item" href="<?= h(app_url('sadmin/settings')); ?>"><i class="bx bx-cog me-2"></i>Settings</a></li>
                    <li><a class="dropdown-item" href="<?= h(app_url('sadmin/instructors')); ?>"><i class="bx bx-user me-2"></i>Users</a></li>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item text-danger" href="<?= h(app_url('logout')); ?>"><i class="bx bx-log-out me-2"></i>Logout</a></li>
                </ul>
            </div>
            <div class="header-element d-none d-lg-flex"><a href="<?= h(app_url('sadmin/settings')); ?>" class="header-link" title="Settings"><i class="bx bx-cog header-link-icon"></i></a></div>
        </div>
    </div>
</header>
<div class="container-fluid gr-panel-container">
    <div class="d-md-flex d-block align-items-center justify-content-between my-4 page-header-breadcrumb">
        <div>
            <h1 class="page-title fw-semibold fs-18 mb-0"><?= h($pageTitle); ?></h1>
            <?php if ($pageSubtitle !== ''): ?><p class="text-muted mb-0 mt-1 fs-13"><?= h($pageSubtitle); ?></p><?php endif; ?>
        </div>
        <div class="ms-md-1 ms-0">
            <nav>
                <ol class="breadcrumb mb-0">
                    <li class="breadcrumb-item"><a href="<?= h(app_url('sadmin/dashboard')); ?>">Super Admin</a></li>
                    <li class="breadcrumb-item active" aria-current="page"><?= h($pageTitle); ?></li>
                </ol>
            </nav>
        </div>
    </div>
