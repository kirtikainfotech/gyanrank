<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/includes/settings_helpers.php';

$user = require_login('superadmin');
$pageTitle = 'Student Plans';
$pageSubtitle = 'Plan wise video, PDF, exam, mock test and live class access controls.';
$activePage = 'settings';

function ensure_student_plan_table(): void
{
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

    $defaults = [
        ['Free', 'free', 0.00, 30, 3, 4, 1, 1, 1, 1],
        ['Silver', 'silver', 499.00, 30, 25, 25, 10, 10, 10, 2],
        ['Gold', 'gold', 999.00, 365, null, null, null, null, null, 3],
    ];
    $stmt = db()->prepare("
        INSERT INTO student_membership_plans
            (plan_name, plan_slug, monthly_price, validity_days, video_limit, pdf_limit, exam_limit, mock_test_limit, live_class_limit, sort_order)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE plan_name = VALUES(plan_name)
    ");
    foreach ($defaults as $plan) {
        [$name, $slug, $price, $validity, $video, $pdf, $exam, $mock, $live, $sort] = $plan;
        $stmt->bind_param('ssdiiiiiii', $name, $slug, $price, $validity, $video, $pdf, $exam, $mock, $live, $sort);
        $stmt->execute();
    }
}

function plan_limit_value(string $value): ?int
{
    $value = trim($value);
    return $value === '' ? null : max(0, (int) $value);
}

ensure_student_plan_table();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['csrf_token'] ?? null)) {
        $_SESSION['settings_error'] = 'Security token expired.';
        redirect('sadmin/settings-plans');
    }

    try {
        $plans = (array) ($_POST['plans'] ?? []);
        $stmt = db()->prepare("
            UPDATE student_membership_plans
            SET plan_name = ?, monthly_price = ?, validity_days = ?, video_limit = ?, pdf_limit = ?,
                exam_limit = ?, mock_test_limit = ?, live_class_limit = ?, limit_reset_period = ?, is_active = ?
            WHERE id = ?
        ");
        foreach ($plans as $id => $plan) {
            $planId = (int) $id;
            $name = substr(trim((string) ($plan['plan_name'] ?? '')), 0, 80);
            $price = max(0, (float) ($plan['monthly_price'] ?? 0));
            $validity = max(1, (int) ($plan['validity_days'] ?? 30));
            $video = plan_limit_value((string) ($plan['video_limit'] ?? ''));
            $pdf = plan_limit_value((string) ($plan['pdf_limit'] ?? ''));
            $exam = plan_limit_value((string) ($plan['exam_limit'] ?? ''));
            $mock = plan_limit_value((string) ($plan['mock_test_limit'] ?? ''));
            $live = plan_limit_value((string) ($plan['live_class_limit'] ?? ''));
            $reset = strtolower((string) ($plan['limit_reset_period'] ?? 'one_time'));
            if (!in_array($reset, ['one_time', 'daily', 'weekly', 'monthly'], true)) {
                $reset = 'one_time';
            }
            $active = isset($plan['is_active']) ? 1 : 0;
            if ($name === '') {
                continue;
            }
            $stmt->bind_param('sdiiiiiisii', $name, $price, $validity, $video, $pdf, $exam, $mock, $live, $reset, $active, $planId);
            $stmt->execute();
        }
        save_setting('student_plan_free', 'Configured');
        save_setting('student_plan_silver', 'Configured');
        save_setting('student_plan_gold', 'Configured');
        $_SESSION['settings_message'] = 'Student plans updated.';
    } catch (Throwable $e) {
        $_SESSION['settings_error'] = $e->getMessage();
    }

    redirect('sadmin/settings-plans');
}

