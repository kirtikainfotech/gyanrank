<?php
require_once __DIR__ . '/../config.php';

$admin = require_login('superadmin');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('sadmin/instructors');
}

if (!verify_csrf($_POST['csrf_token'] ?? null)) {
    $_SESSION['instructor_error'] = 'Security token expired.';
    redirect('sadmin/instructors');
}

$instructorId = (int) ($_POST['instructor_id'] ?? 0);

try {
    $stmt = db()->prepare("
        SELECT u.id, u.full_name, u.username, r.slug AS role
        FROM users u
        INNER JOIN roles r ON r.id = u.role_id
        WHERE u.id = ? AND r.slug = 'instructor'
        LIMIT 1
    ");
    $stmt->bind_param('i', $instructorId);
    $stmt->execute();
    $instructor = $stmt->get_result()->fetch_assoc();

    if (!$instructor) {
        throw new RuntimeException('Instructor account not found.');
    }

    $_SESSION['admin_user'] = [
        'id' => (int) $admin['id'],
        'full_name' => (string) $admin['full_name'],
        'username' => (string) $admin['username'],
        'role' => (string) $admin['role'],
    ];
    $_SESSION['impersonating_instructor_id'] = (int) $instructor['id'];
    $_SESSION['user'] = [
        'id' => (int) $instructor['id'],
        'full_name' => (string) $instructor['full_name'],
        'username' => (string) $instructor['username'],
        'role' => (string) $instructor['role'],
    ];
    session_regenerate_id(true);
    redirect('ins/dashboard');
} catch (Throwable $e) {
    $_SESSION['instructor_error'] = $e->getMessage();
    redirect('sadmin/instructors');
}
