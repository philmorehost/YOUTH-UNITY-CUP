<?php
/** @var array{type:string,message:string}|null $flash */
?>
<section class="login-wrap">
    <div class="login-story">
        <div class="login-kicker"><span class="live-dot"></span> SECURE ACCOUNT RECOVERY</div>
        <h1>Back in the<br><em>control room.</em></h1>
        <p>Request a one-time link to choose a new password for your Youth Unity Cup administrator account.</p>
        <div class="story-pitch" aria-hidden="true"><span class="pitch-center"></span><span class="pitch-box pitch-left"></span><span class="pitch-box pitch-right"></span></div>
    </div>
    <div class="login-card">
        <div class="login-card-top"><div class="mini-shield">Y</div><span>ACCOUNT RECOVERY</span></div>
        <h2>Reset your password</h2>
        <p class="login-card-lede">Enter the email address connected to your admin account.</p>
        <?php if (is_array($flash)): ?><div class="alert alert-<?= yuc_e($flash['type']) ?>" role="status"><span class="alert-icon" aria-hidden="true"><?= $flash['type'] === 'error' ? '!' : '✓' ?></span><p><?= yuc_e($flash['message']) ?></p></div><?php endif; ?>
        <form method="post" action="/admin/forgot-password" class="form-stack login-form">
            <?= yuc_csrf_field() ?>
            <div class="field-group"><label for="reset-email">Administrator email</label><div class="input-wrap input-with-icon"><span class="input-icon" aria-hidden="true">◎</span><input id="reset-email" name="email" type="email" required maxlength="190" autocomplete="email" autofocus placeholder="admin@example.com"></div></div>
            <button class="button button-primary button-full" type="submit">Send reset link <span aria-hidden="true">→</span></button>
        </form>
        <div class="login-security"><span class="security-lock" aria-hidden="true">⌑</span><span>Reset links are one-time use and expire after 60 minutes.</span></div>
        <div class="login-recovery-links"><a class="public-site-link" href="/admin/login">← Return to sign in</a><a class="public-site-link" href="/">Public website</a></div>
    </div>
</section>
