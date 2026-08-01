<?php

function institute_master_counts(): array
{
    return [
        'States' => (int) (db()->query('SELECT COUNT(*) AS total FROM institution_states')->fetch_assoc()['total'] ?? 0),
        'Districts' => (int) (db()->query('SELECT COUNT(*) AS total FROM institution_districts')->fetch_assoc()['total'] ?? 0),
        'Boards' => (int) (db()->query('SELECT COUNT(*) AS total FROM institution_boards')->fetch_assoc()['total'] ?? 0),
        'Universities' => (int) (db()->query('SELECT COUNT(*) AS total FROM institution_universities')->fetch_assoc()['total'] ?? 0),
    ];
}

function institute_master_nav(string $current): void
{
    $items = [
        'requests' => ['label' => 'Requests', 'url' => app_url('sadmin/institute-manage')],
        'erp-accounts' => ['label' => 'ERP Accounts', 'url' => app_url('sadmin/institute-erp-accounts')],
        'erp-onboarding' => ['label' => 'ERP Onboarding', 'url' => app_url('sadmin/institute-erp-onboarding')],
        'erp-domains' => ['label' => 'ERP Domains', 'url' => app_url('sadmin/institute-erp-domains')],
        'erp-support' => ['label' => 'ERP Support', 'url' => app_url('sadmin/institute-erp-support')],
        'erp-plans' => ['label' => 'ERP Plans', 'url' => app_url('sadmin/institute-erp-plans')],
        'erp-backups' => ['label' => 'ERP Backups', 'url' => app_url('sadmin/institute-erp-backups')],
        'states' => ['label' => 'States', 'url' => app_url('sadmin/institute-states')],
        'districts' => ['label' => 'Districts', 'url' => app_url('sadmin/institute-districts')],
        'boards' => ['label' => 'Boards', 'url' => app_url('sadmin/institute-boards')],
        'universities' => ['label' => 'Universities', 'url' => app_url('sadmin/institute-universities')],
    ];
    echo '<nav class="settings-submenu institute-submenu" aria-label="Institute manage submenu">';
    foreach ($items as $key => $item) {
        $active = $current === $key ? ' active' : '';
        echo '<a class="settings-subitem' . $active . '" href="' . h($item['url']) . '"><span><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 6h16M4 12h16M4 18h10"></path></svg></span><strong>' . h($item['label']) . '</strong></a>';
    }
    echo '</nav>';
}

function institute_master_flash(): array
{
    $message = (string) ($_SESSION['institute_master_message'] ?? '');
    $error = (string) ($_SESSION['institute_master_error'] ?? '');
    unset($_SESSION['institute_master_message'], $_SESSION['institute_master_error']);
    return [$message, $error];
}

function institute_handle_master_post(string $type, string $redirectPath): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        return;
    }
    if (!verify_csrf($_POST['csrf_token'] ?? null)) {
        $_SESSION['institute_master_error'] = 'Security token expired.';
        redirect($redirectPath);
    }

    $name = trim((string) ($_POST['name'] ?? ''));
    $stateId = (int) ($_POST['state_id'] ?? 0);
    $recordId = (int) ($_POST['id'] ?? 0);
    $status = isset($_POST['status']) ? 1 : 0;
    if ($name === '') {
        $_SESSION['institute_master_error'] = 'Name is required.';
        redirect($redirectPath);
    }

    if ($type === 'state' && $recordId > 0) {
        $stmt = db()->prepare('UPDATE institution_states SET name = ?, status = ? WHERE id = ?');
        $stmt->bind_param('sii', $name, $status, $recordId);
    } elseif ($type === 'board' && $recordId > 0) {
        $stmt = db()->prepare('UPDATE institution_boards SET name = ?, status = ? WHERE id = ?');
        $stmt->bind_param('sii', $name, $status, $recordId);
    } elseif ($type === 'district' && $recordId > 0 && $stateId > 0) {
        $stmt = db()->prepare('UPDATE institution_districts SET state_id = ?, name = ?, status = ? WHERE id = ?');
        $stmt->bind_param('isii', $stateId, $name, $status, $recordId);
    } elseif ($type === 'university' && $recordId > 0 && $stateId > 0) {
        $stmt = db()->prepare('UPDATE institution_universities SET state_id = ?, name = ?, status = ? WHERE id = ?');
        $stmt->bind_param('isii', $stateId, $name, $status, $recordId);
    } elseif ($type === 'state') {
        $stmt = db()->prepare('INSERT IGNORE INTO institution_states (name, status) VALUES (?, ?)');
        $stmt->bind_param('si', $name, $status);
    } elseif ($type === 'board') {
        $stmt = db()->prepare('INSERT IGNORE INTO institution_boards (name, status) VALUES (?, ?)');
        $stmt->bind_param('si', $name, $status);
    } elseif ($type === 'district' && $stateId > 0) {
        $stmt = db()->prepare('INSERT IGNORE INTO institution_districts (state_id, name, status) VALUES (?, ?, ?)');
        $stmt->bind_param('isi', $stateId, $name, $status);
    } elseif ($type === 'university' && $stateId > 0) {
        $stmt = db()->prepare('INSERT IGNORE INTO institution_universities (state_id, name, status) VALUES (?, ?, ?)');
        $stmt->bind_param('isi', $stateId, $name, $status);
    } else {
        $_SESSION['institute_master_error'] = 'Please select state.';
        redirect($redirectPath);
    }

    try {
        $stmt->execute();
        $_SESSION['institute_master_message'] = $recordId > 0 ? 'Master record updated.' : ($stmt->affected_rows > 0 ? 'Master record added.' : 'Record already exists.');
    } catch (Throwable $e) {
        $_SESSION['institute_master_error'] = 'Record could not be saved. Please check duplicate name.';
    }
    redirect($redirectPath);
}

