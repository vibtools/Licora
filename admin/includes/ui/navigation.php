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
                [
                    'file' => 'settings.php', 'label' => 'Settings', 'icon' => 'bi-gear',
                    'children' => [
                        ['file' => 'audit.php', 'label' => 'Audit Trail', 'icon' => 'bi-journal-text'],
                        ['file' => 'backup.php', 'label' => 'Backup & Export', 'icon' => 'bi-download'],
                        ['file' => 'health.php', 'label' => 'System Health', 'icon' => 'bi-heart-pulse'],
                        ['file' => 'about.php', 'label' => 'About Licora', 'icon' => 'bi-info-circle'],
                    ],
                ],
                ['file' => 'admins.php', 'label' => 'Admins', 'icon' => 'bi-people'],
            ],
        ];
    }
}

if (!function_exists('licora_ui_item_visible')) {
    function licora_ui_item_visible(array $item): bool
    {
        return empty($item['super_admin']) || AdminHelpers::canDelete();
    }
}

if (!function_exists('licora_ui_item_active')) {
    function licora_ui_item_active(array $item, string $currentPage): bool
    {
        if (($item['file'] ?? '') === $currentPage) { return true; }
        foreach (($item['children'] ?? []) as $child) {
            if (($child['file'] ?? '') === $currentPage) { return true; }
        }
        return false;
    }
}

if (!function_exists('licora_ui_page_title')) {
    function licora_ui_page_title(string $currentPage): string
    {
        foreach (licora_ui_navigation_groups() as $items) {
            foreach ($items as $item) {
                if (($item['file'] ?? '') === $currentPage) { return $item['label']; }
                foreach (($item['children'] ?? []) as $child) {
                    if (($child['file'] ?? '') === $currentPage) { return $child['label']; }
                }
            }
        }
        return 'Admin';
    }
}
