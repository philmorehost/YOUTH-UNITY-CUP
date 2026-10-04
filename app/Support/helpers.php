<?php

declare(strict_types=1);

function yuc_e(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function yuc_current_year(?string $timezone = null): string
{
    $timezone = trim((string) ($timezone ?? 'Africa/Lagos'));
    if (!in_array($timezone, DateTimeZone::listIdentifiers(), true)) {
        $timezone = 'Africa/Lagos';
    }

    try {
        return (new DateTimeImmutable('now', new DateTimeZone($timezone)))->format('Y');
    } catch (Throwable) {
        return gmdate('Y');
    }
}

function yuc_player_avatar(int $variant = 1): string
{
    $palettes = [
        ['background' => '#e5f2e8', 'skin' => '#8d5b3d', 'hair' => '#20262b', 'shirt' => '#12365a'],
        ['background' => '#e8eef9', 'skin' => '#6d432f', 'hair' => '#171c24', 'shirt' => '#a4cb38'],
        ['background' => '#f2ebdc', 'skin' => '#a56c49', 'hair' => '#2e211d', 'shirt' => '#1d5870'],
        ['background' => '#e7edf0', 'skin' => '#774a35', 'hair' => '#191b1d', 'shirt' => '#678b36'],
        ['background' => '#f0e9ef', 'skin' => '#915a3a', 'hair' => '#271e1a', 'shirt' => '#29456b'],
        ['background' => '#e4f0f1', 'skin' => '#593b31', 'hair' => '#111820', 'shirt' => '#bfda4b'],
        ['background' => '#f3eadf', 'skin' => '#b67b53', 'hair' => '#34251f', 'shirt' => '#17604b'],
        ['background' => '#e8eaf4', 'skin' => '#704530', 'hair' => '#202024', 'shirt' => '#824a44'],
    ];
    $palette = $palettes[max(1, min(8, $variant)) - 1];

    return '<svg class="player-portrait-art" viewBox="0 0 80 80" role="img" aria-label="Illustrated player headshot" xmlns="http://www.w3.org/2000/svg">'
        . '<circle cx="40" cy="40" r="40" fill="' . $palette['background'] . '"/>'
        . '<path d="M8 80c2-16 14-25 32-25s30 9 32 25" fill="' . $palette['shirt'] . '"/>'
        . '<path d="M32 51h16v11c-4 5-12 5-16 0z" fill="' . $palette['skin'] . '"/>'
        . '<ellipse cx="40" cy="35" rx="18" ry="21" fill="' . $palette['skin'] . '"/>'
        . '<path d="M22 34c-2-17 7-26 19-26 12 0 19 9 17 25-3-5-5-9-7-14-8 7-17 9-29 8z" fill="' . $palette['hair'] . '"/>'
        . '<path d="M30 36h5m10 0h5" stroke="#2b2522" stroke-width="2" stroke-linecap="round"/>'
        . '<path d="M36 45c2 2 6 2 8 0" fill="none" stroke="#663f31" stroke-width="1.8" stroke-linecap="round"/>'
        . '</svg>';
}

function yuc_redirect(string $location, int $status = 303): void
{
    header('Location: ' . $location, true, $status);
    exit;
}

function yuc_csrf_token(): string
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        return '';
    }

    if (empty($_SESSION['_csrf_token'])) {
        $_SESSION['_csrf_token'] = bin2hex(random_bytes(32));
    }

    return (string) $_SESSION['_csrf_token'];
}

function yuc_csrf_field(): string
{
    return '<input type="hidden" name="_csrf" value="' . yuc_e(yuc_csrf_token()) . '">';
}

function yuc_verify_csrf(): bool
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        return false;
    }

    $token = (string) ($_POST['_csrf'] ?? '');
    $stored = (string) ($_SESSION['_csrf_token'] ?? '');

    return $token !== '' && $stored !== '' && hash_equals($stored, $token);
}

function yuc_flash(string $type, string $message): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        $_SESSION['_flash'] = ['type' => $type, 'message' => $message];
    }
}

/** @return array{type:string,message:string}|null */
function yuc_take_flash(): ?array
{
    if (session_status() !== PHP_SESSION_ACTIVE || !isset($_SESSION['_flash'])) {
        return null;
    }

    $flash = $_SESSION['_flash'];
    unset($_SESSION['_flash']);

    if (!is_array($flash) || !isset($flash['type'], $flash['message'])) {
        return null;
    }

    return ['type' => (string) $flash['type'], 'message' => (string) $flash['message']];
}

function yuc_client_ip(): string
{
    // Do not trust X-Forwarded-For: it is client-controlled unless the
    // deployment explicitly normalizes it at a trusted reverse proxy.
    $address = trim((string) ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
    if (filter_var($address, FILTER_VALIDATE_IP) === false) {
        return 'unknown';
    }

    return substr($address, 0, 45);
}

function yuc_current_path(): string
{
    $uri = (string) ($_SERVER['REQUEST_URI'] ?? '/');
    $path = parse_url($uri, PHP_URL_PATH);
    if (!is_string($path) || $path === '') {
        return '/';
    }

    $normalized = '/' . trim($path, '/');
    return $normalized === '/' ? '/' : $normalized;
}

/** @return array<string,mixed>|null */
function yuc_current_admin(): ?array
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        return null;
    }

    $admin = $_SESSION['admin_user'] ?? null;
    return is_array($admin) && isset($admin['id'], $admin['email']) ? $admin : null;
}
