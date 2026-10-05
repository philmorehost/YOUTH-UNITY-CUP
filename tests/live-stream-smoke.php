<?php

declare(strict_types=1);

use Yuc\Core\View;
use Yuc\Services\LiveStreamService;

require dirname(__DIR__) . '/app/bootstrap.php';

function expectLiveStream(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$video = LiveStreamService::inspectUrl('https://youtu.be/dQw4w9WgXcQ?share=1');
expectLiveStream($video['platform'] === 'youtube', 'YouTube video link was not identified.');
expectLiveStream($video['url'] === 'https://www.youtube.com/watch?v=dQw4w9WgXcQ', 'YouTube links should be canonicalized before being stored.');
expectLiveStream(str_contains($video['embed_url'], 'youtube-nocookie.com/embed/dQw4w9WgXcQ?autoplay=1&mute=1&playsinline=1'), 'YouTube player URL must request muted inline autoplay.');

$liveVideo = LiveStreamService::inspectUrl('https://www.youtube.com/live/dQw4w9WgXcQ');
expectLiveStream($liveVideo['embed_url'] !== '', 'YouTube live video IDs should be embeddable.');

$channel = LiveStreamService::inspectUrl('https://www.youtube.com/channel/UC1234567890123456789012/live');
expectLiveStream(str_contains($channel['embed_url'], 'embed/live_stream?channel=UC1234567890123456789012'), 'YouTube channel LIVE links should embed the current channel broadcast.');

$handle = LiveStreamService::inspectUrl('https://www.youtube.com/@youthunitycup/live');
expectLiveStream($handle['platform'] === 'youtube' && $handle['embed_url'] === '', 'YouTube handle links should safely fall back to an external watch link.');

$tiktok = LiveStreamService::inspectUrl('https://www.tiktok.com/@youthunitycup/live?lang=en');
expectLiveStream($tiktok['platform'] === 'tiktok' && $tiktok['url'] === 'https://www.tiktok.com/@youthunitycup/live', 'TikTok LIVE links should be normalized and supported.');
expectLiveStream($tiktok['embed_url'] === '', 'TikTok LIVE must not be represented as an unsupported iframe embed.');

foreach ([
    'http://www.youtube.com/live/dQw4w9WgXcQ',
    'https://youtube.example.com/watch?v=dQw4w9WgXcQ',
    'https://www.tiktok.com/@youthunitycup/video/1234567890123456789',
    'javascript://youtube.com/live/dQw4w9WgXcQ',
] as $invalidUrl) {
    $rejected = false;
    try {
        LiveStreamService::inspectUrl($invalidUrl);
    } catch (InvalidArgumentException) {
        $rejected = true;
    }
    expectLiveStream($rejected, 'Unsupported or unsafe live-stream URL was accepted: ' . $invalidUrl);
}

function renderPublicHomeForLiveStream(array $liveStream): string
{
    ob_start();
    View::render('public-home', [
        'title' => 'Youth Unity Cup · Official site',
        'topNote' => 'OFFICIAL TOURNAMENT INFORMATION',
        'bodyClass' => 'public-data-page',
        'description' => 'Official Youth Unity Cup tournament information.',
        'siteTitle' => 'Youth Unity Cup',
        'heroSettings' => ['type' => 'default'],
        'liveStream' => $liveStream,
        'siteMode' => 'production',
        'teamCount' => 16,
        'playerCount' => 176,
        'fixtureCount' => 24,
        'venueCount' => 8,
        'appTimezone' => 'Africa/Lagos',
    ]);
    return (string) ob_get_clean();
}

$youtubeHome = renderPublicHomeForLiveStream([
    'enabled' => true,
    'title' => 'Youth Unity Cup Final',
    'url' => $video['url'],
    'platform' => 'youtube',
    'embed_url' => $video['embed_url'],
    'can_embed' => true,
]);
expectLiveStream(str_contains($youtubeHome, 'class="button button-live"') && str_contains($youtubeHome, 'href="#live-stream"'), 'The landing hero is missing the Watch live button.');
expectLiveStream(str_contains($youtubeHome, 'id="live-stream"') && str_contains($youtubeHome, 'loading="eager"'), 'The public landing page should load the live player immediately.');
expectLiveStream(str_contains($youtubeHome, 'autoplay=1&amp;mute=1') && str_contains($youtubeHome, 'allow="autoplay;'), 'The YouTube embed does not request browser-permitted muted autoplay.');

$tiktokHome = renderPublicHomeForLiveStream([
    'enabled' => true,
    'title' => 'Youth Unity Cup Final',
    'url' => $tiktok['url'],
    'platform' => 'tiktok',
    'embed_url' => '',
    'can_embed' => false,
]);
expectLiveStream(str_contains($tiktokHome, 'Watch on TikTok') && str_contains($tiktokHome, 'target="_blank" rel="noopener noreferrer"'), 'TikTok LIVE should be offered as a safe external watch link.');
expectLiveStream(str_contains($tiktokHome, 'does not provide an official embeddable player for TikTok LIVE'), 'The page should explain TikTok LIVE embed limitations.');

$hiddenHome = renderPublicHomeForLiveStream(['enabled' => false, 'title' => '', 'url' => '', 'platform' => '', 'embed_url' => '', 'can_embed' => false]);
expectLiveStream(!str_contains($hiddenHome, 'class="button button-live"'), 'The Watch live button should stay hidden when no broadcast is enabled.');

$adminTemplate = file_get_contents(dirname(__DIR__) . '/views/admin-manage.php');
$routes = file_get_contents(dirname(__DIR__) . '/public/index.php');
expectLiveStream(is_string($adminTemplate) && str_contains($adminTemplate, 'action="/admin/live-stream/save"') && str_contains($adminTemplate, 'name="url"'), 'Admin is missing the live-stream configuration form.');
expectLiveStream(is_string($routes) && str_contains($routes, "'live-stream'"), 'Live-stream admin routes are not registered.');

ob_start();
View::render('admin-manage', [
    'title' => 'Watch live · Youth Unity Cup Admin',
    'topNote' => 'ADMIN CONTROL ROOM',
    'bodyClass' => 'admin-page',
    'admin' => ['id' => 1, 'email' => 'admin@example.test', 'username' => 'admin'],
    'resource' => 'live-stream',
    'rows' => [],
    'formValues' => [],
    'liveStreamSettings' => ['enabled' => true, 'title' => 'Youth Unity Cup Final', 'url' => $video['url'], 'platform' => 'youtube', 'embed_url' => $video['embed_url'], 'can_embed' => true],
    'orderProducts' => [],
    'teams' => [],
    'venues' => [],
    'settings' => [],
    'mail' => [],
    'payHubConfigured' => true,
    'appTimezone' => 'Africa/Lagos',
    'flash' => null,
    'auditTotal' => 0,
    'auditPage' => 1,
    'auditPages' => 1,
    'auditSearch' => '',
    'auditCategory' => '',
]);
$adminHtml = (string) ob_get_clean();
expectLiveStream(str_contains($adminHtml, 'Watch live') && str_contains($adminHtml, 'name="enabled"') && str_contains($adminHtml, $video['url']), 'The admin live-stream form did not render configured broadcast settings.');
expectLiveStream(str_contains($adminHtml, 'starts muted') && str_contains($adminHtml, 'TikTok does not provide an official embeddable LIVE player'), 'The admin page does not explain the player/autoplay behavior.');

fwrite(STDOUT, "Live-stream validation, admin controls, and responsive landing-page playback checks passed." . PHP_EOL);
