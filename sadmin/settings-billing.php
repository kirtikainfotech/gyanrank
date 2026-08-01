<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/includes/settings_helpers.php';

$user = require_login('superadmin');
$pageTitle = 'Billing Settings';
$pageSubtitle = 'Invoice numbering, GST, currency and invoice notes.';
$activePage = 'settings';

function gst_states(): array
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
        '97' => 'Other Territory',
    ];
}

function current_financial_year_range(string $startDate): array
{
    $start = DateTime::createFromFormat('Y-m-d', $startDate) ?: new DateTime(date('Y') . '-04-01');
    $today = new DateTime('today');
    $month = (int) $start->format('m');
    $day = (int) $start->format('d');
    $year = (int) $today->format('Y');
    $candidate = DateTime::createFromFormat('Y-m-d', sprintf('%04d-%02d-%02d', $year, $month, $day));
    if ($candidate > $today) {
        $candidate->modify('-1 year');
    }
    $end = clone $candidate;
    $end->modify('+1 year -1 day');

    return [$candidate, $end];
}

function invoice_sample(array $settings): string
{
    [$fyStart, $fyEnd] = current_financial_year_range($settings['financial_year_start'] ?? date('Y') . '-04-01');
    $now = new DateTime();
    $number = (int) ($settings['invoice_current_no'] ?: $settings['invoice_starting_no'] ?: 1);
    $replacements = [
        '{PREFIX}' => $settings['invoice_prefix'] ?: 'INV',
        '{YYYY}' => $now->format('Y'),
        '{YY}' => $now->format('y'),
        '{MM}' => $now->format('m'),
        '{DD}' => $now->format('d'),
        '{HH}' => $now->format('H'),
        '{II}' => $now->format('i'),
        '{SS}' => $now->format('s'),
        '{FY}' => $fyStart->format('y') . '-' . $fyEnd->format('y'),
        '{NO}' => str_pad((string) max(1, $number), 5, '0', STR_PAD_LEFT),
    ];

    return strtr($settings['invoice_format'] ?: '{PREFIX}/{FY}/{NO}', $replacements);
}

function state_options(string $selected): string
{
    $html = '';
    foreach (gst_states() as $code => $name) {
        $isSelected = $selected === $code ? ' selected' : '';
        $html .= '<option value="' . h($code) . '"' . $isSelected . '>' . h($code . ' - ' . $name) . '</option>';
    }
    return $html;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['csrf_token'] ?? null)) {
        $_SESSION['settings_error'] = 'Security token expired.';
        redirect('sadmin/settings-billing');
    }

    save_setting('financial_year_close_reset', isset($_POST['financial_year_close_reset']) ? '1' : '0');
    save_setting('phonepe_enabled', isset($_POST['phonepe_enabled']) ? '1' : '0');
    $stateCode = trim((string) ($_POST['billing_state_code'] ?? ''));
    $states = gst_states();
    save_setting('billing_state_name', $states[$stateCode] ?? '');
    save_settings_keys([
        'invoice_prefix',
        'invoice_format',
        'invoice_starting_no',
        'invoice_current_no',
        'financial_year_start',
        'gst_number',
        'billing_address',
        'billing_state_code',
        'default_supply_state_code',
        'tax_rate',
        'currency',
        'currency_symbol',
        'invoice_terms',
        'invoice_thank_you_note',
        'invoice_footer',
        'phonepe_environment',
        'phonepe_merchant_id',
        'phonepe_salt_key',
        'phonepe_salt_index',
        'phonepe_client_id',
        'phonepe_client_secret',
        'phonepe_client_version',
    ]);
    $_SESSION['settings_message'] = 'Billing settings updated.';
    redirect('sadmin/settings-billing');
}

