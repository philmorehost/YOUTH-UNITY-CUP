<?php

declare(strict_types=1);

namespace Yuc\Core;

use PDO;
use RuntimeException;

final class Database
{
    /** @param array<string,mixed> $settings */
    public static function connect(array $settings): PDO
    {
        $host = (string) ($settings['host'] ?? '');
        $port = (int) ($settings['port'] ?? 3306);
        $name = (string) ($settings['name'] ?? '');
        $username = (string) ($settings['username'] ?? '');
        $password = (string) ($settings['password'] ?? '');

        if ($host === '' || !preg_match('/^[A-Za-z0-9.:-]+$/', $host)) {
            throw new RuntimeException('The database host is not valid.');
        }
        if ($port < 1 || $port > 65535 || !preg_match('/^[A-Za-z0-9_]+$/', $name)) {
            throw new RuntimeException('The database name or port is not valid.');
        }

        $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $host, $port, $name);
        $pdo = new PDO($dsn, $username, $password, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::ATTR_STRINGIFY_FETCHES => false,
            PDO::ATTR_TIMEOUT => 8,
        ]);
        $pdo->exec("SET time_zone = '+00:00'");

        return $pdo;
    }
}
