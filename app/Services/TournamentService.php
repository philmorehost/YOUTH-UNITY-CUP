<?php

declare(strict_types=1);

namespace Yuc\Services;

use InvalidArgumentException;
use PDO;
use Throwable;

final class TournamentService
{
    private NotificationService $notifications;

    /** @param array<string,mixed> $config */
    public function __construct(private PDO $pdo, array $config)
    {
        $this->notifications = new NotificationService($pdo, $config);
    }

    /** @return list<array<string,mixed>> */
    public function teams(bool $activeOnly = false): array
    {
        $sql = 'SELECT id, name, zone, group_name, contact_email, status, notes FROM teams';
        if ($activeOnly) {
            $sql .= " WHERE status = 'active'";
        }
        $sql .= ' ORDER BY zone, name';
        return $this->pdo->query($sql)->fetchAll();
    }

    /** @return list<array<string,mixed>> */
    public function publicTeams(): array
    {
        return $this->pdo->query(
            "SELECT id, name, zone, group_name FROM teams WHERE status='active' ORDER BY zone, name LIMIT 200"
        )->fetchAll();
    }

    /** @return list<array<string,mixed>> */
    public function venues(bool $activeOnly = false): array
    {
        $sql = 'SELECT id, name, zone, address, capacity, status FROM venues';
        if ($activeOnly) {
            $sql .= " WHERE status = 'active'";
        }
        $sql .= ' ORDER BY zone, name';
        return $this->pdo->query($sql)->fetchAll();
    }

    /** @return list<array<string,mixed>> */
    public function fixtures(string $view = 'all'): array
    {
        $where = match ($view) {
            'upcoming' => "WHERE f.status = 'live' OR (f.status IN ('scheduled', 'postponed') AND f.kickoff_at >= UTC_TIMESTAMP())",
            'results' => "WHERE f.status = 'completed'",
            default => '',
        };
        $direction = $view === 'results' ? 'DESC' : 'ASC';
        $statement = $this->pdo->query(
            'SELECT f.id, f.stage, f.kickoff_at, f.status, f.home_score, f.away_score, f.notes, '
            . 'ht.name AS home_team, ht.zone AS home_zone, at.name AS away_team, at.zone AS away_zone, '
            . 'v.name AS venue_name, v.zone AS venue_zone '
            . 'FROM fixtures f INNER JOIN teams ht ON ht.id = f.home_team_id '
            . 'INNER JOIN teams at ON at.id = f.away_team_id '
            . 'LEFT JOIN venues v ON v.id = f.venue_id ' . $where . ' ORDER BY f.kickoff_at ' . $direction . ' LIMIT 200'
        );
        return $statement->fetchAll();
    }

    /** @return list<array<string,mixed>> */
    public function adminFixtures(): array
    {
        return $this->pdo->query(
            'SELECT f.id, f.home_team_id, f.away_team_id, f.venue_id, f.stage, f.kickoff_at, f.status, '
            . 'f.home_score, f.away_score, f.notes, ht.name AS home_team, at.name AS away_team, v.name AS venue_name '
            . 'FROM fixtures f INNER JOIN teams ht ON ht.id = f.home_team_id '
            . 'INNER JOIN teams at ON at.id = f.away_team_id LEFT JOIN venues v ON v.id = f.venue_id '
            . 'ORDER BY f.kickoff_at DESC LIMIT 300'
        )->fetchAll();
    }

    /** @return list<array<string,mixed>> */
    public function registrations(): array
    {
        return $this->pdo->query(
            'SELECT id, reference, full_name, email, phone, category, age, zone, team_name, details, status, submitted_at '
            . 'FROM registrations ORDER BY submitted_at DESC LIMIT 500'
        )->fetchAll();
    }

