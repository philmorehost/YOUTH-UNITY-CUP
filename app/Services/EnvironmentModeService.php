<?php

declare(strict_types=1);

namespace Yuc\Services;

use DateTimeImmutable;
use DateTimeZone;
use JsonException;
use PDO;
use Throwable;

/**
 * Switches only the tournament directory (teams, rosters, venues, fixtures)
 * between the live dataset and a self-contained demo dataset. The production
 * rows are persisted in a single protected snapshot before demo content is
 * installed, and are restored in one database transaction when returning live.
 */
final class EnvironmentModeService
{
    private const MODE_SETTING = 'site_mode';
    private const SNAPSHOT_ID = 1;
    private const SNAPSHOT_VERSION = 1;
    private const SNAPSHOT_TABLES = ['venues', 'teams', 'team_players', 'fixtures'];

    /** @var list<string> */
    private const POSITIONS = [
        'Goalkeeper', 'Defender', 'Defender', 'Defender', 'Defender',
        'Midfielder', 'Midfielder', 'Midfielder', 'Forward', 'Forward', 'Forward',
    ];

    /** @var list<string> */
    private const FIRST_NAMES = [
        'Ayo', 'Damilola', 'Chinedu', 'Tunde', 'Ibrahim', 'Oluwaseun', 'Femi', 'Chisom',
        'Abdul', 'Kehinde', 'Samuel', 'Musa', 'Tochukwu', 'David', 'Adeola', 'Jide',
        'Emeka', 'Tolu', 'Seyi', 'Babatunde', 'Aminu', 'Nkem', 'Kola', 'Zainab',
    ];

    /** @var list<string> */
    private const LAST_NAMES = [
        'Adeyemi', 'Okafor', 'Balogun', 'Ibrahim', 'Oladipo', 'Ogunleye', 'Nwankwo', 'Bakare',
        'Okonkwo', 'Bello', 'Udo', 'Akinyemi', 'Obi', 'Hassan', 'Fashola', 'Danjuma',
        'Lawal', 'Olawale', 'Ekong', 'Ogu',
    ];

    public function __construct(private PDO $pdo)
    {
    }

    public function currentMode(): string
    {
        $statement = $this->pdo->prepare(
            'SELECT setting_value FROM system_settings WHERE setting_key=:setting_key LIMIT 1'
        );
        $statement->execute(['setting_key' => self::MODE_SETTING]);
        return $statement->fetchColumn() === 'demo' ? 'demo' : 'production';
    }

    public function isDemo(): bool
    {
        return $this->currentMode() === 'demo';
    }

    /** @return list<array{name:string,zone:string,group_name:string}> */
    public static function demoTeams(): array
    {
        return [
            ['name' => 'Unity Stars', 'zone' => 'Mushin Central', 'group_name' => 'A'],
            ['name' => 'Palm Avenue FC', 'zone' => 'Palm Avenue', 'group_name' => 'A'],
            ['name' => 'Papa Ajao Eagles', 'zone' => 'Papa Ajao', 'group_name' => 'A'],
            ['name' => 'Idi-Oro Athletic', 'zone' => 'Idi-Oro', 'group_name' => 'A'],
            ['name' => 'Ladipo Rovers', 'zone' => 'Ladipo', 'group_name' => 'B'],
            ['name' => 'Ilasamaja United', 'zone' => 'Ilasamaja', 'group_name' => 'B'],
            ['name' => 'Ajao City', 'zone' => 'Ajao Estate', 'group_name' => 'B'],
            ['name' => 'Isolo Young Lions', 'zone' => 'Isolo', 'group_name' => 'B'],
            ['name' => 'Itire Community FC', 'zone' => 'Itire', 'group_name' => 'C'],
            ['name' => 'Ijesha Falcons', 'zone' => 'Ijesha', 'group_name' => 'C'],
            ['name' => 'Odi-Olowo Rangers', 'zone' => 'Odi-Olowo', 'group_name' => 'C'],
            ['name' => 'Bariga Youth FC', 'zone' => 'Bariga', 'group_name' => 'C'],
            ['name' => 'Olorunsogo City', 'zone' => 'Olorunsogo', 'group_name' => 'D'],
            ['name' => 'Surulere Strikers', 'zone' => 'Surulere', 'group_name' => 'D'],
            ['name' => 'Agege Express', 'zone' => 'Agege', 'group_name' => 'D'],
            ['name' => 'Mainland Athletic', 'zone' => 'Lagos Mainland', 'group_name' => 'D'],
        ];
    }

