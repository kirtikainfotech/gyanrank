<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/important_pages.php';
start_secure_session();

function public_instructor_role_id(): int
{
    $stmt = db()->prepare("SELECT id FROM roles WHERE slug = 'instructor' LIMIT 1");
    $stmt->execute();
    $role = $stmt->get_result()->fetch_assoc();
    return (int) ($role['id'] ?? 0);
}

function ensure_public_instructor_profile_table(): void
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
    db()->query("
        CREATE TABLE IF NOT EXISTS referral_commissions (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            recipient_user_id INT UNSIGNED NOT NULL,
            referred_user_id INT UNSIGNED NOT NULL,
            source_type ENUM('instructor_signup','user_signup') NOT NULL,
            referral_code VARCHAR(40) NOT NULL,
            commission_type ENUM('percent','fixed') NOT NULL,
            commission_value DECIMAL(10,2) NOT NULL DEFAULT 0,
            status ENUM('pending','approved','paid','cancelled') NOT NULL DEFAULT 'pending',
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY referral_commissions_recipient_index (recipient_user_id),
            KEY referral_commissions_referred_index (referred_user_id),
            CONSTRAINT referral_commissions_recipient_foreign FOREIGN KEY (recipient_user_id) REFERENCES users (id) ON DELETE CASCADE,
            CONSTRAINT referral_commissions_referred_foreign FOREIGN KEY (referred_user_id) REFERENCES users (id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    db()->query("
        CREATE TABLE IF NOT EXISTS email_verifications (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id INT UNSIGNED NOT NULL,
            token_hash VARCHAR(128) NOT NULL,
            purpose ENUM('instructor_signup','user_signup') NOT NULL DEFAULT 'instructor_signup',
            expires_at DATETIME NOT NULL,
            verified_at DATETIME NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY email_verifications_token_unique (token_hash),
            KEY email_verifications_user_index (user_id),
            CONSTRAINT email_verifications_user_foreign FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
}

function new_referral_code(string $name): string
{
    $base = strtoupper(preg_replace('/[^A-Z0-9]/', '', substr($name, 0, 4))) ?: 'INS';
    return $base . random_int(10000, 99999);
}

function limit_signup_tags(string $value): string
{
    $items = array_filter(array_map('trim', explode(',', $value)), fn($item) => $item !== '');
    $items = array_values(array_unique($items));
    return substr(implode(', ', array_slice($items, 0, 3)), 0, 180);
}

ensure_public_instructor_profile_table();
ensure_users_phone_column();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $_SESSION['instructor_signup_old'] = array_map(fn($value) => substr(trim((string) $value), 0, 500), $_POST);

    if (!verify_csrf($_POST['csrf_token'] ?? null)) {
        $_SESSION['instructor_signup_error'] = 'Security token expired. Please try again.';
        redirect('instructor-signup');
    }

    $fullName = substr(trim((string) ($_POST['full_name'] ?? '')), 0, 120);
    $email = substr(trim((string) ($_POST['email'] ?? '')), 0, 160);
    $phone = substr(trim((string) ($_POST['phone'] ?? '')), 0, 30);
    $password = (string) ($_POST['password'] ?? '');
    $expertise = limit_signup_tags((string) ($_POST['expertise'] ?? ''));
    $experienceYears = max(0, (int) ($_POST['experience_years'] ?? 0));
    $experienceMonths = min(11, max(0, (int) ($_POST['experience_months'] ?? 0)));
    $experience = $experienceYears + round($experienceMonths / 12, 1);
    $qualification = limit_signup_tags((string) ($_POST['qualification'] ?? ''));
    $referral = strtoupper(substr(trim((string) ($_POST['referral_code'] ?? '')), 0, 40));
    $bio = substr(trim((string) ($_POST['bio'] ?? '')), 0, 1000);

    if ($fullName === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($password) < 8 || $expertise === '' || !preg_match('/^\d{10}$/', $phone) || !isset($_POST['terms_accepted'])) {
        $_SESSION['instructor_signup_error'] = 'Please fill required fields. Password must be at least 8 characters.';
        if (!preg_match('/^\d{10}$/', $phone)) {
            $_SESSION['instructor_signup_error'] = 'Please enter a valid 10 digit phone number.';
        }
        if (!isset($_POST['terms_accepted'])) {
            $_SESSION['instructor_signup_error'] = 'Please accept Terms & Conditions to continue.';
        }
        redirect('instructor-signup');
    }

    try {
        $roleId = public_instructor_role_id();
        if ($roleId <= 0) {
            throw new RuntimeException('Instructor role is not configured.');
        }

        $stmt = db()->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
        $stmt->bind_param('s', $email);
        $stmt->execute();
        if ($stmt->get_result()->fetch_assoc()) {
            throw new RuntimeException('This email is already registered.');
        }

        $stmt = db()->prepare('SELECT id FROM users WHERE phone = ? LIMIT 1');
        $stmt->bind_param('s', $phone);
        $stmt->execute();
        if ($stmt->get_result()->fetch_assoc()) {
            throw new RuntimeException('This phone number is already registered.');
        }

        $stmt = db()->prepare('SELECT id FROM instructor_profiles WHERE phone = ? LIMIT 1');
        $stmt->bind_param('s', $phone);
        $stmt->execute();
        if ($stmt->get_result()->fetch_assoc()) {
            throw new RuntimeException('This phone number is already registered.');
        }

        $username = strtolower(preg_replace('/[^a-z0-9]+/i', '.', strstr($email, '@', true) ?: $fullName));
        $username = trim($username, '.') ?: 'instructor';
        $baseUsername = substr($username, 0, 60);
        $i = 0;
        do {
            $username = $i === 0 ? $baseUsername : substr($baseUsername, 0, 55) . $i;
            $stmt = db()->prepare('SELECT id FROM users WHERE username = ? OR email = ? LIMIT 1');
            $stmt->bind_param('ss', $username, $email);
            $stmt->execute();
            $exists = $stmt->get_result()->fetch_assoc();
            $i++;
        } while ($exists);

        $hash = password_hash($password, PASSWORD_DEFAULT);
        $status = 'inactive';
        $stmt = db()->prepare('INSERT INTO users (role_id, full_name, username, email, phone, password_hash, status) VALUES (?, ?, ?, ?, ?, ?, ?)');
        $stmt->bind_param('issssss', $roleId, $fullName, $username, $email, $phone, $hash, $status);
        $stmt->execute();
        $userId = db()->insert_id;

        do {
            $refCode = new_referral_code($fullName);
            $stmt = db()->prepare('SELECT id FROM instructor_profiles WHERE referral_code = ? LIMIT 1');
            $stmt->bind_param('s', $refCode);
            $stmt->execute();
        } while ($stmt->get_result()->fetch_assoc());

        $commissionType = app_setting('instructor_commission_type', 'percent');
        $commissionValue = (float) app_setting('instructor_commission_value', '40');
        $approvalStatus = 'pending';
        $stmt = db()->prepare('INSERT INTO instructor_profiles (user_id, phone, expertise, experience_years, qualification, bio, referral_code, referred_by_code, commission_type, commission_value, approval_status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
        $stmt->bind_param('issdsssssds', $userId, $phone, $expertise, $experience, $qualification, $bio, $refCode, $referral, $commissionType, $commissionValue, $approvalStatus);
        $stmt->execute();

        if ($referral !== '') {
            $stmt = db()->prepare('SELECT user_id FROM instructor_profiles WHERE referral_code = ? LIMIT 1');
            $stmt->bind_param('s', $referral);
            $stmt->execute();
            $referrer = $stmt->get_result()->fetch_assoc();
            if ($referrer && (int) $referrer['user_id'] !== (int) $userId) {
                $refType = app_setting('instructor_referral_commission_type', 'percent');
                $refValue = (float) app_setting('instructor_referral_commission_value', '10');
                $sourceType = 'instructor_signup';
                $status = 'pending';
                $recipientId = (int) $referrer['user_id'];
                $stmt = db()->prepare('INSERT INTO referral_commissions (recipient_user_id, referred_user_id, source_type, referral_code, commission_type, commission_value, status) VALUES (?, ?, ?, ?, ?, ?, ?)');
                $stmt->bind_param('iisssds', $recipientId, $userId, $sourceType, $referral, $refType, $refValue, $status);
                $stmt->execute();
            }
        }

        $token = bin2hex(random_bytes(32));
        $tokenHash = hash('sha256', $token);
        $purpose = 'instructor_signup';
        $expiresAt = date('Y-m-d H:i:s', time() + 86400);
        $stmt = db()->prepare('INSERT INTO email_verifications (user_id, token_hash, purpose, expires_at) VALUES (?, ?, ?, ?)');
        $stmt->bind_param('isss', $userId, $tokenHash, $purpose, $expiresAt);
        $stmt->execute();

        $verifyUrl = app_url('verify-email?token=' . urlencode($token));
        @mail($email, 'Verify your instructor account', "Please verify your email to activate your instructor account:\n\n" . $verifyUrl);

        unset($_SESSION['instructor_signup_old']);
        $_SESSION['instructor_signup_success'] = 'Signup submitted. Please verify your email to activate your instructor account.';
        $_SESSION['instructor_verify_link'] = $verifyUrl;
        redirect('instructor-signup');
    } catch (Throwable $e) {
        $_SESSION['instructor_signup_error'] = $e->getMessage();
        redirect('instructor-signup');
    }
}

$old = $_SESSION['instructor_signup_old'] ?? [];
$error = $_SESSION['instructor_signup_error'] ?? '';
$success = $_SESSION['instructor_signup_success'] ?? '';
$verifyLink = $_SESSION['instructor_verify_link'] ?? '';
unset($_SESSION['instructor_signup_error'], $_SESSION['instructor_signup_success'], $_SESSION['instructor_verify_link']);
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Instructor Signup - <?= h(app_name()); ?></title>
    <?php if (asset_or_default('favicon_path') !== ''): ?><link rel="icon" href="<?= h(asset_or_default('favicon_path')); ?>"><?php endif; ?>
    <link rel="stylesheet" href="<?= app_url('assets/css/login.css'); ?>">
</head>
<body class="signup-page">
    <main class="signup-shell light-signup">
        <section class="signup-panel">
            <div class="signup-content">
                <div class="signup-form-card">
                    <div class="signup-top-row">
                        <a class="signup-back" href="<?= app_url('index'); ?>">
                            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M15 18l-6-6 6-6"></path></svg>
                            Back
                        </a>
                        <a class="signup-login-link" href="<?= app_url('login'); ?>">Login</a>
                    </div>
                    <a class="signup-head" href="<?= app_url('index'); ?>">
                        <span class="logo-mark"><?php if (asset_or_default('app_logo_path') !== ''): ?><img src="<?= h(asset_or_default('app_logo_path')); ?>" alt="<?= h(app_name()); ?>"><?php else: ?>EL<?php endif; ?></span>
                        <div><p>Instructor Partner</p><h1>Join <?= h(app_name()); ?></h1></div>
                    </a>
                    <?php if ($error): ?><div class="form-alert danger"><?= h($error); ?></div><?php endif; ?>
                    <?php if ($success): ?><div class="form-alert"><?= h($success); ?><?php if ($verifyLink): ?><br><small>Dev verification link: <a href="<?= h($verifyLink); ?>"><?= h($verifyLink); ?></a></small><?php endif; ?></div><?php endif; ?>
                    <form method="post" autocomplete="off" autocapitalize="off" spellcheck="false">
                        <input type="text" name="fake_user" tabindex="-1" aria-hidden="true" class="fake-input" autocomplete="username">
                        <input type="password" name="fake_pass" tabindex="-1" aria-hidden="true" class="fake-input" autocomplete="current-password">
                        <input type="hidden" name="csrf_token" value="<?= h(csrf_token()); ?>">
                        <div class="signup-grid">
                            <label>Full Name<input name="full_name" value="<?= h($old['full_name'] ?? ''); ?>" autocomplete="off" readonly data-unlock required></label>
                            <label>Email<input type="email" name="email" value="<?= h($old['email'] ?? ''); ?>" autocomplete="off" readonly data-unlock required></label>
                            <label>Phone<input name="phone" value="<?= h($old['phone'] ?? ''); ?>" inputmode="numeric" pattern="[0-9]{10}" maxlength="10" autocomplete="off" readonly data-unlock></label>
                            <label>Password<span class="password-field"><input id="signup-password" type="password" name="password" value="<?= h($old['password'] ?? ''); ?>" minlength="8" autocomplete="new-password" readonly data-unlock required><button type="button" data-toggle-password="signup-password" aria-label="Show password">⌕</button></span></label>
                            <label>Expertise<span class="tag-select"><span class="tag-chips"></span><input class="tag-input" data-tag-target="expertise-hidden" data-options="Digital Marketing,Mathematics,Physics,Chemistry,Biology,English,Spoken English,Computer Basics,Python,Java,Web Development,Data Science,AI,Graphic Design,Video Editing,Tally,Accounting,Finance,Marketing,Sales,Personality Development,Yoga,Music" placeholder="Type and select" autocomplete="off" readonly data-unlock><input type="hidden" id="expertise-hidden" name="expertise" value="<?= h($old['expertise'] ?? ''); ?>"><span class="tag-suggestions"></span></span></label>
                            <label>Experience<span class="experience-row"><select name="experience_years"><?php for ($i = 0; $i <= 40; $i++): ?><option value="<?= $i; ?>" <?= (string) ($old['experience_years'] ?? '0') === (string) $i ? 'selected' : ''; ?>><?= $i; ?> Year</option><?php endfor; ?></select><select name="experience_months"><?php for ($i = 0; $i <= 11; $i++): ?><option value="<?= $i; ?>" <?= (string) ($old['experience_months'] ?? '0') === (string) $i ? 'selected' : ''; ?>><?= $i; ?> Month</option><?php endfor; ?></select></span></label>
                            <label>Qualification<span class="tag-select"><span class="tag-chips"></span><input class="tag-input" data-tag-target="qualification-hidden" data-options="High School,Intermediate,Diploma,BA,BSc,BCom,BBA,BCA,MA,MSc,MCom,MBA,MCA,BEd,MEd,BTech,MTech,PhD,Certificate Course,Industry Expert" placeholder="Type and select" autocomplete="off" readonly data-unlock><input type="hidden" id="qualification-hidden" name="qualification" value="<?= h($old['qualification'] ?? ''); ?>"><span class="tag-suggestions"></span></span></label>
                            <label>Referral Code<input name="referral_code" value="<?= h($old['referral_code'] ?? ($_GET['ref'] ?? '')); ?>" placeholder="Optional" autocomplete="off" readonly data-unlock></label>
                            <label class="span-2">Short Bio<textarea name="bio" rows="2"><?= h($old['bio'] ?? ''); ?></textarea></label>
                        </div>
                        <label class="terms-check">
                            <input type="checkbox" name="terms_accepted" value="1" required>
                            <span>I accept <a href="<?= h(app_url('terms-and-conditions')); ?>" data-open-terms>Terms & Conditions</a>.</span>
                        </label>
                        <button type="submit">Submit Signup</button>
                    </form>
                </div>
                <aside class="signup-benefits">
                    <img class="benefit-visual" src="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='520' height='260' viewBox='0 0 520 260'%3E%3Cdefs%3E%3ClinearGradient id='a' x1='0' x2='1' y1='0' y2='1'%3E%3Cstop stop-color='%235c7cfa'/%3E%3Cstop offset='1' stop-color='%23ff5a7f'/%3E%3C/linearGradient%3E%3ClinearGradient id='b' x1='0' x2='1'%3E%3Cstop stop-color='%23eef4ff'/%3E%3Cstop offset='1' stop-color='%23fff5f8'/%3E%3C/linearGradient%3E%3C/defs%3E%3Crect width='520' height='260' rx='26' fill='url(%23b)'/%3E%3Ccircle cx='425' cy='48' r='72' fill='%23dbe7ff'/%3E%3Ccircle cx='72' cy='214' r='64' fill='%23ffe0eb'/%3E%3Crect x='54' y='58' width='250' height='154' rx='18' fill='white' stroke='%23cbd8ee'/%3E%3Crect x='76' y='84' width='116' height='12' rx='6' fill='%23111827'/%3E%3Crect x='76' y='112' width='190' height='10' rx='5' fill='%23cbd8ee'/%3E%3Crect x='76' y='136' width='154' height='10' rx='5' fill='%23dbe5f4'/%3E%3Crect x='76' y='165' width='74' height='28' rx='8' fill='url(%23a)'/%3E%3Ccircle cx='364' cy='128' r='54' fill='white' stroke='%23cbd8ee'/%3E%3Ccircle cx='364' cy='104' r='20' fill='%23111827'/%3E%3Cpath d='M322 180c10-27 73-27 84 0' fill='url(%23a)'/%3E%3Cpath d='M330 70l38-22 38 22-38 22-38-22Z' fill='%23ff5a7f'/%3E%3Cpath d='M406 70v34' stroke='%23111827' stroke-width='8' stroke-linecap='round'/%3E%3C/svg%3E" alt="Online instructor dashboard preview">
                    <span>Why Join Us</span>
                    <h2>Teach online with a managed LMS</h2>
                    <p>Build your instructor profile, verify your email and activate your instructor account automatically.</p>
                    <div class="benefit-list">
                        <article class="blue"><i><svg viewBox="0 0 24 24"><path d="M4 7h16v10H4V7Zm3 3h.01M17 14h1M12 15a3 3 0 1 0 0-6 3 3 0 0 0 0 6Z"></path></svg></i><div><strong>Referral Income</strong><small>Share your code and earn direct commission on eligible signups.</small></div></article>
                        <article class="green"><i><svg viewBox="0 0 24 24"><path d="M16 21v-2a4 4 0 0 0-8 0v2M12 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8m5 2 2 2 4-4"></path></svg></i><div><strong>Support Team</strong><small>Assigned support staff helps with students, reporting and follow-up.</small></div></article>
                        <article class="pink"><i><svg viewBox="0 0 24 24"><path d="M4 19V5m0 14h16M8 16l3-4 3 2 4-6"></path></svg></i><div><strong>Course Growth</strong><small>Manage courses, classes and learner progress from one system.</small></div></article>
                    </div>
                </aside>
            </div>
            <div id="terms-modal" class="terms-modal" aria-hidden="true">
                <div class="terms-box">
                    <div class="terms-head"><h2><?= h(important_page_title('terms-and-conditions')); ?></h2><button type="button" data-close-terms>×</button></div>
                    <div class="terms-content"><?= important_page_body('terms-and-conditions'); ?></div>
                </div>
            </div>
        </section>
    </main>
    <script>
        const termsModal = document.getElementById('terms-modal');
        document.querySelectorAll('[data-open-terms]').forEach((link) => {
            link.addEventListener('click', (event) => {
                event.preventDefault();
                termsModal.classList.add('open');
                termsModal.setAttribute('aria-hidden', 'false');
            });
        });
        document.querySelectorAll('[data-close-terms]').forEach((button) => {
            button.addEventListener('click', () => {
                termsModal.classList.remove('open');
                termsModal.setAttribute('aria-hidden', 'true');
            });
        });
        document.querySelectorAll('[data-unlock]').forEach((input) => {
            const unlock = () => input.removeAttribute('readonly');
            input.addEventListener('focus', unlock, { once: true });
            input.addEventListener('touchstart', unlock, { once: true });
        });
        document.querySelectorAll('input[name="phone"]').forEach((input) => {
            input.addEventListener('input', () => {
                input.value = input.value.replace(/\D/g, '').slice(0, 10);
            });
        });
        document.querySelectorAll('[data-toggle-password]').forEach((button) => {
            button.addEventListener('click', () => {
                const input = document.getElementById(button.dataset.togglePassword);
                if (!input) return;
                input.type = input.type === 'password' ? 'text' : 'password';
                button.classList.toggle('showing', input.type === 'text');
            });
        });
        document.querySelectorAll('.tag-input').forEach((input) => {
            const hidden = document.getElementById(input.dataset.tagTarget);
            const field = input.closest('.tag-select') || input.closest('label');
            const chips = field.querySelector('.tag-chips');
            const suggestions = field.querySelector('.tag-suggestions');
            const options = input.dataset.options.split(',').map((item) => item.trim()).filter(Boolean);
            let selected = (hidden.value || '').split(',').map((item) => item.trim()).filter(Boolean).slice(0, 3);
            const sync = () => {
                hidden.value = selected.join(', ');
                input.placeholder = selected.length >= 3 ? 'Max 3 selected' : 'Type and select';
                chips.innerHTML = selected.map((item) => `<button type="button" data-tag="${item}">${item}<span>×</span></button>`).join('');
            };
            const renderSuggestions = () => {
                if (selected.length >= 3) {
                    suggestions.innerHTML = '';
                    suggestions.classList.remove('open');
                    return;
                }
                const term = input.value.trim().toLowerCase();
                const matches = options.filter((item) => !selected.includes(item) && (!term || item.toLowerCase().includes(term))).slice(0, 7);
                suggestions.innerHTML = matches.map((item) => `<button type="button" data-pick="${item}">${item}</button>`).join('');
                suggestions.classList.toggle('open', matches.length > 0);
            };
            suggestions.addEventListener('click', (event) => {
                const picked = event.target.closest('[data-pick]');
                if (!picked || selected.length >= 3) return;
                selected.push(picked.dataset.pick);
                input.value = '';
                sync();
                renderSuggestions();
            });
            chips.addEventListener('click', (event) => {
                const tag = event.target.closest('[data-tag]');
                if (!tag) return;
                selected = selected.filter((item) => item !== tag.dataset.tag);
                sync();
                renderSuggestions();
            });
            input.addEventListener('input', renderSuggestions);
            input.addEventListener('focus', renderSuggestions);
            input.addEventListener('blur', () => {
                setTimeout(() => suggestions.classList.remove('open'), 160);
            });
            sync();
        });
    </script>
</body>
</html>
