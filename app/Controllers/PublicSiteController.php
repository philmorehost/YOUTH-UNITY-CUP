<?php

declare(strict_types=1);

namespace Yuc\Controllers;

use PDO;
use Throwable;
use Yuc\Core\View;
use Yuc\Services\TournamentService;

final class PublicSiteController
{
    private TournamentService $tournament;

    /** @param array<string,mixed> $config */
    public function __construct(PDO $pdo, private array $config)
    {
        $this->tournament = new TournamentService($pdo, $config);
    }

    public function teams(): void
    {
        $this->renderList('teams', 'Teams', 'Meet the local teams representing communities across Mushin.', $this->tournament->publicTeams());
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
            'appTimezone' => (string) ($this->config['app']['timezone'] ?? 'UTC'),
        ]);
    }

    public function submitRegistration(): void
    {
        if (!yuc_verify_csrf()) {
            yuc_flash('error', 'Your form session expired. Refresh and submit again.');
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

    /** @param list<array<string,mixed>> $rows */
    private function renderList(string $resource, string $heading, string $description, array $rows): void
    {
        $settings = $this->tournament->settings();
        View::render('public-data', [
            'title' => $heading . ' · ' . ($settings['site_title'] ?? 'Youth Unity Cup'),
            'topNote' => 'OFFICIAL TOURNAMENT INFORMATION',
            'bodyClass' => 'public-data-page',
            'resource' => $resource,
            'heading' => $heading,
            'description' => $description,
            'rows' => $rows,
            'contactEmail' => $settings['contact_email'] ?? '',
            'appTimezone' => (string) ($this->config['app']['timezone'] ?? 'UTC'),
        ]);
    }
}
