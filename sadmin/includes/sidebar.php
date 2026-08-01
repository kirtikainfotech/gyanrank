<?php
$activePage = $activePage ?? '';
$logoPath = is_file(__DIR__ . '/../../assets/grlogo-admin.png')
    ? app_url('assets/grlogo-admin.png?v=' . (string) filemtime(__DIR__ . '/../../assets/grlogo-admin.png'))
    : (asset_or_default('logo_path') ?: app_url('assets/grlogo.png'));
$currentPath = trim(parse_url((string) ($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH) ?? '', '/');

$settingsMenu = [
    ['key' => 'settings', 'label' => 'Overview', 'icon' => 'bx bx-grid-alt', 'url' => app_url('sadmin/settings')],
    ['key' => 'settings-general', 'label' => 'General', 'icon' => 'bx bx-slider-alt', 'url' => app_url('sadmin/settings-general')],
    ['key' => 'settings-branding', 'label' => 'Branding', 'icon' => 'bx bx-palette', 'url' => app_url('sadmin/settings-branding')],
    ['key' => 'settings-mail', 'label' => 'Mail', 'icon' => 'bx bx-envelope', 'url' => app_url('sadmin/settings-mail')],
    ['key' => 'settings-billing', 'label' => 'Billing', 'icon' => 'bx bx-receipt', 'url' => app_url('sadmin/settings-billing')],
    ['key' => 'settings-templates', 'label' => 'Templates', 'icon' => 'bx bx-layout', 'url' => app_url('sadmin/settings-templates')],
    ['key' => 'settings-plans', 'label' => 'Plans', 'icon' => 'bx bx-purchase-tag', 'url' => app_url('sadmin/settings-plans')],
    ['key' => 'settings-gcoin', 'label' => 'Gcoin', 'icon' => 'bx bx-coin-stack', 'url' => app_url('sadmin/settings-gcoin')],
    ['key' => 'settings-pages', 'label' => 'Pages', 'icon' => 'bx bx-file', 'url' => app_url('sadmin/settings-pages')],
    ['key' => 'settings-staff', 'label' => 'Staff', 'icon' => 'bx bx-user-check', 'url' => app_url('sadmin/settings-staff')],
];
$learningMenu = [
    ['key' => 'courses', 'label' => 'Courses', 'icon' => 'bx bx-book-open', 'url' => app_url('sadmin/courses')],
    ['key' => 'categories', 'label' => 'Categories', 'icon' => 'bx bx-category', 'url' => app_url('sadmin/categories')],
    ['key' => 'govt-prep', 'label' => 'Govt Exam Prep', 'icon' => 'bx bx-buildings', 'url' => app_url('sadmin/govt-prep')],
    ['key' => 'govt-prep-categories', 'label' => 'Exam Categories', 'icon' => 'bx bx-list-ul', 'url' => app_url('sadmin/govt-prep-categories')],
    ['key' => 'govt-prep-documents', 'label' => 'Exam Documents', 'icon' => 'bx bx-file', 'url' => app_url('sadmin/govt-prep-documents')],
    ['key' => 'govt-prep-live', 'label' => 'Exam Live', 'icon' => 'bx bx-video', 'url' => app_url('sadmin/govt-prep-live')],
    ['key' => 'govt-prep-mocks', 'label' => 'Exam Mock Tests', 'icon' => 'bx bx-task', 'url' => app_url('sadmin/govt-prep-mocks')],
    ['key' => 'govt-prep-questions', 'label' => 'Mock Questions', 'icon' => 'bx bx-help-circle', 'url' => app_url('sadmin/govt-prep-questions')],
    ['key' => 'chapters', 'label' => 'Chapters', 'icon' => 'bx bx-list-check', 'url' => app_url('sadmin/chapters')],
    ['key' => 'batches', 'label' => 'Batches', 'icon' => 'bx bx-layer', 'url' => app_url('sadmin/batches')],
    ['key' => 'classes', 'label' => 'Live Classes', 'icon' => 'bx bx-calendar-event', 'url' => app_url('sadmin/classes')],
];
$instituteMenu = [
    ['key' => 'institute-manage', 'label' => 'Requests', 'icon' => 'bx bx-inbox', 'url' => app_url('sadmin/institute-manage')],
    ['key' => 'institute-erp-accounts', 'label' => 'ERP Accounts', 'icon' => 'bx bx-buildings', 'url' => app_url('sadmin/institute-erp-accounts')],
    ['key' => 'institute-erp-onboarding', 'label' => 'ERP Onboarding', 'icon' => 'bx bx-rocket', 'url' => app_url('sadmin/institute-erp-onboarding')],
    ['key' => 'institute-erp-domains', 'label' => 'ERP Domains', 'icon' => 'bx bx-globe', 'url' => app_url('sadmin/institute-erp-domains')],
    ['key' => 'institute-erp-support', 'label' => 'ERP Support', 'icon' => 'bx bx-support', 'url' => app_url('sadmin/institute-erp-support')],
    ['key' => 'institute-erp-plans', 'label' => 'ERP Plans', 'icon' => 'bx bx-purchase-tag', 'url' => app_url('sadmin/institute-erp-plans')],
    ['key' => 'institute-erp-backups', 'label' => 'ERP Backups', 'icon' => 'bx bx-data', 'url' => app_url('sadmin/institute-erp-backups')],
    ['key' => 'institute-states', 'label' => 'States', 'icon' => 'bx bx-map', 'url' => app_url('sadmin/institute-states')],
    ['key' => 'institute-districts', 'label' => 'Districts', 'icon' => 'bx bx-map-pin', 'url' => app_url('sadmin/institute-districts')],
    ['key' => 'institute-boards', 'label' => 'Boards', 'icon' => 'bx bx-detail', 'url' => app_url('sadmin/institute-boards')],
    ['key' => 'institute-universities', 'label' => 'Universities', 'icon' => 'bx bxs-graduation', 'url' => app_url('sadmin/institute-universities')],
];
$operationsMenu = [
    ['key' => 'staff', 'label' => 'Staff & Roles', 'icon' => 'bx bx-user-check', 'url' => app_url('sadmin/staff')],
    ['key' => 'staff-assignments', 'label' => 'Assignments', 'icon' => 'bx bx-git-branch', 'url' => app_url('sadmin/staff-assignments')],
    ['key' => 'leads', 'label' => 'Sales Leads', 'icon' => 'bx bx-trending-up', 'url' => app_url('sadmin/leads')],
    ['key' => 'institute-erp-support', 'label' => 'Support Queue', 'icon' => 'bx bx-support', 'url' => app_url('sadmin/institute-erp-support')],
    ['key' => 'institute-erp-domains', 'label' => 'Domain Queue', 'icon' => 'bx bx-globe', 'url' => app_url('sadmin/institute-erp-domains')],
];
$menu = [
    ['key' => 'dashboard', 'label' => 'Dashboard', 'icon' => 'bx bx-grid-alt', 'url' => app_url('sadmin/dashboard')],
    ['key' => 'instructors', 'label' => 'Instructor', 'icon' => 'bx bx-user', 'url' => app_url('sadmin/instructors')],
    ['key' => 'institute', 'label' => 'Institute Manage', 'icon' => 'bx bx-buildings', 'url' => app_url('sadmin/institute-manage'), 'children' => $instituteMenu],
    ['key' => 'operations', 'label' => 'Staff CRM', 'icon' => 'bx bx-briefcase-alt-2', 'url' => app_url('sadmin/staff'), 'children' => $operationsMenu],
    ['key' => 'learning', 'label' => 'Learning', 'icon' => 'bx bx-book', 'url' => app_url('sadmin/courses'), 'children' => $learningMenu],
    ['key' => 'reports', 'label' => 'Reports', 'icon' => 'bx bx-bar-chart-alt-2', 'url' => app_url('sadmin/dashboard')],
    ['key' => 'settings', 'label' => 'Settings', 'icon' => 'bx bx-cog', 'url' => app_url('sadmin/settings'), 'children' => $settingsMenu],
];
?>
<aside class="app-sidebar sticky" id="sidebar">
    <div class="main-sidebar-header">
        <a href="<?= h(app_url('sadmin/dashboard')); ?>" class="header-logo gr-panel-logo">
            <img src="<?= h($logoPath); ?>" alt="<?= h(app_name()); ?>">
        </a>
    </div>
    <div class="main-sidebar" id="sidebar-scroll">
        <ul class="gr-erp-session-menu">
            <li>
                <a href="javascript:void(0);">
                    <span>Current Session: 2021-22</span>
                </a>
            </li>
            <li>
                <a href="<?= h(app_url('sadmin/institute-manage')); ?>">
                    <span>Quick Links</span>
                    <i class="bx bx-grid-alt"></i>
                </a>
            </li>
        </ul>
        <nav class="main-menu-container nav nav-pills flex-column sub-open">
            <ul class="main-menu">
                <li class="slide__category"><span class="category-name">Main</span></li>
                <?php foreach ($menu as $item): ?>
                    <?php
                    $children = $item['children'] ?? [];
                    $hasActive = $activePage === $item['key'] || ($children && in_array($activePage, array_column($children, 'key'), true));
                    ?>
                    <?php if ($children): ?>
                        <li class="slide has-sub <?= $hasActive ? 'open active' : ''; ?>">
                            <a class="side-menu__item <?= $hasActive ? 'active' : ''; ?>" href="javascript:void(0);">
                                <i class="<?= h($item['icon']); ?> side-menu__icon"></i>
                                <span class="side-menu__label"><?= h($item['label']); ?></span>
                                <i class="fe fe-chevron-right side-menu__angle"></i>
                            </a>
                            <ul class="slide-menu child1" <?= $hasActive ? 'style="display:block;"' : ''; ?>>
                                <li class="slide side-menu__label1"><a href="javascript:void(0);"><?= h($item['label']); ?></a></li>
                                <?php foreach ($children as $subItem): ?>
                                    <?php
                                    $targetPath = 'sadmin/' . $subItem['key'];
                                    $isCurrent = $activePage === $subItem['key'] || substr($currentPath, -strlen($targetPath)) === $targetPath;
                                    ?>
                                    <li class="slide">
                                        <a class="side-menu__item <?= $isCurrent ? 'active' : ''; ?>" href="<?= h($subItem['url']); ?>">
                                            <span class="gr-submenu-arrow" aria-hidden="true">&raquo;</span>
                                            <span class="side-menu__label"><?= h($subItem['label']); ?></span>
                                        </a>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        </li>
                    <?php else: ?>
                        <li class="slide">
                            <a class="side-menu__item <?= $hasActive ? 'active' : ''; ?>" href="<?= h($item['url']); ?>">
                                <i class="<?= h($item['icon']); ?> side-menu__icon"></i>
                                <span class="side-menu__label"><?= h($item['label']); ?></span>
                            </a>
                        </li>
                    <?php endif; ?>
                <?php endforeach; ?>
            </ul>
        </nav>
    </div>
</aside>
