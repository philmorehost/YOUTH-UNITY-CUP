<?php

declare(strict_types=1);

namespace Yuc\Services;

use PDO;
use Throwable;

final class NotificationService
{
    private ?SmtpMailer $mailer = null;
    private string $adminEmail = '';

    /** @param array<string,mixed> $config */
    public function __construct(private PDO $pdo, private array $config)
    {
        $mailSettings = is_array($config['mail'] ?? null) ? $config['mail'] : [];
        $this->adminEmail = (string) ($mailSettings['notifications_to'] ?? $config['admin']['email'] ?? '');
        $mailer = new SmtpMailer($mailSettings);
        if ($mailer->isConfigured()) {
            $this->mailer = $mailer;
        }
    }

    public function isConfigured(): bool
    {
        return $this->mailer !== null;
    }

    /**
     * Store an audit event and an email in the outbox before attempting delivery.
     * Options: user_email, user_id, notify_admin, ip_address, include_ip, context, record_audit.
     *
     * @param array<string,mixed> $options
     */
    public function notifyActivity(string $eventKey, string $subject, string $body, array $options = []): void
    {
        $eventKey = preg_match('/^[a-z0-9._-]{1,80}$/i', $eventKey) === 1 ? $eventKey : 'system.activity';
        $ip = (string) ($options['ip_address'] ?? (function_exists('yuc_client_ip') ? yuc_client_ip() : 'unknown'));
        if (filter_var($ip, FILTER_VALIDATE_IP) === false) {
            $ip = 'unknown';
        }

        $userId = isset($options['user_id']) ? (int) $options['user_id'] : null;
        $context = is_array($options['context'] ?? null) ? $options['context'] : [];
        if (($options['record_audit'] ?? true) !== false) {
            $this->writeAudit($eventKey, $subject, $ip, $userId, $context);
        }

        $bodyLines = [
            trim($body),
            '',
            'Event: ' . $eventKey,
            'Occurred at (UTC): ' . gmdate('Y-m-d H:i:s'),
        ];
        if (($options['include_ip'] ?? true) === true) {
            $bodyLines[] = 'Source IP: ' . $ip;
        }
        if ($context !== []) {
            $bodyLines[] = '';
            $bodyLines[] = 'Activity details:';
            foreach ($context as $key => $value) {
                if (is_scalar($value) || $value === null) {
                    $bodyLines[] = '- ' . (string) $key . ': ' . (string) ($value ?? '');
                }
            }
        }
        $message = implode("\n", $bodyLines);

        $recipients = [];
        $userEmail = trim((string) ($options['user_email'] ?? ''));
        if ($userEmail !== '' && filter_var($userEmail, FILTER_VALIDATE_EMAIL) !== false) {
            $recipients[] = $userEmail;
        }
        if (($options['notify_admin'] ?? true) === true && $this->adminEmail !== '' && filter_var($this->adminEmail, FILTER_VALIDATE_EMAIL) !== false) {
            $recipients[] = $this->adminEmail;
        }

        foreach (array_unique($recipients) as $recipient) {
            $id = $this->enqueue($eventKey, $recipient, $subject, $message);
            if ($this->mailer !== null) {
                $this->deliver($id);
            } else {
                $this->markUnconfigured($id);
            }
        }
    }

    /** Notify a customer and administrators when a transaction changes. */
    public function notifyTransaction(string $event, array $transaction, ?string $recipientEmail = null, ?int $userId = null): void
    {
        $reference = trim((string) ($transaction['reference'] ?? 'not provided'));
        $status = trim((string) ($transaction['status'] ?? $event));
        $amount = number_format((float) ($transaction['amount'] ?? 0), 2, '.', ' ');
        $currency = strtoupper(substr((string) ($transaction['currency'] ?? 'NGN'), 0, 3));
        $description = trim((string) ($transaction['description'] ?? ''));

        $this->notifyActivity(
            'transaction.' . $event,
            'Youth Unity Cup transaction update: ' . $status,
            "A transaction has been updated.\nReference: {$reference}\nAmount: {$currency} {$amount}\nStatus: {$status}\nDescription: {$description}",
            [
                'user_email' => $recipientEmail,
                'user_id' => $userId,
                'include_ip' => false,
                'context' => [
                    'reference' => $reference,
                    'amount' => $currency . ' ' . $amount,
                    'status' => $status,
                ],
            ]
        );
    }

