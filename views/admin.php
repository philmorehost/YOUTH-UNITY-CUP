<?php
/** @var array<string,mixed> $admin */
/** @var bool $emailConfigured */
/** @var array{queued:int,sent:int,failed:int} $outboxCounts */
/** @var array<string,int> $tournamentCounts */
/** @var list<array<string,mixed>> $recentNotifications */
/** @var list<array<string,mixed>> $history */
/** @var array{type:string,message:string}|null $flash */
$siteMode = ($siteMode ?? 'production') === 'demo' ? 'demo' : 'production';
$demoMode = $siteMode === 'demo';
$appTimezone = (string) ($appTimezone ?? 'Africa/Lagos');
?>
<section class="dashboard-wrap">
    <div class="dashboard-title-row">
        <div>
            <div class="eyebrow"><span class="eyebrow-line"></span>ADMIN CONTROL ROOM</div>
            <h1>Good day, <em><?= yuc_e($admin['full_name'] ?? 'Administrator') ?>.</em></h1>
            <p class="lede">Your tournament operations at a glance. Keep the match-day basics and email channel in good shape.</p>
        </div>
        <div class="dashboard-actions">
            <a class="button button-outline" href="/" target="_blank" rel="noopener noreferrer">View public site <span aria-hidden="true">↗</span></a>
            <form method="post" action="/admin/logout">
                <?= yuc_csrf_field() ?>
                <button class="button button-quiet button-logout" type="submit">Sign out</button>
            </form>
        </div>
    </div>

    <?php if (is_array($flash)): ?>
        <div class="alert alert-<?= yuc_e($flash['type']) ?>" role="status" aria-live="polite">
            <span class="alert-icon" aria-hidden="true"><?= $flash['type'] === 'error' ? '!' : '✓' ?></span>
            <p><?= yuc_e($flash['message']) ?></p>
        </div>
    <?php endif; ?>

    <section class="environment-mode-card <?= $demoMode ? 'is-demo-mode' : 'is-production-mode' ?>">
        <div class="environment-mode-copy"><span class="environment-mode-kicker">SITE ENVIRONMENT</span><h2><?= $demoMode ? 'Demo preview is active' : 'Production site is active' ?></h2><p>Demo mode swaps only the tournament directory — teams, player rosters, venues, fixtures, and scores. The live rows are saved as a private database snapshot and restored when you switch back. Registrations, payments, shop records, accounts, and security history are never cleared; public registration, checkout, and unrelated admin writes are paused during the demo.</p></div>
        <div class="environment-mode-status"><span class="environment-status-dot"></span><strong><?= $demoMode ? 'DEMO' : 'PRODUCTION' ?></strong><small><?= $demoMode ? 'SAMPLE TOURNAMENT DATA' : 'LIVE TOURNAMENT DATA' ?></small></div>
        <form method="post" action="/admin/site-mode" data-confirm="<?= $demoMode ? 'Switch back to Production and replace the demo tournament data with the saved live teams, rosters, venues, and fixtures?' : 'Switch to Demo? The current live teams, rosters, venues, and fixtures will be safely snapshotted and replaced by sample data until you switch back.' ?>">
            <?= yuc_csrf_field() ?><input type="hidden" name="mode" value="<?= $demoMode ? 'production' : 'demo' ?>">
            <button class="button <?= $demoMode ? 'button-primary' : 'button-outline' ?>" type="submit"><?= $demoMode ? 'Switch to Production' : 'Switch to Demo' ?><span aria-hidden="true">→</span></button>
        </form>
    </section>

    <section class="welcome-banner">
        <div class="banner-copy"><span class="banner-kicker">YOUTH UNITY CUP / <?= yuc_e(yuc_current_year($appTimezone)) ?></span><h2>Let’s make match day matter.</h2><p>Start with the essentials: site details, tournament content, and a verified notification channel.</p></div>
        <div class="banner-emblem" aria-hidden="true"><span>Y</span><i></i></div>
        <div class="banner-stat"><strong>01</strong><small>CONTROL ROOM</small></div>
    </section>

    <nav class="ops-quick-nav" aria-label="Tournament management">
        <a href="/admin/teams"><span class="quick-nav-icon">♟</span><span><strong>Teams</strong><small><?= (int) ($tournamentCounts['teams'] ?? 0) ?> records</small></span><b>→</b></a>
        <a href="/admin/players"><span class="quick-nav-icon">⚽</span><span><strong>Player profiles</strong><small><?= (int) ($tournamentCounts['players'] ?? 0) ?> squad members</small></span><b>→</b></a>
        <a href="/admin/venues"><span class="quick-nav-icon">⌖</span><span><strong>Venues</strong><small><?= (int) ($tournamentCounts['venues'] ?? 0) ?> records</small></span><b>→</b></a>
        <a href="/admin/fixtures"><span class="quick-nav-icon">◷</span><span><strong>Fixtures &amp; scores</strong><small><?= (int) ($tournamentCounts['fixtures'] ?? 0) ?> records</small></span><b>→</b></a>
        <a href="/admin/registrations"><span class="quick-nav-icon">♙</span><span><strong>Registrations</strong><small><?= (int) ($tournamentCounts['registrations'] ?? 0) ?> applications</small></span><b>→</b></a>
        <a href="/admin/transactions"><span class="quick-nav-icon">₦</span><span><strong>Transactions</strong><small><?= (int) ($tournamentCounts['transactions'] ?? 0) ?> records</small></span><b>→</b></a>
        <a href="/admin/products"><span class="quick-nav-icon">◈</span><span><strong>Shop products</strong><small>Catalog and stock</small></span><b>→</b></a>
        <a href="/admin/orders"><span class="quick-nav-icon">▣</span><span><strong>Shop orders</strong><small>Payment and fulfillment</small></span><b>→</b></a>
        <a href="/admin/homepage-hero"><span class="quick-nav-icon">▣</span><span><strong>Homepage hero</strong><small>Image, YouTube, video</small></span><b>→</b></a>
        <a href="/admin/settings"><span class="quick-nav-icon">⚙</span><span><strong>Site &amp; security</strong><small>Details, SMTP, limits</small></span><b>→</b></a>
        <a href="/admin/security"><span class="quick-nav-icon">⌑</span><span><strong>Blocked IP access</strong><small><?= (int) ($tournamentCounts['blocked_ips'] ?? 0) ?> active blocks</small></span><b>→</b></a>
        <a href="/admin/activity"><span class="quick-nav-icon">≋</span><span><strong>Audit activity</strong><small>Recent system changes</small></span><b>→</b></a>
    </nav>

    <div class="dashboard-cards">
        <article class="metric-card">
            <div class="metric-icon metric-orange" aria-hidden="true">◎</div>
            <div class="metric-label">ADMIN ACCOUNT</div>
            <strong class="metric-main"><?= yuc_e($admin['username']) ?></strong>
            <span class="metric-foot"><?= yuc_e($admin['email']) ?></span>
        </article>
        <article class="metric-card">
            <div class="metric-icon metric-blue" aria-hidden="true">✉</div>
            <div class="metric-label">EMAIL DELIVERY</div>
            <strong class="metric-main"><?= $emailConfigured ? 'SMTP ready' : 'Setup needed' ?></strong>
            <span class="metric-foot"><?= $demoMode ? 'Test sends paused in Demo mode' : ($emailConfigured ? 'Send a test notice below' : 'Messages are safely queued') ?></span>
        </article>
        <article class="metric-card">
            <div class="metric-icon metric-navy" aria-hidden="true">↗</div>
            <div class="metric-label">OUTBOX STATUS</div>
            <strong class="metric-main"><span><?= (int) $outboxCounts['queued'] ?></span> queued <i>·</i> <?= (int) $outboxCounts['failed'] ?> failed</strong>
            <span class="metric-foot"><?= (int) $outboxCounts['sent'] ?> notifications delivered</span>
        </article>
    </div>

    <div class="dashboard-columns">
        <section class="panel dashboard-panel" aria-labelledby="security-title">
            <div class="panel-topline">
                <div><span class="panel-kicker">ACCESS &amp; SAFETY</span><h2 id="security-title">Recent sign-ins</h2></div>
                <span class="table-caption">LAST 10 EVENTS</span>
            </div>
            <div class="responsive-table">
                <table class="data-table">
                    <thead><tr><th>Time (UTC)</th><th>Result</th><th>Source IP</th></tr></thead>
                    <tbody>
                    <?php if ($history === []): ?>
                        <tr><td class="empty-state" colspan="3">No sign-in activity has been recorded yet.</td></tr>
                    <?php else: ?>
                        <?php foreach ($history as $event): ?>
                            <tr>
                                <td><strong><?= yuc_e($event['occurred_at']) ?></strong><small><?= yuc_e($event['details']) ?></small></td>
                                <td><span class="outcome-badge outcome-<?= yuc_e($event['outcome']) ?>"><?= yuc_e(strtoupper((string) $event['outcome'])) ?></span></td>
                                <td class="mono-cell"><?= yuc_e($event['ip_address']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <div class="security-footnote"><span class="status-dot"></span>Sign-in attempts are rate-limited and recorded for review.</div>
        </section>

        <section class="panel dashboard-panel notification-panel" aria-labelledby="notifications-title">
            <div class="panel-topline">
                <div><span class="panel-kicker">EMAIL ACTIVITY</span><h2 id="notifications-title">Notification outbox</h2></div>
                <span class="outbox-count"><?= count($recentNotifications) ?> recent</span>
            </div>
            <p class="panel-intro">Important account, security, system, and transaction events are written to the outbox before delivery.</p>
            <div class="outbox-list">
                <?php if ($recentNotifications === []): ?>
                    <div class="empty-outbox">No notifications yet. Send a test to verify the channel.</div>
                <?php else: ?>
                    <?php foreach ($recentNotifications as $message): ?>
                        <div class="outbox-row">
                            <span class="outbox-status outbox-<?= yuc_e($message['status']) ?>"></span>
                            <span class="outbox-copy"><strong><?= yuc_e($message['event_key']) ?></strong><small><?= yuc_e($message['recipient_email']) ?> · <?= yuc_e($message['created_at']) ?> UTC</small></span>
                            <span class="outcome-badge outcome-<?= yuc_e($message['status']) ?>"><?= yuc_e(strtoupper((string) $message['status'])) ?></span>
                        </div>
                        <?php if (($message['status'] ?? '') !== 'sent' && !empty($message['last_error'])): ?>
                            <div class="outbox-error"><?= yuc_e($message['last_error']) ?></div>
                        <?php endif; ?>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
            <?php if ($demoMode): ?><p class="panel-intro">Test email sends are paused during the tournament demo.</p><?php endif; ?>
            <form method="post" action="/admin/email-test" class="test-email-form">
                <?= yuc_csrf_field() ?>
                <button class="button button-primary button-full" type="submit" <?= $demoMode ? 'disabled' : '' ?>><?= $demoMode ? 'Test notification paused' : 'Send a test notification' ?> <span aria-hidden="true">→</span></button>
            </form>
            <p class="outbox-help">If SMTP is unavailable, queued messages can be retried with <code>php bin/send-notifications.php</code>.</p>
        </section>
    </div>

    <section class="first-login-note">
        <span class="note-icon" aria-hidden="true">i</span>
        <div><strong>First time in the control room?</strong><p>Confirm the timezone and public contact information, then review teams, venues, fixtures, registration, and safety policies before sharing the site.</p></div>
        <span class="first-login-tag">NEXT: SITE SETUP</span>
    </section>
</section>
