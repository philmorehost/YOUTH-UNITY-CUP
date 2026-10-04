<?php
/** @var array<string,mixed> $team */
/** @var list<array<string,mixed>> $players */
/** @var string $contactEmail */
$players = is_array($players ?? null) ? $players : [];
$teamGroup = trim((string) ($team['group_name'] ?? ''));
$appTimezone = (string) ($appTimezone ?? 'Africa/Lagos');
?>
<section class="public-content-wrap team-profile-wrap">
    <div class="team-profile-hero">
        <div class="team-profile-art" aria-hidden="true"><span class="team-profile-ring"></span><span class="team-profile-crest">Y</span></div>
        <div class="team-profile-copy">
            <a class="back-to-teams" href="/teams">← All teams &amp; groups</a>
            <div class="eyebrow"><span class="eyebrow-line"></span>OFFICIAL TEAM PROFILE</div>
            <h1><?= yuc_e($team['name']) ?><em>.</em></h1>
            <p><?= yuc_e($team['zone']) ?><?= $teamGroup !== '' ? ' · GROUP ' . yuc_e($teamGroup) : '' ?> · <?= (int) ($team['player_count'] ?? 0) ?> active squad members</p>
        </div>
        <div class="team-profile-season"><strong><?= yuc_e(yuc_current_year($appTimezone)) ?></strong><span>YOUTH UNITY CUP</span></div>
    </div>

    <nav class="public-section-nav" aria-label="Tournament pages">
        <a href="/">Home</a><a href="/teams" class="active">Teams &amp; groups</a><a href="/fixtures">Fixtures</a><a href="/results">Results</a><a href="/venues">Venues</a><a href="/registration">Registration</a><a href="/shop">Official shop</a>
    </nav>

    <section class="team-squad-section" aria-labelledby="squad-heading">
        <div class="team-squad-heading"><div><div class="panel-kicker">THE PLAYERS</div><h2 id="squad-heading">Meet the <em>squad.</em></h2><p>Player profiles and squad numbers for <?= yuc_e($team['name']) ?>.</p></div><span class="squad-count"><strong><?= count($players) ?></strong><small>ACTIVE PLAYERS</small></span></div>
        <?php if ($players === []): ?>
            <div class="public-empty squad-empty"><span>♟</span><strong>The roster is being prepared.</strong><p>Player profiles will appear here once the team administrator adds the squad.</p><a class="button button-primary" href="/registration">Register your interest <span aria-hidden="true">→</span></a></div>
        <?php else: ?>
            <div class="player-roster-grid">
                <?php foreach ($players as $player):
                    $variant = max(1, min(8, (int) ($player['avatar_variant'] ?? 1)));
                    $photoUrl = trim((string) ($player['photo_url'] ?? ''));
                    if (!str_starts_with(strtolower($photoUrl), 'https://') || filter_var($photoUrl, FILTER_VALIDATE_URL) === false) {
                        $photoUrl = '';
                    }
                ?>
                    <article class="player-roster-card">
                        <div class="player-roster-photo<?= $photoUrl === '' ? ' has-illustration' : '' ?>">
                            <?php if ($photoUrl !== ''): ?><img src="<?= yuc_e($photoUrl) ?>" alt="Headshot of <?= yuc_e($player['full_name']) ?>" loading="lazy" referrerpolicy="no-referrer">
                            <?php else: ?><?= yuc_player_avatar($variant) ?><?php endif; ?>
                            <span class="player-jersey">#<?= $player['jersey_number'] !== null ? (int) $player['jersey_number'] : '—' ?></span>
                        </div>
                        <div class="player-roster-copy">
                            <span class="player-position"><?= yuc_e($player['position']) ?></span>
                            <h3><?= yuc_e($player['full_name']) ?></h3>
                            <div class="player-details">
                                <?php if ($player['age'] !== null): ?><span><?= (int) $player['age'] ?> yrs</span><?php endif; ?>
                                <?php if (trim((string) $player['hometown']) !== ''): ?><span><?= yuc_e($player['hometown']) ?></span><?php endif; ?>
                            </div>
                            <?php if (trim((string) $player['bio']) !== ''): ?><p><?= yuc_e($player['bio']) ?></p><?php endif; ?>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>

    <?php if ($contactEmail !== ''): ?><p class="public-contact">Team profile enquiries? Contact <a href="mailto:<?= yuc_e($contactEmail) ?>"><?= yuc_e($contactEmail) ?></a>.</p><?php endif; ?>
    <footer class="public-page-footer"><a href="/teams">← Back to group standings</a><span><?= yuc_e($team['zone']) ?> · Mushin, Lagos</span><a href="/fixtures">See upcoming fixtures →</a></footer>
</section>
