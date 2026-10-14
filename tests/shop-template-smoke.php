<?php

declare(strict_types=1);

use Yuc\Core\View;
use Yuc\Services\ShopService;

require dirname(__DIR__) . '/app/bootstrap.php';

function renderTemplate(string $template, array $data): string
{
    ob_start();
    View::render($template, $data);
    return (string) ob_get_clean();
}

$previousErrorHandler = set_error_handler(static function (int $severity, string $message): never {
    throw new ErrorException($message, 0, $severity);
});
try {
    $layoutWithoutDescription = static function (): string {
        $title = 'Layout description fallback';
        $bodyClass = 'admin-page';
        $topNote = 'TEST';
        $content = '';
        ob_start();
        require dirname(__DIR__) . '/views/layout.php';
        return (string) ob_get_clean();
    };
    $layoutMarkup = $layoutWithoutDescription();
} finally {
    restore_error_handler();
}
if (!str_contains($layoutMarkup, 'Youth Unity Cup tournament information and administration portal.')) {
    throw new RuntimeException('The shared layout does not safely default an omitted meta description.');
}

$shop = renderTemplate('shop', [
    'title' => 'Official shop · Youth Unity Cup',
    'topNote' => 'YOUTH UNITY CUP OFFICIAL SHOP',
    'bodyClass' => 'public-data-page shop-page',
    'products' => [[
        'id' => 1,
        'sku' => 'YUC-TEE-01',
        'name' => 'Unity Cup Shirt',
        'description' => 'Official tournament shirt.',
        'price_kobo' => 250000,
        'stock_quantity' => 10,
    ]],
    'payHubConfigured' => true,
    'contactEmail' => 'shop@example.test',
    'flash' => null,
    'lastOrderAvailable' => false,
    'oldForm' => [],
]);
if (!str_contains($shop, 'Unity Cup Shirt') || !str_contains($shop, 'Continue to PayHub')) {
    throw new RuntimeException('Public shop template did not render the catalog and checkout.');
}

$demoProducts = ShopService::demoProducts();
$demoSkus = array_column($demoProducts, 'sku');
$demoIds = array_column($demoProducts, 'id');
if (count($demoProducts) !== 8
    || count(array_unique($demoSkus)) !== count($demoProducts)
    || array_filter($demoSkus, static fn (string $sku): bool => !str_starts_with($sku, 'DEMO-')) !== []
    || array_filter($demoIds, static fn (int $id): bool => $id >= 0) !== []) {
    throw new RuntimeException('Demo shop products must be unique, preview-labelled and impossible to confuse with stored product IDs.');
}
foreach ($demoProducts as $product) {
    if ($product['price_kobo'] < 1 || $product['stock_quantity'] < 1 || $product['name'] === '') {
        throw new RuntimeException('Every demo shop product needs a name, sample price and sample stock.');
    }
}
$shopController = file_get_contents(dirname(__DIR__) . '/app/Controllers/ShopController.php');
if (!is_string($shopController) || !str_contains($shopController, 'ShopService::demoProducts()')) {
    throw new RuntimeException('The shop controller does not use the preview catalog in Demo mode.');
}
$demoShop = renderTemplate('shop', [
    'title' => 'Official shop · Youth Unity Cup',
    'topNote' => 'YOUTH UNITY CUP OFFICIAL SHOP',
    'bodyClass' => 'public-data-page shop-page',
    'siteMode' => 'demo',
    'products' => $demoProducts,
    'payHubConfigured' => true,
    'contactEmail' => 'shop@example.test',
    'flash' => null,
    'lastOrderAvailable' => false,
    'oldForm' => [],
]);
if (substr_count($demoShop, '<article class="shop-product-card">') !== 8
    || substr_count($demoShop, 'class="shop-product-image"') !== 8
    || !str_contains($demoShop, 'src="/assets/demo-products/jersey.svg"')
    || !str_contains($demoShop, 'Navy and Lime Match Jersey')
    || !str_contains($demoShop, 'Size 5 Training Football')
    || !str_contains($demoShop, 'Your live catalogue and stock remain untouched')
    || !str_contains($demoShop, 'DEMO CHECKOUT PAUSED')
    || str_contains($demoShop, 'Continue to PayHub')
    || str_contains($demoShop, 'name="customer_name"')
    || !str_contains($demoShop, 'No personal details, orders, or payments are collected in Demo mode.')
    || !str_contains($demoShop, '<button class="button button-primary" type="submit" disabled>Checkout paused')
    || !str_contains($demoShop, 'name="quantity[-1001]"')
    || !str_contains($demoShop, 'Demo stock: 24')) {
    throw new RuntimeException('Demo shop did not render the preview-only sports catalog or keep live checkout paused.');
}

