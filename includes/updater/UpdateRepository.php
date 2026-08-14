<?php
declare(strict_types=1);

final class UpdateRepository
{
    private PDO $db;
    public function __construct(PDO $db) { $this->db = $db; }
    public function db(): PDO { return $this->db; }

    public function withCoordinatorLock(callable $callback)
    {
        if ($this->db->inTransaction()) {
            throw new UpdateException('UPDATE_COORDINATOR_BUSY', 'Updater coordinator cannot start inside another database transaction.', 409);
        }
        $this->db->beginTransaction();
        try {
            // A permanent settings row gives MySQL/MariaDB a real row to lock, making start/rollback
            // decisions atomic even when two Super Admin requests arrive at the same instant.
            $seed=$this->db->prepare("INSERT INTO settings (setting_key,setting_value) VALUES ('updater_coordinator_lock','1') ON DUPLICATE KEY UPDATE setting_value=setting_value");
            $seed->execute();
            $lock=$this->db->query("SELECT setting_value FROM settings WHERE setting_key='updater_coordinator_lock' FOR UPDATE");
            $lock->fetchColumn();
            $result=$callback();
            $this->db->commit();
            return $result;
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) { $this->db->rollBack(); }
            throw $e;
        }
    }

    public function createJob(array $data): array
    {
        $uuid = self::uuidV4();
        $stmt = $this->db->prepare('INSERT INTO update_jobs (job_uuid, admin_id, from_version, target_version, release_tag, release_url, status, stage, progress, context_json, created_at, updated_at) VALUES (:uuid,:admin,:from_version,:target_version,:tag,:url,\'running\',\'fetch_manifest\',1,:context,NOW(),NOW())');
        $stmt->execute([
            ':uuid'=>$uuid, ':admin'=>$data['admin_id'] ?? null, ':from_version'=>(string)$data['from_version'],
            ':target_version'=>(string)$data['target_version'], ':tag'=>(string)$data['release_tag'],
            ':url'=>(string)($data['release_url'] ?? ''), ':context'=>json_encode($data['context'] ?? [], JSON_UNESCAPED_SLASHES),
        ]);
        return $this->job($uuid) ?? throw new RuntimeException('Unable to read created update job.');
    }

    public function job(string $uuid): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM update_jobs WHERE job_uuid = :uuid LIMIT 1');
        $stmt->execute([':uuid'=>$uuid]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function activeJob(): ?array
    {
        $stmt = $this->db->query("SELECT * FROM update_jobs WHERE status IN ('running','rollback_running') ORDER BY created_at DESC LIMIT 1");
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function updateJob(string $uuid, array $fields): array
    {
        $allowed = ['manifest_json','status','stage','progress','context_json','error_code','error_message','rollback_status','finished_at'];
        $sets=[]; $params=[':uuid'=>$uuid];
        foreach ($fields as $key=>$value) {
            if (!in_array($key, $allowed, true)) { continue; }
            $sets[] = "`{$key}` = :{$key}";
            $params[":{$key}"] = $value;
        }
        if ($sets) {
            $sets[] = 'updated_at = NOW()';
            $stmt=$this->db->prepare('UPDATE update_jobs SET '.implode(', ', $sets).' WHERE job_uuid = :uuid');
            $stmt->execute($params);
        }
        return $this->job($uuid) ?? throw new RuntimeException('Update job disappeared.');
    }

    public function context(array $job): array
    {
        $context=json_decode((string)($job['context_json'] ?? '{}'), true);
        return is_array($context) ? $context : [];
    }

    public function saveContext(string $uuid, array $context, array $extra=[]): array
    {
        return $this->updateJob($uuid, array_merge(['context_json'=>json_encode($context, JSON_UNESCAPED_SLASHES)], $extra));
    }

    public function appendEvent(string $uuid, string $level, string $stage, string $code, string $message, array $context=[]): int
    {
        $allowed=['info','debug','warning','success','error'];
        if (!in_array($level,$allowed,true)) { $level='info'; }
        $stmt=$this->db->prepare('INSERT INTO update_events (job_uuid,level,stage,event_code,message,context_json,created_at) VALUES (:uuid,:level,:stage,:code,:message,:context,NOW())');
        $stmt->execute([':uuid'=>$uuid,':level'=>$level,':stage'=>$stage,':code'=>$code,':message'=>$message,':context'=>$context ? json_encode($context,JSON_UNESCAPED_SLASHES) : null]);
        return (int)$this->db->lastInsertId();
    }

    public function eventsSince(string $uuid, int $after=0, int $limit=250): array
    {
        $limit=max(1,min(500,$limit));
        $stmt=$this->db->prepare("SELECT id,level,stage,event_code,message,created_at FROM update_events WHERE job_uuid=:uuid AND id>:after ORDER BY id ASC LIMIT {$limit}");
        $stmt->execute([':uuid'=>$uuid,':after'=>max(0,$after)]);
        return $stmt->fetchAll();
    }

    public function allEvents(string $uuid): array
    {
        $stmt=$this->db->prepare('SELECT id,level,stage,event_code,message,created_at FROM update_events WHERE job_uuid=:uuid ORDER BY id ASC');
        $stmt->execute([':uuid'=>$uuid]);
        return $stmt->fetchAll();
    }

    public function history(int $limit=20): array
    {
        $limit=max(1,min(100,$limit));
        return $this->db->query("SELECT job_uuid,from_version,target_version,release_tag,status,stage,progress,error_code,error_message,rollback_status,created_at,finished_at FROM update_jobs ORDER BY created_at DESC LIMIT {$limit}")->fetchAll();
    }

    public function getSetting(string $key, ?string $default=null): ?string
    {
        $stmt=$this->db->prepare('SELECT setting_value FROM settings WHERE setting_key=:key LIMIT 1');
        $stmt->execute([':key'=>$key]);
        $value=$stmt->fetchColumn();
        return $value===false ? $default : (string)$value;
    }

    public function setSetting(string $key, string $value): void
    {
        $stmt=$this->db->prepare('INSERT INTO settings (setting_key,setting_value) VALUES (:key,:value) ON DUPLICATE KEY UPDATE setting_value=:value2');
        $stmt->execute([':key'=>$key,':value'=>$value,':value2'=>$value]);
    }

    public function migration(string $id): ?array
    {
        $stmt=$this->db->prepare('SELECT * FROM app_migrations WHERE migration_id=:id LIMIT 1');
        $stmt->execute([':id'=>$id]); $row=$stmt->fetch(); return $row ?: null;
    }

    public function startMigration(string $id,string $version,string $checksum): void
    {
        $existing=$this->migration($id);
        if ($existing) {
            if (($existing['status'] ?? '')==='applied' && hash_equals((string)$existing['checksum'],$checksum)) { return; }
            if (!hash_equals((string)$existing['checksum'],$checksum)) { throw new UpdateException('MIGRATION_CHECKSUM_CONFLICT','A migration ID already exists with a different checksum.',409); }
            $stmt=$this->db->prepare("UPDATE app_migrations SET status='running',started_at=NOW(),applied_at=NULL,error_message=NULL WHERE migration_id=:id");
            $stmt->execute([':id'=>$id]); return;
        }
        $stmt=$this->db->prepare("INSERT INTO app_migrations (migration_id,release_version,checksum,status,started_at) VALUES (:id,:version,:checksum,'running',NOW())");
        $stmt->execute([':id'=>$id,':version'=>$version,':checksum'=>$checksum]);
    }

    public function finishMigration(string $id,int $ms): void
    {
        $stmt=$this->db->prepare("UPDATE app_migrations SET status='applied',applied_at=NOW(),execution_ms=:ms,error_message=NULL WHERE migration_id=:id");
        $stmt->execute([':id'=>$id,':ms'=>max(0,$ms)]);
    }

    public function failMigration(string $id,string $message): void
    {
        $stmt=$this->db->prepare("UPDATE app_migrations SET status='failed',error_message=:message WHERE migration_id=:id");
        $stmt->execute([':id'=>$id,':message'=>substr($message,0,2000)]);
    }

    public function removeMigrationRecord(string $id): void
    {
        $stmt=$this->db->prepare('DELETE FROM app_migrations WHERE migration_id=:id'); $stmt->execute([':id'=>$id]);
    }

    public static function uuidV4(): string
    {
        $data=random_bytes(16); $data[6]=chr((ord($data[6])&0x0f)|0x40); $data[8]=chr((ord($data[8])&0x3f)|0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data),4));
    }
}