$settings = all_settings();
[$fyStart, $fyEnd] = current_financial_year_range($settings['financial_year_start']);
$sampleInvoiceNo = invoice_sample($settings);
$sameStateTax = ($settings['billing_state_code'] ?? '') === ($settings['default_supply_state_code'] ?? '');
$taxMode = $sameStateTax ? 'CGST + SGST' : 'IGST';
[$message, $error] = settings_flash();
?>
<?php include __DIR__ . '/includes/header.php'; ?>
<?php include __DIR__ . '/includes/sidebar.php'; ?>
<main class="main">
    <?php include __DIR__ . '/includes/topbar.php'; ?>
    <section class="content settings-page">
        <?php render_settings_submenu('billing'); ?>
        <?php if ($message): ?><div class="notice"><?= h($message); ?></div><?php endif; ?>
        <?php if ($error): ?><div class="notice danger"><?= h($error); ?></div><?php endif; ?>

        <section class="settings-detail-card">
            <div class="detail-head">
                <div>
                    <span>Billing</span>
                    <h2>Invoice, GST & Financial Year</h2>
                    <p>Current FY <?= h($fyStart->format('d M Y')); ?> to <?= h($fyEnd->format('d M Y')); ?>. Sample invoice: <?= h($sampleInvoiceNo); ?></p>
                </div>
                <a class="modal-button" href="#edit-billing">Edit Billing</a>
            </div>

            <div class="billing-summary-grid">
                <div><strong>Sample No</strong><b><?= h($sampleInvoiceNo); ?></b></div>
                <div><strong>Currency</strong><b><?= h($settings['currency_symbol'] . ' ' . $settings['currency']); ?></b></div>
                <div><strong>Default Tax</strong><b><?= h($settings['tax_rate']); ?>%</b></div>
                <div><strong>Tax Mode</strong><b><?= h($taxMode); ?></b></div>
                <div><strong>FY Reset</strong><b><?= $settings['financial_year_close_reset'] === '1' ? 'Enabled' : 'Disabled'; ?></b></div>
            </div>

            <table class="detail-table">
                <tbody>
                    <tr><th>Invoice Prefix</th><td><?= h($settings['invoice_prefix']); ?></td><th>Invoice Format</th><td><?= h($settings['invoice_format']); ?></td></tr>
                    <tr><th>Starting No</th><td><?= h($settings['invoice_starting_no']); ?></td><th>Current No</th><td><?= h($settings['invoice_current_no']); ?></td></tr>
                    <tr><th>FY Start Date</th><td><?= h($settings['financial_year_start']); ?></td><th>Current FY</th><td><?= h($fyStart->format('d M Y') . ' - ' . $fyEnd->format('d M Y')); ?></td></tr>
                    <tr><th>GST Number</th><td><?= h($settings['gst_number'] ?: 'Not set'); ?></td><th>Billing State</th><td><?= h(($settings['billing_state_code'] ?: 'Not set') . ' - ' . ($settings['billing_state_name'] ?: 'Not set')); ?></td></tr>
                    <tr><th>Supply State</th><td><?= h(($settings['default_supply_state_code'] ?: 'Not set') . ' - ' . (gst_states()[$settings['default_supply_state_code']] ?? 'Not set')); ?></td><th>Tax Bill Type</th><td><?= h($taxMode); ?></td></tr>
                    <tr><th>Billing Address</th><td colspan="3"><?= h($settings['billing_address'] ?: 'Not set'); ?></td></tr>
                    <tr><th>Currency Symbol</th><td><?= h($settings['currency_symbol'] ?: 'Not set'); ?></td><th>Currency</th><td><?= h($settings['currency']); ?></td></tr>
                    <tr><th>PhonePe</th><td><?= ($settings['phonepe_enabled'] ?? '0') === '1' ? 'Enabled' : 'Disabled'; ?></td><th>Environment</th><td><?= h(($settings['phonepe_environment'] ?? '') ?: 'sandbox'); ?></td></tr>
                    <tr><th>Client ID</th><td><?= h(($settings['phonepe_client_id'] ?? '') ?: 'Not set'); ?></td><th>Client Version</th><td><?= h(($settings['phonepe_client_version'] ?? '') ?: '1'); ?></td></tr>
                    <tr><th>Legacy Merchant ID</th><td><?= h(($settings['phonepe_merchant_id'] ?? '') ?: 'Not set'); ?></td><th>Salt Index</th><td><?= h(($settings['phonepe_salt_index'] ?? '') ?: '1'); ?></td></tr>
                    <tr><th>Terms</th><td colspan="3"><?= h($settings['invoice_terms'] ?: 'Not set'); ?></td></tr>
                    <tr><th>Thank You Note</th><td colspan="3"><?= h($settings['invoice_thank_you_note'] ?: 'Not set'); ?></td></tr>
                    <tr><th>Invoice Footer</th><td colspan="3"><?= h($settings['invoice_footer'] ?: 'Not set'); ?></td></tr>
                </tbody>
            </table>
        </section>
    </section>

    <div id="edit-billing" class="modal-overlay">
        <form class="modal-box wide-modal" method="post">
            <input type="hidden" name="csrf_token" value="<?= h(csrf_token()); ?>">
            <div class="modal-head">
                <h2>Edit Billing Settings</h2>
                <a class="modal-close" href="#" aria-label="Close">×</a>
            </div>
            <div class="invoice-sample-strip">
                <span>Sample Invoice No</span>
                <strong id="invoiceSamplePreview"><?= h($sampleInvoiceNo); ?></strong>
                <small id="taxModePreview"><?= h($taxMode); ?> bill</small>
            </div>
            <div class="invoice-token-help">Use {PREFIX}, {FY}, {YYYY}, {YY}, {MM}, {DD}, {HH}, {II}, {SS}, {NO}</div>
            <div class="form-grid">
                <label>Invoice Prefix<input id="invoicePrefix" name="invoice_prefix" value="<?= setting_value($settings, 'invoice_prefix'); ?>"></label>
                <label>Invoice Format<input id="invoiceFormat" name="invoice_format" value="<?= setting_value($settings, 'invoice_format'); ?>"></label>
                <label>Starting No<input id="invoiceStartingNo" type="number" min="1" name="invoice_starting_no" value="<?= setting_value($settings, 'invoice_starting_no'); ?>"></label>
                <label>Current No<input id="invoiceCurrentNo" type="number" min="1" name="invoice_current_no" value="<?= setting_value($settings, 'invoice_current_no'); ?>"></label>
                <label>Financial Year Start<input id="financialYearStart" type="date" name="financial_year_start" value="<?= setting_value($settings, 'financial_year_start'); ?>"></label>
                <label class="switch-field">FY Close Reset<span><input type="checkbox" name="financial_year_close_reset" value="1" <?= $settings['financial_year_close_reset'] === '1' ? 'checked' : ''; ?>><b></b></span></label>
                <label>GST Number<input name="gst_number" value="<?= setting_value($settings, 'gst_number'); ?>"></label>
                <label>Billing State<select id="billingStateCode" name="billing_state_code"><?= state_options($settings['billing_state_code']); ?></select></label>
                <label>Default Supply State<select id="supplyStateCode" name="default_supply_state_code"><?= state_options($settings['default_supply_state_code']); ?></select></label>
                <label>Default Tax %<input type="number" step="0.01" min="0" name="tax_rate" value="<?= setting_value($settings, 'tax_rate'); ?>"></label>
                <label>Currency<input name="currency" value="<?= setting_value($settings, 'currency'); ?>"></label>
                <label>Currency Symbol<input name="currency_symbol" value="<?= setting_value($settings, 'currency_symbol'); ?>"></label>
                <label class="switch-field">PhonePe Enabled<span><input type="checkbox" name="phonepe_enabled" value="1" <?= ($settings['phonepe_enabled'] ?? '0') === '1' ? 'checked' : ''; ?>><b></b></span></label>
                <label>PhonePe Environment<select name="phonepe_environment"><option value="sandbox" <?= ($settings['phonepe_environment'] ?? 'sandbox') === 'sandbox' ? 'selected' : ''; ?>>Sandbox</option><option value="live" <?= ($settings['phonepe_environment'] ?? '') === 'live' ? 'selected' : ''; ?>>Live</option></select></label>
                <label>PhonePe Client ID<input name="phonepe_client_id" value="<?= setting_value($settings, 'phonepe_client_id'); ?>" placeholder="Client ID from PhonePe Developer Settings"></label>
                <label>PhonePe Client Version<input name="phonepe_client_version" value="<?= setting_value($settings, 'phonepe_client_version'); ?>" placeholder="1"></label>
                <label class="span-2">PhonePe Client Secret<input name="phonepe_client_secret" value="<?= setting_value($settings, 'phonepe_client_secret'); ?>" placeholder="Client Secret from PhonePe Developer Settings"></label>
                <label>Legacy Merchant ID<input name="phonepe_merchant_id" value="<?= setting_value($settings, 'phonepe_merchant_id'); ?>"></label>
                <label>Legacy Salt Index<input name="phonepe_salt_index" value="<?= setting_value($settings, 'phonepe_salt_index'); ?>" placeholder="1"></label>
                <label class="span-2">Legacy Salt Key<input name="phonepe_salt_key" value="<?= setting_value($settings, 'phonepe_salt_key'); ?>" placeholder="Old PhonePe salt key"></label>
                <label class="span-2">Billing Address<textarea name="billing_address" rows="2"><?= setting_value($settings, 'billing_address'); ?></textarea></label>
                <label class="span-2">Invoice Terms & Conditions<textarea name="invoice_terms" rows="2"><?= setting_value($settings, 'invoice_terms'); ?></textarea></label>
                <label class="span-2">Thank You Note<textarea name="invoice_thank_you_note" rows="2"><?= setting_value($settings, 'invoice_thank_you_note'); ?></textarea></label>
                <label class="span-2">Invoice Footer<textarea name="invoice_footer" rows="2"><?= setting_value($settings, 'invoice_footer'); ?></textarea></label>
            </div>
            <div class="modal-actions"><button type="submit">Save Billing</button></div>
        </form>
    </div>
