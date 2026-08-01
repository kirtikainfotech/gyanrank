<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/live_streaming.php';

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'OPTIONS') {
    exit;
}

function live_api_out(array $payload, int $status = 200): void
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

function live_api_input(): array
{
    static $input = null;
    if (is_array($input)) {
        return $input;
    }

    $raw = file_get_contents('php://input') ?: '';
    $json = json_decode($raw, true);
    if (is_array($json)) {
        $input = $json;
        return $input;
    }

    $form = [];
    if ($raw !== '') {
        parse_str($raw, $form);
    }
    $input = $form ?: $_POST;
    return $input;
}

function live_bearer_token(): string
{
    $input = live_api_input();
    $header = $_SERVER['HTTP_AUTHORIZATION']
        ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION']
        ?? $_SERVER['Authorization']
        ?? '';

    if ($header === '' && function_exists('apache_request_headers')) {
        $headers = apache_request_headers();
        $header = (string) ($headers['Authorization'] ?? $headers['authorization'] ?? '');
    }

    if (preg_match('/Bearer\s+(.+)/i', (string) $header, $m)) {
        return trim($m[1]);
    }

    return trim((string) ($_GET['app_token'] ?? $input['app_token'] ?? ''));
}

function live_api_student(): array
{
    $token = live_bearer_token();
    if ($token === '') {
        live_api_out(['success' => false, 'message' => 'Login required.'], 401);
    }

    $hash = hash('sha256', $token);
    $stmt = db()->prepare("
        SELECT u.id, u.full_name, u.status
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
        live_api_out(['success' => false, 'message' => 'Account inactive or session expired.'], 401);
    }

    return $user;
}

function live_nginx_text(string $message, int $status): void
{
    header('Content-Type: text/plain; charset=utf-8');
    http_response_code($status);
    echo $message;
    exit;
}

function live_publish_key_from_request(array $input): string
{
    $key = trim((string) ($_GET['key'] ?? $input['key'] ?? $_GET['secret'] ?? $input['secret'] ?? ''));
    if ($key !== '') {
        return $key;
    }

    $args = trim((string) ($_GET['args'] ?? $input['args'] ?? ''));
    if ($args === '') {
        return '';
    }

    $parsed = [];
    parse_str($args, $parsed);
    return trim((string) ($parsed['key'] ?? $parsed['secret'] ?? ''));
}

function live_publish_slug_from_request(array $input): string
{
    $name = trim((string) ($_GET['name'] ?? $input['name'] ?? $_GET['stream'] ?? $input['stream'] ?? ''));
    if ($name === '') {
        return '';
    }

    $parts = parse_url($name);
    if (is_array($parts) && isset($parts['path'])) {
        return trim((string) $parts['path']);
    }

    return strtok($name, '?') ?: $name;
}

function live_student_can_access_course_local(int $studentId, int $courseId): bool
{
    if ($courseId <= 0) {
        return true;
    }

    $stmt = db()->prepare('SELECT id FROM student_course_enrollments WHERE student_id = ? AND course_id = ? AND status IN ("active","completed") LIMIT 1');
    $stmt->bind_param('ii', $studentId, $courseId);
    $stmt->execute();
    if ($stmt->get_result()->fetch_assoc()) {
        return true;
    }

    $stmt = db()->prepare('SELECT id FROM student_course_purchases WHERE student_id = ? AND course_id = ? AND payment_status = "paid" LIMIT 1');
    $stmt->bind_param('ii', $studentId, $courseId);
    $stmt->execute();
    return (bool) $stmt->get_result()->fetch_assoc();
}

function live_student_can_access_channel(array $channel, int $studentId): bool
{
    $courseId = (int) ($channel['course_id'] ?? 0);
    return $courseId <= 0 || live_student_can_access_course_local($studentId, $courseId);
}

