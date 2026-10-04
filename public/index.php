<?php

declare(strict_types=1);

use Yuc\Controllers\AdminController;
use Yuc\Controllers\AdminOperationsController;
use Yuc\Controllers\InstallerController;
use Yuc\Controllers\PublicSiteController;
use Yuc\Controllers\ShopController;
use Yuc\Core\ConfigStore;
use Yuc\Core\Database;
use Yuc\Core\Router;
use Yuc\Core\View;

require dirname(__DIR__) . '/app/bootstrap.php';

header_remove('X-Powered-By');
header('Content-Type: text/html; charset=UTF-8');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('Referrer-Policy: strict-origin-when-cross-origin');
header('Cross-Origin-Opener-Policy: same-origin');
$forwardedProto = strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? ''));
if ((!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off') || $forwardedProto === 'https') {
    header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
}

try {
    $router = new Router();
    $installer = new InstallerController();
    $config = ConfigStore::load();

    if (!ConfigStore::isInstalled($config)) {
        $router->get('/', static function (): void {
            yuc_redirect('/install');
        });
        $router->get('/install', [$installer, 'show']);
        $router->post('/install/license', [$installer, 'validateLicense']);
        $router->post('/install/database', [$installer, 'installDatabase']);
        $router->post('/install/admin', [$installer, 'createAdmin']);
        $router->get('/install/complete', static function (): void {
            yuc_redirect('/install');
        });
    } else {
        $router->get('/', static function () use ($config): void {
            $siteFile = YUC_ROOT . '/youth-unity-cup-site.html';
            if (is_file($siteFile) && is_readable($siteFile)) {
                $contents = file_get_contents($siteFile);
                if ($contents !== false) {
                    $siteTitle = (string) ($config['app']['site_title'] ?? 'Youth Unity Cup');
                    $contents = str_replace('<title>Youth Unity Cup</title>', '<title>' . yuc_e($siteTitle) . '</title>', $contents);
                    echo $contents;
                    return;
                }
            }
            $siteTitle = trim((string) ($config['app']['site_title'] ?? 'Youth Unity Cup'));
            View::render('public-home', [
                'title' => ($siteTitle !== '' ? $siteTitle : 'Youth Unity Cup') . ' · Official site',
                'topNote' => 'OFFICIAL TOURNAMENT INFORMATION',
                'bodyClass' => 'public-data-page',
                'description' => 'The official Youth Unity Cup home for tournament news, teams, fixtures, results, venues, registration, and merchandise.',
                'siteTitle' => $siteTitle,
            ]);
        });
        $router->get('/install', static function (): void {
            yuc_redirect('/admin/login');
        });
        $router->get('/install/complete', [$installer, 'complete']);

        $pdo = null;
        $getPdo = static function () use (&$pdo, $config): PDO {
            if (!$pdo instanceof PDO) {
                if (!is_array($config) || !is_array($config['database'] ?? null)) {
                    throw new RuntimeException('The saved application configuration is incomplete.');
                }
                $pdo = Database::connect($config['database']);
            }
            return $pdo;
        };
        $adminController = null;
        $getAdminController = static function () use (&$adminController, $config, $getPdo): AdminController {
            if (!$adminController instanceof AdminController) {
                $adminController = new AdminController($getPdo(), $config);
            }
            return $adminController;
        };
        $operationsController = null;
        $getOperationsController = static function () use (&$operationsController, $config, $getPdo): AdminOperationsController {
            if (!$operationsController instanceof AdminOperationsController) {
                $operationsController = new AdminOperationsController($getPdo(), $config);
            }
            return $operationsController;
        };
        $publicController = null;
        $getPublicController = static function () use (&$publicController, $config, $getPdo): PublicSiteController {
            if (!$publicController instanceof PublicSiteController) {
                $publicController = new PublicSiteController($getPdo(), $config);
            }
            return $publicController;
        };
        $shopController = null;
        $getShopController = static function () use (&$shopController, $config, $getPdo): ShopController {
            if (!$shopController instanceof ShopController) {
                $shopController = new ShopController($getPdo(), $config);
            }
            return $shopController;
        };

        foreach (['teams', 'venues', 'fixtures', 'registrations', 'transactions', 'products', 'orders', 'settings', 'security', 'activity'] as $resource) {
            $router->get('/admin/' . $resource, static function () use ($getOperationsController, $resource): void {
                $getOperationsController()->manage($resource);
            });
        }
        $router->post('/admin/orders/create', static function () use ($getOperationsController): void {
            $getOperationsController()->createOrder();
        });
        foreach (['teams', 'venues', 'fixtures', 'registrations', 'transactions', 'products', 'orders', 'security'] as $resource) {
            $router->post('/admin/' . $resource . '/save', static function () use ($getOperationsController, $resource): void {
                $getOperationsController()->save($resource);
            });
        }
        foreach (['teams', 'venues', 'fixtures', 'registrations', 'products'] as $resource) {
            $router->post('/admin/' . $resource . '/delete', static function () use ($getOperationsController, $resource): void {
                $getOperationsController()->delete($resource);
            });
        }
        $router->post('/admin/security/delete', static function () use ($getOperationsController): void {
            $getOperationsController()->delete('security');
        });
        foreach (['transactions', 'orders'] as $resource) {
            $router->post('/admin/' . $resource . '/archive', static function () use ($getOperationsController, $resource): void {
                $getOperationsController()->archive($resource);
            });
        }
        foreach (['teams', 'venues', 'registrations', 'transactions', 'orders'] as $resource) {
            $router->post('/admin/' . $resource . '/status', static function () use ($getOperationsController, $resource): void {
                $getOperationsController()->updateStatus($resource);
            });
        }
        $router->post('/admin/settings/save', static function () use ($getOperationsController): void {
            $getOperationsController()->saveSettings();
        });
        $router->post('/admin/security/unblock', static function () use ($getOperationsController): void {
            $getOperationsController()->unblockIp();
        });

        $router->get('/teams', static function () use ($getPublicController): void {
            $getPublicController()->teams();
        });
        $router->get('/fixtures', static function () use ($getPublicController): void {
            $getPublicController()->fixtures();
        });
        $router->get('/results', static function () use ($getPublicController): void {
            $getPublicController()->results();
        });
        $router->get('/venues', static function () use ($getPublicController): void {
            $getPublicController()->venues();
        });
        $router->get('/registration', static function () use ($getPublicController): void {
            $getPublicController()->registration();
        });
        $router->post('/registration', static function () use ($getPublicController): void {
            $getPublicController()->submitRegistration();
        });
        $router->get('/shop', static function () use ($getShopController): void {
            $getShopController()->index();
        });
        $router->post('/shop/checkout', static function () use ($getShopController): void {
            $getShopController()->checkout();
        });
        $router->get('/shop/return', static function () use ($getShopController): void {
            $getShopController()->paymentReturn();
        });
        $router->post('/payments/payhub/webhook', static function () use ($getShopController): void {
            $getShopController()->webhook();
        });

        $router->get('/admin/login', static function () use ($getAdminController): void {
            $getAdminController()->showLogin();
        });
        $router->post('/admin/login', static function () use ($getAdminController): void {
            $getAdminController()->login();
        });
        $router->get('/admin/forgot-password', static function () use ($getAdminController): void {
            $getAdminController()->showPasswordResetRequest();
        });
        $router->post('/admin/forgot-password', static function () use ($getAdminController): void {
            $getAdminController()->requestPasswordReset();
        });
        $router->get('/admin/reset-password', static function () use ($getAdminController): void {
            $getAdminController()->showPasswordReset();
        });
        $router->post('/admin/reset-password', static function () use ($getAdminController): void {
            $getAdminController()->resetPassword();
        });
        $router->get('/admin', static function () use ($getAdminController): void {
            $getAdminController()->dashboard();
        });
        $router->post('/admin/email-test', static function () use ($getAdminController): void {
            $getAdminController()->sendTestEmail();
        });
        $router->post('/admin/logout', static function () use ($getAdminController): void {
            $getAdminController()->logout();
        });
    }

    $router->dispatch(
        strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET')),
        yuc_current_path()
    );
} catch (Throwable $exception) {
    error_log('Youth Unity Cup request failed (' . get_class($exception) . ').');
    if (!headers_sent()) {
        http_response_code(500);
    }

    try {
        View::render('error', ['title' => 'System response · Youth Unity Cup']);
    } catch (Throwable) {
        echo 'Youth Unity Cup could not complete the request. Check the server error log.';
    }
}
