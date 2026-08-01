<?php
declare(strict_types=1);

if (!function_exists('email_setting')) {
    function email_setting(string $key, string $fallback = ''): string
    {
        if (function_exists('app_setting')) {
            return app_setting($key, $fallback);
        }

        try {
            $stmt = db()->prepare('SELECT setting_value FROM system_settings WHERE setting_key = ? LIMIT 1');
            $stmt->bind_param('s', $key);
            $stmt->execute();
            $row = $stmt->get_result()->fetch_assoc();
            return (string) ($row['setting_value'] ?? $fallback);
        } catch (Throwable $e) {
            return $fallback;
        }
    }
}

if (!function_exists('email_base_url')) {
    function email_base_url(): string
    {
        if (function_exists('app_url')) {
            $path = app_url('');
            $host = (string) ($_SERVER['HTTP_HOST'] ?? '');
            $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
            if ($host !== '' && !preg_match('#^https?://#i', $path)) {
                return rtrim($scheme . '://' . $host . $path, '/');
            }
            return rtrim($path, '/');
        }

        $host = (string) ($_SERVER['HTTP_HOST'] ?? 'localhost');
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        return rtrim($scheme . '://' . $host, '/');
    }
}

if (!function_exists('email_render_template')) {
    function email_render_template(string $template, array $vars): string
    {
        $defaults = [
            'site_name' => email_setting('site_name', defined('APP_NAME') ? APP_NAME : 'Gyan Rank'),
            'site_url' => email_base_url(),
            'login_url' => email_base_url() . '/#/login',
            'signup_url' => email_base_url() . '/#/signup',
            'support_email' => email_setting('support_email', email_setting('site_email', '')),
        ];
        $vars = array_merge($defaults, $vars);
        $replace = [];
        foreach ($vars as $key => $value) {
            if (is_scalar($value) || $value === null) {
                $replace['{' . $key . '}'] = (string) $value;
            }
        }
        return strtr($template, $replace);
    }
}

