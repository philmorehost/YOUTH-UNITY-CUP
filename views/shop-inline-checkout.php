<?php
/** @var array<string,mixed> $order */
/** @var string $payHubPublicKey */
/** @var string $returnUrl */
/** @var string $contactEmail */
$adminCheckout = str_contains((string) ($bodyClass ?? ''), 'admin-page');
$returnUrl = (string) ($returnUrl ?? ($adminCheckout ? '/admin/orders' : '/shop'));
?>
<section class="public-content-wrap shop-content-wrap inline-checkout-wrap">
    <div class="public-hero shop-hero shop-status-hero">
        <div class="eyebrow"><span class="eyebrow-line"></span>PAYHUB · SECURE INLINE CHECKOUT</div>
        <h1>Complete your <em>payment.</em></h1>
        <p>Your payment opens securely in PayHub without leaving this page. The order is confirmed only after our server verifies it.</p>
    </div>

    <nav class="public-section-nav" aria-label="Checkout navigation">
        <?php if ($adminCheckout): ?>
            <a href="/admin">Dashboard</a><a href="/admin/orders" class="active">Shop orders</a>
        <?php else: ?>
            <a href="/">Home</a><a href="/shop" class="active">Official shop</a><a href="/teams">Teams</a>
        <?php endif; ?>
    </nav>

    <section class="panel inline-checkout-panel" aria-labelledby="inline-checkout-heading">
        <div class="inline-checkout-order-top">
            <div><div class="panel-kicker">ORDER REFERENCE</div><strong class="mono-cell"><?= yuc_e($order['reference']) ?></strong></div>
            <span class="outcome-badge outcome-pending_payment">AWAITING PAYMENT</span>
        </div>
        <div class="inline-checkout-summary">
            <span><small>Customer</small><strong><?= yuc_e($order['customer_name']) ?></strong><small><?= yuc_e($order['customer_email']) ?></small></span>
            <span><small>Amount due</small><strong class="inline-checkout-total">₦<?= number_format((int) $order['total_kobo'] / 100, 2) ?></strong><small>NGN · PayHub</small></span>
        </div>
        <div class="inline-checkout-action">
            <button
                class="button button-primary button-inline-pay"
                type="button"
                data-payhub-inline-button
                data-public-key="<?= yuc_e($payHubPublicKey) ?>"
                data-customer-email="<?= yuc_e($order['customer_email']) ?>"
                data-amount-kobo="<?= (int) $order['total_kobo'] ?>"
                data-provider-reference="<?= yuc_e($order['provider_reference']) ?>"
                data-return-url="<?= yuc_e($returnUrl) ?>"
                disabled
            >Open secure PayHub checkout <span aria-hidden="true">→</span></button>
            <p class="inline-checkout-status" role="status" aria-live="polite" data-payhub-inline-status>Loading PayHub secure checkout…</p>
        </div>
        <div class="inline-checkout-security"><span aria-hidden="true">✓</span><p>Do not close this page until PayHub returns you. A successful popup message is not proof of payment; the order page checks the transaction with PayHub's verification API.</p></div>
        <div class="inline-checkout-footer"><a class="button button-quiet" href="<?= yuc_e($returnUrl) ?>">Return to <?= $adminCheckout ? 'orders' : 'the shop' ?></a><?php if ($contactEmail !== ''): ?><span>Need help? <a href="mailto:<?= yuc_e($contactEmail) ?>">Contact the tournament team</a>.</span><?php endif; ?></div>
    </section>
</section>
