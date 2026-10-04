<?php

declare(strict_types=1);

namespace Yuc\Services;

use PDO;
use RuntimeException;

final class SchemaInstaller
{
    /**
     * Create only tables declared in the canonical schema that are absent from
     * the connected database. This is safe to run at request startup: it does
     * not drop, truncate, or rebuild existing tables.
     *
     * @return list<string> Names of tables created during this call.
     */
    public function ensureMissingTables(PDO $pdo): array
    {
        return $this->createMissingTables($pdo, $this->tableStatements());
    }

    /** @return list<string> */
    public function declaredTables(): array
    {
        return array_keys($this->tableStatements());
    }

    /** @return list<string> */
    public function install(PDO $pdo): array
    {
        $tableStatements = $this->tableStatements();
        $this->createMissingTables($pdo, $tableStatements);

        $transactionColumns = [
            'recipient_email' => "ALTER TABLE transactions ADD recipient_email VARCHAR(190) NOT NULL DEFAULT '' AFTER user_id",
            'payment_provider' => "ALTER TABLE transactions ADD payment_provider VARCHAR(30) NOT NULL DEFAULT '' AFTER status",
            'provider_reference' => "ALTER TABLE transactions ADD provider_reference VARCHAR(120) NULL AFTER payment_provider",
            'archived_at' => "ALTER TABLE transactions ADD archived_at DATETIME NULL AFTER updated_at",
        ];
        foreach ($transactionColumns as $column => $alterSql) {
            $columnInfo = $pdo->query("SHOW COLUMNS FROM transactions LIKE '" . $column . "'")->fetch();
            if (!is_array($columnInfo)) {
                $pdo->exec($alterSql);
            }
        }
        $providerIndex = $pdo->query("SHOW INDEX FROM transactions WHERE Key_name = 'uq_transactions_provider_reference'")->fetch();
        if (!is_array($providerIndex)) {
            $pdo->exec('ALTER TABLE transactions ADD UNIQUE KEY uq_transactions_provider_reference (provider_reference)');
        }

        $orderColumns = [
            'checkout_url' => "ALTER TABLE shop_orders ADD checkout_url VARCHAR(2048) NULL AFTER fulfillment_notes",
            'provider_amount_kobo' => "ALTER TABLE shop_orders ADD provider_amount_kobo VARCHAR(30) NULL AFTER checkout_url",
            'provider_currency' => "ALTER TABLE shop_orders ADD provider_currency CHAR(3) NULL AFTER provider_amount_kobo",
            'payment_review_reason' => "ALTER TABLE shop_orders ADD payment_review_reason VARCHAR(120) NULL AFTER provider_currency",
            'stock_reserved' => "ALTER TABLE shop_orders ADD stock_reserved TINYINT(1) NOT NULL DEFAULT 1 AFTER total_kobo",
            'archived_at' => "ALTER TABLE shop_orders ADD archived_at DATETIME NULL AFTER updated_at",
        ];
        foreach ($orderColumns as $column => $alterSql) {
            $columnInfo = $pdo->query("SHOW COLUMNS FROM shop_orders LIKE '" . $column . "'")->fetch();
            if (!is_array($columnInfo)) {
                $pdo->exec($alterSql);
            }
        }

        if ((int) $pdo->query('SELECT COUNT(*) FROM venues')->fetchColumn() === 0) {
            $venueSeed = $pdo->prepare(
                "INSERT IGNORE INTO venues (name, zone, address, capacity, status, created_at, updated_at) "
                . "VALUES (:name, :zone, '', 700, 'active', UTC_TIMESTAMP(), UTC_TIMESTAMP())"
            );
            foreach ([
                ['Alaafia', 'Zone 1'], ['Atewolara', 'Zone 2'], ['Idi-Oro', 'Zone 3'],
                ['Papa Ajao', 'Zone 4'], ['Ladipo', 'Zone 5'], ['Ilasamaja', 'Zone 6'],
                ['Itire', 'Zone 7'], ['Ijesha', 'Zone 8'], ['Olorunsogo', 'Zone 9'], ['Idi-Araba', 'Zone 10'],
            ] as [$venueName, $zone]) {
                $venueSeed->execute(['name' => $venueName, 'zone' => $zone]);
            }
        }

        return array_keys($tableStatements);
    }

    /** @return array<string,string> Table names mapped to their CREATE TABLE statements. */
    private function tableStatements(): array
    {
        $file = YUC_ROOT . '/database/schema.mysql.sql';
        if (!is_file($file) || !is_readable($file)) {
            throw new RuntimeException('The database schema file is missing or unreadable.');
        }
        $sql = file_get_contents($file);
        if ($sql === false) {
            throw new RuntimeException('The database schema could not be read.');
        }

        // The canonical schema contains ordinary CREATE TABLE statements, not
        // stored procedures; splitting at statement-ending semicolons is safe.
        $statements = preg_split('/;\s*(?:\r?\n|$)/', $sql) ?: [];
        $tables = [];
        foreach ($statements as $statement) {
            $statement = trim($statement);
            if ($statement === ''
                || preg_match('/\bCREATE\s+TABLE\s+IF\s+NOT\s+EXISTS\s+`?([A-Za-z0-9_]+)`?/i', $statement, $matches) !== 1) {
                continue;
            }
            $tableName = $matches[1];
            $key = strtolower($tableName);
            if (isset($tables[$key])) {
                throw new RuntimeException('The database schema declares the same table more than once.');
            }
            $tables[$key] = $statement;
        }
        if ($tables === []) {
            throw new RuntimeException('The database schema does not declare any application tables.');
        }
        return $tables;
    }

    /** @param array<string,string> $tableStatements @return list<string> */
    private function createMissingTables(PDO $pdo, array $tableStatements): array
    {
        $existingNames = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
        $existing = [];
        foreach ($existingNames as $name) {
            if (is_scalar($name)) {
                $existing[strtolower((string) $name)] = true;
            }
        }

        $created = [];
        foreach ($tableStatements as $key => $statement) {
            if (isset($existing[$key])) {
                continue;
            }
            // IF NOT EXISTS also makes concurrent first requests safe if they
            // discover and create the same missing table at the same time.
            $pdo->exec($statement);
            $existing[$key] = true;
            $created[] = $key;
        }
        if ($created !== []) {
            error_log('Youth Unity Cup automatically created missing schema tables: ' . implode(', ', $created));
        }
        return $created;
    }
}
