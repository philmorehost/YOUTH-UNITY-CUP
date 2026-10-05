<?php

declare(strict_types=1);

namespace Yuc\Services;

use RuntimeException;

/** SMTP transport that sends responsive HTML and plain-text notification alternatives. */
final class SmtpMailer
{
    /** @param array<string,mixed> $settings */
    public function __construct(private array $settings)
    {
    }

    public function isConfigured(): bool
    {
        return trim((string) ($this->settings['host'] ?? '')) !== ''
            && filter_var((string) ($this->settings['from_email'] ?? ''), FILTER_VALIDATE_EMAIL) !== false;
    }

    public function send(string $recipient, string $subject, string $body): void
    {
        $host = trim((string) ($this->settings['host'] ?? ''));
        $port = (int) ($this->settings['port'] ?? 587);
        $encryption = (string) ($this->settings['encryption'] ?? 'tls');
        $username = (string) ($this->settings['username'] ?? '');
        $password = (string) ($this->settings['password'] ?? '');
        $fromEmail = trim((string) ($this->settings['from_email'] ?? ''));
        $fromName = trim((string) ($this->settings['from_name'] ?? 'Youth Unity Cup'));

        if (!$this->isConfigured()) {
            throw new RuntimeException('SMTP is not configured.');
        }
        if (filter_var($recipient, FILTER_VALIDATE_EMAIL) === false) {
            throw new RuntimeException('The notification recipient address is not valid.');
        }
        if ($port < 1 || $port > 65535 || !in_array($encryption, ['tls', 'ssl', 'none'], true)) {
            throw new RuntimeException('The SMTP connection settings are not valid.');
        }
        if (!preg_match('/^[A-Za-z0-9.-]+$/', $host)) {
            throw new RuntimeException('The SMTP server host is not valid.');
        }

        $mimeMessage = $this->message($recipient, $fromEmail, $fromName, $subject, $body);
        $transport = $encryption === 'ssl' ? 'ssl://' : 'tcp://';
        $context = stream_context_create([
            'ssl' => [
                'verify_peer' => true,
                'verify_peer_name' => true,
                'peer_name' => $host,
                'allow_self_signed' => false,
            ],
        ]);
        $errorNumber = 0;
        $errorMessage = '';
        $socket = @stream_socket_client(
            $transport . $host . ':' . $port,
            $errorNumber,
            $errorMessage,
            8,
            STREAM_CLIENT_CONNECT,
            $context
        );

        if (!is_resource($socket)) {
            throw new RuntimeException('The SMTP server could not be reached.');
        }

        stream_set_timeout($socket, 10);
        try {
            $this->expect($socket, [220]);
            $helo = $this->heloName();
            $this->command($socket, 'EHLO ' . $helo, [250]);

            if ($encryption === 'tls') {
                $this->command($socket, 'STARTTLS', [220]);
                $cryptoEnabled = @stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
                if ($cryptoEnabled !== true) {
                    throw new RuntimeException('A secure TLS connection to the SMTP server could not be established.');
                }
                $this->command($socket, 'EHLO ' . $helo, [250]);
            }

            if ($username !== '') {
                if ($password === '') {
                    throw new RuntimeException('An SMTP password is required when SMTP authentication is enabled.');
                }
                $this->command($socket, 'AUTH LOGIN', [334]);
                $this->command($socket, base64_encode($username), [334]);
                $this->command($socket, base64_encode($password), [235]);
            }

            $this->command($socket, 'MAIL FROM:<' . $fromEmail . '>', [250]);
            $this->command($socket, 'RCPT TO:<' . $recipient . '>', [250, 251]);
            $this->command($socket, 'DATA', [354]);
            $this->writeAll($socket, $mimeMessage);
            $this->expect($socket, [250]);
            $this->command($socket, 'QUIT', [221]);
        } finally {
            fclose($socket);
        }
    }

