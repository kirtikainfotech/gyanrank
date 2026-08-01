<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../ins/includes/functions.php';

$user = require_login('superadmin');
$pageTitle = 'Instructors';
$pageSubtitle = 'Compact instructor management with KYC, ban and panel access.';
$activePage = 'instructors';

function instructor_role_id(): int
{
    $stmt = db()->prepare("SELECT id FROM roles WHERE slug = 'instructor' LIMIT 1");
    $stmt->execute();
    $role = $stmt->get_result()->fetch_assoc();
    return (int) ($role['id'] ?? 0);
}

function ensure_instructor_profile_table(): void
{
    db()->query("
        CREATE TABLE IF NOT EXISTS instructor_profiles (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id INT UNSIGNED NOT NULL,
            phone VARCHAR(30) DEFAULT NULL,
            expertise VARCHAR(180) DEFAULT NULL,
            experience_years DECIMAL(4,1) NOT NULL DEFAULT 0,
            qualification VARCHAR(180) DEFAULT NULL,
            bio TEXT NULL,
            referral_code VARCHAR(40) DEFAULT NULL,
            referred_by_code VARCHAR(40) DEFAULT NULL,
            commission_type ENUM('percent','fixed') NOT NULL DEFAULT 'percent',
            commission_value DECIMAL(10,2) NOT NULL DEFAULT 40.00,
            approval_status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY instructor_profiles_user_unique (user_id),
            UNIQUE KEY instructor_profiles_referral_unique (referral_code),
            KEY instructor_profiles_referred_by_index (referred_by_code),
            CONSTRAINT instructor_profiles_user_foreign FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
}

function ensure_user_block_status(): void
{
    $result = db()->query("SHOW COLUMNS FROM users LIKE 'status'");
    $column = $result->fetch_assoc();
    $type = (string) ($column['Type'] ?? '');

    if (str_starts_with($type, 'enum(') && (!str_contains($type, "'blocked'") || !str_contains($type, "'pending'"))) {
        preg_match_all("/'((?:[^'\\\\]|\\\\.)*)'/", $type, $matches);
        $values = array_values(array_unique(array_merge($matches[1] ?? [], ['pending', 'active', 'inactive', 'blocked'])));
        $enum = implode(',', array_map(static fn(string $value): string => "'" . db()->real_escape_string($value) . "'", $values));
        db()->query("ALTER TABLE users MODIFY status ENUM($enum) NOT NULL DEFAULT 'pending'");
    }
}

ensure_instructor_profile_table();
ensure_users_phone_column();
ensure_user_block_status();
ensure_instructor_erp_tables();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['csrf_token'] ?? null)) {
        $_SESSION['instructor_error'] = 'Security token expired.';
        redirect('sadmin/instructors');
    }

    $instructorId = (int) ($_POST['instructor_id'] ?? 0);
    $action = (string) ($_POST['action'] ?? 'save');

    try {
        if ($action === 'approve') {
            $stmt = db()->prepare("UPDATE users u INNER JOIN instructor_profiles p ON p.user_id = u.id SET u.status = 'active', p.approval_status = 'approved' WHERE u.id = ?");
            $stmt->bind_param('i', $instructorId);
            $stmt->execute();
            $_SESSION['instructor_message'] = 'Instructor approved.';
        } elseif ($action === 'reject') {
            $stmt = db()->prepare("UPDATE users u INNER JOIN instructor_profiles p ON p.user_id = u.id SET u.status = 'inactive', p.approval_status = 'rejected' WHERE u.id = ?");
            $stmt->bind_param('i', $instructorId);
            $stmt->execute();
            $_SESSION['instructor_message'] = 'Instructor rejected.';
        } elseif ($action === 'toggle_block') {
            $stmt = db()->prepare("UPDATE users SET status = IF(status = 'blocked', 'active', 'blocked') WHERE id = ?");
            $stmt->bind_param('i', $instructorId);
            $stmt->execute();
            $_SESSION['instructor_message'] = 'Instructor ban status changed.';
        } elseif ($action === 'toggle_kyc') {
            $stmt = db()->prepare("INSERT IGNORE INTO instructor_settings (instructor_id) VALUES (?)");
            $stmt->bind_param('i', $instructorId);
            $stmt->execute();

            $stmt = db()->prepare("UPDATE instructor_settings SET kyc_status = IF(kyc_status = 'verified', 'pending', 'verified') WHERE instructor_id = ?");
            $stmt->bind_param('i', $instructorId);
            $stmt->execute();
            $_SESSION['instructor_message'] = 'KYC status changed.';
        } else {
            $fullName = substr(trim((string) ($_POST['full_name'] ?? '')), 0, 120);
            $email = substr(trim((string) ($_POST['email'] ?? '')), 0, 160);
            $phone = substr(trim((string) ($_POST['phone'] ?? '')), 0, 30);
            $expertise = substr(trim((string) ($_POST['expertise'] ?? '')), 0, 180);
            $qualification = substr(trim((string) ($_POST['qualification'] ?? '')), 0, 180);
            $bio = substr(trim((string) ($_POST['bio'] ?? '')), 0, 1000);
            $commissionType = in_array($_POST['commission_type'] ?? '', ['percent', 'fixed'], true) ? (string) $_POST['commission_type'] : 'percent';
            $commissionValue = (float) ($_POST['commission_value'] ?? 0);
            $approvalStatus = in_array($_POST['approval_status'] ?? '', ['pending', 'approved', 'rejected'], true) ? (string) $_POST['approval_status'] : 'pending';
            $status = $approvalStatus === 'approved' ? 'active' : 'inactive';
            $experience = (float) ($_POST['experience_years'] ?? 0);

            $stmt = db()->prepare("UPDATE users SET full_name = ?, email = ?, phone = ?, status = ? WHERE id = ?");
            $stmt->bind_param('ssssi', $fullName, $email, $phone, $status, $instructorId);
            $stmt->execute();

            $stmt = db()->prepare("UPDATE instructor_profiles SET phone = ?, expertise = ?, experience_years = ?, qualification = ?, bio = ?, commission_type = ?, commission_value = ?, approval_status = ? WHERE user_id = ?");
            $stmt->bind_param('ssdsssdsi', $phone, $expertise, $experience, $qualification, $bio, $commissionType, $commissionValue, $approvalStatus, $instructorId);
            $stmt->execute();
            $_SESSION['instructor_message'] = 'Instructor updated.';
        }
    } catch (Throwable $e) {
        $_SESSION['instructor_error'] = $e->getMessage();
    }

    redirect('sadmin/instructors');
}

