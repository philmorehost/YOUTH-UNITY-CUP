<?php

declare(strict_types=1);

namespace Yuc\Services;

use RuntimeException;

final class NotificationEmailTemplate
{
    public function render(string $subject, string $body): string
    {
        $body = str_replace(["\r\n", "\r"], "\n", trim($body));
        $body = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $body) ?? '';
        $escape = static fn (string $value): string => htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $subjectHtml = $escape($subject !== '' ? $subject : 'Tournament notification');

        $paragraphs = preg_split('/\n{2,}/', $body) ?: [];
        if ($paragraphs === []) {
            $paragraphs = ['You have a new Youth Unity Cup update.'];
        }
        $bodyHtml = '';
        foreach ($paragraphs as $paragraph) {
            $safeParagraph = nl2br($escape(trim($paragraph)), false);
            $bodyHtml .= '<p style="margin:0 0 16px;color:#34495e;font-family:Arial,Helvetica,sans-serif;font-size:15px;line-height:1.7;">'
                . $safeParagraph . '</p>';
        }

        $firstLine = '';
        foreach (preg_split('/\n/', $body) ?: [] as $line) {
            if (trim($line) !== '') {
                $firstLine = trim($line);
                break;
            }
        }
        if ($firstLine === '') {
            $firstLine = 'A Youth Unity Cup tournament update is ready.';
        }
        $preheader = $escape(mb_substr($firstLine, 0, 160));
        $year = gmdate('Y');
        $templatePath = YUC_ROOT . '/views/emails/notification.php';
        if (!is_file($templatePath) || !is_readable($templatePath)) {
            throw new RuntimeException('The Youth Unity Cup email template is unavailable.');
        }

        ob_start();
        require $templatePath;
        return (string) ob_get_clean();
    }
}
