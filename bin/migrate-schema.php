<?php

declare(strict_types=1);

use Yuc\Core\ConfigStore;
use Yuc\Core\Database;
use Yuc\Services\SchemaInstaller;

require dirname(__DIR__) . '/app/bootstrap.php';

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit(1);
}

$config = ConfigStore::load();
if (!ConfigStore::isInstalled($config) || !is_array($config['database'] ?? null)) {
    fwrite(STDERR, "Youth Unity Cup is not installed; complete /install first.\n");
    exit(1);
}

try {
    $pdo = Database::connect($config['database']);
    $tables = (new SchemaInstaller())->install($pdo);
    fwrite(STDOUT, 'Schema is up to date. Verified ' . count($tables) . " application tables. Existing data was not dropped.\n");
} catch (Throwable $exception) {
    fwrite(STDERR, 'Schema update failed (' . get_class($exception) . '). Check the database account permissions and back up the database before retrying.' . "\n");
    exit(1);
}
