<?php
/** @var array<string,mixed>|null $order */
/** @var string $verificationNotice */
/** @var string $contactEmail */
?>
<section class="public-content-wrap shop-content-wrap">
    <div class="public-hero shop-hero shop-status-hero">
        <div class="eyebrow"><span class="eyebrow-line"></span>YOUTH UNITY CUP · OFFICIAL SHOP</div>
        <h1>Order <em>status.</em></h1>
        <p>Payment status is checked directly with PayHub. A checkout redirect by itself is not proof of payment.</p>
    </div>
    <nav class="public-section-nav" aria-label="Tournament pages">
        <a href="/">Home</a><a href="/teams">Teams</a><a href="/fixtures">Fixtures</a><a href="/results">Results</a><a href="/venues">Venues</a><a href="/registration">Registration</a><a href="/shop" class="active">Official shop</a>
    </nav>
    <section class="panel shop-status-panel">
        <?php if ($verificationNotice !== ''): ?><div class="alert alert-info" role="status"><span class="alert-icon" aria-hidden="true">i</span><p><?= yuc_e($verificationNotice) ?></p></div><?php endif; ?>
        <?php if (!is_array($order)): ?>
            <div class="public-empty"><span>◷</span><strong>We could not find a recent order in this browser.</strong><p>Return to the shop to place an order, or contact the tournament team if you have already paid.</p><a class="button button-primary" href="/shop">Return to the shop</a></div>
        <?php else: ?>
            <?php
            $status = (string) ($order['status'] ?? 'pending_payment');
            $statusCopy = match ($status) {
                'paid' => ['Payment confirmed', 'Your PayHub payment is confirmed. The tournament team will prepare your order.'],
                'paid_needs_review' => ['Payment received · review needed', 'PayHub reports a payment, but the order needs manual review before fulfillment. The tournament team will follow up.'],
                'processing' => ['Order processing', 'Your payment is confirmed and the order is being prepared.'],
                'fulfilled' => ['Order fulfilled', 'The tournament team marked this order as fulfilled.'],
                'payment_failed' => ['Payment not completed', 'PayHub reported that this payment did not complete. Your reserved stock has been released.'],
                'cancelled' => ['Order closed', 'This order is no longer active. If you completed payment after it was closed, contact the tournament team.'],
                default => ['Awaiting PayHub confirmation', 'Your order is reserved for a limited time while PayHub verifies payment. This page can be refreshed to check again.'],
            };
            ?>
            <div class="shop-order-heading"><div><div class="panel-kicker">ORDER REFERENCE</div><h2 class="mono-cell"><?= yuc_e($order['reference']) ?></h2></div><span class="outcome-badge outcome-<?= yuc_e($status) ?>"><?= yuc_e(strtoupper(str_replace('_', ' ', $status))) ?></span></div>
            <div class="shop-order-state"><strong><?= yuc_e($statusCopy[0]) ?></strong><p><?= yuc_e($statusCopy[1]) ?></p></div>
            <div class="shop-order-items">
                <?php foreach (($order['items'] ?? []) as $item): ?>
                    <div class="shop-order-line"><span><strong><?= (int) $item['quantity'] ?> × <?= yuc_e($item['product_name']) ?></strong><small><?= yuc_e($item['sku_snapshot']) ?> · ₦<?= number_format((int) $item['unit_price_kobo'] / 100, 2) ?> each</small></span><strong>₦<?= number_format((int) $item['line_total_kobo'] / 100, 2) ?></strong></div>
                <?php endforeach; ?>
                <div class="shop-order-total"><span>Order total</span><strong>₦<?= number_format((int) $order['total_kobo'] / 100, 2) ?></strong></div>
            </div>
            <?php if ($status === 'pending_payment' && !empty($order['checkout_url']) && \Yuc\Services\PayHubClient::isTrustedCheckoutUrl((string) $order['checkout_url'])): ?><div class="shop-status-actions"><a class="button button-primary" href="<?= yuc_e($order['checkout_url']) ?>">Continue to PayHub checkout →</a><a class="button button-quiet" href="/shop/return?order=<?= rawurlencode((string) $order['reference']) ?>">Check payment status</a></div><?php elseif (in_array($status, ['pending_payment', 'paid_needs_review'], true)): ?><div class="shop-status-actions"><a class="button button-primary" href="/shop/return?order=<?= rawurlencode((string) $order['reference']) ?>">Check payment status</a></div><?php endif; ?>
            <p class="shop-order-footnote">Keep this order reference for any follow-up. Status checks use the PayHub verification API; payment details are never exposed in the browser.</p>
        <?php endif; ?>
    </section>
    <?php if ($contactEmail !== ''): ?><p class="public-contact">Need help? Contact <a href="mailto:<?= yuc_e($contactEmail) ?>"><?= yuc_e($contactEmail) ?></a>.</p><?php endif; ?>
    <footer class="public-page-footer"><a href="/">Youth Unity Cup</a><span>Official merchandise · Mushin, Lagos</span><a href="/shop">Back to the shop →</a></footer>
</section>
