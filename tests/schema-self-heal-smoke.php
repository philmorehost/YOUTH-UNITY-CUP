<?php

declare(strict_types=1);

use Yuc\Services\SchemaInstaller;

require dirname(__DIR__) . '/app/bootstrap.php';

$tables = (new SchemaInstaller())->declaredTables();
$expected = [
    'users', 'login_attempts', 'login_history', 'ip_blocks', 'audit_logs', 'notification_outbox',
    'transactions', 'shop_products', 'shop_orders', 'shop_order_items', 'password_reset_tokens',
    'system_settings', 'teams', 'team_players', 'venues', 'fixtures', 'site_mode_snapshots', 'registrations',
];
$missing = array_values(array_diff($expected, $tables));
if ($missing !== []) {
    throw new RuntimeException('The canonical schema is missing required tables: ' . implode(', ', $missing));
}
if (count($tables) !== count(array_unique($tables))) {
    throw new RuntimeException('The canonical schema parser returned duplicate table names.');
}

$schema = file_get_contents(dirname(__DIR__) . '/database/schema.mysql.sql');
if (!is_string($schema) || count($tables) !== substr_count($schema, 'CREATE TABLE IF NOT EXISTS')) {
    throw new RuntimeException('Not every CREATE TABLE declaration is recognized by the self-healing schema parser.');
}

$frontController = file_get_contents(dirname(__DIR__) . '/public/index.php');
if (!is_string($frontController) || !str_contains($frontController, 'ensureMissingTables($pdo)')) {
    throw new RuntimeException('Application requests do not run the missing-table self-healer.');
}

$worker = file_get_contents(dirname(__DIR__) . '/bin/send-notifications.php');
if (!is_string($worker) || !str_contains($worker, 'ensureMissingTables($pdo)')) {
    throw new RuntimeException('The notification worker does not run the missing-table self-healer.');
}

fwrite(STDOUT, "Schema self-heal table declarations and entry points passed." . PHP_EOL);
