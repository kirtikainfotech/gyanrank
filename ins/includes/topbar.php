<?php
$pageTitle = $pageTitle ?? 'Instructor Dashboard';
$pageSubtitle = $pageSubtitle ?? '';
$userName = (string) ($user['full_name'] ?? 'Instructor');
$topbarSettings = function_exists('instructor_setting_row') ? instructor_setting_row((int) ($user['id'] ?? 0)) : [];
$avatarUrl = (string) ($topbarSettings['profile_logo_path'] ?? ($user['avatar_url'] ?? ''));
$initials = strtoupper(substr(trim($userName), 0, 1) ?: 'I');
$adminUser = $_SESSION['admin_user'] ?? null;
$isImpersonating = is_array($adminUser) && in_array((string) ($adminUser['role'] ?? ''), ['superadmin', 'super_admin'], true);
?>
<header class="app-header gr-template-header">
    <div class="main-header-container container-fluid">
        <div class="header-content-left">
            <div class="header-element">
                <a aria-label="Hide Sidebar" class="sidemenu-toggle header-link animated-arrow hor-toggle horizontal-navtoggle" data-bs-toggle="sidebar" href="javascript:void(0);">
                    <span></span>
                </a>
            </div>
            <div class="header-element d-none d-lg-block">
                <a href="javascript:void(0);" class="header-link"><i class="bx bx-search-alt-2 header-link-icon"></i></a>
            </div>
        </div>

        <div class="header-content-right">
            <?php if ($isImpersonating): ?>
                <div class="header-element">
                    <form method="post" action="<?= h(app_url('sadmin/back-to-admin')); ?>" class="m-0">
                        <input type="hidden" name="csrf_token" value="<?= h(csrf_token()); ?>">
                        <button class="btn btn-sm btn-primary-light btn-wave gr-back-admin-btn" type="submit">
                            <i class="bx bx-arrow-back me-1"></i>Back to Admin
                        </button>
                    </form>
                </div>
            <?php endif; ?>
            <div class="header-element d-none d-md-flex">
                <a href="<?= h(app_url('index')); ?>" target="_blank" class="header-link" title="Visit site"><i class="bx bx-globe header-link-icon"></i></a>
            </div>
            <div class="header-element d-none d-lg-flex"><a href="javascript:void(0);" class="header-link"><span class="fw-semibold">EN</span></a></div>
            <div class="header-element d-none d-lg-flex"><a href="javascript:void(0);" class="header-link"><i class="bx bx-moon header-link-icon"></i></a></div>
            <div class="header-element d-none d-lg-flex"><a href="javascript:void(0);" class="header-link"><i class="bx bx-bell header-link-icon"></i><span class="badge bg-secondary rounded-pill header-icon-badge">5</span></a></div>
            <div class="header-element d-none d-lg-flex"><a href="javascript:void(0);" class="header-link"><i class="bx bx-grid-alt header-link-icon"></i></a></div>
            <div class="header-element dropdown">
                <a href="javascript:void(0);" class="header-link dropdown-toggle" data-bs-toggle="dropdown" data-bs-auto-close="outside" aria-expanded="false">
                    <span class="avatar avatar-md avatar-rounded bg-light text-muted fw-semibold">
                        <?php if ($avatarUrl !== ''): ?>
                            <img src="<?= h(app_url($avatarUrl)); ?>" alt="<?= h($userName); ?>">
                        <?php else: ?>
                            <?= h($initials); ?>
                        <?php endif; ?>
                    </span>
                    <span class="d-none d-xl-block ms-2 text-start">
                        <span class="d-block fw-semibold lh-1"><?= h($userName); ?></span>
                        <small class="text-muted">Instructor</small>
                    </span>
                </a>
                <ul class="dropdown-menu dropdown-menu-end gr-profile-menu">
                    <li><a class="dropdown-item" href="<?= h(app_url('ins/settings')); ?>"><i class="bx bx-cog me-2"></i>Settings</a></li>
                    <li><a class="dropdown-item" href="<?= h(app_url('ins/live')); ?>"><i class="bx bx-video me-2"></i>Live Studio</a></li>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item text-danger" href="<?= h(app_url('logout')); ?>"><i class="bx bx-log-out me-2"></i>Logout</a></li>
                </ul>
            </div>
            <div class="header-element d-none d-lg-flex"><a href="<?= h(app_url('ins/settings')); ?>" class="header-link"><i class="bx bx-cog header-link-icon"></i></a></div>
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
                    <li class="breadcrumb-item"><a href="<?= h(app_url('ins/dashboard')); ?>">Instructor</a></li>
                    <li class="breadcrumb-item active" aria-current="page"><?= h($pageTitle); ?></li>
                </ol>
            </nav>
        </div>
    </div>