    /** @return array{entries:list<array<string,mixed>>,total:int,page:int,pages:int} */
    public function auditEntries(int $page = 1, string $search = '', string $category = ''): array
    {
        $page = max(1, min(10000, $page));
        $search = mb_substr(trim($search), 0, 120);
        $categories = ['auth', 'security', 'system', 'registration', 'transaction', 'tournament'];
        if (!in_array($category, $categories, true)) {
            $category = '';
        }

        $filters = [];
        $parameters = [];
        if ($category !== '') {
            $filters[] = 'a.event_key LIKE :event_prefix';
            $parameters['event_prefix'] = $category . '.%';
        }
        if ($search !== '') {
            $filters[] = '(a.description LIKE :description_search OR a.event_key LIKE :event_search OR a.ip_address LIKE :ip_search)';
            $term = '%' . $search . '%';
            $parameters['description_search'] = $term;
            $parameters['event_search'] = $term;
            $parameters['ip_search'] = $term;
        }
        $where = $filters === [] ? '' : ' WHERE ' . implode(' AND ', $filters);
        $count = $this->pdo->prepare('SELECT COUNT(*) FROM audit_logs a' . $where);
        $count->execute($parameters);
        $total = (int) $count->fetchColumn();
        $pages = max(1, (int) ceil($total / 50));
        $page = min($page, $pages);
        $offset = ($page - 1) * 50;
        $statement = $this->pdo->prepare(
            'SELECT a.id, a.user_id, a.event_key, a.description, a.ip_address, a.context_json, a.created_at, '
            . 'u.full_name AS actor_name, u.username AS actor_username '
            . 'FROM audit_logs a LEFT JOIN users u ON u.id=a.user_id' . $where
            . ' ORDER BY a.id DESC LIMIT 50 OFFSET ' . $offset
        );
        $statement->execute($parameters);
        return ['entries' => $statement->fetchAll(), 'total' => $total, 'page' => $page, 'pages' => $pages];
    }

    /** @return list<array<string,mixed>> */
    public function blockedIps(): array
    {
        return $this->pdo->query(
            'SELECT ip_address, blocked_until, reason, created_at FROM ip_blocks '
            . 'WHERE blocked_until > UTC_TIMESTAMP() ORDER BY blocked_until DESC LIMIT 300'
        )->fetchAll();
    }

    public function unblockIp(string $ipAddress, int $adminId): void
    {
        $ipAddress = trim($ipAddress);
        if ($ipAddress !== 'unknown' && filter_var($ipAddress, FILTER_VALIDATE_IP) === false) {
            throw new InvalidArgumentException('The selected IP address is not valid.');
        }
        $statement = $this->pdo->prepare('DELETE FROM ip_blocks WHERE ip_address=:ip');
        $statement->execute(['ip' => $ipAddress]);
        if ($statement->rowCount() > 0) {
            $this->audit($adminId, 'security.ip_unblocked', 'Removed temporary IP block for ' . $ipAddress);
        }
    }

    /** @return list<array<string,mixed>> */
    public function transactions(): array
    {
        return $this->pdo->query(
            'SELECT id, reference, recipient_email, amount, currency, status, payment_provider, provider_reference, description, created_at '
            . 'FROM transactions ORDER BY created_at DESC LIMIT 300'
        )->fetchAll();
    }

    /** @return array<string,mixed>|null */
    public function find(string $resource, int $id): ?array
    {
        $table = match ($resource) {
            'teams' => 'teams',
            'venues' => 'venues',
            'fixtures' => 'fixtures',
            default => throw new InvalidArgumentException('That record type cannot be edited.'),
        };
        $statement = $this->pdo->prepare('SELECT * FROM ' . $table . ' WHERE id = :id LIMIT 1');
        $statement->execute(['id' => $id]);
        $row = $statement->fetch();
        return is_array($row) ? $row : null;
    }

