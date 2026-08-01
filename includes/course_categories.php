<?php

function ensure_course_category_tables(): void
{
    db()->query("
        CREATE TABLE IF NOT EXISTS course_categories (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            parent_id INT UNSIGNED NULL,
            name VARCHAR(120) NOT NULL,
            slug VARCHAR(140) NOT NULL,
            description VARCHAR(255) DEFAULT NULL,
            status ENUM('active','inactive') NOT NULL DEFAULT 'active',
            sort_order INT UNSIGNED NOT NULL DEFAULT 1,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY course_categories_slug_unique (slug),
            KEY course_categories_parent_index (parent_id),
            CONSTRAINT course_categories_parent_foreign FOREIGN KEY (parent_id) REFERENCES course_categories (id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    ensure_course_category_seed();
}

function course_category_slug(string $name): string
{
    $slug = strtolower(trim((string) preg_replace('/[^a-zA-Z0-9]+/', '-', $name), '-'));
    return $slug !== '' ? $slug : 'category-' . bin2hex(random_bytes(3));
}

function ensure_course_category_seed(): void
{
    $result = db()->query('SELECT COUNT(*) AS total FROM course_categories');
    if ((int) ($result->fetch_assoc()['total'] ?? 0) > 0) {
        return;
    }

    $parents = [
        'Computer Course' => ['Web Development', 'Python', 'Artificial Intelligence', 'Data Science'],
        'Digital Marketing' => ['SEO', 'Google Ads', 'Social Media Marketing', 'Performance Marketing'],
        'Language Course' => ['Spoken English', 'Personality Development', 'Interview Skills'],
        'Academic Course' => ['Mathematics', 'Physics', 'Chemistry', 'Biology'],
        'Competitive Exam' => ['Banking', 'SSC', 'Railway', 'Teaching Exam'],
        'Skill Development' => ['Graphic Design', 'Video Editing', 'Accounting', 'Office Tools'],
    ];

    $stmt = db()->prepare('INSERT IGNORE INTO course_categories (parent_id, name, slug, description, sort_order) VALUES (?, ?, ?, ?, ?)');
    $order = 1;
    foreach ($parents as $parent => $children) {
        $parentId = null;
        $slug = course_category_slug($parent);
        $description = $parent . ' master category';
        $stmt->bind_param('isssi', $parentId, $parent, $slug, $description, $order);
        $stmt->execute();
        $findParent = db()->prepare('SELECT id FROM course_categories WHERE slug = ? LIMIT 1');
        $findParent->bind_param('s', $slug);
        $findParent->execute();
        $parentId = (int) ($findParent->get_result()->fetch_assoc()['id'] ?? db()->insert_id);
        $childOrder = 1;
        foreach ($children as $child) {
            $childSlug = course_category_slug($parent . '-' . $child);
            $childDescription = $child . ' sub category';
            $stmt->bind_param('isssi', $parentId, $child, $childSlug, $childDescription, $childOrder);
            $stmt->execute();
            $childOrder++;
        }
        $order++;
    }
}

function course_categories_all(bool $activeOnly = false): array
{
    ensure_course_category_tables();
    $where = $activeOnly ? "WHERE status = 'active'" : '';
    $result = db()->query("
        SELECT c.*, p.name AS parent_name
        FROM course_categories c
        LEFT JOIN course_categories p ON p.id = c.parent_id
        $where
        ORDER BY COALESCE(p.sort_order, c.sort_order), COALESCE(p.name, c.name), c.parent_id IS NOT NULL, c.sort_order, c.name
    ");
    return $result->fetch_all(MYSQLI_ASSOC);
}

function course_parent_categories(bool $activeOnly = true): array
{
    ensure_course_category_tables();
    $where = $activeOnly ? "AND status = 'active'" : '';
    $result = db()->query("SELECT * FROM course_categories WHERE parent_id IS NULL $where ORDER BY sort_order, name");
    return $result->fetch_all(MYSQLI_ASSOC);
}

function course_sub_categories(bool $activeOnly = true): array
{
    ensure_course_category_tables();
    $where = $activeOnly ? "AND c.status = 'active' AND p.status = 'active'" : '';
    $result = db()->query("
        SELECT c.*, p.name AS parent_name
        FROM course_categories c
        INNER JOIN course_categories p ON p.id = c.parent_id
        WHERE c.parent_id IS NOT NULL $where
        ORDER BY p.sort_order, p.name, c.sort_order, c.name
    ");
    return $result->fetch_all(MYSQLI_ASSOC);
}

function valid_course_category_pair(int $categoryId, int $subcategoryId): bool
{
    if ($categoryId <= 0) {
        return false;
    }
    $stmt = db()->prepare("SELECT id FROM course_categories WHERE id = ? AND parent_id IS NULL AND status = 'active' LIMIT 1");
    $stmt->bind_param('i', $categoryId);
    $stmt->execute();
    if (!$stmt->get_result()->fetch_assoc()) {
        return false;
    }
    if ($subcategoryId <= 0) {
        return true;
    }
    $stmt = db()->prepare("SELECT id FROM course_categories WHERE id = ? AND parent_id = ? AND status = 'active' LIMIT 1");
    $stmt->bind_param('ii', $subcategoryId, $categoryId);
    $stmt->execute();
    return (bool) $stmt->get_result()->fetch_assoc();
}
