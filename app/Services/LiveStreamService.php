<?php

declare(strict_types=1);

namespace Yuc\Services;

use InvalidArgumentException;
use PDO;
use Throwable;

final class LiveStreamService
{
    public function __construct(private PDO $pdo)
    {
    }

    /** @return array{enabled:bool,title:string,url:string,platform:string,embed_url:string,can_embed:bool} */
    public function settings(): array
    {
        $stored = [
            'live_stream_enabled' => '0',
            'live_stream_title' => 'Youth Unity Cup Live',
            'live_stream_url' => '',
        ];
        $rows = $this->pdo->query(
            "SELECT setting_key, setting_value FROM system_settings WHERE setting_key IN ('live_stream_enabled','live_stream_title','live_stream_url')"
        )->fetchAll();
        foreach ($rows as $row) {
            $key = (string) ($row['setting_key'] ?? '');
            if (array_key_exists($key, $stored)) {
                $stored[$key] = (string) ($row['setting_value'] ?? '');
            }
        }

        $resolved = null;
        if (trim($stored['live_stream_url']) !== '') {
            try {
                $resolved = self::inspectUrl($stored['live_stream_url']);
            } catch (InvalidArgumentException) {
                // Invalid legacy values fail closed: no untrusted URL is rendered publicly.
            }
        }

        return [
            'enabled' => $stored['live_stream_enabled'] === '1' && $resolved !== null,
            'title' => trim($stored['live_stream_title']) !== '' ? $stored['live_stream_title'] : 'Youth Unity Cup Live',
            'url' => $resolved['url'] ?? '',
            'platform' => $resolved['platform'] ?? '',
            'embed_url' => $resolved['embed_url'] ?? '',
            'can_embed' => $resolved !== null && $resolved['embed_url'] !== '',
        ];
    }

    /** @param array<string,mixed> $input */
    public function save(array $input, int $adminId): void
    {
        $enabledInput = $input['enabled'] ?? '0';
        $enabled = is_scalar($enabledInput) && (string) $enabledInput === '1';
        $titleInput = $input['title'] ?? '';
        $title = is_scalar($titleInput) ? trim((string) $titleInput) : '';
        if ($title === '') {
            $title = 'Youth Unity Cup Live';
        }
        if (mb_strlen($title) > 120) {
            throw new InvalidArgumentException('The live-stream title must be no longer than 120 characters.');
        }

        $urlInput = $input['url'] ?? '';
        $url = is_scalar($urlInput) ? trim((string) $urlInput) : '';
        if (strlen($url) > 500) {
            throw new InvalidArgumentException('The live-stream URL must be no longer than 500 characters.');
        }
        if ($enabled && $url === '') {
            throw new InvalidArgumentException('Add a TikTok LIVE or YouTube live link before enabling the Watch live button.');
        }

        $resolved = $url !== '' ? self::inspectUrl($url) : null;
        $savedUrl = $resolved['url'] ?? '';
        $this->pdo->beginTransaction();
        try {
            $statement = $this->pdo->prepare(
                'INSERT INTO system_settings (setting_key, setting_value, updated_at) VALUES (:setting_key, :setting_value, UTC_TIMESTAMP()) '
                . 'ON DUPLICATE KEY UPDATE setting_value=:updated_value, updated_at=UTC_TIMESTAMP()'
            );
            foreach ([
                'live_stream_enabled' => $enabled ? '1' : '0',
                'live_stream_title' => $title,
                'live_stream_url' => $savedUrl,
            ] as $key => $value) {
                $statement->execute(['setting_key' => $key, 'setting_value' => $value, 'updated_value' => $value]);
            }

            $platform = $resolved['platform'] ?? 'off';
            $description = 'Updated live-stream settings (' . $platform . ', ' . ($enabled ? 'enabled' : 'disabled') . ')';
            $audit = $this->pdo->prepare(
                'INSERT INTO audit_logs (user_id, event_key, description, ip_address, created_at) '
                . 'VALUES (:user_id, :event_key, :description, :ip_address, UTC_TIMESTAMP())'
            );
            $audit->execute([
                'user_id' => $adminId,
                'event_key' => 'system.live_stream_updated',
                'description' => $description,
                'ip_address' => function_exists('yuc_client_ip') ? yuc_client_ip() : 'unknown',
            ]);
            $this->pdo->commit();
        } catch (Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $exception;
        }
    }

