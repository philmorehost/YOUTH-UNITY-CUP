<?php

declare(strict_types=1);

namespace Yuc\Controllers;

use PDO;
use Throwable;
use Yuc\Core\View;
use Yuc\Services\AuthService;
use Yuc\Services\NotificationService;
use Yuc\Services\TournamentService;

final class AdminController
{
    private AuthService $auth;
    private NotificationService $notifications;

    /** @param array<string,mixed> $config */
    public function __construct(private PDO $pdo, private array $config)
    {
        $this->auth = new AuthService($pdo, $config);
        $this->notifications = new NotificationService($pdo, $config);
    }

    public function showLogin(): void
    {
        if (yuc_current_admin() !== null) {
            yuc_redirect('/admin');
        }

        View::render('login', [
            'title' => 'Admin sign in · Youth Unity Cup',
            'bodyClass' => 'login-page',
            'topNote' => 'SECURE ADMIN ACCESS',
            'flash' => yuc_take_flash(),
        ]);
    }

    public function login(): void
    {
        if (!yuc_verify_csrf()) {
            yuc_flash('error', 'Your sign-in session expired. Refresh this page and try again.');
            yuc_redirect('/admin/login');
        }

        $identifier = trim((string) ($_POST['identifier'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');
        $result = $this->auth->authenticate($identifier, $password, yuc_client_ip());

        if (!$result['ok']) {
            yuc_flash('error', $result['message']);
            yuc_redirect('/admin/login');
        }

        yuc_flash('success', 'Welcome back to the control panel.');
        yuc_redirect('/admin');
    }

    public function showPasswordResetRequest(): void
    {
        View::render('password-reset-request', [
            'title' => 'Reset admin password · Youth Unity Cup',
            'bodyClass' => 'login-page',
            'topNote' => 'ACCOUNT RECOVERY',
            'flash' => yuc_take_flash(),
        ]);
    }

    public function requestPasswordReset(): void
    {
        if (!yuc_verify_csrf()) {
            yuc_flash('error', 'Your request session expired. Refresh and try again.');
            yuc_redirect('/admin/forgot-password');
        }
        try {
            $this->auth->requestPasswordReset(
                trim((string) ($_POST['email'] ?? '')),
                $this->passwordResetBaseUrl(),
                yuc_client_ip()
            );
        } catch (Throwable $exception) {
            error_log('Youth Unity Cup password reset request failed (' . get_class($exception) . ').');
        }
        yuc_flash('success', 'If an active administrator account uses that email, a one-time reset link has been queued. Check your inbox.');
        yuc_redirect('/admin/forgot-password');
    }

    public function showPasswordReset(): void
    {
        $token = trim((string) ($_GET['token'] ?? ''));
        View::render('password-reset', [
            'title' => 'Choose a new password · Youth Unity Cup',
            'bodyClass' => 'login-page',
            'topNote' => 'ACCOUNT RECOVERY',
            'token' => preg_match('/^[a-f0-9]{64}$/i', $token) === 1 ? $token : '',
            'flash' => yuc_take_flash(),
        ]);
    }

    public function resetPassword(): void
    {
        if (!yuc_verify_csrf()) {
            yuc_flash('error', 'Your reset session expired. Open the link from your email again.');
            yuc_redirect('/admin/forgot-password');
        }
        $token = trim((string) ($_POST['token'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');
        $confirmation = (string) ($_POST['password_confirmation'] ?? '');
        if (strlen($password) < 12 || strlen($password) > 1024 || $password !== $confirmation) {
            yuc_flash('error', 'Use a password of at least 12 characters and make sure both fields match.');
            yuc_redirect('/admin/reset-password?token=' . rawurlencode($token));
        }
        try {
            if (!$this->auth->completePasswordReset($token, $password)) {
                yuc_flash('error', 'This reset link is invalid, expired, or already used. Request a new link to continue.');
                yuc_redirect('/admin/forgot-password');
            }
            yuc_flash('success', 'Your password has been changed. You can now sign in with the new password.');
            yuc_redirect('/admin/login');
        } catch (Throwable $exception) {
            error_log('Youth Unity Cup password reset could not be completed (' . get_class($exception) . ').');
            yuc_flash('error', 'The password could not be changed right now. Request a new reset link and try again.');
            yuc_redirect('/admin/forgot-password');
        }
    }

    public function dashboard(): void
    {
        $admin = yuc_current_admin();
        if ($admin === null) {
            yuc_redirect('/admin/login');
        }

        $userStatement = $this->pdo->prepare(
            'SELECT id, full_name, email, username, role, status, last_login_at, created_at '
            . 'FROM users WHERE id = :id LIMIT 1'
        );
        $userStatement->execute(['id' => (int) $admin['id']]);
        $user = $userStatement->fetch();
        if (!is_array($user) || $user['status'] !== 'active') {
            unset($_SESSION['admin_user']);
            yuc_flash('error', 'This administrator account is not active.');
            yuc_redirect('/admin/login');
        }

        $historyStatement = $this->pdo->query(
            'SELECT identifier, ip_address, outcome, details, occurred_at '
            . 'FROM login_history ORDER BY id DESC LIMIT 10'
        );
        $history = $historyStatement->fetchAll();

        $outboxStatement = $this->pdo->query(
            'SELECT status, COUNT(*) AS total FROM notification_outbox GROUP BY status'
        );
        $outboxCounts = ['queued' => 0, 'sent' => 0, 'failed' => 0];
        foreach ($outboxStatement->fetchAll() as $row) {
            $outboxCounts[(string) $row['status']] = (int) $row['total'];
        }

        $messageStatement = $this->pdo->query(
            'SELECT event_key, recipient_email, status, created_at, last_error '
            . 'FROM notification_outbox ORDER BY id DESC LIMIT 5'
        );
        $recentNotifications = $messageStatement->fetchAll();

        $tournamentCounts = (new TournamentService($this->pdo, $this->config))->counts();

        View::render('admin', [
            'title' => 'Admin dashboard · Youth Unity Cup',
            'bodyClass' => 'admin-page',
            'topNote' => 'ADMIN CONTROL ROOM',
            'admin' => $user,
            'emailConfigured' => $this->notifications->isConfigured(),
            'tournamentCounts' => $tournamentCounts,
            'outboxCounts' => $outboxCounts,
            'recentNotifications' => $recentNotifications,
            'history' => $history,
            'flash' => yuc_take_flash(),
        ]);
    }

    public function sendTestEmail(): void
    {
        $admin = yuc_current_admin();
        if ($admin === null) {
            yuc_redirect('/admin/login');
        }
        if (!yuc_verify_csrf()) {
            yuc_flash('error', 'Your dashboard session expired. Refresh the page and try again.');
            yuc_redirect('/admin');
        }

        try {
            $this->notifications->notifyActivity(
                'system.email_test',
                'Youth Unity Cup email delivery test',
                'This is a test notification from the Youth Unity Cup administration system. If this arrived in your inbox, SMTP delivery is working.',
                [
                    'user_email' => (string) $admin['email'],
                    'user_id' => (int) $admin['id'],
                    'notify_admin' => true,
                    'ip_address' => yuc_client_ip(),
                    'context' => ['requested_by' => (string) ($admin['username'] ?? $admin['email'])],
                ]
            );
            yuc_flash('success', 'The test notification was added to the email outbox. Check the status table below.');
        } catch (Throwable $exception) {
            error_log('Youth Unity Cup test email could not be queued (' . get_class($exception) . ').');
            yuc_flash('error', 'The test notification could not be queued. Check the database and try again.');
        }

        yuc_redirect('/admin');
    }

    public function logout(): void
    {
        if (!yuc_verify_csrf()) {
            yuc_flash('error', 'Your dashboard session expired.');
            yuc_redirect('/admin/login');
        }

        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $parameters = session_get_cookie_params();
            $cookieOptions = [
                'expires' => time() - 42000,
                'path' => $parameters['path'] ?? '/',
                'secure' => (bool) ($parameters['secure'] ?? false),
                'httponly' => true,
                'samesite' => 'Lax',
            ];
            if (!empty($parameters['domain'])) {
                $cookieOptions['domain'] = $parameters['domain'];
            }
            setcookie(session_name(), '', $cookieOptions);
        }
        session_destroy();
        yuc_redirect('/admin/login');
    }

    private function passwordResetBaseUrl(): string
    {
        $licenseDomain = strtolower(trim((string) ($this->config['license']['domain'] ?? '')));
        $rawHost = trim((string) ($_SERVER['HTTP_HOST'] ?? ''));
        if ($licenseDomain === '' || $rawHost === '' || strlen($rawHost) > 260 || preg_match('/^[A-Za-z0-9.\-:\[\]]+$/', $rawHost) !== 1) {
            return '';
        }
        $parsed = parse_url('http://' . $rawHost);
        if (!is_array($parsed) || !isset($parsed['host'])) {
            return '';
        }
        $host = strtolower(trim((string) $parsed['host'], '[]'));
        if ($host !== $licenseDomain) {
            return '';
        }
        $localHost = in_array($host, ['localhost', '127.0.0.1', '::1'], true);
        $https = (!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off')
            || strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https'
            || !$localHost;
        $scheme = $https ? 'https' : 'http';
        $displayHost = str_contains($host, ':') ? '[' . $host . ']' : $host;
        $port = isset($parsed['port']) ? (int) $parsed['port'] : null;
        if ($port !== null && !(($https && $port === 443) || (!$https && $port === 80))) {
            $displayHost .= ':' . $port;
        }
        return $scheme . '://' . $displayHost;
    }
}
