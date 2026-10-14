<?php
/** @var string $siteTitle */
$siteTitle = trim((string) ($siteTitle ?? 'Youth Unity Cup'));
if ($siteTitle === '') {
    $siteTitle = 'Youth Unity Cup';
}
$teamCount = max(0, (int) ($teamCount ?? 0));
$playerCount = max(0, (int) ($playerCount ?? 0));
$fixtureCount = max(0, (int) ($fixtureCount ?? 0));
$venueCount = max(0, (int) ($venueCount ?? 0));
$appTimezone = (string) ($appTimezone ?? 'Africa/Lagos');
$heroSettings = is_array($heroSettings ?? null) ? $heroSettings : [];
$heroType = in_array(($heroSettings['type'] ?? 'default'), ['default', 'image', 'youtube', 'video'], true) ? $heroSettings['type'] : 'default';
$liveStream = is_array($liveStream ?? null) ? $liveStream : [];
$liveStreamEnabled = !empty($liveStream['enabled']) && is_string($liveStream['url'] ?? null) && $liveStream['url'] !== '';
$liveStreamPlatform = in_array(($liveStream['platform'] ?? ''), ['youtube', 'tiktok'], true) ? $liveStream['platform'] : '';
$liveStreamProviderName = $liveStreamPlatform === 'tiktok' ? 'TikTok' : 'YouTube';
$liveStreamEmbed = is_string($liveStream['embed_url'] ?? null) ? $liveStream['embed_url'] : '';
$liveStreamTitle = trim((string) ($liveStream['title'] ?? 'Youth Unity Cup Live')) ?: 'Youth Unity Cup Live';
$liveStreamCanEmbed = $liveStreamEnabled && $liveStreamPlatform === 'youtube' && $liveStreamEmbed !== '';
?>
<section class="public-content-wrap home-content-wrap">
    <section class="home-hero" aria-labelledby="home-title">
        <div class="home-hero-media" <?= $heroType === 'image' ? '' : 'aria-hidden="true"' ?>>
            <?php if ($heroType === 'image' && !empty($heroSettings['media_url'])): ?>
                <img src="<?= yuc_e($heroSettings['media_url']) ?>" alt="<?= yuc_e($heroSettings['image_alt'] ?? 'Youth Unity Cup community football') ?>" fetchpriority="high" decoding="async">
            <?php elseif ($heroType === 'video' && !empty($heroSettings['media_url'])): ?>
                <video autoplay muted loop playsinline preload="auto" poster="/assets/yuc-hero.jpg"><source src="<?= yuc_e($heroSettings['media_url']) ?>" type="<?= str_ends_with((string) ($heroSettings['media_file'] ?? ''), '.webm') ? 'video/webm' : 'video/mp4' ?>"></video>
            <?php elseif ($heroType === 'youtube' && !empty($heroSettings['embed_url'])): ?>
                <iframe src="<?= yuc_e($heroSettings['embed_url']) ?>" title="Youth Unity Cup homepage hero video" loading="eager" referrerpolicy="strict-origin-when-cross-origin" allow="autoplay; encrypted-media; picture-in-picture" sandbox="allow-scripts allow-same-origin allow-presentation" tabindex="-1"></iframe>
            <?php else: ?>
                <img src="/assets/yuc-hero.jpg" alt="Youth Unity Cup community football" fetchpriority="high" decoding="async">
            <?php endif; ?>
        </div>
        <div class="home-hero-copy">
            <div class="home-hero-kicker"><span class="hero-kicker-ball" aria-hidden="true">●</span><?= yuc_e($siteTitle) ?> <span>· MUSHIN, LAGOS</span></div>
            <h1 id="home-title">Football brings<br><em>us together.</em></h1>
            <p>Local pride. Big-game energy. The Youth Unity Cup puts community football, young talent, and a shared love of the game in the spotlight.</p>
            <div class="home-hero-actions">
                <a class="button button-primary" href="/fixtures">Explore fixtures <span aria-hidden="true">→</span></a>
                <?php if ($liveStreamEnabled): ?>
                    <?php $watchLiveHref = $liveStreamCanEmbed ? '#live-stream' : $liveStream['url']; ?>
                    <a class="button button-live" href="<?= yuc_e($watchLiveHref) ?>" <?= $liveStreamCanEmbed ? '' : 'target="_blank" rel="noopener noreferrer"' ?>><span class="live-button-dot" aria-hidden="true"></span>Watch live <span aria-hidden="true">↗</span></a>
                <?php endif; ?>
                <a class="button button-outline" href="/teams">Meet the teams</a>
            </div>
        </div>
        <div class="home-hero-season"><strong><?= yuc_e(yuc_current_year($appTimezone)) ?> SEASON</strong><span>COMMUNITY FOOTBALL · LAGOS</span></div>
        <div class="home-hero-edge" aria-hidden="true"><span>UNITY</span><span>·</span><span>PRIDE</span><span>·</span><span>PLAY</span></div>
    </section>

    <?php if ($liveStreamEnabled): ?>
        <section id="live-stream" class="home-live-panel <?= $liveStreamCanEmbed ? 'has-embedded-player' : 'has-external-player' ?>" aria-labelledby="home-live-title">
            <div class="home-live-heading">
                <div><span class="home-live-kicker"><i aria-hidden="true"></i>YOUTH UNITY CUP · ON AIR</span><h2 id="home-live-title"><?= yuc_e($liveStreamTitle) ?></h2><p>Join the tournament live from the official broadcast.</p></div>
                <span class="home-live-platform"><?= yuc_e(strtoupper($liveStreamPlatform)) ?> LIVE</span>
            </div>
            <?php if ($liveStreamCanEmbed): ?>
                <div class="home-live-player"><iframe src="<?= yuc_e($liveStreamEmbed) ?>" title="<?= yuc_e($liveStreamTitle) ?> — live football broadcast" loading="eager" allow="autoplay; encrypted-media; picture-in-picture; web-share" allowfullscreen referrerpolicy="strict-origin-when-cross-origin"></iframe></div>
                <p class="home-live-autoplay-note">Playback starts muted where your browser permits autoplay. Use the player controls to turn sound on. <a href="<?= yuc_e($liveStream['url']) ?>" target="_blank" rel="noopener noreferrer">Open directly on YouTube ↗</a></p>
            <?php else: ?>
                <div class="home-live-external"><span class="home-live-external-icon" aria-hidden="true">▶</span><div><strong>Continue to the live broadcast</strong><p><?= $liveStreamPlatform === 'tiktok' ? 'TikTok does not provide an official embeddable player for TikTok LIVE; open the live channel on TikTok to watch.' : 'This YouTube channel link is not directly embeddable. Open it on YouTube to watch the current broadcast.' ?></p></div><a class="button button-live" href="<?= yuc_e($liveStream['url']) ?>" target="_blank" rel="noopener noreferrer">Watch on <?= yuc_e($liveStreamProviderName) ?> <span aria-hidden="true">↗</span></a></div>
            <?php endif; ?>
        </section>
    <?php endif; ?>

    <nav class="public-section-nav" aria-label="Tournament pages">
        <a href="/" class="active">Home</a><a href="/teams">Teams &amp; groups</a><a href="/fixtures">Fixtures</a><a href="/results">Results</a><a href="/venues">Venues</a><a href="/registration">Registration</a><a href="/shop">Official shop</a>
    </nav>

    <section class="home-scorebar" aria-label="Tournament overview">
        <div class="home-stat"><strong><?= $teamCount ?></strong><span>TEAMS</span></div>
        <div class="home-stat"><strong><?= $playerCount ?></strong><span>PLAYERS IN SQUADS</span></div>
        <div class="home-stat"><strong><?= $fixtureCount ?></strong><span>UPCOMING MATCHES</span></div>
        <div class="home-stat"><strong><?= $venueCount ?></strong><span>COMMUNITY VENUES</span></div>
    </section>

    <section class="panel public-data-panel home-explore-panel">
        <div class="panel-topline"><div><div class="panel-kicker">YOUR TOURNAMENT HUB</div><h2>Follow every moment</h2></div><span class="home-season-tag"><?= yuc_e(yuc_current_year($appTimezone)) ?> · MUSHIN, LAGOS</span></div>
        <p class="panel-intro">From the group-stage tables to the final whistle, find the information you need to follow the cup.</p>
        <div class="home-link-grid">
            <a class="home-link-card" href="/teams"><span aria-hidden="true">♟</span><strong>Teams &amp; groups</strong><small>Check standings, squads, player cards, and community profiles.</small><b>Explore the groups →</b></a>
            <a class="home-link-card" href="/fixtures"><span aria-hidden="true">◷</span><strong>Fixtures</strong><small>Know who is playing, when kick-off starts, and where to watch.</small><b>See match day →</b></a>
            <a class="home-link-card" href="/results"><span aria-hidden="true">◎</span><strong>Results</strong><small>Catch up on completed matches and published scorelines.</small><b>View results →</b></a>
            <a class="home-link-card" href="/venues"><span aria-hidden="true">⌖</span><strong>Venues</strong><small>Find local grounds and spectator-capacity information.</small><b>Find a ground →</b></a>
            <a class="home-link-card" href="/registration"><span aria-hidden="true">＋</span><strong>Get involved</strong><small>Register your interest as a player, official, volunteer, or vendor.</small><b>Join the cup →</b></a>
            <a class="home-link-card" href="/shop"><span aria-hidden="true">◈</span><strong>Official shop</strong><small>Browse Youth Unity Cup merchandise and team-day essentials.</small><b>Visit the shop →</b></a>
        </div>
    </section>
    <footer class="public-page-footer"><a href="/">Youth Unity Cup</a><span>Grassroots football · Mushin, Lagos</span><a href="/teams">Meet the squads →</a></footer>
</section>
