<?php
$categoryValue = static fn(string $key, string $default = ''): string => h((string) ($modalCategory[$key] ?? $default));
$categorySelected = static fn(string $key, string $value, string $default = ''): string => ((string) ($modalCategory[$key] ?? $default) === $value) ? 'selected' : '';
$currentId = (int) ($modalCategory['id'] ?? 0);
?>
<div class="form-grid compact-form">
    <label>Parent<select name="parent_id">
        <option value="0">Main Category</option>
        <?php foreach (($parents ?? []) as $parent): ?>
            <?php if ((int) $parent['id'] === $currentId) continue; ?>
            <option value="<?= (int) $parent['id']; ?>" <?= (int) ($modalCategory['parent_id'] ?? 0) === (int) $parent['id'] ? 'selected' : ''; ?>><?= h($parent['name']); ?></option>
        <?php endforeach; ?>
    </select></label>
    <label>Name<input name="name" value="<?= $categoryValue('name'); ?>" placeholder="e.g. Computer Course" required></label>
    <label>Status<select name="status"><option value="active" <?= $categorySelected('status', 'active', 'active'); ?>>Active</option><option value="inactive" <?= $categorySelected('status', 'inactive'); ?>>Inactive</option></select></label>
    <label>Order<input type="number" min="1" name="sort_order" value="<?= $categoryValue('sort_order', '1'); ?>"></label>
    <label class="span-2">Description<textarea name="description" rows="2" placeholder="Short note for admin/instructor..."><?= $categoryValue('description'); ?></textarea></label>
</div>
