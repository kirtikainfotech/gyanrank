<?php
declare(strict_types=1);

date_default_timezone_set('Asia/Kolkata');

if (is_file(__DIR__ . '/.env')) {
    foreach (file(__DIR__ . '/.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim((string) $line);
        if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
            continue;
        }
        [$key, $value] = array_map('trim', explode('=', $line, 2));
        if ($key !== '' && getenv($key) === false) {
            putenv($key . '=' . trim($value, "\"'"));
        }
    }
}

define('APP_NAME', 'Gyan Nexa');
define('APP_BASE', app_base_path());
define('DB_HOST', env_config_value('DB_HOST'));
define('DB_USER', env_config_value('DB_USER'));
define('DB_PASS', env_config_value('DB_PASS'));
define('DB_NAME', env_config_value('DB_NAME'));
define('UPLOAD_BASE', 'uploads/settings');

send_security_headers();
enforce_maintenance_mode();

function env_config_value(string $key): string
{
    $env = getenv($key);
    if ($env !== false) {
        return (string) $env;
    }

    throw new RuntimeException('Missing required environment variable: ' . $key);
}

function app_base_path(): string
{
    $envBase = getenv('APP_BASE');
    if (is_string($envBase) && trim($envBase) !== '') {
        return rtrim('/' . trim($envBase, '/'), '/');
    }

    $host = strtolower((string) ($_SERVER['HTTP_HOST'] ?? ''));
    $isLocalHost =
        $host === ''
        || strpos($host, 'localhost') !== false
        || strpos($host, '127.0.0.1') !== false
        || preg_match('/^(192\.168\.|10\.|172\.(1[6-9]|2[0-9]|3[0-1])\.)/', $host);

    $scriptName = (string) ($_SERVER['SCRIPT_NAME'] ?? '');
    if ($isLocalHost) {
        if (preg_match('#^(/edu)(?:/|$)#i', $scriptName, $matches)) {
            return rtrim($matches[1], '/');
        }

        return '/edu';
    }

    return '';
}

if (!function_exists('str_contains')) {
    function str_contains(string $haystack, string $needle): bool
    {
        return $needle === '' || strpos($haystack, $needle) !== false;
    }
}

if (!function_exists('str_starts_with')) {
    function str_starts_with(string $haystack, string $needle): bool
    {
        return $needle === '' || strncmp($haystack, $needle, strlen($needle)) === 0;
    }
}

if (!function_exists('str_ends_with')) {
    function str_ends_with(string $haystack, string $needle): bool
    {
        if ($needle === '') {
            return true;
        }
        return substr($haystack, -strlen($needle)) === $needle;
    }
}

function db(): mysqli
{
    static $conn = null;

    if ($conn instanceof mysqli) {
        return $conn;
    }

    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    $conn->set_charset('utf8mb4');

    return $conn;
}

function start_secure_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'httponly' => true,
        'samesite' => 'Lax',
        'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
    ]);

    session_start();
}

function send_security_headers(): void
{
    if (headers_sent()) {
        return;
    }

    header('X-Frame-Options: DENY');
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header("Permissions-Policy: camera=(), microphone=(), geolocation=()");
    header("Content-Security-Policy: default-src 'self'; style-src 'self' 'unsafe-inline'; script-src 'self' 'unsafe-inline'; img-src 'self' data:; base-uri 'self'; form-action 'self'; frame-ancestors 'none'");

    if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
        header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
    }
}

function enforce_maintenance_mode(): void
{
    $flag = __DIR__ . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'maintenance.flag';
    if (!is_file($flag) || PHP_SAPI === 'cli') {
        return;
    }

    $uri = (string) ($_SERVER['REQUEST_URI'] ?? '');
    if (strpos($uri, '/assets/') !== false || strpos($uri, '/uploads/') !== false) {
        return;
    }

    http_response_code(503);
    header('Retry-After: 600');
    header('Content-Type: text/html; charset=UTF-8');
    echo '<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Maintenance</title><style>body{margin:0;min-height:100vh;display:grid;place-items:center;background:#f4f7fb;color:#06345f;font-family:Arial,sans-serif}.box{max-width:560px;padding:32px;border-top:4px solid #f68a00;background:#fff;box-shadow:0 16px 40px rgba(6,52,95,.14);border-radius:8px}h1{margin:0 0 10px;font-size:28px}p{margin:0;color:#52677a;line-height:1.55}</style></head><body><main class="box"><h1>Gyan Nexa is under maintenance</h1><p>We are applying updates. Please try again shortly.</p></main></body></html>';
    exit;
}