    /** @return list<array{name:string,zone:string,address:string,capacity:int}> */
    public static function demoVenues(): array
    {
        return [
            ['name' => 'Alaafia Community Ground', 'zone' => 'Zone 1', 'address' => 'Alaafia Street, Mushin, Lagos', 'capacity' => 700],
            ['name' => 'Atewolara Memorial Field', 'zone' => 'Zone 2', 'address' => 'Atewolara Road, Mushin, Lagos', 'capacity' => 650],
            ['name' => 'Idi-Oro Community Pitch', 'zone' => 'Zone 3', 'address' => 'Idi-Oro, Mushin, Lagos', 'capacity' => 600],
            ['name' => 'Papa Ajao Sports Ground', 'zone' => 'Zone 4', 'address' => 'Papa Ajao, Mushin, Lagos', 'capacity' => 750],
            ['name' => 'Ladipo Football Ground', 'zone' => 'Zone 5', 'address' => 'Ladipo Market Road, Mushin, Lagos', 'capacity' => 650],
            ['name' => 'Ilasamaja Unity Park', 'zone' => 'Zone 6', 'address' => 'Ilasamaja, Mushin, Lagos', 'capacity' => 700],
            ['name' => 'Itire Youth Sports Field', 'zone' => 'Zone 7', 'address' => 'Itire Road, Lagos', 'capacity' => 600],
            ['name' => 'Ijesha Community Stadium', 'zone' => 'Zone 8', 'address' => 'Ijesha, Lagos', 'capacity' => 800],
        ];
    }

    /**
     * @return bool True when the site changed modes; false when already in demo mode.
     */
    public function switchToDemo(int $adminId, string $timezone = 'Africa/Lagos'): bool
    {
        $this->pdo->beginTransaction();
        try {
            $mode = $this->lockMode();
            if ($mode === 'demo') {
                $this->pdo->commit();
                return false;
            }

            $snapshotStatement = $this->pdo->prepare(
                'SELECT id FROM site_mode_snapshots WHERE id=:id LIMIT 1 FOR UPDATE'
            );
            $snapshotStatement->execute(['id' => self::SNAPSHOT_ID]);
            if ($snapshotStatement->fetchColumn() !== false) {
                throw new EnvironmentModeException('A saved production snapshot already exists. Restore production before starting another demo session.');
            }

            $snapshot = ['version' => self::SNAPSHOT_VERSION, 'tables' => []];
            foreach (self::SNAPSHOT_TABLES as $table) {
                $snapshot['tables'][$table] = $this->readTable($table);
            }
            $snapshotJson = json_encode(
                $snapshot,
                JSON_THROW_ON_ERROR | JSON_INVALID_UTF8_SUBSTITUTE | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
            );
            $saveSnapshot = $this->pdo->prepare(
                'INSERT INTO site_mode_snapshots (id, snapshot_json, created_by, created_at) '
                . 'VALUES (:id, :snapshot_json, :created_by, UTC_TIMESTAMP())'
            );
            $saveSnapshot->execute([
                'id' => self::SNAPSHOT_ID,
                'snapshot_json' => $snapshotJson,
                'created_by' => $adminId > 0 ? $adminId : null,
            ]);

            $this->clearTournamentContent();
            $counts = $this->seedDemoContent($timezone);
            $this->setMode('demo');
            $this->audit(
                $adminId,
                'system.demo_mode_enabled',
                'Switched to Demo mode; production tournament data was backed up.',
                ['demo_teams' => $counts['teams'], 'demo_players' => $counts['players'], 'demo_venues' => $counts['venues'], 'demo_fixtures' => $counts['fixtures']]
            );
            $this->pdo->commit();
            return true;
        } catch (Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $exception;
        }
    }

