<?php
$isEditExam = is_array($modalExam);
$examFormId = $isEditExam ? 'edit-exam-' . (int) $modalExam['id'] : 'add-exam';
$examValue = static fn(string $key, string $default = ''): string => h((string) ($modalExam[$key] ?? $default));
$examSelected = static fn(string $key, string $value, string $default = ''): string => (($modalExam[$key] ?? $default) === $value) ? 'selected' : '';
$selectedCourseId = (int) ($modalExam['course_id'] ?? 0);
$selectedExamCategoryId = (int) ($modalExam['exam_category_id'] ?? 0);
$selectedContentId = (int) ($modalRule['content_id'] ?? 0);
$questionIdsText = implode(',', array_map('intval', $modalQuestionIds ?? []));
?>
<div id="<?= h($examFormId); ?>" class="modal-overlay">
    <form class="modal-box wide-modal course-modal ins-modal" method="post">
        <input type="hidden" name="csrf_token" value="<?= h(csrf_token()); ?>">
        <?php if ($isEditExam): ?><input type="hidden" name="exam_id" value="<?= (int) $modalExam['id']; ?>"><?php endif; ?>
        <div class="modal-head"><h2><?= $isEditExam ? 'Edit Exam' : 'Add Exam'; ?></h2><a class="modal-close" href="#" aria-label="Close">x</a></div>
        <div class="form-grid compact-form">
            <label>Exam Title<input name="title" value="<?= $examValue('title'); ?>" placeholder="CCC Practice Test" required></label>
            <label>Exam Category<select name="exam_category_id">
                <option value="">Select category</option>
                <?php foreach (($examCategories ?? []) as $category): ?>
                    <option value="<?= (int) $category['id']; ?>" <?= $selectedExamCategoryId === (int) $category['id'] ? 'selected' : ''; ?>><?= h($category['category_name']); ?></option>
                <?php endforeach; ?>
            </select></label>
            <label>New Category<input name="new_exam_category" value="" placeholder="e.g. CCC, O Level, SSC"></label>
            <label>Question Source Course<select name="course_id" data-exam-course>
                <option value="">No course source</option>
                <?php foreach ($courses as $course): ?>
                    <option value="<?= (int) $course['id']; ?>" <?= $selectedCourseId === (int) $course['id'] ? 'selected' : ''; ?>><?= h($course['title']); ?></option>
                <?php endforeach; ?>
            </select></label>
            <label>Exam Type<select name="exam_type">
                <option value="manual" <?= $examSelected('exam_type', 'manual', 'manual'); ?>>Manual</option>
                <option value="random" <?= $examSelected('exam_type', 'random'); ?>>Random</option>
            </select></label>
            <label>Duration Minutes<input type="number" name="duration_minutes" min="1" max="600" value="<?= $examValue('duration_minutes', '60'); ?>"></label>
            <label>Question Limit<input type="number" name="question_limit" min="1" max="500" value="<?= h((string) max(1, (int) ($modalExam['total_questions'] ?? ($modalRule['question_limit'] ?? 20)))); ?>"></label>
            <label>Status<select name="status">
                <option value="draft" <?= $examSelected('status', 'draft', 'draft'); ?>>Draft</option>
                <option value="published" <?= $examSelected('status', 'published'); ?>>Published</option>
                <option value="paused" <?= $examSelected('status', 'paused'); ?>>Paused</option>
            </select></label>
            <label>Random Chapter<select name="content_id" data-exam-content>
                <option value="">Any chapter</option>
                <?php foreach ($contents as $content): ?>
                    <option value="<?= (int) $content['id']; ?>" data-course="<?= (int) $content['course_id']; ?>" <?= $selectedContentId === (int) $content['id'] ? 'selected' : ''; ?>><?= h($content['course_title'] . ' - ' . $content['content_title']); ?></option>
                <?php endforeach; ?>
            </select></label>
            <label class="switch-field">Live Exam<span><input type="checkbox" name="is_live" value="1" <?= (int) ($modalExam['is_live'] ?? 0) === 1 ? 'checked' : ''; ?>><b></b></span></label>
            <label class="switch-field">Random Active Only<span><input type="checkbox" name="only_active" value="1" <?= (int) ($modalRule['only_active'] ?? 1) === 1 ? 'checked' : ''; ?>><b></b></span></label>
            <label class="span-2">Manual Question IDs<textarea name="question_ids" rows="3" placeholder="Blank rakhenge to selected course ke first active questions auto assign honge."><?= h($questionIdsText); ?></textarea></label>
            <label class="span-2">Description<textarea name="description" rows="3"><?= $examValue('description'); ?></textarea></label>
        </div>
        <div class="modal-actions"><button type="submit"><?= $isEditExam ? 'Update Exam' : 'Save Exam'; ?></button></div>
    </form>
</div>
