<?php
$resourceValue = static fn(string $key, string $default = ''): string => h((string) ($modalResource[$key] ?? $default));
$resourceSelected = static fn(string $key, string $value, string $default = ''): string => (($modalResource[$key] ?? $default) === $value) ? 'selected' : '';
$selectedExamCategoryId = (int) ($modalResource['exam_category_id'] ?? 0);
foreach (($exams ?? []) as $examOptionForCategory) {
    if ($selectedExamCategoryId <= 0 && (int) ($modalResource['exam_id'] ?? 0) === (int) ($examOptionForCategory['id'] ?? 0)) {
        $selectedExamCategoryId = (int) ($examOptionForCategory['exam_category_id'] ?? 0);
        break;
    }
}
?>
<div class="course-form-sections">
    <div class="easy-help-box">
        <strong>Document Type:</strong>
        <span>Course document course page par dikhega. Exam preparation document exam/mock-test preparation flow me dikhega.</span>
    </div>
    <section class="course-form-section">
        <h3><span>P</span>PDF Details</h3>
        <div class="form-grid compact-form">
            <label class="span-2">Document For<select name="document_purpose" data-document-purpose>
                <option value="course" <?= $resourceSelected('document_purpose', 'course', 'course'); ?>>Course</option>
                <option value="exam" <?= $resourceSelected('document_purpose', 'exam'); ?>>Exam preparation</option>
            </select></label>
            <label data-course-doc-field>Course<select name="resource_course_id" required>
                <option value="">Select Course</option>
                <?php foreach ($courses as $courseOption): ?>
                    <option value="<?= (int) $courseOption['id']; ?>" <?= (int) ($modalResource['course_id'] ?? 0) === (int) $courseOption['id'] ? 'selected' : ''; ?>><?= h($courseOption['title']); ?></option>
                <?php endforeach; ?>
            </select></label>
            <label data-course-doc-field>Chapter (Optional)
                <select name="resource_chapter_id" data-document-chapters>
                    <option value="">No Chapter (Course Document)</option>
                    <?php foreach ($chaptersByCourse as $courseOptionId => $courseChapters): ?>
                        <?php $groupTitle = $courseTitleById[(int) $courseOptionId] ?? ('Course ' . $courseOptionId); ?>
                        <optgroup
                            label="<?= h((string) $groupTitle); ?>"
                            data-course-id="<?= (int) $courseOptionId; ?>"
                        >
                            <?php foreach ($courseChapters as $chapterOption): ?>
                                <option
                                    value="<?= (int) $chapterOption['id']; ?>"
                                    data-course-id="<?= (int) $courseOptionId; ?>"
                                    <?= (int) ($modalResource['chapter_id'] ?? 0) === (int) $chapterOption['id'] ? 'selected' : ''; ?>
                                >
                                    <?= h($chapterOption['content_title']); ?>
                                </option>
                            <?php endforeach; ?>
                        </optgroup>
                    <?php endforeach; ?>
                </select>
            </label>
            <label class="span-2" data-exam-doc-field>Exam Category<select name="resource_exam_category_id" data-exam-doc-category>
                <option value="">Select exam category</option>
                <?php foreach (($examCategories ?? []) as $categoryOption): ?>
                    <option value="<?= (int) $categoryOption['id']; ?>" <?= $selectedExamCategoryId === (int) $categoryOption['id'] ? 'selected' : ''; ?>><?= h($categoryOption['category_name']); ?></option>
                <?php endforeach; ?>
            </select></label>
            <label class="span-2" data-exam-doc-field>Exam / Mock Test<select name="resource_exam_id" data-exam-doc-exam>
                <option value="">General exam preparation PDF</option>
                <?php foreach (($exams ?? []) as $examOption): ?>
                    <option value="<?= (int) $examOption['id']; ?>" data-category-id="<?= (int) ($examOption['exam_category_id'] ?? 0); ?>" data-course-id="<?= (int) ($examOption['course_id'] ?? 0); ?>" <?= (int) ($modalResource['exam_id'] ?? 0) === (int) $examOption['id'] ? 'selected' : ''; ?>><?= h(($examOption['exam_category_name'] ? $examOption['exam_category_name'] . ' - ' : '') . $examOption['title']); ?></option>
                <?php endforeach; ?>
            </select></label>
            <label>PDF Title<input name="resource_title" value="<?= $resourceValue('resource_title'); ?>" placeholder="Course syllabus / notes / brochure" required></label>
            <label>PDF Price (Rs)<input type="number" min="0" step="0.01" name="resource_price" value="<?= $resourceValue('price', '10.00'); ?>" placeholder="10.00"></label>
            <label>Order<input type="number" min="1" name="resource_sort_order" value="<?= $resourceValue('sort_order', '1'); ?>"></label>
            <label>Status<select name="resource_status">
                <option value="published" <?= $resourceSelected('status', 'published', 'published'); ?>>Published</option>
                <option value="draft" <?= $resourceSelected('status', 'draft'); ?>>Draft</option>
            </select></label>
            <label class="span-2">Upload PDF<input type="file" name="resource_pdf" accept=".pdf" <?= empty($modalResource) ? 'required' : ''; ?>></label>
            <label class="span-2">Upload Thumbnail<input type="file" name="resource_thumbnail" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp"></label>
            <?php if (!empty($modalResource['thumbnail_path'])): ?>
                <label class="span-2">Current Thumbnail
                    <span style="display:flex;align-items:center;gap:12px;border:1px solid #c7d6f3;border-radius:8px;padding:8px;background:#fff">
                        <img src="<?= h(app_url((string) $modalResource['thumbnail_path'])); ?>" alt="" style="width:116px;height:72px;object-fit:contain;border-radius:8px;border:1px solid #dbe5f4;background:#fff">
                        <input value="<?= h($modalResource['thumbnail_path']); ?>" readonly style="margin:0">
                    </span>
                </label>
            <?php endif; ?>
            <?php if (!empty($modalResource['file_path'])): ?>
                <label class="span-2">Current File<input value="<?= h($modalResource['file_path']); ?>" readonly></label>
            <?php endif; ?>
        </div>
    </section>
</div>
