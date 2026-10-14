<?php

declare(strict_types=1);

use Yuc\Services\ProductImageService;
use Yuc\Services\ShopService;

require dirname(__DIR__) . '/app/bootstrap.php';

function expectProductImages(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$products = ShopService::demoProducts();
expectProductImages(count($products) === 8, 'Demo shop data should contain eight sports products.');
$urls = [];
foreach ($products as $product) {
    $url = (string) ($product['image_url'] ?? '');
    expectProductImages(str_starts_with($url, '/assets/demo-products/'), 'Each demo product needs a local illustration.');
    expectProductImages(!in_array($url, $urls, true), 'Demo product image URLs should be unique.');
    $urls[] = $url;
    $assetPath = dirname(__DIR__) . '/public' . $url;
    $asset = file_get_contents($assetPath);
    expectProductImages(is_string($asset) && str_contains($asset, '<svg'), 'A demo product illustration is missing or invalid.');
    expectProductImages(str_starts_with((string) $product['sku'], 'DEMO-') && (int) $product['id'] < 0, 'Demo catalog records must remain clearly separate from database products.');
}

$imageService = new ProductImageService(dirname(__DIR__) . '/storage/test-product-image-fixtures');
expectProductImages($imageService->storeUpload(null) === null, 'No upload should leave the product image unchanged.');
expectProductImages($imageService->storeUpload(['error' => UPLOAD_ERR_NO_FILE]) === null, 'An empty file input should not replace a product image.');
expectProductImages($imageService->findStoredImage('../../README.md') === null, 'Product image lookup accepted a path traversal filename.');
expectProductImages($imageService->findStoredImage('not-an-image.webp') === null, 'Product image lookup accepted a non-random filename.');
expectProductImages($imageService->publicUrl('../../README.md') === '', 'An invalid product image filename produced a public URL.');

$schema = file_get_contents(dirname(__DIR__) . '/database/schema.mysql.sql');
$installer = file_get_contents(dirname(__DIR__) . '/app/Services/SchemaInstaller.php');
expectProductImages(is_string($schema) && preg_match('/image_file VARCHAR\(64\) NOT NULL DEFAULT/i', $schema) === 1, 'Canonical schema is missing the shop product image field.');
expectProductImages(is_string($installer) && str_contains($installer, "SHOW COLUMNS FROM shop_products LIKE 'image_file'"), 'The explicit schema updater does not add the product image field to existing databases.');

$adminTemplate = file_get_contents(dirname(__DIR__) . '/views/admin-manage.php');
$adminController = file_get_contents(dirname(__DIR__) . '/app/Controllers/AdminOperationsController.php');
$frontController = file_get_contents(dirname(__DIR__) . '/public/index.php');
expectProductImages(is_string($adminTemplate) && str_contains($adminTemplate, 'enctype="multipart/form-data"') && str_contains($adminTemplate, 'name="product_image"'), 'Production product manager is missing its image upload field.');
expectProductImages(is_string($adminController) && str_contains($adminController, '$isDemoMode ? ShopService::demoProducts() : $this->shop->adminProducts()'), 'Admin product manager does not load the preview catalog in Demo mode.');
$saveCall = <<<'PHP'
saveProduct($values, (int) $admin['id'], $_FILES)
PHP;
expectProductImages(is_string($adminController) && str_contains($adminController, $saveCall), 'Admin product uploads are not passed to the shop service.');
$routeSnippet = <<<'PHP'
$router->get('/shop/product-image'
PHP;
expectProductImages(is_string($frontController) && str_contains($frontController, $routeSnippet), 'The product image endpoint is not registered.');
expectProductImages(is_string($adminController) && str_contains($adminController, 'shopOrdersDatabaseWarning') && str_contains($adminController, "['42S02', '42S22']"), 'Shop-order database failures do not expose a logged SQLSTATE and schema-upgrade hint.');

fwrite(STDOUT, "Product image assets, demo catalog and upload wiring checks passed." . PHP_EOL);
