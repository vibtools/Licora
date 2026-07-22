<?php
require_once __DIR__ . '/database.php';
require_once __DIR__ . '/security.php';

class AdminHelpers {
    public static function tableExists($table) {
        try {
            $db = Database::getInstance();
            $s = $db->prepare("SHOW TABLES LIKE :t");
            $s->execute([':t' => $table]);
            return $s->fetch() !== false;
        } catch (Exception $e) {
            return false;
        }
    }

    public static function columnExists($table, $col) {
        try {
            $db = Database::getInstance();
            $table = preg_replace('/[^A-Za-z0-9_]/', '', (string)$table);
            $col = preg_replace('/[^A-Za-z0-9_]/', '', (string)$col);
            if ($table === '' || $col === '') {
                return false;
            }

            // information_schema is more reliable on hosts where prepared SHOW COLUMNS fails.
            $s = $db->prepare("SELECT COUNT(*) AS c FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :t AND COLUMN_NAME = :c");
            $s->execute([':t' => $table, ':c' => $col]);
            $row = $s->fetch();
            if ($row && (int)$row['c'] > 0) {
                return true;
            }

            // Fallback for older MySQL/MariaDB environments.
            $s = $db->query("SHOW COLUMNS FROM `$table`");
            foreach ($s->fetchAll() as $field) {
                if (isset($field['Field']) && strcasecmp($field['Field'], $col) === 0) {
                    return true;
                }
            }
            return false;
        } catch (Exception $e) {
            error_log('columnExists: ' . $table . '.' . $col . ' - ' . $e->getMessage());
            return false;
        }
    }

    public static function ensureColumn($table, $column, $definition) {
        try {
            if (!self::tableExists($table) || self::columnExists($table, $column)) {
                return true;
            }
            $db = Database::getInstance();
            $table = str_replace('`', '', $table);
            $column = str_replace('`', '', $column);
            $db->exec("ALTER TABLE `$table` ADD COLUMN `$column` $definition");
            return self::columnExists($table, $column);
        } catch (Exception $e) {
            error_log('ensureColumn: ' . $table . '.' . $column . ' - ' . $e->getMessage());
            return false;
        }
    }

    public static function ensureV5Schema() {
        $ok = true;
        $ok = self::ensureColumn('api_keys', 'app_name', "VARCHAR(120) DEFAULT NULL AFTER `name`") && $ok;
        $ok = self::ensureColumn('api_keys', 'scope_label', "VARCHAR(120) DEFAULT NULL AFTER `app_name`") && $ok;
        $ok = self::ensureColumn('licenses', 'app_scope', "VARCHAR(120) DEFAULT NULL AFTER `notes`") && $ok;
        $ok = self::ensureColumn('licenses', 'api_key_id', "INT(11) DEFAULT NULL AFTER `app_scope`") && $ok;
        return $ok;
    }

    public static function role() {
        return $_SESSION['admin_role'] ?? 'super_admin';
    }

    public static function canManage() {
        return in_array(self::role(), ['super_admin', 'manager'], true);
    }

    public static function canDelete() {
        return self::role() === 'super_admin';
    }

    public static function requireManage() {
        if (!self::canManage()) {
            http_response_code(403);
            die('Permission denied');
        }
    }

    public static function requireDelete() {
        if (!self::canDelete()) {
            http_response_code(403);
            die('Permission denied');
        }
    }

    public static function audit($type, $id, $action, $details = '') {
        try {
            $db = Database::getInstance();
            $admin = $_SESSION['admin_id'] ?? null;
            $txt = is_array($details) ? json_encode($details) : (string)$details;
            if (self::tableExists('audit_trail')) {
                $s = $db->prepare("INSERT INTO audit_trail (admin_id, entity_type, entity_id, action, details, ip_address, user_agent, created_at) VALUES (:a, :t, :i, :ac, :d, :ip, :ua, NOW())");
                $s->execute([
                    ':a' => $admin,
                    ':t' => $type,
                    ':i' => $id,
                    ':ac' => $action,
                    ':d' => $txt,
                    ':ip' => Security::getClientIP(),
                    ':ua' => $_SERVER['HTTP_USER_AGENT'] ?? ''
                ]);
            }
            $l = $db->prepare("INSERT INTO logs (license_id, admin_id, action, details, ip_address) VALUES (:l, :a, :ac, :d, :ip)");
            $l->execute([
                ':l' => $type === 'license' ? $id : null,
                ':a' => $admin,
                ':ac' => $action,
                ':d' => $txt,
                ':ip' => Security::getClientIP()
            ]);
        } catch (Exception $e) {
            error_log('audit: ' . $e->getMessage());
        }
    }

    public static function csv($name, $headers, $rows) {
        header('Content-Type:text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $name . '"');
        $o = fopen('php://output', 'w');
        fputcsv($o, $headers);
        foreach ($rows as $r) {
            fputcsv($o, $r);
        }
        fclose($o);
        exit;
    }
}
?>
