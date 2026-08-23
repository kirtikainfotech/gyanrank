<?php
declare(strict_types=1);

require_once __DIR__ . '/../config.php';

function ensure_live_streaming_tables(): void
{
    db()->query("
        CREATE TABLE IF NOT EXISTS live_stream_channels (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            instructor_id INT UNSIGNED NOT NULL,
            course_id INT UNSIGNED NULL,
            channel_name VARCHAR(160) NOT NULL,
            stream_key VARCHAR(80) NOT NULL,
            playback_slug VARCHAR(90) NOT NULL,
            status ENUM('scheduled','live','offline','disabled') NOT NULL DEFAULT 'offline',
            auto_recording TINYINT(1) NOT NULL DEFAULT 1,
            last_publish_ip VARCHAR(45) DEFAULT NULL,
            last_started_at DATETIME NULL,
            last_ended_at DATETIME NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY live_stream_channels_key_unique (stream_key),
            UNIQUE KEY live_stream_channels_slug_unique (playback_slug),
            KEY live_stream_channels_instructor_index (instructor_id),
            KEY live_stream_channels_course_index (course_id),
            CONSTRAINT live_stream_channels_instructor_foreign FOREIGN KEY (instructor_id) REFERENCES users (id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    live_ensure_channel_column('course_id', 'ALTER TABLE live_stream_channels ADD COLUMN course_id INT UNSIGNED NULL AFTER instructor_id');
    live_ensure_channel_column('class_id', 'ALTER TABLE live_stream_channels ADD COLUMN class_id INT UNSIGNED NULL AFTER course_id');
    live_ensure_channel_column('batch_id', 'ALTER TABLE live_stream_channels ADD COLUMN batch_id INT UNSIGNED NULL AFTER class_id');
    live_ensure_channel_column('thumbnail_path', 'ALTER TABLE live_stream_channels ADD COLUMN thumbnail_path VARCHAR(255) NULL AFTER playback_slug');
    live_ensure_channel_column('announcement_title', 'ALTER TABLE live_stream_channels ADD COLUMN announcement_title VARCHAR(180) NULL AFTER auto_recording');
    live_ensure_channel_column('announcement_text', 'ALTER TABLE live_stream_channels ADD COLUMN announcement_text TEXT NULL AFTER announcement_title');
    live_ensure_channel_column('scheduled_at', 'ALTER TABLE live_stream_channels ADD COLUMN scheduled_at DATETIME NULL AFTER announcement_text');
    live_ensure_channel_column('daily_live_time', 'ALTER TABLE live_stream_channels ADD COLUMN daily_live_time TIME NULL AFTER scheduled_at');
    live_ensure_channel_index('live_stream_channels_course_index', 'ALTER TABLE live_stream_channels ADD INDEX live_stream_channels_course_index (course_id)');
    live_ensure_channel_index('live_stream_channels_class_index', 'ALTER TABLE live_stream_channels ADD INDEX live_stream_channels_class_index (class_id)');
    live_ensure_channel_index('live_stream_channels_batch_index', 'ALTER TABLE live_stream_channels ADD INDEX live_stream_channels_batch_index (batch_id)');

    db()->query("
        CREATE TABLE IF NOT EXISTS live_stream_sessions (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            channel_id INT UNSIGNED NOT NULL,
            instructor_id INT UNSIGNED NOT NULL,
            stream_key VARCHAR(80) NOT NULL,
            publish_ip VARCHAR(45) DEFAULT NULL,
            status ENUM('live','ended','rejected') NOT NULL DEFAULT 'live',
            started_at DATETIME NOT NULL,
            ended_at DATETIME NULL,
            bytes_in BIGINT UNSIGNED NOT NULL DEFAULT 0,
            PRIMARY KEY (id),
            KEY live_stream_sessions_channel_index (channel_id, started_at),
            KEY live_stream_sessions_instructor_index (instructor_id),
            CONSTRAINT live_stream_sessions_channel_foreign FOREIGN KEY (channel_id) REFERENCES live_stream_channels (id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    db()->query("
        CREATE TABLE IF NOT EXISTS live_stream_recordings (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            channel_id INT UNSIGNED NOT NULL,
            session_id BIGINT UNSIGNED NULL,
            instructor_id INT UNSIGNED NOT NULL,
            title VARCHAR(180) NOT NULL,
            file_path VARCHAR(255) NOT NULL,
            duration_seconds INT UNSIGNED NOT NULL DEFAULT 0,
            file_size BIGINT UNSIGNED NOT NULL DEFAULT 0,
            status ENUM('processing','ready','failed') NOT NULL DEFAULT 'processing',
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY live_stream_recordings_channel_index (channel_id, created_at),
            KEY live_stream_recordings_instructor_index (instructor_id),
            CONSTRAINT live_stream_recordings_channel_foreign FOREIGN KEY (channel_id) REFERENCES live_stream_channels (id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
}

function live_ensure_channel_column(string $column, string $alterSql): void
{
    $column = db()->real_escape_string($column);
    $result = db()->query("SHOW COLUMNS FROM live_stream_channels LIKE '{$column}'");
    if (!$result->fetch_assoc()) {
        db()->query($alterSql);
    }
}

function live_ensure_channel_index(string $index, string $alterSql): void
{
    $index = db()->real_escape_string($index);
    $result = db()->query("SHOW INDEX FROM live_stream_channels WHERE Key_name = '{$index}'");
    if (!$result->fetch_assoc()) {
        db()->query($alterSql);
    }
}

function live_stream_secret(): string
{
    $secret = trim((string) getenv('LIVE_STREAM_SECRET'));
    if ($secret !== '') {
        return $secret;
    }

    $secret = app_setting('live_stream_secret', '');
    if ($secret !== '') {
        return $secret;
    }

    return hash('sha256', DB_NAME . '|' . DB_USER . '|' . __DIR__);
}

function live_base_url(): string
{
    $base = trim(app_setting('live_base_url', 'https://live.gyannexa.com'));
    return rtrim($base !== '' ? $base : 'https://live.gyannexa.com', '/');
}

function live_random_key(): string
{
    return 'gr_' . bin2hex(random_bytes(18));
}

function live_slug(string $name): string
{
    $slug = strtolower(trim(preg_replace('/[^a-zA-Z0-9]+/', '-', $name) ?? '', '-'));
    return ($slug !== '' ? $slug : 'class') . '-' . strtolower(bin2hex(random_bytes(3)));
}

function live_channel_for_instructor(int $instructorId): array
{
    ensure_live_streaming_tables();
    $stmt = db()->prepare('SELECT * FROM live_stream_channels WHERE instructor_id = ? ORDER BY id DESC LIMIT 1');
    $stmt->bind_param('i', $instructorId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    if ($row) {
        return $row;
    }

    $userStmt = db()->prepare('SELECT full_name FROM users WHERE id = ? LIMIT 1');
    $userStmt->bind_param('i', $instructorId);
    $userStmt->execute();
    $user = $userStmt->get_result()->fetch_assoc();
    $name = trim((string) ($user['full_name'] ?? 'Live Class'));
    $channel = $name !== '' ? $name . ' Live Class' : 'Live Class';
    $key = live_random_key();
    $slug = live_slug($channel);

    $insert = db()->prepare('INSERT INTO live_stream_channels (instructor_id, channel_name, stream_key, playback_slug) VALUES (?, ?, ?, ?)');
    $insert->bind_param('isss', $instructorId, $channel, $key, $slug);
    $insert->execute();

    return live_channel_by_key($key) ?? [];
}

function live_channel_by_key(string $streamKey): ?array
{
    ensure_live_streaming_tables();
    $stmt = db()->prepare('SELECT * FROM live_stream_channels WHERE stream_key = ? LIMIT 1');
    $stmt->bind_param('s', $streamKey);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    return $row ?: null;
}

function live_channel_by_slug(string $slug): ?array
{
    ensure_live_streaming_tables();
    $stmt = db()->prepare('SELECT * FROM live_stream_channels WHERE playback_slug = ? LIMIT 1');
    $stmt->bind_param('s', $slug);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    return $row ?: null;
}

function live_rotate_stream_key(int $instructorId): array
{
    $channel = live_channel_for_instructor($instructorId);
    $key = live_random_key();
    $stmt = db()->prepare('UPDATE live_stream_channels SET stream_key = ?, status = "offline" WHERE id = ? AND instructor_id = ?');
    $channelId = (int) $channel['id'];
    $stmt->bind_param('sii', $key, $channelId, $instructorId);
    $stmt->execute();
    return live_channel_by_key($key) ?? live_channel_for_instructor($instructorId);
}

function live_set_channel_status(int $channelId, string $status): void
{
    if (!in_array($status, ['scheduled', 'live', 'offline', 'disabled'], true)) {
        return;
    }
    $stmt = db()->prepare('UPDATE live_stream_channels SET status = ? WHERE id = ?');
    $stmt->bind_param('si', $status, $channelId);
    $stmt->execute();
}

function live_start_session(array $channel, string $ip): void
{
    $channelId = (int) $channel['id'];
    $instructorId = (int) $channel['instructor_id'];
    $streamKey = (string) $channel['stream_key'];
    $now = date('Y-m-d H:i:s');

    $stmt = db()->prepare('INSERT INTO live_stream_sessions (channel_id, instructor_id, stream_key, publish_ip, status, started_at) VALUES (?, ?, ?, ?, "live", ?)');
    $stmt->bind_param('iisss', $channelId, $instructorId, $streamKey, $ip, $now);
    $stmt->execute();

    $update = db()->prepare('UPDATE live_stream_channels SET status = "live", last_publish_ip = ?, last_started_at = ?, last_ended_at = NULL WHERE id = ?');
    $update->bind_param('ssi', $ip, $now, $channelId);
    $update->execute();
}

function live_end_session(array $channel): void
{
    $channelId = (int) $channel['id'];
    $now = date('Y-m-d H:i:s');

    $stmt = db()->prepare('UPDATE live_stream_sessions SET status = "ended", ended_at = ? WHERE channel_id = ? AND status = "live"');
    $stmt->bind_param('si', $now, $channelId);
    $stmt->execute();

    $update = db()->prepare('UPDATE live_stream_channels SET status = "offline", last_ended_at = ? WHERE id = ?');
    $update->bind_param('si', $now, $channelId);
    $update->execute();
}

function live_playback_token(string $slug, int $viewerId, int $ttlSeconds = 900): string
{
    $expires = time() + max(60, min(3600, $ttlSeconds));
    $header = live_base64url_encode(json_encode(['alg' => 'HS256', 'typ' => 'JWT'], JSON_UNESCAPED_SLASHES));
    $payload = live_base64url_encode(json_encode([
        'sub' => (string) $viewerId,
        'stream' => $slug,
        'iat' => time(),
        'exp' => $expires,
    ], JSON_UNESCAPED_SLASHES));
    $signature = live_base64url_encode(hash_hmac('sha256', $header . '.' . $payload, live_stream_secret(), true));
    return $header . '.' . $payload . '.' . $signature;
}

function live_validate_playback_token(string $slug, string $token): bool
{
    $parts = explode('.', $token);
    if (count($parts) !== 3) {
        return false;
    }

    [$header, $payload, $signature] = $parts;
    $expected = live_base64url_encode(hash_hmac('sha256', $header . '.' . $payload, live_stream_secret(), true));
    if (!hash_equals($expected, $signature)) {
        return false;
    }

    $headerData = json_decode(live_base64url_decode($header), true);
    $payloadData = json_decode(live_base64url_decode($payload), true);
    if (!is_array($headerData) || !is_array($payloadData) || ($headerData['alg'] ?? '') !== 'HS256') {
        return false;
    }

    return hash_equals($slug, (string) ($payloadData['stream'] ?? ''))
        && (int) ($payloadData['sub'] ?? 0) > 0
        && (int) ($payloadData['exp'] ?? 0) >= time();
}

function live_hls_url(array $channel, string $token = ''): string
{
    $slug = rawurlencode((string) $channel['playback_slug']);
    $url = live_base_url() . '/hls/' . $slug . '/index.m3u8';
    return $token !== '' ? $url . '?token=' . rawurlencode($token) : $url;
}

function live_base64url_encode(string $value): string
{
    return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
}

function live_base64url_decode(string $value): string
{
    $padded = strtr($value, '-_', '+/');
    $padding = strlen($padded) % 4;
    if ($padding > 0) {
        $padded .= str_repeat('=', 4 - $padding);
    }
    $decoded = base64_decode($padded, true);
    return is_string($decoded) ? $decoded : '';
}

function live_recent_recordings(int $instructorId, int $limit = 10): array
{
    ensure_live_streaming_tables();
    $stmt = db()->prepare('SELECT r.*, c.channel_name FROM live_stream_recordings r INNER JOIN live_stream_channels c ON c.id = r.channel_id WHERE r.instructor_id = ? ORDER BY r.id DESC LIMIT ?');
    $stmt->bind_param('ii', $instructorId, $limit);
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

function live_recordings_for_student(int $studentId, int $limit = 30): array
{
    ensure_live_streaming_tables();
    $stmt = db()->prepare("
        SELECT r.id, r.channel_id, r.title, r.file_path, r.duration_seconds, r.file_size, r.status, r.created_at,
               c.channel_name, c.playback_slug, c.course_id, ic.title AS course_title, u.full_name AS instructor_name
        FROM live_stream_recordings r
        INNER JOIN live_stream_channels c ON c.id = r.channel_id
        LEFT JOIN instructor_courses ic ON ic.id = c.course_id
        INNER JOIN users u ON u.id = r.instructor_id
        WHERE r.status = 'ready'
          AND (
              c.course_id IS NULL
              OR c.course_id = 0
              OR EXISTS (
                  SELECT 1 FROM student_course_enrollments e
                  WHERE e.student_id = ? AND e.course_id = c.course_id AND e.status IN ('active','completed')
              )
              OR EXISTS (
                  SELECT 1 FROM student_course_purchases p
                  WHERE p.student_id = ? AND p.course_id = c.course_id AND p.payment_status = 'paid'
              )
          )
        ORDER BY r.id DESC
        LIMIT ?
    ");
    $stmt->bind_param('iii', $studentId, $studentId, $limit);
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}
