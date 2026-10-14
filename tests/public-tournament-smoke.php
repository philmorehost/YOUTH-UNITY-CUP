<?php

declare(strict_types=1);

use Yuc\Core\View;
use Yuc\Services\EnvironmentModeService;

require dirname(__DIR__) . '/app/bootstrap.php';

function renderTournamentTemplate(string $template, array $data): string
{
    ob_start();
    View::render($template, $data);
    return (string) ob_get_clean();
}

function expectTournamentMarkup(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$demoTeams = EnvironmentModeService::demoTeams();
$groups = [];
foreach ($demoTeams as $team) {
    $groups[$team['group_name']] = ($groups[$team['group_name']] ?? 0) + 1;
}
expectTournamentMarkup(count($demoTeams) === 16, 'Demo data must contain exactly 16 teams.');
expectTournamentMarkup($groups === ['A' => 4, 'B' => 4, 'C' => 4, 'D' => 4], 'Demo teams must be balanced across four groups.');
expectTournamentMarkup(count(EnvironmentModeService::demoVenues()) >= 4, 'Demo fixture data must have usable venues.');

$currentYear = yuc_current_year('UTC');
$home = renderTournamentTemplate('public-home', [
    'title' => 'Youth Unity Cup · Official site',
    'topNote' => 'OFFICIAL TOURNAMENT INFORMATION',
    'bodyClass' => 'public-data-page',
    'siteTitle' => 'Youth Unity Cup',
    'siteMode' => 'demo',
    'appTimezone' => 'UTC',
    'teamCount' => 16,
    'playerCount' => 176,
    'fixtureCount' => 20,
    'venueCount' => 8,
]);
expectTournamentMarkup(str_contains($home, 'Football brings'), 'The sport-inspired landing page headline is missing.');
$appCss = file_get_contents(dirname(__DIR__) . '/public/assets/app.css') ?: '';
expectTournamentMarkup(str_contains($appCss, 'yuc-hero.jpg'), 'The full-bleed sports hero image is not referenced.');
expectTournamentMarkup(str_contains($home, $currentYear), 'The public site year is not generated dynamically.');
expectTournamentMarkup(str_contains($home, 'DEMO MODE'), 'The shared layout does not show the demo-mode notice.');

$teamRows = [];
$teamStandings = [];
foreach (array_slice($demoTeams, 0, 4) as $index => $team) {
    $row = [
        'id' => $index + 1,
        'name' => $team['name'],
        'zone' => $team['zone'],
        'group_name' => 'A',
        'player_count' => 11,
        'played' => 1,
        'won' => $index === 0 ? 1 : 0,
        'drawn' => 0,
        'lost' => $index === 1 ? 1 : 0,
        'goals_for' => $index === 0 ? 2 : 0,
        'goals_against' => $index === 1 ? 2 : 0,
        'goal_difference' => $index === 0 ? 2 : ($index === 1 ? -2 : 0),
        'points' => $index === 0 ? 3 : 0,
    ];
    $teamRows[] = $row;
    $teamStandings[] = $row;
}
$teamsPage = renderTournamentTemplate('public-data', [
    'title' => 'Teams · Youth Unity Cup',
    'topNote' => 'OFFICIAL TOURNAMENT INFORMATION',
    'bodyClass' => 'public-data-page',
    'resource' => 'teams',
    'heading' => 'Teams',
    'description' => 'Group standings and squads.',
    'rows' => $teamRows,
    'teamGroups' => [['name' => 'A', 'teams' => $teamStandings]],
    'contactEmail' => '',
    'appTimezone' => 'UTC',
]);
expectTournamentMarkup(str_contains($teamsPage, 'Group A'), 'The grouped standings page is missing a group table.');
expectTournamentMarkup(str_contains($teamsPage, 'Pts'), 'The standings table is missing its points column.');
expectTournamentMarkup(str_contains($teamsPage, '/team?id=1'), 'Standings team names do not link to squad profiles.');

$profile = renderTournamentTemplate('public-team', [
    'title' => 'Unity Stars · Youth Unity Cup',
    'topNote' => 'TEAM PROFILE',
    'bodyClass' => 'public-data-page public-team-page',
    'siteMode' => 'production',
    'appTimezone' => 'UTC',
    'team' => ['id' => 1, 'name' => 'Unity Stars', 'zone' => 'Mushin Central', 'group_name' => 'A', 'player_count' => 1],
    'players' => [[
        'id' => 1,
        'full_name' => 'Ayo Adeyemi',
        'jersey_number' => 8,
        'position' => 'Midfielder',
        'age' => 17,
        'hometown' => 'Mushin, Lagos',
        'bio' => 'A creative player who finds space and moves the ball quickly.',
        'photo_url' => '',
        'avatar_variant' => 2,
    ]],
    'contactEmail' => '',
]);
expectTournamentMarkup(str_contains($profile, 'Ayo Adeyemi') && str_contains($profile, '#8'), 'The public team profile is missing player details.');
expectTournamentMarkup(str_contains($profile, 'player-portrait-art'), 'A local player-headshot fallback is missing.');

$adminPlayers = renderTournamentTemplate('admin-manage', [
    'title' => 'Player profiles · Youth Unity Cup Admin',
    'topNote' => 'ADMIN CONTROL ROOM',
    'bodyClass' => 'admin-page',
    'admin' => ['id' => 1, 'email' => 'admin@example.test', 'username' => 'admin'],
    'resource' => 'players',
    'siteMode' => 'production',
    'rows' => [[
        'id' => 1,
        'team_id' => 1,
        'full_name' => 'Ayo Adeyemi',
        'jersey_number' => 8,
        'position' => 'Midfielder',
        'age' => 17,
        'hometown' => 'Mushin, Lagos',
        'bio' => 'Creative midfielder.',
        'photo_url' => '',
        'avatar_variant' => 2,
        'status' => 'active',
        'team_name' => 'Unity Stars',
        'team_zone' => 'Mushin Central',
    ]],
    'formValues' => [],
    'teams' => [['id' => 1, 'name' => 'Unity Stars', 'zone' => 'Mushin Central']],
    'venues' => [],
    'settings' => [],
    'mail' => [],
    'payHubConfigured' => false,
    'appTimezone' => 'UTC',
    'flash' => null,
    'auditTotal' => 0,
    'auditPage' => 1,
    'auditPages' => 1,
    'auditSearch' => '',
    'auditCategory' => '',
]);
expectTournamentMarkup(str_contains($adminPlayers, 'action="/admin/players/save"'), 'Admin player profile form is missing.');
expectTournamentMarkup(str_contains($adminPlayers, 'HTTPS only'), 'Player headshots are not restricted to secure image URLs.');
expectTournamentMarkup(str_contains($adminPlayers, 'Ayo Adeyemi'), 'Admin player roster list is missing its player row.');

$registration = renderTournamentTemplate('public-registration', [
    'title' => 'Registration · Youth Unity Cup',
    'topNote' => 'OFFICIAL TOURNAMENT INFORMATION',
    'bodyClass' => 'public-data-page',
    'siteMode' => 'demo',
    'appTimezone' => 'UTC',
    'flash' => null,
    'contactEmail' => '',
]);
expectTournamentMarkup(str_contains($registration, 'Registration is paused in Demo mode'), 'Demo registration pause notice is missing.');
expectTournamentMarkup(!str_contains($registration, 'action="/registration" class="form-stack public-registration-form"'), 'Public registration remains open during Demo mode.');

$dashboard = renderTournamentTemplate('admin', [
    'title' => 'Admin dashboard · Youth Unity Cup',
    'topNote' => 'ADMIN CONTROL ROOM',
    'bodyClass' => 'admin-page',
    'siteMode' => 'production',
    'appTimezone' => 'UTC',
    'admin' => ['id' => 1, 'full_name' => 'Admin Sample', 'email' => 'admin@example.test', 'username' => 'admin'],
    'emailConfigured' => false,
    'tournamentCounts' => ['teams' => 16, 'players' => 176, 'venues' => 8, 'fixtures' => 24, 'registrations' => 0, 'transactions' => 0, 'blocked_ips' => 0],
    'outboxCounts' => ['queued' => 0, 'sent' => 0, 'failed' => 0],
    'recentNotifications' => [],
    'history' => [],
    'flash' => null,
]);
expectTournamentMarkup(str_contains($dashboard, 'action="/admin/site-mode"') && str_contains($dashboard, 'Switch to Demo'), 'Admin reversible demo-mode control is missing.');
expectTournamentMarkup(str_contains($dashboard, 'private database snapshot') && str_contains($dashboard, 'Registrations, payments'), 'Admin mode control does not explain production-data protection.');

fwrite(STDOUT, "Public tournament, team profile and demo-mode smoke checks passed.\n");