<script>
(() => {
    const prefix = document.getElementById('invoicePrefix');
    const format = document.getElementById('invoiceFormat');
    const startNo = document.getElementById('invoiceStartingNo');
    const currentNo = document.getElementById('invoiceCurrentNo');
    const fyStart = document.getElementById('financialYearStart');
    const preview = document.getElementById('invoiceSamplePreview');
    const billingState = document.getElementById('billingStateCode');
    const supplyState = document.getElementById('supplyStateCode');
    const taxMode = document.getElementById('taxModePreview');
    if (!prefix || !format || !preview) return;

    const fyText = () => {
        const value = fyStart.value || '<?= h($settings['financial_year_start']); ?>';
        const date = new Date(value + 'T00:00:00');
        if (Number.isNaN(date.getTime())) return '<?= h($fyStart->format('y') . '-' . $fyEnd->format('y')); ?>';
        const now = new Date();
        let startYear = now.getFullYear();
        const candidate = new Date(startYear, date.getMonth(), date.getDate());
        if (candidate > now) startYear -= 1;
        return String(startYear).slice(-2) + '-' + String(startYear + 1).slice(-2);
    };

    const padNo = value => String(Math.max(1, parseInt(value || startNo.value || '1', 10) || 1)).padStart(5, '0');
    const render = () => {
        const now = new Date();
        const map = {
            '{PREFIX}': prefix.value || 'INV',
            '{YYYY}': String(now.getFullYear()),
            '{YY}': String(now.getFullYear()).slice(-2),
            '{MM}': String(now.getMonth() + 1).padStart(2, '0'),
            '{DD}': String(now.getDate()).padStart(2, '0'),
            '{HH}': String(now.getHours()).padStart(2, '0'),
            '{II}': String(now.getMinutes()).padStart(2, '0'),
            '{SS}': String(now.getSeconds()).padStart(2, '0'),
            '{FY}': fyText(),
            '{NO}': padNo(currentNo.value),
        };
        let output = format.value || '{PREFIX}/{FY}/{NO}';
        Object.keys(map).forEach(key => output = output.split(key).join(map[key]));
        preview.textContent = output;
        if (billingState && supplyState && taxMode) {
            taxMode.textContent = (billingState.value === supplyState.value ? 'CGST + SGST' : 'IGST') + ' bill';
        }
    };

    [prefix, format, startNo, currentNo, fyStart, billingState, supplyState].forEach(field => field.addEventListener('input', render));
    [billingState, supplyState].forEach(field => field.addEventListener('change', render));
    render();
})();
</script>
<?php include __DIR__ . '/includes/footer.php'; ?>