    /**
     * @return bool True when the site changed modes; false when already in production mode.
     */
    public function switchToProduction(int $adminId): bool
    {
        $this->pdo->beginTransaction();
        try {
            $mode = $this->lockMode();
            if ($mode === 'production') {
                $this->pdo->commit();
                return false;
            }

            $snapshotStatement = $this->pdo->prepare(
                'SELECT snapshot_json FROM site_mode_snapshots WHERE id=:id LIMIT 1 FOR UPDATE'
            );
            $snapshotStatement->execute(['id' => self::SNAPSHOT_ID]);
            $snapshotJson = $snapshotStatement->fetchColumn();
            if (!is_string($snapshotJson) || $snapshotJson === '') {
                throw new EnvironmentModeException('The production snapshot is missing. Demo mode remains active so no data is silently discarded.');
            }

            try {
                $snapshot = json_decode($snapshotJson, true, 512, JSON_THROW_ON_ERROR);
            } catch (JsonException $exception) {
                throw new EnvironmentModeException('The production snapshot could not be read. Demo mode remains active and its current content has not been changed.', 0, $exception);
            }
            if (!is_array($snapshot)
                || (int) ($snapshot['version'] ?? 0) !== self::SNAPSHOT_VERSION
                || !is_array($snapshot['tables'] ?? null)) {
                throw new EnvironmentModeException('The production snapshot format is not supported. Demo mode remains active and its current content has not been changed.');
            }
            foreach (self::SNAPSHOT_TABLES as $table) {
                if (!is_array($snapshot['tables'][$table] ?? null)) {
                    throw new EnvironmentModeException('The production snapshot is incomplete. Demo mode remains active and its current content has not been changed.');
                }
            }

            $this->clearTournamentContent();
            foreach (self::SNAPSHOT_TABLES as $table) {
                $this->restoreTable($table, $snapshot['tables'][$table]);
            }
            $this->setMode('production');
            $this->pdo->prepare('DELETE FROM site_mode_snapshots WHERE id=:id')->execute(['id' => self::SNAPSHOT_ID]);
            $this->audit(
                $adminId,
                'system.production_mode_restored',
                'Switched to Production mode and restored the saved live tournament dataset.',
                ['restored_tables' => self::SNAPSHOT_TABLES]
            );
            $this->pdo->commit();
            return true;
        } catch (Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $exception;
        }
    }

    private function lockMode(): string
    {
        $ensure = $this->pdo->prepare(
            'INSERT INTO system_settings (setting_key, setting_value, updated_at) '
            . 'VALUES (:setting_key, :setting_value, UTC_TIMESTAMP()) '
            . 'ON DUPLICATE KEY UPDATE setting_key=VALUES(setting_key)'
        );
        $ensure->execute(['setting_key' => self::MODE_SETTING, 'setting_value' => 'production']);
        $statement = $this->pdo->prepare(
            'SELECT setting_value FROM system_settings WHERE setting_key=:setting_key LIMIT 1 FOR UPDATE'
        );
        $statement->execute(['setting_key' => self::MODE_SETTING]);
        return $statement->fetchColumn() === 'demo' ? 'demo' : 'production';
    }

