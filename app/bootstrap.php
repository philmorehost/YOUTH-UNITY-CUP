<?php

declare(strict_types=1);

use Yuc\Core\ConfigStore;

if (!defined('YUC_ROOT')) {
    define('YUC_ROOT', dirname(__DIR__));
}

require_once YUC_ROOT . '/app/Support/helpers.php';

spl_autoload_register(static function (string $class): void {
    $prefix = 'Yuc\\';
    if (!str_starts_with($class, $prefix)) {
        return;
    }

    $relative = substr($class, strlen($prefix));
    $file = YUC_ROOT . '/app/' . str_replace('\\', '/', $relative) . '.php';
    if (is_file($file)) {
        require_once $file;
    }
});

ini_set('session.use_strict_mode', '1');
ini_set('session.use_only_cookies', '1');
ini_set('session.cookie_httponly', '1');

if (PHP_SAPI !== 'cli' && session_status() !== PHP_SESSION_ACTIVE) {
    $forwardedProto = strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? ''));
    $https = (!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off')
        || $forwardedProto === 'https';

    session_name('yuc_session');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => $https,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

// Loading this class here keeps configuration handling in one place while
// leaving database access lazy until a route actually needs it.
ConfigStore::ensureRuntimeDirectories();