    /** @param array<string,mixed> $data */
    public function saveTeam(array $data, int $adminId): void
    {
        $id = max(0, (int) ($data['id'] ?? 0));
        $name = $this->text($data['name'] ?? '', 120, 'Team name');
        $zone = $this->text($data['zone'] ?? '', 50, 'Zone');
        $group = strtoupper(trim((string) ($data['group_name'] ?? '')));
        if ($group !== '' && mb_strlen($group) > 20) {
            throw new InvalidArgumentException('Group name must be 20 characters or fewer.');
        }
        $email = trim((string) ($data['contact_email'] ?? ''));
        if ($email !== '' && (strlen($email) > 190 || filter_var($email, FILTER_VALIDATE_EMAIL) === false)) {
            throw new InvalidArgumentException('Enter a valid team contact email.');
        }
        $status = $this->oneOf($data['status'] ?? 'active', ['active', 'inactive'], 'Team status');
        $notes = mb_substr(trim((string) ($data['notes'] ?? '')), 0, 500);

        if ($id > 0) {
            $statement = $this->pdo->prepare(
                'UPDATE teams SET name=:name, zone=:zone, group_name=:group_name, contact_email=:email, status=:status, notes=:notes WHERE id=:id'
            );
            $statement->execute(['name' => $name, 'zone' => $zone, 'group_name' => $group !== '' ? $group : null, 'email' => $email, 'status' => $status, 'notes' => $notes, 'id' => $id]);
            $this->audit($adminId, 'tournament.team_updated', 'Updated team ' . $name);
            return;
        }

        $statement = $this->pdo->prepare(
            'INSERT INTO teams (name, zone, group_name, contact_email, status, notes, created_at, updated_at) '
            . 'VALUES (:name, :zone, :group_name, :email, :status, :notes, UTC_TIMESTAMP(), UTC_TIMESTAMP())'
        );
        $statement->execute(['name' => $name, 'zone' => $zone, 'group_name' => $group !== '' ? $group : null, 'email' => $email, 'status' => $status, 'notes' => $notes]);
        $this->audit($adminId, 'tournament.team_created', 'Created team ' . $name);
    }

    /** @param array<string,mixed> $data */
    public function saveVenue(array $data, int $adminId): void
    {
        $id = max(0, (int) ($data['id'] ?? 0));
        $name = $this->text($data['name'] ?? '', 140, 'Venue name');
        $zone = $this->text($data['zone'] ?? '', 50, 'Zone');
        $address = mb_substr(trim((string) ($data['address'] ?? '')), 0, 255);
        $capacity = filter_var($data['capacity'] ?? 700, FILTER_VALIDATE_INT);
        if ($capacity === false || $capacity < 1 || $capacity > 50000) {
            throw new InvalidArgumentException('Venue capacity must be between 1 and 50,000.');
        }
        $status = $this->oneOf($data['status'] ?? 'active', ['active', 'inactive'], 'Venue status');

        if ($id > 0) {
            $statement = $this->pdo->prepare('UPDATE venues SET name=:name, zone=:zone, address=:address, capacity=:capacity, status=:status WHERE id=:id');
            $statement->execute(['name' => $name, 'zone' => $zone, 'address' => $address, 'capacity' => $capacity, 'status' => $status, 'id' => $id]);
            $this->audit($adminId, 'tournament.venue_updated', 'Updated venue ' . $name);
            return;
        }

        $statement = $this->pdo->prepare(
            'INSERT INTO venues (name, zone, address, capacity, status, created_at, updated_at) '
            . 'VALUES (:name, :zone, :address, :capacity, :status, UTC_TIMESTAMP(), UTC_TIMESTAMP())'
        );
        $statement->execute(['name' => $name, 'zone' => $zone, 'address' => $address, 'capacity' => $capacity, 'status' => $status]);
        $this->audit($adminId, 'tournament.venue_created', 'Created venue ' . $name);
    }

