<?php
/** @var list<array<string,mixed>> $products */
/** @var bool $payHubConfigured */
/** @var string $contactEmail */
/** @var array{type:string,message:string}|null $flash */
$oldForm = is_array($oldForm ?? null) ? $oldForm : [];
$oldQuantities = is_array($oldForm['quantity'] ?? null) ? $oldForm['quantity'] : [];
$demoMode = ($siteMode ?? 'production') === 'demo';
$checkoutReady = !empty($payHubConfigured) && !$demoMode;
?>
<section class="public-content-wrap shop-content-wrap">
    <div class="public-hero shop-hero">
        <div class="eyebrow"><span class="eyebrow-line"></span>YOUTH UNITY CUP · OFFICIAL SHOP</div>
        <h1>Wear the <em>unity.</em></h1>
        <p>Official Youth Unity Cup merchandise, managed by the tournament team. Secure payment is handled by PayHub.</p>
        <span class="public-hero-stamp"><?= yuc_e(yuc_current_year((string) ($appTimezone ?? 'Africa/Lagos'))) ?><br><small>MUSHIN · LAGOS</small></span>
    </div>
    <nav class="public-section-nav" aria-label="Tournament pages">
        <a href="/">Home</a><a href="/teams">Teams</a><a href="/fixtures">Fixtures</a><a href="/results">Results</a><a href="/venues">Venues</a><a href="/registration">Registration</a><a href="/shop" class="active">Official shop</a>
    </nav>

    <?php if (is_array($flash)): ?>
        <div class="alert alert-<?= yuc_e($flash['type']) ?>" role="status" aria-live="polite"><span class="alert-icon" aria-hidden="true"><?= $flash['type'] === 'error' ? '!' : '✓' ?></span><p><?= yuc_e($flash['message']) ?></p></div>
    <?php endif; ?>

    <?php if (!empty($lastOrderAvailable)): ?>
        <div class="shop-return-note"><span>Already checked out?</span><a href="/shop/return">Check your recent order status →</a></div>
    <?php endif; ?>

    <section class="panel shop-panel">
        <div class="panel-topline"><div><div class="panel-kicker">OFFICIAL MERCHANDISE</div><h2>Shop the collection</h2></div><span class="readiness-pill <?= $checkoutReady ? 'is-ready' : 'is-warning' ?>"><span></span><?= $demoMode ? 'DEMO CHECKOUT PAUSED' : ($checkoutReady ? 'PAYHUB CHECKOUT' : 'PAYMENTS NOT CONFIGURED') ?></span></div>
        <?php if ($demoMode): ?>
            <p class="panel-intro">Browse a sample collection of football gear. Demo prices and stock are illustrative only.</p>
            <div class="demo-checkout-note"><strong>Preview only — checkout is paused.</strong><span>These sample products are shown only in Demo mode. Your live catalogue and stock remain untouched, and no order or payment will be created.</span></div>
        <?php else: ?>
            <p class="panel-intro">Prices are set by Youth Unity Cup administrators. Your chosen items and current price are checked again before inventory is reserved.</p>
        <?php endif; ?>

        <?php if ($products === []): ?>
            <div class="public-empty shop-empty"><span>◈</span><strong>The official collection is being prepared.</strong><p>Check back soon or contact the tournament team for an update.</p></div>
        <?php else: ?>
            <form method="post" action="/shop/checkout" class="shop-checkout-form">
                <?= yuc_csrf_field() ?>
                <div class="honeypot-field" aria-hidden="true"><label for="shop-company-website">Company website</label><input id="shop-company-website" name="company_website" tabindex="-1" autocomplete="off"></div>
                <div class="shop-product-grid">
                    <?php foreach ($products as $product): $id = (int) $product['id']; $stock = (int) $product['stock_quantity']; ?>
                        <article class="shop-product-card">
                            <div class="shop-product-mark" aria-hidden="true">Y</div>
                            <div class="shop-product-copy"><span class="shop-product-sku">SKU · <?= yuc_e($product['sku']) ?></span><h3><?= yuc_e($product['name']) ?></h3><p><?= yuc_e($product['description'] !== '' ? $product['description'] : 'Official Youth Unity Cup merchandise.') ?></p></div>
                            <div class="shop-product-bottom"><strong class="shop-product-price">₦<?= number_format((int) $product['price_kobo'] / 100, 2) ?></strong><label for="shop-quantity-<?= $id ?>">Quantity</label><input id="shop-quantity-<?= $id ?>" name="quantity[<?= $id ?>]" type="number" min="0" max="<?= min(20, $stock) ?>" step="1" value="<?= yuc_e($oldQuantities[$id] ?? '0') ?>" aria-describedby="shop-stock-<?= $id ?>" <?= $demoMode ? 'disabled' : '' ?>><small id="shop-stock-<?= $id ?>"><?= $demoMode ? 'Demo stock: ' . $stock : 'Up to ' . min(20, $stock) . ' available' ?></small></div>
                        </article>
                    <?php endforeach; ?>
                </div>

                <?php if (!$demoMode): ?>
                    <div class="shop-customer-fields">
                        <div><div class="panel-kicker">CHECKOUT DETAILS</div><h3>Where should we send your receipt?</h3><p class="panel-intro">PayHub secures payment. The tournament team uses these details to follow up about your order.</p></div>
                        <div class="form-grid">
                            <div class="field-group"><label for="shop-customer-name">Full name *</label><input id="shop-customer-name" name="customer_name" required maxlength="140" autocomplete="name" value="<?= yuc_e($oldForm['customer_name'] ?? '') ?>"></div>
                            <div class="field-group"><label for="shop-customer-email">Email *</label><input id="shop-customer-email" name="customer_email" type="email" required maxlength="190" autocomplete="email" value="<?= yuc_e($oldForm['customer_email'] ?? '') ?>"></div>
                            <div class="field-group"><label for="shop-customer-phone">Phone <span>(optional)</span></label><input id="shop-customer-phone" name="customer_phone" type="tel" maxlength="40" autocomplete="tel" placeholder="+234" value="<?= yuc_e($oldForm['customer_phone'] ?? '') ?>"></div>
                            <div class="field-group"><label for="shop-fulfillment-notes">Delivery / collection notes <span>(optional)</span></label><input id="shop-fulfillment-notes" name="fulfillment_notes" maxlength="500" placeholder="Preferred collection or delivery details" value="<?= yuc_e($oldForm['fulfillment_notes'] ?? '') ?>"></div>
                        </div>
                    </div>
                    <?php if (!$payHubConfigured): ?><p class="shop-payment-warning">Online checkout is temporarily unavailable while secure payment configuration is completed. Please contact the tournament team for an update.</p><?php endif; ?>
                    <div class="shop-checkout-actions"><span>Payment amount is confirmed by the server from the current catalog.</span><button class="button button-primary" type="submit" <?= !$checkoutReady ? 'disabled' : '' ?>>Continue to PayHub <span aria-hidden="true">→</span></button></div>
                <?php else: ?>
                    <div class="shop-checkout-actions"><span>No personal details, orders, or payments are collected in Demo mode.</span><button class="button button-primary" type="submit" disabled>Checkout paused</button></div>
                <?php endif; ?>
            </form>
        <?php endif; ?>
    </section>
    <?php if ($contactEmail !== ''): ?><p class="public-contact">Order questions? Contact <a href="mailto:<?= yuc_e($contactEmail) ?>"><?= yuc_e($contactEmail) ?></a>.</p><?php endif; ?>
    <footer class="public-page-footer"><a href="/">Youth Unity Cup</a><span>Official merchandise · Mushin, Lagos</span><a href="/registration">Join the tournament →</a></footer>
</section>