    /** @return array{sent:int,queued:int,failed:int} */
    public function dispatchQueued(int $limit = 25): array
    {
        $result = ['sent' => 0, 'queued' => 0, 'failed' => 0];
        if ($this->mailer === null) {
            return $result;
        }

        $limit = max(1, min(100, $limit));
        $statement = $this->pdo->query(
            'SELECT id FROM notification_outbox WHERE status = \'queued\' '
            . 'AND (next_attempt_at IS NULL OR next_attempt_at <= UTC_TIMESTAMP()) '
            . 'ORDER BY id ASC LIMIT ' . $limit
        );
        $rows = $statement->fetchAll(PDO::FETCH_COLUMN);

        foreach ($rows as $id) {
            $this->deliver((int) $id);
            $after = $this->statusFor((int) $id);
            if ($after === 'sent') {
                $result['sent']++;
            } elseif ($after === 'failed') {
                $result['failed']++;
            } else {
                $result['queued']++;
            }
        }

        return $result;
    }

    /** @param array<string,mixed> $context */
    private function writeAudit(string $eventKey, string $description, string $ip, ?int $userId, array $context): void
    {
        try {
            $statement = $this->pdo->prepare(
                'INSERT INTO audit_logs (user_id, event_key, description, ip_address, context_json, created_at) '
                . 'VALUES (:user_id, :event_key, :description, :ip, :context, UTC_TIMESTAMP())'
            );
            $statement->execute([
                'user_id' => $userId,
                'event_key' => $eventKey,
                'description' => mb_substr($description, 0, 255),
                'ip' => $ip,
                'context' => $context === [] ? null : json_encode($context, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE),
            ]);
        } catch (Throwable $exception) {
            error_log('Youth Unity Cup audit event could not be saved (' . get_class($exception) . ').');
        }
    }

    private function enqueue(string $eventKey, string $recipient, string $subject, string $body): int
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO notification_outbox (event_key, recipient_email, subject, body_text, status, created_at) '
            . 'VALUES (:event_key, :recipient, :subject, :body, \'queued\', UTC_TIMESTAMP())'
        );
        $statement->execute([
            'event_key' => $eventKey,
            'recipient' => $recipient,
            'subject' => mb_substr($subject, 0, 200),
            'body' => $body,
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    private function deliver(int $id): void
    {
        if ($this->mailer === null) {
            $this->markUnconfigured($id);
            return;
        }

        $statement = $this->pdo->prepare(
            'SELECT recipient_email, subject, body_text, status FROM notification_outbox WHERE id = :id LIMIT 1'
        );
        $statement->execute(['id' => $id]);
        $message = $statement->fetch();
        if (!is_array($message) || $message['status'] !== 'queued') {
            return;
        }

        try {
            $this->mailer->send(
                (string) $message['recipient_email'],
                (string) $message['subject'],
                (string) $message['body_text']
            );
            $update = $this->pdo->prepare(
                'UPDATE notification_outbox SET status = \'sent\', attempts = attempts + 1, '
                . 'sent_at = UTC_TIMESTAMP(), next_attempt_at = NULL, last_error = NULL WHERE id = :id'
            );
            $update->execute(['id' => $id]);
        } catch (Throwable $exception) {
            $current = $this->pdo->prepare('SELECT attempts FROM notification_outbox WHERE id = :id LIMIT 1');
            $current->execute(['id' => $id]);
            $attempts = (int) $current->fetchColumn() + 1;
            $status = $attempts >= 5 ? 'failed' : 'queued';
            $delaySeconds = min(3600, 60 * (2 ** min(5, max(0, $attempts - 1))));
            $nextAttempt = gmdate('Y-m-d H:i:s', time() + $delaySeconds);
            $error = mb_substr(preg_replace('/[\r\n]+/', ' ', $exception->getMessage()) ?? 'Delivery failed.', 0, 500);
            $update = $this->pdo->prepare(
                'UPDATE notification_outbox SET status = :status, attempts = :attempts, '
                . 'next_attempt_at = :next_attempt, last_error = :last_error WHERE id = :id'
            );
            $update->execute([
                'status' => $status,
                'attempts' => $attempts,
                'next_attempt' => $status === 'queued' ? $nextAttempt : null,
                'last_error' => $error,
                'id' => $id,
            ]);
            error_log('Youth Unity Cup notification delivery failed (' . get_class($exception) . ').');
        }
    }

    private function markUnconfigured(int $id): void
    {
        $statement = $this->pdo->prepare(
            'UPDATE notification_outbox SET last_error = :reason WHERE id = :id AND status = \'queued\''
        );
        $statement->execute(['reason' => 'SMTP is not configured yet.', 'id' => $id]);
    }

    private function statusFor(int $id): string
    {
        $statement = $this->pdo->prepare('SELECT status FROM notification_outbox WHERE id = :id');
        $statement->execute(['id' => $id]);
        return (string) ($statement->fetchColumn() ?: 'queued');
    }
}
