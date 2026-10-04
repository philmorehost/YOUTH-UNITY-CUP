<?php

declare(strict_types=1);

use Yuc\Core\View;

require dirname(__DIR__) . '/app/bootstrap.php';

function renderTemplate(string $template, array $data): string
{
    ob_start();
    View::render($template, $data);
    return (string) ob_get_clean();
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
if (!str_contains($adminProducts, 'Add a product') || !str_contains($adminProducts, 'Unity Cup Shirt')) {
    throw new RuntimeException('Admin product template did not render the catalog manager.');
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
]);
if (!str_contains($admin, 'Shop orders') || !str_contains($admin, 'Confirm review')) {
    throw new RuntimeException('Admin shop order template did not render the review action.');
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
if (!str_contains($settings, 'PayHub shop payments') || !str_contains($settings, 'payhub_secret_key')) {
    throw new RuntimeException('Admin settings template did not render PayHub credential controls.');
}

$home = renderTemplate('public-home', [
    'title' => 'Youth Unity Cup · Official site',
    'topNote' => 'OFFICIAL TOURNAMENT INFORMATION',
    'bodyClass' => 'public-data-page',
    'description' => 'The official Youth Unity Cup home.',
    'siteTitle' => 'Youth <Unity> Cup',
]);
if (!str_contains($home, 'One community.') || !str_contains($home, 'Youth &lt;Unity&gt; Cup')
    || !str_contains($home, 'name="description" content="The official Youth Unity Cup home."')
    || !str_contains($home, 'href="/fixtures"') || !str_contains($home, 'href="/registration"')
    || str_contains($home, 'THAT ROUTE ISN’T ON THE FIXTURE LIST')) {
    throw new RuntimeException('Root landing fallback template did not render the home page.');
}

fwrite(STDOUT, "Shop, admin, and home template smoke checks passed.\n");
