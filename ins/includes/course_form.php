<?php
$courseValue = static fn(string $key, string $default = ''): string => h((string) ($modalCourse[$key] ?? $default));
$courseSelected = static fn(string $key, string $value, string $default = ''): string => (($modalCourse[$key] ?? $default) === $value) ? 'selected' : '';
$courseChecked = static fn(string $key): string => !empty($modalCourse[$key]) ? 'checked' : '';
$freeChecked = !empty($modalCourse['is_free']) && (float) ($modalCourse['price'] ?? 0) <= 0 ? 'checked' : '';
?>
<div class="course-form-sections">
    <section class="course-form-section">
        <h3><span>#</span>Basic Details</h3>
        <div class="form-grid compact-form">
            <label>Title<input name="title" value="<?= $courseValue('title'); ?>" placeholder="Certificate in Artificial Intelligence" required></label>
            <label>Category<select name="category_id" data-category-select required>
                <option value="">Select Category</option>
                <?php foreach (($courseCategories ?? []) as $category): ?>
                    <option value="<?= (int) $category['id']; ?>" <?= (int) ($modalCourse['category_id'] ?? 0) === (int) $category['id'] ? 'selected' : ''; ?>><?= h($category['name']); ?></option>
                <?php endforeach; ?>
            </select></label>
            <label>Sub Category<select name="subcategory_id" data-subcategory-select>
                <option value="">Select Sub Category</option>
                <?php foreach (($courseSubcategories ?? []) as $subcategory): ?>
                    <option value="<?= (int) $subcategory['id']; ?>" data-parent="<?= (int) $subcategory['parent_id']; ?>" <?= (int) ($modalCourse['subcategory_id'] ?? 0) === (int) $subcategory['id'] ? 'selected' : ''; ?>><?= h($subcategory['name']); ?></option>
                <?php endforeach; ?>
            </select></label>
            <label>Course Thumbnail<input type="file" name="course_thumbnail" accept="image/jpeg,image/png,image/webp"><small>JPG, PNG, WEBP. Blank chhodne par title ke hisaab se automatic thumbnail banega.</small></label>
            <?php if (!empty($modalCourse['thumbnail_path'])): ?>
                <div class="course-form-thumb"><img src="<?= h(app_url($modalCourse['thumbnail_path'])); ?>" alt="<?= h($modalCourse['title'] ?? 'Course thumbnail'); ?>"></div>
            <?php endif; ?>
            <div class="course-pricing-row span-3">
                <label>Selling Amount<input type="number" step="0.01" min="0" name="price" value="<?= $courseValue('price', '0'); ?>"></label>
                <label>MRP / Cut Amount<input type="number" step="0.01" min="0" name="original_price" value="<?= $courseValue('original_price', '0'); ?>" placeholder="Original price"></label>
                <label>Price Unit<input name="price_unit" value="<?= $courseValue('price_unit', 'course'); ?>" placeholder="course / month / total"></label>
                <label class="check-line free-course-toggle">Free Course<input type="checkbox" name="is_free" value="1" <?= $freeChecked; ?>></label>
            </div>
            <label>Language<select name="course_language"><option value="hindi" <?= $courseSelected('course_language', 'hindi', 'hindi'); ?>>Hindi</option><option value="english" <?= $courseSelected('course_language', 'english'); ?>>English</option></select></label>
            <label class="check-line">Featured<input type="checkbox" name="featured" value="1" <?= $courseChecked('featured'); ?>></label>
            <label>Status<select name="status"><option value="draft" <?= $courseSelected('status', 'draft', 'draft'); ?>>Draft</option><option value="published" <?= $courseSelected('status', 'published'); ?>>Published</option><option value="paused" <?= $courseSelected('status', 'paused'); ?>>Paused</option></select></label>
        </div>
    </section>

    <section class="course-form-section">
        <h3><span>*</span>Course Specific Details</h3>
        <div class="form-grid compact-form">
            <label>Learning Mode<select name="learning_mode"><option value="online" <?= $courseSelected('learning_mode', 'online', 'online'); ?>>Online</option><option value="offline" <?= $courseSelected('learning_mode', 'offline'); ?>>Offline</option><option value="hybrid" <?= $courseSelected('learning_mode', 'hybrid'); ?>>Hybrid</option><option value="recorded" <?= $courseSelected('learning_mode', 'recorded'); ?>>Recorded</option></select></label>
            <label>Course Level<select name="course_level"><option value="beginner" <?= $courseSelected('course_level', 'beginner', 'beginner'); ?>>Beginner</option><option value="intermediate" <?= $courseSelected('course_level', 'intermediate'); ?>>Intermediate</option><option value="advanced" <?= $courseSelected('course_level', 'advanced'); ?>>Advanced</option><option value="all" <?= $courseSelected('course_level', 'all'); ?>>All Levels</option></select></label>
            <label>Duration<input name="duration" value="<?= $courseValue('duration', '3 months'); ?>"></label>
            <label class="span-2">Short Description<textarea name="short_description" rows="2" placeholder="Write course benefits and outcomes..."><?= $courseValue('short_description'); ?></textarea></label>
        </div>
    </section>
</div>
