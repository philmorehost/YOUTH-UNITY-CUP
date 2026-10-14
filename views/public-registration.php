<?php
/** @var array{type:string,message:string}|null $flash */
/** @var string $contactEmail */
$demoMode = ($siteMode ?? 'production') === 'demo';
?>
<section class="public-content-wrap">
    <div class="public-hero registration-hero">
        <div class="eyebrow"><span class="eyebrow-line"></span>YOUTH UNITY CUP · TAKE PART</div>
        <h1>Register for the <em>cup.</em></h1>
        <p>Tell us how you would like to be involved. Every application is reviewed by the tournament team.</p>
        <span class="public-hero-stamp"><?= yuc_e(yuc_current_year((string) ($appTimezone ?? 'Africa/Lagos'))) ?><br><small>MUSHIN · LAGOS</small></span>
    </div>
    <nav class="public-section-nav" aria-label="Tournament pages"><a href="/">Home</a><a href="/teams">Teams</a><a href="/fixtures">Fixtures</a><a href="/results">Results</a><a href="/venues">Venues</a><a href="/registration" class="active">Registration</a><a href="/shop">Official shop</a></nav>
    <div class="registration-layout">
        <section class="panel public-registration-panel">
            <div class="panel-kicker">APPLICATION FORM</div><h2>Let’s get started</h2><p class="panel-intro">Fields marked with * are required. Your information is only used to review and follow up on this tournament application.</p>
            <?php if (is_array($flash)): ?><div class="alert alert-<?= yuc_e($flash['type']) ?>" role="status"><span class="alert-icon" aria-hidden="true"><?= $flash['type'] === 'error' ? '!' : '✓' ?></span><p><?= yuc_e($flash['message']) ?></p></div><?php endif; ?>
            <?php if ($demoMode): ?>
                <div class="demo-registration-note"><strong>Registration is paused in Demo mode.</strong><p>This preview uses sample tournament data. Switch to Production to accept real player, official, volunteer, and vendor applications.</p></div>
            <?php else: ?>
            <form method="post" action="/registration" class="form-stack public-registration-form">
                <?= yuc_csrf_field() ?>
                <div class="honeypot-field" aria-hidden="true"><label for="company-website">Company website</label><input id="company-website" name="company_website" tabindex="-1" autocomplete="off"></div>
                <div class="form-grid"><div class="field-group"><label for="full-name">Full name *</label><input id="full-name" name="full_name" type="text" required maxlength="140" autocomplete="name"></div><div class="field-group"><label for="category">I’m registering as *</label><select id="category" name="category" required data-player-category><option value="">Choose one</option><option value="player">Player</option><option value="team_official">Team official</option><option value="vendor">Vendor</option><option value="volunteer">Volunteer</option><option value="community">Community / CDA</option></select></div><div class="field-group"><label for="email">Email *</label><input id="email" name="email" type="email" required maxlength="190" autocomplete="email"></div><div class="field-group"><label for="phone">Phone</label><input id="phone" name="phone" type="tel" maxlength="40" autocomplete="tel" placeholder="+234"></div><div class="field-group"><label for="age">Age (players)</label><input id="age" name="age" type="number" min="10" max="99" data-player-age><small>Required for under-19 player applications.</small></div><div class="field-group"><label for="zone">Zone / community</label><input id="zone" name="zone" maxlength="50" placeholder="e.g. Zone 4"></div><div class="field-group field-full"><label for="team-name">Team name (if applicable)</label><input id="team-name" name="team_name" maxlength="120"></div><div class="field-group field-full"><label for="details">Anything else we should know?</label><textarea id="details" name="details" rows="4" maxlength="2000" placeholder="Share relevant context for the tournament team."></textarea></div></div>
                <button class="button button-primary" type="submit">Submit registration <span aria-hidden="true">→</span></button>
            </form>
            <?php endif; ?>
        </section>
        <aside class="registration-aside"><div class="registration-aside-mark">Y</div><div class="panel-kicker">WHAT HAPPENS NEXT?</div><h3>One community.<br>Many ways to play.</h3><ol><li><span>01</span><p>We send you a reference number by email.</p></li><li><span>02</span><p>The tournament team reviews your details.</p></li><li><span>03</span><p>We follow up by email if more information is needed.</p></li></ol><?php if ($contactEmail !== ''): ?><p class="aside-contact">Need help? <a href="mailto:<?= yuc_e($contactEmail) ?>">Contact the team</a>.</p><?php endif; ?></aside>
    </div>
    <footer class="public-page-footer"><a href="/">Youth Unity Cup</a><span>Grassroots football · Mushin, Lagos</span><a href="/fixtures">See the fixtures →</a></footer>
</section>