    /** @param array<string,mixed> $data */
    public function saveFixture(array $data, int $adminId, string $timezone = 'UTC'): void
    {
        $id = max(0, (int) ($data['id'] ?? 0));
        $homeId = filter_var($data['home_team_id'] ?? null, FILTER_VALIDATE_INT);
        $awayId = filter_var($data['away_team_id'] ?? null, FILTER_VALIDATE_INT);
        $venueId = trim((string) ($data['venue_id'] ?? '')) === '' ? null : filter_var($data['venue_id'], FILTER_VALIDATE_INT);
        if ($homeId === false || $awayId === false || $homeId < 1 || $awayId < 1 || $homeId === $awayId) {
            throw new InvalidArgumentException('Choose two different teams for the fixture.');
        }
        if ($venueId === false || ($venueId !== null && $venueId < 1)) {
            throw new InvalidArgumentException('Choose a valid venue.');
        }
        $stage = $this->text($data['stage'] ?? 'Group stage', 60, 'Round/stage');
        $kickoffInput = trim((string) ($data['kickoff_at'] ?? ''));
        $zone = in_array($timezone, \DateTimeZone::listIdentifiers(), true) ? $timezone : 'UTC';
        $kickoff = \DateTimeImmutable::createFromFormat('!Y-m-d\TH:i', $kickoffInput, new \DateTimeZone($zone));
        $dateErrors = \DateTimeImmutable::getLastErrors();
        if ($kickoff === false || (is_array($dateErrors) && ($dateErrors['warning_count'] > 0 || $dateErrors['error_count'] > 0))) {
            throw new InvalidArgumentException('Enter a valid kick-off date and time.');
        }
        $kickoffUtc = $kickoff->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s');
        $status = $this->oneOf($data['status'] ?? 'scheduled', ['scheduled', 'live', 'completed', 'postponed', 'cancelled'], 'Fixture status');
        $homeScore = $this->score($data['home_score'] ?? null);
        $awayScore = $this->score($data['away_score'] ?? null);
        $notes = mb_substr(trim((string) ($data['notes'] ?? '')), 0, 500);

        if ($status === 'completed' && ($homeScore === null || $awayScore === null)) {
            throw new InvalidArgumentException('Add both scores before marking a fixture completed.');
        }
        $values = ['home' => $homeId, 'away' => $awayId, 'venue' => $venueId, 'stage' => $stage, 'kickoff' => $kickoffUtc, 'status' => $status, 'home_score' => $homeScore, 'away_score' => $awayScore, 'notes' => $notes];
        if ($id > 0) {
            $values['id'] = $id;
            $statement = $this->pdo->prepare(
                'UPDATE fixtures SET home_team_id=:home, away_team_id=:away, venue_id=:venue, stage=:stage, kickoff_at=:kickoff, '
                . 'status=:status, home_score=:home_score, away_score=:away_score, notes=:notes WHERE id=:id'
            );
            $statement->execute($values);
            $this->audit($adminId, 'tournament.fixture_updated', 'Updated fixture #' . $id);
            return;
        }
        $statement = $this->pdo->prepare(
            'INSERT INTO fixtures (home_team_id, away_team_id, venue_id, stage, kickoff_at, status, home_score, away_score, notes, created_at, updated_at) '
            . 'VALUES (:home, :away, :venue, :stage, :kickoff, :status, :home_score, :away_score, :notes, UTC_TIMESTAMP(), UTC_TIMESTAMP())'
        );
        $statement->execute($values);
        $this->audit($adminId, 'tournament.fixture_created', 'Created fixture #' . (int) $this->pdo->lastInsertId());
    }