$inlineCheckout = renderTemplate('shop-inline-checkout', [
    'title' => 'Secure checkout · Youth Unity Cup',
    'topNote' => 'PAYHUB SECURE INLINE CHECKOUT',
    'bodyClass' => 'public-data-page shop-page',
    'order' => [
        'id' => 7,
        'reference' => 'YUC-S-261004-ABCDEF1234',
        'customer_name' => 'Sample Customer',
        'customer_email' => 'shop@example.test',
        'total_kobo' => 250000,
        'status' => 'pending_payment',
        'reservation_expires_at' => '2026-10-04 12:00:00',
        'provider_reference' => 'YUC-AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA',
    ],
    'payHubPublicKey' => 'pk_test_public_key_123456',
    'returnUrl' => '/shop/return?order=YUC-S-261004-ABCDEF1234',
    'contactEmail' => 'shop@example.test',
    'inlinePayHubScript' => true,
]);
if (!str_contains($inlineCheckout, 'merchant.payhub.com.ng/inline.js')
    || !str_contains($inlineCheckout, 'data-amount-kobo="250000"')
    || !str_contains($inlineCheckout, 'data-public-key="pk_test_public_key_123456"')
    || !str_contains($inlineCheckout, 'YUC-AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA')
    || str_contains($inlineCheckout, 'sk_live')) {
    throw new RuntimeException('PayHub inline checkout did not safely render its public configuration.');
}
$inlineJs = file_get_contents(dirname(__DIR__) . '/public/assets/app.js');
if (!is_string($inlineJs) || !str_contains($inlineJs, 'window.location.assign(inlinePayButton.dataset.returnUrl)')
    || !str_contains($inlineJs, 'PayHub is ready. Your payment will be confirmed by the server.')) {
    throw new RuntimeException('Inline checkout does not return to server-side transaction verification.');
}

$order = renderTemplate('shop-order', [
    'title' => 'Payment status · Youth Unity Cup',
    'topNote' => 'SHOP ORDER STATUS',
    'bodyClass' => 'public-data-page shop-page',
    'order' => [
        'reference' => 'YUC-S-261004-ABCDEF1234',
        'status' => 'paid',
        'total_kobo' => 250000,
        'items' => [[
            'sku_snapshot' => 'YUC-TEE-01',
            'product_name' => 'Unity Cup Shirt',
            'unit_price_kobo' => 250000,
            'quantity' => 1,
            'line_total_kobo' => 250000,
        ]],
    ],
    'verificationNotice' => '',
    'contactEmail' => '',
]);
if (!str_contains($order, 'Payment confirmed') || !str_contains($order, 'YUC-S-261004-ABCDEF1234')) {
    throw new RuntimeException('Shop order status template did not render the verified order.');
}

$demoOrder = renderTemplate('shop-order', [
    'title' => 'Payment status · Youth Unity Cup',
    'topNote' => 'SHOP ORDER STATUS',
    'bodyClass' => 'public-data-page shop-page',
    'siteMode' => 'demo',
    'order' => [
        'reference' => 'YUC-S-261004-ABCDEF1234',
        'status' => 'pending_payment',
        'total_kobo' => 250000,
        'checkout_url' => 'https://merchant.payhub.com.ng/checkout/test',
        'items' => [],
    ],
    'verificationNotice' => '',
    'contactEmail' => '',
]);
if (!str_contains($demoOrder, 'Live payment actions are paused in Demo mode')
    || str_contains($demoOrder, 'Continue to PayHub checkout')
    || str_contains($demoOrder, 'Check payment status')) {
    throw new RuntimeException('Demo mode exposed a live PayHub checkout or verification action.');
}

