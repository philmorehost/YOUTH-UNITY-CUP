<?php

declare(strict_types=1);

namespace Yuc\Services;

use PDO;
use Throwable;

final class AuthService
{
    private const FAILURE_LIMIT = 5;
    private const FAILURE_WINDOW_MINUTES = 15;
    private const BLOCK_MINUTES = 15;

    private NotificationService $notifications;
    private int $failureLimit = self::FAILURE_LIMIT;
    private int $failureWindowMinutes = self::FAILURE_WINDOW_MINUTES;
    private int $blockMinutes = self::BLOCK_MINUTES;

    /** @param array<string,mixed> $config */
    public function __construct(private PDO $pdo, array $config)
    {
        $this->notifications = new NotificationService($pdo, $config);
        try {
            $settings = $pdo->query("SELECT setting_key, setting_value FROM system_settings WHERE setting_key IN ('max_login_attempts', 'login_window_minutes', 'block_duration_minutes')")->fetchAll();
            foreach ($settings as $setting) {
                $value = filter_var($setting['setting_value'] ?? null, FILTER_VALIDATE_INT);
                if ($value === false) {
                    continue;
                }
                if ($setting['setting_key'] === 'max_login_attempts' && $value >= 3 && $value <= 20) {
                    $this->failureLimit = $value;
                } elseif ($setting['setting_key'] === 'login_window_minutes' && $value >= 5 && $value <= 120) {
                    $this->failureWindowMinutes = $value;
                } elseif ($setting['setting_key'] === 'block_duration_minutes' && $value >= 5 && $value <= 1440) {
                    $this->blockMinutes = $value;
                }
            }
        } catch (Throwable $exception) {
            error_log('Youth Unity Cup login settings unavailable; using secure defaults (' . get_class($exception) . ').');
        }
    }

    /** @return array{ok:bool,blocked?:bool,message:string} */
    public function authenticate(string $identifier, string $password, string $ipAddress): array
    {
        $identifier = trim($identifier);
        if ($identifier === '' || strlen($identifier) > 190 || $password === '' || strlen($password) > 1024) {
            return ['ok' => false, 'message' => 'Enter your username or email and password.'];
        }
        if (random_int(1, 50) === 1) {
            $this->pdo->exec('DELETE FROM login_attempts WHERE occurred_at < (UTC_TIMESTAMP() - INTERVAL 2 DAY)');
            $this->pdo->exec('DELETE FROM ip_blocks WHERE blocked_until < (UTC_TIMESTAMP() - INTERVAL 2 DAY)');
        }
        $identityKey = strtolower($identifier);
        $ipAddress = filter_var($ipAddress, FILTER_VALIDATE_IP) !== false ? substr($ipAddress, 0, 45) : 'unknown';

        if ($this->isIpBlocked($ipAddress)) {
            $this->recordHistory(null, $identityKey, $ipAddress, 'blocked', 'Temporary security block');
            return ['ok' => false, 'blocked' => true, 'message' => 'Too many attempts. Wait a few minutes before trying again.'];
        }

        $statement = $this->pdo->prepare(
            'SELECT id, full_name, email, username, password_hash, role, status '
            . 'FROM users WHERE username = :username OR email = :email LIMIT 1'
        );
        $statement->execute(['username' => $identifier, 'email' => $identifier]);
        $user = $statement->fetch();
        $valid = is_array($user)
            && $user['status'] === 'active'
            && password_verify($password, (string) $user['password_hash']);

        if (!$valid) {
            $this->recordFailure($identityKey, $ipAddress);
            $this->recordHistory(is_array($user) ? (int) $user['id'] : null, $identityKey, $ipAddress, 'failed', 'Invalid credentials or inactive account');
            $failureCount = $this->recentFailureCount($identityKey, $ipAddress);
            if ($failureCount >= $this->failureLimit) {
                $this->blockIp($ipAddress);
                try {
                    $this->notifications->notifyActivity(
                        'security.login_throttled',
                        'Youth Unity Cup admin sign-in temporarily blocked',
                        'Repeated unsuccessful admin sign-in attempts reached the configured threshold.',
                        [
                            'notify_admin' => true,
                            'ip_address' => $ipAddress,
                            'context' => ['identifier' => $identityKey, 'attempts_in_window' => $failureCount],
                        ]
                    );
                } catch (Throwable $exception) {
                    error_log('Youth Unity Cup security notification could not be queued (' . get_class($exception) . ').');
                }
                return ['ok' => false, 'blocked' => true, 'message' => 'Too many attempts. Wait a few minutes before trying again.'];
            }

            return ['ok' => false, 'message' => 'Those sign-in details were not recognized.'];
        }

        $userId = (int) $user['id'];
        if (password_needs_rehash((string) $user['password_hash'], PASSWORD_DEFAULT)) {
            $rehash = password_hash($password, PASSWORD_DEFAULT);
            if (is_string($rehash)) {
                $updateHash = $this->pdo->prepare('UPDATE users SET password_hash = :hash WHERE id = :id');
                $updateHash->execute(['hash' => $rehash, 'id' => $userId]);
            }
        }

        $update = $this->pdo->prepare('UPDATE users SET last_login_at = UTC_TIMESTAMP() WHERE id = :id');
        $update->execute(['id' => $userId]);
        $this->recordHistory($userId, $identityKey, $ipAddress, 'success', 'Admin sign-in successful');

        $clearAttempts = $this->pdo->prepare('DELETE FROM login_attempts WHERE identifier = :identifier OR ip_address = :ip');
        $clearAttempts->execute(['identifier' => $identityKey, 'ip' => $ipAddress]);
        $clearBlock = $this->pdo->prepare('DELETE FROM ip_blocks WHERE ip_address = :ip');
        $clearBlock->execute(['ip' => $ipAddress]);

        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
            $_SESSION['admin_user'] = [
                'id' => $userId,
                'name' => (string) $user['full_name'],
                'email' => (string) $user['email'],
                'username' => (string) $user['username'],
                'role' => (string) $user['role'],
            ];
        }