    /** @param array<string,mixed> $data */
    public function register(array $data, string $ipAddress): string
    {
        $name = $this->text($data['full_name'] ?? '', 140, 'Full name');
        $email = trim((string) ($data['email'] ?? ''));
        if (strlen($email) > 190 || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            throw new InvalidArgumentException('Enter a valid email address.');
        }
        $phone = mb_substr(trim((string) ($data['phone'] ?? '')), 0, 40);
        $category = $this->oneOf($data['category'] ?? '', ['player', 'team_official', 'vendor', 'volunteer', 'community'], 'Registration type');
        $ageInput = trim((string) ($data['age'] ?? ''));
        $age = $ageInput === '' ? null : filter_var($ageInput, FILTER_VALIDATE_INT);
        if ($age === false || ($age !== null && ($age < 10 || $age > 99))) {
            throw new InvalidArgumentException('Enter a valid age between 10 and 99.');
        }
        if ($category === 'player' && ($age === null || $age >= 19)) {
            throw new InvalidArgumentException('Player applications require an age under 19.');
        }
        $zone = mb_substr(trim((string) ($data['zone'] ?? '')), 0, 50);
        $teamName = mb_substr(trim((string) ($data['team_name'] ?? '')), 0, 120);
        $details = mb_substr(trim((string) ($data['details'] ?? '')), 0, 2000);
        $ipAddress = filter_var($ipAddress, FILTER_VALIDATE_IP) !== false ? substr($ipAddress, 0, 45) : 'unknown';
        $rate = $this->pdo->prepare(
            "SELECT COUNT(*) FROM audit_logs WHERE event_key='registration.submitted' AND ip_address=:ip "
            . 'AND created_at >= (UTC_TIMESTAMP() - INTERVAL 15 MINUTE)'
        );
        $rate->execute(['ip' => $ipAddress]);
        if ((int) $rate->fetchColumn() >= 5) {
            throw new InvalidArgumentException('Too many applications were submitted from this connection. Wait a little and try again.');
        }
        $reference = 'YUC-' . gmdate('ymd') . '-' . strtoupper(bin2hex(random_bytes(3)));

        $statement = $this->pdo->prepare(
            'INSERT INTO registrations (reference, full_name, email, phone, category, age, zone, team_name, details, status, submitted_at) '
            . "VALUES (:reference, :name, :email, :phone, :category, :age, :zone, :team_name, :details, 'new', UTC_TIMESTAMP())"
        );
        $statement->execute([
            'reference' => $reference, 'name' => $name, 'email' => $email, 'phone' => $phone,
            'category' => $category, 'age' => $age, 'zone' => $zone, 'team_name' => $teamName, 'details' => $details,
        ]);
        try {
            $this->notifications->notifyActivity(
                'registration.submitted',
                'Youth Unity Cup registration received',
                "We received your {$category} registration. Reference: {$reference}. The team will review your details and follow up by email.",
                ['user_email' => $email, 'notify_admin' => true, 'ip_address' => $ipAddress, 'include_ip' => false, 'context' => ['reference' => $reference, 'category' => $category, 'name' => $name]]
            );
        } catch (Throwable $exception) {
            error_log('Youth Unity Cup registration notification could not be queued (' . get_class($exception) . ').');
        }
        return $reference;
    }

    public function updateRegistrationStatus(int $id, string $status, int $adminId): void
    {
        $status = $this->oneOf($status, ['new', 'reviewing', 'approved', 'rejected'], 'Registration status');
        $statement = $this->pdo->prepare('SELECT reference, full_name, email, status FROM registrations WHERE id=:id LIMIT 1');
        $statement->execute(['id' => $id]);
        $row = $statement->fetch();
        if (!is_array($row)) {
            throw new InvalidArgumentException('That registration no longer exists.');
        }
        if ($row['status'] === $status) {
            return;
        }
        $update = $this->pdo->prepare(
            'UPDATE registrations SET status=:status, reviewed_by=:admin, reviewed_at=UTC_TIMESTAMP() WHERE id=:id'
        );
        $update->execute(['status' => $status, 'admin' => $adminId, 'id' => $id]);
        try {
            $this->notifications->notifyActivity(
                'registration.status_changed',
                'Youth Unity Cup registration update',
                "Hello {$row['full_name']}, your registration {$row['reference']} is now marked {$status}.",
                ['user_email' => (string) $row['email'], 'user_id' => $adminId, 'include_ip' => false, 'context' => ['reference' => $row['reference'], 'status' => $status]]
            );
        } catch (Throwable $exception) {
            error_log('Youth Unity Cup registration status notification failed (' . get_class($exception) . ').');
        }
    }

