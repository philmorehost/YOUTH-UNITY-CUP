<?php

declare(strict_types=1);

function yuc_e(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
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
