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
            "SELECT t.id, t.name, t.zone, t.group_name, COUNT(p.id) AS player_count "
            . "FROM teams t LEFT JOIN team_players p ON p.team_id=t.id AND p.status='active' "
            . "WHERE t.status='active' GROUP BY t.id, t.name, t.zone, t.group_name "
            . "ORDER BY COALESCE(NULLIF(t.group_name, ''), 'Z'), t.name LIMIT 200"
        )->fetchAll();
    }

    /** @return list<array{name:string,teams:list<array<string,mixed>>}> */
    public function publicTeamGroups(): array
    {
        $teams = $this->publicTeams();
        if ($teams === []) {
            return [];
        }

        $stats = [];
        $teamGroupsById = [];
        foreach ($teams as $team) {
            $teamId = (int) $team['id'];
            $stats[$teamId] = [
                'played' => 0, 'won' => 0, 'drawn' => 0, 'lost' => 0,
                'goals_for' => 0, 'goals_against' => 0, 'goal_difference' => 0, 'points' => 0,
            ];
            $teamGroupsById[$teamId] = strtoupper(trim((string) ($team['group_name'] ?? '')));
        }

        $matches = $this->pdo->query(
            "SELECT f.home_team_id, f.away_team_id, f.home_score, f.away_score, f.stage "
            . "FROM fixtures f INNER JOIN teams ht ON ht.id=f.home_team_id AND ht.status='active' "
            . "INNER JOIN teams at ON at.id=f.away_team_id AND at.status='active' "
            . "WHERE f.status='completed' AND f.home_score IS NOT NULL AND f.away_score IS NOT NULL"
        )->fetchAll();
        foreach ($matches as $match) {
            $homeId = (int) $match['home_team_id'];
            $awayId = (int) $match['away_team_id'];
            if (!isset($stats[$homeId], $stats[$awayId])
                || !str_contains(mb_strtolower((string) $match['stage']), 'group')
                || $teamGroupsById[$homeId] === ''
                || $teamGroupsById[$homeId] !== $teamGroupsById[$awayId]) {
                continue;
            }
            $homeScore = (int) $match['home_score'];
            $awayScore = (int) $match['away_score'];
            $stats[$homeId]['played']++;
            $stats[$awayId]['played']++;
            $stats[$homeId]['goals_for'] += $homeScore;
            $stats[$homeId]['goals_against'] += $awayScore;
            $stats[$awayId]['goals_for'] += $awayScore;
            $stats[$awayId]['goals_against'] += $homeScore;
            if ($homeScore > $awayScore) {
                $stats[$homeId]['won']++;
                $stats[$homeId]['points'] += 3;
                $stats[$awayId]['lost']++;
            } elseif ($homeScore < $awayScore) {
                $stats[$awayId]['won']++;
                $stats[$awayId]['points'] += 3;
                $stats[$homeId]['lost']++;
            } else {
                $stats[$homeId]['drawn']++;
                $stats[$awayId]['drawn']++;
                $stats[$homeId]['points']++;
                $stats[$awayId]['points']++;
            }
        }

        $groups = [];
        foreach ($teams as $team) {
            $groupName = trim((string) ($team['group_name'] ?? ''));
            $groupName = $groupName !== '' ? $groupName : 'Unassigned';
            $team['played'] = $stats[(int) $team['id']]['played'];
            $team['won'] = $stats[(int) $team['id']]['won'];
            $team['drawn'] = $stats[(int) $team['id']]['drawn'];
            $team['lost'] = $stats[(int) $team['id']]['lost'];
            $team['goals_for'] = $stats[(int) $team['id']]['goals_for'];
            $team['goals_against'] = $stats[(int) $team['id']]['goals_against'];
            $team['goal_difference'] = $team['goals_for'] - $team['goals_against'];
            $team['points'] = $stats[(int) $team['id']]['points'];
            $groups[$groupName][] = $team;
        }

        uksort($groups, static function (string $left, string $right): int {
            if ($left === 'Unassigned') {
                return 1;
            }
            if ($right === 'Unassigned') {
                return -1;
            }
            return strnatcasecmp($left, $right);
        });
        foreach ($groups as &$groupTeams) {
            usort($groupTeams, static function (array $left, array $right): int {
                return ($right['points'] <=> $left['points'])
                    ?: ($right['goal_difference'] <=> $left['goal_difference'])
                    ?: ($right['goals_for'] <=> $left['goals_for'])
                    ?: strcasecmp((string) $left['name'], (string) $right['name']);
            });
        }
        unset($groupTeams);

        $result = [];
        foreach ($groups as $name => $groupTeams) {
            $result[] = ['name' => (string) $name, 'teams' => $groupTeams];
        }
        return $result;
    }

    /** @return array<string,mixed>|null */
    public function findPublicTeam(int $id): ?array
    {
        $statement = $this->pdo->prepare(
            "SELECT t.id, t.name, t.zone, t.group_name, t.notes, COUNT(p.id) AS player_count "
            . "FROM teams t LEFT JOIN team_players p ON p.team_id=t.id AND p.status='active' "
            . "WHERE t.id=:id AND t.status='active' "
            . "GROUP BY t.id, t.name, t.zone, t.group_name, t.notes LIMIT 1"
        );
        $statement->execute(['id' => $id]);
        $team = $statement->fetch();
        return is_array($team) ? $team : null;
    }

    /** @return list<array<string,mixed>> */
    public function publicTeamPlayers(int $teamId): array
    {
        $statement = $this->pdo->prepare(
            "SELECT id, full_name, jersey_number, position, age, hometown, bio, photo_url, avatar_variant "
            . "FROM team_players WHERE team_id=:team_id AND status='active' "
            . "ORDER BY jersey_number IS NULL, jersey_number, full_name LIMIT 40"
        );
        $statement->execute(['team_id' => $teamId]);
        return $statement->fetchAll();
    }

    /** @return list<array<string,mixed>> */
    public function players(): array
    {
        return $this->pdo->query(
            'SELECT p.id, p.team_id, p.full_name, p.jersey_number, p.position, p.age, p.hometown, p.bio, '
            . 'p.photo_url, p.avatar_variant, p.status, p.created_at, t.name AS team_name, t.zone AS team_zone '
            . 'FROM team_players p INNER JOIN teams t ON t.id=p.team_id '
            . 'ORDER BY t.name, p.jersey_number IS NULL, p.jersey_number, p.full_name LIMIT 1000'
        )->fetchAll();
    }

    /** @return array<string,mixed>|null */
    public function findPlayer(int $id): ?array
    {
        $statement = $this->pdo->prepare('SELECT * FROM team_players WHERE id=:id LIMIT 1');
        $statement->execute(['id' => $id]);
        $player = $statement->fetch();
        return is_array($player) ? $player : null;
    }

    /** @param array<string,mixed> $data */
    public function savePlayer(array $data, int $adminId): void
    {
        $id = filter_var(is_scalar($data['id'] ?? 0) ? $data['id'] : null, FILTER_VALIDATE_INT);
        $teamId = filter_var(is_scalar($data['team_id'] ?? null) ? $data['team_id'] : null, FILTER_VALIDATE_INT);
        if ($id === false || $id < 0 || $teamId === false || $teamId < 1) {
            throw new InvalidArgumentException('Choose a valid team and player record.');
        }
        $fullName = $this->text($data['full_name'] ?? '', 120, 'Player name');
        $position = $this->text($data['position'] ?? '', 40, 'Playing position');
        $jerseyInput = $data['jersey_number'] ?? '';
        $jerseyNumber = null;
        if (is_scalar($jerseyInput) && trim((string) $jerseyInput) !== '') {
            $jerseyNumber = filter_var($jerseyInput, FILTER_VALIDATE_INT);
            if ($jerseyNumber === false || $jerseyNumber < 1 || $jerseyNumber > 99) {
                throw new InvalidArgumentException('Jersey number must be from 1 to 99.');
            }
        }
        $ageInput = $data['age'] ?? '';
        $age = null;
        if (is_scalar($ageInput) && trim((string) $ageInput) !== '') {
            $age = filter_var($ageInput, FILTER_VALIDATE_INT);
            if ($age === false || $age < 10 || $age > 19) {
                throw new InvalidArgumentException('Player age must be from 10 to 19.');
            }
        }
        $hometownInput = $data['hometown'] ?? '';
        $bioInput = $data['bio'] ?? '';
        if (!is_scalar($hometownInput) || !is_scalar($bioInput)) {
            throw new InvalidArgumentException('Player hometown and profile details must be text.');
        }
        $hometown = trim((string) $hometownInput);
        $bio = trim((string) $bioInput);
        if (mb_strlen($hometown) > 80 || mb_strlen($bio) > 350) {
            throw new InvalidArgumentException('Hometown is limited to 80 characters and player details to 350 characters.');
        }
        $photoUrl = $this->validatePlayerPhotoUrl($data['photo_url'] ?? '');
        $avatarInput = $data['avatar_variant'] ?? 1;
        $avatarVariant = filter_var(is_scalar($avatarInput) ? $avatarInput : null, FILTER_VALIDATE_INT);
        if ($avatarVariant === false || $avatarVariant < 1 || $avatarVariant > 8) {
            throw new InvalidArgumentException('Choose a player portrait style from 1 to 8.');
        }
        $status = $this->oneOf($data['status'] ?? 'active', ['active', 'inactive'], 'Player status');

        $teamCheck = $this->pdo->prepare('SELECT id FROM teams WHERE id=:id LIMIT 1');
        $teamCheck->execute(['id' => $teamId]);
        if ($teamCheck->fetchColumn() === false) {
            throw new InvalidArgumentException('The selected team no longer exists.');
        }
        if ($id > 0 && $this->findPlayer($id) === null) {
            throw new InvalidArgumentException('That player profile no longer exists.');
        }

        $values = [
            'team_id' => $teamId,
            'full_name' => $fullName,
            'jersey_number' => $jerseyNumber,
            'position' => $position,
            'age' => $age,
            'hometown' => $hometown,
            'bio' => $bio,
            'photo_url' => $photoUrl,
            'avatar_variant' => $avatarVariant,
            'status' => $status,
        ];
        if ($id > 0) {
            $values['id'] = $id;
            $statement = $this->pdo->prepare(
                'UPDATE team_players SET team_id=:team_id, full_name=:full_name, jersey_number=:jersey_number, '
                . 'position=:position, age=:age, hometown=:hometown, bio=:bio, photo_url=:photo_url, '
                . 'avatar_variant=:avatar_variant, status=:status WHERE id=:id'
            );
            $statement->execute($values);
            $this->audit($adminId, 'tournament.player_updated', 'Updated player profile for ' . $fullName);
            return;
        }

        $statement = $this->pdo->prepare(
            'INSERT INTO team_players (team_id, full_name, jersey_number, position, age, hometown, bio, photo_url, avatar_variant, status, created_at, updated_at) '
            . 'VALUES (:team_id, :full_name, :jersey_number, :position, :age, :hometown, :bio, :photo_url, :avatar_variant, :status, UTC_TIMESTAMP(), UTC_TIMESTAMP())'
        );
        $statement->execute($values);
        $this->audit($adminId, 'tournament.player_created', 'Created player profile for ' . $fullName);
    }

    public function deletePlayer(int $id, int $adminId): void
    {
        $player = $this->findPlayer($id);
        if ($player === null) {
            throw new InvalidArgumentException('That player profile no longer exists.');
        }
        $this->pdo->prepare('DELETE FROM team_players WHERE id=:id')->execute(['id' => $id]);
        $this->audit($adminId, 'tournament.player_deleted', 'Deleted player profile for ' . (string) $player['full_name']);
    }

    private function validatePlayerPhotoUrl(mixed $value): string
    {
        if (!is_scalar($value)) {
            throw new InvalidArgumentException('Player headshot URL must be a secure HTTPS address.');
        }
        $url = trim((string) $value);
        if ($url === '') {
            return '';
        }
        $parts = parse_url($url);
        if (strlen($url) > 500
            || filter_var($url, FILTER_VALIDATE_URL) === false
            || !is_array($parts)
            || strtolower((string) ($parts['scheme'] ?? '')) !== 'https'
            || !isset($parts['host'])
            || preg_match('/^[A-Za-z0-9.-]+$/D', (string) $parts['host']) !== 1
            || isset($parts['user'])
            || isset($parts['pass'])) {
            throw new InvalidArgumentException('Use a valid HTTPS image URL for the player headshot, or leave it blank for the illustrated portrait.');
        }
        return $url;
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
            'SELECT f.id, f.home_team_id, f.away_team_id, f.stage, f.kickoff_at, f.status, f.home_score, f.away_score, f.notes, '
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
            'SELECT ip_address, blocked_until, reason, created_at, '
            . 'GREATEST(1, CEIL(TIMESTAMPDIFF(SECOND, UTC_TIMESTAMP(), blocked_until) / 60)) AS duration_minutes '
            . 'FROM ip_blocks WHERE blocked_until > UTC_TIMESTAMP() ORDER BY blocked_until DESC LIMIT 300'
        )->fetchAll();
    }

    /** @param array<string,mixed> $data */
    public function saveBlockedIp(array $data, int $adminId): void
    {
        $oldIpInput = $data['old_ip'] ?? '';
        $ipInput = $data['ip_address'] ?? '';
        $oldIp = is_scalar($oldIpInput) ? trim((string) $oldIpInput) : '';
        $ipAddress = is_scalar($ipInput) ? trim((string) $ipInput) : '';
        foreach ([$oldIp, $ipAddress] as $candidate) {
            if ($candidate !== '' && $candidate !== 'unknown' && filter_var($candidate, FILTER_VALIDATE_IP) === false) {
                throw new InvalidArgumentException('Enter a valid IPv4 or IPv6 address.');
            }
        }
        if ($ipAddress === '') {
            throw new InvalidArgumentException('An IP address is required.');
        }
        $durationInput = $data['duration_minutes'] ?? null;
        $duration = filter_var(is_scalar($durationInput) ? $durationInput : null, FILTER_VALIDATE_INT);
        if ($duration === false || $duration < 1 || $duration > 1440) {
            throw new InvalidArgumentException('Block duration must be from 1 to 1440 minutes.');
        }
        $reasonInput = $data['reason'] ?? '';
        $reason = is_scalar($reasonInput) ? trim((string) $reasonInput) : '';
        if ($reason === '' || mb_strlen($reason) > 160) {
            throw new InvalidArgumentException('Enter a reason of no more than 160 characters.');
        }
        if ($oldIp !== '') {
            $existing = $this->findBlockedIp($oldIp);
            if ($existing === null) {
                throw new InvalidArgumentException('That active IP block no longer exists.');
            }
        }
        if ($oldIp !== $ipAddress) {
            // Expired rows still occupy the primary key until explicitly removed.
            $this->pdo->prepare('DELETE FROM ip_blocks WHERE ip_address=:ip AND blocked_until<=UTC_TIMESTAMP()')
                ->execute(['ip' => $ipAddress]);
        }
        if ($oldIp !== '' && $oldIp !== $ipAddress) {
            $collision = $this->findBlockedIp($ipAddress);
            if ($collision !== null) {
                throw new InvalidArgumentException('That IP address already has an active block. Edit the existing block instead.');
            }
        } elseif ($oldIp === '' && $this->findBlockedIp($ipAddress) !== null) {
            throw new InvalidArgumentException('That IP address is already blocked. Edit its current block instead.');
        }

        $blockedUntil = (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))
            ->modify('+' . $duration . ' minutes')->format('Y-m-d H:i:s');
        if ($oldIp !== '') {
            $statement = $this->pdo->prepare(
                'UPDATE ip_blocks SET ip_address=:ip, blocked_until=:blocked_until, reason=:reason WHERE ip_address=:old_ip'
            );
            $statement->execute(['ip' => $ipAddress, 'blocked_until' => $blockedUntil, 'reason' => $reason, 'old_ip' => $oldIp]);
            if ($statement->rowCount() < 1 && ($oldIp !== $ipAddress || $this->findBlockedIp($ipAddress) === null)) {
                throw new InvalidArgumentException('That active IP block no longer exists.');
            }
            $this->audit($adminId, 'security.ip_block_updated', 'Updated IP block for ' . $ipAddress);
            return;
        }

        $statement = $this->pdo->prepare(
            'INSERT INTO ip_blocks (ip_address, blocked_until, reason, created_at) VALUES (:ip, :blocked_until, :reason, UTC_TIMESTAMP())'
        );
        $statement->execute(['ip' => $ipAddress, 'blocked_until' => $blockedUntil, 'reason' => $reason]);
        $this->audit($adminId, 'security.ip_block_created', 'Created manual IP block for ' . $ipAddress);
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
    public function transactions(bool $includeArchived = false): array
    {
        $archiveFilter = $includeArchived ? ' WHERE t.archived_at IS NOT NULL' : ' WHERE t.archived_at IS NULL';
        return $this->pdo->query(
            'SELECT t.id, t.reference, t.recipient_email, t.amount, t.currency, t.status, t.payment_provider, '
            . 't.provider_reference, t.description, t.created_at, t.archived_at, o.reference AS shop_order_reference, o.status AS shop_order_status '
            . 'FROM transactions t LEFT JOIN shop_orders o ON o.transaction_id=t.id' . $archiveFilter
            . ' ORDER BY t.created_at DESC, t.id DESC LIMIT 300'
        )->fetchAll();
    }

    /** @return array<string,mixed>|null */
    public function findTransactionForAdmin(int $id): ?array
    {
        $statement = $this->pdo->prepare(
            'SELECT t.id, t.reference, t.recipient_email, t.amount, t.currency, t.status, t.description, t.archived_at '
            . 'FROM transactions t LEFT JOIN shop_orders o ON o.transaction_id=t.id '
            . "WHERE t.id=:id AND t.payment_provider<>'payhub' AND o.id IS NULL LIMIT 1"
        );
        $statement->execute(['id' => $id]);
        $row = $statement->fetch();
        if (!is_array($row)) {
            return null;
        }
        $row['amount'] = number_format((float) $row['amount'], 2, '.', '');
        return $row;
    }

    /** @param array<string,mixed> $data */
    public function saveTransaction(array $data, int $adminId): string
    {
        $rawId = $data['id'] ?? 0;
        $id = filter_var(is_scalar($rawId) ? $rawId : 0, FILTER_VALIDATE_INT);
        if ($id === false || $id < 0) {
            throw new InvalidArgumentException('The selected transaction is not valid.');
        }
        if ($id === 0) {
            return $this->createTransaction($data, $adminId);
        }
        $existing = $this->findTransactionForAdmin($id);
        if ($existing === null) {
            throw new InvalidArgumentException('Only manually recorded transactions without a linked shop order can be edited.');
        }
        if ($existing['archived_at'] !== null) {
            throw new InvalidArgumentException('Restore the archived transaction before editing it.');
        }
        $referenceInput = $data['reference'] ?? '';
        $reference = is_scalar($referenceInput) ? trim((string) $referenceInput) : '';
        if (!preg_match('/^[A-Za-z0-9_-]{4,80}$/D', $reference)) {
            throw new InvalidArgumentException('Transaction reference must use 4–80 letters, numbers, hyphens, or underscores.');
        }
        $emailInput = $data['recipient_email'] ?? '';
        $email = is_scalar($emailInput) ? trim((string) $emailInput) : '';
        if ($email !== '' && (strlen($email) > 190 || filter_var($email, FILTER_VALIDATE_EMAIL) === false)) {
            throw new InvalidArgumentException('Enter a valid transaction recipient email.');
        }
        $amountRaw = $data['amount'] ?? '';
        $amountInput = is_scalar($amountRaw) ? trim((string) $amountRaw) : '';
        if (preg_match('/^\\d{1,10}(?:\\.\\d{1,2})?$/D', $amountInput) !== 1) {
            throw new InvalidArgumentException('Enter a valid transaction amount with no more than two decimal places.');
        }
        $amount = (float) $amountInput;
        if ($amount > 9999999999.99) {
            throw new InvalidArgumentException('Enter a valid transaction amount.');
        }
        $currencyInput = $data['currency'] ?? 'NGN';
        $currency = is_scalar($currencyInput) ? strtoupper(trim((string) $currencyInput)) : '';
        if (!in_array($currency, ['NGN', 'USD', 'GBP', 'EUR'], true)) {
            throw new InvalidArgumentException('Choose a supported transaction currency.');
        }
        $status = $this->oneOf($data['status'] ?? 'pending', ['pending', 'completed', 'failed', 'refunded'], 'Transaction status');
        $descriptionInput = $data['description'] ?? '';
        $description = mb_substr(is_scalar($descriptionInput) ? trim((string) $descriptionInput) : '', 0, 255);
        $update = $this->pdo->prepare(
            'UPDATE transactions SET reference=:reference, recipient_email=:email, amount=:amount, currency=:currency, '
            . 'status=:status, description=:description, updated_at=UTC_TIMESTAMP() WHERE id=:id AND payment_provider<>\'payhub\''
        );
        $update->execute([
            'reference' => $reference, 'email' => $email, 'amount' => number_format($amount, 2, '.', ''),
            'currency' => $currency, 'status' => $status, 'description' => $description, 'id' => $id,
        ]);
        if ($update->rowCount() < 1 && $existing['reference'] !== $reference) {
            throw new InvalidArgumentException('The transaction could not be updated.');
        }
        $this->audit($adminId, 'transaction.manual_updated', 'Updated manually recorded transaction ' . $reference);
        if ((string) $existing['status'] !== $status) {
            try {
                $this->notifications->notifyTransaction(
                    'status_changed',
                    ['reference' => $reference, 'recipient_email' => $email, 'amount' => $amount, 'currency' => $currency, 'status' => $status, 'description' => $description],
                    $email !== '' ? $email : null,
                    $adminId
                );
            } catch (Throwable $exception) {
                error_log('Youth Unity Cup transaction status notification failed (' . get_class($exception) . ').');
            }
        }
        return $reference;
    }

    public function setTransactionArchived(int $id, bool $archived, int $adminId): void
    {
        $this->pdo->beginTransaction();
        try {
            $query = $this->pdo->prepare(
                'SELECT t.reference, t.payment_provider, o.id AS order_id FROM transactions t '
                . 'LEFT JOIN shop_orders o ON o.transaction_id=t.id WHERE t.id=:id LIMIT 1 FOR UPDATE'
            );
            $query->execute(['id' => $id]);
            $row = $query->fetch();
            if (!is_array($row)) {
                throw new InvalidArgumentException('That transaction no longer exists.');
            }
            if ((string) $row['payment_provider'] === 'payhub' || $row['order_id'] !== null) {
                throw new InvalidArgumentException('PayHub and shop-order transactions remain in the payment ledger; manage or archive them through Shop orders.');
            }
            $timestamp = $archived ? gmdate('Y-m-d H:i:s') : null;
            $this->pdo->prepare('UPDATE transactions SET archived_at=:archived_at, updated_at=UTC_TIMESTAMP() WHERE id=:id')
                ->execute(['archived_at' => $timestamp, 'id' => $id]);
            $this->audit(
                $adminId,
                $archived ? 'transaction.manual_archived' : 'transaction.manual_restored',
                ($archived ? 'Archived' : 'Restored') . ' manually recorded transaction ' . (string) $row['reference']
            );
            $this->pdo->commit();
        } catch (Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $exception;
        }
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

    /** @return array<string,mixed>|null */
    public function findRegistration(int $id): ?array
    {
        $statement = $this->pdo->prepare('SELECT * FROM registrations WHERE id=:id LIMIT 1');
        $statement->execute(['id' => $id]);
        $row = $statement->fetch();
        return is_array($row) ? $row : null;
    }

    /** @return array<string,mixed>|null */
    public function findBlockedIp(string $ipAddress): ?array
    {
        $statement = $this->pdo->prepare(
            'SELECT ip_address, blocked_until, reason, created_at, '
            . 'GREATEST(1, CEIL(TIMESTAMPDIFF(SECOND, UTC_TIMESTAMP(), blocked_until) / 60)) AS duration_minutes '
            . 'FROM ip_blocks WHERE ip_address=:ip AND blocked_until > UTC_TIMESTAMP() LIMIT 1'
        );
        $statement->execute(['ip' => $ipAddress]);
        $row = $statement->fetch();
        return is_array($row) ? $row : null;
    }

    public function deleteTeam(int $id, int $adminId): void
    {
        $this->pdo->beginTransaction();
        try {
            $query = $this->pdo->prepare('SELECT name FROM teams WHERE id=:id LIMIT 1 FOR UPDATE');
            $query->execute(['id' => $id]);
            $name = $query->fetchColumn();
            if (!is_string($name)) {
                throw new InvalidArgumentException('That team no longer exists.');
            }
            $usage = $this->pdo->prepare('SELECT COUNT(*) FROM fixtures WHERE home_team_id=:home_id OR away_team_id=:away_id');
            $usage->execute(['home_id' => $id, 'away_id' => $id]);
            if ((int) $usage->fetchColumn() > 0) {
                throw new InvalidArgumentException('This team is used in fixtures and cannot be deleted. Edit the team and set its status to inactive instead.');
            }
            $this->pdo->prepare('DELETE FROM teams WHERE id=:id')->execute(['id' => $id]);
            $this->audit($adminId, 'tournament.team_deleted', 'Deleted team ' . $name);
            $this->pdo->commit();
        } catch (Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $exception;
        }
    }

    public function deleteVenue(int $id, int $adminId): void
    {
        $this->pdo->beginTransaction();
        try {
            $query = $this->pdo->prepare('SELECT name FROM venues WHERE id=:id LIMIT 1 FOR UPDATE');
            $query->execute(['id' => $id]);
            $name = $query->fetchColumn();
            if (!is_string($name)) {
                throw new InvalidArgumentException('That venue no longer exists.');
            }
            $usage = $this->pdo->prepare('SELECT COUNT(*) FROM fixtures WHERE venue_id=:id');
            $usage->execute(['id' => $id]);
            if ((int) $usage->fetchColumn() > 0) {
                throw new InvalidArgumentException('This venue is used in fixtures and cannot be deleted. Edit the venue and set its status to inactive instead.');
            }
            $this->pdo->prepare('DELETE FROM venues WHERE id=:id')->execute(['id' => $id]);
            $this->audit($adminId, 'tournament.venue_deleted', 'Deleted venue ' . $name);
            $this->pdo->commit();
        } catch (Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $exception;
        }
    }

    public function deleteFixture(int $id, int $adminId): void
    {
        $this->pdo->beginTransaction();
        try {
            $query = $this->pdo->prepare('SELECT id FROM fixtures WHERE id=:id LIMIT 1 FOR UPDATE');
            $query->execute(['id' => $id]);
            if ($query->fetchColumn() === false) {
                throw new InvalidArgumentException('That fixture no longer exists.');
            }
            $this->pdo->prepare('DELETE FROM fixtures WHERE id=:id')->execute(['id' => $id]);
            $this->audit($adminId, 'tournament.fixture_deleted', 'Deleted fixture #' . $id);
            $this->pdo->commit();
        } catch (Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $exception;
        }
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
        $zoneInput = $data['zone'] ?? '';
        $teamInput = $data['team_name'] ?? '';
        $detailsInput = $data['details'] ?? '';
        $zone = mb_substr(is_scalar($zoneInput) ? trim((string) $zoneInput) : '', 0, 50);
        $teamName = mb_substr(is_scalar($teamInput) ? trim((string) $teamInput) : '', 0, 120);
        $details = mb_substr(is_scalar($detailsInput) ? trim((string) $detailsInput) : '', 0, 2000);
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

    /** @param array<string,mixed> $data */
    public function saveRegistration(array $data, int $adminId): string
    {
        $rawId = $data['id'] ?? 0;
        $id = filter_var(is_scalar($rawId) ? $rawId : 0, FILTER_VALIDATE_INT);
        if ($id === false || $id < 0) {
            throw new InvalidArgumentException('The selected registration is not valid.');
        }
        $name = $this->text($data['full_name'] ?? '', 140, 'Full name');
        $emailInput = $data['email'] ?? '';
        $email = is_scalar($emailInput) ? trim((string) $emailInput) : '';
        if (strlen($email) > 190 || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            throw new InvalidArgumentException('Enter a valid email address.');
        }
        $phoneInput = $data['phone'] ?? '';
        $phone = is_scalar($phoneInput) ? trim((string) $phoneInput) : '';
        if (!is_scalar($phoneInput) || mb_strlen($phone) > 40 || ($phone !== '' && preg_match('/^[+0-9() .-]+$/D', $phone) !== 1)) {
            throw new InvalidArgumentException('Enter a valid phone number or leave it blank.');
        }
        $category = $this->oneOf($data['category'] ?? '', ['player', 'team_official', 'vendor', 'volunteer', 'community'], 'Registration type');
        $ageRaw = $data['age'] ?? '';
        if (!is_scalar($ageRaw)) {
            throw new InvalidArgumentException('Enter a valid age between 10 and 99.');
        }
        $ageInput = trim((string) $ageRaw);
        $age = $ageInput === '' ? null : filter_var($ageInput, FILTER_VALIDATE_INT);
        if ($age === false || ($age !== null && ($age < 10 || $age > 99))) {
            throw new InvalidArgumentException('Enter a valid age between 10 and 99.');
        }
        if ($category === 'player' && ($age === null || $age >= 19)) {
            throw new InvalidArgumentException('Player registrations require an age under 19.');
        }
        $zoneInput = $data['zone'] ?? '';
        $teamInput = $data['team_name'] ?? '';
        $detailsInput = $data['details'] ?? '';
        $zone = mb_substr(is_scalar($zoneInput) ? trim((string) $zoneInput) : '', 0, 50);
        $teamName = mb_substr(is_scalar($teamInput) ? trim((string) $teamInput) : '', 0, 120);
        $details = mb_substr(is_scalar($detailsInput) ? trim((string) $detailsInput) : '', 0, 2000);
        $status = $this->oneOf($data['status'] ?? 'new', ['new', 'reviewing', 'approved', 'rejected'], 'Registration status');

        if ($id > 0) {
            $query = $this->pdo->prepare('SELECT reference, full_name, email, status FROM registrations WHERE id=:id LIMIT 1');
            $query->execute(['id' => $id]);
            $existing = $query->fetch();
            if (!is_array($existing)) {
                throw new InvalidArgumentException('That registration no longer exists.');
            }
            $statusChanged = (string) $existing['status'] !== $status;
            $update = $this->pdo->prepare(
                'UPDATE registrations SET full_name=:name, email=:email, phone=:phone, category=:category, age=:age, '
                . 'zone=:zone, team_name=:team_name, details=:details, status=:status, '
                . 'reviewed_by=CASE WHEN :changed=1 THEN :admin_id ELSE reviewed_by END, '
                . 'reviewed_at=CASE WHEN :changed_again=1 THEN UTC_TIMESTAMP() ELSE reviewed_at END WHERE id=:id'
            );
            $update->execute([
                'name' => $name, 'email' => $email, 'phone' => $phone, 'category' => $category, 'age' => $age,
                'zone' => $zone, 'team_name' => $teamName, 'details' => $details, 'status' => $status,
                'changed' => $statusChanged ? 1 : 0, 'admin_id' => $adminId,
                'changed_again' => $statusChanged ? 1 : 0, 'id' => $id,
            ]);
            $reference = (string) $existing['reference'];
            $this->audit($adminId, 'registration.admin_updated', 'Updated registration ' . $reference);
            if ($statusChanged) {
                try {
                    $this->notifications->notifyActivity(
                        'registration.status_changed',
                        'Youth Unity Cup registration update',
                        "Hello {$name}, your registration {$reference} is now marked {$status}.",
                        ['user_email' => $email, 'user_id' => $adminId, 'include_ip' => false, 'context' => ['reference' => $reference, 'status' => $status]]
                    );
                } catch (Throwable $exception) {
                    error_log('Youth Unity Cup registration status notification failed (' . get_class($exception) . ').');
                }
            }
            return $reference;
        }

        $reference = 'YUC-' . gmdate('ymd') . '-' . strtoupper(bin2hex(random_bytes(3)));
        $reviewed = $status === 'new' ? null : $adminId;
        $statement = $this->pdo->prepare(
            'INSERT INTO registrations (reference, full_name, email, phone, category, age, zone, team_name, details, status, reviewed_by, reviewed_at, submitted_at) '
            . 'VALUES (:reference, :name, :email, :phone, :category, :age, :zone, :team_name, :details, :status, :reviewed_by, '
            . 'CASE WHEN :is_reviewed=1 THEN UTC_TIMESTAMP() ELSE NULL END, UTC_TIMESTAMP())'
        );
        $statement->execute([
            'reference' => $reference, 'name' => $name, 'email' => $email, 'phone' => $phone, 'category' => $category,
            'age' => $age, 'zone' => $zone, 'team_name' => $teamName, 'details' => $details, 'status' => $status,
            'reviewed_by' => $reviewed, 'is_reviewed' => $reviewed === null ? 0 : 1,
        ]);
        $this->audit($adminId, 'registration.admin_created', 'Created registration ' . $reference);
        return $reference;
    }

    public function deleteRegistration(int $id, int $adminId): void
    {
        $query = $this->pdo->prepare('SELECT reference FROM registrations WHERE id=:id LIMIT 1');
        $query->execute(['id' => $id]);
        $reference = $query->fetchColumn();
        if (!is_string($reference)) {
            throw new InvalidArgumentException('That registration no longer exists.');
        }
        $this->pdo->prepare('DELETE FROM registrations WHERE id=:id')->execute(['id' => $id]);
        $this->audit($adminId, 'registration.admin_deleted', 'Deleted registration ' . $reference);
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
        $referenceInput = $data['reference'] ?? '';
        $reference = is_scalar($referenceInput) ? trim((string) $referenceInput) : '';
        if ($reference === '') {
            $reference = 'YUCT-' . gmdate('ymd') . '-' . strtoupper(bin2hex(random_bytes(4)));
        }
        if (!preg_match('/^[A-Za-z0-9_-]{4,80}$/', $reference)) {
            throw new InvalidArgumentException('Transaction reference must use 4–80 letters, numbers, hyphens, or underscores.');
        }
        $emailInput = $data['recipient_email'] ?? '';
        $email = is_scalar($emailInput) ? trim((string) $emailInput) : '';
        if ($email !== '' && (strlen($email) > 190 || filter_var($email, FILTER_VALIDATE_EMAIL) === false)) {
            throw new InvalidArgumentException('Enter a valid transaction recipient email.');
        }
        $amountRaw = $data['amount'] ?? '';
        $amountInput = is_scalar($amountRaw) ? trim((string) $amountRaw) : '';
        if (preg_match('/^\\d{1,10}(?:\\.\\d{1,2})?$/D', $amountInput) !== 1) {
            throw new InvalidArgumentException('Enter a valid transaction amount with no more than two decimal places.');
        }
        $amount = (float) $amountInput;
        if ($amount > 9999999999.99) {
            throw new InvalidArgumentException('Enter a valid transaction amount.');
        }
        $currencyInput = $data['currency'] ?? 'NGN';
        $currency = is_scalar($currencyInput) ? strtoupper(trim((string) $currencyInput)) : '';
        if (!in_array($currency, ['NGN', 'USD', 'GBP', 'EUR'], true)) {
            throw new InvalidArgumentException('Choose a supported transaction currency.');
        }
        $status = $this->oneOf($data['status'] ?? 'pending', ['pending', 'completed', 'failed', 'refunded'], 'Transaction status');
        $descriptionInput = $data['description'] ?? '';
        $description = mb_substr(is_scalar($descriptionInput) ? trim((string) $descriptionInput) : '', 0, 255);
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
        $this->audit($adminId, 'transaction.manual_created', 'Recorded manually entered transaction ' . $reference);
        return $reference;
    }

    public function updateTransactionStatus(int $id, string $status, int $adminId): void
    {
        $status = $this->oneOf($status, ['pending', 'completed', 'failed', 'refunded'], 'Transaction status');
        $statement = $this->pdo->prepare(
            'SELECT t.reference, t.recipient_email, t.amount, t.currency, t.status, t.payment_provider, t.archived_at, t.description, '
            . 'EXISTS(SELECT 1 FROM shop_orders o WHERE o.transaction_id=t.id) AS has_shop_order '
            . 'FROM transactions t WHERE t.id=:id LIMIT 1'
        );
        $statement->execute(['id' => $id]);
        $row = $statement->fetch();
        if (!is_array($row)) {
            throw new InvalidArgumentException('That transaction no longer exists.');
        }
        if ((string) ($row['payment_provider'] ?? '') === 'payhub' || (int) ($row['has_shop_order'] ?? 0) === 1) {
            throw new InvalidArgumentException('PayHub and shop-order transactions are updated only after server-side payment verification. Manage fulfillment in Shop orders.');
        }
        if ($row['archived_at'] !== null) {
            throw new InvalidArgumentException('Restore the archived transaction before changing its status.');
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
        foreach (['teams', 'team_players', 'venues', 'fixtures', 'registrations', 'transactions'] as $table) {
            $counts[$table] = (int) $this->pdo->query('SELECT COUNT(*) FROM ' . $table)->fetchColumn();
        }
        $counts['players'] = $counts['team_players'];
        unset($counts['team_players']);
        $counts['blocked_ips'] = (int) $this->pdo->query('SELECT COUNT(*) FROM ip_blocks WHERE blocked_until > UTC_TIMESTAMP()')->fetchColumn();
        return $counts;
    }

    /** @param array<string,mixed> $data */
    private function text(mixed $value, int $max, string $label): string
    {
        if (!is_scalar($value)) {
            throw new InvalidArgumentException($label . ' is required and must be no longer than ' . $max . ' characters.');
        }
        $text = trim((string) $value);
        if ($text === '' || mb_strlen($text) > $max) {
            throw new InvalidArgumentException($label . ' is required and must be no longer than ' . $max . ' characters.');
        }
        return $text;
    }

    /** @param list<string> $allowed */
    private function oneOf(mixed $value, array $allowed, string $label): string
    {
        $text = is_scalar($value) ? (string) $value : '';
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
