<?php
declare(strict_types=1);
$root=dirname(__DIR__);
function uf_ok($v,string $m):void{if(!$v){fwrite(STDERR,"FAIL: {$m}\n");exit(1);}}
$contracts=[
 'admin/license.php'=>['name="create_license"','name="hours"','name="device_limit"','name="app_scope"','name="license_api_key_id"','name="bulk_create_license"','name="bulk_app_scope"','id="bulk_v2_app_scope"','name="csrf_token"','id="license-table"'],
 'admin/device.php'=>['name="clear_devices"','name="csrf_token"','id="devices-table"'],
 'admin/logs.php'=>['name="clear_logs"','name="csrf_token"','id="logs-table"'],
 'admin/api_keys.php'=>['name="create_api_key"','name="api_key_name"','name="app_name"','name="scope_label"','name="csrf_token"','id="api-keys-table"'],
 'admin/client_apps.php'=>['name="app_id"','name="display_name"','name="access_token_ttl"','name="refresh_token_ttl"','name="rate_limit_per_hour"','name="csrf_token"'],
 'admin/v2_devices.php'=>['name="device_credential_id"','name="revoke_device"','name="csrf_token"'],
 'admin/settings.php'=>['name="update_settings"','name="system_name"','name="default_license_hours"','name="default_device_limit"','name="csrf_token"','id="settings-form"'],
 'admin/admins.php'=>['name="create_admin"','name="update_admin"','name="delete_admin"','name="username"','name="role"','name="csrf_token"','id="admins-table"'],
 'admin/login.php'=>['name="username"','name="password"','name="csrf_token"'],
];
foreach($contracts as $rel=>$markers){$text=(string)file_get_contents($root.'/'.$rel);foreach($markers as $marker){uf_ok(strpos($text,$marker)!==false,"frozen form/DOM contract missing {$marker} in {$rel}");}}
echo "UI form/DOM contract checks passed.\n";
