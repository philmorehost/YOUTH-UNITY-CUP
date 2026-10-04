<?php
/** @var string $token */
/** @var array{type:string,message:string}|null $flash */
?>
<section class="login-wrap">
    <div class="login-story">
        <div class="login-kicker"><span class="live-dot"></span> ONE-TIME RESET</div>
        <h1>Choose a<br><em>strong password.</em></h1>
        <p>Your new password must be at least 12 characters. The reset link is single-use and expires automatically.</p>
        <div class="story-pitch" aria-hidden="true"><span class="pitch-center"></span><span class="pitch-box pitch-left"></span><span class="pitch-box pitch-right"></span></div>
    </div>
    <div class="login-card">
        <div class="login-card-top"><div class="mini-shield">Y</div><span>ACCOUNT RECOVERY</span></div>
        <h2>Set a new password</h2>
        <p class="login-card-lede">Use a unique password you have not used elsewhere.</p>
        <?php if (is_array($flash)): ?><div class="alert alert-<?= yuc_e($flash['type']) ?>" role="status"><span class="alert-icon" aria-hidden="true"><?= $flash['type'] === 'error' ? '!' : '✓' ?></span><p><?= yuc_e($flash['message']) ?></p></div><?php endif; ?>
        <?php if ($token === ''): ?>
            <div class="alert alert-error" role="status"><span class="alert-icon" aria-hidden="true">!</span><p>This reset link is missing or malformed. Request a fresh link to continue.</p></div>
            <a class="button button-primary button-full" href="/admin/forgot-password">Request a new reset link →</a>
        <?php else: ?>
            <form method="post" action="/admin/reset-password" class="form-stack login-form">
                <?= yuc_csrf_field() ?><input type="hidden" name="token" value="<?= yuc_e($token) ?>">
                <div class="field-group"><label for="new-password">New password</label><div class="input-wrap input-with-icon"><span class="input-icon" aria-hidden="true">⌑</span><input id="new-password" name="password" type="password" required minlength="12" maxlength="1024" autocomplete="new-password" autofocus placeholder="At least 12 characters"><button type="button" class="password-toggle" data-password-toggle="new-password" aria-label="Show password">Show</button></div></div>
                <div class="field-group"><label for="confirm-password">Confirm new password</label><div class="input-wrap input-with-icon"><span class="input-icon" aria-hidden="true">⌑</span><input id="confirm-password" name="password_confirmation" type="password" required minlength="12" maxlength="1024" autocomplete="new-password" placeholder="Enter it once more"></div></div>
                <button class="button button-primary button-full" type="submit">Change password <span aria-hidden="true">→</span></button>
            </form>
        <?php endif; ?>
        <div class="login-recovery-links"><a class="public-site-link" href="/admin/login">← Return to sign in</a><a class="public-site-link" href="/">Public website</a></div>
    </div>
</section>
