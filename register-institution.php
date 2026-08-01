<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/institution_module.php';
start_secure_session();
institution_ensure_tables();

function institution_json(array $payload): void
{
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

if (isset($_GET['ajax'])) {
    if ($_GET['ajax'] === 'districts') {
        institution_json(['ok' => true, 'rows' => institution_rows('institution_districts')]);
    }
    if ($_GET['ajax'] === 'universities') {
        institution_json(['ok' => true, 'rows' => institution_rows('institution_universities')]);
    }
    if ($_GET['ajax'] === 'send_otp') {
        $mobile = preg_replace('/\D+/', '', (string) ($_GET['mobile'] ?? ''));
        if (!preg_match('/^\d{10}$/', $mobile)) {
            institution_json(['ok' => false, 'error' => 'Enter valid 10 digit mobile number.']);
        }
        $otp = (string) random_int(100000, 999999);
        $_SESSION['institution_mobile_otp'] = password_hash($otp, PASSWORD_DEFAULT);
        $_SESSION['institution_mobile_otp_mobile'] = $mobile;
        $_SESSION['institution_mobile_otp_expires'] = time() + 600;
        $_SESSION['institution_mobile_verified'] = false;
        $host = strtolower((string) ($_SERVER['HTTP_HOST'] ?? ''));
        $debug = (str_contains($host, 'localhost') || str_contains($host, '127.0.0.1')) ? $otp : '';
        institution_json(['ok' => true, 'message' => 'OTP generated. Verify before submit.', 'debug_otp' => $debug]);
    }
    if ($_GET['ajax'] === 'verify_otp') {
        $mobile = preg_replace('/\D+/', '', (string) ($_GET['mobile'] ?? ''));
        $otp = preg_replace('/\D+/', '', (string) ($_GET['otp'] ?? ''));
        $hash = (string) ($_SESSION['institution_mobile_otp'] ?? '');
        $sessionMobile = (string) ($_SESSION['institution_mobile_otp_mobile'] ?? '');
        $expires = (int) ($_SESSION['institution_mobile_otp_expires'] ?? 0);
        if ($mobile === '' || $mobile !== $sessionMobile || $expires < time() || !password_verify($otp, $hash)) {
            institution_json(['ok' => false, 'error' => 'OTP invalid or expired.']);
        }
        $_SESSION['institution_mobile_verified'] = true;
        $_SESSION['institution_mobile_verified_mobile'] = $mobile;
        institution_json(['ok' => true, 'message' => 'Mobile verified: +91 ' . $mobile]);
    }
}

$states = institution_rows('institution_states');
$boards = institution_rows('institution_boards');
$message = '';
$error = '';
$form = [
    'institution_type' => 'school',
    'contact_name' => '',
    'email' => '',
    'mobile' => '',
    'institution_name' => '',
    'state_id' => 0,
    'district_id' => 0,
    'board_id' => 0,
    'university_id' => 0,
    'state_other' => '',
    'district_other' => '',
    'board_other' => '',
    'university_other' => '',
    'address' => '',
    'pincode' => '',
];

$requestedType = (string) ($_GET['type'] ?? '');
if ($_SERVER['REQUEST_METHOD'] !== 'POST' && array_key_exists($requestedType, institution_type_options())) {
    $form['institution_type'] = $requestedType;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $form['institution_type'] = array_key_exists((string) ($_POST['institution_type'] ?? ''), institution_type_options()) ? (string) $_POST['institution_type'] : 'school';
    $form['contact_name'] = trim((string) ($_POST['contact_name'] ?? ''));
    $form['email'] = trim((string) ($_POST['email'] ?? ''));
    $form['mobile'] = preg_replace('/\D+/', '', (string) ($_POST['mobile'] ?? ''));
    $form['institution_name'] = trim((string) ($_POST['institution_name'] ?? ''));
    $form['state_id'] = (int) ($_POST['state_id'] ?? 0);
    $form['district_id'] = (int) ($_POST['district_id'] ?? 0);
    $form['board_id'] = (int) ($_POST['board_id'] ?? 0);
    $form['university_id'] = (int) ($_POST['university_id'] ?? 0);
    $form['state_other'] = trim((string) ($_POST['state_other'] ?? ''));
    $form['district_other'] = trim((string) ($_POST['district_other'] ?? ''));
    $form['board_other'] = trim((string) ($_POST['board_other'] ?? ''));
    $form['university_other'] = trim((string) ($_POST['university_other'] ?? ''));
    $form['address'] = trim((string) ($_POST['address'] ?? ''));
    $form['pincode'] = trim((string) ($_POST['pincode'] ?? ''));

    $state = [];
    $district = [];
    $board = [];
    $university = [];
    if ($form['state_id'] === -1 && $form['state_other'] !== '') {
        $stmt = db()->prepare('INSERT IGNORE INTO institution_states (name, status) VALUES (?, 1)');
        $stmt->bind_param('s', $form['state_other']);
        $stmt->execute();
        $form['state_id'] = institution_state_id($form['state_other']);
    }
    if ($form['state_id'] > 0) {
        $stmt = db()->prepare('SELECT id, name FROM institution_states WHERE id = ? AND status = 1 LIMIT 1');
        $stmt->bind_param('i', $form['state_id']);
        $stmt->execute();
        $state = $stmt->get_result()->fetch_assoc() ?: [];
    }
    if ($form['district_id'] === -1 && $form['district_other'] !== '' && $form['state_id'] > 0) {
        $stmt = db()->prepare('INSERT IGNORE INTO institution_districts (state_id, name, status) VALUES (?, ?, 1)');
        $stmt->bind_param('is', $form['state_id'], $form['district_other']);
        $stmt->execute();
        $stmt = db()->prepare('SELECT id, name FROM institution_districts WHERE name = ? AND status = 1 ORDER BY id ASC LIMIT 1');
        $stmt->bind_param('s', $form['district_other']);
        $stmt->execute();
        $district = $stmt->get_result()->fetch_assoc() ?: [];
        $form['district_id'] = (int) ($district['id'] ?? 0);
    } elseif ($form['district_id'] > 0) {
        $stmt = db()->prepare('SELECT id, name FROM institution_districts WHERE id = ? AND status = 1 LIMIT 1');
        $stmt->bind_param('i', $form['district_id']);
        $stmt->execute();
        $district = $stmt->get_result()->fetch_assoc() ?: [];
    }
    if ($form['board_id'] === -1 && $form['board_other'] !== '') {
        $stmt = db()->prepare('INSERT IGNORE INTO institution_boards (name, status) VALUES (?, 1)');
        $stmt->bind_param('s', $form['board_other']);
        $stmt->execute();
        $stmt = db()->prepare('SELECT id, name FROM institution_boards WHERE name = ? LIMIT 1');
        $stmt->bind_param('s', $form['board_other']);
        $stmt->execute();
        $board = $stmt->get_result()->fetch_assoc() ?: [];
        $form['board_id'] = (int) ($board['id'] ?? 0);
    } elseif ($form['board_id'] > 0) {
        $stmt = db()->prepare('SELECT id, name FROM institution_boards WHERE id = ? AND status = 1 LIMIT 1');
        $stmt->bind_param('i', $form['board_id']);
        $stmt->execute();
        $board = $stmt->get_result()->fetch_assoc() ?: [];
    }
    if ($form['university_id'] === -1 && $form['university_other'] !== '' && $form['state_id'] > 0) {
        $stmt = db()->prepare('INSERT IGNORE INTO institution_universities (state_id, name, status) VALUES (?, ?, 1)');
        $stmt->bind_param('is', $form['state_id'], $form['university_other']);
        $stmt->execute();
        $stmt = db()->prepare('SELECT id, name FROM institution_universities WHERE name = ? AND status = 1 ORDER BY id ASC LIMIT 1');
        $stmt->bind_param('s', $form['university_other']);
        $stmt->execute();
        $university = $stmt->get_result()->fetch_assoc() ?: [];
        $form['university_id'] = (int) ($university['id'] ?? 0);
    } elseif ($form['university_id'] > 0) {
        $stmt = db()->prepare('SELECT id, name FROM institution_universities WHERE id = ? AND status = 1 LIMIT 1');
        $stmt->bind_param('i', $form['university_id']);
        $stmt->execute();
        $university = $stmt->get_result()->fetch_assoc() ?: [];
    }

    if (!verify_csrf($_POST['csrf_token'] ?? null)) {
        $error = 'Security token expired. Please try again.';
    } elseif ($form['contact_name'] === '' || $form['email'] === '' || $form['institution_name'] === '' || !$state || !$district || $form['address'] === '' || !preg_match('/^\d{6}$/', $form['pincode'])) {
        $error = 'Fill all required fields with valid address and 6 digit pincode.';
    } elseif (!filter_var($form['email'], FILTER_VALIDATE_EMAIL) || !preg_match('/^\d{10}$/', $form['mobile'])) {
        $error = 'Enter valid email and 10 digit mobile number.';
    } elseif ($form['institution_type'] === 'school' && !$board) {
        $error = 'Please select board for School / College.';
    } elseif ($form['institution_type'] === 'degree_college' && !$university) {
        $error = 'Please select university for Degree College.';
    } else {
        $boardId = $board ? (int) $board['id'] : 0;
        $boardName = (string) ($board['name'] ?? '');
        $universityId = $university ? (int) $university['id'] : 0;
        $universityName = (string) ($university['name'] ?? '');
        $stateName = (string) $state['name'];
        $districtName = (string) $district['name'];
        $stmt = db()->prepare("INSERT INTO institution_registration_requests
            (institution_type, contact_name, email, mobile, institution_name, state_id, state_name, district_id, district_name, board_id, board_name, university_id, university_name, address, pincode)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param(
            'sssssisisisisss',
            $form['institution_type'],
            $form['contact_name'],
            $form['email'],
            $form['mobile'],
            $form['institution_name'],
            $form['state_id'],
            $stateName,
            $form['district_id'],
            $districtName,
            $boardId,
            $boardName,
            $universityId,
            $universityName,
            $form['address'],
            $form['pincode']
        );
        $stmt->execute();
        $message = 'Registration request submitted. Admin will review it from Institute Manage.';
        foreach ($form as $key => $value) {
            $form[$key] = is_int($value) ? 0 : '';
        }
        $form['institution_type'] = 'school';
    }
}

$pageTitle = 'Institute Registration';
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= h($pageTitle); ?> - <?= h(app_name()); ?></title>
    <link rel="stylesheet" href="<?= app_url('assets/css/login.css'); ?>">
</head>
<body class="legal-page">
    <?php require __DIR__ . '/includes/public_header.php'; ?>
    <main class="legal-shell institution-register-page">
        <section class="institution-hero">
            <span>3 Category Institution Onboarding</span>
            <h1>Register Your Institution</h1>
            <p>Select School/College, Degree College, or Institute/Coaching Center. After admin approval, your request moves into Institute Manage for review and setup.</p>
        </section>
        <?php if ($message): ?><div class="form-alert form-alert-success"><?= h($message); ?></div><?php endif; ?>
        <?php if ($error): ?><div class="form-alert form-alert-error"><?= h($error); ?></div><?php endif; ?>
        <div class="institution-register-layout">
            <aside class="institution-side-panel">
                <span>How it works</span>
                <h2>Secure request, clean review, separate data.</h2>
                <p>Your school/institute request stays separate from the education LMS course data.</p>
                <div class="institution-step-list">
                    <div><b>1</b><strong>Choose category</strong><small>School, Degree College, or Institute.</small></div>
                    <div><b>2</b><strong>Validate details</strong><small>Email, mobile, state and affiliation are checked.</small></div>
                    <div><b>3</b><strong>Admin review</strong><small>Request appears in Institute Manage.</small></div>
                </div>
                <div class="institution-login-cta">
                    <small>Already approved?</small>
                    <a href="<?= h(app_url('institute-login')); ?>">Institute Login</a>
                </div>
            </aside>
            <form class="institution-form" method="post" data-base-url="<?= h(app_url('register-institution')); ?>">
                <input type="hidden" name="csrf_token" value="<?= h(csrf_token()); ?>">
                <div class="form-section-head span-2"><span>Contact</span><h2>Registration Details</h2></div>
                <label class="span-2">Institution Type
                    <select name="institution_type" id="institutionType">
                        <?php foreach (institution_type_options() as $key => $label): ?>
                            <option value="<?= h($key); ?>" <?= $form['institution_type'] === $key ? 'selected' : ''; ?>><?= h($label); ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label>Contact Name<input name="contact_name" value="<?= h($form['contact_name']); ?>" required></label>
                <label>Email<input type="email" name="email" value="<?= h($form['email']); ?>" placeholder="official@example.com" required></label>
                <label>Mobile Number<input id="mobileInput" name="mobile" inputmode="numeric" maxlength="10" minlength="10" pattern="[0-9]{10}" value="<?= h($form['mobile']); ?>" placeholder="10 digit mobile" required></label>
                <div class="form-section-head span-2"><span>Institution</span><h2>Institute Information</h2></div>
                <label class="span-2"><span id="institutionNameLabel">School / College Name</span><input name="institution_name" value="<?= h($form['institution_name']); ?>" required></label>
                <label>State
                    <select name="state_id" id="stateSelect" required>
                        <option value="0">Select State</option>
                        <?php foreach ($states as $state): ?>
                            <option value="<?= (int) $state['id']; ?>" <?= (int) $form['state_id'] === (int) $state['id'] ? 'selected' : ''; ?>><?= h($state['name']); ?></option>
                        <?php endforeach; ?>
                        <option value="-1" <?= (int) $form['state_id'] === -1 ? 'selected' : ''; ?>>Other / Not in list</option>
                    </select>
                    <input class="manual-field" id="stateOther" name="state_other" placeholder="Enter state / UT name" value="<?= h($form['state_other']); ?>">
                </label>
                <label>City / District
                    <select name="district_id" id="districtSelect" data-selected="<?= (int) $form['district_id']; ?>" required><option value="0">Loading cities...</option></select>
                    <input class="manual-field" id="districtOther" name="district_other" placeholder="Enter city / district name" value="<?= h($form['district_other']); ?>">
                </label>
                <label id="boardField" class="span-2">Board
                    <select name="board_id" id="boardSelect">
                        <option value="0">Select Board</option>
                        <?php foreach ($boards as $board): ?>
                            <option value="<?= (int) $board['id']; ?>" <?= (int) $form['board_id'] === (int) $board['id'] ? 'selected' : ''; ?>><?= h($board['name']); ?></option>
                        <?php endforeach; ?>
                        <option value="-1" <?= (int) $form['board_id'] === -1 ? 'selected' : ''; ?>>Other / Not in list</option>
                    </select>
                    <input class="manual-field" id="boardOther" name="board_other" placeholder="Enter board name" value="<?= h($form['board_other']); ?>">
                </label>
                <label id="universityField" class="span-2">University
                    <select name="university_id" id="universitySelect" data-selected="<?= (int) $form['university_id']; ?>"><option value="0">Loading universities...</option></select>
                    <input class="manual-field" id="universityOther" name="university_other" placeholder="Enter university name" value="<?= h($form['university_other']); ?>">
                </label>
                <label class="span-2">Address<textarea name="address" rows="3" required><?= h($form['address']); ?></textarea></label>
                <label>Pincode<input name="pincode" inputmode="numeric" maxlength="6" value="<?= h($form['pincode']); ?>" required></label>
                <div class="institution-submit span-2">
                    <button type="submit">Submit Registration Request</button>
                </div>
            </form>
        </div>
    </main>
    <?php require __DIR__ . '/includes/public_footer.php'; ?>
    <script>
    (() => {
        const form = document.querySelector('.institution-form');
        const base = form?.dataset.baseUrl || '';
        const type = document.getElementById('institutionType');
        const state = document.getElementById('stateSelect');
        const district = document.getElementById('districtSelect');
        const boardField = document.getElementById('boardField');
        const universityField = document.getElementById('universityField');
        const stateOther = document.getElementById('stateOther');
        const districtOther = document.getElementById('districtOther');
        const board = document.getElementById('boardSelect');
        const boardOther = document.getElementById('boardOther');
        const universityOther = document.getElementById('universityOther');
        const university = document.getElementById('universitySelect');
        const nameLabel = document.getElementById('institutionNameLabel');
        const mobile = document.getElementById('mobileInput');
        const syncType = () => {
            const value = type.value;
            boardField.style.display = value === 'school' ? '' : 'none';
            universityField.style.display = value === 'degree_college' ? '' : 'none';
            board.required = value === 'school';
            university.required = value === 'degree_college';
            nameLabel.textContent = value === 'degree_college' ? 'Degree College Name' : value === 'institute' ? 'Institute / Coaching Center Name' : 'School / College Name';
            syncManual();
        };
        const fill = (select, rows, placeholder, selectedValue = '0', appendOther = true) => {
            select.innerHTML = `<option value="0">${placeholder}</option>`;
            rows.forEach((row) => {
                const option = document.createElement('option');
                option.value = row.id;
                option.textContent = row.name;
                select.appendChild(option);
            });
            if (appendOther) {
                select.insertAdjacentHTML('beforeend', '<option value="-1">Other / Not in list</option>');
            }
            if (selectedValue && [...select.options].some((option) => option.value === selectedValue)) {
                select.value = selectedValue;
            }
        };
        const syncManual = () => {
            const isManualState = state.value === '-1';
            const needsBoard = type.value === 'school';
            const needsUniversity = type.value === 'degree_college';
            stateOther.classList.toggle('show', isManualState);
            districtOther.classList.toggle('show', district.value === '-1');
            boardOther.classList.toggle('show', board.value === '-1' && needsBoard);
            universityOther.classList.toggle('show', university.value === '-1' && needsUniversity);
            stateOther.required = isManualState;
            districtOther.required = district.value === '-1';
            boardOther.required = board.value === '-1' && needsBoard;
            universityOther.required = university.value === '-1' && needsUniversity;
        };
        const loadMasterData = async () => {
            const [districtRes, universityRes] = await Promise.all([
                fetch(`${base}?ajax=districts`),
                fetch(`${base}?ajax=universities`)
            ]);
            fill(district, (await districtRes.json()).rows || [], 'Select City / District', district.dataset.selected || '0');
            fill(university, (await universityRes.json()).rows || [], 'Select University', university.dataset.selected || '0');
            district.dataset.selected = '0';
            university.dataset.selected = '0';
            syncManual();
        };
        mobile.addEventListener('input', () => {
            mobile.value = String(mobile.value || '').replace(/\D+/g, '').slice(0, 10);
        });
        type.addEventListener('change', syncType);
        state.addEventListener('change', syncManual);
        district.addEventListener('change', syncManual);
        board.addEventListener('change', syncManual);
        university.addEventListener('change', syncManual);
        syncType();
        loadMasterData();
    })();
    </script>
</body>
</html>
