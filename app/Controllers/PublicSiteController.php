<?php

declare(strict_types=1);

namespace Yuc\Controllers;

use PDO;
use Throwable;
use Yuc\Core\View;
use Yuc\Services\EnvironmentModeService;
use Yuc\Services\HeroService;
use Yuc\Services\LiveStreamService;
use Yuc\Services\TournamentService;

final class PublicSiteController
{
    private TournamentService $tournament;
    private EnvironmentModeService $environmentMode;
    private HeroService $hero;
    private LiveStreamService $liveStream;

    /** @param array<string,mixed> $config */
    public function __construct(PDO $pdo, private array $config)
    {
        $this->tournament = new TournamentService($pdo, $config);
        $this->environmentMode = new EnvironmentModeService($pdo);
        $this->hero = new HeroService($pdo);
        $this->liveStream = new LiveStreamService($pdo);
    }

    public function home(): void
    {
        $settings = $this->tournament->settings();
        $teams = $this->tournament->publicTeams();
        $upcoming = $this->tournament->fixtures('upcoming');
        $venues = $this->tournament->venues(true);
        $playerCount = array_sum(array_map(static fn (array $team): int => (int) ($team['player_count'] ?? 0), $teams));
        $siteTitle = trim((string) ($settings['site_title'] ?? 'Youth Unity Cup'));

        View::render('public-home', [
            'title' => ($siteTitle !== '' ? $siteTitle : 'Youth Unity Cup') . ' · Official site',
            'topNote' => 'OFFICIAL TOURNAMENT INFORMATION',
            'bodyClass' => 'public-data-page',
            'description' => 'The official Youth Unity Cup home for tournament news, teams, fixtures, results, venues, registration, and merchandise.',
            'siteTitle' => $siteTitle,
            'heroSettings' => $this->hero->settings(),
            'liveStream' => $this->liveStream->settings(),
            'siteMode' => $this->environmentMode->currentMode(),
            'teamCount' => count($teams),
            'playerCount' => $playerCount,
            'fixtureCount' => count($upcoming),
            'venueCount' => count($venues),
            'appTimezone' => (string) ($this->config['app']['timezone'] ?? 'Africa/Lagos'),
        ]);
    }

    public function heroMedia(): void
    {
        $fileInput = $_GET['file'] ?? '';
        $filename = is_string($fileInput) ? $fileInput : '';
        $media = $this->hero->findStoredMedia($filename);
        if ($media === null) {
            http_response_code(404);
            header('Content-Type: text/plain; charset=UTF-8');
            echo 'Hero media not found.';
            return;
        }

        $size = $media['size'];
        $start = 0;
        $end = $size - 1;
        $rangeInput = $_SERVER['HTTP_RANGE'] ?? '';
        $range = is_string($rangeInput) ? trim($rangeInput) : '';
        if ($range !== '') {
            if (preg_match('/^bytes=([0-9]*)-([0-9]*)$/D', $range, $matches) !== 1
                || ($matches[1] === '' && $matches[2] === '')) {
                $this->unsatisfiedHeroRange($size);
            }
            if ($matches[1] === '') {
                $suffix = $this->heroRangeInteger($matches[2]);
                if ($suffix === null || $suffix < 1) {
                    $this->unsatisfiedHeroRange($size);
                }
                $start = max(0, $size - $suffix);
            } else {
                $startValue = $this->heroRangeInteger($matches[1]);
                $endValue = $matches[2] === '' ? $size - 1 : $this->heroRangeInteger($matches[2]);
                if ($startValue === null || $endValue === null || $startValue < 0 || $endValue < $startValue || $startValue >= $size) {
                    $this->unsatisfiedHeroRange($size);
                }
                $start = $startValue;
                $end = min($size - 1, $endValue);
            }
            http_response_code(206);
            header('Content-Range: bytes ' . $start . '-' . $end . '/' . $size);
        } else {
            http_response_code(200);
        }

        $modified = filemtime($media['path']) ?: time();
        $etag = '"' . $filename . '-' . $size . '-' . $modified . '"';
        header('Content-Type: ' . $media['mime']);
        header('Content-Length: ' . ($end - $start + 1));
        header('Content-Disposition: inline; filename="hero-media.' . pathinfo($filename, PATHINFO_EXTENSION) . '"');
        header('Accept-Ranges: bytes');
        header('Cache-Control: public, max-age=31536000, immutable');
        header('Last-Modified: ' . gmdate('D, d M Y H:i:s', $modified) . ' GMT');
        header('ETag: ' . $etag);
        header('X-Content-Type-Options: nosniff');
        header('Cross-Origin-Resource-Policy: same-site');
        if ($range === '' && trim((string) ($_SERVER['HTTP_IF_NONE_MATCH'] ?? '')) === $etag) {
            http_response_code(304);
            header_remove('Content-Length');
            return;
        }

        $stream = @fopen($media['path'], 'rb');
        if (!is_resource($stream) || fseek($stream, $start) !== 0) {
            if (is_resource($stream)) {
                fclose($stream);
            }
            http_response_code(500);
            return;
        }
        $remaining = $end - $start + 1;
        while ($remaining > 0 && !feof($stream)) {
            $chunk = fread($stream, min(65536, $remaining));
            if (!is_string($chunk) || $chunk === '') {
                break;
            }
            echo $chunk;
            $remaining -= strlen($chunk);
        }
        fclose($stream);
    }

