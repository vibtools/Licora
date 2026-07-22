<?php
require_once 'database.php';
require_once 'security.php';
require_once 'admin_helpers.php';
require_once 'validation.php';

class LicenseSystem {
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance();
    }
    
    // লাইসেন্স তৈরি
    public function createLicense($hours, $device_limit = 1, $admin_id = null, $notes = '', $app_scope = '', $api_key_id = null) {
        try {
            $this->db->beginTransaction();
            
            $license_key = $this->generateLicenseKey();
            $encrypted_key = Security::encrypt($license_key);
            $expires_at = date('Y-m-d H:i:s', strtotime("+{$hours} hours"));
            
            $query = "
                INSERT INTO licenses (license_key, encrypted_key, expires_at, device_limit, created_by, notes) 
                VALUES (:license_key, :encrypted_key, :expires_at, :device_limit, :created_by, :notes)
            ";
            
            $stmt = $this->db->prepare($query);
            $stmt->execute([
                ':license_key' => $license_key,
                ':encrypted_key' => $encrypted_key,
                ':expires_at' => $expires_at,
                ':device_limit' => $device_limit,
                ':created_by' => $admin_id,
                ':notes' => $notes
            ]);
            
            $license_id = $this->db->lastInsertId();
            if ($app_scope !== '' || $api_key_id) {
                try {
                    $up = $this->db->prepare('UPDATE licenses SET app_scope = :app_scope, api_key_id = :api_key_id WHERE id = :id');
                    $up->execute([
                        ':app_scope' => $app_scope,
                        ':api_key_id' => $api_key_id ? (int)$api_key_id : null,
                        ':id' => $license_id
                    ]);
                } catch (PDOException $columnError) {
                    // Older installs without V5 columns keep the old license behavior.
                    // The API contract remains unchanged; admins can run migration-v5-hotfix.sql to enable binding.
                    if ($columnError->getCode() !== '42S22') {
                        throw $columnError;
                    }
                    error_log('License app/API key binding columns missing: ' . $columnError->getMessage());
                }
            }
            
            // লগ এন্ট্রি
            if ($admin_id) {
                $this->addLog($license_id, $admin_id, 'license_created', "License created: " . substr($license_key, 0, 8) . "...");
                AdminHelpers::audit('license', $license_id, 'license_created', 'License created');
            }
            
            $this->db->commit();
            
            return [
                'success' => true,
                'license_key' => $license_key,
                'license_id' => $license_id,
                'expires_at' => $expires_at
            ];
            
        } catch (Exception $e) {
            $this->db->rollBack();
            return ['success' => false, 'message' => 'License creation failed: ' . $e->getMessage()];
        }
    }
    
    // লাইসেন্স ভেরিফাই (বিল্ট-ইন ডিভাইস লিমিট চেক সহ)
    // এই মেথডটি সম্পূর্ণ replace করুন (লাইন 75 থেকে):
    public function verifyLicense($license_key, $device_hash = null, $api_key_id = null) {
        try {
            $this->db->beginTransaction();
            
            // লাইসেন্স খুঁজুন - FIXED VERSION
            $query = "
                SELECT * FROM licenses 
                WHERE license_key = :license_key 
                AND status = 'active'
            ";
            
            $stmt = $this->db->prepare($query);
            $stmt->execute(['license_key' => $license_key]); // Colon removed from key
            $license = $stmt->fetch();
            
            if (!$license) {
                $this->db->rollBack();
                return ['success' => false, 'message' => 'Invalid license key'];
            }
            // API Key / App scope enforcement (Version 5)
            // API contract অপরিবর্তিত রাখা হয়েছে; verify.php থেকে পাওয়া API key id দিয়েই binding enforce হয়।
            if (!empty($license['api_key_id'])) {
                if (!$api_key_id || (int)$license['api_key_id'] !== (int)$api_key_id) {
                    $this->db->rollBack();
                    AdminHelpers::audit('license', $license['id'], 'license_api_key_denied', 'License is locked to a different API key');
                    return ['success' => false, 'message' => 'Invalid license key'];
                }
            } elseif (!empty($license['app_scope'])) {
                if (!$api_key_id) {
                    $this->db->rollBack();
                    AdminHelpers::audit('license', $license['id'], 'license_app_scope_denied', 'Scoped license used without API key context');
                    return ['success'=>false,'message'=>'Invalid license key'];
                }
                try {
                    $scopeStmt = $this->db->prepare("SELECT COALESCE(app_name, '') AS app_name, COALESCE(scope_label, '') AS scope_label FROM api_keys WHERE id = :id LIMIT 1");
                    $scopeStmt->execute([':id'=>$api_key_id]);
                    $apiScope = $scopeStmt->fetch();
                } catch (PDOException $columnError) {
                    $apiScope = false;
                }
                $appName = (string)($apiScope['app_name'] ?? '');
                $scopeLabel = (string)($apiScope['scope_label'] ?? '');
                if (!$apiScope || ((string)$license['app_scope'] !== $appName && (string)$license['app_scope'] !== $scopeLabel)) {
                    $this->db->rollBack();
                    AdminHelpers::audit('license', $license['id'], 'license_app_scope_denied', 'API key app/scope label mismatch');
                    return ['success'=>false,'message'=>'Invalid license key'];
                }
            }
            
            // এক্সপায়ার্ড চেক
            if (strtotime($license['expires_at']) < time()) {
                $this->db->rollBack();
                $this->updateLicenseStatus($license['id'], 'expired');
                return ['success' => false, 'message' => 'License has expired'];
            }
            
            // ব্ল্যাকলিস্ট চেক
            if ($this->isBlacklisted($license_key, $device_hash)) {
                $this->db->rollBack();
                return ['success' => false, 'message' => 'Access denied'];
            }
            
            // ডিভাইস লিমিট চেক
            $active_devices = $this->getActiveDeviceCount($license['id']);
            
            if ($device_hash) {
                // যদি ডিভাইস আগে থেকেই রেজিস্টার করা থাকে
                $existing_device = $this->getDeviceByHash($license['id'], $device_hash);
                
                if ($existing_device) {
                    // ডিভাইস আপডেট করুন
                    $this->updateDeviceActivity($existing_device['id']);
                    $this->db->commit();
                    
                    return [
                        'success' => true,
                        'license' => $license,
                        'message' => 'Device reconnected',
                        'total_devices' => $license['total_devices'] ?? 0,
                        'active_devices' => $active_devices
                    ];
                }
                
                // নতুন ডিভাইস যোগ করতে চাইছে: ডিভাইস লিমিট শেষ হলে নতুন ডিভাইস রেজিস্টার করা হবে না।
                if ($active_devices >= $license['device_limit']) {
                    $this->db->rollBack();
                    AdminHelpers::audit('license', $license['id'], 'device_limit_blocked', 'New device blocked because device limit is full');
                    return ['success' => false, 'message' => 'Device limit reached'];
                }
                
                // নতুন ডিভাইস রেজিস্টার করো
                $device_id = $this->registerDevice($license['id'], $device_hash);
                
                $this->db->commit();
                
                return [
                    'success' => true,
                    'license' => $license,
                    'device_id' => $device_id,
                    'message' => 'New device registered',
                    'total_devices' => $license['total_devices'] ?? 0,
                    'active_devices' => $active_devices + 1
                ];
            }
            
            $this->db->commit();
            
            return [
                'success' => true,
                'license' => $license,
                'active_devices' => $active_devices,
                'total_devices' => $license['total_devices'] ?? 0
            ];
            
        } catch (Exception $e) {
            $this->db->rollBack();
            error_log("License verification error: " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine());
            return ['success' => false, 'message' => 'Verification failed'];
        }
    }
    
    // ডিভাইস রেজিস্টার
    private function registerDevice($license_id, $device_hash) {
        $device_info = [
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
            'ip' => Security::getClientIP(),
            'platform' => php_uname('s') . ' ' . php_uname('r'),
            'timestamp' => time()
        ];
        
        $os = $this->detectOS();
        $browser = $this->detectBrowser();
        
        $query = "
            INSERT INTO devices (license_id, device_hash, device_info, os, browser, login_time, last_active) 
            VALUES (:license_id, :device_hash, :device_info, :os, :browser, NOW(), NOW())
        ";
        
        $stmt = $this->db->prepare($query);
        $stmt->execute([
            'license_id' => $license_id,
            'device_hash' => $device_hash,
            'device_info' => json_encode($device_info),
            'os' => $os,
            'browser' => $browser
        ]);
        
        $device_id = $this->db->lastInsertId();
        
        // টোটাল ডিভাইস কাউন্ট আপডেট
        $this->updateTotalDevices($license_id);
        
        // লগ এন্ট্রি
        $this->addLog($license_id, null, 'device_registered', "Device registered: " . substr($device_hash, 0, 20) . "...");
        AdminHelpers::audit('device', $device_id, 'device_registered', 'Device registered');
        
        return $device_id;
    }
    
    // পুরাতন ডিভাইস লগআউট
    private function logoutOldestDevice($license_id) {
        $query = "
            UPDATE devices 
            SET is_active = FALSE 
            WHERE license_id = :license_id 
            AND is_active = TRUE 
            ORDER BY last_active ASC 
            LIMIT 1
        ";
        
        $stmt = $this->db->prepare($query);
        $stmt->execute([':license_id' => $license_id]);
        
        // লগ এন্ট্রি
        $this->addLog($license_id, null, 'device_logged_out', 'Oldest device logged out due to limit');
    }
    
    // ডিভাইস অ্যাক্টিভিটি আপডেট
    private function updateDeviceActivity($device_id) {
        $query = "
            UPDATE devices 
            SET last_active = NOW(), is_active = TRUE 
            WHERE id = :device_id
        ";
        
        $stmt = $this->db->prepare($query);
        $stmt->execute([':device_id' => $device_id]);
    }
    
    // অ্যাকটিভ ডিভাইস কাউন্ট
    private function getActiveDeviceCount($license_id) {
        $query = "
            SELECT COUNT(*) as count 
            FROM devices 
            WHERE license_id = :license_id 
            AND is_active = TRUE
        ";
        
        $stmt = $this->db->prepare($query);
        $stmt->execute([':license_id' => $license_id]);
        return $stmt->fetch()['count'];
    }
    
    // ডিভাইস হ্যাশ দ্বারা ডিভাইস পান
    private function getDeviceByHash($license_id, $device_hash) {
        $query = "
            SELECT * FROM devices 
            WHERE license_id = :license_id 
            AND device_hash = :device_hash
        ";
        
        $stmt = $this->db->prepare($query);
        $stmt->execute([
            ':license_id' => $license_id,
            ':device_hash' => $device_hash
        ]);
        
        return $stmt->fetch();
    }
    
    // লাইসেন্স কী জেনারেট
    private function generateLicenseKey() {
        $segments = [];
        for ($i = 0; $i < 4; $i++) {
            $segments[] = strtoupper(bin2hex(random_bytes(4)));
        }
        return implode('-', $segments);
    }
    
    // টোটাল ডিভাইস আপডেট
    private function updateTotalDevices($license_id) {
        $query = "
            UPDATE licenses 
            SET total_devices = (
                SELECT COUNT(*) FROM devices 
                WHERE license_id = :license_id
            ),
            updated_at = NOW()
            WHERE id = :license_id2
        ";
        
        $stmt = $this->db->prepare($query);
        $stmt->execute([
            'license_id' => $license_id,
            'license_id2' => $license_id
        ]);
        
        return $stmt->rowCount() > 0;
    }
    
    // লাইসেন্স স্ট্যাটাস আপডেট
    public function updateLicenseStatus($license_id, $status) {
        $query = "
            UPDATE licenses 
            SET status = :status, updated_at = NOW() 
            WHERE id = :license_id
        ";
        
        $stmt = $this->db->prepare($query);
        $stmt->execute([
            ':license_id' => $license_id,
            ':status' => $status
        ]);
        
        // লগ এন্ট্রি
        $this->addLog($license_id, null, 'status_changed', "License status changed to {$status}");
        AdminHelpers::audit('license', $license_id, 'license_status_changed', "License status changed to {$status}");
    }
    
    // লাইসেন্স এক্সটেন্ড
    public function extendLicense($license_id, $additional_hours) {
        $query = "
            UPDATE licenses 
            SET expires_at = DATE_ADD(expires_at, INTERVAL :hours HOUR), 
                updated_at = NOW() 
            WHERE id = :license_id
        ";
        
        $stmt = $this->db->prepare($query);
        $stmt->execute([
            ':license_id' => $license_id,
            ':hours' => $additional_hours
        ]);
        
        // লগ এন্ট্রি
        $this->addLog($license_id, null, 'license_extended', "License extended by {$additional_hours} hours");
        AdminHelpers::audit('license', $license_id, 'license_extended', "License extended by {$additional_hours} hours");
        
        return $stmt->rowCount() > 0;
    }
    
    // লাইসেন্স ব্ল্যাকলিস্ট
    public function blacklistLicense($license_key, $reason, $admin_id) {
        $query = "
            INSERT INTO blacklist (type, value, reason, banned_by, expires_at) 
            VALUES ('license', :value, :reason, :banned_by, NULL)
        ";
        
        $stmt = $this->db->prepare($query);
        $stmt->execute([
            ':value' => $license_key,
            ':reason' => $reason,
            ':banned_by' => $admin_id
        ]);
        
        // লাইসেন্স সাসপেন্ড করো
        $this->suspendLicenseByKey($license_key);
        
        // লগ এন্ট্রি
        $this->addLog(null, $admin_id, 'license_blacklisted', "License blacklisted: {$license_key}");
        
        return true;
    }
    
    // লাইসেন্স সাসপেন্ড
    private function suspendLicenseByKey($license_key) {
        $query = "
            UPDATE licenses 
            SET status = 'suspended', updated_at = NOW() 
            WHERE license_key = :license_key
        ";
        
        $stmt = $this->db->prepare($query);
        $stmt->execute([':license_key' => $license_key]);
    }
    
    // ব্ল্যাকলিস্ট চেক
    private function isBlacklisted($license_key, $device_hash = null) {
        $query = "
            SELECT * FROM blacklist 
            WHERE (
                (type = 'license' AND value = :license_key)
                OR (type = 'device' AND value = :device_hash)
                OR (type = 'ip' AND value = :ip_address)
            )
            AND (expires_at IS NULL OR expires_at > NOW())
            LIMIT 1
        ";
        
        $stmt = $this->db->prepare($query);
        $stmt->execute([
            'license_key' => $license_key,
            'device_hash' => $device_hash,
            'ip_address' => Security::getClientIP()
        ]);
        
        return $stmt->fetch() !== false;
    }
    
    // লগ এন্ট্রি তৈরি
    public function addLog($license_id, $admin_id, $action, $details) {
        $query = "
            INSERT INTO logs (license_id, admin_id, action, details, ip_address) 
            VALUES (:license_id, :admin_id, :action, :details, :ip_address)
        ";
        
        $stmt = $this->db->prepare($query);
        $stmt->execute([
            ':license_id' => $license_id,
            ':admin_id' => $admin_id,
            ':action' => $action,
            ':details' => $details,
            ':ip_address' => Security::getClientIP()
        ]);
    }
    
    // অপারেটিং সিস্টেম ডিটেক্ট
    private function detectOS() {
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        
        if (strpos($user_agent, 'Windows') !== false) return 'Windows';
        if (strpos($user_agent, 'Mac') !== false) return 'macOS';
        if (strpos($user_agent, 'Linux') !== false) return 'Linux';
        if (strpos($user_agent, 'Android') !== false) return 'Android';
        if (strpos($user_agent, 'iOS') !== false) return 'iOS';
        
        return 'Unknown';
    }
    
    // ব্রাউজার ডিটেক্ট
    private function detectBrowser() {
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        
        if (strpos($user_agent, 'Chrome') !== false) return 'Chrome';
        if (strpos($user_agent, 'Firefox') !== false) return 'Firefox';
        if (strpos($user_agent, 'Safari') !== false) return 'Safari';
        if (strpos($user_agent, 'Edge') !== false) return 'Edge';
        if (strpos($user_agent, 'Opera') !== false) return 'Opera';
        
        return 'Unknown';
    }
    
    // স্ট্যাটিস্টিক্স পান
    public function getStats() {
        $stats = [];
        
        // টোটাল লাইসেন্স
        $stmt = $this->db->query("SELECT COUNT(*) as count FROM licenses");
        $stats['total_licenses'] = $stmt->fetch()['count'];
        
        // একটিভ লাইসেন্স
        $stmt = $this->db->query("
            SELECT COUNT(*) as count FROM licenses 
            WHERE status = 'active' AND expires_at > NOW()
        ");
        $stats['active_licenses'] = $stmt->fetch()['count'];
        
        // এক্সপায়ার্ড লাইসেন্স
        $stmt = $this->db->query("
            SELECT COUNT(*) as count FROM licenses 
            WHERE expires_at <= NOW()
        ");
        $stats['expired_licenses'] = $stmt->fetch()['count'];
        
        // সাসপেন্ডেড লাইসেন্স
        $stmt = $this->db->query("
            SELECT COUNT(*) as count FROM licenses 
            WHERE status = 'suspended'
        ");
        $stats['suspended_licenses'] = $stmt->fetch()['count'];
        
        // টোটাল ডিভাইস
        $stmt = $this->db->query("SELECT COUNT(*) as count FROM devices");
        $stats['total_devices'] = $stmt->fetch()['count'];
        
        // একটিভ ডিভাইস
        $stmt = $this->db->query("
            SELECT COUNT(*) as count FROM devices 
            WHERE is_active = TRUE
        ");
        $stats['active_devices'] = $stmt->fetch()['count'];
        
        return $stats;
    }
    public function getLicenseRiskScore($license_id) {
        $risk = ['score'=>0,'level'=>'Low','device_count'=>0,'ip_count'=>0];
        try {
            $st=$this->db->prepare("SELECT COUNT(*) AS c FROM devices WHERE license_id=:id");$st->execute([':id'=>$license_id]);$risk['device_count']=(int)$st->fetch()['c'];
            $st=$this->db->prepare("SELECT device_info FROM devices WHERE license_id=:id");$st->execute([':id'=>$license_id]);$ips=[];
            foreach($st->fetchAll() as $row){$i=json_decode($row['device_info']??'',true); if(is_array($i)&&!empty($i['ip'])){$ips[$i['ip']]=1;}}
            $risk['ip_count']=count($ips); $risk['score']=min(100, $risk['device_count']*10 + $risk['ip_count']*12);
            if($risk['score']>=70)$risk['level']='High'; elseif($risk['score']>=35)$risk['level']='Medium';
        } catch(Exception $e) {}
        return $risk;
    }

}
?>