<?php
if (!function_exists('licora_ui_navigation_groups')) {
    function licora_ui_navigation_groups(): array
    {
        return [
            'Main' => [
                ['file' => 'index.php', 'label' => 'Dashboard', 'icon' => 'bi-speedometer2'],
            ],
            'License Management' => [
                ['file' => 'license.php', 'label' => 'Licenses', 'icon' => 'bi-key'],
                ['file' => 'device.php', 'label' => 'Devices', 'icon' => 'bi-devices'],
            ],
            'API & Clients' => [
                ['file' => 'api_keys.php', 'label' => 'API Keys', 'icon' => 'bi-key-fill'],
                ['file' => 'client_apps.php', 'label' => 'Client Apps', 'icon' => 'bi-boxes'],
                ['file' => 'v2_devices.php', 'label' => 'V2 Devices', 'icon' => 'bi-shield-check'],
            ],
            'Operations' => [
                ['file' => 'logs.php', 'label' => 'Logs', 'icon' => 'bi-clock-history'],
                ['file' => 'updates.php', 'label' => 'Updates', 'icon' => 'bi-cloud-arrow-down', 'super_admin' => true, 'update_badge' => true],
            ],
            'System' => [
                ['file' => 'settings.php', 'label' => 'Settings', 'icon' => 'bi-gear'],
                ['file' => 'admins.php', 'label' => 'Admins', 'icon' => 'bi-people'],
            ],
        ];
    }
}

if (!function_exists('licora_ui_page_title')) {
    function licora_ui_page_title(string $currentPage): string
    {
        foreach (licora_ui_navigation_groups() as $items) {
            foreach ($items as $item) {
                if ($item['file'] === $currentPage) {
                    return $item['label'];
                }
            }
        }
        $secondary = [
            'audit.php' => 'Audit Trail',
            'backup.php' => 'Backup & Export',
            'health.php' => 'System Health',
        ];
        return $secondary[$currentPage] ?? 'Admin';
    }
}