function redirect(string $path)
{
    header('Location: ' . app_url($path));
    exit;
}

function h(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function app_url(string $path = ''): string
{
    $rawPath = trim($path, '/');
    $hash = '';
    if (str_contains($rawPath, '#')) {
        [$rawPath, $hash] = explode('#', $rawPath, 2);
        $hash = '#' . $hash;
    }
    $query = '';
    if (str_contains($rawPath, '?')) {
        [$rawPath, $query] = explode('?', $rawPath, 2);
        $query = '?' . $query;
    }

    $cleanPath = preg_replace('#\.php$#i', '', $rawPath) ?? $rawPath;
    $path = '/' . ltrim($cleanPath, '/');
    $url = rtrim(APP_BASE, '/') . ($path === '/' ? '' : $path);
    return $url . $query . $hash;
}

function current_user(): ?array
{
    start_secure_session();
    return $_SESSION['user'] ?? null;
}

function current_user_permissions(?array $user = null): array
{
    $user = $user ?? current_user();
    if (!$user) {
        return [];
    }
    if (($user['role'] ?? '') === 'superadmin') {
        return ['*'];
    }
    try {
        $stmt = db()->prepare("SELECT rp.permission_key
            FROM role_permissions rp
            INNER JOIN roles r ON r.id = rp.role_id
            WHERE r.slug = ?");
        $role = (string) ($user['role'] ?? '');
        $stmt->bind_param('s', $role);
        $stmt->execute();
        return array_column($stmt->get_result()->fetch_all(MYSQLI_ASSOC), 'permission_key');
    } catch (Throwable $e) {
        return [];
    }
}

function current_user_can(string $permission, ?array $user = null): bool
{
    $permissions = current_user_permissions($user);
    return in_array('*', $permissions, true) || in_array($permission, $permissions, true);
}

function require_permission(string $permission): array
{
    $user = require_login();
    if (!current_user_can($permission, $user)) {
        http_response_code(403);
        exit('Access denied');
    }
    return $user;
}

function default_settings(): array
{
    return [
        'site_name' => APP_NAME,
        'site_tagline' => 'Secure Online Learning Management System',
        'site_email' => 'admin@example.com',
        'site_phone' => '',
        'site_address' => '',
        'support_call_number' => '',
        'support_whatsapp_number' => '',
        'support_email' => '',
        'google_login_enabled' => '0',
        'google_client_id' => '',
        'google_client_secret' => '',
        'google_redirect_uri' => '',
        'footer_title' => APP_NAME,
        'footer_line' => 'License based annual renewal system.',
        'copyright_text' => '© 2026 Gyan Nexa. All rights reserved.',
        'logo_path' => 'assets/grlogo.png',
        'app_logo_path' => 'assets/applogo.png',
        'app_icon_path' => 'assets/applogo.png',
        'website_logo_path' => 'assets/grlogo.png',
        'favicon_path' => 'assets/applogo.png',
        'facebook_url' => '',
        'instagram_url' => '',
        'youtube_url' => '',
        'playstore_url' => '',
        'instructor_referral_commission_type' => 'percent',
        'instructor_referral_commission_value' => '10',
        'user_referral_commission_type' => 'fixed',
        'user_referral_commission_value' => '100',
        'gcoin_enabled' => '1',
        'gcoin_name' => 'Gcoin',
        'gcoin_signup_referrer_reward' => '100',
        'gcoin_signup_joiner_reward' => '50',
        'gcoin_purchase_referrer_reward_type' => 'percent',
        'gcoin_purchase_referrer_reward_value' => '5',
        'gcoin_per_inr' => '10',
        'gcoin_min_redeem' => '10',
        'gcoin_purchase_redeem_enabled' => '1',
        'mail_driver' => 'smtp',
        'mail_host' => '',
        'mail_port' => '587',
        'mail_username' => '',
        'mail_password' => '',
        'mail_encryption' => 'tls',
        'mail_from_email' => '',
        'mail_from_name' => APP_NAME,
        'firebase_enabled' => '0',
        'firebase_project_id' => '',
        'firebase_api_key' => '',
        'firebase_auth_domain' => '',
        'firebase_storage_bucket' => '',
        'firebase_messaging_sender_id' => '',
        'firebase_app_id' => '',
        'firebase_vapid_key' => '',
        'firebase_server_key' => '',
        'invoice_prefix' => 'GR',
        'invoice_format' => '{PREFIX}/{FY}/{STATE}/{NO}',
        'invoice_starting_no' => '1',
        'invoice_current_no' => '1',
        'financial_year_start' => '2026-04-01',
        'financial_year_close_reset' => '1',
        'invoice_footer' => 'Thank you for learning with us.',
        'invoice_terms' => 'Fees once paid are non-refundable unless approved by management.',
        'invoice_thank_you_note' => 'Thank you for choosing us.',
        'gst_number' => '',
        'billing_address' => '',
        'billing_state_code' => '09',
        'billing_state_name' => 'Uttar Pradesh',
        'default_supply_state_code' => '09',
        'tax_rate' => '18',
        'currency' => 'INR',
        'currency_symbol' => '₹',
        'phonepe_enabled' => '0',
        'phonepe_environment' => 'sandbox',
        'phonepe_merchant_id' => '',
        'phonepe_salt_key' => '',
        'phonepe_salt_index' => '1',
        'phonepe_client_id' => '',
        'phonepe_client_secret' => '',
        'phonepe_client_version' => '1',
        'email_template_header' => 'Welcome to {site_name}',
        'email_template_footer' => 'Regards, {site_name}',
        'email_welcome_enabled' => '1',
        'email_welcome_subject' => 'Welcome to {site_name}, {user_name}',
        'email_welcome_body' => '<h2>Welcome to {site_name}</h2><p>Hello {user_name}, your account is ready. You can login and continue your learning journey.</p><p><a href="{login_url}">Login to your account</a></p>',
        'email_referral_enabled' => '1',
        'email_referral_subject' => '{user_name} invited you to {site_name}',
        'email_referral_body' => '<h2>You are invited</h2><p>{user_name} has referred you to join {site_name}. Use referral code <strong>{referral_code}</strong> while signup.</p><p><a href="{signup_url}">Create your account</a></p>',
        'email_forgot_password_enabled' => '1',
        'email_forgot_password_subject' => 'Reset your {site_name} password',
        'email_forgot_password_body' => '<h2>Password reset request</h2><p>Hello {user_name}, click the button below to reset your password. This link will expire soon.</p><p><a href="{reset_url}">Reset Password</a></p>',
        'email_verification_enabled' => '1',
        'email_verification_subject' => 'Verify your email for {site_name}',
        'email_verification_body' => '<h2>Verify your email</h2><p>Hello {user_name}, please verify your email to activate your account.</p><p><a href="{verification_url}">Verify Email</a></p>',
        'email_referral_signup_enabled' => '1',
        'email_referral_signup_subject' => 'Referral signup received',
        'email_referral_signup_body' => '<h2>New referral signup</h2><p>Hello {user_name}, a new user signed up using your referral code <strong>{referral_code}</strong>.</p>',
        'email_instructor_signup_enabled' => '1',
        'email_instructor_signup_subject' => 'Instructor signup submitted',
        'email_instructor_signup_body' => '<h2>Instructor application received</h2><p>Hello {instructor_name}, your instructor signup request has been submitted. Our academic team will review it shortly.</p>',
        'email_instructor_approval_enabled' => '1',
        'email_instructor_approval_subject' => 'Instructor account approved',
        'email_instructor_approval_body' => '<h2>You are approved</h2><p>Hello {instructor_name}, your instructor account has been approved. You can now access your instructor panel.</p><p><a href="{login_url}">Open Instructor Panel</a></p>',
        'email_student_enrollment_enabled' => '1',
        'email_student_enrollment_subject' => 'Course enrollment confirmed',
        'email_student_enrollment_body' => '<h2>Enrollment confirmed</h2><p>Hello {user_name}, you have been enrolled in <strong>{course_name}</strong>.</p><p><a href="{course_url}">Start Learning</a></p>',
        'email_payment_success_enabled' => '1',
        'email_payment_success_subject' => 'Payment received - Invoice {invoice_no}',
        'email_payment_success_body' => '<h2>Payment received</h2><p>Hello {user_name}, we have received your payment of <strong>{amount}</strong>.</p><p>Invoice No: <strong>{invoice_no}</strong></p>',
        'email_payment_failed_enabled' => '1',
        'email_payment_failed_subject' => 'Payment failed - {item_name}',
        'email_payment_failed_body' => '<h2>Payment failed</h2><p>Hello {user_name}, your payment for <strong>{item_name}</strong> was not completed.</p><p>You can retry from your transactions page.</p>',
        'email_membership_activated_enabled' => '1',
        'email_membership_activated_subject' => 'Membership activated - {plan_name}',
        'email_membership_activated_body' => '<h2>Membership activated</h2><p>Hello {user_name}, your <strong>{plan_name}</strong> plan is active.</p><p>Transaction: <strong>{transaction_id}</strong></p>',
        'email_profile_updated_enabled' => '1',
        'email_profile_updated_subject' => 'Profile updated - {site_name}',
        'email_profile_updated_body' => '<h2>Profile updated</h2><p>Hello {user_name}, your profile details were updated successfully.</p>',
        'email_password_changed_enabled' => '1',
        'email_password_changed_subject' => 'Password changed - {site_name}',
        'email_password_changed_body' => '<h2>Password changed</h2><p>Hello {user_name}, your password was changed successfully. If this was not you, please contact support immediately.</p>',
        'email_new_signup_enabled' => '1',
        'email_new_signup_subject' => 'New learner signup - {site_name}',
        'email_new_signup_body' => '<h2>New learner signup</h2><p><strong>{student_name}</strong> joined {site_name}.</p><p>Email: {student_email}<br>Phone: {student_phone}</p>',
        'email_admin_payment_success_enabled' => '1',
        'email_admin_payment_success_subject' => 'Payment received - {item_name}',
        'email_admin_payment_success_body' => '<h2>Payment received</h2><p>{student_name} paid <strong>{amount}</strong> for <strong>{item_name}</strong>.</p><p>Invoice: {invoice_no}<br>Transaction: {transaction_id}</p>',
        'email_admin_payment_failed_enabled' => '1',
        'email_admin_payment_failed_subject' => 'Payment failed - {item_name}',
        'email_admin_payment_failed_body' => '<h2>Payment failed</h2><p>{student_name} could not complete payment for <strong>{item_name}</strong>.</p><p>Transaction: {transaction_id}</p>',
        'email_license_renewal_enabled' => '1',
        'email_license_renewal_subject' => 'License renewal reminder',
        'email_license_renewal_body' => '<h2>Renewal reminder</h2><p>Your {site_name} license will expire on <strong>{expiry_date}</strong>. Please renew to keep all services active.</p><p><a href="{renewal_url}">Renew License</a></p>',
        'email_license_expired_enabled' => '1',
        'email_license_expired_subject' => 'License expired - action required',
        'email_license_expired_body' => '<h2>License expired</h2><p>Your license expired on <strong>{expiry_date}</strong>. System features are paused until renewal is completed.</p>',
        'email_support_assigned_enabled' => '1',
        'email_support_assigned_subject' => 'Support staff assigned',
        'email_support_assigned_body' => '<h2>Support assigned</h2><p>Hello {user_name}, <strong>{support_name}</strong> has been assigned as your support contact.</p><p>Contact: {support_email}</p>',
        'instructor_commission_type' => 'percent',
        'instructor_commission_value' => '40',
        'default_role' => 'student',
        'default_instructor_support_role' => 'support-manager',
        'default_student_support_role' => 'support-staff',
        'support_assignment_mode' => 'manual',
        'support_auto_assign_enabled' => '1',
        'max_students_per_support' => '80',
        'max_instructors_per_support' => '15',
        'support_escalation_hours' => '24',
        'salary_cycle' => 'monthly',
        'salary_pay_day' => '7',
        'probation_days' => '90',
        'monthly_paid_leaves' => '2',
        'attendance_mode' => 'daily',
        'attendance_grace_minutes' => '10',
        'half_day_after_minutes' => '240',
        'report_approval_flow' => 'manager',
        'report_timezone' => 'Asia/Kolkata',
    ];
}

function all_settings(): array
{
    static $settings = null;

    if (is_array($settings)) {
        return $settings;
    }

    $settings = default_settings();

    try {
        $result = db()->query('SELECT setting_key, setting_value FROM system_settings');
        while ($row = $result->fetch_assoc()) {
            $settings[$row['setting_key']] = (string) $row['setting_value'];
        }
    } catch (Throwable $e) {
        return $settings;
    }

    return $settings;
}

function app_setting(string $key, ?string $fallback = null): string
{
    $settings = all_settings();
    return (string) ($settings[$key] ?? $fallback ?? '');
}

function app_name(): string
{
    return app_setting('site_name', APP_NAME);
}

function asset_or_default(string $key): string
{
    $path = app_setting($key);
    return $path !== '' ? app_url($path) : '';
}

function save_setting(string $key, string $value): void
{
    $conn = db();
    $stmt = $conn->prepare("
        INSERT INTO system_settings (setting_key, setting_value)
        VALUES (?, ?)
        ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)
    ");
    $stmt->bind_param('ss', $key, $value);
    $stmt->execute();
}

function store_uploaded_setting_file(string $field, array $allowedMimeTypes): ?string
{
    if (empty($_FILES[$field]['tmp_name']) || ($_FILES[$field]['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return null;
    }

    if (($_FILES[$field]['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Upload failed. Please try again.');
    }

    if ((int) ($_FILES[$field]['size'] ?? 0) > 1024 * 1024) {
        throw new RuntimeException('File size must be below 1 MB.');
    }

    $tmp = (string) $_FILES[$field]['tmp_name'];
    $mime = mime_content_type($tmp) ?: '';

    if (!isset($allowedMimeTypes[$mime])) {
        throw new RuntimeException('Invalid file type uploaded.');
    }

    $targetDir = __DIR__ . DIRECTORY_SEPARATOR . UPLOAD_BASE;
    if (!is_dir($targetDir)) {
        mkdir($targetDir, 0755, true);
    }

    $name = $field . '-' . bin2hex(random_bytes(8)) . '.' . $allowedMimeTypes[$mime];
    $target = $targetDir . DIRECTORY_SEPARATOR . $name;

    if (!move_uploaded_file($tmp, $target)) {
        throw new RuntimeException('Unable to save uploaded file.');
    }

    return UPLOAD_BASE . '/' . $name;
}

function require_login(?string $role = null): array
{
    $user = current_user();

    if (!$user) {
        redirect('login');
    }

    if ($role !== null && ($user['role'] ?? '') !== $role) {
        http_response_code(403);
        exit('Access denied');
    }

    return $user;
}

function ensure_users_phone_column(): void
{
    try {
        $result = db()->query("SHOW COLUMNS FROM users LIKE 'phone'");
        if ($result->num_rows === 0) {
            db()->query("ALTER TABLE users ADD COLUMN phone VARCHAR(30) DEFAULT NULL AFTER email");
        }
    } catch (Throwable $e) {
        // Older installs can still login by username/email; setup pages will surface DB issues separately.
    }
}

function dashboard_path_for_role(string $role): string
{
    switch ($role) {
        case 'superadmin':
            return 'sadmin/dashboard';
        case 'instructor':
            return 'ins/dashboard';
        case 'operations-manager':
        case 'erp-support-manager':
        case 'support-manager':
        case 'support-staff':
        case 'calling-team':
        case 'calling-executive':
        case 'sales-executive':
        case 'billing-executive':
        case 'academic-coordinator':
            return 'staff-dashboard';
        default:
            return 'dashboard';
    }
}

function is_license_active(): bool
{
    $conn = db();
    $stmt = $conn->prepare("SELECT status, expires_at FROM licenses WHERE product_name = ? ORDER BY id DESC LIMIT 1");
    $product = APP_NAME;
    $stmt->bind_param('s', $product);
    $stmt->execute();
    $license = $stmt->get_result()->fetch_assoc();

    if (!$license) {
        return false;
    }

    return $license['status'] === 'active' && strtotime($license['expires_at']) >= strtotime(date('Y-m-d'));
}

function csrf_token(): string
{
    start_secure_session();

    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

function verify_csrf(?string $token): bool
{
    start_secure_session();
    return is_string($token) && hash_equals($_SESSION['csrf_token'] ?? '', $token);
}

function client_ip(): string
{
    return substr((string) ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0'), 0, 45);
}

function login_rate_limited(string $username, string $ip): bool
{
    $conn = db();
    $windowStart = date('Y-m-d H:i:s', time() - 900);
    $stmt = $conn->prepare("
        SELECT COUNT(*) AS attempts
        FROM login_attempts
        WHERE success = 0
          AND attempted_at >= ?
          AND (ip_address = ? OR username = ?)
    ");
    $stmt->bind_param('sss', $windowStart, $ip, $username);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();

    return (int) ($row['attempts'] ?? 0) >= 5;
}

function record_login_attempt(string $username, string $ip, bool $success): void
{
    $conn = db();
    $stmt = $conn->prepare("INSERT INTO login_attempts (username, ip_address, success) VALUES (?, ?, ?)");
    $successValue = $success ? 1 : 0;
    $stmt->bind_param('ssi', $username, $ip, $successValue);
    $stmt->execute();
}

function clear_failed_login_attempts(string $username, string $ip): void
{
    $conn = db();
    $stmt = $conn->prepare("DELETE FROM login_attempts WHERE success = 0 AND (username = ? OR ip_address = ?)");
    $stmt->bind_param('ss', $username, $ip);
    $stmt->execute();
}

function ensure_audit_log_table(): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;

    try {
        db()->query("CREATE TABLE IF NOT EXISTS audit_logs (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id BIGINT UNSIGNED NULL,
            user_role VARCHAR(50) NULL,
            action VARCHAR(120) NOT NULL,
            entity_type VARCHAR(80) NULL,
            entity_id VARCHAR(80) NULL,
            ip_address VARCHAR(45) NULL,
            user_agent VARCHAR(255) NULL,
            metadata_json JSON NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY audit_logs_action_index (action),
            KEY audit_logs_user_index (user_id),
            KEY audit_logs_entity_index (entity_type, entity_id),
            KEY audit_logs_created_index (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    } catch (Throwable $e) {
        // Audit logging must never break the primary workflow.
    }
}

function audit_log(string $action, ?string $entityType = null, ?string $entityId = null, array $metadata = [], ?array $user = null): void
{
    try {
        ensure_audit_log_table();
        $user = $user ?? current_user();
        $userId = isset($user['id']) ? (int) $user['id'] : null;
        $role = isset($user['role']) ? substr((string) $user['role'], 0, 50) : null;
        $ip = client_ip();
        $agent = substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? 'cli'), 0, 255);
        $metadataJson = $metadata ? json_encode($metadata, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : null;
        $stmt = db()->prepare("INSERT INTO audit_logs
            (user_id, user_role, action, entity_type, entity_id, ip_address, user_agent, metadata_json)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param('isssssss', $userId, $role, $action, $entityType, $entityId, $ip, $agent, $metadataJson);
        $stmt->execute();
    } catch (Throwable $e) {
        // Keep production actions usable even if audit storage is temporarily unavailable.
    }
}

function role_permission_groups(): array
{
    return [
        'Academic' => [
            'courses.view' => 'View courses',
            'courses.manage' => 'Manage courses',
            'classes.manage' => 'Manage live classes',
            'exams.manage' => 'Manage tests/exams',
            'certificates.manage' => 'Certificates',
        ],
        'People' => [
            'students.view' => 'View students',
            'students.manage' => 'Manage students',
            'instructors.view' => 'View instructors',
            'instructors.manage' => 'Manage instructors',
            'staff.manage' => 'Manage staff',
        ],
        'Operations' => [
            'support.manage' => 'Support assignment',
            'calling.manage' => 'Calling team',
            'assignments.manage' => 'Institute assignments',
            'leads.manage' => 'Sales leads',
            'attendance.manage' => 'Attendance',
            'salary.manage' => 'Salary',
            'reports.view' => 'Reports',
        ],
        'Finance' => [
            'fees.manage' => 'Fees',
            'invoices.manage' => 'Invoices',
            'tax.manage' => 'Tax/GST',
            'commission.manage' => 'Commission',
        ],
        'System' => [
            'roles.manage' => 'Roles & permissions',
            'settings.manage' => 'System settings',
            'license.manage' => 'License',
        ],
    ];
}

function role_slug(string $name): string
{
    $slug = strtolower(trim(preg_replace('/[^a-zA-Z0-9]+/', '-', $name), '-'));
    return substr($slug !== '' ? $slug : 'role', 0, 80);
}

function fetch_roles(): array
{
    try {
        $result = db()->query("
            SELECT r.id, r.parent_id, r.name, r.slug, r.role_group, r.description, r.is_system,
                   GROUP_CONCAT(rp.permission_key ORDER BY rp.permission_key SEPARATOR ',') AS permissions
            FROM roles r
            LEFT JOIN role_permissions rp ON rp.role_id = r.id
            GROUP BY r.id, r.parent_id, r.name, r.slug, r.role_group, r.description, r.is_system
            ORDER BY COALESCE(r.parent_id, r.id), r.parent_id IS NOT NULL, r.name
        ");
        return $result->fetch_all(MYSQLI_ASSOC);
    } catch (Throwable $e) {
        return [];
    }
}

function save_role_with_permissions(?int $roleId, string $name, ?int $parentId, string $roleGroup, string $description, array $permissions): void
{
    $conn = db();
    $slug = role_slug($name);

    if ($roleId !== null && $roleId > 0) {
        $stmt = $conn->prepare("UPDATE roles SET parent_id = ?, name = ?, role_group = ?, description = ? WHERE id = ?");
        $stmt->bind_param('isssi', $parentId, $name, $roleGroup, $description, $roleId);
        $stmt->execute();
    } else {
        $stmt = $conn->prepare("
            INSERT INTO roles (parent_id, name, slug, role_group, description, is_system)
            VALUES (?, ?, ?, ?, ?, 0)
            ON DUPLICATE KEY UPDATE parent_id = VALUES(parent_id), name = VALUES(name), role_group = VALUES(role_group), description = VALUES(description)
        ");
        $stmt->bind_param('issss', $parentId, $name, $slug, $roleGroup, $description);
        $stmt->execute();
        $roleId = (int) ($conn->insert_id ?: $conn->query("SELECT id FROM roles WHERE slug = '" . $conn->real_escape_string($slug) . "' LIMIT 1")->fetch_assoc()['id']);
    }

    $delete = $conn->prepare('DELETE FROM role_permissions WHERE role_id = ?');
    $delete->bind_param('i', $roleId);
    $delete->execute();

    $insert = $conn->prepare('INSERT INTO role_permissions (role_id, permission_key) VALUES (?, ?)');
    foreach (array_unique($permissions) as $permission) {
        $permission = substr((string) $permission, 0, 120);
        $insert->bind_param('is', $roleId, $permission);
        $insert->execute();
    }
}
