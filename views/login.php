<?php
/** @var array{type:string,message:string}|null $flash */
?>
<section class="login-wrap">
    <div class="login-story">
        <div class="login-kicker"><span class="live-dot"></span> ADMIN ACCESS</div>
        <h1>One cup.<br><em>One community.</em></h1>
        <p>The Youth Unity Cup control room is where your fixtures, registration, safety updates, and match-day stories come together.</p>
        <div class="login-stats">
            <div><strong>20</strong><small>TEAMS</small></div>
            <span></span>
            <div><strong>10</strong><small>ZONES</small></div>
            <span></span>
            <div><strong>01</strong><small>COMMUNITY</small></div>
        </div>
        <div class="story-pitch" aria-hidden="true"><span class="pitch-center"></span><span class="pitch-box pitch-left"></span><span class="pitch-box pitch-right"></span></div>
    </div>
    <div class="login-card">
        <div class="login-card-top"><div class="mini-shield">Y</div><span>ADMIN PORTAL</span></div>
        <h2>Welcome back</h2>
        <p class="login-card-lede">Sign in to manage the tournament.</p>

        <?php if (is_array($flash)): ?>
            <div class="alert alert-<?= yuc_e($flash['type']) ?>" role="alert">
                <span class="alert-icon" aria-hidden="true"><?= $flash['type'] === 'error' ? '!' : '✓' ?></span>
                <p><?= yuc_e($flash['message']) ?></p>
            </div>
        <?php endif; ?>

        <form method="post" action="/admin/login" class="form-stack login-form">
            <?= yuc_csrf_field() ?>
            <div class="field-group">
                <label for="identifier">Username or email</label>
                <div class="input-wrap input-with-icon"><span class="input-icon" aria-hidden="true">◎</span><input id="identifier" name="identifier" type="text" required maxlength="190" autocomplete="username" autofocus placeholder="Enter your username or email"></div>
            </div>
            <div class="field-group">
                <label for="password">Password</label>
                <div class="input-wrap input-with-icon"><span class="input-icon" aria-hidden="true">⌑</span><input id="password" name="password" type="password" required maxlength="1024" autocomplete="current-password" placeholder="Enter your password"><button type="button" class="password-toggle" data-password-toggle="password" aria-label="Show password">Show</button></div>
            </div>
            <button class="button button-primary button-full" type="submit">Sign in securely <span aria-hidden="true">→</span></button>
        </form>
        <div class="login-security"><span class="security-lock" aria-hidden="true">⌑</span><span>Protected sign-in with rate limiting and login alerts.</span></div>
        <div class="login-recovery-links"><a class="public-site-link" href="/admin/forgot-password">Forgot your password?</a><a class="public-site-link" href="/">← Return to public website</a></div>
    </div>
</section>