    /**
     * Resolve only supported HTTPS destinations. YouTube video/channel URLs can
     * autoplay in an embed; TikTok LIVE and YouTube handle URLs remain safe
     * external watch links when no supported embedded player URL is available.
     *
     * @return array{url:string,platform:string,embed_url:string}
     */
    public static function inspectUrl(string $url): array
    {
        $url = trim($url);
        if ($url === '' || strlen($url) > 500) {
            throw new InvalidArgumentException('Enter a TikTok LIVE or YouTube live-stream URL.');
        }
        $parts = parse_url($url);
        if (!is_array($parts)
            || strtolower((string) ($parts['scheme'] ?? '')) !== 'https'
            || !isset($parts['host'])
            || isset($parts['user'])
            || isset($parts['pass'])
            || (isset($parts['port']) && (int) $parts['port'] !== 443)) {
            throw new InvalidArgumentException('Live-stream links must use HTTPS and a supported TikTok or YouTube domain.');
        }

        $host = strtolower(rtrim((string) $parts['host'], '.'));
        $path = (string) ($parts['path'] ?? '/');
        if (in_array($host, ['tiktok.com', 'www.tiktok.com', 'm.tiktok.com'], true)) {
            if (preg_match('~^/@([A-Za-z0-9._]{2,24})/live/?$~D', $path, $matches) !== 1) {
                throw new InvalidArgumentException('Use a TikTok LIVE profile link in the form https://www.tiktok.com/@username/live.');
            }
            $canonicalUrl = 'https://www.tiktok.com/@' . $matches[1] . '/live';
            return ['url' => $canonicalUrl, 'platform' => 'tiktok', 'embed_url' => ''];
        }

        if (!in_array($host, ['youtube.com', 'www.youtube.com', 'm.youtube.com', 'youtu.be'], true)) {
            throw new InvalidArgumentException('Use a supported YouTube or TikTok LIVE link.');
        }

        $videoId = '';
        $channelId = '';
        if ($host === 'youtu.be') {
            $candidate = trim($path, '/');
            if (preg_match('/^[A-Za-z0-9_-]{11}$/D', $candidate) === 1) {
                $videoId = $candidate;
            }
        } elseif ($path === '/watch') {
            $query = [];
            parse_str((string) ($parts['query'] ?? ''), $query);
            $candidate = is_string($query['v'] ?? null) ? $query['v'] : '';
            if (preg_match('/^[A-Za-z0-9_-]{11}$/D', $candidate) === 1) {
                $videoId = $candidate;
            }
        } elseif (preg_match('~^/(?:live|embed|shorts)/([A-Za-z0-9_-]{11})/?$~D', $path, $matches) === 1) {
            $videoId = $matches[1];
        } elseif (preg_match('~^/channel/(UC[A-Za-z0-9_-]{22})/live/?$~D', $path, $matches) === 1) {
            $channelId = $matches[1];
        } elseif (preg_match('~^/@[A-Za-z0-9._-]{3,30}/live/?$~D', $path) === 1) {
            $canonicalUrl = 'https://www.youtube.com' . rtrim($path, '/');
            return ['url' => $canonicalUrl, 'platform' => 'youtube', 'embed_url' => ''];
        }

        if ($videoId !== '') {
            $canonicalUrl = 'https://www.youtube.com/watch?v=' . rawurlencode($videoId);
            $embedUrl = 'https://www.youtube-nocookie.com/embed/' . rawurlencode($videoId)
                . '?autoplay=1&mute=1&playsinline=1&rel=0';
            return ['url' => $canonicalUrl, 'platform' => 'youtube', 'embed_url' => $embedUrl];
        }
        if ($channelId !== '') {
            $canonicalUrl = 'https://www.youtube.com/channel/' . rawurlencode($channelId) . '/live';
            $embedUrl = 'https://www.youtube-nocookie.com/embed/live_stream?channel=' . rawurlencode($channelId)
                . '&autoplay=1&mute=1&playsinline=1&rel=0';
            return ['url' => $canonicalUrl, 'platform' => 'youtube', 'embed_url' => $embedUrl];
        }

        throw new InvalidArgumentException('Use a YouTube live/video URL with its video ID, or a YouTube channel-ID /live URL.');
    }
}
