<?php
declare(strict_types=1);

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../ins/includes/functions.php';
require_once __DIR__ . '/../includes/live_streaming.php';
require_once __DIR__ . '/../includes/govt_exam_prep.php';
require_once __DIR__ . '/../includes/email_controller.php';

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');

$requestMethod = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if ($requestMethod === 'OPTIONS') {
    exit;
}

function api_out(array $payload, int $status = 200)
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

function api_json(array $payload): string
{
    return json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '{}';
}

function api_input(): array
{
    static $input = null;
    if (is_array($input)) {
        return $input;
    }
    $raw = file_get_contents('php://input') ?: '';
    $json = json_decode($raw, true);
    if (!is_array($json) && $raw !== '') {
        $cleanRaw = str_replace("\0", '', $raw);
        if ($cleanRaw !== $raw) {
            $json = json_decode($cleanRaw, true);
        }
    }
    if (is_array($json)) {
        $input = $json;
        return $input;
    }
    $form = [];
    if ($raw !== '') {
        parse_str($raw, $form);
    }
    $input = is_array($form) && $form ? $form : $_POST;
    return $input;
}

function student_app_settings(): array
{
    $name = trim(app_setting('site_name', APP_NAME));
    if ($name === '') {
        $name = APP_NAME;
    }

    return [
        'site_name' => $name,
        'app_name' => $name,
        'tagline' => app_setting('site_tagline', 'Secure Online Learning Management System'),
        'support_email' => app_setting('support_email', app_setting('site_email', '')),
        'support_phone' => app_setting('support_call_number', ''),
        'app_logo' => asset_or_default('app_logo_path'),
        'app_icon' => asset_or_default('app_icon_path'),
        'website_logo' => asset_or_default('website_logo_path'),
        'playstore_url' => app_setting('playstore_url', ''),
        'gcoin_enabled' => app_setting('gcoin_enabled', '1'),
        'gcoin_name' => app_setting('gcoin_name', 'Gcoin'),
        'gcoin_per_inr' => app_setting('gcoin_per_inr', '10'),
        'gcoin_min_redeem' => app_setting('gcoin_min_redeem', '10'),
        'phonepe_enabled' => app_setting('phonepe_enabled', '0'),
    ];
}

function phonepe_enabled(): bool
{
    if (app_setting('phonepe_enabled', '0') !== '1') {
        return false;
    }
    if (phonepe_uses_oauth()) {
        return true;
    }
    return trim(app_setting('phonepe_merchant_id', '')) !== ''
        && trim(app_setting('phonepe_salt_key', '')) !== '';
}

function phonepe_uses_oauth(): bool
{
    return phonepe_client_id() !== '' && phonepe_client_secret() !== '';
}

function phonepe_client_id(): string
{
    return trim(app_setting('phonepe_client_id', ''));
}

function phonepe_client_secret(): string
{
    return trim(app_setting('phonepe_client_secret', ''));
}

function phonepe_base_url(): string
{
    return app_setting('phonepe_environment', 'sandbox') === 'live'
        ? 'https://api.phonepe.com/apis/hermes'
        : 'https://api-preprod.phonepe.com/apis/pg-sandbox';
}

function phonepe_pg_base_url(): string
{
    return app_setting('phonepe_environment', 'sandbox') === 'live'
        ? 'https://api.phonepe.com/apis/pg'
        : 'https://api-preprod.phonepe.com/apis/pg-sandbox';
}

function phonepe_x_verify(string $payloadOrPath, string $path): string
{
    $saltKey = app_setting('phonepe_salt_key', '');
    $saltIndex = app_setting('phonepe_salt_index', '1') ?: '1';
    return hash('sha256', $payloadOrPath . $path . $saltKey) . '###' . $saltIndex;
}

function phonepe_http(string $method, string $path, ?array $body = null): array
{
    $url = phonepe_base_url() . $path;
    $headers = ['Content-Type: application/json', 'Accept: application/json'];
    if ($method === 'GET') {
        $headers[] = 'X-VERIFY: ' . phonepe_x_verify('', $path);
    } else {
        $encoded = (string) ($body['request'] ?? '');
        $headers[] = 'X-VERIFY: ' . phonepe_x_verify($encoded, $path);
    }

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_TIMEOUT => 25,
    ]);
    if ($method !== 'GET' && $body !== null) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body, JSON_UNESCAPED_SLASHES));
    }
    $raw = curl_exec($ch);
    $error = curl_error($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($raw === false || $error !== '') {
        throw new RuntimeException('PhonePe connection failed: ' . $error);
    }
    $json = json_decode((string) $raw, true);
    if (!is_array($json)) {
        throw new RuntimeException('Invalid PhonePe response.');
    }
    $json['_http_status'] = $status;
    return $json;
}

function phonepe_oauth_token(): string
{
    static $token = null;
    if (is_string($token) && $token !== '') {
        return $token;
    }

    $clientId = phonepe_client_id();
    $clientSecret = phonepe_client_secret();
    $clientVersion = trim(app_setting('phonepe_client_version', '1')) ?: '1';
    if ($clientId === '' || $clientSecret === '') {
        throw new RuntimeException('PhonePe Client ID / Client Secret missing.');
    }

    $ch = curl_init(phonepe_pg_base_url() . '/v1/oauth/token');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/x-www-form-urlencoded',
            'Accept: application/json',
        ],
        CURLOPT_POSTFIELDS => http_build_query([
            'client_id' => $clientId,
            'client_version' => $clientVersion,
            'client_secret' => $clientSecret,
            'grant_type' => 'client_credentials',
        ]),
        CURLOPT_TIMEOUT => 25,
    ]);
    $raw = curl_exec($ch);
    $error = curl_error($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($raw === false || $error !== '') {
        throw new RuntimeException('PhonePe token request failed: ' . $error);
    }
    $json = json_decode((string) $raw, true);
    if (!is_array($json)) {
        throw new RuntimeException('Invalid PhonePe token response.');
    }
    $token = (string) ($json['access_token'] ?? $json['data']['access_token'] ?? $json['token'] ?? '');
    if ($token === '') {
        $message = (string) ($json['message'] ?? $json['error_description'] ?? $json['error'] ?? 'PhonePe access token not received.');
        throw new RuntimeException($message . ' (HTTP ' . $status . ')');
    }
    return $token;
}

function phonepe_oauth_http(string $method, string $path, ?array $body = null): array
{
    $ch = curl_init(phonepe_pg_base_url() . $path);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Accept: application/json',
            'Authorization: O-Bearer ' . phonepe_oauth_token(),
        ],
        CURLOPT_TIMEOUT => 25,
    ]);
    if ($method !== 'GET' && $body !== null) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body, JSON_UNESCAPED_SLASHES));
    }
    $raw = curl_exec($ch);
    $error = curl_error($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($raw === false || $error !== '') {
        throw new RuntimeException('PhonePe request failed: ' . $error);
    }
    $json = json_decode((string) $raw, true);
    if (!is_array($json)) {
        throw new RuntimeException('Invalid PhonePe response.');
    }
    $json['_http_status'] = $status;
    return $json;
}

function student_app_payment_redirect_url(string $txn): string
{
    return api_absolute_url('api/student.php?action=payment_return&txn=' . rawurlencode($txn));
}

function student_app_payment_deep_link(string $txn): string
{
    return 'gyannexa://payment-return?txn=' . rawurlencode($txn);
}

function phonepe_create_payment(string $txn, int $studentId, float $amount, string $purpose, bool $appReturn = false): array
{
    if (!phonepe_enabled()) {
        throw new RuntimeException('PhonePe settings are not configured.');
    }
    $redirectUrl = $appReturn
        ? student_app_payment_redirect_url($txn)
        : api_absolute_url('api/student.php?action=payment_status&txn=' . rawurlencode($txn));
    if (phonepe_uses_oauth()) {
        $payload = [
            'merchantOrderId' => $txn,
            'amount' => max(1, (int) round($amount * 100)),
            'expireAfter' => 1200,
            'metaInfo' => [
                'udf1' => 'STU' . $studentId,
                'udf2' => $purpose,
            ],
            'paymentFlow' => [
                'type' => 'PG_CHECKOUT',
                'message' => 'GYAN NEXA payment',
                'merchantUrls' => [
                    'redirectUrl' => $redirectUrl,
                ],
            ],
        ];
        $response = phonepe_oauth_http('POST', '/checkout/v2/pay', $payload);
        $url = (string) (
            $response['redirectUrl']
            ?? $response['data']['redirectUrl']
            ?? $response['data']['instrumentResponse']['redirectInfo']['url']
            ?? $response['data']['paymentUrl']
            ?? ''
        );
        if ($url === '') {
            throw new RuntimeException((string) ($response['message'] ?? $response['error_description'] ?? 'PhonePe payment URL not received.'));
        }
        return [
            'transaction_no' => $txn,
            'payment_url' => $url,
            'gateway' => 'phonepe',
            'purpose' => $purpose,
            'amount' => $amount,
            'raw' => $response,
        ];
    }

    $merchantId = app_setting('phonepe_merchant_id', '');
    $payload = [
        'merchantId' => $merchantId,
        'merchantTransactionId' => $txn,
        'merchantUserId' => 'STU' . $studentId,
        'amount' => max(1, (int) round($amount * 100)),
        'redirectUrl' => $redirectUrl,
        'redirectMode' => 'REDIRECT',
        'callbackUrl' => api_absolute_url('api/student.php?action=phonepe_callback&txn=' . rawurlencode($txn)),
        'paymentInstrument' => ['type' => 'PAY_PAGE'],
    ];
    $encoded = base64_encode(json_encode($payload, JSON_UNESCAPED_SLASHES));
    $response = phonepe_http('POST', '/pg/v1/pay', ['request' => $encoded]);
    $url = $response['data']['instrumentResponse']['redirectInfo']['url'] ?? '';
    if (!$url) {
        throw new RuntimeException((string) ($response['message'] ?? 'PhonePe payment URL not received.'));
    }
    return [
        'transaction_no' => $txn,
        'payment_url' => $url,
        'gateway' => 'phonepe',
        'purpose' => $purpose,
        'amount' => $amount,
        'raw' => $response,
    ];
}

function phonepe_status(string $txn): array
{
    if (phonepe_uses_oauth()) {
        return phonepe_oauth_http('GET', '/checkout/v2/order/' . rawurlencode($txn) . '/status');
    }
    $merchantId = app_setting('phonepe_merchant_id', '');
    return phonepe_http('GET', '/pg/v1/status/' . rawurlencode($merchantId) . '/' . rawurlencode($txn));
}

function phonepe_status_tokens(array $status): array
{
    $tokens = [];
    $statusKeys = ['code' => true, 'state' => true, 'status' => true, 'paymentstate' => true, 'transactionstatus' => true, 'responsecode' => true];
    $collect = static function ($value, ?string $key = null) use (&$tokens, &$collect, $statusKeys): void {
        if (is_array($value)) {
            foreach ($value as $childKey => $child) {
                $collect($child, is_string($childKey) ? $childKey : null);
            }
            return;
        }
        if ($key === null || !isset($statusKeys[strtolower($key)])) {
            return;
        }
        if (is_bool($value) || is_numeric($value) || $value === null) {
            return;
        }
        $value = strtoupper(trim((string) $value));
        if ($value !== '') {
            $tokens[$value] = true;
        }
    };

    foreach (['code', 'state', 'status', 'paymentState'] as $key) {
        if (array_key_exists($key, $status)) {
            $collect($status[$key], $key);
        }
    }
    foreach (['data', 'paymentDetails'] as $key) {
        if (isset($status[$key])) {
            $collect($status[$key], $key);
        }
    }

    return array_keys($tokens);
}

function phonepe_payment_is_success(array $status): bool
{
    $successTokens = [
        'PAYMENT_SUCCESS',
        'SUCCESS',
        'COMPLETED',
        'COMPLETION_SUCCESS',
        'TRANSACTION_SUCCESS',
    ];
    return (bool) array_intersect(phonepe_status_tokens($status), $successTokens);
}

function phonepe_payment_is_failed(array $status): bool
{
    $failedTokens = [
        'PAYMENT_ERROR',
        'PAYMENT_DECLINED',
        'PAYMENT_CANCELLED',
        'CANCELLED',
        'CANCELED',
        'TIMED_OUT',
        'TIMEOUT',
        'FAILED',
        'FAILURE',
        'EXPIRED',
        'AUTHORIZATION_FAILED',
    ];
    return (bool) array_intersect(phonepe_status_tokens($status), $failedTokens);
}

function mark_payment_start_failed(string $table, int $id): void
{
    $allowed = ['student_course_purchases', 'student_resource_purchases', 'student_membership_purchases'];
    if ($id <= 0 || !in_array($table, $allowed, true)) {
        return;
    }
    $stmt = db()->prepare("UPDATE {$table} SET payment_status = 'failed' WHERE id = ? AND payment_status = 'pending'");
    $stmt->bind_param('i', $id);
    $stmt->execute();
}

function gcoin_setting_float(string $key, float $fallback): float
{
    $value = (float) app_setting($key, (string) $fallback);
    return $value > 0 ? $value : $fallback;
}

function gcoin_enabled(): bool
{
    return app_setting('gcoin_enabled', '1') === '1';
}

function student_referral_code(int $studentId): string
{
    return 'GR' . str_pad((string) $studentId, 6, '0', STR_PAD_LEFT);
}

function gcoin_wallet_row(int $studentId): array
{
    db()->query('INSERT IGNORE INTO student_gcoin_wallets (student_id) VALUES (' . (int) $studentId . ')');
    $stmt = db()->prepare('SELECT balance, earned_total, spent_total FROM student_gcoin_wallets WHERE student_id = ? LIMIT 1');
    $stmt->bind_param('i', $studentId);
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc() ?: ['balance' => 0, 'earned_total' => 0, 'spent_total' => 0];
}

function gcoin_add_transaction(int $studentId, string $direction, float $coins, string $sourceType, ?int $sourceId, string $note): float
{
    if (!gcoin_enabled() || $coins <= 0) {
        return (float) (gcoin_wallet_row($studentId)['balance'] ?? 0);
    }

    gcoin_wallet_row($studentId);
    if ($direction === 'debit') {
        $stmt = db()->prepare('UPDATE student_gcoin_wallets SET balance = GREATEST(0, balance - ?), spent_total = spent_total + ? WHERE student_id = ?');
    } else {
        $stmt = db()->prepare('UPDATE student_gcoin_wallets SET balance = balance + ?, earned_total = earned_total + ? WHERE student_id = ?');
    }
    $stmt->bind_param('ddi', $coins, $coins, $studentId);
    $stmt->execute();
    $wallet = gcoin_wallet_row($studentId);
    $balance = (float) ($wallet['balance'] ?? 0);

    $stmt = db()->prepare('INSERT INTO student_gcoin_transactions (student_id, direction, coins, balance_after, source_type, source_id, note) VALUES (?, ?, ?, ?, ?, ?, ?)');
    $sourceIdValue = $sourceId ?? null;
    $stmt->bind_param('isddsis', $studentId, $direction, $coins, $balance, $sourceType, $sourceIdValue, $note);
    $stmt->execute();
    return $balance;
}

function gcoin_find_referrer(string $code, int $newUserId = 0): ?array
{
    $code = strtoupper(trim($code));
    if ($code === '') {
        return null;
    }
    $id = 0;
    if (preg_match('/^GR0*([0-9]+)$/', $code, $m)) {
        $id = (int) $m[1];
    }
    if ($id > 0) {
        $stmt = db()->prepare('SELECT id, full_name, username FROM users WHERE id = ? AND id <> ? LIMIT 1');
        $stmt->bind_param('ii', $id, $newUserId);
    } else {
        $stmt = db()->prepare('SELECT id, full_name, username FROM users WHERE UPPER(username) = ? AND id <> ? LIMIT 1');
        $stmt->bind_param('si', $code, $newUserId);
    }
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    return $row ?: null;
}

function gcoin_purchase_reward(float $amount): float
{
    $type = app_setting('gcoin_purchase_referrer_reward_type', 'percent');
    $value = gcoin_setting_float('gcoin_purchase_referrer_reward_value', 5);
    return $type === 'fixed' ? $value : round(($amount * $value) / 100, 2);
}

function api_absolute_url(string $path): string
{
    if (trim($path) === '') {
        return '';
    }
    if (preg_match('#^https?://#i', $path)) {
        return $path;
    }
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'gyannexa.com';
    return $scheme . '://' . $host . app_url($path);
}

function api_absolute_hash_url(string $hashPath): string
{
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'gyannexa.com';
    $base = rtrim(APP_BASE, '/') . '/';
    return $scheme . '://' . $host . $base . '#/' . ltrim($hashPath, '#/');
}

function student_gst_states(): array
{
    return [
        '01' => 'Jammu & Kashmir',
        '02' => 'Himachal Pradesh',
        '03' => 'Punjab',
        '04' => 'Chandigarh',
        '05' => 'Uttarakhand',
        '06' => 'Haryana',
        '07' => 'Delhi',
        '08' => 'Rajasthan',
        '09' => 'Uttar Pradesh',
        '10' => 'Bihar',
        '11' => 'Sikkim',
        '12' => 'Arunachal Pradesh',
        '13' => 'Nagaland',
        '14' => 'Manipur',
        '15' => 'Mizoram',
        '16' => 'Tripura',
        '17' => 'Meghalaya',
        '18' => 'Assam',
        '19' => 'West Bengal',
        '20' => 'Jharkhand',
        '21' => 'Odisha',
        '22' => 'Chhattisgarh',
        '23' => 'Madhya Pradesh',
        '24' => 'Gujarat',
        '26' => 'Dadra & Nagar Haveli and Daman & Diu',
        '27' => 'Maharashtra',
        '29' => 'Karnataka',
        '30' => 'Goa',
        '31' => 'Lakshadweep',
        '32' => 'Kerala',
        '33' => 'Tamil Nadu',
        '34' => 'Puducherry',
        '35' => 'Andaman & Nicobar Islands',
        '36' => 'Telangana',
        '37' => 'Andhra Pradesh',
        '38' => 'Ladakh',
        '96' => 'Foreign Country',
        '97' => 'Other Territory',
    ];
}

function normalize_gst_state_code(string $code): string
{
    $digits = preg_replace('/\D+/', '', $code);
    if ($digits === '') {
        return '';
    }
    return str_pad(substr($digits, 0, 2), 2, '0', STR_PAD_LEFT);
}

function gst_state_name(string $code): string
{
    $code = normalize_gst_state_code($code);
    $states = student_gst_states();
    return $states[$code] ?? '';
}

function invoice_format_number(int $number, string $stateCode = ''): string
{
    $now = new DateTimeImmutable('now');
    $month = (int) $now->format('n');
    $fyStartYear = $month >= 4 ? (int) $now->format('Y') : (int) $now->format('Y') - 1;
    $fy = substr((string) $fyStartYear, -2) . '-' . substr((string) ($fyStartYear + 1), -2);
    $format = app_setting('invoice_format', '{PREFIX}/{FY}/{STATE}/{NO}') ?: '{PREFIX}/{FY}/{STATE}/{NO}';
    $stateCode = normalize_gst_state_code($stateCode) ?: normalize_gst_state_code(app_setting('billing_state_code', '09'));
    $replacements = [
        '{PREFIX}' => app_setting('invoice_prefix', 'GR') ?: 'GR',
        '{FY}' => $fy,
        '{STATE}' => $stateCode,
        '{GST_STATE}' => $stateCode,
        '{YYYY}' => $now->format('Y'),
        '{YY}' => $now->format('y'),
        '{MM}' => $now->format('m'),
        '{DD}' => $now->format('d'),
        '{HH}' => $now->format('H'),
        '{II}' => $now->format('i'),
        '{SS}' => $now->format('s'),
        '{NO}' => str_pad((string) $number, 5, '0', STR_PAD_LEFT),
    ];
    return strtr($format, $replacements);
}

function next_invoice_no(string $stateCode = ''): string
{
    $current = max(1, (int) app_setting('invoice_current_no', app_setting('invoice_starting_no', '1')));
    for ($i = 0; $i < 20; $i++) {
        $number = $current + $i;
        $invoiceNo = invoice_format_number($number, $stateCode);
        $stmt = db()->prepare('SELECT id FROM student_invoices WHERE invoice_no = ? LIMIT 1');
        $stmt->bind_param('s', $invoiceNo);
        $stmt->execute();
        if (!$stmt->get_result()->fetch_assoc()) {
            save_setting('invoice_current_no', (string) ($number + 1));
            return $invoiceNo;
        }
    }
    $fallback = 'INV/' . date('YmdHis') . '/' . random_int(1000, 9999);
    return $fallback;
}

function student_billing_profile(int $studentId): array
{
    if ($studentId <= 0) {
        return [];
    }
    $stmt = db()->prepare('SELECT * FROM student_billing_profiles WHERE student_id = ? LIMIT 1');
    $stmt->bind_param('i', $studentId);
    $stmt->execute();
    $profile = $stmt->get_result()->fetch_assoc();
    if ($profile) {
        return $profile;
    }

    $stmt = db()->prepare('SELECT full_name, email, phone, city FROM users WHERE id = ? LIMIT 1');
    $stmt->bind_param('i', $studentId);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc() ?: [];
    return [
        'student_id' => $studentId,
        'legal_name' => (string) ($user['full_name'] ?? ''),
        'email' => (string) ($user['email'] ?? ''),
        'phone' => (string) ($user['phone'] ?? ''),
        'gstin' => '',
        'address_line' => '',
        'city' => (string) ($user['city'] ?? ''),
        'state_code' => '',
        'state_name' => '',
        'pincode' => '',
    ];
}

function billing_address_from_profile(array $profile): string
{
    $parts = [];
    foreach (['address_line', 'city', 'state_name', 'pincode'] as $key) {
        $value = trim((string) ($profile[$key] ?? ''));
        if ($value !== '') {
            $parts[] = $value;
        }
    }
    $stateCode = normalize_gst_state_code((string) ($profile['state_code'] ?? ''));
    if ($stateCode !== '') {
        $parts[] = 'State Code: ' . $stateCode;
    }
    return implode(', ', array_unique($parts));
}

function student_billing_profile_complete(array $profile): bool
{
    return trim((string) ($profile['legal_name'] ?? '')) !== ''
        && preg_match('/^\d{10}$/', preg_replace('/\D+/', '', (string) ($profile['phone'] ?? '')))
        && trim((string) ($profile['address_line'] ?? '')) !== ''
        && normalize_gst_state_code((string) ($profile['state_code'] ?? '')) !== ''
        && preg_match('/^\d{6}$/', preg_replace('/\D+/', '', (string) ($profile['pincode'] ?? '')));
}

function save_student_billing_profile(int $studentId, array $data): array
{
    $states = student_gst_states();
    $legalName = substr(trim((string) ($data['legal_name'] ?? $data['name'] ?? '')), 0, 160);
    $email = substr(trim((string) ($data['email'] ?? '')), 0, 190);
    $phone = preg_replace('/\D+/', '', (string) ($data['phone'] ?? ''));
    $gstin = strtoupper(substr(trim((string) ($data['gstin'] ?? '')), 0, 20));
    $address = trim((string) ($data['address_line'] ?? $data['address'] ?? ''));
    $city = substr(trim((string) ($data['city'] ?? '')), 0, 120);
    $stateCode = normalize_gst_state_code((string) ($data['state_code'] ?? ''));
    $stateName = $states[$stateCode] ?? substr(trim((string) ($data['state_name'] ?? '')), 0, 120);
    $pincode = preg_replace('/\D+/', '', (string) ($data['pincode'] ?? ''));

    if ($legalName === '' || !preg_match('/^\d{10}$/', $phone) || $address === '' || $stateCode === '' || $stateName === '' || !preg_match('/^\d{6}$/', $pincode)) {
        api_out([
            'success' => false,
            'billing_required' => true,
            'message' => 'Complete billing details are required for GST invoice.',
            'states' => $states,
        ], 422);
    }
    if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        api_out(['success' => false, 'message' => 'Billing email is invalid.'], 422);
    }
    if ($gstin !== '' && !preg_match('/^[0-9A-Z]{15}$/', $gstin)) {
        api_out(['success' => false, 'message' => 'GSTIN must be 15 characters.'], 422);
    }

    $stmt = db()->prepare('
        INSERT INTO student_billing_profiles
            (student_id, legal_name, email, phone, gstin, address_line, city, state_code, state_name, pincode)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE legal_name = VALUES(legal_name), email = VALUES(email), phone = VALUES(phone),
            gstin = VALUES(gstin), address_line = VALUES(address_line), city = VALUES(city), state_code = VALUES(state_code),
            state_name = VALUES(state_name), pincode = VALUES(pincode), updated_at = CURRENT_TIMESTAMP
    ');
    $stmt->bind_param('isssssssss', $studentId, $legalName, $email, $phone, $gstin, $address, $city, $stateCode, $stateName, $pincode);
    $stmt->execute();

    return student_billing_profile($studentId);
}

function require_student_billing_info(int $studentId): array
{
    $profile = student_billing_profile($studentId);
    if (!student_billing_profile_complete($profile)) {
        api_out([
            'success' => false,
            'billing_required' => true,
            'message' => 'Billing details required before payment for GST invoice.',
            'billing_profile' => $profile,
            'states' => student_gst_states(),
            'seller_state_code' => normalize_gst_state_code(app_setting('billing_state_code', '09')),
            'seller_state_name' => app_setting('billing_state_name', gst_state_name(app_setting('billing_state_code', '09'))),
        ], 422);
    }
    return $profile;
}

function invoice_gst_breakdown(float $taxable, float $taxRate, float $taxAmount, string $buyerStateCode): array
{
    $sellerStateCode = normalize_gst_state_code(app_setting('billing_state_code', '09')) ?: '09';
    $buyerStateCode = normalize_gst_state_code($buyerStateCode) ?: $sellerStateCode;
    $sameState = $sellerStateCode === $buyerStateCode;
    $sellerStateName = app_setting('billing_state_name', gst_state_name($sellerStateCode)) ?: gst_state_name($sellerStateCode);
    $buyerStateName = gst_state_name($buyerStateCode) ?: $buyerStateCode;

    $cgstRate = $sameState ? round($taxRate / 2, 2) : 0.0;
    $sgstRate = $sameState ? round($taxRate / 2, 2) : 0.0;
    $igstRate = $sameState ? 0.0 : $taxRate;
    $cgstAmount = $sameState ? round($taxAmount / 2, 2) : 0.0;
    $sgstAmount = $sameState ? round($taxAmount - $cgstAmount, 2) : 0.0;
    $igstAmount = $sameState ? 0.0 : $taxAmount;

    return [
        'seller_gstin' => app_setting('gst_number', ''),
        'seller_state_code' => $sellerStateCode,
        'seller_state_name' => $sellerStateName,
        'buyer_state_code' => $buyerStateCode,
        'buyer_state_name' => $buyerStateName,
        'place_of_supply_code' => $buyerStateCode,
        'place_of_supply_name' => $buyerStateName,
        'gst_type' => $sameState ? 'CGST_SGST' : 'IGST',
        'cgst_rate' => $cgstRate,
        'cgst_amount' => $cgstAmount,
        'sgst_rate' => $sgstRate,
        'sgst_amount' => $sgstAmount,
        'igst_rate' => $igstRate,
        'igst_amount' => $igstAmount,
    ];
}

