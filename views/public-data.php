<?php
/** @var string $resource */
/** @var string $heading */
/** @var string $description */
/** @var list<array<string,mixed>> $rows */
/** @var string $contactEmail */
$timezone = (string) ($appTimezone ?? 'UTC');
if (!in_array($timezone, DateTimeZone::listIdentifiers(), true)) {
    $timezone = 'UTC';
}
?>
<section class="public-content-wrap">
    <div class="public-hero">
        <div class="eyebrow"><span class="eyebrow-line"></span>YOUTH UNITY CUP · OFFICIAL</div>
        <h1><?= yuc_e($heading) ?><em>.</em></h1>
        <p><?= yuc_e($description) ?></p>
        <span class="public-hero-stamp">2026<br><small>MUSHIN · LAGOS</small></span>
    </div>
    <nav class="public-section-nav" aria-label="Tournament pages">
        <a href="/">Home</a><a href="/teams" class="<?= $resource === 'teams' ? 'active' : '' ?>">Teams</a><a href="/fixtures" class="<?= $resource === 'fixtures' ? 'active' : '' ?>">Fixtures</a><a href="/results" class="<?= $resource === 'results' ? 'active' : '' ?>">Results</a><a href="/venues" class="<?= $resource === 'venues' ? 'active' : '' ?>">Venues</a><a href="/registration">Registration</a><a href="/shop">Official shop</a>
    </nav>
    <section class="panel public-data-panel">
        <?php if ($resource === 'fixtures'): ?>
            <div class="panel-topline"><div><div class="panel-kicker">MATCH SCHEDULE</div><h2>Upcoming fixtures</h2></div><span class="readiness-pill is-ready"><span></span><?= count($rows) ?> MATCHES</span></div>
            <?php if ($rows === []): ?><div class="public-empty"><span>◷</span><strong>Fixtures are being prepared.</strong><p>Check back after the official draw.</p></div><?php else: ?>
                <div class="public-match-list"><?php foreach ($rows as $row): $kickoff = new DateTimeImmutable((string) $row['kickoff_at'], new DateTimeZone('UTC')); $kickoff = $kickoff->setTimezone(new DateTimeZone($timezone)); ?>
                    <article class="public-match-card">
                        <div class="public-match-meta"><span><?= yuc_e($row['stage']) ?></span><span><?= yuc_e($kickoff->format('D, j M Y · H:i')) ?> <?= yuc_e($kickoff->format('T')) ?></span></div>
                        <div class="public-match-teams"><div><small><?= yuc_e($row['home_zone']) ?></small><strong><?= yuc_e($row['home_team']) ?></strong></div><span class="match-vs">VS</span><div><small><?= yuc_e($row['away_zone']) ?></small><strong><?= yuc_e($row['away_team']) ?></strong></div></div>
                        <div class="public-match-footer"><span>⌖ <?= yuc_e($row['venue_name'] ?? 'Venue to be confirmed') ?><?= !empty($row['venue_zone']) ? ' · ' . yuc_e($row['venue_zone']) : '' ?></span><span class="outcome-badge outcome-<?= yuc_e($row['status']) ?>"><?= yuc_e(strtoupper((string) $row['status'])) ?></span></div>
                    </article>
                <?php endforeach; ?></div>
            <?php endif; ?>
        <?php elseif ($resource === 'results'): ?>
            <div class="panel-topline"><div><div class="panel-kicker">VERIFIED SCORES</div><h2>Recent results</h2></div><span class="readiness-pill is-ready"><span></span><?= count($rows) ?> RESULTS</span></div>
            <?php if ($rows === []): ?><div class="public-empty"><span>◎</span><strong>No results have been published yet.</strong><p>Official scores will appear here after each match.</p></div><?php else: ?>
                <div class="responsive-table"><table class="data-table public-results-table"><thead><tr><th>Round</th><th>Home</th><th>Score</th><th>Away</th><th>Venue</th><th>Date</th></tr></thead><tbody>
                <?php foreach ($rows as $row): $resultDate = (new DateTimeImmutable((string) $row['kickoff_at'], new DateTimeZone('UTC')))->setTimezone(new DateTimeZone($timezone)); ?><tr><td><?= yuc_e($row['stage']) ?></td><td><strong><?= yuc_e($row['home_team']) ?></strong></td><td><span class="result-score"><?= (int) $row['home_score'] ?> : <?= (int) $row['away_score'] ?></span></td><td><strong><?= yuc_e($row['away_team']) ?></strong></td><td><?= yuc_e($row['venue_name'] ?? '—') ?></td><td><?= yuc_e($resultDate->format('j M Y · H:i T')) ?></td></tr><?php endforeach; ?>
                </tbody></table></div>
            <?php endif; ?>
        <?php elseif ($resource === 'teams'): ?>
            <div class="panel-topline"><div><div class="panel-kicker">COMMUNITY REPRESENTATIVES</div><h2>Registered teams</h2></div><span class="readiness-pill is-ready"><span></span><?= count($rows) ?> TEAMS</span></div>
            <?php if ($rows === []): ?><div class="public-empty"><span>♟</span><strong>Team entries will be published after verification.</strong><p>Check back as the tournament draw approaches.</p></div><?php else: ?>
                <div class="public-team-grid"><?php foreach ($rows as $row): ?><article class="public-team-card"><span class="team-crest" aria-hidden="true">Y</span><div><span><?= yuc_e($row['zone']) ?><?= !empty($row['group_name']) ? ' · GROUP ' . yuc_e($row['group_name']) : '' ?></span><h3><?= yuc_e($row['name']) ?></h3></div></article><?php endforeach; ?></div>
            <?php endif; ?>
        <?php else: ?>
            <div class="panel-topline"><div><div class="panel-kicker">MATCH-DAY LOCATIONS</div><h2>Official venues</h2></div><span class="readiness-pill is-ready"><span></span><?= count($rows) ?> VENUES</span></div>
            <?php if ($rows === []): ?><div class="public-empty"><span>⌖</span><strong>Venue details are being confirmed.</strong><p>Locations will be published before match day.</p></div><?php else: ?>
                <div class="public-venue-grid"><?php foreach ($rows as $row): ?><article class="public-venue-card"><div class="venue-zone-tag"><?= yuc_e($row['zone']) ?></div><h3><?= yuc_e($row['name']) ?></h3><p><?= yuc_e($row['address'] !== '' ? $row['address'] : 'Address details will be confirmed.') ?></p><span>SAFE CAPACITY <strong><?= (int) $row['capacity'] ?></strong></span></article><?php endforeach; ?></div>
            <?php endif; ?>
        <?php endif; ?>
        <?php if ($contactEmail !== ''): ?><p class="public-contact">Questions? Contact <a href="mailto:<?= yuc_e($contactEmail) ?>"><?= yuc_e($contactEmail) ?></a>.</p><?php endif; ?>
    </section>
    <footer class="public-page-footer"><a href="/">Youth Unity Cup</a><span>Grassroots football · Mushin, Lagos</span><a href="/registration">Register for the cup →</a></footer>
</section>