    /** @param array<string,mixed> $data */
    public function createTransaction(array $data, int $adminId): string
    {
        $reference = trim((string) ($data['reference'] ?? ''));
        if ($reference === '') {
            $reference = 'YUCT-' . gmdate('ymd') . '-' . strtoupper(bin2hex(random_bytes(4)));
        }
        if (!preg_match('/^[A-Za-z0-9_-]{4,80}$/', $reference)) {
            throw new InvalidArgumentException('Transaction reference must use 4–80 letters, numbers, hyphens, or underscores.');
        }
        $email = trim((string) ($data['recipient_email'] ?? ''));
        if ($email !== '' && (strlen($email) > 190 || filter_var($email, FILTER_VALIDATE_EMAIL) === false)) {
            throw new InvalidArgumentException('Enter a valid transaction recipient email.');
        }
        $amountInput = trim((string) ($data['amount'] ?? ''));
        if (preg_match('/^\\d{1,10}(?:\\.\\d{1,2})?$/D', $amountInput) !== 1) {
            throw new InvalidArgumentException('Enter a valid transaction amount with no more than two decimal places.');
        }
        $amount = (float) $amountInput;
        if ($amount > 9999999999.99) {
            throw new InvalidArgumentException('Enter a valid transaction amount.');
        }
        $currency = strtoupper(trim((string) ($data['currency'] ?? 'NGN')));
        if (!in_array($currency, ['NGN', 'USD', 'GBP', 'EUR'], true)) {
            throw new InvalidArgumentException('Choose a supported transaction currency.');
        }
        $status = $this->oneOf($data['status'] ?? 'pending', ['pending', 'completed', 'failed', 'refunded'], 'Transaction status');
        $description = mb_substr(trim((string) ($data['description'] ?? '')), 0, 255);
        $statement = $this->pdo->prepare(
            'INSERT INTO transactions (reference, user_id, recipient_email, amount, currency, status, description, created_at, updated_at) '
            . 'VALUES (:reference, NULL, :email, :amount, :currency, :status, :description, UTC_TIMESTAMP(), UTC_TIMESTAMP())'
        );
        $statement->execute(['reference' => $reference, 'email' => $email, 'amount' => number_format((float) $amount, 2, '.', ''), 'currency' => $currency, 'status' => $status, 'description' => $description]);
        try {
            $this->notifications->notifyTransaction('created', ['reference' => $reference, 'amount' => $amount, 'currency' => $currency, 'status' => $status, 'description' => $description], $email !== '' ? $email : null, $adminId);
        } catch (Throwable $exception) {
            error_log('Youth Unity Cup transaction notification could not be queued (' . get_class($exception) . ').');
        }
        return $reference;
    }

    public function updateTransactionStatus(int $id, string $status, int $adminId): void
    {
        $status = $this->oneOf($status, ['pending', 'completed', 'failed', 'refunded'], 'Transaction status');
        $statement = $this->pdo->prepare('SELECT reference, recipient_email, amount, currency, status, payment_provider, description FROM transactions WHERE id=:id LIMIT 1');
        $statement->execute(['id' => $id]);
        $row = $statement->fetch();
        if (!is_array($row)) {
            throw new InvalidArgumentException('That transaction no longer exists.');
        }
        if ((string) ($row['payment_provider'] ?? '') === 'payhub') {
            throw new InvalidArgumentException('PayHub transaction status is updated only after server-side payment verification. Manage fulfillment in Shop orders.');
        }
        if ($row['status'] === $status) {
            return;
        }
        $update = $this->pdo->prepare('UPDATE transactions SET status=:status, updated_at=UTC_TIMESTAMP() WHERE id=:id');
        $update->execute(['status' => $status, 'id' => $id]);
        $row['status'] = $status;
        try {
            $this->notifications->notifyTransaction('status_changed', $row, (string) $row['recipient_email'], $adminId);
        } catch (Throwable $exception) {
            error_log('Youth Unity Cup transaction status notification failed (' . get_class($exception) . ').');
        }
    }

