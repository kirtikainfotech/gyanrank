<?php
function setting_value(array $settings, string $key): string
{
    return h((string) ($settings[$key] ?? ''));
}

function settings_sections(): array
{
    return [
        ['key' => 'general', 'title' => 'General', 'subtitle' => 'Institute profile', 'url' => app_url('sadmin/settings-general'), 'icon' => 'M4 5h16M4 12h10M4 19h16'],
        ['key' => 'branding', 'title' => 'Branding', 'subtitle' => 'Logo & favicon', 'url' => app_url('sadmin/settings-branding'), 'icon' => 'M12 3l8 4v10l-8 4-8-4V7l8-4z'],
        ['key' => 'mail', 'title' => 'Email & Notification', 'subtitle' => 'SMTP & Firebase', 'url' => app_url('sadmin/settings-mail'), 'icon' => 'M4 6h16v12H4z M4 8l8 6 8-6'],
        ['key' => 'billing', 'title' => 'Billing', 'subtitle' => 'Invoice, tax & GST', 'url' => app_url('sadmin/settings-billing'), 'icon' => 'M7 3h10v18l-3-2-2 2-2-2-3 2V3z M9 8h6M9 12h6M9 16h3'],
        ['key' => 'templates', 'title' => 'Templates', 'subtitle' => 'Email content', 'url' => app_url('sadmin/settings-templates'), 'icon' => 'M5 4h14v16H5z M8 8h8M8 12h8M8 16h5'],
        ['key' => 'plans', 'title' => 'Plans', 'subtitle' => 'Free, Silver, Gold', 'url' => app_url('sadmin/settings-plans'), 'icon' => 'M4 7h16M6 7v12h12V7M8 11h8M8 15h5'],
        ['key' => 'gcoin', 'title' => 'Gcoin', 'subtitle' => 'Referral wallet', 'url' => app_url('sadmin/settings-gcoin'), 'icon' => 'M12 3a9 9 0 1 0 0 18 9 9 0 0 0 0-18Zm-3 9h6M12 7v10'],
        ['key' => 'pages', 'title' => 'Important Pages', 'subtitle' => 'Policies & legal', 'url' => app_url('sadmin/settings-pages'), 'icon' => 'M7 3h10l4 4v14H7V3z M17 3v5h5 M9 13h6M9 17h8'],
        ['key' => 'staff', 'title' => 'Staff & Roles', 'subtitle' => 'Roles, support, salary', 'url' => app_url('sadmin/settings-staff'), 'icon' => 'M16 21v-2a4 4 0 0 0-8 0v2 M12 12a4 4 0 1 0 0-8 4 4 0 0 0 0 8 M19 10v6M22 13h-6'],
    ];
}

function render_settings_submenu(string $current): void
{
    // Settings navigation already lives in the sidebar; detail pages should open cleanly.
    return;
}

function settings_flash(): array
{
    $message = $_SESSION['settings_message'] ?? '';
    $error = $_SESSION['settings_error'] ?? '';
    unset($_SESSION['settings_message'], $_SESSION['settings_error']);
    return [$message, $error];
}

function save_settings_keys(array $keys): void
{
    foreach ($keys as $key) {
        $value = trim((string) ($_POST[$key] ?? ''));
        save_setting($key, substr($value, 0, 5000));
    }
}