$adminProducts = renderTemplate('admin-manage', [
    'title' => 'Shop products · Youth Unity Cup Admin',
    'topNote' => 'ADMIN CONTROL ROOM',
    'bodyClass' => 'admin-page',
    'admin' => ['id' => 1, 'email' => 'admin@example.test', 'username' => 'admin'],
    'resource' => 'products',
    'rows' => [[
        'id' => 1,
        'sku' => 'YUC-TEE-01',
        'name' => 'Unity Cup Shirt',
        'price_kobo' => 250000,
        'stock_quantity' => 10,
        'status' => 'active',
    ]],
    'formValues' => [],
    'teams' => [],
    'venues' => [],
    'settings' => [],
    'mail' => [],
    'payHubConfigured' => true,
    'appTimezone' => 'UTC',
    'flash' => null,
    'auditTotal' => 0,
    'auditPage' => 1,
    'auditPages' => 1,
    'auditSearch' => '',
    'auditCategory' => '',
]);
if (!str_contains($adminProducts, 'Add a product')
    || !str_contains($adminProducts, 'Unity Cup Shirt')
    || !str_contains($adminProducts, 'enctype="multipart/form-data"')
    || !str_contains($adminProducts, 'name="product_image"')) {
    throw new RuntimeException('Admin product template did not render the catalog manager and image upload field.');
}

$demoAdminProducts = renderTemplate('admin-manage', [
    'title' => 'Shop products · Youth Unity Cup Admin',
    'topNote' => 'ADMIN CONTROL ROOM',
    'bodyClass' => 'admin-page',
    'siteMode' => 'demo',
    'admin' => ['id' => 1, 'email' => 'admin@example.test', 'username' => 'admin'],
    'resource' => 'products',
    'rows' => $demoProducts,
    'formValues' => [],
    'teams' => [],
    'venues' => [],
    'settings' => [],
    'mail' => [],
    'payHubConfigured' => true,
    'appTimezone' => 'UTC',
    'flash' => null,
    'auditTotal' => 0,
    'auditPage' => 1,
    'auditPages' => 1,
    'auditSearch' => '',
    'auditCategory' => '',
]);
if (substr_count($demoAdminProducts, '<img class="admin-product-image"') !== 8
    || !str_contains($demoAdminProducts, 'DEMO PREVIEW CATALOG')
    || !str_contains($demoAdminProducts, 'Read-only in Demo')
    || str_contains($demoAdminProducts, 'action="/admin/products/save"')
    || str_contains($demoAdminProducts, 'action="/admin/products/delete"')) {
    throw new RuntimeException('Demo admin product manager must display all sample products and images without write actions.');
}

$admin = renderTemplate('admin-manage', [
    'title' => 'Shop orders · Youth Unity Cup Admin',
    'topNote' => 'ADMIN CONTROL ROOM',
    'bodyClass' => 'admin-page',
    'admin' => ['id' => 1, 'email' => 'admin@example.test', 'username' => 'admin'],
    'resource' => 'orders',
    'rows' => [[
        'id' => 1,
        'reference' => 'YUC-S-261004-ABCDEF1234',
        'customer_name' => 'Sample Customer',
        'customer_email' => 'shop@example.test',
        'customer_phone' => '',
        'fulfillment_notes' => '',
        'total_kobo' => 250000,
        'status' => 'paid_needs_review',
        'reservation_expires_at' => '2026-10-04 12:00:00',
        'provider_reference' => 'PH_test_123',
        'provider_amount_kobo' => '250000',
        'provider_currency' => 'NGN',
        'payment_review_reason' => 'order_closed_before_payment',
        'item_summary' => '1 × Unity Cup Shirt',
        'created_at' => '2026-10-04 11:00:00',
    ]],
    'formValues' => [],
    'teams' => [],
    'venues' => [],
    'settings' => [],
    'mail' => [],
    'payHubConfigured' => true,
    'appTimezone' => 'UTC',
    'flash' => null,
    'auditTotal' => 0,
    'auditPage' => 1,
    'auditPages' => 1,
    'auditSearch' => '',
    'auditCategory' => '',
    'shopSchemaWarning' => 'The shop database schema needs a one-time additive update.',
]);
if (!str_contains($admin, 'Shop orders')
    || !str_contains($admin, 'Confirm review')
    || !str_contains($admin, 'The shop database schema needs a one-time additive update.')) {
    throw new RuntimeException('Admin shop order template did not render the review action.');
}