$roleId = instructor_role_id();
$stmt = db()->prepare("
    SELECT u.id, u.full_name, u.username, u.email, u.status, u.created_at,
           p.phone, p.expertise, p.experience_years, p.qualification, p.bio,
           p.referral_code, p.referred_by_code, p.commission_type, p.commission_value, p.approval_status,
           s.contact_number, s.whatsapp_number, s.support_email, s.kyc_status, s.kyc_document_path,
           (SELECT COUNT(*) FROM instructor_courses c WHERE c.instructor_id = u.id) AS course_count,
           (SELECT COUNT(*) FROM instructor_course_contents cc WHERE cc.instructor_id = u.id) AS chapter_count
    FROM users u
    LEFT JOIN instructor_profiles p ON p.user_id = u.id
    LEFT JOIN instructor_settings s ON s.instructor_id = u.id
    WHERE u.role_id = ?
    ORDER BY u.id DESC
");
$stmt->bind_param('i', $roleId);
$stmt->execute();
$instructors = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$message = $_SESSION['instructor_message'] ?? '';
$error = $_SESSION['instructor_error'] ?? '';
unset($_SESSION['instructor_message'], $_SESSION['instructor_error']);
?>
<?php include __DIR__ . '/includes/header.php'; ?>
<?php include __DIR__ . '/includes/sidebar.php'; ?>
<main class="main sadmin-instructors-main">
    <?php include __DIR__ . '/includes/topbar.php'; ?>
    <section class="content sadmin-instructors-page">
        <?php if ($message): ?><div class="notice"><?= h($message); ?></div><?php endif; ?>
        <?php if ($error): ?><div class="notice danger"><?= h($error); ?></div><?php endif; ?>

        <div class="card custom-card sadmin-instructor-card">
            <div class="card-header justify-content-between">
                <div class="card-title mb-0">
                    <span class="text-primary fs-11 fw-semibold text-uppercase d-block mb-1">Instructor Directory</span>
                    <div><?= count($instructors); ?> Instructors</div>
                    <small class="d-block fw-normal text-muted mt-1">Manage approval, KYC, teaching profile and panel access.</small>
                </div>
                <div class="d-flex gap-2 flex-wrap">
                    <a class="btn btn-light btn-wave" href="<?= h(app_url('sadmin/dashboard')); ?>">Dashboard</a>
                    <a class="btn btn-primary btn-wave" href="<?= h(app_url('instructor-signup')); ?>" target="_blank">New Instructor</a>
                </div>
            </div>

            <div class="card-body p-0">
                <div class="table-responsive">
                <table class="table table-hover align-middle mb-0 sadmin-instructor-table gr-register-table">
                    <thead><tr><th>Instructor</th><th>Contact</th><th>Teaching Profile</th><th>Content</th><th>Status</th><th class="text-end">Actions</th></tr></thead>
                    <tbody>
                        <?php if (!$instructors): ?>
                            <tr><td colspan="6" class="empty-state">No instructor signup yet.</td></tr>
                        <?php endif; ?>
                        <?php foreach ($instructors as $row): ?>
                            <tr>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <span class="avatar avatar-sm avatar-rounded bg-primary-transparent text-primary fw-semibold me-2"><?= h(strtoupper(substr(trim((string) $row['full_name']), 0, 1) ?: 'I')); ?></span>
                                        <div>
                                            <span class="gr-cell-title"><?= h($row['full_name']); ?></span>
                                            <span class="gr-cell-subtitle">@<?= h($row['username']); ?></span>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <span class="gr-cell-title"><?= h($row['email'] ?: 'Email not set'); ?></span>
                                    <span class="gr-cell-subtitle"><?= h($row['contact_number'] ?: $row['phone'] ?: 'Phone not set'); ?></span>
                                </td>
                                <td>
                                    <span class="gr-cell-title"><?= h($row['expertise'] ?: 'Not set'); ?></span>
                                    <span class="gr-cell-subtitle"><?= h(($row['qualification'] ?: 'Qualification not set') . ' - ' . (($row['experience_years'] ?? 0) . ' yrs')); ?></span>
                                </td>
                                <td>
                                    <span class="gr-cell-title"><?= h((string) ($row['course_count'] ?? 0)); ?> courses</span>
                                    <span class="gr-cell-subtitle"><?= h((string) ($row['chapter_count'] ?? 0)); ?> chapters</span>
                                </td>
                                <td>
                                    <span class="badge <?= ($row['approval_status'] ?? 'pending') === 'approved' ? 'bg-primary-transparent text-primary' : 'bg-warning-transparent text-warning'; ?> mb-1"><?= h(ucfirst($row['approval_status'] ?? 'pending')); ?></span>
                                    <span class="badge <?= ($row['kyc_status'] ?? '') === 'verified' ? 'bg-success-transparent text-success' : 'bg-warning-transparent text-warning'; ?> mb-1"><?= ($row['kyc_status'] ?? '') === 'verified' ? 'KYC Verified' : 'KYC Pending'; ?></span>
                                    <span class="badge <?= $row['status'] !== 'blocked' ? 'bg-success-transparent text-success' : 'bg-danger-transparent text-danger'; ?>"><?= $row['status'] === 'blocked' ? 'Banned' : 'Active'; ?></span>
                                </td>
                                <td class="text-end">
                                    <div class="btn-list justify-content-end">
                                        <form class="inline-panel-login" method="post" action="<?= h(app_url('sadmin/instructor-login')); ?>">
                                            <input type="hidden" name="csrf_token" value="<?= h(csrf_token()); ?>">
                                            <input type="hidden" name="instructor_id" value="<?= (int) $row['id']; ?>">
                                            <button class="btn btn-sm btn-primary btn-wave" type="submit">Panel</button>
                                        </form>
                                        <a class="btn btn-sm btn-light btn-wave" href="#instructor-<?= (int) $row['id']; ?>">Manage</a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                </div>
            </div>
        </div>
    </section>
    <style>
        .sadmin-instructors-main .sadmin-instructors-page {
            padding-top: 1.25rem;
        }
        .sadmin-instructors-main .sadmin-instructor-card {
            border: 0;
            border-radius: .65rem;
            box-shadow: 0 .85rem 1.85rem rgba(15, 23, 42, .045);
            overflow: hidden;
        }
        .sadmin-instructors-main .sadmin-instructor-card .card-header {
            padding: .75rem 1rem;
            border-bottom: 1px solid var(--default-border);
            background: var(--custom-white);
        }
        .sadmin-instructors-main .sadmin-instructor-card .card-title > div {
            font-size: 1.05rem;
            line-height: 1.2;
            font-weight: 700;
        }
        .sadmin-instructors-main .sadmin-instructor-card .card-title small {
            font-size: .72rem;
        }
        .sadmin-instructors-main .table-responsive {
            overflow-x: hidden;
            overflow-y: visible;
        }
        .sadmin-instructors-main .sadmin-instructor-table {
            width: 100%;
            min-width: 0;
            table-layout: fixed;
        }
        .sadmin-instructors-main .sadmin-instructor-table th,
        .sadmin-instructors-main .sadmin-instructor-table td {
            padding: .42rem .65rem !important;
            vertical-align: middle;
            font-size: .74rem;
            line-height: 1.2;
        }
        .sadmin-instructors-main .sadmin-instructor-table th {
            background: var(--default-background);
            color: var(--default-text-color);
            font-size: .67rem;
            letter-spacing: .025em;
        }
        .sadmin-instructors-main .sadmin-instructor-table th:nth-child(1) { width: 19%; }
        .sadmin-instructors-main .sadmin-instructor-table th:nth-child(2) { width: 22%; }
        .sadmin-instructors-main .sadmin-instructor-table th:nth-child(3) { width: 22%; }
        .sadmin-instructors-main .sadmin-instructor-table th:nth-child(4) { width: 10%; }
        .sadmin-instructors-main .sadmin-instructor-table th:nth-child(5) { width: 14%; }
        .sadmin-instructors-main .sadmin-instructor-table th:nth-child(6) { width: 13%; }
        .sadmin-instructors-main .sadmin-instructor-table form {
            margin: 0;
        }
        .sadmin-instructors-main .sadmin-instructor-table .badge {
            min-height: 1.2rem;
            padding: .15rem .45rem;
            border-radius: 999px;
            font-size: .62rem;
            font-weight: 700;
        }
        .sadmin-instructors-main .sadmin-instructor-table .btn-sm {
            min-height: 1.55rem;
            padding: .18rem .55rem;
            font-size: .67rem;
        }
        .sadmin-instructors-main .sadmin-instructor-table .btn-icon {
            width: 1.55rem;
            height: 1.55rem;
            padding-inline: 0;
        }
        .sadmin-instructors-main .sadmin-instructor-table .avatar-sm {
            width: 1.55rem;
            height: 1.55rem;
            font-size: .65rem;
        }
        .sadmin-instructors-main .sadmin-instructor-table .gr-cell-title {
            display: block;
            font-size: .75rem;
            line-height: 1.2;
            overflow-wrap: anywhere;
        }
        .sadmin-instructors-main .sadmin-instructor-table .gr-cell-subtitle,
        .sadmin-instructors-main .sadmin-instructor-table .fs-12 {
            display: block;
            font-size: .68rem !important;
            line-height: 1.2;
            overflow-wrap: anywhere;
        }
        .sadmin-instructors-main .sadmin-instructor-table .btn-list {
            gap: .18rem;
            flex-wrap: wrap;
        }
        .sadmin-instructors-main .footer {
            margin-inline: 0 !important;
            width: 100%;
        }
        .sadmin-instructors-main .modal-overlay:target {
            display: flex;
        }
        .sadmin-instructors-main .modal-overlay {
            align-items: center;
            justify-content: center;
            padding: 1rem;
            background: rgba(3, 21, 38, .48);
        }
        .sadmin-instructors-main .wide-modal {
            width: min(760px, 96vw);
            max-height: 88vh;
            border: 1px solid #c9d9e8;
            border-top: 4px solid #f68a00;
            border-radius: 6px;
            background: #fff;
            box-shadow: 0 18px 45px rgba(0,0,0,.25);
            overflow: auto;
        }
        .sadmin-instructors-main .wide-modal .modal-head {
            padding: .85rem 1rem;
            border-bottom: 1px solid #dbe7f2;
            background: #f6f9fc;
        }
        .sadmin-instructors-main .wide-modal .modal-head h2 {
            color: #082f55;
            font-size: 1rem;
            font-weight: 800;
        }
        .sadmin-instructors-main .wide-modal .form-grid {
            gap: .65rem;
            padding: 1rem;
        }
        .sadmin-instructors-main .wide-modal label {
            color: #0f172a;
            font-size: .73rem;
            font-weight: 800;
        }
        .sadmin-instructors-main .wide-modal input,
        .sadmin-instructors-main .wide-modal select,
        .sadmin-instructors-main .wide-modal textarea {
            min-height: 2rem;
            border: 1px solid #c9d9e8;
            border-radius: 3px;
            color: #102a43;
            font-size: .74rem;
        }
        .sadmin-instructors-main .instructor-modal-actions {
            display: flex;
            flex-wrap: wrap;
            justify-content: flex-end;
            gap: .45rem;
            padding: .75rem 1rem;
            border-top: 1px solid #dbe7f2;
        }
        .sadmin-instructors-main .instructor-modal-actions button,
        .sadmin-instructors-main .modal-secondary-link {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 2rem;
            border: 0;
            border-radius: 3px;
            padding: .35rem .75rem;
            background: #0a3c66;
            color: #ffffff;
            font-size: .72rem;
            font-weight: 800;
            text-decoration: none;
        }
        .sadmin-instructors-main .instructor-modal-actions button[value="reject"],
        .sadmin-instructors-main .instructor-modal-actions button[value="toggle_block"] {
            background: #dc3545;
        }
        .sadmin-instructors-main .modal-secondary-link {
            background: #f5f9fc;
            color: #0a3c66;
            border: 1px solid #c9d9e8;
        }
    </style>

    <?php foreach ($instructors as $row): ?>
        <div id="instructor-<?= (int) $row['id']; ?>" class="modal-overlay">
            <form class="modal-box wide-modal" method="post">
                <input type="hidden" name="csrf_token" value="<?= h(csrf_token()); ?>">
                <input type="hidden" name="instructor_id" value="<?= (int) $row['id']; ?>">
                <div class="modal-head"><h2>Edit Instructor</h2><a class="modal-close" href="#" aria-label="Close">x</a></div>
                <div class="form-grid">
                    <label>Full Name<input name="full_name" value="<?= h($row['full_name']); ?>" required></label>
                    <label>Email<input type="email" name="email" value="<?= h($row['email']); ?>"></label>
                    <label>Phone<input name="phone" value="<?= h($row['phone']); ?>"></label>
                    <label>Expertise<input name="expertise" value="<?= h($row['expertise']); ?>"></label>
                    <label>Experience Years<input type="number" step="0.5" min="0" name="experience_years" value="<?= h((string) $row['experience_years']); ?>"></label>
                    <label>Qualification<input name="qualification" value="<?= h($row['qualification']); ?>"></label>
                    <label>Commission Type<select name="commission_type"><option value="percent" <?= ($row['commission_type'] ?? '') === 'percent' ? 'selected' : ''; ?>>Percent</option><option value="fixed" <?= ($row['commission_type'] ?? '') === 'fixed' ? 'selected' : ''; ?>>Fixed</option></select></label>
                    <label>Commission Value<input type="number" step="0.01" min="0" name="commission_value" value="<?= h((string) $row['commission_value']); ?>"></label>
                    <label>Approval Status<select name="approval_status"><option value="pending" <?= ($row['approval_status'] ?? '') === 'pending' ? 'selected' : ''; ?>>Pending</option><option value="approved" <?= ($row['approval_status'] ?? '') === 'approved' ? 'selected' : ''; ?>>Approved</option><option value="rejected" <?= ($row['approval_status'] ?? '') === 'rejected' ? 'selected' : ''; ?>>Rejected</option></select></label>
                    <label>KYC Status<input value="<?= h(ucfirst(str_replace('_', ' ', (string) ($row['kyc_status'] ?? 'not_submitted')))); ?>" readonly></label>
                    <label>Courses<input value="<?= h((string) ($row['course_count'] ?? 0)); ?> courses / <?= h((string) ($row['chapter_count'] ?? 0)); ?> chapters" readonly></label>
                    <label class="span-2">Bio<textarea name="bio" rows="2"><?= h($row['bio']); ?></textarea></label>
                </div>
                <div class="modal-actions instructor-modal-actions">
                    <?php if (!empty($row['kyc_document_path'])): ?>
                        <a class="modal-secondary-link" href="<?= h(app_url((string) $row['kyc_document_path'])); ?>" target="_blank">View KYC Doc</a>
                    <?php endif; ?>
                    <button type="submit" name="action" value="toggle_kyc"><?= ($row['kyc_status'] ?? '') === 'verified' ? 'Mark KYC Pending' : 'Verify KYC'; ?></button>
                    <button type="submit" name="action" value="toggle_block"><?= $row['status'] === 'blocked' ? 'Unban' : 'Ban'; ?></button>
                    <button type="submit" name="action" value="approve">Approve</button>
                    <button type="submit" name="action" value="reject">Reject</button>
                    <button type="submit" name="action" value="save">Save Changes</button>
                </div>
            </form>
        </div>
    <?php endforeach; ?>
<?php include __DIR__ . '/includes/footer.php'; ?>

