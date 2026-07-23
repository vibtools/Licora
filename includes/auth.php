<?php
require_once 'config.php';
require_once 'database.php';
require_once 'security.php';
require_once 'admin_helpers.php';

class Auth {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance();
    }

    // এডমিন লগইন
    public function adminLogin($username, $password) {
        // ফেল্ড লগইন চেক
        $this->checkFailedLogins();

        $stmt = $this->db->prepare("SELECT * FROM admin_users WHERE username = :username");
        $stmt->execute([':username' => $username]);
        $user = $stmt->fetch();

        if ($user) {
            // অ্যাকাউন্ট লকড চেক
            if ($user['locked_until'] && strtotime($user['locked_until']) > time()) {
                $this->logFailedLogin($username, 'Account locked');
                return ['success' => false, 'message' => 'Account is locked. Try again later.'];
            }

            // পাসওয়ার্ড ভেরিফাই
            if (Security::verifyPassword($password, $user['password'])) {
                // সফল লগইন
                if (Security::passwordNeedsRehash($user['password'])) {
                    $rehash = $this->db->prepare("UPDATE admin_users SET password = :password WHERE id = :id");
                    $rehash->execute([':password' => Security::hashPassword($password), ':id' => $user['id']]);
                }
                session_regenerate_id(true);
                $this->resetFailedAttempts($username);

                // সেশন আপডেট
                $_SESSION['admin_id'] = $user['id'];
                $_SESSION['admin_username'] = $user['username'];
                $_SESSION['admin_email'] = $user['email'];
                $_SESSION['admin_role'] = $user['role'] ?? 'super_admin';
                $_SESSION['admin_logged_in'] = true;
                $_SESSION['login_time'] = time();
                $_SESSION['session_ip'] = Security::getClientIP();
                $_SESSION['session_user_agent'] = $_SERVER['HTTP_USER_AGENT'] ?? '';

                // CSRF টোকেন জেনারেট
                Security::generateCSRFToken();

                // শেষ লগইন টাইম আপডেট
                $update = $this->db->prepare("UPDATE admin_users SET last_login = NOW() WHERE id = :id");
                $update->execute([':id' => $user['id']]);

                // লগ এন্ট্রি
                $this->logAdminAction($user['id'], 'login', 'Admin logged in');
                AdminHelpers::audit('admin', $user['id'], 'login', 'Admin logged in');

                return ['success' => true, 'user' => $user];
            }
        }

        // ব্যর্থ লগইন
        $this->incrementFailedAttempts($username);
        $this->logFailedLogin($username, 'Invalid credentials');

        return ['success' => false, 'message' => 'Invalid username or password'];
    }

    private function invalidateAdminSession($action, $details) {
        $adminId = $_SESSION['admin_id'] ?? null;
        try {
            AdminHelpers::audit('security', $adminId, $action, $details);
        } catch (Throwable $e) {
            error_log('Session security log failed: ' . $e->getMessage());
        }

        $_SESSION = [];
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
    }

    // এডমিন লগড ইন চেক
    public function isAdminLoggedIn() {
        if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
            return false;
        }

        if (isset($_SESSION['session_user_agent']) && $_SESSION['session_user_agent'] !== ($_SERVER['HTTP_USER_AGENT'] ?? '')) {
            $this->invalidateAdminSession('session_user_agent_mismatch', 'Admin session invalidated because the user agent changed');
            return false;
        }

        // Preserve the existing 30-minute inactivity contract and enforce it on every admin page.
        $timeout = 30 * 60;
        $lastActivity = (int)($_SESSION['login_time'] ?? 0);
        if ($lastActivity > 0 && (time() - $lastActivity) > $timeout) {
            $this->invalidateAdminSession('session_timeout', 'Admin session expired after 30 minutes of inactivity');
            return false;
        }

        $_SESSION['login_time'] = time();
        return true;
    }

    // লগআউট
    public function adminLogout() {
        if ($this->isAdminLoggedIn()) {
            $this->logAdminAction($_SESSION['admin_id'], 'logout', 'Admin logged out');
        }

        $_SESSION = [];
        session_destroy();
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        return true;
    }

    // ব্যর্থ লগইন চেক
    private function checkFailedLogins() {
        $ip = Security::getClientIP();
        $stmt = $this->db->prepare("
            SELECT COUNT(*) as attempts 
            FROM failed_logins 
            WHERE ip_address = :ip AND attempt_time > DATE_SUB(NOW(), INTERVAL 15 MINUTE)
        ");
        $stmt->execute([':ip' => $ip]);
        $result = $stmt->fetch();

        if ($result['attempts'] >= 5) {
            die('Too many failed login attempts. Please try again later.');
        }
    }

    // ব্যর্থ লগইন রেকর্ড
    private function logFailedLogin($username, $reason) {
        $stmt = $this->db->prepare("
            INSERT INTO failed_logins (ip_address, username, user_agent) 
            VALUES (:ip, :username, :user_agent)
        ");
        $stmt->execute([
            ':ip' => Security::getClientIP(),
            ':username' => $username,
            ':user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? ''
        ]);
    }

    // ব্যর্থ প্রচেষ্টা ইনক্রিমেন্ট
    private function incrementFailedAttempts($username) {
        $stmt = $this->db->prepare("
            UPDATE admin_users 
            SET failed_attempts = failed_attempts + 1 
            WHERE username = :username
        ");
        $stmt->execute([':username' => $username]);

        // ৫+ ব্যর্থ প্রচেষ্টায় অ্যাকাউন্ট লক
        $check = $this->db->prepare("SELECT failed_attempts FROM admin_users WHERE username = :username");
        $check->execute([':username' => $username]);
        $user = $check->fetch();

        if ($user['failed_attempts'] >= 5) {
            $lock = $this->db->prepare("
                UPDATE admin_users 
                SET locked_until = DATE_ADD(NOW(), INTERVAL 30 MINUTE) 
                WHERE username = :username
            ");
            $lock->execute([':username' => $username]);
        }
    }

    // ব্যর্থ প্রচেষ্টা রিসেট
    private function resetFailedAttempts($username) {
        $stmt = $this->db->prepare("
            UPDATE admin_users 
            SET failed_attempts = 0, locked_until = NULL 
            WHERE username = :username
        ");
        $stmt->execute([':username' => $username]);
    }

    // এডমিন অ্যাকশন লগ
    public function logAdminAction($admin_id, $action, $details) {
        $stmt = $this->db->prepare("
            INSERT INTO logs (admin_id, action, details, ip_address) 
            VALUES (:admin_id, :action, :details, :ip_address)
        ");
        $stmt->execute([
            ':admin_id' => $admin_id,
            ':action' => $action,
            ':details' => $details,
            ':ip_address' => Security::getClientIP()
        ]);
    }

    // ইউজার আইডি পান
    public function getUserId() {
        return $_SESSION['admin_id'] ?? null;
    }

    public function getRole() { return $_SESSION['admin_role'] ?? 'super_admin'; }
    public function canManage() { return in_array($this->getRole(), ['super_admin','manager'], true); }
    public function canDelete() { return $this->getRole() === 'super_admin'; }
    // ইউজারনেম পান
    public function getUsername() {
        return $_SESSION['admin_username'] ?? null;
    }

    // সেশন ভ্যালিডিটি চেক
    public function checkSessionValidity() {
        return $this->isAdminLoggedIn();
    }
}
?>
