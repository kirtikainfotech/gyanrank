<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/institute_erp.php';
require_once __DIR__ . '/includes/institute_master.php';

$user = require_login('superadmin');
institute_erp_ensure_tables();

function institute_erp_limit(?string $value): ?int
{
    $value = trim((string) $value);
    return $value === '' ? null : max(0, (int) $value);
}

function institute_erp_feature_catalog(): array
{
    return [
        'student' => 'Student Information',
        'admission' => 'Admission',
        'fees' => 'Fees Collection',
        'attendance' => 'Attendance',
        'exams' => 'Examinations',
        'online_exam' => 'Online Examination',
        'academics' => 'Academics',
        'lesson_plan' => 'Lesson Plan',
        'homework' => 'Homework',
        'communicate' => 'Communicate',
        'download_center' => 'Download Center',
        'library' => 'Library',
        'transport' => 'Transport',
        'hostel' => 'Hostel',
        'front_office' => 'Front Office',
        'human_resource' => 'Human Resource',
        'inventory' => 'Inventory',
        'income' => 'Income',
        'expenses' => 'Expenses',
        'reports' => 'Reports',
        'certificate' => 'Certificate',
        'alumni' => 'Alumni',
        'custom_branding' => 'Custom Branding',
        'priority_support' => 'Priority Support',
        'all_modules' => 'All Modules',
    ];
}

function institute_erp_selected_features(array $source): array
{
    $features = $source['features'] ?? [];
    if (is_string($features)) {
        $features = explode(',', $features);
    }
    if (!is_array($features)) {
        return [];
    }
    return array_values(array_unique(array_filter(array_map(static fn($feature) => trim((string) $feature), $features))));
}

