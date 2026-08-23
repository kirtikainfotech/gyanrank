<?php
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../includes/course_categories.php';

function instructor_user(): array
{
    return require_login('instructor');
}

function ensure_instructor_erp_tables(): void
{
    ensure_course_category_tables();

    db()->query("
        CREATE TABLE IF NOT EXISTS instructor_batches (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            instructor_id INT UNSIGNED NOT NULL,
            batch_name VARCHAR(120) NOT NULL,
            course_title VARCHAR(160) NOT NULL,
            teacher_name VARCHAR(120) DEFAULT NULL,
            mode ENUM('online','offline','hybrid') NOT NULL DEFAULT 'online',
            start_date DATE NULL,
            class_time VARCHAR(40) DEFAULT NULL,
            capacity INT UNSIGNED NOT NULL DEFAULT 30,
            status ENUM('active','paused','completed') NOT NULL DEFAULT 'active',
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY instructor_batches_user_index (instructor_id),
            CONSTRAINT instructor_batches_user_foreign FOREIGN KEY (instructor_id) REFERENCES users (id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    ensure_instructor_batch_column('teacher_name', 'ALTER TABLE instructor_batches ADD COLUMN teacher_name VARCHAR(120) DEFAULT NULL AFTER course_title');

    db()->query("
        CREATE TABLE IF NOT EXISTS instructor_classes (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            instructor_id INT UNSIGNED NOT NULL,
            batch_id INT UNSIGNED NULL,
            class_title VARCHAR(160) NOT NULL,
            class_type ENUM('online','offline') NOT NULL DEFAULT 'online',
            class_date DATE NOT NULL,
            starts_at TIME NULL,
            duration_minutes INT UNSIGNED NOT NULL DEFAULT 60,
            meeting_link VARCHAR(255) DEFAULT NULL,
            room_name VARCHAR(120) DEFAULT NULL,
            class_status ENUM('scheduled','live','completed','cancelled') NOT NULL DEFAULT 'scheduled',
            notes TEXT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY instructor_classes_user_index (instructor_id),
            KEY instructor_classes_batch_index (batch_id),
            CONSTRAINT instructor_classes_user_foreign FOREIGN KEY (instructor_id) REFERENCES users (id) ON DELETE CASCADE,
            CONSTRAINT instructor_classes_batch_foreign FOREIGN KEY (batch_id) REFERENCES instructor_batches (id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    db()->query("
        CREATE TABLE IF NOT EXISTS instructor_courses (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            instructor_id INT UNSIGNED NOT NULL,
            category_id INT UNSIGNED NULL,
            subcategory_id INT UNSIGNED NULL,
            title VARCHAR(180) NOT NULL,
            category VARCHAR(120) DEFAULT NULL,
            price DECIMAL(10,2) NOT NULL DEFAULT 0,
            original_price DECIMAL(10,2) NOT NULL DEFAULT 0,
            thumbnail_path VARCHAR(255) DEFAULT NULL,
            price_unit VARCHAR(40) NOT NULL DEFAULT 'course',
            learning_mode ENUM('online','offline','hybrid','recorded') NOT NULL DEFAULT 'online',
            course_level ENUM('beginner','intermediate','advanced','all') NOT NULL DEFAULT 'beginner',
            course_language ENUM('hindi','english') NOT NULL DEFAULT 'hindi',
            duration VARCHAR(80) DEFAULT NULL,
            city VARCHAR(80) DEFAULT NULL,
            locality VARCHAR(120) DEFAULT NULL,
            featured TINYINT(1) NOT NULL DEFAULT 0,
            is_free TINYINT(1) NOT NULL DEFAULT 0,
            status ENUM('draft','published','paused') NOT NULL DEFAULT 'draft',
            short_description TEXT NULL,
            address TEXT NULL,
            call_number VARCHAR(30) DEFAULT NULL,
            whatsapp_number VARCHAR(30) DEFAULT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY instructor_courses_user_index (instructor_id),
            KEY instructor_courses_category_index (category_id),
            KEY instructor_courses_subcategory_index (subcategory_id),
            CONSTRAINT instructor_courses_user_foreign FOREIGN KEY (instructor_id) REFERENCES users (id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    ensure_instructor_course_column('category_id', 'ALTER TABLE instructor_courses ADD COLUMN category_id INT UNSIGNED NULL AFTER instructor_id');
    ensure_instructor_course_column('subcategory_id', 'ALTER TABLE instructor_courses ADD COLUMN subcategory_id INT UNSIGNED NULL AFTER category_id');
    ensure_instructor_course_column('original_price', 'ALTER TABLE instructor_courses ADD COLUMN original_price DECIMAL(10,2) NOT NULL DEFAULT 0 AFTER price');
    ensure_instructor_course_column('thumbnail_path', 'ALTER TABLE instructor_courses ADD COLUMN thumbnail_path VARCHAR(255) DEFAULT NULL AFTER original_price');
    ensure_instructor_course_column('is_free', 'ALTER TABLE instructor_courses ADD COLUMN is_free TINYINT(1) NOT NULL DEFAULT 0 AFTER featured');
    ensure_instructor_course_column('course_language', "ALTER TABLE instructor_courses ADD COLUMN course_language ENUM('hindi','english') NOT NULL DEFAULT 'hindi' AFTER course_level");

    db()->query("
        CREATE TABLE IF NOT EXISTS instructor_course_contents (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            instructor_id INT UNSIGNED NOT NULL,
            course_id INT UNSIGNED NOT NULL,
            content_title VARCHAR(180) NOT NULL,
            content_type ENUM('pdf','lecture','video_upload','live','youtube','vimeo','quiz','assignment','resource') NOT NULL DEFAULT 'lecture',
            resource_url VARCHAR(255) DEFAULT NULL,
            duration_minutes INT UNSIGNED NOT NULL DEFAULT 0,
            sort_order INT UNSIGNED NOT NULL DEFAULT 1,
            is_preview TINYINT(1) NOT NULL DEFAULT 0,
            status ENUM('draft','published') NOT NULL DEFAULT 'draft',
            instructions TEXT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY instructor_course_contents_user_index (instructor_id),
            KEY instructor_course_contents_course_index (course_id),
            CONSTRAINT instructor_course_contents_user_foreign FOREIGN KEY (instructor_id) REFERENCES users (id) ON DELETE CASCADE,
            CONSTRAINT instructor_course_contents_course_foreign FOREIGN KEY (course_id) REFERENCES instructor_courses (id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    db()->query("
        CREATE TABLE IF NOT EXISTS instructor_course_resources (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            instructor_id INT UNSIGNED NOT NULL,
            course_id INT UNSIGNED NOT NULL,
            chapter_id INT UNSIGNED NULL,
            document_purpose ENUM('course','exam') NOT NULL DEFAULT 'course',
            exam_category_id INT UNSIGNED NULL,
            exam_id INT UNSIGNED NULL,
            resource_title VARCHAR(180) NOT NULL,
            resource_type ENUM('pdf','document') NOT NULL DEFAULT 'pdf',
            file_path VARCHAR(255) NOT NULL,
            thumbnail_path VARCHAR(255) DEFAULT NULL,
            price DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            sort_order INT UNSIGNED NOT NULL DEFAULT 1,
            status ENUM('draft','published') NOT NULL DEFAULT 'published',
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY instructor_course_resources_user_index (instructor_id),
            KEY instructor_course_resources_course_index (course_id),
            KEY instructor_course_resources_chapter_index (chapter_id),
            CONSTRAINT instructor_course_resources_user_foreign FOREIGN KEY (instructor_id) REFERENCES users (id) ON DELETE CASCADE,
            CONSTRAINT instructor_course_resources_course_foreign FOREIGN KEY (course_id) REFERENCES instructor_courses (id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    ensure_instructor_resource_column('chapter_id', 'ALTER TABLE instructor_course_resources ADD COLUMN chapter_id INT UNSIGNED NULL AFTER course_id');
    ensure_instructor_resource_column('document_purpose', 'ALTER TABLE instructor_course_resources ADD COLUMN document_purpose ENUM("course","exam") NOT NULL DEFAULT "course" AFTER chapter_id');
    ensure_instructor_resource_column('exam_category_id', 'ALTER TABLE instructor_course_resources ADD COLUMN exam_category_id INT UNSIGNED NULL AFTER document_purpose');
    ensure_instructor_resource_column('exam_id', 'ALTER TABLE instructor_course_resources ADD COLUMN exam_id INT UNSIGNED NULL AFTER document_purpose');
    ensure_instructor_resource_column('thumbnail_path', 'ALTER TABLE instructor_course_resources ADD COLUMN thumbnail_path VARCHAR(255) DEFAULT NULL AFTER file_path');
    ensure_instructor_resource_column('price', 'ALTER TABLE instructor_course_resources ADD COLUMN price DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER file_path');

    db()->query("
        CREATE TABLE IF NOT EXISTS instructor_content_combos (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            instructor_id INT UNSIGNED NOT NULL,
            combo_name VARCHAR(180) NOT NULL,
            course_id INT UNSIGNED NULL,
            document_id INT UNSIGNED NULL,
            live_channel_id INT UNSIGNED NULL,
            exam_id INT UNSIGNED NULL,
            price DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            status ENUM('draft','published','paused') NOT NULL DEFAULT 'draft',
            description TEXT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY instructor_content_combos_user_index (instructor_id),
            KEY instructor_content_combos_course_index (course_id),
            CONSTRAINT instructor_content_combos_user_foreign FOREIGN KEY (instructor_id) REFERENCES users (id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    db()->query("
        CREATE TABLE IF NOT EXISTS instructor_questions (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            instructor_id INT UNSIGNED NOT NULL,
            course_id INT UNSIGNED NOT NULL,
            content_id INT UNSIGNED NULL,
            source_question_id BIGINT UNSIGNED NULL,
            q_type ENUM('MCQ','TF') NOT NULL DEFAULT 'MCQ',
            question_en TEXT NULL,
            question_hi TEXT NULL,
            option_a_en TEXT NULL,
            option_a_hi TEXT NULL,
            option_b_en TEXT NULL,
            option_b_hi TEXT NULL,
            option_c_en TEXT NULL,
            option_c_hi TEXT NULL,
            option_d_en TEXT NULL,
            option_d_hi TEXT NULL,
            correct_key ENUM('A','B','C','D','TRUE','FALSE') NOT NULL DEFAULT 'A',
            marks DECIMAL(5,2) NOT NULL DEFAULT 1.00,
            solution TEXT NULL,
            status ENUM('active','inactive') NOT NULL DEFAULT 'active',
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY instructor_questions_source_unique (instructor_id, source_question_id),
            KEY instructor_questions_instructor_index (instructor_id),
            KEY instructor_questions_course_index (course_id),
            KEY instructor_questions_content_index (content_id),
            CONSTRAINT instructor_questions_user_foreign FOREIGN KEY (instructor_id) REFERENCES users (id) ON DELETE CASCADE,
            CONSTRAINT instructor_questions_course_foreign FOREIGN KEY (course_id) REFERENCES instructor_courses (id) ON DELETE CASCADE,
            CONSTRAINT instructor_questions_content_foreign FOREIGN KEY (content_id) REFERENCES instructor_course_contents (id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    ensure_instructor_question_column('solution', 'ALTER TABLE instructor_questions ADD COLUMN solution TEXT NULL AFTER marks');

    db()->query("
        CREATE TABLE IF NOT EXISTS instructor_exam_categories (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            instructor_id INT UNSIGNED NOT NULL,
            category_name VARCHAR(140) NOT NULL,
            description TEXT NULL,
            status ENUM('active','archived') NOT NULL DEFAULT 'active',
            sort_order INT UNSIGNED NOT NULL DEFAULT 1,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY instructor_exam_categories_unique (instructor_id, category_name),
            KEY instructor_exam_categories_user_index (instructor_id),
            CONSTRAINT instructor_exam_categories_user_foreign FOREIGN KEY (instructor_id) REFERENCES users (id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    db()->query("
        CREATE TABLE IF NOT EXISTS instructor_exams (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            instructor_id INT UNSIGNED NOT NULL,
            course_id INT UNSIGNED NULL,
            exam_category_id INT UNSIGNED NULL,
            source_exam_id BIGINT UNSIGNED NULL,
            title VARCHAR(180) NOT NULL,
            description TEXT NULL,
            duration_minutes INT UNSIGNED NOT NULL DEFAULT 60,
            exam_type ENUM('manual','random') NOT NULL DEFAULT 'manual',
            total_questions INT UNSIGNED NOT NULL DEFAULT 0,
            total_marks DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            is_live TINYINT(1) NOT NULL DEFAULT 0,
            status ENUM('draft','published','paused') NOT NULL DEFAULT 'draft',
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY instructor_exams_source_unique (instructor_id, source_exam_id),
            KEY instructor_exams_user_index (instructor_id),
            KEY instructor_exams_course_index (course_id),
            KEY instructor_exams_category_index (exam_category_id),
            CONSTRAINT instructor_exams_user_foreign FOREIGN KEY (instructor_id) REFERENCES users (id) ON DELETE CASCADE,
            CONSTRAINT instructor_exams_course_foreign FOREIGN KEY (course_id) REFERENCES instructor_courses (id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    $examCategoryColumn = db()->query("SHOW COLUMNS FROM instructor_exams LIKE 'exam_category_id'");
    if (!$examCategoryColumn->fetch_assoc()) {
        db()->query('ALTER TABLE instructor_exams ADD COLUMN exam_category_id INT UNSIGNED NULL AFTER course_id');
    }
    ensure_table_index('instructor_exams', 'instructor_exams_category_index', 'ALTER TABLE instructor_exams ADD INDEX instructor_exams_category_index (exam_category_id)');

    db()->query("
        CREATE TABLE IF NOT EXISTS instructor_exam_questions (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            instructor_id INT UNSIGNED NOT NULL,
            exam_id INT UNSIGNED NOT NULL,
            question_id INT UNSIGNED NOT NULL,
            marks DECIMAL(5,2) NOT NULL DEFAULT 1.00,
            sort_order INT UNSIGNED NOT NULL DEFAULT 1,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY instructor_exam_questions_unique (exam_id, question_id),
            KEY instructor_exam_questions_user_index (instructor_id),
            KEY instructor_exam_questions_question_index (question_id),
            CONSTRAINT instructor_exam_questions_user_foreign FOREIGN KEY (instructor_id) REFERENCES users (id) ON DELETE CASCADE,
            CONSTRAINT instructor_exam_questions_exam_foreign FOREIGN KEY (exam_id) REFERENCES instructor_exams (id) ON DELETE CASCADE,
            CONSTRAINT instructor_exam_questions_question_foreign FOREIGN KEY (question_id) REFERENCES instructor_questions (id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    db()->query("
        CREATE TABLE IF NOT EXISTS instructor_exam_random_rules (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            instructor_id INT UNSIGNED NOT NULL,
            exam_id INT UNSIGNED NOT NULL,
            content_id INT UNSIGNED NULL,
            question_limit INT UNSIGNED NOT NULL DEFAULT 10,
            only_active TINYINT(1) NOT NULL DEFAULT 1,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY instructor_exam_random_rules_user_index (instructor_id),
            KEY instructor_exam_random_rules_exam_index (exam_id),
            KEY instructor_exam_random_rules_content_index (content_id),
            CONSTRAINT instructor_exam_random_rules_user_foreign FOREIGN KEY (instructor_id) REFERENCES users (id) ON DELETE CASCADE,
            CONSTRAINT instructor_exam_random_rules_exam_foreign FOREIGN KEY (exam_id) REFERENCES instructor_exams (id) ON DELETE CASCADE,
            CONSTRAINT instructor_exam_random_rules_content_foreign FOREIGN KEY (content_id) REFERENCES instructor_course_contents (id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    db()->query("
        CREATE TABLE IF NOT EXISTS student_exam_attempts (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            student_id INT UNSIGNED NOT NULL,
            exam_id INT UNSIGNED NOT NULL,
            score DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            total_marks DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            total_questions INT UNSIGNED NOT NULL DEFAULT 0,
            correct_count INT UNSIGNED NOT NULL DEFAULT 0,
            wrong_count INT UNSIGNED NOT NULL DEFAULT 0,
            skipped_count INT UNSIGNED NOT NULL DEFAULT 0,
            percentage DECIMAL(5,2) NOT NULL DEFAULT 0.00,
            status ENUM('submitted','review') NOT NULL DEFAULT 'submitted',
            started_at DATETIME NOT NULL,
            submitted_at DATETIME NOT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY student_exam_attempts_student_index (student_id),
            KEY student_exam_attempts_exam_index (exam_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    db()->query("
        CREATE TABLE IF NOT EXISTS student_exam_attempt_answers (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            attempt_id BIGINT UNSIGNED NOT NULL,
            question_id INT UNSIGNED NOT NULL,
            selected_key VARCHAR(10) DEFAULT NULL,
            correct_key VARCHAR(10) NOT NULL,
            is_correct TINYINT(1) NOT NULL DEFAULT 0,
            marks DECIMAL(5,2) NOT NULL DEFAULT 0.00,
            earned_marks DECIMAL(5,2) NOT NULL DEFAULT 0.00,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY student_exam_attempt_answers_attempt_index (attempt_id),
            KEY student_exam_attempt_answers_question_index (question_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    db()->query("
        CREATE TABLE IF NOT EXISTS student_course_enrollments (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            student_id INT UNSIGNED NOT NULL,
            course_id INT UNSIGNED NOT NULL,
            purchase_id BIGINT UNSIGNED NULL,
            progress_percent DECIMAL(5,2) NOT NULL DEFAULT 0,
            status ENUM('active','completed','cancelled') NOT NULL DEFAULT 'active',
            enrolled_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            completed_at DATETIME NULL,
            PRIMARY KEY (id),
            UNIQUE KEY student_course_enrollments_unique (student_id, course_id),
            KEY student_course_enrollments_course_index (course_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    ensure_table_index('student_exam_attempts', 'student_exam_attempts_exam_student_index', 'ALTER TABLE student_exam_attempts ADD INDEX student_exam_attempts_exam_student_index (exam_id, student_id)');
    ensure_table_index('student_course_enrollments', 'student_course_enrollments_course_student_index', 'ALTER TABLE student_course_enrollments ADD INDEX student_course_enrollments_course_student_index (course_id, student_id)');

    db()->query("
        CREATE TABLE IF NOT EXISTS instructor_settings (
            instructor_id INT UNSIGNED NOT NULL,
            default_class_mode ENUM('online','offline','hybrid') NOT NULL DEFAULT 'online',
            contact_number VARCHAR(30) DEFAULT NULL,
            whatsapp_number VARCHAR(30) DEFAULT NULL,
            profile_headline VARCHAR(160) DEFAULT NULL,
            profile_bio TEXT NULL,
            expertise VARCHAR(255) DEFAULT NULL,
            qualification VARCHAR(255) DEFAULT NULL,
            profile_logo_path VARCHAR(255) DEFAULT NULL,
            profile_banner_path VARCHAR(255) DEFAULT NULL,
            support_email VARCHAR(160) DEFAULT NULL,
            telegram_channel VARCHAR(255) DEFAULT NULL,
            instagram_url VARCHAR(255) DEFAULT NULL,
            youtube_channel VARCHAR(255) DEFAULT NULL,
            live_platform ENUM('google_meet','youtube_live') NOT NULL DEFAULT 'google_meet',
            google_meet_link VARCHAR(255) DEFAULT NULL,
            youtube_live_link VARCHAR(255) DEFAULT NULL,
            kyc_document_type VARCHAR(60) DEFAULT NULL,
            kyc_document_number VARCHAR(80) DEFAULT NULL,
            kyc_document_path VARCHAR(255) DEFAULT NULL,
            kyc_status ENUM('not_submitted','pending','verified','rejected') NOT NULL DEFAULT 'not_submitted',
            public_profile TINYINT(1) NOT NULL DEFAULT 1,
            auto_recording TINYINT(1) NOT NULL DEFAULT 0,
            notification_email TINYINT(1) NOT NULL DEFAULT 1,
            teaching_timezone VARCHAR(80) NOT NULL DEFAULT 'Asia/Kolkata',
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (instructor_id),
            CONSTRAINT instructor_settings_user_foreign FOREIGN KEY (instructor_id) REFERENCES users (id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    ensure_instructor_setting_column('contact_number', 'ALTER TABLE instructor_settings ADD COLUMN contact_number VARCHAR(30) DEFAULT NULL AFTER default_class_mode');
    ensure_instructor_setting_column('whatsapp_number', 'ALTER TABLE instructor_settings ADD COLUMN whatsapp_number VARCHAR(30) DEFAULT NULL AFTER contact_number');
    ensure_instructor_setting_column('profile_headline', 'ALTER TABLE instructor_settings ADD COLUMN profile_headline VARCHAR(160) DEFAULT NULL AFTER whatsapp_number');
    ensure_instructor_setting_column('profile_bio', 'ALTER TABLE instructor_settings ADD COLUMN profile_bio TEXT NULL AFTER profile_headline');
    ensure_instructor_setting_column('expertise', 'ALTER TABLE instructor_settings ADD COLUMN expertise VARCHAR(255) DEFAULT NULL AFTER profile_bio');
    ensure_instructor_setting_column('qualification', 'ALTER TABLE instructor_settings ADD COLUMN qualification VARCHAR(255) DEFAULT NULL AFTER expertise');
    ensure_instructor_setting_column('profile_logo_path', 'ALTER TABLE instructor_settings ADD COLUMN profile_logo_path VARCHAR(255) DEFAULT NULL AFTER qualification');
    ensure_instructor_setting_column('profile_banner_path', 'ALTER TABLE instructor_settings ADD COLUMN profile_banner_path VARCHAR(255) DEFAULT NULL AFTER profile_logo_path');
    ensure_instructor_setting_column('support_email', 'ALTER TABLE instructor_settings ADD COLUMN support_email VARCHAR(160) DEFAULT NULL AFTER profile_banner_path');
    ensure_instructor_setting_column('telegram_channel', 'ALTER TABLE instructor_settings ADD COLUMN telegram_channel VARCHAR(255) DEFAULT NULL AFTER support_email');
    ensure_instructor_setting_column('instagram_url', 'ALTER TABLE instructor_settings ADD COLUMN instagram_url VARCHAR(255) DEFAULT NULL AFTER telegram_channel');
    ensure_instructor_setting_column('youtube_channel', 'ALTER TABLE instructor_settings ADD COLUMN youtube_channel VARCHAR(255) DEFAULT NULL AFTER instagram_url');
    ensure_instructor_setting_column('live_platform', "ALTER TABLE instructor_settings ADD COLUMN live_platform ENUM('google_meet','youtube_live') NOT NULL DEFAULT 'google_meet' AFTER youtube_channel");
    ensure_instructor_setting_column('google_meet_link', 'ALTER TABLE instructor_settings ADD COLUMN google_meet_link VARCHAR(255) DEFAULT NULL AFTER live_platform');
    ensure_instructor_setting_column('youtube_live_link', 'ALTER TABLE instructor_settings ADD COLUMN youtube_live_link VARCHAR(255) DEFAULT NULL AFTER google_meet_link');
    ensure_instructor_setting_column('kyc_document_type', 'ALTER TABLE instructor_settings ADD COLUMN kyc_document_type VARCHAR(60) DEFAULT NULL AFTER youtube_channel');
    ensure_instructor_setting_column('kyc_document_number', 'ALTER TABLE instructor_settings ADD COLUMN kyc_document_number VARCHAR(80) DEFAULT NULL AFTER kyc_document_type');
    ensure_instructor_setting_column('kyc_document_path', 'ALTER TABLE instructor_settings ADD COLUMN kyc_document_path VARCHAR(255) DEFAULT NULL AFTER kyc_document_number');
    ensure_instructor_setting_column('kyc_status', "ALTER TABLE instructor_settings ADD COLUMN kyc_status ENUM('not_submitted','pending','verified','rejected') NOT NULL DEFAULT 'not_submitted' AFTER kyc_document_path");
}

function instructor_counts(int $instructorId): array
{
    $counts = ['batches' => 0, 'classes' => 0, 'live' => 0, 'completed' => 0];

    $stmt = db()->prepare('SELECT COUNT(*) AS total FROM instructor_batches WHERE instructor_id = ?');
    $stmt->bind_param('i', $instructorId);
    $stmt->execute();
    $counts['batches'] = (int) ($stmt->get_result()->fetch_assoc()['total'] ?? 0);

    $stmt = db()->prepare("SELECT class_status, COUNT(*) AS total FROM instructor_classes WHERE instructor_id = ? GROUP BY class_status");
    $stmt->bind_param('i', $instructorId);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $counts['classes'] += (int) $row['total'];
        if ($row['class_status'] === 'live') {
            $counts['live'] = (int) $row['total'];
        }
        if ($row['class_status'] === 'completed') {
            $counts['completed'] = (int) $row['total'];
        }
    }

    return $counts;
}

function instructor_batches(int $instructorId): array
{
    $stmt = db()->prepare('SELECT * FROM instructor_batches WHERE instructor_id = ? ORDER BY id DESC');
    $stmt->bind_param('i', $instructorId);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    foreach ($rows as &$row) {
        $row['thumbnail_path'] = ensure_course_thumbnail($row);
    }
    unset($row);
    return $rows;
}

function ensure_instructor_batch_column(string $column, string $alterSql): void
{
    $column = db()->real_escape_string($column);
    $result = db()->query("SHOW COLUMNS FROM instructor_batches LIKE '{$column}'");
    if (!$result->fetch_assoc()) {
        db()->query($alterSql);
    }
}

function instructor_classes(int $instructorId, int $limit = 50): array
{
    $stmt = db()->prepare("
        SELECT c.*, b.batch_name, b.teacher_name
        FROM instructor_classes c
        LEFT JOIN instructor_batches b ON b.id = c.batch_id AND b.instructor_id = c.instructor_id
        WHERE c.instructor_id = ?
        ORDER BY c.class_date DESC, c.starts_at DESC
        LIMIT ?
    ");
    $stmt->bind_param('ii', $instructorId, $limit);
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

function instructor_courses(int $instructorId): array
{
    $stmt = db()->prepare("
        SELECT c.*, cat.name AS category_name, sub.name AS subcategory_name
        FROM instructor_courses c
        LEFT JOIN course_categories cat ON cat.id = c.category_id
        LEFT JOIN course_categories sub ON sub.id = c.subcategory_id
        WHERE c.instructor_id = ?
        ORDER BY c.id DESC
    ");
    $stmt->bind_param('i', $instructorId);
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

function ensure_instructor_course_column(string $column, string $alterSql): void
{
    $column = db()->real_escape_string($column);
    $result = db()->query("SHOW COLUMNS FROM instructor_courses LIKE '{$column}'");
    if (!$result->fetch_assoc()) {
        db()->query($alterSql);
    }
}

function ensure_instructor_setting_column(string $column, string $alterSql): void
{
    $column = db()->real_escape_string($column);
    $result = db()->query("SHOW COLUMNS FROM instructor_settings LIKE '{$column}'");
    if (!$result->fetch_assoc()) {
        db()->query($alterSql);
    }
}

function ensure_instructor_question_column(string $column, string $alterSql): void
{
    $column = db()->real_escape_string($column);
    $result = db()->query("SHOW COLUMNS FROM instructor_questions LIKE '{$column}'");
    if (!$result->fetch_assoc()) {
        db()->query($alterSql);
    }
}

function ensure_instructor_resource_column(string $column, ?string $alterSql): void
{
    $column = db()->real_escape_string($column);
    $result = db()->query("SHOW COLUMNS FROM instructor_course_resources LIKE '{$column}'");
    if (!$result->fetch_assoc() && $alterSql !== null) {
        db()->query($alterSql);
    }
}

function ensure_table_index(string $table, string $index, string $alterSql): void
{
    $table = db()->real_escape_string($table);
    $index = db()->real_escape_string($index);
    $result = db()->query("SHOW INDEX FROM `{$table}` WHERE Key_name = '{$index}'");
    if (!$result->fetch_assoc()) {
        db()->query($alterSql);
    }
}

function instructor_questions(int $instructorId, int $limit = 50, int $offset = 0): array
{
    $stmt = db()->prepare("
        SELECT q.*, c.title AS course_title, cc.content_title
        FROM instructor_questions q
        INNER JOIN instructor_courses c ON c.id = q.course_id AND c.instructor_id = q.instructor_id
        LEFT JOIN instructor_course_contents cc ON cc.id = q.content_id AND cc.instructor_id = q.instructor_id
        WHERE q.instructor_id = ?
        ORDER BY q.id DESC
        LIMIT ? OFFSET ?
    ");
    $stmt->bind_param('iii', $instructorId, $limit, $offset);
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

function instructor_question_count(int $instructorId): int
{
    $stmt = db()->prepare('SELECT COUNT(*) AS total FROM instructor_questions WHERE instructor_id = ?');
    $stmt->bind_param('i', $instructorId);
    $stmt->execute();
    return (int) ($stmt->get_result()->fetch_assoc()['total'] ?? 0);
}

function instructor_exams(int $instructorId): array
{
    $stmt = db()->prepare("
        SELECT e.*, c.title AS course_title, ec.category_name AS exam_category_name,
            COUNT(DISTINCT eq.id) AS assigned_questions,
            COUNT(DISTINCT rr.id) AS random_rules,
            COUNT(DISTINCT sea.id) AS attempt_count,
            COUNT(DISTINCT sea.student_id) AS attempted_students
        FROM instructor_exams e
        LEFT JOIN instructor_courses c ON c.id = e.course_id AND c.instructor_id = e.instructor_id
        LEFT JOIN instructor_exam_categories ec ON ec.id = e.exam_category_id AND ec.instructor_id = e.instructor_id
        LEFT JOIN instructor_exam_questions eq ON eq.exam_id = e.id
        LEFT JOIN instructor_exam_random_rules rr ON rr.exam_id = e.id
        LEFT JOIN student_exam_attempts sea ON sea.exam_id = e.id
        WHERE e.instructor_id = ?
        GROUP BY e.id, c.title, ec.category_name
        ORDER BY e.updated_at DESC, e.id DESC
    ");
    $stmt->bind_param('i', $instructorId);
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

function instructor_exam_question_ids(int $instructorId, int $examId): array
{
    $stmt = db()->prepare('SELECT question_id FROM instructor_exam_questions WHERE instructor_id = ? AND exam_id = ? ORDER BY sort_order ASC, id ASC');
    $stmt->bind_param('ii', $instructorId, $examId);
    $stmt->execute();
    return array_map('intval', array_column($stmt->get_result()->fetch_all(MYSQLI_ASSOC), 'question_id'));
}

function instructor_exam_random_rule(int $instructorId, int $examId): ?array
{
    $stmt = db()->prepare('SELECT * FROM instructor_exam_random_rules WHERE instructor_id = ? AND exam_id = ? ORDER BY id ASC LIMIT 1');
    $stmt->bind_param('ii', $instructorId, $examId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    return $row ?: null;
}

function instructor_course_contents(int $instructorId): array
{
    $stmt = db()->prepare("
        SELECT cc.*, c.title AS course_title
        FROM instructor_course_contents cc
        INNER JOIN instructor_courses c ON c.id = cc.course_id AND c.instructor_id = cc.instructor_id
        WHERE cc.instructor_id = ?
        ORDER BY c.title ASC, cc.sort_order ASC, cc.id DESC
    ");
    $stmt->bind_param('i', $instructorId);
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

function instructor_course_resources(int $instructorId): array
{
    $stmt = db()->prepare("
        SELECT r.*, c.title AS course_title, cc.content_title AS chapter_title, e.title AS exam_title, COALESCE(ec.category_name, rec.category_name) AS exam_category_name
        FROM instructor_course_resources r
        INNER JOIN instructor_courses c ON c.id = r.course_id AND c.instructor_id = r.instructor_id
        LEFT JOIN instructor_course_contents cc ON cc.id = r.chapter_id AND cc.instructor_id = r.instructor_id
        LEFT JOIN instructor_exams e ON e.id = r.exam_id AND e.instructor_id = r.instructor_id
        LEFT JOIN instructor_exam_categories ec ON ec.id = e.exam_category_id AND ec.instructor_id = r.instructor_id
        LEFT JOIN instructor_exam_categories rec ON rec.id = r.exam_category_id AND rec.instructor_id = r.instructor_id
        WHERE r.instructor_id = ?
        ORDER BY c.title ASC, r.sort_order ASC, r.id DESC
    ");
    $stmt->bind_param('i', $instructorId);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    foreach ($rows as &$row) {
        $row['thumbnail_path'] = ensure_course_resource_thumbnail($row);
    }
    unset($row);
    return $rows;
}

function instructor_content_combos(int $instructorId): array
{
    $stmt = db()->prepare("
        SELECT cb.*, c.title AS course_title, r.resource_title AS document_title, e.title AS exam_title,
               l.channel_name AS live_title, l.status AS live_status
        FROM instructor_content_combos cb
        LEFT JOIN instructor_courses c ON c.id = cb.course_id AND c.instructor_id = cb.instructor_id
        LEFT JOIN instructor_course_resources r ON r.id = cb.document_id AND r.instructor_id = cb.instructor_id
        LEFT JOIN instructor_exams e ON e.id = cb.exam_id AND e.instructor_id = cb.instructor_id
        LEFT JOIN live_stream_channels l ON l.id = cb.live_channel_id AND l.instructor_id = cb.instructor_id
        WHERE cb.instructor_id = ?
        ORDER BY cb.updated_at DESC, cb.id DESC
    ");
    $stmt->bind_param('i', $instructorId);
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

function instructor_exam_categories(int $instructorId): array
{
    $stmt = db()->prepare('
        SELECT ec.*,
               (SELECT COUNT(*) FROM instructor_exams e WHERE e.exam_category_id = ec.id AND e.instructor_id = ec.instructor_id) AS exam_count
        FROM instructor_exam_categories ec
        WHERE ec.instructor_id = ?
        ORDER BY ec.sort_order ASC, ec.category_name ASC
    ');
    $stmt->bind_param('i', $instructorId);
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

function ensure_exam_category(int $instructorId, string $name): int
{
    $name = substr(trim($name), 0, 140);
    if ($name === '') {
        return 0;
    }
    $stmt = db()->prepare('INSERT INTO instructor_exam_categories (instructor_id, category_name, status) VALUES (?, ?, "active") ON DUPLICATE KEY UPDATE status = "active"');
    $stmt->bind_param('is', $instructorId, $name);
    $stmt->execute();
    $stmt = db()->prepare('SELECT id FROM instructor_exam_categories WHERE instructor_id = ? AND category_name = ? LIMIT 1');
    $stmt->bind_param('is', $instructorId, $name);
    $stmt->execute();
    return (int) ($stmt->get_result()->fetch_assoc()['id'] ?? 0);
}

function save_resource_thumbnail_file(string $field): string
{
    if (empty($_FILES[$field]['tmp_name']) || !is_uploaded_file($_FILES[$field]['tmp_name'])) {
        return '';
    }

    $maxBytes = 4 * 1024 * 1024;
    if ((int) ($_FILES[$field]['size'] ?? 0) > $maxBytes) {
        throw new RuntimeException('Thumbnail is too large. Maximum allowed size is 4 MB.');
    }

    $mime = mime_content_type($_FILES[$field]['tmp_name']) ?: '';
    $allowed = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];
    if (!isset($allowed[$mime])) {
        throw new RuntimeException('Only JPG, PNG or WEBP thumbnail allowed.');
    }

    $dir = __DIR__ . '/../../uploads/document-thumbnails';
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }

    $name = 'thumb-' . bin2hex(random_bytes(8)) . '.' . $allowed[$mime];
    $target = $dir . DIRECTORY_SEPARATOR . $name;
    if (!move_uploaded_file($_FILES[$field]['tmp_name'], $target)) {
        throw new RuntimeException('Unable to upload thumbnail.');
    }

    return 'uploads/document-thumbnails/' . $name;
}

function save_course_thumbnail_file(string $field): string
{
    if (empty($_FILES[$field]['tmp_name']) || !is_uploaded_file($_FILES[$field]['tmp_name'])) {
        return '';
    }

    $maxBytes = 4 * 1024 * 1024;
    if ((int) ($_FILES[$field]['size'] ?? 0) > $maxBytes) {
        throw new RuntimeException('Course thumbnail is too large. Maximum allowed size is 4 MB.');
    }

    $mime = mime_content_type($_FILES[$field]['tmp_name']) ?: '';
    $allowed = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];
    if (!isset($allowed[$mime])) {
        throw new RuntimeException('Only JPG, PNG or WEBP course thumbnail allowed.');
    }

    $dir = __DIR__ . '/../../uploads/course-thumbnails';
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }

    $name = 'course-thumb-' . bin2hex(random_bytes(8)) . '.' . $allowed[$mime];
    $target = $dir . DIRECTORY_SEPARATOR . $name;
    if (!move_uploaded_file($_FILES[$field]['tmp_name'], $target)) {
        throw new RuntimeException('Unable to upload course thumbnail.');
    }

    return 'uploads/course-thumbnails/' . $name;
}

function ensure_course_thumbnail(array $course): string
{
    $existing = trim((string) ($course['thumbnail_path'] ?? ''));
    if (
        $existing !== ''
        && !preg_match('#^uploads/course-thumbnails/course-card-\d+\.svg$#', $existing)
        && is_file(__DIR__ . '/../../' . $existing)
    ) {
        return $existing;
    }

    $id = (int) ($course['id'] ?? 0);
    if ($id <= 0) {
        return $existing;
    }

    $dir = __DIR__ . '/../../uploads/course-thumbnails';
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }

    $title = trim((string) ($course['title'] ?? 'Course'));
    $category = trim((string) (($course['category_name'] ?? '') ?: ($course['category'] ?? 'GYAN NEXA')));
    $level = trim((string) ($course['course_level'] ?? 'all'));
    $headline = preg_match('/\bO\s*Level\b/i', $title . ' ' . $category) ? 'O Level July 2026' : (preg_match('/\bCCC\b|Computer Concept/i', $title . ' ' . $category) ? 'CCC July 2026' : 'GYAN NEXA Course');
    $shortTitle = strlen($title) > 25 ? substr($title, 0, 22) . '...' : $title;
    $shortCategory = strlen($category) > 28 ? substr($category, 0, 25) . '...' : $category;
    $line1 = htmlspecialchars($headline, ENT_QUOTES, 'UTF-8');
    $line2 = htmlspecialchars($shortTitle, ENT_QUOTES, 'UTF-8');
    $line3 = htmlspecialchars($shortCategory, ENT_QUOTES, 'UTF-8');
    $levelLine = htmlspecialchars(ucfirst($level), ENT_QUOTES, 'UTF-8');
    $colors = [
        ['#1116b8', '#080b68', '#e20f8f'],
        ['#b51408', '#064f16', '#f0b400'],
        ['#0b4774', '#0a3e68', '#c90883'],
        ['#5f178f', '#123c75', '#e20f8f'],
        ['#8a123e', '#163f67', '#f0b400'],
    ];
    $palette = $colors[$id % count($colors)];
    $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="640" height="360" viewBox="0 0 640 360">'
        . '<rect width="640" height="360" rx="18" fill="#ffffff"/>'
        . '<rect x="0" y="0" width="640" height="72" rx="18" fill="' . $palette[0] . '"/>'
        . '<rect x="0" y="54" width="640" height="18" fill="' . $palette[0] . '"/>'
        . '<text x="320" y="50" text-anchor="middle" font-family="Georgia, Times New Roman, serif" font-size="46" font-weight="900" fill="#ffffff">' . $line1 . '</text>'
        . '<rect x="0" y="72" width="346" height="204" fill="#fff"/>'
        . '<rect x="346" y="72" width="294" height="204" fill="' . $palette[1] . '"/>'
        . '<circle cx="50" cy="105" r="24" fill="#fff" stroke="#d8d8d8" stroke-width="3"/>'
        . '<text x="50" y="113" text-anchor="middle" font-family="Times New Roman, Times, serif" font-size="14" font-weight="900" fill="#d10000">GR</text>'
        . '<text x="86" y="111" font-family="Times New Roman, Times, serif" font-size="34" font-weight="900" fill="#073f76">' . $line2 . '</text>'
        . '<rect x="12" y="132" width="312" height="42" rx="4" fill="' . $palette[0] . '"/>'
        . '<text x="168" y="160" text-anchor="middle" font-family="Times New Roman, Times, serif" font-size="21" font-weight="900" fill="#fff">' . $line3 . '</text>'
        . '<rect x="16" y="188" width="98" height="38" rx="4" fill="#111827" transform="rotate(-8 65 207)"/>'
        . '<text x="65" y="204" text-anchor="middle" font-family="Times New Roman, Times, serif" font-size="12" font-weight="900" fill="#ffeb3b" transform="rotate(-8 65 207)">LIMITED</text>'
        . '<text x="65" y="218" text-anchor="middle" font-family="Times New Roman, Times, serif" font-size="12" font-weight="900" fill="#ffeb3b" transform="rotate(-8 65 207)">OFFER</text>'
        . '<text x="186" y="208" text-anchor="middle" font-family="Times New Roman, Times, serif" font-size="24" font-weight="900" fill="#c00000">100% Success</text>'
        . '<text x="186" y="238" text-anchor="middle" font-family="Times New Roman, Times, serif" font-size="23" font-weight="900" fill="#111827">JOIN NOW</text>'
        . '<rect x="24" y="248" width="224" height="42" rx="8" fill="#1526bd"/>'
        . '<text x="136" y="276" text-anchor="middle" font-family="Times New Roman, Times, serif" font-size="20" font-weight="900" fill="#ffeb3b">TARGET S GRADE</text>'
        . '<rect x="0" y="306" width="330" height="42" fill="' . $palette[2] . '"/>'
        . '<text x="165" y="334" text-anchor="middle" font-family="Times New Roman, Times, serif" font-size="23" font-weight="900" fill="#fff">Validity: 6 Months</text>'
        . '<g font-family="Times New Roman, Times, serif" font-size="16" font-weight="800" fill="#ffffff">'
        . '<circle cx="368" cy="96" r="9" fill="#ffd400"/><text x="384" y="102">Theory Classes with PDF Notes</text>'
        . '<circle cx="368" cy="124" r="9" fill="#ffd400"/><text x="384" y="130">2500+ MCQ Classes with Notes</text>'
        . '<circle cx="368" cy="152" r="9" fill="#ffd400"/><text x="384" y="158">Concept Oriented Lectures</text>'
        . '<circle cx="368" cy="180" r="9" fill="#ffd400"/><text x="384" y="186">Recorded + Live Classes</text>'
        . '<circle cx="368" cy="208" r="9" fill="#ffd400"/><text x="384" y="214">Practical Classes with PDF Notes</text>'
        . '<circle cx="368" cy="236" r="9" fill="#ffd400"/><text x="384" y="242">Original Solved Old Paper</text>'
        . '<circle cx="368" cy="264" r="9" fill="#ffd400"/><text x="384" y="270">Bilingual Content</text>'
        . '</g>'
        . '<rect x="346" y="306" width="294" height="42" fill="#000000"/>'
        . '<text x="493" y="333" text-anchor="middle" font-family="Times New Roman, Times, serif" font-size="18" font-weight="900" fill="#fff">' . $levelLine . '</text>'
        . '</svg>';

    $path = 'uploads/course-thumbnails/course-card-' . $id . '.svg';
    file_put_contents(__DIR__ . '/../../' . $path, $svg);

    $stmt = db()->prepare('UPDATE instructor_courses SET thumbnail_path = ? WHERE id = ?');
    $stmt->bind_param('si', $path, $id);
    $stmt->execute();

    return $path;
}

function ensure_course_resource_thumbnail(array $resource): string
{
    $existing = trim((string) ($resource['thumbnail_path'] ?? ''));
    if (
        $existing !== ''
        && !str_starts_with($existing, 'uploads/course-content/')
        && !preg_match('#^uploads/document-thumbnails/resource-\d+\.svg$#', $existing)
        && is_file(__DIR__ . '/../../' . $existing)
    ) {
        return $existing;
    }

    $id = (int) ($resource['id'] ?? 0);
    if ($id <= 0) {
        return $existing;
    }

    $dir = __DIR__ . '/../../uploads/document-thumbnails';
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }

    $title = trim((string) ($resource['resource_title'] ?? 'Course PDF'));
    $course = trim((string) ($resource['course_title'] ?? 'Study Material'));
    $source = $title . ' ' . $course;
    $isOLevel = (bool) preg_match('/\bO\s*Level\b/i', $source);
    $isCcc = (bool) preg_match('/\bCCC\b|Computer Concept/i', $source);
    $headline = $isOLevel ? 'O Level July 2026' : ($isCcc ? 'CCC July 2026' : 'Study PDF 2026');
    $module = preg_replace('/\s+/', ' ', $title) ?: 'Course PDF';
    $module = mb_substr($module, 0, 22);
    $subtitle = $isOLevel ? 'Full Notes & Practice PDF' : ($isCcc ? 'Computer Concept Notes' : 'Premium Study Notes');
    $line1 = htmlspecialchars($headline, ENT_QUOTES, 'UTF-8');
    $line2 = htmlspecialchars($module, ENT_QUOTES, 'UTF-8');
    $line3 = htmlspecialchars(mb_substr($subtitle, 0, 28), ENT_QUOTES, 'UTF-8');
    $courseLine = htmlspecialchars(mb_substr($course, 0, 30), ENT_QUOTES, 'UTF-8');
    $colors = [
        ['#1116b8', '#080b68', '#e20f8f'],
        ['#b51408', '#064f16', '#f0b400'],
        ['#0b4774', '#0a3e68', '#c90883'],
        ['#5f178f', '#123c75', '#e20f8f'],
        ['#8a123e', '#163f67', '#f0b400'],
    ];
    $palette = $colors[$id % count($colors)];
    $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="640" height="360" viewBox="0 0 640 360">'
        . '<rect width="640" height="360" rx="18" fill="#ffffff"/>'
        . '<rect x="0" y="0" width="640" height="72" rx="18" fill="' . $palette[0] . '"/>'
        . '<rect x="0" y="54" width="640" height="18" fill="' . $palette[0] . '"/>'
        . '<text x="320" y="50" text-anchor="middle" font-family="Georgia, Times New Roman, serif" font-size="48" font-weight="900" fill="#ffffff">' . $line1 . '</text>'
        . '<rect x="0" y="72" width="346" height="204" fill="#fff"/>'
        . '<rect x="346" y="72" width="294" height="204" fill="' . $palette[1] . '"/>'
        . '<circle cx="50" cy="105" r="24" fill="#fff" stroke="#d8d8d8" stroke-width="3"/>'
        . '<text x="50" y="113" text-anchor="middle" font-family="Times New Roman, Times, serif" font-size="14" font-weight="900" fill="#d10000">NI</text>'
        . '<text x="86" y="111" font-family="Times New Roman, Times, serif" font-size="38" font-weight="900" fill="#073f76">' . $line2 . '</text>'
        . '<rect x="12" y="132" width="312" height="42" rx="4" fill="' . $palette[0] . '"/>'
        . '<text x="168" y="161" text-anchor="middle" font-family="Times New Roman, Times, serif" font-size="22" font-weight="900" fill="#fff">' . $line3 . '</text>'
        . '<rect x="16" y="188" width="98" height="38" rx="4" fill="#111827" transform="rotate(-8 65 207)"/>'
        . '<text x="65" y="204" text-anchor="middle" font-family="Times New Roman, Times, serif" font-size="12" font-weight="900" fill="#ffeb3b" transform="rotate(-8 65 207)">LIMITED</text>'
        . '<text x="65" y="218" text-anchor="middle" font-family="Times New Roman, Times, serif" font-size="12" font-weight="900" fill="#ffeb3b" transform="rotate(-8 65 207)">OFFER</text>'
        . '<text x="184" y="208" text-anchor="middle" font-family="Times New Roman, Times, serif" font-size="24" font-weight="900" fill="#c00000">100% सफलता</text>'
        . '<text x="184" y="238" text-anchor="middle" font-family="Times New Roman, Times, serif" font-size="24" font-weight="900" fill="#111827">के लिए JOIN करें</text>'
        . '<rect x="24" y="248" width="224" height="42" rx="8" fill="#1526bd"/>'
        . '<text x="136" y="276" text-anchor="middle" font-family="Times New Roman, Times, serif" font-size="20" font-weight="900" fill="#ffeb3b">TARGET S GRADE</text>'
        . '<rect x="0" y="306" width="330" height="42" fill="' . $palette[2] . '"/>'
        . '<text x="165" y="334" text-anchor="middle" font-family="Times New Roman, Times, serif" font-size="23" font-weight="900" fill="#fff">Validity: 6 Months</text>'
        . '<g font-family="Times New Roman, Times, serif" font-size="16" font-weight="800" fill="#ffffff">'
        . '<circle cx="368" cy="96" r="9" fill="#ffd400"/><text x="384" y="102">Theory Classes with PDF Notes</text>'
        . '<circle cx="368" cy="124" r="9" fill="#ffd400"/><text x="384" y="130">2500+ MCQ Classes with Notes</text>'
        . '<circle cx="368" cy="152" r="9" fill="#ffd400"/><text x="384" y="158">Concept Oriented Lectures</text>'
        . '<circle cx="368" cy="180" r="9" fill="#ffd400"/><text x="384" y="186">Recorded + Live Classes</text>'
        . '<circle cx="368" cy="208" r="9" fill="#ffd400"/><text x="384" y="214">Practical Classes with PDF Notes</text>'
        . '<circle cx="368" cy="236" r="9" fill="#ffd400"/><text x="384" y="242">Original Solved Old Paper</text>'
        . '<circle cx="368" cy="264" r="9" fill="#ffd400"/><text x="384" y="270">Bilingual Content</text>'
        . '</g>'
        . '<rect x="346" y="306" width="294" height="42" fill="#000000"/>'
        . '<text x="493" y="333" text-anchor="middle" font-family="Times New Roman, Times, serif" font-size="18" font-weight="900" fill="#fff">' . $courseLine . '</text>'
        . '</svg>';

    $path = 'uploads/document-thumbnails/resource-card-' . $id . '.svg';
    file_put_contents(__DIR__ . '/../../' . $path, $svg);

    $stmt = db()->prepare('UPDATE instructor_course_resources SET thumbnail_path = ? WHERE id = ?');
    $stmt->bind_param('si', $path, $id);
    $stmt->execute();

    return $path;
}

function save_course_content_file(string $field): string
{
    if (empty($_FILES[$field]['tmp_name']) || !is_uploaded_file($_FILES[$field]['tmp_name'])) {
        return '';
    }

    $maxBytes = 80 * 1024 * 1024;
    if ((int) ($_FILES[$field]['size'] ?? 0) > $maxBytes) {
        throw new RuntimeException('File is too large. Maximum allowed size is 80 MB.');
    }

    $mime = mime_content_type($_FILES[$field]['tmp_name']) ?: '';
    $allowed = [
        'application/pdf' => 'pdf',
        'application/msword' => 'doc',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
        'application/vnd.ms-powerpoint' => 'ppt',
        'application/vnd.openxmlformats-officedocument.presentationml.presentation' => 'pptx',
        'application/vnd.ms-excel' => 'xls',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'xlsx',
        'video/mp4' => 'mp4',
        'video/webm' => 'webm',
        'video/quicktime' => 'mov',
        'application/zip' => 'zip',
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
    ];

    if (!isset($allowed[$mime])) {
        throw new RuntimeException('Only document, PDF, video, ZIP, JPG and PNG uploads are allowed.');
    }

    $dir = __DIR__ . '/../../uploads/course-content';
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }

    $name = 'content-' . bin2hex(random_bytes(8)) . '.' . $allowed[$mime];
    $target = $dir . DIRECTORY_SEPARATOR . $name;
    if (!move_uploaded_file($_FILES[$field]['tmp_name'], $target)) {
        throw new RuntimeException('Unable to upload content file.');
    }

    return 'uploads/course-content/' . $name;
}

function save_instructor_setting_file(string $field, string $folder, array $allowedMimeTypes, int $maxMb = 5): string
{
    if (empty($_FILES[$field]['tmp_name']) || ($_FILES[$field]['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return '';
    }
    if (($_FILES[$field]['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Upload failed. Please try again.');
    }
    if ((int) ($_FILES[$field]['size'] ?? 0) > $maxMb * 1024 * 1024) {
        throw new RuntimeException('File is too large. Maximum allowed size is ' . $maxMb . ' MB.');
    }

    $tmp = (string) $_FILES[$field]['tmp_name'];
    $mime = mime_content_type($tmp) ?: '';
    if (!isset($allowedMimeTypes[$mime])) {
        throw new RuntimeException('Invalid file type uploaded.');
    }

    $safeFolder = preg_replace('/[^a-z0-9_-]+/i', '-', $folder) ?: 'profile';
    $dir = __DIR__ . '/../../uploads/instructors/' . $safeFolder;
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }

    $name = $field . '-' . bin2hex(random_bytes(8)) . '.' . $allowedMimeTypes[$mime];
    $target = $dir . DIRECTORY_SEPARATOR . $name;
    if (!move_uploaded_file($tmp, $target)) {
        throw new RuntimeException('Unable to save uploaded file.');
    }

    return 'uploads/instructors/' . $safeFolder . '/' . $name;
}

function instructor_setting_row(int $instructorId): array
{
    $stmt = db()->prepare('SELECT * FROM instructor_settings WHERE instructor_id = ? LIMIT 1');
    $stmt->bind_param('i', $instructorId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    if ($row) {
        return $row;
    }

    $stmt = db()->prepare('INSERT IGNORE INTO instructor_settings (instructor_id) VALUES (?)');
    $stmt->bind_param('i', $instructorId);
    $stmt->execute();

    return [
        'default_class_mode' => 'online',
        'contact_number' => '',
        'whatsapp_number' => '',
        'profile_headline' => '',
        'profile_bio' => '',
        'expertise' => '',
        'qualification' => '',
        'profile_logo_path' => '',
        'profile_banner_path' => '',
        'support_email' => '',
        'telegram_channel' => '',
        'instagram_url' => '',
        'youtube_channel' => '',
        'live_platform' => 'google_meet',
        'google_meet_link' => '',
        'youtube_live_link' => '',
        'kyc_document_type' => '',
        'kyc_document_number' => '',
        'kyc_document_path' => '',
        'kyc_status' => 'not_submitted',
        'public_profile' => '1',
        'auto_recording' => '0',
        'notification_email' => '1',
        'teaching_timezone' => 'Asia/Kolkata',
    ];
}

function seed_instructor_demo_content(int $instructorId): void
{
    $stmt = db()->prepare('SELECT COUNT(*) AS total FROM instructor_batches WHERE instructor_id = ?');
    $stmt->bind_param('i', $instructorId);
    $stmt->execute();
    if ((int) ($stmt->get_result()->fetch_assoc()['total'] ?? 0) > 0) {
        return;
    }

    $batches = [
        ['Python Morning Batch', 'Python Full Stack Development', 'online', date('Y-m-d', strtotime('+2 days')), '10:00 AM - 11:00 AM', 35, 'active'],
        ['Digital Marketing Pro', 'Performance Marketing Masterclass', 'hybrid', date('Y-m-d', strtotime('+5 days')), '06:00 PM - 07:30 PM', 45, 'active'],
        ['Offline Spoken English', 'Communication Skills Program', 'offline', date('Y-m-d', strtotime('+1 week')), '04:00 PM - 05:00 PM', 25, 'paused'],
    ];

    $insertBatch = db()->prepare('INSERT INTO instructor_batches (instructor_id, batch_name, course_title, mode, start_date, class_time, capacity, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
    $batchIds = [];
    foreach ($batches as $batch) {
        [$name, $course, $mode, $startDate, $time, $capacity, $status] = $batch;
        $insertBatch->bind_param('isssssis', $instructorId, $name, $course, $mode, $startDate, $time, $capacity, $status);
        $insertBatch->execute();
        $batchIds[] = db()->insert_id;
    }

    $classes = [
        [$batchIds[0] ?? null, 'Python Setup & IDE Walkthrough', 'online', date('Y-m-d'), '10:00:00', 60, 'https://meet.example.com/python-live', '', 'live', 'Install Python, VS Code and run first program.'],
        [$batchIds[0] ?? null, 'Variables, Data Types & Operators', 'online', date('Y-m-d', strtotime('+1 day')), '10:00:00', 75, 'https://meet.example.com/python-day2', '', 'scheduled', 'Practice questions after class.'],
        [$batchIds[1] ?? null, 'Google Ads Campaign Structure', 'online', date('Y-m-d', strtotime('+2 days')), '18:00:00', 90, 'https://meet.example.com/marketing', '', 'scheduled', 'Create sample search campaign.'],
        [$batchIds[2] ?? null, 'Group Speaking Practice', 'offline', date('Y-m-d', strtotime('+3 days')), '16:00:00', 60, '', 'Room 204', 'scheduled', 'Conversation practice and feedback.'],
        [$batchIds[1] ?? null, 'Landing Page Audit Workshop', 'online', date('Y-m-d', strtotime('-1 day')), '18:00:00', 60, 'https://meet.example.com/audit', '', 'completed', 'Reviewed three student landing pages.'],
    ];

    $insertClass = db()->prepare('INSERT INTO instructor_classes (instructor_id, batch_id, class_title, class_type, class_date, starts_at, duration_minutes, meeting_link, room_name, class_status, notes) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
    foreach ($classes as $class) {
        [$batchId, $title, $type, $date, $startsAt, $duration, $meeting, $room, $status, $notes] = $class;
        $insertClass->bind_param('iissssissss', $instructorId, $batchId, $title, $type, $date, $startsAt, $duration, $meeting, $room, $status, $notes);
        $insertClass->execute();
    }
}

function seed_instructor_demo_courses(int $instructorId): void
{
    $stmt = db()->prepare('SELECT COUNT(*) AS total FROM instructor_courses WHERE instructor_id = ?');
    $stmt->bind_param('i', $instructorId);
    $stmt->execute();
    if ((int) ($stmt->get_result()->fetch_assoc()['total'] ?? 0) > 0) {
        seed_instructor_demo_course_contents($instructorId);
        return;
    }

    $courses = [
        ['Python Full Stack Development', 'Computer Course', 24999.00, 'course', 'online', 'beginner', '6 months', 'Orai', 'Civil Lines', 1, 'published', 'Live project based Python, Django, MySQL and deployment course.', 'Near coaching hub, Orai', '9000000101', '9000000101'],
        ['Performance Marketing Masterclass', 'Digital Marketing', 14999.00, 'course', 'hybrid', 'intermediate', '3 months', 'Orai', 'Station Road', 1, 'published', 'Google Ads, Meta Ads, funnels, tracking and campaign optimization.', 'Hybrid center and live classes', '9000000102', '9000000102'],
        ['Spoken English Confidence Program', 'Language Course', 6999.00, 'month', 'offline', 'all', '2 months', 'Orai', 'Main Branch', 0, 'draft', 'Daily speaking practice, grammar polish and interview confidence.', 'Room 204, Main Branch', '9000000103', '9000000103'],
    ];

    $stmt = db()->prepare('INSERT INTO instructor_courses (instructor_id, title, category, price, price_unit, learning_mode, course_level, duration, city, locality, featured, status, short_description, address, call_number, whatsapp_number) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
    foreach ($courses as $course) {
        [$title, $category, $price, $unit, $mode, $level, $duration, $city, $locality, $featured, $status, $desc, $address, $call, $whatsapp] = $course;
        $stmt->bind_param('issdssssssisssss', $instructorId, $title, $category, $price, $unit, $mode, $level, $duration, $city, $locality, $featured, $status, $desc, $address, $call, $whatsapp);
        $stmt->execute();
    }

    seed_instructor_demo_course_contents($instructorId);
}

function seed_instructor_demo_course_contents(int $instructorId): void
{
    $stmt = db()->prepare('SELECT COUNT(*) AS total FROM instructor_course_contents WHERE instructor_id = ?');
    $stmt->bind_param('i', $instructorId);
    $stmt->execute();
    if ((int) ($stmt->get_result()->fetch_assoc()['total'] ?? 0) > 0) {
        return;
    }

    $courseRows = instructor_courses($instructorId);
    if (!$courseRows) {
        return;
    }

    $contentRows = [
        ['Course Orientation & Roadmap', 'lecture', '', 15, 1, 1, 'published', 'Introduce course outcomes, tools and weekly learning plan.'],
        ['Download Syllabus PDF', 'pdf', 'uploads/course-content/sample-syllabus.pdf', 0, 2, 1, 'published', 'Attach course syllabus and checklist.'],
        ['YouTube Demo Lecture', 'youtube', 'https://www.youtube.com/watch?v=dQw4w9WgXcQ', 22, 3, 0, 'published', 'Embed public demo lecture.'],
        ['Live Doubt Class', 'live', 'https://meet.example.com/live-doubt', 60, 4, 0, 'draft', 'Weekly doubt clearing class.'],
        ['Practice Document', 'resource', 'uploads/course-content/practice-document.pdf', 0, 5, 0, 'draft', 'Extra practice material for students.'],
    ];

    $firstCourseId = (int) $courseRows[0]['id'];
    $insertContent = db()->prepare('INSERT INTO instructor_course_contents (instructor_id, course_id, content_title, content_type, resource_url, duration_minutes, sort_order, is_preview, status, instructions) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
    foreach ($contentRows as $row) {
        [$title, $type, $url, $duration, $order, $preview, $status, $instructions] = $row;
        $insertContent->bind_param('iisssiiiss', $instructorId, $firstCourseId, $title, $type, $url, $duration, $order, $preview, $status, $instructions);
        $insertContent->execute();
    }
}