try {
    ensure_live_streaming_tables();
    $action = (string) ($_GET['action'] ?? ($_POST['action'] ?? 'status'));
    $input = live_api_input();

    if ($action === 'publish_auth') {
        $slug = live_publish_slug_from_request($input);
        $streamKey = live_publish_key_from_request($input);
        if ($streamKey === '') {
            $name = trim((string) ($_GET['name'] ?? $input['name'] ?? ''));
            $query = (string) (parse_url($name, PHP_URL_QUERY) ?: '');
            if ($query !== '') {
                $parsed = [];
                parse_str($query, $parsed);
                $streamKey = trim((string) ($parsed['key'] ?? $parsed['secret'] ?? ''));
            }
        }
        $channel = $slug !== '' ? live_channel_by_slug($slug) : null;
        if (!$channel || (string) $channel['status'] === 'disabled') {
            live_nginx_text('Forbidden', 403);
        }
        if ($streamKey === '' || !hash_equals((string) $channel['stream_key'], $streamKey)) {
            live_nginx_text('Forbidden', 403);
        }

        live_start_session($channel, client_ip());
        live_nginx_text('OK', 200);
    }

    if ($action === 'publish_done') {
        $slug = trim((string) ($_GET['name'] ?? $input['name'] ?? $_GET['stream'] ?? $input['stream'] ?? ''));
        $channel = $slug !== '' ? (live_channel_by_slug($slug) ?? live_channel_by_key($slug)) : null;
        if ($channel) {
            live_end_session($channel);
        }
        live_nginx_text('OK', 200);
    }

    if ($action === 'hls_auth') {
        $slug = trim((string) ($_GET['stream'] ?? $input['stream'] ?? $_GET['slug'] ?? $input['slug'] ?? ''));
        $token = trim((string) ($_GET['token'] ?? $input['token'] ?? ''));
        $channel = $slug !== '' ? live_channel_by_slug($slug) : null;
        if (!$channel || (string) $channel['status'] === 'disabled' || !live_validate_playback_token($slug, $token)) {
            live_nginx_text('Forbidden', 403);
        }
        live_nginx_text('OK', 200);
    }

    if ($action === 'playback_token') {
        $user = live_api_student();
        $slug = trim((string) ($_GET['stream'] ?? $input['stream'] ?? $_GET['slug'] ?? $input['slug'] ?? ''));
        $channel = $slug !== '' ? live_channel_by_slug($slug) : null;
        if (!$channel || (string) $channel['status'] === 'disabled') {
            live_api_out(['success' => false, 'message' => 'Live class not found.'], 404);
        }
        if (!live_student_can_access_channel($channel, (int) $user['id'])) {
            live_api_out(['success' => false, 'message' => 'Please enroll in this course to watch this live class.'], 403);
        }

        $token = live_playback_token((string) $channel['playback_slug'], (int) $user['id']);
        live_api_out([
            'success' => true,
            'stream' => [
                'name' => $channel['channel_name'],
                'slug' => $channel['playback_slug'],
                'status' => $channel['status'],
                'hls_url' => live_hls_url($channel, $token),
                'expires_in' => 900,
            ],
        ]);
    }

    if ($action === 'recording_done') {
        $secret = trim((string) ($_GET['secret'] ?? $input['secret'] ?? ''));
        if (!hash_equals(live_stream_secret(), $secret)) {
            live_nginx_text('Forbidden', 403);
        }

        $streamKey = trim((string) ($_GET['name'] ?? $input['name'] ?? $_GET['stream_key'] ?? $input['stream_key'] ?? ''));
        $slug = trim((string) ($_GET['stream'] ?? $input['stream'] ?? $_GET['slug'] ?? $input['slug'] ?? ''));
        $path = substr(trim((string) ($_GET['path'] ?? $input['path'] ?? '')), 0, 255);
        $channel = $slug !== '' ? live_channel_by_slug($slug) : null;
        if (!$channel && $streamKey !== '') {
            $channel = live_channel_by_key($streamKey);
        }
        if ($channel && $path !== '') {
            $title = (string) $channel['channel_name'] . ' - ' . date('d M Y H:i');
            $channelId = (int) $channel['id'];
            $instructorId = (int) $channel['instructor_id'];
            $stmt = db()->prepare('INSERT INTO live_stream_recordings (channel_id, instructor_id, title, file_path, status) VALUES (?, ?, ?, ?, "ready")');
            $stmt->bind_param('iiss', $channelId, $instructorId, $title, $path);
            $stmt->execute();
        }
        live_nginx_text('OK', 200);
    }

    live_api_out(['success' => false, 'message' => 'Invalid action.'], 404);
} catch (Throwable $e) {
    live_api_out(['success' => false, 'message' => $e->getMessage()], 500);
}