    private function setMode(string $mode): void
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO system_settings (setting_key, setting_value, updated_at) '
            . 'VALUES (:setting_key, :setting_value, UTC_TIMESTAMP()) '
            . 'ON DUPLICATE KEY UPDATE setting_value=:updated_value, updated_at=UTC_TIMESTAMP()'
        );
        $statement->execute([
            'setting_key' => self::MODE_SETTING,
            'setting_value' => $mode,
            'updated_value' => $mode,
        ]);
    }

    /** @return list<array<string,mixed>> */
    private function readTable(string $table): array
    {
        $this->assertSnapshotTable($table);
        return $this->pdo->query('SELECT * FROM `' . $table . '` ORDER BY `id`')->fetchAll(PDO::FETCH_ASSOC);
    }

    private function clearTournamentContent(): void
    {
        foreach (['fixtures', 'team_players', 'teams', 'venues'] as $table) {
            $this->assertSnapshotTable($table);
            $this->pdo->exec('DELETE FROM `' . $table . '`');
        }
    }

    /** @param list<array<string,mixed>> $rows */
    private function restoreTable(string $table, array $rows): void
    {
        $this->assertSnapshotTable($table);
        if ($rows === []) {
            return;
        }

        $columnStatement = $this->pdo->query('SHOW COLUMNS FROM `' . $table . '`');
        $validColumns = [];
        foreach ($columnStatement->fetchAll(PDO::FETCH_ASSOC) as $column) {
            if (isset($column['Field']) && is_string($column['Field'])) {
                $validColumns[$column['Field']] = true;
            }
        }

        foreach ($rows as $row) {
            if (!is_array($row) || $row === []) {
                throw new EnvironmentModeException('A saved production row is malformed. No rows were restored.');
            }
            $columns = array_keys($row);
            foreach ($columns as $column) {
                if (!is_string($column) || !isset($validColumns[$column]) || preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/D', $column) !== 1) {
                    throw new EnvironmentModeException('The production snapshot contains an unsupported database column. No rows were restored.');
                }
            }
            $quotedColumns = implode(', ', array_map(static fn (string $column): string => '`' . $column . '`', $columns));
            $placeholders = [];
            $parameters = [];
            foreach ($columns as $index => $column) {
                $placeholder = 'snapshot_' . $index;
                $placeholders[] = ':' . $placeholder;
                $parameters[$placeholder] = $row[$column];
            }
            $insert = $this->pdo->prepare(
                'INSERT INTO `' . $table . '` (' . $quotedColumns . ') VALUES (' . implode(', ', $placeholders) . ')'
            );
            $insert->execute($parameters);
        }
    }

    /** @param array<string,mixed> $context */
    private function audit(int $adminId, string $event, string $description, array $context = []): void
    {
        $contextJson = $context === []
            ? null
            : json_encode($context, JSON_THROW_ON_ERROR | JSON_INVALID_UTF8_SUBSTITUTE | JSON_UNESCAPED_SLASHES);
        $statement = $this->pdo->prepare(
            'INSERT INTO audit_logs (user_id, event_key, description, ip_address, context_json, created_at) '
            . 'VALUES (:user_id, :event_key, :description, :ip_address, :context_json, UTC_TIMESTAMP())'
        );
        $statement->execute([
            'user_id' => $adminId > 0 ? $adminId : null,
            'event_key' => $event,
            'description' => mb_substr($description, 0, 255),
            'ip_address' => function_exists('yuc_client_ip') ? yuc_client_ip() : 'unknown',
            'context_json' => $contextJson,
        ]);
    }

    /** @return array{teams:int,players:int,venues:int,fixtures:int} */
    private function seedDemoContent(string $timezone): array
    {
        if (!in_array($timezone, DateTimeZone::listIdentifiers(), true)) {
            $timezone = 'Africa/Lagos';
        }
        $zone = new DateTimeZone($timezone);
        $venueIds = [];
        $venueInsert = $this->pdo->prepare(
            "INSERT INTO venues (name, zone, address, capacity, status, created_at, updated_at) "
            . "VALUES (:name, :zone, :address, :capacity, 'active', UTC_TIMESTAMP(), UTC_TIMESTAMP())"
        );
        foreach (self::demoVenues() as $venue) {
            $venueInsert->execute($venue);
            $venueIds[] = (int) $this->pdo->lastInsertId();
        }

        $teamIds = [];
        $teamInsert = $this->pdo->prepare(
            "INSERT INTO teams (name, zone, group_name, contact_email, status, notes, created_at, updated_at) "
            . "VALUES (:name, :zone, :group_name, '', 'active', 'Seeded demo team for preview only.', UTC_TIMESTAMP(), UTC_TIMESTAMP())"
        );
        foreach (self::demoTeams() as $team) {
            $teamInsert->execute($team);
            $teamIds[$team['name']] = (int) $this->pdo->lastInsertId();
        }

        $playerInsert = $this->pdo->prepare(
            "INSERT INTO team_players (team_id, full_name, jersey_number, position, age, hometown, bio, photo_url, avatar_variant, status, created_at, updated_at) "
            . "VALUES (:team_id, :full_name, :jersey_number, :position, :age, :hometown, :bio, '', :avatar_variant, 'active', UTC_TIMESTAMP(), UTC_TIMESTAMP())"
        );
        $globalPlayerIndex = 0;
        foreach (self::demoTeams() as $teamIndex => $team) {
            foreach (self::POSITIONS as $playerIndex => $position) {
                $firstName = self::FIRST_NAMES[$globalPlayerIndex % count(self::FIRST_NAMES)];
                $lastName = self::LAST_NAMES[intdiv($globalPlayerIndex, count(self::FIRST_NAMES)) % count(self::LAST_NAMES)];
                $playerInsert->execute([
                    'team_id' => $teamIds[$team['name']],
                    'full_name' => $firstName . ' ' . $lastName,
                    'jersey_number' => $playerIndex + 1,
                    'position' => $position,
                    'age' => 15 + (($teamIndex + $playerIndex) % 4),
                    'hometown' => $team['zone'] . ', Lagos',
                    'bio' => $this->demoPlayerBio($position),
                    'avatar_variant' => ($globalPlayerIndex % 8) + 1,
                ]);
                $globalPlayerIndex++;
            }
        }

        $fixtureInsert = $this->pdo->prepare(
            'INSERT INTO fixtures (home_team_id, away_team_id, venue_id, stage, kickoff_at, status, home_score, away_score, notes, created_at, updated_at) '
            . 'VALUES (:home_team_id, :away_team_id, :venue_id, :stage, :kickoff_at, :status, :home_score, :away_score, :notes, UTC_TIMESTAMP(), UTC_TIMESTAMP())'
        );
        $nextSaturday = (new DateTimeImmutable('next saturday', $zone))->setTime(15, 0);
        $scores = [[2, 1], [1, 1], [0, 1], [3, 0]];
        $fixtureCount = 0;
        foreach (['A', 'B', 'C', 'D'] as $groupIndex => $groupName) {
            $groupTeams = array_values(array_filter(
                self::demoTeams(),
                static fn (array $team): bool => $team['group_name'] === $groupName
            ));
            $pairIndex = 0;
            for ($home = 0; $home < count($groupTeams); $home++) {
                for ($away = $home + 1; $away < count($groupTeams); $away++) {
                    $completed = $pairIndex === 0;
                    $kickoff = $completed
                        ? $nextSaturday->modify('-7 days')
                        : $nextSaturday->modify('+' . ($pairIndex - 1) . ' weeks');
                    $kickoff = $kickoff->modify('+' . (($groupIndex * 45) % 180) . ' minutes');
                    $score = $scores[$groupIndex];
                    $fixtureInsert->execute([
                        'home_team_id' => $teamIds[$groupTeams[$home]['name']],
                        'away_team_id' => $teamIds[$groupTeams[$away]['name']],
                        'venue_id' => $venueIds[($groupIndex + $pairIndex) % count($venueIds)],
                        'stage' => 'Group ' . $groupName,
                        'kickoff_at' => $kickoff->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s'),
                        'status' => $completed ? 'completed' : 'scheduled',
                        'home_score' => $completed ? $score[0] : null,
                        'away_score' => $completed ? $score[1] : null,
                        'notes' => $completed
                            ? 'Demo result — sample score for preview only.'
                            : 'Demo schedule — sample fixture for preview only.',
                    ]);
                    $fixtureCount++;
                    $pairIndex++;
                }
            }
        }

        return [
            'teams' => count($teamIds),
            'players' => $globalPlayerIndex,
            'venues' => count($venueIds),
            'fixtures' => $fixtureCount,
        ];
    }

    private function demoPlayerBio(string $position): string
    {
        return match ($position) {
            'Goalkeeper' => 'A confident shot-stopper with quick hands and calm distribution.',
            'Defender' => 'A disciplined defender who reads the game and wins the ball cleanly.',
            'Midfielder' => 'A tireless link player with a sharp first touch and an eye for a pass.',
            'Forward' => 'A direct attacker who presses high and looks for space behind the line.',
            default => 'A hardworking squad player who brings energy and teamwork to every match.',
        };
    }

    private function assertSnapshotTable(string $table): void
    {
        if (!in_array($table, self::SNAPSHOT_TABLES, true)) {
            throw new EnvironmentModeException('The requested tournament snapshot table is not allowed.');
        }
    }
}
