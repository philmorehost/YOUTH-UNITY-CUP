<?php

declare(strict_types=1);

namespace Yuc\Services;

use InvalidArgumentException;
use RuntimeException;

final class ProductImageService
{
    private const MAX_IMAGE_BYTES = 8_388_608;
    private const MAX_IMAGE_PIXELS = 12_000_000;

    private string $storageDirectory;

    public function __construct(?string $storageDirectory = null)
    {
        $this->storageDirectory = $storageDirectory ?? YUC_ROOT . '/storage/shop-products';
    }

    /**
     * Validate, decode and re-encode a user-uploaded image into an opaque WebP file.
     * Returns null when no file was selected.
     *
     * @param mixed $upload PHP's $_FILES['product_image'] value
     */
    public function storeUpload(mixed $upload): ?string
    {
        if ($upload === null) {
            return null;
        }
        if (!is_array($upload) || !is_scalar($upload['error'] ?? null)) {
            throw new InvalidArgumentException('The product image upload could not be read.');
        }

        $error = (int) $upload['error'];
        if ($error === UPLOAD_ERR_NO_FILE) {
            return null;
        }
        if ($error !== UPLOAD_ERR_OK) {
            throw new InvalidArgumentException($error === UPLOAD_ERR_INI_SIZE || $error === UPLOAD_ERR_FORM_SIZE
                ? 'The product image is too large for the server upload limit.'
                : 'The product image upload failed. Choose the file again.');
        }

        $temporaryPath = is_string($upload['tmp_name'] ?? null) ? $upload['tmp_name'] : '';
        if ($temporaryPath === '' || !is_uploaded_file($temporaryPath)) {
            throw new InvalidArgumentException('The product image is not a valid uploaded file.');
        }
        $size = filesize($temporaryPath);
        if (!is_int($size) || $size < 1 || $size > self::MAX_IMAGE_BYTES) {
            throw new InvalidArgumentException('Upload a JPEG, PNG or WebP product image no larger than 8 MB.');
        }

        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($temporaryPath);
        if (!is_string($mime) || !in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true)) {
            throw new InvalidArgumentException('Product images must be JPEG, PNG or WebP files.');
        }
        if (!function_exists('imagecreatefromstring') || !function_exists('imagewebp') || !function_exists('imagesx')) {
            throw new RuntimeException('Product image uploads require GD with WebP support enabled.');
        }

        $info = @getimagesize($temporaryPath);
        if (!is_array($info)
            || !is_int($info[0] ?? null)
            || !is_int($info[1] ?? null)
            || (string) ($info['mime'] ?? '') !== $mime
            || $info[0] < 1
            || $info[1] < 1
            || $info[0] > 12000
            || $info[1] > 12000
            || $info[0] * $info[1] > self::MAX_IMAGE_PIXELS) {
            throw new InvalidArgumentException('The product image is invalid or has dimensions that are too large.');
        }

        $bytes = @file_get_contents($temporaryPath);
        if (!is_string($bytes)) {
            throw new InvalidArgumentException('The product image could not be read.');
        }
        $source = @imagecreatefromstring($bytes);
        unset($bytes);
        if ($source === false) {
            throw new InvalidArgumentException('The product image could not be decoded.');
        }

        $width = imagesx($source);
        $height = imagesy($source);
        $scale = min(1, 1200 / $width, 1000 / $height);
        $targetWidth = max(1, (int) round($width * $scale));
        $targetHeight = max(1, (int) round($height * $scale));
        $optimized = @imagecreatetruecolor($targetWidth, $targetHeight);
        if ($optimized === false) {
            imagedestroy($source);
            throw new RuntimeException('The product image could not be optimized.');
        }
        imagealphablending($optimized, false);
        imagesavealpha($optimized, true);
        $transparent = imagecolorallocatealpha($optimized, 0, 0, 0, 127);
        imagefill($optimized, 0, 0, $transparent);
        imagecopyresampled($optimized, $source, 0, 0, 0, 0, $targetWidth, $targetHeight, $width, $height);
        imagedestroy($source);

        if (!$this->ensureStorageDirectory()) {
            imagedestroy($optimized);
            throw new RuntimeException('Protected product-image storage is not writable.');
        }

        $filename = bin2hex(random_bytes(16)) . '.webp';
        $path = $this->storageDirectory . DIRECTORY_SEPARATOR . $filename;
        $saved = @imagewebp($optimized, $path, 84);
        imagedestroy($optimized);
        if (!$saved || !is_file($path)) {
            @unlink($path);
            throw new RuntimeException('The product image could not be saved. Check protected storage permissions.');
        }
        @chmod($path, 0600);
        return $filename;
    }

    /** @return array{path:string,mime:string,size:int}|null */
    public function findStoredImage(string $filename): ?array
    {
        if (preg_match('/^[a-f0-9]{32}\.webp$/D', $filename) !== 1 || !is_dir($this->storageDirectory)) {
            return null;
        }
        $directory = realpath($this->storageDirectory);
        $path = realpath($this->storageDirectory . DIRECTORY_SEPARATOR . $filename);
        if ($directory === false
            || $path === false
            || !str_starts_with($path, rtrim($directory, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR)
            || !is_file($path)
            || !is_readable($path)) {
            return null;
        }
        $size = filesize($path);
        if (!is_int($size) || $size < 1) {
            return null;
        }
        return ['path' => $path, 'mime' => 'image/webp', 'size' => $size];
    }

    public function publicUrl(string $filename): string
    {
        return $this->findStoredImage($filename) !== null
            ? '/shop/product-image?file=' . rawurlencode($filename)
            : '';
    }

    public function delete(string $filename): void
    {
        $image = $this->findStoredImage($filename);
        if ($image !== null) {
            @unlink($image['path']);
        }
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
}