function purchase_invoice_payload(string $sourceType, int $purchaseId): array
{
    if ($sourceType === 'course') {
        $stmt = db()->prepare('
            SELECT p.*, c.title AS item_name
            FROM student_course_purchases p
            LEFT JOIN instructor_courses c ON c.id = p.course_id
            WHERE p.id = ? LIMIT 1
        ');
    } elseif ($sourceType === 'resource') {
        $stmt = db()->prepare('
            SELECT p.*, r.resource_title AS item_name
            FROM student_resource_purchases p
            LEFT JOIN instructor_course_resources r ON r.id = p.resource_id
            WHERE p.id = ? LIMIT 1
        ');
    } else {
        $stmt = db()->prepare('
            SELECT p.*, mp.plan_name AS item_name
            FROM student_membership_purchases p
            LEFT JOIN student_membership_plans mp ON mp.id = p.plan_id
            WHERE p.id = ? LIMIT 1
        ');
    }
    $stmt->bind_param('i', $purchaseId);
    $stmt->execute();
    $purchase = $stmt->get_result()->fetch_assoc();
    if (!$purchase) {
        return [];
    }

    $studentId = (int) $purchase['student_id'];
    $stmt = db()->prepare('SELECT full_name, email, phone, city FROM users WHERE id = ? LIMIT 1');
    $stmt->bind_param('i', $studentId);
    $stmt->execute();
    $student = $stmt->get_result()->fetch_assoc() ?: [];
    $billing = student_billing_profile($studentId);

    $original = max(0, (float) ($purchase['original_amount'] ?? $purchase['amount'] ?? 0));
    $total = max(0, (float) ($purchase['amount'] ?? 0));
    $discount = max(0, $original - $total);
    $taxRate = max(0, (float) app_setting('tax_rate', '18'));
    $taxable = $taxRate > 0 ? round($total / (1 + ($taxRate / 100)), 2) : $total;
    $taxAmount = max(0, round($total - $taxable, 2));
    $gst = invoice_gst_breakdown($taxable, $taxRate, $taxAmount, (string) ($billing['state_code'] ?? ''));

    return [
        'purchase' => $purchase,
        'student' => $student,
        'billing_profile' => $billing,
        'gst' => $gst,
        'student_id' => $studentId,
        'item_id' => (int) ($purchase['course_id'] ?? $purchase['resource_id'] ?? $purchase['plan_id'] ?? 0),
        'item_name' => trim((string) ($purchase['item_name'] ?? 'Learning Access')) ?: 'Learning Access',
        'subtotal' => $original,
        'discount' => $discount,
        'taxable' => $taxable,
        'tax_rate' => $taxRate,
        'tax_amount' => $taxAmount,
        'total' => $total,
    ];
}

function ensure_invoice_for_purchase(string $sourceType, int $purchaseId): array
{
    $sourceType = $sourceType === 'pdf' ? 'resource' : $sourceType;
    if (!in_array($sourceType, ['course', 'resource', 'membership'], true) || $purchaseId <= 0) {
        return [];
    }

    $stmt = db()->prepare('SELECT * FROM student_invoices WHERE source_type = ? AND source_id = ? LIMIT 1');
    $stmt->bind_param('si', $sourceType, $purchaseId);
    $stmt->execute();
    $existing = $stmt->get_result()->fetch_assoc();
    if ($existing) {
        return $existing;
    }

    $payload = purchase_invoice_payload($sourceType, $purchaseId);
    if (!$payload) {
        return [];
    }
    if ((float) ($payload['total'] ?? 0) <= 0) {
        return [];
    }
    $purchase = $payload['purchase'];
    if (($purchase['payment_status'] ?? '') !== 'paid') {
        return [];
    }
    $student = $payload['student'];
    $billing = $payload['billing_profile'] ?? [];
    $gst = $payload['gst'] ?? invoice_gst_breakdown((float) $payload['taxable'], (float) $payload['tax_rate'], (float) $payload['tax_amount'], (string) ($billing['state_code'] ?? ''));
    $invoiceNo = next_invoice_no((string) ($gst['place_of_supply_code'] ?? ''));
    $currency = app_setting('currency', 'INR') ?: 'INR';
    $address = billing_address_from_profile($billing) ?: app_setting('billing_address', '');
    $name = (string) (($billing['legal_name'] ?? '') ?: ($student['full_name'] ?? ''));
    $email = (string) (($billing['email'] ?? '') ?: ($student['email'] ?? ''));
    $phone = (string) (($billing['phone'] ?? '') ?: ($student['phone'] ?? ''));
    $txn = (string) ($purchase['transaction_no'] ?? '');
    $method = (string) ($purchase['payment_method'] ?? 'phonepe');
    $studentId = (int) $payload['student_id'];
    $subtotal = (float) $payload['subtotal'];
    $discount = (float) $payload['discount'];
    $taxable = (float) $payload['taxable'];
    $taxRate = (float) $payload['tax_rate'];
    $taxAmount = (float) $payload['tax_amount'];
    $total = (float) $payload['total'];
    $sellerGstin = (string) ($gst['seller_gstin'] ?? '');
    $sellerStateCode = (string) ($gst['seller_state_code'] ?? '');
    $sellerStateName = (string) ($gst['seller_state_name'] ?? '');
    $buyerGstin = (string) ($billing['gstin'] ?? '');
    $buyerStateCode = (string) ($gst['buyer_state_code'] ?? '');
    $buyerStateName = (string) ($gst['buyer_state_name'] ?? '');
    $placeOfSupplyCode = (string) ($gst['place_of_supply_code'] ?? '');
    $placeOfSupplyName = (string) ($gst['place_of_supply_name'] ?? '');
    $gstType = (string) ($gst['gst_type'] ?? 'IGST');
    $cgstRate = (float) ($gst['cgst_rate'] ?? 0);
    $cgstAmount = (float) ($gst['cgst_amount'] ?? 0);
    $sgstRate = (float) ($gst['sgst_rate'] ?? 0);
    $sgstAmount = (float) ($gst['sgst_amount'] ?? 0);
    $igstRate = (float) ($gst['igst_rate'] ?? 0);
    $igstAmount = (float) ($gst['igst_amount'] ?? 0);
    $itemId = (int) $payload['item_id'];
    $itemName = (string) $payload['item_name'];

    $stmt = db()->prepare('
        INSERT INTO student_invoices
            (invoice_no, student_id, source_type, source_id, transaction_no, payment_method, subtotal, discount_amount,
             taxable_amount, tax_rate, tax_amount, total_amount, currency, invoice_status, billed_name, billed_email,
             billed_phone, billing_address, seller_gstin, seller_state_code, seller_state_name, buyer_gstin,
             buyer_state_code, buyer_state_name, place_of_supply_code, place_of_supply_name, gst_type,
             cgst_rate, cgst_amount, sgst_rate, sgst_amount, igst_rate, igst_amount, issued_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, "paid", ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
    ');
    $stmt->bind_param(
        'sisissddddddssssssssssssssdddddd',
        $invoiceNo,
        $studentId,
        $sourceType,
        $purchaseId,
        $txn,
        $method,
        $subtotal,
        $discount,
        $taxable,
        $taxRate,
        $taxAmount,
        $total,
        $currency,
        $name,
        $email,
        $phone,
        $address,
        $sellerGstin,
        $sellerStateCode,
        $sellerStateName,
        $buyerGstin,
        $buyerStateCode,
        $buyerStateName,
        $placeOfSupplyCode,
        $placeOfSupplyName,
        $gstType,
        $cgstRate,
        $cgstAmount,
        $sgstRate,
        $sgstAmount,
        $igstRate,
        $igstAmount
    );
    $stmt->execute();
    $invoiceId = (int) db()->insert_id;

    $stmt = db()->prepare('
        INSERT INTO student_invoice_items
            (invoice_id, item_type, item_id, item_name, quantity, unit_price, discount_amount, taxable_amount, tax_rate, tax_amount, total_amount,
             gst_type, cgst_rate, cgst_amount, sgst_rate, sgst_amount, igst_rate, igst_amount)
        VALUES (?, ?, ?, ?, 1, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ');
    $stmt->bind_param(
        'isisddddddsdddddd',
        $invoiceId,
        $sourceType,
        $itemId,
        $itemName,
        $subtotal,
        $discount,
        $taxable,
        $taxRate,
        $taxAmount,
        $total,
        $gstType,
        $cgstRate,
        $cgstAmount,
        $sgstRate,
        $sgstAmount,
        $igstRate,
        $igstAmount
    );
    $stmt->execute();

    $stmt = db()->prepare('SELECT * FROM student_invoices WHERE id = ? LIMIT 1');
    $stmt->bind_param('i', $invoiceId);
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc() ?: [];
}

function course_resource_thumbnail_url(array $resource): string
{
    $path = ensure_course_resource_thumbnail($resource);
    if ($path === '') {
        return '';
    }
    $file = __DIR__ . '/../' . ltrim(strtok($path, '?') ?: $path, '/');
    if (is_file($file)) {
        $path .= (str_contains($path, '?') ? '&' : '?') . 'v=' . filemtime($file);
    }
    return api_absolute_url($path);
}

function learning_item_thumbnail_url(string $type, array $item): string
{
    $id = (int) ($item['id'] ?? 0);
    if ($id <= 0 || !in_array($type, ['course', 'exam'], true)) {
        return '';
    }

    $existing = trim((string) ($item['thumbnail_path'] ?? $item['thumbnail'] ?? $item['image_path'] ?? ''));
    if ($existing !== '') {
        return api_absolute_url($existing);
    }

    $title = trim((string) ($item['title'] ?? 'GYAN NEXA'));
    $subtitle = trim((string) (
        $item['category_name']
        ?? $item['exam_category_name']
        ?? $item['category']
        ?? $item['course_title']
        ?? ''
    ));
    $label = $type === 'exam' ? 'EXAM / MOCK TEST' : 'COURSE';
    $dir = __DIR__ . '/../uploads/generated-thumbnails';
    if (!is_dir($dir)) {
        @mkdir($dir, 0775, true);
    }

    $path = 'uploads/generated-thumbnails/' . $type . '-' . $id . '.svg';
    $file = __DIR__ . '/../' . $path;
    if (!is_file($file)) {
        $palettes = [
            ['#12355b', '#1d9aaf', '#eef7ff'],
            ['#6d123f', '#d61f69', '#fff1f6'],
            ['#0f5132', '#13a76b', '#ecfdf5'],
            ['#7c1d12', '#ef7d22', '#fff7ed'],
            ['#1e1b4b', '#4f46e5', '#eef2ff'],
            ['#111827', '#334155', '#f8fafc'],
        ];
        $palette = $palettes[abs(crc32($type . $title . $subtitle)) % count($palettes)];
        $shortTitle = function_exists('mb_strimwidth')
            ? mb_strimwidth($title, 0, 42, '...', 'UTF-8')
            : (strlen($title) > 42 ? substr($title, 0, 39) . '...' : $title);
        $displaySubtitle = $subtitle ?: 'GYAN NEXA';
        $shortSubtitle = function_exists('mb_strimwidth')
            ? mb_strimwidth($displaySubtitle, 0, 34, '...', 'UTF-8')
            : (strlen($displaySubtitle) > 34 ? substr($displaySubtitle, 0, 31) . '...' : $displaySubtitle);
        $safeTitle = htmlspecialchars($shortTitle, ENT_QUOTES, 'UTF-8');
        $safeSubtitle = htmlspecialchars($shortSubtitle, ENT_QUOTES, 'UTF-8');
        $safeLabel = htmlspecialchars($label, ENT_QUOTES, 'UTF-8');
        $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="900" height="520" viewBox="0 0 900 520">'
            . '<defs><linearGradient id="g" x1="0" y1="0" x2="1" y2="1"><stop stop-color="' . $palette[0] . '"/><stop offset="1" stop-color="' . $palette[1] . '"/></linearGradient></defs>'
            . '<rect width="900" height="520" rx="36" fill="url(#g)"/>'
            . '<circle cx="770" cy="95" r="92" fill="#ffffff" opacity=".18"/>'
            . '<circle cx="115" cy="420" r="120" fill="#ffffff" opacity=".12"/>'
            . '<rect x="52" y="52" width="796" height="416" rx="28" fill="#ffffff" opacity=".10" stroke="#ffffff" stroke-opacity=".28"/>'
            . '<rect x="70" y="70" width="210" height="48" rx="24" fill="#ffffff" opacity=".92"/>'
            . '<text x="96" y="101" font-family="Arial, sans-serif" font-size="21" font-weight="800" fill="' . $palette[0] . '">' . $safeLabel . '</text>'
            . '<text x="74" y="218" font-family="Georgia, serif" font-size="54" font-weight="800" fill="#ffffff">' . $safeTitle . '</text>'
            . '<text x="78" y="275" font-family="Arial, sans-serif" font-size="28" font-weight="700" fill="' . $palette[2] . '">' . $safeSubtitle . '</text>'
            . '<rect x="74" y="340" width="330" height="56" rx="14" fill="#ffffff" opacity=".94"/>'
            . '<text x="102" y="376" font-family="Arial, sans-serif" font-size="24" font-weight="800" fill="' . $palette[0] . '">GYAN NEXA Learning</text>'
            . '<text x="610" y="410" font-family="Arial, sans-serif" font-size="82" font-weight="900" fill="#ffffff" opacity=".18">GR</text>'
            . '</svg>';
        @file_put_contents($file, $svg);
    }

    if (is_file($file)) {
        $path .= '?v=' . filemtime($file);
    }
    return api_absolute_url($path);
}

function decorate_course_thumbnail(array $row): array
{
    $row['thumbnail_url'] = learning_item_thumbnail_url('course', $row);
    return $row;
}

function decorate_exam_thumbnail(array $row): array
{
    $row['thumbnail_url'] = learning_item_thumbnail_url('exam', $row);
    return $row;
}

function pdf_file_page_count(string $relativePath): int
{
    $path = realpath(__DIR__ . '/../' . ltrim($relativePath, '/'));
    $base = realpath(__DIR__ . '/../uploads/course-content');
    if (!$path || !$base || strpos($path, $base) !== 0 || !is_file($path)) {
        return 1;
    }
    $raw = @file_get_contents($path);
    if (!is_string($raw) || $raw === '') {
        return 1;
    }
    preg_match_all('/\/Type\s*\/Page\b/', $raw, $matches);
    return max(1, count($matches[0] ?? []));
}

function ensure_student_api_tables(): void
{
    ensure_users_phone_column();
    ensure_instructor_erp_tables();
    db()->query("ALTER TABLE users ADD COLUMN IF NOT EXISTS gender VARCHAR(20) DEFAULT NULL");
    db()->query("ALTER TABLE users ADD COLUMN IF NOT EXISTS date_of_birth DATE DEFAULT NULL");
    db()->query("ALTER TABLE users ADD COLUMN IF NOT EXISTS profile_photo VARCHAR(255) DEFAULT NULL");
    db()->query("ALTER TABLE users ADD COLUMN IF NOT EXISTS city VARCHAR(120) DEFAULT NULL");
    db()->query("ALTER TABLE users ADD COLUMN IF NOT EXISTS bio TEXT NULL");

    db()->query("
        CREATE TABLE IF NOT EXISTS student_app_tokens (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id INT UNSIGNED NOT NULL,
            token_hash VARCHAR(128) NOT NULL,
            expires_at DATETIME NOT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY student_app_tokens_hash_unique (token_hash),
            KEY student_app_tokens_user_index (user_id),
            CONSTRAINT student_app_tokens_user_foreign FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    db()->query("
        CREATE TABLE IF NOT EXISTS student_course_purchases (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            student_id INT UNSIGNED NOT NULL,
            course_id INT UNSIGNED NOT NULL,
            amount DECIMAL(10,2) NOT NULL DEFAULT 0,
            payment_method VARCHAR(40) NOT NULL DEFAULT 'manual',
            payment_status ENUM('pending','paid','failed','refunded') NOT NULL DEFAULT 'paid',
            transaction_no VARCHAR(80) DEFAULT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY student_course_purchases_unique (student_id, course_id),
            KEY student_course_purchases_course_index (course_id),
            CONSTRAINT student_course_purchases_student_foreign FOREIGN KEY (student_id) REFERENCES users (id) ON DELETE CASCADE,
            CONSTRAINT student_course_purchases_course_foreign FOREIGN KEY (course_id) REFERENCES instructor_courses (id) ON DELETE CASCADE
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
            KEY student_course_enrollments_course_index (course_id),
            CONSTRAINT student_course_enrollments_student_foreign FOREIGN KEY (student_id) REFERENCES users (id) ON DELETE CASCADE,
            CONSTRAINT student_course_enrollments_course_foreign FOREIGN KEY (course_id) REFERENCES instructor_courses (id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    db()->query("
        CREATE TABLE IF NOT EXISTS student_course_plan_revocations (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            student_id INT UNSIGNED NOT NULL,
            course_id INT UNSIGNED NOT NULL,
            reason VARCHAR(190) NULL,
            revoked_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY student_course_plan_revocations_unique (student_id, course_id),
            KEY student_course_plan_revocations_course_index (course_id),
            CONSTRAINT student_course_plan_revocations_student_foreign FOREIGN KEY (student_id) REFERENCES users (id) ON DELETE CASCADE,
            CONSTRAINT student_course_plan_revocations_course_foreign FOREIGN KEY (course_id) REFERENCES instructor_courses (id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    db()->query("
        CREATE TABLE IF NOT EXISTS student_membership_plans (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            plan_name VARCHAR(80) NOT NULL,
            plan_slug VARCHAR(80) NOT NULL,
            monthly_price DECIMAL(10,2) NOT NULL DEFAULT 0,
            validity_days INT UNSIGNED NOT NULL DEFAULT 30,
            video_limit INT UNSIGNED NULL,
            pdf_limit INT UNSIGNED NULL,
            exam_limit INT UNSIGNED NULL,
            mock_test_limit INT UNSIGNED NULL,
            live_class_limit INT UNSIGNED NULL,
            limit_reset_period VARCHAR(20) NOT NULL DEFAULT 'one_time',
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            sort_order INT UNSIGNED NOT NULL DEFAULT 0,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY student_membership_plans_slug_unique (plan_slug)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    db()->query("ALTER TABLE student_membership_plans ADD COLUMN IF NOT EXISTS limit_reset_period VARCHAR(20) NOT NULL DEFAULT 'one_time' AFTER live_class_limit");

    $plans = [
        ['GYAN NEXA All Access', 'basic', 99.00, 30, null, null, null, null, null, 'monthly', 1],
    ];
    $stmt = db()->prepare("
        INSERT INTO student_membership_plans
            (plan_name, plan_slug, monthly_price, validity_days, video_limit, pdf_limit, exam_limit, mock_test_limit, live_class_limit, limit_reset_period, sort_order)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE
            plan_name = VALUES(plan_name),
            monthly_price = VALUES(monthly_price),
            validity_days = VALUES(validity_days),
            video_limit = VALUES(video_limit),
            pdf_limit = VALUES(pdf_limit),
            exam_limit = VALUES(exam_limit),
            mock_test_limit = VALUES(mock_test_limit),
            live_class_limit = VALUES(live_class_limit),
            limit_reset_period = VALUES(limit_reset_period),
            is_active = 1,
            sort_order = VALUES(sort_order)
    ");
    foreach ($plans as $plan) {
        [$name, $slug, $price, $validity, $video, $pdf, $exam, $mock, $live, $reset, $sort] = $plan;
        $stmt->bind_param('ssdiiiiiisi', $name, $slug, $price, $validity, $video, $pdf, $exam, $mock, $live, $reset, $sort);
        $stmt->execute();
    }
    db()->query("
        UPDATE student_membership_plans
        SET video_limit = NULL,
            pdf_limit = NULL,
            exam_limit = NULL,
            mock_test_limit = NULL,
            live_class_limit = NULL,
            monthly_price = 99.00,
            validity_days = 30,
            limit_reset_period = 'monthly',
            plan_name = 'GYAN NEXA All Access',
            sort_order = 1,
            is_active = 1
        WHERE plan_slug = 'basic'
    ");
    db()->query("UPDATE student_membership_plans SET is_active = 0 WHERE plan_slug <> 'basic'");

    db()->query("
        CREATE TABLE IF NOT EXISTS student_plan_subscriptions (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            student_id INT UNSIGNED NOT NULL,
            plan_id INT UNSIGNED NOT NULL,
            starts_at DATETIME NOT NULL,
            ends_at DATETIME NOT NULL,
            status ENUM('active','expired','cancelled') NOT NULL DEFAULT 'active',
            purchase_id BIGINT UNSIGNED NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY student_plan_subscriptions_student_index (student_id),
            KEY student_plan_subscriptions_plan_index (plan_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    db()->query("ALTER TABLE student_plan_subscriptions ADD COLUMN IF NOT EXISTS purchase_id BIGINT UNSIGNED NULL AFTER status");

    db()->query("
        CREATE TABLE IF NOT EXISTS student_free_plan_revocations (
            student_id INT UNSIGNED NOT NULL,
            revoked_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            note VARCHAR(255) DEFAULT NULL,
            PRIMARY KEY (student_id),
            CONSTRAINT student_free_plan_revocations_student_foreign FOREIGN KEY (student_id) REFERENCES users (id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    db()->query("
        CREATE TABLE IF NOT EXISTS student_membership_purchases (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            student_id INT UNSIGNED NOT NULL,
            plan_id INT UNSIGNED NOT NULL,
            original_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            billing_cycle VARCHAR(20) NOT NULL DEFAULT 'monthly',
            validity_days INT UNSIGNED NOT NULL DEFAULT 30,
            payment_method VARCHAR(40) NOT NULL DEFAULT 'manual',
            payment_status ENUM('pending','paid','failed','refunded') NOT NULL DEFAULT 'paid',
            transaction_no VARCHAR(80) DEFAULT NULL,
            purchased_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY student_membership_purchases_student_index (student_id),
            KEY student_membership_purchases_plan_index (plan_id),
            KEY student_membership_purchases_txn_index (transaction_no)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    db()->query("ALTER TABLE student_membership_purchases ADD COLUMN IF NOT EXISTS billing_cycle VARCHAR(20) NOT NULL DEFAULT 'monthly' AFTER amount");
    db()->query("ALTER TABLE student_membership_purchases ADD COLUMN IF NOT EXISTS validity_days INT UNSIGNED NOT NULL DEFAULT 30 AFTER billing_cycle");

    db()->query("
        CREATE TABLE IF NOT EXISTS student_billing_profiles (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            student_id INT UNSIGNED NOT NULL,
            legal_name VARCHAR(160) NOT NULL,
            email VARCHAR(190) DEFAULT NULL,
            phone VARCHAR(30) DEFAULT NULL,
            gstin VARCHAR(20) DEFAULT NULL,
            address_line TEXT NULL,
            city VARCHAR(120) DEFAULT NULL,
            state_code VARCHAR(4) NOT NULL,
            state_name VARCHAR(120) NOT NULL,
            pincode VARCHAR(10) DEFAULT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY student_billing_profiles_student_unique (student_id),
            KEY student_billing_profiles_state_index (state_code),
            CONSTRAINT student_billing_profiles_student_foreign FOREIGN KEY (student_id) REFERENCES users (id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    db()->query("
        CREATE TABLE IF NOT EXISTS student_invoices (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            invoice_no VARCHAR(80) NOT NULL,
            student_id INT UNSIGNED NOT NULL,
            source_type VARCHAR(30) NOT NULL,
            source_id BIGINT UNSIGNED NOT NULL,
            transaction_no VARCHAR(100) DEFAULT NULL,
            payment_method VARCHAR(40) NOT NULL DEFAULT 'phonepe',
            subtotal DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            discount_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            taxable_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            tax_rate DECIMAL(6,2) NOT NULL DEFAULT 0.00,
            tax_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            total_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            currency VARCHAR(12) NOT NULL DEFAULT 'INR',
            invoice_status ENUM('paid','cancelled','refunded') NOT NULL DEFAULT 'paid',
            billed_name VARCHAR(160) DEFAULT NULL,
            billed_email VARCHAR(190) DEFAULT NULL,
            billed_phone VARCHAR(30) DEFAULT NULL,
            billing_address TEXT NULL,
            issued_at DATETIME NOT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY student_invoices_invoice_no_unique (invoice_no),
            UNIQUE KEY student_invoices_source_unique (source_type, source_id),
            KEY student_invoices_student_index (student_id),
            KEY student_invoices_txn_index (transaction_no)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    $invoiceColumns = [
        "seller_gstin VARCHAR(20) DEFAULT NULL",
        "seller_state_code VARCHAR(4) DEFAULT NULL",
        "seller_state_name VARCHAR(120) DEFAULT NULL",
        "buyer_gstin VARCHAR(20) DEFAULT NULL",
        "buyer_state_code VARCHAR(4) DEFAULT NULL",
        "buyer_state_name VARCHAR(120) DEFAULT NULL",
        "place_of_supply_code VARCHAR(4) DEFAULT NULL",
        "place_of_supply_name VARCHAR(120) DEFAULT NULL",
        "gst_type VARCHAR(20) NOT NULL DEFAULT 'IGST'",
        "cgst_rate DECIMAL(6,2) NOT NULL DEFAULT 0.00",
        "cgst_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00",
        "sgst_rate DECIMAL(6,2) NOT NULL DEFAULT 0.00",
        "sgst_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00",
        "igst_rate DECIMAL(6,2) NOT NULL DEFAULT 0.00",
        "igst_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00",
    ];
    foreach ($invoiceColumns as $columnSql) {
        db()->query("ALTER TABLE student_invoices ADD COLUMN IF NOT EXISTS {$columnSql}");
    }

    db()->query("
        CREATE TABLE IF NOT EXISTS student_invoice_items (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            invoice_id BIGINT UNSIGNED NOT NULL,
            item_type VARCHAR(30) NOT NULL,
            item_id BIGINT UNSIGNED NOT NULL,
            item_name VARCHAR(255) NOT NULL,
            quantity INT UNSIGNED NOT NULL DEFAULT 1,
            unit_price DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            discount_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            taxable_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            tax_rate DECIMAL(6,2) NOT NULL DEFAULT 0.00,
            tax_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            total_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY student_invoice_items_invoice_index (invoice_id),
            CONSTRAINT student_invoice_items_invoice_foreign FOREIGN KEY (invoice_id) REFERENCES student_invoices (id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    $invoiceItemColumns = [
        "gst_type VARCHAR(20) NOT NULL DEFAULT 'IGST'",
        "cgst_rate DECIMAL(6,2) NOT NULL DEFAULT 0.00",
        "cgst_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00",
        "sgst_rate DECIMAL(6,2) NOT NULL DEFAULT 0.00",
        "sgst_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00",
        "igst_rate DECIMAL(6,2) NOT NULL DEFAULT 0.00",
        "igst_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00",
    ];
    foreach ($invoiceItemColumns as $columnSql) {
        db()->query("ALTER TABLE student_invoice_items ADD COLUMN IF NOT EXISTS {$columnSql}");
    }

    db()->query("
        CREATE TABLE IF NOT EXISTS student_resource_access (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            student_id INT UNSIGNED NOT NULL,
            resource_id INT UNSIGNED NOT NULL,
            course_id INT UNSIGNED NOT NULL,
            first_accessed_at DATETIME NOT NULL,
            last_accessed_at DATETIME NOT NULL,
            access_count INT UNSIGNED NOT NULL DEFAULT 1,
            current_page INT UNSIGNED NOT NULL DEFAULT 1,
            total_pages INT UNSIGNED NOT NULL DEFAULT 1,
            progress_percent DECIMAL(5,2) NOT NULL DEFAULT 0,
            completed_at DATETIME NULL,
            PRIMARY KEY (id),
            UNIQUE KEY student_resource_access_unique (student_id, resource_id),
            KEY student_resource_access_student_index (student_id),
            KEY student_resource_access_resource_index (resource_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    db()->query("ALTER TABLE student_resource_access ADD COLUMN IF NOT EXISTS current_page INT UNSIGNED NOT NULL DEFAULT 1");
    db()->query("ALTER TABLE student_resource_access ADD COLUMN IF NOT EXISTS total_pages INT UNSIGNED NOT NULL DEFAULT 1");
    db()->query("ALTER TABLE student_resource_access ADD COLUMN IF NOT EXISTS progress_percent DECIMAL(5,2) NOT NULL DEFAULT 0");
    db()->query("ALTER TABLE student_resource_access ADD COLUMN IF NOT EXISTS completed_at DATETIME NULL");

    db()->query("
        CREATE TABLE IF NOT EXISTS student_resource_purchases (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            student_id INT UNSIGNED NOT NULL,
            resource_id INT UNSIGNED NOT NULL,
            course_id INT UNSIGNED NOT NULL,
            original_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            payment_method VARCHAR(40) NOT NULL DEFAULT 'manual',
            payment_status ENUM('pending','paid','failed','refunded') NOT NULL DEFAULT 'paid',
            transaction_no VARCHAR(80) DEFAULT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY student_resource_purchases_unique (student_id, resource_id),
            KEY student_resource_purchases_student_index (student_id),
            KEY student_resource_purchases_resource_index (resource_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    db()->query("
        CREATE TABLE IF NOT EXISTS student_content_progress (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            student_id INT UNSIGNED NOT NULL,
            course_id INT UNSIGNED NOT NULL,
            content_id INT UNSIGNED NOT NULL,
            watched_seconds INT UNSIGNED NOT NULL DEFAULT 0,
            duration_seconds INT UNSIGNED NOT NULL DEFAULT 0,
            progress_percent DECIMAL(5,2) NOT NULL DEFAULT 0,
            completed_at DATETIME NULL,
            last_watched_at DATETIME NOT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY student_content_progress_unique (student_id, content_id),
            KEY student_content_progress_course_index (student_id, course_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    db()->query("
        CREATE TABLE IF NOT EXISTS student_course_achievements (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            student_id INT UNSIGNED NOT NULL,
            course_id INT UNSIGNED NOT NULL,
            stars INT UNSIGNED NOT NULL DEFAULT 1,
            rank_title VARCHAR(80) NOT NULL DEFAULT 'Course Starter',
            progress_percent DECIMAL(5,2) NOT NULL DEFAULT 0,
            achieved_at DATETIME NOT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY student_course_achievements_unique (student_id, course_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    db()->query("
        CREATE TABLE IF NOT EXISTS student_instructor_follows (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            student_id INT UNSIGNED NOT NULL,
            instructor_id INT UNSIGNED NOT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY student_instructor_follows_unique (student_id, instructor_id),
            KEY student_instructor_follows_instructor_index (instructor_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    db()->query("
        CREATE TABLE IF NOT EXISTS student_content_likes (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            student_id INT UNSIGNED NOT NULL,
            content_id INT UNSIGNED NOT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY student_content_likes_unique (student_id, content_id),
            KEY student_content_likes_content_index (content_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    db()->query("
        CREATE TABLE IF NOT EXISTS student_course_likes (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            student_id INT UNSIGNED NOT NULL,
            course_id INT UNSIGNED NOT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY student_course_likes_unique (student_id, course_id),
            KEY student_course_likes_course_index (course_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    db()->query("
        CREATE TABLE IF NOT EXISTS student_gcoin_wallets (
            student_id INT UNSIGNED NOT NULL,
            balance DECIMAL(12,2) NOT NULL DEFAULT 0.00,
            earned_total DECIMAL(12,2) NOT NULL DEFAULT 0.00,
            spent_total DECIMAL(12,2) NOT NULL DEFAULT 0.00,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (student_id),
            KEY student_gcoin_wallets_balance_index (balance)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    db()->query("
        CREATE TABLE IF NOT EXISTS student_gcoin_transactions (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            student_id INT UNSIGNED NOT NULL,
            direction ENUM('credit','debit') NOT NULL,
            coins DECIMAL(12,2) NOT NULL DEFAULT 0.00,
            balance_after DECIMAL(12,2) NOT NULL DEFAULT 0.00,
            source_type VARCHAR(40) NOT NULL,
            source_id BIGINT UNSIGNED NULL,
            note VARCHAR(255) DEFAULT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY student_gcoin_transactions_student_index (student_id, id),
            KEY student_gcoin_transactions_source_index (source_type, source_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    db()->query("
        CREATE TABLE IF NOT EXISTS student_referrals (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            referrer_id INT UNSIGNED NOT NULL,
            referred_student_id INT UNSIGNED NOT NULL,
            referral_code VARCHAR(80) NOT NULL,
            signup_referrer_coins DECIMAL(12,2) NOT NULL DEFAULT 0.00,
            signup_joiner_coins DECIMAL(12,2) NOT NULL DEFAULT 0.00,
            purchase_referrer_coins DECIMAL(12,2) NOT NULL DEFAULT 0.00,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY student_referrals_referred_unique (referred_student_id),
            KEY student_referrals_referrer_index (referrer_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    db()->query('ALTER TABLE student_course_purchases ADD COLUMN IF NOT EXISTS original_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER course_id');
    db()->query('ALTER TABLE student_course_purchases ADD COLUMN IF NOT EXISTS gcoin_used DECIMAL(12,2) NOT NULL DEFAULT 0.00 AFTER amount');
    db()->query('ALTER TABLE student_course_purchases ADD COLUMN IF NOT EXISTS gcoin_discount DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER gcoin_used');

    db()->query("
        CREATE TABLE IF NOT EXISTS student_live_class_comments (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            source_type ENUM('class','batch') NOT NULL DEFAULT 'class',
            source_id INT UNSIGNED NOT NULL,
            student_id INT UNSIGNED NOT NULL,
            comment_text VARCHAR(500) NOT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY student_live_comments_source_index (source_type, source_id, id),
            KEY student_live_comments_student_index (student_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    db()->query("
        CREATE TABLE IF NOT EXISTS student_live_class_access (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            student_id INT UNSIGNED NOT NULL,
            source_type VARCHAR(40) NOT NULL,
            source_id INT UNSIGNED NOT NULL,
            course_id INT UNSIGNED NULL,
            first_accessed_at DATETIME NOT NULL,
            last_accessed_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY student_live_class_access_unique (student_id, source_type, source_id),
            KEY student_live_class_access_student_index (student_id),
            KEY student_live_class_access_period_index (student_id, first_accessed_at)
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
        CREATE TABLE IF NOT EXISTS student_exam_sessions (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            student_id INT UNSIGNED NOT NULL,
            exam_id INT UNSIGNED NOT NULL,
            started_at DATETIME NOT NULL,
            last_seen_at DATETIME NOT NULL,
            expires_at DATETIME NULL,
            status ENUM('active','completed','expired') NOT NULL DEFAULT 'active',
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY student_exam_sessions_unique (student_id, exam_id),
            KEY student_exam_sessions_student_index (student_id),
            KEY student_exam_sessions_exam_index (exam_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    db()->query("ALTER TABLE student_exam_sessions ADD COLUMN IF NOT EXISTS answer_json MEDIUMTEXT NULL");
    db()->query("ALTER TABLE student_exam_sessions ADD COLUMN IF NOT EXISTS review_json MEDIUMTEXT NULL");
    db()->query("ALTER TABLE student_exam_sessions ADD COLUMN IF NOT EXISTS question_json MEDIUMTEXT NULL");
    db()->query("ALTER TABLE student_exam_sessions ADD COLUMN IF NOT EXISTS current_question INT UNSIGNED NOT NULL DEFAULT 0");

    db()->query("
        CREATE TABLE IF NOT EXISTS student_gov_document_access (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            student_id INT UNSIGNED NOT NULL,
            document_id INT UNSIGNED NOT NULL,
            first_accessed_at DATETIME NOT NULL,
            last_accessed_at DATETIME NOT NULL,
            access_count INT UNSIGNED NOT NULL DEFAULT 1,
            PRIMARY KEY (id),
            UNIQUE KEY student_gov_doc_access_unique (student_id, document_id),
            KEY student_gov_doc_access_period_index (student_id, first_accessed_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    db()->query("
        CREATE TABLE IF NOT EXISTS student_gov_live_access (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            student_id INT UNSIGNED NOT NULL,
            live_id INT UNSIGNED NOT NULL,
            first_accessed_at DATETIME NOT NULL,
            last_accessed_at DATETIME NOT NULL,
            access_count INT UNSIGNED NOT NULL DEFAULT 1,
            PRIMARY KEY (id),
            UNIQUE KEY student_gov_live_access_unique (student_id, live_id),
            KEY student_gov_live_access_period_index (student_id, first_accessed_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    db()->query("
        CREATE TABLE IF NOT EXISTS student_gov_mock_attempts (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            student_id INT UNSIGNED NOT NULL,
            mock_test_id INT UNSIGNED NOT NULL,
            score DECIMAL(10,2) NOT NULL DEFAULT 0,
            total_marks DECIMAL(10,2) NOT NULL DEFAULT 0,
            total_questions INT UNSIGNED NOT NULL DEFAULT 0,
            correct_count INT UNSIGNED NOT NULL DEFAULT 0,
            wrong_count INT UNSIGNED NOT NULL DEFAULT 0,
            skipped_count INT UNSIGNED NOT NULL DEFAULT 0,
            percentage DECIMAL(5,2) NOT NULL DEFAULT 0,
            answer_json MEDIUMTEXT NULL,
            started_at DATETIME NOT NULL,
            submitted_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            KEY student_gov_mock_attempts_student_index (student_id, mock_test_id),
            KEY student_gov_mock_attempts_period_index (student_id, started_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    db()->query("
        CREATE TABLE IF NOT EXISTS student_gov_mock_access (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            student_id INT UNSIGNED NOT NULL,
            mock_test_id INT UNSIGNED NOT NULL,
            first_accessed_at DATETIME NOT NULL,
            last_accessed_at DATETIME NOT NULL,
            access_count INT UNSIGNED NOT NULL DEFAULT 1,
            PRIMARY KEY (id),
            UNIQUE KEY student_gov_mock_access_unique (student_id, mock_test_id),
            KEY student_gov_mock_access_period_index (student_id, first_accessed_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
}

function membership_plans(): array
{
    $result = db()->query("
        SELECT id, plan_name, plan_slug, monthly_price, validity_days, video_limit, pdf_limit, exam_limit, mock_test_limit, live_class_limit, limit_reset_period
        FROM student_membership_plans
        WHERE is_active = 1
        ORDER BY sort_order ASC, id ASC
    ");
    return $result->fetch_all(MYSQLI_ASSOC);
}

function plan_cycle_details(array $plan, string $cycle = 'monthly'): array
{
    $slug = strtolower((string) ($plan['plan_slug'] ?? ''));
    $basePrice = max(0, (float) ($plan['monthly_price'] ?? 0));
    $isFree = $basePrice <= 0 || $slug === 'free';
    $cycle = strtolower(trim($cycle));
    $cycle = $cycle === 'yearly' ? 'yearly' : 'monthly';
    if ($isFree) {
        return [
            'billing_cycle' => 'free',
            'amount' => 0.0,
            'validity_days' => 30,
            'limit_reset_period' => 'one_time',
        ];
    }
    $offerAmount = $cycle === 'yearly' ? ($basePrice === 99.0 ? 999.0 : round($basePrice * 10, 2)) : $basePrice;
    $regularAmount = round($offerAmount * 2, 2);
    return [
        'billing_cycle' => $cycle,
        'amount' => $offerAmount,
        'regular_amount' => $regularAmount,
        'offer_percent' => 50,
        'validity_days' => $cycle === 'yearly' ? 365 : 30,
        'limit_reset_period' => $cycle === 'yearly' ? 'yearly' : 'monthly',
    ];
}

function active_plan_subscription(int $studentId): ?array
{
    $stmt = db()->prepare("
        SELECT s.*, p.plan_name, p.plan_slug, p.monthly_price, p.validity_days, p.video_limit, p.pdf_limit, p.exam_limit, p.mock_test_limit, p.live_class_limit, p.limit_reset_period
        FROM student_plan_subscriptions s
        INNER JOIN student_membership_plans p ON p.id = s.plan_id
        WHERE s.student_id = ? AND s.status = 'active' AND s.starts_at <= NOW() AND s.ends_at >= NOW() AND p.is_active = 1
        ORDER BY s.ends_at DESC, s.id DESC
        LIMIT 1
    ");
    $stmt->bind_param('i', $studentId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    return $row ?: null;
}

function free_membership_plan(): ?array
{
    $stmt = db()->prepare("
        SELECT *
        FROM student_membership_plans
        WHERE is_active = 1 AND monthly_price <= 0
        ORDER BY sort_order ASC, id ASC
        LIMIT 1
    ");
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    return $row ?: null;
}

function ensure_default_free_plan(int $studentId): ?array
{
    return $studentId > 0 ? active_plan_subscription($studentId) : null;
}

function plan_limit_allows(?array $subscription, string $column, int $used, bool $alreadyUsed = false): bool
{
    if (!$subscription) {
        return false;
    }
    if ($alreadyUsed) {
        return true;
    }
    if (!array_key_exists($column, $subscription) || $subscription[$column] === null || $subscription[$column] === '') {
        return true;
    }
    $limit = (int) $subscription[$column];
    return $limit > 0 && $used < $limit;
}

function plan_limit_window(array $subscription): array
{
    $period = strtolower((string) ($subscription['limit_reset_period'] ?? 'one_time'));
    $validPeriods = ['one_time', 'daily', 'weekly', 'monthly', 'yearly'];
    if (!in_array($period, $validPeriods, true)) {
        $period = 'one_time';
    }

    $subscriptionStart = new DateTimeImmutable((string) ($subscription['starts_at'] ?? 'now'));
    $subscriptionEnd = new DateTimeImmutable((string) ($subscription['ends_at'] ?? 'now'));
    $now = new DateTimeImmutable('now');

    if ($period === 'daily') {
        $start = $now->setTime(0, 0, 0);
        $end = $now->setTime(23, 59, 59);
    } elseif ($period === 'weekly') {
        $start = $now->modify('monday this week')->setTime(0, 0, 0);
        $end = $start->modify('+6 days')->setTime(23, 59, 59);
    } elseif ($period === 'monthly') {
        $start = $now->modify('first day of this month')->setTime(0, 0, 0);
        $end = $now->modify('last day of this month')->setTime(23, 59, 59);
    } else {
        $start = $subscriptionStart;
        $end = $subscriptionEnd;
    }

    if ($start < $subscriptionStart) {
        $start = $subscriptionStart;
    }
    if ($end > $subscriptionEnd) {
        $end = $subscriptionEnd;
    }

    return [$start->format('Y-m-d H:i:s'), $end->format('Y-m-d H:i:s'), $period];
}

function plan_quota_usage(int $studentId, ?array $subscription): array
{
    if (!$subscription) {
        $emptyItem = [
            'limit' => 0,
            'used' => 0,
            'remaining' => 0,
            'unlimited' => false,
        ];
        return [
            'active' => false,
            'window_start' => null,
            'window_end' => null,
            'reset_period' => null,
            'courses' => $emptyItem,
            'pdfs' => $emptyItem,
            'exams' => $emptyItem,
            'mock_tests' => $emptyItem,
            'live_classes' => $emptyItem,
        ];
    }
    [$windowStart, $windowEnd, $period] = plan_limit_window($subscription);

    $stmt = db()->prepare('SELECT COUNT(DISTINCT course_id) AS total FROM student_course_enrollments WHERE student_id = ? AND purchase_id IS NULL AND status IN ("active","completed") AND enrolled_at BETWEEN ? AND ?');
    $stmt->bind_param('iss', $studentId, $windowStart, $windowEnd);
    $stmt->execute();
    $coursesUsed = (int) ($stmt->get_result()->fetch_assoc()['total'] ?? 0);

    $stmt = db()->prepare('SELECT COUNT(DISTINCT resource_id) AS total FROM student_resource_access WHERE student_id = ? AND first_accessed_at BETWEEN ? AND ?');
    $stmt->bind_param('iss', $studentId, $windowStart, $windowEnd);
    $stmt->execute();
    $pdfUsed = (int) ($stmt->get_result()->fetch_assoc()['total'] ?? 0);

    $stmt = db()->prepare('
        SELECT COUNT(DISTINCT exam_id) AS total FROM (
            SELECT exam_id FROM student_exam_sessions WHERE student_id = ? AND started_at BETWEEN ? AND ?
            UNION
            SELECT exam_id FROM student_exam_attempts WHERE student_id = ? AND started_at BETWEEN ? AND ?
        ) x
    ');
    $stmt->bind_param('ississ', $studentId, $windowStart, $windowEnd, $studentId, $windowStart, $windowEnd);
    $stmt->execute();
    $examUsed = (int) ($stmt->get_result()->fetch_assoc()['total'] ?? 0);

    $stmt = db()->prepare('SELECT COUNT(DISTINCT CONCAT(source_type, ":", source_id)) AS total FROM student_live_class_access WHERE student_id = ? AND first_accessed_at BETWEEN ? AND ?');
    $stmt->bind_param('iss', $studentId, $windowStart, $windowEnd);
    $stmt->execute();
    $liveUsed = (int) ($stmt->get_result()->fetch_assoc()['total'] ?? 0);

    $stmt = db()->prepare('SELECT COUNT(DISTINCT mock_test_id) AS total FROM student_gov_mock_access WHERE student_id = ? AND first_accessed_at BETWEEN ? AND ?');
    $stmt->bind_param('iss', $studentId, $windowStart, $windowEnd);
    $stmt->execute();
    $mockUsed = (int) ($stmt->get_result()->fetch_assoc()['total'] ?? 0);

    $item = static function (array $subscription, string $column, int $used): array {
        $raw = $subscription[$column] ?? null;
        $unlimited = $raw === null || $raw === '' || (int) $raw <= 0;
        $limit = $unlimited ? null : (int) $raw;
        return [
            'limit' => $limit,
            'used' => $used,
            'remaining' => $unlimited ? null : max(0, $limit - $used),
            'unlimited' => $unlimited,
        ];
    };

    return [
        'active' => true,
        'window_start' => $windowStart,
        'window_end' => $windowEnd,
        'reset_period' => $period,
        'courses' => $item($subscription, 'video_limit', $coursesUsed),
        'pdfs' => $item($subscription, 'pdf_limit', $pdfUsed),
        'exams' => $item($subscription, 'exam_limit', $examUsed),
        'mock_tests' => $item($subscription, 'mock_test_limit', $mockUsed),
        'live_classes' => $item($subscription, 'live_class_limit', $liveUsed),
    ];
}

function student_course_plan_revoked(int $studentId, int $courseId): bool
{
    $stmt = db()->prepare('SELECT id FROM student_course_plan_revocations WHERE student_id = ? AND course_id = ? LIMIT 1');
    $stmt->bind_param('ii', $studentId, $courseId);
    $stmt->execute();
    return (bool) $stmt->get_result()->fetch_assoc();
}

function clear_student_course_plan_revocation(int $studentId, int $courseId): void
{
    $stmt = db()->prepare('DELETE FROM student_course_plan_revocations WHERE student_id = ? AND course_id = ?');
    $stmt->bind_param('ii', $studentId, $courseId);
    $stmt->execute();
}

function student_plan_course_allowed(int $studentId, int $courseId): bool
{
    if (student_course_plan_revoked($studentId, $courseId)) {
        return false;
    }

    $subscription = active_plan_subscription($studentId);
    if (!$subscription) {
        return false;
    }

    [$windowStart, $windowEnd] = plan_limit_window($subscription);
    $stmt = db()->prepare('SELECT id FROM student_course_enrollments WHERE student_id = ? AND course_id = ? AND purchase_id IS NULL AND status IN ("active","completed") AND enrolled_at BETWEEN ? AND ? LIMIT 1');
    $stmt->bind_param('iiss', $studentId, $courseId, $windowStart, $windowEnd);
    $stmt->execute();
    $alreadyUsed = (bool) $stmt->get_result()->fetch_assoc();

    $stmt = db()->prepare('SELECT COUNT(*) AS total FROM student_course_enrollments WHERE student_id = ? AND purchase_id IS NULL AND status IN ("active","completed") AND enrolled_at BETWEEN ? AND ?');
    $stmt->bind_param('iss', $studentId, $windowStart, $windowEnd);
    $stmt->execute();
    $used = (int) ($stmt->get_result()->fetch_assoc()['total'] ?? 0);

    return plan_limit_allows($subscription, 'video_limit', $used, $alreadyUsed);
}

function ensure_plan_course_enrollment(int $studentId, int $courseId): void
{
    if (student_course_plan_revoked($studentId, $courseId)) {
        return;
    }

    $stmt = db()->prepare("
        INSERT INTO student_course_enrollments (student_id, course_id, purchase_id, status)
        VALUES (?, ?, NULL, 'active')
        ON DUPLICATE KEY UPDATE
            enrolled_at = IF(purchase_id IS NULL, NOW(), enrolled_at),
            status = IF(purchase_id IS NULL AND status = 'cancelled', 'active', status)
    ");
    $stmt->bind_param('ii', $studentId, $courseId);
    $stmt->execute();
}

function student_has_course_access(int $studentId, int $courseId): bool
{
    $stmt = db()->prepare('
        SELECT id
        FROM student_course_purchases
        WHERE student_id = ? AND course_id = ? AND payment_status = "paid"
        LIMIT 1
    ');
    $stmt->bind_param('ii', $studentId, $courseId);
    $stmt->execute();
    $purchase = $stmt->get_result()->fetch_assoc();
    if ($purchase) {
        $purchaseId = (int) $purchase['id'];
        $stmt = db()->prepare('
            INSERT INTO student_course_enrollments (student_id, course_id, purchase_id, status)
            VALUES (?, ?, ?, "active")
            ON DUPLICATE KEY UPDATE purchase_id = VALUES(purchase_id), status = IF(status = "cancelled", "active", status)
        ');
        $stmt->bind_param('iii', $studentId, $courseId, $purchaseId);
        $stmt->execute();
        return true;
    }

    if (student_course_plan_revoked($studentId, $courseId)) {
        return false;
    }

    $subscription = active_plan_subscription($studentId);
    if (!$subscription) {
        return false;
    }
    [$windowStart, $windowEnd] = plan_limit_window($subscription);
    $stmt = db()->prepare('
        SELECT e.id
        FROM student_course_enrollments e
        WHERE e.student_id = ?
          AND e.course_id = ?
          AND e.purchase_id IS NULL
          AND e.status IN ("active","completed")
          AND e.enrolled_at BETWEEN ? AND ?
        LIMIT 1
    ');
    $stmt->bind_param('iiss', $studentId, $courseId, $windowStart, $windowEnd);
    $stmt->execute();
    return (bool) $stmt->get_result()->fetch_assoc();
}

function student_can_access_course(int $studentId, int $courseId, bool $autoEnroll = true): bool
{
    if (student_has_course_access($studentId, $courseId)) {
        return true;
    }
    if (student_plan_course_allowed($studentId, $courseId)) {
        if ($autoEnroll) {
            ensure_plan_course_enrollment($studentId, $courseId);
        }
        return true;
    }
    return false;
}

function student_plan_pdf_allowed(int $studentId, int $resourceId): bool
{
    $subscription = active_plan_subscription($studentId);
    if (!$subscription) {
        return false;
    }

    $stmt = db()->prepare('SELECT id FROM student_resource_access WHERE student_id = ? AND resource_id = ? LIMIT 1');
    $stmt->bind_param('ii', $studentId, $resourceId);
    $stmt->execute();
    $alreadyUsed = (bool) $stmt->get_result()->fetch_assoc();

    [$windowStart, $windowEnd] = plan_limit_window($subscription);
    $stmt = db()->prepare('SELECT COUNT(DISTINCT resource_id) AS total FROM student_resource_access WHERE student_id = ? AND first_accessed_at BETWEEN ? AND ?');
    $stmt->bind_param('iss', $studentId, $windowStart, $windowEnd);
    $stmt->execute();
    $used = (int) ($stmt->get_result()->fetch_assoc()['total'] ?? 0);

    return plan_limit_allows($subscription, 'pdf_limit', $used, $alreadyUsed);
}

function student_plan_live_allowed(int $studentId, string $sourceType, int $sourceId): bool
{
    $subscription = active_plan_subscription($studentId);
    if (!$subscription || $sourceId <= 0) {
        return false;
    }

    $stmt = db()->prepare('SELECT id FROM student_live_class_access WHERE student_id = ? AND source_type = ? AND source_id = ? LIMIT 1');
    $stmt->bind_param('isi', $studentId, $sourceType, $sourceId);
    $stmt->execute();
    $alreadyUsed = (bool) $stmt->get_result()->fetch_assoc();

    [$windowStart, $windowEnd] = plan_limit_window($subscription);
    $stmt = db()->prepare('SELECT COUNT(DISTINCT CONCAT(source_type, ":", source_id)) AS total FROM student_live_class_access WHERE student_id = ? AND first_accessed_at BETWEEN ? AND ?');
    $stmt->bind_param('iss', $studentId, $windowStart, $windowEnd);
    $stmt->execute();
    $used = (int) ($stmt->get_result()->fetch_assoc()['total'] ?? 0);

    return plan_limit_allows($subscription, 'live_class_limit', $used, $alreadyUsed);
}

function register_plan_live_access(int $studentId, string $sourceType, int $sourceId, int $courseId = 0): void
{
    if ($studentId <= 0 || $sourceId <= 0) {
        return;
    }
    $courseValue = $courseId > 0 ? $courseId : null;
    $stmt = db()->prepare('
        INSERT INTO student_live_class_access (student_id, source_type, source_id, course_id, first_accessed_at, last_accessed_at)
        VALUES (?, ?, ?, ?, NOW(), NOW())
        ON DUPLICATE KEY UPDATE last_accessed_at = NOW(), course_id = COALESCE(VALUES(course_id), course_id)
    ');
    $stmt->bind_param('isii', $studentId, $sourceType, $sourceId, $courseValue);
    $stmt->execute();
}

function student_has_resource_purchase(int $studentId, int $resourceId): bool
{
    $stmt = db()->prepare('SELECT id FROM student_resource_purchases WHERE student_id = ? AND resource_id = ? AND payment_status = "paid" LIMIT 1');
    $stmt->bind_param('ii', $studentId, $resourceId);
    $stmt->execute();
    return (bool) $stmt->get_result()->fetch_assoc();
}

function student_can_access_resource(int $studentId, array $resource): bool
{
    $resourceId = (int) ($resource['id'] ?? 0);
    $courseId = (int) ($resource['course_id'] ?? 0);
    $price = (float) ($resource['price'] ?? 0);
    if ($resourceId <= 0 || $courseId <= 0) {
        return false;
    }
    return $price <= 0
        || student_has_resource_purchase($studentId, $resourceId)
        || student_can_access_course($studentId, $courseId, false)
        || student_plan_pdf_allowed($studentId, $resourceId);
}

function ensure_resource_purchase(int $studentId, array $resource): array
{
    $resourceId = (int) $resource['id'];
    $courseId = (int) $resource['course_id'];
    $amount = max(0, (float) ($resource['price'] ?? 0));

    if (!student_has_resource_purchase($studentId, $resourceId)) {
        $txn = 'PDF-' . date('ymdHis') . '-' . random_int(1000, 9999);
        $method = $amount <= 0 ? 'free' : 'manual';
        $stmt = db()->prepare('
            INSERT INTO student_resource_purchases (student_id, resource_id, course_id, original_amount, amount, payment_method, payment_status, transaction_no)
            VALUES (?, ?, ?, ?, ?, ?, "paid", ?)
            ON DUPLICATE KEY UPDATE original_amount = VALUES(original_amount), amount = VALUES(amount), payment_method = VALUES(payment_method), payment_status = "paid", transaction_no = VALUES(transaction_no)
        ');
        $stmt->bind_param('iiiddss', $studentId, $resourceId, $courseId, $amount, $amount, $method, $txn);
        $stmt->execute();
    }

    register_pdf_access($studentId, $resourceId, $courseId);
    return [
        'id' => $resourceId,
        'course_id' => $courseId,
        'resource_title' => $resource['resource_title'] ?? 'Course PDF',
        'resource_type' => $resource['resource_type'] ?? 'pdf',
        'price' => $amount,
        'locked' => false,
        'thumbnail_url' => course_resource_thumbnail_url($resource),
        'file_url' => api_absolute_url('api/student?action=download_pdf&id=' . $resourceId),
        'download_url' => api_absolute_url('api/student?action=download_pdf&id=' . $resourceId),
    ];
}

function register_pdf_access(int $studentId, int $resourceId, int $courseId): void
{
    $stmt = db()->prepare("
        INSERT INTO student_resource_access (student_id, resource_id, course_id, first_accessed_at, last_accessed_at, access_count)
        VALUES (?, ?, ?, NOW(), NOW(), 1)
        ON DUPLICATE KEY UPDATE last_accessed_at = NOW(), access_count = access_count + 1
    ");
    $stmt->bind_param('iii', $studentId, $resourceId, $courseId);
    $stmt->execute();
}

function pdf_progress_row(int $studentId, int $resourceId): array
{
    $stmt = db()->prepare('SELECT current_page, total_pages, progress_percent, access_count, first_accessed_at, last_accessed_at, completed_at FROM student_resource_access WHERE student_id = ? AND resource_id = ? LIMIT 1');
    $stmt->bind_param('ii', $studentId, $resourceId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    return $row ?: [
        'current_page' => 1,
        'total_pages' => 1,
        'progress_percent' => 0,
        'access_count' => 0,
        'first_accessed_at' => null,
        'last_accessed_at' => null,
        'completed_at' => null,
    ];
}

function pdf_completion_gate(int $studentId, array $resource): array
{
    $resourceId = (int) ($resource['id'] ?? 0);
    $pageCount = pdf_file_page_count((string) ($resource['file_path'] ?? ''));
    $progress = pdf_progress_row($studentId, $resourceId);
    $firstAccess = $progress['first_accessed_at'] ?? null;
    $completedAt = $progress['completed_at'] ?? null;
    $requiredHours = max(1, $pageCount);

    if ($completedAt) {
        return [
            'completed' => true,
            'can_mark' => false,
            'required_hours' => $requiredHours,
            'available_at' => null,
            'message' => 'PDF already marked complete.',
        ];
    }

    if (!$firstAccess) {
        return [
            'completed' => false,
            'can_mark' => false,
            'required_hours' => $requiredHours,
            'available_at' => null,
            'message' => 'Open this PDF once to start completion timer.',
        ];
    }

    $availableTs = strtotime((string) $firstAccess . ' +' . $requiredHours . ' hours');
    $now = time();
    return [
        'completed' => false,
        'can_mark' => $availableTs !== false && $now >= $availableTs,
        'required_hours' => $requiredHours,
        'available_at' => $availableTs ? date('Y-m-d H:i:s', $availableTs) : null,
        'message' => $availableTs && $now < $availableTs
            ? 'Mark complete will unlock after required reading time.'
            : 'You can mark this PDF as complete.',
    ];
}

function my_pdf_rows(int $studentId): array
{
    $stmt = db()->prepare('
        SELECT a.resource_id AS id, a.course_id, a.current_page, a.total_pages, a.progress_percent,
               a.access_count, a.first_accessed_at, a.last_accessed_at, a.completed_at,
               r.resource_title, r.resource_type, r.document_purpose, r.price, r.thumbnail_path,
               c.title AS course_title, c.category,
               e.title AS exam_title, ec.category_name AS exam_category_name,
               u.full_name AS instructor_name
        FROM student_resource_access a
        INNER JOIN instructor_course_resources r ON r.id = a.resource_id
        LEFT JOIN instructor_courses c ON c.id = a.course_id
        LEFT JOIN instructor_exams e ON e.id = r.exam_id
        LEFT JOIN instructor_exam_categories ec ON ec.id = COALESCE(r.exam_category_id, e.exam_category_id)
        LEFT JOIN users u ON u.id = r.instructor_id
        WHERE a.student_id = ?
        ORDER BY a.last_accessed_at DESC, a.id DESC
        LIMIT 80
    ');
    $stmt->bind_param('i', $studentId);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    foreach ($rows as &$row) {
        $row['thumbnail_url'] = course_resource_thumbnail_url($row);
        $row['file_url'] = api_absolute_url('api/student?action=download_pdf&id=' . (int) $row['id']);
        $row['download_url'] = $row['file_url'];
        $row['locked'] = false;
    }
    unset($row);
    return $rows;
}

function student_plan_exam_allowed(int $studentId, int $examId): bool
{
    $subscription = active_plan_subscription($studentId);
    if (!$subscription) {
        return false;
    }
    [$windowStart, $windowEnd] = plan_limit_window($subscription);

    $stmt = db()->prepare('SELECT id FROM student_exam_sessions WHERE student_id = ? AND exam_id = ? AND started_at BETWEEN ? AND ? LIMIT 1');
    $stmt->bind_param('iiss', $studentId, $examId, $windowStart, $windowEnd);
    $stmt->execute();
    $alreadyUsed = (bool) $stmt->get_result()->fetch_assoc();
    if (!$alreadyUsed) {
        $stmt = db()->prepare('SELECT id FROM student_exam_attempts WHERE student_id = ? AND exam_id = ? AND started_at BETWEEN ? AND ? LIMIT 1');
        $stmt->bind_param('iiss', $studentId, $examId, $windowStart, $windowEnd);
        $stmt->execute();
        $alreadyUsed = (bool) $stmt->get_result()->fetch_assoc();
    }

    $stmt = db()->prepare("
        SELECT COUNT(DISTINCT exam_id) AS total FROM (
            SELECT exam_id FROM student_exam_sessions WHERE student_id = ? AND started_at BETWEEN ? AND ?
            UNION
            SELECT exam_id FROM student_exam_attempts WHERE student_id = ? AND started_at BETWEEN ? AND ?
        ) x
    ");
    $stmt->bind_param('ississ', $studentId, $windowStart, $windowEnd, $studentId, $windowStart, $windowEnd);
    $stmt->execute();
    $used = (int) ($stmt->get_result()->fetch_assoc()['total'] ?? 0);

    return plan_limit_allows($subscription, 'exam_limit', $used, $alreadyUsed);
}

function student_can_access_exam(int $studentId, array $exam): bool
{
    $examId = (int) ($exam['id'] ?? 0);
    $courseId = (int) ($exam['course_id'] ?? 0);
    if ($courseId > 0 && student_can_access_course($studentId, $courseId, false)) {
        return true;
    }
    return $examId > 0 && student_plan_exam_allowed($studentId, $examId);
}

function gov_plan_allowed(int $studentId, string $kind, int $itemId): bool
{
    $subscription = active_plan_subscription($studentId);
    if (!$subscription || $itemId <= 0) {
        return false;
    }
    $config = [
        'document' => ['table' => 'student_gov_document_access', 'column' => 'document_id', 'limit' => 'pdf_limit', 'date' => 'first_accessed_at'],
        'live' => ['table' => 'student_gov_live_access', 'column' => 'live_id', 'limit' => 'live_class_limit', 'date' => 'first_accessed_at'],
        'mock' => ['table' => 'student_gov_mock_access', 'column' => 'mock_test_id', 'limit' => 'mock_test_limit', 'date' => 'first_accessed_at'],
    ][$kind] ?? null;
    if (!$config) {
        return false;
    }
    $table = $config['table'];
    $column = $config['column'];
    $dateColumn = $config['date'];
    [$windowStart, $windowEnd] = plan_limit_window($subscription);
    $stmt = db()->prepare("SELECT id FROM {$table} WHERE student_id = ? AND {$column} = ? AND {$dateColumn} BETWEEN ? AND ? LIMIT 1");
    $stmt->bind_param('iiss', $studentId, $itemId, $windowStart, $windowEnd);
    $stmt->execute();
    $alreadyUsed = (bool) $stmt->get_result()->fetch_assoc();
    $stmt = db()->prepare("SELECT COUNT(DISTINCT {$column}) AS total FROM {$table} WHERE student_id = ? AND {$dateColumn} BETWEEN ? AND ?");
    $stmt->bind_param('iss', $studentId, $windowStart, $windowEnd);
    $stmt->execute();
    $used = (int) ($stmt->get_result()->fetch_assoc()['total'] ?? 0);
    return plan_limit_allows($subscription, $config['limit'], $used, $alreadyUsed);
}

function register_gov_document_access(int $studentId, int $documentId): void
{
    $stmt = db()->prepare('INSERT INTO student_gov_document_access (student_id, document_id, first_accessed_at, last_accessed_at, access_count) VALUES (?, ?, NOW(), NOW(), 1) ON DUPLICATE KEY UPDATE last_accessed_at = NOW(), access_count = access_count + 1');
    $stmt->bind_param('ii', $studentId, $documentId);
    $stmt->execute();
}

function register_gov_live_access(int $studentId, int $liveId): void
{
    $stmt = db()->prepare('INSERT INTO student_gov_live_access (student_id, live_id, first_accessed_at, last_accessed_at, access_count) VALUES (?, ?, NOW(), NOW(), 1) ON DUPLICATE KEY UPDATE last_accessed_at = NOW(), access_count = access_count + 1');
    $stmt->bind_param('ii', $studentId, $liveId);
    $stmt->execute();
}

function register_gov_mock_access(int $studentId, int $mockId): void
{
    $stmt = db()->prepare('INSERT INTO student_gov_mock_access (student_id, mock_test_id, first_accessed_at, last_accessed_at, access_count) VALUES (?, ?, NOW(), NOW(), 1) ON DUPLICATE KEY UPDATE last_accessed_at = NOW(), access_count = access_count + 1');
    $stmt->bind_param('ii', $studentId, $mockId);
    $stmt->execute();
}

function gov_category_smart_description(string $name, ?string $parentName = null): string
{
    $name = trim($name) !== '' ? trim($name) : 'Government Exam';
    $parent = trim((string) $parentName);
    $lower = strtolower($name . ' ' . $parent);
    if (preg_match('/sbi|bank|rbi/', $lower)) {
        return "$name preparation covers banking awareness, reasoning, quantitative aptitude, English and previous-year style mock practice for bank exam aspirants.";
    }
    if (preg_match('/jee|iit|engineering/', $lower)) {
        return "$name preparation focuses on Physics, Chemistry and Mathematics practice with exam-style papers, timed mocks and subject-wise performance review.";
    }
    if (preg_match('/ctet|utet|teacher|pedagogy|child development|hindi|sanskrit|environmental|mathematics/', $lower)) {
        return "$name preparation includes pedagogy, language, EVS, mathematics and teaching-method questions designed for teacher eligibility exam practice.";
    }
    if (preg_match('/police|rpf|constable|sub inspector/', $lower)) {
        return "$name preparation includes reasoning, general awareness, law-and-order aptitude, previous paper practice and timed mock tests for uniformed services.";
    }
    if (preg_match('/rpsc|rajasthan|ukpsc|uksssc|hppsc|mppsc|lekhpal|state/', $lower)) {
        return "$name preparation brings state-level GK, reasoning, subject knowledge and previous-year style mock tests into one focused practice track.";
    }
    if (preg_match('/ssc|gd|cgl|chsl/', $lower)) {
        return "$name preparation covers reasoning, general awareness, quantitative aptitude and English through structured mock tests and previous paper practice.";
    }
    if (preg_match('/railway|rrb/', $lower)) {
        return "$name preparation includes railway-focused reasoning, mathematics, general science and current affairs practice with timed exam sets.";
    }
    if (preg_match('/full paper|memory based|previous/', $lower)) {
        return "$name contains full-length previous-year or memory-based practice sets so learners can revise the real exam pattern under timed conditions.";
    }
    $context = $parent !== '' ? " under $parent" : '';
    return "$name$context includes curated mock tests, important practice questions, exam-style sets and progress tracking for focused preparation.";
}
function gov_categories_payload(): array
{
    $rows = db()->query("SELECT id, parent_id, name, description FROM gov_exam_categories WHERE status='active' ORDER BY COALESCE(parent_id, id), parent_id IS NOT NULL, sort_order, name")->fetch_all(MYSQLI_ASSOC);
    $namesById = [];
    foreach ($rows as $raw) {
        $namesById[(int) $raw['id']] = (string) $raw['name'];
    }
    foreach ($rows as &$row) {
        $row['id'] = (int) $row['id'];
        $row['parent_id'] = $row['parent_id'] !== null ? (int) $row['parent_id'] : null;
        $parentName = $row['parent_id'] ? ($namesById[$row['parent_id']] ?? '') : '';
        $smart = gov_category_smart_description((string) $row['name'], $parentName);
        $current = trim((string) ($row['description'] ?? ''));
        $generic = $current === '' || preg_match('/preparation,\s*PDFs,\s*live classes and mock tests|original practice sets/i', $current);
        $row['description'] = (!$generic && strlen($current) >= 70) ? $current : $smart;
        $row['summary'] = $smart;
        $row['exam_focus'] = $parentName !== '' ? "$parentName > {$row['name']}" : (string) $row['name'];
    }
    unset($row);
    return $rows;
}

function gov_documents_payload(?int $studentId = null): array
{
    $rows = db()->query("SELECT d.*, c.name AS category_name, s.name AS subcategory_name FROM gov_exam_documents d LEFT JOIN gov_exam_categories c ON c.id=d.category_id LEFT JOIN gov_exam_categories s ON s.id=d.subcategory_id WHERE d.status='published' ORDER BY d.sort_order ASC, d.id DESC")->fetch_all(MYSQLI_ASSOC);
    foreach ($rows as &$row) {
        $id = (int) $row['id'];
        $allowed = $studentId !== null && gov_plan_allowed($studentId, 'document', $id);
        $row['id'] = $id;
        $row['price'] = (float) $row['price'];
        $row['locked'] = !$allowed;
        $row['open_url'] = $allowed ? api_absolute_url('api/student?action=gov_document_open&id=' . $id) : '';
    }
    unset($row);
    return $rows;
}

function gov_live_payload(?int $studentId = null): array
{
    $rows = db()->query("SELECT l.*, c.name AS category_name, s.name AS subcategory_name FROM gov_exam_live_sessions l LEFT JOIN gov_exam_categories c ON c.id=l.category_id LEFT JOIN gov_exam_categories s ON s.id=l.subcategory_id WHERE l.status IN ('scheduled','live') ORDER BY FIELD(l.status,'live','scheduled'), l.scheduled_at IS NULL, l.scheduled_at ASC, l.id DESC")->fetch_all(MYSQLI_ASSOC);
    foreach ($rows as &$row) {
        $id = (int) $row['id'];
        $allowed = $studentId !== null && gov_plan_allowed($studentId, 'live', $id);
        $row['id'] = $id;
        $row['locked'] = !$allowed;
        $row['join_url'] = $allowed ? (string) ($row['live_url'] ?? '') : '';
    }
    unset($row);
    return $rows;
}

function gov_mocks_payload(?int $studentId = null): array
{
    $rows = db()->query("SELECT m.*, c.name AS category_name, s.name AS subcategory_name, (SELECT COUNT(*) FROM gov_exam_mock_questions q WHERE q.mock_test_id=m.id AND q.status='active') AS question_count FROM gov_exam_mock_tests m LEFT JOIN gov_exam_categories c ON c.id=m.category_id LEFT JOIN gov_exam_categories s ON s.id=m.subcategory_id WHERE m.status='published' ORDER BY m.id DESC")->fetch_all(MYSQLI_ASSOC);
    foreach ($rows as &$row) {
        $id = (int) $row['id'];
        $row['id'] = $id;
        $row['duration_minutes'] = (int) $row['duration_minutes'];
        $row['question_count'] = (int) $row['question_count'];
        $row['thumbnail_url'] = api_absolute_url((string) ($row['thumbnail_path'] ?? ''));
        $row['locked'] = !($studentId !== null && gov_plan_allowed($studentId, 'mock', $id));
        $row['last_attempt'] = null;
        if ($studentId !== null) {
            $stmt = db()->prepare('SELECT score,total_marks,percentage,submitted_at FROM student_gov_mock_attempts WHERE student_id=? AND mock_test_id=? ORDER BY id DESC LIMIT 1');
            $stmt->bind_param('ii', $studentId, $id);
            $stmt->execute();
            $row['last_attempt'] = $stmt->get_result()->fetch_assoc() ?: null;
        }
    }
    unset($row);
    return $rows;
}


function gov_mocks_fast_payload(?int $categoryId = null, ?int $parentId = null, int $limit = 320): array
{
    $categoryId = max(0, (int) $categoryId);
    $parentId = max(0, (int) $parentId);
    $limit = max(1, min(600, (int) $limit));
    $where = "m.status='published'";
    if ($categoryId > 0) {
        $where .= ' AND (m.subcategory_id=' . $categoryId . ' OR m.category_id=' . $categoryId . ')';
    } elseif ($parentId > 0) {
        $where .= ' AND m.category_id=' . $parentId;
    }
    $sql = "SELECT m.id, m.category_id, m.subcategory_id, m.title, m.description, m.duration_minutes, m.thumbnail_path,
            c.name AS category_name, s.name AS subcategory_name
        FROM gov_exam_mock_tests m
        LEFT JOIN gov_exam_categories c ON c.id=m.category_id
        LEFT JOIN gov_exam_categories s ON s.id=m.subcategory_id
        WHERE $where
        ORDER BY m.id DESC
        LIMIT $limit";
    $rows = db()->query($sql)->fetch_all(MYSQLI_ASSOC);
    foreach ($rows as &$row) {
        $row['id'] = (int) $row['id'];
        $row['category_id'] = (int) ($row['category_id'] ?? 0);
        $row['subcategory_id'] = (int) ($row['subcategory_id'] ?? 0);
        $row['duration_minutes'] = (int) ($row['duration_minutes'] ?? 0);
        $titleQuestionCount = 0;
        if (preg_match('/(\\d+)\\s*Questions?/i', (string) ($row['title'] ?? ''), $matches)) {
            $titleQuestionCount = (int) $matches[1];
        }
        $row['question_count'] = $titleQuestionCount;
        $row['total_questions'] = $titleQuestionCount;
        $row['thumbnail_url'] = api_absolute_url((string) ($row['thumbnail_path'] ?? ''));
        $row['locked'] = true;
        $row['last_attempt'] = null;
    }
    unset($row);
    return $rows;
}
function gov_mock_detail_row(int $mockId): ?array
{
    $stmt = db()->prepare("SELECT m.*, c.name AS category_name, s.name AS subcategory_name FROM gov_exam_mock_tests m LEFT JOIN gov_exam_categories c ON c.id=m.category_id LEFT JOIN gov_exam_categories s ON s.id=m.subcategory_id WHERE m.id=? AND m.status='published' LIMIT 1");
    $stmt->bind_param('i', $mockId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    if (!$row) {
        return null;
    }
    $row['id'] = (int) $row['id'];
    $row['duration_minutes'] = (int) $row['duration_minutes'];
    $row['thumbnail_url'] = api_absolute_url((string) ($row['thumbnail_path'] ?? ''));
    return $row;
}

function gov_mock_questions(int $mockId, bool $withCorrect = false): array
{
    $stmt = db()->prepare('SELECT id, question_en, question_hi, option_a_en, option_a_hi, option_b_en, option_b_hi, option_c_en, option_c_hi, option_d_en, option_d_hi, option_e_en, option_e_hi, marks, negative_marks, explanation_en, explanation_hi, correct_answer FROM gov_exam_mock_questions WHERE mock_test_id=? AND status="active" ORDER BY sort_order ASC, id ASC');
    $stmt->bind_param('i', $mockId);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    foreach ($rows as &$row) {
        $options = [
            'A' => ['en' => (string) $row['option_a_en'], 'hi' => (string) $row['option_a_hi']],
            'B' => ['en' => (string) $row['option_b_en'], 'hi' => (string) $row['option_b_hi']],
            'C' => ['en' => (string) $row['option_c_en'], 'hi' => (string) $row['option_c_hi']],
            'D' => ['en' => (string) $row['option_d_en'], 'hi' => (string) $row['option_d_hi']],
        ];
        if ((string) ($row['option_e_en'] ?? '') !== '' || (string) ($row['option_e_hi'] ?? '') !== '') {
            $options['E'] = ['en' => (string) ($row['option_e_en'] ?? ''), 'hi' => (string) ($row['option_e_hi'] ?? '')];
        }
        $row = [
            'id' => (int) $row['id'],
            'question_en' => (string) $row['question_en'],
            'question_hi' => (string) $row['question_hi'],
            'options' => $options,
            'marks' => (float) $row['marks'],
            'negative_marks' => (float) $row['negative_marks'],
            'explanation_en' => $withCorrect ? (string) $row['explanation_en'] : '',
            'explanation_hi' => $withCorrect ? (string) $row['explanation_hi'] : '',
            'correct_answer' => $withCorrect ? (string) $row['correct_answer'] : '',
        ];
    }
    unset($row);
    return $rows;
}

function save_gov_mock_attempt(int $studentId, array $mock, array $answers): array
{
    $questions = gov_mock_questions((int) $mock['id'], true);
    if (!$questions) {
        api_out(['success' => false, 'message' => 'No questions available in this mock test.'], 422);
    }
    $answerMap = [];
    foreach ($answers as $qid => $answer) {
        $answerMap[(int) $qid] = strtoupper(trim((string) $answer));
    }
    $score = 0.0;
    $totalMarks = 0.0;
    $correct = 0;
    $wrong = 0;
    $skipped = 0;
    $review = [];
    foreach ($questions as $question) {
        $qid = (int) $question['id'];
        $selected = $answerMap[$qid] ?? '';
        $correctKey = strtoupper((string) $question['correct_answer']);
        $marks = (float) $question['marks'];
        $negative = (float) $question['negative_marks'];
        $earned = 0.0;
        $totalMarks += $marks;
        if ($selected === '') {
            $skipped++;
        } elseif ($selected === $correctKey) {
            $correct++;
            $earned = $marks;
        } else {
            $wrong++;
            $earned = -$negative;
        }
        $score += $earned;
        $question['selected_answer'] = $selected;
        $question['earned_marks'] = $earned;
        $review[] = $question;
    }
    $percentage = $totalMarks > 0 ? max(0, min(100, ($score / $totalMarks) * 100)) : 0;
    $answerJson = api_json(['answers' => $answerMap, 'review' => $review]);
    $now = date('Y-m-d H:i:s');
    $mockId = (int) $mock['id'];
    $totalQuestions = count($questions);
    $stmt = db()->prepare('INSERT INTO student_gov_mock_attempts (student_id, mock_test_id, score, total_marks, total_questions, correct_count, wrong_count, skipped_count, percentage, answer_json, started_at, submitted_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
    $stmt->bind_param('iiddiiiidsss', $studentId, $mockId, $score, $totalMarks, $totalQuestions, $correct, $wrong, $skipped, $percentage, $answerJson, $now, $now);
    $stmt->execute();
    return ['attempt_id' => (int) db()->insert_id, 'score' => round($score, 2), 'total_marks' => round($totalMarks, 2), 'total_questions' => $totalQuestions, 'correct_count' => $correct, 'wrong_count' => $wrong, 'skipped_count' => $skipped, 'percentage' => round($percentage, 2), 'review' => $review];
}

function content_play_url(array $chapter): string
{
    $url = trim((string) ($chapter['resource_url'] ?? ''));
    if ($url === '') {
        return '';
    }
    $type = strtolower(trim((string) ($chapter['content_type'] ?? '')));
    if (in_array($type, ['pdf', 'doc', 'document', 'video_upload', 'file'], true) && !preg_match('#^https?://#i', $url)) {
        return api_absolute_url($url);
    }
    return $url;
}

function course_enrolled_display_boost(int $courseId): int
{
    return 200 + (($courseId * 137) % 601);
}

function course_like_display_boost(int $courseId): int
{
    $percent = 18 + (($courseId * 19) % 23);
    return (int) floor(course_enrolled_display_boost($courseId) * $percent / 100);
}

function student_course_progress(int $studentId, int $courseId): array
{
    $stmt = db()->prepare("
        SELECT cc.id, cc.duration_minutes, COALESCE(p.watched_seconds, 0) AS watched_seconds,
               COALESCE(p.duration_seconds, cc.duration_minutes * 60, 0) AS duration_seconds,
               COALESCE(p.progress_percent, 0) AS progress_percent,
               p.completed_at
        FROM instructor_course_contents cc
        LEFT JOIN student_content_progress p ON p.content_id = cc.id AND p.student_id = ?
        WHERE cc.course_id = ? AND cc.status = 'published'
        ORDER BY
            CASE WHEN cc.content_title REGEXP '^[0-9]+' THEN CAST(SUBSTRING_INDEX(cc.content_title, ' ', 1) AS UNSIGNED) ELSE 999999 END ASC,
            cc.sort_order ASC,
            cc.id ASC
    ");
    $stmt->bind_param('ii', $studentId, $courseId);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    $totalDuration = 0;
    $watchedDuration = 0;
    $items = [];
    foreach ($rows as $index => $row) {
        $duration = max(60, (int) ($row['duration_seconds'] ?: ((int) $row['duration_minutes'] * 60)));
        $watched = min($duration, max(0, (int) $row['watched_seconds']));
        $percent = $duration > 0 ? min(100, round(($watched / $duration) * 100, 2)) : (float) $row['progress_percent'];
        $totalDuration += $duration;
        $watchedDuration += $watched;
        $items[(string) $row['id']] = [
            'watched_seconds' => $watched,
            'duration_seconds' => $duration,
            'progress_percent' => $percent,
            'completed' => $percent >= 80 || $row['completed_at'] !== null,
            'locked' => $index > 0,
        ];
    }

    $previousUnlocked = true;
    foreach ($items as $id => &$item) {
        $item['locked'] = !$previousUnlocked;
        $previousUnlocked = $item['progress_percent'] >= 80;
    }

    $coursePercent = $totalDuration > 0 ? min(100, round(($watchedDuration / $totalDuration) * 100, 2)) : 0;
    $achievement = null;
    $stmt = db()->prepare('SELECT stars, rank_title, achieved_at FROM student_course_achievements WHERE student_id = ? AND course_id = ? LIMIT 1');
    $stmt->bind_param('ii', $studentId, $courseId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    if ($row) {
        $achievement = $row;
    }

    return [
        'course_percent' => $coursePercent,
        'watched_seconds' => $watchedDuration,
        'duration_seconds' => $totalDuration,
        'items' => $items,
        'achievement' => $achievement,
    ];
}

function api_role_id(string $slug): int
{
    $stmt = db()->prepare('SELECT id FROM roles WHERE slug = ? LIMIT 1');
    $stmt->bind_param('s', $slug);
    $stmt->execute();
    return (int) ($stmt->get_result()->fetch_assoc()['id'] ?? 0);
}

function issue_token(int $userId): string
{
    $token = bin2hex(random_bytes(32));
    $hash = hash('sha256', $token);
    $expires = date('Y-m-d H:i:s', time() + 86400 * 30);
    $stmt = db()->prepare('INSERT INTO student_app_tokens (user_id, token_hash, expires_at) VALUES (?, ?, ?)');
    $stmt->bind_param('iss', $userId, $hash, $expires);
    $stmt->execute();
    return $token;
}

function bearer_token(): string
{
    $input = api_input();
    $header = $_SERVER['HTTP_AUTHORIZATION']
        ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION']
        ?? $_SERVER['Authorization']
        ?? '';

    if ($header === '' && function_exists('apache_request_headers')) {
        $headers = apache_request_headers();
        $header = (string) ($headers['Authorization'] ?? $headers['authorization'] ?? '');
    }

    if (preg_match('/Bearer\s+(.+)/i', $header, $m)) {
        return trim($m[1]);
    }

    return trim((string) ($_GET['token'] ?? $input['token'] ?? ''));
}

function api_user(): array
{
    $token = bearer_token();
    if ($token === '') {
        api_out(['success' => false, 'message' => 'Login required.'], 401);
    }

    $hash = hash('sha256', $token);
    $stmt = db()->prepare("
        SELECT u.id, u.full_name, u.username, u.email, u.phone, u.profile_photo, u.status
        FROM student_app_tokens t
        INNER JOIN users u ON u.id = t.user_id
        INNER JOIN roles r ON r.id = u.role_id
        WHERE t.token_hash = ? AND t.expires_at > NOW() AND r.slug = 'student'
        LIMIT 1
    ");
    $stmt->bind_param('s', $hash);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();

    if (!$user || $user['status'] !== 'active') {
        api_out(['success' => false, 'message' => 'Account inactive or session expired.'], 401);
    }

    return $user;
}

function api_optional_user(): ?array
{
    $token = bearer_token();
    if ($token === '') {
        return null;
    }

    $hash = hash('sha256', $token);
    $stmt = db()->prepare("
        SELECT u.id, u.full_name, u.username, u.email, u.phone, u.profile_photo, u.status
        FROM student_app_tokens t
        INNER JOIN users u ON u.id = t.user_id
        INNER JOIN roles r ON r.id = u.role_id
        WHERE t.token_hash = ? AND t.expires_at > NOW() AND r.slug = 'student'
        LIMIT 1
    ");
    $stmt->bind_param('s', $hash);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();

    if (!$user || $user['status'] !== 'active') {
        return null;
    }

    return $user;
}

function course_rows(?int $courseId = null, ?int $studentId = null): array
{
    $where = "WHERE c.status = 'published'";
    $types = '';
    $params = [];
    $viewerId = max(0, (int) ($studentId ?? 0));
    if ($courseId) {
        $where .= ' AND c.id = ?';
        $types = 'i';
        $params[] = $courseId;
    }

    $sql = "
        SELECT c.id, c.title, c.category, c.category_id, c.subcategory_id, c.price, c.original_price, c.thumbnail_path, c.price_unit, c.learning_mode, c.course_level,
               c.course_language, c.duration, c.city, c.locality, c.featured, c.is_free,
               c.short_description, c.call_number, c.whatsapp_number, c.instructor_id,
               cat.name AS category_name, sub.name AS subcategory_name, u.full_name AS instructor_name,
               (SELECT COUNT(*) FROM instructor_course_contents cc WHERE cc.course_id = c.id AND cc.status = 'published') AS chapter_count,
               ((SELECT COUNT(*) FROM student_course_enrollments e WHERE e.course_id = c.id AND e.status IN ('active','completed')) + 200 + MOD(c.id * 137, 601)) AS enrolled_count,
               (SELECT COUNT(*) FROM student_instructor_follows sf WHERE sf.instructor_id = c.instructor_id) AS follower_count,
               (SELECT COUNT(*) FROM student_instructor_follows sf WHERE sf.student_id = {$viewerId} AND sf.instructor_id = c.instructor_id) AS following,
               ((SELECT COUNT(*) FROM student_course_likes cl WHERE cl.course_id = c.id) + FLOOR((200 + MOD(c.id * 137, 601)) * (18 + MOD(c.id * 19, 23)) / 100)) AS course_like_count,
               (SELECT COUNT(*) FROM student_course_likes cl WHERE cl.student_id = {$viewerId} AND cl.course_id = c.id) AS course_liked
        FROM instructor_courses c
        LEFT JOIN course_categories cat ON cat.id = c.category_id
        LEFT JOIN course_categories sub ON sub.id = c.subcategory_id
        LEFT JOIN users u ON u.id = c.instructor_id
        $where
        ORDER BY c.featured DESC, c.id DESC
    ";
    $stmt = db()->prepare($sql);
    if ($types !== '') {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    foreach ($rows as &$row) {
        $row = decorate_course_thumbnail($row);
    }
    unset($row);
    return $rows;
}

function my_course_rows(int $studentId): array
{
    $stmt = db()->prepare("
        SELECT c.id, c.title, c.category, c.category_id, c.subcategory_id, c.price, c.original_price, c.thumbnail_path, c.price_unit, c.learning_mode, c.course_level,
               c.course_language, c.duration, c.city, c.locality, c.featured, c.is_free,
               c.short_description, cat.name AS category_name, sub.name AS subcategory_name,
               u.full_name AS instructor_name, e.progress_percent, e.status AS enrollment_status,
               e.enrolled_at, p.payment_status, p.transaction_no,
               (SELECT COUNT(*) FROM instructor_course_contents cc WHERE cc.course_id = c.id AND cc.status = 'published') AS chapter_count,
               ((SELECT COUNT(*) FROM student_course_enrollments e2 WHERE e2.course_id = c.id AND e2.status IN ('active','completed')) + 200 + MOD(c.id * 137, 601)) AS enrolled_count,
               (SELECT COUNT(*) FROM student_instructor_follows sf WHERE sf.instructor_id = c.instructor_id) AS follower_count,
               (SELECT COUNT(*) FROM student_instructor_follows sf WHERE sf.student_id = ? AND sf.instructor_id = c.instructor_id) AS following,
               ((SELECT COUNT(*) FROM student_course_likes cl WHERE cl.course_id = c.id) + FLOOR((200 + MOD(c.id * 137, 601)) * (18 + MOD(c.id * 19, 23)) / 100)) AS course_like_count,
               (SELECT COUNT(*) FROM student_course_likes cl WHERE cl.student_id = ? AND cl.course_id = c.id) AS course_liked
        FROM student_course_enrollments e
        INNER JOIN instructor_courses c ON c.id = e.course_id
        LEFT JOIN student_course_purchases p ON p.id = e.purchase_id
        LEFT JOIN course_categories cat ON cat.id = c.category_id
        LEFT JOIN course_categories sub ON sub.id = c.subcategory_id
        LEFT JOIN users u ON u.id = c.instructor_id
        WHERE e.student_id = ?
        ORDER BY e.enrolled_at DESC
    ");
    $stmt->bind_param('iii', $studentId, $studentId, $studentId);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    foreach ($rows as &$row) {
        $row = decorate_course_thumbnail($row);
    }
    unset($row);
    return $rows;
}

function course_resource_rows(int $courseId, ?int $studentId = null, bool $courseAccess = false): array
{
    $stmt = db()->prepare("
        SELECT id, course_id, resource_title, resource_type, document_purpose, thumbnail_path, price, sort_order
        FROM instructor_course_resources
        WHERE course_id = ? AND status = 'published' AND COALESCE(document_purpose, 'course') = 'course'
        ORDER BY sort_order ASC, id ASC
    ");
    $stmt->bind_param('i', $courseId);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    foreach ($rows as &$row) {
        $resourceId = (int) $row['id'];
        $allowed = $studentId !== null && ($courseAccess || student_can_access_resource($studentId, $row));
        $row['locked'] = !$allowed;
        $row['thumbnail_url'] = course_resource_thumbnail_url($row);
        $row['file_url'] = $allowed ? api_absolute_url('api/student?action=download_pdf&id=' . $resourceId) : '';
        $row['download_url'] = $row['file_url'];
    }
    return $rows;
}

function resource_detail_row(int $resourceId): ?array
{
    $stmt = db()->prepare("
        SELECT r.*, c.status AS course_status, c.title AS course_title, c.category,
               e.title AS exam_title, ec.category_name AS exam_category_name,
               u.full_name AS instructor_name
        FROM instructor_course_resources r
        INNER JOIN instructor_courses c ON c.id = r.course_id
        LEFT JOIN instructor_exams e ON e.id = r.exam_id
        LEFT JOIN instructor_exam_categories ec ON ec.id = COALESCE(r.exam_category_id, e.exam_category_id)
        INNER JOIN users u ON u.id = r.instructor_id
        WHERE r.id = ? AND r.status = 'published' AND c.status = 'published'
        LIMIT 1
    ");
    $stmt->bind_param('i', $resourceId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    return $row ?: null;
}

function document_payload(array $resource, ?int $studentId = null): array
{
    $resourceId = (int) ($resource['id'] ?? 0);
    $allowed = $studentId !== null && student_can_access_resource($studentId, $resource);
    $subscription = $studentId !== null ? active_plan_subscription($studentId) : null;
    $hasPurchase = $studentId !== null && student_has_resource_purchase($studentId, $resourceId);
    $courseAccess = $studentId !== null && student_can_access_course($studentId, (int) ($resource['course_id'] ?? 0), false);
    $progress = $studentId !== null ? pdf_progress_row($studentId, $resourceId) : null;
    $pageCount = pdf_file_page_count((string) ($resource['file_path'] ?? ''));
    return [
        'id' => $resourceId,
        'course_id' => (int) ($resource['course_id'] ?? 0),
        'resource_title' => $resource['resource_title'] ?? 'Course PDF',
        'resource_type' => $resource['resource_type'] ?? 'pdf',
        'document_purpose' => $resource['document_purpose'] ?? 'course',
        'price' => (float) ($resource['price'] ?? 0),
        'course_title' => $resource['course_title'] ?? 'Course',
        'exam_title' => $resource['exam_title'] ?? '',
        'exam_category_name' => $resource['exam_category_name'] ?? '',
        'category' => $resource['category'] ?? '',
        'instructor_name' => $resource['instructor_name'] ?? 'Instructor',
        'locked' => !$allowed,
        'access' => [
            'allowed' => $allowed,
            'has_purchase' => $hasPurchase,
            'course_enrolled' => $courseAccess,
            'active_plan' => $subscription ? [
                'plan_name' => $subscription['plan_name'] ?? '',
                'ends_at' => $subscription['ends_at'] ?? '',
                'pdf_limit' => $subscription['pdf_limit'] ?? null,
            ] : null,
        ],
        'progress' => $progress,
        'completion' => ($studentId !== null && $allowed) ? pdf_completion_gate($studentId, $resource) : null,
        'page_count' => $pageCount,
        'thumbnail_url' => course_resource_thumbnail_url($resource),
        'file_url' => $allowed ? api_absolute_url('api/student?action=download_pdf&id=' . $resourceId) : '',
        'download_url' => $allowed ? api_absolute_url('api/student?action=download_pdf&id=' . $resourceId) : '',
    ];
}

function home_document_payload(array $resource, ?int $studentId = null): array
{
    $resourceId = (int) ($resource['id'] ?? 0);
    $allowed = $studentId !== null && student_can_access_resource($studentId, $resource);
    return [
        'id' => $resourceId,
        'course_id' => (int) ($resource['course_id'] ?? 0),
        'resource_title' => $resource['resource_title'] ?? 'Course PDF',
        'resource_type' => $resource['resource_type'] ?? 'pdf',
        'document_purpose' => $resource['document_purpose'] ?? 'course',
        'price' => (float) ($resource['price'] ?? 0),
        'course_title' => $resource['course_title'] ?? 'Course',
        'exam_title' => $resource['exam_title'] ?? '',
        'exam_category_name' => $resource['exam_category_name'] ?? '',
        'category' => $resource['category'] ?? '',
        'instructor_name' => $resource['instructor_name'] ?? 'Instructor',
        'locked' => !$allowed,
        'thumbnail_url' => course_resource_thumbnail_url($resource),
        'file_url' => $allowed ? api_absolute_url('api/student?action=download_pdf&id=' . $resourceId) : '',
        'download_url' => $allowed ? api_absolute_url('api/student?action=download_pdf&id=' . $resourceId) : '',
    ];
}

function home_document_rows(int $limit = 8, ?int $studentId = null): array
{
    $limit = max(1, min(20, $limit));
    $stmt = db()->prepare("
        SELECT r.id, r.resource_title, r.resource_type, r.document_purpose, r.price, r.sort_order,
               r.thumbnail_path,
               c.id AS course_id, c.title AS course_title, c.category,
               e.title AS exam_title, ec.category_name AS exam_category_name,
               u.full_name AS instructor_name
        FROM instructor_course_resources r
        INNER JOIN instructor_courses c ON c.id = r.course_id
        LEFT JOIN instructor_exams e ON e.id = r.exam_id
        LEFT JOIN instructor_exam_categories ec ON ec.id = COALESCE(r.exam_category_id, e.exam_category_id)
        INNER JOIN users u ON u.id = r.instructor_id
        WHERE r.status = 'published' AND c.status = 'published'
        ORDER BY r.created_at DESC, r.id DESC
        LIMIT ?
    ");
    $stmt->bind_param('i', $limit);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    foreach ($rows as &$row) {
        $row = home_document_payload($row, $studentId);
    }
    return $rows;
}

function instructor_rows(): array
{
    $stmt = db()->prepare("
        SELECT u.id, u.full_name, u.username, u.email, u.phone,
               r.name AS role_name,
               (SELECT COUNT(*) FROM instructor_courses c WHERE c.instructor_id = u.id AND c.status = 'published') AS courses_count,
               (SELECT COUNT(*) FROM student_instructor_follows sf WHERE sf.instructor_id = u.id) AS follower_count
        FROM users u
        INNER JOIN roles r ON r.id = u.role_id
        WHERE r.slug = 'instructor' AND u.status = 'active'
        ORDER BY u.full_name ASC
    ");
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

function combo_rows(?int $limit = 8): array
{
    $limit = max(1, min(30, (int) ($limit ?? 8)));
    $stmt = db()->prepare("
        SELECT cb.id, cb.combo_name, cb.price, cb.status, cb.description, cb.updated_at,
               c.id AS course_id, c.title AS course_title, c.thumbnail_path AS course_thumbnail,
               r.id AS document_id, r.resource_title AS document_title, r.thumbnail_path AS document_thumbnail,
               e.id AS exam_id, e.title AS exam_title,
               l.id AS live_channel_id, l.channel_name AS live_title, l.status AS live_status,
               u.full_name AS instructor_name
        FROM instructor_content_combos cb
        LEFT JOIN instructor_courses c ON c.id = cb.course_id AND c.instructor_id = cb.instructor_id AND c.status = 'published'
        LEFT JOIN instructor_course_resources r ON r.id = cb.document_id AND r.instructor_id = cb.instructor_id AND r.status = 'published'
        LEFT JOIN instructor_exams e ON e.id = cb.exam_id AND e.instructor_id = cb.instructor_id AND e.status = 'published'
        LEFT JOIN live_stream_channels l ON l.id = cb.live_channel_id AND l.instructor_id = cb.instructor_id AND l.status <> 'disabled'
        LEFT JOIN users u ON u.id = cb.instructor_id
        WHERE cb.status = 'published'
        ORDER BY cb.updated_at DESC, cb.id DESC
        LIMIT ?
    ");
    $stmt->bind_param('i', $limit);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    foreach ($rows as &$row) {
        $row['thumbnail_url'] = api_absolute_url((string) ($row['course_thumbnail'] ?: $row['document_thumbnail'] ?: ''));
        $row['item_count'] = (int) (($row['course_id'] ? 1 : 0) + ($row['document_id'] ? 1 : 0) + ($row['exam_id'] ? 1 : 0) + ($row['live_channel_id'] ? 1 : 0));
        $row['price'] = (float) ($row['price'] ?? 0);
        $row['detail_href'] = $row['course_id'] ? '#/course/' . (int) $row['course_id'] : '#/courses';
    }
    unset($row);
    return $rows;
}

try {
    $action = (string) ($_GET['action'] ?? ($_POST['action'] ?? 'home'));
    $earlyHomeCache = __DIR__ . '/../uploads/cache/home-public.json';
    if ($action === 'home' && bearer_token() === '' && is_file($earlyHomeCache) && (time() - filemtime($earlyHomeCache) <= 30)) {
        header('X-Edu-Cache: hit');
        echo (string) file_get_contents($earlyHomeCache);
        exit;
    }

    ensure_student_api_tables();

    if ($action === 'payment_return') {
        $txn = trim((string) ($_GET['txn'] ?? ''));
        $deepLink = student_app_payment_deep_link($txn);
        $statusUrl = api_absolute_url('api/student.php?action=payment_status&txn=' . rawurlencode($txn));
        header('Content-Type: text/html; charset=utf-8');
        echo '<!doctype html><html lang="en"><head><meta charset="utf-8">';
        echo '<meta name="viewport" content="width=device-width, initial-scale=1">';
        echo '<title>Returning to GYAN NEXA</title>';
        echo '<style>body{margin:0;font-family:Arial,sans-serif;background:#eef3f8;color:#002b55;display:grid;place-items:center;min-height:100vh}.box{width:min(420px,90vw);background:#fff;border:1px solid #cfe0ef;border-top:3px solid #ff8a00;border-radius:6px;box-shadow:0 8px 24px rgba(0,43,85,.12);padding:24px}h1{font-size:22px;margin:0 0 10px}p{line-height:1.5;color:#4a5d72}a{display:inline-block;margin-top:10px;background:#003b6f;color:#fff;text-decoration:none;padding:10px 16px;border-radius:4px;font-weight:700}</style>';
        echo '</head><body><div class="box"><h1>Returning to GYAN NEXA</h1>';
        echo '<p>Your payment has been received by the gateway. The app will verify the final payment status automatically.</p>';
        echo '<a href="' . h($deepLink) . '">Open GYAN NEXA App</a>';
        echo '<p><a style="background:transparent;color:#003b6f;padding:0" href="' . h($statusUrl) . '">Check payment status</a></p>';
        echo '</div><script>setTimeout(function(){window.location.href=' . json_encode($deepLink) . ';},500);</script></body></html>';
        exit;
    }

    if ($action === 'ping') {
        api_out([
            'success' => true,
            'message' => 'Student API is online.',
            'base' => app_url('api/student'),
            'settings' => student_app_settings(),
            'time' => date('c'),
        ]);
    }

    if ($action === 'phonepe_callback' || $action === 'payment_status') {
        $data = api_input();
        $txn = trim((string) ($_GET['txn'] ?? $data['transaction_no'] ?? $data['merchantTransactionId'] ?? ''));
        if ($txn === '' && isset($data['response'])) {
            $decoded = json_decode(base64_decode((string) $data['response']), true);
            if (is_array($decoded)) {
                $txn = trim((string) ($decoded['data']['merchantTransactionId'] ?? $decoded['merchantTransactionId'] ?? ''));
            }
        }
        if ($txn === '') {
            api_out(['success' => false, 'message' => 'Transaction missing.'], 422);
        }
        $status = phonepe_status($txn);
        $ok = phonepe_payment_is_success($status);
        $failed = !$ok && phonepe_payment_is_failed($status);
        $local = complete_pending_payment_by_txn($txn, $failed, $ok);
        api_out([
            'success' => true,
            'paid' => $ok,
            'pending' => !$ok && !$failed,
            'failed' => $failed,
            'transaction_no' => $txn,
            'status_tokens' => phonepe_status_tokens($status),
            'payment' => $status,
            'local' => $local,
        ]);
    }

    if ($action === 'retry_payment') {
        $user = api_user();
        $data = api_input();
        $txn = trim((string) ($data['transaction_no'] ?? $data['txn'] ?? $_GET['txn'] ?? ''));
        $appReturn = strtolower(trim((string) ($data['client'] ?? $_GET['client'] ?? ''))) === 'app';
        $retry = retry_payment_by_txn($txn, (int) $user['id'], $appReturn);
        api_out([
            'success' => true,
            'message' => ($retry['payment_required'] ?? false) ? 'Retry payment started.' : 'Access activated.',
            'payment_required' => (bool) ($retry['payment_required'] ?? false),
            'transaction_no' => $retry['transaction_no'] ?? $txn,
            'amount' => $retry['amount'] ?? 0,
            'payment' => $retry['payment'] ?? null,
            'purchase' => $retry['purchase'] ?? null,
        ]);
    }

    if ($action === 'billing_profile') {
        $user = api_user();
        api_out([
            'success' => true,
            'billing_profile' => student_billing_profile((int) $user['id']),
            'complete' => student_billing_profile_complete(student_billing_profile((int) $user['id'])),
            'states' => student_gst_states(),
            'seller_state_code' => normalize_gst_state_code(app_setting('billing_state_code', '09')),
            'seller_state_name' => app_setting('billing_state_name', gst_state_name(app_setting('billing_state_code', '09'))),
        ]);
    }

    if ($action === 'save_billing_profile') {
        $user = api_user();
        $profile = save_student_billing_profile((int) $user['id'], api_input());
        api_out([
            'success' => true,
            'message' => 'Billing details saved.',
            'billing_profile' => $profile,
            'complete' => student_billing_profile_complete($profile),
            'states' => student_gst_states(),
        ]);
    }

    if ($action === 'instructors') {
        api_out([
            'success' => true,
            'instructors' => instructor_rows(),
        ]);
    }

    if ($action === 'signup') {
        $data = api_input();
        $name = substr(trim((string) ($data['name'] ?? '')), 0, 120);
        $email = substr(trim((string) ($data['email'] ?? '')), 0, 160);
        $phone = preg_replace('/\D+/', '', (string) ($data['phone'] ?? ''));
        $password = (string) ($data['password'] ?? '');
        $referralCode = substr(trim((string) ($data['referral_code'] ?? $data['ref'] ?? '')), 0, 80);

        if ($name === '') {
            api_out(['success' => false, 'message' => 'Full name is required.'], 422);
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            api_out(['success' => false, 'message' => 'Enter a valid email address.'], 422);
        }
        if (!preg_match('/^\d{10}$/', $phone)) {
            api_out(['success' => false, 'message' => 'Enter a valid 10 digit mobile number.'], 422);
        }
        if (strlen($password) < 8) {
            api_out(['success' => false, 'message' => 'Password must be at least 8 characters.'], 422);
        }

        $studentRole = api_role_id('student');
        if ($studentRole <= 0) {
            api_out(['success' => false, 'message' => 'Student role is not configured.'], 500);
        }

        $stmt = db()->prepare('SELECT id FROM users WHERE email = ? OR phone = ? LIMIT 1');
        $stmt->bind_param('ss', $email, $phone);
        $stmt->execute();
        if ($stmt->get_result()->fetch_assoc()) {
            api_out(['success' => false, 'message' => 'Email or phone already registered.'], 409);
        }

        $baseUsername = strtolower(preg_replace('/[^a-z0-9]+/i', '.', strstr($email, '@', true) ?: $name));
        $baseUsername = trim($baseUsername, '.') ?: 'student';
        $username = substr($baseUsername, 0, 60);
        for ($i = 0; $i < 50; $i++) {
            $try = $i === 0 ? $username : substr($username, 0, 55) . $i;
            $stmt = db()->prepare('SELECT id FROM users WHERE username = ? LIMIT 1');
            $stmt->bind_param('s', $try);
            $stmt->execute();
            if (!$stmt->get_result()->fetch_assoc()) {
                $username = $try;
                break;
            }
        }

        $hash = password_hash($password, PASSWORD_DEFAULT);
        $status = 'active';
        $stmt = db()->prepare('INSERT INTO users (role_id, full_name, username, email, phone, password_hash, status) VALUES (?, ?, ?, ?, ?, ?, ?)');
        $stmt->bind_param('issssss', $studentRole, $name, $username, $email, $phone, $hash, $status);
        $stmt->execute();
        $userId = (int) db()->insert_id;
        ensure_default_free_plan($userId);

        if ($referralCode !== '' && gcoin_enabled()) {
            $referrer = gcoin_find_referrer($referralCode, $userId);
            if ($referrer) {
                $referrerId = (int) $referrer['id'];
                $referrerCoins = gcoin_setting_float('gcoin_signup_referrer_reward', 100);
                $joinerCoins = gcoin_setting_float('gcoin_signup_joiner_reward', 50);
                $stmt = db()->prepare('INSERT IGNORE INTO student_referrals (referrer_id, referred_student_id, referral_code, signup_referrer_coins, signup_joiner_coins) VALUES (?, ?, ?, ?, ?)');
                $codeValue = strtoupper($referralCode);
                $stmt->bind_param('iisdd', $referrerId, $userId, $codeValue, $referrerCoins, $joinerCoins);
                $stmt->execute();
                if ($stmt->affected_rows > 0) {
                    gcoin_add_transaction($referrerId, 'credit', $referrerCoins, 'referral_signup', $userId, 'Referral signup reward');
                    gcoin_add_transaction($userId, 'credit', $joinerCoins, 'join_referral', $referrerId, 'Welcome referral reward');
                }
            }
        }

        email_notify_user(
            'welcome',
            ['id' => $userId, 'full_name' => $name, 'username' => $username, 'email' => $email, 'phone' => $phone],
            ['referral_code' => student_referral_code($userId)]
        );
        email_notify_admins('new_signup', [
            'student_id' => (string) $userId,
            'student_name' => $name,
            'student_email' => $email,
            'student_phone' => $phone,
            'referral_code' => student_referral_code($userId),
        ]);

        api_out([
            'success' => true,
            'message' => 'Signup successful.',
            'token' => issue_token($userId),
            'user' => ['id' => $userId, 'name' => $name, 'username' => $username, 'email' => $email, 'phone' => $phone, 'profile_photo' => '', 'referral_code' => student_referral_code($userId)],
            'settings' => student_app_settings(),
        ]);
    }

    if ($action === 'login') {
        $data = api_input();
        $identity = substr(trim((string) ($data['identity'] ?? $data['email'] ?? $data['phone'] ?? $data['username'] ?? $_POST['identity'] ?? '')), 0, 160);
        $identityDigits = preg_replace('/\D+/', '', $identity);
        if (strlen($identityDigits) > 10 && substr($identityDigits, -10) !== false) {
            $identityDigits = substr($identityDigits, -10);
        }
        $password = (string) ($data['password'] ?? '');

        $stmt = db()->prepare("
            SELECT u.id, u.full_name, u.username, u.email, u.phone, u.profile_photo, u.password_hash, u.status, r.slug AS role
            FROM users u
            INNER JOIN roles r ON r.id = u.role_id
            WHERE (
                LOWER(u.email) = LOWER(?)
                OR u.username = ?
                OR u.phone = ?
                OR RIGHT(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(u.phone, ' ', ''), '-', ''), '+', ''), '(', ''), ')', ''), 10) = ?
            )
            LIMIT 1
        ");
        $stmt->bind_param('ssss', $identity, $identity, $identity, $identityDigits);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();
        if (!$user) {
            api_out(['success' => false, 'message' => 'No account found.'], 401);
        }
        if (!password_verify($password, (string) $user['password_hash'])) {
            api_out(['success' => false, 'message' => 'Invalid password.'], 401);
        }
        if ($user['status'] !== 'active') {
            api_out(['success' => false, 'message' => 'Your account is not active.'], 403);
        }

        $role = (string) ($user['role'] ?? 'student');
        if ($role !== 'student') {
            start_secure_session();
            session_regenerate_id(true);
            $_SESSION['user'] = [
                'id' => (int) $user['id'],
                'full_name' => $user['full_name'],
                'username' => $user['username'],
                'role' => $role,
            ];
            api_out([
                'success' => true,
                'message' => 'Login successful.',
                'role' => $role,
                'redirect_url' => app_url(dashboard_path_for_role($role)),
            ]);
        }

        ensure_default_free_plan((int) $user['id']);

        api_out([
            'success' => true,
            'message' => 'Login successful.',
            'token' => issue_token((int) $user['id']),
            'user' => ['id' => (int) $user['id'], 'name' => $user['full_name'], 'username' => $user['username'], 'email' => $user['email'], 'phone' => $user['phone'], 'profile_photo' => api_absolute_url((string) ($user['profile_photo'] ?? ''))],
            'settings' => student_app_settings(),
        ]);
    }
    if ($action === 'membership_plans') {
        api_optional_user();
        api_out([
            'success' => true,
            'settings' => student_app_settings(),
            'plans' => membership_plans(),
        ]);
    }
    if ($action === 'dashboard_home') {
        $user = api_user();
        $studentId = (int) $user['id'];
        ensure_default_free_plan($studentId);
        $courses = course_rows(null, $studentId);
        $courses = array_values(array_filter($courses, fn($course) => (int) ($course['chapter_count'] ?? 0) > 0));
        api_out([
            'success' => true,
            'settings' => student_app_settings(),
            'plans' => membership_plans(),
            'courses' => array_slice($courses, 0, 12),
            'live_classes' => array_slice(live_class_rows(true, $studentId), 0, 8),
            'my_courses' => my_course_rows($studentId),
            'active_tests' => active_exam_sessions($studentId),
        ]);
    }
    if ($action === 'home') {
        $user = api_optional_user();
        $studentId = $user ? (int) $user['id'] : null;
        if ($studentId !== null) {
            ensure_default_free_plan($studentId);
        }
        $cacheFile = __DIR__ . '/../uploads/cache/home-public.json';
        if ($studentId === null && is_file($cacheFile) && (time() - filemtime($cacheFile) <= 30)) {
            echo (string) file_get_contents($cacheFile);
            exit;
        }
        $courses = course_rows(null, $studentId);
        $courses = array_values(array_filter($courses, fn($course) => (int) ($course['chapter_count'] ?? 0) > 0));
        $payload = [
            'success' => true,
            'settings' => student_app_settings(),
            'plans' => membership_plans(),
            'courses' => $courses,
            'batches' => batch_rows(),
            'live_classes' => live_class_rows($studentId !== null, $studentId),
            'live_recordings' => $studentId ? student_live_recording_rows($studentId) : [],
            'exams' => exam_rows($studentId),
            'documents' => home_document_rows(10, $studentId),
            'combos' => combo_rows(8),
            'my_courses' => $studentId ? my_course_rows($studentId) : [],
            'active_tests' => $studentId ? active_exam_sessions($studentId) : [],
            'gcoin_wallet' => $studentId ? gcoin_wallet_row($studentId) : null,
            'referral_code' => $studentId ? student_referral_code($studentId) : '',
        ];
        if ($studentId === null) {
            $cacheDir = dirname($cacheFile);
            if (is_dir($cacheDir) || @mkdir($cacheDir, 0775, true)) {
                @file_put_contents($cacheFile, api_json($payload));
            }
        }
        api_out($payload);
    }

    if ($action === 'document') {
        $user = api_optional_user();
        $studentId = $user ? (int) $user['id'] : null;
        $resourceId = max(0, (int) ($_GET['id'] ?? 0));
        $resource = resource_detail_row($resourceId);
        if (!$resource) {
            api_out(['success' => false, 'message' => 'PDF not found.'], 404);
        }
        api_out([
            'success' => true,
            'document' => document_payload($resource, $studentId),
            'plans' => membership_plans(),
        ]);
    }

    if ($action === 'download_pdf') {
        $user = api_user();
        $resourceId = max(0, (int) ($_GET['id'] ?? 0));
        $resource = resource_detail_row($resourceId);
        if (!$resource) {
            api_out(['success' => false, 'message' => 'PDF not found.'], 404);
        }
        $studentId = (int) $user['id'];
        $courseId = (int) $resource['course_id'];
        $allowed = student_can_access_resource($studentId, $resource);
        if (!$allowed) {
            api_out(['success' => false, 'message' => 'Please enroll, buy this resource or activate a plan to access this PDF.'], 403);
        }
        register_pdf_access($studentId, $resourceId, $courseId);

        $path = realpath(__DIR__ . '/../' . (string) $resource['file_path']);
        $base = realpath(__DIR__ . '/../uploads/course-content');
        if (!$path || !$base || strpos($path, $base) !== 0 || !is_file($path)) {
            api_out(['success' => false, 'message' => 'PDF file missing.'], 404);
        }

        if (function_exists('header_remove')) {
            header_remove('Content-Type');
            header_remove('X-Frame-Options');
            header_remove('Content-Security-Policy');
        }
        header('Content-Type: application/pdf');
        header('Content-Disposition: inline; filename="' . basename($path) . '"');
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        header('Pragma: no-cache');
        header('Expires: 0');
        header('X-Download-Options: noopen');
        header('Accept-Ranges: bytes');
        header('Content-Length: ' . filesize($path));
        readfile($path);
        exit;
    }

    if ($action === 'purchase_resource') {
        $user = api_user();
        $data = api_input();
        $resourceId = max(0, (int) ($data['resource_id'] ?? $data['id'] ?? $_GET['id'] ?? 0));
        $appReturn = strtolower(trim((string) ($data['client'] ?? $_GET['client'] ?? ''))) === 'app';
        $resource = resource_detail_row($resourceId);
        if (!$resource) {
            api_out(['success' => false, 'message' => 'PDF not found.'], 404);
        }

        $studentId = (int) $user['id'];
        $courseOrPlanAccess = student_can_access_course($studentId, (int) $resource['course_id'], false) || student_plan_pdf_allowed($studentId, $resourceId);
        if ($courseOrPlanAccess) {
            register_pdf_access($studentId, $resourceId, (int) $resource['course_id']);
            $document = document_payload($resource, $studentId);
            api_out(['success' => true, 'message' => 'PDF access active.', 'document' => $document]);
        }

        if (student_has_resource_purchase($studentId, $resourceId)) {
            register_pdf_access($studentId, $resourceId, (int) $resource['course_id']);
            api_out([
                'success' => true,
                'message' => 'PDF access active.',
                'document' => document_payload($resource, $studentId),
            ]);
        }

        $price = (float) ($resource['price'] ?? 0);
        if ($price > 0) {
            api_out([
                'success' => false,
                'membership_required' => true,
                'message' => 'Activate GYAN NEXA All Access to unlock every PDF, course, exam and live class.',
                'plans' => membership_plans(),
            ], 402);
        }
        if ($price > 0 && !phonepe_enabled()) {
            api_out(['success' => false, 'message' => 'PhonePe payment gateway is not configured. Please contact admin.'], 422);
        }
        if ($price > 0) {
            require_student_billing_info($studentId);
            $txn = 'PDF-' . date('ymdHis') . '-' . random_int(1000, 9999);
            $stmt = db()->prepare('
                INSERT INTO student_resource_purchases (student_id, resource_id, course_id, original_amount, amount, payment_method, payment_status, transaction_no)
                VALUES (?, ?, ?, ?, ?, "phonepe", "pending", ?)
                ON DUPLICATE KEY UPDATE original_amount = VALUES(original_amount), amount = VALUES(amount), payment_method = "phonepe", payment_status = "pending", transaction_no = VALUES(transaction_no)
            ');
            $courseId = (int) $resource['course_id'];
            $stmt->bind_param('iiidds', $studentId, $resourceId, $courseId, $price, $price, $txn);
            $stmt->execute();
            $stmt = db()->prepare('SELECT id FROM student_resource_purchases WHERE student_id = ? AND resource_id = ? LIMIT 1');
            $stmt->bind_param('ii', $studentId, $resourceId);
            $stmt->execute();
            $purchaseId = (int) ($stmt->get_result()->fetch_assoc()['id'] ?? 0);
            try {
                $payment = phonepe_create_payment($txn, $studentId, $price, 'pdf', $appReturn);
            } catch (Throwable $e) {
                mark_payment_start_failed('student_resource_purchases', $purchaseId);
                email_notify_purchase_event('payment_failed', 'resource', $purchaseId);
                api_out(['success' => false, 'message' => 'PhonePe payment start failed: ' . $e->getMessage()], 422);
            }
            api_out([
                'success' => true,
                'payment_required' => true,
                'message' => 'Redirecting to PhonePe payment.',
                'transaction_no' => $txn,
                'amount' => $price,
                'payment' => $payment,
            ]);
        }

        ensure_resource_purchase($studentId, $resource);
        $document = document_payload($resource, $studentId);
        api_out([
            'success' => true,
            'message' => ((float) ($resource['price'] ?? 0)) <= 0 ? 'PDF enrolled successfully.' : 'PDF purchase successful.',
            'document' => $document,
        ]);
    }

    if ($action === 'update_pdf_progress') {
        $user = api_user();
        $data = api_input();
        $studentId = (int) $user['id'];
        $resourceId = max(0, (int) ($data['resource_id'] ?? $data['id'] ?? 0));
        $resource = resource_detail_row($resourceId);
        if (!$resource) {
            api_out(['success' => false, 'message' => 'PDF not found.'], 404);
        }
        if (!student_can_access_resource($studentId, $resource)) {
            api_out(['success' => false, 'message' => 'Please enroll, buy this resource or activate a plan to access this PDF.'], 403);
        }
        $courseId = (int) $resource['course_id'];
        if (isset($data['progress_percent']) || isset($data['scroll_percent'])) {
            $percent = max(0, min(100, round((float) ($data['progress_percent'] ?? $data['scroll_percent'] ?? 0), 2)));
            $totalPages = 100;
            $currentPage = (int) round($percent);
        } else {
            $totalPages = max(1, min(5000, (int) ($data['total_pages'] ?? 1)));
            $currentPage = max(0, min($totalPages, (int) ($data['current_page'] ?? 0)));
            $percent = round(($currentPage / $totalPages) * 100, 2);
        }
        $completed = $percent >= 95 ? date('Y-m-d H:i:s') : null;
        $stmt = db()->prepare('
            INSERT INTO student_resource_access (student_id, resource_id, course_id, first_accessed_at, last_accessed_at, access_count, current_page, total_pages, progress_percent, completed_at)
            VALUES (?, ?, ?, NOW(), NOW(), 1, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE last_accessed_at = NOW(), current_page = VALUES(current_page), total_pages = VALUES(total_pages), progress_percent = VALUES(progress_percent), completed_at = IF(VALUES(completed_at) IS NULL, completed_at, VALUES(completed_at))
        ');
        $stmt->bind_param('iiiiids', $studentId, $resourceId, $courseId, $currentPage, $totalPages, $percent, $completed);
        $stmt->execute();
        api_out([
            'success' => true,
            'message' => 'PDF progress saved.',
            'progress' => pdf_progress_row($studentId, $resourceId),
            'document' => document_payload($resource, $studentId),
        ]);
    }

    if ($action === 'mark_pdf_complete') {
        $user = api_user();
        $data = api_input();
        $studentId = (int) $user['id'];
        $resourceId = max(0, (int) ($data['resource_id'] ?? $data['id'] ?? 0));
        $resource = resource_detail_row($resourceId);
        if (!$resource) {
            api_out(['success' => false, 'message' => 'PDF not found.'], 404);
        }
        if (!student_can_access_resource($studentId, $resource)) {
            api_out(['success' => false, 'message' => 'Please enroll, buy this resource or activate a plan to access this PDF.'], 403);
        }

        $courseId = (int) $resource['course_id'];
        register_pdf_access($studentId, $resourceId, $courseId);
        $gate = pdf_completion_gate($studentId, $resource);
        if (!($gate['completed'] ?? false) && !($gate['can_mark'] ?? false)) {
            api_out([
                'success' => false,
                'message' => $gate['message'] ?? 'Required reading time is not complete.',
                'completion' => $gate,
                'document' => document_payload($resource, $studentId),
            ], 422);
        }

        $pageCount = pdf_file_page_count((string) ($resource['file_path'] ?? ''));
        $stmt = db()->prepare('
            UPDATE student_resource_access
            SET current_page = ?, total_pages = ?, progress_percent = 100, completed_at = COALESCE(completed_at, NOW()), last_accessed_at = NOW()
            WHERE student_id = ? AND resource_id = ?
        ');
        $stmt->bind_param('iiii', $pageCount, $pageCount, $studentId, $resourceId);
        $stmt->execute();

        api_out([
            'success' => true,
            'message' => 'PDF marked as complete.',
            'document' => document_payload($resource, $studentId),
        ]);
    }

    if ($action === 'gcoin_wallet') {
        $user = api_user();
        $studentId = (int) $user['id'];
        $stmt = db()->prepare('
            SELECT id, direction, coins, balance_after, source_type, source_id, note, created_at
            FROM student_gcoin_transactions
            WHERE student_id = ?
            ORDER BY id DESC
            LIMIT 100
        ');
        $stmt->bind_param('i', $studentId);
        $stmt->execute();
        $transactions = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

        $stmt = db()->prepare('
            SELECT r.*, u.full_name AS referred_name, u.email AS referred_email
            FROM student_referrals r
            INNER JOIN users u ON u.id = r.referred_student_id
            WHERE r.referrer_id = ?
            ORDER BY r.id DESC
            LIMIT 100
        ');
        $stmt->bind_param('i', $studentId);
        $stmt->execute();
        $referrals = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

        api_out([
            'success' => true,
            'coin_name' => app_setting('gcoin_name', 'Gcoin'),
            'referral_code' => student_referral_code($studentId),
            'settings' => [
                'signup_referrer_reward' => app_setting('gcoin_signup_referrer_reward', '100'),
                'signup_joiner_reward' => app_setting('gcoin_signup_joiner_reward', '50'),
                'purchase_referrer_reward_type' => app_setting('gcoin_purchase_referrer_reward_type', 'percent'),
                'purchase_referrer_reward_value' => app_setting('gcoin_purchase_referrer_reward_value', '5'),
                'gcoin_per_inr' => app_setting('gcoin_per_inr', '10'),
                'min_redeem' => app_setting('gcoin_min_redeem', '10'),
            ],
            'wallet' => gcoin_wallet_row($studentId),
            'transactions' => $transactions,
            'referrals' => $referrals,
        ]);
    }

    if ($action === 'my_courses') {
        $user = api_user();
        $studentId = (int) $user['id'];
        api_out(['success' => true, 'courses' => my_course_rows($studentId), 'pdfs' => my_pdf_rows($studentId)]);
    }

    if ($action === 'progress_report') {
        $user = api_user();
        $courses = my_course_rows((int) $user['id']);
        $reports = [];
        foreach ($courses as $course) {
            $progress = student_course_progress((int) $user['id'], (int) $course['id']);
            $reports[] = [
                'course_id' => (int) $course['id'],
                'title' => $course['title'],
                'category_name' => $course['category_name'],
                'progress_percent' => $progress['course_percent'],
                'watched_seconds' => $progress['watched_seconds'],
                'remaining_seconds' => max(0, (int) $progress['duration_seconds'] - (int) $progress['watched_seconds']),
                'duration_seconds' => $progress['duration_seconds'],
                'achievement' => $progress['achievement'],
            ];
        }
        api_out(['success' => true, 'reports' => $reports]);
    }

    if ($action === 'student_report') {
        $user = api_user();
        $studentId = (int) $user['id'];
        $courses = my_course_rows($studentId);
        $reports = [];
        $totalProgress = 0.0;
        $completed = 0;
        $watchedSeconds = 0;
        $durationSeconds = 0;
        foreach ($courses as $course) {
            $progress = student_course_progress($studentId, (int) $course['id']);
            $percent = (float) ($progress['course_percent'] ?? 0);
            $totalProgress += $percent;
            if ($percent >= 95) {
                $completed++;
            }
            $watchedSeconds += (int) ($progress['watched_seconds'] ?? 0);
            $durationSeconds += (int) ($progress['duration_seconds'] ?? 0);
            $reports[] = [
                'course_id' => (int) $course['id'],
                'title' => $course['title'],
                'category_name' => $course['category_name'] ?: $course['category'],
                'progress_percent' => $percent,
                'watched_seconds' => (int) ($progress['watched_seconds'] ?? 0),
                'duration_seconds' => (int) ($progress['duration_seconds'] ?? 0),
                'achievement' => $progress['achievement'] ?? null,
            ];
        }
        $avgProgress = count($courses) > 0 ? round($totalProgress / count($courses), 2) : 0;

        $subscription = active_plan_subscription($studentId);

        $stmt = db()->prepare('
            SELECT p.id, p.course_id, c.title AS title, p.original_amount, p.amount, p.gcoin_used, p.gcoin_discount,
                   p.payment_method, p.payment_status, p.transaction_no, p.created_at AS purchased_at, "course" AS type,
                   inv.id AS invoice_id, inv.invoice_no, inv.invoice_status, inv.issued_at AS invoice_date,
                   inv.subtotal AS invoice_taxable_amount, inv.tax_amount AS invoice_tax_amount, inv.total_amount AS invoice_total_amount,
                   inv.gst_type, inv.cgst_rate, inv.cgst_amount, inv.sgst_rate, inv.sgst_amount, inv.igst_rate, inv.igst_amount,
                   inv.seller_gstin, inv.seller_state_code, inv.seller_state_name, inv.buyer_gstin,
                   inv.buyer_state_code, inv.buyer_state_name, inv.place_of_supply_code, inv.place_of_supply_name
            FROM student_course_purchases p
            LEFT JOIN instructor_courses c ON c.id = p.course_id
            LEFT JOIN student_invoices inv ON inv.source_type = "course" AND inv.source_id = p.id AND inv.total_amount > 0
            WHERE p.student_id = ?
            ORDER BY p.id DESC
            LIMIT 100
        ');
        $stmt->bind_param('i', $studentId);
        $stmt->execute();
        $coursePurchases = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

        $stmt = db()->prepare('
            SELECT p.id, p.resource_id, r.resource_title AS title, p.original_amount, p.amount,
                   p.payment_method, p.payment_status, p.transaction_no, p.created_at AS purchased_at, "pdf" AS type,
                   inv.id AS invoice_id, inv.invoice_no, inv.invoice_status, inv.issued_at AS invoice_date,
                   inv.subtotal AS invoice_taxable_amount, inv.tax_amount AS invoice_tax_amount, inv.total_amount AS invoice_total_amount,
                   inv.gst_type, inv.cgst_rate, inv.cgst_amount, inv.sgst_rate, inv.sgst_amount, inv.igst_rate, inv.igst_amount,
                   inv.seller_gstin, inv.seller_state_code, inv.seller_state_name, inv.buyer_gstin,
                   inv.buyer_state_code, inv.buyer_state_name, inv.place_of_supply_code, inv.place_of_supply_name
            FROM student_resource_purchases p
            LEFT JOIN instructor_course_resources r ON r.id = p.resource_id
            LEFT JOIN student_invoices inv ON inv.source_type = "resource" AND inv.source_id = p.id AND inv.total_amount > 0
            WHERE p.student_id = ?
            ORDER BY p.id DESC
            LIMIT 100
        ');
        $stmt->bind_param('i', $studentId);
        $stmt->execute();
        $resourcePurchases = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

        $stmt = db()->prepare('
            SELECT p.id, p.plan_id, pl.plan_name AS title, p.original_amount, p.amount,
                   p.payment_method, p.payment_status, p.transaction_no, p.purchased_at, "membership" AS type,
                   inv.id AS invoice_id, inv.invoice_no, inv.invoice_status, inv.issued_at AS invoice_date,
                   inv.subtotal AS invoice_taxable_amount, inv.tax_amount AS invoice_tax_amount, inv.total_amount AS invoice_total_amount,
                   inv.gst_type, inv.cgst_rate, inv.cgst_amount, inv.sgst_rate, inv.sgst_amount, inv.igst_rate, inv.igst_amount,
                   inv.seller_gstin, inv.seller_state_code, inv.seller_state_name, inv.buyer_gstin,
                   inv.buyer_state_code, inv.buyer_state_name, inv.place_of_supply_code, inv.place_of_supply_name
            FROM student_membership_purchases p
            LEFT JOIN student_membership_plans pl ON pl.id = p.plan_id
            LEFT JOIN student_invoices inv ON inv.source_type = "membership" AND inv.source_id = p.id AND inv.total_amount > 0
            WHERE p.student_id = ?
            ORDER BY p.id DESC
            LIMIT 100
        ');
        $stmt->bind_param('i', $studentId);
        $stmt->execute();
        $membershipPurchases = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

        foreach ($coursePurchases as &$purchaseRow) {
            if (($purchaseRow['payment_status'] ?? '') === 'paid' && empty($purchaseRow['invoice_no']) && (float) ($purchaseRow['amount'] ?? 0) > 0) {
                $invoice = ensure_invoice_for_purchase('course', (int) $purchaseRow['id']);
                $purchaseRow['invoice_id'] = $invoice['id'] ?? null;
                $purchaseRow['invoice_no'] = $invoice['invoice_no'] ?? null;
                $purchaseRow['invoice_status'] = $invoice['invoice_status'] ?? null;
                $purchaseRow['invoice_date'] = $invoice['issued_at'] ?? null;
            }
        }
        unset($purchaseRow);
        foreach ($resourcePurchases as &$purchaseRow) {
            if (($purchaseRow['payment_status'] ?? '') === 'paid' && empty($purchaseRow['invoice_no']) && (float) ($purchaseRow['amount'] ?? 0) > 0) {
                $invoice = ensure_invoice_for_purchase('resource', (int) $purchaseRow['id']);
                $purchaseRow['invoice_id'] = $invoice['id'] ?? null;
                $purchaseRow['invoice_no'] = $invoice['invoice_no'] ?? null;
                $purchaseRow['invoice_status'] = $invoice['invoice_status'] ?? null;
                $purchaseRow['invoice_date'] = $invoice['issued_at'] ?? null;
            }
        }
        unset($purchaseRow);
        foreach ($membershipPurchases as &$purchaseRow) {
            if (($purchaseRow['payment_status'] ?? '') === 'paid' && empty($purchaseRow['invoice_no']) && (float) ($purchaseRow['amount'] ?? 0) > 0) {
                $invoice = ensure_invoice_for_purchase('membership', (int) $purchaseRow['id']);
                $purchaseRow['invoice_id'] = $invoice['id'] ?? null;
                $purchaseRow['invoice_no'] = $invoice['invoice_no'] ?? null;
                $purchaseRow['invoice_status'] = $invoice['invoice_status'] ?? null;
                $purchaseRow['invoice_date'] = $invoice['issued_at'] ?? null;
            }
        }
        unset($purchaseRow);

        $documents = my_pdf_rows($studentId);

        $stmt = db()->prepare('
            SELECT a.id, a.exam_id, e.title, c.title AS course_title,
                   a.score, a.total_marks, a.total_questions, a.correct_count,
                   a.wrong_count, a.skipped_count, a.percentage, a.submitted_at
            FROM student_exam_attempts a
            INNER JOIN instructor_exams e ON e.id = a.exam_id
            LEFT JOIN instructor_courses c ON c.id = e.course_id
            WHERE a.student_id = ?
            ORDER BY a.id DESC
            LIMIT 20
        ');
        $stmt->bind_param('i', $studentId);
        $stmt->execute();
        $examAttempts = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

        api_out([
            'success' => true,
            'summary' => [
                'enrolled_courses' => count($courses),
                'completed_courses' => $completed,
                'avg_progress' => $avgProgress,
                'watched_seconds' => $watchedSeconds,
                'duration_seconds' => $durationSeconds,
                'active_tests' => count(active_exam_sessions($studentId)),
                'pdf_access' => count($documents),
                'pdf_completed' => count(array_filter($documents, fn($doc) => (float) ($doc['progress_percent'] ?? 0) >= 95)),
            ],
            'subscription' => $subscription,
            'quota' => plan_quota_usage($studentId, $subscription),
            'reports' => $reports,
            'exam_attempts' => $examAttempts,
            'purchases' => array_merge($coursePurchases, $resourcePurchases, $membershipPurchases),
            'documents' => $documents,
            'wallet' => gcoin_wallet_row($studentId),
        ]);
    }

    if ($action === 'courses') {
        $user = api_optional_user();
        $courses = course_rows(null, $user ? (int) $user['id'] : null);
        $courses = array_values(array_filter($courses, fn($course) => (int) ($course['chapter_count'] ?? 0) > 0));
        api_out(['success' => true, 'courses' => $courses]);
    }

    if ($action === 'course') {
        $user = api_optional_user();
        $courseId = (int) ($_GET['id'] ?? 0);
        $studentId = $user ? (int) $user['id'] : 0;
        $courses = course_rows($courseId, $studentId > 0 ? $studentId : null);
        if (!$courses) {
            api_out(['success' => false, 'message' => 'Course not found.'], 404);
        }
        $enrolled = $studentId > 0 ? student_can_access_course($studentId, $courseId, false) : false;
        $stmt = db()->prepare("
            SELECT cc.id, cc.content_title, cc.content_type, cc.resource_url, cc.duration_minutes, cc.is_preview,
                   (SELECT COUNT(*) FROM student_content_likes l WHERE l.content_id = cc.id) AS like_count
            FROM instructor_course_contents cc
            WHERE cc.course_id = ? AND cc.status = 'published'
            ORDER BY
                CASE WHEN cc.content_title REGEXP '^[0-9]+' THEN CAST(SUBSTRING_INDEX(cc.content_title, ' ', 1) AS UNSIGNED) ELSE 999999 END ASC,
                cc.sort_order ASC,
                cc.id ASC
        ");
        $stmt->bind_param('i', $courseId);
        $stmt->execute();
        $chapters = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        foreach ($chapters as &$chapter) {
            $chapter['resource_url'] = content_play_url($chapter);
            $chapter['locked'] = !$enrolled && (string) $chapter['is_preview'] !== '1';
            $chapter['liked'] = false;
            if ($studentId > 0) {
                $likeStmt = db()->prepare('SELECT id FROM student_content_likes WHERE student_id = ? AND content_id = ? LIMIT 1');
                $contentId = (int) $chapter['id'];
                $likeStmt->bind_param('ii', $studentId, $contentId);
                $likeStmt->execute();
                $chapter['liked'] = (bool) $likeStmt->get_result()->fetch_assoc();
            }
            if ($chapter['locked']) {
                $chapter['resource_url'] = '';
            }
        }
        unset($chapter);
        $instructorId = (int) ($courses[0]['instructor_id'] ?? 0);
        $followerCount = 0;
        $following = false;
        if ($instructorId > 0) {
            $stmt = db()->prepare('SELECT COUNT(*) AS total FROM student_instructor_follows WHERE instructor_id = ?');
            $stmt->bind_param('i', $instructorId);
            $stmt->execute();
            $followerCount = (int) ($stmt->get_result()->fetch_assoc()['total'] ?? 0);
            if ($studentId > 0) {
                $stmt = db()->prepare('SELECT id FROM student_instructor_follows WHERE student_id = ? AND instructor_id = ? LIMIT 1');
                $stmt->bind_param('ii', $studentId, $instructorId);
                $stmt->execute();
                $following = (bool) $stmt->get_result()->fetch_assoc();
            }
        }
        api_out([
            'success' => true,
            'course' => $courses[0],
            'chapters' => $chapters,
            'course_pdfs' => course_resource_rows($courseId, $studentId > 0 ? $studentId : null, $enrolled),
            'enrolled' => $enrolled,
            'progress' => $studentId > 0 ? student_course_progress($studentId, $courseId) : null,
            'social' => [
                'follower_count' => $followerCount,
                'following' => $following,
                'is_following' => $following,
                'course_like_count' => (int) ($courses[0]['course_like_count'] ?? 0),
                'course_liked' => (int) ($courses[0]['course_liked'] ?? 0) > 0,
            ],
            'plans' => membership_plans(),
        ]);
    }

    if ($action === 'toggle_follow') {
        $user = api_user();
        $data = api_input();
        $instructorId = (int) ($data['instructor_id'] ?? $_GET['instructor_id'] ?? $_POST['instructor_id'] ?? 0);
        $followAction = strtolower(trim((string) ($data['follow_action'] ?? $_GET['follow_action'] ?? $_POST['follow_action'] ?? '')));
        $requestedFollow = null;
        if ($followAction === 'follow') {
            $requestedFollow = true;
        } elseif ($followAction === 'unfollow') {
            $requestedFollow = false;
        } elseif (array_key_exists('follow', $data) || isset($_GET['follow']) || isset($_POST['follow'])) {
            $requestedFollow = filter_var($data['follow'] ?? $_GET['follow'] ?? $_POST['follow'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
        }
        if ($instructorId <= 0) {
            api_out(['success' => false, 'message' => 'Instructor missing.'], 422);
        }
        $stmt = db()->prepare('SELECT id FROM student_instructor_follows WHERE student_id = ? AND instructor_id = ? LIMIT 1');
        $studentId = (int) $user['id'];
        $stmt->bind_param('ii', $studentId, $instructorId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $currentlyFollowing = (bool) $row;
        if (($requestedFollow === false && $currentlyFollowing) || ($requestedFollow === null && $currentlyFollowing)) {
            $stmt = db()->prepare('DELETE FROM student_instructor_follows WHERE student_id = ? AND instructor_id = ?');
            $stmt->bind_param('ii', $studentId, $instructorId);
            $stmt->execute();
        } elseif (($requestedFollow === true && !$currentlyFollowing) || ($requestedFollow === null && !$currentlyFollowing)) {
            $stmt = db()->prepare('INSERT IGNORE INTO student_instructor_follows (student_id, instructor_id) VALUES (?, ?)');
            $stmt->bind_param('ii', $studentId, $instructorId);
            $stmt->execute();
        }
        $stmt = db()->prepare('SELECT id FROM student_instructor_follows WHERE student_id = ? AND instructor_id = ? LIMIT 1');
        $stmt->bind_param('ii', $studentId, $instructorId);
        $stmt->execute();
        $following = (bool) $stmt->get_result()->fetch_assoc();
        if ($requestedFollow === true && !$following) {
            api_out(['success' => false, 'message' => 'Follow could not be saved. Please try again.'], 500);
        }
        if ($requestedFollow === false && $following) {
            api_out(['success' => false, 'message' => 'Unfollow could not be saved. Please try again.'], 500);
        }
        $stmt = db()->prepare('SELECT COUNT(*) AS total FROM student_instructor_follows WHERE instructor_id = ?');
        $stmt->bind_param('i', $instructorId);
        $stmt->execute();
        api_out([
            'success' => true,
            'following' => $following,
            'is_following' => $following,
            'follower_count' => (int) ($stmt->get_result()->fetch_assoc()['total'] ?? 0),
        ]);
    }

    if ($action === 'toggle_like') {
        $user = api_user();
        $data = api_input();
        $contentId = (int) ($data['content_id'] ?? 0);
        if ($contentId <= 0) {
            api_out(['success' => false, 'message' => 'Content missing.'], 422);
        }
        $stmt = db()->prepare('SELECT course_id FROM instructor_course_contents WHERE id = ? LIMIT 1');
        $stmt->bind_param('i', $contentId);
        $stmt->execute();
        $content = $stmt->get_result()->fetch_assoc();
        if (!$content) {
            api_out(['success' => false, 'message' => 'Content not found.'], 404);
        }
        $courseId = (int) $content['course_id'];
        $stmt = db()->prepare('SELECT id FROM student_content_likes WHERE student_id = ? AND content_id = ? LIMIT 1');
        $studentId = (int) $user['id'];
        $stmt->bind_param('ii', $studentId, $contentId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        if ($row) {
            $id = (int) $row['id'];
            $stmt = db()->prepare('DELETE FROM student_content_likes WHERE id = ?');
            $stmt->bind_param('i', $id);
            $stmt->execute();
            $liked = false;
        } else {
            $stmt = db()->prepare('INSERT IGNORE INTO student_content_likes (student_id, content_id) VALUES (?, ?)');
            $stmt->bind_param('ii', $studentId, $contentId);
            $stmt->execute();
            $liked = true;
        }
        $stmt = db()->prepare('SELECT COUNT(*) AS total FROM student_content_likes WHERE content_id = ?');
        $stmt->bind_param('i', $contentId);
        $stmt->execute();
        $likeCount = (int) ($stmt->get_result()->fetch_assoc()['total'] ?? 0);
        $stmt = db()->prepare("
            SELECT COUNT(*) AS total
            FROM student_content_likes l
            INNER JOIN instructor_course_contents cc ON cc.id = l.content_id
            WHERE cc.course_id = ? AND cc.status = 'published'
        ");
        $stmt->bind_param('i', $courseId);
        $stmt->execute();
        $courseContentLikes = (int) ($stmt->get_result()->fetch_assoc()['total'] ?? 0);
        api_out([
            'success' => true,
            'liked' => $liked,
            'like_count' => $likeCount,
            'course_like_count' => $courseContentLikes + course_like_display_boost($courseId),
        ]);
    }

    if ($action === 'toggle_course_like') {
        $user = api_user();
        $data = api_input();
        $courseId = (int) ($data['course_id'] ?? $_GET['course_id'] ?? 0);
        if ($courseId <= 0) {
            api_out(['success' => false, 'message' => 'Course missing.'], 422);
        }
        $stmt = db()->prepare('SELECT id FROM instructor_courses WHERE id = ? AND status = "published" LIMIT 1');
        $stmt->bind_param('i', $courseId);
        $stmt->execute();
        if (!$stmt->get_result()->fetch_assoc()) {
            api_out(['success' => false, 'message' => 'Course not found.'], 404);
        }

        $studentId = (int) $user['id'];
        $stmt = db()->prepare('SELECT id FROM student_course_likes WHERE student_id = ? AND course_id = ? LIMIT 1');
        $stmt->bind_param('ii', $studentId, $courseId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        if ($row) {
            $id = (int) $row['id'];
            $stmt = db()->prepare('DELETE FROM student_course_likes WHERE id = ?');
            $stmt->bind_param('i', $id);
            $stmt->execute();
            $liked = false;
        } else {
            $stmt = db()->prepare('INSERT IGNORE INTO student_course_likes (student_id, course_id) VALUES (?, ?)');
            $stmt->bind_param('ii', $studentId, $courseId);
            $stmt->execute();
            $liked = true;
        }
        $stmt = db()->prepare('SELECT COUNT(*) AS total FROM student_course_likes WHERE course_id = ?');
        $stmt->bind_param('i', $courseId);
        $stmt->execute();
        $realCourseLikes = (int) ($stmt->get_result()->fetch_assoc()['total'] ?? 0);
        api_out([
            'success' => true,
            'course_liked' => $liked,
            'liked' => $liked,
            'course_like_count' => $realCourseLikes + course_like_display_boost($courseId),
        ]);
    }

    if ($action === 'update_progress') {
        $user = api_user();
        $data = api_input();
        $courseId = (int) ($data['course_id'] ?? 0);
        $contentId = (int) ($data['content_id'] ?? 0);
        $watched = max(0, (int) ($data['watched_seconds'] ?? 0));
        $duration = max(60, (int) ($data['duration_seconds'] ?? 0));
        if ($courseId <= 0 || $contentId <= 0) {
            api_out(['success' => false, 'message' => 'Course or content missing.'], 422);
        }

        if (!student_can_access_course((int) $user['id'], $courseId, false)) {
            api_out(['success' => false, 'message' => 'Please enroll before watching this course.'], 403);
        }

        $stmt = db()->prepare('SELECT id FROM instructor_course_contents WHERE id = ? AND course_id = ? AND status = "published" LIMIT 1');
        $stmt->bind_param('ii', $contentId, $courseId);
        $stmt->execute();
        if (!$stmt->get_result()->fetch_assoc()) {
            api_out(['success' => false, 'message' => 'Content not found.'], 404);
        }

        $percent = min(100, round((min($watched, $duration) / $duration) * 100, 2));
        $completedAt = $percent >= 80 ? date('Y-m-d H:i:s') : null;
        $stmt = db()->prepare("
            INSERT INTO student_content_progress
                (student_id, course_id, content_id, watched_seconds, duration_seconds, progress_percent, completed_at, last_watched_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
            ON DUPLICATE KEY UPDATE
                watched_seconds = GREATEST(watched_seconds, VALUES(watched_seconds)),
                duration_seconds = GREATEST(duration_seconds, VALUES(duration_seconds)),
                progress_percent = GREATEST(progress_percent, VALUES(progress_percent)),
                completed_at = IF(completed_at IS NULL AND VALUES(progress_percent) >= 80, VALUES(completed_at), completed_at),
                last_watched_at = NOW()
        ");
        $stmt->bind_param('iiiiids', $user['id'], $courseId, $contentId, $watched, $duration, $percent, $completedAt);
        $stmt->execute();

        $progress = student_course_progress((int) $user['id'], $courseId);
        $coursePercent = (float) $progress['course_percent'];
        $status = $coursePercent >= 95 ? 'completed' : 'active';
        $completed = $coursePercent >= 95 ? date('Y-m-d H:i:s') : null;
        $stmt = db()->prepare('
            INSERT INTO student_course_enrollments (student_id, course_id, progress_percent, status, completed_at)
            VALUES (?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE progress_percent = GREATEST(progress_percent, VALUES(progress_percent)),
                status = IF(VALUES(progress_percent) >= 95, "completed", status),
                completed_at = IF(completed_at IS NULL AND VALUES(progress_percent) >= 95, VALUES(completed_at), completed_at)
        ');
        $stmt->bind_param('iidss', $user['id'], $courseId, $coursePercent, $status, $completed);
        $stmt->execute();

        $newAchievement = false;
        if ($coursePercent >= 95 && !$progress['achievement']) {
            $rank = 'Rising Learner';
            $stmt = db()->prepare('
                INSERT INTO student_course_achievements (student_id, course_id, stars, rank_title, progress_percent, achieved_at)
                VALUES (?, ?, 1, ?, ?, NOW())
                ON DUPLICATE KEY UPDATE progress_percent = GREATEST(progress_percent, VALUES(progress_percent))
            ');
            $stmt->bind_param('iisd', $user['id'], $courseId, $rank, $coursePercent);
            $stmt->execute();
            $newAchievement = true;
            $progress = student_course_progress((int) $user['id'], $courseId);
        }

        api_out([
            'success' => true,
            'message' => $newAchievement ? 'Congratulations! You have achieved 1 star.' : 'Progress saved.',
            'progress' => $progress,
            'achievement_unlocked' => $newAchievement,
        ]);
    }

    if ($action === 'purchase_plan') {
        $user = api_user();
        $data = api_input();
        $planId = (int) ($data['plan_id'] ?? $_GET['plan_id'] ?? 0);
        $billingCycle = strtolower(trim((string) ($data['billing_cycle'] ?? $_GET['billing_cycle'] ?? 'monthly')));
        $appReturn = strtolower(trim((string) ($data['client'] ?? $_GET['client'] ?? ''))) === 'app';
        if ($planId <= 0) {
            api_out(['success' => false, 'message' => 'Plan missing.'], 422);
        }

        $stmt = db()->prepare('SELECT * FROM student_membership_plans WHERE id = ? AND is_active = 1 LIMIT 1');
        $stmt->bind_param('i', $planId);
        $stmt->execute();
        $plan = $stmt->get_result()->fetch_assoc();
        if (!$plan) {
            api_out(['success' => false, 'message' => 'Plan not found.'], 404);
        }

        $studentId = (int) $user['id'];
        $cycle = plan_cycle_details($plan, $billingCycle);
        $baseAmount = max(0, (float) ($cycle['amount'] ?? 0));
        $taxRate = max(0, (float) app_setting('tax_rate', '18'));
        $amount = $baseAmount > 0 ? round($baseAmount * (1 + ($taxRate / 100)), 2) : 0.0;
        if ($amount > 0 && !phonepe_enabled()) {
            api_out(['success' => false, 'message' => 'PhonePe payment gateway is not configured. Please contact admin.'], 422);
        }
        if ($amount > 0) {
            require_student_billing_info($studentId);
        }
        $txn = 'PL-' . date('ymdHis') . '-' . random_int(1000, 9999);
        $usePhonePe = $amount > 0;
        $method = $amount <= 0 ? 'free' : ($usePhonePe ? 'phonepe' : 'manual');
        $status = $usePhonePe ? 'pending' : 'paid';

        $stmt = db()->prepare('
            INSERT INTO student_membership_purchases (student_id, plan_id, original_amount, amount, billing_cycle, validity_days, payment_method, payment_status, transaction_no)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ');
        $cycleName = (string) ($cycle['billing_cycle'] ?? 'monthly');
        $cycleDays = (int) ($cycle['validity_days'] ?? 30);
        $originalBaseAmount = max($baseAmount, (float) ($cycle['regular_amount'] ?? $baseAmount));
        $originalAmount = $originalBaseAmount > 0 ? round($originalBaseAmount * (1 + ($taxRate / 100)), 2) : 0.0;
        $stmt->bind_param('iiddsisss', $studentId, $planId, $originalAmount, $amount, $cycleName, $cycleDays, $method, $status, $txn);
        $stmt->execute();
        $purchaseId = (int) db()->insert_id;

        if ($usePhonePe) {
            try {
                $payment = phonepe_create_payment($txn, $studentId, $amount, 'membership', $appReturn);
            } catch (Throwable $e) {
                mark_payment_start_failed('student_membership_purchases', $purchaseId);
                email_notify_purchase_event('payment_failed', 'membership', $purchaseId);
                api_out(['success' => false, 'message' => 'PhonePe payment start failed: ' . $e->getMessage()], 422);
            }
            api_out([
                'success' => true,
                'payment_required' => true,
                'message' => 'Redirecting to PhonePe payment.',
                'transaction_no' => $txn,
                'amount' => $amount,
                'billing_cycle' => $cycle['billing_cycle'],
                'payment' => $payment,
            ]);
        }

        $purchase = complete_membership_purchase($purchaseId);
        api_out([
            'success' => true,
            'message' => $amount <= 0 ? 'Free plan activated.' : 'Membership plan activated.',
            'transaction_no' => $txn,
            'amount' => $amount,
            'billing_cycle' => $cycle['billing_cycle'],
            'subscription' => active_plan_subscription($studentId),
            'purchase' => $purchase,
        ]);
    }

    if ($action === 'gov_prep') {
        gov_exam_ensure_tables();
        $user = api_optional_user();
        $studentId = $user ? (int) $user['id'] : null;
        $categoryId = max(0, (int) ($_GET['cat'] ?? 0));
        $parentId = max(0, (int) ($_GET['parent'] ?? 0));
        $lite = isset($_GET['lite']) || $categoryId > 0 || $parentId > 0;
        if ($studentId && !$lite) {
            ensure_default_free_plan($studentId);
        }
        api_out([
            'success' => true,
            'categories' => gov_categories_payload(),
            'documents' => $lite ? [] : gov_documents_payload($studentId),
            'live' => $lite ? [] : gov_live_payload($studentId),
            'mocks' => $lite ? gov_mocks_fast_payload($categoryId ?: null, $parentId ?: null) : gov_mocks_payload($studentId),
            'subscription' => $studentId ? active_plan_subscription($studentId) : null,
            'plans' => membership_plans(),
        ]);
    }

    if ($action === 'gov_document_open') {
        gov_exam_ensure_tables();
        $user = api_user();
        $studentId = (int) $user['id'];
        ensure_default_free_plan($studentId);
        $id = max(0, (int) ($_GET['id'] ?? 0));
        $stmt = db()->prepare("SELECT d.*, c.name AS category_name, s.name AS subcategory_name FROM gov_exam_documents d LEFT JOIN gov_exam_categories c ON c.id=d.category_id LEFT JOIN gov_exam_categories s ON s.id=d.subcategory_id WHERE d.id=? AND d.status='published' LIMIT 1");
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $doc = $stmt->get_result()->fetch_assoc();
        if (!$doc) {
            api_out(['success' => false, 'message' => 'Document not found.'], 404);
        }
        if (!gov_plan_allowed($studentId, 'document', $id)) {
            api_out(['success' => false, 'message' => 'Membership PDF limit reached. Please upgrade your plan.'], 403);
        }
        register_gov_document_access($studentId, $id);
        $url = trim((string) ($doc['document_url'] ?? ''));
        if ($url !== '' && !preg_match('#^https?://#i', $url)) {
            $url = api_absolute_url($url);
        }
        $doc['id'] = $id;
        $doc['file_url'] = $url;
        $doc['locked'] = false;
        api_out(['success' => true, 'document' => $doc]);
    }

    if ($action === 'gov_live_join') {
        gov_exam_ensure_tables();
        $user = api_user();
        $studentId = (int) $user['id'];
        ensure_default_free_plan($studentId);
        $id = max(0, (int) ($_GET['id'] ?? 0));
        $stmt = db()->prepare("SELECT l.*, c.name AS category_name, s.name AS subcategory_name FROM gov_exam_live_sessions l LEFT JOIN gov_exam_categories c ON c.id=l.category_id LEFT JOIN gov_exam_categories s ON s.id=l.subcategory_id WHERE l.id=? AND l.status IN ('scheduled','live') LIMIT 1");
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $live = $stmt->get_result()->fetch_assoc();
        if (!$live) {
            api_out(['success' => false, 'message' => 'Live session not found.'], 404);
        }
        if (!gov_plan_allowed($studentId, 'live', $id)) {
            api_out(['success' => false, 'message' => 'Membership live class limit reached. Please upgrade your plan.'], 403);
        }
        register_gov_live_access($studentId, $id);
        $live['id'] = $id;
        $live['locked'] = false;
        $live['join_url'] = (string) ($live['live_url'] ?? '');
        api_out(['success' => true, 'live' => $live]);
    }

    if ($action === 'gov_mock_detail') {
        gov_exam_ensure_tables();
        $user = api_user();
        $studentId = (int) $user['id'];
        $mockId = max(0, (int) ($_GET['id'] ?? 0));
        $mock = gov_mock_detail_row($mockId);
        if (!$mock) {
            api_out(['success' => false, 'message' => 'Mock test not found.'], 404);
        }
        if (!gov_plan_allowed($studentId, 'mock', $mockId)) {
            api_out(['success' => false, 'message' => 'Membership mock test limit reached. Please upgrade your plan.'], 403);
        }
        register_gov_mock_access($studentId, $mockId);
        api_out(['success' => true, 'mock' => $mock, 'questions' => gov_mock_questions($mockId, false)]);
    }

    if ($action === 'submit_gov_mock') {
        gov_exam_ensure_tables();
        $user = api_user();
        $studentId = (int) $user['id'];
        $data = api_input();
        $mockId = max(0, (int) ($data['mock_id'] ?? $_GET['id'] ?? 0));
        $mock = gov_mock_detail_row($mockId);
        if (!$mock) {
            api_out(['success' => false, 'message' => 'Mock test not found.'], 404);
        }
        if (!gov_plan_allowed($studentId, 'mock', $mockId)) {
            api_out(['success' => false, 'message' => 'Membership mock test limit reached. Please upgrade your plan.'], 403);
        }
        $answers = is_array($data['answers'] ?? null) ? $data['answers'] : [];
        api_out(['success' => true, 'result' => save_gov_mock_attempt($studentId, $mock, $answers)]);
    }

    if ($action === 'purchase') {
        $user = api_user();
        $data = api_input();
        $courseId = (int) ($data['course_id'] ?? $_GET['course_id'] ?? 0);
        $appReturn = strtolower(trim((string) ($data['client'] ?? $_GET['client'] ?? ''))) === 'app';
        $useGcoin = !empty($data['use_gcoin']);
        $courses = course_rows($courseId);
        if (!$courses) {
            api_out(['success' => false, 'message' => 'Course not found.'], 404);
        }
        $course = $courses[0];
        if (student_has_course_access((int) $user['id'], $courseId)) {
            api_out([
                'success' => true,
                'message' => 'Already enrolled.',
                'already_enrolled' => true,
                'my_courses' => my_course_rows((int) $user['id']),
            ]);
        }
        $originalAmount = ((int) $course['is_free'] === 1) ? 0.00 : (float) $course['price'];
        if ($originalAmount > 0) {
            if (student_can_access_course((int) $user['id'], $courseId, true)) {
                api_out([
                    'success' => true,
                    'message' => 'Course unlocked with All Access membership.',
                    'membership_access' => true,
                    'my_courses' => my_course_rows((int) $user['id']),
                ]);
            }
            api_out([
                'success' => false,
                'membership_required' => true,
                'message' => 'Activate GYAN NEXA All Access to unlock every course, PDF, exam and live class.',
                'plans' => membership_plans(),
            ], 402);
        }
        $amount = $originalAmount;
        $gcoinUsed = 0.0;
        $gcoinDiscount = 0.0;
        if ($useGcoin && $amount > 0 && gcoin_enabled() && app_setting('gcoin_purchase_redeem_enabled', '1') === '1') {
            $wallet = gcoin_wallet_row((int) $user['id']);
            $balance = (float) ($wallet['balance'] ?? 0);
            $coinsPerInr = gcoin_setting_float('gcoin_per_inr', 10);
            $minRedeem = gcoin_setting_float('gcoin_min_redeem', 10);
            $maxCoinsByAmount = floor($amount * $coinsPerInr);
            $requested = isset($data['redeem_coins']) ? (float) $data['redeem_coins'] : $balance;
            $gcoinUsed = max(0, min($balance, $requested, $maxCoinsByAmount));
            if ($gcoinUsed >= $minRedeem) {
                $gcoinDiscount = round($gcoinUsed / $coinsPerInr, 2);
                $amount = max(0, round($amount - $gcoinDiscount, 2));
            } else {
                $gcoinUsed = 0.0;
                $gcoinDiscount = 0.0;
            }
        }
        if ($amount > 0 && !phonepe_enabled()) {
            api_out(['success' => false, 'message' => 'PhonePe payment gateway is not configured. Please contact admin.'], 422);
        }
        if ($amount > 0) {
            require_student_billing_info((int) $user['id']);
        }
        $txn = 'EL-' . date('ymdHis') . '-' . random_int(1000, 9999);
        $usePhonePe = $amount > 0;
        $method = $amount <= 0 ? 'free' : ($usePhonePe ? 'phonepe' : 'manual');
        $status = $usePhonePe ? 'pending' : 'paid';

        $stmt = db()->prepare('
            INSERT INTO student_course_purchases (student_id, course_id, original_amount, amount, gcoin_used, gcoin_discount, payment_method, payment_status, transaction_no)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE original_amount = VALUES(original_amount), amount = VALUES(amount), gcoin_used = VALUES(gcoin_used), gcoin_discount = VALUES(gcoin_discount), payment_method = VALUES(payment_method), payment_status = VALUES(payment_status), transaction_no = VALUES(transaction_no)
        ');
        $stmt->bind_param('iiddddsss', $user['id'], $courseId, $originalAmount, $amount, $gcoinUsed, $gcoinDiscount, $method, $status, $txn);
        $stmt->execute();

        $stmt = db()->prepare('SELECT id FROM student_course_purchases WHERE student_id = ? AND course_id = ? LIMIT 1');
        $stmt->bind_param('ii', $user['id'], $courseId);
        $stmt->execute();
        $purchaseId = (int) ($stmt->get_result()->fetch_assoc()['id'] ?? 0);

        if ($usePhonePe) {
            try {
                $payment = phonepe_create_payment($txn, (int) $user['id'], $amount, 'course', $appReturn);
            } catch (Throwable $e) {
                mark_payment_start_failed('student_course_purchases', $purchaseId);
                email_notify_purchase_event('payment_failed', 'course', $purchaseId);
                api_out(['success' => false, 'message' => 'PhonePe payment start failed: ' . $e->getMessage()], 422);
            }
            api_out([
                'success' => true,
                'payment_required' => true,
                'message' => 'Redirecting to PhonePe payment.',
                'transaction_no' => $txn,
                'amount' => $amount,
                'gcoin_used' => $gcoinUsed,
                'gcoin_discount' => $gcoinDiscount,
                'payment' => $payment,
            ]);
        }

        if ($gcoinUsed > 0) {
            gcoin_add_transaction((int) $user['id'], 'debit', $gcoinUsed, 'course_purchase', $purchaseId, 'Course purchase discount');
        }

        $stmt = db()->prepare('SELECT referrer_id FROM student_referrals WHERE referred_student_id = ? LIMIT 1');
        $stmt->bind_param('i', $user['id']);
        $stmt->execute();
        $refRow = $stmt->get_result()->fetch_assoc();
        if ($refRow && $originalAmount > 0) {
            $rewardCoins = gcoin_purchase_reward($originalAmount);
            if ($rewardCoins > 0) {
                $referrerId = (int) $refRow['referrer_id'];
                gcoin_add_transaction($referrerId, 'credit', $rewardCoins, 'referral_purchase', $purchaseId, 'Referral purchase reward');
                $stmt = db()->prepare('UPDATE student_referrals SET purchase_referrer_coins = purchase_referrer_coins + ? WHERE referred_student_id = ?');
                $stmt->bind_param('di', $rewardCoins, $user['id']);
                $stmt->execute();
            }
        }

        clear_student_course_plan_revocation((int) $user['id'], $courseId);
        student_has_course_access((int) $user['id'], $courseId);

        api_out([
            'success' => true,
            'message' => $amount <= 0 ? 'Course enrolled with Gcoin/free access.' : 'Course enrolled successfully.',
            'transaction_no' => $txn,
            'amount' => $amount,
            'gcoin_used' => $gcoinUsed,
            'gcoin_discount' => $gcoinDiscount,
            'wallet' => gcoin_wallet_row((int) $user['id']),
            'my_courses' => my_course_rows((int) $user['id'])
        ]);
    }

    if ($action === 'profile') {
        $user = api_user();
        $stmt = db()->prepare('SELECT id, full_name, username, email, phone, gender, date_of_birth, profile_photo, city, bio FROM users WHERE id = ? LIMIT 1');
        $stmt->bind_param('i', $user['id']);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc() ?: $user;
        api_out(['success' => true, 'user' => [
            'id' => (int) $row['id'],
            'name' => $row['full_name'],
            'username' => $row['username'],
            'email' => $row['email'],
            'phone' => $row['phone'],
            'gender' => $row['gender'] ?? '',
            'date_of_birth' => $row['date_of_birth'] ?? '',
            'profile_photo' => api_absolute_url((string) ($row['profile_photo'] ?? '')),
            'city' => $row['city'] ?? '',
            'bio' => $row['bio'] ?? '',
            'referral_code' => student_referral_code((int) $row['id']),
            'gcoin_wallet' => gcoin_wallet_row((int) $row['id']),
        ]]);
    }

    if ($action === 'update_profile') {
        $user = api_user();
        $data = api_input();
        $name = substr(trim((string) ($data['name'] ?? '')), 0, 120);
        $email = substr(trim((string) ($data['email'] ?? '')), 0, 160);
        $phone = preg_replace('/\D+/', '', (string) ($data['phone'] ?? ''));
        $gender = substr(trim((string) ($data['gender'] ?? '')), 0, 20);
        $dob = trim((string) ($data['date_of_birth'] ?? ''));
        $profilePhoto = substr(trim((string) ($data['profile_photo'] ?? '')), 0, 255);
        $city = substr(trim((string) ($data['city'] ?? '')), 0, 120);
        $bio = substr(trim((string) ($data['bio'] ?? '')), 0, 500);
        $photoBase64 = (string) ($data['profile_photo_base64'] ?? '');
        $photoName = substr(trim((string) ($data['profile_photo_name'] ?? 'profile.jpg')), 0, 120);

        if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || !preg_match('/^\d{10}$/', $phone)) {
            api_out(['success' => false, 'message' => 'Enter name, valid email and 10 digit phone.'], 422);
        }
        if ($gender !== '' && !in_array($gender, ['male', 'female', 'other'], true)) {
            api_out(['success' => false, 'message' => 'Select valid gender.'], 422);
        }
        if ($dob !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dob)) {
            api_out(['success' => false, 'message' => 'Date of birth must be YYYY-MM-DD.'], 422);
        }
        if ($photoBase64 !== '') {
            if (preg_match('/^data:image\/[a-zA-Z0-9.+-]+;base64,/', $photoBase64)) {
                $photoBase64 = preg_replace('/^data:image\/[a-zA-Z0-9.+-]+;base64,/', '', $photoBase64);
            }
            $photoBase64 = str_replace(' ', '+', $photoBase64);
            $bytes = base64_decode($photoBase64, true);
            if ($bytes === false || strlen($bytes) > 3 * 1024 * 1024) {
                api_out(['success' => false, 'message' => 'Profile photo must be a valid image under 3 MB.'], 422);
            }
            $info = @getimagesizefromstring($bytes);
            if (!$info || !in_array($info[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP], true)) {
                api_out(['success' => false, 'message' => 'Only JPG, PNG or WEBP profile photo allowed.'], 422);
            }
            if ($info[2] === IMAGETYPE_PNG) {
                $ext = 'png';
            } elseif ($info[2] === IMAGETYPE_WEBP) {
                $ext = 'webp';
            } else {
                $ext = 'jpg';
            }
            $dir = __DIR__ . '/../uploads/profiles';
            if (!is_dir($dir)) {
                mkdir($dir, 0775, true);
            }
            $safeName = preg_replace('/[^a-z0-9._-]+/i', '-', pathinfo($photoName, PATHINFO_FILENAME)) ?: 'profile';
            $fileName = 'student-' . (int) $user['id'] . '-' . time() . '-' . $safeName . '.' . $ext;
            $path = $dir . '/' . $fileName;
            if (file_put_contents($path, $bytes) === false) {
                api_out(['success' => false, 'message' => 'Profile photo upload failed.'], 500);
            }
            $profilePhoto = 'uploads/profiles/' . $fileName;
        }

        $stmt = db()->prepare('SELECT id FROM users WHERE (email = ? OR phone = ?) AND id <> ? LIMIT 1');
        $stmt->bind_param('ssi', $email, $phone, $user['id']);
        $stmt->execute();
        if ($stmt->get_result()->fetch_assoc()) {
            api_out(['success' => false, 'message' => 'Email or phone already used.'], 409);
        }

        $stmt = db()->prepare('UPDATE users SET full_name = ?, email = ?, phone = ?, gender = ?, date_of_birth = NULLIF(?, ""), profile_photo = ?, city = ?, bio = ? WHERE id = ?');
        $stmt->bind_param('ssssssssi', $name, $email, $phone, $gender, $dob, $profilePhoto, $city, $bio, $user['id']);
        $stmt->execute();
        email_notify_user(
            'profile_updated',
            ['id' => (int) $user['id'], 'full_name' => $name, 'username' => $user['username'], 'email' => $email, 'phone' => $phone],
            [],
            'Profile updated on {site_name}',
            '<h2>Profile updated</h2><p>Hello {user_name}, your profile details were updated successfully.</p>'
        );
        api_out(['success' => true, 'message' => 'Profile updated.', 'user' => [
            'id' => (int) $user['id'],
            'name' => $name,
            'username' => $user['username'],
            'email' => $email,
            'phone' => $phone,
            'gender' => $gender,
            'date_of_birth' => $dob,
            'profile_photo' => api_absolute_url($profilePhoto),
            'city' => $city,
            'bio' => $bio,
        ]]);
    }

    if ($action === 'change_password') {
        $user = api_user();
        $data = api_input();
        $current = (string) ($data['current_password'] ?? '');
        $new = (string) ($data['new_password'] ?? '');
        if (strlen($new) < 8) {
            api_out(['success' => false, 'message' => 'New password must be at least 8 characters.'], 422);
        }
        $stmt = db()->prepare('SELECT password_hash FROM users WHERE id = ? LIMIT 1');
        $stmt->bind_param('i', $user['id']);
        $stmt->execute();
        $hash = (string) ($stmt->get_result()->fetch_assoc()['password_hash'] ?? '');
        if (!password_verify($current, $hash)) {
            api_out(['success' => false, 'message' => 'Current password is incorrect.'], 422);
        }
        $newHash = password_hash($new, PASSWORD_DEFAULT);
        $stmt = db()->prepare('UPDATE users SET password_hash = ? WHERE id = ?');
        $stmt->bind_param('si', $newHash, $user['id']);
        $stmt->execute();
        email_notify_user(
            'password_changed',
            (int) $user['id'],
            [],
            'Password changed on {site_name}',
            '<h2>Password changed</h2><p>Hello {user_name}, your account password was changed. If this was not you, contact support immediately.</p>'
        );
        api_out(['success' => true, 'message' => 'Password changed.']);
    }

    if ($action === 'batches') {
        api_user();
        api_out(['success' => true, 'batches' => batch_rows()]);
    }

    if ($action === 'live_classes') {
        $user = api_user();
        api_out(['success' => true, 'live_classes' => live_class_rows(true, (int) $user['id'])]);
    }

    if ($action === 'live_recordings') {
        $user = api_user();
        api_out(['success' => true, 'recordings' => student_live_recording_rows((int) $user['id'])]);
    }

    if ($action === 'live_comments') {
        api_user();
        $sourceType = ($_GET['source_type'] ?? 'class') === 'batch' ? 'batch' : 'class';
        $sourceId = (int) ($_GET['source_id'] ?? 0);
        api_out(['success' => true, 'comments' => live_comment_rows($sourceType, $sourceId)]);
    }

    if ($action === 'post_live_comment') {
        $user = api_user();
        $data = api_input();
        $sourceType = ($data['source_type'] ?? 'class') === 'batch' ? 'batch' : 'class';
        $sourceId = max(0, (int) ($data['source_id'] ?? 0));
        $comment = trim(preg_replace('/\s+/', ' ', (string) ($data['comment'] ?? '')));
        $comment = function_exists('mb_substr') ? mb_substr($comment, 0, 500) : substr($comment, 0, 500);
        if ($sourceId <= 0 || $comment === '') {
            api_out(['success' => false, 'message' => 'Comment required.'], 422);
        }

        $stmt = db()->prepare('INSERT INTO student_live_class_comments (source_type, source_id, student_id, comment_text) VALUES (?, ?, ?, ?)');
        $stmt->bind_param('siis', $sourceType, $sourceId, $user['id'], $comment);
        $stmt->execute();
        api_out([
            'success' => true,
            'message' => 'Comment posted.',
            'comments' => live_comment_rows($sourceType, $sourceId),
        ]);
    }

    if ($action === 'exams') {
        $user = api_optional_user();
        api_out(['success' => true, 'exams' => exam_rows($user ? (int) $user['id'] : null)]);
    }

    if ($action === 'exam_overview') {
        $user = api_user();
        $examId = max(0, (int) ($_GET['exam_id'] ?? 0));
        $exam = exam_detail_row($examId);
        if (!$exam) {
            api_out(['success' => false, 'message' => 'Exam not found.'], 404);
        }
        if (!student_can_access_exam((int) $user['id'], $exam)) {
            api_out(['success' => false, 'message' => 'Please enroll in this course or activate an exam plan to access this test.'], 403);
        }
        api_out([
            'success' => true,
            'exam' => $exam,
            'attempts' => exam_attempt_rows((int) $user['id'], $examId),
            'active_session' => active_exam_session((int) $user['id'], $examId),
        ]);
    }

    if ($action === 'exam_detail') {
        $user = api_user();
        $examId = max(0, (int) ($_GET['exam_id'] ?? 0));
        $exam = exam_detail_row($examId);
        if (!$exam) {
            api_out(['success' => false, 'message' => 'Exam not found.'], 404);
        }
        if (!student_can_access_exam((int) $user['id'], $exam)) {
            api_out(['success' => false, 'message' => 'Please enroll in this course or activate an exam plan to start this test.'], 403);
        }
        start_exam_session((int) $user['id'], $exam);
        $session = active_exam_session((int) $user['id'], $examId);
        $questions = exam_session_question_rows($exam, $session);
        $savedQuestionIds = json_decode((string) ($session['question_json'] ?? ''), true);
        $savedQuestionCount = is_array($savedQuestionIds) ? count($savedQuestionIds) : 0;
        if ($savedQuestionCount !== count($questions)) {
            save_exam_session_questions((int) $user['id'], $examId, array_column($questions, 'id'));
            $session = active_exam_session((int) $user['id'], $examId);
        }
        $lastAttempt = last_exam_attempt((int) $user['id'], $examId);
        api_out([
            'success' => true,
            'exam' => $exam,
            'questions' => $questions,
            'last_attempt' => $lastAttempt,
            'active_session' => $session,
        ]);
    }

    if ($action === 'save_exam_draft') {
        $user = api_user();
        $data = api_input();
        $examId = max(0, (int) ($data['exam_id'] ?? 0));
        $exam = exam_detail_row($examId);
        if (!$exam) {
            api_out(['success' => false, 'message' => 'Exam not found.'], 404);
        }
        if (!student_can_access_exam((int) $user['id'], $exam)) {
            api_out(['success' => false, 'message' => 'Please enroll in this course or activate an exam plan to save this test.'], 403);
        }
        start_exam_session((int) $user['id'], $exam);
        $answers = is_array($data['answers'] ?? null) ? $data['answers'] : [];
        $review = is_array($data['review_questions'] ?? null) ? $data['review_questions'] : [];
        $currentQuestion = max(0, (int) ($data['current_question'] ?? 0));
        save_exam_draft((int) $user['id'], $examId, $answers, $review, $currentQuestion);
        api_out(['success' => true, 'message' => 'Draft saved.']);
    }

    if ($action === 'attempt_detail') {
        $user = api_user();
        $attemptId = max(0, (int) ($_GET['attempt_id'] ?? 0));
        $detail = exam_attempt_detail((int) $user['id'], $attemptId);
        if (!$detail) {
            api_out(['success' => false, 'message' => 'Attempt not found.'], 404);
        }
        api_out(['success' => true] + $detail);
    }

    if ($action === 'submit_exam') {
        $user = api_user();
        $data = api_input();
        $examId = max(0, (int) ($data['exam_id'] ?? 0));
        $exam = exam_detail_row($examId);
        if (!$exam) {
            api_out(['success' => false, 'message' => 'Exam not found.'], 404);
        }
        if (!student_can_access_exam((int) $user['id'], $exam)) {
            api_out(['success' => false, 'message' => 'Please enroll in this course or activate an exam plan to submit this test.'], 403);
        }
        $answers = is_array($data['answers'] ?? null) ? $data['answers'] : [];
        save_exam_draft((int) $user['id'], $examId, $answers, [], 0);
        $session = active_exam_session((int) $user['id'], $examId);
        $questions = exam_session_question_rows($exam, $session, true);
        $result = save_exam_attempt((int) $user['id'], $exam, $answers, $questions);
        complete_exam_session((int) $user['id'], $examId);
        api_out(['success' => true, 'message' => 'Test submitted.', 'result' => $result]);
    }

    api_out(['success' => false, 'message' => 'Invalid action.'], 404);
} catch (Throwable $e) {
    api_out(['success' => false, 'message' => $e->getMessage()], 500);
}

function batch_rows(): array
{
    $result = db()->query("
        SELECT b.id, b.batch_name, b.course_title, b.mode, b.start_date, b.class_time, b.capacity, b.status,
               u.full_name AS instructor_name
        FROM instructor_batches b
        LEFT JOIN users u ON u.id = b.instructor_id
        WHERE b.status = 'active'
        ORDER BY b.start_date IS NULL, b.start_date ASC, b.id DESC
        LIMIT 50
    ");
    return $result->fetch_all(MYSQLI_ASSOC);
}

function live_class_rows(bool $includeLinks = false, ?int $studentId = null): array
{
    $result = db()->query("
        SELECT *
        FROM (
            SELECT c.id, 'class' AS source_type, c.class_title, c.class_type, c.class_date, c.starts_at, c.duration_minutes,
                   COALESCE(NULLIF(c.meeting_link, ''), IF(s.live_platform = 'youtube_live', s.youtube_live_link, s.google_meet_link), '') AS meeting_link,
                   c.room_name, c.class_status, c.notes,
                   b.batch_name, b.course_title, b.teacher_name,
                   u.id AS instructor_id, u.full_name AS instructor_name,
                   l.channel_name AS live_channel_name, l.playback_slug AS live_stream_slug, l.status AS live_stream_status, l.course_id AS live_course_id
            FROM instructor_classes c
            LEFT JOIN instructor_batches b ON b.id = c.batch_id
            LEFT JOIN users u ON u.id = c.instructor_id
            LEFT JOIN instructor_settings s ON s.instructor_id = c.instructor_id
            LEFT JOIN live_stream_channels l ON l.instructor_id = c.instructor_id AND l.status <> 'disabled'
            WHERE c.class_status IN ('live','scheduled')

            UNION ALL

            SELECT b.id, 'batch' AS source_type, b.batch_name AS class_title, b.mode AS class_type,
                   b.start_date AS class_date, b.class_time AS starts_at, 0 AS duration_minutes,
                   COALESCE(
                       (
                           SELECT NULLIF(c2.meeting_link, '')
                           FROM instructor_classes c2
                           WHERE c2.batch_id = b.id
                             AND c2.class_status IN ('live','scheduled')
                             AND NULLIF(c2.meeting_link, '') IS NOT NULL
                           ORDER BY FIELD(c2.class_status, 'live', 'scheduled'), c2.class_date IS NULL, c2.class_date ASC, c2.starts_at ASC
                           LIMIT 1
                       ),
                       IF(s.live_platform = 'youtube_live', s.youtube_live_link, s.google_meet_link),
                       ''
                   ) AS meeting_link,
                   '' AS room_name, 'scheduled' AS class_status,
                   CONCAT('Capacity ', b.capacity, ' students') AS notes,
                   b.batch_name, b.course_title, b.teacher_name,
                   u.id AS instructor_id, u.full_name AS instructor_name,
                   l.channel_name AS live_channel_name, l.playback_slug AS live_stream_slug, l.status AS live_stream_status, l.course_id AS live_course_id
            FROM instructor_batches b
            LEFT JOIN users u ON u.id = b.instructor_id
            LEFT JOIN instructor_settings s ON s.instructor_id = b.instructor_id
            LEFT JOIN live_stream_channels l ON l.instructor_id = b.instructor_id AND l.status <> 'disabled'
            WHERE b.status = 'active'
        ) live_items
        ORDER BY FIELD(class_status, 'live', 'scheduled'), class_date IS NULL, class_date ASC, starts_at ASC
        LIMIT 30
    ");
    $rows = $result->fetch_all(MYSQLI_ASSOC);
    foreach ($rows as &$row) {
        $slug = (string) ($row['live_stream_slug'] ?? '');
        $liveCourseId = (int) ($row['live_course_id'] ?? 0);
        $sourceType = (string) ($row['source_type'] ?? 'class');
        $sourceId = (int) ($row['id'] ?? 0);
        $courseAccess = $studentId !== null && $liveCourseId > 0 && student_can_access_course($studentId, $liveCourseId, false);
        $planLiveAccess = $studentId !== null && student_plan_live_allowed($studentId, $sourceType, $sourceId);
        $canWatchLive = $slug !== '' && ($courseAccess || $planLiveAccess);
        $selfHosted = [
            'enabled' => $slug !== '',
            'name' => (string) ($row['live_channel_name'] ?? ''),
            'slug' => $slug,
            'status' => (string) ($row['live_stream_status'] ?? 'offline'),
            'course_id' => $liveCourseId,
            'locked' => $slug !== '' && !$canWatchLive,
            'player_url' => $slug !== '' ? live_base_url() . '/play/' . rawurlencode($slug) : '',
            'token_url' => $slug !== '' ? api_absolute_url('api/live?action=playback_token&stream=' . rawurlencode($slug)) : '',
            'hls_url' => '',
            'expires_in' => 0,
        ];

        if ($includeLinks && $studentId !== null && $canWatchLive) {
            if (!$courseAccess && $planLiveAccess) {
                register_plan_live_access($studentId, $sourceType, $sourceId, $liveCourseId);
            }
            $token = live_playback_token($slug, $studentId);
            $selfHosted['hls_url'] = live_hls_url(['playback_slug' => $slug], $token);
            $selfHosted['expires_in'] = 900;
        }

        $row['self_hosted_live'] = $selfHosted;
        unset($row['live_channel_name'], $row['live_stream_slug'], $row['live_stream_status'], $row['live_course_id']);

        if (!$includeLinks) {
            $row['meeting_link'] = '';
        }
    }
    unset($row);

    $seenSlugs = [];
    foreach ($rows as $row) {
        $slug = (string) ($row['self_hosted_live']['slug'] ?? '');
        if ($slug !== '') {
            $seenSlugs[$slug] = true;
        }
    }

    $standalone = db()->query("
        SELECT l.id, l.channel_name, l.playback_slug, l.status, l.course_id, l.last_started_at,
               u.id AS instructor_id, u.full_name AS instructor_name,
               c.title AS course_title
        FROM live_stream_channels l
        INNER JOIN users u ON u.id = l.instructor_id
        LEFT JOIN instructor_courses c ON c.id = l.course_id
        WHERE l.status <> 'disabled'
        ORDER BY FIELD(l.status, 'live', 'scheduled', 'offline'), l.last_started_at DESC, l.id DESC
        LIMIT 10
    ");
    foreach ($standalone->fetch_all(MYSQLI_ASSOC) as $channel) {
        $slug = (string) ($channel['playback_slug'] ?? '');
        if ($slug === '' || isset($seenSlugs[$slug])) {
            continue;
        }

        $liveCourseId = (int) ($channel['course_id'] ?? 0);
        $sourceId = (int) ($channel['id'] ?? 0);
        $courseAccess = $studentId !== null && $liveCourseId > 0 && student_can_access_course($studentId, $liveCourseId, false);
        $planLiveAccess = $studentId !== null && student_plan_live_allowed($studentId, 'stream', $sourceId);
        $canWatchLive = $courseAccess || $planLiveAccess;
        $selfHosted = [
            'enabled' => true,
            'name' => (string) ($channel['channel_name'] ?? 'Live Class'),
            'slug' => $slug,
            'status' => (string) ($channel['status'] ?? 'offline'),
            'course_id' => $liveCourseId,
            'locked' => !$canWatchLive,
            'player_url' => live_base_url() . '/play/' . rawurlencode($slug),
            'token_url' => api_absolute_url('api/live?action=playback_token&stream=' . rawurlencode($slug)),
            'hls_url' => '',
            'expires_in' => 0,
        ];

        if ($includeLinks && $studentId !== null && $canWatchLive) {
            if (!$courseAccess && $planLiveAccess) {
                register_plan_live_access($studentId, 'stream', $sourceId, $liveCourseId);
            }
            $token = live_playback_token($slug, $studentId);
            $selfHosted['hls_url'] = live_hls_url(['playback_slug' => $slug], $token);
            $selfHosted['expires_in'] = 900;
        }

        $startedAt = (string) ($channel['last_started_at'] ?? '');
        $rows[] = [
            'id' => (string) $channel['id'],
            'source_type' => 'stream',
            'class_title' => (string) ($channel['channel_name'] ?? 'Live Class'),
            'class_type' => 'online',
            'class_date' => $startedAt !== '' ? date('Y-m-d', strtotime($startedAt)) : date('Y-m-d'),
            'starts_at' => $startedAt !== '' ? date('H:i:s', strtotime($startedAt)) : '',
            'duration_minutes' => '0',
            'meeting_link' => '',
            'room_name' => '',
            'class_status' => (string) ($channel['status'] ?? 'offline'),
            'notes' => 'Secure self-hosted live stream',
            'batch_name' => 'Self Hosted Live',
            'course_title' => (string) ($channel['course_title'] ?? 'Live Class'),
            'teacher_name' => (string) ($channel['instructor_name'] ?? 'Instructor'),
            'instructor_id' => (string) ($channel['instructor_id'] ?? ''),
            'instructor_name' => (string) ($channel['instructor_name'] ?? 'Instructor'),
            'self_hosted_live' => $selfHosted,
        ];
    }

    usort($rows, static function (array $a, array $b): int {
        $rank = ['live' => 0, 'scheduled' => 1, 'offline' => 2];
        $aRank = $rank[(string) ($a['class_status'] ?? '')] ?? 3;
        $bRank = $rank[(string) ($b['class_status'] ?? '')] ?? 3;
        if ($aRank !== $bRank) {
            return $aRank <=> $bRank;
        }
        return strcmp((string) ($a['class_date'] ?? ''), (string) ($b['class_date'] ?? ''));
    });

    return $rows;
}

function student_live_recording_rows(int $studentId): array
{
    $rows = live_recordings_for_student($studentId);
    foreach ($rows as &$row) {
        $path = trim((string) ($row['file_path'] ?? ''));
        $row['play_url'] = $path !== '' ? api_absolute_url($path) : '';
        $row['duration_label'] = duration_label((int) ($row['duration_seconds'] ?? 0));
        $row['file_size_label'] = file_size_label((int) ($row['file_size'] ?? 0));
    }
    unset($row);
    return $rows;
}

function duration_label(int $seconds): string
{
    if ($seconds <= 0) {
        return '';
    }
    $hours = intdiv($seconds, 3600);
    $minutes = intdiv($seconds % 3600, 60);
    $secs = $seconds % 60;
    if ($hours > 0) {
        return sprintf('%d:%02d:%02d', $hours, $minutes, $secs);
    }
    return sprintf('%d:%02d', $minutes, $secs);
}

function file_size_label(int $bytes): string
{
    if ($bytes <= 0) {
        return '';
    }
    $units = ['B', 'KB', 'MB', 'GB'];
    $size = (float) $bytes;
    $unit = 0;
    while ($size >= 1024 && $unit < count($units) - 1) {
        $size /= 1024;
        $unit++;
    }
    return rtrim(rtrim(number_format($size, 1), '0'), '.') . ' ' . $units[$unit];
}

function live_comment_rows(string $sourceType, int $sourceId): array
{
    if (!in_array($sourceType, ['class', 'batch'], true) || $sourceId <= 0) {
        return [];
    }

    $stmt = db()->prepare("
        SELECT c.id, c.comment_text, c.created_at,
               u.full_name AS student_name, u.profile_photo
        FROM student_live_class_comments c
        INNER JOIN users u ON u.id = c.student_id
        WHERE c.source_type = ? AND c.source_id = ?
        ORDER BY c.id DESC
        LIMIT 80
    ");
    $stmt->bind_param('si', $sourceType, $sourceId);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    foreach ($rows as &$row) {
        $row['profile_photo'] = api_absolute_url((string) ($row['profile_photo'] ?? ''));
    }
    return array_reverse($rows);
}

function exam_rows(?int $studentId = null): array
{
    $result = db()->query("
        SELECT e.id, e.title, e.description, e.duration_minutes, e.exam_type,
               e.total_questions, e.total_marks, e.is_live, e.status,
               c.title AS course_title, ec.category_name AS exam_category_name, u.full_name AS instructor_name
        FROM instructor_exams e
        LEFT JOIN instructor_courses c ON c.id = e.course_id
        LEFT JOIN instructor_exam_categories ec ON ec.id = e.exam_category_id
        LEFT JOIN users u ON u.id = e.instructor_id
        WHERE e.status = 'published'
        ORDER BY e.is_live DESC, e.updated_at DESC, e.id DESC
        LIMIT 30
    ");
    $rows = $result->fetch_all(MYSQLI_ASSOC);
    foreach ($rows as &$row) {
        $row = decorate_exam_thumbnail($row);
        $row['attempt_count'] = 0;
        $row['last_attempt'] = null;
        $row['active_session'] = null;
        if ($studentId !== null && $studentId > 0) {
            $examId = (int) $row['id'];
            $stmt = db()->prepare('SELECT COUNT(*) AS total FROM student_exam_attempts WHERE student_id = ? AND exam_id = ?');
            $stmt->bind_param('ii', $studentId, $examId);
            $stmt->execute();
            $row['attempt_count'] = (int) ($stmt->get_result()->fetch_assoc()['total'] ?? 0);
            $row['last_attempt'] = last_exam_attempt($studentId, $examId);
            $row['active_session'] = active_exam_session($studentId, $examId);
        }
    }
    unset($row);
    return $rows;
}

function exam_detail_row(int $examId): ?array
{
    if ($examId <= 0) {
        return null;
    }
    $stmt = db()->prepare("
        SELECT e.id, e.instructor_id, e.course_id, e.exam_category_id, e.title, e.description, e.duration_minutes,
               e.exam_type, e.total_questions, e.total_marks, e.is_live, e.status,
               c.title AS course_title, ec.category_name AS exam_category_name, u.full_name AS instructor_name
        FROM instructor_exams e
        LEFT JOIN instructor_courses c ON c.id = e.course_id
        LEFT JOIN instructor_exam_categories ec ON ec.id = e.exam_category_id
        LEFT JOIN users u ON u.id = e.instructor_id
        WHERE e.id = ? AND e.status = 'published'
        LIMIT 1
    ");
    $stmt->bind_param('i', $examId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    if (!$row) {
        return null;
    }
    $row = decorate_exam_thumbnail($row);
    return decorate_exam_session($row, null);
}

function decorate_exam_session(array $row, ?int $totalQuestions): array
{
    $answers = [];
    $reviews = [];
    $rawAnswers = trim((string) ($row['answer_json'] ?? ''));
    $rawReviews = trim((string) ($row['review_json'] ?? ''));
    if ($rawAnswers !== '') {
        $decoded = json_decode($rawAnswers, true);
        if (is_array($decoded)) {
            $answers = $decoded;
        }
    }
    if ($rawReviews !== '') {
        $decoded = json_decode($rawReviews, true);
        if (is_array($decoded)) {
            $reviews = $decoded;
        }
    }
    $answered = count(array_filter($answers, static fn($value): bool => trim((string) $value) !== ''));
    $row['answered_count'] = $answered;
    $row['review_count'] = count($reviews);
    if ($totalQuestions !== null) {
        $row['remaining_questions'] = max(0, $totalQuestions - $answered);
    }
    return $row;
}

function complete_course_purchase(int $purchaseId): array
{
    $stmt = db()->prepare('SELECT * FROM student_course_purchases WHERE id = ? LIMIT 1');
    $stmt->bind_param('i', $purchaseId);
    $stmt->execute();
    $purchase = $stmt->get_result()->fetch_assoc();
    if (!$purchase) {
        return [];
    }
    $studentId = (int) $purchase['student_id'];
    $courseId = (int) $purchase['course_id'];

    $stmt = db()->prepare('UPDATE student_course_purchases SET payment_status = "paid" WHERE id = ?');
    $stmt->bind_param('i', $purchaseId);
    $stmt->execute();

    $stmt = db()->prepare('
        INSERT INTO student_course_enrollments (student_id, course_id, purchase_id, status)
        VALUES (?, ?, ?, "active")
        ON DUPLICATE KEY UPDATE purchase_id = VALUES(purchase_id), status = IF(status = "cancelled", "active", status)
    ');
    $stmt->bind_param('iii', $studentId, $courseId, $purchaseId);
    $stmt->execute();

    clear_student_course_plan_revocation($studentId, $courseId);

    $gcoinUsed = (float) ($purchase['gcoin_used'] ?? 0);
    if ($gcoinUsed > 0) {
        gcoin_add_transaction($studentId, 'debit', $gcoinUsed, 'course_purchase', $purchaseId, 'Course purchase discount');
    }

    $stmt = db()->prepare('SELECT referrer_id FROM student_referrals WHERE referred_student_id = ? LIMIT 1');
    $stmt->bind_param('i', $studentId);
    $stmt->execute();
    $refRow = $stmt->get_result()->fetch_assoc();
    $originalAmount = (float) ($purchase['original_amount'] ?? $purchase['amount'] ?? 0);
    if ($refRow && $originalAmount > 0) {
        $rewardCoins = gcoin_purchase_reward($originalAmount);
        if ($rewardCoins > 0) {
            $referrerId = (int) $refRow['referrer_id'];
            gcoin_add_transaction($referrerId, 'credit', $rewardCoins, 'referral_purchase', $purchaseId, 'Referral purchase reward');
            $stmt = db()->prepare('UPDATE student_referrals SET purchase_referrer_coins = purchase_referrer_coins + ? WHERE referred_student_id = ?');
            $stmt->bind_param('di', $rewardCoins, $studentId);
            $stmt->execute();
        }
    }

    $purchase['invoice'] = ensure_invoice_for_purchase('course', $purchaseId);
    email_notify_purchase_event('payment_success', 'course', $purchaseId);
    email_notify_purchase_event('student_enrollment', 'course', $purchaseId);
    return $purchase;
}

function complete_resource_purchase(int $purchaseId): array
{
    $stmt = db()->prepare('SELECT * FROM student_resource_purchases WHERE id = ? LIMIT 1');
    $stmt->bind_param('i', $purchaseId);
    $stmt->execute();
    $purchase = $stmt->get_result()->fetch_assoc();
    if (!$purchase) {
        return [];
    }
    $stmt = db()->prepare('UPDATE student_resource_purchases SET payment_status = "paid" WHERE id = ?');
    $stmt->bind_param('i', $purchaseId);
    $stmt->execute();
    register_pdf_access((int) $purchase['student_id'], (int) $purchase['resource_id'], (int) $purchase['course_id']);
    $purchase['invoice'] = ensure_invoice_for_purchase('resource', $purchaseId);
    email_notify_purchase_event('payment_success', 'resource', $purchaseId);
    email_notify_purchase_event('student_enrollment', 'resource', $purchaseId);
    return $purchase;
}

function complete_membership_purchase(int $purchaseId): array
{
    $stmt = db()->prepare('
        SELECT mp.*, p.validity_days AS plan_validity_days
        FROM student_membership_purchases mp
        INNER JOIN student_membership_plans p ON p.id = mp.plan_id
        WHERE mp.id = ? AND p.is_active = 1
        LIMIT 1
    ');
    $stmt->bind_param('i', $purchaseId);
    $stmt->execute();
    $purchase = $stmt->get_result()->fetch_assoc();
    if (!$purchase) {
        return [];
    }

    $studentId = (int) $purchase['student_id'];
    $planId = (int) $purchase['plan_id'];
    $validityDays = max(1, (int) ($purchase['validity_days'] ?? $purchase['plan_validity_days'] ?? 30));

    $stmt = db()->prepare('UPDATE student_membership_purchases SET payment_status = "paid" WHERE id = ?');
    $stmt->bind_param('i', $purchaseId);
    $stmt->execute();

    $stmt = db()->prepare("UPDATE student_plan_subscriptions SET status = 'expired' WHERE student_id = ? AND status = 'active' AND ends_at < NOW()");
    $stmt->bind_param('i', $studentId);
    $stmt->execute();

    $stmt = db()->prepare("UPDATE student_plan_subscriptions SET status = 'expired' WHERE student_id = ? AND status = 'active' AND ends_at >= NOW()");
    $stmt->bind_param('i', $studentId);
    $stmt->execute();

    $stmt = db()->prepare("
        INSERT INTO student_plan_subscriptions (student_id, plan_id, starts_at, ends_at, status, purchase_id)
        VALUES (?, ?, NOW(), DATE_ADD(NOW(), INTERVAL ? DAY), 'active', ?)
    ");
    $stmt->bind_param('iiii', $studentId, $planId, $validityDays, $purchaseId);
    $stmt->execute();

    $purchase['invoice'] = ensure_invoice_for_purchase('membership', $purchaseId);
    email_notify_purchase_event('payment_success', 'membership', $purchaseId);
    email_notify_purchase_event('membership_activated', 'membership', $purchaseId);
    return $purchase;
}

function reverse_purchase_activation(string $sourceType, int $purchaseId, string $nextStatus): void
{
    if ($purchaseId <= 0) {
        return;
    }
    $sourceType = $sourceType === 'pdf' ? 'resource' : $sourceType;
    $nextStatus = in_array($nextStatus, ['pending', 'failed'], true) ? $nextStatus : 'pending';

    if ($sourceType === 'course') {
        $stmt = db()->prepare('UPDATE student_course_purchases SET payment_status = ? WHERE id = ?');
        $stmt->bind_param('si', $nextStatus, $purchaseId);
        $stmt->execute();

        $stmt = db()->prepare('UPDATE student_course_enrollments SET status = "cancelled" WHERE purchase_id = ?');
        $stmt->bind_param('i', $purchaseId);
        $stmt->execute();
    } elseif ($sourceType === 'resource') {
        $stmt = db()->prepare('SELECT student_id, resource_id FROM student_resource_purchases WHERE id = ? LIMIT 1');
        $stmt->bind_param('i', $purchaseId);
        $stmt->execute();
        $purchase = $stmt->get_result()->fetch_assoc();

        $stmt = db()->prepare('UPDATE student_resource_purchases SET payment_status = ? WHERE id = ?');
        $stmt->bind_param('si', $nextStatus, $purchaseId);
        $stmt->execute();

        if ($purchase) {
            $studentId = (int) $purchase['student_id'];
            $resourceId = (int) $purchase['resource_id'];
            $stmt = db()->prepare('
                SELECT id
                FROM student_resource_purchases
                WHERE student_id = ? AND resource_id = ? AND payment_status = "paid" AND id <> ?
                LIMIT 1
            ');
            $stmt->bind_param('iii', $studentId, $resourceId, $purchaseId);
            $stmt->execute();
            if (!$stmt->get_result()->fetch_assoc()) {
                $stmt = db()->prepare('DELETE FROM student_resource_access WHERE student_id = ? AND resource_id = ?');
                $stmt->bind_param('ii', $studentId, $resourceId);
                $stmt->execute();
            }
        }
    } elseif ($sourceType === 'membership') {
        $stmt = db()->prepare('UPDATE student_membership_purchases SET payment_status = ? WHERE id = ?');
        $stmt->bind_param('si', $nextStatus, $purchaseId);
        $stmt->execute();

        $stmt = db()->prepare('UPDATE student_plan_subscriptions SET status = "cancelled" WHERE purchase_id = ? AND status = "active"');
        $stmt->bind_param('i', $purchaseId);
        $stmt->execute();
    }

    $invoiceStatus = $nextStatus === 'failed' ? 'cancelled' : 'cancelled';
    $stmt = db()->prepare('UPDATE student_invoices SET invoice_status = ? WHERE source_type = ? AND source_id = ? AND invoice_status = "paid"');
    $stmt->bind_param('ssi', $invoiceStatus, $sourceType, $purchaseId);
    $stmt->execute();
}

function complete_pending_payment_by_txn(string $txn, bool $markFailed = false, bool $markPaid = false): array
{
    $txn = trim($txn);
    if ($txn === '') {
        return ['found' => false];
    }
    $stmt = db()->prepare('SELECT id, payment_status FROM student_course_purchases WHERE transaction_no = ? LIMIT 1');
    $stmt->bind_param('s', $txn);
    $stmt->execute();
    $course = $stmt->get_result()->fetch_assoc();
    if ($course) {
        $purchase = [];
        if ($markFailed) {
            $id = (int) $course['id'];
            reverse_purchase_activation('course', $id, 'failed');
            email_notify_purchase_event('payment_failed', 'course', $id);
        } elseif ($markPaid && $course['payment_status'] !== 'paid') {
            $purchase = complete_course_purchase((int) $course['id']);
        } else {
            if (!$markPaid && ($course['payment_status'] ?? '') === 'paid') {
                reverse_purchase_activation('course', (int) $course['id'], 'pending');
                $course['payment_status'] = 'pending';
            }
            $purchase = $course['payment_status'] === 'paid'
                ? ['invoice' => ensure_invoice_for_purchase('course', (int) $course['id'])]
                : [];
        }
        return ['found' => true, 'type' => 'course', 'id' => (int) $course['id'], 'status' => $markFailed ? 'failed' : ($course['payment_status'] ?? 'pending'), 'invoice' => $purchase['invoice'] ?? null];
    }

    $stmt = db()->prepare('SELECT id, payment_status FROM student_resource_purchases WHERE transaction_no = ? LIMIT 1');
    $stmt->bind_param('s', $txn);
    $stmt->execute();
    $resource = $stmt->get_result()->fetch_assoc();
    if ($resource) {
        $purchase = [];
        if ($markFailed) {
            $id = (int) $resource['id'];
            reverse_purchase_activation('resource', $id, 'failed');
            email_notify_purchase_event('payment_failed', 'resource', $id);
        } elseif ($markPaid && $resource['payment_status'] !== 'paid') {
            $purchase = complete_resource_purchase((int) $resource['id']);
        } else {
            if (!$markPaid && ($resource['payment_status'] ?? '') === 'paid') {
                reverse_purchase_activation('resource', (int) $resource['id'], 'pending');
                $resource['payment_status'] = 'pending';
            }
            $purchase = $resource['payment_status'] === 'paid'
                ? ['invoice' => ensure_invoice_for_purchase('resource', (int) $resource['id'])]
                : [];
        }
        return ['found' => true, 'type' => 'resource', 'id' => (int) $resource['id'], 'status' => $markFailed ? 'failed' : ($resource['payment_status'] ?? 'pending'), 'invoice' => $purchase['invoice'] ?? null];
    }

    $stmt = db()->prepare('SELECT id, payment_status FROM student_membership_purchases WHERE transaction_no = ? LIMIT 1');
    $stmt->bind_param('s', $txn);
    $stmt->execute();
    $membership = $stmt->get_result()->fetch_assoc();
    if ($membership) {
        $purchase = [];
        if ($markFailed) {
            $id = (int) $membership['id'];
            reverse_purchase_activation('membership', $id, 'failed');
            email_notify_purchase_event('payment_failed', 'membership', $id);
        } elseif ($markPaid && $membership['payment_status'] !== 'paid') {
            $purchase = complete_membership_purchase((int) $membership['id']);
        } else {
            if (!$markPaid && ($membership['payment_status'] ?? '') === 'paid') {
                reverse_purchase_activation('membership', (int) $membership['id'], 'pending');
                $membership['payment_status'] = 'pending';
            }
            $purchase = $membership['payment_status'] === 'paid'
                ? ['invoice' => ensure_invoice_for_purchase('membership', (int) $membership['id'])]
                : [];
        }
        return ['found' => true, 'type' => 'membership', 'id' => (int) $membership['id'], 'status' => $markFailed ? 'failed' : ($membership['payment_status'] ?? 'pending'), 'invoice' => $purchase['invoice'] ?? null];
    }

    return ['found' => false];
}

function retry_payment_by_txn(string $txn, int $studentId, bool $appReturn = false): array
{
    $txn = trim($txn);
    if ($txn === '' || $studentId <= 0) {
        api_out(['success' => false, 'message' => 'Transaction missing.'], 422);
    }

    $sources = [
        ['table' => 'student_membership_purchases', 'prefix' => 'PL', 'kind' => 'membership'],
        ['table' => 'student_course_purchases', 'prefix' => 'CP', 'kind' => 'course'],
        ['table' => 'student_resource_purchases', 'prefix' => 'PDF', 'kind' => 'pdf'],
    ];

    foreach ($sources as $source) {
        $table = $source['table'];
        $stmt = db()->prepare("SELECT id, amount, payment_status FROM {$table} WHERE transaction_no = ? AND student_id = ? LIMIT 1");
        $stmt->bind_param('si', $txn, $studentId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        if (!$row) {
            continue;
        }

        if (($row['payment_status'] ?? '') === 'paid') {
            api_out(['success' => false, 'message' => 'Payment already successful.'], 409);
        }

        $amount = max(0, (float) ($row['amount'] ?? 0));
        if ($amount <= 0) {
            if ($source['kind'] === 'membership') {
                $purchase = complete_membership_purchase((int) $row['id']);
            } elseif ($source['kind'] === 'course') {
                $purchase = complete_course_purchase((int) $row['id']);
            } else {
                $purchase = complete_resource_purchase((int) $row['id']);
            }
            return ['payment_required' => false, 'purchase' => $purchase];
        }

        if (!phonepe_enabled()) {
            api_out(['success' => false, 'message' => 'PhonePe payment gateway is not configured. Please contact admin.'], 422);
        }
        require_student_billing_info($studentId);

        $newTxn = $source['prefix'] . '-' . date('ymdHis') . '-' . random_int(1000, 9999);
        $stmt = db()->prepare("UPDATE {$table} SET payment_status = 'pending', payment_method = 'phonepe', transaction_no = ? WHERE id = ? AND student_id = ?");
        $id = (int) $row['id'];
        $stmt->bind_param('sii', $newTxn, $id, $studentId);
        $stmt->execute();

        try {
            $payment = phonepe_create_payment($newTxn, $studentId, $amount, $source['kind'], $appReturn);
        } catch (Throwable $e) {
            mark_payment_start_failed($table, $id);
            $emailType = $source['kind'] === 'pdf' ? 'resource' : $source['kind'];
            email_notify_purchase_event('payment_failed', $emailType, $id);
            api_out(['success' => false, 'message' => 'PhonePe payment start failed: ' . $e->getMessage()], 422);
        }

        return [
            'payment_required' => true,
            'transaction_no' => $newTxn,
            'amount' => $amount,
            'type' => $source['kind'],
            'payment' => $payment,
        ];
    }

    api_out(['success' => false, 'message' => 'Transaction record not found.'], 404);
}

function exam_question_rows(array $exam, bool $withCorrect = false): array
{
    $examId = (int) $exam['id'];
    $instructorId = (int) $exam['instructor_id'];
    $rows = [];

    if (($exam['exam_type'] ?? 'manual') === 'random') {
        $stmt = db()->prepare('
            SELECT content_id, question_limit, only_active
            FROM instructor_exam_random_rules
            WHERE exam_id = ? AND instructor_id = ?
            ORDER BY id ASC
        ');
        $stmt->bind_param('ii', $examId, $instructorId);
        $stmt->execute();
        $rules = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        if (!$rules) {
            $rules = [[
                'content_id' => 0,
                'question_limit' => (int) ($exam['total_questions'] ?? 20),
                'only_active' => 1,
            ]];
        }
        $seen = [];
        foreach ($rules as $rule) {
            $limit = max(1, min(200, (int) ($rule['question_limit'] ?? 0)));
            $courseId = (int) ($exam['course_id'] ?? 0);
            $contentId = (int) ($rule['content_id'] ?? 0);
            $onlyActive = (int) ($rule['only_active'] ?? 1) === 1;

            $where = ['q.instructor_id = ?'];
            $types = 'i';
            $params = [$instructorId];
            if ($courseId > 0) {
                $where[] = 'q.course_id = ?';
                $types .= 'i';
                $params[] = $courseId;
            }
            if ($contentId > 0) {
                $where[] = 'q.content_id = ?';
                $types .= 'i';
                $params[] = $contentId;
            }
            if ($seen) {
                $where[] = 'q.id NOT IN (' . implode(',', array_map('intval', array_keys($seen))) . ')';
            }
            if ($onlyActive) {
                $where[] = "q.status = 'active'";
            }
            $sql = 'SELECT q.id, q.q_type, q.question_en, q.question_hi, q.option_a_en, q.option_a_hi,
                           q.option_b_en, q.option_b_hi, q.option_c_en, q.option_c_hi,
                           q.option_d_en, q.option_d_hi, q.correct_key, q.marks, q.solution
                    FROM instructor_questions q
                    WHERE ' . implode(' AND ', $where) . '
                    ORDER BY RAND()
                    LIMIT ' . $limit;
            $ruleStmt = db()->prepare($sql);
            mysqli_bind_dynamic($ruleStmt, $types, $params);
            $ruleStmt->execute();
            foreach ($ruleStmt->get_result()->fetch_all(MYSQLI_ASSOC) as $row) {
                $seen[(int) $row['id']] = true;
                $rows[] = $row;
            }
        }
    } else {
        $stmt = db()->prepare("
            SELECT q.id, q.q_type, q.question_en, q.question_hi, q.option_a_en, q.option_a_hi,
                   q.option_b_en, q.option_b_hi, q.option_c_en, q.option_c_hi,
                   q.option_d_en, q.option_d_hi, q.correct_key, eq.marks, q.solution
            FROM instructor_exam_questions eq
            INNER JOIN instructor_questions q ON q.id = eq.question_id
            WHERE eq.exam_id = ? AND eq.instructor_id = ?
            ORDER BY eq.sort_order ASC, eq.id ASC
        ");
        $stmt->bind_param('ii', $examId, $instructorId);
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    return format_exam_question_rows($rows, $withCorrect);
}

function exam_session_question_rows(array $exam, ?array $session, bool $withCorrect = false): array
{
    $raw = trim((string) ($session['question_json'] ?? ''));
    $targetCount = max(1, (int) ($exam['total_questions'] ?? 20));
    if ($raw !== '') {
        $ids = json_decode($raw, true);
        if (is_array($ids)) {
            $ids = array_values(array_filter(array_map('intval', $ids), static fn(int $id): bool => $id > 0));
            if ($ids) {
                $rows = exam_question_rows_by_ids($exam, $ids, $withCorrect);
                if (count($rows) >= $targetCount) {
                    return $rows;
                }
                $existing = array_flip(array_map(static fn(array $row): int => (int) $row['id'], $rows));
                foreach (exam_question_rows($exam, $withCorrect) as $row) {
                    $id = (int) $row['id'];
                    if (!isset($existing[$id])) {
                        $rows[] = $row;
                        $existing[$id] = true;
                    }
                    if (count($rows) >= $targetCount) {
                        break;
                    }
                }
                if ($rows) {
                    return array_slice($rows, 0, $targetCount);
                }
            }
        }
    }
    $answerRaw = trim((string) ($session['answer_json'] ?? ''));
    if ($answerRaw !== '') {
        $answers = json_decode($answerRaw, true);
        if (is_array($answers)) {
            $answeredIds = array_values(array_filter(array_map('intval', array_keys($answers)), static fn(int $id): bool => $id > 0));
            if ($answeredIds) {
                $rows = exam_question_rows_by_ids($exam, $answeredIds, $withCorrect);
                $existing = array_flip(array_map(static fn(array $row): int => (int) $row['id'], $rows));
                foreach (exam_question_rows($exam, $withCorrect) as $row) {
                    $id = (int) $row['id'];
                    if (!isset($existing[$id])) {
                        $rows[] = $row;
                        $existing[$id] = true;
                    }
                    if (count($rows) >= $targetCount) {
                        break;
                    }
                }
                if ($rows) {
                    return $rows;
                }
            }
        }
    }
    return exam_question_rows($exam, $withCorrect);
}

function exam_question_rows_by_ids(array $exam, array $ids, bool $withCorrect = false): array
{
    $ids = array_values(array_unique(array_filter(array_map('intval', $ids), static fn(int $id): bool => $id > 0)));
    if (!$ids) {
        return [];
    }
    $examId = (int) $exam['id'];
    $instructorId = (int) $exam['instructor_id'];
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $types = str_repeat('i', count($ids));
    $order = implode(',', $ids);
    if (($exam['exam_type'] ?? 'manual') === 'random') {
        $sql = "
            SELECT q.id, q.q_type, q.question_en, q.question_hi, q.option_a_en, q.option_a_hi,
                   q.option_b_en, q.option_b_hi, q.option_c_en, q.option_c_hi,
                   q.option_d_en, q.option_d_hi, q.correct_key, q.marks, q.solution
            FROM instructor_questions q
            WHERE q.instructor_id = ? AND q.id IN ($placeholders)
            ORDER BY FIELD(q.id, $order)
        ";
        $stmt = db()->prepare($sql);
        mysqli_bind_dynamic($stmt, 'i' . $types, array_merge([$instructorId], $ids));
    } else {
        $sql = "
            SELECT q.id, q.q_type, q.question_en, q.question_hi, q.option_a_en, q.option_a_hi,
                   q.option_b_en, q.option_b_hi, q.option_c_en, q.option_c_hi,
                   q.option_d_en, q.option_d_hi, q.correct_key, eq.marks, q.solution
            FROM instructor_exam_questions eq
            INNER JOIN instructor_questions q ON q.id = eq.question_id
            WHERE eq.exam_id = ? AND eq.instructor_id = ? AND q.id IN ($placeholders)
            ORDER BY FIELD(q.id, $order)
        ";
        $stmt = db()->prepare($sql);
        mysqli_bind_dynamic($stmt, 'ii' . $types, array_merge([$examId, $instructorId], $ids));
    }
    $stmt->execute();
    return format_exam_question_rows($stmt->get_result()->fetch_all(MYSQLI_ASSOC), $withCorrect);
}

function format_exam_question_rows(array $rows, bool $withCorrect = false): array
{
    return array_map(static function (array $row) use ($withCorrect): array {
        $item = [
            'id' => (int) $row['id'],
            'q_type' => $row['q_type'],
            'question' => trim((string) ($row['question_en'] ?: $row['question_hi'] ?: 'Question')),
            'question_en' => (string) ($row['question_en'] ?? ''),
            'question_hi' => (string) ($row['question_hi'] ?? ''),
            'marks' => (float) $row['marks'],
            'options' => [
                ['key' => 'A', 'text' => (string) ($row['option_a_en'] ?: $row['option_a_hi'] ?: 'Option A'), 'text_en' => (string) ($row['option_a_en'] ?? ''), 'text_hi' => (string) ($row['option_a_hi'] ?? '')],
                ['key' => 'B', 'text' => (string) ($row['option_b_en'] ?: $row['option_b_hi'] ?: 'Option B'), 'text_en' => (string) ($row['option_b_en'] ?? ''), 'text_hi' => (string) ($row['option_b_hi'] ?? '')],
                ['key' => 'C', 'text' => (string) ($row['option_c_en'] ?: $row['option_c_hi'] ?: 'Option C'), 'text_en' => (string) ($row['option_c_en'] ?? ''), 'text_hi' => (string) ($row['option_c_hi'] ?? '')],
                ['key' => 'D', 'text' => (string) ($row['option_d_en'] ?: $row['option_d_hi'] ?: 'Option D'), 'text_en' => (string) ($row['option_d_en'] ?? ''), 'text_hi' => (string) ($row['option_d_hi'] ?? '')],
            ],
        ];
        if ($row['q_type'] === 'TF') {
            $item['options'] = [
                ['key' => 'TRUE', 'text' => 'True'],
                ['key' => 'FALSE', 'text' => 'False'],
            ];
        }
        if ($withCorrect) {
            $item['correct_key'] = (string) $row['correct_key'];
            $item['solution'] = (string) ($row['solution'] ?? '');
        }
        return $item;
    }, $rows);
}

function last_exam_attempt(int $studentId, int $examId): ?array
{
    $stmt = db()->prepare('
        SELECT id, score, total_marks, total_questions, correct_count, wrong_count,
               skipped_count, percentage, submitted_at
        FROM student_exam_attempts
        WHERE student_id = ? AND exam_id = ?
        ORDER BY id DESC
        LIMIT 1
    ');
    $stmt->bind_param('ii', $studentId, $examId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    return $row ?: null;
}

function exam_attempt_rows(int $studentId, int $examId): array
{
    $stmt = db()->prepare('
        SELECT id, score, total_marks, total_questions, correct_count, wrong_count,
               skipped_count, percentage, started_at, submitted_at
        FROM student_exam_attempts
        WHERE student_id = ? AND exam_id = ?
        ORDER BY id DESC
        LIMIT 25
    ');
    $stmt->bind_param('ii', $studentId, $examId);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    foreach ($rows as &$row) {
        $row = decorate_exam_session($row, (int) ($row['total_questions'] ?? 0));
    }
    unset($row);
    return $rows;
}

function exam_attempt_detail(int $studentId, int $attemptId): ?array
{
    if ($attemptId <= 0) {
        return null;
    }
    $stmt = db()->prepare('
        SELECT a.id, a.exam_id, a.score, a.total_marks, a.total_questions,
               a.correct_count, a.wrong_count, a.skipped_count, a.percentage,
               a.started_at, a.submitted_at,
               e.title, e.description, e.duration_minutes, e.exam_type,
               e.is_live, c.title AS course_title, u.full_name AS instructor_name
        FROM student_exam_attempts a
        INNER JOIN instructor_exams e ON e.id = a.exam_id
        LEFT JOIN instructor_courses c ON c.id = e.course_id
        LEFT JOIN users u ON u.id = e.instructor_id
        WHERE a.id = ? AND a.student_id = ?
        LIMIT 1
    ');
    $stmt->bind_param('ii', $attemptId, $studentId);
    $stmt->execute();
    $attempt = $stmt->get_result()->fetch_assoc();
    if (!$attempt) {
        return null;
    }

    $stmt = db()->prepare('
        SELECT q.id, q.q_type, q.question_en, q.question_hi, q.option_a_en, q.option_a_hi,
               q.option_b_en, q.option_b_hi, q.option_c_en, q.option_c_hi,
               q.option_d_en, q.option_d_hi, aa.correct_key, aa.marks, q.solution,
               aa.selected_key, aa.is_correct, aa.earned_marks
        FROM student_exam_attempt_answers aa
        INNER JOIN instructor_questions q ON q.id = aa.question_id
        WHERE aa.attempt_id = ?
        ORDER BY aa.id ASC
    ');
    $stmt->bind_param('i', $attemptId);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $questions = format_exam_question_rows($rows, true);
    foreach ($questions as $index => &$question) {
        $source = $rows[$index] ?? [];
        $question['selected_key'] = (string) ($source['selected_key'] ?? '');
        $question['is_correct'] = (int) ($source['is_correct'] ?? 0);
        $question['earned_marks'] = (float) ($source['earned_marks'] ?? 0);
    }
    unset($question);

    return [
        'attempt' => $attempt,
        'exam' => [
            'id' => (int) $attempt['exam_id'],
            'title' => $attempt['title'],
            'description' => $attempt['description'],
            'duration_minutes' => (int) $attempt['duration_minutes'],
            'exam_type' => $attempt['exam_type'],
            'is_live' => (int) $attempt['is_live'],
            'course_title' => $attempt['course_title'],
            'instructor_name' => $attempt['instructor_name'],
        ],
        'questions' => $questions,
    ];
}

function active_exam_session(int $studentId, int $examId): ?array
{
    db()->query('
        UPDATE student_exam_sessions
        SET status = "expired"
        WHERE status = "active" AND expires_at IS NOT NULL AND expires_at < NOW()
    ');
    $stmt = db()->prepare('
        SELECT exam_id AS id, started_at, last_seen_at, expires_at,
               GREATEST(0, TIMESTAMPDIFF(SECOND, NOW(), expires_at)) AS remaining_seconds,
               answer_json, review_json, question_json, current_question
        FROM student_exam_sessions
        WHERE student_id = ? AND exam_id = ? AND status = "active"
        LIMIT 1
    ');
    $stmt->bind_param('ii', $studentId, $examId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    return $row ? decorate_exam_session($row, null) : null;
}

function save_exam_session_questions(int $studentId, int $examId, array $questionIds): void
{
    $ids = array_values(array_filter(array_map('intval', $questionIds), static fn(int $id): bool => $id > 0));
    $questionJson = json_encode($ids, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    $stmt = db()->prepare('
        UPDATE student_exam_sessions
        SET question_json = ?
        WHERE student_id = ? AND exam_id = ? AND status = "active"
    ');
    $stmt->bind_param('sii', $questionJson, $studentId, $examId);
    $stmt->execute();
}

function save_exam_draft(int $studentId, int $examId, array $answers, array $reviewQuestions, int $currentQuestion): void
{
    $cleanAnswers = [];
    foreach ($answers as $questionId => $selected) {
        $qid = (int) $questionId;
        $key = strtoupper(substr(trim((string) $selected), 0, 10));
        if ($qid > 0 && $key !== '') {
            $cleanAnswers[(string) $qid] = $key;
        }
    }
    $cleanReview = [];
    foreach ($reviewQuestions as $questionId) {
        $qid = (int) $questionId;
        if ($qid > 0) {
            $cleanReview[] = $qid;
        }
    }
    $cleanReview = array_values(array_unique($cleanReview));
    $answerJson = json_encode($cleanAnswers, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    $reviewJson = json_encode($cleanReview, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    $stmt = db()->prepare('
        UPDATE student_exam_sessions
        SET answer_json = ?, review_json = ?, current_question = ?, last_seen_at = NOW()
        WHERE student_id = ? AND exam_id = ? AND status = "active"
    ');
    $stmt->bind_param('ssiii', $answerJson, $reviewJson, $currentQuestion, $studentId, $examId);
    $stmt->execute();
}

function start_exam_session(int $studentId, array $exam): void
{
    $examId = (int) $exam['id'];
    $now = date('Y-m-d H:i:s');
    $duration = max(1, (int) ($exam['duration_minutes'] ?? 60));
    $expiresAt = date('Y-m-d H:i:s', time() + ($duration * 60));
    $stmt = db()->prepare('
        INSERT INTO student_exam_sessions (student_id, exam_id, started_at, last_seen_at, expires_at, status)
        VALUES (?, ?, ?, ?, ?, "active")
        ON DUPLICATE KEY UPDATE
            answer_json = IF(status IN ("completed", "expired"), NULL, answer_json),
            review_json = IF(status IN ("completed", "expired"), NULL, review_json),
            question_json = IF(status IN ("completed", "expired"), NULL, question_json),
            current_question = IF(status IN ("completed", "expired"), 0, current_question),
            started_at = IF(status IN ("completed", "expired"), VALUES(started_at), started_at),
            expires_at = IF(status IN ("completed", "expired"), VALUES(expires_at), expires_at),
            last_seen_at = VALUES(last_seen_at),
            status = "active"
    ');
    $stmt->bind_param('iisss', $studentId, $examId, $now, $now, $expiresAt);
    $stmt->execute();
}

function complete_exam_session(int $studentId, int $examId): void
{
    $now = date('Y-m-d H:i:s');
    $stmt = db()->prepare('
        UPDATE student_exam_sessions
        SET status = "completed", last_seen_at = ?
        WHERE student_id = ? AND exam_id = ?
    ');
    $stmt->bind_param('sii', $now, $studentId, $examId);
    $stmt->execute();
}

function active_exam_sessions(int $studentId): array
{
    db()->query('
        UPDATE student_exam_sessions
        SET status = "expired"
        WHERE status = "active" AND expires_at IS NOT NULL AND expires_at < NOW()
    ');
    $stmt = db()->prepare("
        SELECT s.exam_id AS id, s.started_at, s.last_seen_at, s.expires_at,
               GREATEST(0, TIMESTAMPDIFF(SECOND, NOW(), s.expires_at)) AS remaining_seconds,
               s.answer_json, s.review_json, s.question_json, s.current_question,
               e.title, e.description, e.duration_minutes, e.exam_type,
               e.total_questions, e.total_marks, e.is_live,
               c.title AS course_title, u.full_name AS instructor_name
        FROM student_exam_sessions s
        INNER JOIN instructor_exams e ON e.id = s.exam_id
        LEFT JOIN instructor_courses c ON c.id = e.course_id
        LEFT JOIN users u ON u.id = e.instructor_id
        WHERE s.student_id = ? AND s.status = 'active' AND e.status = 'published'
        ORDER BY s.last_seen_at DESC
        LIMIT 10
    ");
    $stmt->bind_param('i', $studentId);
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

function mysqli_bind_dynamic(mysqli_stmt $stmt, string $types, array $params): void
{
    $refs = [$types];
    foreach ($params as $key => $value) {
        $params[$key] = $value;
        $refs[] = &$params[$key];
    }
    $stmt->bind_param(...$refs);
}

function save_exam_attempt(int $studentId, array $exam, array $answers, ?array $questions = null): array
{
    $questions = $questions ?? exam_question_rows($exam, true);
    if (!$questions) {
        api_out(['success' => false, 'message' => 'No questions available for this test.'], 422);
    }

    $answerMap = [];
    foreach ($answers as $questionId => $selected) {
        $answerMap[(int) $questionId] = strtoupper(trim((string) $selected));
    }

    $totalQuestions = count($questions);
    $totalMarks = 0.0;
    $score = 0.0;
    $correct = 0;
    $wrong = 0;
    $skipped = 0;
    $now = date('Y-m-d H:i:s');

    db()->begin_transaction();
    try {
        $stmt = db()->prepare('
            INSERT INTO student_exam_attempts
                (student_id, exam_id, score, total_marks, total_questions, correct_count, wrong_count, skipped_count, percentage, started_at, submitted_at)
            VALUES (?, ?, 0, 0, ?, 0, 0, 0, 0, ?, ?)
        ');
        $examId = (int) $exam['id'];
        $stmt->bind_param('iiiss', $studentId, $examId, $totalQuestions, $now, $now);
        $stmt->execute();
        $attemptId = (int) db()->insert_id;

        $insert = db()->prepare('
            INSERT INTO student_exam_attempt_answers
                (attempt_id, question_id, selected_key, correct_key, is_correct, marks, earned_marks)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ');

        foreach ($questions as $question) {
            $qid = (int) $question['id'];
            $marks = (float) $question['marks'];
            $totalMarks += $marks;
            $selected = $answerMap[$qid] ?? '';
            $correctKey = strtoupper((string) $question['correct_key']);
            $isCorrect = $selected !== '' && $selected === $correctKey;
            $earned = $isCorrect ? $marks : 0.0;
            if ($selected === '') {
                $skipped++;
                $selectedValue = null;
            } elseif ($isCorrect) {
                $correct++;
                $selectedValue = $selected;
            } else {
                $wrong++;
                $selectedValue = $selected;
            }
            $score += $earned;
            $isCorrectInt = $isCorrect ? 1 : 0;
            $insert->bind_param('iissidd', $attemptId, $qid, $selectedValue, $correctKey, $isCorrectInt, $marks, $earned);
            $insert->execute();
        }

        $percentage = $totalMarks > 0 ? round(($score / $totalMarks) * 100, 2) : 0.0;
        $stmt = db()->prepare('
            UPDATE student_exam_attempts
            SET score = ?, total_marks = ?, correct_count = ?, wrong_count = ?, skipped_count = ?, percentage = ?
            WHERE id = ?
        ');
        $stmt->bind_param('ddiiidi', $score, $totalMarks, $correct, $wrong, $skipped, $percentage, $attemptId);
        $stmt->execute();
        db()->commit();
    } catch (Throwable $e) {
        db()->rollback();
        throw $e;
    }

    return [
        'attempt_id' => $attemptId,
        'score' => $score,
        'total_marks' => $totalMarks,
        'total_questions' => $totalQuestions,
        'correct_count' => $correct,
        'wrong_count' => $wrong,
        'skipped_count' => $skipped,
        'percentage' => $percentage,
        'submitted_at' => $now,
    ];
}
