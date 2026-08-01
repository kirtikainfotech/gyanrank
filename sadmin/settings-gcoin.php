<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/includes/settings_helpers.php';

$user = require_login('superadmin');
$pageTitle = 'Gcoin Wallet';
$pageSubtitle = 'Referral rewards, coin conversion and wallet transactions.';
$activePage = 'settings';

$keys = [
    'gcoin_enabled',
    'gcoin_name',
    'gcoin_signup_referrer_reward',
    'gcoin_signup_joiner_reward',
    'gcoin_purchase_referrer_reward_type',
    'gcoin_purchase_referrer_reward_value',
    'gcoin_per_inr',
    'gcoin_min_redeem',
    'gcoin_purchase_redeem_enabled',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['csrf_token'] ?? null)) {
        $_SESSION['settings_error'] = 'Security token expired.';
        redirect('sadmin/settings-gcoin');
    }
    $_POST['gcoin_enabled'] = isset($_POST['gcoin_enabled']) ? '1' : '0';
    $_POST['gcoin_purchase_redeem_enabled'] = isset($_POST['gcoin_purchase_redeem_enabled']) ? '1' : '0';
    save_settings_keys($keys);
    $_SESSION['settings_message'] = 'Gcoin settings updated.';
    redirect('sadmin/settings-gcoin');
}

