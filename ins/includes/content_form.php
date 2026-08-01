<?php
$contentValue = static fn(string $key, string $default = ''): string => h((string) ($modalContent[$key] ?? $default));
$contentSelected = static fn(string $key, string $value, string $default = ''): string => (($modalContent[$key] ?? $default) === $value) ? 'selected' : '';
$contentChecked = static fn(string $key): string => !empty($modalContent[$key]) ? 'checked' : '';
?>
<div class="course-form-sections">
    <div class="easy-help-box">
        <strong>Easy Builder:</strong>
        <span>Course choose karein, new chapter banayein ya old chapter reuse karein. Type change karte hi related options auto-show honge.</span>
    </div>
    <section class="course-form-section">
        <h3><span>#</span>Chapter Details</h3>
        <p class="selected-course-note" data-selected-course>Select a course for this chapter.</p>
        <div class="form-grid compact-form">
            <?php if (empty($modalContent)): ?>
                <label class="span-2">Reuse Old Chapter<select name="existing_content_id" data-existing-content>
                    <option value="">Create New Chapter</option>
                    <?php foreach (($contents ?? []) as $oldItem): ?>
                        <option
                            value="<?= (int) $oldItem['id']; ?>"
                            data-title="<?= h($oldItem['content_title']); ?>"
                            data-type="<?= h($oldItem['content_type']); ?>"
                            data-url="<?= h($oldItem['resource_url']); ?>"
                            data-duration="<?= h((string) $oldItem['duration_minutes']); ?>"
                            data-order="<?= h((string) $oldItem['sort_order']); ?>"
                            data-preview="<?= (int) $oldItem['is_preview']; ?>"
                            data-status="<?= h($oldItem['status']); ?>"
                            data-instructions="<?= h($oldItem['instructions'] ?? ''); ?>"
                        ><?= h($oldItem['content_title'] . ' - ' . $oldItem['course_title']); ?></option>
                    <?php endforeach; ?>
                </select></label>
            <?php endif; ?>
            <label>Course<select name="course_id" required>
                <option value="">Select Course</option>
                <?php foreach ($courses as $courseOption): ?>
                    <option value="<?= (int) $courseOption['id']; ?>" <?= (int) ($modalContent['course_id'] ?? 0) === (int) $courseOption['id'] ? 'selected' : ''; ?>><?= h($courseOption['title']); ?></option>
                <?php endforeach; ?>
            </select></label>
            <label>Chapter Title<input name="content_title" value="<?= $contentValue('content_title'); ?>" placeholder="Unique title in selected course" required></label>
            <label>Type<select name="content_type">
                <option value="lecture" <?= $contentSelected('content_type', 'lecture', 'lecture'); ?>>Lecture</option>
                <option value="pdf" <?= $contentSelected('content_type', 'pdf'); ?>>Chapter PDF</option>
                <option value="video_upload" <?= $contentSelected('content_type', 'video_upload'); ?>>Video Upload</option>
                <option value="live" <?= $contentSelected('content_type', 'live'); ?>>Live Class</option>
                <option value="youtube" <?= $contentSelected('content_type', 'youtube'); ?>>YouTube</option>
                <option value="vimeo" <?= $contentSelected('content_type', 'vimeo'); ?>>Vimeo</option>
                <option value="resource" <?= $contentSelected('content_type', 'resource'); ?>>Document</option>
            </select></label>
            <label>Status<select name="content_status"><option value="draft" <?= $contentSelected('status', 'draft', 'draft'); ?>>Draft</option><option value="published" <?= $contentSelected('status', 'published'); ?>>Published</option></select></label>
            <label data-content-field="duration">Duration <input type="number" min="0" name="duration_minutes" value="<?= $contentValue('duration_minutes', '0'); ?>" placeholder="Minutes"></label>
            <label>Order<input type="number" min="1" name="sort_order" value="<?= $contentValue('sort_order', '1'); ?>"></label>
            <label class="check-line">Free Preview<input type="checkbox" name="is_preview" value="1" <?= $contentChecked('is_preview'); ?>></label>
        </div>
    </section>

    <section class="course-form-section">
        <h3><span>@</span>Link / Upload</h3>
        <div class="form-grid compact-form">
            <label class="span-2" data-content-field="link">Paste Link<input name="resource_url" value="<?= $contentValue('resource_url'); ?>" placeholder="YouTube, Vimeo, Google Meet or leave blank for live default"></label>
            <label class="span-2" data-content-field="upload">Upload PDF / File<input type="file" name="resource_file" accept=".pdf,.doc,.docx,.ppt,.pptx,.xls,.xlsx,.mp4,.webm,.mov,.zip,.jpg,.jpeg,.png"></label>
            <label class="span-2" data-content-field="instructions">Notes / Description<textarea name="instructions" rows="2" placeholder="Lecture notes, document description or student instructions..."><?= $contentValue('instructions'); ?></textarea></label>
        </div>
    </section>
</div>
