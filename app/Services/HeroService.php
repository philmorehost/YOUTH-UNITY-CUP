<?php

declare(strict_types=1);

namespace Yuc\Services;

use InvalidArgumentException;
use PDO;
use RuntimeException;
use Throwable;

final class HeroService
{
    private const SETTINGS = [
        'homepage_hero_type' => 'default',
        'homepage_hero_media' => '',
        'homepage_hero_youtube_id' => '',
        'homepage_hero_image_alt' => 'Youth Unity Cup community football',
    ];
    private const MAX_IMAGE_BYTES = 10_485_760;
    private const MAX_VIDEO_BYTES = 26_214_400;
    private const MAX_IMAGE_PIXELS = 12_000_000;

    private string $storageDirectory;

    public function __construct(private PDO $pdo, ?string $storageDirectory = null)
    {
        $this->storageDirectory = $storageDirectory ?? YUC_ROOT . '/storage/hero';
    }

    /** @return array{type:string,media_file:string,youtube_id:string,image_alt:string,media_url:string,youtube_url:string,embed_url:string} */
    public function settings(): array
    {
        $keys = array_keys(self::SETTINGS);
        $placeholders = implode(',', array_fill(0, count($keys), '?'));
        $statement = $this->pdo->prepare('SELECT setting_key, setting_value FROM system_settings WHERE setting_key IN (' . $placeholders . ')');
        $statement->execute($keys);
        $values = self::SETTINGS;
        foreach ($statement->fetchAll() as $row) {
            $key = (string) ($row['setting_key'] ?? '');
            if (array_key_exists($key, $values) && is_scalar($row['setting_value'] ?? null)) {
                $values[$key] = (string) $row['setting_value'];
            }
        }

        $type = in_array($values['homepage_hero_type'], ['default', 'image', 'youtube', 'video'], true)
            ? $values['homepage_hero_type']
            : 'default';
        $mediaFile = $this->validatedMediaName($values['homepage_hero_media']);
        $youtubeId = preg_match('/^[A-Za-z0-9_-]{11}$/D', $values['homepage_hero_youtube_id']) === 1
            ? $values['homepage_hero_youtube_id']
            : '';
        $alt = trim($values['homepage_hero_image_alt']);
        if ($alt === '' || mb_strlen($alt) > 160) {
            $alt = self::SETTINGS['homepage_hero_image_alt'];
        }
        if (($type === 'image' && ($mediaFile === '' || preg_match('/\.(?:webp|jpg|png)$/D', $mediaFile) !== 1 || $this->findStoredMedia($mediaFile) === null))
            || ($type === 'video' && ($mediaFile === '' || preg_match('/\.(?:mp4|webm)$/D', $mediaFile) !== 1 || $this->findStoredMedia($mediaFile) === null))
            || ($type === 'youtube' && $youtubeId === '')) {
            $type = 'default';
            $mediaFile = '';
            $youtubeId = '';
        }

        return [
            'type' => $type,
            'media_file' => $mediaFile,
            'youtube_id' => $youtubeId,
            'image_alt' => $alt,
            'media_url' => $mediaFile !== '' ? '/hero-media?file=' . rawurlencode($mediaFile) : '',
            'youtube_url' => $youtubeId !== '' ? 'https://youtu.be/' . $youtubeId : '',
            'embed_url' => $youtubeId !== ''
                ? 'https://www.youtube-nocookie.com/embed/' . $youtubeId . '?autoplay=1&mute=1&loop=1&playlist=' . $youtubeId . '&controls=0&modestbranding=1&rel=0&playsinline=1'
                : '',
        ];
    }