function institute_erp_plan_validity(float $monthly, float $yearly): int
{
    return $yearly > 0 ? 365 : 30;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['csrf_token'] ?? null)) {
        $_SESSION['institute_master_error'] = 'Security token expired.';
        redirect('sadmin/institute-erp-plans');
    }

    try {
        if (($_POST['mode'] ?? '') === 'create') {
            $name = trim((string) ($_POST['plan_name'] ?? ''));
            if ($name === '') {
                throw new RuntimeException('Plan name is required.');
            }
            $slug = institute_erp_slug($name);
            $monthly = max(0, (float) ($_POST['monthly_price'] ?? 0));
            $yearly = max(0, (float) ($_POST['yearly_price'] ?? 0));
            $validity = institute_erp_plan_validity($monthly, $yearly);
            $students = institute_erp_limit($_POST['max_students'] ?? '');
            $staff = institute_erp_limit($_POST['max_staff'] ?? '');
            $storage = institute_erp_limit($_POST['max_storage_mb'] ?? '');
            $features = institute_erp_selected_features($_POST);
            $featuresJson = json_encode($features, JSON_UNESCAPED_SLASHES);
            $sort = max(0, (int) ($_POST['sort_order'] ?? 0));
            $active = isset($_POST['is_active']) ? 1 : 0;
            $stmt = db()->prepare("INSERT INTO institution_erp_plans
                (plan_name, plan_slug, monthly_price, yearly_price, validity_days, max_students, max_staff, max_storage_mb, features_json, is_active, sort_order)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param('ssddiiiisii', $name, $slug, $monthly, $yearly, $validity, $students, $staff, $storage, $featuresJson, $active, $sort);
            $stmt->execute();
            $_SESSION['institute_master_message'] = 'ERP plan added.';
        } else {
            $plans = (array) ($_POST['plans'] ?? []);
            $stmt = db()->prepare("UPDATE institution_erp_plans
                SET plan_name = ?, monthly_price = ?, yearly_price = ?, validity_days = ?, max_students = ?,
                    max_staff = ?, max_storage_mb = ?, features_json = ?, is_active = ?, sort_order = ?
                WHERE id = ?");
            foreach ($plans as $id => $plan) {
                $planId = (int) $id;
                $name = trim((string) ($plan['plan_name'] ?? ''));
                if ($planId <= 0 || $name === '') {
                    continue;
                }
                $monthly = max(0, (float) ($plan['monthly_price'] ?? 0));
                $yearly = max(0, (float) ($plan['yearly_price'] ?? 0));
                $validity = institute_erp_plan_validity($monthly, $yearly);
                $students = institute_erp_limit($plan['max_students'] ?? '');
                $staff = institute_erp_limit($plan['max_staff'] ?? '');
                $storage = institute_erp_limit($plan['max_storage_mb'] ?? '');
                $features = institute_erp_selected_features($plan);
                $featuresJson = json_encode($features, JSON_UNESCAPED_SLASHES);
                $active = isset($plan['is_active']) ? 1 : 0;
                $sort = max(0, (int) ($plan['sort_order'] ?? 0));
                $stmt->bind_param('sddiiiisiii', $name, $monthly, $yearly, $validity, $students, $staff, $storage, $featuresJson, $active, $sort, $planId);
                $stmt->execute();
            }
            $_SESSION['institute_master_message'] = 'ERP plans updated.';
        }
    } catch (Throwable $e) {
        $_SESSION['institute_master_error'] = $e->getMessage();
    }
    redirect('sadmin/institute-erp-plans');
}

$plans = db()->query("SELECT * FROM institution_erp_plans ORDER BY sort_order ASC, id ASC")->fetch_all(MYSQLI_ASSOC);
$featureCatalog = institute_erp_feature_catalog();
[$message, $error] = institute_master_flash();

$pageTitle = 'Institute ERP Plans';
$pageSubtitle = 'Subscription plans for school, college and institute ERP tenants.';
$activePage = 'institute-erp-plans';
?>
<?php include __DIR__ . '/includes/header.php'; ?>
<?php include __DIR__ . '/includes/sidebar.php'; ?>
<main class="main sadmin-institute-main">
    <?php include __DIR__ . '/includes/topbar.php'; ?>
    <section class="content institute-admin-page">
        <?php institute_master_nav('erp-plans'); ?>
        <?php if ($message !== ''): ?><div class="flash success"><?= h($message); ?></div><?php endif; ?>
        <?php if ($error !== ''): ?><div class="flash danger"><?= h($error); ?></div><?php endif; ?>

        <section class="card custom-card">
            <div class="card-header justify-content-between">
                <div>
                    <span class="text-primary fs-11 fw-semibold text-uppercase d-block mb-1">ERP Subscription</span>
                    <h5 class="mb-1 fw-semibold">Institute ERP Plans</h5>
                    <p class="mb-0 text-muted fs-12">Blank limit means unlimited. Expired plans will keep reporting available and lock new entries.</p>
                </div>
                <button class="btn btn-primary btn-wave" form="erpPlansForm" type="submit">Save Plans</button>
            </div>
            <div class="card-body p-0">
                <form method="post" id="erpPlansForm">
                    <input type="hidden" name="csrf_token" value="<?= h(csrf_token()); ?>">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0 gr-register-table institute-request-table">
                            <thead>
                            <tr>
                                <th>Plan</th>
                                <th>Monthly</th>
                                <th>Yearly</th>
                                <th>Students</th>
                                <th>Staff</th>
                                <th>Storage MB</th>
                                <th>Modules / Features</th>
                                <th>Sort</th>
                                <th>Status</th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($plans as $plan): ?>
                                <?php
                                $features = json_decode((string) ($plan['features_json'] ?? '[]'), true);
                                $features = is_array($features) ? $features : [];
                                ?>
                                <tr>
                                    <td>
                                        <strong><?= h((string) $plan['plan_slug']); ?></strong>
                                        <input class="form-control form-control-sm" name="plans[<?= (int) $plan['id']; ?>][plan_name]" value="<?= h($plan['plan_name']); ?>">
                                    </td>
                                    <td><input class="form-control form-control-sm" type="number" step="0.01" min="0" name="plans[<?= (int) $plan['id']; ?>][monthly_price]" value="<?= h((string) $plan['monthly_price']); ?>"></td>
                                    <td><input class="form-control form-control-sm" type="number" step="0.01" min="0" name="plans[<?= (int) $plan['id']; ?>][yearly_price]" value="<?= h((string) $plan['yearly_price']); ?>"></td>
                                    <td><input class="form-control form-control-sm" type="number" min="0" placeholder="Unlimited" name="plans[<?= (int) $plan['id']; ?>][max_students]" value="<?= h((string) ($plan['max_students'] ?? '')); ?>"></td>
                                    <td><input class="form-control form-control-sm" type="number" min="0" placeholder="Unlimited" name="plans[<?= (int) $plan['id']; ?>][max_staff]" value="<?= h((string) ($plan['max_staff'] ?? '')); ?>"></td>
                                    <td><input class="form-control form-control-sm" type="number" min="0" placeholder="Unlimited" name="plans[<?= (int) $plan['id']; ?>][max_storage_mb]" value="<?= h((string) ($plan['max_storage_mb'] ?? '')); ?>"></td>
                                    <td class="erp-features-cell">
                                        <div class="erp-feature-picker" data-feature-picker>
                                            <button class="erp-feature-button" type="button" data-feature-toggle>
                                                <i class="bx bx-list-check"></i>
                                                <span><?= h((string) count($features)); ?> Features</span>
                                            </button>
                                            <div class="erp-feature-modal" aria-hidden="true">
                                                <div class="erp-feature-panel">
                                                    <div class="erp-feature-panel-head">
                                                        <div>
                                                            <strong><?= h((string) $plan['plan_name']); ?> Features</strong>
                                                            <span>Select ERP modules included in this plan.</span>
                                                        </div>
                                                        <button type="button" data-feature-close aria-label="Close"><i class="bx bx-x"></i></button>
                                                    </div>
                                                    <div class="erp-feature-menu">
                                                        <?php foreach ($featureCatalog as $featureKey => $featureLabel): ?>
                                                            <label>
                                                                <input type="checkbox" name="plans[<?= (int) $plan['id']; ?>][features][]" value="<?= h($featureKey); ?>" <?= in_array($featureKey, $features, true) ? 'checked' : ''; ?>>
                                                                <span><?= h($featureLabel); ?></span>
                                                            </label>
                                                        <?php endforeach; ?>
                                                    </div>
                                                    <div class="erp-feature-panel-actions">
                                                        <button class="btn btn-primary btn-sm" type="button" data-feature-close>Done</button>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                    <td><input class="form-control form-control-sm" type="number" min="0" name="plans[<?= (int) $plan['id']; ?>][sort_order]" value="<?= h((string) $plan['sort_order']); ?>"></td>
                                    <td class="erp-status-cell">
                                        <label class="erp-status-toggle">
                                            <input type="checkbox" name="plans[<?= (int) $plan['id']; ?>][is_active]" value="1" <?= (int) $plan['is_active'] === 1 ? 'checked' : ''; ?>>
                                            <span>Active</span>
                                        </label>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </form>
            </div>
        </section>

        <section class="card custom-card mt-3">
            <div class="card-header">
                <div>
                    <span class="text-primary fs-11 fw-semibold text-uppercase d-block mb-1">New Plan</span>
                    <h6 class="mb-0 fw-semibold">Add ERP Plan</h6>
                </div>
            </div>
            <div class="card-body">
                <form method="post" class="erp-plan-create-grid">
                    <input type="hidden" name="csrf_token" value="<?= h(csrf_token()); ?>">
                    <input type="hidden" name="mode" value="create">
                    <input class="form-control" name="plan_name" placeholder="Plan name" required>
                    <input class="form-control" type="number" step="0.01" min="0" name="monthly_price" placeholder="Monthly price">
                    <input class="form-control" type="number" step="0.01" min="0" name="yearly_price" placeholder="Yearly price">
                    <input class="form-control" type="number" min="0" name="max_students" placeholder="Max students">
                    <input class="form-control" type="number" min="0" name="max_staff" placeholder="Max staff">
                    <input class="form-control" type="number" min="0" name="max_storage_mb" placeholder="Storage MB">
                    <div class="erp-feature-picker erp-feature-picker-create" data-feature-picker>
                        <button class="erp-feature-button" type="button" data-feature-toggle>
                            <i class="bx bx-list-check"></i>
                            <span>Select Features</span>
                        </button>
                        <div class="erp-feature-modal" aria-hidden="true">
                            <div class="erp-feature-panel">
                                <div class="erp-feature-panel-head">
                                    <div>
                                        <strong>New Plan Features</strong>
                                        <span>Select ERP modules included in this plan.</span>
                                    </div>
                                    <button type="button" data-feature-close aria-label="Close"><i class="bx bx-x"></i></button>
                                </div>
                                <div class="erp-feature-menu">
                                    <?php foreach ($featureCatalog as $featureKey => $featureLabel): ?>
                                        <label>
                                            <input type="checkbox" name="features[]" value="<?= h($featureKey); ?>">
                                            <span><?= h($featureLabel); ?></span>
                                        </label>
                                    <?php endforeach; ?>
                                </div>
                                <div class="erp-feature-panel-actions">
                                    <button class="btn btn-primary btn-sm" type="button" data-feature-close>Done</button>
                                </div>
                            </div>
                        </div>
                    </div>
                    <input class="form-control" type="number" min="0" name="sort_order" value="10">
                    <label class="d-flex align-items-center gap-2"><input type="checkbox" name="is_active" value="1" checked> Active</label>
                    <button class="btn btn-primary btn-wave" type="submit">Add Plan</button>
                </form>
            </div>
        </section>
    </section>
    <style>
        .sadmin-institute-main .institute-admin-page { padding-top: 1.25rem; }
        .sadmin-institute-main .table-responsive { width: 100%; overflow-x: auto; overflow-y: visible; }
        .sadmin-institute-main .institute-request-table { width: 100%; min-width: 78rem; table-layout: fixed; }
        .sadmin-institute-main .institute-request-table th,
        .sadmin-institute-main .institute-request-table td { padding: .45rem .6rem !important; font-size: .72rem; vertical-align: middle; }
        .sadmin-institute-main .institute-request-table th:nth-child(1) { width: 11rem; }
        .sadmin-institute-main .institute-request-table th:nth-child(2),
        .sadmin-institute-main .institute-request-table th:nth-child(3) { width: 9.5rem; }
        .sadmin-institute-main .institute-request-table th:nth-child(4),
        .sadmin-institute-main .institute-request-table th:nth-child(5),
        .sadmin-institute-main .institute-request-table th:nth-child(6) { width: 8.5rem; }
        .sadmin-institute-main .institute-request-table th:nth-child(7) { width: 8rem; }
        .sadmin-institute-main .institute-request-table th:nth-child(8) { width: 6rem; }
        .sadmin-institute-main .institute-request-table th:nth-child(9) { width: 6.25rem; }
        .sadmin-institute-main .institute-request-table .form-control { min-width: 0 !important; width: 100% !important; }
        .sadmin-institute-main .institute-request-table th:last-child,
        .sadmin-institute-main .institute-request-table td:last-child { text-align: center; }
        .sadmin-institute-main .erp-features-cell { text-align: center; }
        .sadmin-institute-main .erp-status-cell { white-space: nowrap; overflow: visible; }
        .sadmin-institute-main .erp-status-toggle { display: inline-flex; align-items: center; justify-content: center; gap: .35rem; width: 100%; margin: 0; font-weight: 700; color: #0a3c66; }
        .sadmin-institute-main .erp-feature-picker { position: static; }
        .sadmin-institute-main .erp-feature-button { display: inline-flex; align-items: center; justify-content: center; gap: .35rem; min-height: 32px !important; width: 100%; padding: .3rem .5rem !important; border: 1px solid #c9d9e8; border-radius: 3px; background: #f5f9fc; color: #0a3c66; font-weight: 800; white-space: nowrap; }
        .sadmin-institute-main .erp-feature-button:hover { border-color: #f68a00; background: #fff3e3; color: #f68a00; }
        .sadmin-institute-main .erp-feature-modal { display: none; position: fixed; inset: 0; z-index: 5000; align-items: center; justify-content: center; padding: 1.5rem; background: rgba(0, 22, 42, .45); }
        .sadmin-institute-main .erp-feature-picker.open .erp-feature-modal { display: flex; }
        .sadmin-institute-main .erp-feature-panel { width: min(780px, 96vw); max-height: 86vh; display: flex; flex-direction: column; border: 1px solid #c9d9e8; border-top: 4px solid #f68a00; border-radius: 6px; background: #fff; box-shadow: 0 18px 45px rgba(0,0,0,.28); overflow: hidden; }
        .sadmin-institute-main .erp-feature-panel-head { display: flex; align-items: center; justify-content: space-between; gap: 1rem; padding: .8rem 1rem; border-bottom: 1px solid #dbe7f2; background: #f6f9fc; }
        .sadmin-institute-main .erp-feature-panel-head strong { display: block; color: #082f55; font-size: 1rem; line-height: 1.2; }
        .sadmin-institute-main .erp-feature-panel-head span { display: block; margin-top: .15rem; color: #718096; font-size: .78rem; }
        .sadmin-institute-main .erp-feature-panel-head button { width: 34px; min-height: 34px !important; padding: 0 !important; border: 1px solid #c9d9e8; border-radius: 3px; background: #fff; color: #0a3c66; font-size: 1.25rem; }
        .sadmin-institute-main .erp-feature-menu { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: .45rem; padding: 1rem; overflow: auto; }
        .sadmin-institute-main .erp-feature-menu label { display: flex; align-items: center; gap: .45rem; min-height: 34px; margin: 0; padding: .35rem .55rem; border: 1px solid #d6e4f0; border-radius: 4px; background: #f8fbfd; color: #102a43; font-size: .76rem; font-weight: 800; cursor: pointer; }
        .sadmin-institute-main .erp-feature-menu label:hover { border-color: #f68a00; background: #fff3e3; color: #f68a00; }
        .sadmin-institute-main .erp-feature-panel-actions { display: flex; justify-content: flex-end; padding: .75rem 1rem; border-top: 1px solid #dbe7f2; background: #fff; }
        .sadmin-institute-main .erp-plan-create-grid { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: .65rem; align-items: start; }
        .sadmin-institute-main .erp-plan-create-grid .erp-feature-picker { min-width: 100%; }
        @media (max-width: 1199.98px) { .sadmin-institute-main .erp-plan-create-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
        @media (max-width: 991.98px) { .sadmin-institute-main .erp-feature-menu { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
        @media (max-width: 767.98px) { .sadmin-institute-main .erp-plan-create-grid, .sadmin-institute-main .erp-feature-menu { grid-template-columns: 1fr; } }
    </style>
    <script>
    (() => {
        const catalog = <?= json_encode($featureCatalog, JSON_UNESCAPED_SLASHES); ?>;
        const updatePicker = (picker) => {
            const checked = [...picker.querySelectorAll('input[type="checkbox"]:checked')];
            const toggleText = picker.querySelector('[data-feature-toggle] span');
            if (toggleText) toggleText.textContent = checked.length ? `${checked.length} Features` : 'Select Features';
        };
        const closePicker = (picker) => {
            picker.classList.remove('open');
            picker.querySelector('.erp-feature-modal')?.setAttribute('aria-hidden', 'true');
        };
        document.querySelectorAll('[data-feature-picker]').forEach((picker) => {
            updatePicker(picker);
            picker.querySelector('[data-feature-toggle]')?.addEventListener('click', () => {
                document.querySelectorAll('[data-feature-picker].open').forEach((openPicker) => {
                    if (openPicker !== picker) closePicker(openPicker);
                });
                picker.classList.toggle('open');
                picker.querySelector('.erp-feature-modal')?.setAttribute('aria-hidden', picker.classList.contains('open') ? 'false' : 'true');
            });
            picker.querySelectorAll('[data-feature-close]').forEach((button) => {
                button.addEventListener('click', () => closePicker(picker));
            });
            picker.querySelector('.erp-feature-modal')?.addEventListener('click', (event) => {
                if (event.target.classList.contains('erp-feature-modal')) closePicker(picker);
            });
            picker.querySelectorAll('input[type="checkbox"]').forEach((input) => {
                input.addEventListener('change', () => updatePicker(picker));
            });
        });
        document.addEventListener('keydown', (event) => {
            if (event.key !== 'Escape') return;
            document.querySelectorAll('[data-feature-picker].open').forEach(closePicker);
        });
    })();
    </script>
</main>
<?php include __DIR__ . '/includes/footer.php'; ?>
