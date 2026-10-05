<?php

declare(strict_types=1);

use Yuc\Services\NotificationEmailTemplate;
use Yuc\Services\SmtpMailer;

require dirname(__DIR__) . '/app/bootstrap.php';

function expectNotificationEmail(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$subject = 'Matchday update <goal> & team news';
$body = "A great result for the Youth Unity Cup.\n\nEvent: fixture.completed\nScorer: <script>alert('no')</script>\nSource IP: 192.0.2.10";
$html = (new NotificationEmailTemplate())->render($subject, $body);
expectNotificationEmail(str_contains($html, 'YOUTH UNITY CUP') && str_contains($html, 'TOURNAMENT NOTICE'), 'Sports notification template branding is missing.');
expectNotificationEmail(str_contains($html, 'name="viewport"') && str_contains($html, '@media only screen and (max-width: 620px)'), 'The email template is missing responsive viewport and mobile styles.');
expectNotificationEmail(str_contains($html, 'Matchday update &lt;goal&gt; &amp; team news'), 'Email subject HTML was not escaped.');
expectNotificationEmail(str_contains($html, '&lt;script&gt;alert(&#039;no&#039;)&lt;/script&gt;') && !str_contains($html, '<script>alert'), 'Email body HTML was not escaped.');
expectNotificationEmail(str_contains($html, 'background:#0a213a') && str_contains($html, '#c7e747'), 'The modern navy-and-lime sports design tokens are missing.');
expectNotificationEmail(str_contains($html, 'Scorer:') && str_contains($html, 'Source IP:'), 'Notification details were not carried into the HTML email.');

$mailerSource = file_get_contents(dirname(__DIR__) . '/app/Services/SmtpMailer.php');
expectNotificationEmail(is_string($mailerSource) && str_contains($mailerSource, 'multipart/alternative') && str_contains($mailerSource, 'text/html; charset=UTF-8') && str_contains($mailerSource, 'NotificationEmailTemplate'), 'SMTP does not send an HTML version alongside the plain-text email.');

$method = new ReflectionMethod(SmtpMailer::class, 'message');
$mimeMessage = $method->invoke(new SmtpMailer([]), 'fan@example.test', 'admin@example.test', 'Youth Unity Cup', $subject, $body);
expectNotificationEmail(is_string($mimeMessage) && str_contains($mimeMessage, 'multipart/alternative') && str_contains($mimeMessage, 'Content-Transfer-Encoding: base64'), 'SMTP MIME message did not contain encoded alternative parts.');

fwrite(STDOUT, "Responsive sports notification email rendering and multipart SMTP checks passed." . PHP_EOL);