        try {
            $this->notifications->notifyActivity(
                'auth.login_success',
                'Youth Unity Cup admin sign-in',
                'An administrator signed in to the Youth Unity Cup control panel.',
                [
                    'user_email' => (string) $user['email'],
                    'user_id' => $userId,
                    'notify_admin' => true,
                    'ip_address' => $ipAddress,
                    'context' => ['username' => (string) $user['username']],
                ]
            );
        } catch (Throwable $exception) {
            error_log('Youth Unity Cup sign-in notification could not be queued (' . get_class($exception) . ').');
        }

        return ['ok' => true, 'message' => 'Signed in successfully.'];
    }

    public function requestPasswordReset(string $email, string $baseUrl, string $ipAddress): void
    {
        $email = trim($email);
        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false || $baseUrl === '') {
            return;
        }
        $statement = $this->pdo->prepare("SELECT id, full_name, email FROM users WHERE email=:email AND status='active' LIMIT 1");
        $statement->execute(['email' => $email]);
        $user = $statement->fetch();
        if (!is_array($user)) {
            return;
        }

        $throttle = $this->pdo->prepare(
            'SELECT id FROM password_reset_tokens WHERE user_id=:user_id AND created_at >= (UTC_TIMESTAMP() - INTERVAL 1 MINUTE) LIMIT 1'
        );
        $throttle->execute(['user_id' => (int) $user['id']]);
        if ($throttle->fetchColumn() !== false) {
            return;
        }

        $token = bin2hex(random_bytes(32));
        $this->pdo->beginTransaction();
        try {
            $invalidate = $this->pdo->prepare(
                'UPDATE password_reset_tokens SET used_at=UTC_TIMESTAMP() WHERE user_id=:user_id AND used_at IS NULL'
            );
            $invalidate->execute(['user_id' => (int) $user['id']]);
            $insert = $this->pdo->prepare(
                'INSERT INTO password_reset_tokens (user_id, token_hash, expires_at, created_at) '
                . 'VALUES (:user_id, :token_hash, DATE_ADD(UTC_TIMESTAMP(), INTERVAL 60 MINUTE), UTC_TIMESTAMP())'
            );
            $insert->execute(['user_id' => (int) $user['id'], 'token_hash' => hash('sha256', $token)]);
            $this->pdo->commit();
        } catch (Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $exception;
        }

        $resetUrl = rtrim($baseUrl, '/') . '/admin/reset-password?token=' . rawurlencode($token);
        $this->notifications->notifyActivity(
            'auth.password_reset_requested',
            'Youth Unity Cup admin password reset',
            "A password reset was requested for your Youth Unity Cup administrator account.\n\nUse this one-time link within 60 minutes:\n{$resetUrl}\n\nIf you did not request a reset, you can ignore this message.",
            [
                'user_email' => (string) $user['email'],
                'user_id' => (int) $user['id'],
                'notify_admin' => false,
                'ip_address' => $ipAddress,
                'include_ip' => false,
                'context' => ['requested_for' => (string) $user['email']],
            ]
        );
    }

    public function completePasswordReset(string $token, string $newPassword): bool
    {
        if (preg_match('/^[a-f0-9]{64}$/i', $token) !== 1 || strlen($newPassword) < 12 || strlen($newPassword) > 1024) {
            return false;
        }
        $token = strtolower($token);
        $passwordHash = password_hash($newPassword, PASSWORD_DEFAULT);
        if (!is_string($passwordHash)) {
            return false;
        }

        $this->pdo->beginTransaction();
        try {
            $lookup = $this->pdo->prepare(
                'SELECT t.id AS token_id, t.user_id, u.email, u.full_name FROM password_reset_tokens t '
                . 'INNER JOIN users u ON u.id=t.user_id WHERE t.token_hash=:token_hash AND t.used_at IS NULL '
                . 'AND t.expires_at > UTC_TIMESTAMP() AND u.status=\'active\' LIMIT 1 FOR UPDATE'
            );
            $lookup->execute(['token_hash' => hash('sha256', $token)]);
            $record = $lookup->fetch();
            if (!is_array($record)) {
                $this->pdo->rollBack();
                return false;
            }
            $updatePassword = $this->pdo->prepare('UPDATE users SET password_hash=:password_hash, updated_at=UTC_TIMESTAMP() WHERE id=:id');
            $updatePassword->execute(['password_hash' => $passwordHash, 'id' => (int) $record['user_id']]);
            $consume = $this->pdo->prepare('UPDATE password_reset_tokens SET used_at=UTC_TIMESTAMP() WHERE user_id=:user_id AND used_at IS NULL');
            $consume->execute(['user_id' => (int) $record['user_id']]);
            $audit = $this->pdo->prepare(
                'INSERT INTO audit_logs (user_id, event_key, description, ip_address, created_at) '
                . 'VALUES (:user_id, \'auth.password_reset_completed\', :description, :ip, UTC_TIMESTAMP())'
            );
            $audit->execute(['user_id' => (int) $record['user_id'], 'description' => 'Administrator password was reset', 'ip' => function_exists('yuc_client_ip') ? yuc_client_ip() : 'unknown']);
            $this->pdo->commit();
        } catch (Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $exception;
        }

        try {
            $this->notifications->notifyActivity(
                'auth.password_reset_completed',
                'Youth Unity Cup admin password changed',
                'The password for your administrator account was changed using a one-time reset link. If you did not make this change, contact the tournament owner immediately.',
                ['user_email' => (string) $record['email'], 'user_id' => (int) $record['user_id'], 'notify_admin' => true]
            );
        } catch (Throwable $exception) {
            error_log('Youth Unity Cup password reset confirmation could not be queued (' . get_class($exception) . ').');
        }
        return true;
    }

    private function isIpBlocked(string $ipAddress): bool
    {
        $statement = $this->pdo->prepare('SELECT blocked_until FROM ip_blocks WHERE ip_address = :ip LIMIT 1');
        $statement->execute(['ip' => $ipAddress]);
        $blockedUntil = $statement->fetchColumn();
        if (!is_string($blockedUntil) || $blockedUntil === '') {
            return false;
        }
        if (strtotime($blockedUntil . ' UTC') > time()) {
            return true;
        }

        $delete = $this->pdo->prepare('DELETE FROM ip_blocks WHERE ip_address = :ip');
        $delete->execute(['ip' => $ipAddress]);
        return false;
    }

    private function recordFailure(string $identifier, string $ipAddress): void
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO login_attempts (identifier, ip_address, occurred_at) VALUES (:identifier, :ip, UTC_TIMESTAMP())'
        );
        $statement->execute(['identifier' => $identifier, 'ip' => $ipAddress]);
    }

    private function recentFailureCount(string $identifier, string $ipAddress): int
    {
        $statement = $this->pdo->prepare(
            'SELECT COUNT(*) FROM login_attempts '
            . 'WHERE occurred_at >= (UTC_TIMESTAMP() - INTERVAL ' . $this->failureWindowMinutes . ' MINUTE) '
            . 'AND (identifier = :identifier OR ip_address = :ip)'
        );
        $statement->execute(['identifier' => $identifier, 'ip' => $ipAddress]);
        return (int) $statement->fetchColumn();
    }

    private function blockIp(string $ipAddress): void
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO ip_blocks (ip_address, blocked_until, reason, created_at) '
            . 'VALUES (:ip, DATE_ADD(UTC_TIMESTAMP(), INTERVAL ' . $this->blockMinutes . ' MINUTE), :reason, UTC_TIMESTAMP()) '
            . "ON DUPLICATE KEY UPDATE blocked_until = DATE_ADD(UTC_TIMESTAMP(), INTERVAL " . $this->blockMinutes . " MINUTE), reason = 'Repeated failed admin sign-in attempts', created_at = UTC_TIMESTAMP()"
        );
        $statement->execute(['ip' => $ipAddress, 'reason' => 'Repeated failed admin sign-in attempts']);
    }

    private function recordHistory(?int $userId, string $identifier, string $ipAddress, string $outcome, string $details): void
    {
        $userAgent = substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255);
        try {
            $statement = $this->pdo->prepare(
                'INSERT INTO login_history (user_id, identifier, ip_address, outcome, details, user_agent, occurred_at) '
                . 'VALUES (:user_id, :identifier, :ip, :outcome, :details, :user_agent, UTC_TIMESTAMP())'
            );
            $statement->execute([
                'user_id' => $userId,
                'identifier' => $identifier,
                'ip' => $ipAddress,
                'outcome' => $outcome,
                'details' => $details,
                'user_agent' => $userAgent,
            ]);
        } catch (Throwable $exception) {
            error_log('Youth Unity Cup sign-in history could not be saved (' . get_class($exception) . ').');
        }
    }
}
