<?php
/** @var string $siteTitle */
$siteTitle = trim((string) ($siteTitle ?? 'Youth Unity Cup'));
if ($siteTitle === '') {
    $siteTitle = 'Youth Unity Cup';
}
?>
<section class="public-content-wrap home-content-wrap">
    <div class="public-hero home-hero">
        <div class="eyebrow"><span class="eyebrow-line"></span><?= yuc_e($siteTitle) ?> · MUSHIN, LAGOS</div>
        <h1>One community.<br><em>One cup.</em></h1>
        <p>Grassroots football bringing communities together through sport, opportunity, and a shared love of the game.</p>
        <div class="home-hero-actions"><a class="button button-primary" href="/fixtures">Explore fixtures <span aria-hidden="true">→</span></a><a class="button button-outline" href="/registration">Take part</a></div>
        <span class="public-hero-stamp">2026<br><small>MUSHIN · LAGOS</small></span>
    </div>
    <nav class="public-section-nav" aria-label="Tournament pages">
        <a href="/" class="active">Home</a><a href="/teams">Teams</a><a href="/fixtures">Fixtures</a><a href="/results">Results</a><a href="/venues">Venues</a><a href="/registration">Registration</a><a href="/shop">Official shop</a>
    </nav>
    <section class="panel public-data-panel home-explore-panel">
        <div class="panel-topline"><div><div class="panel-kicker">YOUR TOURNAMENT HUB</div><h2>Explore the cup</h2></div></div>
        <p class="panel-intro">Follow the tournament, meet the teams, or find out how you can get involved.</p>
        <div class="home-link-grid">
            <a class="home-link-card" href="/teams"><span aria-hidden="true">♟</span><strong>Teams</strong><small>Meet the communities taking part.</small><b>View teams →</b></a>
            <a class="home-link-card" href="/fixtures"><span aria-hidden="true">◷</span><strong>Fixtures</strong><small>See upcoming matches and venues.</small><b>View fixtures →</b></a>
            <a class="home-link-card" href="/results"><span aria-hidden="true">◎</span><strong>Results</strong><small>Follow verified match scores.</small><b>View results →</b></a>
            <a class="home-link-card" href="/venues"><span aria-hidden="true">⌖</span><strong>Venues</strong><small>Find official match locations.</small><b>View venues →</b></a>
            <a class="home-link-card" href="/registration"><span aria-hidden="true">＋</span><strong>Get involved</strong><small>Register to take part in the cup.</small><b>Register →</b></a>
            <a class="home-link-card" href="/shop"><span aria-hidden="true">◈</span><strong>Official shop</strong><small>Browse Youth Unity Cup merchandise.</small><b>Visit shop →</b></a>
        </div>
    </section>
</section>