$adminPendingOrder = renderTemplate('admin-manage', [
    'title' => 'Shop orders · Youth Unity Cup Admin',
    'topNote' => 'ADMIN CONTROL ROOM',
    'bodyClass' => 'admin-page',
    'admin' => ['id' => 1, 'email' => 'admin@example.test', 'username' => 'admin'],
    'resource' => 'orders',
    'rows' => [[
        'id' => 12,
        'reference' => 'YUC-S-261004-ABCDEF1234',
        'customer_name' => 'Sample Customer',
        'customer_email' => 'shop@example.test',
        'customer_phone' => '',
        'fulfillment_notes' => '',
        'total_kobo' => 250000,
        'status' => 'pending_payment',
        'reservation_expires_at' => '2026-10-04 12:00:00',
        'provider_reference' => 'YUC-AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA',
        'provider_amount_kobo' => null,
        'provider_currency' => null,
        'payment_review_reason' => null,
        'item_summary' => '1 × Unity Cup Shirt',
        'created_at' => '2026-10-04 11:00:00',
        'checkout_url' => null,
        'archived_at' => null,
    ]],
    'formValues' => [],
    'orderProducts' => [],
    'teams' => [],
    'venues' => [],
    'settings' => [],
    'mail' => [],
    'payHubConfigured' => true,
    'appTimezone' => 'UTC',
    'flash' => null,
    'auditTotal' => 0,
    'auditPage' => 1,
    'auditPages' => 1,
    'auditSearch' => '',
    'auditCategory' => '',
]);
if (!str_contains($adminPendingOrder, 'Open inline checkout') || !str_contains($adminPendingOrder, '/admin/orders?pay=12')) {
    throw new RuntimeException('Pending orders do not expose the authenticated PayHub inline-checkout action.');
}

$demoAdminOrders = renderTemplate('admin-manage', [
    'title' => 'Shop orders · Youth Unity Cup Admin',
    'topNote' => 'ADMIN CONTROL ROOM',
    'bodyClass' => 'admin-page',
    'admin' => ['id' => 1, 'email' => 'admin@example.test', 'username' => 'admin'],
    'resource' => 'orders',
    'siteMode' => 'demo',
    'rows' => [[
        'id' => 1,
        'reference' => 'YUC-S-261004-ABCDEF1234',
        'customer_name' => 'Sample Customer',
        'customer_email' => 'shop@example.test',
        'customer_phone' => '',
        'fulfillment_notes' => '',
        'total_kobo' => 250000,
        'status' => 'pending_payment',
        'reservation_expires_at' => '2026-10-04 12:00:00',
        'provider_reference' => 'PH_test_123',
        'provider_amount_kobo' => null,
        'provider_currency' => null,
        'payment_review_reason' => null,
        'item_summary' => '1 × Unity Cup Shirt',
        'created_at' => '2026-10-04 11:00:00',
        'checkout_url' => 'https://merchant.payhub.com.ng/checkout/test',
    ]],
    'formValues' => [],
    'orderProducts' => [],
    'teams' => [],
    'venues' => [],
    'settings' => [],
    'mail' => [],
    'payHubConfigured' => true,
    'appTimezone' => 'UTC',
    'flash' => null,
    'auditTotal' => 0,
    'auditPage' => 1,
    'auditPages' => 1,
    'auditSearch' => '',
    'auditCategory' => '',
]);
if (!str_contains($demoAdminOrders, 'Shop operations are read-only in Demo mode')
    || str_contains($demoAdminOrders, 'Open PayHub checkout')
    || str_contains($demoAdminOrders, 'Edit details')
    || str_contains($demoAdminOrders, 'action="/admin/orders/status"')) {
    throw new RuntimeException('Demo mode exposed a mutable or live-payment admin order action.');
}

$settings = renderTemplate('admin-manage', [
    'title' => 'Settings · Youth Unity Cup Admin',
    'topNote' => 'ADMIN CONTROL ROOM',
    'bodyClass' => 'admin-page',
    'admin' => ['id' => 1, 'email' => 'admin@example.test', 'username' => 'admin'],
    'resource' => 'settings',
    'rows' => [],
    'formValues' => [],
    'teams' => [],
    'venues' => [],
    'settings' => [
        'site_title' => 'Youth Unity Cup', 'timezone' => 'UTC', 'contact_email' => '',
        'max_login_attempts' => '5', 'login_window_minutes' => '15', 'block_duration_minutes' => '15',
    ],
    'mail' => [],
    'payHubConfigured' => true,
    'appTimezone' => 'UTC',
    'flash' => null,
    'auditTotal' => 0,
    'auditPage' => 1,
    'auditPages' => 1,
    'auditSearch' => '',
    'auditCategory' => '',
]);
if (!str_contains($settings, 'PayHub shop payments') || !str_contains($settings, 'payhub_secret_key')
    || !str_contains($settings, 'payhub_public_key')) {
    throw new RuntimeException('Admin settings template did not render both PayHub credential controls.');
}