$settings = all_settings();
[$message, $error] = settings_flash();
$summary = ['wallets' => 0, 'coins' => 0, 'earned' => 0, 'spent' => 0];
if (db()->query("SHOW TABLES LIKE 'student_gcoin_wallets'")->fetch_assoc()) {
    $row = db()->query('SELECT COUNT(*) AS wallets, COALESCE(SUM(balance),0) AS coins, COALESCE(SUM(earned_total),0) AS earned, COALESCE(SUM(spent_total),0) AS spent FROM student_gcoin_wallets')->fetch_assoc();
    $summary = array_merge($summary, $row ?: []);
}
$transactions = [];
if (db()->query("SHOW TABLES LIKE 'student_gcoin_transactions'")->fetch_assoc()) {
    $transactions = db()->query("
        SELECT t.*, u.full_name, u.email
        FROM student_gcoin_transactions t
        LEFT JOIN users u ON u.id = t.student_id
        ORDER BY t.id DESC
        LIMIT 80
    ")->fetch_all(MYSQLI_ASSOC);
}
?>
<?php include __DIR__ . '/includes/header.php'; ?>
<?php include __DIR__ . '/includes/sidebar.php'; ?>
<main class="main">
    <?php include __DIR__ . '/includes/topbar.php'; ?>
    <section class="content settings-page">
        <?php render_settings_submenu('gcoin'); ?>
        <?php if ($message): ?><div class="notice"><?= h($message); ?></div><?php endif; ?>
        <?php if ($error): ?><div class="notice danger"><?= h($error); ?></div><?php endif; ?>

        <section class="settings-detail-card">
            <div class="detail-head">
                <div>
                    <span>Gcoin Wallet</span>
                    <h2>Referral & Coin Conversion</h2>
                    <p>Control signup rewards, purchase rewards and INR conversion for student wallet purchases.</p>
                </div>
                <a class="modal-button" href="#edit-gcoin">Edit Gcoin</a>
            </div>
            <table class="detail-table">
                <tbody>
                    <tr><th>Status</th><td><?= $settings['gcoin_enabled'] === '1' ? 'Enabled' : 'Disabled'; ?></td><th>Coin Name</th><td><?= h($settings['gcoin_name']); ?></td></tr>
                    <tr><th>Signup Referrer</th><td><?= h($settings['gcoin_signup_referrer_reward']); ?> coins</td><th>Signup Joiner</th><td><?= h($settings['gcoin_signup_joiner_reward']); ?> coins</td></tr>
                    <tr><th>Purchase Referrer</th><td><?= h($settings['gcoin_purchase_referrer_reward_value'] . ' ' . $settings['gcoin_purchase_referrer_reward_type']); ?></td><th>Conversion</th><td>Rs 1 = <?= h($settings['gcoin_per_inr']); ?> coins</td></tr>
                    <tr><th>Wallets</th><td><?= h((string) $summary['wallets']); ?></td><th>Balance Coins</th><td><?= h(number_format((float) $summary['coins'], 2)); ?></td></tr>
                    <tr><th>Total Earned</th><td><?= h(number_format((float) $summary['earned'], 2)); ?></td><th>Total Spent</th><td><?= h(number_format((float) $summary['spent'], 2)); ?></td></tr>
                </tbody>
            </table>
        </section>

        <section class="settings-detail-card compact-section">
            <div class="detail-head"><div><span>Transactions</span><h2>Latest Gcoin Ledger</h2></div></div>
            <table class="role-access-table smart-table">
                <thead><tr><th>Student</th><th>Type</th><th>Coins</th><th>Balance</th><th>Source</th><th>Date</th></tr></thead>
                <tbody>
                    <?php if (!$transactions): ?><tr><td colspan="6">No Gcoin transaction yet.</td></tr><?php endif; ?>
                    <?php foreach ($transactions as $row): ?>
                        <tr>
                            <td><strong><?= h($row['full_name'] ?: 'Student'); ?></strong><small><?= h($row['email'] ?: ''); ?></small></td>
                            <td><?= h(ucfirst($row['direction'])); ?></td>
                            <td><?= h(number_format((float) $row['coins'], 2)); ?></td>
                            <td><?= h(number_format((float) $row['balance_after'], 2)); ?></td>
                            <td><?= h($row['source_type'] . ' - ' . ($row['note'] ?? '')); ?></td>
                            <td><?= h($row['created_at']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </section>
    </section>

    <div id="edit-gcoin" class="modal-overlay">
        <form class="modal-box wide-modal" method="post">
            <input type="hidden" name="csrf_token" value="<?= h(csrf_token()); ?>">
            <div class="modal-head"><h2>Edit Gcoin Rules</h2><a class="modal-close" href="#" aria-label="Close">x</a></div>
            <div class="form-grid">
                <label class="switch-field">Gcoin Enabled<span><input type="checkbox" name="gcoin_enabled" value="1" <?= $settings['gcoin_enabled'] === '1' ? 'checked' : ''; ?>><b></b></span></label>
                <label>Coin Name<input name="gcoin_name" value="<?= setting_value($settings, 'gcoin_name'); ?>"></label>
                <label>Referrer Signup Coins<input type="number" min="0" step="0.01" name="gcoin_signup_referrer_reward" value="<?= setting_value($settings, 'gcoin_signup_referrer_reward'); ?>"></label>
                <label>Joiner Signup Coins<input type="number" min="0" step="0.01" name="gcoin_signup_joiner_reward" value="<?= setting_value($settings, 'gcoin_signup_joiner_reward'); ?>"></label>
                <label>Purchase Reward Type<select name="gcoin_purchase_referrer_reward_type"><option value="percent" <?= $settings['gcoin_purchase_referrer_reward_type'] === 'percent' ? 'selected' : ''; ?>>Percent of purchase</option><option value="fixed" <?= $settings['gcoin_purchase_referrer_reward_type'] === 'fixed' ? 'selected' : ''; ?>>Fixed coins</option></select></label>
                <label>Purchase Reward Value<input type="number" min="0" step="0.01" name="gcoin_purchase_referrer_reward_value" value="<?= setting_value($settings, 'gcoin_purchase_referrer_reward_value'); ?>"></label>
                <label>Coins Deducted for Rs 1<input type="number" min="1" step="0.01" name="gcoin_per_inr" value="<?= setting_value($settings, 'gcoin_per_inr'); ?>"></label>
                <label>Minimum Redeem Coins<input type="number" min="0" step="0.01" name="gcoin_min_redeem" value="<?= setting_value($settings, 'gcoin_min_redeem'); ?>"></label>
                <label class="switch-field">Allow Purchase Redeem<span><input type="checkbox" name="gcoin_purchase_redeem_enabled" value="1" <?= $settings['gcoin_purchase_redeem_enabled'] === '1' ? 'checked' : ''; ?>><b></b></span></label>
            </div>
            <div class="modal-actions"><button type="submit">Save Gcoin Rules</button></div>
        </form>
    </div>
<?php include __DIR__ . '/includes/footer.php'; ?>
