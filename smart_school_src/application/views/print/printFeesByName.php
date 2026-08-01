<?php
$currency_symbol = $this->customlib->getSchoolCurrencyFormat();
$student_name    = $this->customlib->getFullName($feeList->firstname, $feeList->middlename, $feeList->lastname, $sch_setting->middlename, $sch_setting->lastname);
$school_name     = 'GyanRank International School';
$school_address  = 'Knowledge Park, Tonk Road, Jaipur, Rajasthan 302017';
$school_contact  = '+91 98765 43210';
$school_email    = 'accounts@gyanrank.in';
$logo_url        = base_url() . 'uploads/admit_card/gyanrank-admit-logo.png';
$small_logo_url  = base_url() . 'uploads/admit_card/gyanrank-admit-small-logo.png';
$signature_url   = base_url() . 'uploads/admit_card/gyanrank-authorized-sign.svg';

$amount_detail = isJSON($feeList->amount_detail) ? json_decode($feeList->amount_detail) : null;
$record        = ($amount_detail && isset($amount_detail->{$sub_invoice_id})) ? $amount_detail->{$sub_invoice_id} : null;
$receipt_date  = $record && !empty($record->date) ? $record->date : date('Y-m-d');
$paid_amount   = $record && isset($record->amount) ? (float) $record->amount : 0;
$discount      = $record && isset($record->amount_discount) ? (float) $record->amount_discount : 0;
$fine          = $record && isset($record->amount_fine) ? (float) $record->amount_fine : 0;
$net_received  = $paid_amount + $fine;
$payment_mode  = $record && !empty($record->payment_mode) ? $record->payment_mode : 'Cash';
$description   = $record && !empty($record->description) ? $record->description : 'Fee payment received successfully.';
$collected_by  = $record && !empty($record->collected_by) ? $record->collected_by : 'GyanRank Accounts';
$receipt_no    = 'GR-FEE-' . str_pad($feeList->id, 5, '0', STR_PAD_LEFT) . '-' . $sub_invoice_id;
$copy_labels   = array('School Copy', 'Student Copy');

