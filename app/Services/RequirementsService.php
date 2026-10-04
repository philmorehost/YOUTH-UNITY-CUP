<?php

declare(strict_types=1);

namespace Yuc\Services;

final class RequirementsService
{
    /** @return array{checks:list<array{label:string,required:string,actual:string,ok:bool}>,ready:bool} */
    public function inspect(): array
    {
        $checks = [];
        $add = static function (string $label, string $required, string $actual, bool $ok) use (&$checks): void {
            $checks[] = ['label' => $label, 'required' => $required, 'actual' => $actual, 'ok' => $ok];
        };

        $add('PHP version', '8.1 or newer', PHP_VERSION, version_compare(PHP_VERSION, '8.1.0', '>='));

        foreach (['pdo', 'pdo_mysql', 'curl', 'openssl', 'mbstring', 'json', 'session'] as $extension) {
            $add(
                'PHP extension: ' . $extension,
                'Enabled',
                extension_loaded($extension) ? 'Enabled' : 'Missing',
                extension_loaded($extension)
            );
        }

        $configDirectory = YUC_ROOT . '/config';
        $storageDirectory = YUC_ROOT . '/storage';
        $add('Protected config directory', 'Writable by PHP', is_writable($configDirectory) ? 'Writable' : 'Not writable', is_writable($configDirectory));
        $add('Protected storage directory', 'Writable by PHP', is_writable($storageDirectory) ? 'Writable' : 'Not writable', is_writable($storageDirectory));

        $ready = !in_array(false, array_column($checks, 'ok'), true);
        return ['checks' => $checks, 'ready' => $ready];
    }
}
