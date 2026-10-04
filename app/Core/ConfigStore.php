<?php

declare(strict_types=1);

namespace Yuc\Core;

use RuntimeException;

final class ConfigStore
{
    public static function configPath(): string
    {
        return YUC_ROOT . '/config/local.php';
    }

    public static function pendingPath(): string
    {
        return YUC_ROOT . '/storage/install.pending.json';
    }

    public static function ensureRuntimeDirectories(): void
    {
        foreach ([YUC_ROOT . '/config', YUC_ROOT . '/storage'] as $directory) {
            if (!is_dir($directory) && !@mkdir($directory, 0700, true) && !is_dir($directory)) {
                continue;
            }
            @chmod($directory, 0700);
        }
    }

    /** @return array<string,mixed>|null */
    public static function load(): ?array
    {
        $path = self::configPath();
        if (!is_file($path) || !is_readable($path)) {
            return null;
        }

        $config = require $path;
        return is_array($config) ? $config : null;
    }

    /** @param array<string,mixed>|null $config */
    public static function isInstalled(?array $config = null): bool
    {
        $config ??= self::load();
        return is_array($config)
            && (($config['app']['installation_complete'] ?? false) === true)
            && isset($config['database']['name'], $config['admin']['id']);
    }

    /** @param array<string,mixed> $pending */
    public static function savePending(array $pending): void
    {
        self::ensureRuntimeDirectories();
        $json = json_encode($pending, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        self::writeAtomically(self::pendingPath(), $json . "\n", 0600);
    }

    /** @return array<string,mixed>|null */
    public static function loadPending(): ?array
    {
        $path = self::pendingPath();
        if (!is_file($path) || !is_readable($path)) {
            return null;
        }

        $json = file_get_contents($path);
        if ($json === false) {
            return null;
        }

        try {
            $pending = json_decode($json, true, 32, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return null;
        }

        return is_array($pending) ? $pending : null;
    }

    /** @param array<string,mixed> $config */
    public static function finalize(array $config): void
    {
        self::ensureRuntimeDirectories();
        $source = "<?php\n\ndeclare(strict_types=1);\n\nreturn " . var_export($config, true) . ";\n";
        self::writeAtomically(self::configPath(), $source, 0600);
        @unlink(self::pendingPath());
    }

    private static function writeAtomically(string $path, string $contents, int $mode): void
    {
        $directory = dirname($path);
        if (!is_dir($directory) || !is_writable($directory)) {
            throw new RuntimeException('The application cannot write its protected setup files.');
        }

        $temporary = $path . '.' . bin2hex(random_bytes(8)) . '.tmp';
        $written = @file_put_contents($temporary, $contents, LOCK_EX);
        if ($written === false || $written !== strlen($contents)) {
            @unlink($temporary);
            throw new RuntimeException('The application could not save its protected setup files.');
        }

        @chmod($temporary, $mode);
        if (!@rename($temporary, $path)) {
            @unlink($temporary);
            throw new RuntimeException('The application could not finalize its protected setup files.');
        }
        @chmod($path, $mode);
    }
}