function institute_state_options(int $selected = 0): string
{
    $html = '';
    foreach (institution_rows('institution_states') as $state) {
        $isSelected = (int) $state['id'] === $selected ? ' selected' : '';
        $html .= '<option value="' . (int) $state['id'] . '"' . $isSelected . '>' . h($state['name']) . '</option>';
    }
    return $html;
}

function institute_master_status_badge(int $status): string
{
    return $status === 1 ? '<span class="edu-status success">Active</span>' : '<span class="edu-status danger">Inactive</span>';
}

function institute_master_edit_script(): void
{
    ?>
    <script>
    (() => {
        const modal = document.getElementById('edit-master');
        if (!modal) return;
        document.querySelectorAll('[data-master-edit]').forEach((button) => {
            button.addEventListener('click', () => {
                modal.querySelector('[name="id"]').value = button.dataset.id || '';
                modal.querySelector('[name="name"]').value = button.dataset.name || '';
                const status = modal.querySelector('[name="status"]');
                if (status) status.checked = button.dataset.status === '1';
                const state = modal.querySelector('[name="state_id"]');
                if (state && button.dataset.stateId) state.value = button.dataset.stateId;
            });
        });
    })();
    </script>
    <?php
}

function institute_master_page_styles(): void
{
    ?>
    <style>
        .sadmin-institute-main .institute-admin-page {
            padding-top: 1.25rem;
        }
        .sadmin-institute-main .institute-master-table {
            border: 0;
            border-radius: 4px;
            border-top: 3px solid #f68a00;
            background: #ffffff;
            box-shadow: 0 .35rem 1rem rgba(15, 23, 42, .12);
            overflow: hidden;
        }
        .sadmin-institute-main .institute-master-table .table-head {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
            padding: .75rem 1rem;
            border-bottom: 1px solid #dbe7f2;
            background: #ffffff;
        }
        .sadmin-institute-main .institute-master-table .table-head span {
            display: block;
            color: #f68a00;
            font-size: .68rem;
            font-weight: 800;
            letter-spacing: .03em;
            line-height: 1.1;
            text-transform: uppercase;
        }
        .sadmin-institute-main .institute-master-table .table-head h2 {
            margin: .2rem 0 .1rem;
            color: #102a43;
            font-size: 1.05rem;
            font-weight: 800;
            line-height: 1.15;
        }
        .sadmin-institute-main .institute-master-table .table-head small {
            color: #64748b;
            font-size: .72rem;
        }
        .sadmin-institute-main .institute-master-table .modal-button {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 2rem;
            padding: .35rem .75rem;
            border-radius: 3px;
            background: #0a3c66;
            color: #ffffff;
            font-size: .72rem;
            font-weight: 800;
            text-decoration: none;
            white-space: nowrap;
        }
        .sadmin-institute-main .settings-mini-table {
            width: 100%;
            overflow: hidden;
        }
        .sadmin-institute-main .settings-mini-table table {
            width: 100%;
            table-layout: fixed;
            border-collapse: collapse;
        }
        .sadmin-institute-main .settings-mini-table th,
        .sadmin-institute-main .settings-mini-table td {
            padding: .45rem .65rem;
            border-bottom: 1px solid #dbe7f2;
            color: #102a43;
            font-size: .75rem;
            line-height: 1.25;
            vertical-align: middle;
            overflow-wrap: anywhere;
        }
        .sadmin-institute-main .settings-mini-table th {
            background: #edf5fb;
            color: #003763;
            font-size: .68rem;
            font-weight: 900;
            letter-spacing: .02em;
            text-transform: uppercase;
        }
        .sadmin-institute-main .settings-mini-table td strong {
            color: #102a43;
            font-size: .76rem;
            font-weight: 800;
        }
        .sadmin-institute-main .settings-mini-table th:first-child {
            width: 7%;
        }
        .sadmin-institute-main .settings-mini-table th:last-child {
            width: 11%;
            text-align: center;
        }
        .sadmin-institute-main .settings-mini-table td:last-child {
            text-align: center;
        }
        .sadmin-institute-main .table-edit-icon {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 1.7rem;
            padding: .2rem .55rem;
            border-radius: 3px;
            background: #fff3e3;
            color: #f68a00;
            font-size: .7rem;
            font-weight: 800;
            text-decoration: none;
        }
        .sadmin-institute-main .table-edit-icon:hover {
            background: #f68a00;
            color: #ffffff;
        }
        .sadmin-institute-main .modal-overlay:target {
            display: flex;
        }
        .sadmin-institute-main .modal-overlay {
            display: none;
            position: fixed;
            inset: 0;
            z-index: 6500;
            align-items: center;
            justify-content: center;
            padding: 1rem;
            background: rgba(3, 21, 38, .48);
        }
        .sadmin-institute-main .compact-master-modal {
            width: min(520px, 96vw);
            border: 1px solid #c9d9e8;
            border-top: 4px solid #f68a00;
            border-radius: 6px;
            background: #ffffff;
            box-shadow: 0 18px 45px rgba(0, 0, 0, .25);
            overflow: hidden;
        }
        .sadmin-institute-main .modal-head {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
            padding: .8rem 1rem;
            border-bottom: 1px solid #dbe7f2;
            background: #f6f9fc;
        }
        .sadmin-institute-main .modal-head h2 {
            margin: 0;
            color: #082f55;
            font-size: .95rem;
            font-weight: 800;
        }
        .sadmin-institute-main .modal-head a {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 32px;
            min-height: 32px;
            border: 1px solid #c9d9e8;
            border-radius: 3px;
            background: #ffffff;
            color: #0a3c66;
            font-size: 1.25rem;
            text-decoration: none;
        }
        .sadmin-institute-main .compact-master-modal .form-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: .75rem;
            padding: 1rem;
        }
        .sadmin-institute-main .compact-master-modal label {
            display: grid;
            gap: .3rem;
            margin: 0;
            color: #0f172a;
            font-size: .74rem;
            font-weight: 800;
        }
        .sadmin-institute-main .compact-master-modal .span-2 {
            grid-column: 1 / -1;
        }
        .sadmin-institute-main .compact-master-modal input:not([type="checkbox"]),
        .sadmin-institute-main .compact-master-modal select {
            min-height: 2.05rem;
            width: 100%;
            border: 1px solid #c9d9e8;
            border-radius: 3px;
            padding: .35rem .55rem;
            color: #102a43;
            font-size: .76rem;
        }
        .sadmin-institute-main .compact-master-modal .switch-field {
            display: flex;
            align-items: center;
            justify-content: space-between;
            min-height: 2.05rem;
            padding: .35rem .55rem;
            border: 1px solid #d6e4f0;
            border-radius: 3px;
            background: #f8fbfd;
        }
        .sadmin-institute-main .compact-master-modal .switch-field > span {
            position: relative;
            display: inline-flex;
            align-items: center;
            width: 38px;
            height: 20px;
            margin: 0;
        }
        .sadmin-institute-main .compact-master-modal .switch-field input[type="checkbox"] {
            position: absolute;
            inset: 0;
            width: 100%;
            height: 100%;
            margin: 0;
            opacity: 0;
            cursor: pointer;
            z-index: 2;
        }
        .sadmin-institute-main .compact-master-modal .switch-field b {
            position: absolute;
            inset: 0;
            border-radius: 999px;
            background: #cbd5e1;
            transition: .18s ease;
        }
        .sadmin-institute-main .compact-master-modal .switch-field b::after {
            content: "";
            position: absolute;
            top: 3px;
            left: 3px;
            width: 14px;
            height: 14px;
            border-radius: 50%;
            background: #ffffff;
            box-shadow: 0 1px 3px rgba(15, 23, 42, .25);
            transition: .18s ease;
        }
        .sadmin-institute-main .compact-master-modal .switch-field input[type="checkbox"]:checked + b {
            background: #0a3c66;
        }
        .sadmin-institute-main .compact-master-modal .switch-field input[type="checkbox"]:checked + b::after {
            transform: translateX(18px);
        }
        .sadmin-institute-main .compact-master-modal .modal-actions {
            display: flex;
            justify-content: flex-end;
            padding: .75rem 1rem;
            border-top: 1px solid #dbe7f2;
        }
        .sadmin-institute-main .compact-master-modal .modal-actions button {
            min-height: 2rem;
            border: 0;
            border-radius: 3px;
            padding: .35rem 1rem;
            background: #0a3c66;
            color: #ffffff;
            font-size: .74rem;
            font-weight: 800;
        }
        @media (max-width: 767.98px) {
            .sadmin-institute-main .institute-master-table .table-head,
            .sadmin-institute-main .compact-master-modal .form-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
    <?php
}
