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
    'teams' => 'Teams', 'venues' => 'Venues', 'fixtures' => 'Fixtures & scores',
    'registrations' => 'Registrations', 'transactions' => 'Transactions', 'products' => 'Shop products', 'orders' => 'Shop orders', 'settings' => 'Site & security settings', 'security' => 'Blocked IP access', 'activity' => 'Audit activity',
];
$title = $resourceTitles[$resource] ?? 'Admin';
$formValues = is_array($formValues ?? null) ? $formValues : [];
$settings = is_array($settings ?? null) ? $settings : [];
$mail = is_array($mail ?? null) ? $mail : [];
$auditTotal = (int) ($auditTotal ?? 0);
$auditPage = max(1, (int) ($auditPage ?? 1));
$auditPages = max(1, (int) ($auditPages ?? 1));
$auditSearch = (string) ($auditSearch ?? '');
$auditCategory = (string) ($auditCategory ?? '');
$showArchived = !empty($showArchived);
$payHubConfigured = !empty($payHubConfigured);
$orderProducts = is_array($orderProducts ?? null) ? $orderProducts : [];
$liveSearchLabels = [
    'teams' => 'teams', 'venues' => 'venues', 'fixtures' => 'fixtures and scores',
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
        <a href="/admin/venues" class="<?= $resource === 'venues' ? 'active' : '' ?>">Venues</a>
        <a href="/admin/fixtures" class="<?= $resource === 'fixtures' ? 'active' : '' ?>">Fixtures &amp; scores</a>
        <a href="/admin/registrations" class="<?= $resource === 'registrations' ? 'active' : '' ?>">Registrations</a>
        <a href="/admin/transactions" class="<?= $resource === 'transactions' ? 'active' : '' ?>">Transactions</a>
        <a href="/admin/products" class="<?= $resource === 'products' ? 'active' : '' ?>">Shop products</a>
        <a href="/admin/orders" class="<?= $resource === 'orders' ? 'active' : '' ?>">Shop orders</a>
        <a href="/admin/settings" class="<?= $resource === 'settings' ? 'active' : '' ?>">Settings</a>
        <a href="/admin/security" class="<?= $resource === 'security' ? 'active' : '' ?>">Blocked IPs</a>
        <a href="/admin/activity" class="<?= $resource === 'activity' ? 'active' : '' ?>">Audit activity</a>
    </nav>

    <?php if (is_array($flash)): ?>
        <div class="alert alert-<?= yuc_e($flash['type']) ?>" role="status" aria-live="polite"><span class="alert-icon" aria-hidden="true"><?= $flash['type'] === 'error' ? '!' : '✓' ?></span><p><?= yuc_e($flash['message']) ?></p></div>
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
        <div class="ops-grid shop-admin-grid">
            <section class="panel ops-form-panel"><div class="panel-kicker">OFFICIAL MERCHANDISE</div><h2><?= isset($formValues['id']) ? 'Edit product' : 'Add a product' ?></h2><p class="panel-intro">Set the public catalog price and available stock. Product details and price are snapshotted when an order is placed.</p>
                <form method="post" action="/admin/products/save" class="form-stack">
                    <?= yuc_csrf_field() ?><input type="hidden" name="id" value="<?= yuc_e($formValues['id'] ?? '0') ?>">
                    <div class="field-group"><label for="shop-product-name">Product name</label><input id="shop-product-name" name="name" required maxlength="140" value="<?= yuc_e($formValues['name'] ?? '') ?>"></div>
                    <div class="field-group"><label for="shop-product-sku">SKU</label><input id="shop-product-sku" name="sku" required maxlength="60" pattern="[A-Za-z0-9][A-Za-z0-9._-]{0,59}" value="<?= yuc_e($formValues['sku'] ?? '') ?>" placeholder="YUC-JERSEY-01"></div>
                    <div class="field-group"><label for="shop-product-description">Description</label><textarea id="shop-product-description" name="description" rows="4" maxlength="1000"><?= yuc_e($formValues['description'] ?? '') ?></textarea></div>
                    <div class="form-grid"><div class="field-group"><label for="shop-product-price">Price (NGN)</label><input id="shop-product-price" name="price" type="number" min="0.01" max="9999999999.99" step="0.01" required value="<?= yuc_e($formValues['price_amount'] ?? '') ?>"></div><div class="field-group"><label for="shop-product-stock">Available stock</label><input id="shop-product-stock" name="stock_quantity" type="number" min="0" max="2000000000" step="1" required value="<?= yuc_e($formValues['stock_quantity'] ?? '0') ?>"></div></div>
                    <div class="field-group"><label for="shop-product-status">Catalog status</label><select id="shop-product-status" name="status"><option value="active" <?= ($formValues['status'] ?? 'active') === 'active' ? 'selected' : '' ?>>Active</option><option value="inactive" <?= ($formValues['status'] ?? '') === 'inactive' ? 'selected' : '' ?>>Inactive</option></select></div>
                    <div class="form-actions"><a class="button button-quiet" href="/admin/products">Clear</a><button class="button button-primary" type="submit">Save product</button></div>
                </form>
            </section>
            <section class="panel ops-table-panel"><div class="panel-topline"><div><div class="panel-kicker">CURRENT CATALOG</div><h2>Shop products</h2></div><span class="readiness-pill is-ready"><span></span><?= count($rows) ?> PRODUCTS</span></div>
                <div class="responsive-table"><table class="data-table ops-table"><thead><tr><th>Product / SKU</th><th>Price</th><th>Available</th><th>Status</th><th>Manage</th></tr></thead><tbody>
                <?php if ($rows === []): ?><tr><td colspan="5" class="empty-state">No products yet. Add merchandise to make the public shop available.</td></tr><?php else: foreach ($rows as $row): ?>
                    <tr data-search-text="<?= yuc_e($row['description'] ?? '') ?>"><td><strong><?= yuc_e($row['name']) ?></strong><small><?= yuc_e($row['sku']) ?></small></td><td>₦<?= number_format((int) $row['price_kobo'] / 100, 2) ?></td><td><?= (int) $row['stock_quantity'] ?></td><td><span class="outcome-badge outcome-<?= yuc_e($row['status']) ?>"><?= yuc_e(strtoupper((string) $row['status'])) ?></span></td><td class="table-actions"><a class="table-action" href="/admin/products?edit=<?= (int) $row['id'] ?>">Edit</a><form method="post" action="/admin/products/delete" data-confirm="<?= (int) ($row['order_item_count'] ?? 0) > 0 ? 'This product has order history. It will be archived from the public catalog, not erased.' : 'Permanently delete this product? This cannot be undone.' ?>"><?= yuc_csrf_field() ?><input type="hidden" name="id" value="<?= (int) $row['id'] ?>"><button class="table-action table-action-danger" type="submit"><?= (int) ($row['order_item_count'] ?? 0) > 0 ? 'Archive' : 'Delete' ?></button></form></td></tr>
                <?php endforeach; endif; ?></tbody></table></div>
            </section>
        </div>

    <?php elseif ($resource === 'orders'): ?>
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
            <summary><div><span class="panel-kicker">SAFE ORDER CREATION</span><strong>Create a shop order</strong><small>Reserve stock and initialize a verified PayHub checkout</small></div><span class="readiness-pill <?= $payHubConfigured && $orderProducts !== [] ? 'is-ready' : 'is-warning' ?>"><span></span>NEW ORDER</span></summary>
            <p class="panel-intro">The order starts as pending payment. Inventory is reserved atomically, payment is initialized with PayHub, and no order is marked paid until server-side verification succeeds. Open the saved checkout link from the order list to share it with the customer.</p>
            <?php if (!$payHubConfigured): ?><div class="alert alert-error" role="status"><span class="alert-icon" aria-hidden="true">!</span><p>PayHub is not configured. Add the secret under Settings before creating shop orders.</p></div><?php elseif ($orderProducts === []): ?><div class="alert alert-error" role="status"><span class="alert-icon" aria-hidden="true">!</span><p>No active products with available stock can be ordered right now.</p></div><?php endif; ?>
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
                <div class="form-actions"><button class="button button-primary" type="submit" <?= !$payHubConfigured || $orderProducts === [] ? 'disabled' : '' ?>>Create order &amp; initialize PayHub</button></div>
            </form>
        </details>
        <section class="panel ops-table-panel full-ops-panel"><div class="panel-topline"><div><div class="panel-kicker">PAYMENT &amp; FULFILLMENT</div><h2><?= $showArchived ? 'Archived shop orders' : 'Shop orders' ?></h2></div><div class="panel-topline-actions"><span class="readiness-pill is-ready"><span></span><?= count($rows) ?> ORDERS</span><a class="table-action" href="/admin/orders?show_archived=<?= $showArchived ? '0' : '1' ?>"><?= $showArchived ? 'Show active' : 'Show archived' ?></a></div></div>
            <p class="panel-intro">Admin-created orders use the same checkout, atomic stock reservation, and PayHub verification path as public orders. Edit only customer/fulfillment details; archive only fulfilled, failed, or cancelled orders. Payment history is never deleted.</p>
            <div class="responsive-table"><table class="data-table ops-table shop-orders-table"><thead><tr><th>Order / items</th><th>Customer &amp; fulfillment</th><th>Total</th><th>PayHub reference</th><th>Order status</th><th>Next action</th><th>Manage</th></tr></thead><tbody>
            <?php if ($rows === []): ?><tr><td colspan="7" class="empty-state">No shop orders have been placed.</td></tr><?php else: foreach ($rows as $row):
                $statusOptions = !empty($row['archived_at']) ? [] : match ((string) $row['status']) {
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
                    <td class="table-actions"><?php if ($row['status'] === 'pending_payment' && !empty($row['checkout_url']) && \Yuc\Services\PayHubClient::isTrustedCheckoutUrl((string) $row['checkout_url'])): ?><a class="table-action" href="<?= yuc_e($row['checkout_url']) ?>" target="_blank" rel="noopener noreferrer">Open PayHub checkout</a><?php endif; ?><?php if (empty($row['archived_at'])): ?><a class="table-action" href="/admin/orders?edit=<?= (int) $row['id'] ?>">Edit details</a><?php endif; ?><?php if (!empty($row['archived_at']) || in_array((string) $row['status'], ['fulfilled','payment_failed','cancelled'], true)): ?><form method="post" action="/admin/orders/archive" data-confirm="<?= !empty($row['archived_at']) ? 'Restore this order to the active list?' : 'Archive this closed order from operations? The order, payment record, and audit history will be preserved.' ?>"><?= yuc_csrf_field() ?><input type="hidden" name="id" value="<?= (int) $row['id'] ?>"><input type="hidden" name="archived" value="<?= empty($row['archived_at']) ? '1' : '0' ?>"><button class="table-action <?= empty($row['archived_at']) ? 'table-action-danger' : '' ?>" type="submit"><?= empty($row['archived_at']) ? 'Archive' : 'Restore' ?></button></form><?php else: ?><span class="table-action-locked">Close/fulfill first</span><?php endif; ?></td></tr>
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
                <p class="panel-intro payhub-settings-note">Status: <strong><?= !empty($payHubConfigured) ? 'Secret key configured' : 'Not configured' ?></strong>. The secret stays server-side in the protected local configuration file and is never sent to the browser. Leave blank to keep the current key.</p>
                <div class="field-group"><label for="payhub-secret-key">PayHub secret key</label><input id="payhub-secret-key" name="payhub_secret_key" type="password" maxlength="512" autocomplete="new-password" placeholder="Enter the PayHub secret key"></div>
                <?php if (!empty($payHubConfigured)): ?><label class="settings-checkbox"><input type="checkbox" name="payhub_clear_secret" value="1"> Remove the saved PayHub key and disable new checkout</label><?php endif; ?>
                <div class="form-actions"><button class="button button-primary" type="submit">Save settings</button><a class="button button-outline" href="/admin">Cancel</a></div>
            </form>
        </section>
    <?php endif; ?>
</section>
