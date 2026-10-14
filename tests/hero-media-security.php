<?php

declare(strict_types=1);

use Yuc\Services\HeroService;

require dirname(__DIR__) . '/app/bootstrap.php';

function expectHero(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$videoId = 'dQw4w9WgXcQ';
expectHero(HeroService::youtubeId('https://www.youtube.com/watch?v=' . $videoId . '&feature=share') === $videoId, 'A standard YouTube watch link was rejected.');
expectHero(HeroService::youtubeId('https://youtu.be/' . $videoId . '?si=example') === $videoId, 'A short YouTube link was rejected.');
expectHero(HeroService::youtubeId('https://youtube.com/shorts/' . $videoId) === $videoId, 'A YouTube Shorts link was rejected.');
expectHero(HeroService::youtubeId('https://www.youtube-nocookie.com/embed/' . $videoId) === $videoId, 'A privacy-enhanced YouTube embed link was rejected.');
expectHero(HeroService::youtubeId('http://www.youtube.com/watch?v=' . $videoId) === null, 'An insecure YouTube link was accepted.');
expectHero(HeroService::youtubeId('https://youtube.attacker.example/watch?v=' . $videoId) === null, 'An untrusted YouTube hostname was accepted.');
expectHero(HeroService::youtubeId('https://youtube.com.evil.test/watch?v=' . $videoId) === null, 'A deceptive YouTube hostname was accepted.');
expectHero(HeroService::youtubeId('https://user@youtube.com/watch?v=' . $videoId) === null, 'A YouTube link containing userinfo was accepted.');
expectHero(HeroService::youtubeId('https://youtube.com:8443/watch?v=' . $videoId) === null, 'A nonstandard YouTube port was accepted.');
expectHero(HeroService::youtubeId('https://youtu.be/not-a-video-id') === null, 'A malformed YouTube video id was accepted.');

fwrite(STDOUT, "Homepage hero media validation checks passed.\n");
