<?php
declare(strict_types=1);

function sadmin_staff_exec(string $sql): void
{
    try {
        db()->query($sql);
    } catch (Throwable $e) {
        // Keep admin pages usable while older MySQL variants skip optional DDL.
    }
}

function sadmin_staff_ensure_tables(): void
{
    sadmin_staff_exec("CREATE TABLE IF NOT EXISTS sadmin_staff_profiles (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT,
        user_id INT UNSIGNED NOT NULL,
        department ENUM('management','support','calling','sales','billing','academic','operations') NOT NULL DEFAULT 'operations',
        designation VARCHAR(120) NOT NULL DEFAULT '',
        manager_user_id INT UNSIGNED NULL,
        joining_date DATE NULL,
        employee_code VARCHAR(40) NULL,
        can_manage_school TINYINT(1) NOT NULL DEFAULT 0,
        can_manage_degree TINYINT(1) NOT NULL DEFAULT 0,
        can_manage_institute TINYINT(1) NOT NULL DEFAULT 0,
        notes TEXT NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY sadmin_staff_profiles_user_unique (user_id),
        KEY sadmin_staff_profiles_department_index (department),
        KEY sadmin_staff_profiles_manager_index (manager_user_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    sadmin_staff_exec("CREATE TABLE IF NOT EXISTS sadmin_staff_institute_assignments (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT,
        staff_user_id INT UNSIGNED NOT NULL,
        institution_account_id INT UNSIGNED NOT NULL,
        assignment_role ENUM('support_manager','implementation','calling','sales','billing','academic','account_manager') NOT NULL DEFAULT 'support_manager',
        status ENUM('active','paused','closed') NOT NULL DEFAULT 'active',
        assigned_by INT UNSIGNED NULL,
        assigned_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        notes TEXT NULL,
        PRIMARY KEY (id),
        UNIQUE KEY staff_institute_role_unique (staff_user_id, institution_account_id, assignment_role),
        KEY staff_assignment_staff_index (staff_user_id),
        KEY staff_assignment_institute_index (institution_account_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    sadmin_staff_exec("CREATE TABLE IF NOT EXISTS sadmin_sales_leads (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT,
        owner_user_id INT UNSIGNED NULL,
        institution_account_id INT UNSIGNED NULL,
        lead_name VARCHAR(160) NOT NULL,
        contact_person VARCHAR(120) NULL,
        phone VARCHAR(30) NULL,
        email VARCHAR(160) NULL,
        institution_type VARCHAR(60) NULL,
        source VARCHAR(80) NULL,
        expected_amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
        paid_amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
        status ENUM('new','follow_up','demo_done','proposal','paid','unpaid','lost') NOT NULL DEFAULT 'new',
        next_followup DATE NULL,
        notes TEXT NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY sadmin_sales_leads_owner_index (owner_user_id),
        KEY sadmin_sales_leads_status_index (status),
        KEY sadmin_sales_leads_institution_index (institution_account_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    sadmin_staff_exec("CREATE TABLE IF NOT EXISTS sadmin_sales_lead_activities (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT,
        lead_id INT UNSIGNED NOT NULL,
        user_id INT UNSIGNED NULL,
        old_status VARCHAR(40) NULL,
        new_status VARCHAR(40) NOT NULL,
        next_followup DATE NULL,
        note TEXT NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY lead_activity_lead_index (lead_id),
        KEY lead_activity_user_index (user_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $roles = [
        ['Operations Manager', 'operations-manager', 'management', 'Assigns institute accounts to staff and tracks fulfilment.', ['staff.manage', 'support.manage', 'assignments.manage', 'leads.manage', 'reports.view']],
        ['ERP Support Manager', 'erp-support-manager', 'support', 'Owns school, college and institute ERP support.', ['support.manage', 'assignments.manage', 'students.view', 'instructors.view', 'reports.view']],
        ['Calling Executive', 'calling-executive', 'support', 'Lead calling, demos and follow-up operations.', ['calling.manage', 'leads.manage', 'reports.view']],
        ['Sales Executive', 'sales-executive', 'sales', 'Lead ownership, conversion and revenue tracking.', ['calling.manage', 'leads.manage', 'invoices.manage', 'reports.view']],
        ['Billing Executive', 'billing-executive', 'finance', 'Invoices, payment references and plan renewals.', ['invoices.manage', 'fees.manage', 'reports.view']],
        ['Academic Coordinator', 'academic-coordinator', 'academic', 'Instructor coordination and academic support.', ['instructors.view', 'courses.view', 'reports.view']],
    ];

    foreach ($roles as [$name, $slug, $group, $description, $permissions]) {
        $stmt = db()->prepare("INSERT INTO roles (parent_id, name, slug, role_group, description, is_system)
            VALUES (NULL, ?, ?, ?, ?, 1)
            ON DUPLICATE KEY UPDATE name = VALUES(name), role_group = VALUES(role_group), description = VALUES(description)");
        $stmt->bind_param('ssss', $name, $slug, $group, $description);
        $stmt->execute();

        $roleId = (int) db()->query("SELECT id FROM roles WHERE slug = '" . db()->real_escape_string($slug) . "' LIMIT 1")->fetch_assoc()['id'];
        $insert = db()->prepare('INSERT IGNORE INTO role_permissions (role_id, permission_key) VALUES (?, ?)');
        foreach ($permissions as $permission) {
            $insert->bind_param('is', $roleId, $permission);
            $insert->execute();
        }
    }
}

function sadmin_staff_roles(): array
{
    sadmin_staff_ensure_tables();
    $result = db()->query("SELECT id, name, slug, role_group FROM roles WHERE slug NOT IN ('student') ORDER BY FIELD(slug, 'superadmin') DESC, role_group, name");
    return $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
}

function sadmin_staff_rows(): array
{
    sadmin_staff_ensure_tables();
    $result = db()->query("SELECT u.id, u.full_name, u.username, u.email, u.phone, u.status, u.created_at,
            r.name AS role_name, r.slug AS role_slug,
            p.department, p.designation, p.employee_code, p.manager_user_id,
            manager.full_name AS manager_name,
            COUNT(DISTINCT a.id) AS assigned_institutes,
            COUNT(DISTINCT l.id) AS total_leads,
            SUM(CASE WHEN l.status = 'paid' THEN 1 ELSE 0 END) AS paid_leads,
            COALESCE(SUM(l.paid_amount), 0) AS paid_amount
        FROM users u
        INNER JOIN roles r ON r.id = u.role_id
        LEFT JOIN sadmin_staff_profiles p ON p.user_id = u.id
        LEFT JOIN users manager ON manager.id = p.manager_user_id
        LEFT JOIN sadmin_staff_institute_assignments a ON a.staff_user_id = u.id AND a.status = 'active'
        LEFT JOIN sadmin_sales_leads l ON l.owner_user_id = u.id
        WHERE r.slug NOT IN ('student', 'instructor')
        GROUP BY u.id, u.full_name, u.username, u.email, u.phone, u.status, u.created_at, r.name, r.slug,
            p.department, p.designation, p.employee_code, p.manager_user_id, manager.full_name
        ORDER BY u.id DESC");
    return $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
}

function sadmin_staff_create_or_update(array $data): void
{
    sadmin_staff_ensure_tables();
    ensure_users_phone_column();
    $userId = (int) ($data['user_id'] ?? 0);
    $roleId = (int) ($data['role_id'] ?? 0);
    $fullName = substr(trim((string) ($data['full_name'] ?? '')), 0, 120);
    $email = substr(trim((string) ($data['email'] ?? '')), 0, 160);
    $phone = substr(trim((string) ($data['phone'] ?? '')), 0, 30);
    $username = substr(trim((string) ($data['username'] ?? $email)), 0, 80);
    $status = in_array($data['status'] ?? '', ['pending', 'active', 'inactive', 'blocked'], true) ? (string) $data['status'] : 'active';
    $department = in_array($data['department'] ?? '', ['management','support','calling','sales','billing','academic','operations'], true) ? (string) $data['department'] : 'operations';
    $designation = substr(trim((string) ($data['designation'] ?? '')), 0, 120);
    $employeeCode = substr(trim((string) ($data['employee_code'] ?? '')), 0, 40);
    $managerId = (int) ($data['manager_user_id'] ?? 0);
    $managerId = $managerId > 0 ? $managerId : null;
    $notes = substr(trim((string) ($data['notes'] ?? '')), 0, 1000);
    $school = !empty($data['can_manage_school']) ? 1 : 0;
    $degree = !empty($data['can_manage_degree']) ? 1 : 0;
    $institute = !empty($data['can_manage_institute']) ? 1 : 0;

    if ($roleId <= 0 || $fullName === '' || $email === '') {
        throw new RuntimeException('Name, email and role are required.');
    }

    if ($userId > 0) {
        $stmt = db()->prepare('UPDATE users SET role_id = ?, full_name = ?, username = ?, email = ?, phone = ?, status = ? WHERE id = ?');
        $stmt->bind_param('isssssi', $roleId, $fullName, $username, $email, $phone, $status, $userId);
        $stmt->execute();
    } else {
        $password = trim((string) ($data['password'] ?? ''));
        if ($password === '') {
            $password = 'Staff@12345';
        }
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = db()->prepare('INSERT INTO users (role_id, full_name, username, email, phone, password_hash, status) VALUES (?, ?, ?, ?, ?, ?, ?)');
        $stmt->bind_param('issssss', $roleId, $fullName, $username, $email, $phone, $hash, $status);
        $stmt->execute();
        $userId = (int) db()->insert_id;
    }

    $stmt = db()->prepare("INSERT INTO sadmin_staff_profiles
        (user_id, department, designation, manager_user_id, employee_code, can_manage_school, can_manage_degree, can_manage_institute, notes)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE department = VALUES(department), designation = VALUES(designation), manager_user_id = VALUES(manager_user_id),
        employee_code = VALUES(employee_code), can_manage_school = VALUES(can_manage_school), can_manage_degree = VALUES(can_manage_degree),
        can_manage_institute = VALUES(can_manage_institute), notes = VALUES(notes)");
    $stmt->bind_param('issisiiis', $userId, $department, $designation, $managerId, $employeeCode, $school, $degree, $institute, $notes);
    $stmt->execute();
}

function sadmin_staff_assignment_rows(): array
{
    sadmin_staff_ensure_tables();
    $result = db()->query("SELECT a.*, u.full_name AS staff_name, r.name AS role_name, i.institution_name, i.institution_type, i.contact_name, i.mobile, i.email,
            t.erp_base_path, t.setup_status, t.erp_status, t.custom_domain, t.custom_domain_status
        FROM sadmin_staff_institute_assignments a
        INNER JOIN users u ON u.id = a.staff_user_id
        INNER JOIN roles r ON r.id = u.role_id
        INNER JOIN institution_accounts i ON i.id = a.institution_account_id
        LEFT JOIN institution_erp_tenants t ON t.institution_account_id = i.id
        ORDER BY a.id DESC");
    return $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
}

function sadmin_sales_lead_rows(): array
{
    sadmin_staff_ensure_tables();
    $result = db()->query("SELECT l.*, u.full_name AS owner_name, i.institution_name AS linked_institute
        FROM sadmin_sales_leads l
        LEFT JOIN users u ON u.id = l.owner_user_id
        LEFT JOIN institution_accounts i ON i.id = l.institution_account_id
        ORDER BY l.id DESC");
    return $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
}

function sadmin_staff_update_lead(int $leadId, int $userId, string $status, ?string $nextFollowup, string $note, bool $ownerOnly = true): void
{
    sadmin_staff_ensure_tables();
    $allowed = ['new','follow_up','demo_done','proposal','paid','unpaid','lost'];
    if (!in_array($status, $allowed, true)) {
        throw new RuntimeException('Invalid lead status.');
    }

    $sql = 'SELECT id, owner_user_id, status FROM sadmin_sales_leads WHERE id = ?';
    if ($ownerOnly) {
        $sql .= ' AND owner_user_id = ?';
    }
    $sql .= ' LIMIT 1';
    $stmt = db()->prepare($sql);
    if ($ownerOnly) {
        $stmt->bind_param('ii', $leadId, $userId);
    } else {
        $stmt->bind_param('i', $leadId);
    }
    $stmt->execute();
    $lead = $stmt->get_result()->fetch_assoc();
    if (!$lead) {
        throw new RuntimeException('Lead not found or not assigned to you.');
    }

    $followup = $nextFollowup ?: null;
    $stmt = db()->prepare('UPDATE sadmin_sales_leads SET status = ?, next_followup = ?, notes = CONCAT(COALESCE(notes, ""), IF(COALESCE(notes, "") = "", "", "\n"), ?), updated_at = NOW() WHERE id = ?');
    $stmt->bind_param('sssi', $status, $followup, $note, $leadId);
    $stmt->execute();

    $oldStatus = (string) ($lead['status'] ?? '');
    $stmt = db()->prepare('INSERT INTO sadmin_sales_lead_activities (lead_id, user_id, old_status, new_status, next_followup, note) VALUES (?, ?, ?, ?, ?, ?)');
    $stmt->bind_param('iissss', $leadId, $userId, $oldStatus, $status, $followup, $note);
    $stmt->execute();
}

function sadmin_staff_lead_activity_rows(int $userId, bool $ownerOnly = true): array
{
    sadmin_staff_ensure_tables();
    $sql = "SELECT a.*, l.lead_name, u.full_name AS user_name
        FROM sadmin_sales_lead_activities a
        INNER JOIN sadmin_sales_leads l ON l.id = a.lead_id
        LEFT JOIN users u ON u.id = a.user_id";
    if ($ownerOnly) {
        $sql .= ' WHERE l.owner_user_id = ' . (int) $userId;
    }
    $sql .= ' ORDER BY a.id DESC LIMIT 20';
    $result = db()->query($sql);
    return $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
}
