<?php

function gov_slug(string $name): string
{
    $slug = strtolower(trim(preg_replace('/[^a-z0-9]+/i', '-', $name) ?? ''));
    return trim($slug, '-') ?: 'item';
}

function gov_exam_ensure_tables(): void
{
    db()->query("CREATE TABLE IF NOT EXISTS gov_exam_categories (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT,
        parent_id INT UNSIGNED NULL,
        name VARCHAR(160) NOT NULL,
        slug VARCHAR(180) NOT NULL,
        description VARCHAR(255) NULL,
        status ENUM('active','inactive') NOT NULL DEFAULT 'active',
        sort_order INT UNSIGNED NOT NULL DEFAULT 1,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY gov_exam_categories_slug_unique (slug),
        KEY gov_exam_categories_parent_index (parent_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    db()->query("CREATE TABLE IF NOT EXISTS gov_exam_documents (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT,
        category_id INT UNSIGNED NOT NULL,
        subcategory_id INT UNSIGNED NULL,
        title VARCHAR(180) NOT NULL,
        description VARCHAR(255) NULL,
        document_url VARCHAR(255) NULL,
        price DECIMAL(10,2) NOT NULL DEFAULT 0,
        status ENUM('draft','published') NOT NULL DEFAULT 'published',
        sort_order INT UNSIGNED NOT NULL DEFAULT 1,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY gov_exam_documents_category_index (category_id, subcategory_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    db()->query("CREATE TABLE IF NOT EXISTS gov_exam_live_sessions (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT,
        category_id INT UNSIGNED NOT NULL,
        subcategory_id INT UNSIGNED NULL,
        title VARCHAR(180) NOT NULL,
        description VARCHAR(255) NULL,
        live_url VARCHAR(255) NULL,
        scheduled_at DATETIME NULL,
        status ENUM('scheduled','live','completed','cancelled') NOT NULL DEFAULT 'scheduled',
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY gov_exam_live_category_index (category_id, subcategory_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    db()->query("CREATE TABLE IF NOT EXISTS gov_exam_mock_tests (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT,
        category_id INT UNSIGNED NOT NULL,
        subcategory_id INT UNSIGNED NULL,
        title VARCHAR(180) NOT NULL,
        description VARCHAR(255) NULL,
        duration_minutes INT UNSIGNED NOT NULL DEFAULT 60,
        total_questions INT UNSIGNED NOT NULL DEFAULT 0,
        total_marks DECIMAL(10,2) NOT NULL DEFAULT 0,
        status ENUM('draft','published','paused') NOT NULL DEFAULT 'draft',
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY gov_exam_mock_category_index (category_id, subcategory_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    gov_exam_ensure_mock_column('thumbnail_path', 'ALTER TABLE gov_exam_mock_tests ADD COLUMN thumbnail_path VARCHAR(255) NULL AFTER description');

    db()->query("CREATE TABLE IF NOT EXISTS gov_exam_mock_questions (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT,
        mock_test_id INT UNSIGNED NOT NULL,
        question_en TEXT NULL,
        question_hi TEXT NULL,
        option_a_en VARCHAR(255) NULL,
        option_b_en VARCHAR(255) NULL,
        option_c_en VARCHAR(255) NULL,
        option_d_en VARCHAR(255) NULL,
        option_a_hi VARCHAR(255) NULL,
        option_b_hi VARCHAR(255) NULL,
        option_c_hi VARCHAR(255) NULL,
        option_d_hi VARCHAR(255) NULL,
        correct_answer ENUM('A','B','C','D') NOT NULL DEFAULT 'A',
        marks DECIMAL(8,2) NOT NULL DEFAULT 1,
        negative_marks DECIMAL(8,2) NOT NULL DEFAULT 0,
        explanation_en TEXT NULL,
        explanation_hi TEXT NULL,
        sort_order INT UNSIGNED NOT NULL DEFAULT 1,
        status ENUM('active','inactive') NOT NULL DEFAULT 'active',
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY gov_exam_mock_questions_mock_index (mock_test_id, sort_order)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    gov_exam_ensure_question_column('option_e_en', 'ALTER TABLE gov_exam_mock_questions ADD COLUMN option_e_en VARCHAR(255) NULL AFTER option_d_en');
    gov_exam_ensure_question_column('option_e_hi', 'ALTER TABLE gov_exam_mock_questions ADD COLUMN option_e_hi VARCHAR(255) NULL AFTER option_d_hi');
    db()->query("ALTER TABLE gov_exam_mock_questions MODIFY correct_answer ENUM('A','B','C','D','E') NOT NULL DEFAULT 'A'");
}

function gov_exam_ensure_question_column(string $column, string $sql): void
{
    $columnEscaped = db()->real_escape_string($column);
    $result = db()->query("SHOW COLUMNS FROM gov_exam_mock_questions LIKE '{$columnEscaped}'");
    if (!$result || $result->num_rows === 0) {
        db()->query($sql);
    }
}

function gov_exam_ensure_mock_column(string $column, string $sql): void
{
    $columnEscaped = db()->real_escape_string($column);
    $result = db()->query("SHOW COLUMNS FROM gov_exam_mock_tests LIKE '{$columnEscaped}'");
    if (!$result || $result->num_rows === 0) {
        db()->query($sql);
    }
}

function gov_exam_seed_category(?int $parentId, string $name, string $slug, string $description, int $sortOrder): int
{
    $existing = db()->prepare('SELECT id FROM gov_exam_categories WHERE slug = ? LIMIT 1');
    $existing->bind_param('s', $slug);
    $existing->execute();
    $row = $existing->get_result()->fetch_assoc();
    $existing->close();

    if ($row) {
        $id = (int) $row['id'];
        if ($parentId === null) {
            $update = db()->prepare("UPDATE gov_exam_categories SET parent_id = NULL, name = ?, description = ?, status = 'active', sort_order = ? WHERE id = ?");
            $update->bind_param('ssii', $name, $description, $sortOrder, $id);
        } else {
            $update = db()->prepare("UPDATE gov_exam_categories SET parent_id = ?, name = ?, description = ?, status = 'active', sort_order = ? WHERE id = ?");
            $update->bind_param('issii', $parentId, $name, $description, $sortOrder, $id);
        }
        $update->execute();
        $update->close();
        return $id;
    }

    if ($parentId === null) {
        $insert = db()->prepare("INSERT INTO gov_exam_categories (parent_id, name, slug, description, status, sort_order) VALUES (NULL, ?, ?, ?, 'active', ?)");
        $insert->bind_param('sssi', $name, $slug, $description, $sortOrder);
    } else {
        $insert = db()->prepare("INSERT INTO gov_exam_categories (parent_id, name, slug, description, status, sort_order) VALUES (?, ?, ?, ?, 'active', ?)");
        $insert->bind_param('isssi', $parentId, $name, $slug, $description, $sortOrder);
    }
    $insert->execute();
    $id = (int) db()->insert_id;
    $insert->close();
    return $id;
}

function gov_exam_seed_india_categories(): void
{
    gov_exam_ensure_tables();
    $catalog = [
        'SSC Exams' => ['icon' => 'SSC recruitment exams and central government staff selection.', 'items' => ['SSC CGL', 'SSC CHSL', 'SSC MTS', 'SSC GD Constable', 'SSC CPO', 'SSC Stenographer', 'SSC Selection Post', 'SSC JE', 'SSC JE Civil', 'SSC JE Electrical', 'SSC JE Mechanical', 'SSC Head Constable', 'SSC JHT', 'SSC Scientific Assistant', 'SSC Havaldar']],
        'Banking Exams' => ['icon' => 'Bank PO, clerk, assistant and specialist officer preparation.', 'items' => ['IBPS PO', 'IBPS Clerk', 'IBPS RRB PO', 'IBPS RRB Clerk', 'SBI PO', 'SBI Clerk', 'SBI SO', 'RBI Grade B', 'RBI Assistant', 'NABARD Grade A', 'NABARD Development Assistant', 'SEBI Grade A', 'SIDBI Grade A', 'LIC AAO', 'NIACL AO']],
        'Teaching Exams' => ['icon' => 'Teacher eligibility, school recruitment and higher education exams.', 'items' => ['CTET', 'UPTET', 'REET', 'HTET PRT', 'HTET TGT', 'HTET PGT', 'SUPER TET', 'KVS PRT', 'KVS TGT', 'KVS PGT', 'DSSSB PRT', 'DSSSB TGT', 'DSSSB PGT', 'UGC NET', 'CSIR NET', 'Bihar Primary Teacher', 'Bihar Upper Primary Teacher', 'Bihar Secondary Teacher', 'Bihar Senior Secondary Teacher', 'UP LT Grade Teacher', 'UP TGT', 'UP PGT', 'EMRS TGT', 'EMRS PGT', 'NVS TGT', 'NVS PGT', 'Punjab ETT Teacher', 'MP TET Varg 1', 'MP TET Varg 2', 'MP TET Varg 3']],
        'Civil Services Exam' => ['icon' => 'UPSC and state public service commission exams.', 'items' => ['UPSC CSE', 'UPSC Prelims', 'UPSC Mains', 'UPSC CSAT', 'UPSC CAPF AC', 'UPSC CDS', 'UPSC NDA', 'UPSC EPFO', 'UPSC CSE Optional', 'BPSC', 'UPPSC', 'MPPSC', 'RPSC', 'MPSC', 'GPSC', 'HPSC', 'JPSC', 'CGPSC', 'UKPSC', 'OPSC', 'APPSC', 'TSPSC', 'WBPSC', 'TNPSC Group 1']],
        'Railways Exams' => ['icon' => 'Railway recruitment board and metro rail exams.', 'items' => ['RRB NTPC', 'RRB Group D', 'RRB ALP', 'RRB Technician', 'RRB JE', 'RRB Paramedical', 'RPF Constable', 'RPF SI', 'RRB Ministerial', 'DMRC CRA', 'DMRC JE']],
        'Engineering Recruitment Exams' => ['icon' => 'Engineering services, PSU and technical recruitment exams.', 'items' => ['GATE', 'ESE IES', 'ISRO Scientist Engineer', 'BARC OCES', 'DRDO CEPTAM', 'DRDO Scientist B', 'BHEL Engineer Trainee', 'NTPC Engineer', 'ONGC Graduate Trainee', 'SAIL MT', 'HPCL Engineer', 'IOCL Engineer', 'AAI JE ATC', 'AAI Junior Executive', 'RSMSSB JE', 'UPPCL JE', 'UPPCL AE', 'BPSC AE', 'SSC JE CE', 'SSC JE EE', 'SSC JE ME']],
        'Defence Exams' => ['icon' => 'Army, Navy, Air Force and paramilitary recruitment preparation.', 'items' => ['NDA', 'CDS', 'AFCAT', 'INET', 'Indian Navy SSR', 'Indian Navy AA', 'Indian Navy MR', 'Indian Army Agniveer', 'Air Force Agniveer Vayu', 'CAPF AC', 'BSF Head Constable', 'CRPF Constable', 'CISF Constable', 'SSB Constable', 'Assam Rifles', 'Coast Guard Navik GD', 'Coast Guard Yantrik']],
        'State Govt. Exams' => ['icon' => 'Popular state-level staff, assistant and administrative recruitment exams.', 'items' => ['UPSSSC PET', 'UPSSSC Lekhpal', 'UP Police Constable', 'UP Police SI', 'Bihar Police Constable', 'Bihar Police SI', 'BSSC CGL', 'Bihar Sachivalaya', 'MP Patwari', 'MP Police Constable', 'Rajasthan Patwari', 'RSMSSB CET', 'RSMSSB LDC', 'RSMSSB VDO', 'Rajasthan Police Constable', 'Haryana CET', 'HSSC Clerk', 'Punjab Patwari', 'Gujarat Talati', 'Gujarat Police Constable', 'Maharashtra Talathi', 'MPSC Group C', 'WB Police Constable', 'Kolkata Police Constable', 'TNPSC Group 2', 'TNPSC Group 4', 'Kerala PSC LDC']],
        'Police Exams' => ['icon' => 'Police constable, SI, intelligence and security assistant exams.', 'items' => ['Delhi Police Constable', 'Delhi Police Head Constable', 'Delhi Police Driver', 'Delhi Police MTS', 'Delhi Police SI', 'IB ACIO', 'IB Security Assistant', 'IB MTS', 'CRPF ASI', 'CISF Head Constable', 'BSF Tradesman', 'UP Police Constable', 'UP Police SI', 'Bihar Police Constable', 'Rajasthan Police Constable', 'MP Police Constable']],
        'Insurance Exams' => ['icon' => 'Insurance sector assistant, officer and administrative exams.', 'items' => ['LIC AAO', 'LIC ADO', 'LIC Assistant', 'NIACL AO', 'NIACL Assistant', 'NICL AO', 'OICL AO', 'UIIC AO', 'GIC Assistant Manager', 'IRDAI Assistant Manager']],
        'Nursing Exams' => ['icon' => 'Nursing officer and medical recruitment exams.', 'items' => ['AIIMS NORCET', 'DSSSB Nursing Officer', 'ESIC Nursing Officer', 'RRB Staff Nurse', 'Bihar Staff Nurse', 'UPPSC Staff Nurse', 'Rajasthan Staff Nurse', 'NHM CHO', 'JIPMER Nursing Officer', 'PGIMER Nursing Officer']],
        'Judiciary Exams' => ['icon' => 'Civil judge, judicial services and court recruitment exams.', 'items' => ['UP Judiciary', 'Bihar Judiciary', 'MP Judiciary', 'Rajasthan Judiciary', 'Delhi Judiciary', 'Haryana Judiciary', 'Punjab Judiciary', 'Gujarat Judiciary', 'Jharkhand Judiciary', 'Chhattisgarh Judiciary', 'Supreme Court Junior Court Assistant', 'Allahabad High Court RO ARO', 'Delhi High Court Assistant']],
        'Regulatory Body Exams' => ['icon' => 'Regulatory, research and national institution exams.', 'items' => ['SEBI Grade A', 'RBI Grade B', 'NABARD Grade A', 'SIDBI Grade A', 'EPFO SSA', 'EPFO APFC', 'NTA Delhi University', 'ICMR Assistant', 'CSIR ASO', 'CSIR SO', 'NBE', 'CCR AS LDC']],
        'Other Govt. Exams' => ['icon' => 'Other high-demand government and public sector exams.', 'items' => ['CUET PG Government Jobs', 'FSSAI Assistant', 'FSSAI Technical Officer', 'ESIC UDC', 'ESIC MTS', 'EPFO SSA', 'India Post GDS', 'India Post Postman', 'DDA ASO', 'DDA Patwari', 'Delhi Forest Guard', 'Supreme Court Assistant', 'NTA Non Teaching', 'IGNOU Junior Assistant', 'ISRO Assistant']],
    ];

    $parentOrder = 10;
    foreach ($catalog as $parentName => $group) {
        $parentSlug = gov_slug($parentName);
        $parentId = gov_exam_seed_category(null, $parentName, $parentSlug, (string) $group['icon'], $parentOrder);
        $childOrder = 10;
        foreach ($group['items'] as $childName) {
            $childSlug = $parentSlug . '-' . gov_slug($childName);
            gov_exam_seed_category($parentId, $childName, $childSlug, $childName . ' preparation, PDFs, live classes and mock tests.', $childOrder);
            $childOrder += 10;
        }
        $parentOrder += 10;
    }
}

function gov_exam_categories(bool $activeOnly = false): array
{
    $where = $activeOnly ? "WHERE c.status = 'active'" : '';
    $result = db()->query("
        SELECT c.*, p.name AS parent_name
        FROM gov_exam_categories c
        LEFT JOIN gov_exam_categories p ON p.id = c.parent_id
        {$where}
        ORDER BY COALESCE(p.sort_order, c.sort_order), p.name, c.parent_id IS NOT NULL, c.sort_order, c.name
    ");
    return $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
}

function gov_exam_parent_options(?int $selected = null, int $excludeId = 0): string
{
    $html = '<option value="0">Main category</option>';
    $rows = db()->query("SELECT id, name FROM gov_exam_categories WHERE parent_id IS NULL AND status = 'active' ORDER BY sort_order, name")->fetch_all(MYSQLI_ASSOC);
    foreach ($rows as $row) {
        if ((int) $row['id'] === $excludeId) {
            continue;
        }
        $isSelected = (int) $row['id'] === (int) $selected ? ' selected' : '';
        $html .= '<option value="' . (int) $row['id'] . '"' . $isSelected . '>' . h($row['name']) . '</option>';
    }
    return $html;
}

function gov_exam_category_options(?int $selected = null): string
{
    $html = '<option value="">Select category</option>';
    foreach (gov_exam_categories(true) as $row) {
        $label = ($row['parent_name'] ? $row['parent_name'] . ' / ' : '') . $row['name'];
        $isSelected = (int) $row['id'] === (int) $selected ? ' selected' : '';
        $html .= '<option value="' . (int) $row['id'] . '"' . $isSelected . '>' . h($label) . '</option>';
    }
    return $html;
}

function gov_exam_mock_options(?int $selected = null): string
{
    $html = '<option value="">Select mock test</option>';
    $result = db()->query("
        SELECT m.id, m.title, c.name AS category_name, s.name AS subcategory_name
        FROM gov_exam_mock_tests m
        LEFT JOIN gov_exam_categories c ON c.id = m.category_id
        LEFT JOIN gov_exam_categories s ON s.id = m.subcategory_id
        ORDER BY m.id DESC
    ");
    foreach (($result ? $result->fetch_all(MYSQLI_ASSOC) : []) as $row) {
        $prefix = trim(($row['category_name'] ?? '') . (($row['subcategory_name'] ?? '') ? ' / ' . $row['subcategory_name'] : ''));
        $label = ($prefix !== '' ? $prefix . ' - ' : '') . $row['title'];
        $isSelected = (int) $row['id'] === (int) $selected ? ' selected' : '';
        $html .= '<option value="' . (int) $row['id'] . '"' . $isSelected . '>' . h($label) . '</option>';
    }
    return $html;
}

function gov_exam_flash(): array
{
    $message = (string) ($_SESSION['gov_exam_message'] ?? '');
    $error = (string) ($_SESSION['gov_exam_error'] ?? '');
    unset($_SESSION['gov_exam_message'], $_SESSION['gov_exam_error']);
    return [$message, $error];
}

function gov_exam_status(string $status): string
{
    $class = in_array($status, ['active', 'published', 'live', 'scheduled'], true) ? 'ready' : 'empty';
    return '<span class="status-pill ' . $class . '">' . h(ucfirst($status)) . '</span>';
}