$plans = db()->query("
    SELECT id, plan_name, plan_slug, monthly_price, validity_days, video_limit, pdf_limit, exam_limit, mock_test_limit, live_class_limit, limit_reset_period, is_active
    FROM student_membership_plans
    ORDER BY sort_order ASC, id ASC
")->fetch_all(MYSQLI_ASSOC);
[$message, $error] = settings_flash();
?>
<?php include __DIR__ . '/includes/header.php'; ?>
<?php include __DIR__ . '/includes/sidebar.php'; ?>
<main class="main">
    <?php include __DIR__ . '/includes/topbar.php'; ?>
    <section class="content settings-page">
        <?php render_settings_submenu('plans'); ?>
        <?php if ($message !== ''): ?><div class="flash success"><?= h($message); ?></div><?php endif; ?>
        <?php if ($error !== ''): ?><div class="flash danger"><?= h($error); ?></div><?php endif; ?>

        <section class="settings-detail-card compact-section">
            <div class="section-heading compact-heading">
                <div>
                    <span class="section-kicker">STUDENT ACCESS</span>
                    <h1>Free, Silver & Gold plans</h1>
                    <p>Blank limit means unlimited. Single course purchase remains lifetime access.</p>
                </div>
                <button class="modal-button" form="plansForm" type="submit">Save Plans</button>
            </div>
            <form method="post" id="plansForm">
                <input type="hidden" name="csrf_token" value="<?= h(csrf_token()); ?>">
                <div class="table-wrap">
                    <table class="settings-table simple-table">
                        <thead>
                        <tr>
                            <th>Plan</th>
                            <th>Price</th>
                            <th>Validity Days</th>
                            <th>Videos</th>
                            <th>PDF</th>
                            <th>Exam</th>
                            <th>Mock</th>
                            <th>Live</th>
                            <th>Limit Reset</th>
                            <th>Status</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($plans as $plan): ?>
                            <tr>
                                <td>
                                    <strong><?= h(ucfirst((string) $plan['plan_slug'])); ?></strong>
                                    <input name="plans[<?= (int) $plan['id']; ?>][plan_name]" value="<?= h($plan['plan_name']); ?>">
                                </td>
                                <td><input type="number" step="0.01" min="0" name="plans[<?= (int) $plan['id']; ?>][monthly_price]" value="<?= h((string) $plan['monthly_price']); ?>"></td>
                                <td><input type="number" min="1" name="plans[<?= (int) $plan['id']; ?>][validity_days]" value="<?= h((string) $plan['validity_days']); ?>"></td>
                                <td><input type="number" min="0" placeholder="Unlimited" name="plans[<?= (int) $plan['id']; ?>][video_limit]" value="<?= h((string) ($plan['video_limit'] ?? '')); ?>"></td>
                                <td><input type="number" min="0" placeholder="Unlimited" name="plans[<?= (int) $plan['id']; ?>][pdf_limit]" value="<?= h((string) ($plan['pdf_limit'] ?? '')); ?>"></td>
                                <td><input type="number" min="0" placeholder="Unlimited" name="plans[<?= (int) $plan['id']; ?>][exam_limit]" value="<?= h((string) ($plan['exam_limit'] ?? '')); ?>"></td>
                                <td><input type="number" min="0" placeholder="Unlimited" name="plans[<?= (int) $plan['id']; ?>][mock_test_limit]" value="<?= h((string) ($plan['mock_test_limit'] ?? '')); ?>"></td>
                                <td><input type="number" min="0" placeholder="Unlimited" name="plans[<?= (int) $plan['id']; ?>][live_class_limit]" value="<?= h((string) ($plan['live_class_limit'] ?? '')); ?>"></td>
                                <td>
                                    <select name="plans[<?= (int) $plan['id']; ?>][limit_reset_period]">
                                        <?php foreach (['one_time' => 'One time', 'daily' => 'Daily', 'weekly' => 'Weekly', 'monthly' => 'Monthly'] as $value => $label): ?>
                                            <option value="<?= h($value); ?>" <?= (string) ($plan['limit_reset_period'] ?? 'one_time') === $value ? 'selected' : ''; ?>><?= h($label); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </td>
                                <td>
                                    <label class="switch-field mini-switch">
                                        <span><input type="checkbox" name="plans[<?= (int) $plan['id']; ?>][is_active]" value="1" <?= (int) $plan['is_active'] === 1 ? 'checked' : ''; ?>><b></b></span>
                                    </label>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </form>
        </section>
    </section>
</main>
<?php include __DIR__ . '/includes/footer.php'; ?>
