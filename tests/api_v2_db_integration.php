<?php
declare(strict_types=1);
if (getenv('LICORA_V2_TEST_ALLOW_SCHEMA_RESET') !== '1') { echo "API v2 DB integration skipped (dedicated test DB not enabled).\n"; exit(0); }
$dsn = getenv('LICORA_TEST_DB_DSN') ?: '';
if ($dsn === '') { fwrite(STDERR,"LICORA_TEST_DB_DSN is required.\n"); exit(1); }
$db = new PDO($dsn, getenv('LICORA_TEST_DB_USER') ?: '', getenv('LICORA_TEST_DB_PASS') ?: '', [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
foreach (['v2_used_nonces','v2_refresh_tokens','v2_audit_logs','v2_device_credentials','v2_client_apps','blacklist','devices','licenses'] as $table) { $db->exec('DROP TABLE IF EXISTS `'.$table.'`'); }
$db->exec("CREATE TABLE licenses (id INT AUTO_INCREMENT PRIMARY KEY, license_key VARCHAR(50) NOT NULL UNIQUE, app_scope VARCHAR(120), status VARCHAR(20) NOT NULL, expires_at DATETIME NOT NULL, device_limit INT NOT NULL, total_devices INT NOT NULL DEFAULT 0) ENGINE=InnoDB");
$db->exec("CREATE TABLE devices (id INT AUTO_INCREMENT PRIMARY KEY, license_id INT NOT NULL, device_hash VARCHAR(255) NOT NULL, device_info TEXT, os VARCHAR(50), browser VARCHAR(50), login_time DATETIME, last_active DATETIME, is_active TINYINT NOT NULL DEFAULT 1, KEY(license_id,device_hash)) ENGINE=InnoDB");
$db->exec("CREATE TABLE blacklist (id INT AUTO_INCREMENT PRIMARY KEY, type VARCHAR(20), value VARCHAR(255), expires_at DATETIME NULL) ENGINE=InnoDB");
$migration=(string)file_get_contents(dirname(__DIR__).'/migration-v5.2.0-api-v2.sql');
$migration=preg_replace('/INSERT INTO settings[\s\S]*$/i','',$migration);
foreach (array_filter(array_map('trim',explode(';',$migration))) as $stmt) { $db->exec($stmt); }
require_once dirname(__DIR__).'/includes/v2/V2Exception.php';
require_once dirname(__DIR__).'/includes/v2/V2TokenService.php';
require_once dirname(__DIR__).'/includes/v2/V2Repository.php';
function ok($v,$m){if(!$v){fwrite(STDERR,"FAIL: {$m}\n");exit(1);}}
$db->exec("INSERT INTO licenses (license_key,app_scope,status,expires_at,device_limit,total_devices) VALUES ('AAAAAAAA-BBBBBBBB-CCCCCCCC-DDDDDDDD','vibrapilot','active',DATE_ADD(NOW(), INTERVAL 1 DAY),1,0)");
$db->exec("INSERT INTO v2_client_apps (app_id,display_name,is_active,min_version,access_token_ttl,refresh_token_ttl,clock_skew_seconds,rate_limit_per_hour) VALUES ('vibrapilot','VibraPilot',1,'1.0.6.2',3600,86400,300,300)");
$ec=openssl_pkey_new(['private_key_type'=>OPENSSL_KEYTYPE_EC,'curve_name'=>'prime256v1']); $details=openssl_pkey_get_details($ec); $public=trim((string)$details['key'])."\n"; $fp=hash('sha256',$public);
$repo=new V2Repository($db); $app=$repo->clientApp('vibrapilot');
$r=$repo->activate('AAAAAAAA-BBBBBBBB-CCCCCCCC-DDDDDDDD',$app,'1.0.6.2','device-000000000001',$public,$fp); ok((int)$r['credential']['id']>0,'activation');
$blocked=false; try{$repo->activate('AAAAAAAA-BBBBBBBB-CCCCCCCC-DDDDDDDD',$app,'1.0.6.2','device-000000000002',$public,$fp);}catch(V2Exception $e){$blocked=$e->machineCode()==='DEVICE_LIMIT_REACHED';} ok($blocked,'device limit');
$old=$repo->createRefreshToken((int)$r['credential']['id'],86400); $ctx=$repo->refreshContextForUpdate($old['token']); $new=$repo->completeRefresh($ctx); ok($new['token']!==$old['token'],'refresh rotation');
$reused=false; try{$repo->refreshContextForUpdate($old['token']);}catch(V2Exception $e){$reused=$e->machineCode()==='REFRESH_TOKEN_REUSED';} ok($reused,'refresh reuse detection');
$stmt=$db->prepare('SELECT revoked_at FROM v2_refresh_tokens WHERE token_hash=:h'); $stmt->execute([':h'=>$new['hash']]); ok($stmt->fetchColumn()!==null,'refresh family persisted revocation');
$repo->revokeCredential((int)$r['credential']['id']); $stmt=$db->query("SELECT status FROM v2_device_credentials LIMIT 1"); ok($stmt->fetchColumn()==='revoked','device revoke');
echo "API v2 DB integration checks passed.\n";