    /** @return array<string,string> */
    public function settings(): array
    {
        $defaults = [
            'site_title' => 'Youth Unity Cup',
            'timezone' => 'UTC',
            'contact_email' => '',
            'max_login_attempts' => '5',
            'login_window_minutes' => '15',
            'block_duration_minutes' => '15',
        ];
        $rows = $this->pdo->query('SELECT setting_key, setting_value FROM system_settings')->fetchAll();
        foreach ($rows as $row) {
            if (array_key_exists((string) $row['setting_key'], $defaults)) {
                $defaults[(string) $row['setting_key']] = (string) $row['setting_value'];
            }
        }
        return $defaults;
    }

    /** @param array<string,string> $settings */
    public function saveSettings(array $settings, int $adminId): void
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO system_settings (setting_key, setting_value, updated_at) VALUES (:setting_key, :setting_value, UTC_TIMESTAMP()) '
            . 'ON DUPLICATE KEY UPDATE setting_value=:updated_value, updated_at=UTC_TIMESTAMP()'
        );
        foreach ($settings as $key => $value) {
            $statement->execute(['setting_key' => $key, 'setting_value' => $value, 'updated_value' => $value]);
        }
        $this->audit($adminId, 'system.settings_updated', 'Updated site and security settings');
    }

    /** @return array<string,int> */
    public function counts(): array
    {
        $counts = [];
        foreach (['teams', 'venues', 'fixtures', 'registrations', 'transactions'] as $table) {
            $counts[$table] = (int) $this->pdo->query('SELECT COUNT(*) FROM ' . $table)->fetchColumn();
        }
        $counts['blocked_ips'] = (int) $this->pdo->query('SELECT COUNT(*) FROM ip_blocks WHERE blocked_until > UTC_TIMESTAMP()')->fetchColumn();
        return $counts;
    }

    /** @param array<string,mixed> $data */
    private function text(mixed $value, int $max, string $label): string
    {
        $text = trim((string) $value);
        if ($text === '' || mb_strlen($text) > $max) {
            throw new InvalidArgumentException($label . ' is required and must be no longer than ' . $max . ' characters.');
        }
        return $text;
    }

    /** @param list<string> $allowed */
    private function oneOf(mixed $value, array $allowed, string $label): string
    {
        $text = (string) $value;
        if (!in_array($text, $allowed, true)) {
            throw new InvalidArgumentException('Choose a valid ' . strtolower($label) . '.');
        }
        return $text;
    }

    private function score(mixed $value): ?int
    {
        if ($value === null || trim((string) $value) === '') {
            return null;
        }
        $score = filter_var($value, FILTER_VALIDATE_INT);
        if ($score === false || $score < 0 || $score > 99) {
            throw new InvalidArgumentException('Scores must be a whole number from 0 to 99.');
        }
        return $score;
    }

    private function audit(?int $userId, string $event, string $description, string $ip = 'unknown'): void
    {
        try {
            $statement = $this->pdo->prepare(
                'INSERT INTO audit_logs (user_id, event_key, description, ip_address, created_at) '
                . 'VALUES (:user_id, :event_key, :description, :ip, UTC_TIMESTAMP())'
            );
            $statement->execute(['user_id' => $userId, 'event_key' => $event, 'description' => mb_substr($description, 0, 255), 'ip' => $ip]);
        } catch (Throwable $exception) {
            error_log('Youth Unity Cup tournament audit failed (' . get_class($exception) . ').');
        }
    }
}