if (!function_exists('email_text_from_html')) {
    function email_text_from_html(string $html): string
    {
        $html = preg_replace('#<br\s*/?>#i', "\n", $html) ?? $html;
        $html = preg_replace('#</p\s*>#i', "\n\n", $html) ?? $html;
        return trim(html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    }
}

if (!function_exists('email_layout')) {
    function email_layout(string $subject, string $body, array $vars = []): string
    {
        $siteName = htmlspecialchars(email_setting('site_name', 'Gyan Rank'), ENT_QUOTES, 'UTF-8');
        $header = email_render_template(email_setting('email_template_header', 'Welcome to {site_name}'), $vars);
        $footer = email_render_template(email_setting('email_template_footer', 'Regards, {site_name}'), $vars);
        $safeSubject = htmlspecialchars($subject, ENT_QUOTES, 'UTF-8');

        return '<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">'
            . '<title>' . $safeSubject . '</title></head>'
            . '<body style="margin:0;background:#f4f7fb;font-family:Arial,Helvetica,sans-serif;color:#0f172a;">'
            . '<div style="max-width:680px;margin:0 auto;padding:28px 16px;">'
            . '<div style="background:#0f172a;color:#fff;border-radius:18px 18px 0 0;padding:22px 26px;">'
            . '<div style="font-size:22px;font-weight:800;">' . $siteName . '</div>'
            . '<div style="margin-top:6px;color:#cbd5e1;font-size:14px;">' . $header . '</div>'
            . '</div>'
            . '<div style="background:#fff;border:1px solid #dbe5f3;border-top:0;padding:28px 26px;line-height:1.65;font-size:15px;">'
            . $body
            . '</div>'
            . '<div style="background:#fff;border:1px solid #dbe5f3;border-top:0;border-radius:0 0 18px 18px;padding:18px 26px;color:#64748b;font-size:13px;">'
            . $footer
            . '</div></div></body></html>';
    }
}

if (!function_exists('email_ensure_log_table')) {
    function email_ensure_log_table(): void
    {
        db()->query("
            CREATE TABLE IF NOT EXISTS email_activity_logs (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                event_key VARCHAR(80) NOT NULL,
                recipient_email VARCHAR(190) NOT NULL,
                subject VARCHAR(255) NOT NULL,
                status VARCHAR(30) NOT NULL DEFAULT 'queued',
                error_message TEXT NULL,
                meta_json LONGTEXT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                KEY email_activity_logs_event_index (event_key),
                KEY email_activity_logs_recipient_index (recipient_email),
                KEY email_activity_logs_status_index (status)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
    }
}

if (!function_exists('email_log_event')) {
    function email_log_event(string $event, string $to, string $subject, string $status, string $error = '', array $meta = []): void
    {
        try {
            email_ensure_log_table();
            $json = $meta ? json_encode($meta, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null;
            $stmt = db()->prepare('
                INSERT INTO email_activity_logs (event_key, recipient_email, subject, status, error_message, meta_json)
                VALUES (?, ?, ?, ?, NULLIF(?, ""), ?)
            ');
            $stmt->bind_param('ssssss', $event, $to, $subject, $status, $error, $json);
            $stmt->execute();
        } catch (Throwable $e) {
            // Email logging must never interrupt user-facing activity.
        }
    }
}

if (!function_exists('email_smtp_command')) {
    function email_smtp_command($socket, string $command, array $okCodes): string
    {
        if ($command !== '') {
            fwrite($socket, $command . "\r\n");
        }
        $response = '';
        while (($line = fgets($socket, 515)) !== false) {
            $response .= $line;
            if (strlen($line) >= 4 && $line[3] === ' ') {
                break;
            }
        }
        $code = (int) substr($response, 0, 3);
        if (!in_array($code, $okCodes, true)) {
            throw new RuntimeException(trim($response) ?: 'SMTP command failed.');
        }
        return $response;
    }
}

if (!function_exists('email_send_smtp')) {
    function email_send_smtp(string $to, string $subject, string $html, string $text, string $fromEmail, string $fromName): void
    {
        $host = trim(email_setting('mail_host', ''));
        $port = (int) email_setting('mail_port', '587');
        $username = trim(email_setting('mail_username', ''));
        $password = (string) email_setting('mail_password', '');
        $encryption = strtolower(trim(email_setting('mail_encryption', 'tls')));
        if ($host === '') {
            throw new RuntimeException('SMTP host is not configured.');
        }

        $remote = ($encryption === 'ssl' ? 'ssl://' : '') . $host . ':' . ($port > 0 ? $port : 587);
        $socket = @stream_socket_client($remote, $errno, $errstr, 12, STREAM_CLIENT_CONNECT);
        if (!$socket) {
            throw new RuntimeException('SMTP connect failed: ' . ($errstr ?: $errno));
        }
        stream_set_timeout($socket, 12);

        try {
            email_smtp_command($socket, '', [220]);
            email_smtp_command($socket, 'EHLO ' . ($_SERVER['SERVER_NAME'] ?? 'localhost'), [250]);
            if ($encryption === 'tls') {
                email_smtp_command($socket, 'STARTTLS', [220]);
                if (!stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                    throw new RuntimeException('SMTP TLS negotiation failed.');
                }
                email_smtp_command($socket, 'EHLO ' . ($_SERVER['SERVER_NAME'] ?? 'localhost'), [250]);
            }
            if ($username !== '') {
                email_smtp_command($socket, 'AUTH LOGIN', [334]);
                email_smtp_command($socket, base64_encode($username), [334]);
                email_smtp_command($socket, base64_encode($password), [235]);
            }

            $encodedSubject = '=?UTF-8?B?' . base64_encode($subject) . '?=';
            $boundary = 'b_' . bin2hex(random_bytes(12));
            $headers = [
                'From: ' . email_header_address($fromEmail, $fromName),
                'To: ' . $to,
                'Subject: ' . $encodedSubject,
                'MIME-Version: 1.0',
                'Content-Type: multipart/alternative; boundary="' . $boundary . '"',
                'Date: ' . date(DATE_RFC2822),
            ];
            $message = implode("\r\n", $headers) . "\r\n\r\n"
                . '--' . $boundary . "\r\nContent-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: 8bit\r\n\r\n" . $text . "\r\n\r\n"
                . '--' . $boundary . "\r\nContent-Type: text/html; charset=UTF-8\r\nContent-Transfer-Encoding: 8bit\r\n\r\n" . $html . "\r\n\r\n"
                . '--' . $boundary . "--\r\n";

            email_smtp_command($socket, 'MAIL FROM:<' . $fromEmail . '>', [250]);
            email_smtp_command($socket, 'RCPT TO:<' . $to . '>', [250, 251]);
            email_smtp_command($socket, 'DATA', [354]);
            fwrite($socket, str_replace("\r\n.", "\r\n..", $message) . "\r\n.\r\n");
            email_smtp_command($socket, '', [250]);
            email_smtp_command($socket, 'QUIT', [221]);
        } finally {
            fclose($socket);
        }
    }
}

if (!function_exists('email_header_address')) {
    function email_header_address(string $email, string $name): string
    {
        $name = trim($name);
        if ($name === '') {
            return '<' . $email . '>';
        }
        return '=?UTF-8?B?' . base64_encode($name) . '?= <' . $email . '>';
    }
}

if (!function_exists('email_send')) {
    function email_send(string $to, string $subject, string $html, string $text = ''): void
    {
        $to = trim($to);
        if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
            throw new RuntimeException('Invalid recipient email.');
        }
        $fromEmail = trim(email_setting('mail_from_email', email_setting('site_email', '')));
        if (!filter_var($fromEmail, FILTER_VALIDATE_EMAIL)) {
            throw new RuntimeException('From email is not configured.');
        }
        $fromName = trim(email_setting('mail_from_name', email_setting('site_name', 'Gyan Rank')));
        $text = $text !== '' ? $text : email_text_from_html($html);

        if (strtolower(email_setting('mail_driver', 'smtp')) === 'smtp') {
            email_send_smtp($to, $subject, $html, $text, $fromEmail, $fromName);
            return;
        }

        $headers = [
            'MIME-Version: 1.0',
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . email_header_address($fromEmail, $fromName),
        ];
        if (!mail($to, '=?UTF-8?B?' . base64_encode($subject) . '?=', $html, implode("\r\n", $headers))) {
            throw new RuntimeException('PHP mail() failed.');
        }
    }
}

if (!function_exists('email_notify')) {
    function email_notify(string $event, string $to, array $vars = [], string $fallbackSubject = '', string $fallbackBody = ''): bool
    {
        $event = preg_replace('/[^a-z0-9_]+/i', '_', strtolower($event)) ?: 'notification';
        if (email_setting('email_' . $event . '_enabled', '1') !== '1') {
            email_log_event($event, $to, $fallbackSubject ?: $event, 'skipped', 'Template disabled.', $vars);
            return false;
        }

        $subjectTemplate = email_setting('email_' . $event . '_subject', $fallbackSubject ?: 'Notification from {site_name}');
        $bodyTemplate = email_setting('email_' . $event . '_body', $fallbackBody ?: '<p>Hello {user_name}, an important activity was completed on {site_name}.</p>');
        $subject = email_render_template($subjectTemplate, $vars);
        $body = email_render_template($bodyTemplate, $vars);
        $html = email_layout($subject, $body, $vars);

        try {
            email_send($to, $subject, $html);
            email_log_event($event, $to, $subject, 'sent', '', $vars);
            return true;
        } catch (Throwable $e) {
            email_log_event($event, $to, $subject, 'failed', $e->getMessage(), $vars);
            return false;
        }
    }
}

if (!function_exists('email_user_row')) {
    function email_user_row(int $userId): array
    {
        if ($userId <= 0) {
            return [];
        }
        try {
            $stmt = db()->prepare('SELECT id, full_name, username, email, phone FROM users WHERE id = ? LIMIT 1');
            $stmt->bind_param('i', $userId);
            $stmt->execute();
            return $stmt->get_result()->fetch_assoc() ?: [];
        } catch (Throwable $e) {
            return [];
        }
    }
}

if (!function_exists('email_notify_user')) {
    function email_notify_user(string $event, $user, array $vars = [], string $fallbackSubject = '', string $fallbackBody = ''): bool
    {
        if (is_numeric($user)) {
            $user = email_user_row((int) $user);
        }
        if (!is_array($user) || empty($user['email'])) {
            return false;
        }
        $vars = array_merge([
            'user_id' => (string) ($user['id'] ?? ''),
            'user_name' => (string) ($user['full_name'] ?? $user['name'] ?? $user['username'] ?? 'Learner'),
            'user_email' => (string) ($user['email'] ?? ''),
            'user_phone' => (string) ($user['phone'] ?? ''),
        ], $vars);
        return email_notify($event, (string) $user['email'], $vars, $fallbackSubject, $fallbackBody);
    }
}

if (!function_exists('email_notify_admins')) {
    function email_notify_admins(string $event, array $vars = [], string $fallbackSubject = '', string $fallbackBody = ''): void
    {
        $emails = array_filter(array_unique([
            trim(email_setting('support_email', '')),
            trim(email_setting('site_email', '')),
            trim(email_setting('mail_from_email', '')),
        ]));
        foreach ($emails as $email) {
            if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
                email_notify($event, $email, $vars, $fallbackSubject, $fallbackBody);
            }
        }
    }
}

if (!function_exists('email_notify_purchase_event')) {
    function email_notify_purchase_event(string $event, string $sourceType, int $purchaseId): bool
    {
        if ($purchaseId <= 0 || !function_exists('purchase_invoice_payload')) {
            return false;
        }
        $sourceType = $sourceType === 'pdf' ? 'resource' : $sourceType;
        $payload = purchase_invoice_payload($sourceType, $purchaseId);
        if (!$payload || empty($payload['student']['email'])) {
            return false;
        }

        $purchase = $payload['purchase'];
        $invoice = [];
        if (($purchase['payment_status'] ?? '') === 'paid' && function_exists('ensure_invoice_for_purchase')) {
            $invoice = ensure_invoice_for_purchase($sourceType, $purchaseId);
        }
        $itemName = (string) ($payload['item_name'] ?? 'Learning Access');
        $amount = (float) ($payload['total'] ?? $purchase['amount'] ?? 0);
        $vars = [
            'item_name' => $itemName,
            'course_name' => $itemName,
            'plan_name' => $itemName,
            'amount' => 'Rs ' . number_format($amount, 2),
            'invoice_no' => (string) ($invoice['invoice_no'] ?? ''),
            'transaction_id' => (string) ($purchase['transaction_no'] ?? ''),
            'payment_method' => (string) ($purchase['payment_method'] ?? ''),
            'source_type' => $sourceType,
            'student_name' => (string) ($payload['student']['full_name'] ?? $payload['student']['name'] ?? $payload['student']['username'] ?? 'Learner'),
            'student_email' => (string) ($payload['student']['email'] ?? ''),
            'student_phone' => (string) ($payload['student']['phone'] ?? ''),
            'course_url' => email_base_url() . '/#/course/' . (int) ($purchase['course_id'] ?? 0),
            'invoice_url' => email_base_url() . '/#/invoices',
        ];

        if ($event === 'student_enrollment') {
            return email_notify_user(
                'student_enrollment',
                $payload['student'],
                $vars,
                'Enrollment confirmed - {course_name}',
                '<h2>Enrollment confirmed</h2><p>Hello {user_name}, access for <strong>{course_name}</strong> is active.</p><p><a href="{course_url}">Start Learning</a></p>'
            );
        }

        if ($event === 'membership_activated') {
            $sent = email_notify_user(
                'membership_activated',
                $payload['student'],
                $vars,
                'Membership activated - {plan_name}',
                '<h2>Membership activated</h2><p>Hello {user_name}, your <strong>{plan_name}</strong> plan is active.</p><p>Transaction: {transaction_id}</p>'
            );
            email_notify_admins(
                'admin_payment_success',
                $vars,
                'Membership payment received - {plan_name}',
                '<h2>Membership activated</h2><p>{student_name} activated <strong>{plan_name}</strong> for <strong>{amount}</strong>.</p><p>Transaction: {transaction_id}</p>'
            );
            return $sent;
        }

        if ($event === 'payment_failed') {
            $sent = email_notify_user(
                'payment_failed',
                $payload['student'],
                $vars,
                'Payment failed - {item_name}',
                '<h2>Payment failed</h2><p>Hello {user_name}, payment for <strong>{item_name}</strong> was not completed.</p><p>You can retry from your transactions page.</p>'
            );
            email_notify_admins(
                'admin_payment_failed',
                $vars,
                'Payment failed - {item_name}',
                '<h2>Payment failed</h2><p>{student_name} could not complete payment for <strong>{item_name}</strong>.</p><p>Transaction: {transaction_id}</p>'
            );
            return $sent;
        }

        $sent = email_notify_user(
            'payment_success',
            $payload['student'],
            $vars,
            'Payment received - {item_name}',
            '<h2>Payment received</h2><p>Hello {user_name}, we have received your payment of <strong>{amount}</strong> for <strong>{item_name}</strong>.</p><p>Invoice No: <strong>{invoice_no}</strong></p>'
        );
        email_notify_admins(
            'admin_payment_success',
            $vars,
            'Payment received - {item_name}',
            '<h2>Payment received</h2><p>{student_name} paid <strong>{amount}</strong> for <strong>{item_name}</strong>.</p><p>Invoice: {invoice_no}<br>Transaction: {transaction_id}</p>'
        );
        return $sent;
    }
}