$heroAdmin = renderTemplate('admin-manage', [
    'title' => 'Homepage hero · Youth Unity Cup Admin',
    'topNote' => 'ADMIN CONTROL ROOM',
    'bodyClass' => 'admin-page',
    'admin' => ['id' => 1, 'email' => 'admin@example.test', 'username' => 'admin'],
    'resource' => 'homepage-hero',
    'rows' => [],
    'formValues' => [],
    'teams' => [],
    'venues' => [],
    'settings' => [],
    'heroSettings' => ['type' => 'default', 'media_file' => '', 'media_url' => '', 'youtube_id' => '', 'youtube_url' => '', 'image_alt' => 'Youth Unity Cup community football'],
    'mail' => [],
    'payHubConfigured' => true,
    'appTimezone' => 'UTC',
    'flash' => null,
    'auditTotal' => 0,
    'auditPage' => 1,
    'auditPages' => 1,
    'auditSearch' => '',
    'auditCategory' => '',
]);
if (!str_contains($heroAdmin, 'action="/admin/homepage-hero/save"')
    || !str_contains($heroAdmin, 'enctype="multipart/form-data"')
    || !str_contains($heroAdmin, 'hero_youtube_url')
    || !str_contains($heroAdmin, 'hero_image_file')
    || !str_contains($heroAdmin, 'hero_video_file')
    || !str_contains($heroAdmin, 'fast-start')) {
    throw new RuntimeException('Admin homepage hero controls did not render the safe image, video, and YouTube options.');
}

$home = renderTemplate('public-home', [
    'title' => 'Youth Unity Cup · Official site',
    'topNote' => 'OFFICIAL TOURNAMENT INFORMATION',
    'bodyClass' => 'public-data-page',
    'description' => 'The official Youth Unity Cup home.',
    'siteTitle' => 'Youth <Unity> Cup',
]);
if (!str_contains($home, 'Football brings') || !str_contains($home, 'Youth &lt;Unity&gt; Cup')
    || !str_contains($home, 'name="description" content="The official Youth Unity Cup home."')
    || !str_contains($home, 'href="/fixtures"') || !str_contains($home, 'href="/registration"')
    || str_contains($home, 'THAT ROUTE ISN’T ON THE FIXTURE LIST')) {
    throw new RuntimeException('Root landing fallback template did not render the home page.');
}

$videoHome = renderTemplate('public-home', [
    'title' => 'Youth Unity Cup · Official site',
    'topNote' => 'OFFICIAL TOURNAMENT INFORMATION',
    'bodyClass' => 'public-data-page',
    'description' => 'The official Youth Unity Cup home.',
    'siteTitle' => 'Youth Unity Cup',
    'heroSettings' => ['type' => 'video', 'media_file' => str_repeat('a', 32) . '.mp4', 'media_url' => '/hero-media?file=' . str_repeat('a', 32) . '.mp4'],
]);
if (!str_contains($videoHome, '<video autoplay muted loop playsinline preload="auto"')
    || !str_contains($videoHome, 'type="video/mp4"')
    || !str_contains($videoHome, 'hero-media?file=')) {
    throw new RuntimeException('Uploaded homepage video does not autoplay and loop with protected media delivery.');
}

$youtubeHome = renderTemplate('public-home', [
    'title' => 'Youth Unity Cup · Official site',
    'topNote' => 'OFFICIAL TOURNAMENT INFORMATION',
    'bodyClass' => 'public-data-page',
    'description' => 'The official Youth Unity Cup home.',
    'siteTitle' => 'Youth Unity Cup',
    'heroSettings' => ['type' => 'youtube', 'youtube_id' => 'dQw4w9WgXcQ', 'embed_url' => 'https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ?autoplay=1&mute=1&loop=1&playlist=dQw4w9WgXcQ'],
]);
if (!str_contains($youtubeHome, 'www.youtube-nocookie.com/embed/dQw4w9WgXcQ')
    || !str_contains($youtubeHome, 'loop=1&amp;playlist=dQw4w9WgXcQ')) {
    throw new RuntimeException('YouTube hero does not use a privacy-enhanced, looping inline embed.');
}

fwrite(STDOUT, "Shop, admin, and home template smoke checks passed.\n");
