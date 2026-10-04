<?php

declare(strict_types=1);

use Yuc\Core\View;

require dirname(__DIR__) . '/app/bootstrap.php';

function renderAdminManagement(string $resource, array $rows, array $formValues = [], bool $showArchived = false, array $orderProducts = []): string
{
    ob_start();
    View::render('admin-manage', [
        'title' => ucfirst($resource) . ' · Youth Unity Cup Admin',
        'topNote' => 'ADMIN CONTROL ROOM',
        'bodyClass' => 'admin-page',
        'admin' => ['id' => 1, 'email' => 'admin@example.test', 'username' => 'admin'],
        'resource' => $resource,
        'rows' => $rows,
        'formValues' => $formValues,
        'orderProducts' => $orderProducts,
        'teams' => [],
        'venues' => [],
        'settings' => [],
        'mail' => [],
        'payHubConfigured' => true,
        'appTimezone' => 'UTC',
        'showArchived' => $showArchived,
        'flash' => null,
        'auditTotal' => 0,
        'auditPage' => 1,
        'auditPages' => 1,
        'auditSearch' => '',
        'auditCategory' => '',
    ]);
    return (string) ob_get_clean();
}

function expectAdminMarkup(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$cases = [
    'teams' => [[
        'id' => 1, 'name' => 'Unity FC', 'contact_email' => 'team@example.test', 'zone' => 'Zone 1',
        'group_name' => 'A', 'status' => 'active',
    ]],
    'venues' => [[
        'id' => 1, 'name' => 'Community Ground', 'address' => 'Lagos', 'zone' => 'Zone 1',
        'capacity' => 700, 'status' => 'active',
    ]],
    'fixtures' => [[
        'id' => 1, 'home_team' => 'Unity FC', 'away_team' => 'Unity Stars', 'kickoff_at' => '2026-10-04 12:00:00',
        'venue_name' => 'Community Ground', 'stage' => 'Group A', 'home_score' => 1, 'away_score' => 0, 'status' => 'completed',
    ]],
    'registrations' => [[
        'id' => 1, 'reference' => 'YUC-261004-ABC123', 'full_name' => 'Amina Sample', 'email' => 'amina@example.test',
        'phone' => '+2348000000000', 'category' => 'player', 'age' => 17, 'zone' => 'Zone 1', 'team_name' => 'Unity FC',
        'status' => 'new', 'submitted_at' => '2026-10-04 12:00:00',
    ]],
    'transactions' => [[
        'id' => 1, 'reference' => 'YUCT-261004-ABC123', 'recipient_email' => 'member@example.test', 'amount' => '25.00',
        'currency' => 'NGN', 'status' => 'pending', 'payment_provider' => '', 'provider_reference' => null,
        'description' => 'Manual record', 'created_at' => '2026-10-04 12:00:00', 'archived_at' => null,
        'shop_order_reference' => null,
    ]],
    'products' => [[
        'id' => 1, 'sku' => 'YUC-TEE-01', 'name' => 'Unity Cup Shirt', 'price_kobo' => 250000,
        'stock_quantity' => 10, 'status' => 'active', 'order_item_count' => 0,
    ]],
    'orders' => [[
        'id' => 1, 'reference' => 'YUC-S-261004-ABCDEF1234', 'customer_name' => 'Sample Customer',
        'customer_email' => 'shop@example.test', 'customer_phone' => '', 'fulfillment_notes' => '',
        'total_kobo' => 250000, 'status' => 'fulfilled', 'reservation_expires_at' => '2026-10-04 12:00:00',
        'provider_reference' => 'PH_test_123', 'checkout_url' => 'https://merchant.payhub.com.ng/checkout.php?ref=PH_test_123',
        'provider_amount_kobo' => '250000', 'provider_currency' => 'NGN',
        'payment_review_reason' => null, 'item_summary' => '1 × Unity Cup Shirt', 'created_at' => '2026-10-04 11:00:00',
        'archived_at' => null,
    ]],
    'security' => [[
        'ip_address' => '203.0.113.10', 'reason' => 'Manual administrator block', 'created_at' => '2026-10-04 11:00:00',
        'blocked_until' => '2026-10-04 11:15:00', 'duration_minutes' => 15,
    ]],
];

foreach ($cases as $resource => $rows) {
    $html = renderAdminManagement($resource, $rows);
    expectAdminMarkup(str_contains($html, 'data-live-search-input'), $resource . ' is missing its live-search input.');
    expectAdminMarkup(str_contains($html, 'Results update as you type'), $resource . ' is missing the no-reload search hint.');
}

$registrations = renderAdminManagement('registrations', $cases['registrations']);
expectAdminMarkup(str_contains($registrations, 'action="/admin/registrations/save"'), 'Registration create/edit form is missing.');
expectAdminMarkup(str_contains($registrations, 'action="/admin/registrations/delete"'), 'Registration delete action is missing.');

$manualTransaction = renderAdminManagement('transactions', $cases['transactions']);
expectAdminMarkup(str_contains($manualTransaction, 'action="/admin/transactions/archive"'), 'Manual transaction archive control is missing.');
expectAdminMarkup(str_contains($manualTransaction, 'data-confirm='), 'Destructive actions are missing a confirmation prompt.');

$archivedTransaction = $cases['transactions'][0];
$archivedTransaction['archived_at'] = '2026-10-04 13:00:00';
$archivedLedger = renderAdminManagement('transactions', [$archivedTransaction], [], true);
expectAdminMarkup(str_contains($archivedLedger, 'Restore'), 'Archived manual transaction does not expose restore.');
expectAdminMarkup(!str_contains($archivedLedger, 'Save</button>'), 'Archived manual transaction still exposes status editing.');

$payhubTransaction = $cases['transactions'][0];
$payhubTransaction['payment_provider'] = 'payhub';
$payhubTransaction['shop_order_reference'] = 'YUC-S-261004-ABCDEF1234';
$payhubLedger = renderAdminManagement('transactions', [$payhubTransaction]);
expectAdminMarkup(str_contains($payhubLedger, 'Payment record'), 'PayHub transaction is not presented as a locked record.');
expectAdminMarkup(!str_contains($payhubLedger, '/admin/transactions?edit=1'), 'PayHub transaction exposes a manual edit link.');

$products = renderAdminManagement('products', $cases['products']);
expectAdminMarkup(str_contains($products, 'action="/admin/products/delete"'), 'Product delete/archive control is missing.');

$pendingCheckoutOrder = $cases['orders'][0];
$pendingCheckoutOrder['id'] = 2;
$pendingCheckoutOrder['reference'] = 'YUC-S-261004-0123456789';
$pendingCheckoutOrder['status'] = 'pending_payment';
$orders = renderAdminManagement('orders', [$cases['orders'][0], $pendingCheckoutOrder], [], false, [[
    'id' => 2, 'sku' => 'YUC-CAP-01', 'name' => 'Unity Cup Cap', 'description' => 'Official cap',
    'price_kobo' => 75000, 'stock_quantity' => 8,
]]);
expectAdminMarkup(str_contains($orders, 'action="/admin/orders/create"'), 'Safe admin order creation form is missing.');
expectAdminMarkup(str_contains($orders, 'name="quantity[2]"'), 'Admin order form does not expose stock-checked product quantities.');
expectAdminMarkup(str_contains($orders, 'action="/admin/orders/archive"'), 'Closed order archive control is missing.');
expectAdminMarkup(str_contains($orders, 'atomic stock reservation'), 'Shop order payment/stock safeguard is not explained.');
expectAdminMarkup(str_contains($orders, 'Open PayHub checkout'), 'PayHub checkout link is not available for the customer order.');

$blockedIp = renderAdminManagement('security', $cases['security'], [
    'ip_address' => '203.0.113.10', 'reason' => 'Manual administrator block', 'duration_minutes' => 15,
]);
expectAdminMarkup(str_contains($blockedIp, 'action="/admin/security/save"'), 'Blocked-IP create/edit form is missing.');
expectAdminMarkup(str_contains($blockedIp, 'action="/admin/security/delete"'), 'Blocked-IP unblock action is missing.');

fwrite(STDOUT, "Admin management view smoke checks passed.\n");
