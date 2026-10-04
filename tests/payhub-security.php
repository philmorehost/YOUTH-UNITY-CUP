<?php

declare(strict_types=1);

use Yuc\Services\PayHubClient;
use Yuc\Services\ShopService;

require dirname(__DIR__) . '/app/bootstrap.php';

function expect(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

expect(ShopService::amountToKobo('5') === 500, 'Whole-naira conversion failed.');
expect(ShopService::amountToKobo('1250.4') === 125040, 'Decimal-naira conversion failed.');
expect(ShopService::formatKobo(125040) === '1250.40', 'Kobo formatting failed.');

$secret = 'sk_live_test_secret_123456';
$client = new PayHubClient(['payments' => ['secret_key' => $secret]]);
$body = '{"event":"charge.success","data":{"reference":"PH_test_123"}}';
$signature = hash_hmac('sha256', $body, $secret);
expect($client->hasValidWebhookSignature($body, $signature), 'Valid webhook signature was rejected.');
expect(!$client->hasValidWebhookSignature($body . ' ', $signature), 'Modified webhook body was accepted.');
expect(!$client->hasValidWebhookSignature($body, str_repeat('0', 64)), 'Invalid webhook signature was accepted.');

expect(PayHubClient::isTrustedCheckoutUrl('https://merchant.payhub.com.ng/checkout.php?ref=PH_test'), 'PayHub checkout URL was rejected.');
expect(!PayHubClient::isTrustedCheckoutUrl('http://merchant.payhub.com.ng/checkout.php?ref=PH_test'), 'Insecure checkout URL was accepted.');
expect(!PayHubClient::isTrustedCheckoutUrl('https://attacker.example/checkout.php?ref=PH_test'), 'Untrusted checkout host was accepted.');

$invalidAmountRejected = false;
try {
    ShopService::amountToKobo('1.999');
} catch (InvalidArgumentException) {
    $invalidAmountRejected = true;
}
expect($invalidAmountRejected, 'An amount with excess decimals was accepted.');

fwrite(STDOUT, "PayHub security and money-format checks passed.\n");
