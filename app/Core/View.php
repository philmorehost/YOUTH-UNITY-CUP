<?php

declare(strict_types=1);

namespace Yuc\Core;

use RuntimeException;

final class View
{
    /** @param array<string,mixed> $data */
    public static function render(string $template, array $data = []): void
    {
        if (!preg_match('/^[a-z0-9-]+$/', $template)) {
            throw new RuntimeException('Invalid view name.');
        }

        $viewFile = YUC_ROOT . '/views/' . $template . '.php';
        if (!is_file($viewFile)) {
            throw new RuntimeException('Requested view was not found.');
        }

        extract($data, EXTR_SKIP);
        $title = (string) ($data['title'] ?? 'Youth Unity Cup');
        $bodyClass = (string) ($data['bodyClass'] ?? '');
        $topNote = (string) ($data['topNote'] ?? 'TOURNAMENT ADMINISTRATION');
        $description = (string) ($data['description'] ?? 'Youth Unity Cup administration and installation portal.');

        ob_start();
        require $viewFile;
        $content = (string) ob_get_clean();

        require YUC_ROOT . '/views/layout.php';
    }
}
