<?php
$comboValue = static fn(string $key, string $default = ''): string => h((string) ($modalCombo[$key] ?? $default));
$comboSelected = static fn(string $key, int $value): string => (int) ($modalCombo[$key] ?? 0) === $value ? 'selected' : '';
$statusSelected = static fn(string $value, string $default = 'draft'): string => (($modalCombo['status'] ?? $default) === $value) ? 'selected' : '';
?>
<div class="form-grid combo-form-grid">
    <label class="span-2">Combo Name<input name="combo_name" value="<?= $comboValue('combo_name'); ?>" placeholder="Python Complete Combo" required></label>
    <label>Price<input type="number" name="price" min="0" step="0.01" value="<?= $comboValue('price', '0'); ?>"></label>
    <label>Status<select name="status">
        <option value="draft" <?= $statusSelected('draft'); ?>>Draft</option>
        <option value="published" <?= $statusSelected('published'); ?>>Published</option>
        <option value="paused" <?= $statusSelected('paused'); ?>>Paused</option>
    </select></label>
    <label>Course<select name="course_id">
        <option value="">No course</option>
        <?php foreach (($courses ?? []) as $courseOption): ?>
            <option value="<?= (int) $courseOption['id']; ?>" <?= $comboSelected('course_id', (int) $courseOption['id']); ?>><?= h($courseOption['title']); ?></option>
        <?php endforeach; ?>
    </select></label>
    <label>Document<select name="document_id">
        <option value="">No document</option>
        <?php foreach (($documents ?? []) as $documentOption): ?>
            <option value="<?= (int) $documentOption['id']; ?>" <?= $comboSelected('document_id', (int) $documentOption['id']); ?>><?= h($documentOption['resource_title']); ?></option>
        <?php endforeach; ?>
    </select></label>
    <label>Live Class<select name="live_channel_id">
        <option value="">No live access</option>
        <?php foreach (($liveChannels ?? []) as $liveOption): ?>
            <option value="<?= (int) $liveOption['id']; ?>" <?= $comboSelected('live_channel_id', (int) $liveOption['id']); ?>><?= h($liveOption['channel_name']); ?></option>
        <?php endforeach; ?>
    </select></label>
    <label>Mock Test<select name="exam_id">
        <option value="">No mock test</option>
        <?php foreach (($exams ?? []) as $examOption): ?>
            <option value="<?= (int) $examOption['id']; ?>" <?= $comboSelected('exam_id', (int) $examOption['id']); ?>><?= h($examOption['title']); ?></option>
        <?php endforeach; ?>
    </select></label>
    <label class="span-2">Description<textarea name="description" rows="3" placeholder="Is combo me kya milega..."><?= $comboValue('description'); ?></textarea></label>
</div>