    /**
     * @param array<string,mixed> $input
     * @param array<string,mixed> $files
     */
    public function save(array $input, array $files, int $adminId, string $ipAddress = 'unknown'): void
    {
        if ($adminId < 1) {
            throw new InvalidArgumentException('An active administrator is required to update the homepage hero.');
        }

        $typeInput = $input['hero_type'] ?? '';
        $type = is_string($typeInput) ? trim($typeInput) : '';
        if (!in_array($type, ['default', 'image', 'youtube', 'video'], true)) {
            throw new InvalidArgumentException('Choose a valid homepage hero type.');
        }

        $current = $this->settings();
        $altInput = $input['hero_image_alt'] ?? '';
        $alt = is_string($altInput) ? trim($altInput) : '';
        $alt = preg_replace('/[\x00-\x1F\x7F]/u', ' ', $alt) ?? '';
        $alt = trim(preg_replace('/\s+/u', ' ', $alt) ?? $alt);
        if ($alt === '') {
            $alt = self::SETTINGS['homepage_hero_image_alt'];
        }
        if (mb_strlen($alt) > 160) {
            throw new InvalidArgumentException('Image description must be 160 characters or fewer.');
        }

        $newFile = '';
        $newYoutubeId = '';
        $createdFile = '';
        try {
            if ($type === 'image') {
                $upload = $this->optionalUpload($files['hero_image_file'] ?? null, 'homepage hero image');
                if ($upload !== null) {
                    $newFile = $this->storeImage($upload);
                    $createdFile = $newFile;
                } elseif ($current['type'] === 'image' && $current['media_file'] !== '') {
                    $newFile = $current['media_file'];
                } else {
                    throw new InvalidArgumentException('Choose a hero image to upload.');
                }
            } elseif ($type === 'video') {
                $upload = $this->optionalUpload($files['hero_video_file'] ?? null, 'homepage hero video');
                if ($upload !== null) {
                    $newFile = $this->storeVideo($upload);
                    $createdFile = $newFile;
                } elseif ($current['type'] === 'video' && $current['media_file'] !== '') {
                    $newFile = $current['media_file'];
                } else {
                    throw new InvalidArgumentException('Choose a hero video to upload.');
                }
            } elseif ($type === 'youtube') {
                $urlInput = $input['hero_youtube_url'] ?? '';
                $url = is_string($urlInput) ? trim($urlInput) : '';
                if ($url === '' && $current['type'] === 'youtube') {
                    $newYoutubeId = $current['youtube_id'];
                } else {
                    $newYoutubeId = self::youtubeId($url) ?? '';
                }
                if ($newYoutubeId === '') {
                    throw new InvalidArgumentException('Enter a valid public YouTube video link (youtube.com or youtu.be).');
                }
            }

            $values = [
                'homepage_hero_type' => $type,
                'homepage_hero_media' => $newFile,
                'homepage_hero_youtube_id' => $newYoutubeId,
                'homepage_hero_image_alt' => $alt,
            ];
            $this->pdo->beginTransaction();
            try {
                $save = $this->pdo->prepare(
                    'INSERT INTO system_settings (setting_key, setting_value, updated_at) '
                    . 'VALUES (:setting_key, :setting_value, UTC_TIMESTAMP()) '
                    . 'ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value), updated_at=UTC_TIMESTAMP()'
                );
                foreach ($values as $key => $value) {
                    $save->execute(['setting_key' => $key, 'setting_value' => $value]);
                }
                $audit = $this->pdo->prepare(
                    'INSERT INTO audit_logs (user_id, event_key, description, ip_address, context_json, created_at) '
                    . 'VALUES (:user_id, :event_key, :description, :ip_address, :context_json, UTC_TIMESTAMP())'
                );
                $audit->execute([
                    'user_id' => $adminId,
                    'event_key' => 'system.homepage_hero_updated',
                    'description' => 'Updated the homepage hero to use ' . $type . ' content.',
                    'ip_address' => filter_var($ipAddress, FILTER_VALIDATE_IP) !== false ? substr($ipAddress, 0, 45) : 'unknown',
                    'context_json' => json_encode(['type' => $type], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
                ]);
                $this->pdo->commit();
            } catch (Throwable $exception) {
                if ($this->pdo->inTransaction()) {
                    $this->pdo->rollBack();
                }
                throw $exception;
            }
        } catch (Throwable $exception) {
            if ($createdFile !== '') {
                $this->deleteMedia($createdFile);
            }
            throw $exception;
        }

        if ($current['media_file'] !== '' && $current['media_file'] !== $newFile) {
            $this->deleteMedia($current['media_file']);
        }
    }

    /** @return array{path:string,mime:string,size:int}|null */
    public function findStoredMedia(string $filename): ?array
    {
        $filename = $this->validatedMediaName($filename);
        if ($filename === '') {
            return null;
        }
        $path = $this->storageDirectory . DIRECTORY_SEPARATOR . $filename;
        if (!is_file($path) || !is_readable($path)) {
            return null;
        }
        $mime = match (pathinfo($filename, PATHINFO_EXTENSION)) {
            'webp' => 'image/webp',
            'jpg' => 'image/jpeg',
            'png' => 'image/png',
            'mp4' => 'video/mp4',
            'webm' => 'video/webm',
            default => '',
        };
        $size = filesize($path);
        if ($mime === '' || !is_int($size) || $size < 1) {
            return null;
        }
        return ['path' => $path, 'mime' => $mime, 'size' => $size];
    }

    public static function youtubeId(string $url): ?string
    {
        if ($url === '' || strlen($url) > 500 || filter_var($url, FILTER_VALIDATE_URL) === false) {
            return null;
        }
        $parts = parse_url($url);
        if (!is_array($parts)
            || strtolower((string) ($parts['scheme'] ?? '')) !== 'https'
            || isset($parts['user'])
            || isset($parts['pass'])
            || (isset($parts['port']) && (int) $parts['port'] !== 443)) {
            return null;
        }
        $host = strtolower((string) ($parts['host'] ?? ''));
        $path = (string) ($parts['path'] ?? '');
        $id = '';
        if (in_array($host, ['youtube.com', 'www.youtube.com', 'm.youtube.com'], true)) {
            if ($path === '/watch') {
                parse_str((string) ($parts['query'] ?? ''), $query);
                $id = is_string($query['v'] ?? null) ? $query['v'] : '';
            } elseif (preg_match('~^/(?:embed|shorts)/([A-Za-z0-9_-]{11})/?$~D', $path, $matches) === 1) {
                $id = $matches[1];
            }
        } elseif ($host === 'youtu.be') {
            $id = trim($path, '/');
        } elseif ($host === 'www.youtube-nocookie.com' && preg_match('~^/embed/([A-Za-z0-9_-]{11})/?$~D', $path, $matches) === 1) {
            $id = $matches[1];
        }

        return preg_match('/^[A-Za-z0-9_-]{11}$/D', $id) === 1 ? $id : null;
    }

    /** @param mixed $upload @return array{tmp_name:string,size:int,mime:string}|null */
    private function optionalUpload(mixed $upload, string $label): ?array
    {
        if ($upload === null) {
            return null;
        }
        if (!is_array($upload) || !is_scalar($upload['error'] ?? null)) {
            throw new InvalidArgumentException('The ' . $label . ' upload could not be read.');
        }
        $error = (int) $upload['error'];
        if ($error === UPLOAD_ERR_NO_FILE) {
            return null;
        }
        if ($error !== UPLOAD_ERR_OK) {
            throw new InvalidArgumentException($error === UPLOAD_ERR_INI_SIZE || $error === UPLOAD_ERR_FORM_SIZE
                ? 'The ' . $label . ' is too large for the server upload limit.'
                : 'The ' . $label . ' upload failed. Please choose the file again.');
        }
        $temporaryPath = is_string($upload['tmp_name'] ?? null) ? $upload['tmp_name'] : '';
        if ($temporaryPath === '' || !is_uploaded_file($temporaryPath)) {
            throw new InvalidArgumentException('The ' . $label . ' is not a valid uploaded file.');
        }
        $size = filesize($temporaryPath);
        if (!is_int($size) || $size < 1) {
            throw new InvalidArgumentException('The ' . $label . ' is empty or unreadable.');
        }
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($temporaryPath);
        if (!is_string($mime)) {
            throw new InvalidArgumentException('The ' . $label . ' file type could not be checked.');
        }
        return ['tmp_name' => $temporaryPath, 'size' => $size, 'mime' => $mime];
    }

    /** @param array{tmp_name:string,size:int,mime:string} $upload */
    private function storeImage(array $upload): string
    {
        if ($upload['size'] > self::MAX_IMAGE_BYTES
            || !in_array($upload['mime'], ['image/jpeg', 'image/png', 'image/webp'], true)
            || !function_exists('imagecreatefromstring')
            || !function_exists('imagesx')
            || !function_exists('imagewebp')) {
            throw new InvalidArgumentException('Upload a JPEG, PNG, or WebP hero image under 10 MB. The server must have GD with WebP support enabled.');
        }
        $info = @getimagesize($upload['tmp_name']);
        if (!is_array($info)
            || !is_int($info[0] ?? null)
            || !is_int($info[1] ?? null)
            || (string) ($info['mime'] ?? '') !== $upload['mime']
            || $info[0] < 1 || $info[1] < 1
            || $info[0] > 12000 || $info[1] > 12000
            || $info[0] * $info[1] > self::MAX_IMAGE_PIXELS) {
            throw new InvalidArgumentException('The hero image is invalid or has dimensions that are too large.');
        }
        $sourceBytes = file_get_contents($upload['tmp_name']);
        if (!is_string($sourceBytes)) {
            throw new InvalidArgumentException('The hero image could not be read.');
        }
        $source = @imagecreatefromstring($sourceBytes);
        unset($sourceBytes);
        if ($source === false) {
            throw new InvalidArgumentException('The hero image could not be decoded.');
        }

        $width = imagesx($source);
        $height = imagesy($source);
        $scale = min(1, 2400 / $width, 1400 / $height);
        $targetWidth = max(1, (int) round($width * $scale));
        $targetHeight = max(1, (int) round($height * $scale));
        $optimized = imagecreatetruecolor($targetWidth, $targetHeight);
        if ($optimized === false) {
            imagedestroy($source);
            throw new RuntimeException('The hero image could not be optimized.');
        }
        imagealphablending($optimized, false);
        imagesavealpha($optimized, true);
        $transparent = imagecolorallocatealpha($optimized, 0, 0, 0, 127);
        imagefill($optimized, 0, 0, $transparent);
        imagecopyresampled($optimized, $source, 0, 0, 0, 0, $targetWidth, $targetHeight, $width, $height);
        imagedestroy($source);

        if (!$this->ensureStorageDirectory()) {
            imagedestroy($optimized);
            throw new RuntimeException('Protected hero-media storage is not writable.');
        }
        $filename = bin2hex(random_bytes(16)) . '.webp';
        $path = $this->storageDirectory . DIRECTORY_SEPARATOR . $filename;
        $saved = @imagewebp($optimized, $path, 82);
        imagedestroy($optimized);
        if (!$saved || !is_file($path)) {
            @unlink($path);
            throw new RuntimeException('The hero image could not be saved as an optimized WebP file.');
        }
        @chmod($path, 0600);
        return $filename;
    }

    /** @param array{tmp_name:string,size:int,mime:string} $upload */
    private function storeVideo(array $upload): string
    {
        if ($upload['size'] > self::MAX_VIDEO_BYTES) {
            throw new InvalidArgumentException('Upload a hero video under 25 MB so it starts quickly on mobile data.');
        }
        if ($upload['mime'] === 'video/mp4') {
            $extension = 'mp4';
            if (!$this->mp4HasFastStart($upload['tmp_name'], $upload['size'])) {
                throw new InvalidArgumentException('This MP4 is not optimized for streaming. Export it with fast-start or web optimization enabled, then upload it again.');
            }
        } elseif ($upload['mime'] === 'video/webm') {
            $extension = 'webm';
            $handle = @fopen($upload['tmp_name'], 'rb');
            $signature = is_resource($handle) ? fread($handle, 4) : false;
            if (is_resource($handle)) {
                fclose($handle);
            }
            if ($signature !== "\x1A\x45\xDF\xA3") {
                throw new InvalidArgumentException('The WebM video file is not valid.');
            }
        } else {
            throw new InvalidArgumentException('Upload an MP4 or WebM homepage hero video.');
        }

        if (!$this->ensureStorageDirectory()) {
            throw new RuntimeException('Protected hero-media storage is not writable.');
        }
        $filename = bin2hex(random_bytes(16)) . '.' . $extension;
        $path = $this->storageDirectory . DIRECTORY_SEPARATOR . $filename;
        if (!move_uploaded_file($upload['tmp_name'], $path)) {
            throw new RuntimeException('The hero video could not be saved in protected storage.');
        }
        @chmod($path, 0600);
        return $filename;
    }

    private function mp4HasFastStart(string $path, int $fileSize): bool
    {
        $handle = @fopen($path, 'rb');
        if (!is_resource($handle)) {
            return false;
        }
        $offset = 0;
        $moovSeen = false;
        $boxes = 0;
        while ($offset + 8 <= $fileSize && $boxes < 1000) {
            if (fseek($handle, $offset) !== 0) {
                fclose($handle);
                return false;
            }
            $header = fread($handle, 8);
            if (!is_string($header) || strlen($header) !== 8) {
                fclose($handle);
                return false;
            }
            $boxLength = unpack('Nlength', substr($header, 0, 4));
            $boxSize = (int) ($boxLength['length'] ?? 0);
            $boxType = substr($header, 4, 4);
            $headerSize = 8;
            if ($boxSize === 1) {
                $extended = fread($handle, 8);
                if (!is_string($extended) || strlen($extended) !== 8) {
                    fclose($handle);
                    return false;
                }
                $parts = unpack('Nhigh/Nlow', $extended);
                $high = (int) ($parts['high'] ?? 0);
                $low = (int) ($parts['low'] ?? 0);
                if ($high > 0) {
                    fclose($handle);
                    return false;
                }
                $boxSize = $low;
                $headerSize = 16;
            } elseif ($boxSize === 0) {
                $boxSize = $fileSize - $offset;
            }
            if ($boxSize < $headerSize || $boxSize > $fileSize - $offset) {
                fclose($handle);
                return false;
            }
            if ($boxType === 'moov') {
                $moovSeen = true;
            } elseif ($boxType === 'mdat') {
                fclose($handle);
                return $moovSeen;
            }
            $offset += $boxSize;
            $boxes++;
        }
        fclose($handle);
        return false;
    }

    private function ensureStorageDirectory(): bool
    {
        if (!is_dir($this->storageDirectory)
            && !@mkdir($this->storageDirectory, 0700, true)
            && !is_dir($this->storageDirectory)) {
            return false;
        }
        @chmod($this->storageDirectory, 0700);
        return is_writable($this->storageDirectory);
    }

    private function validatedMediaName(string $filename): string
    {
        return preg_match('/^[a-f0-9]{32}\.(?:webp|jpg|png|mp4|webm)$/D', $filename) === 1 ? $filename : '';
    }

    private function deleteMedia(string $filename): void
    {
        $filename = $this->validatedMediaName($filename);
        if ($filename !== '') {
            @unlink($this->storageDirectory . DIRECTORY_SEPARATOR . $filename);
        }
    }
}
