<?php
/** @var string $title */
/** @var string $bodyClass */
/** @var string $topNote */
/** @var string $description */
/** @var string $content */
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#0b2545">
    <meta name="description" content="<?= yuc_e($description) ?>">
    <link rel="icon" href="/favicon.svg" type="image/svg+xml">
    <title><?= yuc_e($title) ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Barlow+Condensed:wght@600;700;800&family=DM+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/assets/app.css">
</head>
<body class="<?= yuc_e($bodyClass) ?>">
    <div class="site-frame">
        <header class="topbar">
            <a class="brand" href="/" aria-label="Youth Unity Cup home">
                <span class="brand-mark" aria-hidden="true">Y</span>
                <span class="brand-name">Youth Unity <b>Cup</b><small>2026 · MUSHIN, LAGOS</small></span>
            </a>
            <div class="topbar-note"><span class="status-dot" aria-hidden="true"></span><?= yuc_e($topNote) ?></div>
        </header>
        <main class="main-wrap">
            <?= $content ?>
        </main>
        <footer class="site-footer">
            <div class="footer-brand"><span class="footer-mark">Y</span><span>YOUTH UNITY CUP</span></div>
            <span>Built for the game. Run with care.</span>
            <span class="footer-footnote"><?= str_contains($bodyClass, 'public-data-page') ? 'Official tournament information' : 'Secure administration portal' ?></span>
        </footer>
    </div>
    <script src="/assets/app.js" defer></script>
</body>
</html>