$render_receipt = function ($copy_label) use (
    $currency_symbol,
    $student_name,
    $student,
    $feeList,
    $school_name,
    $school_address,
    $school_contact,
    $school_email,
    $logo_url,
    $small_logo_url,
    $signature_url,
    $receipt_date,
    $paid_amount,
    $discount,
    $fine,
    $net_received,
    $payment_mode,
    $description,
    $collected_by,
    $receipt_no,
    $sub_invoice_id,
    $record
) {
    ?>
    <section class="gr-receipt">
        <div class="gr-watermark"><img src="<?php echo $small_logo_url; ?>" alt=""></div>

        <header class="gr-header">
            <div class="gr-brand">
                <img src="<?php echo $logo_url; ?>" alt="GyanRank">
                <div>
                    <h1><?php echo $school_name; ?></h1>
                    <p>Learn. Grow. Succeed.</p>
                </div>
            </div>
            <div class="gr-school-meta">
                <strong>Fee Receipt</strong>
                <span><?php echo $copy_label; ?></span>
                <small><?php echo $school_address; ?></small>
                <small>Phone: <?php echo $school_contact; ?> | Email: <?php echo $school_email; ?></small>
            </div>
        </header>

        <div class="gr-strip">
            <div>
                <span>Receipt No</span>
                <strong><?php echo $receipt_no; ?></strong>
            </div>
            <div>
                <span>Payment ID</span>
                <strong><?php echo $feeList->id . '/' . $sub_invoice_id; ?></strong>
            </div>
            <div>
                <span>Date</span>
                <strong><?php echo date($this->customlib->getSchoolDateFormat(), $this->customlib->dateyyyymmddTodateformat($receipt_date)); ?></strong>
            </div>
        </div>

        <div class="gr-info-grid">
            <div class="gr-info-card">
                <h2>Student Details</h2>
                <dl>
                    <dt>Name</dt><dd><?php echo $student_name; ?></dd>
                    <dt>Admission No</dt><dd><?php echo $feeList->admission_no; ?></dd>
                    <dt>Class</dt><dd><?php echo $feeList->class . ' (' . $feeList->section . ')'; ?></dd>
                    <dt>Father Name</dt><dd><?php echo !empty($student['father_name']) ? $student['father_name'] : '-'; ?></dd>
                </dl>
            </div>
            <div class="gr-info-card">
                <h2>Payment Details</h2>
                <dl>
                    <dt>Collected By</dt><dd><?php echo $collected_by; ?></dd>
                    <dt>Mode</dt><dd><?php echo $payment_mode; ?></dd>
                    <dt>Fee Group</dt><dd><?php echo $feeList->name; ?></dd>
                    <dt>Fee Code</dt><dd><?php echo $feeList->code; ?></dd>
                </dl>
            </div>
        </div>

        <table class="gr-table">
            <thead>
                <tr>
                    <th>Fee Particular</th>
                    <th>Code</th>
                    <th>Mode</th>
                    <th class="gr-right">Amount</th>
                    <th class="gr-right">Discount</th>
                    <th class="gr-right">Fine</th>
                    <th class="gr-right">Received</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($record) { ?>
                    <tr>
                        <td>
                            <strong><?php echo $feeList->name; ?></strong>
                            <span><?php echo $feeList->type; ?></span>
                        </td>
                        <td><?php echo $feeList->code; ?></td>
                        <td><?php echo $payment_mode; ?></td>
                        <td class="gr-right"><?php echo $currency_symbol . number_format($paid_amount, 2); ?></td>
                        <td class="gr-right"><?php echo $currency_symbol . number_format($discount, 2); ?></td>
                        <td class="gr-right"><?php echo $currency_symbol . number_format($fine, 2); ?></td>
                        <td class="gr-right gr-strong"><?php echo $currency_symbol . number_format($net_received, 2); ?></td>
                    </tr>
                <?php } else { ?>
                    <tr>
                        <td colspan="7" class="gr-empty">No transaction found for this receipt.</td>
                    </tr>
                <?php } ?>
            </tbody>
        </table>

        <div class="gr-summary">
            <div class="gr-note">
                <strong>Remarks</strong>
                <p><?php echo $description; ?></p>
                <small>This receipt is system generated and valid without a physical signature.</small>
            </div>
            <div class="gr-total">
                <div><span>Paid Amount</span><strong><?php echo $currency_symbol . number_format($paid_amount, 2); ?></strong></div>
                <div><span>Discount</span><strong><?php echo $currency_symbol . number_format($discount, 2); ?></strong></div>
                <div><span>Fine</span><strong><?php echo $currency_symbol . number_format($fine, 2); ?></strong></div>
                <div class="gr-grand"><span>Total Received</span><strong><?php echo $currency_symbol . number_format($net_received, 2); ?></strong></div>
            </div>
        </div>

        <footer class="gr-footer">
            <div>
                <span>Prepared by GyanRank ERP</span>
                <strong><?php echo date('d M Y, h:i A'); ?></strong>
            </div>
            <div class="gr-sign">
                <img src="<?php echo $signature_url; ?>" alt="">
                <span>Authorized Signature</span>
            </div>
        </footer>
    </section>
    <?php
};
?>
<!doctype html>
<html lang="en">
    <head>
        <meta charset="utf-8">
        <title>GyanRank Fee Receipt</title>
        <style>
            * { box-sizing: border-box; }
            body {
                margin: 0;
                background: #f4f7fb;
                color: #172033;
                font-family: Arial, Helvetica, sans-serif;
                font-size: 13px;
            }
            .gr-page {
                width: 1100px;
                max-width: 100%;
                margin: 20px auto;
                padding: 0 14px;
            }
            .gr-receipt {
                position: relative;
                overflow: hidden;
                min-height: 690px;
                padding: 28px 34px 24px;
                background: #ffffff;
                border: 2px solid #06345f;
                border-radius: 16px;
                box-shadow: 0 18px 40px rgba(6, 52, 95, 0.16);
            }
            .gr-receipt + .gr-receipt { margin-top: 24px; }
            .gr-watermark {
                position: absolute;
                right: 42px;
                bottom: 58px;
                width: 210px;
                opacity: 0.045;
            }
            .gr-watermark img { width: 100%; }
            .gr-header {
                position: relative;
                display: flex;
                justify-content: space-between;
                gap: 24px;
                padding-bottom: 18px;
                border-bottom: 4px solid #f68a00;
            }
            .gr-brand {
                display: flex;
                align-items: center;
                gap: 18px;
            }
            .gr-brand img {
                width: 210px;
                height: 62px;
                object-fit: contain;
            }
            .gr-brand h1 {
                margin: 0;
                color: #06345f;
                font-size: 26px;
                line-height: 1.1;
                letter-spacing: 0;
            }
            .gr-brand p {
                margin: 7px 0 0;
                color: #f68a00;
                font-weight: 700;
            }
            .gr-school-meta {
                min-width: 330px;
                text-align: right;
                color: #24344d;
            }
            .gr-school-meta strong {
                display: block;
                color: #06345f;
                font-size: 28px;
                text-transform: uppercase;
            }
            .gr-school-meta span {
                display: inline-block;
                margin: 7px 0 9px;
                padding: 5px 12px;
                color: #ffffff;
                background: #06345f;
                border-radius: 999px;
                font-size: 12px;
                font-weight: 700;
                text-transform: uppercase;
            }
            .gr-school-meta small {
                display: block;
                line-height: 1.45;
            }
            .gr-strip {
                display: grid;
                grid-template-columns: repeat(3, 1fr);
                margin: 18px 0;
                border: 1px solid #d8e3ef;
                border-radius: 12px;
                overflow: hidden;
            }
            .gr-strip div {
                padding: 13px 16px;
                background: #f7fbff;
                border-right: 1px solid #d8e3ef;
            }
            .gr-strip div:last-child { border-right: 0; }
            .gr-strip span,
            .gr-info-card dt,
            .gr-total span {
                display: block;
                color: #667085;
                font-size: 11px;
                font-weight: 700;
                text-transform: uppercase;
            }
            .gr-strip strong {
                display: block;
                margin-top: 5px;
                color: #06345f;
                font-size: 16px;
            }
            .gr-info-grid {
                display: grid;
                grid-template-columns: 1fr 1fr;
                gap: 16px;
                margin-bottom: 18px;
            }
            .gr-info-card {
                padding: 16px;
                background: #fbfdff;
                border: 1px solid #d8e3ef;
                border-radius: 12px;
            }
            .gr-info-card h2 {
                margin: 0 0 12px;
                color: #06345f;
                font-size: 16px;
            }
            .gr-info-card dl {
                display: grid;
                grid-template-columns: 125px 1fr;
                gap: 9px 14px;
                margin: 0;
            }
            .gr-info-card dd {
                margin: 0;
                color: #172033;
                font-weight: 700;
            }
            .gr-table {
                width: 100%;
                border-collapse: collapse;
                margin-top: 6px;
                overflow: hidden;
                border: 1px solid #d8e3ef;
                border-radius: 12px;
            }
            .gr-table th {
                padding: 13px 12px;
                color: #ffffff;
                background: #06345f;
                font-size: 12px;
                text-align: left;
                text-transform: uppercase;
            }
            .gr-table td {
                padding: 14px 12px;
                border-top: 1px solid #e7eef6;
                vertical-align: top;
            }
            .gr-table td span {
                display: block;
                margin-top: 4px;
                color: #667085;
                font-size: 12px;
            }
            .gr-right { text-align: right !important; }
            .gr-strong { color: #06345f; font-weight: 800; }
            .gr-empty { color: #b42318; text-align: center; }
            .gr-summary {
                display: grid;
                grid-template-columns: 1fr 330px;
                gap: 18px;
                margin-top: 18px;
            }
            .gr-note {
                padding: 15px 16px;
                background: #fff8ef;
                border-left: 5px solid #f68a00;
                border-radius: 12px;
            }
            .gr-note strong {
                color: #06345f;
                text-transform: uppercase;
            }
            .gr-note p {
                min-height: 24px;
                margin: 8px 0 10px;
            }
            .gr-note small { color: #667085; }
            .gr-total {
                padding: 14px 16px;
                background: #f7fbff;
                border: 1px solid #d8e3ef;
                border-radius: 12px;
            }
            .gr-total div {
                display: flex;
                justify-content: space-between;
                gap: 18px;
                padding: 8px 0;
                border-bottom: 1px solid #d8e3ef;
            }
            .gr-total div:last-child { border-bottom: 0; }
            .gr-total strong { font-size: 14px; }
            .gr-total .gr-grand {
                margin: 6px -8px -4px;
                padding: 12px 8px;
                color: #ffffff;
                background: #06345f;
                border-radius: 10px;
            }
            .gr-total .gr-grand span,
            .gr-total .gr-grand strong {
                color: #ffffff;
                font-size: 16px;
            }
            .gr-footer {
                display: flex;
                justify-content: space-between;
                align-items: flex-end;
                margin-top: 22px;
                padding-top: 14px;
                border-top: 1px dashed #b8c7d8;
            }
            .gr-footer span {
                display: block;
                color: #667085;
                font-size: 12px;
            }
            .gr-footer strong {
                display: block;
                margin-top: 4px;
                color: #06345f;
            }
            .gr-sign {
                min-width: 230px;
                text-align: center;
            }
            .gr-sign img {
                width: 92px;
                height: 34px;
                object-fit: contain;
            }
            .gr-sign span {
                padding-top: 8px;
                border-top: 2px solid #06345f;
                color: #06345f;
                font-weight: 700;
            }
            .gr-copy-divider {
                height: 18px;
            }
            @media print {
                @page { size: A4 portrait; margin: 6mm; }
                body {
                    background: #ffffff;
                    font-size: 9px;
                    -webkit-print-color-adjust: exact;
                    print-color-adjust: exact;
                }
                .gr-page {
                    width: 198mm;
                    height: 285mm;
                    margin: 0;
                    padding: 0;
                }
                .gr-receipt {
                    height: 137mm;
                    min-height: 0;
                    padding: 5mm 6mm 4mm;
                    border-width: 1.2px;
                    border-radius: 3mm;
                    box-shadow: none;
                    page-break-inside: avoid;
                }
                .gr-receipt + .gr-receipt { margin-top: 0; }
                .gr-copy-divider {
                    height: 5mm;
                    page-break-before: auto;
                    page-break-after: auto;
                }
                .gr-watermark {
                    right: 8mm;
                    bottom: 8mm;
                    width: 38mm;
                }
                .gr-header {
                    gap: 5mm;
                    padding-bottom: 3mm;
                    border-bottom-width: 1mm;
                }
                .gr-brand { gap: 3mm; }
                .gr-brand img {
                    width: 40mm;
                    height: 13mm;
                }
                .gr-brand h1 {
                    font-size: 14pt;
                    line-height: 1.05;
                }
                .gr-brand p {
                    margin-top: 1mm;
                    font-size: 6.5pt;
                }
                .gr-school-meta {
                    min-width: 67mm;
                    max-width: 70mm;
                }
                .gr-school-meta strong {
                    font-size: 14pt;
                }
                .gr-school-meta span {
                    margin: 1mm 0 1.5mm;
                    padding: 1mm 3mm;
                    font-size: 6pt;
                }
                .gr-school-meta small {
                    font-size: 5.8pt;
                    line-height: 1.25;
                }
                .gr-strip {
                    margin: 3mm 0;
                    border-radius: 2mm;
                }
                .gr-strip div {
                    padding: 2mm 2.5mm;
                }
                .gr-strip span,
                .gr-info-card dt,
                .gr-total span {
                    font-size: 5.8pt;
                }
                .gr-strip strong {
                    margin-top: 1mm;
                    font-size: 8.5pt;
                }
                .gr-info-grid {
                    gap: 3mm;
                    margin-bottom: 3mm;
                }
                .gr-info-card {
                    padding: 2.5mm;
                    border-radius: 2mm;
                }
                .gr-info-card h2 {
                    margin-bottom: 2mm;
                    font-size: 8.5pt;
                }
                .gr-info-card dl {
                    grid-template-columns: 25mm 1fr;
                    gap: 1.4mm 2mm;
                }
                .gr-info-card dd {
                    font-size: 6.8pt;
                }
                .gr-table {
                    margin-top: 1mm;
                    border-radius: 2mm;
                }
                .gr-table th {
                    padding: 2mm 2mm;
                    font-size: 6.2pt;
                }
                .gr-table td {
                    padding: 2.2mm 2mm;
                    font-size: 7pt;
                }
                .gr-table td span {
                    margin-top: 0.8mm;
                    font-size: 6pt;
                }
                .gr-summary {
                    grid-template-columns: 1fr 58mm;
                    gap: 3mm;
                    margin-top: 3mm;
                }
                .gr-note {
                    padding: 2.5mm;
                    border-left-width: 1mm;
                    border-radius: 2mm;
                }
                .gr-note p {
                    min-height: 0;
                    margin: 1.5mm 0 1.5mm;
                    font-size: 6.8pt;
                }
                .gr-note small {
                    font-size: 5.8pt;
                }
                .gr-total {
                    padding: 2mm 2.5mm;
                    border-radius: 2mm;
                }
                .gr-total div {
                    padding: 1.5mm 0;
                }
                .gr-total strong {
                    font-size: 7pt;
                }
                .gr-total .gr-grand {
                    margin: 1mm -1mm -0.5mm;
                    padding: 2mm 1mm;
                    border-radius: 1.8mm;
                }
                .gr-total .gr-grand span,
                .gr-total .gr-grand strong {
                    font-size: 8pt;
                }
                .gr-footer {
                    margin-top: 3mm;
                    padding-top: 2.5mm;
                }
                .gr-footer span {
                    font-size: 6pt;
                }
                .gr-footer strong {
                    margin-top: 0.8mm;
                    font-size: 6.5pt;
                }
                .gr-sign {
                    min-width: 45mm;
                }
                .gr-sign img {
                    width: 18mm;
                    height: 7mm;
                }
                .gr-sign span {
                    padding-top: 1.2mm;
                    border-top-width: 1px;
                    font-size: 6.2pt;
                }
            }
        </style>
    </head>
    <body>
        <main class="gr-page">
            <?php foreach ($copy_labels as $index => $copy_label) {
    if ($index > 0) {
        echo '<div class="gr-copy-divider"></div>';
    }
    $render_receipt($copy_label);
}?>
        </main>
    </body>
</html>
