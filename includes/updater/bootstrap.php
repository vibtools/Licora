<?php
declare(strict_types=1);
require_once __DIR__.'/UpdateException.php';
require_once __DIR__.'/UpdateRuntime.php';
require_once __DIR__.'/UpdateSchema.php';
require_once __DIR__.'/UpdateRepository.php';
require_once __DIR__.'/UpdateLogger.php';
require_once __DIR__.'/HttpClient.php';
require_once __DIR__.'/ReleaseClient.php';
require_once __DIR__.'/ManifestVerifier.php';
require_once __DIR__.'/PreflightService.php';
require_once __DIR__.'/ArchiveValidator.php';
require_once __DIR__.'/BackupService.php';
require_once __DIR__.'/MigrationRunner.php';
require_once __DIR__.'/FileInstaller.php';
require_once __DIR__.'/RollbackService.php';
require_once __DIR__.'/UpdateLock.php';
require_once __DIR__.'/UpdateService.php';

function licora_updater_services(?PDO $db=null): array
{
    $db=$db ?: Database::getInstance();
    $schema=new UpdateSchema($db);$schema->ensure();
    $repo=new UpdateRepository($db);
    UpdateLock::recoverOrphaned($repo);
    $http=new HttpClient(defined('LICORA_UPDATE_HTTP_TIMEOUT')?(int)LICORA_UPDATE_HTTP_TIMEOUT:120);
    $releaseClient=new ReleaseClient($http);
    $service=new UpdateService($repo,$releaseClient,$http,new ManifestVerifier(),new PreflightService(),new ArchiveValidator(),new BackupService($repo),new MigrationRunner($repo),new FileInstaller($repo),new RollbackService($repo));
    return [$service,$repo,$schema];
}
