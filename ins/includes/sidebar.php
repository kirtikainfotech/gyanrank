<?php
$activePage = $activePage ?? '';
$logoPath = is_file(__DIR__ . '/../../assets/grlogo-admin.png')
    ? app_url('assets/grlogo-admin.png?v=' . (string) filemtime(__DIR__ . '/../../assets/grlogo-admin.png'))
    : (asset_or_default('logo_path') ?: app_url('assets/grlogo.png'));
$classroomMenu = [
    ['key' => 'batches', 'label' => 'Batches', 'url' => app_url('ins/batches')],
    ['key' => 'classes', 'label' => 'Classes', 'url' => app_url('ins/classes')],
    ['key' => 'live', 'label' => 'Live Studio', 'url' => app_url('ins/live')],
    ['key' => 'attendance', 'label' => 'Attendance', 'url' => app_url('ins/attendance')],
];
$contentMenu = [
    ['key' => 'courses', 'label' => 'Courses', 'url' => app_url('ins/courses')],
    ['key' => 'chapters', 'label' => 'Chapters', 'url' => app_url('ins/chapters')],
    ['key' => 'documents', 'label' => 'Documents', 'url' => app_url('ins/documents')],
    ['key' => 'combos', 'label' => 'Combos', 'url' => app_url('ins/combos')],
];
$examMenu = [
    ['key' => 'exam-management', 'label' => 'Exam Management', 'url' => app_url('ins/exam-management')],
    ['key' => 'questions', 'label' => 'Question Bank', 'url' => app_url('ins/questions')],
    ['key' => 'exams', 'label' => 'Exams', 'url' => app_url('ins/exams')],
];
$studentMenu = [
    ['key' => 'students', 'label' => 'Students', 'url' => app_url('ins/students')],
    ['key' => 'reports', 'label' => 'Reports', 'url' => app_url('ins/reports')],
];
$menu = [
    ['key' => 'dashboard', 'label' => 'Dashboard', 'icon' => 'bx bx-grid-alt', 'url' => app_url('ins/dashboard')],
    ['key' => 'classroom', 'label' => 'Classroom', 'icon' => 'bx bx-calendar-event', 'url' => app_url('ins/classes'), 'children' => $classroomMenu],
    ['key' => 'content', 'label' => 'Content', 'icon' => 'bx bx-book-open', 'url' => app_url('ins/courses'), 'children' => $contentMenu],
    ['key' => 'assessments', 'label' => 'Assessments', 'icon' => 'bx bx-task', 'url' => app_url('ins/exam-management'), 'children' => $examMenu],
    ['key' => 'learners', 'label' => 'Learners', 'icon' => 'bx bx-user', 'url' => app_url('ins/students'), 'children' => $studentMenu],
    ['key' => 'settings', 'label' => 'Settings', 'icon' => 'bx bx-cog', 'url' => app_url('ins/settings')],
];
?>
<aside class="app-sidebar sticky" id="sidebar">
    <div class="main-sidebar-header">
        <a href="<?= h(app_url('ins/dashboard')); ?>" class="header-logo gr-panel-logo">
            <img src="<?= h($logoPath); ?>" alt="<?= h(app_name()); ?>">
        </a>
    </div>
    <div class="main-sidebar" id="sidebar-scroll">
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
                                    <li class="slide">
                                        <a class="side-menu__item <?= $activePage === $subItem['key'] ? 'active' : ''; ?>" href="<?= h($subItem['url']); ?>">
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
