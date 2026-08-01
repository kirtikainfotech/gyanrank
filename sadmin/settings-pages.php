<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/important_pages.php';
require_once __DIR__ . '/includes/settings_helpers.php';

$user = require_login('superadmin');
$pageTitle = 'Important Pages';
$pageSubtitle = 'Manage public policy and legal pages with HTML editor.';
$activePage = 'settings';
$pages = important_page_catalog();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['csrf_token'] ?? null)) {
        $_SESSION['settings_error'] = 'Security token expired.';
        redirect('sadmin/settings-pages');
    }

    foreach ($pages as $slug => $page) {
        save_setting(important_page_setting_key($slug, 'title'), substr(trim((string) ($_POST[$slug . '_title'] ?? $page['title'])), 0, 160));
        save_setting(important_page_setting_key($slug, 'body'), substr(trim((string) ($_POST[$slug . '_body'] ?? important_page_default_body($slug))), 0, 20000));
    }
    $_SESSION['settings_message'] = 'Important pages updated.';
    redirect('sadmin/settings-pages');
}

$settings = all_settings();
[$message, $error] = settings_flash();
?>
<?php include __DIR__ . '/includes/header.php'; ?>
<?php include __DIR__ . '/includes/sidebar.php'; ?>
<main class="main">
    <?php include __DIR__ . '/includes/topbar.php'; ?>
    <section class="content settings-page">
        <?php render_settings_submenu('pages'); ?>
        <?php if ($message): ?><div class="notice"><?= h($message); ?></div><?php endif; ?>
        <?php if ($error): ?><div class="notice danger"><?= h($error); ?></div><?php endif; ?>

        <section class="settings-detail-card">
            <div class="detail-head">
                <div>
                    <span>Important Pages</span>
                    <h2>Public policy pages</h2>
                    <p>Manage page title, URL and HTML content used on website and registration terms.</p>
                </div>
            </div>
            <div class="email-template-table-wrap">
                <table class="email-template-table">
                    <thead><tr><th>Page</th><th>URL</th><th>Status</th><th>Edit</th></tr></thead>
                    <tbody>
                        <?php foreach ($pages as $slug => $page): ?>
                            <?php $body = trim(important_page_body($slug)); ?>
                            <tr>
                                <td><strong><?= h(important_page_title($slug)); ?></strong><small><?= h($slug); ?></small></td>
                                <td><code><?= h($page['url']); ?></code></td>
                                <td><span class="status-pill <?= $body === '' ? 'empty' : 'ready'; ?>"><?= $body === '' ? 'Not Set' : 'Set'; ?></span></td>
                                <td><a class="table-edit-icon" href="#page-<?= h($slug); ?>" aria-label="Edit <?= h($page['title']); ?>">Edit</a></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>
    </section>

    <?php foreach ($pages as $slug => $page): ?>
        <div id="page-<?= h($slug); ?>" class="modal-overlay">
            <form class="modal-box wide-modal template-editor-form" method="post">
                <input type="hidden" name="csrf_token" value="<?= h(csrf_token()); ?>">
                <?php foreach ($pages as $otherSlug => $otherPage): ?>
                    <?php if ($otherSlug !== $slug): ?>
                        <input type="hidden" name="<?= h($otherSlug); ?>_title" value="<?= h(important_page_title($otherSlug)); ?>">
                        <textarea hidden name="<?= h($otherSlug); ?>_body"><?= h(important_page_body($otherSlug)); ?></textarea>
                    <?php endif; ?>
                <?php endforeach; ?>
                <div class="modal-head"><h2>Edit <?= h($page['title']); ?></h2><a class="modal-close" href="#" aria-label="Close">×</a></div>
                <div class="form-grid">
                    <label class="span-2">Page Title<input name="<?= h($slug); ?>_title" value="<?= h(important_page_title($slug)); ?>"></label>
                </div>
                <div class="template-editor-layout">
                    <div class="template-editor-shell">
                        <div class="editor-toolbar">
                            <button type="button" data-command="bold">B</button>
                            <button type="button" data-command="italic">I</button>
                            <button type="button" data-command="insertUnorderedList">List</button>
                            <button type="button" data-command="createLink">Link</button>
                            <button type="button" data-command="formatBlock" data-value="h2">H2</button>
                            <button type="button" data-command="formatBlock" data-value="p">P</button>
                        </div>
                        <textarea id="body-<?= h($slug); ?>" name="<?= h($slug); ?>_body" hidden><?= h(important_page_body($slug)); ?></textarea>
                        <div class="html-editor" contenteditable="true" data-target="body-<?= h($slug); ?>" data-preview="preview-<?= h($slug); ?>"><?= important_page_body($slug); ?></div>
                    </div>
                    <div class="template-preview-panel">
                        <div class="preview-label">Page Preview</div>
                        <div class="email-design-preview"><div class="email-preview-body" id="preview-<?= h($slug); ?>"><?= important_page_body($slug); ?></div></div>
                    </div>
                </div>
                <div class="modal-actions"><button type="submit">Save Page</button></div>
            </form>
        </div>
    <?php endforeach; ?>
<script>
(() => {
    document.querySelectorAll('.template-editor-form').forEach((form) => {
        const sync = () => {
            form.querySelectorAll('.html-editor[data-target]').forEach((editor) => {
                const field = document.getElementById(editor.dataset.target);
                if (field) field.value = editor.innerHTML.trim();
                const preview = document.getElementById(editor.dataset.preview || '');
                if (preview) preview.innerHTML = editor.innerHTML.trim();
            });
        };
        form.querySelectorAll('.editor-toolbar button').forEach((button) => {
            button.addEventListener('click', () => {
                let value = button.dataset.value || null;
                if (button.dataset.command === 'createLink') {
                    value = window.prompt('Enter link URL', 'https://');
                    if (!value) return;
                }
                document.execCommand(button.dataset.command, false, value);
                sync();
            });
        });
        form.querySelectorAll('.html-editor').forEach((editor) => editor.addEventListener('input', sync));
        form.addEventListener('submit', sync);
    });
})();
</script>
<?php include __DIR__ . '/includes/footer.php'; ?>