    /** @param resource $socket @param list<int> $expected */
    private function command($socket, string $command, array $expected): void
    {
        if (str_contains($command, "\r") || str_contains($command, "\n")) {
            throw new RuntimeException('An invalid SMTP command was rejected.');
        }
        $this->writeAll($socket, $command . "\r\n");
        $this->expect($socket, $expected);
    }

    /** @param resource $socket @param list<int> $expected */
    private function expect($socket, array $expected): void
    {
        [$code] = $this->readReply($socket);
        if (!in_array($code, $expected, true)) {
            throw new RuntimeException('The SMTP server rejected a message (response ' . $code . ').');
        }
    }

    /** @param resource $socket @return array{int,string} */
    private function readReply($socket): array
    {
        $reply = '';
        $finalCode = 0;
        for ($lineNumber = 0; $lineNumber < 30; $lineNumber++) {
            $line = fgets($socket, 520);
            if ($line === false) {
                throw new RuntimeException('The SMTP server closed the connection unexpectedly.');
            }
            $reply .= $line;
            if (preg_match('/^(\d{3})([ -])/', $line, $matches) !== 1) {
                throw new RuntimeException('The SMTP server returned an invalid response.');
            }
            $finalCode = (int) $matches[1];
            if ($matches[2] === ' ') {
                return [$finalCode, $reply];
            }
        }

        throw new RuntimeException('The SMTP server returned an incomplete response.');
    }

    /** @param resource $socket */
    private function writeAll($socket, string $data): void
    {
        $length = strlen($data);
        $offset = 0;
        while ($offset < $length) {
            $written = fwrite($socket, substr($data, $offset));
            if ($written === false || $written === 0) {
                throw new RuntimeException('The SMTP connection could not send the message.');
            }
            $offset += $written;
        }
    }

    private function message(string $recipient, string $fromEmail, string $fromName, string $subject, string $body): string
    {
        $safeSubject = '=?UTF-8?B?' . base64_encode($subject) . '?=';
        $safeFromName = '=?UTF-8?B?' . base64_encode($fromName) . '?=';
        $domain = substr(strrchr($fromEmail, '@') ?: '@youthunitycup.local', 1);
        $messageId = bin2hex(random_bytes(16)) . '@' . preg_replace('/[^A-Za-z0-9.-]/', '', $domain);
        $boundary = '=_YUC_' . bin2hex(random_bytes(18));
        $normalizedBody = str_replace(["\r\n", "\r"], "\n", $body);
        $normalizedBody = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $normalizedBody) ?? '';
        $htmlBody = (new NotificationEmailTemplate())->render($subject, $normalizedBody);
        $plainPart = chunk_split(base64_encode($normalizedBody), 76, "\r\n");
        $htmlPart = chunk_split(base64_encode($htmlBody), 76, "\r\n");
        $headers = [
            'Date: ' . gmdate('D, d M Y H:i:s') . ' +0000',
            'From: ' . $safeFromName . ' <' . $fromEmail . '>',
            'To: <' . $recipient . '>',
            'Subject: ' . $safeSubject,
            'Message-ID: <' . $messageId . '>',
            'MIME-Version: 1.0',
            'Content-Type: multipart/alternative; boundary="' . $boundary . '"',
        ];
        $mimeBody = '--' . $boundary . "\r\n"
            . "Content-Type: text/plain; charset=UTF-8\r\n"
            . "Content-Transfer-Encoding: base64\r\n\r\n"
            . $plainPart . "\r\n"
            . '--' . $boundary . "\r\n"
            . "Content-Type: text/html; charset=UTF-8\r\n"
            . "Content-Transfer-Encoding: base64\r\n\r\n"
            . $htmlPart . "\r\n"
            . '--' . $boundary . '--';

        return implode("\r\n", $headers) . "\r\n\r\n" . $mimeBody . "\r\n.\r\n";
    }

    private function heloName(): string
    {
        $hostname = gethostname() ?: 'youthunitycup.local';
        $hostname = strtolower((string) preg_replace('/[^A-Za-z0-9.-]/', '-', $hostname));
        return trim($hostname, '.') !== '' ? trim($hostname, '.') : 'youthunitycup.local';
    }
}
