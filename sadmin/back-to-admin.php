<?php
require_once __DIR__ . '/../config.php';
start_secure_session();

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !verify_csrf($_POST['csrf_token'] ?? null)) {
    redirect('login');
}

$admin = $_SESSION['admin_user'] ?? null;

if (!is_array($admin) || !in_array((string) ($admin['role'] ?? ''), ['superadmin', 'super_admin'], true)) {
    redirect('login');
}

$_SESSION['user'] = [
    'id' => (int) $admin['id'],
    'full_name' => (string) $admin['full_name'],
    'username' => (string) $admin['username'],
    'role' => (string) $admin['role'],
];
unset($_SESSION['admin_user'], $_SESSION['impersonating_instructor_id']);
session_regenerate_id(true);
redirect('sadmin/dashboard');
