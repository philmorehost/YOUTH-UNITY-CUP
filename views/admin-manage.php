<?php
/** @var array<string,mixed> $admin */
/** @var string $resource */
/** @var list<array<string,mixed>> $rows */
/** @var array<string,mixed> $formValues */
/** @var list<array<string,mixed>> $teams */
/** @var list<array<string,mixed>> $venues */
/** @var array<string,string> $settings */
/** @var array<string,mixed> $mail */
/** @var array{type:string,message:string}|null $flash */
$resourceTitles = [
    'teams' => 'Teams', 'players' => 'Player profiles', 'venues' => 'Venues', 'fixtures' => 'Fixtures & scores',
    'registrations' => 'Registrations', 'transactions' => 'Transactions', 'products' => 'Shop products', 'orders' => 'Shop orders', 'settings' => 'Site & security settings', 'homepage-hero' => 'Homepage hero', 'live-stream' => 'Watch live', 'security' => 'Blocked IP access', 'activity' => 'Audit activity',
];
$title = $resourceTitles[$resource] ?? 'Admin';
$formValues = is_array($formValues ?? null) ? $formValues : [];
$settings = is_array($settings ?? null) ? $settings : [];
$heroSettings = is_array($heroSettings ?? null) ? $heroSettings : [];
$liveStreamSettings = is_array($liveStreamSettings ?? null) ? $liveStreamSettings : [];
$mail = is_array($mail ?? null) ? $mail : [];
$auditTotal = (int) ($auditTotal ?? 0);
$auditPage = max(1, (int) ($auditPage ?? 1));
$auditPages = max(1, (int) ($auditPages ?? 1));
$auditSearch = (string) ($auditSearch ?? '');
$auditCategory = (string) ($auditCategory ?? '');
$showArchived = !empty($showArchived);
$payHubConfigured = !empty($payHubConfigured);
$payHubSecretConfigured = !empty($payHubSecretConfigured ?? $payHubConfigured);
$payHubPublicConfigured = !empty($payHubPublicConfigured ?? $payHubConfigured);
$siteMode = ($siteMode ?? 'production') === 'demo' ? 'demo' : 'production';
$orderProducts = is_array($orderProducts ?? null) ? $orderProducts : [];
$liveSearchLabels = [
    'teams' => 'teams', 'players' => 'player profiles', 'venues' => 'venues', 'fixtures' => 'fixtures and scores',
    'registrations' => 'registrations', 'transactions' => 'transactions', 'products' => 'shop products',
    'orders' => 'shop orders', 'security' => 'blocked IPs',
];
$liveSearchEnabled = array_key_exists($resource, $liveSearchLabels);
$liveSearchLabel = $liveSearchLabels[$resource] ?? '';
$timezone = (string) ($appTimezone ?? 'UTC');
if (!in_array($timezone, DateTimeZone::listIdentifiers(), true)) {
    $timezone = 'UTC';
}
$teams = is_array($teams ?? null) ? $teams : [];
$venues = is_array($venues ?? null) ? $venues : [];
?>
<section class="ops-wrap">
    <div class="ops-header">
        <div>
            <div class="eyebrow"><span class="eyebrow-line"></span>TOURNAMENT ADMINISTRATION</div>
            <h1><?= yuc_e($title) ?><em>.</em></h1>
            <p class="lede">Manage live Youth Unity Cup information. Changes are stored in MySQL and recorded in the audit log.</p>
        </div>
        <div class="ops-header-actions"><a class="button button-outline" href="/admin">← Dashboard</a><a class="button button-quiet" href="/" target="_blank" rel="noopener noreferrer">Public site ↗</a></div>
    </div>

    <nav class="ops-nav" aria-label="Admin sections">
        <a href="/admin/teams" class="<?= $resource === 'teams' ? 'active' : '' ?>">Teams</a>
        <a href="/admin/players" class="<?= $resource === 'players' ? 'active' : '' ?>">Players</a>
        <a href="/admin/venues" class="<?= $resource === 'venues' ? 'active' : '' ?>">Venues</a>
        <a href="/admin/fixtures" class="<?= $resource === 'fixtures' ? 'active' : '' ?>">Fixtures &amp; scores</a>
        <a href="/admin/registrations" class="<?= $resource === 'registrations' ? 'active' : '' ?>">Registrations</a>
        <a href="/admin/transactions" class="<?= $resource === 'transactions' ? 'active' : '' ?>">Transactions</a>
        <a href="/admin/products" class="<?= $resource === 'products' ? 'active' : '' ?>">Shop products</a>
        <a href="/admin/orders" class="<?= $resource === 'orders' ? 'active' : '' ?>">Shop orders</a>
        <a href="/admin/homepage-hero" class="<?= $resource === 'homepage-hero' ? 'active' : '' ?>">Homepage hero</a>
        <a href="/admin/live-stream" class="<?= $resource === 'live-stream' ? 'active' : '' ?>">Watch live</a>
        <a href="/admin/settings" class="<?= $resource === 'settings' ? 'active' : '' ?>">Settings</a>
        <a href="/admin/security" class="<?= $resource === 'security' ? 'active' : '' ?>">Blocked IPs</a>
        <a href="/admin/activity" class="<?= $resource === 'activity' ? 'active' : '' ?>">Audit activity</a>
    </nav>

    <?php if (is_array($flash)): ?>
        <div class="alert alert-<?= yuc_e($flash['type']) ?>" role="status" aria-live="polite"><span class="alert-icon" aria-hidden="true"><?= $flash['type'] === 'error' ? '!' : '✓' ?></span><p><?= yuc_e($flash['message']) ?></p></div>
    <?php endif; ?>

    <?php if ($siteMode === 'demo'): ?>
        <aside class="demo-admin-note" role="status"><strong>DEMO MODE — SAMPLE DATA ONLY</strong><span>Tournament edits affect demo data only. Registrations, payment records, shop, settings, and security operations are read-only. <a href="/admin">Use the dashboard to switch back to Production.</a></span></aside>
    <?php endif; ?>

    <?php if ($liveSearchEnabled): ?>
        <section class="admin-live-search" data-live-search>
            <div class="admin-live-search-copy"><label for="admin-live-search-input">Search <?= yuc_e($liveSearchLabel) ?></label><p>Results update as you type — no page reload.</p></div>
            <div class="admin-live-search-control"><span class="admin-search-icon" aria-hidden="true">⌕</span><input id="admin-live-search-input" type="search" data-live-search-input maxlength="120" autocomplete="off" placeholder="Search by name, reference, contact, status…"><span class="admin-search-count" data-live-search-count aria-live="polite"><?= count($rows) ?> records</span></div>
        </section>
    <?php endif; ?>

    <?php if ($resource === 'teams'): ?>
        <div class="ops-grid">
            <section class="panel ops-form-panel">
                <div class="panel-kicker">TEAM DIRECTORY</div><h2><?= isset($formValues['id']) ? 'Edit team' : 'Add a team' ?></h2>
                <p class="panel-intro">Set the zone and optional group assigned after the draw.</p>
                <form method="post" action="/admin/teams/save" class="form-stack">
                    <?= yuc_csrf_field() ?><input type="hidden" name="id" value="<?= yuc_e($formValues['id'] ?? '') ?>">
                    <div class="field-group"><label for="team-name">Team name</label><input id="team-name" name="name" required maxlength="120" value="<?= yuc_e($formValues['name'] ?? '') ?>"></div>
                    <div class="form-grid"><div class="field-group"><label for="team-zone">Zone</label><input id="team-zone" name="zone" required maxlength="50" placeholder="Zone 1" value="<?= yuc_e($formValues['zone'] ?? '') ?>"></div><div class="field-group"><label for="team-group">Group</label><input id="team-group" name="group_name" maxlength="20" placeholder="A" value="<?= yuc_e($formValues['group_name'] ?? '') ?>"></div></div>
                    <div class="field-group"><label for="team-email">Contact email</label><input id="team-email" name="contact_email" type="email" maxlength="190" value="<?= yuc_e($formValues['contact_email'] ?? '') ?>"></div>
                    <div class="field-group"><label for="team-status">Status</label><select id="team-status" name="status"><option value="active" <?= ($formValues['status'] ?? 'active') === 'active' ? 'selected' : '' ?>>Active</option><option value="inactive" <?= ($formValues['status'] ?? '') === 'inactive' ? 'selected' : '' ?>>Inactive</option></select></div>
                    <div class="field-group"><label for="team-notes">Notes</label><textarea id="team-notes" name="notes" rows="3" maxlength="500"><?= yuc_e($formValues['notes'] ?? '') ?></textarea></div>
                    <div class="form-actions"><a class="button button-quiet" href="/admin/teams">Clear</a><button class="button button-primary" type="submit">Save team</button></div>
                </form>
            </section>
            <section class="panel ops-table-panel"><div class="panel-topline"><div><div class="panel-kicker">CURRENT RECORDS</div><h2>Registered teams</h2></div><span class="readiness-pill is-ready"><span></span><?= count($rows) ?> TEAMS</span></div>
                <div class="responsive-table"><table class="data-table ops-table"><thead><tr><th>Team</th><th>Zone / group</th><th>Status</th><th>Manage</th></tr></thead><tbody>
                <?php if ($rows === []): ?><tr><td colspan="4" class="empty-state">No teams yet. Add the first team using the form.</td></tr><?php else: foreach ($rows as $row): ?>
                    <tr data-search-text="<?= yuc_e($row['notes'] ?? '') ?>"><td><strong><?= yuc_e($row['name']) ?></strong><small><?= yuc_e($row['contact_email']) ?></small></td><td><?= yuc_e($row['zone']) ?><?= !empty($row['group_name']) ? ' · ' . yuc_e($row['group_name']) : '' ?></td><td><span class="outcome-badge outcome-<?= yuc_e($row['status']) ?>"><?= yuc_e(strtoupper((string) $row['status'])) ?></span></td><td class="table-actions"><a class="table-action" href="/admin/teams?edit=<?= (int) $row['id'] ?>">Edit</a><form method="post" action="/admin/teams/delete" data-confirm="Delete this team? Teams used in fixtures cannot be deleted; set them inactive instead."><?= yuc_csrf_field() ?><input type="hidden" name="id" value="<?= (int) $row['id'] ?>"><button class="table-action table-action-danger" type="submit">Delete</button></form></td></tr>
                <?php endforeach; endif; ?></tbody></table></div>
            </section>
        </div>

    <?php elseif ($resource === 'players'): ?>
        <div class="ops-grid player-admin-grid">
            <section class="panel ops-form-panel"><div class="panel-kicker">TEAM SQUADS</div><h2><?= isset($formValues['id']) ? 'Edit player profile' : 'Add a player profile' ?></h2><p class="panel-intro">Manage public squad cards. Use an HTTPS image URL for a real headshot, or choose a local illustrated portrait.</p>
                <form method="post" action="/admin/players/save" class="form-stack">
                    <?= yuc_csrf_field() ?><input type="hidden" name="id" value="<?= yuc_e($formValues['id'] ?? '0') ?>">
                    <div class="field-group"><label for="player-team">Team</label><select id="player-team" name="team_id" required><option value="">Choose team</option><?php foreach ($teams as $team): ?><option value="<?= (int) $team['id'] ?>" <?= (string) ($formValues['team_id'] ?? '') === (string) $team['id'] ? 'selected' : '' ?>><?= yuc_e($team['name']) ?> · <?= yuc_e($team['zone']) ?></option><?php endforeach; ?></select></div>
                    <div class="field-group"><label for="player-name">Full name</label><input id="player-name" name="full_name" required maxlength="120" value="<?= yuc_e($formValues['full_name'] ?? '') ?>"></div>
                    <div class="form-grid"><div class="field-group"><label for="player-position">Playing position</label><input id="player-position" name="position" required maxlength="40" placeholder="Midfielder" value="<?= yuc_e($formValues['position'] ?? '') ?>"></div><div class="field-group"><label for="player-number">Squad number</label><input id="player-number" name="jersey_number" type="number" min="1" max="99" value="<?= yuc_e($formValues['jersey_number'] ?? '') ?>"></div></div>
                    <div class="form-grid"><div class="field-group"><label for="player-age">Age</label><input id="player-age" name="age" type="number" min="10" max="19" value="<?= yuc_e($formValues['age'] ?? '') ?>"></div><div class="field-group"><label for="player-hometown">Hometown / zone</label><input id="player-hometown" name="hometown" maxlength="80" value="<?= yuc_e($formValues['hometown'] ?? '') ?>"></div></div>
                    <div class="field-group"><label for="player-photo-url">Headshot URL <span>(optional, HTTPS only)</span></label><input id="player-photo-url" name="photo_url" type="url" maxlength="500" placeholder="https://example.com/player.jpg" value="<?= yuc_e($formValues['photo_url'] ?? '') ?>"><small>Leave blank to use a self-hosted illustrated portrait.</small></div>
                    <div class="form-grid"><div class="field-group"><label for="player-avatar">Illustrated portrait</label><select id="player-avatar" name="avatar_variant"><?php for ($avatar = 1; $avatar <= 8; $avatar++): ?><option value="<?= $avatar ?>" <?= (int) ($formValues['avatar_variant'] ?? 1) === $avatar ? 'selected' : '' ?>>Portrait palette <?= $avatar ?></option><?php endfor; ?></select></div><div class="field-group"><label for="player-status">Roster status</label><select id="player-status" name="status"><option value="active" <?= ($formValues['status'] ?? 'active') === 'active' ? 'selected' : '' ?>>Active</option><option value="inactive" <?= ($formValues['status'] ?? '') === 'inactive' ? 'selected' : '' ?>>Inactive</option></select></div></div>
                    <div class="field-group"><label for="player-bio">Player details</label><textarea id="player-bio" name="bio" rows="3" maxlength="350" placeholder="A brief playing style or profile note."><?= yuc_e($formValues['bio'] ?? '') ?></textarea></div>
                    <div class="form-actions"><a class="button button-quiet" href="/admin/players">Clear</a><button class="button button-primary" type="submit" <?= $teams === [] ? 'disabled' : '' ?>>Save player</button></div>
                </form>
            </section>
            <section class="panel ops-table-panel"><div class="panel-topline"><div><div class="panel-kicker">SQUAD DIRECTORY</div><h2>Player profiles</h2></div><span class="readiness-pill is-ready"><span></span><?= count($rows) ?> PLAYERS</span></div>
                <div class="responsive-table"><table class="data-table ops-table"><thead><tr><th>Player</th><th>Team</th><th>Role / age</th><th>Status</th><th>Manage</th></tr></thead><tbody>
                <?php if ($rows === []): ?><tr><td colspan="5" class="empty-state">No player profiles yet. Choose a team and add its first squad member.</td></tr><?php else: foreach ($rows as $row): ?><tr data-search-text="<?= yuc_e($row['bio'] . ' ' . $row['hometown']) ?>"><td><span class="admin-player-cell"><span class="admin-player-avatar"><?= yuc_player_avatar((int) $row['avatar_variant']) ?></span><span><strong><?= yuc_e($row['full_name']) ?></strong><small><?= $row['jersey_number'] !== null ? '#' . (int) $row['jersey_number'] : 'No squad number' ?><?= !empty($row['photo_url']) ? ' · headshot URL' : '' ?></small></span></span></td><td><strong><?= yuc_e($row['team_name']) ?></strong><small><?= yuc_e($row['team_zone']) ?></small></td><td><?= yuc_e($row['position']) ?><?= $row['age'] !== null ? ' · ' . (int) $row['age'] . ' yrs' : '' ?></td><td><span class="outcome-badge outcome-<?= yuc_e($row['status']) ?>"><?= yuc_e(strtoupper((string) $row['status'])) ?></span></td><td class="table-actions"><a class="table-action" href="/admin/players?edit=<?= (int) $row['id'] ?>">Edit</a><form method="post" action="/admin/players/delete" data-confirm="Delete this player profile from the public team roster?"><?= yuc_csrf_field() ?><input type="hidden" name="id" value="<?= (int) $row['id'] ?>"><button class="table-action table-action-danger" type="submit">Delete</button></form></td></tr><?php endforeach; endif; ?></tbody></table></div>
            </section>
        </div>

    <?php elseif ($resource === 'venues'): ?>
        <div class="ops-grid">
            <section class="panel ops-form-panel"><div class="panel-kicker">MATCH-DAY LOCATIONS</div><h2><?= isset($formValues['id']) ? 'Edit venue' : 'Add a venue' ?></h2><p class="panel-intro">Keep the venue name, zone, address, and safe capacity current.</p>
                <form method="post" action="/admin/venues/save" class="form-stack">
                    <?= yuc_csrf_field() ?><input type="hidden" name="id" value="<?= yuc_e($formValues['id'] ?? '') ?>">
                    <div class="field-group"><label for="venue-name">Venue name</label><input id="venue-name" name="name" required maxlength="140" value="<?= yuc_e($formValues['name'] ?? '') ?>"></div>
                    <div class="form-grid"><div class="field-group"><label for="venue-zone">Zone</label><input id="venue-zone" name="zone" required maxlength="50" placeholder="Zone 1" value="<?= yuc_e($formValues['zone'] ?? '') ?>"></div><div class="field-group"><label for="venue-capacity">Safe capacity</label><input id="venue-capacity" name="capacity" type="number" min="1" max="50000" value="<?= yuc_e($formValues['capacity'] ?? '700') ?>"></div></div>
                    <div class="field-group"><label for="venue-address">Address / directions</label><input id="venue-address" name="address" maxlength="255" value="<?= yuc_e($formValues['address'] ?? '') ?>"></div>
                    <div class="field-group"><label for="venue-status">Status</label><select id="venue-status" name="status"><option value="active" <?= ($formValues['status'] ?? 'active') === 'active' ? 'selected' : '' ?>>Active</option><option value="inactive" <?= ($formValues['status'] ?? '') === 'inactive' ? 'selected' : '' ?>>Inactive</option></select></div>
                    <div class="form-actions"><a class="button button-quiet" href="/admin/venues">Clear</a><button class="button button-primary" type="submit">Save venue</button></div>
                </form>
            </section>
            <section class="panel ops-table-panel"><div class="panel-topline"><div><div class="panel-kicker">CURRENT RECORDS</div><h2>Match venues</h2></div><span class="readiness-pill is-ready"><span></span><?= count($rows) ?> VENUES</span></div>
                <div class="responsive-table"><table class="data-table ops-table"><thead><tr><th>Venue</th><th>Zone / capacity</th><th>Status</th><th></th></tr></thead><tbody>
                <?php if ($rows === []): ?><tr><td colspan="4" class="empty-state">No venues yet. Add the first location.</td></tr><?php else: foreach ($rows as $row): ?>
                    <tr><td><strong><?= yuc_e($row['name']) ?></strong><small><?= yuc_e($row['address']) ?></small></td><td><?= yuc_e($row['zone']) ?> · <?= (int) $row['capacity'] ?></td><td><span class="outcome-badge outcome-<?= yuc_e($row['status']) ?>"><?= yuc_e(strtoupper((string) $row['status'])) ?></span></td><td class="table-actions"><a class="table-action" href="/admin/venues?edit=<?= (int) $row['id'] ?>">Edit</a><form method="post" action="/admin/venues/delete" data-confirm="Delete this venue? Venues used in fixtures cannot be deleted; set them inactive instead."><?= yuc_csrf_field() ?><input type="hidden" name="id" value="<?= (int) $row['id'] ?>"><button class="table-action table-action-danger" type="submit">Delete</button></form></td></tr>
                <?php endforeach; endif; ?></tbody></table></div>
            </section>
        </div>

    <?php elseif ($resource === 'fixtures'): ?>
        <div class="ops-grid">
            <section class="panel ops-form-panel"><div class="panel-kicker">MATCH SCHEDULE</div><h2><?= isset($formValues['id']) ? 'Edit fixture' : 'Schedule a fixture' ?></h2><p class="panel-intro">Scores are required when a fixture is marked complete. Time is entered in the configured site timezone.</p>
                <form method="post" action="/admin/fixtures/save" class="form-stack">
                    <?= yuc_csrf_field() ?><input type="hidden" name="id" value="<?= yuc_e($formValues['id'] ?? '') ?>">
                    <div class="form-grid"><div class="field-group"><label for="home-team">Home team</label><select id="home-team" name="home_team_id" required><option value="">Choose team</option><?php foreach ($teams as $team): ?><option value="<?= (int) $team['id'] ?>" <?= (string) ($formValues['home_team_id'] ?? '') === (string) $team['id'] ? 'selected' : '' ?>><?= yuc_e($team['name']) ?> · <?= yuc_e($team['zone']) ?></option><?php endforeach; ?></select></div><div class="field-group"><label for="away-team">Away team</label><select id="away-team" name="away_team_id" required><option value="">Choose team</option><?php foreach ($teams as $team): ?><option value="<?= (int) $team['id'] ?>" <?= (string) ($formValues['away_team_id'] ?? '') === (string) $team['id'] ? 'selected' : '' ?>><?= yuc_e($team['name']) ?> · <?= yuc_e($team['zone']) ?></option><?php endforeach; ?></select></div></div>
                    <div class="field-group"><label for="fixture-venue">Venue</label><select id="fixture-venue" name="venue_id"><option value="">Venue to be confirmed</option><?php foreach ($venues as $venue): ?><option value="<?= (int) $venue['id'] ?>" <?= (string) ($formValues['venue_id'] ?? '') === (string) $venue['id'] ? 'selected' : '' ?>><?= yuc_e($venue['name']) ?> · <?= yuc_e($venue['zone']) ?></option><?php endforeach; ?></select></div>
                    <div class="form-grid"><div class="field-group"><label for="fixture-stage">Round / stage</label><input id="fixture-stage" name="stage" required maxlength="60" value="<?= yuc_e($formValues['stage'] ?? 'Group stage') ?>"></div><div class="field-group"><label for="fixture-time">Kick-off</label><input id="fixture-time" name="kickoff_at" type="datetime-local" required value="<?= yuc_e($formValues['kickoff_at'] ?? '') ?>"></div></div>
                    <div class="form-grid"><div class="field-group"><label for="fixture-status">Status</label><select id="fixture-status" name="status"><?php foreach (['scheduled','live','completed','postponed','cancelled'] as $status): ?><option value="<?= yuc_e($status) ?>" <?= ($formValues['status'] ?? 'scheduled') === $status ? 'selected' : '' ?>><?= yuc_e(ucfirst($status)) ?></option><?php endforeach; ?></select></div><div class="form-grid score-grid"><div class="field-group"><label for="home-score">Home score</label><input id="home-score" name="home_score" type="number" min="0" max="99" value="<?= yuc_e($formValues['home_score'] ?? '') ?>"></div><div class="field-group"><label for="away-score">Away score</label><input id="away-score" name="away_score" type="number" min="0" max="99" value="<?= yuc_e($formValues['away_score'] ?? '') ?>"></div></div></div>
                    <div class="field-group"><label for="fixture-notes">Notes</label><input id="fixture-notes" name="notes" maxlength="500" value="<?= yuc_e($formValues['notes'] ?? '') ?>"></div>
                    <div class="form-actions"><a class="button button-quiet" href="/admin/fixtures">Clear</a><button class="button button-primary" type="submit">Save fixture</button></div>
                </form>
            </section>
            <section class="panel ops-table-panel"><div class="panel-topline"><div><div class="panel-kicker">CURRENT RECORDS</div><h2>Fixture list</h2></div><span class="readiness-pill is-ready"><span></span><?= count($rows) ?> FIXTURES</span></div>
                <div class="responsive-table"><table class="data-table ops-table"><thead><tr><th>Match</th><th>Kick-off</th><th>Venue / round</th><th>Score / status</th><th></th></tr></thead><tbody>
                <?php if ($rows === []): ?><tr><td colspan="5" class="empty-state">No fixtures scheduled yet.</td></tr><?php else: foreach ($rows as $row): ?>
                    <tr data-search-text="<?= yuc_e($row['notes'] ?? '') ?>"><td><strong><?= yuc_e($row['home_team']) ?> vs <?= yuc_e($row['away_team']) ?></strong></td><td><?= yuc_e((new DateTimeImmutable((string) $row['kickoff_at'], new DateTimeZone('UTC')))->setTimezone(new DateTimeZone($timezone))->format('D, j M Y · H:i T')) ?></td><td><?= yuc_e($row['venue_name'] ?? 'TBC') ?><small><?= yuc_e($row['stage']) ?></small></td><td><?= $row['home_score'] === null ? '–' : (int) $row['home_score'] ?> : <?= $row['away_score'] === null ? '–' : (int) $row['away_score'] ?><small><?= yuc_e($row['status']) ?></small></td><td class="table-actions"><a class="table-action" href="/admin/fixtures?edit=<?= (int) $row['id'] ?>">Edit</a><form method="post" action="/admin/fixtures/delete" data-confirm="Permanently delete this fixture and its score? This cannot be undone."><?= yuc_csrf_field() ?><input type="hidden" name="id" value="<?= (int) $row['id'] ?>"><button class="table-action table-action-danger" type="submit">Delete</button></form></td></tr>
                <?php endforeach; endif; ?></tbody></table></div>
            </section>
        </div>

    <?php elseif ($resource === 'registrations'): ?>
        <div class="ops-grid registration-admin-grid">
            <section class="panel ops-form-panel"><div class="panel-kicker">PUBLIC APPLICATIONS</div><h2><?= isset($formValues['id']) ? 'Edit registration' : 'Add registration' ?></h2><p class="panel-intro">Create or update an applicant record. Player applications require an age under 19.</p>
                <form method="post" action="/admin/registrations/save" class="form-stack">
                    <?= yuc_csrf_field() ?><input type="hidden" name="id" value="<?= yuc_e($formValues['id'] ?? '0') ?>">
                    <?php if (!empty($formValues['reference'])): ?><div class="registration-reference-note">Reference: <strong><?= yuc_e($formValues['reference']) ?></strong></div><?php endif; ?>
                    <div class="field-group"><label for="registration-name">Full name</label><input id="registration-name" name="full_name" required maxlength="140" value="<?= yuc_e($formValues['full_name'] ?? '') ?>"></div>
                    <div class="field-group"><label for="registration-email">Email</label><input id="registration-email" name="email" type="email" required maxlength="190" value="<?= yuc_e($formValues['email'] ?? '') ?>"></div>
                    <div class="form-grid"><div class="field-group"><label for="registration-phone">Phone</label><input id="registration-phone" name="phone" maxlength="40" value="<?= yuc_e($formValues['phone'] ?? '') ?>"></div><div class="field-group"><label for="registration-category">Category</label><select id="registration-category" name="category" required><?php foreach (['player'=>'Player','team_official'=>'Team official','vendor'=>'Vendor','volunteer'=>'Volunteer','community'=>'Community'] as $value=>$label): ?><option value="<?= yuc_e($value) ?>" <?= ($formValues['category'] ?? 'player') === $value ? 'selected' : '' ?>><?= yuc_e($label) ?></option><?php endforeach; ?></select></div></div>
                    <div class="form-grid"><div class="field-group"><label for="registration-age">Age</label><input id="registration-age" name="age" type="number" min="10" max="99" value="<?= yuc_e($formValues['age'] ?? '') ?>"></div><div class="field-group"><label for="registration-zone">Zone</label><input id="registration-zone" name="zone" maxlength="50" value="<?= yuc_e($formValues['zone'] ?? '') ?>"></div></div>
                    <div class="field-group"><label for="registration-team">Team name (if applicable)</label><input id="registration-team" name="team_name" maxlength="120" value="<?= yuc_e($formValues['team_name'] ?? '') ?>"></div>
                    <div class="field-group"><label for="registration-details">Application details</label><textarea id="registration-details" name="details" rows="3" maxlength="2000"><?= yuc_e($formValues['details'] ?? '') ?></textarea></div>
                    <div class="field-group"><label for="registration-status">Review status</label><select id="registration-status" name="status"><?php foreach (['new','reviewing','approved','rejected'] as $status): ?><option value="<?= yuc_e($status) ?>" <?= ($formValues['status'] ?? 'new') === $status ? 'selected' : '' ?>><?= yuc_e(ucfirst($status)) ?></option><?php endforeach; ?></select></div>
                    <div class="form-actions"><a class="button button-quiet" href="/admin/registrations">Clear</a><button class="button button-primary" type="submit">Save registration</button></div>
                </form>
            </section>
            <section class="panel ops-table-panel full-ops-panel"><div class="panel-topline"><div><div class="panel-kicker">PUBLIC APPLICATIONS</div><h2>Registration inbox</h2></div><span class="readiness-pill is-ready"><span></span><?= count($rows) ?> SUBMISSIONS</span></div>
                <div class="responsive-table"><table class="data-table ops-table"><thead><tr><th>Applicant</th><th>Category / team</th><th>Contact</th><th>Submitted</th><th>Review status</th><th>Manage</th></tr></thead><tbody>
                <?php if ($rows === []): ?><tr><td colspan="6" class="empty-state">No registrations have been submitted.</td></tr><?php else: foreach ($rows as $row): ?>
                    <tr data-search-text="<?= yuc_e($row['details'] ?? '') ?>"><td><strong><?= yuc_e($row['full_name']) ?></strong><small><?= yuc_e($row['reference']) ?><?= $row['age'] ? ' · age ' . (int) $row['age'] : '' ?></small></td><td><?= yuc_e(str_replace('_', ' ', $row['category'])) ?><small><?= yuc_e($row['team_name']) ?><?= $row['zone'] !== '' ? ' · ' . yuc_e($row['zone']) : '' ?></small></td><td><strong><?= yuc_e($row['email']) ?></strong><small><?= yuc_e($row['phone']) ?></small></td><td><?= yuc_e($row['submitted_at']) ?></td><td><form method="post" action="/admin/registrations/status" class="inline-status-form" data-confirm="Save this registration status? A change may email the applicant."><?= yuc_csrf_field() ?><input type="hidden" name="id" value="<?= (int) $row['id'] ?>"><select name="status" aria-label="Registration status"><?php foreach (['new','reviewing','approved','rejected'] as $status): ?><option value="<?= yuc_e($status) ?>" <?= $row['status'] === $status ? 'selected' : '' ?>><?= yuc_e(ucfirst($status)) ?></option><?php endforeach; ?></select><button class="button button-quiet" type="submit">Save</button></form></td><td class="table-actions"><a class="table-action" href="/admin/registrations?edit=<?= (int) $row['id'] ?>">Edit</a><form method="post" action="/admin/registrations/delete" data-confirm="Permanently delete this registration and remove its personal details?"><?= yuc_csrf_field() ?><input type="hidden" name="id" value="<?= (int) $row['id'] ?>"><button class="table-action table-action-danger" type="submit">Delete</button></form></td></tr>
                <?php endforeach; endif; ?></tbody></table></div>
            </section>
        </div>

    <?php elseif ($resource === 'transactions'): ?>
        <div class="ops-grid transaction-ops-grid">
            <section class="panel ops-form-panel"><div class="panel-kicker">FINANCE ACTIVITY LOG</div><h2><?= isset($formValues['id']) ? 'Edit transaction' : 'Record a transaction' ?></h2><p class="panel-intro">Only manual records can be edited here. PayHub amounts and statuses remain server-verified; archived ledger entries are retained for audit.</p>
                <form method="post" action="/admin/transactions/save" class="form-stack">
                    <?= yuc_csrf_field() ?><input type="hidden" name="id" value="<?= yuc_e($formValues['id'] ?? '0') ?>">
                    <div class="field-group"><label for="transaction-reference">Reference <?= empty($formValues['id']) ? '<small class="inline-hint">Optional: generated if blank</small>' : '' ?></label><input id="transaction-reference" name="reference" maxlength="80" <?= !empty($formValues['id']) ? 'required' : '' ?> value="<?= yuc_e($formValues['reference'] ?? '') ?>"></div>
                    <div class="form-grid"><div class="field-group"><label for="transaction-amount">Amount</label><input id="transaction-amount" name="amount" type="number" min="0" step="0.01" required value="<?= yuc_e($formValues['amount'] ?? '') ?>"></div><div class="field-group"><label for="transaction-currency">Currency</label><select id="transaction-currency" name="currency"><?php foreach (['NGN','USD','GBP','EUR'] as $currency): ?><option value="<?= yuc_e($currency) ?>" <?= ($formValues['currency'] ?? 'NGN') === $currency ? 'selected' : '' ?>><?= yuc_e($currency) ?></option><?php endforeach; ?></select></div></div>
                    <div class="field-group"><label for="transaction-recipient">Recipient email</label><input id="transaction-recipient" name="recipient_email" type="email" maxlength="190" value="<?= yuc_e($formValues['recipient_email'] ?? '') ?>"></div>
                    <div class="field-group"><label for="transaction-status">Status</label><select id="transaction-status" name="status"><?php foreach (['pending','completed','failed','refunded'] as $status): ?><option value="<?= yuc_e($status) ?>" <?= ($formValues['status'] ?? 'pending') === $status ? 'selected' : '' ?>><?= yuc_e(ucfirst($status)) ?></option><?php endforeach; ?></select></div>
                    <div class="field-group"><label for="transaction-description">Description</label><input id="transaction-description" name="description" maxlength="255" value="<?= yuc_e($formValues['description'] ?? '') ?>"></div>
                    <div class="form-actions"><a class="button button-quiet" href="/admin/transactions">Clear</a><button class="button button-primary" type="submit"><?= isset($formValues['id']) ? 'Save transaction' : 'Record &amp; notify' ?></button></div>
                </form>
            </section>
            <section class="panel ops-table-panel"><div class="panel-topline"><div><div class="panel-kicker">TRANSACTION HISTORY</div><h2><?= $showArchived ? 'Archived transactions' : 'Recent transactions' ?></h2></div><div class="panel-topline-actions"><span class="readiness-pill is-ready"><span></span><?= count($rows) ?> RECORDS</span><a class="table-action" href="/admin/transactions?show_archived=<?= $showArchived ? '0' : '1' ?>"><?= $showArchived ? 'Show active' : 'Show archived' ?></a></div></div>
                <div class="responsive-table"><table class="data-table ops-table"><thead><tr><th>Reference / recipient</th><th>Amount</th><th>Recorded</th><th>Status</th><th>Manage</th></tr></thead><tbody>
                <?php if ($rows === []): ?><tr><td colspan="5" class="empty-state">No transaction records yet.</td></tr><?php else: foreach ($rows as $row): $manualRecord = ($row['payment_provider'] ?? '') !== 'payhub' && empty($row['shop_order_reference']); ?>
                    <tr data-search-text="<?= yuc_e($row['description'] ?? '') ?>"><td><strong><?= yuc_e($row['reference']) ?></strong><small><?= yuc_e($row['recipient_email']) ?></small><?php if (($row['payment_provider'] ?? '') === 'payhub'): ?><small>PayHub ref: <?= yuc_e($row['provider_reference'] ?? 'pending') ?></small><?php endif; ?><?php if (!empty($row['shop_order_reference'])): ?><small>Shop order: <?= yuc_e($row['shop_order_reference']) ?></small><?php endif; ?></td><td><?= yuc_e($row['currency']) ?> <?= number_format((float) $row['amount'], 2) ?></td><td><?= yuc_e($row['created_at']) ?></td><td><?php if (!empty($row['archived_at'])): ?><span class="outcome-badge outcome-cancelled">ARCHIVED</span><?php endif; ?><?php if (($row['payment_provider'] ?? '') === 'payhub'): ?><span class="outcome-badge outcome-<?= yuc_e($row['status']) ?>"><?= yuc_e(strtoupper((string) $row['status'])) ?></span><small>Verified by PayHub</small><?php elseif (!empty($row['archived_at'])): ?><span class="audit-no-context">Restore to change status</span><?php else: ?><form method="post" action="/admin/transactions/status" class="inline-status-form" data-confirm="Save this manual transaction status? A change may queue a notification."><?= yuc_csrf_field() ?><input type="hidden" name="id" value="<?= (int) $row['id'] ?>"><select name="status" aria-label="Transaction status"><?php foreach (['pending','completed','failed','refunded'] as $status): ?><option value="<?= yuc_e($status) ?>" <?= $row['status'] === $status ? 'selected' : '' ?>><?= yuc_e(ucfirst($status)) ?></option><?php endforeach; ?></select><button class="button button-quiet" type="submit">Save</button></form><?php endif; ?></td><td class="table-actions"><?php if ($manualRecord): ?><?php if (empty($row['archived_at'])): ?><a class="table-action" href="/admin/transactions?edit=<?= (int) $row['id'] ?>">Edit</a><?php endif; ?><form method="post" action="/admin/transactions/archive" data-confirm="<?= !empty($row['archived_at']) ? 'Restore this transaction to the active list?' : 'Archive this manual transaction? Its financial record and audit history will be preserved.' ?>"><?= yuc_csrf_field() ?><input type="hidden" name="id" value="<?= (int) $row['id'] ?>"><input type="hidden" name="archived" value="<?= empty($row['archived_at']) ? '1' : '0' ?>"><button class="table-action <?= empty($row['archived_at']) ? 'table-action-danger' : '' ?>" type="submit"><?= empty($row['archived_at']) ? 'Archive' : 'Restore' ?></button></form><?php else: ?><span class="table-action-locked">Payment record</span><?php endif; ?></td></tr>
                <?php endforeach; endif; ?></tbody></table></div>
            </section>
        </div>

    <?php elseif ($resource === 'products'): ?>
        <?php if ($siteMode === 'demo'): ?>
            <div class="demo-checkout-note"><strong>Demo product catalog — read-only.</strong><span>These sample items and images are preview-only. Product changes and uploads are available after switching back to Production.</span></div>
        <?php endif; ?>
        <div class="ops-grid shop-admin-grid">
            <?php if ($siteMode !== 'demo'): ?>
                <section class="panel ops-form-panel"><div class="panel-kicker">OFFICIAL MERCHANDISE</div><h2><?= isset($formValues['id']) ? 'Edit product' : 'Add a product' ?></h2><p class="panel-intro">Set the public catalog price and available stock. Product details and price are snapshotted when an order is placed.</p>
                    <form method="post" action="/admin/products/save" class="form-stack" enctype="multipart/form-data">
                        <?= yuc_csrf_field() ?><input type="hidden" name="id" value="<?= yuc_e($formValues['id'] ?? '0') ?>">
                        <div class="field-group"><label for="shop-product-name">Product name</label><input id="shop-product-name" name="name" required maxlength="140" value="<?= yuc_e($formValues['name'] ?? '') ?>"></div>
                        <div class="field-group"><label for="shop-product-sku">SKU</label><input id="shop-product-sku" name="sku" required maxlength="60" pattern="[A-Za-z0-9][A-Za-z0-9._-]{0,59}" value="<?= yuc_e($formValues['sku'] ?? '') ?>" placeholder="YUC-JERSEY-01"></div>
                        <div class="field-group"><label for="shop-product-description">Description</label><textarea id="shop-product-description" name="description" rows="4" maxlength="1000"><?= yuc_e($formValues['description'] ?? '') ?></textarea></div>
                        <div class="form-grid"><div class="field-group"><label for="shop-product-price">Price (NGN)</label><input id="shop-product-price" name="price" type="number" min="0.01" max="9999999999.99" step="0.01" required value="<?= yuc_e($formValues['price_amount'] ?? '') ?>"></div><div class="field-group"><label for="shop-product-stock">Available stock</label><input id="shop-product-stock" name="stock_quantity" type="number" min="0" max="2000000000" step="1" required value="<?= yuc_e($formValues['stock_quantity'] ?? '0') ?>"></div></div>
                        <div class="field-group"><label for="shop-product-status">Catalog status</label><select id="shop-product-status" name="status"><option value="active" <?= ($formValues['status'] ?? 'active') === 'active' ? 'selected' : '' ?>>Active</option><option value="inactive" <?= ($formValues['status'] ?? '') === 'inactive' ? 'selected' : '' ?>>Inactive</option></select></div>
                        <?php if (!empty($formValues['image_url'])): ?><div class="product-image-preview"><img src="<?= yuc_e($formValues['image_url']) ?>" alt="Current image for <?= yuc_e($formValues['name'] ?? 'product') ?>" loading="lazy" decoding="async"><span>Current product image</span></div><?php endif; ?>
                        <div class="field-group"><label for="shop-product-image">Product image</label><input id="shop-product-image" name="product_image" type="file" accept="image/jpeg,image/png,image/webp"><small>Optional JPEG, PNG or WebP, up to 8 MB. The server validates and optimizes it before protected storage; leave blank to keep the current image.</small></div>
                        <div class="form-actions"><a class="button button-quiet" href="/admin/products">Clear</a><button class="button button-primary" type="submit">Save product</button></div>
                    </form>
                </section>
            <?php endif; ?>
            <section class="panel ops-table-panel <?= $siteMode === 'demo' ? 'full-ops-panel' : '' ?>"><div class="panel-topline"><div><div class="panel-kicker"><?= $siteMode === 'demo' ? 'DEMO PREVIEW CATALOG' : 'CURRENT CATALOG' ?></div><h2>Shop products</h2></div><span class="readiness-pill is-ready"><span></span><?= count($rows) ?> PRODUCTS</span></div>
                <div class="responsive-table"><table class="data-table ops-table"><thead><tr><th>Product / SKU</th><th>Price</th><th>Available</th><th>Status</th><th><?= $siteMode === 'demo' ? 'Access' : 'Manage' ?></th></tr></thead><tbody>
                <?php if ($rows === []): ?><tr><td colspan="5" class="empty-state">No products yet. Add merchandise to make the public shop available.</td></tr><?php else: foreach ($rows as $row): ?>
                    <tr data-search-text="<?= yuc_e($row['description'] ?? '') ?>"><td><div class="admin-product-cell"><?php if (!empty($row['image_url'])): ?><img class="admin-product-image" src="<?= yuc_e($row['image_url']) ?>" alt="<?= yuc_e($row['name']) ?>" loading="lazy" decoding="async"><?php else: ?><span class="admin-product-image-placeholder" aria-hidden="true">Y</span><?php endif; ?><div><strong><?= yuc_e($row['name']) ?></strong><small><?= yuc_e($row['sku']) ?></small></div></div></td><td>₦<?= number_format((int) $row['price_kobo'] / 100, 2) ?></td><td><?= (int) $row['stock_quantity'] ?></td><td><span class="outcome-badge outcome-<?= yuc_e($row['status']) ?>"><?= yuc_e(strtoupper((string) $row['status'])) ?></span></td><td class="table-actions"><?php if ($siteMode === 'demo'): ?><span class="table-action-locked">Read-only in Demo</span><?php else: ?><a class="table-action" href="/admin/products?edit=<?= (int) $row['id'] ?>">Edit</a><form method="post" action="/admin/products/delete" data-confirm="<?= (int) ($row['order_item_count'] ?? 0) > 0 ? 'This product has order history. It will be archived from the public catalog, not erased.' : 'Permanently delete this product? This cannot be undone.' ?>"><?= yuc_csrf_field() ?><input type="hidden" name="id" value="<?= (int) $row['id'] ?>"><button class="table-action table-action-danger" type="submit"><?= (int) ($row['order_item_count'] ?? 0) > 0 ? 'Archive' : 'Delete' ?></button></form><?php endif; ?></td></tr>
                <?php endforeach; endif; ?></tbody></table></div>
            </section>
        </div>

    <?php elseif ($resource === 'orders'): ?>
        <?php if (is_string($shopSchemaWarning ?? null) && $shopSchemaWarning !== ''): ?><div class="alert alert-error" role="alert"><span class="alert-icon" aria-hidden="true">!</span><p><?= yuc_e($shopSchemaWarning) ?></p></div><?php endif; ?>
        <?php if (isset($formValues['id'])): ?>
            <section class="panel ops-form-panel order-edit-panel"><div class="panel-kicker">CUSTOMER &amp; FULFILLMENT DETAILS</div><h2>Edit order <?= yuc_e($formValues['reference'] ?? '') ?></h2><p class="panel-intro">Payment amounts, items, and PayHub verification are locked. Only customer contact and fulfillment notes can be changed.</p>
                <form method="post" action="/admin/orders/save" class="form-stack">
                    <?= yuc_csrf_field() ?><input type="hidden" name="id" value="<?= (int) $formValues['id'] ?>">
                    <div class="form-grid"><div class="field-group"><label for="order-customer-name">Customer name</label><input id="order-customer-name" name="customer_name" required maxlength="140" value="<?= yuc_e($formValues['customer_name'] ?? '') ?>"></div><div class="field-group"><label for="order-customer-email">Customer email</label><input id="order-customer-email" name="customer_email" type="email" required maxlength="190" value="<?= yuc_e($formValues['customer_email'] ?? '') ?>"></div></div>
                    <div class="form-grid"><div class="field-group"><label for="order-customer-phone">Phone</label><input id="order-customer-phone" name="customer_phone" maxlength="40" value="<?= yuc_e($formValues['customer_phone'] ?? '') ?>"></div><div class="field-group"><label for="order-fulfillment-notes">Delivery / collection notes</label><input id="order-fulfillment-notes" name="fulfillment_notes" maxlength="500" value="<?= yuc_e($formValues['fulfillment_notes'] ?? '') ?>"></div></div>
                    <div class="form-actions"><a class="button button-quiet" href="/admin/orders">Cancel</a><button class="button button-primary" type="submit">Save order details</button></div>
                </form>
            </section>
        <?php endif; ?>
        <details class="panel order-create-panel" <?= !empty($formValues['create_order']) ? 'open' : '' ?>>
            <summary><div><span class="panel-kicker">SAFE ORDER CREATION</span><strong>Create a shop order</strong><small>Reserve stock and open a verified PayHub inline checkout</small></div><span class="readiness-pill <?= $payHubConfigured && $orderProducts !== [] ? 'is-ready' : 'is-warning' ?>"><span></span>NEW ORDER</span></summary>
            <p class="panel-intro">The order starts as pending payment and inventory is reserved atomically. The inline checkout opens with a user click; payment remains unconfirmed until the webhook or return page is reconciled against PayHub's verification API.</p>
            <?php if ($siteMode === 'demo'): ?><div class="demo-checkout-note"><strong>Shop operations are read-only in Demo mode.</strong><span>No stock reservation, shop order, or payment record will be created. Existing production orders remain unchanged.</span></div><?php elseif (!$payHubConfigured): ?><div class="alert alert-error" role="status"><span class="alert-icon" aria-hidden="true">!</span><p>PayHub inline checkout is not configured. Add both the secret and public keys under Settings before creating shop orders.</p></div><?php elseif ($orderProducts === []): ?><div class="alert alert-error" role="status"><span class="alert-icon" aria-hidden="true">!</span><p>No active products with available stock can be ordered right now.</p></div><?php endif; ?>
            <form method="post" action="/admin/orders/create" class="form-stack">
                <?= yuc_csrf_field() ?><input type="hidden" name="create_order" value="1">
                <div class="form-grid"><div class="field-group"><label for="new-order-customer-name">Customer name</label><input id="new-order-customer-name" name="customer_name" required maxlength="140" value="<?= yuc_e($formValues['customer_name'] ?? '') ?>"></div><div class="field-group"><label for="new-order-customer-email">Customer email</label><input id="new-order-customer-email" name="customer_email" type="email" required maxlength="190" value="<?= yuc_e($formValues['customer_email'] ?? '') ?>"></div></div>
                <div class="form-grid"><div class="field-group"><label for="new-order-customer-phone">Phone</label><input id="new-order-customer-phone" name="customer_phone" maxlength="40" value="<?= yuc_e($formValues['customer_phone'] ?? '') ?>"></div><div class="field-group"><label for="new-order-fulfillment-notes">Delivery / collection notes</label><input id="new-order-fulfillment-notes" name="fulfillment_notes" maxlength="500" value="<?= yuc_e($formValues['fulfillment_notes'] ?? '') ?>"></div></div>
                <fieldset class="admin-order-products"><legend>Select items and quantities</legend><div class="admin-order-product-list">
                    <?php foreach ($orderProducts as $product): $productId = (int) $product['id']; $productStock = (int) $product['stock_quantity']; $quantityInput = $formValues['quantity'][$productId] ?? 0; $quantityValue = is_scalar($quantityInput) ? (string) $quantityInput : '0'; ?>
                        <div class="admin-order-product"><div><strong><?= yuc_e($product['name']) ?></strong><small><?= yuc_e($product['sku']) ?> · ₦<?= number_format((int) $product['price_kobo'] / 100, 2) ?> · <?= $productStock ?> available</small></div><label for="new-order-quantity-<?= $productId ?>">Qty</label><input id="new-order-quantity-<?= $productId ?>" type="number" name="quantity[<?= $productId ?>]" min="0" max="<?= min(20, $productStock) ?>" step="1" value="<?= yuc_e($quantityValue) ?>"></div>
                    <?php endforeach; ?>
                    <?php if ($orderProducts === []): ?><p class="admin-order-products-empty">Product choices appear here when an active item has stock.</p><?php endif; ?>
                </div></fieldset>
                <div class="form-actions"><button class="button button-primary" type="submit" <?= $siteMode === 'demo' || !$payHubConfigured || $orderProducts === [] ? 'disabled' : '' ?>>Create order &amp; open inline checkout</button></div>
            </form>
        </details>
        <section class="panel ops-table-panel full-ops-panel"><div class="panel-topline"><div><div class="panel-kicker">PAYMENT &amp; FULFILLMENT</div><h2><?= $showArchived ? 'Archived shop orders' : 'Shop orders' ?></h2></div><div class="panel-topline-actions"><span class="readiness-pill is-ready"><span></span><?= count($rows) ?> ORDERS</span><a class="table-action" href="/admin/orders?show_archived=<?= $showArchived ? '0' : '1' ?>"><?= $showArchived ? 'Show active' : 'Show archived' ?></a></div></div>
            <p class="panel-intro">Admin-created orders use PayHub inline checkout with the same atomic stock reservation and server-side payment verification as public orders. Pending orders open in a secure modal from this list; edit only customer/fulfillment details. Payment history is never deleted.</p>
            <div class="responsive-table"><table class="data-table ops-table shop-orders-table"><thead><tr><th>Order / items</th><th>Customer &amp; fulfillment</th><th>Total</th><th>PayHub reference</th><th>Order status</th><th>Next action</th><th>Manage</th></tr></thead><tbody>
            <?php if ($rows === []): ?><tr><td colspan="7" class="empty-state">No shop orders have been placed.</td></tr><?php else: foreach ($rows as $row):
                $statusOptions = $siteMode === 'demo' || !empty($row['archived_at']) ? [] : match ((string) $row['status']) {
                    'pending_payment' => ['cancelled' => 'Close &amp; release stock'],
                    'paid_needs_review' => ['paid' => 'Confirm review'],
                    'paid' => ['processing' => 'Start processing'],
                    'processing' => ['fulfilled' => 'Mark fulfilled'],
                    default => [],
                };
                ?>
                <tr data-search-text="<?= yuc_e($row['payment_review_reason'] ?? '') ?>"><td><strong class="mono-cell"><?= yuc_e($row['reference']) ?></strong><small><?= yuc_e($row['item_summary'] ?? '') ?></small><small><?= yuc_e($row['created_at']) ?> UTC</small></td>
                    <td><strong><?= yuc_e($row['customer_name']) ?></strong><small><?= yuc_e($row['customer_email']) ?><?= $row['customer_phone'] !== '' ? ' · ' . yuc_e($row['customer_phone']) : '' ?></small><?php if ($row['fulfillment_notes'] !== ''): ?><small class="shop-fulfillment-note">Delivery / collection: <?= yuc_e($row['fulfillment_notes']) ?></small><?php endif; ?></td>
                    <td>₦<?= number_format((int) $row['total_kobo'] / 100, 2) ?></td><td class="mono-cell"><?= yuc_e($row['provider_reference'] ?? 'Not initialized') ?><?php if ($row['provider_amount_kobo'] !== null): ?><small>Paid: <?= yuc_e($row['provider_amount_kobo']) ?> kobo (<?= yuc_e($row['provider_currency'] ?? '') ?>)</small><?php endif; ?><?php if (!empty($row['payment_review_reason'])): ?><small class="shop-review-reason">Review: <?= yuc_e(str_replace('_', ' ', $row['payment_review_reason'])) ?></small><?php endif; ?></td>
                    <td><?php if (!empty($row['archived_at'])): ?><span class="outcome-badge outcome-cancelled">ARCHIVED</span><?php endif; ?><span class="outcome-badge outcome-<?= yuc_e($row['status']) ?>"><?= yuc_e(strtoupper(str_replace('_', ' ', (string) $row['status']))) ?></span><?php if ($row['status'] === 'pending_payment'): ?><small>Reservation expires <?= yuc_e($row['reservation_expires_at']) ?> UTC</small><?php endif; ?></td>
                    <td><?php if ($statusOptions !== []): ?><form method="post" action="/admin/orders/status" class="inline-status-form" data-confirm="Update this shop order? Cancellation releases the unpaid stock reservation."><?= yuc_csrf_field() ?><input type="hidden" name="id" value="<?= (int) $row['id'] ?>"><select name="status" aria-label="Next order status"><?php foreach ($statusOptions as $nextStatus => $label): ?><option value="<?= yuc_e($nextStatus) ?>"><?= $label ?></option><?php endforeach; ?></select><button class="button button-quiet" type="submit">Update</button></form><?php else: ?><span class="audit-no-context">No action</span><?php endif; ?></td>
                    <td class="table-actions"><?php if ($siteMode !== 'demo' && $row['status'] === 'pending_payment' && !empty($row['checkout_url']) && \Yuc\Services\PayHubClient::isTrustedCheckoutUrl((string) $row['checkout_url'])): ?><a class="table-action" href="<?= yuc_e($row['checkout_url']) ?>" target="_blank" rel="noopener noreferrer">Open PayHub checkout</a><?php elseif ($siteMode !== 'demo' && $row['status'] === 'pending_payment' && !empty($row['provider_reference']) && empty($row['checkout_url'])): ?><a class="table-action" href="/admin/orders?pay=<?= (int) $row['id'] ?>">Open inline checkout</a><?php endif; ?><?php if ($siteMode !== 'demo' && empty($row['archived_at'])): ?><a class="table-action" href="/admin/orders?edit=<?= (int) $row['id'] ?>">Edit details</a><?php endif; ?><?php if ($siteMode !== 'demo' && (!empty($row['archived_at']) || in_array((string) $row['status'], ['fulfilled','payment_failed','cancelled'], true))): ?><form method="post" action="/admin/orders/archive" data-confirm="<?= !empty($row['archived_at']) ? 'Restore this order to the active list?' : 'Archive this closed order from operations? The order, payment record, and audit history will be preserved.' ?>"><?= yuc_csrf_field() ?><input type="hidden" name="id" value="<?= (int) $row['id'] ?>"><input type="hidden" name="archived" value="<?= empty($row['archived_at']) ? '1' : '0' ?>"><button class="table-action <?= empty($row['archived_at']) ? 'table-action-danger' : '' ?>" type="submit"><?= empty($row['archived_at']) ? 'Archive' : 'Restore' ?></button></form><?php else: ?><span class="table-action-locked"><?= $siteMode === 'demo' ? 'Read-only in Demo mode' : 'Close/fulfill first' ?></span><?php endif; ?></td></tr>
            <?php endforeach; endif; ?></tbody></table></div>
        </section>

    <?php elseif ($resource === 'activity'): ?>
        <section class="panel ops-table-panel full-ops-panel audit-panel"><div class="panel-topline"><div><div class="panel-kicker">ACCOUNTABILITY &amp; CHANGE HISTORY</div><h2>Audit activity</h2></div><span class="readiness-pill is-ready"><span></span><?= $auditTotal ?> EVENTS</span></div>
            <p class="panel-intro">Recent administrative changes, authentication events, registration activity, and notification-related events. Entries are newest first; records are read-only.</p>
            <form method="get" action="/admin/activity" class="audit-toolbar">
                <div class="field-group audit-search-field"><label for="audit-search">Search activity</label><input id="audit-search" name="q" type="search" maxlength="120" value="<?= yuc_e($auditSearch) ?>" placeholder="Description, event, or IP address"></div>
                <div class="field-group audit-category-field"><label for="audit-category">Category</label><select id="audit-category" name="category"><option value="">All activity</option><?php foreach (['auth'=>'Authentication','security'=>'Security','system'=>'System','registration'=>'Registration','transaction'=>'Transaction','tournament'=>'Tournament'] as $value => $label): ?><option value="<?= yuc_e($value) ?>" <?= $auditCategory === $value ? 'selected' : '' ?>><?= yuc_e($label) ?></option><?php endforeach; ?></select></div>
                <button class="button button-primary" type="submit">Filter activity</button>
                <?php if ($auditSearch !== '' || $auditCategory !== ''): ?><a class="button button-quiet" href="/admin/activity">Clear</a><?php endif; ?>
            </form>
            <div class="responsive-table"><table class="data-table ops-table audit-table"><thead><tr><th>Occurred (UTC)</th><th>Actor</th><th>Event</th><th>Activity details</th><th>Source IP</th><th>Context</th></tr></thead><tbody>
            <?php if ($rows === []): ?><tr><td colspan="6" class="empty-state">No audit entries match these filters.</td></tr><?php else: foreach ($rows as $row): $context = []; if (!empty($row['context_json'])) { $decodedContext = json_decode((string) $row['context_json'], true); if (is_array($decodedContext)) { $context = $decodedContext; } } ?>
                <tr><td><?= yuc_e($row['created_at']) ?></td><td><strong><?= yuc_e($row['actor_name'] ?: 'System / public') ?></strong><?php if (!empty($row['actor_username'])): ?><small>@<?= yuc_e($row['actor_username']) ?></small><?php endif; ?></td><td><span class="audit-event-tag"><?= yuc_e($row['event_key']) ?></span></td><td><?= yuc_e($row['description']) ?></td><td class="mono-cell"><?= yuc_e($row['ip_address']) ?></td><td><?php if ($context !== []): ?><details class="audit-context"><summary>View</summary><pre><?= yuc_e(json_encode($context, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE) ?: '{}') ?></pre></details><?php else: ?><span class="audit-no-context">—</span><?php endif; ?></td></tr>
            <?php endforeach; endif; ?></tbody></table></div>
            <div class="audit-pagination"><span>Page <?= $auditPage ?> of <?= $auditPages ?> · <?= $auditTotal ?> total events</span><div><?php if ($auditPage > 1): ?><a class="button button-quiet" href="/admin/activity?<?= yuc_e(http_build_query(['q' => $auditSearch, 'category' => $auditCategory, 'page' => $auditPage - 1])) ?>">← Newer</a><?php endif; ?><?php if ($auditPage < $auditPages): ?><a class="button button-quiet" href="/admin/activity?<?= yuc_e(http_build_query(['q' => $auditSearch, 'category' => $auditCategory, 'page' => $auditPage + 1])) ?>">Older →</a><?php endif; ?></div></div>
        </section>

    <?php elseif ($resource === 'security'): ?>
        <div class="ops-grid security-admin-grid">
            <section class="panel ops-form-panel"><div class="panel-kicker">ACCESS CONTROL</div><h2><?= !empty($formValues['ip_address']) ? 'Edit IP block' : 'Block an IP address' ?></h2><p class="panel-intro">Add or update a temporary IPv4/IPv6 sign-in block. The block expires automatically.</p>
                <form method="post" action="/admin/security/save" class="form-stack">
                    <?= yuc_csrf_field() ?><input type="hidden" name="old_ip" value="<?= yuc_e($formValues['old_ip'] ?? $formValues['ip_address'] ?? '') ?>">
                    <div class="field-group"><label for="blocked-ip-address">IP address</label><input id="blocked-ip-address" name="ip_address" required maxlength="45" value="<?= yuc_e($formValues['ip_address'] ?? '') ?>" placeholder="203.0.113.10"></div>
                    <div class="field-group"><label for="blocked-ip-reason">Reason</label><input id="blocked-ip-reason" name="reason" required maxlength="160" value="<?= yuc_e($formValues['reason'] ?? 'Manual administrator block') ?>"></div>
                    <div class="field-group"><label for="blocked-ip-duration">Duration from now (minutes)</label><input id="blocked-ip-duration" name="duration_minutes" type="number" min="1" max="1440" required value="<?= yuc_e($formValues['duration_minutes'] ?? '15') ?>"></div>
                    <div class="form-actions"><a class="button button-quiet" href="/admin/security">Clear</a><button class="button button-primary" type="submit">Save block</button></div>
                </form>
            </section>
            <section class="panel ops-table-panel full-ops-panel"><div class="panel-topline"><div><div class="panel-kicker">AUTOMATIC SIGN-IN PROTECTION</div><h2>Temporary IP blocks</h2></div><span class="readiness-pill <?= $rows === [] ? 'is-ready' : 'is-warning' ?>"><span></span><?= count($rows) ?> ACTIVE</span></div>
                <p class="panel-intro">Addresses blocked automatically after failed sign-ins and manual blocks appear here. Removing a block allows that address to sign in again immediately.</p>
                <div class="responsive-table"><table class="data-table ops-table"><thead><tr><th>IP address</th><th>Reason</th><th>Blocked since</th><th>Expires (UTC)</th><th>Action</th></tr></thead><tbody>
                <?php if ($rows === []): ?><tr><td colspan="5" class="empty-state">No active IP blocks. Rate limiting is still enabled.</td></tr><?php else: foreach ($rows as $row): ?>
                    <tr><td><strong class="mono-cell"><?= yuc_e($row['ip_address']) ?></strong></td><td><?= yuc_e($row['reason']) ?></td><td><?= yuc_e($row['created_at']) ?> UTC</td><td><?= yuc_e($row['blocked_until']) ?> UTC</td><td class="table-actions"><a class="table-action" href="/admin/security?edit_ip=<?= rawurlencode((string) $row['ip_address']) ?>">Edit</a><form method="post" action="/admin/security/delete" data-confirm="Remove this IP block now? The address will be able to sign in again."><?= yuc_csrf_field() ?><input type="hidden" name="ip_address" value="<?= yuc_e($row['ip_address']) ?>"><button class="table-action table-action-danger" type="submit">Unblock</button></form></td></tr>
                <?php endforeach; endif; ?></tbody></table></div>
                <div class="security-footnote"><span class="status-dot"></span><span>Login thresholds and default block duration are configurable in <a href="/admin/settings">Site &amp; security settings</a>.</span></div>
            </section>
        </div>

    <?php elseif ($resource === 'homepage-hero'): ?>
        <section class="panel settings-panel homepage-hero-settings-panel"><div class="panel-kicker">HOMEPAGE PRESENTATION</div><h2>Manage the hero media</h2>
            <p class="panel-intro">Choose the default tournament art, a protected image upload, a looping YouTube video, or a short web-optimized video file. Text, match links, and season information remain editable in the site source; uploaded media stays outside the public document root.</p>
            <?php if ($siteMode === 'demo'): ?><div class="alert alert-error" role="status"><span class="alert-icon" aria-hidden="true">!</span><p>Homepage changes are read-only in Demo mode. Switch to Production to update the live hero.</p></div><?php endif; ?>
            <div class="hero-admin-current">
                <div><span class="panel-kicker">CURRENT HERO · <?= yuc_e(strtoupper((string) ($heroSettings['type'] ?? 'default'))) ?></span>
                    <?php if (($heroSettings['type'] ?? 'default') === 'image' && !empty($heroSettings['media_url'])): ?><img src="<?= yuc_e($heroSettings['media_url']) ?>" alt="<?= yuc_e($heroSettings['image_alt'] ?? 'Current homepage hero') ?>" loading="lazy" decoding="async">
                    <?php elseif (($heroSettings['type'] ?? 'default') === 'video' && !empty($heroSettings['media_url'])): ?><video controls playsinline preload="metadata" poster="/assets/yuc-hero.jpg"><source src="<?= yuc_e($heroSettings['media_url']) ?>" type="<?= str_ends_with((string) $heroSettings['media_file'], '.webm') ? 'video/webm' : 'video/mp4' ?>">Your browser cannot preview this video.</video>
                    <?php elseif (($heroSettings['type'] ?? 'default') === 'youtube' && !empty($heroSettings['youtube_url'])): ?><p>Looping clip: <a href="<?= yuc_e($heroSettings['youtube_url']) ?>" target="_blank" rel="noopener noreferrer"><?= yuc_e($heroSettings['youtube_id']) ?> ↗</a></p>
                    <?php else: ?><img src="/assets/yuc-hero.jpg" alt="Current default Youth Unity Cup hero art" loading="lazy" decoding="async"><?php endif; ?>
                </div>
                <a class="button button-quiet" href="/" target="_blank" rel="noopener noreferrer">Preview homepage ↗</a>
            </div>
            <form method="post" action="/admin/homepage-hero/save" enctype="multipart/form-data" class="form-stack homepage-hero-form">
                <?= yuc_csrf_field() ?>
                <div class="field-group"><label for="homepage-hero-type">Hero media source</label><select id="homepage-hero-type" name="hero_type" required <?= $siteMode === 'demo' ? 'disabled' : '' ?>><option value="default" <?= ($heroSettings['type'] ?? 'default') === 'default' ? 'selected' : '' ?>>Default Youth Unity Cup image</option><option value="image" <?= ($heroSettings['type'] ?? '') === 'image' ? 'selected' : '' ?>>Uploaded image</option><option value="youtube" <?= ($heroSettings['type'] ?? '') === 'youtube' ? 'selected' : '' ?>>YouTube video</option><option value="video" <?= ($heroSettings['type'] ?? '') === 'video' ? 'selected' : '' ?>>Uploaded video file</option></select></div>
                <div class="field-group"><label for="homepage-hero-youtube">YouTube link</label><input id="homepage-hero-youtube" name="hero_youtube_url" type="url" maxlength="500" placeholder="https://youtu.be/VIDEO_ID" value="<?= yuc_e($heroSettings['youtube_url'] ?? '') ?>" <?= $siteMode === 'demo' ? 'disabled' : '' ?>><small>Accepted links: youtube.com/watch, youtube.com/shorts, youtube.com/embed, and youtu.be. The embed uses the privacy-enhanced YouTube domain and loops muted.</small></div>
                <div class="field-group"><label for="homepage-hero-image">Upload hero image</label><input id="homepage-hero-image" name="hero_image_file" type="file" accept="image/jpeg,image/png,image/webp" <?= $siteMode === 'demo' ? 'disabled' : '' ?>><small>JPEG, PNG, or WebP, up to 10 MB. The server validates the image, strips embedded metadata, resizes large images, and optimizes to WebP.</small></div>
                <div class="field-group"><label for="homepage-hero-video">Upload hero video</label><input id="homepage-hero-video" name="hero_video_file" type="file" accept="video/mp4,video/webm" <?= $siteMode === 'demo' ? 'disabled' : '' ?>><small>MP4 or WebM, up to 25 MB (under 10 MB is best). Use a short, muted 1080p loop; MP4 must be web-optimized with fast-start enabled. Videos autoplay muted, loop continuously, and use range streaming.</small></div>
                <div class="field-group"><label for="homepage-hero-alt">Image description for accessibility</label><input id="homepage-hero-alt" name="hero_image_alt" maxlength="160" value="<?= yuc_e($heroSettings['image_alt'] ?? 'Youth Unity Cup community football') ?>" <?= $siteMode === 'demo' ? 'disabled' : '' ?>></div>
                <div class="form-actions"><button class="button button-primary" type="submit" <?= $siteMode === 'demo' ? 'disabled' : '' ?>>Save homepage hero</button></div>
            </form>
        </section>

    <?php elseif ($resource === 'live-stream'): ?>
        <?php
        $liveStreamForm = array_merge(
            ['enabled' => '0', 'title' => 'Youth Unity Cup Live', 'url' => ''],
            $liveStreamSettings,
            $formValues
        );
        $liveStreamEnabledValue = is_scalar($liveStreamForm['enabled'] ?? null) && (string) $liveStreamForm['enabled'] === '1';
        ?>
        <div class="ops-grid live-stream-admin-grid">
            <section class="panel ops-form-panel live-stream-settings-panel">
                <div class="panel-kicker">MATCHDAY BROADCAST</div>
                <h2>Configure Watch live</h2>
                <p class="panel-intro">Add a YouTube or TikTok LIVE link. When enabled, the public landing page shows a Watch live button and opens the broadcast section.</p>
                <?php if ($siteMode === 'demo'): ?><div class="alert alert-info" role="status"><span class="alert-icon" aria-hidden="true">i</span><p>Demo mode shows a fixed, read-only YouTube sample. This link is not saved to Production settings. Switch to Production to view or change the public broadcast.</p></div><?php endif; ?>
                <form method="post" action="/admin/live-stream/save" class="form-stack">
                    <?= yuc_csrf_field() ?>
                    <div class="field-group"><label for="live-stream-enabled">Watch live button</label><select id="live-stream-enabled" name="enabled" <?= $siteMode === 'demo' ? 'disabled' : '' ?>><option value="0" <?= !$liveStreamEnabledValue ? 'selected' : '' ?>>Off — hide the button</option><option value="1" <?= $liveStreamEnabledValue ? 'selected' : '' ?>>On — show the button and player</option></select></div>
                    <div class="field-group"><label for="live-stream-title">Broadcast title</label><input id="live-stream-title" name="title" maxlength="120" value="<?= yuc_e($liveStreamForm['title'] ?? 'Youth Unity Cup Live') ?>" placeholder="Youth Unity Cup Final" <?= $siteMode === 'demo' ? 'disabled' : '' ?>></div>
                    <div class="field-group"><label for="live-stream-url">YouTube live or TikTok LIVE URL</label><input id="live-stream-url" name="url" type="url" inputmode="url" maxlength="500" value="<?= yuc_e($liveStreamForm['url'] ?? '') ?>" placeholder="https://www.youtube.com/live/VIDEO_ID" <?= $siteMode === 'demo' ? 'disabled' : '' ?>><small>Only HTTPS links from YouTube or TikTok are accepted. YouTube video-ID links and channel-ID /live links embed in-page; YouTube handle pages and TikTok LIVE open on their platform.</small></div>
                    <div class="live-stream-autoplay-note"><strong>Autoplay behavior</strong><span>YouTube starts muted because browsers block most autoplay with sound. Viewers can unmute in the player. TikTok does not provide an official embeddable LIVE player, so its link opens TikTok directly.</span></div>
                    <div class="form-actions"><a class="button button-quiet" href="/" target="_blank" rel="noopener noreferrer">Preview landing page ↗</a><button class="button button-primary" type="submit" <?= $siteMode === 'demo' ? 'disabled' : '' ?>>Save live settings</button></div>
                </form>
            </section>
            <aside class="panel ops-table-panel live-stream-status-panel">
                <div class="panel-kicker">PUBLIC PLAYER STATUS</div><h2><?= !empty($liveStreamSettings['enabled']) ? 'Watch live is ready' : 'No active broadcast' ?></h2>
                <?php if (!empty($liveStreamSettings['enabled'])): ?>
                    <span class="readiness-pill is-ready"><span></span>PUBLIC BUTTON ON</span>
                    <p class="panel-intro"><strong><?= yuc_e($liveStreamSettings['title']) ?></strong><br><?= strtoupper(yuc_e($liveStreamSettings['platform'] ?? '')) ?> · <?= !empty($liveStreamSettings['can_embed']) ? 'autoplay player enabled' : 'external live link' ?></p>
                    <a class="live-stream-external-link" href="<?= yuc_e($liveStreamSettings['url']) ?>" target="_blank" rel="noopener noreferrer">Open configured broadcast ↗</a>
                <?php else: ?>
                    <p class="panel-intro">The landing page Watch live button is hidden until you enable a supported broadcast link.</p>
                <?php endif; ?>
                <div class="live-stream-status-rule"></div>
                <p class="live-stream-status-footnote">A YouTube player must be public or unlisted and allow embedding. Actual playback and audible sound are subject to the viewer’s browser and platform settings.</p>
            </aside>
        </div>

    <?php else: ?>
        <section class="panel settings-panel"><div class="panel-kicker">SITE OPERATIONS</div><h2>Site, email &amp; security</h2><p class="panel-intro">Keep public details current, tune failed-login protection, and manage the SMTP connection. Leave the SMTP password blank to retain the saved password.</p>
            <form method="post" action="/admin/settings/save" class="form-stack">
                <?= yuc_csrf_field() ?>
                <div class="settings-section-title">Public site details</div>
                <div class="form-grid"><div class="field-group"><label for="site-title">Site title</label><input id="site-title" name="site_title" required maxlength="100" value="<?= yuc_e($settings['site_title'] ?? 'Youth Unity Cup') ?>"></div><div class="field-group"><label for="site-timezone">Timezone</label><select id="site-timezone" name="timezone"><?php foreach (['UTC','Africa/Lagos','Africa/Accra','Europe/London','America/New_York'] as $timezone): ?><option value="<?= yuc_e($timezone) ?>" <?= ($settings['timezone'] ?? 'UTC') === $timezone ? 'selected' : '' ?>><?= yuc_e($timezone) ?></option><?php endforeach; ?></select></div></div>
                <div class="field-group"><label for="contact-email">Public contact email</label><input id="contact-email" name="contact_email" type="email" maxlength="190" value="<?= yuc_e($settings['contact_email'] ?? '') ?>"></div>
                <div class="settings-section-title">Sign-in protection</div>
                <div class="form-grid settings-security-grid"><div class="field-group"><label for="max-login-attempts">Failed attempts before temporary block</label><input id="max-login-attempts" name="max_login_attempts" type="number" min="3" max="20" value="<?= yuc_e($settings['max_login_attempts'] ?? '5') ?>"></div><div class="field-group"><label for="login-window">Attempt window (minutes)</label><input id="login-window" name="login_window_minutes" type="number" min="5" max="120" value="<?= yuc_e($settings['login_window_minutes'] ?? '15') ?>"></div><div class="field-group"><label for="block-duration">Block duration (minutes)</label><input id="block-duration" name="block_duration_minutes" type="number" min="5" max="1440" value="<?= yuc_e($settings['block_duration_minutes'] ?? '15') ?>"></div></div>
                <div class="settings-section-title">SMTP delivery</div>
                <div class="form-grid"><div class="field-group"><label for="smtp-host">SMTP host</label><input id="smtp-host" name="smtp_host" maxlength="253" value="<?= yuc_e($mail['host'] ?? '') ?>" placeholder="smtp.example.com"></div><div class="field-group"><label for="smtp-port">SMTP port</label><input id="smtp-port" name="smtp_port" type="number" min="1" max="65535" value="<?= yuc_e($mail['port'] ?? 587) ?>"></div><div class="field-group"><label for="smtp-encryption">Encryption</label><select id="smtp-encryption" name="smtp_encryption"><?php foreach (['tls'=>'STARTTLS','ssl'=>'SSL/TLS','none'=>'None (not recommended)'] as $value=>$label): ?><option value="<?= yuc_e($value) ?>" <?= ($mail['encryption'] ?? 'tls') === $value ? 'selected' : '' ?>><?= yuc_e($label) ?></option><?php endforeach; ?></select></div><div class="field-group"><label for="smtp-username">SMTP username</label><input id="smtp-username" name="smtp_username" maxlength="190" value="<?= yuc_e($mail['username'] ?? '') ?>" autocomplete="off"></div><div class="field-group"><label for="smtp-password">SMTP password</label><input id="smtp-password" name="smtp_password" type="password" maxlength="1024" autocomplete="new-password" placeholder="Leave blank to keep current password"></div><div class="field-group"><label for="mail-from-email">Sender email</label><input id="mail-from-email" name="mail_from_email" type="email" maxlength="190" value="<?= yuc_e($mail['from_email'] ?? '') ?>"></div><div class="field-group"><label for="mail-from-name">Sender name</label><input id="mail-from-name" name="mail_from_name" maxlength="120" value="<?= yuc_e($mail['from_name'] ?? 'Youth Unity Cup') ?>"></div><div class="field-group"><label for="notifications-to">Alert recipient email</label><input id="notifications-to" name="notifications_to" type="email" maxlength="190" value="<?= yuc_e($mail['notifications_to'] ?? '') ?>"></div></div>
                <div class="settings-section-title">PayHub shop payments</div>
                <p class="panel-intro payhub-settings-note">Inline checkout status: <strong><?= $payHubConfigured ? 'Ready' : 'Missing one or both keys' ?></strong>. Secret: <?= $payHubSecretConfigured ? 'configured' : 'not configured' ?>. Public: <?= $payHubPublicConfigured ? 'configured' : 'not configured' ?>. The secret remains server-side in protected local configuration; the public key is sent only to pages that start PayHub checkout. Leave either field blank to keep its saved value.</p>
                <div class="form-grid"><div class="field-group"><label for="payhub-public-key">PayHub public key</label><input id="payhub-public-key" name="payhub_public_key" type="password" maxlength="512" autocomplete="new-password" placeholder="Enter the PayHub public key"></div><div class="field-group"><label for="payhub-secret-key">PayHub secret key</label><input id="payhub-secret-key" name="payhub_secret_key" type="password" maxlength="512" autocomplete="new-password" placeholder="Enter the PayHub secret key"></div></div>
                <?php if ($payHubPublicConfigured): ?><label class="settings-checkbox"><input type="checkbox" name="payhub_clear_public" value="1"> Remove the saved PayHub public key</label><?php endif; ?>
                <?php if ($payHubSecretConfigured): ?><label class="settings-checkbox"><input type="checkbox" name="payhub_clear_secret" value="1"> Remove the saved PayHub secret key and disable payment verification</label><?php endif; ?>
                <div class="form-actions"><button class="button button-primary" type="submit">Save settings</button><a class="button button-outline" href="/admin">Cancel</a></div>
            </form>
        </section>
    <?php endif; ?>
</section>
