<?php

declare(strict_types=1);

// Compatibility entry point for hosts that require the repository root as their
// document root. Production deployments should point the document root at /public.
require __DIR__ . '/public/index.php';