    public function teams(): void
    {
        $this->renderList(
            'teams',
            'Teams',
            'Meet the local teams representing communities across Mushin.',
            $this->tournament->publicTeams(),
            ['teamGroups' => $this->tournament->publicTeamGroups()]
        );
    }

    public function team(): void
    {
        $idInput = $_GET['id'] ?? null;
        $id = filter_var(is_scalar($idInput) ? $idInput : null, FILTER_VALIDATE_INT);
        $team = $id !== false && $id > 0 ? $this->tournament->findPublicTeam($id) : null;
        if ($team === null) {
            http_response_code(404);
            View::render('not-found', [
                'title' => 'Team not found · Youth Unity Cup',
                'bodyClass' => 'public-data-page',
                'topNote' => 'OFFICIAL TOURNAMENT INFORMATION',
                'siteMode' => $this->environmentMode->currentMode(),
            ]);
            return;
        }

        $settings = $this->tournament->settings();
        View::render('public-team', [
            'title' => $team['name'] . ' · Youth Unity Cup',
            'topNote' => 'TEAM PROFILE',
            'bodyClass' => 'public-data-page public-team-page',
            'team' => $team,
            'players' => $this->tournament->publicTeamPlayers((int) $team['id']),
            'contactEmail' => $settings['contact_email'] ?? '',
            'siteMode' => $this->environmentMode->currentMode(),
            'appTimezone' => (string) ($this->config['app']['timezone'] ?? 'Africa/Lagos'),
        ]);
    }

    public function fixtures(): void
    {
        $this->renderList('fixtures', 'Fixtures', 'Upcoming matches, kick-off times, and confirmed venues.', $this->tournament->fixtures('upcoming'));
    }

    public function results(): void
    {
        $this->renderList('results', 'Results', 'Completed matches and verified scores from the tournament.', $this->tournament->fixtures('results'));
    }

    public function venues(): void
    {
        $this->renderList('venues', 'Venues', 'Match locations, zones, and safe spectator capacity.', $this->tournament->venues(true));
    }

    public function registration(): void
    {
        $settings = $this->tournament->settings();
        View::render('public-registration', [
            'title' => 'Registration · ' . ($settings['site_title'] ?? 'Youth Unity Cup'),
            'topNote' => 'OFFICIAL TOURNAMENT INFORMATION',
            'bodyClass' => 'public-data-page',
            'flash' => yuc_take_flash(),
            'contactEmail' => $settings['contact_email'] ?? '',
            'siteMode' => $this->environmentMode->currentMode(),
            'appTimezone' => (string) ($this->config['app']['timezone'] ?? 'Africa/Lagos'),
        ]);
    }

    public function submitRegistration(): void
    {
        if (!yuc_verify_csrf()) {
            yuc_flash('error', 'Your form session expired. Refresh and submit again.');
            yuc_redirect('/registration');
        }
        if ($this->environmentMode->isDemo()) {
            yuc_flash('error', 'Registration is paused while the site is in Demo mode. Switch to Production to accept live applications.');
            yuc_redirect('/registration');
        }
        if (trim((string) ($_POST['company_website'] ?? '')) !== '') {
            yuc_redirect('/registration');
        }

        try {
            $reference = $this->tournament->register($_POST, yuc_client_ip());
            yuc_flash('success', 'Registration received. Save your reference: ' . $reference . '.');
        } catch (\InvalidArgumentException $exception) {
            yuc_flash('error', $exception->getMessage());
        } catch (Throwable $exception) {
            error_log('Youth Unity Cup public registration failed (' . get_class($exception) . ').');
            yuc_flash('error', 'We could not save your registration right now. Please try again later.');
        }
        yuc_redirect('/registration');
    }

    private function heroRangeInteger(string $value): ?int
    {
        if ($value === '' || preg_match('/^[0-9]{1,20}$/D', $value) !== 1) {
            return null;
        }
        $normalized = ltrim($value, '0');
        if ($normalized === '') {
            return 0;
        }
        $maximum = (string) PHP_INT_MAX;
        if (strlen($normalized) > strlen($maximum)
            || (strlen($normalized) === strlen($maximum) && strcmp($normalized, $maximum) > 0)) {
            return null;
        }
        return (int) $normalized;
    }

    private function unsatisfiedHeroRange(int $size): never
    {
        http_response_code(416);
        header('Content-Range: bytes */' . $size);
        header('Content-Length: 0');
        exit;
    }

    /** @param list<array<string,mixed>> $rows @param array<string,mixed> $extra */
    private function renderList(string $resource, string $heading, string $description, array $rows, array $extra = []): void
    {
        $settings = $this->tournament->settings();
        View::render('public-data', array_merge([
            'title' => $heading . ' · ' . ($settings['site_title'] ?? 'Youth Unity Cup'),
            'topNote' => 'OFFICIAL TOURNAMENT INFORMATION',
            'bodyClass' => 'public-data-page',
            'resource' => $resource,
            'heading' => $heading,
            'description' => $description,
            'rows' => $rows,
            'contactEmail' => $settings['contact_email'] ?? '',
            'siteMode' => $this->environmentMode->currentMode(),
            'appTimezone' => (string) ($this->config['app']['timezone'] ?? 'Africa/Lagos'),
        ], $extra));
    }
}
