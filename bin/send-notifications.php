<?php

declare(strict_types=1);

use Yuc\Core\ConfigStore;
use Yuc\Core\Database;
use Yuc\Services\NotificationService;
use Yuc\Services\SchemaInstaller;

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit("Not found\n");
}

require dirname(__DIR__) . '/app/bootstrap.php';

$config = ConfigStore::load();
if (!ConfigStore::isInstalled($config) || !is_array($config)) {
    fwrite(STDERR, "Youth Unity Cup is not installed.\n");
    exit(1);
}

try {
    $pdo = Database::connect((array) $config['database']);
    (new SchemaInstaller())->ensureMissingTables($pdo);
    $result = (new NotificationService($pdo, $config))->dispatchQueued(50);
    printf(
        "Notification run complete: %d sent, %d still queued, %d permanently failed.\n",
        $result['sent'],
        $result['queued'],
        $result['failed']
    );
} catch (Throwable $exception) {
    error_log('Youth Unity Cup notification worker failed (' . get_class($exception) . ').');
    fwrite(STDERR, "Notification delivery failed. Check the server error log.\n");
    exit(1);
}
