<?php
/** @var string $resource */
/** @var string $heading */
/** @var string $description */
/** @var list<array<string,mixed>> $rows */
/** @var string $contactEmail */
$timezone = (string) ($appTimezone ?? 'Africa/Lagos');
if (!in_array($timezone, DateTimeZone::listIdentifiers(), true)) {
    $timezone = 'Africa/Lagos';
}
$teamGroups = is_array($teamGroups ?? null) ? $teamGroups : [];
?>
<section class="public-content-wrap">
    <div class="public-hero">
        <div class="eyebrow"><span class="eyebrow-line"></span>YOUTH UNITY CUP · OFFICIAL</div>
        <h1><?= yuc_e($heading) ?><em>.</em></h1>
        <p><?= yuc_e($description) ?></p>
        <span class="public-hero-stamp"><?= yuc_e(yuc_current_year($timezone)) ?><br><small>MUSHIN · LAGOS</small></span>
    </div>
    <nav class="public-section-nav" aria-label="Tournament pages">
        <a href="/">Home</a><a href="/teams" class="<?= $resource === 'teams' ? 'active' : '' ?>">Teams &amp; groups</a><a href="/fixtures" class="<?= $resource === 'fixtures' ? 'active' : '' ?>">Fixtures</a><a href="/results" class="<?= $resource === 'results' ? 'active' : '' ?>">Results</a><a href="/venues" class="<?= $resource === 'venues' ? 'active' : '' ?>">Venues</a><a href="/registration">Registration</a><a href="/shop">Official shop</a>
    </nav>
    <section class="panel public-data-panel">
        <?php if ($resource === 'fixtures'): ?>
            <div class="panel-topline"><div><div class="panel-kicker">MATCH SCHEDULE</div><h2>Upcoming fixtures</h2></div><span class="readiness-pill is-ready"><span></span><?= count($rows) ?> MATCHES</span></div>
            <?php if ($rows === []): ?><div class="public-empty"><span>◷</span><strong>Fixtures are being prepared.</strong><p>Check back after the official draw.</p></div><?php else: ?>
                <div class="public-match-list"><?php foreach ($rows as $row): $kickoff = (new DateTimeImmutable((string) $row['kickoff_at'], new DateTimeZone('UTC')))->setTimezone(new DateTimeZone($timezone)); ?>
                    <article class="public-match-card">
                        <div class="public-match-meta"><span><?= yuc_e($row['stage']) ?></span><time datetime="<?= yuc_e($kickoff->format('c')) ?>"><?= yuc_e($kickoff->format('D, j M Y · H:i T')) ?></time></div>
                        <div class="public-match-teams">
                            <a href="/team?id=<?= (int) $row['home_team_id'] ?>"><small><?= yuc_e($row['home_zone']) ?></small><strong><?= yuc_e($row['home_team']) ?></strong></a>
                            <span class="match-vs">VS</span>
                            <a href="/team?id=<?= (int) $row['away_team_id'] ?>"><small><?= yuc_e($row['away_zone']) ?></small><strong><?= yuc_e($row['away_team']) ?></strong></a>
                        </div>
                        <div class="public-match-footer"><span>⌖ <?= yuc_e($row['venue_name'] ?? 'Venue to be confirmed') ?><?= !empty($row['venue_zone']) ? ' · ' . yuc_e($row['venue_zone']) : '' ?></span><span class="outcome-badge outcome-<?= yuc_e($row['status']) ?>"><?= yuc_e(strtoupper((string) $row['status'])) ?></span></div>
                    </article>
                <?php endforeach; ?></div>
            <?php endif; ?>
        <?php elseif ($resource === 'results'): ?>
            <div class="panel-topline"><div><div class="panel-kicker">VERIFIED SCORES</div><h2>Recent results</h2></div><span class="readiness-pill is-ready"><span></span><?= count($rows) ?> RESULTS</span></div>
            <?php if ($rows === []): ?><div class="public-empty"><span>◎</span><strong>No results have been published yet.</strong><p>Official scores will appear here after each match.</p></div><?php else: ?>
                <div class="result-match-list">
                    <?php foreach ($rows as $row):
                        $resultDate = (new DateTimeImmutable((string) $row['kickoff_at'], new DateTimeZone('UTC')))->setTimezone(new DateTimeZone($timezone));
                        $homeScore = (int) $row['home_score'];
                        $awayScore = (int) $row['away_score'];
                        $homeResult = $homeScore > $awayScore ? 'is-winner' : ($homeScore === $awayScore ? 'is-draw' : '');
                        $awayResult = $awayScore > $homeScore ? 'is-winner' : ($homeScore === $awayScore ? 'is-draw' : '');
                    ?>
                        <article class="result-match-card">
                            <header class="result-match-meta"><span><?= yuc_e($row['stage']) ?></span><time datetime="<?= yuc_e($resultDate->format('c')) ?>"><?= yuc_e($resultDate->format('D, j M Y · H:i T')) ?></time></header>
                            <div class="result-match-body">
                                <a class="result-team <?= $homeResult ?>" href="/team?id=<?= (int) $row['home_team_id'] ?>"><small><?= yuc_e($row['home_zone']) ?></small><strong><?= yuc_e($row['home_team']) ?></strong></a>
                                <div class="result-score-board"><strong><?= $homeScore ?><span>:</span><?= $awayScore ?></strong><small>FULL TIME</small></div>
                                <a class="result-team <?= $awayResult ?>" href="/team?id=<?= (int) $row['away_team_id'] ?>"><small><?= yuc_e($row['away_zone']) ?></small><strong><?= yuc_e($row['away_team']) ?></strong></a>
                            </div>
                            <footer class="result-match-footer"><span>⌖ <?= yuc_e($row['venue_name'] ?? 'Venue not recorded') ?><?= !empty($row['venue_zone']) ? ' · ' . yuc_e($row['venue_zone']) : '' ?></span><span class="result-verified">✓ SCORE VERIFIED</span></footer>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        <?php elseif ($resource === 'teams'): ?>
            <div class="panel-topline"><div><div class="panel-kicker">GROUP STAGE · LIVE TABLES</div><h2>Teams &amp; standings</h2></div><span class="readiness-pill is-ready"><span></span><?= count($rows) ?> TEAMS</span></div>
            <p class="panel-intro">Select a team to open its full squad profile. Standings update from completed, published results.</p>
            <?php if ($rows === []): ?><div class="public-empty"><span>♟</span><strong>Team entries will be published after verification.</strong><p>Check back as the tournament draw approaches.</p></div><?php else: ?>
                <div class="group-standings-grid">
                    <?php foreach ($teamGroups as $group): ?>
                        <section class="group-standings-card" aria-label="Group <?= yuc_e($group['name']) ?> standings">
                            <header class="group-standings-heading"><div><span class="group-overline">GROUP STAGE</span><h3><?= $group['name'] === 'Unassigned' ? 'Teams' : 'Group ' . yuc_e($group['name']) ?></h3></div><span class="group-team-count"><?= count($group['teams']) ?> CLUBS</span></header>
                            <div class="responsive-table group-table-wrap">
                                <table class="data-table standings-table">
                                    <thead><tr><th scope="col">#</th><th scope="col">Team</th><th scope="col">P</th><th scope="col">W</th><th scope="col">D</th><th scope="col">L</th><th scope="col">GD</th><th scope="col">Pts</th></tr></thead>
                                    <tbody>
                                    <?php foreach ($group['teams'] as $rank => $team): ?>
                                        <tr class="<?= $rank === 0 ? 'is-top-spot' : '' ?>">
                                            <td class="standings-rank"><?= $rank + 1 ?></td>
                                            <td><a class="standings-team-link" href="/team?id=<?= (int) $team['id'] ?>"><span class="standings-crest" aria-hidden="true">Y</span><span class="standings-team-copy"><strong><?= yuc_e($team['name']) ?></strong><small><?= yuc_e($team['zone']) ?> · <?= (int) $team['player_count'] ?> players</small></span></a></td>
                                            <td><?= (int) $team['played'] ?></td><td><?= (int) $team['won'] ?></td><td><?= (int) $team['drawn'] ?></td><td><?= (int) $team['lost'] ?></td><td class="goal-difference <?= (int) $team['goal_difference'] > 0 ? 'is-positive' : ((int) $team['goal_difference'] < 0 ? 'is-negative' : '') ?>"><?= (int) $team['goal_difference'] > 0 ? '+' : '' ?><?= (int) $team['goal_difference'] ?></td><td class="standings-points"><?= (int) $team['points'] ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </section>
                    <?php endforeach; ?>
                </div>
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
