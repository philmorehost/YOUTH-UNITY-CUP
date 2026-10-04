<?php

declare(strict_types=1);

namespace Yuc\Controllers;

use Yuc\Services\ProductImageService;

final class ProductImageController
{
    public function __construct(private ProductImageService $images = new ProductImageService())
    {
    }

    public function show(): void
    {
        $fileInput = $_GET['file'] ?? '';
        $filename = is_string($fileInput) ? $fileInput : '';
        $image = $this->images->findStoredImage($filename);
        if ($image === null) {
            http_response_code(404);
            header('Content-Type: text/plain; charset=UTF-8');
            header('X-Content-Type-Options: nosniff');
            echo 'Product image not found.';
            return;
        }

        $modified = filemtime($image['path']) ?: time();
        $etag = '"' . $filename . '-' . $image['size'] . '-' . $modified . '"';
        header('Content-Type: ' . $image['mime']);
        header('Content-Length: ' . $image['size']);
        header('Content-Disposition: inline; filename="product.webp"');
        header('Cache-Control: public, max-age=31536000, immutable');
        header('Last-Modified: ' . gmdate('D, d M Y H:i:s', $modified) . ' GMT');
        header('ETag: ' . $etag);
        header('X-Content-Type-Options: nosniff');
        header('Cross-Origin-Resource-Policy: same-site');
        if (trim((string) ($_SERVER['HTTP_IF_NONE_MATCH'] ?? '')) === $etag) {
            http_response_code(304);
            header_remove('Content-Length');
            return;
        }

        http_response_code(200);
        readfile($image['path']);
    }
}
