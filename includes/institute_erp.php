<?php

require_once __DIR__ . '/institution_module.php';

function institute_erp_ensure_tables(): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;

    institution_ensure_tables();

    institution_db_exec("CREATE TABLE IF NOT EXISTS institution_erp_plans (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT,
        plan_name VARCHAR(100) NOT NULL,
        plan_slug VARCHAR(100) NOT NULL,
        monthly_price DECIMAL(10,2) NOT NULL DEFAULT 0,
        yearly_price DECIMAL(10,2) NOT NULL DEFAULT 0,
        validity_days INT UNSIGNED NOT NULL DEFAULT 365,
        max_students INT UNSIGNED NULL,
        max_staff INT UNSIGNED NULL,
        max_storage_mb INT UNSIGNED NULL,
        features_json JSON NULL,
        is_active TINYINT(1) NOT NULL DEFAULT 1,
        sort_order INT UNSIGNED NOT NULL DEFAULT 0,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY institution_erp_plans_slug_unique (plan_slug)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    institution_db_exec("CREATE TABLE IF NOT EXISTS institution_erp_tenants (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        institution_account_id BIGINT UNSIGNED NOT NULL,
        request_id BIGINT UNSIGNED NULL,
        tenant_code VARCHAR(80) NOT NULL,
        erp_base_path VARCHAR(180) NOT NULL,
        erp_template_type ENUM('school','degree_college','coaching') NOT NULL DEFAULT 'school',
        custom_domain VARCHAR(190) NULL,
        custom_domain_status ENUM('none','requested','dns_pending','mapped','rejected') NOT NULL DEFAULT 'none',
        erp_db_name VARCHAR(120) NULL,
        erp_status ENUM('pending_setup','active','read_only','blocked') NOT NULL DEFAULT 'pending_setup',
        setup_status ENUM('not_started','queued','installed','failed') NOT NULL DEFAULT 'not_started',
        setup_note VARCHAR(255) NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY institution_erp_tenants_account_unique (institution_account_id),
        UNIQUE KEY institution_erp_tenants_code_unique (tenant_code),
        KEY institution_erp_tenants_status_index (erp_status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    institution_db_exec("ALTER TABLE institution_erp_tenants ADD COLUMN IF NOT EXISTS erp_template_type ENUM('school','degree_college','coaching') NOT NULL DEFAULT 'school' AFTER erp_base_path");
    institution_db_exec("ALTER TABLE institution_erp_tenants ADD COLUMN IF NOT EXISTS custom_domain VARCHAR(190) NULL AFTER erp_base_path");
    institution_db_exec("ALTER TABLE institution_erp_tenants ADD COLUMN IF NOT EXISTS custom_domain_status ENUM('none','requested','dns_pending','mapped','rejected') NOT NULL DEFAULT 'none' AFTER custom_domain");

    institution_db_exec("CREATE TABLE IF NOT EXISTS institution_erp_subscriptions (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        institution_account_id BIGINT UNSIGNED NOT NULL,
        tenant_id BIGINT UNSIGNED NULL,
        plan_id INT UNSIGNED NOT NULL,
        status ENUM('trial','active','expired','cancelled','suspended') NOT NULL DEFAULT 'trial',
        starts_at DATE NOT NULL,
        expires_at DATE NOT NULL,
        amount DECIMAL(10,2) NOT NULL DEFAULT 0,
        payment_status ENUM('trial','pending','paid','failed','refunded') NOT NULL DEFAULT 'trial',
        payment_reference VARCHAR(160) NULL,
        notes VARCHAR(255) NULL,
        created_by INT UNSIGNED NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY institution_erp_subscriptions_account_index (institution_account_id),
        KEY institution_erp_subscriptions_status_index (status, expires_at),
        KEY institution_erp_subscriptions_plan_index (plan_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    institution_db_exec("CREATE TABLE IF NOT EXISTS institution_erp_renewal_requests (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        institution_account_id BIGINT UNSIGNED NOT NULL,
        tenant_id BIGINT UNSIGNED NULL,
        plan_id INT UNSIGNED NULL,
        billing_cycle ENUM('monthly','yearly','custom') NOT NULL DEFAULT 'yearly',
        status ENUM('pending','approved','rejected','cancelled') NOT NULL DEFAULT 'pending',
        requested_days INT UNSIGNED NULL,
        amount DECIMAL(10,2) NULL,
        payment_reference VARCHAR(160) NULL,
        admin_note VARCHAR(255) NULL,
        requested_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        reviewed_at DATETIME NULL,
        reviewed_by INT UNSIGNED NULL,
        PRIMARY KEY (id),
        KEY institution_erp_renewal_requests_account_index (institution_account_id),
        KEY institution_erp_renewal_requests_status_index (status),
        KEY institution_erp_renewal_requests_plan_index (plan_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    institution_db_exec("CREATE TABLE IF NOT EXISTS institution_erp_backups (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        tenant_id BIGINT UNSIGNED NOT NULL,
        institution_account_id BIGINT UNSIGNED NOT NULL,
        backup_path VARCHAR(255) NOT NULL,
        file_size BIGINT UNSIGNED NOT NULL DEFAULT 0,
        status ENUM('created','failed','restored') NOT NULL DEFAULT 'created',
        note VARCHAR(255) NULL,
        created_by INT UNSIGNED NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        restored_by INT UNSIGNED NULL,
        restored_at DATETIME NULL,
        PRIMARY KEY (id),
        KEY institution_erp_backups_tenant_index (tenant_id),
        KEY institution_erp_backups_account_index (institution_account_id),
        KEY institution_erp_backups_status_index (status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    institution_db_exec("CREATE TABLE IF NOT EXISTS institution_erp_domain_requests (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        tenant_id BIGINT UNSIGNED NOT NULL,
        institution_account_id BIGINT UNSIGNED NOT NULL,
        requested_domain VARCHAR(190) NOT NULL,
        normalized_domain VARCHAR(190) NOT NULL,
        status ENUM('pending','dns_pending','mapped','rejected','cancelled') NOT NULL DEFAULT 'pending',
        dns_target VARCHAR(190) NULL,
        server_docroot VARCHAR(255) NULL,
        ssl_status ENUM('pending','issued','not_required','failed') NOT NULL DEFAULT 'pending',
        admin_note TEXT NULL,
        requested_by INT UNSIGNED NULL,
        reviewed_by INT UNSIGNED NULL,
        requested_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        reviewed_at DATETIME NULL,
        updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY institution_erp_domain_requests_domain_unique (normalized_domain),
        KEY institution_erp_domain_requests_tenant_index (tenant_id),
        KEY institution_erp_domain_requests_account_index (institution_account_id),
        KEY institution_erp_domain_requests_status_index (status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    institution_db_exec("CREATE TABLE IF NOT EXISTS institution_erp_support_tickets (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        institution_account_id BIGINT UNSIGNED NOT NULL,
        tenant_id BIGINT UNSIGNED NULL,
        ticket_no VARCHAR(40) NOT NULL,
        category ENUM('technical','billing','erp_setup','domain','training','other') NOT NULL DEFAULT 'technical',
        priority ENUM('low','normal','high','urgent') NOT NULL DEFAULT 'normal',
        subject VARCHAR(190) NOT NULL,
        message TEXT NULL,
        status ENUM('open','in_progress','resolved','closed') NOT NULL DEFAULT 'open',
        admin_note TEXT NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY institution_erp_support_tickets_no_unique (ticket_no),
        KEY institution_erp_support_tickets_account_index (institution_account_id),
        KEY institution_erp_support_tickets_status_index (status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    institution_db_exec("CREATE TABLE IF NOT EXISTS institution_erp_invoices (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        institution_account_id BIGINT UNSIGNED NOT NULL,
        tenant_id BIGINT UNSIGNED NULL,
        renewal_request_id BIGINT UNSIGNED NULL,
        plan_id INT UNSIGNED NULL,
        invoice_no VARCHAR(80) NOT NULL,
        billing_cycle ENUM('monthly','yearly','custom') NOT NULL DEFAULT 'yearly',
        subtotal DECIMAL(10,2) NOT NULL DEFAULT 0,
        tax_amount DECIMAL(10,2) NOT NULL DEFAULT 0,
        total_amount DECIMAL(10,2) NOT NULL DEFAULT 0,
        currency VARCHAR(10) NOT NULL DEFAULT 'INR',
        payment_gateway VARCHAR(40) NOT NULL DEFAULT 'manual',
        payment_status ENUM('draft','pending','paid','failed','cancelled') NOT NULL DEFAULT 'pending',
        payment_reference VARCHAR(160) NULL,
        payment_note VARCHAR(255) NULL,
        invoice_status ENUM('unpaid','paid','void') NOT NULL DEFAULT 'unpaid',
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        paid_at DATETIME NULL,
        updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY institution_erp_invoices_no_unique (invoice_no),
        KEY institution_erp_invoices_account_index (institution_account_id),
        KEY institution_erp_invoices_payment_index (payment_status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    institute_erp_seed_default_plans();
    institute_erp_sync_default_plan_features();
}

function institute_erp_seed_default_plans(): void
{
    $row = db()->query('SELECT COUNT(*) AS total FROM institution_erp_plans')->fetch_assoc();
    if ((int) ($row['total'] ?? 0) > 0) {
        return;
    }

    $plans = [
        ['Starter ERP', 'starter-erp', 2499.00, 24999.00, 365, 500, 50, 2048, ['student', 'admission', 'fees', 'attendance', 'exams', 'homework', 'communicate', 'reports'], 1],
        ['Growth ERP', 'growth-erp', 4999.00, 49999.00, 365, 2000, 150, 10240, ['student', 'admission', 'fees', 'attendance', 'exams', 'online_exam', 'academics', 'lesson_plan', 'homework', 'communicate', 'download_center', 'transport', 'hostel', 'library', 'front_office', 'human_resource', 'income', 'expenses', 'reports'], 2],
        ['Enterprise ERP', 'enterprise-erp', 9999.00, 99999.00, 365, null, null, null, ['all_modules', 'student', 'admission', 'fees', 'attendance', 'exams', 'online_exam', 'academics', 'lesson_plan', 'homework', 'communicate', 'download_center', 'library', 'transport', 'hostel', 'front_office', 'human_resource', 'inventory', 'income', 'expenses', 'reports', 'certificate', 'alumni', 'custom_branding', 'priority_support'], 3],
    ];
    $stmt = db()->prepare("INSERT INTO institution_erp_plans
        (plan_name, plan_slug, monthly_price, yearly_price, validity_days, max_students, max_staff, max_storage_mb, features_json, sort_order)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    foreach ($plans as $plan) {
        [$name, $slug, $monthly, $yearly, $validity, $students, $staff, $storage, $features, $sort] = $plan;
        $featuresJson = json_encode($features, JSON_UNESCAPED_SLASHES);
        $stmt->bind_param('ssddiiiisi', $name, $slug, $monthly, $yearly, $validity, $students, $staff, $storage, $featuresJson, $sort);
        $stmt->execute();
    }
}

function institute_erp_default_feature_sets(): array
{
    return [
        'starter-erp' => ['student', 'admission', 'fees', 'attendance', 'exams', 'homework', 'communicate', 'reports'],
        'growth-erp' => ['student', 'admission', 'fees', 'attendance', 'exams', 'online_exam', 'academics', 'lesson_plan', 'homework', 'communicate', 'download_center', 'transport', 'hostel', 'library', 'front_office', 'human_resource', 'income', 'expenses', 'reports'],
        'enterprise-erp' => ['all_modules', 'student', 'admission', 'fees', 'attendance', 'exams', 'online_exam', 'academics', 'lesson_plan', 'homework', 'communicate', 'download_center', 'library', 'transport', 'hostel', 'front_office', 'human_resource', 'inventory', 'income', 'expenses', 'reports', 'certificate', 'alumni', 'custom_branding', 'priority_support'],
    ];
}

function institute_erp_sync_default_plan_features(): void
{
    static $synced = false;
    if ($synced) {
        return;
    }
    $synced = true;

    $stmt = db()->prepare('UPDATE institution_erp_plans SET features_json = ? WHERE plan_slug = ?');
    foreach (institute_erp_default_feature_sets() as $slug => $features) {
        $featuresJson = json_encode($features, JSON_UNESCAPED_SLASHES);
        $stmt->bind_param('ss', $featuresJson, $slug);
        $stmt->execute();
    }
}

function institute_erp_slug(string $value): string
{
    $slug = strtolower(trim(preg_replace('/[^a-zA-Z0-9]+/', '-', $value), '-'));
    return substr($slug !== '' ? $slug : 'institute', 0, 48);
}

function institute_erp_template_from_institution_type(string $institutionType): string
{
    return match ($institutionType) {
        'degree_college' => 'degree_college',
        'institute' => 'coaching',
        default => 'school',
    };
}

function institute_erp_template_label(string $templateType): string
{
    return match ($templateType) {
        'degree_college' => 'Degree College ERP',
        'coaching' => 'Institute / Coaching ERP',
        default => 'School / College ERP',
    };
}

function institute_erp_default_plan_id(): int
{
    institute_erp_ensure_tables();
    $row = db()->query("SELECT id FROM institution_erp_plans WHERE is_active = 1 ORDER BY sort_order ASC, id ASC LIMIT 1")->fetch_assoc();
    return (int) ($row['id'] ?? 0);
}

function institute_erp_plan_rows(bool $activeOnly = true): array
{
    institute_erp_ensure_tables();
    $where = $activeOnly ? 'WHERE is_active = 1' : '';
    $result = db()->query("SELECT * FROM institution_erp_plans {$where} ORDER BY sort_order ASC, id ASC");
    return $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
}

function institute_erp_latest_renewal_request(int $accountId): array
{
    institute_erp_ensure_tables();
    $stmt = db()->prepare("SELECT r.*, p.plan_name
        FROM institution_erp_renewal_requests r
        LEFT JOIN institution_erp_plans p ON p.id = r.plan_id
        WHERE r.institution_account_id = ?
        ORDER BY r.id DESC
        LIMIT 1");
    $stmt->bind_param('i', $accountId);
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc() ?: [];
}

function institute_erp_create_renewal_request(int $accountId, int $planId, string $billingCycle, int $requestedDays = 0, string $note = ''): int
{
    institute_erp_ensure_tables();
    $billingCycle = in_array($billingCycle, ['monthly', 'yearly', 'custom'], true) ? $billingCycle : 'yearly';
    $stmt = db()->prepare('SELECT id FROM institution_erp_tenants WHERE institution_account_id = ? ORDER BY id DESC LIMIT 1');
    $stmt->bind_param('i', $accountId);
    $stmt->execute();
    $tenantId = (int) ($stmt->get_result()->fetch_assoc()['id'] ?? 0);

    $pending = db()->prepare("SELECT id FROM institution_erp_renewal_requests WHERE institution_account_id = ? AND status = 'pending' ORDER BY id DESC LIMIT 1");
    $pending->bind_param('i', $accountId);
    $pending->execute();
    $pendingId = (int) ($pending->get_result()->fetch_assoc()['id'] ?? 0);
    if ($pendingId > 0) {
        return $pendingId;
    }

    $requestedDays = $requestedDays > 0 ? $requestedDays : null;
    $amount = null;
    $paymentReference = null;
    $status = 'pending';
    $stmt = db()->prepare("INSERT INTO institution_erp_renewal_requests
        (institution_account_id, tenant_id, plan_id, billing_cycle, status, requested_days, amount, payment_reference, admin_note)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param('iiissidss', $accountId, $tenantId, $planId, $billingCycle, $status, $requestedDays, $amount, $paymentReference, $note);
    $stmt->execute();
    return (int) db()->insert_id;
}

function institute_erp_invoice_no(int $accountId): string
{
    return 'GR-ERP-' . date('Ymd') . '-' . str_pad((string) $accountId, 4, '0', STR_PAD_LEFT) . '-' . strtoupper(substr(bin2hex(random_bytes(3)), 0, 6));
}

function institute_erp_create_invoice(int $accountId, int $planId, string $billingCycle, int $renewalRequestId = 0): int
{
    institute_erp_ensure_tables();
    $billingCycle = in_array($billingCycle, ['monthly', 'yearly', 'custom'], true) ? $billingCycle : 'yearly';
    $stmt = db()->prepare('SELECT * FROM institution_erp_plans WHERE id = ? AND is_active = 1 LIMIT 1');
    $stmt->bind_param('i', $planId);
    $stmt->execute();
    $plan = $stmt->get_result()->fetch_assoc();
    if (!$plan) {
        throw new RuntimeException('Selected ERP plan is not available.');
    }

    $stmt = db()->prepare('SELECT id FROM institution_erp_tenants WHERE institution_account_id = ? ORDER BY id DESC LIMIT 1');
    $stmt->bind_param('i', $accountId);
    $stmt->execute();
    $tenantId = (int) ($stmt->get_result()->fetch_assoc()['id'] ?? 0);

    $subtotal = (float) ($billingCycle === 'monthly' ? $plan['monthly_price'] : $plan['yearly_price']);
    $settings = all_settings();
    $taxRate = (float) ($settings['tax_rate'] ?? 0);
    $taxAmount = round($subtotal * $taxRate / 100, 2);
    $total = round($subtotal + $taxAmount, 2);
    $currency = (string) ($settings['currency'] ?? 'INR');
    $gateway = (($settings['phonepe_enabled'] ?? '0') === '1') ? 'phonepe' : 'manual';
    $invoiceNo = institute_erp_invoice_no($accountId);
    $status = 'pending';
    $invoiceStatus = 'unpaid';
    $stmt = db()->prepare("INSERT INTO institution_erp_invoices
        (institution_account_id, tenant_id, renewal_request_id, plan_id, invoice_no, billing_cycle, subtotal, tax_amount, total_amount, currency, payment_gateway, payment_status, invoice_status)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param('iiiissdddssss', $accountId, $tenantId, $renewalRequestId, $planId, $invoiceNo, $billingCycle, $subtotal, $taxAmount, $total, $currency, $gateway, $status, $invoiceStatus);
    $stmt->execute();
    return (int) db()->insert_id;
}

function institute_erp_create_support_ticket(int $accountId, string $category, string $priority, string $subject, string $message): int
{
    institute_erp_ensure_tables();
    $category = in_array($category, ['technical', 'billing', 'erp_setup', 'domain', 'training', 'other'], true) ? $category : 'technical';
    $priority = in_array($priority, ['low', 'normal', 'high', 'urgent'], true) ? $priority : 'normal';
    $subject = trim($subject);
    $message = trim($message);
    if ($subject === '') {
        throw new RuntimeException('Please enter support ticket subject.');
    }
    $stmt = db()->prepare('SELECT id FROM institution_erp_tenants WHERE institution_account_id = ? ORDER BY id DESC LIMIT 1');
    $stmt->bind_param('i', $accountId);
    $stmt->execute();
    $tenantId = (int) ($stmt->get_result()->fetch_assoc()['id'] ?? 0);
    $ticketNo = 'GR-TKT-' . date('Ymd') . '-' . strtoupper(substr(bin2hex(random_bytes(3)), 0, 6));
    $status = 'open';
    $stmt = db()->prepare("INSERT INTO institution_erp_support_tickets
        (institution_account_id, tenant_id, ticket_no, category, priority, subject, message, status)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param('iissssss', $accountId, $tenantId, $ticketNo, $category, $priority, $subject, $message, $status);
    $stmt->execute();
    return (int) db()->insert_id;
}

function institute_erp_extend_subscription(int $accountId, int $planId, int $days, float $amount, string $paymentStatus, string $reference, int $adminId = 0, int $renewalRequestId = 0): int
{
    institute_erp_ensure_tables();
    $days = max(1, $days);
    $paymentStatus = in_array($paymentStatus, ['trial', 'pending', 'paid', 'failed', 'refunded'], true) ? $paymentStatus : 'paid';

    $stmt = db()->prepare('SELECT id FROM institution_erp_tenants WHERE institution_account_id = ? ORDER BY id DESC LIMIT 1');
    $stmt->bind_param('i', $accountId);
    $stmt->execute();
    $tenantId = (int) ($stmt->get_result()->fetch_assoc()['id'] ?? 0);

    $stmt = db()->prepare("SELECT expires_at FROM institution_erp_subscriptions
        WHERE institution_account_id = ?
        ORDER BY expires_at DESC, id DESC
        LIMIT 1");
    $stmt->bind_param('i', $accountId);
    $stmt->execute();
    $latest = $stmt->get_result()->fetch_assoc();

    $todayTs = strtotime(date('Y-m-d'));
    $baseTs = $todayTs;
    if (!empty($latest['expires_at'])) {
        $latestTs = strtotime((string) $latest['expires_at']);
        if ($latestTs !== false && $latestTs > $baseTs) {
            $baseTs = $latestTs;
        }
    }
    $starts = date('Y-m-d', $todayTs);
    $expires = date('Y-m-d', strtotime('+' . $days . ' days', $baseTs));
    $status = $paymentStatus === 'paid' || $paymentStatus === 'trial' ? 'active' : 'trial';
    $notes = $renewalRequestId > 0 ? 'Renewed from request #' . $renewalRequestId : 'Manual renewal by admin.';

    $stmt = db()->prepare("INSERT INTO institution_erp_subscriptions
        (institution_account_id, tenant_id, plan_id, status, starts_at, expires_at, amount, payment_status, payment_reference, notes, created_by)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param('iiisssdsssi', $accountId, $tenantId, $planId, $status, $starts, $expires, $amount, $paymentStatus, $reference, $notes, $adminId);
    $stmt->execute();
    $subscriptionId = (int) db()->insert_id;

    if ($tenantId > 0) {
        $erpStatus = 'active';
        $stmt = db()->prepare("UPDATE institution_erp_tenants SET erp_status = ?, updated_at = NOW() WHERE id = ?");
        $stmt->bind_param('si', $erpStatus, $tenantId);
        $stmt->execute();
    }

    if ($renewalRequestId > 0) {
        $approved = 'approved';
        $stmt = db()->prepare("UPDATE institution_erp_renewal_requests
            SET status = ?, amount = ?, payment_reference = ?, reviewed_at = NOW(), reviewed_by = ?
            WHERE id = ?");
        $stmt->bind_param('sdsii', $approved, $amount, $reference, $adminId, $renewalRequestId);
        $stmt->execute();
    }

    return $subscriptionId;
}

function institute_erp_expire_latest_subscription(int $accountId, int $adminId = 0): void
{
    institute_erp_ensure_tables();
    $stmt = db()->prepare("SELECT id, tenant_id FROM institution_erp_subscriptions
        WHERE institution_account_id = ?
        ORDER BY expires_at DESC, id DESC
        LIMIT 1");
    $stmt->bind_param('i', $accountId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    if (!$row) {
        return;
    }

    $expired = 'expired';
    $expires = date('Y-m-d', strtotime('-1 day'));
    $notes = 'Manually expired by admin #' . $adminId;
    $stmt = db()->prepare('UPDATE institution_erp_subscriptions SET status = ?, expires_at = ?, notes = ?, updated_at = NOW() WHERE id = ?');
    $stmt->bind_param('sssi', $expired, $expires, $notes, $row['id']);
    $stmt->execute();

    $tenantId = (int) ($row['tenant_id'] ?? 0);
    if ($tenantId > 0) {
        $erpStatus = 'read_only';
        $stmt = db()->prepare("UPDATE institution_erp_tenants SET erp_status = ?, updated_at = NOW() WHERE id = ?");
        $stmt->bind_param('si', $erpStatus, $tenantId);
        $stmt->execute();
    }
}

function institute_erp_backup_tenant_database(int $tenantId, int $createdBy = 0, string $note = 'Manual backup'): array
{
    institute_erp_ensure_tables();
    $stmt = db()->prepare('SELECT tenant_code, erp_db_name, institution_account_id FROM institution_erp_tenants WHERE id = ? LIMIT 1');
    $stmt->bind_param('i', $tenantId);
    $stmt->execute();
    $tenant = $stmt->get_result()->fetch_assoc();
    if (!$tenant) {
        return ['ok' => false, 'message' => 'ERP tenant not found.'];
    }

    $dbName = (string) ($tenant['erp_db_name'] ?? '');
    if (!preg_match('/^[a-zA-Z0-9_]+$/', $dbName)) {
        return ['ok' => false, 'message' => 'Invalid ERP database name.'];
    }

    $backupDir = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'database_exports' . DIRECTORY_SEPARATOR . 'erp';
    if (!is_dir($backupDir) && !mkdir($backupDir, 0755, true)) {
        return ['ok' => false, 'message' => 'Unable to create ERP backup directory.'];
    }

    $safeTenant = preg_replace('/[^a-zA-Z0-9_-]+/', '_', (string) $tenant['tenant_code']);
    $fileName = $safeTenant . '_' . date('Ymd_His') . '.sql';
    $filePath = $backupDir . DIRECTORY_SEPARATOR . $fileName;
    $handle = fopen($filePath, 'wb');
    if (!$handle) {
        return ['ok' => false, 'message' => 'Unable to create ERP backup file.'];
    }

    $erp = new mysqli(DB_HOST, DB_USER, DB_PASS, $dbName);
    if ($erp->connect_errno) {
        fclose($handle);
        @unlink($filePath);
        return ['ok' => false, 'message' => 'Unable to connect ERP database: ' . $erp->connect_error];
    }
    $erp->set_charset('utf8mb4');

    fwrite($handle, "-- GyanRank ERP tenant backup\n");
    fwrite($handle, "-- Tenant: " . (string) $tenant['tenant_code'] . "\n");
    fwrite($handle, "-- Database: {$dbName}\n");
    fwrite($handle, "-- Created: " . date('Y-m-d H:i:s') . "\n\n");
    fwrite($handle, "SET FOREIGN_KEY_CHECKS=0;\nSET NAMES utf8mb4;\n\n");

    $tables = $erp->query('SHOW TABLES');
    while ($tableRow = $tables->fetch_row()) {
        $table = (string) $tableRow[0];
        $escapedTable = '`' . str_replace('`', '``', $table) . '`';
        $create = $erp->query('SHOW CREATE TABLE ' . $escapedTable)->fetch_assoc();
        fwrite($handle, "DROP TABLE IF EXISTS {$escapedTable};\n");
        fwrite($handle, ($create['Create Table'] ?? array_values($create)[1]) . ";\n\n");

        $rows = $erp->query('SELECT * FROM ' . $escapedTable, MYSQLI_USE_RESULT);
        if ($rows) {
            $fields = $rows->fetch_fields();
            $columns = array_map(function ($field) {
                return '`' . str_replace('`', '``', $field->name) . '`';
            }, $fields);
            $batch = [];
            while ($row = $rows->fetch_assoc()) {
                $values = [];
                foreach ($row as $value) {
                    $values[] = $value === null ? 'NULL' : "'" . $erp->real_escape_string((string) $value) . "'";
                }
                $batch[] = '(' . implode(',', $values) . ')';
                if (count($batch) >= 100) {
                    fwrite($handle, 'INSERT INTO ' . $escapedTable . ' (' . implode(',', $columns) . ') VALUES' . "\n" . implode(",\n", $batch) . ";\n");
                    $batch = [];
                }
            }
            if ($batch) {
                fwrite($handle, 'INSERT INTO ' . $escapedTable . ' (' . implode(',', $columns) . ') VALUES' . "\n" . implode(",\n", $batch) . ";\n");
            }
            fwrite($handle, "\n");
        }
        if ($rows) {
            $rows->close();
        }
    }

    fwrite($handle, "SET FOREIGN_KEY_CHECKS=1;\n");
    fclose($handle);
    $erp->close();

    $relative = 'database_exports/erp/' . $fileName;
    $size = is_file($filePath) ? (int) filesize($filePath) : 0;
    $accountId = (int) ($tenant['institution_account_id'] ?? 0);
    $status = 'created';
    $stmt = db()->prepare("INSERT INTO institution_erp_backups
        (tenant_id, institution_account_id, backup_path, file_size, status, note, created_by)
        VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param('iisissi', $tenantId, $accountId, $relative, $size, $status, $note, $createdBy);
    $stmt->execute();

    return ['ok' => true, 'message' => 'ERP backup created: ' . $relative, 'path' => $relative, 'backup_id' => (int) db()->insert_id, 'size' => $size];
}

function institute_erp_backup_rows(int $tenantId = 0): array
{
    institute_erp_ensure_tables();
    $sql = "SELECT b.*, t.tenant_code, t.erp_db_name, a.institution_name
        FROM institution_erp_backups b
        INNER JOIN institution_erp_tenants t ON t.id = b.tenant_id
        INNER JOIN institution_accounts a ON a.id = b.institution_account_id";
    if ($tenantId > 0) {
        $stmt = db()->prepare($sql . ' WHERE b.tenant_id = ? ORDER BY b.id DESC LIMIT 200');
        $stmt->bind_param('i', $tenantId);
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    $result = db()->query($sql . ' ORDER BY b.id DESC LIMIT 200');
    return $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
}

function institute_erp_backup_absolute_path(string $relativePath): string
{
    $relativePath = str_replace(['\\', '..'], ['/', ''], trim($relativePath));
    if (!preg_match('#^database_exports/erp/[a-zA-Z0-9_.-]+\.sql$#', $relativePath)) {
        return '';
    }

    $base = realpath(dirname(__DIR__) . DIRECTORY_SEPARATOR . 'database_exports' . DIRECTORY_SEPARATOR . 'erp');
    $path = realpath(dirname(__DIR__) . DIRECTORY_SEPARATOR . $relativePath);
    if (!$base || !$path || strpos($path, $base . DIRECTORY_SEPARATOR) !== 0) {
        return '';
    }
    return $path;
}

function institute_erp_restore_tenant_database(int $tenantId, int $backupId, string $confirmation, int $adminId = 0): array
{
    institute_erp_ensure_tables();
    $stmt = db()->prepare("SELECT b.*, t.tenant_code, t.erp_db_name
        FROM institution_erp_backups b
        INNER JOIN institution_erp_tenants t ON t.id = b.tenant_id
        WHERE b.id = ? AND b.tenant_id = ?
        LIMIT 1");
    $stmt->bind_param('ii', $backupId, $tenantId);
    $stmt->execute();
    $backup = $stmt->get_result()->fetch_assoc();
    if (!$backup) {
        return ['ok' => false, 'message' => 'Backup record not found for this tenant.'];
    }

    $required = 'RESTORE ' . (string) $backup['tenant_code'];
    if (trim($confirmation) !== $required) {
        return ['ok' => false, 'message' => 'Restore blocked. Type "' . $required . '" to confirm.'];
    }

    $dbName = (string) ($backup['erp_db_name'] ?? '');
    if (!preg_match('/^[a-zA-Z0-9_]+$/', $dbName)) {
        return ['ok' => false, 'message' => 'Invalid ERP database name.'];
    }

    $filePath = institute_erp_backup_absolute_path((string) $backup['backup_path']);
    if ($filePath === '' || !is_file($filePath)) {
        return ['ok' => false, 'message' => 'Backup file is missing or invalid.'];
    }

    $header = file_get_contents($filePath, false, null, 0, 512);
    if (strpos((string) $header, '-- GyanRank ERP tenant backup') === false || strpos((string) $header, '-- Database: ' . $dbName) === false) {
        return ['ok' => false, 'message' => 'Backup file does not match this tenant database.'];
    }

    $preBackup = institute_erp_backup_tenant_database($tenantId, $adminId, 'Automatic backup before restore #' . $backupId);
    if (empty($preBackup['ok'])) {
        return ['ok' => false, 'message' => 'Restore stopped because pre-restore backup failed: ' . ($preBackup['message'] ?? '')];
    }

    $erp = new mysqli(DB_HOST, DB_USER, DB_PASS, $dbName);
    if ($erp->connect_errno) {
        return ['ok' => false, 'message' => 'Unable to connect ERP database: ' . $erp->connect_error];
    }
    $erp->set_charset('utf8mb4');

    $sql = file_get_contents($filePath);
    if ($sql === false) {
        $erp->close();
        return ['ok' => false, 'message' => 'Unable to read backup file.'];
    }

    if (!$erp->multi_query($sql)) {
        $error = $erp->error;
        $erp->close();
        return ['ok' => false, 'message' => 'Restore failed: ' . $error];
    }
    do {
        if ($result = $erp->store_result()) {
            $result->free();
        }
    } while ($erp->more_results() && $erp->next_result());
    if ($erp->errno) {
        $error = $erp->error;
        $erp->close();
        return ['ok' => false, 'message' => 'Restore failed: ' . $error];
    }
    $erp->close();

    $status = 'restored';
    $stmt = db()->prepare('UPDATE institution_erp_backups SET status = ?, restored_by = ?, restored_at = NOW() WHERE id = ?');
    $stmt->bind_param('sii', $status, $adminId, $backupId);
    $stmt->execute();

    return ['ok' => true, 'message' => 'ERP backup restored. Pre-restore backup created: ' . ($preBackup['path'] ?? '')];
}

function institute_erp_provision_account(int $accountId, int $requestId = 0, int $createdBy = 0, int $planId = 0, int $validityDays = 0): void
{
    institute_erp_ensure_tables();

    $stmt = db()->prepare('SELECT id, institution_name, institution_type FROM institution_accounts WHERE id = ? LIMIT 1');
    $stmt->bind_param('i', $accountId);
    $stmt->execute();
    $account = $stmt->get_result()->fetch_assoc();
    if (!$account) {
        return;
    }

    $baseCode = institute_erp_slug((string) $account['institution_name']);
    $tenantCode = $baseCode . '-' . $accountId;
    $basePath = 'erp/' . $tenantCode;
    $dbName = 'erp_' . preg_replace('/[^a-z0-9_]+/', '_', $tenantCode);
    $templateType = institute_erp_template_from_institution_type((string) ($account['institution_type'] ?? 'school'));

    $status = 'pending_setup';
    $setup = 'queued';
    $note = institute_erp_template_label($templateType) . ' tenant queued. Install/copy step pending.';
    $stmt = db()->prepare("INSERT INTO institution_erp_tenants
        (institution_account_id, request_id, tenant_code, erp_base_path, erp_template_type, erp_db_name, erp_status, setup_status, setup_note)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE request_id = VALUES(request_id), erp_template_type = VALUES(erp_template_type), updated_at = NOW()");
    $stmt->bind_param('iisssssss', $accountId, $requestId, $tenantCode, $basePath, $templateType, $dbName, $status, $setup, $note);
    $stmt->execute();

    $tenantId = (int) (db()->insert_id ?: db()->query("SELECT id FROM institution_erp_tenants WHERE institution_account_id = " . $accountId . " LIMIT 1")->fetch_assoc()['id']);
    $planId = $planId > 0 ? $planId : institute_erp_default_plan_id();
    if ($planId <= 0) {
        return;
    }

    $stmt = db()->prepare('SELECT validity_days, yearly_price FROM institution_erp_plans WHERE id = ? LIMIT 1');
    $stmt->bind_param('i', $planId);
    $stmt->execute();
    $plan = $stmt->get_result()->fetch_assoc();
    $days = $validityDays > 0 ? $validityDays : (int) ($plan['validity_days'] ?? 365);
    $amount = (float) ($plan['yearly_price'] ?? 0);
    $starts = date('Y-m-d');
    $expires = date('Y-m-d', strtotime('+' . max(1, $days) . ' days'));
    $subStatus = $amount > 0 ? 'active' : 'trial';
    $paymentStatus = $amount > 0 ? 'pending' : 'trial';
    $notes = 'Created during institute approval.';

    $stmt = db()->prepare("INSERT INTO institution_erp_subscriptions
        (institution_account_id, tenant_id, plan_id, status, starts_at, expires_at, amount, payment_status, notes, created_by)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param('iiisssdssi', $accountId, $tenantId, $planId, $subStatus, $starts, $expires, $amount, $paymentStatus, $notes, $createdBy);
    $stmt->execute();
}

function institute_erp_access_state(int $accountId): array
{
    institute_erp_ensure_tables();
    $stmt = db()->prepare("SELECT s.status, s.expires_at, t.erp_status, t.erp_base_path, t.erp_template_type, t.setup_status, p.features_json, p.plan_name, p.plan_slug
        FROM institution_erp_subscriptions s
        LEFT JOIN institution_erp_tenants t ON t.id = s.tenant_id
        LEFT JOIN institution_erp_plans p ON p.id = s.plan_id
        WHERE s.institution_account_id = ?
        ORDER BY s.expires_at DESC, s.id DESC
        LIMIT 1");
    $stmt->bind_param('i', $accountId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc() ?: [];
    $active = $row && in_array((string) $row['status'], ['trial', 'active'], true) && strtotime((string) $row['expires_at']) >= strtotime(date('Y-m-d'));
    return [
        'can_write' => $active && (string) ($row['erp_status'] ?? '') === 'active',
        'can_report' => (bool) $row,
        'is_expired' => $row && !$active,
        'erp_base_path' => (string) ($row['erp_base_path'] ?? ''),
        'erp_template_type' => (string) ($row['erp_template_type'] ?? 'school'),
        'setup_status' => (string) ($row['setup_status'] ?? 'not_started'),
        'expires_at' => (string) ($row['expires_at'] ?? ''),
        'plan_name' => (string) ($row['plan_name'] ?? ''),
        'plan_slug' => (string) ($row['plan_slug'] ?? ''),
        'features' => institute_erp_decode_features((string) ($row['features_json'] ?? '[]')),
    ];
}

function institute_erp_decode_features(string $featuresJson): array
{
    $features = json_decode($featuresJson !== '' ? $featuresJson : '[]', true);
    if (!is_array($features)) {
        return [];
    }
    return array_values(array_unique(array_filter(array_map(static fn($feature) => trim((string) $feature), $features))));
}

function institute_erp_plan_has_feature(array $accessState, string $feature): bool
{
    $features = (array) ($accessState['features'] ?? []);
    return in_array('all_modules', $features, true) || in_array($feature, $features, true);
}

function institute_erp_normalize_domain(string $domain): string
{
    $domain = strtolower(trim($domain));
    $domain = preg_replace('#^https?://#', '', $domain) ?? $domain;
    $domain = preg_replace('#/.*$#', '', $domain) ?? $domain;
    $domain = preg_replace('#:\d+$#', '', $domain) ?? $domain;
    $domain = trim($domain, ". \t\n\r\0\x0B");
    return $domain;
}

function institute_erp_is_valid_domain(string $domain): bool
{
    if ($domain === '' || strlen($domain) > 190 || substr_count($domain, '.') < 1) {
        return false;
    }
    return (bool) preg_match('/^(?!-)(?:[a-z0-9-]{1,63}\.)+[a-z]{2,63}$/', $domain);
}

function institute_erp_create_domain_request(int $tenantId, string $domain, int $requestedBy = 0, string $note = ''): array
{
    institute_erp_ensure_tables();
    $normalized = institute_erp_normalize_domain($domain);
    if (!institute_erp_is_valid_domain($normalized)) {
        return ['ok' => false, 'message' => 'Please enter a valid domain like school.example.com.'];
    }

    $stmt = db()->prepare('SELECT id, institution_account_id FROM institution_erp_tenants WHERE id = ? LIMIT 1');
    $stmt->bind_param('i', $tenantId);
    $stmt->execute();
    $tenant = $stmt->get_result()->fetch_assoc();
    if (!$tenant) {
        return ['ok' => false, 'message' => 'ERP tenant not found.'];
    }

    $existing = db()->prepare('SELECT id, status FROM institution_erp_domain_requests WHERE normalized_domain = ? LIMIT 1');
    $existing->bind_param('s', $normalized);
    $existing->execute();
    $row = $existing->get_result()->fetch_assoc();
    if ($row && !in_array((string) $row['status'], ['rejected', 'cancelled'], true)) {
        return ['ok' => false, 'message' => 'This domain already has an active request.'];
    }

    $accountId = (int) $tenant['institution_account_id'];
    $status = 'pending';
    $stmt = db()->prepare("INSERT INTO institution_erp_domain_requests
        (tenant_id, institution_account_id, requested_domain, normalized_domain, status, admin_note, requested_by)
        VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param('iissssi', $tenantId, $accountId, $domain, $normalized, $status, $note, $requestedBy);
    $stmt->execute();

    $tenantStatus = 'requested';
    $stmt = db()->prepare('UPDATE institution_erp_tenants SET custom_domain = ?, custom_domain_status = ?, updated_at = NOW() WHERE id = ?');
    $stmt->bind_param('ssi', $normalized, $tenantStatus, $tenantId);
    $stmt->execute();

    return ['ok' => true, 'message' => 'Custom domain request added. Team can now verify DNS and map server.', 'request_id' => (int) db()->insert_id];
}

function institute_erp_update_domain_request(int $requestId, string $status, string $dnsTarget, string $serverDocroot, string $sslStatus, string $note, int $adminId = 0): array
{
    institute_erp_ensure_tables();
    $allowedStatus = ['pending', 'dns_pending', 'mapped', 'rejected', 'cancelled'];
    $allowedSsl = ['pending', 'issued', 'not_required', 'failed'];
    if (!in_array($status, $allowedStatus, true)) {
        $status = 'pending';
    }
    if (!in_array($sslStatus, $allowedSsl, true)) {
        $sslStatus = 'pending';
    }

    $stmt = db()->prepare('SELECT tenant_id, normalized_domain FROM institution_erp_domain_requests WHERE id = ? LIMIT 1');
    $stmt->bind_param('i', $requestId);
    $stmt->execute();
    $request = $stmt->get_result()->fetch_assoc();
    if (!$request) {
        return ['ok' => false, 'message' => 'Domain request not found.'];
    }

    $stmt = db()->prepare("UPDATE institution_erp_domain_requests
        SET status = ?, dns_target = ?, server_docroot = ?, ssl_status = ?, admin_note = ?, reviewed_by = ?, reviewed_at = NOW()
        WHERE id = ?");
    $stmt->bind_param('sssssii', $status, $dnsTarget, $serverDocroot, $sslStatus, $note, $adminId, $requestId);
    $stmt->execute();

    $tenantStatus = match ($status) {
        'mapped' => 'mapped',
        'dns_pending' => 'dns_pending',
        'rejected', 'cancelled' => 'rejected',
        default => 'requested',
    };
    $domain = $status === 'mapped' ? (string) $request['normalized_domain'] : null;
    if ($domain === null) {
        $stmt = db()->prepare('UPDATE institution_erp_tenants SET custom_domain_status = ?, updated_at = NOW() WHERE id = ?');
        $stmt->bind_param('si', $tenantStatus, $request['tenant_id']);
    } else {
        $stmt = db()->prepare('UPDATE institution_erp_tenants SET custom_domain = ?, custom_domain_status = ?, updated_at = NOW() WHERE id = ?');
        $stmt->bind_param('ssi', $domain, $tenantStatus, $request['tenant_id']);
    }
    $stmt->execute();

    return ['ok' => true, 'message' => 'Domain request updated.'];
}

function institute_erp_domain_request_rows(): array
{
    institute_erp_ensure_tables();
    $result = db()->query("SELECT d.*, t.tenant_code, t.erp_base_path, t.erp_status, t.setup_status, a.institution_name, a.email, a.mobile
        FROM institution_erp_domain_requests d
        INNER JOIN institution_erp_tenants t ON t.id = d.tenant_id
        INNER JOIN institution_accounts a ON a.id = d.institution_account_id
        ORDER BY FIELD(d.status, 'pending', 'dns_pending', 'mapped', 'rejected', 'cancelled'), d.id DESC
        LIMIT 300");
    return $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
}

function institute_erp_install_tenant(int $tenantId): array
{
    institute_erp_ensure_tables();
    @set_time_limit(900);

    $stmt = db()->prepare("SELECT t.*, a.institution_name
        FROM institution_erp_tenants t
        INNER JOIN institution_accounts a ON a.id = t.institution_account_id
        WHERE t.id = ?
        LIMIT 1");
    $stmt->bind_param('i', $tenantId);
    $stmt->execute();
    $tenant = $stmt->get_result()->fetch_assoc();
    if (!$tenant) {
        return ['ok' => false, 'message' => 'ERP tenant not found.'];
    }

    $source = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'smart_school_src';
    if (!is_dir($source) || !is_file($source . DIRECTORY_SEPARATOR . 'index.php')) {
        return ['ok' => false, 'message' => 'GyanRank ERP source folder missing.'];
    }

    $tenantPath = dirname(__DIR__) . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, (string) $tenant['erp_base_path']);
    if (!is_dir(dirname($tenantPath))) {
        mkdir(dirname($tenantPath), 0755, true);
    }

    try {
        institute_erp_mark_install_state($tenantId, 'queued', 'pending_setup', 'Creating tenant bootstrap and importing ERP database.');
        if (!is_dir($tenantPath)) {
            mkdir($tenantPath, 0755, true);
        }
        $dbName = (string) $tenant['erp_db_name'];
        if ($dbName === '') {
            $dbName = 'erp_' . preg_replace('/[^a-z0-9_]+/', '_', strtolower((string) $tenant['tenant_code']));
        }
        institute_erp_create_and_import_database($dbName);
        institute_erp_apply_branding_defaults($dbName);
        institute_erp_seed_default_admin($dbName, (string) $tenant['institution_name']);
        institute_erp_seed_operational_demo_data($dbName);
        institute_erp_apply_template_setup($dbName, (string) ($tenant['erp_template_type'] ?? 'school'), (string) $tenant['institution_name']);
        institute_erp_write_tenant_bootstrap($tenantPath, (string) $tenant['tenant_code'], $dbName, (int) $tenant['institution_account_id'], $tenantId, (string) ($tenant['erp_template_type'] ?? 'school'));

        $status = 'active';
        $setup = 'installed';
        $note = 'GyanRank ERP tenant bootstrap installed and connected to ' . $dbName . '.';
        $stmt = db()->prepare("UPDATE institution_erp_tenants SET erp_db_name = ?, erp_status = ?, setup_status = ?, setup_note = ?, updated_at = NOW() WHERE id = ?");
        $stmt->bind_param('ssssi', $dbName, $status, $setup, $note, $tenantId);
        $stmt->execute();
        return ['ok' => true, 'message' => $note];
    } catch (Throwable $e) {
        institute_erp_mark_install_state($tenantId, 'failed', 'pending_setup', substr($e->getMessage(), 0, 240));
        return ['ok' => false, 'message' => $e->getMessage()];
    }
}

function institute_erp_mark_install_state(int $tenantId, string $setupStatus, string $erpStatus, string $note): void
{
    $stmt = db()->prepare('UPDATE institution_erp_tenants SET setup_status = ?, erp_status = ?, setup_note = ?, updated_at = NOW() WHERE id = ?');
    $stmt->bind_param('sssi', $setupStatus, $erpStatus, $note, $tenantId);
    $stmt->execute();
}

function institute_erp_write_tenant_bootstrap(string $tenantPath, string $tenantCode, string $dbName, int $accountId, int $tenantId, string $templateType = 'school'): void
{
    $root = dirname(__DIR__);
    $source = $root . DIRECTORY_SEPARATOR . 'smart_school_src';
    $relativeSource = str_replace('\\', '/', '../../smart_school_src');
    $index = "<?php\n\n";
    $index .= "ini_set('log_errors', '1');\n";
    $index .= "ini_set('error_log', __DIR__ . '/erp-error.log');\n";
    $index .= "register_shutdown_function(function () {\n";
    $index .= "    \$error = error_get_last();\n";
    $index .= "    if (\$error && in_array(\$error['type'], array(E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR), true)) {\n";
    $index .= "        error_log(json_encode(\$error, JSON_UNESCAPED_SLASHES));\n";
    $index .= "    }\n";
    $index .= "});\n";
    $index .= "define('GYANRANK_TENANT_CODE', '" . addslashes($tenantCode) . "');\n";
    $index .= "define('GYANRANK_ERP_TEMPLATE_TYPE', '" . addslashes($templateType) . "');\n";
    $index .= "define('GYANRANK_TENANT_DB_HOST', '" . addslashes(DB_HOST) . "');\n";
    $index .= "define('GYANRANK_TENANT_DB_USER', '" . addslashes(DB_USER) . "');\n";
    $index .= "define('GYANRANK_TENANT_DB_PASS', '" . addslashes(DB_PASS) . "');\n";
    $index .= "define('GYANRANK_TENANT_DB_NAME', '" . addslashes($dbName) . "');\n";
    $index .= "define('GYANRANK_PARENT_DB_HOST', '" . addslashes(DB_HOST) . "');\n";
    $index .= "define('GYANRANK_PARENT_DB_USER', '" . addslashes(DB_USER) . "');\n";
    $index .= "define('GYANRANK_PARENT_DB_PASS', '" . addslashes(DB_PASS) . "');\n";
    $index .= "define('GYANRANK_PARENT_DB_NAME', '" . addslashes(DB_NAME) . "');\n";
    $index .= "define('GYANRANK_INSTITUTION_ACCOUNT_ID', " . $accountId . ");\n";
    $index .= "define('GYANRANK_ERP_TENANT_ID', " . $tenantId . ");\n";
    $index .= "chdir(__DIR__ . '/" . $relativeSource . "');\n";
    $index .= "require __DIR__ . '/" . $relativeSource . "/index.php';\n";

    if (!is_dir($source) || !is_file($source . DIRECTORY_SEPARATOR . 'index.php')) {
        throw new RuntimeException('Shared GyanRank ERP runtime missing.');
    }
    if (file_put_contents($tenantPath . DIRECTORY_SEPARATOR . 'index.php', $index) === false) {
        throw new RuntimeException('Unable to write tenant bootstrap.');
    }

    $base = rtrim(APP_BASE, '/');
    $htaccess = "<IfModule mod_rewrite.c>\n";
    $htaccess .= "RewriteEngine On\n";
    $htaccess .= "RewriteRule ^backend/(.*)$ " . $base . "/smart_school_src/backend/$1 [L]\n";
    $htaccess .= "RewriteRule ^uploads/(.*)$ " . $base . "/smart_school_src/uploads/$1 [L]\n";
    $htaccess .= "RewriteRule ^temp/(.*)$ " . $base . "/smart_school_src/temp/$1 [L]\n";
    $htaccess .= "RewriteCond %{REQUEST_FILENAME} !-f\n";
    $htaccess .= "RewriteCond %{REQUEST_FILENAME} !-d\n";
    $htaccess .= "RewriteRule ^(.*)$ index.php?/$1 [L]\n";
    $htaccess .= "</IfModule>\n";
    file_put_contents($tenantPath . DIRECTORY_SEPARATOR . '.htaccess', $htaccess);
}

function institute_erp_recursive_copy(string $source, string $destination): void
{
    if (!is_dir($source)) {
        throw new RuntimeException('Source folder missing: ' . $source);
    }
    if (!is_dir($destination) && !mkdir($destination, 0755, true)) {
        throw new RuntimeException('Unable to create tenant folder.');
    }

    $items = scandir($source);
    if ($items === false) {
        throw new RuntimeException('Unable to read source folder.');
    }

    foreach ($items as $item) {
        if ($item === '.' || $item === '..') {
            continue;
        }
        $src = $source . DIRECTORY_SEPARATOR . $item;
        $dst = $destination . DIRECTORY_SEPARATOR . $item;
        if (is_dir($src)) {
            institute_erp_recursive_copy($src, $dst);
        } elseif (!is_file($dst) && !copy($src, $dst)) {
            throw new RuntimeException('Unable to copy file: ' . $item);
        }
    }
}

function institute_erp_create_and_import_database(string $dbName): void
{
    if (!preg_match('/^[a-zA-Z0-9_]+$/', $dbName)) {
        throw new RuntimeException('Invalid ERP database name.');
    }

    $conn = db();
    $escapedDb = '`' . str_replace('`', '``', $dbName) . '`';
    $conn->query("CREATE DATABASE IF NOT EXISTS {$escapedDb} CHARACTER SET utf8 COLLATE utf8_general_ci");

    $check = $conn->query("SELECT COUNT(*) AS total FROM information_schema.tables WHERE table_schema = '" . $conn->real_escape_string($dbName) . "'");
    $hasTables = (int) ($check->fetch_assoc()['total'] ?? 0) > 0;
    if ($hasTables) {
        institute_erp_apply_legacy_teacher_compatibility($dbName);
        return;
    }

    $sqlFile = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'smart_school_src' . DIRECTORY_SEPARATOR . 'application' . DIRECTORY_SEPARATOR . 'controllers' . DIRECTORY_SEPARATOR . 'install' . DIRECTORY_SEPARATOR . 'database.sql';
    $sql = file_get_contents($sqlFile);
    if ($sql === false || trim($sql) === '') {
        throw new RuntimeException('GyanRank ERP SQL file is empty or missing.');
    }

    $import = new mysqli(DB_HOST, DB_USER, DB_PASS, $dbName);
    $import->set_charset('utf8');
    if (!$import->multi_query($sql)) {
        throw new RuntimeException('ERP database import failed: ' . $import->error);
    }
    do {
        if ($result = $import->store_result()) {
            $result->free();
        }
    } while ($import->more_results() && $import->next_result());

    if ($import->errno) {
        throw new RuntimeException('ERP database import failed: ' . $import->error);
    }
    $import->close();
    institute_erp_apply_legacy_teacher_compatibility($dbName);
}

function institute_erp_apply_branding_defaults(string $dbName): void
{
    $erp = new mysqli(DB_HOST, DB_USER, DB_PASS, $dbName);
    $erp->set_charset('utf8');

    $schoolName = 'GyanRank';
    $currency = 'INR';
    $currencySymbol = '₹';
    $image = '1.png';
    $adminLogo = '1.png';
    $adminSmallLogo = '1.png';
    $appLogo = '1.png';

    $theme = 'default.jpg';
    $stmt = $erp->prepare("UPDATE sch_settings
        SET name = ?, currency = ?, currency_symbol = ?, image = ?, admin_logo = ?, admin_small_logo = ?, app_logo = ?, theme = ?
        WHERE id = 1");
    $stmt->bind_param('ssssssss', $schoolName, $currency, $currencySymbol, $image, $adminLogo, $adminSmallLogo, $appLogo, $theme);
    $stmt->execute();

    $frontLogo = 'uploads/school_content/logo/1.png';
    $favIcon = 'uploads/school_content/admin_small_logo/1.png';
    $footerText = '© ' . date('Y') . ' GyanRank';
    $stmt = $erp->prepare("UPDATE front_cms_settings
        SET logo = ?, fav_icon = ?, footer_text = ?
        WHERE id = 1");
    $stmt->bind_param('sss', $frontLogo, $favIcon, $footerText);
    $stmt->execute();

    $legacySchoolName = 'Your' . ' School' . ' Name';
    $legacyLowerBrand = 'smart' . ' school';
    $legacyTitleBrand = 'Smart' . ' School';
    $legacyUpperBrand = 'SMART' . ' SCHOOL';
    $stmt = $erp->prepare("UPDATE notification_setting
        SET template = REPLACE(REPLACE(REPLACE(REPLACE(template, ?, 'GyanRank'), ?, 'GyanRank'), ?, 'GyanRank'), ?, 'GyanRank')");
    $stmt->bind_param('ssss', $legacySchoolName, $legacyLowerBrand, $legacyTitleBrand, $legacyUpperBrand);
    $stmt->execute();

    $erp->close();
}

function institute_erp_apply_legacy_teacher_compatibility(string $dbName): void
{
    if (!preg_match('/^[a-zA-Z0-9_]+$/', $dbName)) {
        throw new RuntimeException('Invalid ERP database name.');
    }

    $erp = new mysqli(DB_HOST, DB_USER, DB_PASS, $dbName);
    if ($erp->connect_errno) {
        throw new RuntimeException('Unable to connect ERP database: ' . $erp->connect_error);
    }
    $erp->set_charset('utf8');

    $erp->query("CREATE TABLE IF NOT EXISTS teachers (
        id INT(11) NOT NULL AUTO_INCREMENT,
        name VARCHAR(100) NOT NULL,
        email VARCHAR(100) NOT NULL,
        password VARCHAR(100) NULL,
        sex VARCHAR(20) NULL,
        dob DATE NULL,
        address TEXT NULL,
        phone VARCHAR(30) NULL,
        image VARCHAR(255) NULL,
        is_active TINYINT(1) NOT NULL DEFAULT 1,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY teachers_email_index (email)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci");

    $count = $erp->query('SELECT COUNT(*) AS total FROM teachers')->fetch_assoc();
    if ((int) ($count['total'] ?? 0) === 0) {
        $password = password_hash('teacher123', PASSWORD_DEFAULT);
        $image = 'uploads/student_images/no_image.png';
        $teachers = [
            ['Priya Sharma', 'priya.sharma@gyanrank.test', 'Female', '1988-04-14', 'Knowledge Park, Tonk Road, Jaipur', '9876543211'],
            ['Amit Verma', 'amit.verma@gyanrank.test', 'Male', '1985-09-22', 'C-Scheme, Jaipur', '9876543212'],
        ];
        $stmt = $erp->prepare('INSERT INTO teachers (name, email, password, sex, dob, address, phone, image) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
        foreach ($teachers as $teacher) {
            [$name, $email, $sex, $dob, $address, $phone] = $teacher;
            $stmt->bind_param('ssssssss', $name, $email, $password, $sex, $dob, $address, $phone, $image);
            $stmt->execute();
        }
    }

    $erp->close();
}

function institute_erp_apply_template_setup(string $dbName, string $templateType, string $institutionName = ''): void
{
    if (!preg_match('/^[a-zA-Z0-9_]+$/', $dbName)) {
        throw new RuntimeException('Invalid ERP database name.');
    }

    $templateType = in_array($templateType, ['school', 'degree_college', 'coaching'], true) ? $templateType : 'school';
    $erp = new mysqli(DB_HOST, DB_USER, DB_PASS, $dbName);
    if ($erp->connect_errno) {
        throw new RuntimeException('Unable to connect ERP database: ' . $erp->connect_error);
    }
    $erp->set_charset('utf8');

    $erp->query("CREATE TABLE IF NOT EXISTS gr_erp_template_settings (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT,
        template_type ENUM('school','degree_college','coaching') NOT NULL DEFAULT 'school',
        label_class VARCHAR(80) NOT NULL DEFAULT 'Class',
        label_section VARCHAR(80) NOT NULL DEFAULT 'Section',
        label_session VARCHAR(80) NOT NULL DEFAULT 'Session',
        academic_pattern ENUM('school','yearly','semester','duration_batch') NOT NULL DEFAULT 'school',
        notes VARCHAR(255) NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci");

    $labels = [
        'school' => ['Class', 'Section', 'Session', 'school', 'Default school/college ERP template.'],
        'degree_college' => ['Program / Year', 'Subject Group / Semester', 'Academic Year', 'yearly', 'Degree college template for BA/BSc/BCom yearly or semester workflows.'],
        'coaching' => ['Course', 'Batch', 'Training Session', 'duration_batch', 'Coaching template for 3/6/12/24 month course and batch workflows.'],
    ];
    [$classLabel, $sectionLabel, $sessionLabel, $pattern, $note] = $labels[$templateType];
    $erp->query('TRUNCATE TABLE gr_erp_template_settings');
    $stmt = $erp->prepare('INSERT INTO gr_erp_template_settings (template_type, label_class, label_section, label_session, academic_pattern, notes) VALUES (?, ?, ?, ?, ?, ?)');
    $stmt->bind_param('ssssss', $templateType, $classLabel, $sectionLabel, $sessionLabel, $pattern, $note);
    $stmt->execute();

    if ($templateType === 'degree_college') {
        institute_erp_apply_degree_college_setup($erp, $institutionName);
        institute_erp_sync_degree_master_to_admission($erp);
    } elseif ($templateType === 'coaching') {
        institute_erp_apply_coaching_setup($erp, $institutionName);
        institute_erp_sync_coaching_master_to_admission($erp);
    }

    $erp->close();
}

function institute_erp_apply_degree_college_setup(mysqli $erp, string $institutionName = ''): void
{
    $erp->query("CREATE TABLE IF NOT EXISTS gr_degree_programs (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT,
        program_code VARCHAR(40) NOT NULL,
        program_name VARCHAR(160) NOT NULL,
        duration_years TINYINT UNSIGNED NOT NULL DEFAULT 3,
        academic_pattern ENUM('yearly','semester') NOT NULL DEFAULT 'yearly',
        total_terms TINYINT UNSIGNED NOT NULL DEFAULT 3,
        fee_mode ENUM('full_course','yearly','semester') NOT NULL DEFAULT 'yearly',
        status TINYINT(1) NOT NULL DEFAULT 1,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY gr_degree_programs_code_unique (program_code)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci");

    $erp->query("CREATE TABLE IF NOT EXISTS gr_degree_terms (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT,
        program_id INT UNSIGNED NOT NULL,
        term_no TINYINT UNSIGNED NOT NULL,
        term_name VARCHAR(80) NOT NULL,
        term_type ENUM('year','semester') NOT NULL DEFAULT 'year',
        sort_order INT UNSIGNED NOT NULL DEFAULT 0,
        status TINYINT(1) NOT NULL DEFAULT 1,
        PRIMARY KEY (id),
        UNIQUE KEY gr_degree_terms_program_term_unique (program_id, term_no),
        KEY gr_degree_terms_program_index (program_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci");

    $erp->query("CREATE TABLE IF NOT EXISTS gr_degree_subjects (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT,
        program_id INT UNSIGNED NOT NULL,
        term_id INT UNSIGNED NULL,
        subject_code VARCHAR(40) NULL,
        subject_name VARCHAR(160) NOT NULL,
        subject_type ENUM('core','elective','optional','practical','project') NOT NULL DEFAULT 'core',
        status TINYINT(1) NOT NULL DEFAULT 1,
        PRIMARY KEY (id),
        KEY gr_degree_subjects_program_index (program_id),
        KEY gr_degree_subjects_term_index (term_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci");

    $erp->query("CREATE TABLE IF NOT EXISTS gr_degree_fee_structures (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT,
        program_id INT UNSIGNED NOT NULL,
        term_id INT UNSIGNED NULL,
        fee_mode ENUM('full_course','yearly','semester') NOT NULL DEFAULT 'yearly',
        fee_title VARCHAR(160) NOT NULL,
        amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
        due_days INT UNSIGNED NOT NULL DEFAULT 30,
        status TINYINT(1) NOT NULL DEFAULT 1,
        PRIMARY KEY (id),
        KEY gr_degree_fee_program_index (program_id),
        KEY gr_degree_fee_term_index (term_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci");

    $count = $erp->query('SELECT COUNT(*) AS total FROM gr_degree_programs')->fetch_assoc();
    if ((int) ($count['total'] ?? 0) > 0) {
        return;
    }

    $programs = [
        ['BA', 'Bachelor of Arts', 3, 'yearly', 3, 'yearly'],
        ['BSC', 'Bachelor of Science', 3, 'semester', 6, 'semester'],
        ['BCOM', 'Bachelor of Commerce', 3, 'yearly', 3, 'yearly'],
    ];
    $stmt = $erp->prepare('INSERT INTO gr_degree_programs (program_code, program_name, duration_years, academic_pattern, total_terms, fee_mode) VALUES (?, ?, ?, ?, ?, ?)');
    foreach ($programs as $program) {
        [$code, $name, $years, $academicPattern, $terms, $feeMode] = $program;
        $stmt->bind_param('ssisis', $code, $name, $years, $academicPattern, $terms, $feeMode);
        $stmt->execute();
        $programId = (int) $erp->insert_id;
        institute_erp_seed_degree_terms($erp, $programId, $academicPattern, $terms);
        institute_erp_seed_degree_subjects($erp, $programId, $code);
        institute_erp_seed_degree_fees($erp, $programId, $feeMode);
    }
}

function institute_erp_seed_degree_terms(mysqli $erp, int $programId, string $academicPattern, int $terms): void
{
    $stmt = $erp->prepare('INSERT INTO gr_degree_terms (program_id, term_no, term_name, term_type, sort_order) VALUES (?, ?, ?, ?, ?)');
    for ($termNo = 1; $termNo <= $terms; $termNo++) {
        $termType = $academicPattern === 'semester' ? 'semester' : 'year';
        $termName = $academicPattern === 'semester' ? 'Semester ' . $termNo : $termNo . ($termNo === 1 ? 'st' : ($termNo === 2 ? 'nd' : 'rd')) . ' Year';
        $sort = $termNo;
        $stmt->bind_param('iissi', $programId, $termNo, $termName, $termType, $sort);
        $stmt->execute();
    }
}

function institute_erp_seed_degree_subjects(mysqli $erp, int $programId, string $programCode): void
{
    $subjects = match ($programCode) {
        'BSC' => [['PHY', 'Physics', 'core'], ['CHEM', 'Chemistry', 'core'], ['MATH', 'Mathematics', 'core'], ['LAB', 'Science Practical', 'practical']],
        'BCOM' => [['ACC', 'Accountancy', 'core'], ['ECO', 'Economics', 'core'], ['BST', 'Business Studies', 'core'], ['TAX', 'Taxation', 'elective']],
        default => [['HIS', 'History', 'core'], ['POL', 'Political Science', 'core'], ['SOC', 'Sociology', 'elective'], ['HIN', 'Hindi Literature', 'optional']],
    };
    $stmt = $erp->prepare('INSERT INTO gr_degree_subjects (program_id, subject_code, subject_name, subject_type) VALUES (?, ?, ?, ?)');
    foreach ($subjects as $subject) {
        [$code, $name, $type] = $subject;
        $stmt->bind_param('isss', $programId, $code, $name, $type);
        $stmt->execute();
    }
}

function institute_erp_seed_degree_fees(mysqli $erp, int $programId, string $feeMode): void
{
    $amount = $feeMode === 'semester' ? 7500.00 : 15000.00;
    $title = $feeMode === 'semester' ? 'Semester Tuition Fee' : 'Annual Tuition Fee';
    $stmt = $erp->prepare('INSERT INTO gr_degree_fee_structures (program_id, fee_mode, fee_title, amount, due_days) VALUES (?, ?, ?, ?, 30)');
    $stmt->bind_param('issd', $programId, $feeMode, $title, $amount);
    $stmt->execute();
}

function institute_erp_apply_coaching_setup(mysqli $erp, string $institutionName = ''): void
{
    $erp->query("CREATE TABLE IF NOT EXISTS gr_coaching_courses (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT,
        course_code VARCHAR(40) NOT NULL,
        course_name VARCHAR(160) NOT NULL,
        duration_months INT UNSIGNED NOT NULL DEFAULT 3,
        fee_mode ENUM('full_course','monthly','installment') NOT NULL DEFAULT 'full_course',
        course_fee DECIMAL(10,2) NOT NULL DEFAULT 0.00,
        status TINYINT(1) NOT NULL DEFAULT 1,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY gr_coaching_courses_code_unique (course_code)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci");

    $erp->query("CREATE TABLE IF NOT EXISTS gr_coaching_batches (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT,
        course_id INT UNSIGNED NOT NULL,
        batch_name VARCHAR(120) NOT NULL,
        start_date DATE NULL,
        end_date DATE NULL,
        timing VARCHAR(80) NULL,
        capacity INT UNSIGNED NULL,
        trainer_name VARCHAR(120) NULL,
        status TINYINT(1) NOT NULL DEFAULT 1,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY gr_coaching_batches_course_index (course_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci");

    $erp->query("CREATE TABLE IF NOT EXISTS gr_coaching_fee_plans (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT,
        course_id INT UNSIGNED NOT NULL,
        fee_title VARCHAR(160) NOT NULL,
        fee_mode ENUM('full_course','monthly','installment') NOT NULL DEFAULT 'full_course',
        installment_count INT UNSIGNED NOT NULL DEFAULT 1,
        amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
        due_days INT UNSIGNED NOT NULL DEFAULT 30,
        status TINYINT(1) NOT NULL DEFAULT 1,
        PRIMARY KEY (id),
        KEY gr_coaching_fee_course_index (course_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci");

    $count = $erp->query('SELECT COUNT(*) AS total FROM gr_coaching_courses')->fetch_assoc();
    if ((int) ($count['total'] ?? 0) > 0) {
        return;
    }

    $courses = [
        ['BASIC-3M', 'Basic Computer Course', 3, 'full_course', 4999.00],
        ['ADV-6M', 'Advanced Computer Course', 6, 'installment', 11999.00],
        ['DIP-12M', 'One Year Diploma Program', 12, 'monthly', 24000.00],
        ['PRO-24M', 'Professional Program', 24, 'monthly', 48000.00],
    ];
    $stmt = $erp->prepare('INSERT INTO gr_coaching_courses (course_code, course_name, duration_months, fee_mode, course_fee) VALUES (?, ?, ?, ?, ?)');
    foreach ($courses as $course) {
        [$code, $name, $months, $feeMode, $fee] = $course;
        $stmt->bind_param('ssisd', $code, $name, $months, $feeMode, $fee);
        $stmt->execute();
        $courseId = (int) $erp->insert_id;
        institute_erp_seed_coaching_batch($erp, $courseId, $name, $months);
        institute_erp_seed_coaching_fee($erp, $courseId, $feeMode, $fee, $months);
    }
}

function institute_erp_seed_coaching_batch(mysqli $erp, int $courseId, string $courseName, int $months): void
{
    $start = date('Y-m-d');
    $end = date('Y-m-d', strtotime('+' . max(1, $months) . ' months'));
    $batchName = 'Morning Batch - ' . date('M Y');
    $timing = '08:00 AM - 10:00 AM';
    $capacity = 40;
    $trainer = 'Demo Trainer';
    $stmt = $erp->prepare('INSERT INTO gr_coaching_batches (course_id, batch_name, start_date, end_date, timing, capacity, trainer_name) VALUES (?, ?, ?, ?, ?, ?, ?)');
    $stmt->bind_param('issssis', $courseId, $batchName, $start, $end, $timing, $capacity, $trainer);
    $stmt->execute();
}

function institute_erp_seed_coaching_fee(mysqli $erp, int $courseId, string $feeMode, float $fee, int $months): void
{
    $installments = $feeMode === 'monthly' ? max(1, $months) : ($feeMode === 'installment' ? 3 : 1);
    $title = $feeMode === 'monthly' ? 'Monthly Course Fee' : ($feeMode === 'installment' ? 'Course Installment Plan' : 'Full Course Fee');
    $stmt = $erp->prepare('INSERT INTO gr_coaching_fee_plans (course_id, fee_title, fee_mode, installment_count, amount, due_days) VALUES (?, ?, ?, ?, ?, 30)');
    $stmt->bind_param('issid', $courseId, $title, $feeMode, $installments, $fee);
    $stmt->execute();
}

function institute_erp_sync_degree_master_to_admission(mysqli $erp): void
{
    institute_erp_ensure_legacy_admission_tables($erp);
    $programs = $erp->query('SELECT id, program_code, program_name FROM gr_degree_programs WHERE status = 1 ORDER BY id ASC');
    if (!$programs) {
        return;
    }

    while ($program = $programs->fetch_assoc()) {
        $className = trim((string) $program['program_code'] . ' - ' . (string) $program['program_name']);
        $classId = institute_erp_ensure_legacy_class($erp, $className);
        $programId = (int) $program['id'];
        $terms = $erp->query('SELECT term_name FROM gr_degree_terms WHERE program_id = ' . $programId . ' AND status = 1 ORDER BY term_no ASC');
        if (!$terms) {
            continue;
        }
        while ($term = $terms->fetch_assoc()) {
            $sectionId = institute_erp_ensure_legacy_section($erp, (string) $term['term_name']);
            institute_erp_ensure_legacy_class_section($erp, $classId, $sectionId);
        }
    }
}

function institute_erp_sync_coaching_master_to_admission(mysqli $erp): void
{
    institute_erp_ensure_legacy_admission_tables($erp);
    $courses = $erp->query('SELECT id, course_code, course_name FROM gr_coaching_courses WHERE status = 1 ORDER BY id ASC');
    if (!$courses) {
        return;
    }

    while ($course = $courses->fetch_assoc()) {
        $className = trim((string) $course['course_code'] . ' - ' . (string) $course['course_name']);
        $classId = institute_erp_ensure_legacy_class($erp, $className);
        $courseId = (int) $course['id'];
        $batches = $erp->query('SELECT batch_name FROM gr_coaching_batches WHERE course_id = ' . $courseId . ' AND status = 1 ORDER BY id ASC');
        if (!$batches) {
            continue;
        }
        while ($batch = $batches->fetch_assoc()) {
            $sectionId = institute_erp_ensure_legacy_section($erp, (string) $batch['batch_name']);
            institute_erp_ensure_legacy_class_section($erp, $classId, $sectionId);
        }
    }
}

function institute_erp_ensure_legacy_admission_tables(mysqli $erp): void
{
    $erp->query("CREATE TABLE IF NOT EXISTS classes (
        id INT(11) NOT NULL AUTO_INCREMENT,
        class VARCHAR(60) NOT NULL,
        is_active VARCHAR(255) DEFAULT 'no',
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci");

    $erp->query("CREATE TABLE IF NOT EXISTS sections (
        id INT(11) NOT NULL AUTO_INCREMENT,
        section VARCHAR(60) NOT NULL,
        is_active VARCHAR(255) DEFAULT 'no',
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci");

    $erp->query("CREATE TABLE IF NOT EXISTS class_sections (
        id INT(11) NOT NULL AUTO_INCREMENT,
        class_id INT(11) NOT NULL,
        section_id INT(11) NOT NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY class_id (class_id),
        KEY section_id (section_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci");
}

function institute_erp_ensure_legacy_class(mysqli $erp, string $className): int
{
    $className = trim($className);
    if ($className === '') {
        return 0;
    }
    $stmt = $erp->prepare('SELECT id FROM classes WHERE class = ? LIMIT 1');
    $stmt->bind_param('s', $className);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    if ($row) {
        return (int) $row['id'];
    }
    $stmt = $erp->prepare("INSERT INTO classes (class, is_active) VALUES (?, 'yes')");
    $stmt->bind_param('s', $className);
    $stmt->execute();
    return (int) $erp->insert_id;
}

function institute_erp_ensure_legacy_section(mysqli $erp, string $sectionName): int
{
    $sectionName = trim($sectionName);
    if ($sectionName === '') {
        return 0;
    }
    $stmt = $erp->prepare('SELECT id FROM sections WHERE section = ? LIMIT 1');
    $stmt->bind_param('s', $sectionName);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    if ($row) {
        return (int) $row['id'];
    }
    $stmt = $erp->prepare("INSERT INTO sections (section, is_active) VALUES (?, 'yes')");
    $stmt->bind_param('s', $sectionName);
    $stmt->execute();
    return (int) $erp->insert_id;
}

function institute_erp_ensure_legacy_class_section(mysqli $erp, int $classId, int $sectionId): void
{
    if ($classId <= 0 || $sectionId <= 0) {
        return;
    }
    $stmt = $erp->prepare('SELECT id FROM class_sections WHERE class_id = ? AND section_id = ? LIMIT 1');
    $stmt->bind_param('ii', $classId, $sectionId);
    $stmt->execute();
    if ($stmt->get_result()->fetch_assoc()) {
        return;
    }
    $stmt = $erp->prepare('INSERT INTO class_sections (class_id, section_id) VALUES (?, ?)');
    $stmt->bind_param('ii', $classId, $sectionId);
    $stmt->execute();
}

function institute_erp_table_count(mysqli $erp, string $table): int
{
    if (!preg_match('/^[a-zA-Z0-9_]+$/', $table)) {
        return 0;
    }
    $exists = $erp->query("SHOW TABLES LIKE '" . $erp->real_escape_string($table) . "'");
    if (!$exists || $exists->num_rows === 0) {
        return 0;
    }
    $result = $erp->query('SELECT COUNT(*) AS total FROM `' . str_replace('`', '``', $table) . '`');
    return $result ? (int) ($result->fetch_assoc()['total'] ?? 0) : 0;
}

function institute_erp_seed_operational_demo_data(string $dbName): void
{
    if (!preg_match('/^[a-zA-Z0-9_]+$/', $dbName)) {
        throw new RuntimeException('Invalid ERP database name.');
    }

    $erp = new mysqli(DB_HOST, DB_USER, DB_PASS, $dbName);
    if ($erp->connect_errno) {
        throw new RuntimeException('Unable to connect ERP database: ' . $erp->connect_error);
    }
    $erp->set_charset('utf8');

    if (institute_erp_table_count($erp, 'room_types') === 0) {
        $erp->query("INSERT INTO room_types (room_type, description) VALUES
            ('Two Seater', 'Shared room with study table and wardrobe'),
            ('Four Seater', 'Economy hostel room for junior students')");
    }

    if (institute_erp_table_count($erp, 'hostel') === 0) {
        $erp->query("INSERT INTO hostel (hostel_name, type, address, intake, description, is_active) VALUES
            ('GyanRank Boys Residence', 'Boys', 'Knowledge Park Campus, Jaipur', 80, 'Demo hostel block for senior students.', 'yes'),
            ('GyanRank Girls Residence', 'Girls', 'Knowledge Park Campus, Jaipur', 80, 'Demo hostel block with supervised study area.', 'yes')");
    }

    if (institute_erp_table_count($erp, 'hostel_rooms') === 0) {
        $hostel = $erp->query('SELECT id FROM hostel ORDER BY id ASC LIMIT 1')->fetch_assoc();
        $roomType = $erp->query('SELECT id FROM room_types ORDER BY id ASC LIMIT 1')->fetch_assoc();
        $hostelId = (int) ($hostel['id'] ?? 0);
        $roomTypeId = (int) ($roomType['id'] ?? 0);
        if ($hostelId > 0 && $roomTypeId > 0) {
            $erp->query("INSERT INTO hostel_rooms (hostel_id, room_type_id, room_no, no_of_bed, cost_per_bed, title, description) VALUES
                ({$hostelId}, {$roomTypeId}, 'A-101', 2, 4500.00, 'Senior Wing A-101', 'Demo room with two beds.'),
                ({$hostelId}, {$roomTypeId}, 'A-102', 2, 4500.00, 'Senior Wing A-102', 'Demo room with two beds.')");
        }
    }

    if (institute_erp_table_count($erp, 'transport_route') === 0) {
        $erp->query("INSERT INTO transport_route (route_title, no_of_vehicle, fare, note, is_active) VALUES
            ('Tonk Road - Campus', 1, 1800.00, 'Morning pickup and afternoon drop route.', 'yes'),
            ('Mansarovar - Campus', 1, 2200.00, 'Demo city route for transport module.', 'yes')");
    }

    if (institute_erp_table_count($erp, 'vehicles') === 0) {
        $erp->query("INSERT INTO vehicles (vehicle_no, vehicle_model, manufacture_year, driver_name, driver_licence, driver_contact, note) VALUES
            ('RJ14-GR-2026', 'Tata Starbus', '2022', 'Mahesh Kumar', 'RJ142026001', '9876543221', 'Demo school bus'),
            ('RJ14-GR-2027', 'Force Traveller', '2021', 'Sandeep Singh', 'RJ142026002', '9876543222', 'Demo van route')");
    }

    if (institute_erp_table_count($erp, 'vehicle_routes') === 0) {
        $route = $erp->query('SELECT id FROM transport_route ORDER BY id ASC LIMIT 1')->fetch_assoc();
        $vehicle = $erp->query('SELECT id FROM vehicles ORDER BY id ASC LIMIT 1')->fetch_assoc();
        $routeId = (int) ($route['id'] ?? 0);
        $vehicleId = (int) ($vehicle['id'] ?? 0);
        if ($routeId > 0 && $vehicleId > 0) {
            $erp->query("INSERT INTO vehicle_routes (route_id, vehicle_id) VALUES ({$routeId}, {$vehicleId})");
        }
    }

    if (institute_erp_table_count($erp, 'books') === 0) {
        $today = date('Y-m-d');
        $erp->query("INSERT INTO books (book_title, book_no, isbn_no, subject, rack_no, publish, author, qty, perunitcost, postdate, description, available, is_active) VALUES
            ('Mathematics Practice Companion', 'GR-LIB-001', '9789350000011', 'Mathematics', 'R1-A', 'GyanRank Academic', 'R. K. Sharma', 15, 350.00, '{$today}', 'Class 10 practice reference.', 'yes', 'yes'),
            ('Science Lab Manual', 'GR-LIB-002', '9789350000012', 'Science', 'R1-B', 'GyanRank Academic', 'Dr. Meera Nair', 12, 420.00, '{$today}', 'Practical science experiments.', 'yes', 'yes'),
            ('English Grammar Workbook', 'GR-LIB-003', '9789350000013', 'English', 'R2-A', 'GyanRank Academic', 'Anita Joseph', 20, 280.00, '{$today}', 'Grammar and writing practice.', 'yes', 'yes')");
    }

    if (institute_erp_table_count($erp, 'onlineexam') === 0) {
        $session = $erp->query("SELECT id FROM sessions WHERE is_active = 'yes' ORDER BY id DESC LIMIT 1")->fetch_assoc();
        if (!$session) {
            $session = $erp->query('SELECT id FROM sessions ORDER BY id DESC LIMIT 1')->fetch_assoc();
        }
        $sessionId = (int) ($session['id'] ?? 0);
        if ($sessionId <= 0) {
            $erp->query("INSERT INTO sessions (session, is_active) VALUES ('2026-27', 'yes')");
            $sessionId = (int) $erp->insert_id;
        }
        $from = date('Y-m-d 09:00:00', strtotime('+2 days'));
        $to = date('Y-m-d 18:00:00', strtotime('+2 days'));
        $auto = date('Y-m-d 19:00:00', strtotime('+2 days'));
        $erp->query("INSERT INTO onlineexam
            (exam, attempt, exam_from, exam_to, is_quiz, auto_publish_date, time_from, time_to, duration, passing_percentage, description, session_id, publish_result, is_active, is_marks_display, is_neg_marking, is_random_question, is_rank_generated, publish_exam_notification, publish_result_notification)
            VALUES ('Class 10 Demo Assessment', 1, '{$from}', '{$to}', 0, '{$auto}', '09:00:00', '18:00:00', '01:00:00', 40, 'Demo online exam for GyanRank ERP presentation.', {$sessionId}, 1, '1', 1, 0, 0, 0, 1, 1)");
    }

    $erp->close();
}

function institute_erp_seed_default_admin(string $dbName, string $institutionName): void
{
    $erp = new mysqli(DB_HOST, DB_USER, DB_PASS, $dbName);
    $erp->set_charset('utf8');

    $exists = $erp->query("SELECT id FROM staff WHERE email = 'admin@gyanrank.test' LIMIT 1");
    if ($exists && $exists->num_rows > 0) {
        $erp->close();
        return;
    }

    $passwordHash = password_hash('Admin@12345', PASSWORD_DEFAULT);
    $name = trim($institutionName) !== '' ? substr($institutionName, 0, 190) : 'Gyan Rank Admin';
    $surname = 'Admin';
    $email = 'admin@gyanrank.test';
    $employeeId = 'GRADMIN';
    $langId = 4;
    $isActive = 1;

    $stmt = $erp->prepare("INSERT INTO staff
        (employee_id, lang_id, name, surname, email, password, gender, is_active, date_of_joining)
        VALUES (?, ?, ?, ?, ?, ?, 'Male', ?, CURDATE())");
    $stmt->bind_param('sissssi', $employeeId, $langId, $name, $surname, $email, $passwordHash, $isActive);
    $stmt->execute();
    $staffId = (int) $erp->insert_id;

    $roleId = 7;
    $stmt = $erp->prepare("INSERT INTO staff_roles (role_id, staff_id, is_active) VALUES (?, ?, 1)");
    $stmt->bind_param('ii', $roleId, $staffId);
    $stmt->execute();

    $erp->close();
}

function institute_erp_write_tenant_database_config(string $tenantPath, string $dbName): void
{
    $file = $tenantPath . DIRECTORY_SEPARATOR . 'application' . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'database.php';
    $content = "<?php\n\ndefined('BASEPATH') OR exit('No direct script access allowed');\n\n";
    $content .= "\$active_group = 'default';\n\$query_builder = TRUE;\n\n";
    $content .= "\$db['default'] = array(\n";
    $content .= "    'dsn' => '',\n";
    $content .= "    'hostname' => '" . addslashes(DB_HOST) . "',\n";
    $content .= "    'username' => '" . addslashes(DB_USER) . "',\n";
    $content .= "    'password' => '" . addslashes(DB_PASS) . "',\n";
    $content .= "    'database' => '" . addslashes($dbName) . "',\n";
    $content .= "    'dbdriver' => 'mysqli',\n";
    $content .= "    'dbprefix' => '',\n";
    $content .= "    'pconnect' => FALSE,\n";
    $content .= "    'db_debug' => (ENVIRONMENT !== 'production'),\n";
    $content .= "    'cache_on' => FALSE,\n";
    $content .= "    'cachedir' => '',\n";
    $content .= "    'char_set' => 'utf8',\n";
    $content .= "    'dbcollat' => 'utf8_general_ci',\n";
    $content .= "    'swap_pre' => '',\n";
    $content .= "    'encrypt' => FALSE,\n";
    $content .= "    'compress' => FALSE,\n";
    $content .= "    'stricton' => FALSE,\n";
    $content .= "    'failover' => array(),\n";
    $content .= "    'save_queries' => TRUE\n";
    $content .= ");\n";
    if (file_put_contents($file, $content) === false) {
        throw new RuntimeException('Unable to write ERP database config.');
    }
}

function institute_erp_write_tenant_parent_config(string $tenantPath, int $accountId, int $tenantId, string $templateType = 'school'): void
{
    $file = $tenantPath . DIRECTORY_SEPARATOR . 'application' . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'gyanrank.php';
    $content = "<?php\n\n";
    $content .= "defined('BASEPATH') OR exit('No direct script access allowed');\n\n";
    $content .= "\$config['gyanrank_parent_db'] = array(\n";
    $content .= "    'hostname' => '" . addslashes(DB_HOST) . "',\n";
    $content .= "    'username' => '" . addslashes(DB_USER) . "',\n";
    $content .= "    'password' => '" . addslashes(DB_PASS) . "',\n";
    $content .= "    'database' => '" . addslashes(DB_NAME) . "',\n";
    $content .= ");\n";
    $content .= "\$config['gyanrank_institution_account_id'] = " . $accountId . ";\n";
    $content .= "\$config['gyanrank_tenant_id'] = " . $tenantId . ";\n";
    $content .= "\$config['gyanrank_erp_template_type'] = '" . addslashes($templateType) . "';\n";
    if (file_put_contents($file, $content) === false) {
        throw new RuntimeException('Unable to write Gyan Rank tenant config.');
    }
}

function institute_erp_write_tenant_installed_config(string $tenantPath): void
{
    $file = $tenantPath . DIRECTORY_SEPARATOR . 'application' . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'config.php';
    $content = file_get_contents($file);
    if ($content === false) {
        throw new RuntimeException('Unable to read ERP config.');
    }
    $content = str_replace("\$config['installed'] = false;", "\$config['installed'] = true;", $content);
    $content = str_replace("\$config['base_url'] = '';", "\$config['base_url'] = '';", $content);
    if (file_put_contents($file, $content) === false) {
        throw new RuntimeException('Unable to write ERP installed config.');
    }
}

function institute_erp_disable_tenant_install_folder(string $tenantPath): void
{
    $install = $tenantPath . DIRECTORY_SEPARATOR . 'application' . DIRECTORY_SEPARATOR . 'controllers' . DIRECTORY_SEPARATOR . 'install';
    $disabled = $tenantPath . DIRECTORY_SEPARATOR . 'application' . DIRECTORY_SEPARATOR . 'controllers' . DIRECTORY_SEPARATOR . '_install_disabled';
    if (is_dir($install) && !is_dir($disabled) && !rename($install, $disabled)) {
        throw new RuntimeException('Unable to disable tenant install folder.');
    }
}
