<?php
$questionValue = static fn(string $key, string $default = ''): string => h((string) ($modalQuestion[$key] ?? $default));
$questionSelected = static fn(string $key, string $value, string $default = ''): string => (($modalQuestion[$key] ?? $default) === $value) ? 'selected' : '';
?>
<div class="course-form-sections">
    <section class="course-form-section">
        <h3><span>#</span>Question Details</h3>
        <div class="form-grid compact-form">
            <label>Course<select name="course_id" data-question-course required>
                <option value="">Select Course</option>
                <?php foreach ($courses as $course): ?>
                    <option value="<?= (int) $course['id']; ?>" <?= (int) ($modalQuestion['course_id'] ?? 0) === (int) $course['id'] ? 'selected' : ''; ?>><?= h($course['title']); ?></option>
                <?php endforeach; ?>
            </select></label>
            <label>Chapter<select name="content_id" data-question-content>
                <option value="">No Chapter</option>
                <?php foreach ($contents as $content): ?>
                    <option value="<?= (int) $content['id']; ?>" data-course="<?= (int) $content['course_id']; ?>" <?= (int) ($modalQuestion['content_id'] ?? 0) === (int) $content['id'] ? 'selected' : ''; ?>><?= h($content['content_title']); ?></option>
                <?php endforeach; ?>
            </select></label>
            <label>Type<select name="q_type">
                <option value="MCQ" <?= $questionSelected('q_type', 'MCQ', 'MCQ'); ?>>MCQ</option>
                <option value="TF" <?= $questionSelected('q_type', 'TF'); ?>>True / False</option>
            </select></label>
            <label>Correct Answer<select name="correct_key">
                <?php foreach (['A', 'B', 'C', 'D', 'TRUE', 'FALSE'] as $answer): ?>
                    <option value="<?= h($answer); ?>" <?= $questionSelected('correct_key', $answer, 'A'); ?>><?= h($answer); ?></option>
                <?php endforeach; ?>
            </select></label>
            <label>Marks<input type="number" step="0.01" min="0" name="marks" value="<?= $questionValue('marks', '1'); ?>"></label>
            <label>Status<select name="status">
                <option value="active" <?= $questionSelected('status', 'active', 'active'); ?>>Active</option>
                <option value="inactive" <?= $questionSelected('status', 'inactive'); ?>>Inactive</option>
            </select></label>
            <label class="span-2">Question English<textarea name="question_en" rows="3" required><?= $questionValue('question_en'); ?></textarea></label>
            <label class="span-2">Question Hindi<textarea name="question_hi" rows="3"><?= $questionValue('question_hi'); ?></textarea></label>
        </div>
    </section>

    <section class="course-form-section">
        <h3><span>A</span>Options</h3>
        <div class="form-grid compact-form">
            <label>Option A English<textarea name="option_a_en" rows="2"><?= $questionValue('option_a_en'); ?></textarea></label>
            <label>Option A Hindi<textarea name="option_a_hi" rows="2"><?= $questionValue('option_a_hi'); ?></textarea></label>
            <label>Option B English<textarea name="option_b_en" rows="2"><?= $questionValue('option_b_en'); ?></textarea></label>
            <label>Option B Hindi<textarea name="option_b_hi" rows="2"><?= $questionValue('option_b_hi'); ?></textarea></label>
            <label>Option C English<textarea name="option_c_en" rows="2"><?= $questionValue('option_c_en'); ?></textarea></label>
            <label>Option C Hindi<textarea name="option_c_hi" rows="2"><?= $questionValue('option_c_hi'); ?></textarea></label>
            <label>Option D English<textarea name="option_d_en" rows="2"><?= $questionValue('option_d_en'); ?></textarea></label>
            <label>Option D Hindi<textarea name="option_d_hi" rows="2"><?= $questionValue('option_d_hi'); ?></textarea></label>
        </div>
    </section>

    <section class="course-form-section">
        <h3><span>!</span>Solution</h3>
        <div class="form-grid compact-form">
            <label class="span-2">Solution / Explanation<textarea name="solution" rows="3" placeholder="Answer explanation or solving steps..."><?= $questionValue('solution'); ?></textarea></label>
        </div>
    </section>
</div>
